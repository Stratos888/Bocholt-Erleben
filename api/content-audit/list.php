<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-audit/list.php | Zweck: liefert offene Content-Quality-Befunde fuer die interne Inbox-Ansicht; Umfang: geschuetzter Read-Endpunkt fuer Content_Audit/Content_Audit_Staging === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

function cal_cell(array $row, array $index, string $name): string
{
    $position = $index[$name] ?? null;
    if (!is_int($position) || $position < 0 || $position >= count($row)) {
        return '';
    }

    return trim((string)$row[$position]);
}

function cal_severity_rank(string $severity): int
{
    return match ($severity) {
        'critical' => 0,
        'review_needed' => 1,
        'warning' => 2,
        default => 3,
    };
}

try {
    $tabName = be_content_audit_tab_name();
    $response = be_google_sheets_values_get($tabName . '!A:R');
    $values = $response['values'] ?? [];

    if (!is_array($values) || count($values) < 3) {
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'items' => [],
                'total' => 0,
                'tab_name' => $tabName,
                'generated_at' => '',
                'message' => 'Content_Audit tab is empty.',
            ],
        ]);
    }

    $metaRow = is_array($values[0] ?? null) ? $values[0] : [];
    $generatedAt = trim((string)($metaRow[1] ?? ''));
    $header = is_array($values[2] ?? null) ? array_map(static fn($value) => trim((string)$value), $values[2]) : [];
    $index = [];
    foreach ($header as $position => $name) {
        if ($name !== '') {
            $index[$name] = $position;
        }
    }

    $items = [];
    $hiddenStatuses = ['done', 'checked', 'resolved', 'ignored', 'archived'];
    $visibleSeverities = ['critical', 'review_needed', 'warning'];

    for ($i = 3; $i < count($values); $i++) {
        $row = is_array($values[$i]) ? $values[$i] : [];
        if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) {
            continue;
        }

        $severity = cal_cell($row, $index, 'severity');
        $status = cal_cell($row, $index, 'status') ?: 'open';
        if (!in_array($severity, $visibleSeverities, true) || in_array($status, $hiddenStatuses, true)) {
            continue;
        }

        $contentType = cal_cell($row, $index, 'content_type');
        $sourceSystem = cal_cell($row, $index, 'source_system');
        $contentId = cal_cell($row, $index, 'content_id');
        $issueCode = cal_cell($row, $index, 'issue_code');
        $sourceUrl = cal_cell($row, $index, 'source_url');
        $issueText = cal_cell($row, $index, 'issue_text');
        $suggestedUrl = '';

        if (preg_match('/->\s*(https?:\/\/\S+)/u', $issueText, $match)) {
            $suggestedUrl = rtrim((string)$match[1], " \t\n\r\0\x0B.,)");
        }

        $items[] = [
            'review_source' => 'content_audit',
            'intake_origin' => 'content_quality',
            'intake_origin_label' => 'Content-Prüfung',
            'row_number' => $i + 1,
            'sheet_tab' => $tabName,
            'generated_at' => $generatedAt,
            'severity' => $severity,
            'status' => $status,
            'content_type' => $contentType,
            'source_system' => $sourceSystem,
            'content_id' => $contentId,
            'title' => cal_cell($row, $index, 'title'),
            'date' => cal_cell($row, $index, 'date'),
            'issue_code' => $issueCode,
            'issue_text' => $issueText,
            'recommended_action' => cal_cell($row, $index, 'recommended_action'),
            'source_url' => $sourceUrl,
            'public_url' => cal_cell($row, $index, 'public_url'),
            'first_seen_at' => cal_cell($row, $index, 'first_seen_at'),
            'last_seen_at' => cal_cell($row, $index, 'last_seen_at'),
            'occurrence_count' => cal_cell($row, $index, 'occurrence_count'),
            'auto_fix_allowed' => cal_cell($row, $index, 'auto_fix_allowed'),
            'auto_fix_done' => cal_cell($row, $index, 'auto_fix_done'),
            'review_note' => cal_cell($row, $index, 'review_note'),
            'url' => cal_cell($row, $index, 'public_url'),
            'suggested_url' => $suggestedUrl,
        ];
    }

    usort($items, static function (array $a, array $b): int {
        $severity = cal_severity_rank((string)($a['severity'] ?? '')) <=> cal_severity_rank((string)($b['severity'] ?? ''));
        if ($severity !== 0) {
            return $severity;
        }

        return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
    });

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'items' => $items,
            'total' => count($items),
            'tab_name' => $tabName,
            'generated_at' => $generatedAt,
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Content audit list could not be loaded.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}
/* === END FILE: api/content-audit/list.php === */
