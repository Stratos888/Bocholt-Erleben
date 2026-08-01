<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_gate4_domain.php';

const BE_GATE4_MARKER_CLEANUP_KEY = '241_gate4_staging_lifecycle_completed';
const BE_GATE4_MARKER_CLEANUP_LOCK = 'bocholt_gate4_staging_final_241';
const BE_GATE4_MARKER_CLEANUP_USER_AGENT = 'Bocholt-Erleben-Deploy-Smoke/1.0';
const BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID = '24100000-0000-4000-8000-000000000101';
const BE_GATE4_MARKER_CLEANUP_PILOT_ID = '24100000-0000-4000-8000-000000000102';
const BE_GATE4_MARKER_CLEANUP_EMAIL = 'gate4-241-staging@example.invalid';
const BE_GATE4_MARKER_CLEANUP_SESSION_TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_gate4_marker_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function be_gate4_marker_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    $safeTable = str_replace('`', '``', $table);
    $statement = $pdo->prepare("SELECT COUNT(*) FROM `{$safeTable}` WHERE {$where}");
    $statement->execute($params);
    return (int)$statement->fetchColumn();
}

function be_gate4_marker_locked_counts(PDO $pdo): array
{
    $counts = [];
    foreach ([
        'organizers',
        'organizer_magic_links',
        'organizer_portal_sessions',
        'submissions',
        'subscriptions',
        'publication_entitlements',
        'publication_consumptions',
    ] as $table) {
        $counts[$table] = be_gate4_marker_count($pdo, $table);
    }
    return $counts;
}

function be_gate4_marker_residue(PDO $pdo): array
{
    $caseStatement = $pdo->prepare(
        "SELECT id FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $caseStatement->execute(['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]);
    $caseIds = array_map('strval', $caseStatement->fetchAll(PDO::FETCH_COLUMN));
    $caseChildren = 0;
    foreach ($caseIds as $caseId) {
        foreach (['control_case_events', 'control_operations', 'control_editorial_feedback'] as $table) {
            $caseChildren += be_gate4_marker_count(
                $pdo,
                $table,
                'case_id = :case_id',
                ['case_id' => $caseId]
            );
        }
    }
    $counts = [
        'candidate' => be_gate4_marker_count($pdo, 'startpartner_candidates', 'id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'contacts' => be_gate4_marker_count($pdo, 'startpartner_candidate_contacts', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'qualifications' => be_gate4_marker_count($pdo, 'startpartner_candidate_qualifications', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'decisions' => be_gate4_marker_count($pdo, 'startpartner_candidate_decisions', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'reservations' => be_gate4_marker_count($pdo, 'startpartner_candidate_reservations', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'waitlist' => be_gate4_marker_count($pdo, 'startpartner_candidate_waitlist', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'candidate_operations' => be_gate4_marker_count($pdo, 'startpartner_candidate_operations', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'candidate_events' => be_gate4_marker_count($pdo, 'startpartner_candidate_events', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'terms' => be_gate4_marker_count($pdo, 'startpartner_pilot_terms_acceptances', 'candidate_id = :candidate_id', ['candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]),
        'pilots' => be_gate4_marker_count(
            $pdo,
            'startpartner_pilots',
            'id = :pilot_id OR candidate_id = :candidate_id',
            ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID, 'candidate_id' => BE_GATE4_MARKER_CLEANUP_CANDIDATE_ID]
        ),
        'scopes' => be_gate4_marker_count($pdo, 'startpartner_pilot_scopes', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'pilot_entitlements' => be_gate4_marker_count($pdo, 'startpartner_pilot_entitlements', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'pilot_events' => be_gate4_marker_count($pdo, 'startpartner_pilot_events', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'onboarding_items' => be_gate4_marker_count($pdo, 'startpartner_pilot_onboarding_items', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'content_links' => be_gate4_marker_count($pdo, 'startpartner_pilot_content_links', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'measurements' => be_gate4_marker_count($pdo, 'startpartner_pilot_measurement_preflights', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'distributions' => be_gate4_marker_count($pdo, 'startpartner_pilot_distribution_commitments', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'usages' => be_gate4_marker_count($pdo, 'startpartner_pilot_usages', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_MARKER_CLEANUP_PILOT_ID]),
        'portal_sessions' => be_gate4_marker_count(
            $pdo,
            'organizer_portal_sessions',
            'session_token_hash = :session_hash',
            ['session_hash' => hash('sha256', BE_GATE4_MARKER_CLEANUP_SESSION_TOKEN)]
        ),
        'submissions' => be_gate4_marker_count(
            $pdo,
            'submissions',
            "email_snapshot = :email AND payment_kind = 'startpartner_pilot'",
            ['email' => BE_GATE4_MARKER_CLEANUP_EMAIL]
        ),
        'control_cases' => count($caseIds),
        'control_case_children' => $caseChildren,
        'organizer' => be_gate4_marker_count(
            $pdo,
            'organizers',
            'email_normalized = :email',
            ['email' => BE_GATE4_MARKER_CLEANUP_EMAIL]
        ),
    ];
    $counts['total'] = array_sum($counts);
    return $counts;
}

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

$pdo = null;
$lockHeld = false;
try {
    be_gate4_marker_assert(
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '') === BE_GATE4_MARKER_CLEANUP_USER_AGENT,
        'Gate-4 marker cleanup is restricted to the deploy smoke user agent.'
    );
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    be_gate4_marker_assert(is_array($input), 'Invalid marker cleanup request.');
    $expectedBuild = trim((string)($input['expected_build'] ?? ''));
    be_gate4_marker_assert(
        preg_match('/^[0-9a-f]{12}$/', $expectedBuild) === 1,
        'Expected build is invalid.'
    );
    $buildPath = dirname(__DIR__, 3) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
    be_gate4_marker_assert($deployedBuild === $expectedBuild, 'Marker cleanup build does not match deployed build.');

    $pdo = be_db();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);
    be_startpartner_gate4_require_schema($pdo);

    $lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $lockStatement->execute(['lock_name' => BE_GATE4_MARKER_CLEANUP_LOCK]);
    $lockHeld = (int)$lockStatement->fetchColumn() === 1;
    be_gate4_marker_assert($lockHeld, 'Gate-4 marker cleanup lock is already held.');

    $markerBefore = be_gate4_marker_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :marker_key',
        ['marker_key' => BE_GATE4_MARKER_CLEANUP_KEY]
    );
    be_gate4_marker_assert(in_array($markerBefore, [0, 1], true), 'Gate-4 marker count is invalid.');
    if ($markerBefore === 0) {
        $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $releaseStatement->execute(['lock_name' => BE_GATE4_MARKER_CLEANUP_LOCK]);
        $lockHeld = false;
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => 'no_marker',
                'build' => $deployedBuild,
                'marker' => BE_GATE4_MARKER_CLEANUP_KEY,
                'marker_before' => 0,
                'marker_after' => 0,
            ],
        ]);
    }

    $residueBefore = be_gate4_marker_residue($pdo);
    be_gate4_marker_assert((int)$residueBefore['total'] === 0, 'Completion marker exists with synthetic residue.');
    $lockedBefore = be_gate4_marker_locked_counts($pdo);
    $capacityBefore = be_startpartner_gate4_capacity($pdo);

    $deleteStatement = $pdo->prepare(
        'DELETE FROM app_schema_migrations WHERE migration_key = :marker_key'
    );
    $deleteStatement->execute(['marker_key' => BE_GATE4_MARKER_CLEANUP_KEY]);
    be_gate4_marker_assert($deleteStatement->rowCount() === 1, 'Completion marker was not removed exactly once.');

    $markerAfter = be_gate4_marker_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :marker_key',
        ['marker_key' => BE_GATE4_MARKER_CLEANUP_KEY]
    );
    $residueAfter = be_gate4_marker_residue($pdo);
    $lockedAfter = be_gate4_marker_locked_counts($pdo);
    $capacityAfter = be_startpartner_gate4_capacity($pdo);
    be_gate4_marker_assert($markerAfter === 0, 'Completion marker remains after cleanup.');
    be_gate4_marker_assert((int)$residueAfter['total'] === 0, 'Synthetic residue exists after marker cleanup.');
    be_gate4_marker_assert($lockedAfter === $lockedBefore, 'Locked owner counts changed during marker cleanup.');
    be_gate4_marker_assert($capacityAfter === $capacityBefore, 'Startpartner capacity changed during marker cleanup.');

    $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $releaseStatement->execute(['lock_name' => BE_GATE4_MARKER_CLEANUP_LOCK]);
    $lockHeld = false;
    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'action' => 'cleaned',
            'already_completed' => true,
            'build' => $deployedBuild,
            'marker' => BE_GATE4_MARKER_CLEANUP_KEY,
            'marker_cleanup' => [
                'before' => $markerBefore,
                'after' => $markerAfter,
                'residue_before' => $residueBefore,
                'residue_after' => $residueAfter,
                'locked_before' => $lockedBefore,
                'locked_after' => $lockedAfter,
                'capacity_before' => $capacityBefore,
                'capacity_after' => $capacityAfter,
            ],
            'residue' => $residueAfter,
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $lockHeld) {
        try {
            $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $releaseStatement->execute(['lock_name' => BE_GATE4_MARKER_CLEANUP_LOCK]);
        } catch (Throwable) {
            // Connection close also releases the advisory lock.
        }
    }
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Gate-4 marker cleanup failed.',
        'error_message' => $error::class . ': ' . $error->getMessage(),
    ]);
}
