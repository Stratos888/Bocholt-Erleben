<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_contracts.php';
require_once __DIR__ . '/_content_ops.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

function be_cc_event_quality_metrics(): array
{
    $response = be_google_sheets_values_get('Events!A:AZ');
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    [$headerOffset, $index] = be_cc_find_header_row($values);
    if ($headerOffset < 0) return ['total' => 0, 'complete' => 0, 'missingDescription' => 0, 'missingSource' => 0, 'missingDate' => 0];
    $total = $complete = $missingDescription = $missingSource = $missingDate = 0;
    for ($i = $headerOffset + 1; $i < count($values); $i++) {
        $row = is_array($values[$i] ?? null) ? $values[$i] : [];
        $id = be_cc_sheet_value($row, $index, ['id', 'event_id']);
        $title = be_cc_sheet_value($row, $index, ['title']);
        if ($id === '' || $title === '') continue;
        $status = strtolower(be_cc_sheet_value($row, $index, ['status', 'publication_status']));
        if (in_array($status, ['deleted', 'archived'], true)) continue;
        $total++;
        $description = be_cc_sheet_value($row, $index, ['description']);
        $source = be_cc_sheet_value($row, $index, ['source_url', 'url', 'event_url']);
        $date = be_cc_sheet_value($row, $index, ['date', 'start_date']);
        if ($description === '') $missingDescription++;
        if ($source === '') $missingSource++;
        if ($date === '') $missingDate++;
        if ($description !== '' && $source !== '' && $date !== '') $complete++;
    }
    return compact('total', 'complete', 'missingDescription', 'missingSource', 'missingDate');
}

function be_cc_technical_seo_metrics(): array
{
    $root = dirname(__DIR__, 2);
    $pages = ['/' => $root . '/index.html', '/events/' => $root . '/events/index.html', '/aktivitaeten/' => $root . '/aktivitaeten/index.html'];
    $checks = [];
    $passed = $total = 0;
    foreach ($pages as $route => $path) {
        $html = is_file($path) ? (string)file_get_contents($path) : '';
        $pageChecks = [
            'title' => $html !== '' && preg_match('/<title>\s*[^<]{8,}\s*<\/title>/i', $html) === 1,
            'meta_description' => $html !== '' && (preg_match('/<meta\s+name=["\']description["\'][^>]+content=["\'][^"\']{40,}["\']/i', $html) === 1 || preg_match('/<meta\s+content=["\'][^"\']{40,}["\'][^>]+name=["\']description["\']/i', $html) === 1),
            'canonical' => $html !== '' && (preg_match('/<link\s+rel=["\']canonical["\'][^>]+href=["\']https:\/\//i', $html) === 1 || preg_match('/<link\s+href=["\']https:\/\/[^"\']+["\'][^>]+rel=["\']canonical["\']/i', $html) === 1),
        ];
        foreach ($pageChecks as $ok) { $total++; if ($ok) $passed++; }
        $checks[$route] = $pageChecks;
    }
    $infrastructure = ['sitemap' => is_file($root . '/sitemap.xml'), 'robots' => is_file($root . '/robots.txt'), 'event_feed' => is_file($root . '/data/events.json'), 'activity_feed' => is_file($root . '/data/offers.json')];
    foreach ($infrastructure as $ok) { $total++; if ($ok) $passed++; }
    return ['passed' => $passed, 'total' => $total, 'coverage_percent' => $total > 0 ? round(($passed / $total) * 100, 1) : 0.0, 'pages' => $checks, 'infrastructure' => $infrastructure];
}

function be_cc_snapshot_row_to_array(array|false $row): ?array
{
    if (!$row) return null;
    $metrics = json_decode((string)$row['metrics_json'], true);
    return is_array($metrics) ? ['metrics' => $metrics, 'created_at' => (string)$row['created_at']] : null;
}
function be_cc_latest_development_snapshot(PDO $pdo): ?array { return be_cc_snapshot_row_to_array($pdo->query('SELECT metrics_json, created_at FROM control_development_snapshots ORDER BY created_at DESC, id DESC LIMIT 1')->fetch()); }
function be_cc_comparison_development_snapshot(PDO $pdo): ?array { $stmt = $pdo->prepare('SELECT metrics_json, created_at FROM control_development_snapshots WHERE created_at <= :cutoff ORDER BY created_at DESC, id DESC LIMIT 1'); $stmt->execute(['cutoff' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]); return be_cc_snapshot_row_to_array($stmt->fetch()); }
function be_cc_store_development_snapshot(PDO $pdo, array $metrics, ?array $latest): void { if ($latest && new DateTimeImmutable($latest['created_at']) > new DateTimeImmutable('-1 hour')) return; $stmt = $pdo->prepare('INSERT INTO control_development_snapshots (metrics_json) VALUES (:metrics)'); $stmt->execute(['metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]); }
function be_cc_delta(float|int $current, array $previous, string $key): float { return round((float)$current - (float)($previous[$key] ?? $current), 1); }

try {
    be_cc_ensure_schema();
    $pdo = be_db();
    $count = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    $quality = be_cc_event_quality_metrics();
    $technicalSeo = be_cc_technical_seo_metrics();
    $contentOps = be_cc_content_ops_status($pdo);
    $processHealth = be_cc_process_health($contentOps);
    $activityCount = count(be_cc_activity_items());
    $providerPublicEvents = $count("SELECT COUNT(*) FROM submissions WHERE submission_kind='event' AND status='approved' AND approved_at IS NOT NULL AND start_date >= CURRENT_DATE()");
    $workpackPath = dirname(__DIR__, 2) . '/data/control_center_repo_workpacks.json';
    $workpacks = [];
    if (is_file($workpackPath)) { $decoded = json_decode((string)file_get_contents($workpackPath), true); $workpacks = is_array($decoded['items'] ?? null) ? $decoded['items'] : []; }
    $openWorkpacks = count(array_filter($workpacks, static fn(array $item): bool => !in_array(strtolower((string)($item['status'] ?? 'open')), ['done','completed','rejected'], true)));
    $total = (int)$quality['total'];
    $complete = (int)$quality['complete'];
    $coverage = $total > 0 ? round(($complete / $total) * 100, 1) : 0.0;
    $blocked = $count("SELECT COUNT(*) FROM control_cases WHERE state='blocked'");
    $openQuality = $count("SELECT COUNT(*) FROM control_cases WHERE source_system='content_audit' AND state NOT IN ('done','rejected','parked')");
    $openReviews = $count("SELECT COUNT(*) FROM control_cases WHERE case_type='intake' AND state NOT IN ('done','rejected','parked')");
    $waitingTasks = $count("SELECT COUNT(*) FROM control_cases WHERE case_type='task' AND state IN ('waiting','snoozed')");
    $completedCases = $count("SELECT COUNT(*) FROM control_cases WHERE state='done'");
    $publicationProblems = $count("SELECT COUNT(*) FROM control_content_changes WHERE publication_state IN ('deploy_failed','verification_failed')");

    $snapshotMetrics = ['content_coverage' => $coverage, 'missing_content' => (int)$quality['missingDescription'] + (int)$quality['missingSource'] + (int)$quality['missingDate'], 'open_quality_reviews' => $openQuality, 'open_reviews' => $openReviews, 'blocked_tasks' => $blocked, 'technical_seo_coverage' => $technicalSeo['coverage_percent'], 'publication_problems' => $publicationProblems, 'learning_prevented' => (int)($contentOps['learning']['prevented_total_35d'] ?? 0), 'learning_false_positives' => (int)($contentOps['learning']['false_positive_total_35d'] ?? 0)];
    $latest = be_cc_latest_development_snapshot($pdo);
    $comparison = be_cc_comparison_development_snapshot($pdo);
    $comparisonMetrics = $comparison['metrics'] ?? [];
    $trendAvailable = $comparison !== null;
    $assessment = be_cc_development_assessment(['missing_description' => (int)$quality['missingDescription'], 'missing_source' => (int)$quality['missingSource'], 'missing_date' => (int)$quality['missingDate'], 'open_quality_reviews' => $openQuality], ['blocked_tasks' => $blocked], $trendAvailable);
    $trends = ['available' => $trendAvailable, 'previous_at' => $comparison['created_at'] ?? null, 'content_coverage_delta' => be_cc_delta($coverage, $comparisonMetrics, 'content_coverage'), 'missing_content_delta' => be_cc_delta($snapshotMetrics['missing_content'], $comparisonMetrics, 'missing_content'), 'open_quality_reviews_delta' => be_cc_delta($openQuality, $comparisonMetrics, 'open_quality_reviews'), 'open_reviews_delta' => be_cc_delta($openReviews, $comparisonMetrics, 'open_reviews'), 'blocked_tasks_delta' => be_cc_delta($blocked, $comparisonMetrics, 'blocked_tasks'), 'technical_seo_delta' => be_cc_delta($technicalSeo['coverage_percent'], $comparisonMetrics, 'technical_seo_coverage'), 'publication_problems_delta' => be_cc_delta($publicationProblems, $comparisonMetrics, 'publication_problems'), 'learning_prevented_delta' => be_cc_delta($snapshotMetrics['learning_prevented'], $comparisonMetrics, 'learning_prevented'), 'learning_false_positives_delta' => be_cc_delta($snapshotMetrics['learning_false_positives'], $comparisonMetrics, 'learning_false_positives')];
    be_cc_store_development_snapshot($pdo, $snapshotMetrics, $latest);

    $funnel = $contentOps['funnel'] ?? ['available' => false, 'message' => 'Keine Funnel-Daten verfügbar.'];
    $searchConsoleConnected = !empty($funnel['gsc_rows']);
    $seoMessage = $searchConsoleConnected ? 'Search-Console-/Growth-Signale sind aus der Betriebsdatenbank angebunden.' : 'Technische Onpage-Basissignale werden geprüft. Search-Console-Kennzahlen sind noch nicht belastbar vorhanden.';

    be_json_response(200, ['status' => 'ok', 'data' => [
        'generated_at' => gmdate('c'),
        'summary' => $assessment,
        'trends' => $trends,
        'content_quality' => ['events_total' => $total + $providerPublicEvents, 'sheet_events_total' => $total, 'provider_events_total' => $providerPublicEvents, 'activities_total' => $activityCount, 'complete_events' => $complete, 'coverage_percent' => $coverage, 'missing_description' => (int)$quality['missingDescription'], 'missing_source' => (int)$quality['missingSource'], 'missing_date' => (int)$quality['missingDate'], 'open_quality_reviews' => $openQuality],
        'automation' => ['open_reviews' => $openReviews, 'waiting_tasks' => $waitingTasks, 'blocked_tasks' => $blocked, 'completed_cases' => $completedCases, 'publication_problems' => $publicationProblems, 'quality_effect_available' => (bool)$contentOps['available'], 'quality_effect_message' => (string)($contentOps['message'] ?? ''), 'learning' => $contentOps['learning'] ?? [], 'process_health' => $processHealth],
        'seo' => ['content_basis_percent' => $coverage, 'onpage_event_coverage_percent' => $coverage, 'missing_descriptions' => (int)$quality['missingDescription'], 'missing_sources' => (int)$quality['missingSource'], 'technical_seo_available' => true, 'technical_seo' => $technicalSeo, 'search_console_connected' => $searchConsoleConnected, 'funnel' => $funnel, 'message' => $seoMessage, 'search_console_message' => $seoMessage],
        'product' => ['open_repo_workpacks' => $openWorkpacks, 'workpacks' => array_values(array_slice($workpacks, 0, 8))],
    ]]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Entwicklungsstand konnte nicht geladen werden.', 'error_message' => $error->getMessage()]);
}
