<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

const BE_ORGANIZER_AUDIT_328_ORGANIZATION = 'Testpuper';
const BE_ORGANIZER_AUDIT_328_USER_AGENT = 'Bocholt-Erleben-Deploy-Smoke/1.0';
const BE_ORGANIZER_AUDIT_328_MAX_MATCHES = 10;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_organizer_audit_328_fail(int $status, string $code): never
{
    be_json_response($status, [
        'status' => 'error',
        'code' => $code,
        'database_mutation' => false,
    ]);
}

function be_organizer_audit_328_identifier(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        throw new RuntimeException('AUDIT_UNSAFE_IDENTIFIER');
    }
    return '`' . $value . '`';
}

function be_organizer_audit_328_columns(PDO $pdo, string $database, string $table): array
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table'
    );
    $statement->execute(['database' => $database, 'table' => $table]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function be_organizer_audit_328_reference_map(PDO $pdo, string $database): array
{
    $references = $pdo->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME, 'fk' AS source_kind\n"
        . "FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE\n"
        . "WHERE TABLE_SCHEMA = :database_fk\n"
        . "  AND REFERENCED_TABLE_SCHEMA = :database_ref\n"
        . "  AND REFERENCED_TABLE_NAME = 'organizers'\n"
        . "  AND REFERENCED_COLUMN_NAME = 'id'\n"
        . "UNION ALL\n"
        . "SELECT TABLE_NAME, COLUMN_NAME, 'column' AS source_kind\n"
        . "FROM INFORMATION_SCHEMA.COLUMNS\n"
        . "WHERE TABLE_SCHEMA = :database_column AND COLUMN_NAME = 'organizer_id'\n"
        . "ORDER BY TABLE_NAME, COLUMN_NAME, source_kind"
    );
    $references->execute([
        'database_fk' => $database,
        'database_ref' => $database,
        'database_column' => $database,
    ]);

    $referenceMap = [];
    foreach ($references->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string)$row['TABLE_NAME'];
        $column = (string)$row['COLUMN_NAME'];
        $key = $table . "\0" . $column;
        $referenceMap[$key] ??= [
            'table' => $table,
            'column' => $column,
            'is_fk_to_organizers_id' => false,
        ];
        if ((string)$row['source_kind'] === 'fk') {
            $referenceMap[$key]['is_fk_to_organizers_id'] = true;
        }
    }
    return $referenceMap;
}

function be_organizer_audit_328_direct_dependencies(
    PDO $pdo,
    string $database,
    array $referenceMap,
    int|string $organizerId
): array {
    $safeGroupFields = [
        'status', 'submission_kind', 'requested_model_key', 'payment_kind',
        'plan_key', 'pending_plan_key', 'source_type', 'source_provider',
        'content_type', 'intended_action', 'consumed_reason', 'event_type',
        'scope_type', 'scope_key', 'period_unit',
    ];
    $presenceFields = [
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_checkout_session_id',
        'stripe_price_id', 'token_hash', 'session_token_hash', 'paid_at',
        'consumed_at', 'revoked_at', 'canceled_at', 'last_sent_at', 'last_seen_at',
    ];

    $directDependencies = [];
    $directRowsTotal = 0;
    $tablesWithRows = 0;

    foreach ($referenceMap as $reference) {
        $table = (string)$reference['table'];
        $column = (string)$reference['column'];
        $quotedTable = be_organizer_audit_328_identifier($table);
        $quotedColumn = be_organizer_audit_328_identifier($column);
        $countStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM {$quotedTable} WHERE {$quotedColumn} = :organizer_id"
        );
        $countStatement->execute(['organizer_id' => $organizerId]);
        $rowCount = (int)$countStatement->fetchColumn();

        $item = [
            'table' => $table,
            'column' => $column,
            'is_fk_to_organizers_id' => (bool)$reference['is_fk_to_organizers_id'],
            'row_count' => $rowCount,
        ];

        if ($rowCount > 0) {
            $directRowsTotal += $rowCount;
            $tablesWithRows++;
            $columns = be_organizer_audit_328_columns($pdo, $database, $table);

            $groups = [];
            foreach (array_values(array_intersect($safeGroupFields, $columns)) as $field) {
                $quotedField = be_organizer_audit_328_identifier($field);
                $groupStatement = $pdo->prepare(
                    "SELECT COALESCE(CAST({$quotedField} AS CHAR), '<NULL>') AS value, COUNT(*) AS n "
                    . "FROM {$quotedTable} WHERE {$quotedColumn} = :organizer_id "
                    . "GROUP BY {$quotedField} ORDER BY n DESC, value ASC LIMIT 25"
                );
                $groupStatement->execute(['organizer_id' => $organizerId]);
                $groups[$field] = array_map(
                    static fn(array $row): array => [
                        'value' => (string)$row['value'],
                        'count' => (int)$row['n'],
                    ],
                    $groupStatement->fetchAll(PDO::FETCH_ASSOC)
                );
            }
            if ($groups !== []) {
                $item['groups'] = $groups;
            }

            $presence = [];
            foreach (array_values(array_intersect($presenceFields, $columns)) as $field) {
                $quotedField = be_organizer_audit_328_identifier($field);
                $presenceStatement = $pdo->prepare(
                    "SELECT COUNT(*) FROM {$quotedTable} "
                    . "WHERE {$quotedColumn} = :organizer_id AND {$quotedField} IS NOT NULL"
                );
                $presenceStatement->execute(['organizer_id' => $organizerId]);
                $presence[$field] = (int)$presenceStatement->fetchColumn();
            }
            if ($presence !== []) {
                $item['non_null_presence_only'] = $presence;
            }

            if (in_array('expires_at', $columns, true)) {
                $expiryStatement = $pdo->prepare(
                    "SELECT SUM(expires_at > UTC_TIMESTAMP()) AS unexpired, "
                    . "SUM(expires_at <= UTC_TIMESTAMP()) AS expired "
                    . "FROM {$quotedTable} WHERE {$quotedColumn} = :organizer_id"
                );
                $expiryStatement->execute(['organizer_id' => $organizerId]);
                $expiry = $expiryStatement->fetch(PDO::FETCH_ASSOC);
                $item['expiry_state'] = [
                    'unexpired' => (int)($expiry['unexpired'] ?? 0),
                    'expired' => (int)($expiry['expired'] ?? 0),
                ];
            }
        }

        $directDependencies[] = $item;
    }

    return [
        'items' => $directDependencies,
        'rows_total' => $directRowsTotal,
        'tables_with_rows' => $tablesWithRows,
    ];
}

function be_organizer_audit_328_logical_references(PDO $pdo, string $database, int|string $organizerId): array
{
    $logicalReferences = [];
    foreach ([
        ['reporting_target_type', 'reporting_target_id'],
        ['target_type', 'target_id'],
        ['owner_type', 'owner_id'],
        ['entity_type', 'entity_id'],
    ] as [$typeColumn, $idColumn]) {
        $tablesStatement = $pdo->prepare(
            'SELECT c1.TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS c1 '
            . 'JOIN INFORMATION_SCHEMA.COLUMNS c2 '
            . 'ON c2.TABLE_SCHEMA = c1.TABLE_SCHEMA AND c2.TABLE_NAME = c1.TABLE_NAME '
            . 'WHERE c1.TABLE_SCHEMA = :database AND c1.COLUMN_NAME = :type_column '
            . 'AND c2.COLUMN_NAME = :id_column ORDER BY c1.TABLE_NAME'
        );
        $tablesStatement->execute([
            'database' => $database,
            'type_column' => $typeColumn,
            'id_column' => $idColumn,
        ]);

        foreach ($tablesStatement->fetchAll(PDO::FETCH_COLUMN) as $tableValue) {
            $table = (string)$tableValue;
            $quotedTable = be_organizer_audit_328_identifier($table);
            $quotedType = be_organizer_audit_328_identifier($typeColumn);
            $quotedId = be_organizer_audit_328_identifier($idColumn);
            $logicalStatement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$quotedTable} "
                . "WHERE LOWER(CAST({$quotedType} AS CHAR)) = 'organizer' "
                . "AND CAST({$quotedId} AS CHAR) = :organizer_id"
            );
            $logicalStatement->execute(['organizer_id' => (string)$organizerId]);
            $rowCount = (int)$logicalStatement->fetchColumn();
            if ($rowCount > 0) {
                $logicalReferences[] = [
                    'table' => $table,
                    'type_column' => $typeColumn,
                    'id_column' => $idColumn,
                    'row_count' => $rowCount,
                ];
            }
        }
    }
    return $logicalReferences;
}

if (be_app_env_value() !== 'staging') {
    be_organizer_audit_328_fail(404, 'AUDIT_NOT_AVAILABLE');
}

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_organizer_audit_328_fail(405, 'METHOD_NOT_ALLOWED');
}

if ((string)($_SERVER['HTTP_USER_AGENT'] ?? '') !== BE_ORGANIZER_AUDIT_328_USER_AGENT) {
    be_organizer_audit_328_fail(403, 'AUDIT_USER_AGENT_REJECTED');
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('AUDIT_INPUT_INVALID');
    }

    $expectedBuild = trim((string)($input['expected_build'] ?? ''));
    if (preg_match('/^[0-9a-f]{12}$/', $expectedBuild) !== 1) {
        throw new InvalidArgumentException('AUDIT_BUILD_INVALID');
    }

    $buildPath = dirname(__DIR__) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
    if ($deployedBuild !== $expectedBuild) {
        throw new RuntimeException('AUDIT_BUILD_MISMATCH');
    }

    $pdo = be_db();
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('AUDIT_DATABASE_UNKNOWN');
    }

    $pdo->exec('START TRANSACTION READ ONLY');
    $rollbackPerformed = false;

    try {
        $organizerStatement = $pdo->prepare(
            "SELECT id, organization_name, default_plan_key, created_at, updated_at,\n"
            . "       SHA2(LOWER(TRIM(email_normalized)), 256) AS email_sha256,\n"
            . "       (stripe_customer_id IS NOT NULL AND stripe_customer_id <> '') AS has_stripe_customer\n"
            . "FROM organizers\n"
            . "WHERE organization_name = :organization\n"
            . "ORDER BY id ASC"
        );
        $organizerStatement->execute(['organization' => BE_ORGANIZER_AUDIT_328_ORGANIZATION]);
        $organizerRows = $organizerStatement->fetchAll(PDO::FETCH_ASSOC);
        if ($organizerRows === [] || count($organizerRows) > BE_ORGANIZER_AUDIT_328_MAX_MATCHES) {
            throw new RuntimeException('AUDIT_ORGANIZER_MATCH_COUNT_' . count($organizerRows));
        }

        $candidateStatement = $pdo->prepare(
            "SELECT c.id, c.status, c.desired_content_scope, c.created_at, c.updated_at,\n"
            . "       SHA2(LOWER(TRIM(cc.email_normalized)), 256) AS primary_contact_sha256\n"
            . "FROM startpartner_candidates c\n"
            . "LEFT JOIN startpartner_candidate_contacts cc\n"
            . "  ON cc.candidate_id = c.id AND cc.is_primary = 1\n"
            . "WHERE c.organization_name = :organization\n"
            . "ORDER BY c.updated_at DESC, c.id ASC"
        );
        $candidateStatement->execute(['organization' => BE_ORGANIZER_AUDIT_328_ORGANIZATION]);
        $candidateRows = $candidateStatement->fetchAll(PDO::FETCH_ASSOC);
        if (count($candidateRows) > BE_ORGANIZER_AUDIT_328_MAX_MATCHES) {
            throw new RuntimeException('AUDIT_CANDIDATE_MATCH_COUNT_' . count($candidateRows));
        }

        $candidateHashes = [];
        $candidates = [];
        foreach ($candidateRows as $candidateRow) {
            $hash = strtolower(trim((string)($candidateRow['primary_contact_sha256'] ?? '')));
            if ($hash !== '') {
                $candidateHashes[$hash] = true;
            }
            $candidates[] = [
                'candidate_id' => (string)$candidateRow['id'],
                'status' => (string)$candidateRow['status'],
                'desired_content_scope' => (string)$candidateRow['desired_content_scope'],
                'created_at' => (string)$candidateRow['created_at'],
                'updated_at' => (string)$candidateRow['updated_at'],
                'has_primary_contact' => $hash !== '',
                'is_latest_by_updated_at' => count($candidates) === 0,
            ];
        }

        $latestCandidateHash = '';
        if ($candidateRows !== []) {
            $latestCandidateHash = strtolower(trim((string)($candidateRows[0]['primary_contact_sha256'] ?? '')));
        }

        $referenceMap = be_organizer_audit_328_reference_map($pdo, $database);
        $organizers = [];
        $allDirectRows = 0;
        $allLogicalRows = 0;

        foreach ($organizerRows as $organizerRow) {
            $organizerId = $organizerRow['id'];
            $emailHash = strtolower(trim((string)$organizerRow['email_sha256']));
            $direct = be_organizer_audit_328_direct_dependencies(
                $pdo,
                $database,
                $referenceMap,
                $organizerId
            );
            $logical = be_organizer_audit_328_logical_references($pdo, $database, $organizerId);
            $logicalRows = array_sum(array_column($logical, 'row_count'));
            $allDirectRows += (int)$direct['rows_total'];
            $allLogicalRows += $logicalRows;

            $organizers[] = [
                'organizer_id' => (int)$organizerId,
                'organization_name' => (string)$organizerRow['organization_name'],
                'created_at' => (string)$organizerRow['created_at'],
                'updated_at' => (string)$organizerRow['updated_at'],
                'default_plan_key' => $organizerRow['default_plan_key'] ?? null,
                'has_stripe_customer' => (bool)$organizerRow['has_stripe_customer'],
                'matches_any_testpuper_candidate_primary_contact' => isset($candidateHashes[$emailHash]),
                'matches_latest_testpuper_candidate_primary_contact' => (
                    $latestCandidateHash !== '' && hash_equals($latestCandidateHash, $emailHash)
                ),
                'direct_dependencies' => $direct['items'],
                'logical_references' => $logical,
                'dependency_summary' => [
                    'direct_reference_tables_with_rows' => (int)$direct['tables_with_rows'],
                    'direct_reference_rows_total' => (int)$direct['rows_total'],
                    'logical_reference_rows_total' => $logicalRows,
                ],
            ];
        }

        $pdo->rollBack();
        $rollbackPerformed = true;

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'audit' => 'ORGANIZER_IDENTITY_AUDIT_328_V1',
                'environment' => 'staging',
                'build' => $deployedBuild,
                'database_mutation' => false,
                'organization_name' => BE_ORGANIZER_AUDIT_328_ORGANIZATION,
                'organizer_match_count' => count($organizers),
                'candidate_match_count' => count($candidates),
                'candidates' => $candidates,
                'organizers' => $organizers,
                'summary' => [
                    'discovered_direct_reference_columns' => count($referenceMap),
                    'direct_reference_rows_total_all_matches' => $allDirectRows,
                    'logical_reference_rows_total_all_matches' => $allLogicalRows,
                    'read_only_transaction' => true,
                    'rollback_performed' => $rollbackPerformed,
                ],
            ],
        ]);
    } catch (Throwable $auditError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $auditError;
    }
} catch (InvalidArgumentException $error) {
    be_organizer_audit_328_fail(422, 'AUDIT_INPUT_REJECTED');
} catch (Throwable $error) {
    be_organizer_audit_328_fail(500, 'AUDIT_FAILED');
}
