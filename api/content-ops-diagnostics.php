<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-diagnostics.php | Zweck: temporaere, secret-freie Diagnose ob der aktive Staging-PHP-Endpunkt die private Config und den Content-Ops-Ingest-Token sieht; Umfang: nur sichere Booleans, keine Secrets === */

require __DIR__ . '/_bootstrap.php';

function be_content_ops_diag_tail(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $parts = array_values(array_filter(explode('/', $normalized), static fn($part) => $part !== ''));
    $tail = array_slice($parts, -4);
    return '/' . implode('/', $tail);
}

$configPath = __DIR__ . '/_config.php';
$realConfigPath = realpath($configPath) ?: $configPath;

$result = [
    'status' => 'ok',
    'generated_at_utc' => gmdate('c'),
    'diagnostic' => 'content_ops_config_visibility',
    'script_dir_tail' => be_content_ops_diag_tail(__DIR__),
    'config_file_tail' => be_content_ops_diag_tail($realConfigPath),
    'config_file_exists' => is_file($configPath),
    'config_file_readable' => is_readable($configPath),
    'config_file_size_bytes' => is_file($configPath) ? filesize($configPath) : null,
    'config_file_mtime_utc' => is_file($configPath) ? gmdate('c', filemtime($configPath)) : null,
    'config_loaded' => false,
    'config_is_array' => false,
    'app_env' => null,
    'content_ops_block_exists' => false,
    'ingest_token_configured' => false,
    'ingest_token_source' => 'missing',
];

try {
    $config = be_get_config();
    $result['config_loaded'] = true;
    $result['config_is_array'] = is_array($config);

    if (is_array($config)) {
        $result['app_env'] = isset($config['app_env']) ? (string)$config['app_env'] : null;
        $contentOps = $config['content_ops'] ?? null;
        $result['content_ops_block_exists'] = is_array($contentOps);

        if (is_array($contentOps) && trim((string)($contentOps['ingest_token'] ?? '')) !== '') {
            $result['ingest_token_configured'] = true;
            $result['ingest_token_source'] = 'config.content_ops.ingest_token';
        } elseif (trim((string)($config['content_ops_ingest_token'] ?? '')) !== '') {
            $result['ingest_token_configured'] = true;
            $result['ingest_token_source'] = 'config.content_ops_ingest_token';
        } elseif (trim((string)(getenv('CONTENT_OPS_INGEST_TOKEN') ?: getenv('BE_CONTENT_OPS_INGEST_TOKEN') ?: '')) !== '') {
            $result['ingest_token_configured'] = true;
            $result['ingest_token_source'] = 'environment';
        }
    }
} catch (Throwable $error) {
    $result['status'] = 'error';
    $result['config_error_class'] = get_class($error);
    $result['config_error_message'] = be_should_expose_diagnostics() ? $error->getMessage() : 'Config could not be loaded.';
}

be_json_response($result['status'] === 'ok' ? 200 : 500, $result);

/* === END FILE: api/content-ops-diagnostics.php === */
