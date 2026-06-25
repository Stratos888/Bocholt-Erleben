<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-audit/update.php | Zweck: bearbeitet Content-Verification-Faelle aus der internen Inbox und aktualisiert bei Bedarf sichere Sheet-Event-Felder; Umfang: geschuetzter Write-Endpunkt fuer Content_Audit und Events === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

function cau_request_payload(): array
{
    $raw = file_get_contents('php://input');
    $payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    return is_array($payload) ? $payload : [];
}

function cau_valid_sheet_row_number($value): int
{
    $rowNumber = (int)$value;
    if ($rowNumber < 4 || $rowNumber > 10000) {
        throw new RuntimeException('Invalid audit row number.');
    }

    return $rowNumber;
}

function cau_column_letter(int $indexZeroBased): string
{
    $index = $indexZeroBased + 1;
    $letter = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = intdiv($index - $mod, 26);
    }

    return $letter;
}

function cau_now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s');
}

function cau_next_review_date(int $days): string
{
    $safeDays = max(1, min(180, $days));
    return gmdate('Y-m-d', time() + ($safeDays * 86400));
}

function cau_read_header(string $range): array
{
    $response = be_google_sheets_values_get($range);
    $values = $response['values'] ?? [];
    $header = is_array($values[0] ?? null) ? array_map(static fn($value) => trim((string)$value), $values[0]) : [];
    $index = [];
    foreach ($header as $position => $name) {
        if ($name !== '') {
            $index[$name] = $position;
        }
    }

    return [$header, $index];
}

function cau_update_cells_by_header(string $tabName, int $rowNumber, array $updates): void
{
    [, $index] = cau_read_header($tabName . '!A3:ZZ3');
    foreach ($updates as $name => $value) {
        if (!isset($index[$name])) {
            continue;
        }
        $columnLetter = cau_column_letter((int)$index[$name]);
        be_google_sheets_values_update($tabName . '!' . $columnLetter . $rowNumber . ':' . $columnLetter . $rowNumber, [[(string)$value]]);
    }
}

function cau_mark_audit_row(string $tabName, int $rowNumber, string $status, string $note, array $extra = []): void
{
    $updates = array_merge([
        'status' => $status,
        'review_note' => $note,
    ], $extra);
    cau_update_cells_by_header($tabName, $rowNumber, $updates);
}

function cau_events_table(): array
{
    $events = be_google_sheets_values_get('Events!A:ZZ');
    $values = $events['values'] ?? [];
    if (!is_array($values) || count($values) < 2) {
        throw new RuntimeException('Events tab is empty.');
    }

    $header = array_map(static fn($value) => trim((string)$value), is_array($values[0]) ? $values[0] : []);
    $index = [];
    foreach ($header as $position => $name) {
        if ($name !== '') {
            $index[$name] = $position;
        }
    }

    if (!isset($index['id'])) {
        throw new RuntimeException('Events tab has no id column.');
    }

    return [$values, $header, $index];
}

function cau_find_event_row_by_id(string $contentId): array
{
    [$values, $header, $index] = cau_events_table();

    for ($i = 1; $i < count($values); $i++) {
        $row = is_array($values[$i]) ? $values[$i] : [];
        $rowId = trim((string)($row[$index['id']] ?? ''));
        if ($rowId === $contentId) {
            return [
                'row_number' => $i + 1,
                'header' => $header,
                'index' => $index,
            ];
        }
    }

    throw new RuntimeException('Matching event row was not found.');
}

function cau_update_event_fields(string $contentId, array $updates): array
{
    if ($contentId === '') {
        throw new RuntimeException('Missing content_id.');
    }

    $target = cau_find_event_row_by_id($contentId);
    $rowNumber = (int)$target['row_number'];
    $index = is_array($target['index']) ? $target['index'] : [];

    $allowed = [
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

    $updatedRanges = [];
    foreach ($updates as $field => $value) {
        $field = trim((string)$field);
        if (!in_array($field, $allowed, true) || !isset($index[$field])) {
            continue;
        }

        $newValue = trim((string)$value);
        if (in_array($field, ['source_url', 'url', 'event_url', 'ticket_url'], true) && $newValue !== '') {
            if (!filter_var($newValue, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $newValue)) {
                throw new RuntimeException('Invalid URL for ' . $field . '.');
            }
        }
        if (in_array($field, ['date', 'endDate', 'end_date'], true) && $newValue !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newValue)) {
                throw new RuntimeException('Invalid date for ' . $field . '. Use YYYY-MM-DD.');
            }
        }

        $columnLetter = cau_column_letter((int)$index[$field]);
        $range = 'Events!' . $columnLetter . $rowNumber . ':' . $columnLetter . $rowNumber;
        be_google_sheets_values_update($range, [[$newValue]]);
        $updatedRanges[] = $range;
    }

    if (!$updatedRanges) {
        throw new RuntimeException('No supported Events fields were updated.');
    }

    return $updatedRanges;
}

try {
    $payload = cau_request_payload();
    $action = trim((string)($payload['action'] ?? ''));
    $tabName = be_content_audit_tab_name();
    $rowNumber = cau_valid_sheet_row_number($payload['row_number'] ?? 0);
    $contentId = trim((string)($payload['content_id'] ?? ''));
    $issueCode = trim((string)($payload['issue_code'] ?? ''));
    $note = trim((string)($payload['review_note'] ?? ''));
    $now = cau_now_iso();
    $nextReviewDays = (int)($payload['next_review_days'] ?? 7);
    $nextReviewAt = cau_next_review_date($nextReviewDays);

    if ($action === 'mark_checked' || $action === 'verify_full') {
        cau_mark_audit_row($tabName, $rowNumber, 'verified', $note !== '' ? $note : 'Über interne Inbox vollständig geprüft.', [
            'verified_at' => $now,
            'next_review_at' => $nextReviewAt,
            'action_state' => 'verified_until_next_review',
        ]);
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
                'next_review_at' => $nextReviewAt,
            ],
        ]);
    }

    if ($action === 'snooze') {
        cau_mark_audit_row($tabName, $rowNumber, 'snoozed', $note !== '' ? $note : 'Für spätere Prüfung zurückgestellt.', [
            'next_review_at' => $nextReviewAt,
            'action_state' => 'snoozed',
        ]);
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
                'next_review_at' => $nextReviewAt,
            ],
        ]);
    }

    if ($action === 'mark_patch_needed') {
        cau_mark_audit_row($tabName, $rowNumber, 'patch_needed', $note !== '' ? $note : 'Repo-Patch für Activity/Visual-Datensatz nötig.', [
            'action_state' => 'patch_needed',
        ]);
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
            ],
        ]);
    }

    if ($action === 'update_event_source_url') {
        $newUrl = trim((string)($payload['new_url'] ?? ''));
        $updatedRanges = cau_update_event_fields($contentId, ['source_url' => $newUrl, 'url' => $newUrl, 'event_url' => $newUrl]);
        $doneNote = $note !== '' ? $note : sprintf('Event-URL über interne Inbox korrigiert: %s', $newUrl);
        cau_mark_audit_row($tabName, $rowNumber, 'corrected', $doneNote, [
            'verified_at' => $now,
            'next_review_at' => $nextReviewAt,
            'action_state' => 'event_sheet_corrected',
        ]);

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
                'content_id' => $contentId,
                'updated_ranges' => $updatedRanges,
                'issue_code' => $issueCode,
            ],
        ]);
    }

    if ($action === 'update_event_fields') {
        $eventUpdates = $payload['event_updates'] ?? [];
        if (!is_array($eventUpdates)) {
            throw new RuntimeException('event_updates must be an object.');
        }

        $updatedRanges = cau_update_event_fields($contentId, $eventUpdates);
        $doneNote = $note !== '' ? $note : 'Event-Felder über interne Inbox korrigiert und geprüft.';
        cau_mark_audit_row($tabName, $rowNumber, 'corrected', $doneNote, [
            'verified_at' => $now,
            'next_review_at' => $nextReviewAt,
            'action_state' => 'event_sheet_corrected',
        ]);

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
                'content_id' => $contentId,
                'updated_ranges' => $updatedRanges,
                'issue_code' => $issueCode,
            ],
        ]);
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $error) {
    be_json_response(400, [
        'status' => 'error',
        'message' => 'Content audit update failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}
/* === END FILE: api/content-audit/update.php === */
