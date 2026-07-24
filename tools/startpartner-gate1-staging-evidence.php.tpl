<?php
declare(strict_types=1);

const GATE1_EVIDENCE_TOKEN_HASH = '__TOKEN_HASH__';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$submittedToken = trim((string)($_SERVER['HTTP_X_GATE1_EVIDENCE_TOKEN'] ?? ''));
if (
    $submittedToken === '' ||
    !hash_equals(GATE1_EVIDENCE_TOKEN_HASH, hash('sha256', $submittedToken))
) {
    http_response_code(404);
    echo json_encode(['status' => 'not_found']);
    exit;
}

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__) . '/startpartner/_domain.php';

if (be_app_env_value() !== 'staging') {
    http_response_code(404);
    echo json_encode(['status' => 'not_found']);
    exit;
}

$action = trim((string)($_GET['action'] ?? ''));
$marker = trim((string)($_SERVER['HTTP_X_GATE1_EVIDENCE_MARKER'] ?? ''));
if (!preg_match('/^GATE1_SYNTHETIC_194_[A-F0-9]{16}$/', $marker)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Synthetic marker is invalid.']);
    exit;
}

function gate1_json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function gate1_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() === 1;
}

function gate1_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Unsafe table name.');
    }
    if (!gate1_table_exists($pdo, $table)) {
        return 0;
    }
    $statement = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $statement->execute($params);
    return (int)$statement->fetchColumn();
}

function gate1_migration_pdo(): PDO
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

function gate1_inspect(PDO $pdo, string $marker): array
{
    $candidateIds = [];
    if (gate1_table_exists($pdo, 'startpartner_candidates')) {
        $statement = $pdo->prepare('SELECT id FROM startpartner_candidates WHERE organization_name = :marker ORDER BY id');
        $statement->execute(['marker' => $marker]);
        $candidateIds = array_map(
            static fn(array $row): string => (string)$row['id'],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    $candidateId = $candidateIds[0] ?? null;
    $controlCase = null;
    if ($candidateId !== null && gate1_table_exists($pdo, 'control_cases')) {
        $caseStatement = $pdo->prepare(
            "SELECT state, decision_ready, source_system, source_reference
             FROM control_cases
             WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id
             LIMIT 1"
        );
        $caseStatement->execute(['candidate_id' => $candidateId]);
        $row = $caseStatement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $controlCase = [
                'state' => (string)$row['state'],
                'decision_ready' => (int)$row['decision_ready'],
                'source_system' => (string)$row['source_system'],
                'source_reference_matches_candidate' => hash_equals($candidateId, (string)$row['source_reference']),
            ];
        }
    }

    $lockedTables = [
        'organizers',
        'submissions',
        'subscriptions',
        'publication_entitlements',
        'publication_consumptions',
    ];
    $lockedCounts = [];
    foreach ($lockedTables as $table) {
        $lockedCounts[$table] = gate1_count($pdo, $table);
    }

    $migrationKeys = [];
    if (gate1_table_exists($pdo, 'app_schema_migrations')) {
        $migrationStatement = $pdo->query(
            "SELECT migration_key FROM app_schema_migrations
             WHERE migration_key IN ('007_runtime_schema_reconciliation', '008_startpartner_candidates')
             ORDER BY migration_key"
        );
        $migrationKeys = array_map(
            static fn(array $row): string => (string)$row['migration_key'],
            $migrationStatement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    return [
        'candidate_ids' => $candidateIds,
        'candidate_rows' => count($candidateIds),
        'contact_rows' => $candidateId === null ? 0 : gate1_count(
            $pdo,
            'startpartner_candidate_contacts',
            'candidate_id = :candidate_id',
            ['candidate_id' => $candidateId]
        ),
        'candidate_event_rows' => $candidateId === null ? 0 : gate1_count(
            $pdo,
            'startpartner_candidate_events',
            'candidate_id = :candidate_id',
            ['candidate_id' => $candidateId]
        ),
        'control_case_rows' => $candidateId === null ? 0 : gate1_count(
            $pdo,
            'control_cases',
            "source_system = 'startpartner_candidate' AND source_reference = :candidate_id",
            ['candidate_id' => $candidateId]
        ),
        'control_case' => $controlCase,
        'locked_table_counts' => $lockedCounts,
        'migrations' => $migrationKeys,
    ];
}

try {
    if ($action === 'migrate') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            gate1_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
        }
        $pdo = gate1_migration_pdo();
        $lockName = 'be_startpartner_gate1_evidence_194';
        $locked = (int)$pdo->query("SELECT GET_LOCK('{$lockName}', 10)")->fetchColumn() === 1;
        if (!$locked) {
            throw new RuntimeException('Could not acquire the evidence lock.');
        }
        try {
            $before = [
                'startpartner_candidates' => gate1_table_exists($pdo, 'startpartner_candidates'),
                'startpartner_candidate_contacts' => gate1_table_exists($pdo, 'startpartner_candidate_contacts'),
                'startpartner_candidate_events' => gate1_table_exists($pdo, 'startpartner_candidate_events'),
            ];
            gate1_apply_sql($pdo, dirname(__DIR__) . '/sql/007_runtime_schema_reconciliation.sql');
            gate1_apply_sql($pdo, dirname(__DIR__) . '/sql/008_startpartner_candidates.sql');
            $after = [
                'startpartner_candidates' => gate1_table_exists($pdo, 'startpartner_candidates'),
                'startpartner_candidate_contacts' => gate1_table_exists($pdo, 'startpartner_candidate_contacts'),
                'startpartner_candidate_events' => gate1_table_exists($pdo, 'startpartner_candidate_events'),
            ];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('{$lockName}')")->fetchColumn();
        }
        gate1_json_response(200, [
            'status' => 'ok',
            'action' => 'migrate',
            'tables_before' => $before,
            'tables_after' => $after,
            'inspection' => gate1_inspect(be_db(), $marker),
        ]);
    }

    if ($action === 'inspect') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            gate1_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
        }
        gate1_json_response(200, [
            'status' => 'ok',
            'action' => 'inspect',
            'inspection' => gate1_inspect(be_db(), $marker),
        ]);
    }

    if ($action === 'cleanup') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            gate1_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
        }
        $pdo = be_db();
        $inspection = gate1_inspect($pdo, $marker);
        foreach ($inspection['candidate_ids'] as $candidateId) {
            $deleteCases = $pdo->prepare(
                "DELETE FROM control_cases WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
            );
            $deleteCases->execute(['candidate_id' => $candidateId]);
            $deleteCandidate = $pdo->prepare('DELETE FROM startpartner_candidates WHERE id = :candidate_id');
            $deleteCandidate->execute(['candidate_id' => $candidateId]);
        }
        $after = gate1_inspect($pdo, $marker);
        $selfDeleted = @unlink(__FILE__);
        gate1_json_response(200, [
            'status' => 'ok',
            'action' => 'cleanup',
            'candidate_rows' => $after['candidate_rows'],
            'contact_rows' => $after['contact_rows'],
            'candidate_event_rows' => $after['candidate_event_rows'],
            'control_case_rows' => $after['control_case_rows'],
            'self_deleted' => $selfDeleted,
        ]);
    }

    if (in_array($action, ['intake', 'candidates', 'triage'], true)) {
        $reviewPassword = be_review_password();
        if (preg_match('/[\r\n]/', $reviewPassword)) {
            throw new RuntimeException('Review password contains invalid control characters.');
        }
        $_SERVER['HTTP_X_BE_REVIEW_PASSWORD'] = $reviewPassword;

        if ($action === 'intake') {
            require dirname(__DIR__) . '/startpartner/intake.php';
        }
        if ($action === 'candidates') {
            unset($_GET['action']);
            require dirname(__DIR__) . '/startpartner/candidates.php';
        }
        require dirname(__DIR__) . '/startpartner/triage.php';
    }

    gate1_json_response(404, ['status' => 'not_found']);
} catch (Throwable $error) {
    gate1_json_response(500, [
        'status' => 'error',
        'action' => $action,
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}
