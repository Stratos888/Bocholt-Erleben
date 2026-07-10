<?php
declare(strict_types=1);

require_once __DIR__ . '/_sources.php';

function be_cc_sync_sheet_inbox(): array
{
    $response = be_google_sheets_values_get('Inbox!A:AZ');
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    if (count($values) < 2) return ['seen' => 0, 'upserted' => 0, 'source' => 'sheet'];

    $headerOffset = -1;
    $index = [];
    foreach ($values as $offset => $row) {
        if (!is_array($row)) continue;
        $normalized = array_map(static fn($value): string => strtolower(trim((string)$value)), $row);
        if (in_array('title', $normalized, true) && in_array('status', $normalized, true)) {
            $headerOffset = $offset;
            $index = array_flip($normalized);
            break;
        }
    }
    if ($headerOffset < 0) throw new RuntimeException('Der führende Inbox-Tab besitzt keinen erkennbaren Header.');

    $cell = static function(array $row, array $names) use ($index): string {
        foreach ($names as $name) {
            $position = $index[strtolower($name)] ?? null;
            if (is_int($position) && isset($row[$position])) {
                $value = trim((string)$row[$position]);
                if ($value !== '') return $value;
            }
        }
        return '';
    };

    $closed = ['übernommen','uebernommen','verworfen','rejected','done','archived'];
    $seen = $upserted = 0;
    for ($i = $headerOffset + 1; $i < count($values); $i++) {
        $row = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (!array_filter($row, static fn($value): bool => trim((string)$value) !== '')) continue;
        $seen++;
        $status = mb_strtolower($cell($row, ['status']) ?: 'review', 'UTF-8');
        if (in_array($status, $closed, true)) continue;
        $title = $cell($row, ['title']);
        if ($title === '') continue;
        $reference = $cell($row, ['id_suggestion','id','event_id','source_url','url']);
        if ($reference === '') $reference = 'sheet-row-' . (string)($i + 1);
        $payload = [];
        foreach ($index as $name => $position) {
            $payload[$name] = isset($row[$position]) ? trim((string)$row[$position]) : '';
        }
        $payload['row_number'] = $i + 1;

        be_cc_upsert_source_case([
            'type' => 'intake',
            'state' => 'decision_required',
            'priority' => 'normal',
            'title' => $title,
            'reason' => $cell($row, ['description','notes']) ?: 'Neuer Kandidat aus dem führenden Inbox-Tab wartet auf Prüfung.',
            'next_action' => 'Übernehmen, ablehnen oder zurückstellen.',
            'object_type' => 'event_candidate',
            'object_id' => $reference,
            'object_title' => $title,
            // Absichtlich identisch zum bisherigen JSON-Adapter: dieselbe Quelle erzeugt keinen Doppelvorgang.
            'source_system' => 'inbox_feed',
            'source_reference' => $reference,
            'source_payload' => $payload,
            'decision_ready' => true,
        ]);
        $upserted++;
    }

    return ['seen' => $seen, 'upserted' => $upserted, 'source' => 'sheet'];
}
