<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate2_domain.php';

$dsn = getenv('STARTPARTNER_TEST_DSN') ?: '';
$user = getenv('STARTPARTNER_TEST_USER') ?: '';
$password = getenv('STARTPARTNER_TEST_PASSWORD') ?: '';
if ($dsn === '' || $user === '') {
    exit(2);
}

$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$expectException = static function(callable $callback, string $class, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message;
    } catch (Throwable $error) {
        if (!$error instanceof $class) {
            $failures[] = $message . ' (unexpected ' . $error::class . ': ' . $error->getMessage() . ')';
        }
    }
};
$count = static function(string $table, string $where = '1=1', array $params = []) use ($db): int {
    $statement = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $statement->execute($params);
    return (int)$statement->fetchColumn();
};

$lockedTables = [
    'organizers', 'submissions', 'subscriptions',
    'publication_entitlements', 'publication_consumptions',
];
$lockedBefore = [];
foreach ($lockedTables as $table) {
    $lockedBefore[$table] = $count($table);
}

$prefix = 'GATE2_SYNTHETIC_199_RUNTIME_';
$createdIds = [];
$sequence = 1;
$nextId = static function() use (&$sequence): string {
    return sprintf('19900000-0000-0000-0000-%012d', $sequence++);
};
$insertCandidate = static function(string $name, string $status = 'new') use ($db, $nextId, &$createdIds, $prefix): string {
    $id = $nextId();
    $organization = $prefix . $name;
    $statement = $db->prepare(
        'INSERT INTO startpartner_candidates (
            id, source, organization_name, organization_name_normalized,
            desired_content_scope, status, identity_key, idempotency_key_hash,
            form_version, retention_review_at
         ) VALUES (
            :id, \'targeted_outreach\', :organization_name, :organization_name_normalized,
            \'both\', :status, :identity_key, :idempotency_key_hash,
            \'gate2-runtime-contract\', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY)
         )'
    );
    $statement->execute([
        'id' => $id,
        'organization_name' => $organization,
        'organization_name_normalized' => strtolower($organization),
        'status' => $status,
        'identity_key' => hash('sha256', 'identity|' . $id),
        'idempotency_key_hash' => hash('sha256', 'idempotency|' . $id),
    ]);
    $email = strtolower($name) . '-' . substr($id, -4) . '@example.org';
    $db->prepare(
        'INSERT INTO startpartner_candidate_contacts (
            candidate_id, email, email_normalized, is_primary
         ) VALUES (:candidate_id, :email, :email_normalized, 1)'
    )->execute([
        'candidate_id' => $id,
        'email' => $email,
        'email_normalized' => $email,
    ]);
    $createdIds[] = $id;
    return $id;
};
$insertReadyQualifications = static function(string $candidateId) use ($db): void {
    $statement = $db->prepare(
        'INSERT INTO startpartner_candidate_qualifications (
            candidate_id, dimension, assessment, reason, evidence_text, operator_reference
         ) VALUES (
            :candidate_id, :dimension, :assessment, :reason, :evidence_text, \'runtime-contract\'
         )'
    );
    foreach (BE_STARTPARTNER_QUALIFICATION_DIMENSIONS as $dimension) {
        $statement->execute([
            'candidate_id' => $candidateId,
            'dimension' => $dimension,
            'assessment' => in_array($dimension, BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS, true) ? 'adequate' : 'weak',
            'reason' => 'Contract reason ' . $dimension,
            'evidence_text' => 'Contract evidence ' . $dimension,
        ]);
    }
};
$seedReservation = static function(string $name) use ($db, $insertCandidate): string {
    $id = $insertCandidate($name, 'accepted_pending_terms');
    $db->prepare(
        'INSERT INTO startpartner_candidate_reservations (
            candidate_id, status, starts_at, ends_at, capacity_snapshot_json, operator_reference
         ) VALUES (
            :candidate_id, \'active\', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 DAY),
            \'{}\', \'runtime-contract\'
         )'
    )->execute(['candidate_id' => $id]);
    return $id;
};
$operation = static function(string $id, int $revision, string $operator = 'Runtime Contract'): array {
    return [
        'operation_id' => 'gate2:199:' . $id,
        'expected_revision' => $revision,
        'operator_name' => $operator,
    ];
};

try {
    $candidateId = $insertCandidate('PRIMARY');
    $profileInput = $operation('profile-primary', 1) + [
        'candidate_id' => $candidateId,
        'assigned_to' => 'M. Muster',
        'website_url' => 'example.org/primary',
        'next_review_at' => (new DateTimeImmutable('+5 days'))->format(DateTimeInterface::ATOM),
    ];
    $profile = be_startpartner_gate2_profile_update($db, $candidateId, $profileInput);
    $assert((int)$profile['candidate']['revision'] === 2, 'Profile update must increment candidate revision exactly once.');
    $assert($profile['candidate']['website_url'] === 'https://example.org/primary', 'Profile URL must be normalized.');

    $profileReplay = be_startpartner_gate2_profile_update($db, $candidateId, $profileInput);
    $assert($profileReplay['idempotent_replay'] === true, 'Identical operation replay must return the stored result.');
    $assert((int)$profileReplay['candidate']['revision'] === 2, 'Replay must not increment revision.');
    $expectException(
        static fn() => be_startpartner_gate2_profile_update(
            $db,
            $candidateId,
            array_replace($profileInput, ['assigned_to' => 'Andere Person'])
        ),
        BeStartpartnerConflictException::class,
        'Same operation_id with different payload must conflict.'
    );
    $expectException(
        static fn() => be_startpartner_gate2_profile_update(
            $db,
            $candidateId,
            $operation('profile-stale', 1) + ['assigned_to' => 'Stale']
        ),
        BeStartpartnerConflictException::class,
        'Stale candidate revision must conflict.'
    );

    $qualificationItems = array_map(
        static fn(string $dimension): array => [
            'dimension' => $dimension,
            'assessment' => in_array($dimension, BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS, true) ? 'adequate' : 'weak',
            'reason' => 'Reason ' . $dimension,
            'evidence_text' => 'Evidence ' . $dimension,
        ],
        BE_STARTPARTNER_QUALIFICATION_DIMENSIONS
    );
    $qualification = be_startpartner_gate2_qualification_update($db, $candidateId, $operation('qualification-primary', 2) + [
        'qualifications' => $qualificationItems,
    ]);
    $assert((int)$qualification['candidate']['revision'] === 3, 'Qualification batch must increment candidate revision once.');
    $assert($qualification['candidate']['readiness']['ready'] === true, 'All 14 dimensions with minimums must be ready.');
    $assert(count($qualification['candidate']['qualifications']) === 14, 'Read-back must contain all 14 dimensions.');

    $qualifying = be_startpartner_gate2_action($db, $candidateId, $operation('start-qualification-primary', 3) + [
        'action' => 'start_qualification',
    ]);
    $assert($qualifying['candidate']['status'] === 'qualifying', 'Candidate must enter qualifying.');
    $ready = be_startpartner_gate2_action($db, $candidateId, $operation('decision-ready-primary', 4) + [
        'action' => 'mark_decision_ready',
    ]);
    $assert($ready['candidate']['status'] === 'decision_ready', 'Explicit readiness action must set decision_ready.');

    $accepted = be_startpartner_gate2_action($db, $candidateId, $operation('accept-primary', 5) + [
        'action' => 'accept_pending_terms',
        'reason' => 'Strong pilot fit.',
        'reservation_ends_at' => (new DateTimeImmutable('+20 days'))->format(DateTimeInterface::ATOM),
    ]);
    $assert($accepted['candidate']['status'] === 'accepted_pending_terms', 'Acceptance must remain pending terms.');
    $assert(is_array($accepted['candidate']['active_reservation']), 'Acceptance must create one active reservation.');
    $assert($accepted['candidate']['decision']['result'] === 'accepted_pending_terms', 'Acceptance must create current decision.');

    $extended = be_startpartner_gate2_action($db, $candidateId, $operation('extend-primary', 6) + [
        'action' => 'extend_reservation',
        'reason' => 'Controlled extension.',
        'reservation_ends_at' => (new DateTimeImmutable('+25 days'))->format(DateTimeInterface::ATOM),
    ]);
    $assert((int)$extended['candidate']['revision'] === 7, 'Reservation extension must increment revision once.');
    $assert(count($extended['candidate']['reservations']) === 2, 'Reservation extension must retain history.');
    $assert($count('startpartner_candidate_reservations', "candidate_id = :id AND status = 'active'", ['id' => $candidateId]) === 1, 'Exactly one active reservation may remain.');

    $released = be_startpartner_gate2_action($db, $candidateId, $operation('release-primary', 7) + [
        'action' => 'release_reservation',
        'reason' => 'Return to decision.',
        'target_status' => 'decision_ready',
    ]);
    $assert($released['candidate']['status'] === 'decision_ready', 'Reservation release must return to decision_ready.');
    $assert($released['candidate']['active_reservation'] === null, 'Released reservation must no longer be active.');

    $downgrade = be_startpartner_gate2_qualification_update($db, $candidateId, $operation('downgrade-primary', 8) + [
        'qualifications' => [[
            'dimension' => 'local_relevance',
            'assessment' => 'weak',
            'reason' => 'Local link no longer sufficient.',
            'evidence_text' => 'Updated evidence.',
        ]],
    ]);
    $assert($downgrade['candidate']['status'] === 'qualifying', 'Worsened hard qualification must revoke decision readiness.');
    $assert($downgrade['candidate']['readiness']['ready'] === false, 'Downgraded hard qualification must block readiness.');

    for ($index = 1; $index <= 6; $index++) {
        $seedReservation('SOFT_FIXTURE_' . $index);
    }
    $softCandidate = $insertCandidate('SOFT_CANDIDATE', 'decision_ready');
    $insertReadyQualifications($softCandidate);
    $expectException(
        static fn() => be_startpartner_gate2_action($db, $softCandidate, $operation('accept-soft-missing-reason', 1) + [
            'action' => 'accept_pending_terms',
            'reason' => 'Suitable.',
            'reservation_ends_at' => (new DateTimeImmutable('+10 days'))->format(DateTimeInterface::ATOM),
        ]),
        InvalidArgumentException::class,
        'Soft stop must require an explicit capacity reason.'
    );
    $softAccepted = be_startpartner_gate2_action($db, $softCandidate, $operation('accept-soft-with-reason', 1) + [
        'action' => 'accept_pending_terms',
        'reason' => 'Suitable.',
        'capacity_exception_reason' => 'Controlled seventh reservation.',
        'reservation_ends_at' => (new DateTimeImmutable('+10 days'))->format(DateTimeInterface::ATOM),
    ]);
    $assert($softAccepted['candidate']['status'] === 'accepted_pending_terms', 'Soft stop override must allow seventh reservation.');

    $seedReservation('HARD_FIXTURE');
    $hardCandidate = $insertCandidate('HARD_CANDIDATE', 'decision_ready');
    $insertReadyQualifications($hardCandidate);
    $capacityAtHardStop = be_startpartner_gate2_capacity($db);
    $assert($capacityAtHardStop['active_reservations'] === 8 && $capacityAtHardStop['hard_stop'], 'Eight active reservations must trigger hard stop.');
    $expectException(
        static fn() => be_startpartner_gate2_action($db, $hardCandidate, $operation('accept-hard', 1) + [
            'action' => 'accept_pending_terms',
            'reason' => 'Would exceed capacity.',
            'capacity_exception_reason' => 'Must not override hard stop.',
            'reservation_ends_at' => (new DateTimeImmutable('+10 days'))->format(DateTimeInterface::ATOM),
        ]),
        DomainException::class,
        'Hard stop must reject a ninth reservation.'
    );
    $assert((int)be_startpartner_gate2_candidate_detail($db, $hardCandidate)['revision'] === 1, 'Rejected hard-stop operation must not increment revision.');

    $waitlistCandidate = $insertCandidate('WAITLIST', 'decision_ready');
    $insertReadyQualifications($waitlistCandidate);
    $waitlisted = be_startpartner_gate2_action($db, $waitlistCandidate, $operation('waitlist', 1) + [
        'action' => 'waitlist',
        'reason' => 'Capacity currently exhausted.',
        'eligibility_reason' => 'Suitable candidate.',
        'priority_reason' => 'High local relevance.',
        'next_review_at' => (new DateTimeImmutable('+14 days'))->format(DateTimeInterface::ATOM),
        'contact_status' => 'paused',
        'regular_alternative' => 'Reguläres Sichtbarkeitspaket',
    ]);
    $assert($waitlisted['candidate']['status'] === 'waitlisted', 'Waitlist action must set normalized status.');
    $assert(is_array($waitlisted['candidate']['waitlist']), 'Waitlist action must create normalized owner.');
    $assert($waitlisted['candidate']['decision']['result'] === 'waitlisted', 'Waitlist action must append current decision.');

    $projection = $db->prepare(
        "SELECT state, decision_ready, source_payload_json
         FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
    );
    $projection->execute(['candidate_id' => $waitlistCandidate]);
    $projectionRow = $projection->fetch();
    $projectionPayload = json_decode((string)($projectionRow['source_payload_json'] ?? ''), true);
    $assert(($projectionRow['state'] ?? '') === 'parked', 'Waitlist projection must be parked.');
    $assert((int)($projectionRow['decision_ready'] ?? 1) === 0, 'Waitlist projection must not remain decision-ready.');
    $assert((int)($projectionPayload['candidate_revision'] ?? 0) === 2, 'Projection must contain current candidate revision.');

    foreach ($lockedTables as $table) {
        $assert($count($table) === $lockedBefore[$table], "Gate-2 runtime must not change {$table}.");
    }
} finally {
    foreach ($createdIds as $candidateId) {
        $db->prepare(
            "DELETE FROM control_cases
             WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
        )->execute(['candidate_id' => $candidateId]);
    }
    if ($createdIds !== []) {
        $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
        $db->prepare("DELETE FROM startpartner_candidates WHERE id IN ({$placeholders})")->execute($createdIds);
    }
}

foreach ($createdIds as $candidateId) {
    $assert($count('startpartner_candidates', 'id = :id', ['id' => $candidateId]) === 0, 'Candidate cleanup must leave zero residue.');
    $assert($count('startpartner_candidate_operations', 'candidate_id = :id', ['id' => $candidateId]) === 0, 'Operation cleanup must cascade.');
    $assert($count('startpartner_candidate_reservations', 'candidate_id = :id', ['id' => $candidateId]) === 0, 'Reservation cleanup must cascade.');
}
foreach ($lockedTables as $table) {
    $assert($count($table) === $lockedBefore[$table], "Locked table {$table} must remain unchanged after cleanup.");
}

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-2 Runtime MariaDB Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-2 Runtime MariaDB Contract: OK ===\n";
