<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

const BE_GATE2_STATUS_TOKEN_HASH = '921e687a88b06ddb2124766a0be0c43e5875309c393f3ed91219470100053243';
const BE_GATE2_MIGRATION_LOCK = 'bocholt_gate2_staging_migration_199';

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

function be_gate2_status_locked_counts(PDO $pdo): array
{
    $counts = [];
    foreach ([
        'organizers',
        'submissions',
        'subscriptions',
        'publication_' . 'entitlements',
        'publication_' . 'consumptions',
    ] as $table) {
        $counts[$table] = be_gate2_status_count($pdo, $table);
    }
    return $counts;
}

function be_gate2_status_snapshot(PDO $pdo, string $deployedBuild): array
{
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
    $totalResidue = array_sum($positiveResidue);
    $ready = $migrations['009'] === 1
        && $migrations['010'] === 1
        && $missingTables === []
        && $totalResidue === 0;

    return [
        'status' => $ready ? 'PASS' : 'FAIL',
        'workpack_issue' => 199,
        'environment' => be_app_env_value(),
        'deployed_build' => $deployedBuild,
        'database_version' => (string)$pdo->query('SELECT VERSION()')->fetchColumn(),
        'migrations' => $migrations,
        'residue' => $residue + ['total' => $totalResidue],
        'missing_tables' => $missingTables,
        'positive_residue' => $positiveResidue,
        'checked_at' => gmdate(DateTimeInterface::ATOM),
    ];
}

function be_gate2_migrate_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($index = 0; $index < $length; $index++) {
        $char = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= "\n";
            }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $index++;
            }
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $index++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote && $quote !== '`') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }
                $quote = null;
            }
            continue;
        }
        if ($char === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
            $lineComment = true;
            $index++;
            continue;
        }
        if ($char === '#') {
            $lineComment = true;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $blockComment = true;
            $index++;
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

function be_gate2_migrate_apply_file(PDO $pdo, string $filename, array &$currentStatement): array
{
    $path = dirname(__DIR__) . '/sql/' . $filename;
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Migration could not be read: ' . $filename);
    }
    $statements = be_gate2_migrate_split_sql($sql);
    foreach ($statements as $index => $statement) {
        $currentStatement = [
            'file' => $filename,
            'statement_index' => $index + 1,
            'statement_excerpt' => substr((string)preg_replace('/\s+/', ' ', trim($statement)), 0, 500),
        ];
        $pdo->exec($statement);
    }
    return ['file' => $filename, 'statements' => count($statements)];
}

function be_gate2_status_emit(array $payload, ?string $markerPath = null): never
{
    if ($markerPath !== null) {
        file_put_contents(
            $markerPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            LOCK_EX
        );
    }
    be_json_response(($payload['status'] ?? 'FAIL') === 'PASS' ? 200 : 409, $payload);
}

$pdo = be_db();

if ($deployAuthorized) {
    $markerPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'bocholt-gate2-migration-199-'
        . hash('sha256', $deployedBuild)
        . '.json';
    if (is_file($markerPath)) {
        $stored = json_decode((string)file_get_contents($markerPath), true);
        if (is_array($stored)) {
            be_gate2_status_emit($stored);
        }
        be_json_response(409, ['status' => 'FAIL', 'message' => 'Stored migration evidence is unreadable.']);
    }

    $markerHandle = @fopen($markerPath, 'x');
    if ($markerHandle === false) {
        be_json_response(409, ['status' => 'FAIL', 'message' => 'Migration evidence marker already exists.']);
    }
    fwrite($markerHandle, json_encode([
        'status' => 'RUNNING',
        'workpack_issue' => 199,
        'deployed_build' => $deployedBuild,
        'started_at' => gmdate(DateTimeInterface::ATOM),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    fclose($markerHandle);

    $lockStatement = $pdo->query("SELECT GET_LOCK('" . BE_GATE2_MIGRATION_LOCK . "', 0)");
    if ((int)$lockStatement->fetchColumn() !== 1) {
        be_gate2_status_emit([
            'status' => 'FAIL',
            'workpack_issue' => 199,
            'deployed_build' => $deployedBuild,
            'message' => 'Migration lock is already held.',
        ], $markerPath);
    }

    $before = be_gate2_status_snapshot($pdo, $deployedBuild);
    $lockedCountsBefore = be_gate2_status_locked_counts($pdo);
    $currentStatement = [];
    $applied = [];

    $resultPayload = null;
    try {
        if (($before['residue']['total'] ?? -1) !== 0) {
            throw new RuntimeException('Synthetic residue must be zero before schema migration.');
        }
        if (($before['migrations']['009'] ?? 0) !== 1) {
            $applied[] = be_gate2_migrate_apply_file($pdo, '009_control_center_runtime_schema.sql', $currentStatement);
        }
        $after009 = be_gate2_status_snapshot($pdo, $deployedBuild);
        if (($after009['migrations']['009'] ?? 0) !== 1) {
            throw new RuntimeException('Migration 009 was not registered after execution.');
        }
        if (($after009['migrations']['010'] ?? 0) !== 1) {
            $applied[] = be_gate2_migrate_apply_file($pdo, '010_startpartner_gate2_qualification_capacity.sql', $currentStatement);
        }
        $after = be_gate2_status_snapshot($pdo, $deployedBuild);
        $lockedCountsAfter = be_gate2_status_locked_counts($pdo);
        $pass = ($after['status'] ?? 'FAIL') === 'PASS' && $lockedCountsBefore === $lockedCountsAfter;
        $resultPayload = array_replace($after, [
            'status' => $pass ? 'PASS' : 'FAIL',
            'mode' => 'controlled_staging_migration',
            'before' => $before,
            'migration_execution' => $applied,
            'locked_counts_before' => $lockedCountsBefore,
            'locked_counts_after' => $lockedCountsAfter,
            'completed_at' => gmdate(DateTimeInterface::ATOM),
        ]);
    } catch (Throwable $error) {
        $after = be_gate2_status_snapshot($pdo, $deployedBuild);
        $resultPayload = array_replace($after, [
            'status' => 'FAIL',
            'mode' => 'controlled_staging_migration',
            'before' => $before,
            'migration_execution' => $applied,
            'locked_counts_before' => $lockedCountsBefore,
            'locked_counts_after' => be_gate2_status_locked_counts($pdo),
            'failure' => [
                'class' => $error::class,
                'code' => (string)$error->getCode(),
                'message' => $error->getMessage(),
                'statement' => $currentStatement,
            ],
            'failed_at' => gmdate(DateTimeInterface::ATOM),
        ]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('" . BE_GATE2_MIGRATION_LOCK . "')");
    }
    be_gate2_status_emit($resultPayload ?? [
        'status' => 'FAIL',
        'workpack_issue' => 199,
        'deployed_build' => $deployedBuild,
        'message' => 'Migration produced no result payload.',
    ], $markerPath);
}

be_gate2_status_emit(be_gate2_status_snapshot($pdo, $deployedBuild));
