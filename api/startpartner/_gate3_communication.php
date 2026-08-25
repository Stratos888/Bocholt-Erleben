<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_domain.php';

const BE_STARTPARTNER_GATE3_TERMS_VERSION = 'startpartner-pilot-2026-08-v2';
const BE_STARTPARTNER_GATE3_TERMS_REFERENCE = 'system://startpartner/pilot-terms/startpartner-pilot-2026-08-v2';
const BE_STARTPARTNER_GATE3_COMMUNICATION_NOTICE_VERSION = 'startpartner-pilot-terms-mail-v2';

function be_startpartner_gate3_terms_scope_label(string $scope): string
{
    return match ($scope) {
        'events' => 'Veranstaltungen',
        'activities' => 'Aktivitäten',
        'both' => 'Veranstaltungen und Aktivitäten',
        default => throw new DomainException('Candidate content scope must be resolved before pilot terms can be sent.'),
    };
}

function be_startpartner_gate3_terms_snapshot(array $candidate): array
{
    $scope = (string)($candidate['desired_content_scope'] ?? '');
    $scopeLabel = be_startpartner_gate3_terms_scope_label($scope);

    $targetPlanKeys = match ($scope) {
        'events' => ['active'],
        'activities' => ['activity_basic'],
        'both' => ['active', 'activity_basic'],
    };
    $eventLimit = in_array($scope, ['events', 'both'], true) ? 8 : null;
    $activityLimit = in_array($scope, ['activities', 'both'], true) ? 1 : null;
    $month = (int)gmdate('n');
    $cohortKey = sprintf('startpartner-%s-h%d', gmdate('Y'), $month <= 6 ? 1 : 2);

    $sourceCare = 'Der Startpartner stellt vollständige Inhaltsinformationen bereit, meldet Änderungen oder Absagen zeitnah und bestätigt, dass Bocholt erleben die von ihm bereitgestellten Texte und Bilder für die vereinbarte Darstellung im Pilot redaktionell bearbeiten und veröffentlichen darf.';
    $maintenanceScope = 'Bocholt erleben übernimmt im Pilot die vereinbarte redaktionelle Aufbereitung und Pflege. Konkrete Quellen- und Betreuungsdetails werden vor der Aktivierung im Onboarding festgelegt.';
    $reachContribution = 'Der Startpartner und Bocholt erleben vereinbaren vor dem Pilotstart einen realistischen Reichweitenbeitrag mit Kanal und Zieltermin. Die tatsächliche Erfüllung wird erst während des laufenden Piloten bewertet.';
    $termsClauses = [
        'Die Pilotphase läuft sechs Kalendermonate ab der späteren Aktivierung.',
        'Der Pilot ist kostenlos. Es wird keine Zahlungsart hinterlegt und kein Stripe-Abonnement angelegt.',
        'Es gibt keine automatische kostenpflichtige Verlängerung oder Umwandlung. Eine spätere Fortführung erfordert eine neue ausdrückliche Entscheidung.',
        'Inhalte bleiben redaktionell prüfpflichtig; der Startpartnerstatus ist keine Veröffentlichungsgarantie.',
        'Der Startpartner stellt vollständige Informationen bereit und meldet Änderungen oder Absagen zeitnah.',
        'Der Startpartner bestätigt für die von ihm bereitgestellten Texte und Bilder die erforderliche Nutzungsfreigabe für die vereinbarte Darstellung auf Bocholt erleben; redaktionelle Anpassungen im Rahmen der Darstellung sind zulässig.',
        'Quellen- und Pflegeweg werden im Pilot verbindlich dokumentiert.',
        'Vor dem Pilotstart wird ein realistischer Reichweitenbeitrag mit Kanal und Zieltermin vereinbart; seine tatsächliche Erfüllung ist Teil des laufenden Piloten und keine Voraussetzung, die vorab künstlich nachgewiesen werden muss.',
        'Bocholt erleben misst nachvollziehbare Nutzung und Interaktionen auf der Plattform; daraus werden keine Besucherzahlen vor Ort, Buchungen oder Umsätze abgeleitet.',
    ];
    $privacyVersion = trim((string)($candidate['privacy_policy_version'] ?? ''));
    if ($privacyVersion === '') {
        $privacyVersion = 'startpartner-public-consent-v1';
    }

    $termsContent = [
        'terms_version' => BE_STARTPARTNER_GATE3_TERMS_VERSION,
        'desired_content_scope' => $scope,
        'target_plan_keys' => $targetPlanKeys,
        'event_limit_per_pilot_month' => $eventLimit,
        'activity_concurrent_limit' => $activityLimit,
        'is_event_unlimited' => false,
        'source_care_text' => $sourceCare,
        'maintenance_scope_text' => $maintenanceScope,
        'reach_contribution_text' => $reachContribution,
        'content_rights_granted' => true,
        'content_rights_scope' => 'partner_provided_texts_and_images_for_pilot_publication',
        'distribution_commitment_rule' => 'agreed_before_activation_fulfilled_during_active_pilot',
        'privacy_notice_version' => $privacyVersion,
        'communication_notice_version' => BE_STARTPARTNER_GATE3_COMMUNICATION_NOTICE_VERSION,
        'pilot_duration_rule' => 'six_calendar_months_after_gate4_activation',
        'terms_clauses' => $termsClauses,
        'no_automatic_paid_renewal' => true,
        'payment_method_required' => false,
        'paid_subscription_created' => false,
    ];
    $termsDigest = hash(
        'sha256',
        json_encode(
            $termsContent,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    );

    return array_merge($termsContent, [
        'terms_reference' => BE_STARTPARTNER_GATE3_TERMS_REFERENCE,
        'terms_digest' => $termsDigest,
        'scope_label' => $scopeLabel,
        'cohort_key' => $cohortKey,
        'candidate_revision' => (int)($candidate['revision'] ?? 0),
        'planned_activation_start' => null,
        'planned_activation_end' => null,
    ]);
}

function be_startpartner_gate3_terms_event_for_operation(
    PDO $pdo,
    string $candidateId,
    string $operationId
): ?array {
    $statement = $pdo->prepare(
        "SELECT event_type, payload_json
         FROM startpartner_candidate_events
         WHERE candidate_id = :candidate_id
           AND event_type IN ('pilot_terms_sent', 'pilot_terms_mail_failed')
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
            'status' => (string)$row['event_type'] === 'pilot_terms_sent' ? 'sent' : 'failed',
            'payload' => $payload,
        ];
    }
    return null;
}

function be_startpartner_gate3_latest_terms_snapshot(PDO $pdo, string $candidateId): ?array
{
    $statement = $pdo->prepare(
        "SELECT payload_json
         FROM startpartner_candidate_events
         WHERE candidate_id = :candidate_id
           AND event_type = 'pilot_terms_sent'
         ORDER BY id DESC
         LIMIT 1"
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $payloadJson = $statement->fetchColumn();
    if ($payloadJson === false || $payloadJson === null) {
        return null;
    }
    $payload = json_decode((string)$payloadJson, true, 512, JSON_THROW_ON_ERROR);
    $snapshot = is_array($payload) ? ($payload['terms_snapshot'] ?? null) : null;
    return is_array($snapshot) ? $snapshot : null;
}

function be_startpartner_gate3_terms_primary_contact(array $candidate): array
{
    $contacts = is_array($candidate['contacts'] ?? null) ? $candidate['contacts'] : [];
    foreach ($contacts as $contact) {
        if (is_array($contact) && !empty($contact['is_primary'])) {
            return $contact;
        }
    }
    if (isset($contacts[0]) && is_array($contacts[0])) {
        return $contacts[0];
    }
    throw new DomainException('Primary candidate contact is missing.');
}

function be_startpartner_gate3_terms_record(
    PDO $pdo,
    array $candidate,
    string $eventType,
    string $operatorName,
    string $operationId,
    ?array $snapshot,
    ?string $failureCode = null
): void {
    $payload = ['operation_id' => $operationId];
    if ($snapshot !== null) {
        $payload['terms_snapshot'] = $snapshot;
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

function be_startpartner_gate3_terms_mail(array $candidate, array $contact, array $snapshot): array
{
    $contactName = trim((string)($contact['contact_name'] ?? ''));
    $organization = trim((string)($candidate['organization_name'] ?? ''));
    $eventText = $snapshot['event_limit_per_pilot_month'] !== null
        ? (string)$snapshot['event_limit_per_pilot_month'] . ' Veranstaltungen je Pilotmonat'
        : null;
    $activityText = $snapshot['activity_concurrent_limit'] !== null
        ? (string)$snapshot['activity_concurrent_limit'] . ' Aktivität gleichzeitig'
        : null;
    $scopeDetails = implode(' + ', array_values(array_filter([$eventText, $activityText])));

    return [
        'subject' => 'Deine Pilotbedingungen für Startpartner',
        'to_address' => trim((string)($contact['email'] ?? '')),
        'to_name' => $contactName !== '' ? $contactName : null,
        'mail_data' => [
            'title' => 'Pilotbedingungen für deinen Startpartner-Pilot',
            'preheader' => 'Dein Platz ist reserviert. Für die Piloteinrichtung brauchen wir jetzt deine ausdrückliche Bestätigung.',
            'greeting' => be_mail_greeting($contactName),
            'intro' => 'Dein Startpartnerplatz ist reserviert. Bevor wir die Piloteinrichtung starten, bestätigen wir gemeinsam den vereinbarten Rahmen.',
            'details' => [
                ['label' => 'Organisation', 'value' => $organization],
                ['label' => 'Bereich', 'value' => (string)$snapshot['scope_label']],
                ['label' => 'Pilotumfang', 'value' => $scopeDetails !== '' ? $scopeDetails : 'vereinbarter Pilotumfang'],
                ['label' => 'Fassung', 'value' => (string)$snapshot['terms_version']],
            ],
            'body' => "Für den Startpartner-Pilot gilt:\n\n"
                . implode("\n", array_map(
                    static fn(string $clause): string => '• ' . $clause,
                    (array)$snapshot['terms_clauses']
                ))
                . "\n\nWenn du damit einverstanden bist, antworte bitte auf diese E-Mail mit einer eindeutigen Bestätigung, zum Beispiel: „Ich bestätige die Pilotbedingungen.“",
            'notice_title' => 'Noch kein Pilotstart',
            'notice_text' => 'Mit deiner Bestätigung beginnt die sechsmonatige Pilotphase noch nicht. Sie startet erst nach der vollständigen Einrichtung und der ausdrücklichen Aktion „Pilot jetzt starten“.',
        ],
    ];
}

function be_startpartner_gate3_send_terms(PDO $pdo, string $candidateId, array $input): array
{
    be_startpartner_require_schema($pdo);
    be_startpartner_gate3_require_schema($pdo);

    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $operatorName = be_startpartner_gate2_operator_name($input['operator_name'] ?? null);
    $expectedRevision = be_startpartner_gate2_expected_revision($input['expected_revision'] ?? null);

    $operationEvent = be_startpartner_gate3_terms_event_for_operation($pdo, $candidateId, $operationId);
    if (is_array($operationEvent)) {
        if ((string)$operationEvent['status'] === 'sent') {
            return [
                'status' => 'sent',
                'sent' => true,
                'idempotent_replay' => true,
                'terms_snapshot' => $operationEvent['payload']['terms_snapshot'] ?? null,
                'candidate' => be_startpartner_gate3_candidate_detail($pdo, $candidateId),
            ];
        }
        throw new DomainException('Der vorige Versandversuch ist fehlgeschlagen. Bitte löse den Versand erneut aus.');
    }

    $candidateRow = be_startpartner_gate2_candidate_row($pdo, $candidateId);
    if ((int)$candidateRow['revision'] !== $expectedRevision) {
        throw new BeStartpartnerConflictException(
            'Candidate was changed in the meantime.',
            be_startpartner_gate3_candidate_detail($pdo, $candidateId)
        );
    }
    if ((string)$candidateRow['status'] !== 'accepted_pending_terms') {
        throw new DomainException('Pilotbedingungen können nur für einen reservierten Startpartnerplatz gesendet werden.');
    }

    $pilotStatement = $pdo->prepare(
        'SELECT id FROM startpartner_pilots WHERE candidate_id = :candidate_id LIMIT 1'
    );
    $pilotStatement->execute(['candidate_id' => $candidateId]);
    if ($pilotStatement->fetchColumn() !== false) {
        throw new DomainException('Für diesen Kandidaten wurde bereits ein Pilot angelegt.');
    }

    $candidate = be_startpartner_gate3_candidate_detail($pdo, $candidateId);
    $latestSnapshot = be_startpartner_gate3_latest_terms_snapshot($pdo, $candidateId);
    if (
        is_array($latestSnapshot)
        && (int)($latestSnapshot['candidate_revision'] ?? -1) === (int)$candidate['revision']
    ) {
        return [
            'status' => 'sent',
            'sent' => true,
            'idempotent_replay' => true,
            'terms_snapshot' => $latestSnapshot,
            'candidate' => $candidate,
        ];
    }

    $snapshot = be_startpartner_gate3_terms_snapshot($candidate);
    $contact = be_startpartner_gate3_terms_primary_contact($candidate);
    $mail = be_startpartner_gate3_terms_mail($candidate, $contact, $snapshot);
    $toAddress = (string)$mail['to_address'];
    if ($toAddress === '' || !filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
        be_startpartner_gate3_terms_record(
            $pdo,
            $candidate,
            'pilot_terms_mail_failed',
            $operatorName,
            $operationId,
            $snapshot,
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
        be_startpartner_gate3_terms_record(
            $pdo,
            $candidate,
            'pilot_terms_mail_failed',
            $operatorName,
            $operationId,
            $snapshot,
            'mail_transport_failed'
        );
        error_log('Startpartner pilot terms mail failed: ' . $error->getMessage());
        throw new DomainException('Pilotbedingungen konnten nicht versendet werden. Bitte versuche es erneut.');
    }

    be_startpartner_gate3_terms_record(
        $pdo,
        $candidate,
        'pilot_terms_sent',
        $operatorName,
        $operationId,
        $snapshot
    );

    return [
        'status' => 'sent',
        'sent' => true,
        'idempotent_replay' => false,
        'terms_snapshot' => $snapshot,
        'candidate' => be_startpartner_gate3_candidate_detail($pdo, $candidateId),
    ];
}

function be_startpartner_gate3_simple_confirmation_input(
    array $candidate,
    array $contact,
    array $snapshot,
    array $input
): array {
    if (($input['partner_acceptance_confirmed'] ?? false) !== true) {
        throw new InvalidArgumentException('Die ausdrückliche Bestätigung des Partners muss vorliegen.');
    }
    if (
        (int)($snapshot['candidate_revision'] ?? -1) !== (int)($candidate['revision'] ?? -2)
        || (string)($snapshot['desired_content_scope'] ?? '') !== (string)($candidate['desired_content_scope'] ?? '')
    ) {
        throw new DomainException('Das Startpartner-Profil wurde nach dem Bedingungenversand geändert. Bitte sende die Pilotbedingungen erneut.');
    }
    if (
        (string)($snapshot['terms_version'] ?? '') === BE_STARTPARTNER_GATE3_TERMS_VERSION
        && (($snapshot['content_rights_granted'] ?? false) !== true)
    ) {
        throw new DomainException('Die gebundene Pilotbedingungen-Fassung enthält keine eindeutige Nutzungsfreigabe. Bitte sende die aktuellen Pilotbedingungen erneut.');
    }

    $acceptedAt = trim((string)($input['accepted_at'] ?? ''));
    if ($acceptedAt === '') {
        $acceptedAt = gmdate('c');
    }
    $channel = trim((string)($input['confirmation_channel'] ?? ''));
    if ($channel === '') {
        $channel = 'email_reply';
    }

    return array_merge($input, [
        'terms_version' => (string)$snapshot['terms_version'],
        'terms_reference' => (string)$snapshot['terms_reference'],
        'terms_digest' => (string)$snapshot['terms_digest'],
        'accepting_person' => (string)($contact['contact_name'] ?? ''),
        'accepting_organization' => (string)$candidate['organization_name'],
        'accepted_at' => $acceptedAt,
        'confirmation_channel' => $channel,
        'target_plan_keys' => (array)$snapshot['target_plan_keys'],
        'cohort_key' => (string)$snapshot['cohort_key'],
        'event_limit_per_pilot_month' => $snapshot['event_limit_per_pilot_month'],
        'activity_concurrent_limit' => $snapshot['activity_concurrent_limit'],
        'is_event_unlimited' => (bool)$snapshot['is_event_unlimited'],
        'source_care_text' => (string)$snapshot['source_care_text'],
        'maintenance_scope_text' => (string)$snapshot['maintenance_scope_text'],
        'reach_contribution_text' => (string)$snapshot['reach_contribution_text'],
        'privacy_notice_version' => (string)$snapshot['privacy_notice_version'],
        'communication_notice_version' => (string)$snapshot['communication_notice_version'],
        'planned_activation_start' => '',
        'planned_activation_end' => '',
        'no_automatic_paid_renewal' => true,
    ]);
}

function be_startpartner_gate3_completed_simple_replay(
    PDO $pdo,
    string $candidateId,
    string $operationId
): ?array {
    $statement = $pdo->prepare(
        'SELECT candidate_id, action, status, result_json
         FROM startpartner_candidate_operations
         WHERE operation_id = :operation_id
         LIMIT 1'
    );
    $statement->execute(['operation_id' => $operationId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }
    if (
        (string)$row['candidate_id'] !== $candidateId
        || (string)$row['action'] !== 'gate3.confirm_terms_and_create_pilot'
    ) {
        throw new BeStartpartnerConflictException('operation_id was already used with a different action.');
    }
    if ((string)$row['status'] !== 'completed' || $row['result_json'] === null) {
        throw new BeStartpartnerConflictException('operation_id is not replayable.');
    }
    $result = json_decode((string)$row['result_json'], true, 512, JSON_THROW_ON_ERROR);
    $result['idempotent_replay'] = true;
    return $result;
}

function be_startpartner_gate3_confirm_from_sent_terms(
    PDO $pdo,
    string $candidateId,
    array $input
): array {
    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $replay = be_startpartner_gate3_completed_simple_replay($pdo, $candidateId, $operationId);
    if (is_array($replay)) {
        return $replay;
    }

    $snapshot = be_startpartner_gate3_latest_terms_snapshot($pdo, $candidateId);
    if (!is_array($snapshot)) {
        throw new DomainException('Pilotbedingungen wurden noch nicht erfolgreich an den Partner versendet.');
    }

    $candidate = be_startpartner_gate3_candidate_detail($pdo, $candidateId);
    $contact = be_startpartner_gate3_terms_primary_contact($candidate);
    $confirmationInput = be_startpartner_gate3_simple_confirmation_input(
        $candidate,
        $contact,
        $snapshot,
        $input
    );

    return be_startpartner_gate3_confirm($pdo, $candidateId, $confirmationInput);
}
