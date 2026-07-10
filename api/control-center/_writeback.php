<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';
require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_contracts.php';
require_once dirname(__DIR__) . '/growth-backlog-lib.php';

const BE_CC_INBOX_APPS_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbxU3MYnINbhk-1XFehzGRYeto3f4BH9fyL6RmMlQPwmTt_wciHoESRo27nx1-KSIAos/exec';

function be_cc_jsonp_request(array $params): array
{
    $callback = '__be_cc_' . bin2hex(random_bytes(6));
    $params['callback'] = $callback;
    $url = BE_CC_INBOX_APPS_SCRIPT_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 30, 'ignore_errors' => true]]);
    $raw = file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') throw new RuntimeException('Inbox writeback returned no response.');
    $prefix = $callback . '(';
    $start = strpos($raw, $prefix);
    $end = strrpos($raw, ')');
    if ($start === false || $end === false || $end <= $start) throw new RuntimeException('Inbox writeback returned invalid JSONP.');
    $json = substr($raw, $start + strlen($prefix), $end - ($start + strlen($prefix)));
    $payload = json_decode($json, true);
    if (!is_array($payload)) throw new RuntimeException('Inbox writeback returned invalid JSON.');
    return $payload;
}

function be_cc_inbox_token(): string
{
    $unlock = be_cc_jsonp_request(['action' => 'unlock', 'password' => be_review_password()]);
    $token = trim((string)($unlock['token'] ?? ''));
    if (empty($unlock['ok']) || $token === '') throw new RuntimeException('Inbox writeback unlock failed.');
    return $token;
}

function be_cc_writeback_inbox_feed(array $case, string $action, array $payload): void
{
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    if (!is_array($source)) throw new RuntimeException('Inbox source payload is missing.');
    $rowNumber = (int)($source['row_number'] ?? 0);
    if ($rowNumber < 2) throw new RuntimeException('Inbox row number is invalid.');
    $token = be_cc_inbox_token();
    if ($action === 'approve') {
        $result = be_cc_jsonp_request(['action' => 'approve', 'token' => $token, 'row_number' => (string)$rowNumber]);
    } elseif ($action === 'reject') {
        $result = be_cc_jsonp_request(['action' => 'setStatus','token' => $token,'row_number' => (string)$rowNumber,'status' => 'verwerfen','ablehnungsgrund' => trim((string)($payload['reason'] ?? 'Redaktionell nicht passend'))]);
    } elseif ($action === 'snooze') {
        $result = be_cc_jsonp_request(['action' => 'setStatus','token' => $token,'row_number' => (string)$rowNumber,'status' => 'später prüfen','ablehnungsgrund' => '']);
    } else return;
    if (empty($result['ok'])) throw new RuntimeException('Inbox writeback failed: ' . trim((string)($result['error'] ?? $result['detail'] ?? 'unknown_error')));
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
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    [$headerOffset, $index] = be_cc_find_header_row($values);
    if ($headerOffset < 0) throw new RuntimeException('Events header row was not found.');
    $idColumn = $index['id'] ?? $index['event_id'] ?? null;
    if (!is_int($idColumn)) throw new RuntimeException('Events id column is missing.');
    for ($i = $headerOffset + 1; $i < count($values); $i++) {
        $row = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (trim((string)($row[$idColumn] ?? '')) === $contentId) {
            return ['row_number' => $i + 1, 'header_row' => $headerOffset + 1, 'index' => $index, 'row' => $row];
        }
    }
    throw new RuntimeException('Matching event row was not found.');
}

function be_cc_update_event_fields(string $contentId, array $updates): array
{
    $target = be_cc_find_event_row($contentId);
    $resolved = be_cc_validate_event_update($target['index'], $target['row'], $updates);
    $data = [];
    foreach ($resolved as $canonical => $plan) {
        $column = be_cc_column_letter((int)$target['index'][$plan['column_name']]);
        $data[] = [
            'range' => 'Events!' . $column . $target['row_number'] . ':' . $column . $target['row_number'],
            'values' => [[$plan['value']]],
        ];
    }
    $google = be_google_config();
    $url = sprintf('https://sheets.googleapis.com/v4/spreadsheets/%s/values:batchUpdate', rawurlencode($google['sheet_id']));
    be_google_sheets_request('POST', $url, ['valueInputOption' => 'USER_ENTERED', 'data' => $data]);
    return array_keys($resolved);
}

function be_cc_writeback_content_audit(array $case, string $action, array $payload): void
{
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    if (!is_array($source)) throw new RuntimeException('Audit source payload is missing.');
    $rowNumber = (int)($source['row_number'] ?? 0);
    if ($rowNumber < 4) $rowNumber = (int)($payload['row_number'] ?? 0);
    if ($rowNumber < 4) throw new RuntimeException('Audit row number is invalid.');
    $tab = be_content_audit_tab_name();
    $now = gmdate('Y-m-d\TH:i:s');
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
    } else return;
    be_cc_update_sheet_row_by_header($tab, 3, $rowNumber, $updates);
}

function be_cc_writeback_growth_backlog(array $case, string $action, array $payload): void
{
    $id = trim((string)($case['source_reference'] ?? ''));
    if ($id === '') throw new RuntimeException('Backlog source id is missing.');
    [$header, $index, $rows] = gbl_read_table();
    foreach ($rows as $row) {
        if ((string)($row['id'] ?? '') !== $id) continue;
        $now = gbl_now_iso();
        if ($action === 'edit_source') {
            $updates = ['updated_at' => $now];
            if (array_key_exists('title', $payload)) $updates['title'] = trim((string)$payload['title']);
            if (array_key_exists('description', $payload)) {
                $description = trim((string)$payload['description']);
                $updates['short_reason'] = mb_substr($description, 0, 220, 'UTF-8');
                $updates['why_relevant'] = $description;
            }
            if (!empty($payload['priority'])) {
                $priority = mb_strtolower(trim((string)$payload['priority']), 'UTF-8');
                if (in_array($priority, ['hoch','mittel','niedrig'], true)) $updates['priority'] = $priority;
            }
            gbl_update_cells($header, $index, (int)$row['_sheet_row'], $updates);
            return;
        }
        if ($action === 'convert_to_task') {
            gbl_update_cells($header, $index, (int)$row['_sheet_row'], ['status' => 'started', 'updated_at' => $now, 'decision_note' => 'Als konkrete Aufgabe in der Steuerzentrale gestartet.']);
            return;
        }
        if (in_array($action, ['complete','reject'], true)) {
            gbl_update_cells($header, $index, (int)$row['_sheet_row'], [
                'status' => $action === 'complete' ? 'completed' : 'rejected',
                'updated_at' => $now,
                'closed_at' => $now,
                'decision_note' => trim((string)($payload['reason'] ?? $payload['decision_note'] ?? 'Über Steuerzentrale abgeschlossen.')),
            ]);
            return;
        }
        return;
    }
    throw new RuntimeException('Backlog-Punkt nicht gefunden.');
}

function be_cc_apply_source_writeback(array $case, string $action, array $payload): void
{
    $sourceSystem = (string)($case['source_system'] ?? '');
    if ($sourceSystem === 'inbox_feed') be_cc_writeback_inbox_feed($case, $action, $payload);
    elseif ($sourceSystem === 'content_audit') be_cc_writeback_content_audit($case, $action, $payload);
    elseif ($sourceSystem === 'growth_backlog') be_cc_writeback_growth_backlog($case, $action, $payload);
}
