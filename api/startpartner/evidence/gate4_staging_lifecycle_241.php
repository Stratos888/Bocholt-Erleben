<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_gate4_domain.php';

const BE_GATE4_E4_CANDIDATE_ID = '24100000-0000-4000-8000-000000000101';
const BE_GATE4_E4_PILOT_ID = '24100000-0000-4000-8000-000000000102';
const BE_GATE4_E4_ENTITLEMENT_ID = '24100000-0000-4000-8000-000000000103';
const BE_GATE4_E4_EMAIL = 'gate4-241-staging@example.invalid';
const BE_GATE4_E4_ORGANIZATION = 'GATE4_SYNTHETIC_241_E4_ORGANISATION';
const BE_GATE4_E4_SESSION_TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const BE_GATE4_E4_MARKER = '241_gate4_staging_lifecycle_completed';
const BE_GATE4_E4_LOCK = 'bocholt_gate4_staging_final_241';
const BE_GATE4_E4_USER_AGENT = 'Bocholt-Erleben-Deploy-Smoke/1.0';
const BE_GATE4_E4_ORIGIN = 'https://staging.bocholt-erleben.de';
const BE_GATE4_E4_OPERATOR = 'Gate 4 E4 Staging Lifecycle #241';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_gate4_e4_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function be_gate4_e4_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function be_gate4_e4_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    $safeTable = str_replace('`', '``', $table);
    return (int)be_gate4_e4_scalar($pdo, "SELECT COUNT(*) FROM `{$safeTable}` WHERE {$where}", $params);
}

function be_gate4_e4_locked_counts(PDO $pdo): array
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
        $counts[$table] = be_gate4_e4_count($pdo, $table);
    }
    return $counts;
}

function be_gate4_e4_case_ids(PDO $pdo): array
{
    $statement = $pdo->prepare(
        "SELECT id FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $statement->execute(['candidate_id' => BE_GATE4_E4_CANDIDATE_ID]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function be_gate4_e4_residue(PDO $pdo): array
{
    $candidateId = BE_GATE4_E4_CANDIDATE_ID;
    $pilotId = BE_GATE4_E4_PILOT_ID;
    $caseIds = be_gate4_e4_case_ids($pdo);
    $caseChildren = 0;
    foreach ($caseIds as $caseId) {
        foreach (['control_case_events', 'control_operations', 'control_editorial_feedback'] as $table) {
            $caseChildren += be_gate4_e4_count($pdo, $table, 'case_id = :case_id', ['case_id' => $caseId]);
        }
    }
    $counts = [
        'candidate' => be_gate4_e4_count($pdo, 'startpartner_candidates', 'id = :candidate_id', ['candidate_id' => $candidateId]),
        'contacts' => be_gate4_e4_count($pdo, 'startpartner_candidate_contacts', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'qualifications' => be_gate4_e4_count($pdo, 'startpartner_candidate_qualifications', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'decisions' => be_gate4_e4_count($pdo, 'startpartner_candidate_decisions', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'reservations' => be_gate4_e4_count($pdo, 'startpartner_candidate_reservations', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'waitlist' => be_gate4_e4_count($pdo, 'startpartner_candidate_waitlist', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'candidate_operations' => be_gate4_e4_count($pdo, 'startpartner_candidate_operations', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'candidate_events' => be_gate4_e4_count($pdo, 'startpartner_candidate_events', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'terms' => be_gate4_e4_count($pdo, 'startpartner_pilot_terms_acceptances', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'pilots' => be_gate4_e4_count($pdo, 'startpartner_pilots', 'id = :pilot_id OR candidate_id = :candidate_id', ['pilot_id' => $pilotId, 'candidate_id' => $candidateId]),
        'scopes' => be_gate4_e4_count($pdo, 'startpartner_pilot_scopes', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'pilot_entitlements' => be_gate4_e4_count($pdo, 'startpartner_pilot_entitlements', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'pilot_events' => be_gate4_e4_count($pdo, 'startpartner_pilot_events', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'onboarding_items' => be_gate4_e4_count($pdo, 'startpartner_pilot_onboarding_items', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'content_links' => be_gate4_e4_count($pdo, 'startpartner_pilot_content_links', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'measurements' => be_gate4_e4_count($pdo, 'startpartner_pilot_measurement_preflights', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'distributions' => be_gate4_e4_count($pdo, 'startpartner_pilot_distribution_commitments', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'usages' => be_gate4_e4_count($pdo, 'startpartner_pilot_usages', 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]),
        'portal_sessions' => be_gate4_e4_count(
            $pdo,
            'organizer_portal_sessions',
            'session_token_hash = :session_hash',
            ['session_hash' => hash('sha256', BE_GATE4_E4_SESSION_TOKEN)]
        ),
        'submissions' => be_gate4_e4_count(
            $pdo,
            'submissions',
            "email_snapshot = :email AND payment_kind = 'startpartner_pilot'",
            ['email' => BE_GATE4_E4_EMAIL]
        ),
        'control_cases' => count($caseIds),
        'control_case_children' => $caseChildren,
        'organizer' => be_gate4_e4_count($pdo, 'organizers', 'email_normalized = :email', ['email' => BE_GATE4_E4_EMAIL]),
    ];
    $counts['total'] = array_sum($counts);
    return $counts;
}

function be_gate4_e4_cleanup(PDO $pdo): void
{
    $candidateId = BE_GATE4_E4_CANDIDATE_ID;
    $pilotId = BE_GATE4_E4_PILOT_ID;
    $pdo->beginTransaction();
    try {
        foreach (be_gate4_e4_case_ids($pdo) as $caseId) {
            foreach (['control_operations', 'control_editorial_feedback', 'control_case_events'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE case_id = :case_id")->execute(['case_id' => $caseId]);
            }
            $pdo->prepare('DELETE FROM control_cases WHERE id = :case_id')->execute(['case_id' => $caseId]);
        }

        foreach ([
            'startpartner_pilot_usages',
            'startpartner_pilot_measurement_preflights',
            'startpartner_pilot_distribution_commitments',
            'startpartner_pilot_onboarding_items',
            'startpartner_pilot_events',
        ] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE pilot_id = :pilot_id")->execute(['pilot_id' => $pilotId]);
        }
        $pdo->prepare('DELETE FROM startpartner_pilot_content_links WHERE pilot_id = :pilot_id')->execute(['pilot_id' => $pilotId]);
        $pdo->prepare(
            "DELETE FROM submissions WHERE email_snapshot = :email AND payment_kind = 'startpartner_pilot'"
        )->execute(['email' => BE_GATE4_E4_EMAIL]);
        $pdo->prepare('DELETE FROM startpartner_pilot_entitlements WHERE pilot_id = :pilot_id')->execute(['pilot_id' => $pilotId]);
        $pdo->prepare('DELETE FROM startpartner_pilot_scopes WHERE pilot_id = :pilot_id')->execute(['pilot_id' => $pilotId]);
        $pdo->prepare('DELETE FROM startpartner_pilots WHERE id = :pilot_id OR candidate_id = :candidate_id')->execute([
            'pilot_id' => $pilotId,
            'candidate_id' => $candidateId,
        ]);
        $pdo->prepare('DELETE FROM startpartner_pilot_terms_acceptances WHERE candidate_id = :candidate_id')->execute(['candidate_id' => $candidateId]);

        foreach ([
            'startpartner_candidate_operations',
            'startpartner_candidate_events',
            'startpartner_candidate_contacts',
            'startpartner_candidate_qualifications',
            'startpartner_candidate_waitlist',
            'startpartner_candidate_reservations',
            'startpartner_candidate_decisions',
        ] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE candidate_id = :candidate_id")->execute(['candidate_id' => $candidateId]);
        }
        $pdo->prepare('DELETE FROM startpartner_candidates WHERE id = :candidate_id')->execute(['candidate_id' => $candidateId]);
        $pdo->prepare('DELETE FROM organizer_portal_sessions WHERE session_token_hash = :session_hash')->execute([
            'session_hash' => hash('sha256', BE_GATE4_E4_SESSION_TOKEN),
        ]);
        $pdo->prepare('DELETE FROM organizers WHERE email_normalized = :email')->execute(['email' => BE_GATE4_E4_EMAIL]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function be_gate4_e4_seed(PDO $pdo): array
{
    $pdo->prepare(
        "INSERT INTO organizers (organization_name, contact_name, email, email_normalized)
         VALUES (:organization_name, :contact_name, :email, :email)"
    )->execute([
        'organization_name' => BE_GATE4_E4_ORGANIZATION,
        'contact_name' => 'Synthetischer Gate-4-Kontakt',
        'email' => BE_GATE4_E4_EMAIL,
    ]);
    $organizerId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO startpartner_candidates (
            id, source, source_reference, organization_name, organization_name_normalized,
            desired_content_scope, status, status_reason, identity_key, idempotency_key_hash,
            privacy_policy_version, form_version, retention_review_at, revision, assigned_to,
            next_review_at, status_changed_at
         ) VALUES (
            :id, 'targeted_outreach', 'issue-241:staging-e4', :organization_name,
            :organization_name_normalized, 'both', 'accepted_pending_terms',
            'Synthetischer Gate-4-Staging-Nachweis', :identity_key, :idempotency_key_hash,
            'privacy-e4-v1', 'gate4-e4-v1', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY),
            1, :operator, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY), UTC_TIMESTAMP()
         )"
    )->execute([
        'id' => BE_GATE4_E4_CANDIDATE_ID,
        'organization_name' => BE_GATE4_E4_ORGANIZATION,
        'organization_name_normalized' => be_startpartner_normalize_organization(BE_GATE4_E4_ORGANIZATION),
        'identity_key' => hash('sha256', 'gate4-241-e4-identity'),
        'idempotency_key_hash' => hash('sha256', 'gate4-241-e4-idempotency'),
        'operator' => BE_GATE4_E4_OPERATOR,
    ]);
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_contacts (
            candidate_id, contact_name, contact_role, email, email_normalized, is_primary
         ) VALUES (
            :candidate_id, 'Synthetischer Gate-4-Kontakt', 'Pilotkontakt', :email, :email, 1
         )"
    )->execute(['candidate_id' => BE_GATE4_E4_CANDIDATE_ID, 'email' => BE_GATE4_E4_EMAIL]);
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_decisions (
            candidate_id, result, reason, operator_reference, candidate_revision,
            qualification_snapshot_json, capacity_snapshot_json, is_current
         ) VALUES (
            :candidate_id, 'accepted_pending_terms', 'Synthetischer Gate-4-Staging-Nachweis',
            :operator, 1, JSON_OBJECT('ready', TRUE), JSON_OBJECT('active_reservations', 1), 1
         )"
    )->execute(['candidate_id' => BE_GATE4_E4_CANDIDATE_ID, 'operator' => BE_GATE4_E4_OPERATOR]);
    $decisionId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_reservations (
            candidate_id, decision_id, status, starts_at, ends_at,
            capacity_snapshot_json, operator_reference
         ) VALUES (
            :candidate_id, :decision_id, 'active', UTC_TIMESTAMP(),
            DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 DAY), JSON_OBJECT('active_reservations', 1), :operator
         )"
    )->execute([
        'candidate_id' => BE_GATE4_E4_CANDIDATE_ID,
        'decision_id' => $decisionId,
        'operator' => BE_GATE4_E4_OPERATOR,
    ]);
    $reservationId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO startpartner_pilot_terms_acceptances (
            candidate_id, decision_id, terms_version, terms_reference, terms_digest,
            accepting_person, accepting_organization, accepted_at, confirmation_channel,
            service_scope_json, source_care_json, reach_contribution_json,
            no_automatic_paid_renewal, operator_reference
         ) VALUES (
            :candidate_id, :decision_id, 'gate4-e4-v1', 'repo://issue-241/staging-e4', :digest,
            'Synthetischer Gate-4-Kontakt', :organization, UTC_TIMESTAMP(), 'operator_recorded',
            JSON_OBJECT('target_plan_keys', JSON_ARRAY('active','activity_basic')),
            JSON_OBJECT('description','Automatische Quelle und Veranstalterzugang'),
            JSON_OBJECT('description','Newsletter und eigener Kanal'), 1, :operator
         )"
    )->execute([
        'candidate_id' => BE_GATE4_E4_CANDIDATE_ID,
        'decision_id' => $decisionId,
        'digest' => hash('sha256', 'gate4-241-staging-e4-terms'),
        'organization' => BE_GATE4_E4_ORGANIZATION,
        'operator' => BE_GATE4_E4_OPERATOR,
    ]);
    $termsId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO startpartner_pilots (
            id, candidate_id, organizer_id, terms_acceptance_id, reservation_id,
            cohort_key, status, target_plan_keys_json, internal_owner,
            partner_contact_name_snapshot, partner_contact_email_snapshot, revision
         ) VALUES (
            :id, :candidate_id, :organizer_id, :terms_id, :reservation_id,
            'gate4-241-e4', 'onboarding', JSON_ARRAY('active','activity_basic'), :operator,
            'Synthetischer Gate-4-Kontakt', :email, 1
         )"
    )->execute([
        'id' => BE_GATE4_E4_PILOT_ID,
        'candidate_id' => BE_GATE4_E4_CANDIDATE_ID,
        'organizer_id' => $organizerId,
        'terms_id' => $termsId,
        'reservation_id' => $reservationId,
        'operator' => BE_GATE4_E4_OPERATOR,
        'email' => BE_GATE4_E4_EMAIL,
    ]);

    foreach ([
        ['events', 'events', 'active', 8, 'pilot_month'],
        ['activities', 'activities', 'activity_basic', 1, 'concurrent'],
        ['automatic-source', 'automatic_source', null, null, 'not_applicable'],
        ['maintenance-service', 'maintenance_service', null, null, 'not_applicable'],
        ['provider-portal', 'provider_portal', null, null, 'not_applicable'],
        ['measurement', 'measurement', null, null, 'not_applicable'],
        ['reach-contribution', 'reach_contribution', null, null, 'not_applicable'],
    ] as $scope) {
        $pdo->prepare(
            "INSERT INTO startpartner_pilot_scopes (
                pilot_id, scope_key, scope_type, status, target_plan_key,
                limit_value, is_unlimited, period_unit, details_json
             ) VALUES (
                :pilot_id, :scope_key, :scope_type, 'planned', :target_plan_key,
                :limit_value, 0, :period_unit, JSON_OBJECT('synthetic_issue', 241)
             )"
        )->execute([
            'pilot_id' => BE_GATE4_E4_PILOT_ID,
            'scope_key' => $scope[0],
            'scope_type' => $scope[1],
            'target_plan_key' => $scope[2],
            'limit_value' => $scope[3],
            'period_unit' => $scope[4],
        ]);
    }

    $pdo->prepare(
        "INSERT INTO startpartner_pilot_entitlements (
            id, pilot_id, organizer_id, source_reference, status,
            target_plan_keys_json, event_limit_per_pilot_month,
            activity_concurrent_limit, is_event_unlimited, source_scope_json,
            audit_json, revision
         ) VALUES (
            :id, :pilot_id, :organizer_id, :source_reference, 'pending_activation',
            JSON_ARRAY('active','activity_basic'), 8, 1, 0,
            JSON_OBJECT('synthetic_issue', 241), JSON_OBJECT('synthetic_issue', 241), 1
         )"
    )->execute([
        'id' => BE_GATE4_E4_ENTITLEMENT_ID,
        'pilot_id' => BE_GATE4_E4_PILOT_ID,
        'organizer_id' => $organizerId,
        'source_reference' => BE_GATE4_E4_PILOT_ID,
    ]);
    $pdo->prepare(
        "INSERT INTO organizer_portal_sessions (
            organizer_id, session_token_hash, expires_at, last_seen_at
         ) VALUES (
            :organizer_id, :session_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), UTC_TIMESTAMP()
         )"
    )->execute([
        'organizer_id' => $organizerId,
        'session_hash' => hash('sha256', BE_GATE4_E4_SESSION_TOKEN),
    ]);

    return [
        'organizer_id' => $organizerId,
        'decision_id' => $decisionId,
        'reservation_id' => $reservationId,
        'terms_id' => $termsId,
    ];
}

function be_gate4_e4_http(
    string $method,
    string $path,
    ?array $body,
    ?string $reviewPassword = null,
    ?string $sessionToken = null
): array {
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: ' . BE_GATE4_E4_USER_AGENT,
    ];
    if ($reviewPassword !== null) {
        $headers[] = 'X-BE-Review-Password: ' . $reviewPassword;
    }
    if ($sessionToken !== null) {
        $headers[] = 'Cookie: be_organizer_portal_session=' . $sessionToken;
    }
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 90,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    if ($body !== null) {
        $options['http']['content'] = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
    $raw = @file_get_contents(BE_GATE4_E4_ORIGIN . $path, false, stream_context_create($options));
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
            $status = (int)$match[1];
        }
    }
    if ($raw === false && $status === 0) {
        throw new RuntimeException("HTTP request failed without response: {$method} {$path}");
    }
    $decoded = json_decode((string)$raw, true);
    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : ['raw' => (string)$raw],
    ];
}

function be_gate4_e4_expect(array $response, array $statuses, string $label): array
{
    be_gate4_e4_assert(
        in_array((int)$response['status'], $statuses, true),
        $label . ' returned HTTP ' . (int)$response['status'] . ': ' . json_encode($response['body'])
    );
    be_gate4_e4_assert(is_array($response['body']), $label . ' returned no JSON object.');
    return $response['body'];
}

function be_gate4_e4_revisions(PDO $pdo): array
{
    return [
        'expected_revision' => (int)be_gate4_e4_scalar(
            $pdo,
            'SELECT revision FROM startpartner_candidates WHERE id = :candidate_id',
            ['candidate_id' => BE_GATE4_E4_CANDIDATE_ID]
        ),
        'expected_pilot_revision' => (int)be_gate4_e4_scalar(
            $pdo,
            'SELECT revision FROM startpartner_pilots WHERE id = :pilot_id',
            ['pilot_id' => BE_GATE4_E4_PILOT_ID]
        ),
    ];
}

function be_gate4_e4_review_payload(PDO $pdo, string $operationId, array $extra): array
{
    return array_merge([
        'candidate_id' => BE_GATE4_E4_CANDIDATE_ID,
        'operation_id' => $operationId,
        'operator_name' => BE_GATE4_E4_OPERATOR,
    ], be_gate4_e4_revisions($pdo), $extra);
}

function be_gate4_e4_control_case_id(PDO $pdo): string
{
    $caseIds = be_gate4_e4_case_ids($pdo);
    be_gate4_e4_assert(count($caseIds) === 1, 'Exactly one synthetic Control Center case is required.');
    return $caseIds[0];
}

function be_gate4_e4_marker_exists(PDO $pdo): bool
{
    return be_gate4_e4_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :marker_key',
        ['marker_key' => BE_GATE4_E4_MARKER]
    ) === 1;
}

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

$pdo = null;
$lockHeld = false;
$lockedBefore = null;
$capacityBefore = null;
$error = null;
$cleanupError = null;
$evidence = [];
$residueAfter = null;
$lockedAfter = null;
$capacityAfter = null;
$deployedBuild = '';

try {
    be_gate4_e4_assert(
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '') === BE_GATE4_E4_USER_AGENT,
        'Gate-4 lifecycle is restricted to the deploy smoke user agent.'
    );
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    be_gate4_e4_assert(is_array($input), 'Invalid lifecycle request.');
    $expectedBuild = trim((string)($input['expected_build'] ?? ''));
    be_gate4_e4_assert(preg_match('/^[0-9a-f]{12}$/', $expectedBuild) === 1, 'Expected build is invalid.');
    $buildPath = dirname(__DIR__, 3) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
    be_gate4_e4_assert($deployedBuild === $expectedBuild, 'Lifecycle build does not match deployed build.');

    $reviewPassword = trim((string)($_SERVER['HTTP_X_BE_REVIEW_PASSWORD'] ?? ''));
    be_gate4_e4_assert($reviewPassword !== '', 'Review password is unavailable to the lifecycle owner.');

    $pdo = be_db();
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);
    be_startpartner_gate4_require_schema($pdo);
    $lock = (int)be_gate4_e4_scalar($pdo, 'SELECT GET_LOCK(:lock_name, 0)', ['lock_name' => BE_GATE4_E4_LOCK]);
    be_gate4_e4_assert($lock === 1, 'Gate-4 lifecycle lock is already held.');
    $lockHeld = true;

    if (be_gate4_e4_marker_exists($pdo)) {
        $residue = be_gate4_e4_residue($pdo);
        be_gate4_e4_assert((int)$residue['total'] === 0, 'Completion marker exists together with synthetic residue.');
        be_gate4_e4_scalar($pdo, 'SELECT RELEASE_LOCK(:lock_name)', ['lock_name' => BE_GATE4_E4_LOCK]);
        $lockHeld = false;
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'already_completed' => true,
                'marker' => BE_GATE4_E4_MARKER,
                'build' => $deployedBuild,
                'residue' => $residue,
            ],
        ]);
    }

    $initialResidue = be_gate4_e4_residue($pdo);
    be_gate4_e4_assert((int)$initialResidue['total'] === 0, 'Synthetic Gate-4 residue exists before lifecycle.');
    $lockedBefore = be_gate4_e4_locked_counts($pdo);
    $capacityBefore = be_startpartner_gate4_capacity($pdo);
    $seed = be_gate4_e4_seed($pdo);
    $capacitySeeded = be_startpartner_gate4_capacity($pdo);
    be_gate4_e4_assert(
        (int)$capacitySeeded['occupied_slots'] === (int)$capacityBefore['occupied_slots'] + 1,
        'Synthetic active reservation must add exactly one occupied capacity slot.'
    );

    $portalBefore = be_gate4_e4_expect(
        be_gate4_e4_http('GET', '/api/organizer-portal/pilot.php', null, null, BE_GATE4_E4_SESSION_TOKEN),
        [200],
        'Organizer portal readback before content'
    );
    be_gate4_e4_assert(($portalBefore['status'] ?? '') === 'ok', 'Organizer portal readback is not ok.');

    $timezone = new DateTimeZone('Europe/Berlin');
    $eventDate = (new DateTimeImmutable('+30 days', $timezone))->format('Y-m-d');
    $distributionDate = (new DateTimeImmutable('+7 days', $timezone))->format('Y-m-d');
    $activationDate = (new DateTimeImmutable('today', $timezone))->format('Y-m-d');

    $eventSubmission = be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/content.php', [
            'content_type' => 'event',
            'client_reference' => 'gate4-241-staging-event',
            'title' => 'Synthetischer Gate-4-Veranstaltungstest',
            'start_date' => $eventDate,
            'time_text' => '18:00',
            'location_name' => 'Bocholt',
            'location_address' => 'Synthetischer Testort 1',
            'location_public_confirmed' => true,
            'description_text' => 'Ausschließlich synthetischer No-Send-Nachweis für Issue 241.',
            'notes_text' => 'Nicht veröffentlichen. Keine Zahlung. Keine Kommunikation.',
        ], null, BE_GATE4_E4_SESSION_TOKEN),
        [201],
        'Event submission through permanent portal API'
    );
    $eventData = $eventSubmission['data'] ?? null;
    be_gate4_e4_assert(is_array($eventData), 'Event submission data is missing.');
    $eventContentId = (string)($eventData['content_link']['id'] ?? '');
    be_gate4_e4_assert($eventContentId !== '', 'Event content link id is missing.');

    $activitySubmission = be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/content.php', [
            'content_type' => 'activity',
            'client_reference' => 'gate4-241-staging-activity',
            'title' => 'Synthetischer Gate-4-Aktivitätstest',
            'location_name' => 'Bocholt',
            'location_address' => 'Synthetischer Testort 2',
            'location_public_confirmed' => true,
            'description_text' => 'Ausschließlich synthetischer Aktivitäts-Kompatibilitätsnachweis für Issue 241.',
            'notes_text' => 'Nicht veröffentlichen. Keine Zahlung. Keine Kommunikation.',
        ], null, BE_GATE4_E4_SESSION_TOKEN),
        [201],
        'Activity submission through permanent portal API'
    );
    $activityData = $activitySubmission['data'] ?? null;
    be_gate4_e4_assert(is_array($activityData), 'Activity submission data is missing.');
    $activityContentId = (string)($activityData['content_link']['id'] ?? '');
    be_gate4_e4_assert($activityContentId !== '', 'Activity content link id is missing.');

    $eventReplay = be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/content.php', [
            'content_type' => 'event',
            'client_reference' => 'gate4-241-staging-event',
            'title' => 'Synthetischer Gate-4-Veranstaltungstest',
            'start_date' => $eventDate,
            'time_text' => '18:00',
            'location_name' => 'Bocholt',
            'location_address' => 'Synthetischer Testort 1',
            'location_public_confirmed' => true,
            'description_text' => 'Ausschließlich synthetischer No-Send-Nachweis für Issue 241.',
            'notes_text' => 'Nicht veröffentlichen. Keine Zahlung. Keine Kommunikation.',
        ], null, BE_GATE4_E4_SESSION_TOKEN),
        [200],
        'Event submission idempotent replay'
    );
    be_gate4_e4_assert(!empty($eventReplay['data']['idempotent_replay']), 'Portal content replay must be idempotent.');

    foreach ([
        ['portal_access_tested', 'Der synthetische Veranstalterzugang wurde über den permanenten Portal-Readback geprüft.'],
        ['content_rights_cleared', 'Die synthetischen Inhalte sind als Testdaten gekennzeichnet und besitzen keine Veröffentlichungserlaubnis.'],
        ['activation_target_set', 'Der synthetische Pilotstart ist ausschließlich für den aktuellen Staging-Tag vorgesehen.'],
    ] as $index => [$itemKey, $evidenceText]) {
        $payload = be_gate4_e4_review_payload(
            $pdo,
            'gate4:241:staging-e4:onboarding:' . ($index + 1),
            [
                'action' => 'update_item',
                'item_key' => $itemKey,
                'status' => 'complete',
                'evidence_text' => $evidenceText,
                'evidence_reference' => 'issue-241:staging-e4',
            ]
        );
        $response = be_gate4_e4_expect(
            be_gate4_e4_http('POST', '/api/startpartner/onboarding.php', $payload, $reviewPassword),
            [200],
            'Manual onboarding item ' . $itemKey
        );
        be_gate4_e4_assert(($response['status'] ?? '') === 'ok', 'Manual onboarding response is not ok.');
    }

    $readyPayload = be_gate4_e4_review_payload(
        $pdo,
        'gate4:241:staging-e4:content-ready',
        ['action' => 'mark_content_ready', 'content_link_id' => $eventContentId]
    );
    be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/onboarding.php', $readyPayload, $reviewPassword),
        [200],
        'Editorial readiness through permanent API'
    );

    $measurementPayload = be_gate4_e4_review_payload(
        $pdo,
        'gate4:241:staging-e4:measurement',
        [
            'action' => 'set_measurement',
            'content_link_id' => $eventContentId,
            'status' => 'ready',
            'evidence_text' => 'Autoritativer Readback aus value_metric_daily für den synthetischen Veranstalter und Inhalt.',
        ]
    );
    $measurementResponse = be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/onboarding.php', $measurementPayload, $reviewPassword),
        [200],
        'Measurement readiness through permanent API'
    );
    $technicalReadback = $measurementResponse['data']['meta']['technical_readback'] ?? null;
    be_gate4_e4_assert(
        is_array($technicalReadback)
        && ($technicalReadback['owner'] ?? '') === 'value_metric_daily'
        && ($technicalReadback['query_status'] ?? '') === 'ok',
        'Measurement readiness must include authoritative value_metric_daily readback.'
    );

    $distributionPayload = be_gate4_e4_review_payload(
        $pdo,
        'gate4:241:staging-e4:distribution',
        [
            'action' => 'set_distribution',
            'channel' => 'newsletter',
            'target_reference' => 'repo://issue-241/staging-e4/reach-contribution',
            'planned_at' => $distributionDate,
            'status' => 'ready',
            'evidence_text' => 'Synthetischer Reichweitenbeitrag ohne externe Veröffentlichung oder Nachricht.',
        ]
    );
    be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/onboarding.php', $distributionPayload, $reviewPassword),
        [200],
        'Distribution readiness through permanent API'
    );

    $caseId = be_gate4_e4_control_case_id($pdo);
    $controlReady = be_gate4_e4_expect(
        be_gate4_e4_http('GET', '/api/control-center/case.php?id=' . rawurlencode($caseId), null, $reviewPassword),
        [200],
        'Control Center activation-ready readback'
    );
    $controlReadyData = $controlReady['data'] ?? null;
    be_gate4_e4_assert(is_array($controlReadyData), 'Control Center activation-ready data is missing.');
    $readyGate4 = $controlReadyData['decision_context']['gate4'] ?? null;
    be_gate4_e4_assert(
        is_array($readyGate4)
        && !empty($readyGate4['activation_ready'])
        && (int)($readyGate4['onboarding']['completed_count'] ?? 0) === 14,
        'Control Center must read back 14/14 and activation readiness.'
    );

    $portalReady = be_gate4_e4_expect(
        be_gate4_e4_http('GET', '/api/organizer-portal/pilot.php', null, null, BE_GATE4_E4_SESSION_TOKEN),
        [200],
        'Organizer portal activation-ready readback'
    );
    $portalReadyGate4 = $portalReady['data']['gate4'] ?? null;
    be_gate4_e4_assert(
        is_array($portalReadyGate4) && !empty($portalReadyGate4['activation_ready']),
        'Organizer portal must read back activation readiness.'
    );
    $portalTypes = array_values(array_unique(array_map(
        static fn(array $row): string => (string)($row['content_type'] ?? ''),
        array_values(array_filter((array)($portalReadyGate4['content_links'] ?? []), 'is_array'))
    )));
    sort($portalTypes);
    be_gate4_e4_assert($portalTypes === ['activity', 'event'], 'Portal must read back event and activity compatibility.');

    $activationPayload = be_gate4_e4_review_payload(
        $pdo,
        'gate4:241:staging-e4:activate',
        ['activation_date_local' => $activationDate]
    );
    $activationExpectedRevisions = [
        'candidate' => (int)$activationPayload['expected_revision'],
        'pilot' => (int)$activationPayload['expected_pilot_revision'],
    ];
    $activationResponse = be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/activation.php', $activationPayload, $reviewPassword),
        [200],
        'Atomic activation through permanent API'
    );
    $activated = $activationResponse['data'] ?? null;
    be_gate4_e4_assert(is_array($activated), 'Activation response data is missing.');
    $activatedGate4 = $activated['candidate']['gate4'] ?? null;
    be_gate4_e4_assert(
        is_array($activatedGate4)
        && !empty($activatedGate4['active'])
        && (string)($activatedGate4['first_content']['status'] ?? '') === 'approved',
        'Activation must atomically activate the pilot and approve the first content.'
    );

    $activationReplay = be_gate4_e4_expect(
        be_gate4_e4_http('POST', '/api/startpartner/activation.php', $activationPayload, $reviewPassword),
        [200],
        'Activation idempotent replay'
    );
    be_gate4_e4_assert(!empty($activationReplay['data']['idempotent_replay']), 'Activation retry must replay idempotently.');

    $changedPayload = $activationPayload;
    $changedPayload['activation_date_local'] = (new DateTimeImmutable('yesterday', $timezone))->format('Y-m-d');
    $changedConflict = be_gate4_e4_http('POST', '/api/startpartner/activation.php', $changedPayload, $reviewPassword);
    be_gate4_e4_assert((int)$changedConflict['status'] === 409, 'Changed activation payload must conflict with HTTP 409.');

    $stalePayload = [
        'candidate_id' => BE_GATE4_E4_CANDIDATE_ID,
        'operation_id' => 'gate4:241:staging-e4:stale-revision',
        'operator_name' => BE_GATE4_E4_OPERATOR,
        'expected_revision' => $activationExpectedRevisions['candidate'],
        'expected_pilot_revision' => $activationExpectedRevisions['pilot'],
        'action' => 'update_item',
        'item_key' => 'activation_target_set',
        'status' => 'complete',
        'evidence_text' => 'Dieser veraltete Schreibversuch muss abgewiesen werden.',
    ];
    $staleConflict = be_gate4_e4_http('POST', '/api/startpartner/onboarding.php', $stalePayload, $reviewPassword);
    be_gate4_e4_assert((int)$staleConflict['status'] === 409, 'Stale revisions must conflict with HTTP 409.');

    $controlActive = be_gate4_e4_expect(
        be_gate4_e4_http('GET', '/api/control-center/case.php?id=' . rawurlencode($caseId), null, $reviewPassword),
        [200],
        'Control Center active readback'
    );
    $controlActiveGate4 = $controlActive['data']['decision_context']['gate4'] ?? null;
    be_gate4_e4_assert(
        is_array($controlActiveGate4)
        && !empty($controlActiveGate4['active'])
        && (string)($controlActiveGate4['pilot']['status'] ?? '') === 'active',
        'Control Center must read back the active pilot.'
    );

    $portalActive = be_gate4_e4_expect(
        be_gate4_e4_http('GET', '/api/organizer-portal/pilot.php', null, null, BE_GATE4_E4_SESSION_TOKEN),
        [200],
        'Organizer portal active readback'
    );
    $portalActiveGate4 = $portalActive['data']['gate4'] ?? null;
    $plannedEnd = be_startpartner_gate4_add_calendar_months($activationDate, 6);
    be_gate4_e4_assert(
        is_array($portalActiveGate4)
        && !empty($portalActiveGate4['active'])
        && (string)($portalActiveGate4['pilot']['activation_date_local'] ?? '') === $activationDate
        && (string)($portalActiveGate4['pilot']['planned_end_date'] ?? '') === $plannedEnd,
        'Organizer portal must read back the exact local activation window.'
    );

    $reservationStatus = (string)be_gate4_e4_scalar(
        $pdo,
        'SELECT status FROM startpartner_candidate_reservations WHERE id = :reservation_id',
        ['reservation_id' => $seed['reservation_id']]
    );
    $usageCount = be_gate4_e4_count($pdo, 'startpartner_pilot_usages', 'pilot_id = :pilot_id', ['pilot_id' => BE_GATE4_E4_PILOT_ID]);
    $activeScopeCount = be_gate4_e4_count(
        $pdo,
        'startpartner_pilot_scopes',
        "pilot_id = :pilot_id AND status = 'active'",
        ['pilot_id' => BE_GATE4_E4_PILOT_ID]
    );
    $activityStatus = (string)be_gate4_e4_scalar(
        $pdo,
        'SELECT status FROM startpartner_pilot_content_links WHERE id = :content_id',
        ['content_id' => $activityContentId]
    );
    $capacityActivated = be_startpartner_gate4_capacity($pdo);
    be_gate4_e4_assert($reservationStatus === 'released', 'Activation must release the active reservation.');
    be_gate4_e4_assert($usageCount === 1, 'Activation must create exactly one dedicated pilot usage.');
    be_gate4_e4_assert($activeScopeCount === 7, 'Activation must activate all seven agreed pilot scopes.');
    be_gate4_e4_assert($activityStatus === 'draft', 'Second activity content must remain unapproved and prove no automatic publication.');
    be_gate4_e4_assert(
        (int)$capacityActivated['occupied_slots'] === (int)$capacitySeeded['occupied_slots'],
        'Activation must preserve occupied capacity while replacing the reservation with the active pilot.'
    );

    $lockedDuring = be_gate4_e4_locked_counts($pdo);
    foreach (['organizer_magic_links', 'subscriptions', 'publication_entitlements', 'publication_consumptions'] as $lockedTable) {
        be_gate4_e4_assert(
            (int)$lockedDuring[$lockedTable] === (int)$lockedBefore[$lockedTable],
            "Locked owner changed during lifecycle: {$lockedTable}"
        );
    }
    be_gate4_e4_assert(
        (int)$lockedDuring['organizers'] === (int)$lockedBefore['organizers'] + 1
        && (int)$lockedDuring['organizer_portal_sessions'] === (int)$lockedBefore['organizer_portal_sessions'] + 1
        && (int)$lockedDuring['submissions'] === (int)$lockedBefore['submissions'] + 2,
        'Only the single synthetic organizer, portal session and two pilot submissions may be added temporarily.'
    );

    $evidence = [
        'build' => $deployedBuild,
        'content_types' => $portalTypes,
        'event_content_link_id' => $eventContentId,
        'activity_content_link_id' => $activityContentId,
        'portal_content_replay' => true,
        'onboarding_completed' => 14,
        'measurement_owner' => (string)$technicalReadback['owner'],
        'measurement_target_type' => (string)$technicalReadback['reporting_target_type'],
        'measurement_target_id' => (string)$technicalReadback['reporting_target_id'],
        'distribution_date_local' => $distributionDate,
        'activation_date_local' => $activationDate,
        'planned_end_date' => $plannedEnd,
        'activation_replay' => true,
        'changed_payload_status' => (int)$changedConflict['status'],
        'stale_revision_status' => (int)$staleConflict['status'],
        'reservation_status' => $reservationStatus,
        'active_scope_count' => $activeScopeCount,
        'pilot_usage_count' => $usageCount,
        'second_content_status' => $activityStatus,
        'capacity_before' => $capacityBefore,
        'capacity_seeded' => $capacitySeeded,
        'capacity_activated' => $capacityActivated,
        'locked_before' => $lockedBefore,
        'locked_during' => $lockedDuring,
        'control_center_readback' => [
            'activation_ready' => true,
            'active' => true,
            'case_id' => $caseId,
        ],
        'organizer_portal_readback' => [
            'activation_ready' => true,
            'active' => true,
            'content_types' => $portalTypes,
        ],
    ];
} catch (Throwable $caught) {
    $error = $caught;
}

if ($pdo instanceof PDO) {
    try {
        be_gate4_e4_cleanup($pdo);
        $residueAfter = be_gate4_e4_residue($pdo);
        $lockedAfter = be_gate4_e4_locked_counts($pdo);
        $capacityAfter = be_startpartner_gate4_capacity($pdo);
        be_gate4_e4_assert((int)$residueAfter['total'] === 0, 'Synthetic Gate-4 residue remains after cleanup.');
        if (is_array($lockedBefore)) {
            be_gate4_e4_assert($lockedAfter === $lockedBefore, 'Locked owner counts differ after cleanup.');
        }
        if (is_array($capacityBefore)) {
            be_gate4_e4_assert(
                (int)$capacityAfter['occupied_slots'] === (int)$capacityBefore['occupied_slots'],
                'Occupied capacity was not restored after cleanup.'
            );
        }
        if ($error === null) {
            $pdo->prepare(
                'INSERT INTO app_schema_migrations (migration_key, description)
                 VALUES (:marker_key, :description)'
            )->execute([
                'marker_key' => BE_GATE4_E4_MARKER,
                'description' => 'Gate-4 E4 staging lifecycle completed for build ' . $deployedBuild . '.',
            ]);
        }
    } catch (Throwable $caughtCleanup) {
        $cleanupError = $caughtCleanup;
    }
    if ($lockHeld) {
        try {
            be_gate4_e4_scalar($pdo, 'SELECT RELEASE_LOCK(:lock_name)', ['lock_name' => BE_GATE4_E4_LOCK]);
        } catch (Throwable) {
            // Connection close also releases the advisory lock.
        }
    }
}

if ($error !== null || $cleanupError !== null) {
    $messages = [];
    if ($error !== null) {
        $messages[] = $error::class . ': ' . $error->getMessage();
    }
    if ($cleanupError !== null) {
        $messages[] = 'Cleanup ' . $cleanupError::class . ': ' . $cleanupError->getMessage();
    }
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Gate-4 staging lifecycle failed.',
        'error_message' => implode(' | ', $messages),
        'data' => [
            'build' => $deployedBuild,
            'residue' => $residueAfter,
            'locked_after_cleanup' => $lockedAfter,
            'capacity_after_cleanup' => $capacityAfter,
        ],
    ]);
}

$evidence['locked_after_cleanup'] = $lockedAfter;
$evidence['capacity_after_cleanup'] = $capacityAfter;
be_json_response(200, [
    'status' => 'ok',
    'data' => [
        'already_completed' => false,
        'marker' => BE_GATE4_E4_MARKER,
        'build' => $deployedBuild,
        'evidence' => $evidence,
        'residue' => $residueAfter,
    ],
]);
