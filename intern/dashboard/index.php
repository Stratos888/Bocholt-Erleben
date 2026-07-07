<?php
declare(strict_types=1);
/* === BEGIN FILE: intern/dashboard/index.php | Zweck: zentrales internes Verwaltungsdashboard fuer Content-Ops-, Growth- und Betriebsmetriken; Umfang: passwortgeschuetzte SQL-Ansicht auf Content-Ops-Ingest-Daten === */

require __DIR__ . '/../../api/_bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_name('BE_INTERNAL_DASHBOARD');
session_start();

function be_dash_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function be_dash_key_label(string $key): string
{
    $labels = [
        'issue_total' => 'Findings gesamt',
        'issue_critical' => 'Kritische Findings',
        'issue_review_needed' => 'Review nötig',
        'issue_warning' => 'Warnungen',
        'issue_auto_fixed' => 'Automatisch bereinigt',
        'manual_action_required' => 'Manuelle Aktionen',
        'manual_warning' => 'Manuelle Warnungen',
        'ai_factcheck_candidate' => 'KI-Faktencheck-Kandidaten',
        'neutral_observed' => 'Neutral beobachtet',
        'guarded_by_runtime' => 'Runtime-Guard',
        'visual_workflow' => 'Visual-Workflow',
        'search_feedback_rules_applied' => 'Search-Feedback angewendet',
        'visual_feedback_signals' => 'Visual-Feedback-Signale',
        'new_candidates' => 'Neue Kandidaten',
        'dropped_candidates' => 'Verworfene Kandidaten',
        'stored_runs' => 'Gespeicherte Läufe',
    ];

    if (isset($labels[$key])) {
        return $labels[$key];
    }

    return ucfirst(str_replace('_', ' ', $key));
}

function be_dash_source_label(string $source): string
{
    $labels = [
        'content_quality_audit' => 'Content Quality',
        'growth_intelligence' => 'Growth Intelligence',
        'weekly_ki_websearch' => 'Weekly KI-Suche',
        'manual_ki_intake' => 'Manual KI Intake',
        'inbox_cleanup' => 'Inbox Cleanup',
    ];

    return $labels[$source] ?? be_dash_key_label($source);
}

function be_dash_number(float|int|string|null $value): string
{
    $number = (float)($value ?? 0);
    if (abs($number - round($number)) < 0.00001) {
        return number_format((int)round($number), 0, ',', '.');
    }

    return number_format($number, 1, ',', '.');
}

function be_dash_delta(float|int|string|null $value): string
{
    $number = (float)($value ?? 0);
    if (abs($number) < 0.00001) {
        return '±0';
    }

    $prefix = $number > 0 ? '+' : '';
    return $prefix . be_dash_number($number);
}

function be_dash_periods(int $days = 28): array
{
    $days = max(7, min(90, $days));
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $currentStart = $today->modify('-' . ($days - 1) . ' days');
    $previousEnd = $currentStart->modify('-1 day');
    $previousStart = $previousEnd->modify('-' . ($days - 1) . ' days');

    return [
        'current' => [
            'start' => $currentStart,
            'end' => $today,
        ],
        'previous' => [
            'start' => $previousStart,
            'end' => $previousEnd,
        ],
    ];
}

function be_dash_table_exists(PDO $pdo, string $tableName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $statement->execute([':table_name' => $tableName]);

    return (int)($statement->fetch()['total'] ?? 0) > 0;
}

function be_dash_fetch_value(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int)($statement->fetch()['total'] ?? 0);
}

function be_dash_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll() ?: [];
}

function be_dash_metric_totals(PDO $pdo, string $environment, string $startDate, string $endDate): array
{
    $rows = be_dash_fetch_all($pdo, <<<'SQL'
SELECT metric_key, metric_scope, dimension_key, COALESCE(SUM(metric_value), 0) AS total
FROM content_ops_metric_daily
WHERE environment = :environment
  AND metric_date BETWEEN :start_date AND :end_date
GROUP BY metric_key, metric_scope, dimension_key
ORDER BY total DESC, metric_key ASC
SQL, [
        ':environment' => $environment,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $indexed = [];
    foreach ($rows as $row) {
        $key = (string)$row['metric_key'] . '|' . (string)$row['metric_scope'] . '|' . (string)$row['dimension_key'];
        $indexed[$key] = $row;
    }

    return $indexed;
}

function be_dash_metric_comparison(PDO $pdo, string $environment, array $periods): array
{
    $current = be_dash_metric_totals(
        $pdo,
        $environment,
        $periods['current']['start']->format('Y-m-d'),
        $periods['current']['end']->format('Y-m-d')
    );
    $previous = be_dash_metric_totals(
        $pdo,
        $environment,
        $periods['previous']['start']->format('Y-m-d'),
        $periods['previous']['end']->format('Y-m-d')
    );

    $keys = array_unique(array_merge(array_keys($current), array_keys($previous)));
    $rows = [];

    foreach ($keys as $key) {
        $base = $current[$key] ?? $previous[$key] ?? [];
        $currentValue = (float)($current[$key]['total'] ?? 0);
        $previousValue = (float)($previous[$key]['total'] ?? 0);
        $rows[] = [
            'metric_key' => (string)($base['metric_key'] ?? ''),
            'metric_scope' => (string)($base['metric_scope'] ?? ''),
            'dimension_key' => (string)($base['dimension_key'] ?? ''),
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'delta' => $currentValue - $previousValue,
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $manualKeys = ['manual_action_required', 'issue_review_needed', 'issue_critical', 'issue_warning'];
        $aPriority = in_array($a['metric_key'], $manualKeys, true) ? 0 : 1;
        $bPriority = in_array($b['metric_key'], $manualKeys, true) ? 0 : 1;
        if ($aPriority !== $bPriority) {
            return $aPriority <=> $bPriority;
        }
        return abs((float)$b['current_value']) <=> abs((float)$a['current_value']);
    });

    return array_slice($rows, 0, 18);
}

$errorMessage = '';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: /intern/dashboard/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = trim((string)($_POST['password'] ?? ''));

    try {
        $expectedPassword = be_review_password();
        if ($submittedPassword !== '' && hash_equals($expectedPassword, $submittedPassword)) {
            $_SESSION['be_internal_dashboard_unlocked'] = true;
            header('Location: /intern/dashboard/');
            exit;
        }

        $errorMessage = 'Passwort nicht korrekt.';
    } catch (Throwable $error) {
        http_response_code(503);
        $errorMessage = 'Review-Passwort ist serverseitig nicht konfiguriert.';
    }
}

$isUnlocked = (bool)($_SESSION['be_internal_dashboard_unlocked'] ?? false);
$checkedAt = date('d.m.Y H:i');
$environment = strtolower(trim((string)($_GET['environment'] ?? be_app_env_value() ?: 'staging')));
if (!in_array($environment, ['staging', 'live', 'main', 'production'], true)) {
    $environment = 'staging';
}
if ($environment === 'production') {
    $environment = 'live';
}
$periodDays = max(7, min(90, (int)($_GET['days'] ?? 28)));
$periods = be_dash_periods($periodDays);

$dashboard = [
    'status' => 'locked',
    'message' => '',
    'tables_ready' => false,
    'runs_current' => 0,
    'runs_previous' => 0,
    'manual_actions_current' => 0,
    'manual_actions_previous' => 0,
    'open_manual_actions' => 0,
    'latest_run_at' => '',
    'latest_source' => '',
    'latest_status' => '',
    'source_rows' => [],
    'metric_rows' => [],
    'action_rows' => [],
    'rule_rows' => [],
    'latest_runs' => [],
];

if ($isUnlocked) {
    try {
        $pdo = be_db();
        $requiredTables = ['content_ops_run', 'content_ops_metric_daily', 'content_ops_action_log', 'feedback_rule_effectiveness_daily'];
        $missingTables = [];
        foreach ($requiredTables as $tableName) {
            if (!be_dash_table_exists($pdo, $tableName)) {
                $missingTables[] = $tableName;
            }
        }

        if ($missingTables !== []) {
            $dashboard['status'] = 'not_ready';
            $dashboard['message'] = 'Content-Ops-Tabellen fehlen noch: ' . implode(', ', $missingTables) . '.';
        } else {
            $dashboard['status'] = 'ok';
            $dashboard['tables_ready'] = true;

            $currentStart = $periods['current']['start']->format('Y-m-d');
            $currentEnd = $periods['current']['end']->format('Y-m-d');
            $previousStart = $periods['previous']['start']->format('Y-m-d');
            $previousEnd = $periods['previous']['end']->format('Y-m-d');

            $dashboard['runs_current'] = be_dash_fetch_value($pdo, <<<'SQL'
SELECT COUNT(*) AS total
FROM content_ops_run
WHERE environment = :environment
  AND generated_at_utc >= CONCAT(:start_date, ' 00:00:00')
  AND generated_at_utc <= CONCAT(:end_date, ' 23:59:59')
SQL, [':environment' => $environment, ':start_date' => $currentStart, ':end_date' => $currentEnd]);

            $dashboard['runs_previous'] = be_dash_fetch_value($pdo, <<<'SQL'
SELECT COUNT(*) AS total
FROM content_ops_run
WHERE environment = :environment
  AND generated_at_utc >= CONCAT(:start_date, ' 00:00:00')
  AND generated_at_utc <= CONCAT(:end_date, ' 23:59:59')
SQL, [':environment' => $environment, ':start_date' => $previousStart, ':end_date' => $previousEnd]);

            $dashboard['manual_actions_current'] = be_dash_fetch_value($pdo, <<<'SQL'
SELECT COUNT(*) AS total
FROM content_ops_action_log
WHERE environment = :environment
  AND user_action_required = 1
  AND generated_at_utc >= CONCAT(:start_date, ' 00:00:00')
  AND generated_at_utc <= CONCAT(:end_date, ' 23:59:59')
SQL, [':environment' => $environment, ':start_date' => $currentStart, ':end_date' => $currentEnd]);

            $dashboard['manual_actions_previous'] = be_dash_fetch_value($pdo, <<<'SQL'
SELECT COUNT(*) AS total
FROM content_ops_action_log
WHERE environment = :environment
  AND user_action_required = 1
  AND generated_at_utc >= CONCAT(:start_date, ' 00:00:00')
  AND generated_at_utc <= CONCAT(:end_date, ' 23:59:59')
SQL, [':environment' => $environment, ':start_date' => $previousStart, ':end_date' => $previousEnd]);

            $dashboard['open_manual_actions'] = be_dash_fetch_value($pdo, <<<'SQL'
SELECT COUNT(*) AS total
FROM content_ops_action_log
WHERE environment = :environment
  AND user_action_required = 1
  AND status = 'open'
SQL, [':environment' => $environment]);

            $latest = be_dash_fetch_all($pdo, <<<'SQL'
SELECT generated_at_utc, source_mode, status
FROM content_ops_run
WHERE environment = :environment
ORDER BY generated_at_utc DESC, id DESC
LIMIT 1
SQL, [':environment' => $environment]);

            if ($latest !== []) {
                $dashboard['latest_run_at'] = (string)$latest[0]['generated_at_utc'];
                $dashboard['latest_source'] = (string)$latest[0]['source_mode'];
                $dashboard['latest_status'] = (string)$latest[0]['status'];
            }

            $dashboard['source_rows'] = be_dash_fetch_all($pdo, <<<'SQL'
SELECT source_mode, COUNT(*) AS total_runs, MAX(generated_at_utc) AS latest_run_at
FROM content_ops_run
WHERE environment = :environment
GROUP BY source_mode
ORDER BY latest_run_at DESC
LIMIT 8
SQL, [':environment' => $environment]);

            $dashboard['metric_rows'] = be_dash_metric_comparison($pdo, $environment, $periods);

            $dashboard['action_rows'] = be_dash_fetch_all($pdo, <<<'SQL'
SELECT action_type, finding_type, user_action_required, COUNT(*) AS total
FROM content_ops_action_log
WHERE environment = :environment
  AND generated_at_utc >= CONCAT(:start_date, ' 00:00:00')
  AND generated_at_utc <= CONCAT(:end_date, ' 23:59:59')
GROUP BY action_type, finding_type, user_action_required
ORDER BY user_action_required DESC, total DESC, action_type ASC
LIMIT 18
SQL, [':environment' => $environment, ':start_date' => $currentStart, ':end_date' => $currentEnd]);

            $dashboard['rule_rows'] = be_dash_fetch_all($pdo, <<<'SQL'
SELECT rule_key, rule_type, rule_class,
       COALESCE(SUM(applied_count), 0) AS applied_count,
       COALESCE(SUM(prevented_count), 0) AS prevented_count,
       COALESCE(SUM(recurrence_count), 0) AS recurrence_count,
       COALESCE(SUM(false_positive_count), 0) AS false_positive_count
FROM feedback_rule_effectiveness_daily
WHERE environment = :environment
  AND metric_date BETWEEN :start_date AND :end_date
GROUP BY rule_key, rule_type, rule_class
ORDER BY applied_count DESC, prevented_count DESC, rule_key ASC
LIMIT 12
SQL, [':environment' => $environment, ':start_date' => $currentStart, ':end_date' => $currentEnd]);

            $dashboard['latest_runs'] = be_dash_fetch_all($pdo, <<<'SQL'
SELECT generated_at_utc, workflow_name, source_mode, status, action_required, github_run_url
FROM content_ops_run
WHERE environment = :environment
ORDER BY generated_at_utc DESC, id DESC
LIMIT 10
SQL, [':environment' => $environment]);
        }
    } catch (Throwable $error) {
        $dashboard['status'] = 'error';
        $dashboard['message'] = be_should_expose_diagnostics() ? $error->getMessage() : 'Dashboard-Daten konnten nicht geladen werden.';
    }
}

?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Verwaltung · Bocholt erleben</title>
    <style>
        :root {
            --be-primary: #8BCF4A;
            --be-primary-muted: #EAF6DB;
            --be-bg: #F9FBF6;
            --be-surface: #FFFFFF;
            --be-text: #1F2933;
            --be-text-soft: #5F6B73;
            --be-text-muted: #8A949B;
            --be-border: rgba(31, 41, 51, 0.12);
            --be-shadow: 0 18px 50px rgba(31, 41, 51, 0.08);
            --be-warn: #B7791F;
            --be-danger: #B91C1C;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top left, rgba(139, 207, 74, 0.20), transparent 34rem), var(--be-bg);
            color: var(--be-text);
            font: 15px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        a { color: inherit; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .page { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 32px 0 48px; }
        .topbar { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 22px; }
        .eyebrow { color: var(--be-text-soft); font-size: 0.78rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        h1 { margin: 4px 0 6px; font-size: clamp(2rem, 5vw, 3.6rem); line-height: 0.95; letter-spacing: -0.06em; }
        .subline { max-width: 760px; margin: 0; color: var(--be-text-soft); font-size: 1rem; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .chip { border: 1px solid var(--be-border); background: rgba(255,255,255,0.72); border-radius: 999px; padding: 7px 10px; color: var(--be-text-soft); font-size: 0.82rem; font-weight: 700; }
        .chip strong { color: var(--be-text); }
        .logout { color: var(--be-text-soft); font-weight: 700; font-size: 0.9rem; }
        .login-card, .panel, .metric-card {
            background: rgba(255,255,255,0.88);
            border: 1px solid var(--be-border);
            border-radius: 26px;
            box-shadow: var(--be-shadow);
        }
        .login-card { max-width: 430px; margin: 12vh auto 0; padding: 26px; }
        .field { display: grid; gap: 8px; margin-top: 18px; }
        label { font-weight: 800; }
        input, select, button {
            border: 1px solid var(--be-border);
            border-radius: 14px;
            padding: 12px 13px;
            font: inherit;
        }
        button { border: 0; background: var(--be-primary); color: #16320F; font-weight: 900; cursor: pointer; }
        .error { margin-top: 12px; color: var(--be-danger); font-weight: 800; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin: 20px 0; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filters select { background: var(--be-surface); font-weight: 800; color: var(--be-text); }
        .grid { display: grid; gap: 16px; }
        .grid.cards { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid.two { grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr); margin-top: 16px; }
        .metric-card { padding: 18px; min-height: 128px; }
        .metric-label { color: var(--be-text-soft); font-size: 0.84rem; font-weight: 800; }
        .metric-value { margin-top: 8px; font-size: 2.35rem; line-height: 1; font-weight: 950; letter-spacing: -0.05em; }
        .metric-foot { margin-top: 10px; color: var(--be-text-muted); font-size: 0.86rem; font-weight: 700; }
        .delta-pos { color: var(--be-danger); }
        .delta-neg { color: #2F7D32; }
        .delta-neutral { color: var(--be-text-muted); }
        .panel { overflow: hidden; }
        .panel-head { padding: 18px 18px 12px; border-bottom: 1px solid var(--be-border); display: flex; justify-content: space-between; gap: 12px; align-items: baseline; }
        .panel-title { margin: 0; font-size: 1.1rem; letter-spacing: -0.03em; }
        .panel-note { margin: 0; color: var(--be-text-muted); font-size: 0.83rem; font-weight: 700; }
        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid rgba(31,41,51,0.08); vertical-align: top; }
        th { color: var(--be-text-muted); font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.06em; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .status { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 8px; font-size: 0.76rem; font-weight: 900; }
        .status.ok { background: var(--be-primary-muted); color: #2F5F17; }
        .status.warn { background: #FFF7E0; color: var(--be-warn); }
        .status.err { background: #FEE2E2; color: var(--be-danger); }
        .muted { color: var(--be-text-muted); }
        .empty { padding: 22px 18px; color: var(--be-text-soft); font-weight: 700; }
        .stack { display: grid; gap: 16px; }
        @media (max-width: 900px) { .grid.cards, .grid.two { grid-template-columns: 1fr; } .topbar { display: block; } }
        @media (max-width: 640px) { .page { width: min(100% - 22px, 1180px); padding-top: 22px; } .metric-value { font-size: 2rem; } th, td { padding: 10px; } }
    </style>
</head>
<body>
<main class="page">
    <?php if (!$isUnlocked): ?>
        <section class="login-card" aria-labelledby="login-title">
            <div class="eyebrow">Interne Verwaltung</div>
            <h1 id="login-title">Bocholt erleben</h1>
            <p class="subline">Passwortgeschützte Betriebsansicht für Content Ops, Growth und spätere Verwaltungsmetriken.</p>
            <form method="post" action="/intern/dashboard/" autocomplete="off">
                <div class="field">
                    <label for="password">Review-Passwort</label>
                    <input id="password" name="password" type="password" required autofocus>
                </div>
                <div class="field">
                    <button type="submit">Dashboard öffnen</button>
                </div>
                <?php if ($errorMessage !== ''): ?><div class="error"><?= be_dash_h($errorMessage) ?></div><?php endif; ?>
            </form>
        </section>
    <?php else: ?>
        <header class="topbar">
            <div>
                <div class="eyebrow">Interne Verwaltung</div>
                <h1>Dashboard</h1>
                <p class="subline">Content-Ops-Ingest, manuelle Aufgaben, Feedback-Wirkung und technische Laufhistorie. Werte sind bewusst konkret als aktueller Zeitraum gegen Vorzeitraum dargestellt.</p>
                <div class="meta">
                    <span class="chip">Umgebung: <strong><?= be_dash_h($environment) ?></strong></span>
                    <span class="chip">Zeitraum: <strong><?= $periodDays ?> Tage</strong></span>
                    <span class="chip">Aktuell: <strong><?= be_dash_h($periods['current']['start']->format('d.m.')) ?>–<?= be_dash_h($periods['current']['end']->format('d.m.Y')) ?></strong></span>
                    <span class="chip">Stand: <strong><?= be_dash_h($checkedAt) ?></strong></span>
                </div>
            </div>
            <a class="logout" href="/intern/dashboard/?logout=1">Abmelden</a>
        </header>

        <form class="toolbar" method="get" action="/intern/dashboard/">
            <div class="filters">
                <select name="environment" aria-label="Umgebung">
                    <option value="staging" <?= $environment === 'staging' ? 'selected' : '' ?>>Staging</option>
                    <option value="live" <?= $environment === 'live' ? 'selected' : '' ?>>Live</option>
                </select>
                <select name="days" aria-label="Zeitraum">
                    <option value="7" <?= $periodDays === 7 ? 'selected' : '' ?>>7 Tage</option>
                    <option value="28" <?= $periodDays === 28 ? 'selected' : '' ?>>28 Tage</option>
                    <option value="90" <?= $periodDays === 90 ? 'selected' : '' ?>>90 Tage</option>
                </select>
                <button type="submit">Aktualisieren</button>
            </div>
            <span class="panel-note">Standard: 28 Tage vs. vorherige 28 Tage</span>
        </form>

        <?php if ($dashboard['status'] !== 'ok'): ?>
            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Datenstatus</h2>
                    <span class="status <?= $dashboard['status'] === 'not_ready' ? 'warn' : 'err' ?>"><?= be_dash_h((string)$dashboard['status']) ?></span>
                </div>
                <div class="empty"><?= be_dash_h((string)$dashboard['message']) ?></div>
            </section>
        <?php else: ?>
            <?php
            $runsDelta = (int)$dashboard['runs_current'] - (int)$dashboard['runs_previous'];
            $manualDelta = (int)$dashboard['manual_actions_current'] - (int)$dashboard['manual_actions_previous'];
            ?>
            <section class="grid cards" aria-label="Kernmetriken">
                <article class="metric-card">
                    <div class="metric-label">Roboterläufe</div>
                    <div class="metric-value"><?= be_dash_number($dashboard['runs_current']) ?></div>
                    <div class="metric-foot">Vorperiode <?= be_dash_number($dashboard['runs_previous']) ?> · <span class="<?= $runsDelta === 0 ? 'delta-neutral' : 'delta-pos' ?>"><?= be_dash_delta($runsDelta) ?></span></div>
                </article>
                <article class="metric-card">
                    <div class="metric-label">Manuelle Aktionen</div>
                    <div class="metric-value"><?= be_dash_number($dashboard['manual_actions_current']) ?></div>
                    <div class="metric-foot">Vorperiode <?= be_dash_number($dashboard['manual_actions_previous']) ?> · <span class="<?= $manualDelta > 0 ? 'delta-pos' : ($manualDelta < 0 ? 'delta-neg' : 'delta-neutral') ?>"><?= be_dash_delta($manualDelta) ?></span></div>
                </article>
                <article class="metric-card">
                    <div class="metric-label">Offene manuelle Fälle</div>
                    <div class="metric-value"><?= be_dash_number($dashboard['open_manual_actions']) ?></div>
                    <div class="metric-foot">Status aus Content-Ops-Action-Log</div>
                </article>
                <article class="metric-card">
                    <div class="metric-label">Letzter Lauf</div>
                    <div class="metric-value" style="font-size:1.35rem;line-height:1.15;"><?= $dashboard['latest_run_at'] !== '' ? be_dash_h(be_dash_source_label((string)$dashboard['latest_source'])) : '—' ?></div>
                    <div class="metric-foot"><?= $dashboard['latest_run_at'] !== '' ? be_dash_h((string)$dashboard['latest_run_at']) . ' · ' . be_dash_h((string)$dashboard['latest_status']) : 'Noch kein Lauf gespeichert' ?></div>
                </article>
            </section>

            <section class="grid two">
                <div class="stack">
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Metrikvergleich</h2>
                            <p class="panel-note">Aktuell vs. Vorperiode</p>
                        </div>
                        <?php if ($dashboard['metric_rows'] === []): ?>
                            <div class="empty">Noch keine Metriken für diese Umgebung im gewählten Zeitraum.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Metrik</th><th>Scope</th><th class="num">Vorher</th><th class="num">Aktuell</th><th class="num">Delta</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dashboard['metric_rows'] as $row):
                                        $delta = (float)$row['delta'];
                                        ?>
                                        <tr>
                                            <td><strong><?= be_dash_h(be_dash_key_label((string)$row['metric_key'])) ?></strong><?php if ((string)$row['dimension_key'] !== ''): ?><br><span class="muted"><?= be_dash_h((string)$row['dimension_key']) ?></span><?php endif; ?></td>
                                            <td><?= be_dash_h((string)$row['metric_scope']) ?></td>
                                            <td class="num"><?= be_dash_number($row['previous_value']) ?></td>
                                            <td class="num"><?= be_dash_number($row['current_value']) ?></td>
                                            <td class="num <?= $delta > 0 ? 'delta-pos' : ($delta < 0 ? 'delta-neg' : 'delta-neutral') ?>"><?= be_dash_delta($delta) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Geroutete Findings</h2>
                            <p class="panel-note">Aktueller Zeitraum</p>
                        </div>
                        <?php if ($dashboard['action_rows'] === []): ?>
                            <div class="empty">Keine gerouteten Findings im Zeitraum.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Aktion</th><th>Finding</th><th>Status</th><th class="num">Anzahl</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dashboard['action_rows'] as $row): ?>
                                        <tr>
                                            <td><?= be_dash_h(be_dash_key_label((string)$row['action_type'])) ?></td>
                                            <td><?= be_dash_h(be_dash_key_label((string)$row['finding_type'])) ?></td>
                                            <td><?= ((int)$row['user_action_required'] === 1) ? '<span class="status warn">manuell</span>' : '<span class="status ok">auto</span>' ?></td>
                                            <td class="num"><?= be_dash_number($row['total']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="stack">
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Quellläufe</h2>
                            <p class="panel-note">Letzter Stand je Quelle</p>
                        </div>
                        <?php if ($dashboard['source_rows'] === []): ?>
                            <div class="empty">Noch keine Quellläufe gespeichert.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Quelle</th><th class="num">Läufe</th><th>Letzter Lauf</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dashboard['source_rows'] as $row): ?>
                                        <tr>
                                            <td><strong><?= be_dash_h(be_dash_source_label((string)$row['source_mode'])) ?></strong></td>
                                            <td class="num"><?= be_dash_number($row['total_runs']) ?></td>
                                            <td><?= be_dash_h((string)$row['latest_run_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Feedback-Wirkung</h2>
                            <p class="panel-note">Regeln im Zeitraum</p>
                        </div>
                        <?php if ($dashboard['rule_rows'] === []): ?>
                            <div class="empty">Noch keine Feedback-Regelwirkung gespeichert.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Regel</th><th class="num">Angew.</th><th class="num">Verhindert</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dashboard['rule_rows'] as $row): ?>
                                        <tr>
                                            <td><strong><?= be_dash_h((string)$row['rule_key']) ?></strong><br><span class="muted"><?= be_dash_h((string)$row['rule_type']) ?> · <?= be_dash_h((string)$row['rule_class']) ?></span></td>
                                            <td class="num"><?= be_dash_number($row['applied_count']) ?></td>
                                            <td class="num"><?= be_dash_number($row['prevented_count']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Letzte Läufe</h2>
                            <p class="panel-note">Audit-Trail</p>
                        </div>
                        <?php if ($dashboard['latest_runs'] === []): ?>
                            <div class="empty">Keine Läufe vorhanden.</div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Zeit</th><th>Workflow</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dashboard['latest_runs'] as $row): ?>
                                        <tr>
                                            <td><?= be_dash_h((string)$row['generated_at_utc']) ?></td>
                                            <td>
                                                <?php if ((string)$row['github_run_url'] !== ''): ?>
                                                    <a href="<?= be_dash_h((string)$row['github_run_url']) ?>" rel="noreferrer" target="_blank"><?= be_dash_h((string)$row['workflow_name'] ?: be_dash_source_label((string)$row['source_mode'])) ?></a>
                                                <?php else: ?>
                                                    <?= be_dash_h((string)$row['workflow_name'] ?: be_dash_source_label((string)$row['source_mode'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= ((int)$row['action_required'] === 1) ? '<span class="status warn">Aktion</span>' : '<span class="status ok">ok</span>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
<?php /* === END FILE: intern/dashboard/index.php === */ ?>
