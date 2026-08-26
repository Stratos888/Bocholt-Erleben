<?php
declare(strict_types=1);

function be_startpartner_gate4_portal_session(PDO $pdo): array
{
    $plainToken = trim((string)($_COOKIE['be_organizer_portal_session'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
        throw new InvalidArgumentException('Organizer session is missing or invalid.');
    }
    $statement = $pdo->prepare(
        'SELECT s.id AS portal_session_id, s.organizer_id, s.expires_at, s.revoked_at,
                o.organization_name, o.contact_name, o.email
         FROM organizer_portal_sessions s
         INNER JOIN organizers o ON o.id = s.organizer_id
         WHERE s.session_token_hash = :session_token_hash LIMIT 1'
    );
    $statement->execute(['session_token_hash' => hash('sha256', $plainToken)]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !empty($row['revoked_at'])) {
        throw new InvalidArgumentException('Organizer session is invalid.');
    }
    $expiry = new DateTimeImmutable((string)$row['expires_at'], new DateTimeZone('UTC'));
    if ($expiry < new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
        throw new InvalidArgumentException('Organizer session expired.');
    }
    return $row;
}

function be_startpartner_gate4_portal_candidate(PDO $pdo, int $organizerId): array
{
    be_startpartner_gate4_require_schema($pdo);
    $statement = $pdo->prepare(
        "SELECT candidate_id FROM startpartner_pilots
         WHERE organizer_id = :organizer_id
           AND status IN ('onboarding','activation_ready','active','paused','closing')
         ORDER BY created_at DESC LIMIT 2"
    );
    $statement->execute(['organizer_id' => $organizerId]);
    $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) !== 1) {
        throw new DomainException(count($rows) === 0 ? 'No active Startpartner pilot found.' : 'Organizer has ambiguous Startpartner pilots.');
    }
    return be_startpartner_gate4_candidate_detail($pdo, (string)$rows[0], false);
}

function be_startpartner_gate4_portal_payload(array $values): array
{
    return [
        'content_type' => (string)($values['content_type'] ?? ''),
        'requested_model_key' => (string)($values['requested_model_key'] ?? ''),
        'title' => (string)($values['title'] ?? ''),
        'start_date' => ($values['start_date'] ?? null) !== null ? (string)$values['start_date'] : null,
        'time_text' => ($values['time_text'] ?? null) !== null ? (string)$values['time_text'] : null,
        'event_url' => ($values['event_url'] ?? null) !== null ? (string)$values['event_url'] : null,
        'ticket_url' => ($values['ticket_url'] ?? null) !== null ? (string)$values['ticket_url'] : null,
        'description_text' => ($values['description_text'] ?? null) !== null ? (string)$values['description_text'] : null,
        'notes_text' => ($values['notes_text'] ?? null) !== null ? (string)$values['notes_text'] : null,
        'location_name' => (string)($values['location_name'] ?? ''),
        'location_address' => ($values['location_address'] ?? null) !== null ? (string)$values['location_address'] : null,
        'location_public_confirmed' => (int)($values['location_public_confirmed'] ?? 0) === 1 ? 1 : 0,
    ];
}

function be_startpartner_gate4_portal_payload_hash(array $payload): string
{
    return hash(
        'sha256',
        json_encode(
            be_startpartner_gate4_portal_payload($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    );
}

function be_startpartner_gate4_portal_assert_active_capacity(
    PDO $pdo,
    array $pilot,
    array $scope,
    string $contentType
): array {
    $entitlementStatement = $pdo->prepare(
        "SELECT * FROM startpartner_pilot_entitlements
         WHERE pilot_id = :pilot_id LIMIT 1 FOR UPDATE"
    );
    $entitlementStatement->execute(['pilot_id' => (string)$pilot['id']]);
    $entitlement = $entitlementStatement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($entitlement) || (string)$entitlement['status'] !== 'active') {
        throw new DomainException('Pilot entitlement is not active.');
    }
    $window = be_startpartner_gate4_lifecycle_window($pilot, $entitlement);
    if ((string)($scope['status'] ?? '') !== 'active') {
        throw new DomainException('The agreed content scope is currently not active.');
    }

    if ($contentType === 'event') {
        $unlimited = (int)($entitlement['is_event_unlimited'] ?? 0) === 1;
        if ($unlimited !== ((int)($scope['is_unlimited'] ?? 0) === 1)) {
            throw new DomainException('Event limit contract is inconsistent.');
        }
        $usage = $pdo->prepare(
            "SELECT id, units FROM startpartner_pilot_usages
             WHERE pilot_id = :pilot_id AND content_type = 'event'
               AND pilot_month_index = :pilot_month_index
             ORDER BY id FOR UPDATE"
        );
        $usage->execute([
            'pilot_id' => (string)$pilot['id'],
            'pilot_month_index' => (int)$window['pilot_month_index'],
        ]);
        $used = 0;
        foreach ($usage->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $used += (int)$row['units'];
        }
        $limit = $entitlement['event_limit_per_pilot_month'] !== null
            ? (int)$entitlement['event_limit_per_pilot_month']
            : null;
        $scopeLimit = $scope['limit_value'] !== null ? (int)$scope['limit_value'] : null;
        if (!$unlimited && ($limit === null || $limit < 1 || $scopeLimit !== $limit)) {
            throw new DomainException('Event monthly limit is missing or inconsistent.');
        }
        if (!$unlimited && $used >= $limit) {
            throw new DomainException('Das vereinbarte Event-Limit für diesen Pilotmonat ist erreicht.');
        }
        return [
            'limit_type' => 'pilot_month',
            'pilot_month_index' => (int)$window['pilot_month_index'],
            'used' => $used,
            'limit' => $limit,
            'is_unlimited' => $unlimited,
            'reset_date_local' => $window['month_window']['next_start_date_local'] ?? null,
        ];
    }

    $limit = $entitlement['activity_concurrent_limit'] !== null
        ? (int)$entitlement['activity_concurrent_limit']
        : null;
    $scopeLimit = $scope['limit_value'] !== null ? (int)$scope['limit_value'] : null;
    if ($limit === null || $limit < 1 || $scopeLimit !== $limit || (int)$scope['is_unlimited'] === 1) {
        throw new DomainException('Activity concurrent limit is missing or inconsistent.');
    }
    $occupancy = $pdo->prepare(
        "SELECT id FROM startpartner_pilot_content_links
         WHERE pilot_id = :pilot_id AND content_type = 'activity' AND status = 'approved'
         ORDER BY id FOR UPDATE"
    );
    $occupancy->execute(['pilot_id' => (string)$pilot['id']]);
    $used = count($occupancy->fetchAll(PDO::FETCH_ASSOC));
    if ($used >= $limit) {
        throw new DomainException('Die vereinbarte gleichzeitige Aktivitätspräsenz ist bereits vollständig belegt.');
    }
    return [
        'limit_type' => 'concurrent',
        'used' => $used,
        'limit' => $limit,
        'is_unlimited' => false,
    ];
}

function be_startpartner_gate4_create_portal_submission(PDO $pdo, array $session, array $input): array
{
    be_startpartner_gate4_require_schema($pdo);
    $candidate = be_startpartner_gate4_portal_candidate($pdo, (int)$session['organizer_id']);
    $gate4 = $candidate['gate4'];
    $pilot = $gate4['pilot'];
    if (!is_array($pilot)) {
        throw new DomainException('Pilot is missing.');
    }
    $contentType = be_startpartner_gate4_content_type($input['content_type'] ?? 'event');
    $scopeKey = $contentType === 'event' ? 'events' : 'activities';
    $expectedRequestedModel = be_startpartner_gate3_scope_target_plan_key($scopeKey);
    $scopeAllowed = false;
    foreach ((array)$candidate['gate3']['scopes'] as $scope) {
        if ((string)($scope['scope_key'] ?? '') === $scopeKey) {
            $scopeAllowed = true;
            break;
        }
    }
    if (!$scopeAllowed) {
        throw new DomainException('Content type is outside the agreed pilot scope.');
    }
    $clientReference = be_startpartner_gate4_required_text($input['client_reference'] ?? null, 128, 'client_reference');
    if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $clientReference)) {
        throw new InvalidArgumentException('client_reference is invalid.');
    }
    $sourceReference = 'portal:' . $clientReference;
    $title = be_startpartner_gate4_required_text($input['title'] ?? null, 255, 'title');
    $locationName = be_startpartner_gate4_required_text($input['location_name'] ?? null, 255, 'location_name');
    $startDate = null;
    if ($contentType === 'event') {
        $startDate = be_startpartner_gate4_validate_local_date($input['start_date'] ?? null);
    }
    $description = be_startpartner_gate4_optional_text($input['description_text'] ?? null, 20000, 'description_text');
    $timeText = be_startpartner_gate4_optional_text($input['time_text'] ?? null, 64, 'time_text');
    $eventUrl = be_startpartner_gate4_optional_text($input['event_url'] ?? null, 2048, 'event_url');
    $ticketUrl = be_startpartner_gate4_optional_text($input['ticket_url'] ?? null, 2048, 'ticket_url');
    $notesText = be_startpartner_gate4_optional_text($input['notes_text'] ?? null, 20000, 'notes_text');
    $locationAddress = be_startpartner_gate4_optional_text($input['location_address'] ?? null, 500, 'location_address');
    $locationConfirmed = filter_var($input['location_public_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!$locationConfirmed) {
        throw new InvalidArgumentException('location_public_confirmed must be true.');
    }
    $incomingPayload = be_startpartner_gate4_portal_payload([
        'content_type' => $contentType,
        'requested_model_key' => $expectedRequestedModel,
        'title' => $title,
        'start_date' => $startDate,
        'time_text' => $timeText,
        'event_url' => $eventUrl,
        'ticket_url' => $ticketUrl,
        'description_text' => $description,
        'notes_text' => $notesText,
        'location_name' => $locationName,
        'location_address' => $locationAddress,
        'location_public_confirmed' => 1,
    ]);

    $pdo->beginTransaction();
    try {
        $pilotLock = $pdo->prepare('SELECT * FROM startpartner_pilots WHERE id = :id FOR UPDATE');
        $pilotLock->execute(['id' => (string)$pilot['id']]);
        $lockedPilot = $pilotLock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($lockedPilot)) {
            throw new RuntimeException('Pilot disappeared.');
        }

        $scopeLock = $pdo->prepare(
            'SELECT id, scope_key, status, target_plan_key, limit_value, is_unlimited, period_unit
             FROM startpartner_pilot_scopes
             WHERE pilot_id = :pilot_id AND scope_key = :scope_key
             LIMIT 1 FOR UPDATE'
        );
        $scopeLock->execute([
            'pilot_id' => (string)$lockedPilot['id'],
            'scope_key' => $scopeKey,
        ]);
        $lockedScope = $scopeLock->fetch(PDO::FETCH_ASSOC);
        $targetPlans = json_decode((string)$lockedPilot['target_plan_keys_json'], true);
        if (
            !is_array($lockedScope)
            || (string)($lockedScope['target_plan_key'] ?? '') !== $expectedRequestedModel
            || !is_array($targetPlans)
            || !in_array($expectedRequestedModel, $targetPlans, true)
        ) {
            throw new DomainException('Pilot scope target-plan mapping is inconsistent; operator repair is required.');
        }

        $existing = $pdo->prepare(
            'SELECT pcl.*, s.status AS submission_status, s.submission_kind,
                    s.requested_model_key, s.title, s.start_date, s.time_text, s.event_url,
                    s.ticket_url, s.description_text, s.notes_text, s.location_name,
                    s.location_address, s.location_public_confirmed
             FROM startpartner_pilot_content_links pcl
             INNER JOIN submissions s ON s.id = pcl.submission_id
             WHERE pcl.pilot_id = :pilot_id AND pcl.source_reference = :source_reference LIMIT 1 FOR UPDATE'
        );
        $existing->execute([
            'pilot_id' => (string)$lockedPilot['id'],
            'source_reference' => $sourceReference,
        ]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingRow)) {
            $existingPayload = be_startpartner_gate4_portal_payload([
                'content_type' => (string)$existingRow['content_type'],
                'requested_model_key' => (string)$existingRow['requested_model_key'],
                'title' => (string)$existingRow['title'],
                'start_date' => $existingRow['start_date'],
                'time_text' => $existingRow['time_text'],
                'event_url' => $existingRow['event_url'],
                'ticket_url' => $existingRow['ticket_url'],
                'description_text' => $existingRow['description_text'],
                'notes_text' => $existingRow['notes_text'],
                'location_name' => (string)$existingRow['location_name'],
                'location_address' => $existingRow['location_address'],
                'location_public_confirmed' => (int)$existingRow['location_public_confirmed'],
            ]);
            if (!hash_equals(
                be_startpartner_gate4_portal_payload_hash($existingPayload),
                be_startpartner_gate4_portal_payload_hash($incomingPayload)
            )) {
                throw new DomainException('client_reference wurde bereits mit anderen Inhaltsdaten verwendet.');
            }
            $pdo->commit();
            return ['content_link' => $existingRow, 'idempotent_replay' => true];
        }

        $pilotStatus = (string)$lockedPilot['status'];
        if (!in_array($pilotStatus, ['onboarding', 'activation_ready', 'active'], true)) {
            throw new DomainException('Pilot cannot create content in the current state.');
        }
        $limitState = null;
        if ($pilotStatus === 'active') {
            $limitState = be_startpartner_gate4_portal_assert_active_capacity(
                $pdo,
                $lockedPilot,
                $lockedScope,
                $contentType
            );
        } elseif ((string)$lockedScope['status'] !== 'planned') {
            throw new DomainException('Pre-activation pilot scope is not in the expected planned state.');
        }

        $paymentReference = be_startpartner_gate4_uuid();
        $insertSubmission = $pdo->prepare(
            'INSERT INTO submissions (
                organizer_id, submission_kind, status, requested_model_key, payment_kind,
                payment_reference_key, organization_name_snapshot, contact_name_snapshot,
                email_snapshot, event_url, title, start_date, time_text, location_name,
                location_address, location_public_confirmed, ticket_url, description_text, notes_text
             ) VALUES (
                :organizer_id, :submission_kind, :status, :requested_model_key, :payment_kind,
                :payment_reference_key, :organization_name_snapshot, :contact_name_snapshot,
                :email_snapshot, :event_url, :title, :start_date, :time_text, :location_name,
                :location_address, 1, :ticket_url, :description_text, :notes_text
             )'
        );
        $insertSubmission->execute([
            'organizer_id' => (int)$session['organizer_id'],
            'submission_kind' => $contentType,
            'status' => 'in_review',
            'requested_model_key' => $expectedRequestedModel,
            'payment_kind' => 'startpartner_pilot',
            'payment_reference_key' => $paymentReference,
            'organization_name_snapshot' => (string)$session['organization_name'],
            'contact_name_snapshot' => $session['contact_name'] ?? null,
            'email_snapshot' => (string)$session['email'],
            'event_url' => $eventUrl,
            'title' => $title,
            'start_date' => $startDate,
            'time_text' => $timeText,
            'location_name' => $locationName,
            'location_address' => $locationAddress,
            'ticket_url' => $ticketUrl,
            'description_text' => $description,
            'notes_text' => $notesText,
        ]);
        $submissionId = (int)$pdo->lastInsertId();
        $contentLinkId = be_startpartner_gate4_uuid();
        $reportingTargetId = be_startpartner_gate4_reporting_target_id((int)$session['organizer_id']);
        $insertLink = $pdo->prepare(
            'INSERT INTO startpartner_pilot_content_links (
                id, pilot_id, organizer_id, submission_id, content_type, status,
                reporting_target_type, reporting_target_id, source_reference
             ) VALUES (
                :id, :pilot_id, :organizer_id, :submission_id, :content_type, :status,
                :reporting_target_type, :reporting_target_id, :source_reference
             )'
        );
        $insertLink->execute([
            'id' => $contentLinkId,
            'pilot_id' => (string)$lockedPilot['id'],
            'organizer_id' => (int)$session['organizer_id'],
            'submission_id' => $submissionId,
            'content_type' => $contentType,
            'status' => 'draft',
            'reporting_target_type' => 'organizer',
            'reporting_target_id' => $reportingTargetId,
            'source_reference' => $sourceReference,
        ]);
        $pilotRevision = (int)$lockedPilot['revision'] + 1;
        $updatePilot = $pdo->prepare('UPDATE startpartner_pilots SET revision = :revision WHERE id = :id');
        $updatePilot->execute(['revision' => $pilotRevision, 'id' => (string)$lockedPilot['id']]);
        $candidateRow = be_startpartner_gate2_candidate_row($pdo, (string)$candidate['id'], true);
        $candidateRevision = (int)$candidateRow['revision'] + 1;
        be_startpartner_gate2_update_candidate($pdo, (string)$candidate['id'], [
            'revision' => $candidateRevision,
            'status_reason' => 'Pilotinhalt über den Anbieterbereich eingereicht.',
        ]);
        $event = $pdo->prepare(
            'INSERT INTO startpartner_pilot_events (pilot_id, event_type, actor_reference, payload_json)
             VALUES (:pilot_id, :event_type, :actor_reference, :payload_json)'
        );
        $event->execute([
            'pilot_id' => (string)$lockedPilot['id'],
            'event_type' => 'gate4_content_submitted',
            'actor_reference' => 'organizer:' . (int)$session['organizer_id'],
            'payload_json' => json_encode([
                'submission_id' => $submissionId,
                'content_link_id' => $contentLinkId,
                'content_type' => $contentType,
                'requested_model_key' => $expectedRequestedModel,
                'source_reference' => $sourceReference,
                'client_payload_hash' => be_startpartner_gate4_portal_payload_hash($incomingPayload),
                'limit_state_at_submit' => $limitState,
                'mail_effect' => 'none',
                'stripe_effect' => 'none',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $detail = be_startpartner_gate4_candidate_detail($pdo, (string)$candidate['id']);
        be_startpartner_gate4_project_control_case(
            $pdo,
            $detail,
            $detail['gate4'],
            'organizer:' . (int)$session['organizer_id']
        );
        $pdo->commit();
        return [
            'content_link' => [
                'id' => $contentLinkId,
                'pilot_id' => (string)$lockedPilot['id'],
                'submission_id' => $submissionId,
                'content_type' => $contentType,
                'status' => 'draft',
                'reporting_target_type' => 'organizer',
                'reporting_target_id' => $reportingTargetId,
                'source_reference' => $sourceReference,
            ],
            'submission_id' => $submissionId,
            'candidate_revision' => $candidateRevision,
            'pilot_revision' => $pilotRevision,
            'limit_state' => $limitState,
            'idempotent_replay' => false,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
