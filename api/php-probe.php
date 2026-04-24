<?php
declare(strict_types=1);
/* === BEGIN FILE: api/php-probe.php | Zweck: prueft nach erfolgreichem Bootstrap gezielt private Config und DB-Verbindung; Umfang: komplette Datei === */

require __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'config' => false,
    'database' => false,
];

try {
    $config = be_get_config();
    $checks['config'] = true;

    be_db();
    $checks['database'] = true;

    echo json_encode([
        'status' => 'ok',
        'checks' => $checks,
        'app_env' => $config['app_env'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'checks' => $checks,
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* === END FILE: api/php-probe.php === */
