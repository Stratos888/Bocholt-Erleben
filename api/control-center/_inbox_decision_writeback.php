<?php
declare(strict_types=1);

/**
 * Direct, verified status writeback for Inbox decisions that do not require
 * the legacy Apps Script publication workflow.
 */

const BE_CC_INBOX_DIRECT_HTTP_TIMEOUT_SECONDS = 8;
const BE_CC_INBOX_DIRECT_VERIFY_ATTEMPTS = 2;

function be_cc_inbox_direct_updates(array $expected): array
{
    return [
        'status' => trim((string)($expected['status'] ?? '')),
        'ablehnungsgrund' => trim((string)($expected['reason'] ?? '')),
    ];
}

function be_cc_inbox_direct_http_request(string $method, string $url, ?array $body = null): array
{
    $token = be_google_access_token(['https://www.googleapis.com/auth/spreadsheets']);
    $headers = "Authorization: Bearer {$token}\r\nAccept: application/json\r\n";
    $content = '';
    if ($body !== null) {
        $content = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers .= "Content-Type: application/json; charset=utf-8\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $content,
            'timeout' => BE_CC_INBOX_DIRECT_HTTP_TIMEOUT_SECONDS,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $match)) {
            $statusCode = (int)$match[1];
        }
    }
    $payload = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($payload)) {
        throw new RuntimeException('Der direkte Google-Sheets-Zugriff ist fehlgeschlagen oder hat das Zeitlimit überschritten.');
    }
    return $payload;
}

function be_cc_inbox_direct_table_from_values(array $values): array
{
    $headerOffset = -1;
    $index = [];
    foreach ($values as $offset => $candidate) {
        if (!is_array($candidate)) continue;
        $normalized = array_map(static fn($value): string => be_cc_unicode_lower(trim((string)$value)), $candidate);
        $candidateIndex = array_flip($normalized);
        $hasIdentity = isset($candidateIndex['id_suggestion']) || isset($candidateIndex['source_url']) || isset($candidateIndex['url']);
        if (isset($candidateIndex['status'], $candidateIndex['title'], $candidateIndex['date']) && $hasIdentity) {
            $headerOffset = (int)$offset;
            $index = $candidateIndex;
            break;
        }
    }
    if ($headerOffset < 0) throw new RuntimeException('Inbox-Header wurde nicht eindeutig gefunden.');

    $rows = [];
    for ($i = $headerOffset + 1; $i < count($values); $i++) {
        $raw = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (!array_filter($raw, static fn($value): bool => trim((string)$value) !== '')) continue;
        $row = [];
        foreach ($index as $name => $position) $row[$name] = trim((string)($raw[$position] ?? ''));
        $row['row_number'] = $i + 1;
        $rows[] = $row;
    }
    return ['header_row' => $headerOffset + 1, 'index' => $index, 'rows' => $rows];
}

function be_cc_inbox_direct_table(): array
{
    $google = be_google_config();
    $range = 'Inbox!A:AZ';
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?majorDimension=ROWS',
        rawurlencode($google['sheet_id']),
        rawurlencode($range)
    );
    $response = be_cc_inbox_direct_http_request('GET', $url);
    return be_cc_inbox_direct_table_from_values(is_array($response['values'] ?? null) ? $response['values'] : []);
}

function be_cc_inbox_direct_batch_body(array $table, int $rowNumber, array $updates): array
{
    $data = [];
    foreach ($updates as $name => $value) {
        $position = $table['index'][be_cc_unicode_lower((string)$name)] ?? null;
        if (!is_int($position)) throw new RuntimeException('Inbox-Spalte fehlt: ' . $name);
        $column = be_cc_column_letter($position);
        $data[] = [
            'range' => 'Inbox!' . $column . $rowNumber . ':' . $column . $rowNumber,
            'majorDimension' => 'ROWS',
            'values' => [[(string)$value]],
        ];
    }
    return [
        'valueInputOption' => 'RAW',
        'includeValuesInResponse' => true,
        'data' => $data,
    ];
}

function be_cc_inbox_direct_batch_update(array $table, int $rowNumber, array $updates): array
{
    $google = be_google_config();
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values:batchUpdate',
        rawurlencode($google['sheet_id'])
    );
    $response = be_cc_inbox_direct_http_request('POST', $url, be_cc_inbox_direct_batch_body($table, $rowNumber, $updates));
    if ((int)($response['totalUpdatedCells'] ?? 0) < count($updates)) {
        throw new RuntimeException('Die Inbox-Aktualisierung wurde nicht vollständig bestätigt.');
    }
    return $response;
}

function be_cc_verify_inbox_writeback_direct(array $source, array $expected): array
{
    $identity = be_cc_inbox_identity($source);
    $lastError = null;
    for ($attempt = 1; $attempt <= BE_CC_INBOX_DIRECT_VERIFY_ATTEMPTS; $attempt++) {
        try {
            $table = be_cc_inbox_direct_table();
            $resolution = be_cc_resolve_inbox_row($table['rows'], $identity);
            if (($resolution['status'] ?? '') !== 'resolved') {
                throw new RuntimeException((string)($resolution['message'] ?? 'Inbox-Datensatz konnte nicht eindeutig zurückgelesen werden.'));
            }
            return [
                'attempts' => $attempt,
                'resolution' => $resolution,
                'verified_row' => be_cc_inbox_verify_writeback_row((array)($resolution['row'] ?? []), $expected),
            ];
        } catch (RuntimeException $error) {
            $lastError = $error;
            if ($attempt < BE_CC_INBOX_DIRECT_VERIFY_ATTEMPTS) usleep(150000);
        }
    }
    throw new RuntimeException('Inbox-Writeback wurde nicht bestätigt. ' . ($lastError?->getMessage() ?? ''));
}

function be_cc_writeback_inbox_decision_direct(array $case, string $action, array $decision): array
{
    if (!in_array($action, ['reject', 'snooze'], true)) {
        throw new InvalidArgumentException('Direkter Inbox-Writeback unterstützt nur Ablehnen und Zurückstellen.');
    }

    $startedAt = microtime(true);
    $source = be_cc_editorial_payload($case);
    $table = be_cc_inbox_direct_table();
    $resolution = be_cc_resolve_inbox_row($table['rows'], be_cc_inbox_identity($source));
    if (($resolution['status'] ?? '') !== 'resolved') {
        throw new DomainException((string)($resolution['message'] ?? 'Inbox-Datensatz ist nicht eindeutig.'));
    }

    $expected = be_cc_inbox_expected_writeback($action, $decision);
    $updates = be_cc_inbox_direct_updates($expected);
    if ($updates['status'] === '') throw new RuntimeException('Kanonischer Inbox-Status fehlt.');

    $writeResponse = be_cc_inbox_direct_batch_update($table, (int)$resolution['row_number'], $updates);
    $verification = be_cc_verify_inbox_writeback_direct($source, $expected);
    return [
        'transport' => 'google_sheets_direct_batch',
        'resolution' => $resolution,
        'written_fields' => array_keys($updates),
        'updated_cells' => (int)($writeResponse['totalUpdatedCells'] ?? 0),
        'expected' => $expected,
        'verification' => $verification,
        'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ];
}
