<?php
declare(strict_types=1);
/* === BEGIN FILE: api/push/test.php | Zweck: sendet fuer die interne Inbox-Seite eine manuelle Test-Pushnachricht; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';
require __DIR__ . '/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

try {
    $result = be_push_notify_inbox(
        be_db(),
        'manual_test',
        'inbox-push-test-' . gmdate('YmdHis'),
        true
    );

    be_json_response(200, [
        'status' => 'ok',
        'data' => $result,
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Push test failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/push/test.php === */
