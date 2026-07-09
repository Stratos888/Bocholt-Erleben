<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-dashboard.php | Zweck: liefert das mobile interne Content-/SEO-/KI-/Inbox-/System-Dashboard aus persistierten Content-Ops-Runs; Umfang: read-only, review-passwortgeschuetzter JSON-Endpunkt === */
require __DIR__ . '/_bootstrap.php';

function dash_key(string $value, int $max = 80): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_\-.]+/', '_', $value) ?? '';
    return substr(trim($value, '_-.'), 0, $max);
}
function dash_json(?string $raw): array {
    if ($raw === null || trim($raw) === '') { return []; }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function dash_int(mixed $value): int {
    if ($value === null || $value === '') { return 0; }
    return (int)round((float)str_replace(',', '.', (string)$value));
}
function dash_days_old(string $utc): ?float {
    if (trim($utc) === '') { return null; }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return max(0, ($now->getTimestamp() - $dt->getTimestamp()) / 86400);
    } catch (Throwable $e) {
        return null;
    }
}
function dash_format_age(?float $days): string {
    if ($days === null) { return 'unbekannt'; }
    if ($days < 1 / 24) { return 'gerade eben'; }
    if ($days < 1) { return max(1, (int)round($days * 24)) . ' Std.'; }
    return (int)floor($days) . ' Tage';
}
function dash_latest_by_source(array $runs): array {
    $latest = [];
    foreach ($runs as $run) {
        $source = (string)($run['source_mode'] ?? '');
        if ($source !== '' && !isset($latest[$source])) {
            $latest[$source] = $run;
        }
    }
    return $latest;
}
function dash_run_status(?array $run): array {
    if (!$run) {
        return ['state' => 'missing', 'label' => 'fehlt', 'reason' => 'Noch kein persistierter Lauf vorhanden.'];
    }
    $status = (string)($run['status'] ?? '');
    $age = dash_days_old((string)($run['generated_at_utc'] ?? ''));
    if ($age !== null && $age > 3) {
        return ['state' => 'stale', 'label' => 'veraltet', 'reason' => 'Letzter Lauf ist älter als 3 Tage.'];
    }
    if (str_contains($status, 'missing') || str_contains($status, 'error') || str_contains($status, 'failed')) {
        return ['state' => 'warning', 'label' => 'prüfen', 'reason' => 'Der letzte Lauf meldet Auffälligkeiten.'];
    }
    return ['state' => 'ok', 'label' => 'OK', 'reason' => 'Letzter Lauf ist aktuell und persistiert.'];
}
function dash_metric_dates(PDO $pdo, string $env): array {
    $stmt = $pdo->prepare("SELECT metric_date, metric_key, dimension_key, metric_value, source_mode FROM content_ops_metric_daily WHERE environment = ? AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 186 DAY) ORDER BY metric_date ASC, metric_key ASC");
    $stmt->execute([$env]);
    $rows = $stmt->fetchAll() ?: [];
    $dates = [];
    foreach ($rows as $row) {
        $date = (string)($row['metric_date'] ?? '');
        if ($date !== '') { $dates[$date] = true; }
    }
    return ['rows' => $rows, 'date_count' => count($dates), 'dates' => array_keys($dates)];
}
function dash_trend_text(array $metricBundle): string {
    if (($metricBundle['date_count'] ?? 0) < 2) {
        return 'Startwert: Entwicklung wird ab den nächsten Läufen belastbar.';
    }
    return 'Trenddaten vorhanden: Entwicklung kann über 7/28 Tage bewertet werden.';
}
function dash_source_label(string $source): string {
    return match ($source) {
        'content_quality_audit' => 'Content-Prüfung',
        'weekly_ki_websearch' => 'KI-Suche',
        'manual_ki_intake' => 'KI-Import',
        'inbox_cleanup' => 'Inbox',
        'growth_intelligence' => 'Wachstum / SEO',
        default => $source,
    };
}
function dash_task(string $id, string $title, int $count, string $area, string $priority, string $why, string $next, bool $aiUseful = false): array {
    return [
        'id' => $id,
        'title' => $title,
        'count' => $count,
        'area' => $area,
        'priority' => $priority,
        'why' => $why,
        'next_action' => $next,
        'ai_useful' => $aiUseful,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

be_require_review_access();

$env = dash_key((string)($_GET['env'] ?? 'staging'), 32) ?: 'staging';

try {
    $pdo = be_db();

    $runsStmt = $pdo->prepare("SELECT id, run_fingerprint, generated_at_utc, environment, branch_name, workflow_name, github_run_id, github_run_url, source_mode, status, action_required, summary_json, metrics_json, findings_json, created_at FROM content_ops_run WHERE environment = ? ORDER BY generated_at_utc DESC, id DESC LIMIT 80");
    $runsStmt->execute([$env]);
    $runs = $runsStmt->fetchAll() ?: [];

    $latest = dash_latest_by_source($runs);
    $metricBundle = dash_metric_dates($pdo, $env);

    $content = $latest['content_quality_audit'] ?? null;
    $contentSummary = $content ? dash_json((string)$content['summary_json']) : [];
    $decisionCounts = is_array($contentSummary['decision_class_counts'] ?? null) ? $contentSummary['decision_class_counts'] : [];

    $needsPatch = dash_int($decisionCounts['needs_patch'] ?? 0);
    $needsSource = dash_int($decisionCounts['needs_source'] ?? 0);
    $needsVisual = dash_int($decisionCounts['needs_visual_fix'] ?? 0);
    $watch = dash_int($decisionCounts['watch'] ?? 0);
    $done = dash_int($decisionCounts['done'] ?? 0);
    $attention = $needsPatch + $needsSource + $needsVisual;
    $attentionWithWatch = $attention + $watch;

    $weekly = $latest['weekly_ki_websearch'] ?? null;
    $weeklySummary = $weekly ? dash_json((string)$weekly['summary_json']) : [];
    $kiCandidates = dash_int($weeklySummary['selected_candidates'] ?? 0);

    $manual = $latest['manual_ki_intake'] ?? null;
    $manualSummary = $manual ? dash_json((string)$manual['summary_json']) : [];
    $manualAppended = dash_int($manualSummary['appended_count'] ?? 0);

    $inbox = $latest['inbox_cleanup'] ?? null;
    $inboxSummary = $inbox ? dash_json((string)$inbox['summary_json']) : [];
    $inboxOpen = dash_int($inboxSummary['remaining_open_count'] ?? 0);

    $growth = $latest['growth_intelligence'] ?? null;
    $growthSummary = $growth ? dash_json((string)$growth['summary_json']) : [];
    $growthItems = dash_int($growthSummary['items_created'] ?? 0);
    $gscRows = dash_int($growthSummary['gsc_rows'] ?? 0);
    $ga4Rows = dash_int($growthSummary['ga4_rows'] ?? 0);

    $runStates = [];
    foreach (['content_quality_audit', 'weekly_ki_websearch', 'manual_ki_intake', 'inbox_cleanup', 'growth_intelligence'] as $source) {
        $run = $latest[$source] ?? null;
        $state = dash_run_status($run);
        $runStates[] = [
            'source_mode' => $source,
            'label' => dash_source_label($source),
            'state' => $state['state'],
            'status_label' => $state['label'],
            'reason' => $state['reason'],
            'last_run' => $run['generated_at_utc'] ?? '',
            'age_label' => dash_format_age($run ? dash_days_old((string)$run['generated_at_utc']) : null),
            'github_run_url' => $run['github_run_url'] ?? '',
            'workflow_name' => $run['workflow_name'] ?? '',
            'raw_status' => $run['status'] ?? '',
        ];
    }

    $systemProblems = 0;
    foreach ($runStates as $state) {
        if (!in_array($state['state'], ['ok'], true)) { $systemProblems++; }
    }

    $tasks = [];
    if ($needsPatch > 0) {
        $tasks[] = dash_task('content_patch', 'Inhalte korrigieren', $needsPatch, 'Qualität', 'hoch', 'Diese Fälle betreffen direkt Daten, Termine, Texte oder fachliche Korrekturen.', 'Zuerst prüfen und über Inbox oder Content-Patch bereinigen.', true);
    }
    if ($needsSource > 0) {
        $tasks[] = dash_task('content_source', 'Quellen prüfen', $needsSource, 'Qualität', 'hoch', 'Ohne belastbare Quelle kann die Automatik nicht sicher entscheiden.', 'Offizielle Quelle suchen oder Fall zur KI-Recherche geben.', true);
    }
    if ($needsVisual > 0) {
        $tasks[] = dash_task('content_visual', 'Bilder prüfen', $needsVisual, 'Qualität', 'mittel', 'Falsche Motive sind sofort sichtbar und schwächen den Premium-Eindruck.', 'Bildprozess oder Visual-Key prüfen lassen.', true);
    }
    if ($kiCandidates > 0) {
        $tasks[] = dash_task('ki_candidates', 'KI-Funde prüfen', $kiCandidates, 'KI-Suche', 'mittel', 'Die Suche hat Kandidaten gefunden, die nicht automatisch live gehen sollen.', 'Kandidaten fachlich prüfen und übernehmen oder verwerfen.', true);
    }
    if ($manualAppended > 0) {
        $tasks[] = dash_task('manual_intake', 'Neue Inbox-Einträge prüfen', $manualAppended, 'Inbox', 'hoch', 'Neue Einträge warten auf eine Entscheidung.', 'Inbox öffnen und Fälle entscheiden.', false);
    }
    if ($growthItems > 0) {
        $tasks[] = dash_task('growth_items', 'SEO-/Wachstumschancen prüfen', $growthItems, 'Wachstum', 'mittel', 'Es gibt neue Hinweise, wie die App sichtbarer oder relevanter werden kann.', 'Monatlich mit KI bewerten lassen und sinnvolle Maßnahmen ableiten.', true);
    }
    if ($inboxOpen > 0) {
        $tasks[] = dash_task('inbox_open', 'Offene Inbox prüfen', $inboxOpen, 'Inbox', 'hoch', 'Offene Inbox-Fälle sind echte manuelle Entscheidungen.', 'Offene Fälle bearbeiten, damit nichts liegen bleibt.', false);
    }
    if ($systemProblems > 0) {
        $tasks[] = dash_task('system_health', 'Automatik prüfen', $systemProblems, 'System', 'hoch', 'Mindestens ein Roboter fehlt, ist veraltet oder meldet Auffälligkeiten.', 'System-Tab prüfen und Fehler gezielt beheben.', true);
    }
    if ($watch > 0) {
        $tasks[] = dash_task('watch', 'Beobachten', $watch, 'Qualität', 'niedrig', 'Diese Fälle brauchen aktuell keine direkte Änderung, sollen aber nicht verschwinden.', 'Beim Monatsreview mit betrachten.', true);
    }

    $overallState = 'ok';
    $headline = 'Prozess greift';
    $explanation = 'Die Läufe sind persistiert, die Content-Prüfung ist differenziert und offene Arbeit wird als konkrete Aufgaben sichtbar.';
    if ($systemProblems > 0) {
        $overallState = 'warning';
        $headline = 'Prozess teilweise prüfen';
        $explanation = 'Die fachlichen Daten sind vorhanden, aber mindestens ein Automatik-Bereich ist nicht vollständig gesund.';
    }
    if (!$content) {
        $overallState = 'blocked';
        $headline = 'Prozess nicht bewertbar';
        $explanation = 'Es fehlt ein persistierter Content-Quality-Lauf als Bewertungsgrundlage.';
    }

    $qualityTotal = $attentionWithWatch + $done;
    $qualityClearPercent = $qualityTotal > 0 ? round(($done / $qualityTotal) * 100) : null;

    be_json_response(200, [
        'status' => 'ok',
        'environment' => $env,
        'generated_at_utc' => gmdate('c'),
        'baseline_date' => '2026-07-08',
        'overall' => [
            'state' => $overallState,
            'headline' => $headline,
            'attention_count' => $attention,
            'attention_with_watch_count' => $attentionWithWatch,
            'next_action' => $tasks[0]['title'] ?? 'Keine manuelle Aktion',
            'explanation' => $explanation,
        ],
        'today' => [
            'content_events_checked' => dash_int($contentSummary['issue_total'] ?? 0),
            'done' => $done,
            'needs_patch' => $needsPatch,
            'needs_source' => $needsSource,
            'needs_visual_fix' => $needsVisual,
            'watch' => $watch,
            'quality_clear_percent' => $qualityClearPercent,
        ],
        'tasks' => $tasks,
        'quality' => [
            'decision_class_counts' => [
                'done' => $done,
                'needs_patch' => $needsPatch,
                'needs_source' => $needsSource,
                'needs_visual_fix' => $needsVisual,
                'watch' => $watch,
            ],
            'trend' => dash_trend_text($metricBundle),
            'evaluation' => $qualityClearPercent === null
                ? 'Noch keine belastbare Qualitätsquote vorhanden.'
                : ($qualityClearPercent >= 75
                    ? 'Die Qualitätsbasis ist gut. Der größte Hebel liegt bei den offenen Korrektur-, Quellen- und Bildfällen.'
                    : 'Die Qualitätsquote ist noch nicht stark genug. Der Prozess sollte priorisiert offene Fälle abbauen.'),
        ],
        'growth' => [
            'growth_items_created' => $growthItems,
            'gsc_rows' => $gscRows,
            'ga4_rows' => $ga4Rows,
            'ki_selected_candidates' => $kiCandidates,
            'evaluation' => $growthItems > 0 || $kiCandidates > 0
                ? 'Es gibt verwertbare Wachstums- oder KI-Signale. Diese sollten nicht automatisch live gehen, sondern gebündelt bewertet werden.'
                : 'Aktuell kein manueller Wachstums- oder KI-Handlungsbedarf aus den letzten persistierten Läufen.',
        ],
        'timeframes' => [
            ['key' => 'today', 'label' => 'Heute', 'purpose' => 'Aktueller Zustand und direkte Aufgaben.'],
            ['key' => '7d', 'label' => '7 Tage', 'purpose' => 'Operative Wirkung der wöchentlichen Prozesse.'],
            ['key' => '28d', 'label' => '28 Tage', 'purpose' => 'Hauptzeitraum für Monatsbewertung.'],
            ['key' => 'baseline', 'label' => 'Seit 08.07.2026', 'purpose' => 'Belastbarer Referenzpunkt seit sauberem Ingest und Entscheidungsklassen.'],
            ['key' => 'launch', 'label' => 'Seit Launch', 'purpose' => 'Meilenstein- und Produktfortschritt, nicht immer harte Messbasis.'],
            ['key' => '6m', 'label' => '6 Monate', 'purpose' => 'Langfristtrend, sobald genügend Historie vorhanden ist.'],
        ],
        'system' => [
            'run_states' => $runStates,
            'history_date_count' => $metricBundle['date_count'],
            'history_dates' => $metricBundle['dates'],
            'diagnostic_note' => 'Technische Details sind bewusst im Systembereich gebündelt, damit die Hauptansicht nutzerorientiert bleibt.',
        ],
    ]);
} catch (Throwable $e) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Content-Ops-Dashboard konnte nicht geladen werden.',
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
    ]);
}
/* === END FILE: api/content-ops-dashboard.php === */
