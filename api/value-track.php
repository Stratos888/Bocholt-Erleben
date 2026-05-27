<?php
declare(strict_types=1);
/* === BEGIN FILE: api/value-track.php | Zweck: aggregiert anonyme Nutzwert-Events für das interne Mehrwert-Dashboard; Umfang: komplette Datei === */

require __DIR__ . '/_bootstrap.php';

function be_value_metrics_same_origin_allowed(): bool
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return true;
    }

    $originHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?: ''));
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;

    return $originHost !== '' && $requestHost !== '' && $originHost === $requestHost;
}

function be_value_metrics_clean_key(string $value, int $maxLength = 64): string
{
    $clean = strtolower(trim($value));
    $clean = preg_replace('/[^a-z0-9_\-]+/', '_', $clean) ?? '';
    $clean = trim($clean, '_-');
    return substr($clean, 0, $maxLength);
}

function be_value_metrics_clean_text(string $value, int $maxLength = 255): string
{
    $clean = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($clean === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($clean, 0, $maxLength, 'UTF-8');
    }

    return substr($clean, 0, $maxLength);
}

function be_value_metrics_clean_url(string $value, int $maxLength = 1024): string
{
    $clean = be_value_metrics_clean_text($value, $maxLength);
    if ($clean === '') {
        return '';
    }

    if (!filter_var($clean, FILTER_VALIDATE_URL)) {
        return '';
    }

    return $clean;
}

function be_value_metrics_normalize_path(string $value): string
{
    $clean = be_value_metrics_clean_text($value, 255);
    if ($clean === '') {
        return '';
    }

    if ($clean[0] !== '/') {
        return '';
    }

    return $clean;
}

/* === BEGIN BLOCK: VALUE_METRICS_REPORTING_TARGET_SCHEMA_V1 | Zweck: erweitert Nutzwert-Metriken um optionale Reporting-Ziele für Anbieter-/Location-Auswertungen; Umfang: ersetzt Schema-Helfer für value_metric_daily === */
function be_value_metrics_table_column_exists(PDO $pdo, string $columnName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "value_metric_daily"
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([':column_name' => $columnName]);

    return (int)($statement->fetch()['total'] ?? 0) > 0;
}

function be_value_metrics_table_index_exists(PDO $pdo, string $indexName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "value_metric_daily"
           AND INDEX_NAME = :index_name'
    );
    $statement->execute([':index_name' => $indexName]);

    return (int)($statement->fetch()['total'] ?? 0) > 0;
}

function be_value_metrics_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS value_metric_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_date DATE NOT NULL,
    metric_key VARCHAR(64) NOT NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT '',
    entity_id VARCHAR(191) NOT NULL DEFAULT '',
    entity_title VARCHAR(255) NULL,
    destination_url VARCHAR(1024) NULL,
    reporting_target_type VARCHAR(40) NOT NULL DEFAULT '',
    reporting_target_id VARCHAR(191) NOT NULL DEFAULT '',
    reporting_target_title VARCHAR(255) NULL,
    page_path VARCHAR(255) NULL,
    bucket_hash CHAR(64) NOT NULL,
    count_value INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_value_metric_daily_bucket (bucket_hash),
    KEY idx_value_metric_daily_date_key (metric_date, metric_key),
    KEY idx_value_metric_daily_entity (metric_date, entity_type, entity_id),
    KEY idx_value_metric_daily_reporting_target (metric_date, reporting_target_type, reporting_target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    if (!be_value_metrics_table_column_exists($pdo, 'reporting_target_type')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD COLUMN reporting_target_type VARCHAR(40) NOT NULL DEFAULT "" AFTER destination_url');
    }
    if (!be_value_metrics_table_column_exists($pdo, 'reporting_target_id')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD COLUMN reporting_target_id VARCHAR(191) NOT NULL DEFAULT "" AFTER reporting_target_type');
    }
    if (!be_value_metrics_table_column_exists($pdo, 'reporting_target_title')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD COLUMN reporting_target_title VARCHAR(255) NULL AFTER reporting_target_id');
    }
    if (!be_value_metrics_table_index_exists($pdo, 'idx_value_metric_daily_reporting_target')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD KEY idx_value_metric_daily_reporting_target (metric_date, reporting_target_type, reporting_target_id)');
    }
}
/* === END BLOCK: VALUE_METRICS_REPORTING_TARGET_SCHEMA_V1 === */

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

if (!be_value_metrics_same_origin_allowed()) {
    be_json_response(403, [
        'status' => 'error',
        'message' => 'Origin not allowed.',
    ]);
}

/* === BEGIN BLOCK: VALUE_METRICS_SERVER_OPTOUT_V1 | Zweck: ignoriert eigene Geräte mit Opt-out-Cookie, damit interne Testklicks nicht in Nutzwert-Metriken landen; Umfang: ergänzt serverseitigen Guard vor Payload-Verarbeitung === */
if (trim((string)($_COOKIE['be_value_metrics_opt_out'] ?? '')) === '1') {
    be_json_response(200, [
        'status' => 'ignored',
        'reason' => 'opt_out',
    ]);
}
/* === END BLOCK: VALUE_METRICS_SERVER_OPTOUT_V1 === */

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    be_json_response(400, [
        'status' => 'error',
        'message' => 'Invalid JSON payload.',
    ]);
}

$allowedMetricKeys = [
    'website_click',
    'maps_click',
    'location_click',
    'organizer_cta_click',
    'event_detail_view',
    'activity_detail_view',
];

$metricKey = be_value_metrics_clean_key((string)($payload['metric_key'] ?? ''));
if (!in_array($metricKey, $allowedMetricKeys, true)) {
    be_json_response(400, [
        'status' => 'error',
        'message' => 'Metric key is not allowed.',
    ]);
}

$entityType = be_value_metrics_clean_key((string)($payload['entity_type'] ?? ''), 40);
$entityId = be_value_metrics_clean_text((string)($payload['entity_id'] ?? ''), 191);
$entityTitle = be_value_metrics_clean_text((string)($payload['entity_title'] ?? ''), 255);
$destinationUrl = be_value_metrics_clean_url((string)($payload['destination_url'] ?? ''), 1024);
$reportingTargetType = be_value_metrics_clean_key((string)($payload['reporting_target_type'] ?? ''), 40);
$reportingTargetId = be_value_metrics_clean_text((string)($payload['reporting_target_id'] ?? ''), 191);
$reportingTargetTitle = be_value_metrics_clean_text((string)($payload['reporting_target_title'] ?? ''), 255);
$pagePath = be_value_metrics_normalize_path((string)($payload['page_path'] ?? ''));
$metricDate = gmdate('Y-m-d');

$bucketHash = hash('sha256', implode('|', [
    $metricDate,
    $metricKey,
    $entityType,
    $entityId,
    $destinationUrl,
    $reportingTargetType,
    $reportingTargetId,
    $pagePath,
]));

try {
    $pdo = be_db();
    be_value_metrics_schema($pdo);

    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO value_metric_daily (
    metric_date,
    metric_key,
    entity_type,
    entity_id,
    entity_title,
    destination_url,
    reporting_target_type,
    reporting_target_id,
    reporting_target_title,
    page_path,
    bucket_hash,
    count_value
) VALUES (
    :metric_date,
    :metric_key,
    :entity_type,
    :entity_id,
    :entity_title,
    :destination_url,
    :reporting_target_type,
    :reporting_target_id,
    :reporting_target_title,
    :page_path,
    :bucket_hash,
    1
)
ON DUPLICATE KEY UPDATE
    count_value = count_value + 1,
    entity_title = VALUES(entity_title),
    reporting_target_title = VALUES(reporting_target_title),
    updated_at = CURRENT_TIMESTAMP
SQL);

    $statement->execute([
        ':metric_date' => $metricDate,
        ':metric_key' => $metricKey,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':entity_title' => $entityTitle !== '' ? $entityTitle : null,
        ':destination_url' => $destinationUrl !== '' ? $destinationUrl : null,
        ':reporting_target_type' => $reportingTargetType,
        ':reporting_target_id' => $reportingTargetId,
        ':reporting_target_title' => $reportingTargetTitle !== '' ? $reportingTargetTitle : null,
        ':page_path' => $pagePath !== '' ? $pagePath : null,
        ':bucket_hash' => $bucketHash,
    ]);

    be_json_response(200, [
        'status' => 'ok',
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Value metric could not be stored.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/value-track.php === */
