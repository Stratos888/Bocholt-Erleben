<?php
declare(strict_types=1);
/* === BEGIN FILE: api/push/notify-inbox.php | Zweck: interner Endpoint fuer einfache Pushmeldung bei neuen Inbox-Elementen; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';
require __DIR__ . '/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

try {
    $config = be_push_config();
    $expectedSecret = trim((string)($config['secret'] ?? ''));
    $submittedSecret = be_request_header('X-BE-Push-Secret');

    if ($expectedSecret === '' || $submittedSecret === '' || !hash_equals($expectedSecret, $submittedSecret)) {
        be_json_response(401, [
            'status' => 'error',
            'message' => 'Push notification access denied.',
        ]);
    }

    $input = be_push_json_input();
    $sourceType = trim((string)($input['source_type'] ?? 'external'));
    $sourceKey = trim((string)($input['source_key'] ?? ''));

    if ($sourceKey === '') {
        throw new InvalidArgumentException('source_key is required.');
    }

    $result = be_push_notify_inbox(be_db(), $sourceType, $sourceKey, false);

    be_json_response(200, [
        'status' => 'ok',
        'data' => $result,
    ]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Inbox push notification failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/push/notify-inbox.php === */
