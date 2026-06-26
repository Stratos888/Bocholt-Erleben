<?php
declare(strict_types=1);
/* === BEGIN FILE: api/growth-backlog-lib.php | Zweck: gemeinsame Helfer fuer das private Growth-/Acquisition-Backlog; Umfang: Sheet-Persistenz, Normalisierung, Deduplizierung und Statuslogik === */

const GBL_TAB = 'Growth_Backlog';
const GBL_HEADER_ROW = 1;
const GBL_DATA_START_ROW = 2;
const GBL_RANGE = GBL_TAB . '!A1:Z1000';

function gbl_now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s');
}

function gbl_slug(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?: '';
    return trim($value, '-');
}

function gbl_cluster_key(string $title, string $type = ''): string
{
    $base = gbl_slug($title);
    $prefix = gbl_slug($type);
    if ($base === '') {
        $base = 'manuell-' . gmdate('YmdHis');
    }
    return trim(($prefix !== '' ? $prefix . '-' : '') . $base, '-');
}

function gbl_required_header(): array
{
    return [
        'id', 'cluster_key', 'status', 'priority', 'type', 'title', 'short_reason',
        'why_relevant', 'recommended_action', 'expected_benefit', 'acquisition_note',
        'source', 'signals_json', 'created_at', 'updated_at', 'closed_at', 'decision_note'
    ];
}

function gbl_ensure_tab_and_header(): void
{
    static $done = false;
    if ($done) return;

    $google = be_google_config();
    $metaUrl = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s?fields=sheets.properties.title',
        rawurlencode($google['sheet_id'])
    );
    $meta = be_google_sheets_request('GET', $metaUrl);
    $sheets = is_array($meta['sheets'] ?? null) ? $meta['sheets'] : [];
    $exists = false;
    foreach ($sheets as $sheet) {
        if ((string)($sheet['properties']['title'] ?? '') === GBL_TAB) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $batchUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate',
            rawurlencode($google['sheet_id'])
        );
        be_google_sheets_request('POST', $batchUrl, [
            'requests' => [[
                'addSheet' => [
                    'properties' => ['title' => GBL_TAB]
                ]
            ]]
        ]);
    }

    be_google_sheets_values_update(GBL_TAB . '!A1:Q1', [gbl_required_header()]);
    $done = true;
}

function gbl_read_table(): array
{
    gbl_ensure_tab_and_header();
    $response = be_google_sheets_values_get(GBL_RANGE);
    $values = $response['values'] ?? [];
    if (!is_array($values) || count($values) < 1) {
        return [gbl_required_header(), [], []];
    }

    $header = array_map(static fn($v) => trim((string)$v), is_array($values[0] ?? null) ? $values[0] : []);
    if (!$header) {
        $header = gbl_required_header();
    }

    $index = [];
    foreach ($header as $i => $name) {
        if ($name !== '') $index[$name] = $i;
    }

    $rows = [];
    for ($r = 1; $r < count($values); $r++) {
        $raw = is_array($values[$r]) ? $values[$r] : [];
        $row = ['_sheet_row' => $r + 1];
        foreach ($header as $i => $name) {
            if ($name === '') continue;
            $row[$name] = trim((string)($raw[$i] ?? ''));
        }
        if (implode('', array_values(array_filter($row, 'is_string'))) !== '') {
            $rows[] = $row;
        }
    }

    return [$header, $index, $rows];
}

function gbl_column_letter(int $indexZeroBased): string
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

function gbl_append_row(array $header, array $row): void
{
    $google = be_google_config();
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
        rawurlencode($google['sheet_id']),
        rawurlencode(GBL_TAB . '!A:Z')
    );
    $values = [[...array_map(static fn($name) => (string)($row[$name] ?? ''), $header)]];
    be_google_sheets_request('POST', $url, ['values' => $values]);
}

function gbl_update_cells(array $header, array $index, int $sheetRow, array $updates): void
{
    foreach ($updates as $name => $value) {
        if (!isset($index[$name])) continue;
        $letter = gbl_column_letter((int)$index[$name]);
        be_google_sheets_values_update(GBL_TAB . '!' . $letter . $sheetRow . ':' . $letter . $sheetRow, [[(string)$value]]);
    }
}

function gbl_public_item(array $row): array
{
    return [
        'id' => (string)($row['id'] ?? ''),
        'cluster_key' => (string)($row['cluster_key'] ?? ''),
        'status' => (string)($row['status'] ?? 'open'),
        'priority' => (string)($row['priority'] ?? 'mittel'),
        'type' => (string)($row['type'] ?? 'Sonstiges'),
        'title' => (string)($row['title'] ?? ''),
        'short_reason' => (string)($row['short_reason'] ?? ''),
        'why_relevant' => (string)($row['why_relevant'] ?? ''),
        'recommended_action' => (string)($row['recommended_action'] ?? ''),
        'expected_benefit' => (string)($row['expected_benefit'] ?? ''),
        'acquisition_note' => (string)($row['acquisition_note'] ?? ''),
        'source' => (string)($row['source'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function gbl_status_is_open(string $status): bool
{
    return in_array($status, ['', 'open', 'offen'], true);
}

/* === END FILE: api/growth-backlog-lib.php === */
