<?php
declare(strict_types=1);

require_once __DIR__ . '/_review_decision_domain.php';

const BE_STARTPARTNER_REVIEW_MAIL_TOPICS = ['question', 'accepted', 'rejected', 'waitlisted'];

function be_startpartner_review_communication_topic_for_decision(string $decision): string
{
    return match ($decision) {
        'needs_information' => 'question',
        'approve' => 'accepted',
        'reject' => 'rejected',
        'waitlist' => 'waitlisted',
        default => throw new InvalidArgumentException('Unsupported review communication decision.'),
    };
}

function be_startpartner_review_communication_scope_label(string $scope): string
{
    return match ($scope) {
        'events' => 'Veranstaltungen',
        'activities' => 'Aktivitäten',
        'both' => 'Veranstaltungen und Aktivitäten',
        default => 'Noch nicht festgelegt',
    };
}

function be_startpartner_review_communication_primary_contact(PDO $pdo, string $candidateId): ?array
{
    foreach (be_startpartner_gate2_contacts($pdo, $candidateId) as $contact) {
        if (!empty($contact['is_primary'])) {
            return $contact;
        }
    }
    $contacts = be_startpartner_gate2_contacts($pdo, $candidateId);
    return isset($contacts[0]) && is_array($contacts[0]) ? $contacts[0] : null;
}

function be_startpartner_review_communication_allowed_statuses(string $topic): array
{
    return match ($topic) {
        'question' => ['needs_information', 'contact_pending', 'awaiting_response'],
        'accepted' => ['accepted_pending_terms'],
        'rejected' => ['rejected'],
        'waitlisted' => ['waitlisted'],
        default => throw new InvalidArgumentException('Unsupported review mail topic.'),
    };
}

function be_startpartner_review_communication_event_state(PDO $pdo, string $candidateId, string $topic, string $operationId): ?string
{
    $statement = $pdo->prepare(
        "SELECT event_type, payload_json
         FROM startpartner_candidate_events
         WHERE candidate_id = :candidate_id
           AND event_type IN ('review_mail_sent', 'review_mail_failed')
         ORDER BY id DESC
         LIMIT 100"
    );
    $statement->execute(['candidate_id' => $candidateId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload = $row['payload_json'] === null ? null : json_decode((string)$row['payload_json'], true);
        if (!is_array($payload)) {
            continue;
        }
        if ((string)($payload['topic'] ?? '') !== $topic || (string)($payload['operation_id'] ?? '') !== $operationId) {
            continue;
        }
        return (string)$row['event_type'] === 'review_mail_sent' ? 'sent' : 'failed';
    }
    return null;
}

function be_startpartner_review_communication_record(
    PDO $pdo,
    array $candidate,
    string $eventType,
    string $operatorName,
    string $topic,
    string $operationId,
    ?string $customerMessage = null,
    ?string $failureCode = null
): void {
    $payload = [
        'topic' => $topic,
        'operation_id' => $operationId,
    ];
    if ($customerMessage !== null) {
        $payload['customer_message'] = $customerMessage;
    }
    if ($failureCode !== null) {
        $payload['failure_code'] = $failureCode;
    }
    be_startpartner_gate2_record_event(
        $pdo,
        (string)$candidate['id'],
        $eventType,
        (string)$candidate['status'],
        (string)$candidate['status'],
        $operatorName,
        $payload
    );
}

function be_startpartner_review_communication_mail_data(
    array $candidate,
    array $contact,
    string $topic,
    ?string $customerMessage
): array {
    $organization = trim((string)($candidate['organization_name'] ?? ''));
    $contactName = trim((string)($contact['contact_name'] ?? ''));
    $scope = be_startpartner_review_communication_scope_label((string)($candidate['desired_content_scope'] ?? 'unknown'));
    $details = [
        ['label' => 'Organisation', 'value' => $organization],
        ['label' => 'Bereich', 'value' => $scope],
    ];

    if ($topic === 'question') {
        return [
            'subject' => 'Rückfrage zu deiner Startpartner-Anfrage',
            'to_name' => $contactName !== '' ? $contactName : null,
            'mail_data' => [
                'title' => 'Noch eine Rückfrage zu deiner Startpartner-Anfrage',
                'preheader' => 'Für die Prüfung deiner Startpartner-Anfrage fehlt uns noch eine Angabe.',
                'greeting' => be_mail_greeting($contactName),
                'intro' => 'Vielen Dank für deine Startpartner-Anfrage. Für unsere Prüfung fehlt uns noch eine Angabe:',
                'details' => $details,
                'body' => (string)$customerMessage . "\n\nBitte sende uns die fehlenden Angaben als Antwort auf diese Nachricht. Danach prüfen wir deine Anfrage weiter.",
                'notice_title' => '',
                'notice_text' => '',
            ],
        ];
    }

    if ($topic === 'accepted') {
        return [
            'subject' => 'Deine Startpartner-Anfrage passt',
            'to_name' => $contactName !== '' ? $contactName : null,
            'mail_data' => [
                'title' => 'Wir möchten dich als Startpartner aufnehmen',
                'preheader' => 'Deine Anfrage wurde geprüft und ein Startpartnerplatz ist für dich reserviert.',
                'greeting' => be_mail_greeting($contactName),
                'intro' => 'Wir haben deine Anfrage geprüft und möchten dich als Startpartner von Bocholt erleben aufnehmen.',
                'details' => $details,
                'body' => 'Dein Startpartnerplatz ist zunächst reserviert. Als Nächstes klären wir gemeinsam die Bedingungen und die Einrichtung.',
                'notice_title' => 'Wichtig',
                'notice_text' => 'Die Pilotphase ist dadurch noch nicht gestartet. Es wird kein kostenpflichtiger Tarif und keine Zahlung ausgelöst.',
            ],
        ];
    }

    if ($topic === 'rejected') {
        return [
            'subject' => 'Rückmeldung zu deiner Startpartner-Anfrage',
            'to_name' => $contactName !== '' ? $contactName : null,
            'mail_data' => [
                'title' => 'Rückmeldung zu deiner Startpartner-Anfrage',
                'preheader' => 'Wir haben deine Startpartner-Anfrage geprüft.',
                'greeting' => be_mail_greeting($contactName),
                'intro' => 'Vielen Dank für dein Interesse an Startpartner. Wir haben deine Anfrage geprüft und können sie aktuell leider nicht für den Startpartner-Test berücksichtigen.',
                'details' => $details,
                'body' => 'Wenn sich dein Angebot oder die Rahmenbedingungen ändern, kannst du dich später gerne erneut bei uns melden.',
                'notice_title' => $customerMessage !== null ? 'Hinweis zur Entscheidung' : '',
                'notice_text' => $customerMessage ?? '',
            ],
        ];
    }

    return [
        'subject' => 'Deine Startpartner-Anfrage ist auf der Warteliste',
        'to_name' => $contactName !== '' ? $contactName : null,
        'mail_data' => [
            'title' => 'Aktuell ist kein Startpartnerplatz frei',
            'preheader' => 'Deine Anfrage passt grundsätzlich, aktuell sind jedoch alle Startpartnerplätze belegt.',
            'greeting' => be_mail_greeting($contactName),
            'intro' => 'Wir haben deine Anfrage geprüft. Grundsätzlich passt dein Angebot zu unserem Startpartner-Test.',
            'details' => $details,
            'body' => 'Aktuell sind jedoch alle Startpartnerplätze belegt. Wir haben deine Anfrage deshalb vorgemerkt und prüfen sie erneut, sobald wieder Kapazität frei wird.',
            'notice_title' => '',
            'notice_text' => '',
        ],
    ];
}

function be_startpartner_review_communication_child_operation_id(string $operationId, string $suffix): string
{
    return 'gate2:304:communication:' . $suffix . ':' . substr(hash('sha256', $operationId . '|' . $suffix), 0, 32);
}

function be_startpartner_review_communication_prepare_question_state(
    PDO $pdo,
    array $candidate,
    string $question,
    string $operatorName,
    string $operationId
): array {
    if ((string)$candidate['status'] !== 'needs_information') {
        return $candidate;
    }
    $prepared = be_startpartner_gate2_action($pdo, (string)$candidate['id'], [
        'candidate_id' => (string)$candidate['id'],
        'operation_id' => be_startpartner_review_communication_child_operation_id($operationId, 'prepare'),
        'expected_revision' => (int)$candidate['revision'],
        'operator_name' => $operatorName,
        'action' => 'mark_contact_pending',
        'reason' => $question,
    ]);
    return (array)$prepared['candidate'];
}

function be_startpartner_review_communication_mark_awaiting(
    PDO $pdo,
    array $candidate,
    string $question,
    string $operatorName,
    string $operationId
): array {
    if ((string)$candidate['status'] !== 'contact_pending') {
        return $candidate;
    }
    $awaiting = be_startpartner_gate2_action($pdo, (string)$candidate['id'], [
        'candidate_id' => (string)$candidate['id'],
        'operation_id' => be_startpartner_review_communication_child_operation_id($operationId, 'await'),
        'expected_revision' => (int)$candidate['revision'],
        'operator_name' => $operatorName,
        'action' => 'mark_awaiting_response',
        'reason' => $question,
    ]);
    return (array)$awaiting['candidate'];
}

function be_startpartner_review_communication_send(PDO $pdo, string $candidateId, array $input): array
{
    be_startpartner_require_schema($pdo);
    $topic = be_startpartner_validate_enum_value(
        trim((string)($input['topic'] ?? '')),
        BE_STARTPARTNER_REVIEW_MAIL_TOPICS,
        'topic'
    );
    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $operatorName = be_startpartner_gate2_operator_name($input['operator_name'] ?? null);

    $eventState = be_startpartner_review_communication_event_state($pdo, $candidateId, $topic, $operationId);
    if ($eventState !== null) {
        return [
            'status' => $eventState,
            'sent' => $eventState === 'sent',
            'idempotent_replay' => true,
            'candidate' => be_startpartner_gate2_candidate_detail($pdo, $candidateId),
        ];
    }

    $expectedRevision = be_startpartner_gate2_expected_revision($input['expected_revision'] ?? null);
    $candidate = be_startpartner_gate2_candidate_row($pdo, $candidateId);
    if ((int)$candidate['revision'] !== $expectedRevision) {
        throw new BeStartpartnerConflictException(
            'Candidate was changed in the meantime.',
            be_startpartner_gate2_candidate_detail($pdo, $candidateId)
        );
    }
    if (!in_array((string)$candidate['status'], be_startpartner_review_communication_allowed_statuses($topic), true)) {
        throw new DomainException('Review communication is not allowed in the current candidate status.');
    }

    $customerMessage = be_startpartner_clean_text($input['customer_message'] ?? null, 5000, 'customer_message');
    if ($topic === 'question' && $customerMessage === null) {
        $customerMessage = be_startpartner_clean_text($candidate['status_reason'] ?? null, 5000, 'status_reason');
    }
    if ($topic === 'question' && $customerMessage === null) {
        be_startpartner_review_communication_record(
            $pdo, $candidate, 'review_mail_failed', $operatorName, $topic, $operationId, null, 'question_missing'
        );
        return [
            'status' => 'failed',
            'sent' => false,
            'idempotent_replay' => false,
            'failure_code' => 'question_missing',
            'candidate' => be_startpartner_gate2_candidate_detail($pdo, $candidateId),
        ];
    }

    if ($topic === 'question' && (string)$candidate['status'] === 'needs_information') {
        $candidate = be_startpartner_review_communication_prepare_question_state(
            $pdo, $candidate, (string)$customerMessage, $operatorName, $operationId
        );
    }

    $contact = be_startpartner_review_communication_primary_contact($pdo, $candidateId);
    $email = trim((string)($contact['email'] ?? ''));
    if (!is_array($contact) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        be_startpartner_review_communication_record(
            $pdo, $candidate, 'review_mail_failed', $operatorName, $topic, $operationId, $customerMessage, 'recipient_missing'
        );
        return [
            'status' => 'failed',
            'sent' => false,
            'idempotent_replay' => false,
            'failure_code' => 'recipient_missing',
            'candidate' => be_startpartner_gate2_candidate_detail($pdo, $candidateId),
        ];
    }

    $mail = be_startpartner_review_communication_mail_data($candidate, $contact, $topic, $customerMessage);
    $mailData = (array)$mail['mail_data'];
    try {
        be_send_mail(
            $email,
            (string)$mail['subject'],
            be_render_system_mail_text($mailData),
            $mail['to_name'] !== null ? (string)$mail['to_name'] : null,
            be_render_system_mail_html($mailData)
        );
    } catch (Throwable $error) {
        error_log('Startpartner review mail failed: ' . $error->getMessage());
        be_startpartner_review_communication_record(
            $pdo, $candidate, 'review_mail_failed', $operatorName, $topic, $operationId, $customerMessage, 'delivery_failed'
        );
        return [
            'status' => 'failed',
            'sent' => false,
            'idempotent_replay' => false,
            'failure_code' => 'delivery_failed',
            'candidate' => be_startpartner_gate2_candidate_detail($pdo, $candidateId),
        ];
    }

    be_startpartner_review_communication_record(
        $pdo, $candidate, 'review_mail_sent', $operatorName, $topic, $operationId, $customerMessage
    );

    $current = be_startpartner_gate2_candidate_detail($pdo, $candidateId);
    $stateSynced = true;
    if ($topic === 'question' && (string)$current['status'] !== 'awaiting_response') {
        try {
            $current = be_startpartner_review_communication_mark_awaiting(
                $pdo, $current, (string)$customerMessage, $operatorName, $operationId
            );
        } catch (Throwable $error) {
            $stateSynced = false;
            error_log('Startpartner review mail state sync failed: ' . $error->getMessage());
            be_startpartner_review_communication_record(
                $pdo, $candidate, 'review_mail_state_sync_failed', $operatorName, $topic, $operationId, $customerMessage, 'state_sync_failed'
            );
            $current = be_startpartner_gate2_candidate_detail($pdo, $candidateId);
        }
    }

    return [
        'status' => 'sent',
        'sent' => true,
        'idempotent_replay' => false,
        'state_synced' => $stateSynced,
        'candidate' => $current,
    ];
}
