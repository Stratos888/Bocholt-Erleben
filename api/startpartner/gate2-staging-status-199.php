<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

const BE_GATE2_STATUS_TOKEN_HASH = '921e687a88b06ddb2124766a0be0c43e5875309c393f3ed91219470100053243';

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

function be_gate2_status_identifier(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

function be_gate2_status_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() === 1;
}

function be_gate2_status_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!be_gate2_status_table_exists($pdo, $table)) {
        return -1;
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM ' . be_gate2_status_identifier($table) . ' WHERE ' . $where);
    $statement->execute($params);
    return (int)$statement->fetchColumn();
}

function be_gate2_status_table_metadata(PDO $pdo, string $table): ?array
{
    $statement = $pdo->prepare(
        'SELECT TABLE_TYPE, ENGINE, TABLE_COLLATION
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function be_gate2_status_column_metadata(PDO $pdo, string $table, string $column): ?array
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_KEY
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function be_gate2_status_constraint_matches(PDO $pdo, string $constraint): array
{
    $statement = $pdo->prepare(
        'SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
         FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :constraint_name
         ORDER BY TABLE_NAME'
    );
    $statement->execute(['constraint_name' => $constraint]);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function be_gate2_status_orphan_count(PDO $pdo, array $spec): int
{
    foreach (['child_table', 'child_column', 'parent_table', 'parent_column'] as $field) {
        if (!isset($spec[$field]) || !is_string($spec[$field])) {
            return -1;
        }
    }
    if (!be_gate2_status_table_exists($pdo, $spec['child_table']) || !be_gate2_status_table_exists($pdo, $spec['parent_table'])) {
        return -1;
    }
    $sql = 'SELECT COUNT(*) FROM ' . be_gate2_status_identifier($spec['child_table']) . ' child_row'
        . ' LEFT JOIN ' . be_gate2_status_identifier($spec['parent_table']) . ' parent_row'
        . ' ON parent_row.' . be_gate2_status_identifier($spec['parent_column'])
        . ' = child_row.' . be_gate2_status_identifier($spec['child_column'])
        . ' WHERE child_row.' . be_gate2_status_identifier($spec['child_column']) . ' IS NOT NULL'
        . ' AND parent_row.' . be_gate2_status_identifier($spec['parent_column']) . ' IS NULL';
    return (int)$pdo->query($sql)->fetchColumn();
}

function be_gate2_status_columns_compatible(?array $child, ?array $parent): bool
{
    if ($child === null || $parent === null) {
        return false;
    }
    foreach (['COLUMN_TYPE', 'CHARACTER_SET_NAME', 'COLLATION_NAME'] as $field) {
        if (($child[$field] ?? null) !== ($parent[$field] ?? null)) {
            return false;
        }
    }
    return true;
}

function be_gate2_status_fk_preflight(PDO $pdo, array $spec): array
{
    $childTable = (string)$spec['child_table'];
    $parentTable = (string)$spec['parent_table'];
    $constraint = (string)$spec['constraint'];
    $childTableMetadata = be_gate2_status_table_metadata($pdo, $childTable);
    $parentTableMetadata = be_gate2_status_table_metadata($pdo, $parentTable);
    $childColumn = be_gate2_status_column_metadata($pdo, $childTable, (string)$spec['child_column']);
    $parentColumn = be_gate2_status_column_metadata($pdo, $parentTable, (string)$spec['parent_column']);
    $constraintMatches = be_gate2_status_constraint_matches($pdo, $constraint);
    $orphanRows = be_gate2_status_orphan_count($pdo, $spec);
    $expectedConstraintPresent = false;
    $constraintNameCollision = false;
    foreach ($constraintMatches as $match) {
        if (($match['TABLE_NAME'] ?? '') === $childTable && ($match['REFERENCED_TABLE_NAME'] ?? '') === $parentTable) {
            $expectedConstraintPresent = true;
        } else {
            $constraintNameCollision = true;
        }
    }
    $engineCompatible = ($childTableMetadata['ENGINE'] ?? null) === 'InnoDB'
        && ($parentTableMetadata['ENGINE'] ?? null) === 'InnoDB';
    $columnCompatible = be_gate2_status_columns_compatible($childColumn, $parentColumn);
    $blockingReasons = [];
    if ($childTableMetadata === null) {
        $blockingReasons[] = 'child_table_missing';
    }
    if ($parentTableMetadata === null) {
        $blockingReasons[] = 'parent_table_missing';
    }
    if ($childColumn === null) {
        $blockingReasons[] = 'child_column_missing';
    }
    if ($parentColumn === null) {
        $blockingReasons[] = 'parent_column_missing';
    }
    if (!$engineCompatible) {
        $blockingReasons[] = 'engine_incompatible';
    }
    if (!$columnCompatible) {
        $blockingReasons[] = 'column_incompatible';
    }
    if ($orphanRows > 0) {
        $blockingReasons[] = 'orphan_rows';
    }
    if ($constraintNameCollision) {
        $blockingReasons[] = 'constraint_name_collision';
    }
    return [
        'constraint' => $constraint,
        'child_table' => $childTable,
        'parent_table' => $parentTable,
        'child_rows' => be_gate2_status_count($pdo, $childTable),
        'parent_rows' => be_gate2_status_count($pdo, $parentTable),
        'orphan_rows' => $orphanRows,
        'expected_constraint_present' => $expectedConstraintPresent,
        'constraint_name_collision' => $constraintNameCollision,
        'constraint_matches' => $constraintMatches,
        'engine_compatible' => $engineCompatible,
        'column_compatible' => $columnCompatible,
        'child_table_metadata' => $childTableMetadata,
        'parent_table_metadata' => $parentTableMetadata,
        'child_column_metadata' => $childColumn,
        'parent_column_metadata' => $parentColumn,
        'blocking_reasons' => $blockingReasons,
        'can_add_constraint' => $blockingReasons === [],
    ];
}

$pdo = be_db();
$prefix = 'GATE2_SYNTHETIC_199_%';
$operationPrefix = 'gate2:199:staging-%';
$candidateSubquery = 'candidate_id IN (
    SELECT id FROM startpartner_candidates WHERE organization_name LIKE :candidate_prefix
)';

$migrations = [
    '009' => be_gate2_status_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :migration_key',
        ['migration_key' => '009_control_center_runtime_schema']
    ),
    '010' => be_gate2_status_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :migration_key',
        ['migration_key' => '010_startpartner_gate2_qualification_capacity']
    ),
];

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

$foreignKeySpecs = [
    [
        'constraint' => 'fk_control_case_events_case',
        'child_table' => 'control_case_events',
        'child_column' => 'case_id',
        'parent_table' => 'control_cases',
        'parent_column' => 'id',
    ],
    [
        'constraint' => 'fk_control_operations_case',
        'child_table' => 'control_operations',
        'child_column' => 'case_id',
        'parent_table' => 'control_cases',
        'parent_column' => 'id',
    ],
    [
        'constraint' => 'fk_control_editorial_feedback_case',
        'child_table' => 'control_editorial_feedback',
        'child_column' => 'case_id',
        'parent_table' => 'control_cases',
        'parent_column' => 'id',
    ],
];
$foreignKeyPreflight = [];
foreach ($foreignKeySpecs as $spec) {
    $foreignKeyPreflight[(string)$spec['constraint']] = be_gate2_status_fk_preflight($pdo, $spec);
}
$foreignKeyBlockers = [];
foreach ($foreignKeyPreflight as $constraint => $diagnostic) {
    if (($diagnostic['can_add_constraint'] ?? false) !== true) {
        $foreignKeyBlockers[$constraint] = $diagnostic['blocking_reasons'] ?? ['unknown'];
    }
}

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
    'migrations' => $migrations,
    'residue' => $residue + ['total' => $totalResidue],
    'missing_tables' => $missingTables,
    'positive_residue' => $positiveResidue,
    'migration_009_preflight' => [
        'foreign_keys' => $foreignKeyPreflight,
        'blockers' => $foreignKeyBlockers,
        'ready' => $foreignKeyBlockers === [],
    ],
    'checked_at' => gmdate(DateTimeInterface::ATOM),
]);
