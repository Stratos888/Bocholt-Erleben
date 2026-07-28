<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_domain.php';

const BE_GATE3_E4_CANDIDATE_ID = '23100000-0000-4000-8000-000000000001';
const BE_GATE3_E4_EMAIL = 'gate3-231-e4@example.org';
const BE_GATE3_E4_ORGANIZATION = 'GATE3_SYNTHETIC_231_E4_ORGANISATION';
const BE_GATE3_E4_OPERATION_ID = 'gate3:231:staging-e4-final';
const BE_GATE3_E4_MARKER = '231_gate3_staging_lifecycle_completed';
const BE_GATE3_E4_LOCK = 'bocholt_gate3_staging_final_231';
const BE_GATE3_E4_USER_AGENT = 'Bocholt-Erleben-Deploy-Smoke/1.0';
const BE_GATE3_E4_ORIGIN = 'https://staging.bocholt-erleben.de';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_gate3_e4_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function be_gate3_e4_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function be_gate3_e4_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    $safeTable = str_replace('`', '``', $table);
    return (int)be_gate3_e4_scalar($pdo, "SELECT COUNT(*) FROM `{$safeTable}` WHERE {$where}", $params);
}

function be_gate3_e4_locked_counts(PDO $pdo): array
{
    $counts = [];
    foreach ([
        'subscriptions',
        'organizer_magic_links',
        'organizer_portal_sessions',
        'submissions',
        'publication_entitlements',
        'publication_consumptions',
    ] as $table) {
        $counts[$table] = be_gate3_e4_count($pdo, $table);
    }
    return $counts;
}

function be_gate3_e4_residue(PDO $pdo): array
{
    $candidateId = BE_GATE3_E4_CANDIDATE_ID;
    $pilotIds = $pdo->prepare('SELECT id FROM startpartner_pilots WHERE candidate_id = :candidate_id');
    $pilotIds->execute(['candidate_id' => $candidateId]);
    $ids = array_map('strval', $pilotIds->fetchAll(PDO::FETCH_COLUMN));
    $pilotResidue = 0;
    foreach ($ids as $pilotId) {
        foreach (['startpartner_pilot_events', 'startpartner_pilot_entitlements', 'startpartner_pilot_scopes'] as $table) {
            $pilotResidue += be_gate3_e4_count($pdo, $table, 'pilot_id = :pilot_id', ['pilot_id' => $pilotId]);
        }
    }
    $counts = [
        'candidate' => be_gate3_e4_count($pdo, 'startpartner_candidates', 'id = :candidate_id', ['candidate_id' => $candidateId]),
        'contacts' => be_gate3_e4_count($pdo, 'startpartner_candidate_contacts', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'decisions' => be_gate3_e4_count($pdo, 'startpartner_candidate_decisions', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'reservations' => be_gate3_e4_count($pdo, 'startpartner_candidate_reservations', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'operations' => be_gate3_e4_count($pdo, 'startpartner_candidate_operations', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'candidate_events' => be_gate3_e4_count($pdo, 'startpartner_candidate_events', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'terms' => be_gate3_e4_count($pdo, 'startpartner_pilot_terms_acceptances', 'candidate_id = :candidate_id', ['candidate_id' => $candidateId]),
        'pilots' => count($ids),
        'pilot_children' => $pilotResidue,
        'control_cases' => be_gate3_e4_count(
            $pdo,
            'control_cases',
            "source_system = 'startpartner_candidate' AND source_reference = :candidate_id",
            ['candidate_id' => $candidateId]
        ),
        'organizer' => be_gate3_e4_count($pdo, 'organizers', 'email_normalized = :email', ['email' => BE_GATE3_E4_EMAIL]),
    ];
    $counts['total'] = array_sum($counts);
    return $counts;
}

function be_gate3_e4_cleanup(PDO $pdo): void
{
    $candidateId = BE_GATE3_E4_CANDIDATE_ID;
    $pilotStatement = $pdo->prepare('SELECT id FROM startpartner_pilots WHERE candidate_id = :candidate_id');
    $pilotStatement->execute(['candidate_id' => $candidateId]);
    foreach ($pilotStatement->fetchAll(PDO::FETCH_COLUMN) as $pilotId) {
        foreach (['startpartner_pilot_events', 'startpartner_pilot_entitlements', 'startpartner_pilot_scopes'] as $table) {
            $statement = $pdo->prepare("DELETE FROM {$table} WHERE pilot_id = :pilot_id");
            $statement->execute(['pilot_id' => (string)$pilotId]);
        }
    }
    $pdo->prepare('DELETE FROM startpartner_pilots WHERE candidate_id = :candidate_id')->execute(['candidate_id' => $candidateId]);
    $pdo->prepare('DELETE FROM startpartner_pilot_terms_acceptances WHERE candidate_id = :candidate_id')->execute(['candidate_id' => $candidateId]);

    $caseStatement = $pdo->prepare(
        "SELECT id FROM control_cases WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $caseStatement->execute(['candidate_id' => $candidateId]);
    foreach ($caseStatement->fetchAll(PDO::FETCH_COLUMN) as $caseId) {
        $pdo->prepare('DELETE FROM control_case_events WHERE case_id = :case_id')->execute(['case_id' => (string)$caseId]);
    }
    $pdo->prepare(
        "DELETE FROM control_cases WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    )->execute(['candidate_id' => $candidateId]);

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
    $pdo->prepare('DELETE FROM organizers WHERE email_normalized = :email')->execute(['email' => BE_GATE3_E4_EMAIL]);
}

function be_gate3_e4_http(string $method, string $path, ?array $body, string $reviewPassword): array
{
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: ' . BE_GATE3_E4_USER_AGENT,
        'X-BE-Review-Password: ' . $reviewPassword,
    ];
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 60,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    if ($body !== null) {
        $options['http']['content'] = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
    $raw = @file_get_contents(BE_GATE3_E4_ORIGIN . $path, false, stream_context_create($options));
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
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['raw' => (string)$raw]];
}

function be_gate3_e4_expect(array $response, array $statuses, string $label): array
{
    be_gate3_e4_assert(
        in_array((int)$response['status'], $statuses, true),
        $label . ' returned HTTP ' . (int)$response['status'] . ': ' . json_encode($response['body'])
    );
    return $response['body'];
}

function be_gate3_e4_seed(PDO $pdo): array
{
    $pdo->prepare(
        "INSERT INTO startpartner_candidates (
            id, source, source_reference, organization_name, organization_name_normalized,
            desired_content_scope, status, identity_key, idempotency_key_hash,
            privacy_policy_version, form_version, retention_review_at,
            revision, assigned_to, next_review_at, status_changed_at
         ) VALUES (
            :id, 'targeted_outreach', 'issue-231:staging-e4', :organization_name,
            :organization_name_normalized, 'both', 'accepted_pending_terms',
            :identity_key, :idempotency_key_hash, 'privacy-e4-v1', 'gate3-e4-v1',
            DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY), 1,
            'Gate 3 E4 Staging Lifecycle #231', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY), UTC_TIMESTAMP()
         )"
    )->execute([
        'id' => BE_GATE3_E4_CANDIDATE_ID,
        'organization_name' => BE_GATE3_E4_ORGANIZATION,
        'organization_name_normalized' => be_startpartner_normalize_organization(BE_GATE3_E4_ORGANIZATION),
        'identity_key' => hash('sha256', 'gate3-231-e4-identity'),
        'idempotency_key_hash' => hash('sha256', 'gate3-231-e4-idempotency'),
    ]);
    $pdo->prepare(
        'INSERT INTO startpartner_candidate_contacts (
            candidate_id, contact_name, contact_role, email, email_normalized, is_primary
         ) VALUES (
            :candidate_id, :contact_name, :contact_role, :email, :email_normalized, 1
         )'
    )->execute([
        'candidate_id' => BE_GATE3_E4_CANDIDATE_ID,
        'contact_name' => 'Synthetischer Gate-3-E4-Kontakt',
        'contact_role' => 'Pilotkontakt',
        'email' => BE_GATE3_E4_EMAIL,
        'email_normalized' => BE_GATE3_E4_EMAIL,
    ]);
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_decisions (
            candidate_id, result, reason, operator_reference, candidate_revision,
            qualification_snapshot_json, capacity_snapshot_json, is_current
         ) VALUES (
            :candidate_id, 'accepted_pending_terms', 'Synthetischer Gate-3-E4-Nachweis',
            'Gate 3 E4 Staging Lifecycle #231', 1, JSON_OBJECT('ready', TRUE),
            JSON_OBJECT('active_reservations', 1), 1
         )"
    )->execute(['candidate_id' => BE_GATE3_E4_CANDIDATE_ID]);
    $decisionId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_reservations (
            candidate_id, decision_id, status, starts_at, ends_at,
            capacity_snapshot_json, operator_reference
         ) VALUES (
            :candidate_id, :decision_id, 'active', UTC_TIMESTAMP(),
            DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 DAY),
            JSON_OBJECT('active_reservations', 1), 'Gate 3 E4 Staging Lifecycle #231'
         )"
    )->execute(['candidate_id' => BE_GATE3_E4_CANDIDATE_ID, 'decision_id' => $decisionId]);
    return ['decision_id' => $decisionId, 'reservation_id' => (int)$pdo->lastInsertId()];
}

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    be_gate3_e4_assert(
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '') === BE_GATE3_E4_USER_AGENT,
        'Gate-3 lifecycle is restricted to the deploy smoke user agent.'
    );
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    be_gate3_e4_assert(is_array($input), 'Invalid lifecycle request.');
    $expectedBuild = trim((string)($input['expected_build'] ?? ''));
    be_gate3_e4_assert(preg_match('/^[0-9a-f]{12}$/', $expectedBuild) === 1, 'Expected build is invalid.');
    $buildPath = dirname(__DIR__, 2) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
    be_gate3_e4_assert($deployedBuild === $expectedBuild, 'Lifecycle build does not match deployed build.');

    $pdo = be_db();
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);
    $lock = (int)be_gate3_e4_scalar($pdo, 'SELECT GET_LOCK(:lock_name, 0)', ['lock_name' => BE_GATE3_E4_LOCK]);
    be_gate3_e4_assert($lock === 1, 'Gate-3 lifecycle lock is already held.');

    try {
        $markerExists = be_gate3_e4_count(
            $pdo,
            'app_schema_migrations',
            'migration_key = :marker_key',
            ['marker_key' => BE_GATE3_E4_MARKER]
        ) === 1;
        if ($markerExists) {
            be_json_response(200, [
                'status' => 'ok',
                'data' => ['already_completed' => true, 'marker' => BE_GATE3_E4_MARKER, 'build' => $deployedBuild],
            ]);
        }

        $initialResidue = be_gate3_e4_residue($pdo);
        be_gate3_e4_assert((int)$initialResidue['total'] === 0, 'Synthetic Gate-3 residue exists before lifecycle.');
        $lockedBefore = be_gate3_e4_locked_counts($pdo);
        $capacityBefore = be_startpartner_gate2_capacity($pdo);
        $seed = be_gate3_e4_seed($pdo);
        $reservationBefore = $pdo->prepare('SELECT * FROM startpartner_candidate_reservations WHERE id = :id');
        $reservationBefore->execute(['id' => $seed['reservation_id']]);
        $reservationSnapshot = $reservationBefore->fetch(PDO::FETCH_ASSOC);
        be_gate3_e4_assert(is_array($reservationSnapshot), 'Synthetic active reservation is missing.');

        $payload = [
            'candidate_id' => BE_GATE3_E4_CANDIDATE_ID,
            'action' => 'confirm_pilot_terms',
            'operation_id' => BE_GATE3_E4_OPERATION_ID,
            'expected_revision' => 1,
            'operator_name' => 'Gate 3 E4 Staging Lifecycle #231',
            'terms_version' => 'pilot-terms-e4-v1',
            'terms_reference' => 'repo://issue-231/staging-e4/pilot-terms-e4-v1',
            'terms_digest' => hash('sha256', 'gate3-231-e4-terms-v1'),
            'accepting_person' => 'Synthetischer Gate-3-E4-Kontakt',
            'accepting_organization' => BE_GATE3_E4_ORGANIZATION,
            'accepted_at' => gmdate('c'),
            'confirmation_channel' => 'operator_recorded',
            'target_plan_keys' => ['active'],
            'cohort_key' => 'gate3-e4-2026',
            'event_limit_per_pilot_month' => 8,
            'activity_concurrent_limit' => 1,
            'is_event_unlimited' => false,
            'source_care_text' => 'Synthetische Quellenpflege ohne externe Kommunikation.',
            'maintenance_scope_text' => 'Synthetischer interner Pilotservice.',
            'reach_contribution_text' => 'Keine reale Distribution; nur E4-Vertragsnachweis.',
            'privacy_notice_version' => 'privacy-e4-v1',
            'communication_notice_version' => 'communication-e4-v1',
            'planned_activation_start' => gmdate('Y-m-d', strtotime('+7 days')),
            'planned_activation_end' => gmdate('Y-m-d', strtotime('+6 months +7 days')),
            'no_automatic_paid_renewal' => true,
        ];
        $reviewPassword = be_review_password();
        $first = be_gate3_e4_expect(
            be_gate3_e4_http('POST', '/api/startpartner/action.php', $payload, $reviewPassword),
            [200],
            'Gate-3 permanent action'
        );
        $firstData = $first['data'] ?? null;
        be_gate3_e4_assert(is_array($firstData) && ($firstData['idempotent_replay'] ?? true) === false, 'First Gate-3 call is invalid.');
        $pilotId = (string)($firstData['meta']['pilot_id'] ?? '');
        $organizerId = (int)($firstData['meta']['organizer_id'] ?? 0);
        be_gate3_e4_assert($pilotId !== '' && $organizerId > 0, 'Gate-3 action did not return pilot and organizer identities.');
        be_gate3_e4_assert((int)($firstData['meta']['reservation_id'] ?? 0) === (int)$seed['reservation_id'], 'Pilot does not reference the existing reservation.');

        $replay = be_gate3_e4_expect(
            be_gate3_e4_http('POST', '/api/startpartner/action.php', $payload, $reviewPassword),
            [200],
            'Gate-3 identical replay'
        );
        be_gate3_e4_assert(($replay['data']['idempotent_replay'] ?? false) === true, 'Identical Gate-3 retry did not replay.');

        $changed = $payload;
        $changed['cohort_key'] = 'gate3-e4-changed';
        be_gate3_e4_expect(
            be_gate3_e4_http('POST', '/api/startpartner/action.php', $changed, $reviewPassword),
            [409],
            'Gate-3 changed-payload conflict'
        );
        $stale = $payload;
        $stale['operation_id'] = BE_GATE3_E4_OPERATION_ID . '-stale';
        be_gate3_e4_expect(
            be_gate3_e4_http('POST', '/api/startpartner/action.php', $stale, $reviewPassword),
            [409],
            'Gate-3 stale-revision conflict'
        );

        $readback = be_gate3_e4_expect(
            be_gate3_e4_http(
                'GET',
                '/api/startpartner/pilot.php?candidate_id=' . rawurlencode(BE_GATE3_E4_CANDIDATE_ID),
                null,
                $reviewPassword
            ),
            [200],
            'Gate-3 permanent pilot readback'
        );
        $state = $readback['data'] ?? null;
        be_gate3_e4_assert(is_array($state) && ($state['complete'] ?? false) === true, 'Gate-3 readback is incomplete.');
        be_gate3_e4_assert((string)($state['pilot']['id'] ?? '') === $pilotId, 'Pilot readback identity mismatch.');
        be_gate3_e4_assert((int)($state['organizer']['id'] ?? 0) === $organizerId, 'Organizer readback identity mismatch.');
        be_gate3_e4_assert((string)($state['pilot']['status'] ?? '') === 'onboarding', 'Pilot must remain onboarding.');
        be_gate3_e4_assert((string)($state['entitlement']['status'] ?? '') === 'pending_activation', 'Pilot grant must remain pending_activation.');
        be_gate3_e4_assert(($state['entitlement']['starts_at'] ?? null) === null, 'Pending grant must not have starts_at.');
        be_gate3_e4_assert(($state['entitlement']['ends_at'] ?? null) === null, 'Pending grant must not have ends_at.');
        be_gate3_e4_assert(count((array)($state['scopes'] ?? [])) === 7, 'Gate-3 scope readback is incomplete.');

        $reservationAfterStatement = $pdo->prepare('SELECT * FROM startpartner_candidate_reservations WHERE id = :id');
        $reservationAfterStatement->execute(['id' => $seed['reservation_id']]);
        $reservationAfter = $reservationAfterStatement->fetch(PDO::FETCH_ASSOC);
        be_gate3_e4_assert(is_array($reservationAfter), 'Reservation disappeared during Gate 3.');
        foreach (['status', 'starts_at', 'ends_at', 'capacity_snapshot_json', 'operator_reference'] as $field) {
            be_gate3_e4_assert(
                (string)($reservationAfter[$field] ?? '') === (string)($reservationSnapshot[$field] ?? ''),
                "Reservation field changed during Gate 3: {$field}"
            );
        }
        $capacityAfter = be_startpartner_gate2_capacity($pdo);
        be_gate3_e4_assert(
            (int)$capacityAfter['active_reservations'] === (int)$capacityBefore['active_reservations'] + 1,
            'Synthetic reservation must remain the sole added occupied capacity slot during onboarding.'
        );
        foreach ($lockedBefore as $table => $beforeCount) {
            be_gate3_e4_assert(be_gate3_e4_count($pdo, $table) === $beforeCount, "Locked table changed: {$table}");
        }

        $evidence = [
            'build' => $deployedBuild,
            'candidate_revision' => (int)be_gate3_e4_scalar(
                $pdo,
                'SELECT revision FROM startpartner_candidates WHERE id = :candidate_id',
                ['candidate_id' => BE_GATE3_E4_CANDIDATE_ID]
            ),
            'pilot_status' => (string)($state['pilot']['status'] ?? ''),
            'entitlement_status' => (string)($state['entitlement']['status'] ?? ''),
            'scope_count' => count((array)($state['scopes'] ?? [])),
            'reservation_id' => (int)$seed['reservation_id'],
            'capacity_before' => (int)$capacityBefore['active_reservations'],
            'capacity_during' => (int)$capacityAfter['active_reservations'],
            'replay' => true,
            'payload_conflict' => true,
            'stale_conflict' => true,
            'locked_counts' => $lockedBefore,
        ];

        be_gate3_e4_cleanup($pdo);
        $residue = be_gate3_e4_residue($pdo);
        be_gate3_e4_assert((int)$residue['total'] === 0, 'Gate-3 cleanup left synthetic residue.');
        foreach ($lockedBefore as $table => $beforeCount) {
            be_gate3_e4_assert(be_gate3_e4_count($pdo, $table) === $beforeCount, "Locked table count differs after cleanup: {$table}");
        }
        be_gate3_e4_assert(
            (int)be_startpartner_gate2_capacity($pdo)['active_reservations'] === (int)$capacityBefore['active_reservations'],
            'Capacity did not return to its before state after cleanup.'
        );

        $description = 'Gate 3 staging lifecycle completed; evidence sha256=' . hash(
            'sha256',
            json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
        $pdo->prepare(
            'INSERT INTO app_schema_migrations (migration_key, description)
             VALUES (:marker_key, :description)
             ON DUPLICATE KEY UPDATE description = VALUES(description)'
        )->execute(['marker_key' => BE_GATE3_E4_MARKER, 'description' => $description]);

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'already_completed' => false,
                'marker' => BE_GATE3_E4_MARKER,
                'evidence' => $evidence,
                'residue' => $residue,
            ],
        ]);
    } catch (Throwable $error) {
        try {
            be_gate3_e4_cleanup($pdo);
        } catch (Throwable $cleanupError) {
            throw new RuntimeException(
                $error->getMessage() . '; cleanup failed: ' . $cleanupError->getMessage(),
                0,
                $error
            );
        }
        throw $error;
    } finally {
        be_gate3_e4_scalar($pdo, 'SELECT RELEASE_LOCK(:lock_name)', ['lock_name' => BE_GATE3_E4_LOCK]);
    }
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Gate-3 staging lifecycle failed.',
        'error_message' => $error->getMessage(),
    ]);
}
