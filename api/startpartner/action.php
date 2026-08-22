<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_delivery_retry.php';

function be_startpartner_gate3_identity_conflict_message(
    PDO $pdo,
    string $candidateId,
    string $fallbackMessage
): string {
    $emailConflict = $fallbackMessage === 'Organizer email exists for an incompatible organization.';
    $organizationConflict = $fallbackMessage === 'Organizer organization exists with another email; manual identity resolution is required.';
    if (!$emailConflict && !$organizationConflict) {
        return $fallbackMessage;
    }

    try {
        $candidateStatement = $pdo->prepare(
            'SELECT organization_name FROM startpartner_candidates WHERE id = :candidate_id LIMIT 1'
        );
        $candidateStatement->execute(['candidate_id' => $candidateId]);
        $candidateOrganization = trim((string)($candidateStatement->fetchColumn() ?: ''));

        $contactStatement = $pdo->prepare(
            'SELECT email, email_normalized
             FROM startpartner_candidate_contacts
             WHERE candidate_id = :candidate_id AND is_primary = 1
             ORDER BY id ASC LIMIT 1'
        );
        $contactStatement->execute(['candidate_id' => $candidateId]);
        $contact = $contactStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($contact)) {
            return $fallbackMessage;
        }

        $contactEmail = trim((string)($contact['email'] ?? ''));
        $emailNormalized = trim((string)($contact['email_normalized'] ?? ''));

        if ($emailConflict && $emailNormalized !== '') {
            $organizerStatement = $pdo->prepare(
                'SELECT id, organization_name, email
                 FROM organizers
                 WHERE email_normalized = :email_normalized
                 LIMIT 2'
            );
            $organizerStatement->execute(['email_normalized' => $emailNormalized]);
            $matches = $organizerStatement->fetchAll(PDO::FETCH_ASSOC);
            if (count($matches) === 1) {
                $existingOrganization = trim((string)($matches[0]['organization_name'] ?? ''));
                return sprintf(
                    'Organizer-Zuordnung blockiert: %s ist bereits dem Organizer „%s“ zugeordnet; Kandidat: „%s“. Es wurde nichts angelegt.',
                    $contactEmail !== '' ? $contactEmail : $emailNormalized,
                    $existingOrganization !== '' ? $existingOrganization : 'ohne Organisationsname',
                    $candidateOrganization !== '' ? $candidateOrganization : 'ohne Organisationsname'
                );
            }
            if (count($matches) > 1) {
                return sprintf(
                    'Organizer-Zuordnung blockiert: Für %s existieren mehrere Organizer-Identitäten. Es wurde nichts angelegt.',
                    $contactEmail !== '' ? $contactEmail : $emailNormalized
                );
            }
        }

        if ($organizationConflict && $candidateOrganization !== '') {
            $organizerStatement = $pdo->prepare(
                'SELECT id, organization_name, email
                 FROM organizers
                 WHERE organization_name = :organization_name
                 LIMIT 2'
            );
            $organizerStatement->execute(['organization_name' => $candidateOrganization]);
            $matches = $organizerStatement->fetchAll(PDO::FETCH_ASSOC);
            if (count($matches) === 1) {
                $existingEmail = trim((string)($matches[0]['email'] ?? ''));
                return sprintf(
                    'Organizer-Zuordnung blockiert: Die Organisation „%s“ existiert bereits mit %s; Hauptkontakt des Kandidaten: %s. Es wurde nichts angelegt.',
                    $candidateOrganization,
                    $existingEmail !== '' ? $existingEmail : 'einer anderen E-Mail-Adresse',
                    $contactEmail !== '' ? $contactEmail : $emailNormalized
                );
            }
        }
    } catch (Throwable $diagnosticError) {
        error_log('Startpartner organizer identity diagnostic failed: ' . $diagnosticError->getMessage());
    }

    return $fallbackMessage;
}

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }
    $candidateId = trim((string)($input['candidate_id'] ?? ''));
    if ($candidateId === '') {
        throw new InvalidArgumentException('candidate_id is required.');
    }

    $pdo = be_db();
    $requestedAction = trim((string)($input['action'] ?? ''));
    if ($requestedAction === 'send_pilot_terms') {
        $result = be_startpartner_gate3_send_terms($pdo, $candidateId, $input);
    } elseif ($requestedAction === 'resend_pilot_terms') {
        // Der bewusst bestätigte Dialog-Button ist die Operator-Aussage,
        // dass die zuvor SMTP-angenommene Nachricht extern nicht angekommen ist.
        $input['delivery_not_received_confirmed'] = true;
        $result = be_startpartner_gate3_resend_terms($pdo, $candidateId, $input);
    } elseif ($requestedAction === 'confirm_pilot_terms_simple') {
        // Der bewusst bestätigte Dialog-Button ist die Operator-Aussage,
        // dass eine ausdrückliche Partnerbestätigung tatsächlich vorliegt.
        $input['partner_acceptance_confirmed'] = true;
        $result = be_startpartner_gate3_confirm_from_sent_terms($pdo, $candidateId, $input);
    } elseif ($requestedAction === 'confirm_pilot_terms') {
        // Legacy-/Contract-Kompatibilität für bestehende synthetische Gate-3-Aufrufer.
        $result = be_startpartner_gate3_confirm($pdo, $candidateId, $input);
    } else {
        be_startpartner_gate3_guard_gate2_action($pdo, $candidateId, $requestedAction);
        $result = be_startpartner_gate2_action($pdo, $candidateId, $input);
    }
    be_json_response(200, ['status' => 'ok', 'data' => $result]);
} catch (BeStartpartnerConflictException $error) {
    be_json_response(409, [
        'status' => 'error',
        'code' => 'STARTPARTNER_CONFLICT',
        'message' => 'Zwischenzeitlich geändert.',
        'current' => $error->currentState,
        'error_message' => $error->getMessage(),
    ]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    $message = $error->getMessage();
    if (isset($pdo, $candidateId) && $pdo instanceof PDO && is_string($candidateId) && $candidateId !== '') {
        $message = be_startpartner_gate3_identity_conflict_message($pdo, $candidateId, $message);
    }
    be_json_response(422, ['status' => 'error', 'message' => $message]);
} catch (RuntimeException $error) {
    $schemaMissing = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:')
        || str_starts_with($error->getMessage(), 'STARTPARTNER_GATE3_SCHEMA_MISSING:');
    $statusCode = $schemaMissing ? 503 : 404;
    be_json_response($statusCode, [
        'status' => 'error',
        'message' => $statusCode === 503 ? 'Startpartner schema is not ready.' : $error->getMessage(),
        'error_message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Startpartner action could not be applied.',
        'error_message' => $error->getMessage(),
    ]);
}
