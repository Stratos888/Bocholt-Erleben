<?php
declare(strict_types=1);

require_once __DIR__ . '/_review_communication.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');
    $candidateId = trim((string)($input['candidate_id'] ?? ''));
    if ($candidateId === '') throw new InvalidArgumentException('candidate_id is required.');
    $result = be_startpartner_review_communication_send(be_db(), $candidateId, $input);
    be_json_response(200, ['status' => 'ok', 'data' => $result]);
} catch (BeStartpartnerConflictException $error) {
    be_json_response(409, ['status' => 'error', 'code' => 'STARTPARTNER_CONFLICT', 'message' => 'Zwischenzeitlich geändert.', 'current' => $error->currentState]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Startpartner communication could not be applied.']);
}
