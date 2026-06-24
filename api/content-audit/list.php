<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-audit/list.php | Zweck: liefert offene Content-Verification-Befunde fuer die interne Inbox-Ansicht; Umfang: geschuetzter Read-Endpunkt fuer Content_Audit/Content_Audit_Staging inklusive Event-Felddaten === */

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

/* === BEGIN BLOCK: CONTENT_AUDIT_QUEUE_ROUTING_V1 | Zweck: trennt zentrale Inbox-Navigation von reinen Arbeitspaket-/Warnfaellen; Umfang: Sortier- und Queue-Rollenlogik ohne Datenänderung === */
function cal_workbench_group_rank(string $group): int
{
    return match ($group) {
        'Repo-Datenpatch' => 10,
        'Sheet-/Quellenkorrektur' => 20,
        'Quellenprüfung' => 30,
        'Faktencheck' => 40,
        'KI-Faktencheck' => 45,
        'Activity-Prüfung' => 50,
        'DB-/Anbieter-Review' => 60,
        'Visual-Fit' => 70,
        'Beobachten / Retry' => 80,
        default => 90,
    };
}

function cal_content_audit_queue_role(array $item): string
{
    $category = (string)($item['process_category'] ?? '');
    $severity = (string)($item['severity'] ?? '');

    if ($severity === 'critical' || $severity === 'review_needed') {
        return 'main_queue';
    }

    return match ($category) {
        'fact_check_candidate',
        'ai_verification_candidate',
        'visual_fit_candidate',
        'source_retry_observation' => 'package_only',
        default => 'main_queue',
    };
}

function cal_sort_content_audit_items(array &$items): void
{
    usort($items, static function (array $a, array $b): int {
        $role = strcmp((string)($a['queue_role'] ?? ''), (string)($b['queue_role'] ?? ''));
        if ($role !== 0) {
            return $role;
        }

        $severity = cal_severity_rank((string)($a['severity'] ?? '')) <=> cal_severity_rank((string)($b['severity'] ?? ''));
        if ($severity !== 0) {
            return $severity;
        }

        $group = cal_workbench_group_rank((string)($a['workbench_group'] ?? '')) <=> cal_workbench_group_rank((string)($b['workbench_group'] ?? ''));
        if ($group !== 0) {
            return $group;
        }

        $status = strcmp((string)($a['status'] ?? ''), (string)($b['status'] ?? ''));
        if ($status !== 0) {
            return $status;
        }

        $date = strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
        if ($date !== 0) {
            return $date;
        }

        return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });
}
/* === END BLOCK: CONTENT_AUDIT_QUEUE_ROUTING_V1 === */

function cal_read_table_indexed(string $range): array
{
    $response = be_google_sheets_values_get($range);
    $values = $response['values'] ?? [];
    if (!is_array($values) || count($values) < 1) {
        return [[], [], []];
    }

    $header = is_array($values[0] ?? null) ? array_map(static fn($value) => trim((string)$value), $values[0]) : [];
    $index = [];
    foreach ($header as $position => $name) {
        if ($name !== '') {
            $index[$name] = $position;
        }
    }

    return [$values, $header, $index];
}

function cal_event_lookup_by_id(): array
{
    try {
        [$values, $header, $index] = cal_read_table_indexed('Events!A:ZZ');
    } catch (Throwable) {
        return [];
    }

    if (!isset($index['id']) || count($values) < 2) {
        return [];
    }

    $wanted = [
        'id',
        'title',
        'date',
        'endDate',
        'end_date',
        'time',
        'city',
        'location',
        'address',
        'kategorie',
        'tags',
        'source_url',
        'url',
        'event_url',
        'ticket_url',
        'description',
        'visual_key',
        'visual_motif',
        'image_visual_motif',
    ];

    $out = [];
    for ($i = 1; $i < count($values); $i++) {
        $row = is_array($values[$i]) ? $values[$i] : [];
        $id = cal_cell($row, $index, 'id');
        if ($id === '') {
            continue;
        }

        $fields = [];
        foreach ($wanted as $name) {
            if (isset($index[$name])) {
                $fields[$name] = cal_cell($row, $index, $name);
            }
        }
        $fields['_row_number'] = (string)($i + 1);
        $out[$id] = $fields;
    }

    return $out;
}


function cal_source_suggestion_lookup(): array
{
    $path = dirname(__DIR__, 2) . '/data/content_source_suggestions.json';
    if (!is_file($path)) {
        return [];
    }

    $payload = json_decode((string)file_get_contents($path), true);
    if (!is_array($payload)) {
        return [];
    }

    $events = $payload['events'] ?? [];
    return is_array($events) ? $events : [];
}

function cal_curated_source_suggestion(array $lookup, string $contentType, string $contentId): array
{
    if ($contentType !== 'event' || $contentId === '' || !isset($lookup[$contentId]) || !is_array($lookup[$contentId])) {
        return [];
    }

    $item = $lookup[$contentId];
    return [
        'suggested_url' => trim((string)($item['suggested_url'] ?? '')),
        'suggested_url_label' => trim((string)($item['label'] ?? $item['suggested_url_label'] ?? '')),
        'suggestion_reason' => trim((string)($item['reason'] ?? $item['suggestion_reason'] ?? '')),
    ];
}

function cal_suggested_redirect_url(string $issueText): string
{
    if (preg_match('/->\s*(https?:\/\/\S+)/u', $issueText, $match)) {
        return rtrim((string)$match[1], " \t\n\r\0\x0B.,)");
    }

    return '';
}

try {
    $tabName = be_content_audit_tab_name();
    $response = be_google_sheets_values_get($tabName . '!A:AZ');
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

    $eventById = cal_event_lookup_by_id();
    $sourceSuggestionByEventId = cal_source_suggestion_lookup();

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
    $hiddenStatuses = ['done', 'checked', 'verified', 'corrected', 'resolved', 'ignored', 'archived', 'snoozed'];
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
        $suggestedUrl = cal_cell($row, $index, 'suggested_url');
        $suggestedUrlLabel = cal_cell($row, $index, 'suggested_url_label');
        $suggestionReason = cal_cell($row, $index, 'suggestion_reason');
        $suggestedVisualKey = cal_cell($row, $index, 'suggested_visual_key');
        $suggestedVisualMotif = cal_cell($row, $index, 'suggested_visual_motif');
        $suggestedVisualMotifRole = cal_cell($row, $index, 'suggested_visual_motif_role');
        $visualAssetStatus = cal_cell($row, $index, 'visual_asset_status');
        $evidenceStatus = cal_cell($row, $index, 'evidence_status');
        $evidenceSummary = cal_cell($row, $index, 'evidence_summary');
        $evidenceCheckedFields = cal_cell($row, $index, 'evidence_checked_fields');
        $evidenceMissingFields = cal_cell($row, $index, 'evidence_missing_fields');
        $evidenceFieldStatuses = cal_cell($row, $index, 'evidence_field_statuses');

        $curatedSuggestion = cal_curated_source_suggestion($sourceSuggestionByEventId, $contentType, $contentId);
        if ($suggestedUrl === '' && ($curatedSuggestion['suggested_url'] ?? '') !== '') {
            $suggestedUrl = $curatedSuggestion['suggested_url'];
        }
        if ($suggestedUrlLabel === '' && ($curatedSuggestion['suggested_url_label'] ?? '') !== '') {
            $suggestedUrlLabel = $curatedSuggestion['suggested_url_label'];
        }
        if ($suggestionReason === '' && ($curatedSuggestion['suggestion_reason'] ?? '') !== '') {
            $suggestionReason = $curatedSuggestion['suggestion_reason'];
        }
        if ($suggestedUrl === '') {
            $suggestedUrl = cal_suggested_redirect_url($issueText);
        }

        $eventFields = [];
        if ($contentType === 'event' && $contentId !== '' && isset($eventById[$contentId])) {
            $eventFields = $eventById[$contentId];
        }

        $item = [
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
            'process_category' => cal_cell($row, $index, 'process_category'),
            'correction_owner' => cal_cell($row, $index, 'correction_owner'),
            'workbench_group' => cal_cell($row, $index, 'workbench_group'),
            'automation_policy' => cal_cell($row, $index, 'automation_policy'),
            'content_id' => $contentId,
            'title' => cal_cell($row, $index, 'title'),
            'date' => cal_cell($row, $index, 'date'),
            'issue_code' => $issueCode,
            'issue_text' => $issueText,
            'recommended_action' => cal_cell($row, $index, 'recommended_action'),
            'source_url' => $sourceUrl,
            'suggested_url' => $suggestedUrl,
            'suggested_url_label' => $suggestedUrlLabel,
            'suggestion_reason' => $suggestionReason,
            'suggested_visual_key' => $suggestedVisualKey,
            'suggested_visual_motif' => $suggestedVisualMotif,
            'suggested_visual_motif_role' => $suggestedVisualMotifRole,
            'visual_asset_status' => $visualAssetStatus,
            'evidence_status' => $evidenceStatus,
            'evidence_summary' => $evidenceSummary,
            'evidence_checked_fields' => $evidenceCheckedFields,
            'evidence_missing_fields' => $evidenceMissingFields,
            'evidence_field_statuses' => $evidenceFieldStatuses,
            'verification_key' => cal_cell($row, $index, 'verification_key'),
            'verification_status' => cal_cell($row, $index, 'verification_status'),
            'verified_by' => cal_cell($row, $index, 'verified_by'),
            'last_verified_at' => cal_cell($row, $index, 'last_verified_at'),
            'verified_until' => cal_cell($row, $index, 'verified_until'),
            'verification_reason' => cal_cell($row, $index, 'verification_reason'),
            'better_source_url' => cal_cell($row, $index, 'better_source_url'),
            'field_findings_json' => cal_cell($row, $index, 'field_findings_json'),
            'source_fingerprint' => cal_cell($row, $index, 'source_fingerprint'),
            'content_fingerprint' => cal_cell($row, $index, 'content_fingerprint'),
            'next_check_at' => cal_cell($row, $index, 'next_check_at'),
            'ai_candidate_priority' => cal_cell($row, $index, 'ai_candidate_priority'),
            'ai_candidate_reason' => cal_cell($row, $index, 'ai_candidate_reason'),
            'public_url' => cal_cell($row, $index, 'public_url'),
            'first_seen_at' => cal_cell($row, $index, 'first_seen_at'),
            'last_seen_at' => cal_cell($row, $index, 'last_seen_at'),
            'occurrence_count' => cal_cell($row, $index, 'occurrence_count'),
            'auto_fix_allowed' => cal_cell($row, $index, 'auto_fix_allowed'),
            'auto_fix_done' => cal_cell($row, $index, 'auto_fix_done'),
            'review_note' => cal_cell($row, $index, 'review_note'),
            'verified_at' => cal_cell($row, $index, 'verified_at'),
            'next_review_at' => cal_cell($row, $index, 'next_review_at'),
            'action_state' => cal_cell($row, $index, 'action_state'),
            'url' => cal_cell($row, $index, 'public_url'),
            'event_fields' => $eventFields,
        ];
        $item['queue_role'] = cal_content_audit_queue_role($item);
        $items[] = $item;
    }

    cal_sort_content_audit_items($items);

    $mainItems = array_values(array_filter($items, static fn(array $item): bool => (string)($item['queue_role'] ?? '') !== 'package_only'));
    $packageItems = $items;

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'items' => $mainItems,
            'package_items' => $packageItems,
            'total' => count($mainItems),
            'package_total' => count($packageItems),
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
