<?php
declare(strict_types=1);
/* === BEGIN FILE: api/health.php | Zweck: finaler Health-Endpoint fuer PHP, private Config und Datenbankverbindung; Umfang: komplette Datei === */

require __DIR__ . '/_bootstrap.php';

$checks = [
    'config' => false,
    'database' => false,
];

try {
    $config = be_get_config();
    $checks['config'] = true;

    be_db();
    $checks['database'] = true;

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
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
        'message' => $message,
        'timestamp_utc' => gmdate('c'),
    ]);
}

/* === END FILE: api/health.php === */
