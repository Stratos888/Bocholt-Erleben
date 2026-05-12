<?php
declare(strict_types=1);
/* === BEGIN FILE: api/push/config.php | Zweck: liefert die oeffentliche VAPID-Konfiguration fuer interne Inbox-Push-Registrierung; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';
require __DIR__ . '/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

$config = be_push_config();
if (!be_push_is_configured($config)) {
    be_json_response(503, [
        'status' => 'error',
        'message' => 'Push is not configured.',
    ]);
}

be_json_response(200, [
    'status' => 'ok',
    'data' => [
        'public_key' => $config['public_key'],
    ],
]);

/* === END FILE: api/push/config.php === */
