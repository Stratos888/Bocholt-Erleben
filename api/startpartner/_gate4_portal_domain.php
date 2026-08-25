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

function be_startpartner_gate4_create_portal_submission(PDO $pdo, array $session, array $input): array
{
    be_startpartner_gate4_require_schema($pdo);
    $candidate = be_startpartner_gate4_portal_candidate($pdo, (int)$session['organizer_id']);
    $gate4 = $candidate['gate4'];
    $pilot = $gate4['pilot'];
    if (!is_array($pilot) || !in_array((string)$pilot['status'], ['onboarding', 'activation_ready', 'active'], true)) {
        throw new DomainException('Pilot cannot create content in the current state.');
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

    $pdo->beginTransaction();
    try {
        $pilotLock = $pdo->prepare('SELECT * FROM startpartner_pilots WHERE id = :id FOR UPDATE');
        $pilotLock->execute(['id' => (string)$pilot['id']]);
        $lockedPilot = $pilotLock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($lockedPilot)) {
            throw new RuntimeException('Pilot disappeared.');
        }
        if (!in_array((string)$lockedPilot['status'], ['onboarding', 'activation_ready', 'active'], true)) {
            throw new DomainException('Pilot cannot create content in the current state.');
        }

        $scopeLock = $pdo->prepare(
            'SELECT id, scope_key, target_plan_key
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
            'SELECT pcl.*, s.status AS submission_status, s.title
             FROM startpartner_pilot_content_links pcl
             INNER JOIN submissions s ON s.id = pcl.submission_id
             WHERE pcl.pilot_id = :pilot_id AND pcl.source_reference = :source_reference LIMIT 1'
        );
        $existing->execute(['pilot_id' => (string)$pilot['id'], 'source_reference' => $sourceReference]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingRow)) {
            $pdo->commit();
            return ['content_link' => $existingRow, 'idempotent_replay' => true];
        }
        $paymentReference = be_startpartner_gate4_uuid();
        $requestedModel = $expectedRequestedModel;
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
            'requested_model_key' => $requestedModel,
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
            'pilot_id' => (string)$pilot['id'],
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
        $updatePilot->execute(['revision' => $pilotRevision, 'id' => (string)$pilot['id']]);
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
            'pilot_id' => (string)$pilot['id'],
            'event_type' => 'gate4_content_submitted',
            'actor_reference' => 'organizer:' . (int)$session['organizer_id'],
            'payload_json' => json_encode([
                'submission_id' => $submissionId,
                'content_link_id' => $contentLinkId,
                'content_type' => $contentType,
                'requested_model_key' => $requestedModel,
                'source_reference' => $sourceReference,
                'mail_effect' => 'none',
                'stripe_effect' => 'none',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $detail = be_startpartner_gate4_candidate_detail($pdo, (string)$candidate['id']);
        be_startpartner_gate4_project_control_case($pdo, $detail, $detail['gate4'], 'organizer:' . (int)$session['organizer_id']);
        $pdo->commit();
        return [
            'content_link' => [
                'id' => $contentLinkId,
                'pilot_id' => (string)$pilot['id'],
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
            'idempotent_replay' => false,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}