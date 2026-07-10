<?php
declare(strict_types=1);

require __DIR__ . '/_submission_source.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'inbox' => be_cc_sync_inbox_feed(),
            'submissions' => be_cc_sync_submissions(),
            'content_audit' => be_cc_sync_content_audit(),
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Die Quellsysteme konnten nicht synchronisiert werden.',
        'error_message' => $error->getMessage(),
    ]);
}
