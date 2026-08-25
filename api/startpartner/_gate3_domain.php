<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate2_domain.php';

const BE_STARTPARTNER_GATE3_REQUIRED_SCHEMA = [
    'organizers' => ['id', 'organization_name', 'contact_name', 'email', 'email_normalized'],
    'startpartner_pilot_terms_acceptances' => [
        'id', 'candidate_id', 'decision_id', 'terms_version', 'terms_reference',
        'terms_digest', 'accepting_person', 'accepting_organization', 'accepted_at',
        'confirmation_channel', 'service_scope_json', 'source_care_json',
        'reach_contribution_json', 'planned_activation_start', 'planned_activation_end',
        'privacy_notice_version', 'communication_notice_version',
        'no_automatic_paid_renewal', 'operator_reference', 'created_at',
    ],
    'startpartner_pilots' => [
        'id', 'candidate_id', 'organizer_id', 'terms_acceptance_id', 'reservation_id',
        'cohort_key', 'status', 'health', 'target_plan_keys_json', 'internal_owner',
        'partner_contact_name_snapshot', 'partner_contact_email_snapshot',
        'onboarding_started_at', 'starts_at', 'ends_at', 'revision',
        'created_at', 'updated_at',
    ],
    'startpartner_pilot_scopes' => [
        'id', 'pilot_id', 'scope_key', 'scope_type', 'status', 'target_plan_key',
        'limit_value', 'is_unlimited', 'period_unit', 'details_json',
        'created_at', 'updated_at',
    ],
    'startpartner_pilot_entitlements' => [
        'id', 'pilot_id', 'organizer_id', 'source_type', 'source_reference',
        'status', 'starts_at', 'ends_at', 'target_plan_keys_json',
        'event_limit_per_pilot_month', 'activity_concurrent_limit',
        'is_event_unlimited', 'source_scope_json', 'audit_json', 'revision',
        'created_at', 'updated_at',
    ],
    'startpartner_pilot_events' => [
        'id', 'pilot_id', 'event_type', 'actor_reference', 'payload_json', 'created_at',
    ],
];

const BE_STARTPARTNER_GATE3_CHANNELS = [
    'operator_recorded',
    'signed_document',
    'email_reply',
    'portal',
];

function be_startpartner_gate3_schema_gaps(PDO $pdo): array
{
    $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        return ['database' => ['No database selected.']];
    }

    $statement = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema_name'
    );
    $statement->execute(['schema_name' => $databaseName]);
    $present = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string)($row['TABLE_NAME'] ?? '');
        $column = (string)($row['COLUMN_NAME'] ?? '');
        if ($table !== '' && $column !== '') {
            $present[$table][$column] = true;
        }
    }

    $gaps = [];
    foreach (BE_STARTPARTNER_GATE3_REQUIRED_SCHEMA as $table => $columns) {
        if (!isset($present[$table])) {
            $gaps[$table] = ['table missing'];
            continue;
        }
        foreach ($columns as $column) {
            if (!isset($present[$table][$column])) {
                $gaps[$table][] = $column;
            }
        }
    }
    return $gaps;
}

function be_startpartner_gate3_require_schema(PDO $pdo): void
{
    $gaps = be_startpartner_gate3_schema_gaps($pdo);
    if ($gaps !== []) {
        throw new RuntimeException(
            'STARTPARTNER_GATE3_SCHEMA_MISSING: ' . json_encode(
                $gaps,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
    }
}

function be_startpartner_gate3_digest(mixed $value): string
{
    $digest = strtolower(trim((string)$value));
    if (!preg_match('/^[0-9a-f]{64}$/', $digest)) {
        throw new InvalidArgumentException('terms_digest must be a lowercase SHA-256.');
    }
    return $digest;
}

function be_startpartner_gate3_datetime(mixed $value, string $field): string
{
    $text = trim((string)$value);
    if ($text === '') {
        throw new InvalidArgumentException("{$field} is required.");
    }
    $date = (new DateTimeImmutable($text))->setTimezone(new DateTimeZone('UTC'));
    $latest = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+5 minutes');
    if ($date > $latest) {
        throw new InvalidArgumentException("{$field} must not be in the future.");
    }
    return $date->format('Y-m-d H:i:s');
}

function be_startpartner_gate3_optional_date(mixed $value, string $field): ?string
{
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($text, new DateTimeZone('UTC')))->format('Y-m-d');
    } catch (Throwable $error) {
        throw new InvalidArgumentException("{$field} is invalid.", 0, $error);
    }
}

function be_startpartner_gate3_plan_keys(mixed $value): array
{
    $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($items) || $items === [] || count($items) > 5) {
        throw new InvalidArgumentException('target_plan_keys must contain one to five entries.');
    }
    $result = [];
    foreach ($items as $item) {
        $key = strtolower(trim((string)$item));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $key)) {
            throw new InvalidArgumentException('target_plan_keys contains an invalid entry.');
        }
        $result[$key] = true;
    }
    return array_keys($result);
}

function be_startpartner_gate3_scope_target_plan_key(string $scopeKey): string
{
    return match ($scopeKey) {
        'events' => 'active',
        'activities' => 'activity_basic',
        default => throw new InvalidArgumentException('Unsupported content scope target-plan mapping.'),
    };
}

function be_startpartner_gate3_validate_target_plan_contract(string $desiredScope, array $targetPlanKeys): array
{
    $expected = match ($desiredScope) {
        'events' => ['active'],
        'activities' => ['activity_basic'],
        'both' => ['active', 'activity_basic'],
        default => throw new DomainException('Candidate content scope must be resolved before Gate 3.'),
    };
    $actual = array_values(array_unique(array_map(
        static fn(mixed $key): string => strtolower(trim((string)$key)),
        $targetPlanKeys
    )));
    $missing = array_values(array_diff($expected, $actual));
    $unexpected = array_values(array_diff($actual, $expected));
    if ($missing !== [] || $unexpected !== []) {
        throw new DomainException('target_plan_keys do not match the candidate content scope.');
    }
    return $expected;
}

function be_startpartner_gate3_positive_int(mixed $value, string $field, int $max): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 1 || $number > $max) {
        throw new InvalidArgumentException("{$field} must be between 1 and {$max}.");
    }
    return (int)$number;
}

function be_startpartner_gate3_primary_contact(PDO $pdo, string $candidateId): array
{
    $statement = $pdo->prepare(
        'SELECT id, contact_name, contact_role, email, email_normalized, phone
         FROM startpartner_candidate_contacts
         WHERE candidate_id = :candidate_id AND is_primary = 1
         ORDER BY id ASC LIMIT 1 FOR UPDATE'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new DomainException('Primary candidate contact is missing.');
    }
    return $row;
}

function be_startpartner_gate3_current_decision(PDO $pdo, string $candidateId): array
{
    $statement = $pdo->prepare(
        "SELECT * FROM startpartner_candidate_decisions
         WHERE candidate_id = :candidate_id AND is_current = 1
         ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)$row['result'] !== 'accepted_pending_terms') {
        throw new DomainException('Current accepted_pending_terms decision is missing.');
    }
    return $row;
}

function be_startpartner_gate3_active_reservation(PDO $pdo, string $candidateId, int $decisionId): array
{
    $statement = $pdo->prepare(
        "SELECT * FROM startpartner_candidate_reservations
         WHERE candidate_id = :candidate_id
           AND decision_id = :decision_id
           AND status = 'active'
         ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $statement->execute([
        'candidate_id' => $candidateId,
        'decision_id' => $decisionId,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new DomainException('Active reservation for the current decision is missing.');
    }
    $endsAt = new DateTimeImmutable((string)$row['ends_at'], new DateTimeZone('UTC'));
    if ($endsAt <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
        throw new DomainException('Active reservation is expired.');
    }
    return $row;
}

function be_startpartner_gate3_organizer_compatible(array $organizer, array $candidate, array $contact): bool
{
    return hash_equals(
        be_startpartner_normalize_organization((string)$organizer['organization_name']),
        (string)$candidate['organization_name_normalized']
    ) && hash_equals(
        strtolower(trim((string)$organizer['email_normalized'])),
        strtolower(trim((string)$contact['email_normalized']))
    );
}

function be_startpartner_gate3_resolve_organizer(
    PDO $pdo,
    array $candidate,
    array $contact,
    ?int $explicitOrganizerId
): array {
    if ($explicitOrganizerId !== null) {
        $statement = $pdo->prepare('SELECT * FROM organizers WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $explicitOrganizerId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !be_startpartner_gate3_organizer_compatible($row, $candidate, $contact)) {
            throw new DomainException('Explicit organizer identity does not match candidate organization and primary email.');
        }
        return ['organizer' => $row, 'created' => false];
    }

    $emailStatement = $pdo->prepare(
        'SELECT * FROM organizers WHERE email_normalized = :email_normalized LIMIT 2 FOR UPDATE'
    );
    $emailStatement->execute(['email_normalized' => (string)$contact['email_normalized']]);
    $emailMatches = $emailStatement->fetchAll(PDO::FETCH_ASSOC);
    if (count($emailMatches) > 1) {
        throw new DomainException('Organizer email identity is ambiguous.');
    }
    if (count($emailMatches) === 1) {
        if (!be_startpartner_gate3_organizer_compatible($emailMatches[0], $candidate, $contact)) {
            throw new DomainException('Organizer email exists for an incompatible organization.');
        }
        return ['organizer' => $emailMatches[0], 'created' => false];
    }

    $organizationStatement = $pdo->prepare(
        'SELECT id, organization_name, email, email_normalized
         FROM organizers
         WHERE organization_name = :organization_name
         LIMIT 2 FOR UPDATE'
    );
    $organizationStatement->execute(['organization_name' => (string)$candidate['organization_name']]);
    if ($organizationStatement->fetchAll(PDO::FETCH_ASSOC) !== []) {
        throw new DomainException('Organizer organization exists with another email; manual identity resolution is required.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO organizers (
            organization_name, contact_name, email, email_normalized, default_plan_key
         ) VALUES (
            :organization_name, :contact_name, :email, :email_normalized, NULL
         )'
    );
    $insert->execute([
        'organization_name' => (string)$candidate['organization_name'],
        'contact_name' => $contact['contact_name'] ?? null,
        'email' => (string)$contact['email'],
        'email_normalized' => (string)$contact['email_normalized'],
    ]);
    $organizerId = (int)$pdo->lastInsertId();
    $statement = $pdo->prepare('SELECT * FROM organizers WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $organizerId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Created organizer could not be read back.');
    }
    return ['organizer' => $row, 'created' => true];
}

function be_startpartner_gate3_normalize_confirmation(
    array $candidate,
    array $contact,
    array $input
): array {
    $acceptingOrganization = (string)be_startpartner_clean_text(
        $input['accepting_organization'] ?? null,
        190,
        'accepting_organization',
        true
    );
    if (!hash_equals(
        be_startpartner_normalize_organization($acceptingOrganization),
        (string)$candidate['organization_name_normalized']
    )) {
        throw new DomainException('Accepting organization must match the candidate organization.');
    }

    $desiredScope = (string)$candidate['desired_content_scope'];
    if (!in_array($desiredScope, ['events', 'activities', 'both'], true)) {
        throw new DomainException('Candidate content scope must be resolved before Gate 3.');
    }
    $targetPlanKeys = be_startpartner_gate3_validate_target_plan_contract(
        $desiredScope,
        be_startpartner_gate3_plan_keys($input['target_plan_keys'] ?? null)
    );

    $isEventUnlimited = filter_var(
        $input['is_event_unlimited'] ?? false,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );
    if ($isEventUnlimited === null) {
        throw new InvalidArgumentException('is_event_unlimited is invalid.');
    }
    $eventLimit = null;
    if (in_array($desiredScope, ['events', 'both'], true) && !$isEventUnlimited) {
        $eventLimit = be_startpartner_gate3_positive_int(
            $input['event_limit_per_pilot_month'] ?? null,
            'event_limit_per_pilot_month',
            1000
        );
    }
    $activityLimit = null;
    if (in_array($desiredScope, ['activities', 'both'], true)) {
        $activityLimit = be_startpartner_gate3_positive_int(
            $input['activity_concurrent_limit'] ?? null,
            'activity_concurrent_limit',
            100
        );
    }

    $noAutomaticRenewal = filter_var(
        $input['no_automatic_paid_renewal'] ?? null,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );
    if ($noAutomaticRenewal !== true) {
        throw new InvalidArgumentException('no_automatic_paid_renewal must be explicitly confirmed.');
    }

    $plannedStart = be_startpartner_gate3_optional_date(
        $input['planned_activation_start'] ?? null,
        'planned_activation_start'
    );
    $plannedEnd = be_startpartner_gate3_optional_date(
        $input['planned_activation_end'] ?? null,
        'planned_activation_end'
    );
    if ($plannedStart !== null && $plannedEnd !== null && $plannedEnd < $plannedStart) {
        throw new InvalidArgumentException('planned_activation_end must not be before planned_activation_start.');
    }

    $explicitOrganizerId = null;
    if (trim((string)($input['organizer_id'] ?? '')) !== '') {
        $validated = filter_var($input['organizer_id'], FILTER_VALIDATE_INT);
        if ($validated === false || $validated < 1) {
            throw new InvalidArgumentException('organizer_id is invalid.');
        }
        $explicitOrganizerId = (int)$validated;
    }

    return [
        'terms_version' => (string)be_startpartner_clean_text(
            $input['terms_version'] ?? null,
            64,
            'terms_version',
            true
        ),
        'terms_reference' => (string)be_startpartner_clean_text(
            $input['terms_reference'] ?? null,
            2048,
            'terms_reference',
            true
        ),
        'terms_digest' => be_startpartner_gate3_digest($input['terms_digest'] ?? null),
        'accepting_person' => (string)be_startpartner_clean_text(
            $input['accepting_person'] ?? ($contact['contact_name'] ?? null),
            190,
            'accepting_person',
            true
        ),
        'accepting_organization' => $acceptingOrganization,
        'accepted_at' => be_startpartner_gate3_datetime($input['accepted_at'] ?? null, 'accepted_at'),
        'confirmation_channel' => be_startpartner_validate_enum_value(
            trim((string)($input['confirmation_channel'] ?? '')),
            BE_STARTPARTNER_GATE3_CHANNELS,
            'confirmation_channel'
        ),
        'target_plan_keys' => $targetPlanKeys,
        'cohort_key' => (string)be_startpartner_clean_text(
            $input['cohort_key'] ?? null,
            64,
            'cohort_key',
            true
        ),
        'event_limit_per_pilot_month' => $eventLimit,
        'activity_concurrent_limit' => $activityLimit,
        'is_event_unlimited' => $isEventUnlimited,
        'source_care_text' => (string)be_startpartner_clean_text(
            $input['source_care_text'] ?? null,
            5000,
            'source_care_text',
            true
        ),
        'maintenance_scope_text' => (string)be_startpartner_clean_text(
            $input['maintenance_scope_text'] ?? null,
            5000,
            'maintenance_scope_text',
            true
        ),
        'reach_contribution_text' => (string)be_startpartner_clean_text(
            $input['reach_contribution_text'] ?? null,
            5000,
            'reach_contribution_text',
            true
        ),
        'privacy_notice_version' => be_startpartner_clean_text(
            $input['privacy_notice_version'] ?? null,
            64,
            'privacy_notice_version'
        ),
        'communication_notice_version' => be_startpartner_clean_text(
            $input['communication_notice_version'] ?? null,
            64,
            'communication_notice_version'
        ),
        'planned_activation_start' => $plannedStart,
        'planned_activation_end' => $plannedEnd,
        'organizer_id' => $explicitOrganizerId,
        'desired_content_scope' => $desiredScope,
    ];
}

function be_startpartner_gate3_decode_json_fields(array $row, array $fields): array
{
    foreach ($fields as $field) {
        if (array_key_exists($field, $row)) {
            $row[str_replace('_json', '', $field)] = json_decode(
                (string)$row[$field],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            unset($row[$field]);
        }
    }
    return $row;
}

function be_startpartner_gate3_state(PDO $pdo, string $candidateId, bool $includeEvents = true): array
{
    be_startpartner_gate3_require_schema($pdo);
    $pilotStatement = $pdo->prepare(
        'SELECT * FROM startpartner_pilots WHERE candidate_id = :candidate_id LIMIT 1'
    );
    $pilotStatement->execute(['candidate_id' => $candidateId]);
    $pilot = $pilotStatement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($pilot)) {
        return [
            'complete' => false,
            'terms_acceptance' => null,
            'organizer' => null,
            'pilot' => null,
            'scopes' => [],
            'entitlement' => null,
            'events' => [],
            'blockers' => [[
                'code' => 'terms_confirmation_required',
                'message' => 'Pilotbedingungen müssen ausdrücklich bestätigt und einem Organizer zugeordnet werden.',
            ]],
        ];
    }
    $pilot = be_startpartner_gate3_decode_json_fields($pilot, ['target_plan_keys_json']);

    $termsStatement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_terms_acceptances WHERE id = :id LIMIT 1'
    );
    $termsStatement->execute(['id' => (int)$pilot['terms_acceptance_id']]);
    $terms = $termsStatement->fetch(PDO::FETCH_ASSOC);
    if (is_array($terms)) {
        $terms = be_startpartner_gate3_decode_json_fields(
            $terms,
            ['service_scope_json', 'source_care_json', 'reach_contribution_json']
        );
    }

    $organizerStatement = $pdo->prepare(
        'SELECT id, organization_name, contact_name, email, email_normalized, default_plan_key, created_at, updated_at
         FROM organizers WHERE id = :id LIMIT 1'
    );
    $organizerStatement->execute(['id' => (int)$pilot['organizer_id']]);
    $organizer = $organizerStatement->fetch(PDO::FETCH_ASSOC);

    $scopeStatement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_scopes WHERE pilot_id = :pilot_id ORDER BY id'
    );
    $scopeStatement->execute(['pilot_id' => (string)$pilot['id']]);
    $scopes = [];
    foreach ($scopeStatement->fetchAll(PDO::FETCH_ASSOC) as $scope) {
        $scopes[] = be_startpartner_gate3_decode_json_fields($scope, ['details_json']);
    }

    $entitlementStatement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_entitlements WHERE pilot_id = :pilot_id LIMIT 1'
    );
    $entitlementStatement->execute(['pilot_id' => (string)$pilot['id']]);
    $entitlement = $entitlementStatement->fetch(PDO::FETCH_ASSOC);
    if (is_array($entitlement)) {
        $entitlement = be_startpartner_gate3_decode_json_fields(
            $entitlement,
            ['target_plan_keys_json', 'source_scope_json', 'audit_json']
        );
    }

    $events = [];
    if ($includeEvents) {
        $eventStatement = $pdo->prepare(
            'SELECT * FROM startpartner_pilot_events WHERE pilot_id = :pilot_id ORDER BY id'
        );
        $eventStatement->execute(['pilot_id' => (string)$pilot['id']]);
        foreach ($eventStatement->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $events[] = be_startpartner_gate3_decode_json_fields($event, ['payload_json']);
        }
    }

    $blockers = [];
    if (!is_array($terms)) {
        $blockers[] = ['code' => 'terms_owner_missing', 'message' => 'Bedingungenbestätigung fehlt.'];
    }
    if (!is_array($organizer)) {
        $blockers[] = ['code' => 'organizer_missing', 'message' => 'Organizer-Verknüpfung fehlt.'];
    }
    if (!is_array($entitlement)) {
        $blockers[] = ['code' => 'pilot_entitlement_missing', 'message' => 'Pilotberechtigung fehlt.'];
    } elseif (
        (string)$entitlement['status'] !== 'pending_activation'
        || $entitlement['starts_at'] !== null
        || $entitlement['ends_at'] !== null
    ) {
        $blockers[] = [
            'code' => 'pilot_entitlement_not_fail_closed',
            'message' => 'Pilotberechtigung ist vor Aktivierung nicht fail-closed.',
        ];
    }

    return [
        'complete' => $blockers === [],
        'terms_acceptance' => is_array($terms) ? $terms : null,
        'organizer' => is_array($organizer) ? $organizer : null,
        'pilot' => $pilot,
        'scopes' => $scopes,
        'entitlement' => is_array($entitlement) ? $entitlement : null,
        'events' => $events,
        'blockers' => $blockers,
    ];
}

function be_startpartner_gate3_candidate_detail(PDO $pdo, string $candidateId, bool $includeEvents = true): array
{
    $candidate = be_startpartner_gate2_candidate_detail($pdo, $candidateId, $includeEvents);
    $candidate['gate3'] = be_startpartner_gate3_state($pdo, $candidateId, $includeEvents);
    return $candidate;
}

function be_startpartner_gate3_project_control_case(
    PDO $pdo,
    array $candidate,
    array $readiness,
    array $capacity,
    array $gate3,
    string $actor
): void {
    $candidateId = (string)$candidate['id'];
    $payload = json_encode([
        'candidate_id' => $candidateId,
        'candidate_status' => $candidate['status'],
        'candidate_revision' => (int)$candidate['revision'],
        'candidate_source' => $candidate['source'],
        'desired_content_scope' => $candidate['desired_content_scope'],
        'assigned_to' => $candidate['assigned_to'] ?? null,
        'next_review_at' => $candidate['next_review_at'] ?? null,
        'readiness' => $readiness,
        'capacity' => $capacity,
        'gate3' => $gate3,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $select = $pdo->prepare(
        "SELECT id, state FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :reference
         FOR UPDATE"
    );
    $select->execute(['reference' => $candidateId]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);
    $state = ($gate3['complete'] ?? false) ? 'in_progress' : 'waiting';
    $nextAction = ($gate3['complete'] ?? false)
        ? 'Pilot-Onboarding vorbereiten; Aktivierung bleibt gesperrt.'
        : 'Pilotbedingungen bestätigen und Organizer verknüpfen.';
    $reason = ($gate3['complete'] ?? false)
        ? 'Bedingungen bestätigt; Pilot und ausstehende Pilotberechtigung angelegt.'
        : (($gate3['blockers'][0]['message'] ?? null) ?: 'Gate 3 ist offen.');
    $dueAt = $candidate['active_reservation']['ends_at'] ?? $candidate['next_review_at'] ?? null;

    if (is_array($existing)) {
        $statement = $pdo->prepare(
            'UPDATE control_cases
             SET state = :state, priority = :priority, title = :title, reason = :reason,
                 next_action = :next_action, object_type = :object_type,
                 object_id = :object_id, object_title = :object_title,
                 source_payload_json = :payload, due_at = :due_at,
                 decision_ready = 0, completed_at = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'state' => $state,
            'priority' => 'high',
            'title' => 'Startpartner-Pilot vorbereiten: ' . (string)$candidate['organization_name'],
            'reason' => $reason,
            'next_action' => $nextAction,
            'object_type' => 'startpartner_candidate',
            'object_id' => $candidateId,
            'object_title' => (string)$candidate['organization_name'],
            'payload' => $payload,
            'due_at' => $dueAt,
            'id' => (string)$existing['id'],
        ]);
        be_cc_record_event(
            $pdo,
            (string)$existing['id'],
            'startpartner_gate3_sync',
            (string)$existing['state'],
            $state,
            ['candidate_revision' => (int)$candidate['revision'], 'gate3_complete' => (bool)$gate3['complete']],
            $actor
        );
        return;
    }

    $caseId = be_cc_uuid();
    $statement = $pdo->prepare(
        'INSERT INTO control_cases (
            id, case_type, state, priority, title, reason, next_action,
            object_type, object_id, object_title,
            source_system, source_reference, source_payload_json,
            due_at, decision_ready
         ) VALUES (
            :id, :case_type, :state, :priority, :title, :reason, :next_action,
            :object_type, :object_id, :object_title,
            :source_system, :source_reference, :payload,
            :due_at, 0
         )'
    );
    $statement->execute([
        'id' => $caseId,
        'case_type' => 'intake',
        'state' => $state,
        'priority' => 'high',
        'title' => 'Startpartner-Pilot vorbereiten: ' . (string)$candidate['organization_name'],
        'reason' => $reason,
        'next_action' => $nextAction,
        'object_type' => 'startpartner_candidate',
        'object_id' => $candidateId,
        'object_title' => (string)$candidate['organization_name'],
        'source_system' => 'startpartner_candidate',
        'source_reference' => $candidateId,
        'payload' => $payload,
        'due_at' => $dueAt,
    ]);
    be_cc_record_event(
        $pdo,
        $caseId,
        'startpartner_gate3_create',
        null,
        $state,
        ['candidate_revision' => (int)$candidate['revision'], 'gate3_complete' => (bool)$gate3['complete']],
        $actor
    );
}

function be_startpartner_gate3_insert_scope(
    PDO $pdo,
    string $pilotId,
    string $scopeKey,
    string $scopeType,
    ?string $targetPlanKey,
    ?int $limitValue,
    bool $isUnlimited,
    string $periodUnit,
    array $details
): void {
    $statement = $pdo->prepare(
        "INSERT INTO startpartner_pilot_scopes (
            pilot_id, scope_key, scope_type, status, target_plan_key,
            limit_value, is_unlimited, period_unit, details_json
         ) VALUES (
            :pilot_id, :scope_key, :scope_type, 'planned', :target_plan_key,
            :limit_value, :is_unlimited, :period_unit, :details_json
         )"
    );
    $statement->execute([
        'pilot_id' => $pilotId,
        'scope_key' => $scopeKey,
        'scope_type' => $scopeType,
        'target_plan_key' => $targetPlanKey,
        'limit_value' => $limitValue,
        'is_unlimited' => $isUnlimited ? 1 : 0,
        'period_unit' => $periodUnit,
        'details_json' => json_encode(
            $details,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
    ]);
}

function be_startpartner_gate3_confirm(PDO $pdo, string $candidateId, array $input): array
{
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);
    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $operatorName = be_startpartner_gate2_operator_name($input['operator_name'] ?? null);
    $expectedRevision = be_startpartner_gate2_expected_revision($input['expected_revision'] ?? null);
    $action = 'gate3.confirm_terms_and_create_pilot';
    $payloadHash = be_startpartner_gate2_payload_hash($candidateId, $action, $input);

    $pdo->beginTransaction();
    try {
        $operationStatement = $pdo->prepare(
            'SELECT * FROM startpartner_candidate_operations WHERE operation_id = :operation_id FOR UPDATE'
        );
        $operationStatement->execute(['operation_id' => $operationId]);
        $existingOperation = $operationStatement->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingOperation)) {
            if (
                (string)$existingOperation['candidate_id'] !== $candidateId
                || (string)$existingOperation['action'] !== $action
                || !hash_equals((string)$existingOperation['payload_hash'], $payloadHash)
            ) {
                throw new BeStartpartnerConflictException('operation_id was already used with a different payload.');
            }
            if ((string)$existingOperation['status'] !== 'completed' || $existingOperation['result_json'] === null) {
                throw new BeStartpartnerConflictException('operation_id is not replayable.');
            }
            $result = json_decode((string)$existingOperation['result_json'], true, 512, JSON_THROW_ON_ERROR);
            $result['idempotent_replay'] = true;
            $pdo->commit();
            return $result;
        }

        $candidate = be_startpartner_gate2_candidate_row($pdo, $candidateId, true);
        if ((int)$candidate['revision'] !== $expectedRevision) {
            $pdo->rollBack();
            throw new BeStartpartnerConflictException(
                'Candidate was changed in the meantime.',
                be_startpartner_gate3_candidate_detail($pdo, $candidateId)
            );
        }
        if ((string)$candidate['status'] !== 'accepted_pending_terms') {
            throw new DomainException('Only accepted_pending_terms candidates can enter Gate 3.');
        }

        $pilotExists = $pdo->prepare(
            'SELECT id FROM startpartner_pilots WHERE candidate_id = :candidate_id LIMIT 1 FOR UPDATE'
        );
        $pilotExists->execute(['candidate_id' => $candidateId]);
        if ($pilotExists->fetchColumn() !== false) {
            throw new DomainException('Candidate already has a pilot.');
        }

        $decision = be_startpartner_gate3_current_decision($pdo, $candidateId);
        $reservation = be_startpartner_gate3_active_reservation($pdo, $candidateId, (int)$decision['id']);
        $contact = be_startpartner_gate3_primary_contact($pdo, $candidateId);
        $confirmation = be_startpartner_gate3_normalize_confirmation($candidate, $contact, $input);
        $organizerResult = be_startpartner_gate3_resolve_organizer(
            $pdo,
            $candidate,
            $contact,
            $confirmation['organizer_id']
        );
        $organizer = $organizerResult['organizer'];

        $insertOperation = $pdo->prepare(
            'INSERT INTO startpartner_candidate_operations (
                operation_id, candidate_id, action, payload_hash, status,
                candidate_revision_before
             ) VALUES (
                :operation_id, :candidate_id, :action, :payload_hash, :status,
                :candidate_revision_before
             )'
        );
        $insertOperation->execute([
            'operation_id' => $operationId,
            'candidate_id' => $candidateId,
            'action' => $action,
            'payload_hash' => $payloadHash,
            'status' => 'started',
            'candidate_revision_before' => $expectedRevision,
        ]);

        $serviceScope = [
            'desired_content_scope' => $confirmation['desired_content_scope'],
            'target_plan_keys' => $confirmation['target_plan_keys'],
            'event_limit_per_pilot_month' => $confirmation['event_limit_per_pilot_month'],
            'activity_concurrent_limit' => $confirmation['activity_concurrent_limit'],
            'is_event_unlimited' => $confirmation['is_event_unlimited'],
            'maintenance_scope_text' => $confirmation['maintenance_scope_text'],
        ];
        $sourceCare = ['description' => $confirmation['source_care_text']];
        $reachContribution = ['description' => $confirmation['reach_contribution_text']];

        $termsStatement = $pdo->prepare(
            'INSERT INTO startpartner_pilot_terms_acceptances (
                candidate_id, decision_id, terms_version, terms_reference, terms_digest,
                accepting_person, accepting_organization, accepted_at, confirmation_channel,
                service_scope_json, source_care_json, reach_contribution_json,
                planned_activation_start, planned_activation_end,
                privacy_notice_version, communication_notice_version,
                no_automatic_paid_renewal, operator_reference
             ) VALUES (
                :candidate_id, :decision_id, :terms_version, :terms_reference, :terms_digest,
                :accepting_person, :accepting_organization, :accepted_at, :confirmation_channel,
                :service_scope_json, :source_care_json, :reach_contribution_json,
                :planned_activation_start, :planned_activation_end,
                :privacy_notice_version, :communication_notice_version,
                1, :operator_reference
             )'
        );
        $termsStatement->execute([
            'candidate_id' => $candidateId,
            'decision_id' => (int)$decision['id'],
            'terms_version' => $confirmation['terms_version'],
            'terms_reference' => $confirmation['terms_reference'],
            'terms_digest' => $confirmation['terms_digest'],
            'accepting_person' => $confirmation['accepting_person'],
            'accepting_organization' => $confirmation['accepting_organization'],
            'accepted_at' => $confirmation['accepted_at'],
            'confirmation_channel' => $confirmation['confirmation_channel'],
            'service_scope_json' => json_encode($serviceScope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'source_care_json' => json_encode($sourceCare, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'reach_contribution_json' => json_encode($reachContribution, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'planned_activation_start' => $confirmation['planned_activation_start'],
            'planned_activation_end' => $confirmation['planned_activation_end'],
            'privacy_notice_version' => $confirmation['privacy_notice_version'],
            'communication_notice_version' => $confirmation['communication_notice_version'],
            'operator_reference' => $operatorName,
        ]);
        $termsId = (int)$pdo->lastInsertId();

        $pilotId = be_cc_uuid();
        $pilotStatement = $pdo->prepare(
            "INSERT INTO startpartner_pilots (
                id, candidate_id, organizer_id, terms_acceptance_id, reservation_id,
                cohort_key, status, target_plan_keys_json, internal_owner,
                partner_contact_name_snapshot, partner_contact_email_snapshot
             ) VALUES (
                :id, :candidate_id, :organizer_id, :terms_acceptance_id, :reservation_id,
                :cohort_key, 'onboarding', :target_plan_keys_json, :internal_owner,
                :partner_contact_name_snapshot, :partner_contact_email_snapshot
             )"
        );
        $pilotStatement->execute([
            'id' => $pilotId,
            'candidate_id' => $candidateId,
            'organizer_id' => (int)$organizer['id'],
            'terms_acceptance_id' => $termsId,
            'reservation_id' => (int)$reservation['id'],
            'cohort_key' => $confirmation['cohort_key'],
            'target_plan_keys_json' => json_encode($confirmation['target_plan_keys'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'internal_owner' => $operatorName,
            'partner_contact_name_snapshot' => $contact['contact_name'] ?? null,
            'partner_contact_email_snapshot' => (string)$contact['email'],
        ]);

        if (in_array($confirmation['desired_content_scope'], ['events', 'both'], true)) {
            be_startpartner_gate3_insert_scope(
                $pdo,
                $pilotId,
                'events',
                'events',
                be_startpartner_gate3_scope_target_plan_key('events'),
                $confirmation['event_limit_per_pilot_month'],
                $confirmation['is_event_unlimited'],
                'pilot_month',
                ['source' => 'terms_acceptance']
            );
        }
        if (in_array($confirmation['desired_content_scope'], ['activities', 'both'], true)) {
            be_startpartner_gate3_insert_scope(
                $pdo,
                $pilotId,
                'activities',
                'activities',
                be_startpartner_gate3_scope_target_plan_key('activities'),
                $confirmation['activity_concurrent_limit'],
                false,
                'concurrent',
                ['source' => 'terms_acceptance']
            );
        }
        be_startpartner_gate3_insert_scope($pdo, $pilotId, 'automatic-source', 'automatic_source', null, null, false, 'not_applicable', $sourceCare);
        be_startpartner_gate3_insert_scope($pdo, $pilotId, 'maintenance-service', 'maintenance_service', null, null, false, 'not_applicable', ['description' => $confirmation['maintenance_scope_text']]);
        be_startpartner_gate3_insert_scope($pdo, $pilotId, 'provider-portal', 'provider_portal', null, null, false, 'not_applicable', ['access' => 'not_created_in_gate3']);
        be_startpartner_gate3_insert_scope($pdo, $pilotId, 'measurement', 'measurement', null, null, false, 'not_applicable', ['activation' => 'deferred']);
        be_startpartner_gate3_insert_scope($pdo, $pilotId, 'reach-contribution', 'reach_contribution', null, null, false, 'not_applicable', $reachContribution);

        $entitlementId = be_cc_uuid();
        $entitlementStatement = $pdo->prepare(
            "INSERT INTO startpartner_pilot_entitlements (
                id, pilot_id, organizer_id, source_reference, status,
                target_plan_keys_json, event_limit_per_pilot_month,
                activity_concurrent_limit, is_event_unlimited,
                source_scope_json, audit_json
             ) VALUES (
                :id, :pilot_id, :organizer_id, :source_reference, 'pending_activation',
                :target_plan_keys_json, :event_limit_per_pilot_month,
                :activity_concurrent_limit, :is_event_unlimited,
                :source_scope_json, :audit_json
             )"
        );
        $entitlementStatement->execute([
            'id' => $entitlementId,
            'pilot_id' => $pilotId,
            'organizer_id' => (int)$organizer['id'],
            'source_reference' => $pilotId,
            'target_plan_keys_json' => json_encode($confirmation['target_plan_keys'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'event_limit_per_pilot_month' => $confirmation['event_limit_per_pilot_month'],
            'activity_concurrent_limit' => $confirmation['activity_concurrent_limit'],
            'is_event_unlimited' => $confirmation['is_event_unlimited'] ? 1 : 0,
            'source_scope_json' => json_encode($serviceScope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'audit_json' => json_encode([
                'created_by' => $operatorName,
                'operation_id' => $operationId,
                'terms_acceptance_id' => $termsId,
                'reservation_id' => (int)$reservation['id'],
                'publication_effect' => 'none_before_activation',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $pilotEvent = $pdo->prepare(
            'INSERT INTO startpartner_pilot_events (
                pilot_id, event_type, actor_reference, payload_json
             ) VALUES (
                :pilot_id, :event_type, :actor_reference, :payload_json
             )'
        );
        $pilotEvent->execute([
            'pilot_id' => $pilotId,
            'event_type' => 'gate3_pilot_created',
            'actor_reference' => $operatorName,
            'payload_json' => json_encode([
                'candidate_id' => $candidateId,
                'organizer_id' => (int)$organizer['id'],
                'organizer_created' => (bool)$organizerResult['created'],
                'reservation_id' => (int)$reservation['id'],
                'entitlement_id' => $entitlementId,
                'entitlement_status' => 'pending_activation',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $newRevision = $expectedRevision + 1;
        be_startpartner_gate2_update_candidate($pdo, $candidateId, [
            'revision' => $newRevision,
            'status_reason' => 'Pilotbedingungen bestätigt; Pilot-Onboarding angelegt.',
        ]);
        be_startpartner_gate2_record_event(
            $pdo,
            $candidateId,
            'gate3_pilot_created',
            (string)$candidate['status'],
            (string)$candidate['status'],
            $operatorName,
            [
                'pilot_id' => $pilotId,
                'organizer_id' => (int)$organizer['id'],
                'organizer_created' => (bool)$organizerResult['created'],
                'reservation_id' => (int)$reservation['id'],
                'entitlement_id' => $entitlementId,
                'entitlement_status' => 'pending_activation',
            ]
        );

        $updatedCandidate = be_startpartner_gate2_candidate_row($pdo, $candidateId);
        $qualifications = be_startpartner_gate2_qualification_rows($pdo, $candidateId);
        $readiness = be_startpartner_gate2_readiness($qualifications);
        $capacity = be_startpartner_gate2_capacity($pdo);
        $gate3State = be_startpartner_gate3_state($pdo, $candidateId);
        be_startpartner_gate3_project_control_case($pdo, $updatedCandidate, $readiness, $capacity, $gate3State, $operatorName);

        $result = [
            'candidate' => be_startpartner_gate3_candidate_detail($pdo, $candidateId),
            'operation_id' => $operationId,
            'idempotent_replay' => false,
            'meta' => [
                'pilot_id' => $pilotId,
                'organizer_id' => (int)$organizer['id'],
                'organizer_created' => (bool)$organizerResult['created'],
                'terms_acceptance_id' => $termsId,
                'reservation_id' => (int)$reservation['id'],
                'entitlement_id' => $entitlementId,
            ],
        ];
        $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $completeOperation = $pdo->prepare(
            "UPDATE startpartner_candidate_operations
             SET status = 'completed', result_json = :result_json,
                 candidate_revision_after = :revision_after,
                 completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE operation_id = :operation_id"
        );
        $completeOperation->execute([
            'result_json' => $resultJson,
            'revision_after' => $newRevision,
            'operation_id' => $operationId,
        ]);

        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function be_startpartner_gate3_guard_gate2_action(PDO $pdo, string $candidateId, string $action): void
{
    if (!in_array($action, ['extend_reservation', 'release_reservation'], true)) {
        return;
    }
    be_startpartner_gate3_require_schema($pdo);
    $statement = $pdo->prepare(
        'SELECT id FROM startpartner_pilots WHERE candidate_id = :candidate_id LIMIT 1'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    if ($statement->fetchColumn() !== false) {
        throw new DomainException('Reservation cannot be changed after Gate-3 pilot creation.');
    }
}
