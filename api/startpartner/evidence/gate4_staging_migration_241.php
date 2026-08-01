<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_gate4_domain.php';

const BE_GATE4_MIGRATION_KEY = '012_startpartner_gate4_onboarding_content_activation';
const BE_GATE4_MIGRATION_FILE = '012_startpartner_gate4_onboarding_content_activation.sql';
const BE_GATE4_MIGRATION_LOCK = 'bocholt_gate4_migration_012_241';
const BE_GATE4_MIGRATION_USER_AGENT = 'Bocholt-Erleben-Deploy-Smoke/1.0';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_gate4_migration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function be_gate4_migration_scalar(PDOStatement $statement): mixed
{
    $value = $statement->fetchColumn();
    $statement->closeCursor();
    return $value;
}

function be_gate4_migration_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    $safeTable = str_replace('`', '``', $table);
    $statement = $pdo->prepare("SELECT COUNT(*) FROM `{$safeTable}` WHERE {$where}");
    $statement->execute($params);
    return (int)be_gate4_migration_scalar($statement);
}

function be_gate4_migration_locked_counts(PDO $pdo): array
{
    $counts = [];
    foreach ([
        'organizers',
        'subscriptions',
        'organizer_magic_links',
        'organizer_portal_sessions',
        'submissions',
        'publication_entitlements',
        'publication_consumptions',
    ] as $table) {
        $counts[$table] = be_gate4_migration_count($pdo, $table);
    }
    return $counts;
}

function be_gate4_migration_split_sql(string $sql): array
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

function be_gate4_migration_execute_statement(PDO $pdo, string $sql): void
{
    $statement = $pdo->prepare($sql);
    try {
        $statement->execute();
        do {
            if ($statement->columnCount() > 0) {
                $statement->fetchAll(PDO::FETCH_NUM);
            }
        } while ($statement->nextRowset());
    } finally {
        $statement->closeCursor();
    }
}

function be_gate4_migration_apply(PDO $pdo): array
{
    $path = dirname(__DIR__, 2) . '/sql/' . BE_GATE4_MIGRATION_FILE;
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Migration 012 could not be read.');
    }
    $statements = be_gate4_migration_split_sql($sql);
    foreach ($statements as $index => $statement) {
        try {
            be_gate4_migration_execute_statement($pdo, $statement);
        } catch (Throwable $error) {
            $excerpt = preg_replace('/\s+/', ' ', trim($statement));
            throw new RuntimeException(
                'Migration 012 failed at statement ' . ($index + 1) . ': '
                . substr((string)$excerpt, 0, 240) . ' | ' . $error->getMessage(),
                0,
                $error
            );
        }
    }
    return ['file' => BE_GATE4_MIGRATION_FILE, 'statements' => count($statements)];
}

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    be_gate4_migration_assert(
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '') === BE_GATE4_MIGRATION_USER_AGENT,
        'Gate-4 migration is restricted to the deploy smoke user agent.'
    );
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    be_gate4_migration_assert(is_array($input), 'Invalid migration request.');
    $expectedBuild = trim((string)($input['expected_build'] ?? ''));
    be_gate4_migration_assert(
        preg_match('/^[0-9a-f]{12}$/', $expectedBuild) === 1,
        'Expected build is invalid.'
    );
    $buildPath = dirname(__DIR__, 3) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
    be_gate4_migration_assert(
        $deployedBuild === $expectedBuild,
        'Migration build does not match deployed build.'
    );

    $pdo = be_db();
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);
    be_gate4_migration_assert(
        be_gate4_migration_count(
            $pdo,
            'app_schema_migrations',
            'migration_key = :migration_key',
            ['migration_key' => '011_startpartner_gate3_terms_organizer_entitlement']
        ) === 1,
        'Required migration 011 is not registered.'
    );

    $lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $lockStatement->execute(['lock_name' => BE_GATE4_MIGRATION_LOCK]);
    $lockAcquired = (int)be_gate4_migration_scalar($lockStatement) === 1;
    be_gate4_migration_assert($lockAcquired, 'Gate-4 migration lock is already held.');

    try {
        $lockedBefore = be_gate4_migration_locked_counts($pdo);
        $migrationCountBefore = be_gate4_migration_count(
            $pdo,
            'app_schema_migrations',
            'migration_key = :migration_key',
            ['migration_key' => BE_GATE4_MIGRATION_KEY]
        );
        $applied = null;
        if ($migrationCountBefore === 0) {
            $applied = be_gate4_migration_apply($pdo);
        }
        $migrationCountAfter = be_gate4_migration_count(
            $pdo,
            'app_schema_migrations',
            'migration_key = :migration_key',
            ['migration_key' => BE_GATE4_MIGRATION_KEY]
        );
        be_gate4_migration_assert(
            $migrationCountAfter === 1,
            'Migration 012 was not registered after execution.'
        );
        $schemaGaps = be_startpartner_gate4_schema_gaps($pdo);
        be_gate4_migration_assert(
            $schemaGaps === [],
            'Gate-4 schema remains incomplete after migration.'
        );
        $lockedAfter = be_gate4_migration_locked_counts($pdo);
        be_gate4_migration_assert(
            $lockedAfter === $lockedBefore,
            'Locked table counts changed during migration 012.'
        );

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'build' => $deployedBuild,
                'migration_key' => BE_GATE4_MIGRATION_KEY,
                'migration_count_before' => $migrationCountBefore,
                'migration_count_after' => $migrationCountAfter,
                'action' => $applied === null ? 'already_applied' : 'applied',
                'applied' => $applied,
                'schema_gaps' => $schemaGaps,
                'locked_counts_before' => $lockedBefore,
                'locked_counts_after' => $lockedAfter,
            ],
        ]);
    } finally {
        $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $releaseStatement->execute(['lock_name' => BE_GATE4_MIGRATION_LOCK]);
        be_gate4_migration_scalar($releaseStatement);
    }
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Gate-4 staging migration failed.',
        'error_message' => $error->getMessage(),
    ]);
}
