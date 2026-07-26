<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

const BE_GATE2_STATUS_TOKEN_HASH = '921e687a88b06ddb2124766a0be0c43e5875309c393f3ed91219470100053243';
const BE_GATE2_COMPLETION_MARKER = '199_gate2_staging_lifecycle_completed';
const BE_GATE2_CLEANUP_LOCK = 'bocholt_gate2_staging_cleanup_199';
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

function be_gate2_status_count(
    PDO $pdo,
    string $table,
    string $where = '1=1',
    array $params = []
): int {
    if (!be_gate2_status_table_exists($pdo, $table)) {
        return -1;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where
    );
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

function be_gate2_status_residue(PDO $pdo): array
{
    $candidatePrefix = 'GATE2_SYNTHETIC_199_%';
    $operationPrefix = 'gate2:199:staging-%';
    $candidateSubquery = 'candidate_id IN (
        SELECT id FROM startpartner_candidates
        WHERE organization_name LIKE :candidate_prefix
    )';
    $residue = [
        'candidates' => be_gate2_status_count(
            $pdo,
            'startpartner_candidates',
            'organization_name LIKE :candidate_prefix',
            ['candidate_prefix' => $candidatePrefix]
        ),
        'contacts' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_contacts',
            $candidateSubquery,
            ['candidate_prefix' => $candidatePrefix]
        ),
        'events' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_events',
            $candidateSubquery,
            ['candidate_prefix' => $candidatePrefix]
        ),
        'qualifications' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_qualifications',
            $candidateSubquery,
            ['candidate_prefix' => $candidatePrefix]
        ),
        'decisions' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_decisions',
            $candidateSubquery,
            ['candidate_prefix' => $candidatePrefix]
        ),
        'reservations' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_reservations',
            $candidateSubquery,
            ['candidate_prefix' => $candidatePrefix]
        ),
        'waitlist' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_waitlist',
            $candidateSubquery,
            ['candidate_prefix' => $candidatePrefix]
        ),
        'operations' => be_gate2_status_count(
            $pdo,
            'startpartner_candidate_operations',
            'operation_id LIKE :operation_prefix',
            ['operation_prefix' => $operationPrefix]
        ),
        'control_cases' => be_gate2_status_count(
            $pdo,
            'control_cases',
            "source_system = 'startpartner_candidate'
             AND (
                title LIKE :title_prefix
                OR object_title LIKE :object_title_prefix
             )",
            [
                'title_prefix' => '%Startpartner prüfen: GATE2_SYNTHETIC_199_%',
                'object_title_prefix' => 'GATE2_SYNTHETIC_199_%',
            ]
        ),
    ];
    $residue['total'] = array_sum(array_filter(
        $residue,
        static fn(int $count): bool => $count > 0
    ));
    return $residue;
}

function be_gate2_status_remove_completion_marker(PDO $pdo): array
{
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $lockStatement->execute(['lock_name' => BE_GATE2_CLEANUP_LOCK]);
    if ((int)be_gate2_status_scalar($lockStatement) !== 1) {
        throw new RuntimeException('Gate 2 cleanup lock is unavailable.');
    }

    try {
        $before = be_gate2_status_migration_count($pdo, BE_GATE2_COMPLETION_MARKER);
        if (!in_array($before, [0, 1], true)) {
            throw new RuntimeException('Gate 2 completion marker count is invalid.');
        }

        $deletedRows = 0;
        if ($before === 1) {
            $statement = $pdo->prepare(
                'DELETE FROM app_schema_migrations WHERE migration_key = :marker_key'
            );
            $statement->execute(['marker_key' => BE_GATE2_COMPLETION_MARKER]);
            $deletedRows = $statement->rowCount();
        }
        $after = be_gate2_status_migration_count($pdo, BE_GATE2_COMPLETION_MARKER);
        if ($after !== 0) {
            throw new RuntimeException('Gate 2 completion marker was not removed.');
        }

        return [
            'status' => $before === 1 ? 'removed' : 'already_removed',
            'before' => $before,
            'deleted_rows' => $deletedRows,
            'after' => $after,
        ];
    } finally {
        $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $releaseStatement->execute(['lock_name' => BE_GATE2_CLEANUP_LOCK]);
        be_gate2_status_scalar($releaseStatement);
    }
}

$pdo = be_db();
$markerCleanup = $deployAuthorized
    ? be_gate2_status_remove_completion_marker($pdo)
    : [
        'status' => 'read_only',
        'before' => be_gate2_status_migration_count($pdo, BE_GATE2_COMPLETION_MARKER),
        'deleted_rows' => 0,
        'after' => be_gate2_status_migration_count($pdo, BE_GATE2_COMPLETION_MARKER),
    ];

$migrations = [];
foreach (BE_GATE2_MIGRATIONS as $number => $migrationKey) {
    $migrations[$number] = be_gate2_status_migration_count($pdo, $migrationKey);
}
$residue = be_gate2_status_residue($pdo);
$missingTables = array_keys(array_filter(
    $residue,
    static fn(int $count): bool => $count < 0
));
$positiveResidue = array_filter(
    $residue,
    static fn(int $count): bool => $count > 0
);
$lifecycleEndpointPresent = is_file(__DIR__ . '/gate2-staging-lifecycle-199.php');
$status = $migrations['009'] === 1
    && $migrations['010'] === 1
    && $missingTables === []
    && ($residue['total'] ?? -1) === 0
    && (int)$markerCleanup['after'] === 0
    && $lifecycleEndpointPresent === false
        ? 'PASS'
        : 'FAIL';

be_json_response($status === 'PASS' ? 200 : 500, [
    'status' => $status,
    'workpack_issue' => 199,
    'environment' => be_app_env_value(),
    'deployed_build' => $deployedBuild,
    'cleanup_action' => $deployAuthorized ? 'remove_completion_marker' : 'read_only',
    'marker_cleanup' => $markerCleanup,
    'lifecycle_endpoint_present' => $lifecycleEndpointPresent,
    'migrations' => $migrations,
    'residue' => $residue,
    'missing_tables' => $missingTables,
    'positive_residue' => $positiveResidue,
    'checked_at' => gmdate(DateTimeInterface::ATOM),
]);
