<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_decision_contract.php';
require_once __DIR__ . '/_editorial_contracts.php';
require_once __DIR__ . '/_sheet_identity.php';
require_once __DIR__ . '/_operations.php';
require_once __DIR__ . '/_editorial_feedback.php';
require_once __DIR__ . '/_writeback.php';

function be_cc_editorial_payload(array $case): array
{
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    return is_array($source) ? $source : [];
}

function be_cc_editorial_merge_updates(array $source, array $updates): array
{
    $aliases = [
        'end_date'=>'endDate', 'category'=>'kategorie_suggestion', 'source_url'=>'source_url',
        'ticket_url'=>'ticket_url', 'visual_motif'=>'visual_motif',
    ];
    foreach ($updates as $key => $value) {
        $target = $aliases[$key] ?? $key;
        $source[$target] = is_array($value) ? $value : trim((string)$value);
    }
    if ($updates) $source['final_description'] = trim((string)($updates['description'] ?? $source['final_description'] ?? $source['description'] ?? ''));
    return $source;
}

function be_cc_editorial_validate_action(array $case, string $action, array $payload): array
{
    $decision = be_cc_validate_operator_decision($action, $payload, ['today'=>date('Y-m-d')]);
    if ($action === 'approve' && (string)($case['source_system'] ?? '') === 'inbox_feed') {
        $candidate = be_cc_editorial_merge_updates(be_cc_editorial_payload($case), $decision['event_updates']);
        $review = be_cc_event_candidate_review_contract($candidate);
        if (empty($review['decision_gate']['ready'])) {
            $labels = array_map(static fn(array $item): string => (string)($item['message'] ?? $item['code'] ?? 'Unvollständige Angabe'), (array)($review['decision_gate']['blockers'] ?? []));
            throw new DomainException('Event ist nicht entscheidungsreif: ' . implode(' ', $labels));
        }
        $decision['review_contract'] = $review;
    }
    return $decision;
}

function be_cc_editorial_operation_id(array $input, string $caseId, string $action): array
{
    $provided = trim((string)($input['operation_id'] ?? ''));
    if ($provided !== '') return ['id'=>$provided, 'legacy'=>false];
    return ['id'=>'legacy:' . $caseId . ':' . $action . ':' . bin2hex(random_bytes(8)), 'legacy'=>true];
}

function be_cc_operation_reserve_persistent(PDO $pdo, string $operationId, string $caseId, string $action, array $payload): array
{
    $hash = be_cc_operation_payload_hash($payload);
    $stmt = $pdo->prepare('SELECT * FROM control_operations WHERE operation_id=:id');
    $stmt->execute(['id'=>$operationId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($existing)) {
        if ((string)$existing['case_id'] !== $caseId || (string)$existing['action'] !== $action || (string)$existing['payload_hash'] !== $hash) {
            throw new DomainException('operation_id wurde bereits für einen anderen Vorgang verwendet.');
        }
        return ['decision'=>'replay','row'=>$existing];
    }
    $insert = $pdo->prepare('INSERT INTO control_operations (operation_id,case_id,action,payload_hash,status) VALUES (:id,:case_id,:action,:hash,\'started\')');
    $insert->execute(['id'=>$operationId,'case_id'=>$caseId,'action'=>$action,'hash'=>$hash]);
    return ['decision'=>'reserve','row'=>['operation_id'=>$operationId,'status'=>'started']];
}

function be_cc_operation_finish(PDO $pdo, string $operationId, string $status, ?array $result = null, string $error = ''): void
{
    $stmt = $pdo->prepare('UPDATE control_operations SET status=:status,result_json=:result,error_text=:error,updated_at=NOW(),completed_at=IF(:done=1,NOW(),completed_at) WHERE operation_id=:id');
    $stmt->execute([
        'status'=>$status,
        'result'=>$result === null ? null : json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        'error'=>$error !== '' ? $error : null,
        'done'=>in_array($status, ['completed','failed'], true) ? 1 : 0,
        'id'=>$operationId,
    ]);
}

function be_cc_sheet_table(string $range): array
{
    $response = be_google_sheets_values_get($range);
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    [$headerOffset, $index] = be_cc_find_header_row($values);
    if ($headerOffset < 0) throw new RuntimeException('Sheet-Header wurde nicht gefunden: ' . $range);
    $rows = [];
    for ($i=$headerOffset+1; $i<count($values); $i++) {
        $raw = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (!array_filter($raw, static fn($value): bool => trim((string)$value) !== '')) continue;
        $row = [];
        foreach ($index as $name=>$position) $row[$name] = trim((string)($raw[$position] ?? ''));
        $row['row_number'] = $i + 1;
        $rows[] = $row;
    }
    return ['header_row'=>$headerOffset+1,'index'=>$index,'rows'=>$rows];
}

function be_cc_update_sheet_row_canonical(string $tab, int $headerRow, int $rowNumber, array $updates): array
{
    $aliases = [
        'title'=>['title'], 'date'=>['date','start_date'], 'end_date'=>['enddate','end_date'],
        'time'=>['time'], 'city'=>['city'], 'location'=>['location','venue'], 'address'=>['address'],
        'category'=>['kategorie_suggestion','kategorie','category'], 'source_url'=>['source_url','url','event_url'],
        'ticket_url'=>['ticket_url'], 'description'=>['description','description_text'],
        'visual_key'=>['visual_key'], 'visual_motif'=>['visual_motif','image_visual_motif'],
    ];
    $headerResponse = be_google_sheets_values_get($tab . '!A' . $headerRow . ':ZZ' . $headerRow);
    $header = array_map(static fn($value): string => strtolower(trim((string)$value)), (array)($headerResponse['values'][0] ?? []));
    $index = array_flip($header);
    $written = [];
    foreach ($updates as $canonical=>$value) {
        foreach ($aliases[$canonical] ?? [$canonical] as $name) {
            $pos = $index[strtolower($name)] ?? null;
            if (!is_int($pos)) continue;
            $column = be_cc_column_letter($pos);
            be_google_sheets_values_update($tab . '!' . $column . $rowNumber . ':' . $column . $rowNumber, [[trim((string)$value)]]);
            $written[] = $canonical;
            break;
        }
    }
    return $written;
}

function be_cc_writeback_inbox_stable(array $case, string $action, array $payload, array $decision): array
{
    $source = be_cc_editorial_payload($case);
    $table = be_cc_sheet_table('Inbox!A:AZ');
    $resolution = be_cc_resolve_inbox_row($table['rows'], be_cc_inbox_identity($source));
    if (($resolution['status'] ?? '') !== 'resolved') throw new DomainException((string)($resolution['message'] ?? 'Inbox-Datensatz ist nicht eindeutig.'));
    $rowNumber = (int)$resolution['row_number'];
    $written = [];
    if ($action === 'approve' && $decision['event_updates']) {
        $written = be_cc_update_sheet_row_canonical('Inbox', (int)$table['header_row'], $rowNumber, $decision['event_updates']);
    }
    $token = be_cc_inbox_token();
    if ($action === 'approve') {
        $result = be_cc_jsonp_request(['action'=>'approve','token'=>$token,'row_number'=>(string)$rowNumber]);
    } elseif ($action === 'reject') {
        $result = be_cc_jsonp_request(['action'=>'setStatus','token'=>$token,'row_number'=>(string)$rowNumber,'status'=>'verwerfen','ablehnungsgrund'=>$decision['decision_note'] ?: $decision['decision_class']]);
    } elseif ($action === 'snooze') {
        $result = be_cc_jsonp_request(['action'=>'setStatus','token'=>$token,'row_number'=>(string)$rowNumber,'status'=>'später prüfen','ablehnungsgrund'=>$decision['decision_note']]);
    } else {
        return ['resolution'=>$resolution,'written_fields'=>$written];
    }
    if (empty($result['ok'])) throw new RuntimeException('Inbox writeback failed: ' . trim((string)($result['error'] ?? $result['detail'] ?? 'unknown_error')));
    return ['resolution'=>$resolution,'written_fields'=>$written];
}

function be_cc_writeback_audit_stable(array $case, string $action, array $payload, array $decision): array
{
    $source = be_cc_editorial_payload($case);
    $tab = be_content_audit_tab_name();
    $table = be_cc_sheet_table($tab . '!A:ZZ');
    $resolution = be_cc_resolve_audit_row($table['rows'], be_cc_audit_identity($source));
    if (($resolution['status'] ?? '') === 'ambiguous') throw new DomainException((string)$resolution['message']);
    $resolutionKind = ($resolution['status'] ?? '') === 'resolved' ? 'current_audit_row' : 'superseded_audit_row';
    if ($action === 'approve' && $decision['event_updates']) {
        be_cc_update_event_fields(trim((string)($source['content_id'] ?? '')), $decision['event_updates']);
    }
    if (($resolution['status'] ?? '') === 'resolved') {
        $now = gmdate('Y-m-d\TH:i:s');
        $updates = match ($action) {
            'approve' => $decision['event_updates']
                ? ['status'=>'corrected','action_state'=>'event_sheet_corrected','verification_status'=>'corrected']
                : ['status'=>'verified','action_state'=>'verified_until_next_review','verification_status'=>'confirmed'],
            'reject' => ['status'=>'ignored','action_state'=>'ignored'],
            'snooze' => ['status'=>'snoozed','action_state'=>'snoozed','next_review_at'=>$decision['suppress_until'],'next_check_at'=>$decision['suppress_until']],
            default => [],
        };
        $updates += ['verified_at'=>$now,'last_verified_at'=>$now,'verified_by'=>'control_center','review_note'=>$decision['decision_note']];
        be_cc_update_sheet_row_by_header($tab, (int)$table['header_row'], (int)$resolution['row_number'], $updates);
    }
    return ['resolution'=>$resolution,'resolution_kind'=>$resolutionKind];
}

function be_cc_record_editorial_feedback(PDO $pdo, array $case, array $decision, array $payload): void
{
    if ($decision['decision_class'] !== 'corrected') return;
    $source = be_cc_editorial_payload($case);
    $before = trim((string)($source['current_description'] ?? $source['description'] ?? ''));
    $suggested = trim((string)($source['suggested_description'] ?? ''));
    $final = trim((string)($decision['event_updates']['description'] ?? ''));
    if ($final === '') return;
    $observation = be_cc_editorial_feedback_observation($before, $suggested, $final, [
        'event_id'=>$source['content_id'] ?? $case['object_id'] ?? '',
        'issue_code'=>$source['issue_code'] ?? '',
        'source_fingerprint'=>$source['source_fingerprint'] ?? '',
        'content_fingerprint'=>$source['content_fingerprint'] ?? '',
        'rule_version'=>$payload['rule_version'] ?? 'control-center-editorial-v1',
    ]);
    $stmt = $pdo->prepare('INSERT INTO control_editorial_feedback (id,case_id,object_id,issue_code,before_text,suggested_text,final_text,diff_json,categories_json,decision_class,source_fingerprint,content_fingerprint,rule_version,status) VALUES (:id,:case_id,:object_id,:issue_code,:before_text,:suggested_text,:final_text,:diff_json,:categories_json,\'corrected\',:source_fingerprint,:content_fingerprint,:rule_version,\'observation\')');
    $stmt->execute([
        'id'=>be_cc_uuid(),'case_id'=>$case['id'],'object_id'=>(string)($observation['event_id'] ?? ''),'issue_code'=>(string)($observation['issue_code'] ?? ''),
        'before_text'=>$before,'suggested_text'=>$suggested,'final_text'=>$final,
        'diff_json'=>json_encode($observation['diff'] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'categories_json'=>json_encode($observation['categories'] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'source_fingerprint'=>(string)($observation['source_fingerprint'] ?? ''),'content_fingerprint'=>(string)($observation['content_fingerprint'] ?? ''),
        'rule_version'=>(string)($observation['rule_version'] ?? ''),
    ]);
}
