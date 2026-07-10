<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

function be_cc_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $stmt->execute(['table_name' => $table]);
    return (bool)$stmt->fetchColumn();
}

function be_cc_content_ops_status(PDO $pdo): array
{
    $required = ['content_ops_run','content_ops_metric_daily','feedback_rule_effectiveness_daily'];
    foreach ($required as $table) {
        if (!be_cc_table_exists($pdo, $table)) {
            return [
                'available' => false,
                'message' => 'Content-Ops-Metriken sind in dieser Laufzeit noch nicht initialisiert.',
                'processes' => [],
                'metrics' => [],
                'learning' => [],
                'funnel' => ['available' => false, 'message' => 'Keine belastbaren Growth-/Funnel-Metriken vorhanden.'],
            ];
        }
    }

    $runs = $pdo->query(
        "SELECT source_mode, status, action_required, generated_at_utc, github_run_url
         FROM content_ops_run
         WHERE environment IN ('live','staging')
         ORDER BY generated_at_utc DESC, id DESC
         LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC);
    $latestByMode = [];
    foreach ($runs as $row) {
        $mode = (string)$row['source_mode'];
        if ($mode !== '' && !isset($latestByMode[$mode])) $latestByMode[$mode] = $row;
    }

    $metricRows = $pdo->query(
        "SELECT metric_key, metric_value, metric_date, source_mode, metric_scope, dimension_key
         FROM content_ops_metric_daily
         WHERE metric_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 35 DAY)
         ORDER BY metric_date DESC, id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
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

    $learningRows = $pdo->query(
        "SELECT rule_key, rule_type, rule_class,
                SUM(applied_count) AS applied_count,
                SUM(prevented_count) AS prevented_count,
                SUM(recurrence_count) AS recurrence_count,
                SUM(false_positive_count) AS false_positive_count,
                MAX(metric_date) AS last_date
         FROM feedback_rule_effectiveness_daily
         WHERE metric_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 35 DAY)
         GROUP BY rule_key, rule_type, rule_class
         ORDER BY prevented_count DESC, applied_count DESC
         LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);

    $value = static fn(string $key): ?float => isset($latestMetrics[$key]) ? (float)$latestMetrics[$key]['value'] : null;
    $funnelKeys = [
        'growth.gsc_rows', 'growth.ga4_rows', 'growth.value_metric_rows',
        'weekly.candidates.generated', 'weekly.candidates.accepted', 'weekly.candidates.rejected',
        'intake.manual.appended_items', 'intake.manual.skipped_items',
    ];
    $funnelMetrics = [];
    foreach ($funnelKeys as $key) {
        if (isset($latestMetrics[$key])) $funnelMetrics[$key] = $latestMetrics[$key];
    }

    $prevented = 0;
    $falsePositives = 0;
    $recurrences = 0;
    foreach ($learningRows as $row) {
        $prevented += (int)$row['prevented_count'];
        $falsePositives += (int)$row['false_positive_count'];
        $recurrences += (int)$row['recurrence_count'];
    }

    return [
        'available' => true,
        'message' => 'Content-Ops-Läufe und Lernwirkung werden aus der gemeinsamen Betriebsdatenbank gelesen.',
        'processes' => $latestByMode,
        'metrics' => $latestMetrics,
        'learning' => [
            'rules' => $learningRows,
            'prevented_total_35d' => $prevented,
            'false_positive_total_35d' => $falsePositives,
            'recurrence_total_35d' => $recurrences,
        ],
        'funnel' => [
            'available' => count($funnelMetrics) > 0,
            'metrics' => $funnelMetrics,
            'gsc_rows' => $value('growth.gsc_rows'),
            'ga4_rows' => $value('growth.ga4_rows'),
            'value_metric_rows' => $value('growth.value_metric_rows'),
            'message' => count($funnelMetrics) > 0
                ? 'Vorhandene Search-Console-/GA4-/Intake-Signale sind angebunden.'
                : 'Noch keine belastbaren Growth-/Funnel-Metriken in der Betriebsdatenbank.',
        ],
    ];
}

function be_cc_process_health(array $contentOps): array
{
    if (empty($contentOps['available'])) {
        return ['status' => 'unknown', 'message' => (string)($contentOps['message'] ?? 'Prozesszustand unbekannt.'), 'items' => []];
    }
    $expected = [
        'weekly_ki_websearch' => 'KI-Eventsuche',
        'manual_ki_intake' => 'KI-Intake',
        'content_quality_audit' => 'Content-Prüfung',
        'inbox_cleanup' => 'Inbox-Bereinigung',
        'growth_intelligence' => 'Growth-/SEO-Auswertung',
    ];
    $items = [];
    $attention = 0;
    foreach ($expected as $mode => $label) {
        $run = $contentOps['processes'][$mode] ?? null;
        if (!$run) {
            $items[] = ['key' => $mode, 'label' => $label, 'status' => 'unknown', 'message' => 'Noch kein Lauf nachgewiesen.'];
            $attention++;
            continue;
        }
        $status = strtolower((string)($run['status'] ?? 'unknown'));
        $ok = !str_contains($status, 'fail') && !str_contains($status, 'error') && empty($run['action_required']);
        if (!$ok) $attention++;
        $items[] = [
            'key' => $mode,
            'label' => $label,
            'status' => $ok ? 'ok' : 'attention',
            'run_status' => $status,
            'last_run_at' => (string)($run['generated_at_utc'] ?? ''),
            'run_url' => (string)($run['github_run_url'] ?? ''),
            'message' => $ok ? 'Letzter erfasster Lauf ohne bekannten Handlungsbedarf.' : 'Letzter Lauf benötigt Aufmerksamkeit.',
        ];
    }
    return [
        'status' => $attention > 0 ? 'attention' : 'ok',
        'message' => $attention > 0 ? $attention . ' Prozessbereiche sind nicht vollständig bestätigt.' : 'Alle erfassten Kernprozesse melden einen unauffälligen letzten Lauf.',
        'items' => $items,
    ];
}
