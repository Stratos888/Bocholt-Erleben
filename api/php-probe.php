<?php
declare(strict_types=1);
/* === BEGIN FILE: api/php-probe.php | Zweck: prueft gezielt, ob _bootstrap.php existiert, lesbar ist und sich laden laesst; Umfang: komplette Datei === */

header('Content-Type: application/json; charset=utf-8');

$bootstrapPath = __DIR__ . '/_bootstrap.php';

$result = [
    'bootstrap_path' => $bootstrapPath,
    'bootstrap_exists' => is_file($bootstrapPath),
    'bootstrap_readable' => is_readable($bootstrapPath),
    'require_bootstrap' => false,
];

try {
    require $bootstrapPath;
    $result['require_bootstrap'] = true;
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    $result['error_class'] = get_class($e);
    $result['error_message'] = $e->getMessage();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* === END FILE: api/php-probe.php === */
