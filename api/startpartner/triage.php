<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';

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
    $toStatus = trim((string)($input['status'] ?? ''));
    if ($candidateId === '' || $toStatus === '') {
        throw new InvalidArgumentException('candidate_id and status are required.');
    }

    $candidate = be_startpartner_triage_candidate(
        be_db(),
        $candidateId,
        $toStatus,
        isset($input['reason']) ? (string)$input['reason'] : null,
        'review-access'
    );
    be_json_response(200, ['status' => 'ok', 'data' => $candidate]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    $statusCode = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:') ? 503 : 404;
    be_json_response($statusCode, [
        'status' => 'error',
        'message' => $statusCode === 503 ? 'Startpartner schema is not ready.' : $error->getMessage(),
        'error_message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'The Startpartner candidate could not be triaged.',
        'error_message' => $error->getMessage(),
    ]);
}
