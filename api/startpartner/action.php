<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_communication.php';

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
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
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
