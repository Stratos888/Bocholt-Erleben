<?php
declare(strict_types=1);

/* Temporary evidence endpoint. Never merge or deploy permanently. */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

const BE_EVIDENCE_TOKEN_HASH = '__TOKEN_HASH__';
const BE_EVIDENCE_TABLES = [
    'organizers',
    'submissions',
    'subscriptions',
    'publication_entitlements',
    'publication_consumptions',
    'organizer_magic_links',
    'organizer_portal_sessions',
    'webhook_events',
    'value_metric_daily',
    'control_cases',
    'control_case_events',
];

require dirname(__DIR__) . '/_bootstrap.php';

function evidence_json(array $payload): never
{
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n";
    exit;
}

function evidence_require_token(): void
{
    $provided = trim((string)($_SERVER['HTTP_X_BE_EVIDENCE_TOKEN'] ?? ''));
    if ($provided === '' || !hash_equals(BE_EVIDENCE_TOKEN_HASH, hash('sha256', $provided))) {
        http_response_code(404);
        evidence_json(['status' => 'not_found']);
    }
}

function evidence_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function evidence_table_exists(PDO $pdo, string $database, string $table): bool
{
    $rows = evidence_fetch_all(
        $pdo,
        'SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :database_name AND TABLE_NAME = :table_name',
        [':database_name' => $database, ':table_name' => $table]
    );
    return (int)($rows[0]['count_value'] ?? 0) > 0;
}

function evidence_show_create(PDO $pdo, string $table): string
{
    $statement = $pdo->query('SHOW CREATE TABLE `' . $table . '`');
    $row = $statement !== false ? $statement->fetch(PDO::FETCH_NUM) : false;
    return is_array($row) ? (string)($row[1] ?? '') : '';
}

function evidence_aggregates(PDO $pdo, string $table): array
{
    $queries = [
        'organizers' => [
            'total' => 'SELECT COUNT(*) AS count_value FROM organizers',
        ],
        'submissions' => [
            'by_status_kind_model_payment_origin' =>
                "SELECT status, submission_kind, requested_model_key, payment_kind,
                        COALESCE(intake_origin, '') AS intake_origin,
                        COUNT(*) AS count_value
                 FROM submissions
                 GROUP BY status, submission_kind, requested_model_key, payment_kind, COALESCE(intake_origin, '')
                 ORDER BY status, submission_kind, requested_model_key, payment_kind, intake_origin",
        ],
        'subscriptions' => [
            'by_provider_plan_status' =>
                'SELECT source_provider, plan_key, status, COUNT(*) AS count_value
                 FROM subscriptions
                 GROUP BY source_provider, plan_key, status
                 ORDER BY source_provider, plan_key, status',
        ],
        'publication_entitlements' => [
            'by_source_plan_status_unlimited' =>
                "SELECT source_type, COALESCE(plan_key, '') AS plan_key, status, is_unlimited,
                        COUNT(*) AS count_value,
                        COALESCE(SUM(included_publications), 0) AS included_publications,
                        COALESCE(SUM(consumed_publications), 0) AS consumed_publications
                 FROM publication_entitlements
                 GROUP BY source_type, COALESCE(plan_key, ''), status, is_unlimited
                 ORDER BY source_type, plan_key, status, is_unlimited",
        ],
        'publication_consumptions' => [
            'by_reason' =>
                'SELECT consumed_reason, COUNT(*) AS count_value, COALESCE(SUM(units), 0) AS units
                 FROM publication_consumptions
                 GROUP BY consumed_reason
                 ORDER BY consumed_reason',
        ],
        'organizer_magic_links' => [
            'by_lifecycle' =>
                "SELECT CASE
                            WHEN consumed_at IS NOT NULL THEN 'consumed'
                            WHEN revoked_at IS NOT NULL THEN 'revoked'
                            WHEN expires_at < UTC_TIMESTAMP() THEN 'expired'
                            ELSE 'active'
                        END AS lifecycle,
                        COUNT(*) AS count_value
                 FROM organizer_magic_links
                 GROUP BY lifecycle
                 ORDER BY lifecycle",
        ],
        'organizer_portal_sessions' => [
            'by_lifecycle' =>
                "SELECT CASE
                            WHEN revoked_at IS NOT NULL THEN 'revoked'
                            WHEN expires_at < UTC_TIMESTAMP() THEN 'expired'
                            ELSE 'active'
                        END AS lifecycle,
                        COUNT(*) AS count_value
                 FROM organizer_portal_sessions
                 GROUP BY lifecycle
                 ORDER BY lifecycle",
        ],
        'webhook_events' => [
            'by_provider_event_processed' =>
                'SELECT provider, event_type, is_processed, COUNT(*) AS count_value
                 FROM webhook_events
                 GROUP BY provider, event_type, is_processed
                 ORDER BY provider, event_type, is_processed',
        ],
        'value_metric_daily' => [
            'period' =>
                'SELECT MIN(metric_date) AS oldest_metric_date,
                        MAX(metric_date) AS newest_metric_date,
                        COUNT(*) AS bucket_count,
                        COALESCE(SUM(count_value), 0) AS count_value
                 FROM value_metric_daily',
            'by_metric_and_target' =>
                'SELECT metric_key, entity_type, reporting_target_type, source_context,
                        COUNT(*) AS bucket_count,
                        COALESCE(SUM(count_value), 0) AS count_value,
                        MIN(metric_date) AS oldest_metric_date,
                        MAX(metric_date) AS newest_metric_date
                 FROM value_metric_daily
                 GROUP BY metric_key, entity_type, reporting_target_type, source_context
                 ORDER BY metric_key, entity_type, reporting_target_type, source_context',
        ],
        'control_cases' => [
            'by_type_state_priority_source' =>
                'SELECT case_type, state, priority, source_system, decision_ready,
                        COUNT(*) AS count_value
                 FROM control_cases
                 GROUP BY case_type, state, priority, source_system, decision_ready
                 ORDER BY case_type, state, priority, source_system, decision_ready',
        ],
        'control_case_events' => [
            'by_transition' =>
                "SELECT action, COALESCE(from_state, '') AS from_state,
                        COALESCE(to_state, '') AS to_state,
                        COUNT(*) AS count_value
                 FROM control_case_events
                 GROUP BY action, COALESCE(from_state, ''), COALESCE(to_state, '')
                 ORDER BY action, from_state, to_state",
        ],
    ];

    $result = [];
    foreach (($queries[$table] ?? []) as $name => $sql) {
        try {
            $result[$name] = evidence_fetch_all($pdo, $sql);
        } catch (Throwable $error) {
            $result[$name] = [
                'status' => 'NOT_COMPATIBLE',
                'error_class' => get_class($error),
                'error_code' => (string)$error->getCode(),
            ];
        }
    }
    return $result;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    evidence_json(['status' => 'method_not_allowed']);
}

evidence_require_token();

$pdo = null;
try {
    $config = be_get_config();
    $configuredDatabase = trim((string)($config['db']['name'] ?? ''));
    $pdo = be_db();
    $pdo->exec('START TRANSACTION READ ONLY');

    $selectedDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = [];

    foreach (BE_EVIDENCE_TABLES as $table) {
        $exists = evidence_table_exists($pdo, $selectedDatabase, $table);
        $entry = [
            'exists' => $exists,
            'columns' => [],
            'indexes' => [],
            'foreign_keys' => [],
            'show_create_table' => null,
            'row_count' => null,
            'aggregates' => [],
        ];

        if ($exists) {
            $entry['columns'] = evidence_fetch_all(
                $pdo,
                'SELECT ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :database_name AND TABLE_NAME = :table_name
                 ORDER BY ORDINAL_POSITION',
                [':database_name' => $selectedDatabase, ':table_name' => $table]
            );
            $entry['indexes'] = evidence_fetch_all(
                $pdo,
                'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = :database_name AND TABLE_NAME = :table_name
                 ORDER BY INDEX_NAME, SEQ_IN_INDEX',
                [':database_name' => $selectedDatabase, ':table_name' => $table]
            );
            $entry['foreign_keys'] = evidence_fetch_all(
                $pdo,
                'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = :database_name AND TABLE_NAME = :table_name
                   AND REFERENCED_TABLE_NAME IS NOT NULL
                 ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',
                [':database_name' => $selectedDatabase, ':table_name' => $table]
            );
            $entry['show_create_table'] = evidence_show_create($pdo, $table);
            $entry['row_count'] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            $entry['aggregates'] = evidence_aggregates($pdo, $table);
        }

        $tables[$table] = $entry;
    }

    $pdo->rollBack();
    evidence_json([
        'report_type' => 'startpartner_gate0_readonly_host_db_preflight',
        'generated_at_utc' => gmdate('c'),
        'environment' => be_app_env_value(),
        'status' => 'PASS',
        'database_name_matches_config' => $configuredDatabase !== '' && hash_equals($configuredDatabase, $selectedDatabase),
        'read_only_transaction_enforced' => true,
        'write_operations_executed' => false,
        'personal_rows_exported' => false,
        'tables' => $tables,
    ]);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    evidence_json([
        'report_type' => 'startpartner_gate0_readonly_host_db_preflight',
        'generated_at_utc' => gmdate('c'),
        'environment' => function_exists('be_app_env_value') ? be_app_env_value() : 'unknown',
        'status' => 'FAIL',
        'error_class' => get_class($error),
        'error_code' => (string)$error->getCode(),
        'write_operations_executed' => false,
        'personal_rows_exported' => false,
    ]);
}
