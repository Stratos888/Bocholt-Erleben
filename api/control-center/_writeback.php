<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';

const BE_CC_INBOX_APPS_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbxU3MYnINbhk-1XFehzGRYeto3f4BH9fyL6RmMlQPwmTt_wciHoESRo27nx1-KSIAos/exec';

function be_cc_jsonp_request(array $params): array
{
    $callback = '__be_cc_' . bin2hex(random_bytes(6));
    $params['callback'] = $callback;
    $url = BE_CC_INBOX_APPS_SCRIPT_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 30, 'ignore_errors' => true]]);
    $raw = file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('Inbox writeback returned no response.');
    }
    $prefix = $callback . '(';
    $start = strpos($raw, $prefix);
    $end = strrpos($raw, ')');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Inbox writeback returned invalid JSONP.');
    }
    $json = substr($raw, $start + strlen($prefix), $end - ($start + strlen($prefix)));
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Inbox writeback returned invalid JSON.');
    }
    return $payload;
}

function be_cc_inbox_token(): string
{
    $unlock = be_cc_jsonp_request(['action' => 'unlock', 'password' => be_review_password()]);
    $token = trim((string)($unlock['token'] ?? ''));
    if (empty($unlock['ok']) || $token === '') {
        throw new RuntimeException('Inbox writeback unlock failed.');
    }
    return $token;
}

function be_cc_writeback_inbox_feed(array $case, string $action, array $payload): void
{
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    if (!is_array($source)) {
        throw new RuntimeException('Inbox source payload is missing.');
    }
    $rowNumber = (int)($source['row_number'] ?? 0);
    if ($rowNumber < 2) {
        throw new RuntimeException('Inbox row number is invalid.');
    }
    $token = be_cc_inbox_token();
    if ($action === 'approve') {
        $result = be_cc_jsonp_request(['action' => 'approve', 'token' => $token, 'row_number' => (string)$rowNumber]);
    } elseif ($action === 'reject') {
        $reason = trim((string)($payload['reason'] ?? 'Redaktionell nicht passend'));
        $result = be_cc_jsonp_request([
            'action' => 'setStatus',
            'token' => $token,
            'row_number' => (string)$rowNumber,
            'status' => 'verwerfen',
            'ablehnungsgrund' => $reason,
        ]);
    } elseif ($action === 'snooze') {
        $result = be_cc_jsonp_request([
            'action' => 'setStatus',
            'token' => $token,
            'row_number' => (string)$rowNumber,
            'status' => 'später prüfen',
            'ablehnungsgrund' => '',
        ]);
    } else {
        return;
    }
    if (empty($result['ok'])) {
        throw new RuntimeException('Inbox writeback failed: ' . trim((string)($result['error'] ?? $result['detail'] ?? 'unknown_error')));
    }
}

function be_cc_column_letter(int $indexZeroBased): string
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

function be_cc_update_sheet_row_by_header(string $tab, int $headerRow, int $rowNumber, array $updates): void
{
    $response = be_google_sheets_values_get($tab . '!A' . $headerRow . ':ZZ' . $headerRow);
    $header = array_map(static fn($value) => trim((string)$value), is_array($response['values'][0] ?? null) ? $response['values'][0] : []);
    $index = array_flip($header);
    foreach ($updates as $name => $value) {
        if (!isset($index[$name])) continue;
        $column = be_cc_column_letter((int)$index[$name]);
        be_google_sheets_values_update($tab . '!' . $column . $rowNumber . ':' . $column . $rowNumber, [[(string)$value]]);
    }
}

function be_cc_find_event_row(string $contentId): array
{
    $response = be_google_sheets_values_get('Events!A:ZZ');
    $values = $response['values'] ?? [];
    $header = array_map(static fn($value) => trim((string)$value), is_array($values[0] ?? null) ? $values[0] : []);
    $index = array_flip($header);
    if (!isset($index['id'])) throw new RuntimeException('Events id column is missing.');
    for ($i = 1; $i < count($values); $i++) {
        $row = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (trim((string)($row[$index['id']] ?? '')) === $contentId) {
            return ['row_number' => $i + 1, 'index' => $index];
        }
    }
    throw new RuntimeException('Matching event row was not found.');
}

function be_cc_update_event_fields(string $contentId, array $updates): void
{
    $allowed = ['title','date','endDate','end_date','time','city','location','address','kategorie','tags','source_url','url','event_url','ticket_url','description','visual_key','visual_motif','image_visual_motif'];
    $target = be_cc_find_event_row($contentId);
    foreach ($updates as $field => $value) {
        if (!in_array($field, $allowed, true) || !isset($target['index'][$field])) continue;
        $clean = trim((string)$value);
        if (in_array($field, ['source_url','url','event_url','ticket_url'], true) && $clean !== '' && (!filter_var($clean, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $clean))) {
            throw new InvalidArgumentException('Invalid URL for ' . $field . '.');
        }
        if (in_array($field, ['date','endDate','end_date'], true) && $clean !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            throw new InvalidArgumentException('Invalid date for ' . $field . '.');
        }
        $column = be_cc_column_letter((int)$target['index'][$field]);
        be_google_sheets_values_update('Events!' . $column . $target['row_number'] . ':' . $column . $target['row_number'], [[$clean]]);
    }
}

function be_cc_writeback_content_audit(array $case, string $action, array $payload): void
{
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    if (!is_array($source)) throw new RuntimeException('Audit source payload is missing.');
    $rowNumber = (int)($source['row_number'] ?? 0);
    if ($rowNumber < 4) {
        $reference = (string)($case['source_reference'] ?? '');
        $rowNumber = (int)($payload['row_number'] ?? 0);
    }
    if ($rowNumber < 4) throw new RuntimeException('Audit row number is invalid.');
    $tab = be_content_audit_tab_name();
    $now = gmdate('Y-m-d\TH:i:s');
    $updates = [];
    if ($action === 'approve') {
        $eventUpdates = is_array($payload['event_updates'] ?? null) ? $payload['event_updates'] : [];
        if ($eventUpdates) {
            be_cc_update_event_fields(trim((string)($source['content_id'] ?? '')), $eventUpdates);
            $updates = ['status' => 'corrected', 'action_state' => 'event_sheet_corrected', 'verification_status' => 'corrected'];
        } else {
            $updates = ['status' => 'verified', 'action_state' => 'verified_until_next_review', 'verification_status' => 'confirmed'];
        }
        $updates += ['verified_at' => $now, 'last_verified_at' => $now, 'verified_by' => 'control_center', 'review_note' => trim((string)($payload['note'] ?? 'Über Steuerzentrale fachlich geprüft.'))];
    } elseif ($action === 'reject') {
        $updates = ['status' => 'ignored', 'action_state' => 'ignored', 'review_note' => trim((string)($payload['reason'] ?? 'Über Steuerzentrale verworfen.'))];
    } elseif ($action === 'snooze') {
        $until = trim((string)($payload['until'] ?? ''));
        $updates = ['status' => 'snoozed', 'action_state' => 'snoozed', 'next_review_at' => $until, 'next_check_at' => $until, 'review_note' => 'Über Steuerzentrale zurückgestellt.'];
    } else {
        return;
    }
    be_cc_update_sheet_row_by_header($tab, 3, $rowNumber, $updates);
}

function be_cc_apply_source_writeback(array $case, string $action, array $payload): void
{
    $sourceSystem = (string)($case['source_system'] ?? '');
    if ($sourceSystem === 'inbox_feed') {
        be_cc_writeback_inbox_feed($case, $action, $payload);
    } elseif ($sourceSystem === 'content_audit') {
        be_cc_writeback_content_audit($case, $action, $payload);
    }
}
