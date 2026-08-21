<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_communication.php';

function be_startpartner_gate3_retry_event_for_operation(
    PDO $pdo,
    string $candidateId,
    string $operationId
): ?array {
    $statement = $pdo->prepare(
        "SELECT event_type, payload_json
         FROM startpartner_candidate_events
         WHERE candidate_id = :candidate_id
           AND event_type IN ('pilot_terms_resent', 'pilot_terms_resend_failed')
         ORDER BY id DESC
         LIMIT 100"
    );
    $statement->execute(['candidate_id' => $candidateId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload = $row['payload_json'] === null
            ? null
            : json_decode((string)$row['payload_json'], true);
        if (!is_array($payload) || (string)($payload['operation_id'] ?? '') !== $operationId) {
            continue;
        }
        return [
            'status' => (string)$row['event_type'] === 'pilot_terms_resent' ? 'sent' : 'failed',
            'payload' => $payload,
        ];
    }
    return null;
}

function be_startpartner_gate3_retry_record(
    PDO $pdo,
    array $candidate,
    string $eventType,
    string $operatorName,
    string $operationId,
    array $snapshot,
    array $contact,
    string $transportStatus,
    ?string $failureCode = null
): void {
    $payload = [
        'operation_id' => $operationId,
        'terms_snapshot' => $snapshot,
        'recipient_address' => trim((string)($contact['email'] ?? '')),
        'recipient_name' => trim((string)($contact['contact_name'] ?? '')),
        'transport_status' => $transportStatus,
    ];
    if ($transportStatus === 'accepted') {
        // be_send_mail() kehrt erst nach der finalen SMTP-DATA-Antwort 250 zurück.
        $payload['transport_final_code'] = 250;
        $payload['transport_accepted_at'] = gmdate('c');
    }
    if ($failureCode !== null) {
        $payload['failure_code'] = $failureCode;
    }

    be_startpartner_record_event(
        $pdo,
        (string)$candidate['id'],
        $eventType,
        (string)$candidate['status'],
        (string)$candidate['status'],
        'operator',
        $operatorName,
        $payload
    );
}

function be_startpartner_gate3_resend_terms(PDO $pdo, string $candidateId, array $input): array
{
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);

    if (($input['delivery_not_received_confirmed'] ?? false) !== true) {
        throw new InvalidArgumentException('Der Wiederholungsversand muss bewusst nach bestätigter Nichtzustellung ausgelöst werden.');
    }

    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $operatorName = be_startpartner_gate2_operator_name($input['operator_name'] ?? null);
    $expectedRevision = be_startpartner_gate2_expected_revision($input['expected_revision'] ?? null);

    $operationEvent = be_startpartner_gate3_retry_event_for_operation($pdo, $candidateId, $operationId);
    if (is_array($operationEvent)) {
        if ((string)$operationEvent['status'] === 'sent') {
            return [
                'status' => 'resent',
                'sent' => true,
                'idempotent_replay' => true,
                'terms_snapshot' => $operationEvent['payload']['terms_snapshot'] ?? null,
                'delivery' => $operationEvent['payload'],
                'candidate' => be_startpartner_gate3_candidate_detail($pdo, $candidateId),
            ];
        }
        throw new DomainException('Der vorige Wiederholungsversand ist fehlgeschlagen. Bitte löse einen neuen Versandversuch aus.');
    }

    $candidateRow = be_startpartner_gate2_candidate_row($pdo, $candidateId);
    if ((int)$candidateRow['revision'] !== $expectedRevision) {
        throw new BeStartpartnerConflictException(
            'Candidate was changed in the meantime.',
            be_startpartner_gate3_candidate_detail($pdo, $candidateId)
        );
    }
    if ((string)$candidateRow['status'] !== 'accepted_pending_terms') {
        throw new DomainException('Pilotbedingungen können nur für einen reservierten Startpartnerplatz erneut gesendet werden.');
    }

    $pilotStatement = $pdo->prepare(
        'SELECT id FROM startpartner_pilots WHERE candidate_id = :candidate_id LIMIT 1'
    );
    $pilotStatement->execute(['candidate_id' => $candidateId]);
    if ($pilotStatement->fetchColumn() !== false) {
        throw new DomainException('Für diesen Kandidaten wurde bereits ein Pilot angelegt.');
    }

    $candidate = be_startpartner_gate3_candidate_detail($pdo, $candidateId);
    if (!is_array($candidate['active_reservation'] ?? null)) {
        throw new DomainException('Der Startpartnerplatz ist nicht mehr aktiv reserviert.');
    }

    $snapshot = be_startpartner_gate3_latest_terms_snapshot($pdo, $candidateId);
    if (!is_array($snapshot)) {
        throw new DomainException('Es gibt keine erfolgreich gebundene Pilotbedingungen-Fassung für einen Wiederholungsversand.');
    }
    if (
        (int)($snapshot['candidate_revision'] ?? -1) !== (int)$candidate['revision']
        || (string)($snapshot['desired_content_scope'] ?? '') !== (string)$candidate['desired_content_scope']
    ) {
        throw new DomainException('Das Startpartner-Profil wurde nach dem Bedingungenversand geändert. Bitte sende die aktuellen Pilotbedingungen neu.');
    }

    $contact = be_startpartner_gate3_terms_primary_contact($candidate);
    $mail = be_startpartner_gate3_terms_mail($candidate, $contact, $snapshot);
    $toAddress = (string)$mail['to_address'];
    if ($toAddress === '' || !filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
        be_startpartner_gate3_retry_record(
            $pdo,
            $candidate,
            'pilot_terms_resend_failed',
            $operatorName,
            $operationId,
            $snapshot,
            $contact,
            'failed',
            'recipient_missing'
        );
        throw new DomainException('Für den Hauptkontakt ist keine gültige E-Mail-Adresse hinterlegt.');
    }

    $mailData = (array)$mail['mail_data'];
    try {
        be_send_mail(
            $toAddress,
            (string)$mail['subject'],
            be_render_system_mail_text($mailData),
            $mail['to_name'] !== null ? (string)$mail['to_name'] : null,
            be_render_system_mail_html($mailData)
        );
    } catch (Throwable $error) {
        be_startpartner_gate3_retry_record(
            $pdo,
            $candidate,
            'pilot_terms_resend_failed',
            $operatorName,
            $operationId,
            $snapshot,
            $contact,
            'failed',
            'mail_transport_failed'
        );
        error_log('Startpartner pilot terms resend failed: ' . $error->getMessage());
        throw new DomainException('Pilotbedingungen konnten nicht erneut versendet werden. Bitte versuche es erneut.');
    }

    be_startpartner_gate3_retry_record(
        $pdo,
        $candidate,
        'pilot_terms_resent',
        $operatorName,
        $operationId,
        $snapshot,
        $contact,
        'accepted'
    );

    return [
        'status' => 'resent',
        'sent' => true,
        'idempotent_replay' => false,
        'terms_snapshot' => $snapshot,
        'delivery' => [
            'recipient_address' => $toAddress,
            'transport_status' => 'accepted',
            'transport_final_code' => 250,
        ],
        'candidate' => be_startpartner_gate3_candidate_detail($pdo, $candidateId),
    ];
}
