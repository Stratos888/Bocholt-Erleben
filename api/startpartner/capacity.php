<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate2_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    be_json_response(200, [
        'status' => 'ok',
        'data' => be_startpartner_gate2_capacity(be_db()),
    ]);
} catch (RuntimeException $error) {
    $statusCode = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:') ? 503 : 500;
    be_json_response($statusCode, [
        'status' => 'error',
        'message' => $statusCode === 503 ? 'Startpartner schema is not ready.' : 'Capacity could not be loaded.',
        'error_message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Capacity could not be loaded.',
        'error_message' => $error->getMessage(),
    ]);
}
