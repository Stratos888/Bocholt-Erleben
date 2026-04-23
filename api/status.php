<?php
declare(strict_types=1);
/* === BEGIN FILE: api/status.php | Zweck: prueft minimal und oeffentlich, ob PHP, private Config und Datenbankverbindung im Backend funktionieren; Umfang: komplette Datei === */

require __DIR__ . '/_bootstrap.php';

$checks = [
    'config' => false,
    'database' => false,
];

try {
    be_get_config();
    $checks['config'] = true;

    be_db();
    $checks['database'] = true;

    $config = be_get_config();

    be_json_response(200, [
        'status' => 'ok',
        'app_env' => (string)($config['app_env'] ?? 'unknown'),
        'checks' => $checks,
        'timestamp_utc' => gmdate('c'),
    ]);
} catch (Throwable $error) {
    $message = $checks['config']
        ? 'Database connection failed.'
        : 'Private config missing or invalid.';

    be_json_response(500, [
        'status' => 'error',
        'checks' => $checks,
        'message' => $message,
        'timestamp_utc' => gmdate('c'),
    ]);
}
/* === END FILE: api/status.php === */
