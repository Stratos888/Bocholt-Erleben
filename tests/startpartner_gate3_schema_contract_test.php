<?php
declare(strict_types=1);

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

$required = [
    'startpartner_pilot_terms_acceptances' => [
        'candidate_id','decision_id','terms_version','terms_reference','terms_digest',
        'accepting_person','accepting_organization','accepted_at','confirmation_channel',
        'service_scope_json','source_care_json','reach_contribution_json',
        'no_automatic_paid_renewal','operator_reference',
    ],
    'startpartner_pilots' => [
        'id','candidate_id','organizer_id','terms_acceptance_id','reservation_id',
        'cohort_key','status','target_plan_keys_json','internal_owner',
        'onboarding_started_at','starts_at','ends_at','revision',
    ],
    'startpartner_pilot_scopes' => [
        'pilot_id','scope_key','scope_type','status','target_plan_key',
        'limit_value','is_unlimited','period_unit','details_json',
    ],
    'startpartner_pilot_entitlements' => [
        'id','pilot_id','organizer_id','source_type','source_reference','status',
        'starts_at','ends_at','target_plan_keys_json','event_limit_per_pilot_month',
        'activity_concurrent_limit','is_event_unlimited','source_scope_json','audit_json','revision',
    ],
    'startpartner_pilot_events' => ['pilot_id','event_type','actor_reference','payload_json','created_at'],
];

$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$columns = $pdo->prepare(
    'SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema_name'
);
$columns->execute(['schema_name' => $database]);
$present = [];
foreach ($columns->fetchAll() as $row) {
    $present[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']] = true;
}
foreach ($required as $table => $names) {
    $assert(isset($present[$table]), "Gate-3 table missing: {$table}");
    foreach ($names as $name) {
        $assert(isset($present[$table][$name]), "Gate-3 column missing: {$table}.{$name}");
    }
}
$assert(
    (int)$scalar(
        $pdo,
        "SELECT COUNT(*) FROM app_schema_migrations
         WHERE migration_key = '011_startpartner_gate3_terms_organizer_entitlement'"
    ) === 1,
    'Migration 011 must be recorded exactly once.'
);

$lockedTables = [
    'subscriptions','organizer_magic_links','organizer_portal_sessions',
    'submissions','publication_entitlements','publication_consumptions',
];
$before = [];
foreach ($lockedTables as $table) {
    $before[$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

$candidateId = '00000000-0000-4000-8000-000000000311';
$pilotId = '00000000-0000-4000-8000-000000000312';
$entitlementId = '00000000-0000-4000-8000-000000000313';
$email = 'gate3-schema@example.org';

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO organizers (
            organization_name, contact_name, email, email_normalized, default_plan_key
         ) VALUES (
            :organization_name, :contact_name, :email, :email_normalized, NULL
         )'
    )->execute([
        'organization_name' => 'Gate 3 Schema Organisation',
        'contact_name' => 'Schema Kontakt',
        'email' => $email,
        'email_normalized' => $email,
    ]);
    $organizerId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO startpartner_candidates (
            id, source, source_reference, organization_name, organization_name_normalized,
            desired_content_scope, status, identity_key, idempotency_key_hash,
            privacy_policy_version, form_version, retention_review_at,
            revision, assigned_to, next_review_at, status_changed_at
         ) VALUES (
            :id, 'targeted_outreach', 'gate3-schema-contract',
            'Gate 3 Schema Organisation', 'gate 3 schema organisation',
            'both', 'accepted_pending_terms', :identity_key, :idempotency_key_hash,
            'privacy-test-v1', 'gate3-schema-v1', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY),
            1, 'Schema Contract', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY), UTC_TIMESTAMP()
         )"
    )->execute([
        'id' => $candidateId,
        'identity_key' => hash('sha256', 'gate3-schema-identity'),
        'idempotency_key_hash' => hash('sha256', 'gate3-schema-idempotency'),
    ]);
    $pdo->prepare(
        'INSERT INTO startpartner_candidate_contacts (
            candidate_id, contact_name, contact_role, email, email_normalized, is_primary
         ) VALUES (
            :candidate_id, :contact_name, :contact_role, :email, :email_normalized, 1
         )'
    )->execute([
        'candidate_id' => $candidateId,
        'contact_name' => 'Schema Kontakt',
        'contact_role' => 'Pilotkontakt',
        'email' => $email,
        'email_normalized' => $email,
    ]);
    $pdo->prepare(
        "INSERT INTO startpartner_candidate_decisions (
            candidate_id, result, reason, operator_reference, candidate_revision,
            qualification_snapshot_json, capacity_snapshot_json, is_current
         ) VALUES (
            :candidate_id, 'accepted_pending_terms', 'Schema contract acceptance',
            'Schema Contract', 1, JSON_OBJECT('ready', TRUE),
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
            JSON_OBJECT('active_reservations', 1), 'Schema Contract'
         )"
    )->execute(['candidate_id' => $candidateId, 'decision_id' => $decisionId]);
    $reservationId = (int)$pdo->lastInsertId();

    $digest = hash('sha256', 'gate3-terms-v1');
    $pdo->prepare(
        "INSERT INTO startpartner_pilot_terms_acceptances (
            candidate_id, decision_id, terms_version, terms_reference, terms_digest,
            accepting_person, accepting_organization, accepted_at, confirmation_channel,
            service_scope_json, source_care_json, reach_contribution_json,
            privacy_notice_version, communication_notice_version,
            no_automatic_paid_renewal, operator_reference
         ) VALUES (
            :candidate_id, :decision_id, 'pilot-terms-v1',
            'repo://docs/pilot-terms-v1', :terms_digest,
            'Schema Kontakt', 'Gate 3 Schema Organisation', UTC_TIMESTAMP(),
            'operator_recorded', JSON_OBJECT('content_scope', 'both'),
            JSON_OBJECT('mode', 'manual'), JSON_OBJECT('channel', 'partner'),
            'privacy-test-v1', 'communication-test-v1', 1, 'Schema Contract'
         )"
    )->execute([
        'candidate_id' => $candidateId,
        'decision_id' => $decisionId,
        'terms_digest' => $digest,
    ]);
    $termsId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO startpartner_pilots (
            id, candidate_id, organizer_id, terms_acceptance_id, reservation_id,
            cohort_key, status, target_plan_keys_json, internal_owner,
            partner_contact_name_snapshot, partner_contact_email_snapshot
         ) VALUES (
            :id, :candidate_id, :organizer_id, :terms_acceptance_id, :reservation_id,
            'cohort-contract', 'onboarding', JSON_ARRAY('active'),
            'Schema Contract', 'Schema Kontakt', :email
         )"
    )->execute([
        'id' => $pilotId,
        'candidate_id' => $candidateId,
        'organizer_id' => $organizerId,
        'terms_acceptance_id' => $termsId,
        'reservation_id' => $reservationId,
        'email' => $email,
    ]);

    $scopeInsert = $pdo->prepare(
        "INSERT INTO startpartner_pilot_scopes (
            pilot_id, scope_key, scope_type, status, target_plan_key,
            limit_value, is_unlimited, period_unit, details_json
         ) VALUES (
            :pilot_id, :scope_key, :scope_type, 'planned', :target_plan_key,
            :limit_value, 0, :period_unit, :details_json
         )"
    );
    $scopeInsert->execute([
        'pilot_id' => $pilotId,
        'scope_key' => 'events',
        'scope_type' => 'events',
        'target_plan_key' => 'active',
        'limit_value' => 8,
        'period_unit' => 'pilot_month',
        'details_json' => json_encode(['mode' => 'curated'], JSON_THROW_ON_ERROR),
    ]);
    $scopeInsert->execute([
        'pilot_id' => $pilotId,
        'scope_key' => 'activities',
        'scope_type' => 'activities',
        'target_plan_key' => null,
        'limit_value' => 1,
        'period_unit' => 'concurrent',
        'details_json' => json_encode(['mode' => 'presence'], JSON_THROW_ON_ERROR),
    ]);

    $pdo->prepare(
        "INSERT INTO startpartner_pilot_entitlements (
            id, pilot_id, organizer_id, source_reference, status,
            target_plan_keys_json, event_limit_per_pilot_month,
            activity_concurrent_limit, is_event_unlimited,
            source_scope_json, audit_json
         ) VALUES (
            :id, :pilot_id, :organizer_id, :source_reference, 'pending_activation',
            JSON_ARRAY('active'), 8, 1, 0,
            JSON_OBJECT('content_scope', 'both'),
            JSON_OBJECT('created_by', 'schema-contract')
         )"
    )->execute([
        'id' => $entitlementId,
        'pilot_id' => $pilotId,
        'organizer_id' => $organizerId,
        'source_reference' => $pilotId,
    ]);
    $pdo->prepare(
        "INSERT INTO startpartner_pilot_events (
            pilot_id, event_type, actor_reference, payload_json
         ) VALUES (
            :pilot_id, 'pilot_created', 'Schema Contract',
            JSON_OBJECT('entitlement_status', 'pending_activation')
         )"
    )->execute(['pilot_id' => $pilotId]);

    $assert(
        (string)$scalar(
            $pdo,
            'SELECT status FROM startpartner_candidate_reservations WHERE id = :id',
            ['id' => $reservationId]
        ) === 'active',
        'Gate 3 must keep the candidate reservation active during onboarding.'
    );
    $grant = $pdo->prepare(
        'SELECT status, starts_at, ends_at, source_type
         FROM startpartner_pilot_entitlements WHERE id = :id'
    );
    $grant->execute(['id' => $entitlementId]);
    $grantRow = $grant->fetch();
    $assert(($grantRow['status'] ?? '') === 'pending_activation', 'Pilot grant must start pending_activation.');
    $assert(($grantRow['starts_at'] ?? null) === null, 'Pending pilot grant must not have starts_at.');
    $assert(($grantRow['ends_at'] ?? null) === null, 'Pending pilot grant must not have ends_at.');
    $assert(($grantRow['source_type'] ?? '') === 'startpartner_pilot', 'Pilot grant source must be startpartner_pilot.');

    foreach ($lockedTables as $table) {
        $after = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $assert($after === $before[$table], "Locked table changed during Gate-3 schema contract: {$table}");
    }

    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $failures[] = 'Gate-3 schema lifecycle failed: ' . $error->getMessage();
}

$assert(
    (int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_pilots WHERE id = :id', ['id' => $pilotId]) === 0,
    'Schema contract rollback must leave no pilot residue.'
);
$assert(
    (int)$scalar($pdo, 'SELECT COUNT(*) FROM startpartner_candidates WHERE id = :id', ['id' => $candidateId]) === 0,
    'Schema contract rollback must leave no candidate residue.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-3 Schema Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-3 Schema Contract: OK ===\n";
