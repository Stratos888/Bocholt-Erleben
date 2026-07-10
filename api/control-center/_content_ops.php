<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

function be_cc_runtime_environment(): string
{
    $explicit = strtolower(trim((string)getenv('BE_ENVIRONMENT')));
    if (in_array($explicit, ['live','staging'], true)) return $explicit;
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    return str_contains($host, 'staging') ? 'staging' : 'live';
}

function be_cc_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function be_cc_content_ops_status(PDO $pdo): array
{
    $required = ['content_ops_run','content_ops_metric_daily','feedback_rule_effectiveness_daily'];
    foreach ($required as $table) {
        if (!be_cc_table_exists($pdo, $table)) {
            return [
                'available' => false,
                'environment' => be_cc_runtime_environment(),
                'message' => 'Content-Ops-Metriken sind in dieser Laufzeit noch nicht initialisiert.',
                'processes' => [],
                'metrics' => [],
                'learning' => [],
                'funnel' => ['available' => false, 'message' => 'Keine belastbaren Growth-/Funnel-Metriken vorhanden.'],
            ];
        }
    }

    $environment = be_cc_runtime_environment();
    $stmt = $pdo->prepare(
        'SELECT source_mode, status, action_required, generated_at_utc, github_run_url
         FROM content_ops_run
         WHERE environment = :environment
         ORDER BY generated_at_utc DESC, id DESC
         LIMIT 100'
    );
    $stmt->execute(['environment' => $environment]);
    $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $latestByMode = [];
    foreach ($runs as $row) {
        $mode = (string)$row['source_mode'];
        if ($mode !== '' && !isset($latestByMode[$mode])) $latestByMode[$mode] = $row;
    }

    $stmt = $pdo->prepare(
        'SELECT metric_key, metric_value, metric_date, source_mode, metric_scope, dimension_key
         FROM content_ops_metric_daily
         WHERE environment = :environment
           AND metric_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 35 DAY)
         ORDER BY metric_date DESC, id DESC'
    );
    $stmt->execute(['environment' => $environment]);
    $metricRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $latestMetrics = [];
    foreach ($metricRows as $row) {
        $key = (string)$row['metric_key'];
        if ($key !== '' && !isset($latestMetrics[$key])) {
            $latestMetrics[$key] = [
                'value' => (float)$row['metric_value'],
                'date' => (string)$row['metric_date'],
                'source_mode' => (string)$row['source_mode'],
                'scope' => (string)$row['metric_scope'],
                'dimension' => (string)$row['dimension_key'],
            ];
        }
    }

    $stmt = $pdo->prepare(
        'SELECT rule_key, rule_type, rule_class,
                SUM(applied_count) AS applied_count,
                SUM(prevented_count) AS prevented_count,
                SUM(recurrence_count) AS recurrence_count,
                SUM(false_positive_count) AS false_positive_count,
                MAX(metric_date) AS last_date
         FROM feedback_rule_effectiveness_daily
         WHERE environment = :environment
           AND metric_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 35 DAY)
         GROUP BY rule_key, rule_type, rule_class
         ORDER BY prevented_count DESC, applied_count DESC
         LIMIT 20'
    );
    $stmt->execute(['environment' => $environment]);
    $learningRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $value = static fn(string $key): ?float => isset($latestMetrics[$key]) ? (float)$latestMetrics[$key]['value'] : null;
    $funnelKeys = [
        'growth.gsc_rows', 'growth.ga4_rows', 'growth.value_metric_rows',
        'search.weekly.raw_candidates', 'search.weekly.selected_candidates',
        'search.weekly.dropped_candidates', 'search.weekly.prevented_candidates',
        'search.weekly.selected_rate_percent', 'search.weekly.dropped_rate_percent',
        'intake.manual.input_items', 'intake.manual.appended_items', 'intake.manual.skipped_items',
    ];
    $funnelMetrics = [];
    foreach ($funnelKeys as $key) if (isset($latestMetrics[$key])) $funnelMetrics[$key] = $latestMetrics[$key];

    $prevented = $falsePositives = $recurrences = 0;
    foreach ($learningRows as $row) {
        $prevented += (int)$row['prevented_count'];
        $falsePositives += (int)$row['false_positive_count'];
        $recurrences += (int)$row['recurrence_count'];
    }

    return [
        'available' => true,
        'environment' => $environment,
        'message' => 'Content-Ops-Läufe und Lernwirkung werden aus der gemeinsamen Betriebsdatenbank gelesen.',
        'processes' => $latestByMode,
        'metrics' => $latestMetrics,
        'learning' => [
            'rules' => $learningRows,
            'prevented_total_35d' => $prevented,
            'false_positive_total_35d' => $falsePositives,
            'recurrence_total_35d' => $recurrences,
        ],
        'search' => [
            'raw_candidates' => $value('search.weekly.raw_candidates'),
            'selected_candidates' => $value('search.weekly.selected_candidates'),
            'dropped_candidates' => $value('search.weekly.dropped_candidates'),
            'prevented_candidates' => $value('search.weekly.prevented_candidates'),
            'selected_rate_percent' => $value('search.weekly.selected_rate_percent'),
            'dropped_rate_percent' => $value('search.weekly.dropped_rate_percent'),
            'intake_appended' => $value('intake.manual.appended_items'),
            'intake_skipped' => $value('intake.manual.skipped_items'),
        ],
        'funnel' => [
            'available' => count($funnelMetrics) > 0,
            'metrics' => $funnelMetrics,
            'gsc_rows' => $value('growth.gsc_rows'),
            'ga4_rows' => $value('growth.ga4_rows'),
            'value_metric_rows' => $value('growth.value_metric_rows'),
            'message' => count($funnelMetrics) > 0
                ? 'Vorhandene Search-, Intake-, Search-Console- und GA4-Signale sind angebunden.'
                : 'Noch keine belastbaren Search-/Growth-/Funnel-Metriken in der Betriebsdatenbank.',
        ],
    ];
}

function be_cc_process_health(array $contentOps): array
{
    if (empty($contentOps['available'])) {
        return ['status' => 'unknown', 'message' => (string)($contentOps['message'] ?? 'Prozesszustand unbekannt.'), 'items' => []];
    }
    $expected = [
        'weekly_ki_websearch' => ['label' => 'KI-Eventsuche', 'max_age_hours' => 24 * 9, 'required' => true],
        'manual_ki_intake' => ['label' => 'KI-Intake', 'max_age_hours' => 24 * 9, 'required' => false],
        'content_quality_audit' => ['label' => 'Content-Prüfung', 'max_age_hours' => 50, 'required' => true],
        'inbox_cleanup' => ['label' => 'Inbox-Bereinigung', 'max_age_hours' => 50, 'required' => false],
        'growth_intelligence' => ['label' => 'Growth-/SEO-Auswertung', 'max_age_hours' => 24 * 9, 'required' => true],
    ];
    $items = [];
    $attention = $unknown = 0;
    foreach ($expected as $mode => $config) {
        $run = $contentOps['processes'][$mode] ?? null;
        if (!$run) {
            $status = !empty($config['required']) ? 'unknown' : 'optional';
            if ($status === 'unknown') $unknown++;
            $items[] = ['key' => $mode, 'label' => $config['label'], 'status' => $status, 'message' => !empty($config['required']) ? 'Noch kein Lauf nachgewiesen.' : 'Kein Lauf erforderlich, solange kein entsprechender Input vorlag.'];
            continue;
        }
        $runStatus = strtolower((string)($run['status'] ?? 'unknown'));
        $failed = str_contains($runStatus, 'fail')
            || str_contains($runStatus, 'error')
            || str_contains($runStatus, 'missing')
            || str_contains($runStatus, 'partial_run');
        $lastRun = null;
        try { $lastRun = new DateTimeImmutable((string)$run['generated_at_utc']); } catch (Throwable) {}
        $stale = !$lastRun || $lastRun < new DateTimeImmutable('-' . (int)$config['max_age_hours'] . ' hours');
        $status = $failed || $stale ? 'attention' : 'ok';
        if ($status === 'attention') $attention++;
        $workGenerated = !empty($run['action_required']) && !$failed;
        $message = $failed
            ? 'Der letzte Lauf meldet einen technischen oder unvollständigen Zustand.'
            : ($stale
                ? 'Der letzte erfolgreiche Lauf ist älter als erwartet.'
                : ($workGenerated ? 'Lauf erfolgreich; fachliche Folgearbeit wurde erzeugt.' : 'Letzter erfasster Lauf ohne bekannten Handlungsbedarf.'));
        $items[] = [
            'key' => $mode,
            'label' => $config['label'],
            'status' => $status,
            'run_status' => $runStatus,
            'last_run_at' => (string)($run['generated_at_utc'] ?? ''),
            'run_url' => (string)($run['github_run_url'] ?? ''),
            'work_generated' => $workGenerated,
            'message' => $message,
        ];
    }
    $overall = $attention > 0 ? 'attention' : ($unknown > 0 ? 'unknown' : 'ok');
    return [
        'status' => $overall,
        'message' => $attention > 0
            ? $attention . ' Prozessbereiche benötigen technische Aufmerksamkeit.'
            : ($unknown > 0 ? $unknown . ' erforderliche Prozessbereiche sind noch nicht durch einen aktuellen Lauf bestätigt.' : 'Alle erforderlichen Kernprozesse melden einen aktuellen, technisch unauffälligen Lauf.'),
        'items' => $items,
    ];
}
