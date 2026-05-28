<?php
declare(strict_types=1);
/* === BEGIN FILE: intern/seo-dashboard/index.php | Zweck: internes, passwortgeschuetztes SEO-/Mehrwert-Dashboard fuer Betreiber; Umfang: komplette Datei === */

require __DIR__ . '/../../api/_bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_name('BE_INTERNAL_SEO_DASHBOARD');
session_start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errorMessage = '';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: /intern/seo-dashboard/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = trim((string)($_POST['password'] ?? ''));

    try {
        $expectedPassword = be_review_password();
        if ($submittedPassword !== '' && hash_equals($expectedPassword, $submittedPassword)) {
            $_SESSION['be_internal_seo_dashboard_unlocked'] = true;
            header('Location: /intern/seo-dashboard/');
            exit;
        }

        $errorMessage = 'Passwort nicht korrekt.';
    } catch (Throwable $error) {
        http_response_code(503);
        $errorMessage = 'Review-Passwort ist serverseitig nicht konfiguriert.';
    }
}

$isUnlocked = (bool)($_SESSION['be_internal_seo_dashboard_unlocked'] ?? false);
$checkedAt = date('d.m.Y H:i');

/* === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_VALUE_METRICS_DB_V2 | Zweck: liest automatisierte First-Party-Nutzwert-Metriken für aktuellen und vorherigen 28-Tage-Zeitraum; Umfang: ersetzt serverseitige Aggregation vor dem HTML === */
/* === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_REPORTING_TARGET_SCHEMA_V1 | Zweck: hält Dashboard-Schema mit value-track.php synchron und ergänzt Reporting-Ziel-Spalten bei bestehenden Tabellen; Umfang: ersetzt Schema-Helfer === */
function be_seo_dashboard_value_metrics_column_exists(PDO $pdo, string $columnName): bool
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

function be_seo_dashboard_value_metrics_index_exists(PDO $pdo, string $indexName): bool
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

function be_seo_dashboard_value_metrics_schema(PDO $pdo): void
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

    if (!be_seo_dashboard_value_metrics_column_exists($pdo, 'reporting_target_type')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD COLUMN reporting_target_type VARCHAR(40) NOT NULL DEFAULT "" AFTER destination_url');
    }
    if (!be_seo_dashboard_value_metrics_column_exists($pdo, 'reporting_target_id')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD COLUMN reporting_target_id VARCHAR(191) NOT NULL DEFAULT "" AFTER reporting_target_type');
    }
    if (!be_seo_dashboard_value_metrics_column_exists($pdo, 'reporting_target_title')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD COLUMN reporting_target_title VARCHAR(255) NULL AFTER reporting_target_id');
    }
    if (!be_seo_dashboard_value_metrics_index_exists($pdo, 'idx_value_metric_daily_reporting_target')) {
        $pdo->exec('ALTER TABLE value_metric_daily ADD KEY idx_value_metric_daily_reporting_target (metric_date, reporting_target_type, reporting_target_id)');
    }
}
/* === END BLOCK: INTERNAL_SEO_DASHBOARD_REPORTING_TARGET_SCHEMA_V1 === */

function be_seo_dashboard_periods(): array
{
    $currentEnd = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $currentStart = $currentEnd->modify('-27 days');
    $previousEnd = $currentStart->modify('-1 day');
    $previousStart = $previousEnd->modify('-27 days');

    return [
        'current' => [
            'start' => $currentStart,
            'end' => $currentEnd,
        ],
        'previous' => [
            'start' => $previousStart,
            'end' => $previousEnd,
        ],
    ];
}

function be_seo_dashboard_empty_metrics(): array
{
    return [
        'website_clicks' => 0,
        'maps_clicks' => 0,
        'location_clicks' => 0,
        'organizer_cta_clicks' => 0,
        'detail_views' => 0,
        'performing_events' => 0,
        'performing_locations' => 0,
    ];
}

function be_seo_dashboard_empty_value_metrics(string $status = 'not_configured', string $message = ''): array
{
    $periods = be_seo_dashboard_periods();

    return [
        'status' => $status,
        'generated_at' => gmdate('c'),
        'period' => [
            'start_date' => $periods['current']['start']->format('Y-m-d'),
            'end_date' => $periods['current']['end']->format('Y-m-d'),
            'days' => 28,
        ],
        'previous_period' => [
            'start_date' => $periods['previous']['start']->format('Y-m-d'),
            'end_date' => $periods['previous']['end']->format('Y-m-d'),
            'days' => 28,
        ],
        'metrics' => be_seo_dashboard_empty_metrics(),
        'previous_metrics' => be_seo_dashboard_empty_metrics(),
        'reporting_targets' => [],
        'previous_reporting_targets' => [],
        'configured_reporting_targets' => [],
        'message' => $message,
    ];
}

function be_seo_dashboard_aggregate_value_metrics(PDO $pdo, string $startDate, string $endDate): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT metric_key, COALESCE(SUM(count_value), 0) AS total_count
FROM value_metric_daily
WHERE metric_date BETWEEN :start_date AND :end_date
GROUP BY metric_key
SQL);
    $statement->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $totals = [];
    foreach ($statement->fetchAll() as $row) {
        $totals[(string)$row['metric_key']] = (int)$row['total_count'];
    }

    $eventsStatement = $pdo->prepare(<<<'SQL'
SELECT COUNT(*) AS total_count
FROM (
    SELECT entity_id
    FROM value_metric_daily
    WHERE metric_date BETWEEN :start_date AND :end_date
      AND entity_type = 'event'
      AND entity_id <> ''
      AND metric_key IN ('event_detail_view', 'website_click', 'maps_click', 'location_click')
    GROUP BY entity_id
    HAVING SUM(count_value) > 0
) AS event_metrics
SQL);
    $eventsStatement->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);
    $performingEvents = (int)($eventsStatement->fetch()['total_count'] ?? 0);

    $locationsStatement = $pdo->prepare(<<<'SQL'
SELECT COUNT(*) AS total_count
FROM (
    SELECT COALESCE(NULLIF(destination_url, ''), NULLIF(entity_title, ''), NULLIF(entity_id, '')) AS location_key
    FROM value_metric_daily
    WHERE metric_date BETWEEN :start_date AND :end_date
      AND metric_key IN ('website_click', 'maps_click', 'location_click')
    GROUP BY location_key
    HAVING location_key IS NOT NULL AND location_key <> '' AND SUM(count_value) > 0
) AS location_metrics
SQL);
    $locationsStatement->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);
    $performingLocations = (int)($locationsStatement->fetch()['total_count'] ?? 0);

    return [
        'website_clicks' => (int)($totals['website_click'] ?? 0),
        'maps_clicks' => (int)($totals['maps_click'] ?? 0),
        'location_clicks' => (int)($totals['location_click'] ?? 0),
        'organizer_cta_clicks' => (int)($totals['organizer_cta_click'] ?? 0),
        'detail_views' => (int)(($totals['event_detail_view'] ?? 0) + ($totals['activity_detail_view'] ?? 0)),
        'performing_events' => $performingEvents,
        'performing_locations' => $performingLocations,
    ];
}

/* === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_REPORTING_TARGET_AGGREGATION_V1 | Zweck: aggregiert Nutzwertdaten nach explizit zugeordneten Anbieter-/Location-Zielen und weist unklare Werte als nicht zugeordnet aus; Umfang: neue additive Auswertung === */
function be_seo_dashboard_aggregate_reporting_targets(PDO $pdo, string $startDate, string $endDate): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT
    CASE
        WHEN reporting_target_type <> '' AND reporting_target_id <> '' THEN reporting_target_type
        ELSE 'unassigned'
    END AS target_type,
    CASE
        WHEN reporting_target_type <> '' AND reporting_target_id <> '' THEN reporting_target_id
        ELSE 'unassigned'
    END AS target_id,
    CASE
        WHEN reporting_target_type <> '' AND reporting_target_id <> '' THEN COALESCE(NULLIF(reporting_target_title, ''), reporting_target_id)
        ELSE 'nicht zugeordnet'
    END AS target_title,
    COALESCE(SUM(CASE WHEN metric_key IN ('event_detail_view', 'activity_detail_view') THEN count_value ELSE 0 END), 0) AS detail_views,
    COALESCE(SUM(CASE WHEN metric_key = 'website_click' THEN count_value ELSE 0 END), 0) AS website_clicks,
    COALESCE(SUM(CASE WHEN metric_key = 'maps_click' THEN count_value ELSE 0 END), 0) AS maps_clicks,
    COALESCE(SUM(CASE WHEN metric_key = 'location_click' THEN count_value ELSE 0 END), 0) AS location_clicks,
    COALESCE(SUM(CASE WHEN metric_key = 'organizer_cta_click' THEN count_value ELSE 0 END), 0) AS organizer_cta_clicks,
    COALESCE(SUM(count_value), 0) AS total_interactions,
    COUNT(DISTINCT CASE WHEN entity_type <> '' AND entity_id <> '' THEN CONCAT(entity_type, ':', entity_id) ELSE NULL END) AS item_count
FROM value_metric_daily
WHERE metric_date BETWEEN :start_date AND :end_date
GROUP BY target_type, target_id, target_title
HAVING total_interactions > 0
ORDER BY
    CASE WHEN target_type = 'unassigned' THEN 1 ELSE 0 END ASC,
    total_interactions DESC,
    target_title ASC
LIMIT 30
SQL);
    $statement->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $rows = $statement->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'target_type' => (string)($row['target_type'] ?? 'unassigned'),
            'target_id' => (string)($row['target_id'] ?? 'unassigned'),
            'target_title' => (string)($row['target_title'] ?? 'nicht zugeordnet'),
            'detail_views' => (int)($row['detail_views'] ?? 0),
            'website_clicks' => (int)($row['website_clicks'] ?? 0),
            'maps_clicks' => (int)($row['maps_clicks'] ?? 0),
            'location_clicks' => (int)($row['location_clicks'] ?? 0),
            'organizer_cta_clicks' => (int)($row['organizer_cta_clicks'] ?? 0),
            'total_interactions' => (int)($row['total_interactions'] ?? 0),
            'item_count' => (int)($row['item_count'] ?? 0),
        ];
    }, $rows);
}
/* === END BLOCK: INTERNAL_SEO_DASHBOARD_REPORTING_TARGET_AGGREGATION_V1 === */

/* === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_CONFIGURED_REPORTING_TARGETS_V1 | Zweck: liest konfigurierte Activity-Reporting-Ziele auch ohne aktuelle Messwerte für den Feedbackbericht; Umfang: additive Helfer === */
function be_seo_dashboard_read_configured_reporting_targets(): array
{
    $path = __DIR__ . '/../../data/offers.json';
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    $offers = is_array($decoded['offers'] ?? null) ? $decoded['offers'] : [];
    $targets = [];

    foreach ($offers as $offer) {
        if (!is_array($offer)) {
            continue;
        }

        $target = $offer['reporting_target'] ?? null;
        if (!is_array($target)) {
            continue;
        }

        $type = trim((string)($target['type'] ?? ''));
        $id = trim((string)($target['id'] ?? ''));
        $title = trim((string)($target['title'] ?? ''));

        if ($type === '' || $id === '') {
            continue;
        }

        $key = $type . ':' . $id;
        if (!isset($targets[$key])) {
            $targets[$key] = [
                'target_type' => $type,
                'target_id' => $id,
                'target_title' => $title !== '' ? $title : $id,
                'detail_views' => 0,
                'website_clicks' => 0,
                'maps_clicks' => 0,
                'location_clicks' => 0,
                'organizer_cta_clicks' => 0,
                'total_interactions' => 0,
                'item_count' => 0,
                'item_titles' => [],
            ];
        }

        $targets[$key]['item_count']++;
        $offerTitle = trim((string)($offer['title'] ?? ''));
        if ($offerTitle !== '') {
            $targets[$key]['item_titles'][] = $offerTitle;
        }
    }

    uasort($targets, static function (array $left, array $right): int {
        return strcasecmp((string)$left['target_title'], (string)$right['target_title']);
    });

    return array_values($targets);
}
/* === END BLOCK: INTERNAL_SEO_DASHBOARD_CONFIGURED_REPORTING_TARGETS_V1 === */

function be_seo_dashboard_read_value_metrics(): array
{
    $valueMetrics = be_seo_dashboard_empty_value_metrics('ok');

    try {
        $pdo = be_db();
        be_seo_dashboard_value_metrics_schema($pdo);

        $valueMetrics['metrics'] = be_seo_dashboard_aggregate_value_metrics(
            $pdo,
            (string)$valueMetrics['period']['start_date'],
            (string)$valueMetrics['period']['end_date']
        );

        $valueMetrics['previous_metrics'] = be_seo_dashboard_aggregate_value_metrics(
            $pdo,
            (string)$valueMetrics['previous_period']['start_date'],
            (string)$valueMetrics['previous_period']['end_date']
        );

        $valueMetrics['reporting_targets'] = be_seo_dashboard_aggregate_reporting_targets(
            $pdo,
            (string)$valueMetrics['period']['start_date'],
            (string)$valueMetrics['period']['end_date']
        );

        $valueMetrics['previous_reporting_targets'] = be_seo_dashboard_aggregate_reporting_targets(
            $pdo,
            (string)$valueMetrics['previous_period']['start_date'],
            (string)$valueMetrics['previous_period']['end_date']
        );

        $valueMetrics['configured_reporting_targets'] = be_seo_dashboard_read_configured_reporting_targets();

        return $valueMetrics;
    } catch (Throwable $error) {
        return be_seo_dashboard_empty_value_metrics(
            'error',
            be_should_expose_diagnostics() ? $error->getMessage() : 'Nutzwert-Metriken konnten nicht gelesen werden.'
        );
    }
}

$valueMetrics = $isUnlocked
    ? be_seo_dashboard_read_value_metrics()
    : be_seo_dashboard_empty_value_metrics();
/* === END BLOCK: INTERNAL_SEO_DASHBOARD_VALUE_METRICS_DB_V2 === */
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow,noarchive" />
  <title>Internes SEO-Dashboard · Bocholt erleben</title>
  <style>
    /* === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_MOBILE_AKQUISE_CSS_V32 | Zweck: kompakte mobile Akquise-Ansicht mit Tooltip-Buttons und eingeklappten Technikdetails; Umfang: ersetzt kompletten Inline-Style der internen SEO-Seite === */
    :root {
      color-scheme: light;
      --bg: #f6f2ea;
      --surface: #fffaf2;
      --surface-2: #ffffff;
      --text: #221b14;
      --muted: #74685d;
      --line: rgba(46, 34, 24, .14);
      --good: #0f6b3d;
      --warn: #8a5a00;
      --bad: #9b1c1c;
      --soft-good: rgba(15, 107, 61, .10);
      --soft-warn: rgba(138, 90, 0, .12);
      --soft-bad: rgba(155, 28, 28, .10);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      line-height: 1.42;
    }

    a { color: inherit; }
    p { margin: 0; }
    h1 { margin: 0; font-size: clamp(24px, 4vw, 38px); line-height: 1.08; letter-spacing: -.03em; }
    h2 { margin: 0 0 10px; font-size: 18px; line-height: 1.2; }

    .wrap { width: min(1180px, calc(100% - 28px)); margin: 0 auto; padding: 18px 0 36px; }
    .muted { color: var(--muted); }
    .small { font-size: 13px; }
    .eyebrow { margin: 0 0 4px; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }

    .topbar { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-bottom: 14px; }
    .topActions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
    .logout,
    .softButton {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 36px;
      padding: 7px 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: var(--surface);
      color: var(--text);
      text-decoration: none;
      font-size: 13px;
      font-weight: 800;
      cursor: pointer;
    }

    .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 12px; }
    .card { grid-column: span 12; background: var(--surface); border: 1px solid var(--line); border-radius: 20px; padding: 14px; box-shadow: 0 12px 32px rgba(45, 33, 20, .06); }
    .card--third { grid-column: span 4; }
    .card--half { grid-column: span 6; }
    .card--wide { grid-column: span 8; }

    .snapshotGrid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 12px; }
    .statusLine { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    .status { display: inline-flex; align-items: center; gap: 7px; min-height: 27px; padding: 4px 10px; border-radius: 999px; border: 1px solid var(--line); font-size: 13px; font-weight: 800; background: var(--surface-2); }
    .dot { width: 9px; height: 9px; border-radius: 999px; background: var(--muted); }
    .status[data-state="good"] { color: var(--good); background: var(--soft-good); border-color: rgba(15,107,61,.22); }
    .status[data-state="warn"] { color: var(--warn); background: var(--soft-warn); border-color: rgba(138,90,0,.25); }
    .status[data-state="bad"] { color: var(--bad); background: var(--soft-bad); border-color: rgba(155,28,28,.22); }
    .status[data-state="good"] .dot { background: var(--good); }
    .status[data-state="warn"] .dot { background: var(--warn); }
    .status[data-state="bad"] .dot { background: var(--bad); }

    .compactStatusBar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: -2px 0 10px; }
    .compactGuidance { max-width: 860px; margin: 0 0 12px; color: var(--muted); font-size: 13px; }

    .feedbackHeader { display: grid; grid-template-columns: minmax(0, 1fr) minmax(240px, 320px); gap: 12px; align-items: end; }
    .targetSelect { width: 100%; min-height: 40px; padding: 9px 11px; border-radius: 12px; border: 1px solid var(--line); background: var(--surface-2); color: var(--text); font: inherit; font-weight: 750; }
    .feedbackReport { display: grid; gap: 12px; margin-top: 12px; padding: 12px; border: 1px solid var(--line); border-radius: 16px; background: var(--surface-2); }
    .feedbackReportHead { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
    .feedbackReportTitle { margin: 0; font-size: 20px; line-height: 1.15; letter-spacing: -.02em; }
    .feedbackKpiGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .feedbackKpi { padding: 10px; border: 1px solid var(--line); border-radius: 14px; background: rgba(255,250,242,.72); }
    .feedbackKpiLabel { color: var(--muted); font-size: 12px; line-height: 1.2; }
    .feedbackKpiValue { margin-top: 4px; font-size: 22px; font-weight: 900; line-height: 1; letter-spacing: -.03em; }
    .feedbackText { display: grid; gap: 8px; color: var(--text); font-size: 14px; }
    .feedbackEmpty { color: var(--muted); }

    .kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px; }
    .kpiBox { position: relative; min-width: 0; padding: 10px 11px; border: 1px solid var(--line); border-radius: 16px; background: var(--surface-2); }
    .kpiLabel { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--muted); font-size: 12px; line-height: 1.2; }
    .kpiValue { margin-top: 5px; font-size: 23px; font-weight: 900; line-height: 1.05; letter-spacing: -.03em; }
    .kpiTrend { margin-top: 5px; color: var(--muted); font-size: 12px; line-height: 1.25; }

    .infoButton { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 21px; height: 21px; flex: 0 0 auto; border: 1px solid var(--line); border-radius: 999px; background: rgba(255,255,255,.72); color: var(--muted); font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
    .infoButton[data-open="true"] { color: var(--text); background: var(--surface); }
    .infoButton[data-open="true"]::after { content: attr(data-info); position: absolute; right: -4px; top: calc(100% + 8px); z-index: 50; width: min(280px, 76vw); padding: 10px 11px; border: 1px solid var(--line); border-radius: 14px; background: #fff; color: var(--text); box-shadow: 0 18px 42px rgba(45,33,20,.16); font-size: 13px; font-weight: 650; line-height: 1.35; text-align: left; }

    .note { padding: 10px; border-radius: 14px; background: rgba(255,255,255,.56); border: 1px solid var(--line); }
    .proofBox { display: grid; gap: 9px; }
    .reportList { display: grid; gap: 8px; margin-top: 10px; }
    .reportRow { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); gap: 10px; align-items: start; padding: 10px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface-2); }
    .reportRowTitle { font-weight: 900; overflow-wrap: anywhere; }
    .reportRowMeta { margin-top: 3px; color: var(--muted); font-size: 12px; }
    .reportRowValues { display: grid; gap: 4px; color: var(--muted); font-size: 12px; text-align: right; }
    .reportRowValues strong { color: var(--text); }

    .checklist { display: grid; gap: 9px; margin-top: 10px; }
    .check { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface-2); }
    .check code { font-size: 12px; color: var(--muted); overflow-wrap: anywhere; }
    .linkList { display: grid; gap: 8px; margin-top: 10px; }
    .linkItem { display: flex; justify-content: space-between; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface-2); text-decoration: none; }

    details.card { padding: 0; overflow: hidden; }
    details.card > summary { list-style: none; cursor: pointer; padding: 14px; font-weight: 900; }
    details.card > summary::-webkit-details-marker { display: none; }
    details.card > summary::after { content: "anzeigen"; float: right; color: var(--muted); font-size: 12px; font-weight: 800; }
    details.card[open] > summary::after { content: "ausblenden"; }
    details.card > .detailsBody { padding: 0 14px 14px; }

    .login { min-height: 100vh; display: grid; place-items: center; padding: 22px; }
    .loginCard { width: min(440px, 100%); padding: 22px; background: var(--surface); border: 1px solid var(--line); border-radius: 22px; box-shadow: 0 18px 42px rgba(45, 33, 20, .10); }
    .loginCard form { display: grid; gap: 12px; margin-top: 16px; }
    label { display: grid; gap: 5px; font-size: 12px; font-weight: 800; color: var(--muted); }
    input { width: 100%; min-height: 40px; padding: 9px 11px; border-radius: 12px; border: 1px solid var(--line); background: var(--surface-2); font: inherit; color: var(--text); }
    button { font: inherit; }
    .error { color: var(--bad); font-weight: 800; }

    @media (max-width: 860px) {
      .wrap { width: min(100% - 18px, 680px); padding: 10px 0 24px; }
      .topbar { display: grid; gap: 10px; }
      .topActions { justify-content: stretch; }
      .logout,
      .softButton { flex: 1 1 auto; min-height: 34px; padding: 7px 10px; }
      .grid { gap: 10px; }
      .card { padding: 12px; border-radius: 18px; }
      .card--third,
      .card--half,
      .card--wide { grid-column: span 12; }
      .snapshotGrid { grid-template-columns: 1fr; }
      .feedbackHeader { grid-template-columns: 1fr; align-items: start; }
      .feedbackKpiGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .feedbackReportTitle { font-size: 18px; }
      .kpi { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
      .kpiBox { padding: 9px; border-radius: 14px; }
      .kpiValue { font-size: 21px; }
      .kpiLabel,
      .kpiTrend { font-size: 11px; }
      .check,
      .linkItem,
      .reportRow { grid-template-columns: 1fr; align-items: start; }
      .reportRowValues { text-align: left; }
      h1 { font-size: 25px; }
      h2 { font-size: 17px; }
    }
    /* === END BLOCK: INTERNAL_SEO_DASHBOARD_MOBILE_AKQUISE_CSS_V32 === */
  </style>
</head>
<body>
<?php if (!$isUnlocked): ?>
  <main class="login">
    <section class="loginCard" aria-labelledby="login-title">
      <p class="eyebrow">Intern · nicht öffentlich</p>
      <h1 id="login-title">SEO-Dashboard</h1>
      <p class="muted small" style="margin-top:8px;">Diese Seite ist serverseitig geschützt und nutzt dasselbe Review-Passwort wie die interne Kuratier-Inbox.</p>

      <?php if ($errorMessage !== ''): ?>
        <p class="error small" style="margin-top:12px;"><?= h($errorMessage) ?></p>
      <?php endif; ?>

      <form method="post" action="/intern/seo-dashboard/">
        <label>
          Passwort
          <input name="password" type="password" autocomplete="current-password" required autofocus />
        </label>
        <button type="submit">Dashboard öffnen</button>
      </form>
    </section>
  </main>
<?php else: ?>
  <main class="wrap">
    <!-- === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_MOBILE_AKQUISE_LAYOUT_V32 | Zweck: reduziert das Dashboard auf Akquise-Snapshot, kompakte Hauptzahlen, Tooltips, Opt-out und eingeklappte Technik; Umfang: ersetzt kompletten eingeloggten Main-Inhalt bis zum Script-Start === -->
    <header class="topbar">
      <div>
        <p class="eyebrow">Internes Dashboard · Stand <?= h($checkedAt) ?></p>
        <h1>SEO- & Mehrwert-Status</h1>
        <p class="muted small" style="margin-top:7px; max-width:760px;">Kompakte Betreiberansicht für Sichtbarkeit, Akquise-Reife und automatisch gemessenen Mehrwert.</p>
      </div>
      <div class="topActions">
        <button type="button" id="optOutToggle" class="softButton">Eigenes Tracking ausschließen</button>
        <a class="logout" href="/intern/seo-dashboard/?logout=1">Abmelden</a>
      </div>
    </header>

    <div class="compactStatusBar" aria-label="Dashboard-Status">
      <span id="snapshotStatus" class="status" data-state="warn"><span class="dot"></span><span>wird bewertet</span></span>
      <span id="overallStatus" class="status" data-state="warn"><span class="dot"></span><span>Technik wird geprüft</span></span>
      <span id="topOptOutStatus" class="status" data-state="warn"><span class="dot"></span><span>Eigenes Tracking wird geprüft</span></span>
    </div>
    <p class="compactGuidance">Erst aktiv verkaufen, wenn konkrete Website-, Route-/Maps- oder CTA-Klicks sichtbar werden. Der Detailstatus bleibt einklappbar.</p>

    <section class="grid">
      <article class="card">
        <div class="feedbackHeader">
          <div>
            <h2>Location-Feedbackbericht</h2>
            <p class="muted small">Interner, screenshotfähiger Einzelbericht für Akquise und spätere Anbieter-Auswertung. Zeigt nur echte gemessene Interaktionen, keine Besucherzahlen vor Ort.</p>
          </div>
          <label>
            Reporting-Ziel auswählen
            <select id="feedbackTargetSelect" class="targetSelect">
              <option value="">Ziele werden geladen …</option>
            </select>
          </label>
        </div>
        <div id="feedbackReport" class="feedbackReport">
          <p class="feedbackEmpty small">Feedbackbericht wird geladen.</p>
        </div>
      </article>

      <article class="card">
        <h2>Hauptzahlen</h2>
        <p class="muted small">Aktueller 28-Tage-Zeitraum im Vergleich zum vorherigen Zeitraum. Tippe auf ⓘ für die Erklärung einer Kennzahl.</p>

        <div class="kpi">
          <div class="kpiBox">
            <div class="kpiLabel"><span>Sichtbarkeit</span><button type="button" class="infoButton" data-info="Wie oft Bocholt erleben in Google und Bing angezeigt wurde. Das zeigt Reichweite, aber noch keinen echten Besuch." aria-label="Sichtbarkeit erklären">i</button></div>
            <div id="kpiImpressions" class="kpiValue">0</div>
            <div id="kpiImpressionsTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Such-Klicks</span><button type="button" class="infoButton" data-info="Wie oft Nutzer über Google oder Bing auf Bocholt erleben geklickt haben. Das zeigt echtes Suchinteresse." aria-label="Such-Klicks erklären">i</button></div>
            <div id="kpiClicks" class="kpiValue">0</div>
            <div id="kpiClicksTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Nutzwert-Klicks</span><button type="button" class="infoButton" data-info="Summe aus Website-Klicks, Maps-/Route-Klicks, Location-Link-Klicks und Veranstalter-CTA. Das ist der wichtigste Wert für konkreten Mehrwert." aria-label="Nutzwert-Klicks erklären">i</button></div>
            <div id="kpiLocationValue" class="kpiValue">0</div>
            <div id="kpiLocationValueTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Status</span><button type="button" class="infoButton" data-info="Automatische Einschätzung, ob die Zahlen schon für aktive Veranstalter- oder Location-Akquise reichen." aria-label="Status erklären">i</button></div>
            <div id="kpiStage" class="kpiValue">Rot</div>
            <div id="kpiStageTrend" class="kpiTrend">automatisch bewertet</div>
          </div>
        </div>

        <div class="kpi">
          <div class="kpiBox">
            <div class="kpiLabel"><span>Detail-Interesse</span><button type="button" class="infoButton" data-info="Wie oft Nutzer ein Event oder eine Aktivität genauer geöffnet haben. Das zeigt konkretes Interesse am Inhalt." aria-label="Detail-Interesse erklären">i</button></div>
            <div id="kpiDetailViews" class="kpiValue">0</div>
            <div id="kpiDetailViewsTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Website-Klicks</span><button type="button" class="infoButton" data-info="Wie oft Nutzer von Bocholt erleben zu einer externen Website, Ticketseite oder Infoseite geklickt haben." aria-label="Website-Klicks erklären">i</button></div>
            <div id="kpiWebsiteClicks" class="kpiValue">0</div>
            <div id="kpiWebsiteClicksTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Route/Maps</span><button type="button" class="infoButton" data-info="Wie oft Nutzer eine Karte oder Route geöffnet haben. Das ist ein starkes Signal für Besuchsabsicht." aria-label="Route- und Maps-Klicks erklären">i</button></div>
            <div id="kpiMapsClicks" class="kpiValue">0</div>
            <div id="kpiMapsClicksTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Veranstalter-CTA</span><button type="button" class="infoButton" data-info="Wie oft Links wie Event veröffentlichen oder Veranstaltung einreichen geklickt wurden. Das zeigt Interesse von Veranstaltern." aria-label="Veranstalter-CTA erklären">i</button></div>
            <div id="kpiOrganizerCtaClicks" class="kpiValue">0</div>
            <div id="kpiOrganizerCtaClicksTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
        </div>

        <div class="kpi">
          <div class="kpiBox">
            <div class="kpiLabel"><span>performende Events</span><button type="button" class="infoButton" data-info="Wie viele unterschiedliche Events im Zeitraum mindestens eine relevante Interaktion hatten." aria-label="performende Events erklären">i</button></div>
            <div id="kpiPerformingEvents" class="kpiValue">0</div>
            <div id="kpiPerformingEventsTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>performende Ziele</span><button type="button" class="infoButton" data-info="Wie viele unterschiedliche Ziele, Websites oder Locations relevante Klicks erhalten haben." aria-label="performende Ziele erklären">i</button></div>
            <div id="kpiPerformingLocations" class="kpiValue">0</div>
            <div id="kpiPerformingLocationsTrend" class="kpiTrend">vs. vorher: —</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Location-Links</span><button type="button" class="infoButton" data-info="Wie oft ein expliziter Location-Link geklickt wurde. Sekundär, solange Website- und Route-Klicks den Hauptnutzen abbilden." aria-label="Location-Links erklären">i</button></div>
            <div id="kpiLocationClicks" class="kpiValue">0</div>
            <div id="kpiLocationClicksTrend" class="kpiTrend">sekundär</div>
          </div>
          <div class="kpiBox">
            <div class="kpiLabel"><span>Automatik</span><button type="button" class="infoButton" data-info="Search-Daten kommen aus Google/Bing. Nutzwert-Klicks werden anonym im eigenen Backend gezählt." aria-label="Automatik erklären">i</button></div>
            <div id="kpiAutomation" class="kpiValue">aktiv</div>
            <div id="optOutStatus" class="kpiTrend">Eigenes Tracking: aktiv</div>
          </div>
        </div>

        <p id="kpiExplanation" class="note small" style="margin-top:10px;">Noch keine Werte geladen.</p>
      </article>

      <details class="card">
        <summary>Akquise-Gesamtstatus</summary>
        <div class="detailsBody">
          <div class="snapshotGrid">
            <article class="card">
              <h2>Akquise-Snapshot</h2>
              <p id="snapshotPeriod" class="muted small">28-Tage-Auswertung wird geladen.</p>
              <p id="snapshotReason" style="margin-top:10px;">Noch keine Bewertung geladen.</p>
              <p id="snapshotNext" class="note small" style="margin-top:10px;">Nächster Hebel wird berechnet.</p>
              <p id="overallText" class="muted small" style="margin-top:8px;">Technik-Checks sind unten eingeklappt.</p>
            </article>

            <article class="card">
              <h2>Für Screenshot / Akquise</h2>
              <div class="proofBox">
                <p id="proofStatus" class="small"><strong>Status:</strong> wird geladen</p>
                <p id="proofPeriod" class="muted small">Zeitraum wird geladen.</p>
                <p id="proofSummary">Mehrwertnachweis wird geladen.</p>
                <p id="proofTrend" class="muted small">Vergleich wird geladen.</p>
              </div>
            </article>
          </div>
        </div>
      </details>

      <details class="card">
        <summary>Technik, Quellen und Detailwerte</summary>
        <div class="detailsBody">
          <div class="grid">
            <article class="card card--wide">
              <h2>Live-Checks</h2>
              <p class="muted small">Technische Erreichbarkeit, Search-Automatik, GA4 und eigener Nutzwert-Endpunkt.</p>
              <div class="checklist" id="checklist">
                <div class="check" data-check="status"><span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span><div><strong>Backend-Status</strong><br><code>/api/status.php</code></div><a href="/api/status.php" target="_blank" rel="noopener">öffnen</a></div>
                <div class="check" data-check="valueMetrics"><span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span><div><strong>Nutzwert-Tracking</strong><br><code>/api/value-track.php</code></div><span class="muted small">intern</span></div>
                <div class="check" data-check="robots"><span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span><div><strong>Robots</strong><br><code>/robots.txt</code></div><a href="/robots.txt" target="_blank" rel="noopener">öffnen</a></div>
                <div class="check" data-check="sitemap"><span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span><div><strong>Sitemap</strong><br><code>/sitemap.xml</code></div><a href="/sitemap.xml" target="_blank" rel="noopener">öffnen</a></div>
                <div class="check" data-check="build"><span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span><div><strong>Build-Version</strong><br><code>/meta/build.txt</code></div><a href="/meta/build.txt" target="_blank" rel="noopener">öffnen</a></div>
                <div class="check" data-check="analytics"><span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span><div><strong>GA4-Konfiguration</strong><br><code>/config.js</code></div><a href="/config.js" target="_blank" rel="noopener">öffnen</a></div>
              </div>
            </article>

            <article class="card card--third"><h2>Indexierbare Kernseiten</h2><div id="indexedPages" class="linkList"></div></article>
            <article class="card card--half"><h2>Search-Automatik</h2><div id="searchMetricsStatus" class="note small">Search-Daten werden geladen.</div></article>
            <article class="card card--half"><h2>Location-/Veranstalter-Nutzen</h2><div id="locationValueStatus" class="note small">Nutzwerte werden geladen.</div></article>
            <article class="card card--half"><h2>Zuordnung / Reporting-Ziele</h2><p class="muted small">Nur explizit zugeordnete Anbieter oder Locations werden als Ziel gezeigt. Alles andere bleibt bewusst nicht zugeordnet.</p><div id="reportingTargets" class="reportList"></div></article>
            <article class="card card--half"><h2>Externe Messquellen</h2><div class="linkList"><a class="linkItem" href="https://search.google.com/search-console" target="_blank" rel="noopener"><strong>Google Search Console</strong><span>Impressionen, Klicks, Seiten</span></a><a class="linkItem" href="https://www.bing.com/webmasters" target="_blank" rel="noopener"><strong>Bing Webmaster Tools</strong><span>Bing-Sichtbarkeit</span></a><a class="linkItem" href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener"><strong>Google Analytics 4</strong><span>Parallelmessung</span></a></div></article>
          </div>
        </div>
      </details>

      <script type="application/json" id="valueMetricsData"><?=
        json_encode($valueMetrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
      ?></script>
    </section>
    <!-- === END BLOCK: INTERNAL_SEO_DASHBOARD_MOBILE_AKQUISE_LAYOUT_V32 === -->
  </main>

  <script>
    // === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_LOGIC_V32_MOBILE_TOOLTIP_OPTOUT | Zweck: rendert kompaktes Akquise-Dashboard, Tooltips, 28-Tage-Vergleich und internes Tracking-Opt-out; Umfang: ersetzt nur Dashboard-Logik dieser Seite ===
    const CHECKS = new Map();
    const CORE_PAGES = ["/", "/angebote/", "/events-veroeffentlichen/", "/events-veroeffentlichen/einreichen/", "/events-veroeffentlichen/anbindung/", "/fuer-veranstalter/", "/ueber/", "/impressum/", "/datenschutz/"];
    const SEARCH_METRICS_URL = "/data/search-metrics.json";
    const VALUE_OPT_OUT_KEY = "be_value_metrics_opt_out";

    const DEFAULT_METRICS = {
      googleImpressions: 0,
      googleClicks: 0,
      bingImpressions: 0,
      bingClicks: 0,
      websiteClicks: 0,
      mapsClicks: 0,
      detailViews: 0,
      locationClicks: 0,
      organizerCtaClicks: 0,
      performingEvents: 0,
      performingLocations: 0
    };

    let currentMetrics = { ...DEFAULT_METRICS };
    let previousMetrics = { ...DEFAULT_METRICS };
    let searchPeriodLabel = "aktueller 28-Tage-Zeitraum";
    let valuePeriodLabel = "aktueller 28-Tage-Zeitraum";

    function metricNumber(value) {
      const number = Number(value || 0);
      return Number.isFinite(number) ? Math.max(0, Math.round(number)) : 0;
    }

    function formatMetric(value) {
      return metricNumber(value).toLocaleString("de-DE");
    }

    function setText(id, value) {
      const node = document.getElementById(id);
      if (node) node.textContent = value;
    }

    function readJsonScript(id) {
      const node = document.getElementById(id);
      if (!node) return null;
      try { return JSON.parse(node.textContent || "{}"); } catch (_) { return null; }
    }

    function formatPeriod(period) {
      if (!period?.start_date || !period?.end_date) return "28-Tage-Zeitraum";
      return `${period.start_date} bis ${period.end_date}`;
    }

    function formatDelta(current, previous, preferPercent = false) {
      const currentValue = metricNumber(current);
      const previousValue = metricNumber(previous);
      const delta = currentValue - previousValue;
      if (delta === 0) return "±0 vs. vorher";
      const sign = delta > 0 ? "+" : "−";
      const absolute = Math.abs(delta);
      if (preferPercent && previousValue > 0) {
        return `${sign}${Math.round((absolute / previousValue) * 100)} % vs. vorher`;
      }
      return `${sign}${absolute.toLocaleString("de-DE")} vs. vorher`;
    }

    function setStatusPill(id, state, label) {
      const node = document.getElementById(id);
      if (!node) return;
      node.dataset.state = state === "strong" ? "good" : state;
      const text = node.querySelector("span:last-child");
      if (text) text.textContent = label;
    }

    function isValueMetricsOptedOut() {
      try {
        if (localStorage.getItem(VALUE_OPT_OUT_KEY) === "1") return true;
      } catch (_) {}
      return document.cookie.split(";").some((entry) => entry.trim() === `${VALUE_OPT_OUT_KEY}=1`);
    }

    function setValueMetricsOptOut(enabled) {
      try {
        if (enabled) localStorage.setItem(VALUE_OPT_OUT_KEY, "1");
        else localStorage.removeItem(VALUE_OPT_OUT_KEY);
      } catch (_) {}

      const maxAge = enabled ? 60 * 60 * 24 * 365 : 0;
      const secure = location.protocol === "https:" ? "; Secure" : "";
      document.cookie = `${VALUE_OPT_OUT_KEY}=${enabled ? "1" : ""}; Max-Age=${maxAge}; Path=/; SameSite=Lax${secure}`;
      updateOptOutUi();
    }

    function updateOptOutUi() {
      const optedOut = isValueMetricsOptedOut();
      const button = document.getElementById("optOutToggle");
      if (button) button.textContent = optedOut ? "Eigenes Tracking wieder einschließen" : "Eigenes Tracking ausschließen";
      setText("optOutStatus", optedOut ? "Eigenes Tracking: ausgeschlossen" : "Eigenes Tracking: aktiv");
      setStatusPill("topOptOutStatus", optedOut ? "good" : "warn", optedOut ? "Eigenes Tracking ausgeschlossen" : "Eigenes Tracking aktiv");
    }

    function initTooltips() {
      document.addEventListener("click", (event) => {
        const button = event.target instanceof Element ? event.target.closest(".infoButton") : null;
        document.querySelectorAll(".infoButton[data-open='true']").forEach((item) => {
          if (item !== button) item.removeAttribute("data-open");
        });
        if (!button) return;
        event.preventDefault();
        button.dataset.open = button.dataset.open === "true" ? "false" : "true";
        if (button.dataset.open !== "true") button.removeAttribute("data-open");
      });
    }

    function setCheck(name, state, label, detail = "") {
      const row = document.querySelector(`[data-check="${name}"]`);
      if (!row) return;
      const status = row.querySelector(".status");
      const statusLabel = status?.querySelector("span:last-child");
      const code = row.querySelector("code");
      if (status) status.dataset.state = state;
      if (statusLabel) statusLabel.textContent = label;
      if (detail && code) code.textContent = detail;
      CHECKS.set(name, state);
      updateOverallStatus();
    }

    function updateOverallStatus() {
      const values = Array.from(CHECKS.values());
      const known = values.length > 0;
      const hasBad = values.includes("bad");
      const hasWarn = values.includes("warn");
      let state = "warn";
      let label = "Technik läuft";
      let copy = "Technik-Checks sind unten eingeklappt.";

      if (known && !hasBad && !hasWarn) {
        state = "good";
        label = "Technik ok";
        copy = "Technik: Backend, Search, GA4 und Nutzwert-Tracking wirken erreichbar.";
      } else if (known && hasBad) {
        state = "bad";
        label = "Technik prüfen";
        copy = "Technik: Mindestens ein Check ist fehlgeschlagen.";
      }

      setStatusPill("overallStatus", state, label);
      setText("overallText", copy);
    }

    async function fetchText(path) {
      const res = await fetch(path, { cache: "no-store" });
      const text = await res.text();
      return { ok: res.ok, status: res.status, text };
    }

    function runValueMetricCheck(payload) {
      if (!payload || payload.status === "error") {
        setCheck("valueMetrics", "bad", "Fehler", "/api/value-track.php · DB-Auswertung fehlerhaft");
        return;
      }
      setCheck("valueMetrics", "good", "ok", "/api/value-track.php · aggregierte DB-Metriken aktiv");
    }

    async function runChecks() {
      try {
        const res = await fetch("/api/status.php", { cache: "no-store" });
        const json = await res.json();
        const ok = res.ok && json.status === "ok" && json.checks?.config && json.checks?.database;
        setCheck("status", ok ? "good" : "bad", ok ? "ok" : "Fehler", `/api/status.php · ${json.app_env || "unknown"}`);
      } catch (_) { setCheck("status", "bad", "Fehler"); }

      runValueMetricCheck(readJsonScript("valueMetricsData"));

      try {
        const robots = await fetchText("/robots.txt");
        const ok = robots.ok && /Sitemap:\s*https:\/\/bocholt-erleben\.de\/sitemap\.xml/i.test(robots.text) && /User-agent:\s*GPTBot/i.test(robots.text);
        const internalBlocked = /Disallow:\s*\/intern\//i.test(robots.text);
        setCheck("robots", ok && internalBlocked ? "good" : "warn", ok && internalBlocked ? "ok" : "prüfen", internalBlocked ? "/robots.txt · intern gesperrt" : "/robots.txt · intern nicht gesperrt");
      } catch (_) { setCheck("robots", "bad", "Fehler"); }

      try {
        const sitemap = await fetchText("/sitemap.xml");
        const urls = Array.from(sitemap.text.matchAll(/<loc>(.*?)<\/loc>/g)).map((match) => match[1]);
        const missing = CORE_PAGES.filter((path) => !urls.some((url) => url.endsWith(path)));
        setCheck("sitemap", sitemap.ok && missing.length === 0 ? "good" : "warn", missing.length === 0 ? `${urls.length} URLs` : `${missing.length} fehlt`, `/sitemap.xml · ${urls.length} URLs`);
        renderIndexedPages(urls);
      } catch (_) { setCheck("sitemap", "bad", "Fehler"); renderIndexedPages([]); }

      try {
        const build = await fetchText("/meta/build.txt");
        const value = build.text.trim();
        setCheck("build", build.ok && value ? "good" : "warn", value ? value : "nicht gefunden", `/meta/build.txt · ${value || "leer"}`);
      } catch (_) { setCheck("build", "bad", "Fehler"); }

      try {
        const config = await fetchText("/config.js");
        const ok = config.ok && /measurementId:\s*["']G-Y6QLCQ4HXT["']/.test(config.text) && /valueMetricsEndpoint:\s*["']\/api\/value-track\.php["']/.test(config.text) && /trackOutboundClick/.test(config.text);
        setCheck("analytics", ok ? "good" : "warn", ok ? "ok" : "prüfen", ok ? "GA4 + First-Party-Nutzwerttracking vorbereitet" : "Analytics-Konfiguration prüfen");
      } catch (_) { setCheck("analytics", "bad", "Fehler"); }
    }

    function renderIndexedPages(urls) {
      const box = document.getElementById("indexedPages");
      if (!box) return;
      const visibleUrls = urls.length ? urls : CORE_PAGES.map((path) => `${location.origin}${path}`);
      box.innerHTML = visibleUrls.map((url) => {
        const path = new URL(url, location.origin).pathname;
        return `<a class="linkItem" href="${path}" target="_blank" rel="noopener"><strong>${path}</strong><span>öffnen</span></a>`;
      }).join("");
    }

    function buildMetricModel(metrics) {
      const safe = { ...DEFAULT_METRICS, ...(metrics || {}) };
      const searchImpressions = metricNumber(safe.googleImpressions) + metricNumber(safe.bingImpressions);
      const searchClicks = metricNumber(safe.googleClicks) + metricNumber(safe.bingClicks);
      const directLocationValue = metricNumber(safe.websiteClicks) + metricNumber(safe.mapsClicks) + metricNumber(safe.locationClicks) + metricNumber(safe.organizerCtaClicks);
      const engagementValue = directLocationValue + metricNumber(safe.detailViews);
      return { ...safe, searchImpressions, searchClicks, directLocationValue, engagementValue };
    }

    function classifyMetrics(metrics) {
      const model = buildMetricModel(metrics);
      if (model.searchImpressions >= 20000 && model.searchClicks >= 1000 && model.directLocationValue >= 120 && metricNumber(model.performingEvents) >= 20 && metricNumber(model.performingLocations) >= 5) {
        return ["strong", "Stark", "Stark verkaufsfähig: Sichtbarkeit, Weiterleitungen und mehrere performende Ziele/Locations sind automatisch belegbar."];
      }
      if (model.searchImpressions >= 10000 && model.searchClicks >= 500 && model.directLocationValue >= 50 && metricNumber(model.performingEvents) >= 10 && metricNumber(model.performingLocations) >= 3) {
        return ["good", "Grün", "Aktiv verkaufsfähig: Die Plattform erzeugt automatisch messbaren Nutzen für Events und Locations."];
      }
      if (model.searchImpressions >= 3000 && model.searchClicks >= 150 && (model.directLocationValue >= 10 || metricNumber(model.detailViews) >= 100 || metricNumber(model.performingEvents) >= 5)) {
        return ["warn", "Gelb", "Erste Akquise-Tests sind plausibel. Für Grün fehlen noch stärkere automatisch gemessene Website-, Maps- oder Location-Klicks."];
      }
      return ["bad", "Rot", "Noch nicht aktiv verkaufen. Erst Sichtbarkeit und automatisch gemessene Nutzsignale für Locations weiter aufbauen."];
    }

    function nextLever(model) {
      if (model.directLocationValue < 10) return "Nächster Hebel: mehr Website-, Route-/Maps- und CTA-Klicks sammeln. Diese Werte sind für Locations am stärksten erklärbar.";
      if (model.detailViews < 100) return "Nächster Hebel: mehr Detail-Aufrufe erzeugen, damit klarer wird, dass Nutzer einzelne Events und Aktivitäten wirklich prüfen.";
      if (model.performingEvents < 5) return "Nächster Hebel: mehr unterschiedliche Events mit Interaktion aufbauen, damit der Nutzen nicht an einem Einzelfall hängt.";
      if (model.performingLocations < 3) return "Nächster Hebel: mehr unterschiedliche Ziele/Locations mit Weiterleitungen aufbauen.";
      return "Nächster Hebel: erste gezielte Akquise mit konkreten Beispielzahlen testen.";
    }

    function renderTrend(id, current, previous, preferPercent = false) { setText(id, formatDelta(current, previous, preferPercent)); }

    function renderLocationValue(payload) {
      const box = document.getElementById("locationValueStatus");
      if (!box) return;
      if (!payload || payload.status === "error") {
        box.innerHTML = `<p><strong>Status:</strong> Fehler</p><p style="margin-top:8px;">${payload?.message || "Nutzwert-Metriken konnten nicht gelesen werden."}</p>`;
        return;
      }

      const metrics = payload.metrics || {};
      const previous = payload.previous_metrics || {};
      const directLocationValue = metricNumber(metrics.website_clicks) + metricNumber(metrics.maps_clicks) + metricNumber(metrics.location_clicks) + metricNumber(metrics.organizer_cta_clicks);
      const previousDirectLocationValue = metricNumber(previous.website_clicks) + metricNumber(previous.maps_clicks) + metricNumber(previous.location_clicks) + metricNumber(previous.organizer_cta_clicks);
      const generatedAt = payload.generated_at ? new Date(payload.generated_at) : null;
      const generatedLabel = generatedAt && !Number.isNaN(generatedAt.getTime()) ? generatedAt.toLocaleString("de-DE") : "unbekannt";
      valuePeriodLabel = formatPeriod(payload.period);

      box.innerHTML = `<p><strong>Status:</strong> ${payload.status || "ok"}</p><p style="margin-top:6px;"><strong>Zeitraum:</strong> ${formatPeriod(payload.period)}</p><p style="margin-top:6px;"><strong>Vorher:</strong> ${formatPeriod(payload.previous_period)}</p><p style="margin-top:6px;"><strong>Aktualisiert:</strong> ${generatedLabel}</p><p style="margin-top:10px;"><strong>Direkter Nutzwert:</strong> ${formatMetric(directLocationValue)} Klicks (${formatDelta(directLocationValue, previousDirectLocationValue)})</p><p style="margin-top:6px;"><strong>Detail-Interesse:</strong> ${formatMetric(metrics.detail_views)} Detail-Aufrufe (${formatDelta(metrics.detail_views, previous.detail_views)})</p><p style="margin-top:6px;"><strong>Performende Events:</strong> ${formatMetric(metrics.performing_events)} (${formatDelta(metrics.performing_events, previous.performing_events)})</p><p style="margin-top:6px;"><strong>Performende Ziele/Locations:</strong> ${formatMetric(metrics.performing_locations)} (${formatDelta(metrics.performing_locations, previous.performing_locations)})</p>`;
    }

    /* === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_REPORTING_TARGET_RENDER_V1 | Zweck: zeigt explizite Anbieter-/Location-Zuordnungen statt unklare Werte zu raten; Umfang: additive Dashboard-Detailliste === */
    function escapeHtml(value) {
      return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#039;"
      }[char]));
    }

    function reportingTargetKey(row) {
      return `${row?.target_type || ""}:${row?.target_id || ""}`;
    }

    function normalizeReportingTarget(row) {
      return {
        target_type: String(row?.target_type || ""),
        target_id: String(row?.target_id || ""),
        target_title: String(row?.target_title || row?.target_id || "Unbenanntes Ziel"),
        detail_views: metricNumber(row?.detail_views),
        website_clicks: metricNumber(row?.website_clicks),
        maps_clicks: metricNumber(row?.maps_clicks),
        location_clicks: metricNumber(row?.location_clicks),
        organizer_cta_clicks: metricNumber(row?.organizer_cta_clicks),
        total_interactions: metricNumber(row?.total_interactions),
        item_count: metricNumber(row?.item_count),
        item_titles: Array.isArray(row?.item_titles) ? row.item_titles.filter(Boolean).map(String) : []
      };
    }

    function collectFeedbackTargets(payload) {
      const map = new Map();
      const configuredRows = Array.isArray(payload?.configured_reporting_targets) ? payload.configured_reporting_targets : [];
      const currentRows = Array.isArray(payload?.reporting_targets) ? payload.reporting_targets : [];

      configuredRows.forEach((raw) => {
        const row = normalizeReportingTarget(raw);
        const key = reportingTargetKey(row);
        if (!row.target_type || !row.target_id || row.target_type === "unassigned") return;
        map.set(key, { ...row, configured: true });
      });

      currentRows.forEach((raw) => {
        const row = normalizeReportingTarget(raw);
        const key = reportingTargetKey(row);
        if (!row.target_type || !row.target_id || row.target_type === "unassigned") return;
        const existing = map.get(key) || {};
        map.set(key, {
          ...existing,
          ...row,
          configured: Boolean(existing.configured),
          item_count: Math.max(metricNumber(existing.item_count), row.item_count),
          item_titles: existing.item_titles?.length ? existing.item_titles : row.item_titles
        });
      });

      return Array.from(map.values()).sort((left, right) => {
        if (left.target_id === "biotopwildpark-anholter-schweiz") return -1;
        if (right.target_id === "biotopwildpark-anholter-schweiz") return 1;
        const byInteractions = metricNumber(right.total_interactions) - metricNumber(left.total_interactions);
        if (byInteractions !== 0) return byInteractions;
        return left.target_title.localeCompare(right.target_title, "de");
      });
    }

    function findPreviousReportingTarget(payload, row) {
      const rows = Array.isArray(payload?.previous_reporting_targets) ? payload.previous_reporting_targets : [];
      const key = reportingTargetKey(row);
      return normalizeReportingTarget(rows.find((candidate) => reportingTargetKey(candidate) === key) || {});
    }

    function feedbackTypeLabel(type) {
      if (type === "organizer") return "Anbieter";
      if (type === "location") return "Location";
      return "Reporting-Ziel";
    }

    function renderFeedbackReport(payload, selectedKey = "") {
      const select = document.getElementById("feedbackTargetSelect");
      const report = document.getElementById("feedbackReport");
      if (!select || !report) return;

      if (!payload || payload.status === "error") {
        select.innerHTML = `<option value="">Keine Daten verfügbar</option>`;
        report.innerHTML = `<p class="feedbackEmpty small">${escapeHtml(payload?.message || "Nutzwert-Metriken konnten nicht gelesen werden.")}</p>`;
        return;
      }

      const rows = collectFeedbackTargets(payload);
      if (!rows.length) {
        select.innerHTML = `<option value="">Keine Reporting-Ziele konfiguriert</option>`;
        report.innerHTML = `<p class="feedbackEmpty small">Noch keine Reporting-Ziele für einen Einzelbericht vorhanden.</p>`;
        return;
      }

      const currentKey = selectedKey && rows.some((row) => reportingTargetKey(row) === selectedKey)
        ? selectedKey
        : reportingTargetKey(rows[0]);

      select.innerHTML = rows.map((row) => {
        const key = reportingTargetKey(row);
        return `<option value="${escapeHtml(key)}"${key === currentKey ? " selected" : ""}>${escapeHtml(row.target_title)}</option>`;
      }).join("");

      const row = rows.find((candidate) => reportingTargetKey(candidate) === currentKey) || rows[0];
      const previous = findPreviousReportingTarget(payload, row);
      const directClicks = metricNumber(row.website_clicks) + metricNumber(row.maps_clicks) + metricNumber(row.location_clicks) + metricNumber(row.organizer_cta_clicks);
      const previousDirectClicks = metricNumber(previous.website_clicks) + metricNumber(previous.maps_clicks) + metricNumber(previous.location_clicks) + metricNumber(previous.organizer_cta_clicks);
      const period = formatPeriod(payload.period);
      const sourceTitles = row.item_titles?.length ? row.item_titles.slice(0, 3).join(", ") : `${formatMetric(row.item_count)} Inhalt(e)`;
      const hasSignals = metricNumber(row.total_interactions) > 0;

      report.innerHTML = `
        <div class="feedbackReportHead">
          <div>
            <h3 class="feedbackReportTitle">${escapeHtml(row.target_title)}</h3>
            <p class="muted small">${escapeHtml(feedbackTypeLabel(row.target_type))} · Zeitraum: ${escapeHtml(period)} · Inhalt: ${escapeHtml(sourceTitles)}</p>
          </div>
          <span class="status" data-state="${hasSignals ? "good" : "warn"}"><span class="dot"></span><span>${hasSignals ? "Nutzsignal gemessen" : "noch ohne Signal"}</span></span>
        </div>
        <div class="feedbackKpiGrid">
          <div class="feedbackKpi"><div class="feedbackKpiLabel">Interaktionen gesamt</div><div class="feedbackKpiValue">${formatMetric(row.total_interactions)}</div></div>
          <div class="feedbackKpi"><div class="feedbackKpiLabel">Detail-Aufrufe</div><div class="feedbackKpiValue">${formatMetric(row.detail_views)}</div></div>
          <div class="feedbackKpi"><div class="feedbackKpiLabel">Website-Klicks</div><div class="feedbackKpiValue">${formatMetric(row.website_clicks)}</div></div>
          <div class="feedbackKpi"><div class="feedbackKpiLabel">Route/Maps</div><div class="feedbackKpiValue">${formatMetric(row.maps_clicks)}</div></div>
        </div>
        <div class="feedbackText">
          <p>${hasSignals
            ? `Nutzer haben diesen Eintrag geöffnet oder eine konkrete Weiterleitung ausgelöst. Direkt erklärbare Klicks: ${formatMetric(directClicks)} (${formatDelta(directClicks, previousDirectClicks)}).`
            : `Für dieses Ziel liegen im aktuellen Zeitraum noch keine gemessenen Interaktionen vor. Der Bericht ist vorbereitet, sollte aber noch nicht als Akquise-Beleg verwendet werden.`}</p>
          <p class="muted small">Wichtig: Das sind gemessene Interaktionen auf Bocholt erleben. Es sind keine Besucherzahlen vor Ort, keine Buchungen und keine eindeutigen Personen.</p>
        </div>
      `.trim();
    }

    function initFeedbackReport(payload) {
      renderFeedbackReport(payload);
      document.getElementById("feedbackTargetSelect")?.addEventListener("change", (event) => {
        renderFeedbackReport(payload, event.target.value);
      });
    }

    function renderReportingTargets(payload) {
      const box = document.getElementById("reportingTargets");
      if (!box) return;

      const rows = Array.isArray(payload?.reporting_targets) ? payload.reporting_targets : [];
      if (!rows.length) {
        box.innerHTML = `<div class="note small">Noch keine explizit zugeordneten Reporting-Ziele im aktuellen Zeitraum.</div>`;
        return;
      }

      box.innerHTML = rows.map((row) => {
        const directClicks = metricNumber(row.website_clicks) + metricNumber(row.maps_clicks) + metricNumber(row.location_clicks) + metricNumber(row.organizer_cta_clicks);
        const typeLabel = row.target_type === "organizer"
          ? "Anbieter"
          : (row.target_type === "location" ? "Location" : "nicht zugeordnet");

        return `
          <div class="reportRow">
            <div>
              <div class="reportRowTitle">${escapeHtml(row.target_title || "nicht zugeordnet")}</div>
              <div class="reportRowMeta">${escapeHtml(typeLabel)} · ${formatMetric(row.item_count)} Inhalt(e)</div>
            </div>
            <div class="reportRowValues">
              <span><strong>${formatMetric(row.total_interactions)}</strong> Interaktionen gesamt</span>
              <span>${formatMetric(row.detail_views)} Detail · ${formatMetric(directClicks)} Klicks</span>
              <span>${formatMetric(row.website_clicks)} Website · ${formatMetric(row.maps_clicks)} Maps</span>
            </div>
          </div>
        `.trim();
      }).join("");
    }
    /* === END BLOCK: INTERNAL_SEO_DASHBOARD_REPORTING_TARGET_RENDER_V1 === */

    function renderMetrics(metrics, previous) {
      const model = buildMetricModel(metrics);
      const previousModel = buildMetricModel(previous);
      const [state, label, copy] = classifyMetrics(model);

      setText("kpiImpressions", formatMetric(model.searchImpressions));
      setText("kpiClicks", formatMetric(model.searchClicks));
      setText("kpiLocationValue", formatMetric(model.directLocationValue));
      setText("kpiStage", label);
      setText("kpiWebsiteClicks", formatMetric(model.websiteClicks));
      setText("kpiMapsClicks", formatMetric(model.mapsClicks));
      setText("kpiDetailViews", formatMetric(model.detailViews));
      setText("kpiOrganizerCtaClicks", formatMetric(model.organizerCtaClicks));
      setText("kpiLocationClicks", formatMetric(model.locationClicks));
      setText("kpiPerformingEvents", formatMetric(model.performingEvents));
      setText("kpiPerformingLocations", formatMetric(model.performingLocations));
      setText("kpiExplanation", copy);

      renderTrend("kpiImpressionsTrend", model.searchImpressions, previousModel.searchImpressions, true);
      renderTrend("kpiClicksTrend", model.searchClicks, previousModel.searchClicks, true);
      renderTrend("kpiLocationValueTrend", model.directLocationValue, previousModel.directLocationValue);
      renderTrend("kpiWebsiteClicksTrend", model.websiteClicks, previousModel.websiteClicks);
      renderTrend("kpiMapsClicksTrend", model.mapsClicks, previousModel.mapsClicks);
      renderTrend("kpiDetailViewsTrend", model.detailViews, previousModel.detailViews);
      renderTrend("kpiOrganizerCtaClicksTrend", model.organizerCtaClicks, previousModel.organizerCtaClicks);
      renderTrend("kpiLocationClicksTrend", model.locationClicks, previousModel.locationClicks);
      renderTrend("kpiPerformingEventsTrend", model.performingEvents, previousModel.performingEvents);
      renderTrend("kpiPerformingLocationsTrend", model.performingLocations, previousModel.performingLocations);

      setStatusPill("snapshotStatus", state, `${label} – ${label === "Rot" ? "noch nicht aktiv verkaufen" : "Akquise prüfen"}`);
      setText("snapshotPeriod", `Suchdaten: ${searchPeriodLabel} · Nutzwerte: ${valuePeriodLabel}`);
      setText("snapshotReason", copy);
      setText("snapshotNext", nextLever(model));
      setText("proofStatus", `Status: ${label}`);
      setText("proofPeriod", `Suchdaten: ${searchPeriodLabel} · Nutzwerte: ${valuePeriodLabel}`);
      setText("proofSummary", `Bocholt erleben wurde ${formatMetric(model.searchImpressions)}-mal über Google/Bing sichtbar, erzeugte ${formatMetric(model.searchClicks)} Such-Klicks und ${formatMetric(model.directLocationValue)} konkrete Weiterleitungen bzw. Aktionen. Zusätzlich wurden ${formatMetric(model.detailViews)} Detail-Aufrufe, ${formatMetric(model.performingEvents)} performende Events und ${formatMetric(model.performingLocations)} performende Ziele/Locations gemessen.`);
      setText("proofTrend", `Entwicklung: Sichtbarkeit ${formatDelta(model.searchImpressions, previousModel.searchImpressions, true)}, Such-Klicks ${formatDelta(model.searchClicks, previousModel.searchClicks, true)}, konkrete Nutzwert-Klicks ${formatDelta(model.directLocationValue, previousModel.directLocationValue)}.`);
    }

    function applyMetrics(metrics = {}, previous = {}) {
      currentMetrics = buildMetricModel({ ...currentMetrics, ...metrics });
      previousMetrics = buildMetricModel({ ...previousMetrics, ...previous });
      renderMetrics(currentMetrics, previousMetrics);
    }

    function sourceStatusLabel(status) {
      if (status === "ok") return "ok";
      if (status === "not_configured") return "nicht konfiguriert";
      return "Fehler";
    }

    function renderSearchMetricsStatus(payload) {
      const box = document.getElementById("searchMetricsStatus");
      if (!box) return;
      if (!payload) { box.textContent = "Search-Daten werden geladen."; return; }
      const google = payload.sources?.google || {};
      const bing = payload.sources?.bing || {};
      const previousGoogle = payload.previous_sources?.google || {};
      const previousBing = payload.previous_sources?.bing || {};
      const period = payload.period || {};
      const previousPeriod = payload.previous_period || {};
      const generatedAt = payload.generated_at ? new Date(payload.generated_at) : null;
      const generatedLabel = generatedAt && !Number.isNaN(generatedAt.getTime()) ? generatedAt.toLocaleString("de-DE") : "unbekannt";
      searchPeriodLabel = formatPeriod(period);
      box.innerHTML = `<p><strong>Status:</strong> ${payload.status || "unbekannt"}</p><p style="margin-top:6px;"><strong>Zeitraum:</strong> ${formatPeriod(period)}</p><p style="margin-top:6px;"><strong>Vorher:</strong> ${formatPeriod(previousPeriod)}</p><p style="margin-top:6px;"><strong>Aktualisiert:</strong> ${generatedLabel}</p><p style="margin-top:10px;"><strong>Google:</strong> ${sourceStatusLabel(google.status)} · ${formatMetric(google.impressions)} Impressionen · ${formatMetric(google.clicks)} Klicks</p><p style="margin-top:6px;"><strong>Google vorher:</strong> ${formatMetric(previousGoogle.impressions)} Impressionen · ${formatMetric(previousGoogle.clicks)} Klicks</p><p style="margin-top:10px;"><strong>Bing:</strong> ${sourceStatusLabel(bing.status)} · ${formatMetric(bing.impressions)} Impressionen · ${formatMetric(bing.clicks)} Klicks</p><p style="margin-top:6px;"><strong>Bing vorher:</strong> ${formatMetric(previousBing.impressions)} Impressionen · ${formatMetric(previousBing.clicks)} Klicks</p>${google.message ? `<p class="muted" style="margin-top:8px;">Google: ${google.message}</p>` : ""}${bing.message ? `<p class="muted" style="margin-top:4px;">Bing: ${bing.message}</p>` : ""}`;
    }

    function extractSearchMetrics(payload, previous = false) {
      const sources = previous ? (payload.previous_sources || {}) : (payload.sources || {});
      const google = sources.google || {};
      const bing = sources.bing || {};
      return {
        googleImpressions: google.status === "ok" ? metricNumber(google.impressions) : 0,
        googleClicks: google.status === "ok" ? metricNumber(google.clicks) : 0,
        bingImpressions: bing.status === "ok" ? metricNumber(bing.impressions) : 0,
        bingClicks: bing.status === "ok" ? metricNumber(bing.clicks) : 0
      };
    }

    function extractValueMetrics(payload, key = "metrics") {
      const metrics = payload?.[key] || {};
      return {
        websiteClicks: metricNumber(metrics.website_clicks),
        mapsClicks: metricNumber(metrics.maps_clicks),
        detailViews: metricNumber(metrics.detail_views),
        locationClicks: metricNumber(metrics.location_clicks),
        organizerCtaClicks: metricNumber(metrics.organizer_cta_clicks),
        performingEvents: metricNumber(metrics.performing_events),
        performingLocations: metricNumber(metrics.performing_locations)
      };
    }

    async function loadSearchMetrics() {
      renderSearchMetricsStatus(null);
      try {
        const res = await fetch(SEARCH_METRICS_URL, { cache: "no-store" });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        renderSearchMetricsStatus(payload);
        applyMetrics(extractSearchMetrics(payload, false), extractSearchMetrics(payload, true));
      } catch (_) {
        renderSearchMetricsStatus({ status: "error", generated_at: null, period: {}, previous_period: {}, sources: { google: { status: "error", impressions: 0, clicks: 0, message: "Search-Metrics-JSON konnte nicht geladen werden." }, bing: { status: "error", impressions: 0, clicks: 0, message: "Search-Metrics-JSON konnte nicht geladen werden." } }, previous_sources: { google: { status: "error", impressions: 0, clicks: 0 }, bing: { status: "error", impressions: 0, clicks: 0 } } });
      }
    }

    const valuePayload = readJsonScript("valueMetricsData");
    currentMetrics = buildMetricModel(extractValueMetrics(valuePayload, "metrics"));
    previousMetrics = buildMetricModel(extractValueMetrics(valuePayload, "previous_metrics"));
    renderLocationValue(valuePayload);
    initFeedbackReport(valuePayload);
    renderReportingTargets(valuePayload);
    applyMetrics({}, {});
    initTooltips();
    updateOptOutUi();
    document.getElementById("optOutToggle")?.addEventListener("click", () => setValueMetricsOptOut(!isValueMetricsOptedOut()));
    loadSearchMetrics();
    runChecks();
    // === END BLOCK: INTERNAL_SEO_DASHBOARD_LOGIC_V32_MOBILE_TOOLTIP_OPTOUT ===
  </script>
<?php endif; ?>
</body>
</html>
<?php /* === END FILE: intern/seo-dashboard/index.php === */ ?>
