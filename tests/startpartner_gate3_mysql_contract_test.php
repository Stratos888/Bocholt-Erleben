<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate3_domain.php';

$dsn = getenv('STARTPARTNER_TEST_DSN') ?: '';
$user = getenv('STARTPARTNER_TEST_USER') ?: '';
$password = getenv('STARTPARTNER_TEST_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "STARTPARTNER_TEST_DSN is required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$scalar = static function(PDO $pdo, string $sql, array $params = []): mixed {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
};

$candidateId = '00000000-0000-4000-8000-000000000321';
$email = 'gate3-domain@example.org';
$operationId = 'gate3:231:mysql-contract';
$lockedTables = [
    'subscriptions','organizer_magic_links','organizer_portal_sessions',
    'submissions','publication_entitlements','publication_consumptions',
];
$lockedBefore = [];
foreach ($lockedTables as $table) {
    $lockedBefore[$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

$cleanup = static function(PDO $pdo, string $candidateId, string $email): void {
    $pilotStatement = $pdo->prepare('SELECT id, organizer_id FROM startpartner_pilots WHERE candidate_id = :candidate_id');
    $pilotStatement->execute(['candidate_id' => $candidateId]);
    $pilots = $pilotStatement->fetchAll();
    foreach ($pilots as $pilot) {
        $pilotId = (string)$pilot['id'];
        foreach ([
            'startpartner_pilot_events',
            'startpartner_pilot_entitlements',
            'startpartner_pilot_scopes',
        ] as $table) {
            $statement = $pdo->prepare("DELETE FROM {$table} WHERE pilot_id = :pilot_id");
            $statement->execute(['pilot_id' => $pilotId]);
        }
    }
    $statement = $pdo->prepare('DELETE FROM startpartner_pilots WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_pilot_terms_acceptances WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $caseStatement = $pdo->prepare(
        "SELECT id FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $caseStatement->execute(['candidate_id' => $candidateId]);
    foreach ($caseStatement->fetchAll(PDO::FETCH_COLUMN) as $caseId) {
        $eventDelete = $pdo->prepare('DELETE FROM control_case_events WHERE case_id = :case_id');
        $eventDelete->execute(['case_id' => $caseId]);
    }
    $statement = $pdo->prepare(
        "DELETE FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_operations WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_events WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_contacts WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_qualifications WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_reservations WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidate_decisions WHERE candidate_id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM startpartner_candidates WHERE id = :candidate_id');
    $statement->execute(['candidate_id' => $candidateId]);
    $statement = $pdo->prepare('DELETE FROM organizers WHERE email_normalized = :email');
    $statement->execute(['email' => $email]);
};

$cleanup($pdo, $candidateId, $email);

try {
    $pdo->prepare(
        "INSERT INTO startpartner_candidates (
            id, source, source_reference, organization_name, organization_name_normalized,
            desired_content_scope, status, identity_key, idempotency_key_hash,
            privacy_policy_version, form_version, retention_review_at,
            revision, assigned_to, next_review_at, status_changed_at
         ) VALUES (
            :id, 'targeted_outreach', 'gate3-domain-contract',
            'Gate 3 Domain Organisation', :organization_name_normalized,
            'both', 'accepted_pending_terms', :identity_key, :idempotency_key_hash,
            'privacy-test-v1', 'gate3-domain-v1', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY),
            1, 'Domain Contract', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY), UTC_TIMESTAMP()
         )"
    )->execute([
        'id' => $candidateId,
        'organization_name_normalized' => be_startpartner_normalize_organization('Gate 3 Domain Organisation'),
        'identity_key' => hash('sha256', 'gate3-domain-identity'),
        'idempotency_key_hash' => hash('sha256', 'gate3-domain-idempotency'),
    ]);
    $pdo->prepare(
        'INSERT INTO startpartner_candidate_contacts (
            candidate_id, contact_name, contact_role, email, email_normalized, is_primary
         ) VALUES (
            :candidate_id, :contact_name, :contact_role, :email, :email_normalized, 1
         )'
    )->execute([
        'candidate_id' => $candidateId,
        'contact_name' => 'Erika Domain',
        'contact_role' => 'Pilotkontakt',
        'email' => $email,
        'email_normalized' => $email,
    ]);
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_decisions (
            candidate_id, result, reason, operator_reference, candidate_revision,
            qualification_snapshot_json, capacity_snapshot_json, is_current
         ) VALUES (
            :candidate_id, 'accepted_pending_terms', 'Domain contract acceptance',
            'Domain Contract', 1, JSON_OBJECT('ready', TRUE),
            JSON_OBJECT('active_reservations', 1), 1
         )"
    )->execute(['candidate_id' => $candidateId]);
    $decisionId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_reservations (
            candidate_id, decision_id, status, starts_at, ends_at,
            capacity_snapshot_json, operator_reference
         ) VALUES (
            :candidate_id, :decision_id, 'active', UTC_TIMESTAMP(),
            DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 DAY),
            JSON_OBJECT('active_reservations', 1), 'Domain Contract'
         )"
    )->execute(['candidate_id' => $candidateId, 'decision_id' => $decisionId]);
    $reservationId = (int)$pdo->lastInsertId();

    $capacityBefore = be_startpartner_gate2_capacity($pdo);
    $input = [
        'action' => 'confirm_pilot_terms',
        'operation_id' => $operationId,
        'expected_revision' => 1,
        'operator_name' => 'Domain Contract',
        'terms_version' => 'pilot-terms-v1',
        'terms_reference' => 'repo://docs/pilot-terms-v1',
        'terms_digest' => hash('sha256', 'gate3-domain-terms-v1'),
        'accepting_person' => 'Erika Domain',
        'accepting_organization' => 'Gate 3 Domain Organisation',
        'accepted_at' => gmdate('c'),
        'confirmation_channel' => 'operator_recorded',
        'target_plan_keys' => ['active', 'activity_basic'],
        'cohort_key' => 'pilot-contract-2026',
        'event_limit_per_pilot_month' => 8,
        'activity_concurrent_limit' => 1,
        'is_event_unlimited' => false,
        'source_care_text' => 'Freigegebene Quellen werden im Pilot gepflegt.',
        'maintenance_scope_text' => 'Vereinbarte redaktionelle Pilotpflege.',
        'reach_contribution_text' => 'Partner verweist auf freigegebene Inhalte.',
        'privacy_notice_version' => 'privacy-test-v1',
        'communication_notice_version' => 'communication-test-v1',
        'planned_activation_start' => gmdate('Y-m-d', strtotime('+7 days')),
        'planned_activation_end' => gmdate('Y-m-d', strtotime('+6 months +7 days')),
        'no_automatic_paid_renewal' => true,
    ];

    $result = be_startpartner_gate3_confirm($pdo, $candidateId, $input);
    $assert(($result['idempotent_replay'] ?? true) === false, 'First Gate-3 operation must not be a replay.');
    $pilotId = (string)($result['meta']['pilot_id'] ?? '');
    $organizerId = (int)($result['meta']['organizer_id'] ?? 0);
    $assert($pilotId !== '', 'Gate-3 operation must return a pilot id.');
    $assert($organizerId > 0, 'Gate-3 operation must return an organizer id.');
    $assert(($result['meta']['reservation_id'] ?? null) === $reservationId, 'Pilot must reference the existing active reservation.');
    $assert((int)$scalar($pdo, 'SELECT revision FROM startpartner_candidates WHERE id = :id', ['id' => $candidateId]) === 2, 'Candidate revision must increment once.');
    $assert((string)$scalar($pdo, 'SELECT status FROM startpartner_candidates WHERE id = :id', ['id' => $candidateId]) === 'accepted_pending_terms', 'Gate 3 must not overstate activation through candidate status.');
    $assert((string)$scalar($pdo, 'SELECT status FROM startpartner_candidate_reservations WHERE id = :id', ['id' => $reservationId]) === 'active', 'Reservation must remain active through onboarding.');
    $assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilots WHERE candidate_id = :id', ['id' => $candidateId]) === 1, 'Exactly one pilot must be created.');
    $assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilot_terms_acceptances WHERE candidate_id = :id', ['id' => $candidateId]) === 1, 'Exactly one terms acceptance must be created.');
    $assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilot_scopes WHERE pilot_id = :id', ['id' => $pilotId]) === 7, 'Normalized Gate-3 scopes are incomplete.');
    $assert((string)$scalar($pdo, "SELECT target_plan_key FROM startpartner_pilot_scopes WHERE pilot_id = :id AND scope_key = 'events'", ['id' => $pilotId]) === 'active', 'Events scope must persist target_plan_key=active.');
    $assert((string)$scalar($pdo, "SELECT target_plan_key FROM startpartner_pilot_scopes WHERE pilot_id = :id AND scope_key = 'activities'", ['id' => $pilotId]) === 'activity_basic', 'Activities scope must persist target_plan_key=activity_basic.');
    $assert((string)$scalar($pdo, 'SELECT status FROM startpartner_pilot_entitlements WHERE pilot_id = :id', ['id' => $pilotId]) === 'pending_activation', 'Pilot grant must remain pending_activation.');
    $assert($scalar($pdo, 'SELECT starts_at FROM startpartner_pilot_entitlements WHERE pilot_id = :id', ['id' => $pilotId]) === null, 'Pending pilot grant must not have a start timestamp.');
    $assert($scalar($pdo, 'SELECT ends_at FROM startpartner_pilot_entitlements WHERE pilot_id = :id', ['id' => $pilotId]) === null, 'Pending pilot grant must not have an end timestamp.');

    $capacityAfter = be_startpartner_gate2_capacity($pdo);
    $assert(
        (int)$capacityAfter['active_reservations'] === (int)$capacityBefore['active_reservations'],
        'Gate 3 must preserve occupied capacity through the existing active reservation.'
    );

    foreach ($lockedTables as $table) {
        $after = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $assert($after === $lockedBefore[$table], "Locked table changed during Gate-3 domain action: {$table}");
    }

    $replay = be_startpartner_gate3_confirm($pdo, $candidateId, $input);
    $assert(($replay['idempotent_replay'] ?? false) === true, 'Identical operation retry must replay.');
    $assert((string)($replay['meta']['pilot_id'] ?? '') === $pilotId, 'Replay must return the same pilot id.');
    $assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilots WHERE candidate_id = :id', ['id' => $candidateId]) === 1, 'Replay must not duplicate the pilot.');

    $changedConflict = false;
    try {
        $changed = $input;
        $changed['cohort_key'] = 'changed-cohort';
        be_startpartner_gate3_confirm($pdo, $candidateId, $changed);
    } catch (BeStartpartnerConflictException $expected) {
        $changedConflict = true;
    }
    $assert($changedConflict, 'Changed payload with the same operation id must conflict.');

    $staleConflict = false;
    try {
        $stale = $input;
        $stale['operation_id'] = 'gate3:231:mysql-contract-stale';
        be_startpartner_gate3_confirm($pdo, $candidateId, $stale);
    } catch (BeStartpartnerConflictException $expected) {
        $staleConflict = true;
    }
    $assert($staleConflict, 'Stale expected revision must conflict.');

    $guarded = false;
    try {
        be_startpartner_gate3_guard_gate2_action($pdo, $candidateId, 'release_reservation');
    } catch (DomainException $expected) {
        $guarded = true;
    }
    $assert($guarded, 'Reservation release must be blocked after pilot creation.');

    $state = be_startpartner_gate3_state($pdo, $candidateId, true);
    $assert(($state['complete'] ?? false) === true, 'Gate-3 readback must be complete.');
    $assert((int)($state['organizer']['id'] ?? 0) === $organizerId, 'Gate-3 readback organizer mismatch.');
    $assert((string)($state['pilot']['id'] ?? '') === $pilotId, 'Gate-3 readback pilot mismatch.');
    $assert((string)($state['entitlement']['status'] ?? '') === 'pending_activation', 'Gate-3 readback entitlement mismatch.');
} catch (Throwable $error) {
    $failures[] = 'Gate-3 domain lifecycle failed: ' . $error->getMessage();
} finally {
    try {
        $cleanup($pdo, $candidateId, $email);
    } catch (Throwable $cleanupError) {
        $failures[] = 'Gate-3 cleanup failed: ' . $cleanupError->getMessage();
    }
}

$assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilots WHERE candidate_id = :id', ['id' => $candidateId]) === 0, 'Cleanup must leave zero pilot residue.');
$assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilot_terms_acceptances WHERE candidate_id = :id', ['id' => $candidateId]) === 0, 'Cleanup must leave zero terms residue.');
$assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_candidates WHERE id = :id', ['id' => $candidateId]) === 0, 'Cleanup must leave zero candidate residue.');
$assert((int)$scalar($pdo, 'SELECT COUNT(*) FROM organizers WHERE email_normalized = :email', ['email' => $email]) === 0, 'Cleanup must remove only the synthetic organizer.');
foreach ($lockedTables as $table) {
    $afterCleanup = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    $assert($afterCleanup === $lockedBefore[$table], "Locked table count differs after cleanup: {$table}");
}

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-3 MySQL Domain Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-3 MySQL Domain Contract: OK ===\n";
