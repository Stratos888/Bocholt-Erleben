<?php
declare(strict_types=1);

require_once __DIR__ . '/_editorial_runtime.php';
require_once __DIR__ . '/_inbox_decision_writeback.php';
require_once __DIR__ . '/_verified_source_writeback.php';

const BE_CC_STAGING_EVENT_COLUMNS = [
    'id', 'title', 'date', 'endDate', 'time', 'city', 'location',
    'kategorie', 'url', 'description', 'visual_key',
];

function be_cc_staging_event_slug(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
    $normalized = strtolower(is_string($ascii) && $ascii !== '' ? $ascii : $value);
    $normalized = (string)preg_replace('/[^a-z0-9]+/', '-', $normalized);
    return trim($normalized, '-');
}

function be_cc_staging_event_effective_time(array $source): string
{
    $time = trim((string)be_cc_contract_value($source, ['time', 'start_time'], ''));
    $details = trim((string)be_cc_contract_value($source, ['time_details', 'schedule_text'], ''));
    $status = be_cc_decision_token(be_cc_contract_value($source, ['time_status', 'schedule_status'], ''));

    if ($details !== '' && preg_match('/\d{1,2}:\d{2}.*\d{1,2}:\d{2}/u', $details) === 1) return $details;
    if ($details !== '' && in_array($status, ['multiple_times', 'during_opening_hours'], true)) return $details;
    return $time;
}

function be_cc_staging_event_from_source(array $source): array
{
    $title = trim((string)be_cc_contract_value($source, ['title', 'eventName'], ''));
    $date = trim((string)be_cc_contract_value($source, ['date', 'start_date'], ''));
    $providedId = trim((string)be_cc_contract_value($source, ['id_suggestion', 'id', 'event_id'], ''));
    $eventId = be_cc_staging_event_slug($providedId !== '' ? $providedId : $title . '-' . $date);

    $event = [
        'id' => $eventId,
        'title' => $title,
        'date' => $date,
        'endDate' => trim((string)be_cc_contract_value($source, ['endDate', 'end_date', 'enddate'], '')),
        'time' => be_cc_staging_event_effective_time($source),
        'city' => trim((string)be_cc_contract_value($source, ['city', 'stadt'], '')),
        'location' => trim((string)be_cc_contract_value($source, ['location', 'venue', 'ort'], '')),
        'kategorie' => trim((string)be_cc_contract_value($source, ['kategorie', 'kategorie_suggestion', 'category'], '')),
        'url' => trim((string)be_cc_contract_value($source, ['source_url', 'url', 'event_url'], '')),
        'description' => trim((string)be_cc_contract_value($source, ['final_description', 'description', 'description_text'], '')),
        'visual_key' => trim((string)be_cc_contract_value($source, ['visual_key', 'image_visual_key'], '')),
    ];

    foreach (['id', 'title', 'date', 'city', 'location', 'kategorie', 'url', 'description', 'visual_key'] as $required) {
        if ($event[$required] === '') throw new DomainException('Staging-Eventfeld fehlt: ' . $required);
    }
    return $event;
}

function be_cc_staging_event_validate_header(array $header): void
{
    $header = array_map(static fn($value): string => trim((string)$value), $header);
    if ($header !== BE_CC_STAGING_EVENT_COLUMNS) {
        throw new RuntimeException('Events_Staging muss exakt denselben kanonischen Header wie Events besitzen.');
    }
}

function be_cc_staging_event_row(array $event): array
{
    return array_map(static fn(string $column): string => trim((string)($event[$column] ?? '')), BE_CC_STAGING_EVENT_COLUMNS);
}

function be_cc_staging_event_from_table_row(array $row): array
{
    $event = [];
    foreach (BE_CC_STAGING_EVENT_COLUMNS as $column) {
        $event[$column] = trim((string)($row[be_cc_unicode_lower($column)] ?? ''));
    }
    return $event;
}

function be_cc_staging_event_fingerprint(array $event): string
{
    $normalized = [];
    foreach (BE_CC_STAGING_EVENT_COLUMNS as $column) {
        $normalized[$column] = be_cc_identity_text((string)($event[$column] ?? ''));
    }
    return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function be_cc_staging_event_resolution(array $rows, array $event): array
{
    $wantedId = be_cc_identity_text((string)$event['id']);
    $wantedUrl = be_cc_identity_text((string)$event['url']);
    $idMatches = [];
    $urlMatches = [];

    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $candidate = be_cc_staging_event_from_table_row($row);
        if ($wantedId !== '' && be_cc_identity_text($candidate['id']) === $wantedId) $idMatches[] = $row;
        if ($wantedUrl !== '' && be_cc_identity_text($candidate['url']) === $wantedUrl) $urlMatches[] = $row;
    }

    if (count($idMatches) > 1 || count($urlMatches) > 1) {
        return ['status'=>'conflict', 'message'=>'Events_Staging enthält keine eindeutige Eventidentität.'];
    }

    $idRow = $idMatches[0] ?? null;
    $urlRow = $urlMatches[0] ?? null;
    if (is_array($idRow) && is_array($urlRow) && (int)$idRow['row_number'] !== (int)$urlRow['row_number']) {
        return ['status'=>'conflict', 'message'=>'Event-ID und Event-URL verweisen auf unterschiedliche Staging-Zeilen.'];
    }

    $row = is_array($idRow) ? $idRow : (is_array($urlRow) ? $urlRow : null);
    if (!is_array($row)) return ['status'=>'absent'];

    $current = be_cc_staging_event_from_table_row($row);
    if (is_array($urlRow) && !is_array($idRow) && be_cc_identity_text($current['id']) !== $wantedId) {
        return ['status'=>'conflict', 'message'=>'Die Event-URL ist bereits mit einer anderen Staging-ID belegt.'];
    }

    return [
        'status'=>'resolved',
        'row_number'=>(int)$row['row_number'],
        'row'=>$row,
        'event'=>$current,
        'identical'=>hash_equals(be_cc_staging_event_fingerprint($current), be_cc_staging_event_fingerprint($event)),
    ];
}

function be_cc_staging_events_table(): array
{
    $inboxTab = be_cc_inbox_tab_name();
    $eventsTab = be_cc_events_tab_name();
    if ($inboxTab !== 'Inbox_Staging' || $eventsTab !== 'Events_Staging') {
        throw new DomainException('Der direkte Staging-Eventwriteback ist nur für Inbox_Staging und Events_Staging zulässig.');
    }

    $table = be_cc_source_table($eventsTab, 'A:AZ', 1);
    $header = is_array($table['values'][0] ?? null) ? $table['values'][0] : [];
    be_cc_staging_event_validate_header($header);
    return $table;
}

function be_cc_staging_event_append(array $event): array
{
    $google = be_google_config();
    $range = be_cc_events_tab_name() . '!A1';
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS&includeValuesInResponse=true',
        rawurlencode($google['sheet_id']),
        rawurlencode($range)
    );
    $response = be_cc_source_http_request('POST', $url, [
        'majorDimension'=>'ROWS',
        'values'=>[be_cc_staging_event_row($event)],
    ]);
    if ((int)($response['updates']['updatedRows'] ?? 0) < 1) {
        throw new RuntimeException('Events_Staging hat die neue Eventzeile nicht bestätigt.');
    }
    return $response;
}

function be_cc_staging_event_update(array $table, int $rowNumber, array $event): array
{
    $updates = [];
    foreach (BE_CC_STAGING_EVENT_COLUMNS as $column) $updates[$column] = (string)$event[$column];
    return be_cc_source_batch_update($table, $rowNumber, $updates);
}

function be_cc_staging_event_upsert_verified(array $event): array
{
    $startedAt = microtime(true);
    $table = be_cc_staging_events_table();
    $resolution = be_cc_staging_event_resolution((array)$table['rows'], $event);
    if (($resolution['status'] ?? '') === 'conflict') {
        throw new DomainException((string)$resolution['message']);
    }

    $mode = 'existing';
    if (($resolution['status'] ?? '') === 'absent') {
        be_cc_staging_event_append($event);
        $mode = 'appended';
    } elseif (empty($resolution['identical'])) {
        be_cc_staging_event_update($table, (int)$resolution['row_number'], $event);
        $mode = 'updated';
    }

    $fresh = be_cc_staging_events_table();
    $verified = be_cc_staging_event_resolution((array)$fresh['rows'], $event);
    if (($verified['status'] ?? '') !== 'resolved' || empty($verified['identical'])) {
        throw new RuntimeException('Events_Staging konnte die Eventzeile nicht vollständig zurückbestätigen.');
    }

    return [
        'verified'=>true,
        'mode'=>$mode,
        'event_id'=>$event['id'],
        'row_number'=>(int)$verified['row_number'],
        'fingerprint'=>be_cc_staging_event_fingerprint($event),
        'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000),
    ];
}

function be_cc_writeback_staging_inbox_approve_verified(array $case, array $payload, array $decision): array
{
    if (be_cc_inbox_tab_name() !== 'Inbox_Staging' || be_cc_events_tab_name() !== 'Events_Staging') {
        throw new DomainException('Staging-Freigabe ist ohne isolierte Inbox- und Events-Tabs gesperrt.');
    }

    $startedAt = microtime(true);
    $written = [];
    $current = be_cc_inbox_direct_current_source($case);
    $source = (array)$current['source_payload'];

    if (!empty($decision['event_updates'])) {
        $updated = be_cc_inbox_direct_canonical_update_verified(
            $case,
            (array)$decision['event_updates'],
            trim((string)($decision['source_fingerprint'] ?? ''))
        );
        $written = (array)$updated['written_fields'];
        $source = (array)$updated['source_payload'];
        $current = be_cc_inbox_direct_current_source($case);
        $source = array_replace($source, (array)$current['source_payload']);
    }

    $event = be_cc_staging_event_from_source($source);
    $eventWriteback = be_cc_staging_event_upsert_verified($event);

    $expected = be_cc_inbox_expected_writeback('approve', $decision);
    $inboxUpdates = be_cc_inbox_direct_updates($expected);
    $table = (array)$current['table'];
    $resolution = (array)$current['resolution'];
    be_cc_inbox_direct_batch_update($table, (int)$resolution['row_number'], $inboxUpdates);
    $inboxVerification = be_cc_verify_inbox_writeback_direct($source, $expected);

    return [
        'transport'=>'google_sheets_staging_isolated_verified',
        'source_verified'=>true,
        'environment'=>'staging',
        'events_tab'=>'Events_Staging',
        'inbox_tab'=>'Inbox_Staging',
        'written_fields'=>$written,
        'event'=>$eventWriteback,
        'expected'=>$expected,
        'verification'=>$inboxVerification,
        'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000),
    ];
}
