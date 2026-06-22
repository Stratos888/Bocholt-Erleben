<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-audit/update.php | Zweck: bearbeitet Content-Quality-Befunde aus der internen Inbox und aktualisiert bei Bedarf sichere Sheet-Event-Quellen; Umfang: geschuetzter Write-Endpunkt fuer Content_Audit und Events === */

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
    if ($rowNumber < 4 || $rowNumber > 5000) {
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

function cau_mark_audit_row(string $tabName, int $rowNumber, string $status, string $note): void
{
    be_google_sheets_values_update($tabName . '!B' . $rowNumber . ':B' . $rowNumber, [[$status]]);
    be_google_sheets_values_update($tabName . '!R' . $rowNumber . ':R' . $rowNumber, [[$note]]);
}

function cau_find_event_row_by_id(string $contentId): array
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

    $targetColumn = null;
    foreach (['source_url', 'url', 'event_url'] as $candidate) {
        if (isset($index[$candidate])) {
            $targetColumn = $index[$candidate];
            break;
        }
    }

    if ($targetColumn === null) {
        throw new RuntimeException('Events tab has no source_url/url/event_url column.');
    }

    for ($i = 1; $i < count($values); $i++) {
        $row = is_array($values[$i]) ? $values[$i] : [];
        $rowId = trim((string)($row[$index['id']] ?? ''));
        if ($rowId === $contentId) {
            return [
                'row_number' => $i + 1,
                'column_index' => $targetColumn,
                'column_name' => (string)$header[$targetColumn],
            ];
        }
    }

    throw new RuntimeException('Matching event row was not found.');
}

try {
    $payload = cau_request_payload();
    $action = trim((string)($payload['action'] ?? ''));
    $tabName = be_content_audit_tab_name();
    $rowNumber = cau_valid_sheet_row_number($payload['row_number'] ?? 0);
    $contentId = trim((string)($payload['content_id'] ?? ''));
    $issueCode = trim((string)($payload['issue_code'] ?? ''));
    $note = trim((string)($payload['review_note'] ?? ''));

    if ($action === 'mark_checked') {
        cau_mark_audit_row($tabName, $rowNumber, 'checked', $note !== '' ? $note : 'Über interne Inbox geprüft.');
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
            ],
        ]);
    }

    if ($action === 'snooze') {
        cau_mark_audit_row($tabName, $rowNumber, 'snoozed', $note !== '' ? $note : 'Für spätere Prüfung zurückgestellt.');
        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
            ],
        ]);
    }

    if ($action === 'update_event_source_url') {
        if ($contentId === '') {
            throw new RuntimeException('Missing content_id.');
        }

        $newUrl = trim((string)($payload['new_url'] ?? ''));
        if (!filter_var($newUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $newUrl)) {
            throw new RuntimeException('Invalid URL.');
        }

        $eventTarget = cau_find_event_row_by_id($contentId);
        $columnLetter = cau_column_letter((int)$eventTarget['column_index']);
        $eventRange = 'Events!' . $columnLetter . $eventTarget['row_number'] . ':' . $columnLetter . $eventTarget['row_number'];
        be_google_sheets_values_update($eventRange, [[$newUrl]]);

        $doneNote = $note !== ''
            ? $note
            : sprintf('Event-%s über interne Inbox auf %s aktualisiert.', (string)$eventTarget['column_name'], $newUrl);
        cau_mark_audit_row($tabName, $rowNumber, 'checked', $doneNote);

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'action' => $action,
                'row_number' => $rowNumber,
                'content_id' => $contentId,
                'updated_range' => $eventRange,
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
