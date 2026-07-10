<?php
declare(strict_types=1);

require __DIR__ . '/_domain.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }

    $caseId = trim((string)($input['case_id'] ?? ''));
    $action = trim((string)($input['action'] ?? ''));
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

    if ($caseId === '' || $action === '') {
        throw new InvalidArgumentException('Case and action are required.');
    }

    be_json_response(200, [
        'status' => 'ok',
        'data' => be_cc_apply_action($caseId, $action, $payload),
    ]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(404, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Die Aktion konnte nicht ausgeführt werden.',
        'error_message' => $error->getMessage(),
    ]);
}
