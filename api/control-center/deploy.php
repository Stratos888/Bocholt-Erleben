<?php
declare(strict_types=1);

require_once __DIR__ . '/_writeback.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $token = be_cc_inbox_token();
    $result = be_cc_jsonp_request(['action' => 'deploy', 'token' => $token]);
    if (empty($result['ok'])) {
        throw new RuntimeException(trim((string)($result['error'] ?? $result['detail'] ?? 'deploy_failed')));
    }
    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'started' => true,
            'message' => 'Deployment wurde gestartet.',
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(409, [
        'status' => 'error',
        'message' => 'Deployment konnte nicht gestartet werden.',
        'error_message' => $error->getMessage(),
    ]);
}
