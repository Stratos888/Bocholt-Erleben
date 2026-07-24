<?php
declare(strict_types=1);

require_once __DIR__ . '/_contract.php';

function be_startpartner_record_event(
    PDO $pdo,
    string $candidateId,
    string $eventType,
    ?string $fromStatus,
    ?string $toStatus,
    string $actorType,
    ?string $actorReference,
    array $payload = []
): void {
    $statement = $pdo->prepare(
        'INSERT INTO startpartner_candidate_events (
            candidate_id, event_type, from_status, to_status, actor_type, actor_reference, payload_json
         ) VALUES (
            :candidate_id, :event_type, :from_status, :to_status, :actor_type, :actor_reference, :payload_json
         )'
    );
    $statement->execute([
        'candidate_id' => $candidateId,
        'event_type' => $eventType,
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'actor_type' => $actorType,
        'actor_reference' => $actorReference,
        'payload_json' => $payload === [] ? null : json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
    ]);
}

function be_startpartner_upsert_control_case(
    PDO $pdo,
    string $candidateId,
    string $organizationName,
    string $source,
    string $desiredContentScope,
    string $candidateStatus,
    ?string $statusReason,
    string $actorReference = 'startpartner-gate1'
): void {
    $caseState = be_startpartner_case_state($candidateStatus);
    $caseId = be_cc_uuid();
    $sourcePayload = json_encode([
        'candidate_id' => $candidateId,
        'candidate_status' => $candidateStatus,
        'candidate_source' => $source,
        'desired_content_scope' => $desiredContentScope,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $select = $pdo->prepare(
        'SELECT id, state
         FROM control_cases
         WHERE source_system = :source_system
           AND source_reference = :source_reference
         FOR UPDATE'
    );
    $select->execute([
        'source_system' => 'startpartner_candidate',
        'source_reference' => $candidateId,
    ]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (is_array($existing)) {
        $update = $pdo->prepare(
            'UPDATE control_cases
             SET state = :state,
                 title = :title,
                 reason = :reason,
                 next_action = :next_action,
                 object_type = :object_type,
                 object_id = :object_id,
                 object_title = :object_title,
                 source_payload_json = :source_payload_json,
                 decision_ready = :decision_ready,
                 completed_at = :completed_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $update->execute([
            'state' => $caseState,
            'title' => 'Startpartner prüfen: ' . $organizationName,
            'reason' => $statusReason,
            'next_action' => be_startpartner_next_action($candidateStatus),
            'object_type' => 'startpartner_candidate',
            'object_id' => $candidateId,
            'object_title' => $organizationName,
            'source_payload_json' => $sourcePayload,
            'decision_ready' => $candidateStatus === 'qualified' ? 1 : 0,
            'completed_at' => in_array($caseState, ['done', 'rejected'], true)
                ? gmdate('Y-m-d H:i:s')
                : null,
            'id' => (string)$existing['id'],
        ]);
        be_cc_record_event(
            $pdo,
            (string)$existing['id'],
            'startpartner_candidate_sync',
            (string)$existing['state'],
            $caseState,
            ['candidate_status' => $candidateStatus],
            $actorReference
        );
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO control_cases (
            id, case_type, state, priority, title, reason, next_action,
            object_type, object_id, object_title,
            source_system, source_reference, source_payload_json,
            due_at, snoozed_until, blocked_reason, decision_ready, completed_at
         ) VALUES (
            :id, :case_type, :state, :priority, :title, :reason, :next_action,
            :object_type, :object_id, :object_title,
            :source_system, :source_reference, :source_payload_json,
            NULL, NULL, NULL, :decision_ready, :completed_at
         )'
    );
    $insert->execute([
        'id' => $caseId,
        'case_type' => 'intake',
        'state' => $caseState,
        'priority' => 'normal',
        'title' => 'Startpartner prüfen: ' . $organizationName,
        'reason' => $statusReason,
        'next_action' => be_startpartner_next_action($candidateStatus),
        'object_type' => 'startpartner_candidate',
        'object_id' => $candidateId,
        'object_title' => $organizationName,
        'source_system' => 'startpartner_candidate',
        'source_reference' => $candidateId,
        'source_payload_json' => $sourcePayload,
        'decision_ready' => $candidateStatus === 'qualified' ? 1 : 0,
        'completed_at' => in_array($caseState, ['done', 'rejected'], true)
            ? gmdate('Y-m-d H:i:s')
            : null,
    ]);
    be_cc_record_event(
        $pdo,
        $caseId,
        'startpartner_candidate_create',
        null,
        $caseState,
        ['candidate_status' => $candidateStatus],
        $actorReference
    );
}

function be_startpartner_candidate_from_row(PDO $pdo, array $row, bool $includeEvents = true): array
{
    $candidateId = (string)$row['id'];
    $contactStatement = $pdo->prepare(
        'SELECT id, contact_name, contact_role, email, phone, is_primary, created_at, updated_at
         FROM startpartner_candidate_contacts
         WHERE candidate_id = :candidate_id
         ORDER BY is_primary DESC, id ASC'
    );
    $contactStatement->execute(['candidate_id' => $candidateId]);

    $events = [];
    if ($includeEvents) {
        $eventStatement = $pdo->prepare(
            'SELECT id, event_type, from_status, to_status, actor_type, actor_reference, payload_json, created_at
             FROM startpartner_candidate_events
             WHERE candidate_id = :candidate_id
             ORDER BY id ASC'
        );
        $eventStatement->execute(['candidate_id' => $candidateId]);
        foreach ($eventStatement->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $event['payload'] = !empty($event['payload_json'])
                ? json_decode((string)$event['payload_json'], true)
                : null;
            unset($event['payload_json']);
            $events[] = $event;
        }
    }

    return [
        'id' => $candidateId,
        'source' => (string)$row['source'],
        'source_reference' => $row['source_reference'] ?? null,
        'organization_name' => (string)$row['organization_name'],
        'website_url' => $row['website_url'] ?? null,
        'description_text' => $row['description_text'] ?? null,
        'desired_content_scope' => (string)$row['desired_content_scope'],
        'status' => (string)$row['status'],
        'status_reason' => $row['status_reason'] ?? null,
        'privacy_policy_version' => $row['privacy_policy_version'] ?? null,
        'form_version' => (string)$row['form_version'],
        'retention_review_at' => $row['retention_review_at'] ?? null,
        'closed_at' => $row['closed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'contacts' => $contactStatement->fetchAll(PDO::FETCH_ASSOC),
        'events' => $events,
    ];
}

function be_startpartner_find_candidate_row(PDO $pdo, string $field, string $value, bool $forUpdate = false): ?array
{
    if (!in_array($field, ['id', 'identity_key', 'idempotency_key_hash'], true)) {
        throw new InvalidArgumentException('Unsupported candidate lookup field.');
    }
    $sql = "SELECT * FROM startpartner_candidates WHERE {$field} = :value LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute(['value' => $value]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

