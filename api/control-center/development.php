<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_contracts.php';

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
    $pages = [
        '/' => $root . '/index.html',
        '/events/' => $root . '/events/index.html',
        '/aktivitaeten/' => $root . '/aktivitaeten/index.html',
    ];
    $checks = [];
    $passed = 0;
    $total = 0;
    foreach ($pages as $route => $path) {
        $html = is_file($path) ? (string)file_get_contents($path) : '';
        $pageChecks = [
            'title' => $html !== '' && preg_match('/<title>\s*[^<]{8,}\s*<\/title>/i', $html) === 1,
            'meta_description' => $html !== '' && (
                preg_match('/<meta\s+name=["\']description["\'][^>]+content=["\'][^"\']{40,}["\']/i', $html) === 1
                || preg_match('/<meta\s+content=["\'][^"\']{40,}["\'][^>]+name=["\']description["\']/i', $html) === 1
            ),
            'canonical' => $html !== '' && (
                preg_match('/<link\s+rel=["\']canonical["\'][^>]+href=["\']https:\/\//i', $html) === 1
                || preg_match('/<link\s+href=["\']https:\/\/[^"\']+["\'][^>]+rel=["\']canonical["\']/i', $html) === 1
            ),
        ];
        foreach ($pageChecks as $ok) { $total++; if ($ok) $passed++; }
        $checks[$route] = $pageChecks;
    }
    $infrastructure = [
        'sitemap' => is_file($root . '/sitemap.xml'),
        'robots' => is_file($root . '/robots.txt'),
        'event_feed' => is_file($root . '/data/events.json'),
    ];
    foreach ($infrastructure as $ok) { $total++; if ($ok) $passed++; }
    return [
        'passed' => $passed,
        'total' => $total,
        'coverage_percent' => $total > 0 ? round(($passed / $total) * 100, 1) : 0.0,
        'pages' => $checks,
        'infrastructure' => $infrastructure,
    ];
}

function be_cc_previous_development_snapshot(PDO $pdo): ?array
{
    $row = $pdo->query('SELECT metrics_json, created_at FROM control_development_snapshots ORDER BY created_at DESC, id DESC LIMIT 1')->fetch();
    if (!$row) return null;
    $metrics = json_decode((string)$row['metrics_json'], true);
    if (!is_array($metrics)) return null;
    return ['metrics' => $metrics, 'created_at' => (string)$row['created_at']];
}

function be_cc_store_development_snapshot(PDO $pdo, array $metrics, ?array $previous): void
{
    if ($previous) {
        $last = new DateTimeImmutable($previous['created_at']);
        if ($last > new DateTimeImmutable('-1 hour')) return;
    }
    $stmt = $pdo->prepare('INSERT INTO control_development_snapshots (metrics_json) VALUES (:metrics)');
    $stmt->execute(['metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
}

function be_cc_delta(float|int $current, array $previous, string $key): float
{
    return round((float)$current - (float)($previous[$key] ?? $current), 1);
}

try {
    be_cc_ensure_schema();
    $pdo = be_db();
    $count = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    $quality = be_cc_event_quality_metrics();
    $technicalSeo = be_cc_technical_seo_metrics();
    $activityCount = count(be_cc_activity_items());
    $workpackPath = dirname(__DIR__, 2) . '/data/control_center_repo_workpacks.json';
    $workpacks = [];
    if (is_file($workpackPath)) {
        $decoded = json_decode((string)file_get_contents($workpackPath), true);
        $workpacks = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    }
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

    $snapshotMetrics = [
        'content_coverage' => $coverage,
        'missing_content' => (int)$quality['missingDescription'] + (int)$quality['missingSource'] + (int)$quality['missingDate'],
        'open_quality_reviews' => $openQuality,
        'open_reviews' => $openReviews,
        'blocked_tasks' => $blocked,
        'technical_seo_coverage' => $technicalSeo['coverage_percent'],
        'publication_problems' => $publicationProblems,
    ];
    $previous = be_cc_previous_development_snapshot($pdo);
    $previousMetrics = $previous['metrics'] ?? [];
    $trendAvailable = false;
    if ($previous) {
        $previousAt = new DateTimeImmutable($previous['created_at']);
        $trendAvailable = $previousAt <= new DateTimeImmutable('-1 hour');
    }
    $assessment = be_cc_development_assessment([
        'missing_description' => (int)$quality['missingDescription'],
        'missing_source' => (int)$quality['missingSource'],
        'missing_date' => (int)$quality['missingDate'],
        'open_quality_reviews' => $openQuality,
    ], ['blocked_tasks' => $blocked], $trendAvailable);

    $trends = [
        'available' => $trendAvailable,
        'previous_at' => $trendAvailable ? ($previous['created_at'] ?? null) : null,
        'content_coverage_delta' => be_cc_delta($coverage, $previousMetrics, 'content_coverage'),
        'missing_content_delta' => be_cc_delta($snapshotMetrics['missing_content'], $previousMetrics, 'missing_content'),
        'open_quality_reviews_delta' => be_cc_delta($openQuality, $previousMetrics, 'open_quality_reviews'),
        'open_reviews_delta' => be_cc_delta($openReviews, $previousMetrics, 'open_reviews'),
        'blocked_tasks_delta' => be_cc_delta($blocked, $previousMetrics, 'blocked_tasks'),
        'technical_seo_delta' => be_cc_delta($technicalSeo['coverage_percent'], $previousMetrics, 'technical_seo_coverage'),
        'publication_problems_delta' => be_cc_delta($publicationProblems, $previousMetrics, 'publication_problems'),
    ];
    be_cc_store_development_snapshot($pdo, $snapshotMetrics, $previous);

    $seoMessage = 'Technische Onpage-Basissignale werden geprüft. Search-Console-Kennzahlen sind noch nicht angebunden.';
    be_json_response(200, ['status' => 'ok', 'data' => [
        'generated_at' => gmdate('c'),
        'summary' => $assessment,
        'trends' => $trends,
        'content_quality' => [
            'events_total' => $total,
            'activities_total' => $activityCount,
            'complete_events' => $complete,
            'coverage_percent' => $coverage,
            'missing_description' => (int)$quality['missingDescription'],
            'missing_source' => (int)$quality['missingSource'],
            'missing_date' => (int)$quality['missingDate'],
            'open_quality_reviews' => $openQuality,
        ],
        'automation' => [
            'open_reviews' => $openReviews,
            'waiting_tasks' => $waitingTasks,
            'blocked_tasks' => $blocked,
            'completed_cases' => $completedCases,
            'publication_problems' => $publicationProblems,
            'quality_effect_available' => $trendAvailable,
            'quality_effect_message' => $trendAvailable ? 'Vergleich zu einem zeitlich getrennten Snapshot verfügbar.' : 'Der aktuelle Snapshot bildet zunächst die Vergleichsbasis.',
        ],
        'seo' => [
            'content_basis_percent' => $coverage,
            'onpage_event_coverage_percent' => $coverage,
            'missing_descriptions' => (int)$quality['missingDescription'],
            'missing_sources' => (int)$quality['missingSource'],
            'technical_seo_available' => true,
            'technical_seo' => $technicalSeo,
            'search_console_connected' => false,
            'message' => $seoMessage,
            'search_console_message' => $seoMessage,
        ],
        'product' => ['open_repo_workpacks' => $openWorkpacks, 'workpacks' => array_values(array_slice($workpacks, 0, 8))],
    ]]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Entwicklungsstand konnte nicht geladen werden.', 'error_message' => $error->getMessage()]);
}
