<?php
declare(strict_types=1);

require_once __DIR__ . '/_editorial_contracts.php';

/**
 * Side-effect-free schema contract shared by Inbox readers and writers.
 * Unknown optional headers remain allowed, but ambiguous headers and values
 * below empty header cells are rejected before a partial payload is built.
 */
function be_cc_inbox_schema_aliases(): array
{
    return [
        'title'=>['title'], 'date'=>['date','start_date'], 'end_date'=>['enddate','end_date'],
        'schedule_type'=>['schedule_type','event_schedule_type'], 'time'=>['time','start_time'],
        'time_status'=>['time_status','schedule_status'], 'time_details'=>['time_details','schedule_text'],
        'time_not_applicable_allowed'=>['time_not_applicable_allowed'], 'city'=>['city','stadt'],
        'local_reference'=>['local_reference','local_scope'], 'location'=>['location','venue','ort'],
        'meeting_point'=>['meeting_point','treffpunkt'], 'address'=>['address','location_address','adresse'],
        'address_status'=>['address_status'], 'category'=>['kategorie_suggestion','kategorie','category'],
        'source_url'=>['source_url','official_source_url','url'], 'event_url'=>['event_url','target_url','listing_url'],
        'source_quality'=>['source_quality','source_status'], 'ticket_url'=>['ticket_url','booking_url','registration_url'],
        'access_status'=>['access_status','ticket_status','registration_status'],
        'description'=>['description','description_text','final_description'],
        'visual_key'=>['visual_key','image_visual_key'], 'visual_motif'=>['visual_motif','image_visual_motif'],
        'visual_asset_id'=>['visual_asset_id','image_asset_id','visual_override_asset_id'],
        'visual_asset_role'=>['visual_asset_role','image_asset_role'], 'visual_gap_id'=>['visual_gap_id'],
        'matched_event_id'=>['matched_event_id','duplicate_event_id'], 'duplicate_status'=>['duplicate_status','dedupe_status'],
        'event_status'=>['event_status','current_status','cancellation_status'], 'next_review_at'=>['next_review_at','recheck_at'],
        'status'=>['status'],
    ];
}

/** Backward-compatible public name used by the verified writeback layer. */
function be_cc_inbox_canonical_aliases(): array
{
    return be_cc_inbox_schema_aliases();
}

function be_cc_inbox_schema_contract(): array
{
    return [
        'required_headers'=>['status','title','date'],
        'identity_headers'=>['id_suggestion','id','event_id','source_url','url'],
        'canonical_aliases'=>be_cc_inbox_schema_aliases(),
    ];
}

function be_cc_inbox_schema_column_letter(int $index): string
{
    if ($index < 0) throw new InvalidArgumentException('Spaltenindex darf nicht negativ sein.');
    $index++;
    $letters = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function be_cc_inbox_schema_header_token(mixed $value): string
{
    return be_cc_unicode_lower(trim((string)$value));
}

function be_cc_inbox_schema_index(array $header): array
{
    $index = [];
    $positions = [];
    foreach ($header as $position=>$value) {
        $name = be_cc_inbox_schema_header_token($value);
        if ($name === '') continue;
        $positions[$name][] = (int)$position;
        if (!array_key_exists($name, $index)) $index[$name] = (int)$position;
    }
    $duplicates = array_filter($positions, static fn(array $items): bool => count($items) > 1);
    return ['index'=>$index, 'duplicates'=>$duplicates];
}

function be_cc_inbox_schema_header_matches(array $index): bool
{
    $contract = be_cc_inbox_schema_contract();
    foreach ($contract['required_headers'] as $required) {
        if (!isset($index[$required])) return false;
    }
    foreach ($contract['identity_headers'] as $identity) {
        if (isset($index[$identity])) return true;
    }
    return false;
}

function be_cc_inbox_schema_format_positions(array $positions): string
{
    return implode(', ', array_map(
        static fn(int $position): string => be_cc_inbox_schema_column_letter($position),
        array_map('intval', $positions),
    ));
}

function be_cc_inbox_schema_analyze(array $values, string $tab): array
{
    $tab = trim($tab);
    if ($tab === '') throw new RuntimeException('Der Inbox-Tab ist nicht definiert.');

    $headerOffset = -1;
    $header = [];
    $index = [];
    $duplicates = [];
    foreach ($values as $offset=>$candidate) {
        if (!is_array($candidate)) continue;
        $candidateSchema = be_cc_inbox_schema_index($candidate);
        if (!be_cc_inbox_schema_header_matches($candidateSchema['index'])) continue;
        $headerOffset = (int)$offset;
        $header = $candidate;
        $index = $candidateSchema['index'];
        $duplicates = $candidateSchema['duplicates'];
        break;
    }
    if ($headerOffset < 0) {
        throw new RuntimeException($tab . '-Header wurde nicht eindeutig gefunden. Erforderlich sind status, title, date und mindestens eine stabile Identität.');
    }

    if ($duplicates) {
        $details = [];
        foreach ($duplicates as $name=>$positions) {
            $details[] = $name . ' (' . be_cc_inbox_schema_format_positions($positions) . ')';
        }
        throw new RuntimeException($tab . '-Schema ungültig: Doppelte Header: ' . implode('; ', $details) . '.');
    }

    $maxColumns = count($header);
    for ($rowOffset = $headerOffset + 1; $rowOffset < count($values); $rowOffset++) {
        if (is_array($values[$rowOffset] ?? null)) $maxColumns = max($maxColumns, count($values[$rowOffset]));
    }

    $orphaned = [];
    for ($position = 0; $position < $maxColumns; $position++) {
        if (be_cc_inbox_schema_header_token($header[$position] ?? '') !== '') continue;
        $rowNumbers = [];
        for ($rowOffset = $headerOffset + 1; $rowOffset < count($values); $rowOffset++) {
            $row = is_array($values[$rowOffset] ?? null) ? $values[$rowOffset] : [];
            if (trim((string)($row[$position] ?? '')) !== '') $rowNumbers[] = $rowOffset + 1;
        }
        if ($rowNumbers) {
            $orphaned[] = [
                'position'=>$position,
                'column'=>be_cc_inbox_schema_column_letter($position),
                'row_numbers'=>$rowNumbers,
            ];
        }
    }

    if ($orphaned) {
        $details = array_map(static function(array $entry): string {
            $rows = implode(', ', array_map('strval', $entry['row_numbers']));
            return $entry['column'] . ' (Zeile' . (count($entry['row_numbers']) === 1 ? ' ' : 'n ') . $rows . ')';
        }, $orphaned);
        throw new RuntimeException(
            $tab . '-Schema ungültig: Nichtleere Werte unter leeren Headern in ' . implode('; ', $details)
            . '. Die Quelle wird nicht als unvollständiger fachlicher Payload verarbeitet.'
        );
    }

    return [
        'tab'=>$tab,
        'header_offset'=>$headerOffset,
        'header_row'=>$headerOffset + 1,
        'header'=>$header,
        'index'=>$index,
        'max_columns'=>$maxColumns,
        'orphaned_values'=>[],
        'duplicate_headers'=>[],
    ];
}

function be_cc_inbox_table_from_values(array $values, string $tab): array
{
    $schema = be_cc_inbox_schema_analyze($values, $tab);
    $rows = [];
    for ($i = $schema['header_offset'] + 1; $i < count($values); $i++) {
        $raw = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (!array_filter($raw, static fn($value): bool => trim((string)$value) !== '')) continue;
        $row = [];
        foreach ($schema['index'] as $name=>$position) {
            $row[$name] = trim((string)($raw[$position] ?? ''));
        }
        $row['row_number'] = $i + 1;
        $row['_raw'] = $raw;
        $rows[] = $row;
    }
    return [
        'tab'=>$schema['tab'],
        'header_row'=>$schema['header_row'],
        'header'=>$schema['header'],
        'index'=>$schema['index'],
        'rows'=>$rows,
        'values'=>$values,
        'schema'=>$schema,
    ];
}
