<?php
declare(strict_types=1);

const GATE1_EVIDENCE_TOKEN_HASH = '__TOKEN_HASH__';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$submittedToken = trim((string)($_SERVER['HTTP_X_GATE1_EVIDENCE_TOKEN'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $submittedToken === '' || !hash_equals(GATE1_EVIDENCE_TOKEN_HASH, hash('sha256', $submittedToken))) {
    http_response_code(404);
    echo json_encode(['status' => 'not_found']);
    exit;
}

require_once dirname(__DIR__, 2) . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/startpartner/_domain.php';

$response = [
    'status' => 'error',
    'gate' => 'startpartner_gate1_staging_write',
    'environment' => be_app_env_value(),
];
$httpStatus = 500;
$domainPdo = null;
$migrationPdo = null;
$candidateId = null;
$marker = trim((string)($_SERVER['HTTP_X_GATE1_EVIDENCE_MARKER'] ?? ''));
$runReference = trim((string)($_SERVER['HTTP_X_GATE1_EVIDENCE_RUN'] ?? ''));
$lockAcquired = false;
$cleanupErrors = [];

function gate1_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Unsafe table name.');
    }
    $statement = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $statement->execute($params);
    return (int)$statement->fetchColumn();
}

function gate1_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() === 1;
}

function gate1_apply_sql(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Migration file is missing or empty: ' . basename($path));
    }
    $statement = $pdo->prepare($sql);
    $statement->execute();
    do {
        if ($statement->columnCount() > 0) {
            $statement->fetchAll(PDO::FETCH_ASSOC);
        }
    } while ($statement->nextRowset());
}

function gate1_db_from_config(): PDO
{
    $config = be_get_config();
    $db = $config['db'] ?? null;
    if (!is_array($db)) {
        throw new RuntimeException('Database config is missing.');
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        trim((string)($db['host'] ?? '')),
        (int)($db['port'] ?? 3306),
        trim((string)($db['name'] ?? '')),
        trim((string)($db['charset'] ?? 'utf8mb4'))
    );
    return new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
}

try {
    if (be_app_env_value() !== 'staging') {
        throw new RuntimeException('Evidence endpoint is staging-only.');
    }
    if (!preg_match('/^GATE1_SYNTHETIC_194_[A-F0-9]{16}$/', $marker)) {
        throw new InvalidArgumentException('Synthetic marker is invalid.');
    }
    if (!preg_match('/^[0-9]{6,20}$/', $runReference)) {
        throw new InvalidArgumentException('Run reference is invalid.');
    }

    $migrationPdo = gate1_db_from_config();
    $lockValue = $migrationPdo->query("SELECT GET_LOCK('be_startpartner_gate1_evidence_194', 10)")->fetchColumn();
    if ((int)$lockValue !== 1) {
        throw new RuntimeException('Could not acquire the evidence lock.');
    }
    $lockAcquired = true;

    $candidateTablesBefore = [
        'startpartner_candidates' => gate1_table_exists($migrationPdo, 'startpartner_candidates'),
        'startpartner_candidate_contacts' => gate1_table_exists($migrationPdo, 'startpartner_candidate_contacts'),
        'startpartner_candidate_events' => gate1_table_exists($migrationPdo, 'startpartner_candidate_events'),
    ];

    gate1_apply_sql($migrationPdo, dirname(__DIR__, 2) . '/sql/007_runtime_schema_reconciliation.sql');
    gate1_apply_sql($migrationPdo, dirname(__DIR__, 2) . '/sql/008_startpartner_candidates.sql');

    $domainPdo = be_db();
    be_startpartner_require_schema($domainPdo);

    $lockedTables = [
        'organizers',
        'submissions',
        'subscriptions',
        'publication_entitlements',
        'publication_consumptions',
    ];
    $lockedBefore = [];
    foreach ($lockedTables as $table) {
        $lockedBefore[$table] = gate1_count($domainPdo, $table);
    }

    $syntheticBefore = [
        'candidates' => gate1_count($domainPdo, 'startpartner_candidates', 'organization_name = :marker', ['marker' => $marker]),
        'control_cases' => gate1_count(
            $domainPdo,
            'control_cases',
            "source_system = 'startpartner_candidate' AND object_title = :marker",
            ['marker' => $marker]
        ),
    ];
    if ($syntheticBefore['candidates'] !== 0 || $syntheticBefore['control_cases'] !== 0) {
        throw new RuntimeException('Synthetic before-state is not empty.');
    }

    $retentionReviewAt = (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    $input = [
        'source' => 'targeted_outreach',
        'source_reference' => 'gate1-staging-evidence-' . $runReference,
        'organization_name' => $marker,
        'contacts' => [
            [
                'contact_name' => 'Synthetic Primary',
                'contact_role' => 'Evidence',
                'email' => strtolower($marker) . '@example.org',
                'is_primary' => true,
            ],
            [
                'contact_name' => 'Synthetic Secondary',
                'contact_role' => 'Evidence',
                'email' => 'second-' . strtolower($marker) . '@example.org',
                'is_primary' => false,
            ],
        ],
        'website_url' => 'https://example.org/' . strtolower($marker),
        'description_text' => 'Synthetic Gate 1 staging evidence only.',
        'desired_content_scope' => 'both',
        'form_version' => 'gate1-staging-evidence-v1',
        'retention_review_at' => $retentionReviewAt,
        'idempotency_key' => 'gate1-staging-' . strtolower($marker) . '-' . $runReference,
    ];

    $created = be_startpartner_create_candidate($domainPdo, $input, 'operator', 'gate1-staging-evidence');
    $candidateId = (string)($created['candidate']['id'] ?? '');
    if (!$created['created'] || $candidateId === '') {
        throw new RuntimeException('Synthetic candidate was not created.');
    }

    $replay = be_startpartner_create_candidate($domainPdo, $input, 'operator', 'gate1-staging-evidence');
    if ($replay['created'] || !$replay['idempotent_replay'] || (string)$replay['candidate']['id'] !== $candidateId) {
        throw new RuntimeException('Identical idempotent replay was not stable.');
    }

    $conflictInput = $input;
    $conflictInput['description_text'] = 'Conflicting synthetic payload.';
    $conflictRejected = false;
    try {
        be_startpartner_create_candidate($domainPdo, $conflictInput, 'operator', 'gate1-staging-evidence');
    } catch (DomainException) {
        $conflictRejected = true;
    }
    if (!$conflictRejected) {
        throw new RuntimeException('Conflicting idempotency replay was not rejected.');
    }

    $duplicateInput = $input;
    $duplicateInput['idempotency_key'] .= '-duplicate';
    $duplicate = be_startpartner_create_candidate($domainPdo, $duplicateInput, 'operator', 'gate1-staging-evidence');
    if (!$duplicate['duplicate_identity'] || (string)$duplicate['candidate']['id'] !== $candidateId) {
        throw new RuntimeException('Duplicate identity was not resolved to the existing candidate.');
    }

    be_startpartner_triage_candidate($domainPdo, $candidateId, 'qualified', null, 'gate1-staging-evidence');
    $triaged = be_startpartner_triage_candidate(
        $domainPdo,
        $candidateId,
        'waitlisted',
        'Synthetic capacity evidence.',
        'gate1-staging-evidence'
    );

    $caseStatement = $domainPdo->prepare(
        "SELECT id, state, decision_ready, source_system, source_reference
         FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $caseStatement->execute(['candidate_id' => $candidateId]);
    $case = $caseStatement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($case) || $case['state'] !== 'parked' || (int)$case['decision_ready'] !== 0) {
        throw new RuntimeException('Control-Center projection read-back is invalid.');
    }

    $lockedAfterWrite = [];
    foreach ($lockedTables as $table) {
        $lockedAfterWrite[$table] = gate1_count($domainPdo, $table);
        if ($lockedAfterWrite[$table] !== $lockedBefore[$table]) {
            throw new RuntimeException('Forbidden side effect detected in ' . $table . '.');
        }
    }

    $eventCount = gate1_count($domainPdo, 'startpartner_candidate_events', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]);
    $contactCount = gate1_count($domainPdo, 'startpartner_candidate_contacts', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]);
    $candidateCount = gate1_count($domainPdo, 'startpartner_candidates', 'id = :candidate_id', ['candidate_id' => $candidateId]);
    if ($candidateCount !== 1 || $contactCount !== 2 || $eventCount !== 4) {
        throw new RuntimeException('Candidate read-back cardinality is invalid.');
    }

    $migrationStatement = $domainPdo->query(
        "SELECT migration_key FROM app_schema_migrations
         WHERE migration_key IN ('007_runtime_schema_reconciliation', '008_startpartner_candidates')
         ORDER BY migration_key"
    );
    $migrationKeys = array_map(
        static fn(array $row): string => (string)$row['migration_key'],
        $migrationStatement->fetchAll(PDO::FETCH_ASSOC)
    );
    if ($migrationKeys !== ['007_runtime_schema_reconciliation', '008_startpartner_candidates']) {
        throw new RuntimeException('Migration read-back is incomplete.');
    }

    $response = [
        'status' => 'ok',
        'gate' => 'startpartner_gate1_staging_write',
        'environment' => 'staging',
        'run_reference' => $runReference,
        'marker' => $marker,
        'candidate_id' => $candidateId,
        'candidate_tables_before' => $candidateTablesBefore,
        'synthetic_before' => $syntheticBefore,
        'migrations' => $migrationKeys,
        'created' => $created['created'],
        'idempotent_replay' => $replay['idempotent_replay'],
        'conflicting_replay_rejected' => $conflictRejected,
        'duplicate_identity' => $duplicate['duplicate_identity'],
        'final_candidate_status' => (string)$triaged['status'],
        'candidate_rows' => $candidateCount,
        'contact_rows' => $contactCount,
        'candidate_event_rows' => $eventCount,
        'control_case' => [
            'state' => (string)$case['state'],
            'decision_ready' => (int)$case['decision_ready'],
            'source_system' => (string)$case['source_system'],
            'source_reference_matches_candidate' => hash_equals($candidateId, (string)$case['source_reference']),
        ],
        'locked_table_counts_before' => $lockedBefore,
        'locked_table_counts_after_write' => $lockedAfterWrite,
        'forbidden_external_effects' => [
            'mail' => 'not_called_by_domain_path',
            'stripe' => 'not_called_by_domain_path',
            'publication' => 'not_called_by_domain_path',
        ],
    ];
    $httpStatus = 200;
} catch (Throwable $error) {
    $response['status'] = 'error';
    $response['error_class'] = get_class($error);
    $response['error_message'] = $error->getMessage();
} finally {
    if ($domainPdo instanceof PDO) {
        try {
            $candidateIds = [];
            if (is_string($candidateId) && $candidateId !== '') {
                $candidateIds[] = $candidateId;
            }
            if ($marker !== '') {
                $findCandidates = $domainPdo->prepare('SELECT id FROM startpartner_candidates WHERE organization_name = :marker');
                $findCandidates->execute(['marker' => $marker]);
                foreach ($findCandidates->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $candidateIds[] = (string)$row['id'];
                }
            }
            $candidateIds = array_values(array_unique(array_filter($candidateIds)));
            foreach ($candidateIds as $cleanupCandidateId) {
                $deleteCases = $domainPdo->prepare(
                    "DELETE FROM control_cases WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
                );
                $deleteCases->execute(['candidate_id' => $cleanupCandidateId]);
                $deleteCandidate = $domainPdo->prepare('DELETE FROM startpartner_candidates WHERE id = :candidate_id');
                $deleteCandidate->execute(['candidate_id' => $cleanupCandidateId]);
            }

            if ($marker !== '' && gate1_table_exists($domainPdo, 'startpartner_candidates')) {
                $response['cleanup'] = [
                    'candidate_rows' => gate1_count($domainPdo, 'startpartner_candidates', 'organization_name = :marker', ['marker' => $marker]),
                    'control_case_rows' => gate1_count(
                        $domainPdo,
                        'control_cases',
                        "source_system = 'startpartner_candidate' AND object_title = :marker",
                        ['marker' => $marker]
                    ),
                ];
                if ($response['cleanup']['candidate_rows'] !== 0 || $response['cleanup']['control_case_rows'] !== 0) {
                    $cleanupErrors[] = 'Synthetic rows remain after cleanup.';
                }
            }
        } catch (Throwable $cleanupError) {
            $cleanupErrors[] = get_class($cleanupError) . ': ' . $cleanupError->getMessage();
        }
    }

    if ($lockAcquired && $migrationPdo instanceof PDO) {
        try {
            $migrationPdo->query("SELECT RELEASE_LOCK('be_startpartner_gate1_evidence_194')")->fetchColumn();
        } catch (Throwable $lockError) {
            $cleanupErrors[] = get_class($lockError) . ': ' . $lockError->getMessage();
        }
    }

    $response['cleanup_errors'] = $cleanupErrors;
    if ($cleanupErrors !== []) {
        $response['status'] = 'error';
        $httpStatus = 500;
    }
    $response['self_deleted'] = @unlink(__FILE__);
}

http_response_code($httpStatus);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
