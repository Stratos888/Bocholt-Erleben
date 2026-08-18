<?php
declare(strict_types=1);

/* Evidence-only inspector. It is copied under api/ with a random filename and removed in the same workflow. */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

const BE_RELEASE_EVIDENCE_TOKEN_HASH = '__TOKEN_HASH__';

const BE_RELEASE_STARTPARTNER_TABLES = [
    'startpartner_candidates',
    'startpartner_candidate_contacts',
    'startpartner_candidate_events',
    'startpartner_candidate_qualifications',
    'startpartner_candidate_decisions',
    'startpartner_candidate_reservations',
    'startpartner_candidate_waitlist',
    'startpartner_candidate_operations',
    'startpartner_pilot_terms_acceptances',
    'startpartner_pilots',
    'startpartner_pilot_scopes',
    'startpartner_pilot_entitlements',
    'startpartner_pilot_events',
    'startpartner_pilot_onboarding_items',
    'startpartner_pilot_content_links',
    'startpartner_pilot_measurement_preflights',
    'startpartner_pilot_distribution_commitments',
    'startpartner_pilot_usages',
];

require __DIR__ . '/_bootstrap.php';

function be_release_evidence_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n";
    exit;
}

function be_release_evidence_require_token(): void
{
    $provided = trim((string)($_SERVER['HTTP_X_BE_EVIDENCE_TOKEN'] ?? ''));
    if ($provided === '' || !hash_equals(BE_RELEASE_EVIDENCE_TOKEN_HASH, hash('sha256', $provided))) {
        be_release_evidence_json(['status' => 'not_found'], 404);
    }
}

function be_release_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function be_release_table_exists(PDO $pdo, string $database, string $table): bool
{
    $rows = be_release_fetch_all(
        $pdo,
        'SELECT COUNT(*) AS count_value FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table',
        [':db' => $database, ':table' => $table]
    );
    return (int)($rows[0]['count_value'] ?? 0) > 0;
}

function be_release_column_exists(PDO $pdo, string $database, string $table, string $column): bool
{
    $rows = be_release_fetch_all(
        $pdo,
        'SELECT COUNT(*) AS count_value FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column',
        [':db' => $database, ':table' => $table, ':column' => $column]
    );
    return (int)($rows[0]['count_value'] ?? 0) > 0;
}

function be_release_count(PDO $pdo, string $sql): int
{
    $value = $pdo->query($sql)->fetchColumn();
    return (int)$value;
}

function be_release_normalize_create(string $sql): string
{
    return preg_replace('/AUTO_INCREMENT=\d+/i', 'AUTO_INCREMENT=<redacted>', $sql) ?? $sql;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    be_release_evidence_json(['status' => 'method_not_allowed'], 405);
}

be_release_evidence_require_token();

$pdo = null;
try {
    $config = be_get_config();
    $configuredDatabase = trim((string)($config['db']['name'] ?? ''));
    $pdo = be_db();
    $pdo->exec('START TRANSACTION READ ONLY');

    $identityStatement = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS server_version');
    $identity = $identityStatement !== false ? $identityStatement->fetch(PDO::FETCH_ASSOC) : false;
    $selectedDatabase = is_array($identity) ? (string)($identity['database_name'] ?? '') : '';
    $serverVersion = is_array($identity) ? (string)($identity['server_version'] ?? '') : '';

    $schemaRows = be_release_fetch_all(
        $pdo,
        'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db',
        [':db' => $selectedDatabase]
    );

    $tableRows = be_release_fetch_all(
        $pdo,
        'SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME',
        [':db' => $selectedDatabase]
    );

    $tables = [];
    foreach ($tableRows as $tableRow) {
        $tableName = (string)($tableRow['TABLE_NAME'] ?? '');
        if ($tableName === '') {
            continue;
        }

        $columns = be_release_fetch_all(
            $pdo,
            'SELECT ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION',
            [':db' => $selectedDatabase, ':table' => $tableName]
        );
        $indexes = be_release_fetch_all(
            $pdo,
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, COLLATION, SUB_PART, INDEX_TYPE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [':db' => $selectedDatabase, ':table' => $tableName]
        );
        $constraints = be_release_fetch_all(
            $pdo,
            'SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :db AND TABLE_NAME = :table ORDER BY CONSTRAINT_NAME',
            [':db' => $selectedDatabase, ':table' => $tableName]
        );
        $foreignKeys = be_release_fetch_all(
            $pdo,
            'SELECT rc.CONSTRAINT_NAME, rc.UPDATE_RULE, rc.DELETE_RULE, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, kcu.ORDINAL_POSITION FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.TABLE_NAME = rc.TABLE_NAME WHERE rc.CONSTRAINT_SCHEMA = :db AND rc.TABLE_NAME = :table ORDER BY rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION',
            [':db' => $selectedDatabase, ':table' => $tableName]
        );

        $showCreate = null;
        if (strtoupper((string)($tableRow['TABLE_TYPE'] ?? '')) === 'BASE TABLE') {
            $escaped = str_replace('`', '``', $tableName);
            $statement = $pdo->query('SHOW CREATE TABLE `' . $escaped . '`');
            $createRow = $statement !== false ? $statement->fetch(PDO::FETCH_NUM) : false;
            if (is_array($createRow)) {
                $showCreate = be_release_normalize_create((string)($createRow[1] ?? ''));
            }
        }

        $tables[$tableName] = [
            'table_type' => $tableRow['TABLE_TYPE'] ?? null,
            'engine' => $tableRow['ENGINE'] ?? null,
            'table_collation' => $tableRow['TABLE_COLLATION'] ?? null,
            'columns' => $columns,
            'indexes' => $indexes,
            'constraints' => $constraints,
            'foreign_keys' => $foreignKeys,
            'show_create_table' => $showCreate,
        ];
    }

    $migrationKeys = [];
    if (be_release_table_exists($pdo, $selectedDatabase, 'app_schema_migrations')) {
        $migrationRows = be_release_fetch_all(
            $pdo,
            'SELECT migration_key FROM app_schema_migrations ORDER BY migration_key'
        );
        foreach ($migrationRows as $migrationRow) {
            $migrationKeys[] = (string)($migrationRow['migration_key'] ?? '');
        }
    }

    $startpartnerRowCounts = [];
    foreach (BE_RELEASE_STARTPARTNER_TABLES as $tableName) {
        if (be_release_table_exists($pdo, $selectedDatabase, $tableName)) {
            $escaped = str_replace('`', '``', $tableName);
            $startpartnerRowCounts[$tableName] = be_release_count($pdo, 'SELECT COUNT(*) FROM `' . $escaped . '`');
        }
    }

    $integrity = [];
    if (be_release_table_exists($pdo, $selectedDatabase, 'control_cases')) {
        if (be_release_table_exists($pdo, $selectedDatabase, 'control_case_events')) {
            $integrity['control_case_events_orphans'] = be_release_count(
                $pdo,
                'SELECT COUNT(*) FROM control_case_events e LEFT JOIN control_cases c ON c.id = e.case_id WHERE c.id IS NULL'
            );
        }
        if (be_release_table_exists($pdo, $selectedDatabase, 'control_operations')) {
            $integrity['control_operations_orphans'] = be_release_count(
                $pdo,
                'SELECT COUNT(*) FROM control_operations o LEFT JOIN control_cases c ON c.id = o.case_id WHERE c.id IS NULL'
            );
        }
        if (be_release_table_exists($pdo, $selectedDatabase, 'control_editorial_feedback')) {
            $integrity['control_editorial_feedback_orphans'] = be_release_count(
                $pdo,
                'SELECT COUNT(*) FROM control_editorial_feedback f LEFT JOIN control_cases c ON c.id = f.case_id WHERE c.id IS NULL'
            );
        }
    }

    $submissionsFlags = [
        'activity_opening_json' => be_release_column_exists($pdo, $selectedDatabase, 'submissions', 'activity_opening_json'),
        'activity_image_json' => be_release_column_exists($pdo, $selectedDatabase, 'submissions', 'activity_image_json'),
        'organizer_edited_at' => be_release_column_exists($pdo, $selectedDatabase, 'submissions', 'organizer_edited_at'),
        'idx_submissions_organizer_edited_at' => false,
    ];
    $indexRows = be_release_fetch_all(
        $pdo,
        'SELECT COUNT(*) AS count_value FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :idx',
        [':db' => $selectedDatabase, ':table' => 'submissions', ':idx' => 'idx_submissions_organizer_edited_at']
    );
    $submissionsFlags['idx_submissions_organizer_edited_at'] = (int)($indexRows[0]['count_value'] ?? 0) > 0;

    $pdo->rollBack();

    be_release_evidence_json([
        'report_type' => 'release_live_host_schema_readonly_preflight',
        'generated_at_utc' => gmdate('c'),
        'environment' => function_exists('be_app_env_value') ? be_app_env_value() : 'unknown',
        'status' => 'PASS',
        'database_name_matches_config' => $configuredDatabase !== '' && hash_equals($configuredDatabase, $selectedDatabase),
        'server_version' => $serverVersion,
        'schema' => $schemaRows[0] ?? [],
        'read_only_transaction_enforced' => true,
        'write_operations_executed' => false,
        'personal_or_business_rows_exported' => false,
        'tables' => $tables,
        'migration_keys' => $migrationKeys,
        'startpartner_row_counts' => $startpartnerRowCounts,
        'integrity_counts' => $integrity,
        'submissions_reconciliation_flags' => $submissionsFlags,
    ]);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    be_release_evidence_json([
        'report_type' => 'release_live_host_schema_readonly_preflight',
        'generated_at_utc' => gmdate('c'),
        'environment' => function_exists('be_app_env_value') ? be_app_env_value() : 'unknown',
        'status' => 'FAIL',
        'error_class' => get_class($error),
        'error_code' => (string)$error->getCode(),
        'read_only_transaction_enforced' => false,
        'write_operations_executed' => false,
        'personal_or_business_rows_exported' => false,
    ], 500);
}
