<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (be_app_env_value() !== 'staging') {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
}

$userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$expectedBuild = trim((string)($_SERVER['HTTP_X_BE_EXPECTED_BUILD'] ?? ''));
$buildPath = dirname(__DIR__, 2) . '/meta/build.txt';
$deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
if (
    $userAgent !== 'Bocholt-Erleben-Deploy-Smoke/1.0' ||
    $expectedBuild === '' ||
    $deployedBuild === '' ||
    !hash_equals($deployedBuild, $expectedBuild)
) {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
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
    $statement = $pdo->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where);
    $statement->execute($params);
    return (int)$statement->fetchColumn();
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
    'checked_at' => gmdate(DateTimeInterface::ATOM),
]);
