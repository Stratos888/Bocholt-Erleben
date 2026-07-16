<?php
declare(strict_types=1);

const BE_CC_SOURCE_HTTP_TIMEOUT_SECONDS = 8;
const BE_CC_SOURCE_VERIFY_ATTEMPTS = 2;

function be_cc_source_http_request(string $method, string $url, ?array $body = null): array
{
    $token = be_google_access_token(['https://www.googleapis.com/auth/spreadsheets']);
    $headers = "Authorization: Bearer {$token}\r\nAccept: application/json\r\n";
    $content = '';
    if ($body !== null) {
        $content = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers .= "Content-Type: application/json; charset=utf-8\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $content,
        'timeout' => BE_CC_SOURCE_HTTP_TIMEOUT_SECONDS,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $match)) $statusCode = (int)$match[1];
    }
    $payload = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($payload)) {
        throw new RuntimeException('Die führende Google-Sheets-Quelle antwortet nicht rechtzeitig oder fehlerhaft.');
    }
    return $payload;
}

function be_cc_source_values_get(string $range): array
{
    $google = be_google_config();
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?majorDimension=ROWS',
        rawurlencode($google['sheet_id']),
        rawurlencode($range)
    );
    $response = be_cc_source_http_request('GET', $url);
    return is_array($response['values'] ?? null) ? $response['values'] : [];
}

function be_cc_source_table(string $tab, string $columns, int $headerRow): array
{
    $values = be_cc_source_values_get($tab . '!' . $columns);
    $offset = $headerRow - 1;
    $header = is_array($values[$offset] ?? null) ? $values[$offset] : [];
    $normalized = array_map(static fn($value): string => be_cc_unicode_lower(trim((string)$value)), $header);
    $index = array_flip($normalized);
    if (!$index) throw new RuntimeException('Der Header der führenden Quelle wurde nicht gefunden: ' . $tab);
    $rows = [];
    for ($i = $offset + 1; $i < count($values); $i++) {
        $raw = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (!array_filter($raw, static fn($value): bool => trim((string)$value) !== '')) continue;
        $row = [];
        foreach ($index as $name => $position) $row[$name] = trim((string)($raw[$position] ?? ''));
        $row['row_number'] = $i + 1;
        $row['_raw'] = $raw;
        $rows[] = $row;
    }
    return ['tab' => $tab, 'header_row' => $headerRow, 'index' => $index, 'rows' => $rows, 'values' => $values];
}

function be_cc_source_batch_update(array $table, int $rowNumber, array $updates, string $inputOption = 'RAW'): array
{
    $data = [];
    foreach ($updates as $name => $value) {
        $position = $table['index'][be_cc_unicode_lower((string)$name)] ?? null;
        if (!is_int($position)) throw new RuntimeException(sprintf('Pflichtspalte "%s" fehlt in %s.', $name, (string)$table['tab']));
        $column = be_cc_column_letter($position);
        $data[] = [
            'range' => $table['tab'] . '!' . $column . $rowNumber . ':' . $column . $rowNumber,
            'majorDimension' => 'ROWS',
            'values' => [[(string)$value]],
        ];
    }
    $google = be_google_config();
    $url = sprintf('https://sheets.googleapis.com/v4/spreadsheets/%s/values:batchUpdate', rawurlencode($google['sheet_id']));
    $response = be_cc_source_http_request('POST', $url, [
        'valueInputOption' => $inputOption,
        'includeValuesInResponse' => true,
        'data' => $data,
    ]);
    if ((int)($response['totalUpdatedCells'] ?? 0) < count($updates)) {
        throw new RuntimeException('Die führende Quelle hat nicht alle vorgesehenen Felder bestätigt.');
    }
    return $response;
}

function be_cc_source_verify_fields(array $row, array $expected): void
{
    foreach ($expected as $name => $value) {
        $actual = trim((string)($row[be_cc_unicode_lower((string)$name)] ?? ''));
        if (be_cc_identity_text($actual) !== be_cc_identity_text((string)$value)) {
            throw new RuntimeException(sprintf('Rückleseprüfung fehlgeschlagen: %s stimmt nicht überein.', $name));
        }
    }
}

function be_cc_bounded_jsonp_request(array $params): array
{
    $callback = '__be_cc_' . bin2hex(random_bytes(6));
    $params['callback'] = $callback;
    $url = BE_CC_INBOX_APPS_SCRIPT_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => BE_CC_SOURCE_HTTP_TIMEOUT_SECONDS,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') throw new RuntimeException('Der Inbox-Veröffentlichungsdienst antwortet nicht rechtzeitig.');
    $prefix = $callback . '(';
    $start = strpos($raw, $prefix);
    $end = strrpos($raw, ')');
    if ($start === false || $end === false || $end <= $start) throw new RuntimeException('Der Inbox-Veröffentlichungsdienst lieferte keine gültige Antwort.');
    $payload = json_decode(substr($raw, $start + strlen($prefix), $end - ($start + strlen($prefix))), true);
    if (!is_array($payload)) throw new RuntimeException('Der Inbox-Veröffentlichungsdienst lieferte ungültige Daten.');
    return $payload;
}

function be_cc_bounded_inbox_token(): string
{
    $unlock = be_cc_bounded_jsonp_request(['action' => 'unlock', 'password' => be_review_password()]);
    $token = trim((string)($unlock['token'] ?? ''));
    if (empty($unlock['ok']) || $token === '') throw new RuntimeException('Der Inbox-Veröffentlichungsdienst konnte nicht entsperrt werden.');
    return $token;
}

function be_cc_inbox_canonical_batch_updates(array $table, array $updates): array
{
    $aliases = [
        'title'=>['title'],'date'=>['date','start_date'],'end_date'=>['enddate','end_date'],'time'=>['time'],'city'=>['city'],
        'location'=>['location','venue'],'address'=>['address'],'category'=>['kategorie_suggestion','kategorie','category'],
        'source_url'=>['source_url','url','event_url'],'ticket_url'=>['ticket_url'],'description'=>['description','description_text'],
        'visual_key'=>['visual_key'],'visual_motif'=>['visual_motif','image_visual_motif'],
    ];
    $resolved = [];
    foreach ($updates as $canonical => $value) {
        foreach ($aliases[$canonical] ?? [$canonical] as $name) {
            if (!isset($table['index'][be_cc_unicode_lower($name)])) continue;
            $resolved[$name] = trim((string)$value);
            break;
        }
    }
    if (count($resolved) !== count($updates)) throw new RuntimeException('Nicht alle bearbeiteten Inbox-Felder konnten einer Quellspalte zugeordnet werden.');
    return $resolved;
}

function be_cc_writeback_inbox_approve_verified(array $case, array $payload, array $decision): array
{
    $startedAt = microtime(true);
    $source = be_cc_editorial_payload($case);
    $table = be_cc_inbox_direct_table();
    $resolution = be_cc_resolve_inbox_row($table['rows'], be_cc_inbox_identity($source));
    if (($resolution['status'] ?? '') !== 'resolved') throw new DomainException((string)($resolution['message'] ?? 'Inbox-Datensatz ist nicht eindeutig.'));
    $rowNumber = (int)$resolution['row_number'];
    $written = [];
    $verificationSource = $source;

    if ($decision['event_updates']) {
        $updates = be_cc_inbox_canonical_batch_updates($table, $decision['event_updates']);
        be_cc_source_batch_update($table, $rowNumber, $updates);
        $written = array_keys($decision['event_updates']);
        $verificationSource = be_cc_editorial_merge_updates($source, $decision['event_updates']);
    }

    $token = be_cc_bounded_inbox_token();
    $result = be_cc_bounded_jsonp_request(['action' => 'approve', 'token' => $token, 'row_number' => (string)$rowNumber]);
    if (empty($result['ok'])) throw new RuntimeException('Inbox-Übernahme fehlgeschlagen: ' . trim((string)($result['error'] ?? $result['detail'] ?? 'unknown_error')));
    $expected = be_cc_inbox_expected_writeback('approve', $decision);
    $verification = be_cc_verify_inbox_writeback_direct($verificationSource, $expected);
    return [
        'transport' => 'bounded_apps_script_and_verified_sheet',
        'source_verified' => true,
        'resolution' => $resolution,
        'written_fields' => $written,
        'expected' => $expected,
        'verification' => $verification,
        'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ];
}

function be_cc_events_verified_table(): array
{
    $values = be_cc_source_values_get('Events!A:AZ');
    $headerRow = 0;
    foreach ($values as $offset => $row) {
        if (!is_array($row)) continue;
        $normalized = array_map(static fn($value): string => be_cc_unicode_lower(trim((string)$value)), $row);
        if (in_array('title', $normalized, true) && (in_array('id', $normalized, true) || in_array('event_id', $normalized, true))) {
            $headerRow = $offset + 1;
            break;
        }
    }
    if ($headerRow < 1) throw new RuntimeException('Events-Header wurde nicht gefunden.');
    return be_cc_source_table('Events', 'A:AZ', $headerRow);
}

function be_cc_verified_current_event(string $contentId): array
{
    $table = be_cc_events_verified_table();
    $idPosition = $table['index']['id'] ?? $table['index']['event_id'] ?? null;
    if (!is_int($idPosition)) throw new RuntimeException('Events-ID-Spalte fehlt.');
    foreach ($table['rows'] as $row) {
        $raw = (array)($row['_raw'] ?? []);
        if (trim((string)($raw[$idPosition] ?? '')) !== $contentId) continue;
        $cell = static function(array $names) use ($row): string {
            foreach ($names as $name) {
                $value = trim((string)($row[be_cc_unicode_lower($name)] ?? ''));
                if ($value !== '') return $value;
            }
            return '';
        };
        return [
            'table' => $table,
            'row_number' => (int)$row['row_number'],
            'raw' => $raw,
            'event' => [
                'id' => $contentId,
                'title' => $cell(['title']),
                'date' => $cell(['date','start_date']),
                'end_date' => $cell(['enddate','end_date']),
                'time' => $cell(['time']),
                'city' => $cell(['city']),
                'location' => $cell(['location','venue']),
                'address' => $cell(['address']),
                'category' => $cell(['kategorie','category']),
                'source_url' => $cell(['source_url','url','event_url']),
                'description' => $cell(['description']),
            ],
            'row' => $row,
        ];
    }
    throw new RuntimeException('Der aktuelle Eventstand wurde nicht gefunden.');
}

function be_cc_verified_update_event(string $contentId, array $updates): array
{
    $current = be_cc_verified_current_event($contentId);
    $resolved = be_cc_validate_event_update($current['table']['index'], $current['raw'], $updates);
    $sheetUpdates = [];
    foreach ($resolved as $canonical => $plan) $sheetUpdates[$plan['column_name']] = $plan['value'];
    be_cc_source_batch_update($current['table'], (int)$current['row_number'], $sheetUpdates, 'USER_ENTERED');
    $fresh = be_cc_verified_current_event($contentId);
    be_cc_source_verify_fields($fresh['row'], $sheetUpdates);
    return array_keys($resolved);
}

function be_cc_content_audit_verified_table(): array
{
    return be_cc_source_table(be_content_audit_tab_name(), 'A:ZZ', 3);
}

function be_cc_writeback_audit_verified(array $case, string $action, array $payload, array $decision): array
{
    $startedAt = microtime(true);
    $source = be_cc_editorial_payload($case);
    $table = be_cc_content_audit_verified_table();
    $identity = be_cc_audit_identity($source);
    $resolution = be_cc_resolve_audit_row($table['rows'], $identity);
    $contentId = trim((string)($source['content_id'] ?? $case['object_id'] ?? ''));
    $current = $contentId !== '' ? be_cc_verified_current_event($contentId) : [];
    $currentEvent = (array)($current['event'] ?? []);
    $currentFingerprint = $currentEvent ? be_cc_runtime_event_fingerprint($currentEvent) : '';
    $resolutionState = be_cc_audit_resolution_state($identity, $currentFingerprint, $resolution);
    if (empty($resolutionState['write_allowed'])) throw new DomainException('Der Event wurde zwischenzeitlich verändert oder der Auditfall ist nicht eindeutig. Bitte den aktuellen Stand neu laden; dein Entwurf bleibt erhalten.');
    if (($resolution['status'] ?? '') !== 'resolved') throw new DomainException('Der Auditfall konnte nicht eindeutig aufgelöst werden.');

    $writtenEventFields = [];
    if ($action === 'approve' && $decision['event_updates']) {
        $expectedDescriptionHash = trim((string)($payload['current_description_hash'] ?? ''));
        if (!be_cc_description_matches($expectedDescriptionHash, (string)($currentEvent['description'] ?? ''))) {
            throw new DomainException('Die Beschreibung wurde zwischenzeitlich verändert. Bitte den aktuellen Stand neu laden; dein Entwurf bleibt erhalten.');
        }
        $writtenEventFields = be_cc_verified_update_event($contentId, $decision['event_updates']);
    }

    $now = gmdate('Y-m-d\TH:i:s');
    $updates = match ($action) {
        'approve' => $decision['event_updates']
            ? ['status'=>'corrected','action_state'=>'event_sheet_corrected','verification_status'=>'corrected']
            : ['status'=>'verified','action_state'=>'verified_until_next_review','verification_status'=>'confirmed'],
        'reject' => ['status'=>'ignored','action_state'=>'ignored'],
        'snooze' => ['status'=>'snoozed','action_state'=>'snoozed','next_review_at'=>$decision['suppress_until'],'next_check_at'=>$decision['suppress_until']],
        default => throw new InvalidArgumentException('Unbekannte Content-Audit-Aktion.'),
    };
    $updates += ['verified_at'=>$now,'last_verified_at'=>$now,'verified_by'=>'control_center','review_note'=>$decision['decision_note']];
    be_cc_source_batch_update($table, (int)$resolution['row_number'], $updates);

    $lastError = null;
    for ($attempt = 1; $attempt <= BE_CC_SOURCE_VERIFY_ATTEMPTS; $attempt++) {
        try {
            $freshTable = be_cc_content_audit_verified_table();
            $freshResolution = be_cc_resolve_audit_row($freshTable['rows'], $identity);
            if (($freshResolution['status'] ?? '') !== 'resolved') throw new RuntimeException('Auditfall wurde nach dem Writeback nicht wiedergefunden.');
            be_cc_source_verify_fields((array)$freshResolution['row'], $updates);
            return [
                'transport' => 'google_sheets_verified_batch',
                'source_verified' => true,
                'resolution' => $freshResolution,
                'resolution_state' => $resolutionState['state'],
                'resolution_kind' => $resolutionState['resolution_kind'],
                'written_event_fields' => $writtenEventFields,
                'written_audit_fields' => array_keys($updates),
                'verification_attempts' => $attempt,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (RuntimeException $error) {
            $lastError = $error;
            if ($attempt < BE_CC_SOURCE_VERIFY_ATTEMPTS) usleep(150000);
        }
    }
    throw new RuntimeException('Content-Audit-Writeback wurde nicht bestätigt. ' . ($lastError?->getMessage() ?? ''));
}
