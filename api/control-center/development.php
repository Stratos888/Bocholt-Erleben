<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_content_source.php';

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

try {
    be_cc_ensure_schema();
    $pdo = be_db();
    $count = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    $quality = be_cc_event_quality_metrics();
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

    be_json_response(200, ['status' => 'ok', 'data' => [
        'generated_at' => gmdate('c'),
        'summary' => ['status' => $blocked > 0 ? 'attention' : 'stable', 'label' => $blocked > 0 ? 'Aufmerksamkeit erforderlich' : 'Projektentwicklung stabil'],
        'content_quality' => [
            'events_total' => $total,
            'activities_total' => $activityCount,
            'complete_events' => $complete,
            'coverage_percent' => $coverage,
            'missing_description' => (int)$quality['missingDescription'],
            'missing_source' => (int)$quality['missingSource'],
            'missing_date' => (int)$quality['missingDate'],
            'open_quality_reviews' => $count("SELECT COUNT(*) FROM control_cases WHERE source_system='content_audit' AND state NOT IN ('done','rejected','parked')"),
        ],
        'automation' => [
            'open_reviews' => $count("SELECT COUNT(*) FROM control_cases WHERE case_type='intake' AND state NOT IN ('done','rejected','parked')"),
            'waiting_tasks' => $count("SELECT COUNT(*) FROM control_cases WHERE case_type='task' AND state IN ('waiting','snoozed')"),
            'blocked_tasks' => $blocked,
            'completed_cases' => $count("SELECT COUNT(*) FROM control_cases WHERE state='done'"),
        ],
        'seo' => [
            'onpage_event_coverage_percent' => $coverage,
            'missing_descriptions' => (int)$quality['missingDescription'],
            'missing_sources' => (int)$quality['missingSource'],
            'search_console_connected' => false,
            'search_console_message' => 'Search-Console-Kennzahlen sind noch nicht angebunden. Es werden ausschließlich belastbare Onpage-Signale angezeigt.',
        ],
        'product' => ['open_repo_workpacks' => $openWorkpacks, 'workpacks' => array_values(array_slice($workpacks, 0, 8))],
    ]]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Entwicklungsstand konnte nicht geladen werden.', 'error_message' => $error->getMessage()]);
}
