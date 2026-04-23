<?php
declare(strict_types=1);
/* === BEGIN FILE: api/_bootstrap.php | Zweck: zentraler Backend-Bootstrap fuer private Config, JSON-Antworten und PDO-DB-Zugriff; Umfang: komplette Datei === */

function be_base_path(string ...$segments): string
{
    $path = __DIR__;

    foreach ($segments as $segment) {
        $path .= DIRECTORY_SEPARATOR . ltrim($segment, DIRECTORY_SEPARATOR);
    }

    return $path;
}

function be_get_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $configPath = be_base_path('_config.php');
    if (!is_file($configPath)) {
        throw new RuntimeException('Private app config is missing.');
    }

    $loaded = require $configPath;
    if (!is_array($loaded)) {
        throw new RuntimeException('Private app config is invalid.');
    }

    $config = $loaded;
    return $config;
}

function be_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = be_get_config();
    $db = $config['db'] ?? null;

    if (!is_array($db)) {
        throw new RuntimeException('Database config is missing.');
    }

    $host = trim((string)($db['host'] ?? ''));
    $port = (int)($db['port'] ?? 3306);
    $name = trim((string)($db['name'] ?? ''));
    $user = trim((string)($db['user'] ?? ''));
    $password = (string)($db['password'] ?? '');
    $charset = trim((string)($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Database credentials are incomplete.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $name,
        $charset
    );

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function be_json_response(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
/* === END FILE: api/_bootstrap.php === */
