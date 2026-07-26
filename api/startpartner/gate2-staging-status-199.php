<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

const BE_GATE2_STATUS_TOKEN_HASH = '921e687a88b06ddb2124766a0be0c43e5875309c393f3ed91219470100053243';
const BE_GATE2_MIGRATIONS = [
    '009' => '009_control_center_runtime_schema',
    '010' => '010_startpartner_gate2_qualification_capacity',
];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (be_app_env_value() !== 'staging') {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
}

$userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$expectedBuild = trim((string)($_SERVER['HTTP_X_BE_EXPECTED_BUILD'] ?? ''));
$buildPath = dirname(__DIR__, 2) . '/meta/build.txt';
$deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
$deployAuthorized = $userAgent === 'Bocholt-Erleben-Deploy-Smoke/1.0'
    && $expectedBuild !== ''
    && $deployedBuild !== ''
    && hash_equals($deployedBuild, $expectedBuild);
$diagnosticToken = trim((string)($_GET['diagnostic_token'] ?? ''));
$diagnosticAuthorized = $diagnosticToken !== ''
    && hash_equals(BE_GATE2_STATUS_TOKEN_HASH, hash('sha256', $diagnosticToken));
if (!$deployAuthorized && !$diagnosticAuthorized) {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
}

function be_gate2_status_scalar(PDOStatement $statement): mixed
{
    $value = $statement->fetchColumn();
    $statement->closeCursor();
    return $value;
}

function be_gate2_status_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)be_gate2_status_scalar($statement) === 1;
}

function be_gate2_status_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!be_gate2_status_table_exists($pdo, $table)) {
        return -1;
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where);
    $statement->execute($params);
    return (int)be_gate2_status_scalar($statement);
}

function be_gate2_status_migration_count(PDO $pdo, string $migrationKey): int
{
    return be_gate2_status_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :migration_key',
        ['migration_key' => $migrationKey]
    );
}

function be_gate2_status_table_metadata(PDO $pdo, string $table): ?array
{
    $statement = $pdo->prepare(
        'SELECT ENGINE, TABLE_COLLATION
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    if (!is_array($row)) {
        return null;
    }
    return [
        'engine' => (string)($row['ENGINE'] ?? ''),
        'collation' => (string)($row['TABLE_COLLATION'] ?? ''),
    ];
}

function be_gate2_status_column_metadata(PDO $pdo, string $table, string $column): ?array
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    if (!is_array($row)) {
        return null;
    }
    return [
        'type' => (string)($row['COLUMN_TYPE'] ?? ''),
        'nullable' => (string)($row['IS_NULLABLE'] ?? ''),
        'default' => $row['COLUMN_DEFAULT'] ?? null,
        'extra' => (string)($row['EXTRA'] ?? ''),
        'collation' => $row['COLLATION_NAME'] ?? null,
    ];
}

function be_gate2_status_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name'
    );
    $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
    return (int)be_gate2_status_scalar($statement) === 1;
}

function be_gate2_status_orphan_count(PDO $pdo, string $childTable, string $childColumn, string $parentTable, string $parentColumn): int
{
    if (!be_gate2_status_table_exists($pdo, $childTable) || !be_gate2_status_table_exists($pdo, $parentTable)) {
        return -1;
    }
    $quote = static fn(string $value): string => '`' . str_replace('`', '``', $value) . '`';
    $sql = sprintf(
        'SELECT COUNT(*)
         FROM %s child
         LEFT JOIN %s parent ON parent.%s = child.%s
         WHERE child.%s IS NOT NULL AND parent.%s IS NULL',
        $quote($childTable),
        $quote($parentTable),
        $quote($parentColumn),
        $quote($childColumn),
        $quote($childColumn),
        $quote($parentColumn)
    );
    $statement = $pdo->query($sql);
    return (int)be_gate2_status_scalar($statement);
}

$pdo = be_db();
$prefix = 'GATE2_SYNTHETIC_199_%';
$operationPrefix = 'gate2:199:staging-%';
$candidateSubquery = 'candidate_id IN (
    SELECT id FROM startpartner_candidates WHERE organization_name LIKE :candidate_prefix
)';

$migrations = [];
foreach (BE_GATE2_MIGRATIONS as $number => $migrationKey) {
    $migrations[$number] = be_gate2_status_migration_count($pdo, $migrationKey);
}

$residue = [
    'candidates' => be_gate2_status_count(
        $pdo,
        'startpartner_candidates',
        'organization_name LIKE :candidate_prefix',
        ['candidate_prefix' => $prefix]
    ),
    'contacts' => be_gate2_status_count($pdo, 'startpartner_candidate_contacts', $candidateSubquery, ['candidate_prefix' => $prefix]),
    'events' => be_gate2_status_count($pdo, 'startpartner_candidate_events', $candidateSubquery, ['candidate_prefix' => $prefix]),
    'qualifications' => be_gate2_status_count($pdo, 'startpartner_candidate_qualifications', $candidateSubquery, ['candidate_prefix' => $prefix]),
    'decisions' => be_gate2_status_count($pdo, 'startpartner_candidate_decisions', $candidateSubquery, ['candidate_prefix' => $prefix]),
    'reservations' => be_gate2_status_count($pdo, 'startpartner_candidate_reservations', $candidateSubquery, ['candidate_prefix' => $prefix]),
    'waitlist' => be_gate2_status_count($pdo, 'startpartner_candidate_waitlist', $candidateSubquery, ['candidate_prefix' => $prefix]),
    'operations' => be_gate2_status_count(
        $pdo,
        'startpartner_candidate_operations',
        'operation_id LIKE :operation_prefix',
        ['operation_prefix' => $operationPrefix]
    ),
    'control_cases' => be_gate2_status_count(
        $pdo,
        'control_cases',
        "source_system = 'startpartner_candidate' AND title LIKE :candidate_title",
        ['candidate_title' => '%GATE2_SYNTHETIC_199_%']
    ),
];

$controlTables = [
    'control_cases',
    'control_case_events',
    'control_content_changes',
    'control_development_snapshots',
    'control_operations',
    'control_editorial_feedback',
];
$controlTableMetadata = [];
foreach ($controlTables as $table) {
    $controlTableMetadata[$table] = be_gate2_status_table_metadata($pdo, $table);
}

$versionStatement = $pdo->query('SELECT VERSION()');
$preflight = [
    'database_version' => (string)be_gate2_status_scalar($versionStatement),
    'control_tables' => $controlTableMetadata,
    'control_columns' => [
        'control_cases.id' => be_gate2_status_column_metadata($pdo, 'control_cases', 'id'),
        'control_case_events.case_id' => be_gate2_status_column_metadata($pdo, 'control_case_events', 'case_id'),
        'control_operations.case_id' => be_gate2_status_column_metadata($pdo, 'control_operations', 'case_id'),
        'control_editorial_feedback.case_id' => be_gate2_status_column_metadata($pdo, 'control_editorial_feedback', 'case_id'),
    ],
    'control_foreign_keys' => [
        'fk_control_case_events_case' => be_gate2_status_foreign_key_exists($pdo, 'control_case_events', 'fk_control_case_events_case'),
        'fk_control_operations_case' => be_gate2_status_foreign_key_exists($pdo, 'control_operations', 'fk_control_operations_case'),
        'fk_control_editorial_feedback_case' => be_gate2_status_foreign_key_exists($pdo, 'control_editorial_feedback', 'fk_control_editorial_feedback_case'),
    ],
    'control_orphan_counts' => [
        'control_case_events' => be_gate2_status_orphan_count($pdo, 'control_case_events', 'case_id', 'control_cases', 'id'),
        'control_operations' => be_gate2_status_orphan_count($pdo, 'control_operations', 'case_id', 'control_cases', 'id'),
        'control_editorial_feedback' => be_gate2_status_orphan_count($pdo, 'control_editorial_feedback', 'case_id', 'control_cases', 'id'),
    ],
    'candidate_schema' => [
        'table' => be_gate2_status_table_metadata($pdo, 'startpartner_candidates'),
        'status' => be_gate2_status_column_metadata($pdo, 'startpartner_candidates', 'status'),
        'revision' => be_gate2_status_column_metadata($pdo, 'startpartner_candidates', 'revision'),
        'assigned_to' => be_gate2_status_column_metadata($pdo, 'startpartner_candidates', 'assigned_to'),
        'next_review_at' => be_gate2_status_column_metadata($pdo, 'startpartner_candidates', 'next_review_at'),
        'status_changed_at' => be_gate2_status_column_metadata($pdo, 'startpartner_candidates', 'status_changed_at'),
        'qualified_rows' => be_gate2_status_count($pdo, 'startpartner_candidates', "status = 'qualified'"),
    ],
];

$missingTables = array_keys(array_filter($residue, static fn(int $count): bool => $count < 0));
$positiveResidue = array_filter($residue, static fn(int $count): bool => $count > 0);
$totalResidue = array_sum(array_filter($residue, static fn(int $count): bool => $count > 0));
$status = $migrations['009'] === 1 && $migrations['010'] === 1 && $missingTables === [] && $totalResidue === 0
    ? 'PASS'
    : 'FAIL';

be_json_response($status === 'PASS' ? 200 : 409, [
    'status' => $status,
    'workpack_issue' => 199,
    'environment' => be_app_env_value(),
    'deployed_build' => $deployedBuild,
    'migration_action' => 'read_only',
    'applied_migrations' => [],
    'migrations' => $migrations,
    'residue' => $residue + ['total' => $totalResidue],
    'missing_tables' => $missingTables,
    'positive_residue' => $positiveResidue,
    'preflight' => $preflight,
    'checked_at' => gmdate(DateTimeInterface::ATOM),
]);
