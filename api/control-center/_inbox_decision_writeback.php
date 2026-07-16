<?php
declare(strict_types=1);

/** Direct, bounded and verified Google-Sheets writeback for Inbox decisions and review tasks. */

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
    $context = stream_context_create(['http' => [
        'method'=>$method,'header'=>$headers,'content'=>$content,
        'timeout'=>BE_CC_INBOX_DIRECT_HTTP_TIMEOUT_SECONDS,'ignore_errors'=>true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $match)) $statusCode = (int)$match[1];
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
        $row['_raw'] = $raw;
        $rows[] = $row;
    }
    return [
        'tab' => 'Inbox','header_row'=>$headerOffset + 1,'header'=>(array)($values[$headerOffset] ?? []),
        'index'=>$index,'rows'=>$rows,'values'=>$values,
    ];
}

function be_cc_inbox_direct_table(): array
{
    $google = be_google_config();
    $range = 'Inbox!A:ZZ';
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?majorDimension=ROWS',
        rawurlencode($google['sheet_id']), rawurlencode($range)
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
            'range'=>'Inbox!' . $column . $rowNumber . ':' . $column . $rowNumber,
            'majorDimension'=>'ROWS','values'=>[[(string)$value]],
        ];
    }
    return ['valueInputOption'=>'RAW','includeValuesInResponse'=>true,'data'=>$data];
}

function be_cc_inbox_direct_batch_request(array $data): array
{
    if (!$data) return ['totalUpdatedCells'=>0];
    $google = be_google_config();
    $url = sprintf('https://sheets.googleapis.com/v4/spreadsheets/%s/values:batchUpdate', rawurlencode($google['sheet_id']));
    $response = be_cc_inbox_direct_http_request('POST', $url, [
        'valueInputOption'=>'RAW','includeValuesInResponse'=>true,'data'=>array_values($data),
    ]);
    if ((int)($response['totalUpdatedCells'] ?? 0) < count($data)) {
        throw new RuntimeException('Die Inbox-Aktualisierung wurde nicht vollständig bestätigt.');
    }
    return $response;
}

function be_cc_inbox_direct_batch_update(array $table, int $rowNumber, array $updates): array
{
    $body = be_cc_inbox_direct_batch_body($table, $rowNumber, $updates);
    return be_cc_inbox_direct_batch_request((array)$body['data']);
}

function be_cc_inbox_canonical_aliases(): array
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

function be_cc_inbox_direct_canonical_plan(array $table, int $rowNumber, array $updates): array
{
    $aliases = be_cc_inbox_canonical_aliases();
    $index = (array)($table['index'] ?? []);
    $nextPosition = $index ? max(array_values($index)) + 1 : 0;
    $data = [];
    $columns = [];
    $newHeaders = [];
    foreach ($updates as $canonical=>$value) {
        $canonical = trim((string)$canonical);
        if (!isset($aliases[$canonical])) throw new InvalidArgumentException('Dieses Eventfeld ist nicht Teil des kanonischen Reviewvertrags: ' . $canonical);
        $columnName = '';
        $position = null;
        foreach ($aliases[$canonical] as $alias) {
            $candidate = $index[be_cc_unicode_lower($alias)] ?? null;
            if (!is_int($candidate)) continue;
            $columnName = $alias;
            $position = $candidate;
            break;
        }
        if (!is_int($position)) {
            if ($nextPosition >= 702) throw new RuntimeException('Die Inbox besitzt keine freie Spalte mehr innerhalb A:ZZ.');
            $position = $nextPosition++;
            $columnName = $canonical;
            $index[be_cc_unicode_lower($columnName)] = $position;
            $newHeaders[$canonical] = $columnName;
            $column = be_cc_column_letter($position);
            $data[] = [
                'range'=>'Inbox!' . $column . (int)$table['header_row'] . ':' . $column . (int)$table['header_row'],
                'majorDimension'=>'ROWS','values'=>[[$columnName]],
            ];
        }
        $column = be_cc_column_letter($position);
        $data[] = [
            'range'=>'Inbox!' . $column . $rowNumber . ':' . $column . $rowNumber,
            'majorDimension'=>'ROWS','values'=>[[trim((string)$value)]],
        ];
        $columns[$canonical] = ['column_name'=>$columnName,'position'=>$position,'value'=>trim((string)$value)];
    }
    return ['data'=>$data,'columns'=>$columns,'new_headers'=>$newHeaders];
}

function be_cc_inbox_direct_verify_canonical_row(array $row, array $columns): void
{
    foreach ($columns as $canonical=>$plan) {
        $name = be_cc_unicode_lower((string)$plan['column_name']);
        $actual = trim((string)($row[$name] ?? ''));
        $expected = trim((string)($plan['value'] ?? ''));
        if (be_cc_identity_text($actual) !== be_cc_identity_text($expected)) {
            throw new RuntimeException('Rückleseprüfung fehlgeschlagen: ' . $canonical . ' stimmt nicht überein.');
        }
    }
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
                'attempts'=>$attempt,'resolution'=>$resolution,
                'verified_row'=>be_cc_inbox_verify_writeback_row((array)($resolution['row'] ?? []), $expected),
            ];
        } catch (RuntimeException $error) {
            $lastError = $error;
            if ($attempt < BE_CC_INBOX_DIRECT_VERIFY_ATTEMPTS) usleep(150000);
        }
    }
    throw new RuntimeException('Inbox-Writeback wurde nicht bestätigt. ' . ($lastError?->getMessage() ?? ''));
}


function be_cc_inbox_direct_source_fingerprint(array $payload): string
{
    unset($payload['_raw'], $payload['row_number']);
    ksort($payload);
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function be_cc_inbox_direct_current_source(array $case): array
{
    $stored = be_cc_editorial_payload($case);
    $table = be_cc_inbox_direct_table();
    $resolution = be_cc_resolve_inbox_row($table['rows'], be_cc_inbox_identity($stored));
    if (($resolution['status'] ?? '') !== 'resolved') throw new DomainException((string)($resolution['message'] ?? 'Inbox-Datensatz ist nicht eindeutig.'));
    $row = (array)($resolution['row'] ?? []);
    unset($row['_raw'], $row['row_number']);
    $source = array_replace($stored, $row);
    return ['table'=>$table,'resolution'=>$resolution,'source_payload'=>$source,'fingerprint'=>be_cc_inbox_direct_source_fingerprint($source)];
}

function be_cc_inbox_direct_canonical_update_verified(array $case, array $updates, string $expectedSourceFingerprint = ''): array
{
    if (!$updates) throw new InvalidArgumentException('Mindestens ein Eventfeld muss aktualisiert werden.');
    $startedAt = microtime(true);
    $current = be_cc_inbox_direct_current_source($case);
    $source = $current['source_payload'];
    $table = $current['table'];
    $resolution = $current['resolution'];
    if ($expectedSourceFingerprint !== '' && !hash_equals($expectedSourceFingerprint, (string)$current['fingerprint'])) {
        throw new DomainException('Der Event wurde zwischenzeitlich verändert. Bitte den aktuellen Stand neu laden.');
    }
    $plan = be_cc_inbox_direct_canonical_plan($table, (int)$resolution['row_number'], $updates);
    $response = be_cc_inbox_direct_batch_request($plan['data']);

    $lastError = null;
    for ($attempt = 1; $attempt <= BE_CC_INBOX_DIRECT_VERIFY_ATTEMPTS; $attempt++) {
        try {
            $freshTable = be_cc_inbox_direct_table();
            $freshResolution = be_cc_resolve_inbox_row($freshTable['rows'], be_cc_inbox_identity($source));
            if (($freshResolution['status'] ?? '') !== 'resolved') throw new RuntimeException('Inbox-Datensatz wurde nach der Teilaktion nicht wiedergefunden.');
            $freshRow = (array)($freshResolution['row'] ?? []);
            be_cc_inbox_direct_verify_canonical_row($freshRow, $plan['columns']);
            unset($freshRow['_raw'], $freshRow['row_number']);
            $sourcePayload = array_replace($source, $freshRow, $updates);
            return [
                'transport'=>'google_sheets_verified_review_task_batch','source_verified'=>true,
                'resolution'=>$freshResolution,'written_fields'=>array_keys($updates),
                'created_headers'=>array_values($plan['new_headers']),
                'updated_cells'=>(int)($response['totalUpdatedCells'] ?? 0),
                'verification_attempts'=>$attempt,'source_payload'=>$sourcePayload,
                'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000),
            ];
        } catch (RuntimeException $error) {
            $lastError = $error;
            if ($attempt < BE_CC_INBOX_DIRECT_VERIFY_ATTEMPTS) usleep(150000);
        }
    }
    throw new RuntimeException('Die Event-Teilaktion wurde nicht vollständig zurückbestätigt. ' . ($lastError?->getMessage() ?? ''));
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
    if (($resolution['status'] ?? '') !== 'resolved') throw new DomainException((string)($resolution['message'] ?? 'Inbox-Datensatz ist nicht eindeutig.'));
    $expected = be_cc_inbox_expected_writeback($action, $decision);
    $updates = be_cc_inbox_direct_updates($expected);
    if ($updates['status'] === '') throw new RuntimeException('Kanonischer Inbox-Status fehlt.');
    $writeResponse = be_cc_inbox_direct_batch_update($table, (int)$resolution['row_number'], $updates);
    $verification = be_cc_verify_inbox_writeback_direct($source, $expected);
    return [
        'transport'=>'google_sheets_direct_batch','source_verified'=>true,'resolution'=>$resolution,
        'written_fields'=>array_keys($updates),'updated_cells'=>(int)($writeResponse['totalUpdatedCells'] ?? 0),
        'expected'=>$expected,'verification'=>$verification,
        'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000),
    ];
}
