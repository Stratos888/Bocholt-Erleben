<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

function be_cc_find_header_row(array $values): array
{
    foreach ($values as $offset => $row) {
        if (!is_array($row)) continue;
        $normalized = array_map(static fn($value) => strtolower(trim((string)$value)), $row);
        if (in_array('title', $normalized, true) && (in_array('id', $normalized, true) || in_array('event_id', $normalized, true))) {
            return [$offset, array_flip($normalized)];
        }
    }
    return [-1, []];
}

function be_cc_sheet_value(array $row, array $index, array $names): string
{
    foreach ($names as $name) {
        $position = $index[strtolower($name)] ?? null;
        if (is_int($position) && isset($row[$position])) {
            $value = trim((string)$row[$position]);
            if ($value !== '') return $value;
        }
    }
    return '';
}

function be_cc_event_items(bool $full = false): array
{
    $response = be_google_sheets_values_get('Events!A:AZ');
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    [$headerOffset, $index] = be_cc_find_header_row($values);
    if ($headerOffset < 0) return [];
    $items = [];
    for ($i = $headerOffset + 1; $i < count($values); $i++) {
        $row = is_array($values[$i] ?? null) ? $values[$i] : [];
        $id = be_cc_sheet_value($row, $index, ['id', 'event_id']);
        $title = be_cc_sheet_value($row, $index, ['title']);
        if ($id === '' || $title === '') continue;
        $status = be_cc_sheet_value($row, $index, ['status', 'publication_status']);
        if (in_array(strtolower($status), ['deleted', 'archived'], true)) continue;
        $item = [
            'id' => $id,
            'title' => $title,
            'date' => be_cc_sheet_value($row, $index, ['date', 'start_date']),
            'end_date' => be_cc_sheet_value($row, $index, ['enddate', 'end_date']),
            'time' => be_cc_sheet_value($row, $index, ['time']),
            'location' => be_cc_sheet_value($row, $index, ['location', 'venue']),
            'city' => be_cc_sheet_value($row, $index, ['city']),
            'category' => be_cc_sheet_value($row, $index, ['kategorie', 'category']),
            'status' => $status !== '' ? $status : 'published',
            'source_url' => be_cc_sheet_value($row, $index, ['source_url', 'url', 'event_url']),
            'ticket_url' => be_cc_sheet_value($row, $index, ['ticket_url']),
            'public_url' => '/events/?event=' . rawurlencode($id),
            'updated_at' => be_cc_sheet_value($row, $index, ['updated_at', 'last_modified']),
        ];
        if ($full) {
            $item['description'] = be_cc_sheet_value($row, $index, ['description']);
            $item['address'] = be_cc_sheet_value($row, $index, ['address']);
            $item['visual_key'] = be_cc_sheet_value($row, $index, ['visual_key']);
            $item['visual_motif'] = be_cc_sheet_value($row, $index, ['visual_motif', 'image_visual_motif']);
        }
        $items[] = $item;
    }
    return $items;
}

function be_cc_activity_items(bool $full = false): array
{
    $path = dirname(__DIR__, 2) . '/data/offers.json';
    if (!is_file($path)) return [];
    $payload = json_decode((string)file_get_contents($path), true);
    $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
    $items = [];
    foreach ($offers as $offer) {
        if (!is_array($offer)) continue;
        $id = trim((string)($offer['id'] ?? ''));
        $title = trim((string)($offer['title'] ?? ''));
        if ($id === '' || $title === '') continue;
        $item = [
            'id' => $id,
            'title' => $title,
            'category' => trim((string)($offer['kategorie'] ?? '')),
            'location' => trim((string)($offer['location'] ?? '')),
            'status' => 'published',
            'source_url' => trim((string)($offer['url'] ?? '')),
            'public_url' => '/aktivitaeten/?activity=' . rawurlencode($id),
            'updated_at' => trim((string)($offer['checked_at'] ?? '')),
            'editable' => false,
            'edit_notice' => 'Aktivitäten sind derzeit repo-geführt. Ein dauerhafter Repo-Writeback wird noch eingerichtet.',
        ];
        if ($full) {
            $item['description'] = trim((string)($offer['description'] ?? ''));
            $item['address'] = trim((string)($offer['maps_query'] ?? ''));
            $item['visual_key'] = trim((string)($offer['visual_key'] ?? ''));
        }
        $items[] = $item;
    }
    return $items;
}
