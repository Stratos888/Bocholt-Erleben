<?php
declare(strict_types=1);

/** Verified partial writeback for one event-review task without closing the case. */

function be_cc_review_runtime_find_task(array $contract, string $taskId): array
{
    foreach ((array)($contract['review_tasks'] ?? []) as $task) {
        if (is_array($task) && (string)($task['task_id'] ?? '') === $taskId) return $task;
    }
    throw new DomainException('Der Prüfpunkt ist nicht mehr offen. Bitte den aktuellen Stand neu laden.');
}

function be_cc_review_runtime_find_action(array $task, string $resolution): array
{
    foreach ((array)($task['actions'] ?? []) as $action) {
        if (is_array($action) && (string)($action['key'] ?? '') === $resolution) return $action;
    }
    throw new InvalidArgumentException('Diese Aktion ist für den Prüfpunkt nicht zulässig.');
}

function be_cc_review_runtime_values(array $action, array $payload): array
{
    $provided = is_array($payload['values'] ?? null) ? $payload['values'] : [];
    $values = [];
    foreach ((array)($action['defaults'] ?? []) as $name=>$value) $values[(string)$name] = is_scalar($value) ? trim((string)$value) : '';
    foreach ((array)($action['fields'] ?? []) as $field) {
        if (!is_array($field)) continue;
        $name = trim((string)($field['name'] ?? ''));
        if ($name === '') continue;
        $fallback = trim((string)($field['value'] ?? ''));
        $candidate = array_key_exists($name, $provided) ? $provided[$name] : $fallback;
        $value = is_scalar($candidate) ? trim((string)$candidate) : '';
        if (!empty($field['required']) && $value === '') {
            throw new InvalidArgumentException((string)($field['label'] ?? $name) . ' fehlt.');
        }
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        if ($options && $value !== '') {
            $allowed = array_map(static fn(array $option): string => trim((string)($option['value'] ?? '')), array_filter($options, 'is_array'));
            if (!in_array($value, $allowed, true)) throw new InvalidArgumentException('Die gewählte Option ist nicht mehr verfügbar.');
        }
        $values[$name] = $value;
    }
    foreach ($provided as $name=>$value) {
        if (!is_string($name) || array_key_exists($name, $values) || !is_scalar($value)) continue;
        $values[$name] = trim((string)$value);
    }
    return $values;
}

function be_cc_review_runtime_validate_updates(array $source, string $resolution, array $updates): array
{
    $allowedFields = [
        'title','date','end_date','schedule_type','time','time_status','time_details','time_not_applicable_allowed',
        'city','local_reference','location','meeting_point','address','address_status','category',
        'source_url','event_url','source_quality','ticket_url','access_status','description',
        'visual_key','visual_motif','visual_asset_id','visual_asset_role','visual_gap_id',
        'matched_event_id','duplicate_status','event_status','next_review_at',
    ];
    $normalized = [];
    foreach ($updates as $name=>$value) {
        $name = trim((string)$name);
        if (!in_array($name, $allowedFields, true)) continue;
        $normalized[$name] = trim((string)$value);
    }

    foreach (['date','end_date','next_review_at'] as $field) {
        if (($normalized[$field] ?? '') !== '' && !be_cc_valid_iso_day($normalized[$field])) {
            throw new InvalidArgumentException('Ungültiges Datum: ' . $field . '.');
        }
    }
    foreach (['source_url','event_url','ticket_url'] as $field) {
        if (($normalized[$field] ?? '') !== '' && !be_cc_contract_url_valid($normalized[$field])) {
            throw new InvalidArgumentException('Ungültige HTTP(S)-Adresse: ' . $field . '.');
        }
    }
    if (isset($normalized['time_status']) && $normalized['time_status'] !== '' && !in_array($normalized['time_status'], BE_CC_TIME_STATUSES, true)) {
        throw new InvalidArgumentException('Der Zeitstatus ist nicht zulässig.');
    }
    if (in_array($normalized['time_status'] ?? '', ['during_opening_hours','multiple_times'], true) && ($normalized['time_details'] ?? '') === '') {
        throw new InvalidArgumentException('Dieser Zeitstatus benötigt einen verständlichen Zeitraum oder Programmhinweis.');
    }
    if (($normalized['time_status'] ?? '') === 'time_not_applicable' && !be_cc_contract_bool($source, ['time_not_applicable_allowed']) && !in_array(be_cc_review_token($normalized['time_not_applicable_allowed'] ?? ''), ['1','true','yes','ja'], true)) {
        throw new InvalidArgumentException('„Uhrzeit nicht anwendbar“ ist für diesen Eventtyp nicht als sichere Ausnahme freigegeben.');
    }
    if (isset($normalized['access_status']) && $normalized['access_status'] !== '' && !in_array($normalized['access_status'], BE_CC_ACCESS_STATUSES, true)) {
        throw new InvalidArgumentException('Der Zugangsstatus ist nicht zulässig.');
    }
    if (($normalized['time_status'] ?? '') === 'fixed_time' && ($normalized['time'] ?? '') === '') {
        throw new InvalidArgumentException('Für eine feste Uhrzeit muss eine Beginnzeit gespeichert werden.');
    }
    if (($normalized['visual_asset_id'] ?? '') !== '') {
        $asset = be_cc_event_visual_asset_by_id($normalized['visual_asset_id']);
        if ($asset === null) throw new InvalidArgumentException('Das ausgewählte Bild ist nicht mehr als ready verfügbar.');
        $key = be_cc_review_token($normalized['visual_key'] ?? $source['visual_key'] ?? '');
        $motif = be_cc_review_token($normalized['visual_motif'] ?? $source['visual_motif'] ?? '');
        $role = be_cc_review_token($normalized['visual_asset_role'] ?? 'specific');
        if ($asset['visual_key'] !== $key) throw new InvalidArgumentException('Das ausgewählte Bild gehört zu einem anderen Bildbereich.');
        if ($role !== 'fallback' && $motif !== '' && $asset['visual_motif'] !== $motif) {
            throw new InvalidArgumentException('Das ausgewählte Bild passt nicht zum bestätigten Motiv.');
        }
        $normalized['visual_asset_role'] = $role === 'fallback' ? 'fallback' : 'specific';
    }
    if (in_array($resolution, ['time_not_published','time_conflict','snooze_case'], true)) {
        $until = trim((string)($normalized['next_review_at'] ?? ''));
        if ($until === '' || !be_cc_valid_iso_day($until) || $until <= date('Y-m-d')) {
            throw new InvalidArgumentException('Eine gültige zukünftige Wiedervorlage ist erforderlich.');
        }
    }
    return $normalized;
}

function be_cc_review_runtime_local_row(PDO $pdo, string $caseId): array
{
    $stmt = $pdo->prepare('SELECT * FROM control_cases WHERE id=:id');
    $stmt->execute(['id'=>$caseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('Der lokale Prüffall konnte nicht zurückgelesen werden.');
    return $row;
}

function be_cc_review_runtime_update_local(PDO $pdo, array $case, array $sourcePayload, array $review, string $state, string $blockedReason = '', ?string $snoozedUntil = null): array
{
    $caseId = (string)$case['id'];
    $from = (string)$case['state'];
    $update = $pdo->prepare(
        'UPDATE control_cases SET case_type=\'intake\', state=:state, source_payload_json=:payload, decision_ready=:ready, '
        . 'snoozed_until=:snoozed_until, blocked_reason=:blocked_reason, completed_at=NULL, updated_at=NOW() WHERE id=:id'
    );
    $update->execute([
        'state'=>$state,
        'payload'=>json_encode($sourcePayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        'ready'=>!empty($review['decision_gate']['ready']) ? 1 : 0,
        'snoozed_until'=>$snoozedUntil,
        'blocked_reason'=>$blockedReason !== '' ? $blockedReason : null,
        'id'=>$caseId,
    ]);
    if ($update->rowCount() !== 1) throw new RuntimeException('Der lokale Prüffall wurde nicht aktualisiert.');
    be_cc_record_event($pdo, $caseId, 'review_task_state', $from, $state, [
        'decision_ready'=>!empty($review['decision_gate']['ready']),
        'open_tasks'=>count((array)($review['decision_gate']['blockers'] ?? [])),
        'blocked_reason'=>$blockedReason,
    ]);
    return be_cc_review_runtime_local_row($pdo, $caseId);
}

function be_cc_review_runtime_completion(array $row, bool $visible): array
{
    return [
        'verified'=>true,
        'state'=>(string)($row['state'] ?? ''),
        'review_visible'=>$visible,
        'completed_at'=>$row['completed_at'] ?? null,
        'snoozed_until'=>$row['snoozed_until'] ?? null,
    ];
}

function be_cc_resolve_event_review_task(PDO $pdo, array $case, array $payload, string $operationId = ''): array
{
    if ((string)($case['source_system'] ?? '') !== 'inbox_feed') throw new DomainException('Teilaufgaben sind nur für Eventkandidaten definiert.');
    $currentSource = be_cc_inbox_direct_current_source($case);
    $source = $currentSource['source_payload'];
    $contract = be_cc_event_candidate_review_contract($source);
    $taskId = trim((string)($payload['task_id'] ?? ''));
    $taskRevision = trim((string)($payload['task_revision'] ?? ''));
    $resolution = trim((string)($payload['resolution'] ?? ''));
    if ($taskId === '' || $taskRevision === '' || $resolution === '') throw new InvalidArgumentException('Prüfpunkt, Revision und Aktion sind erforderlich.');
    $task = be_cc_review_runtime_find_task($contract, $taskId);
    if (!hash_equals((string)($task['task_revision'] ?? ''), $taskRevision)) {
        throw new DomainException('Der Prüfpunkt wurde zwischenzeitlich verändert. Bitte neu laden.');
    }
    $action = be_cc_review_runtime_find_action($task, $resolution);
    $values = be_cc_review_runtime_values($action, $payload);
    if (isset($values['suppress_until'])) {
        $values['next_review_at'] = $values['suppress_until'];
        unset($values['suppress_until']);
    }

    $terminalRejects = [
        'reject_duplicate'=>'duplicate', 'reject_expired'=>'rejected_not_event',
        'reject_cancelled'=>'rejected_not_event', 'reject_not_local'=>'rejected_not_local',
        'reject_weak_source'=>'rejected_source_weak',
    ];
    if (isset($terminalRejects[$resolution])) {
        $decision = be_cc_validate_operator_decision('reject', [
            'decision_class'=>$terminalRejects[$resolution],
            'decision_note'=>trim((string)($values['decision_note'] ?? $task['title'] ?? '')), 
        ]);
        $writeback = be_cc_writeback_inbox_decision_direct($case, 'reject', $decision);
        if ($operationId !== '') be_cc_operation_finish($pdo, $operationId, 'source_written', ['writeback'=>$writeback]);
        $result = be_cc_apply_action((string)$case['id'], 'reject', ['review_task'=>$taskId,'resolution'=>$resolution,'writeback'=>$writeback]);
        $local = be_cc_assert_case_postcondition($pdo, (string)$case['id'], 'rejected');
        be_cc_record_event($pdo, (string)$case['id'], 'review_task_resolved', (string)$case['state'], 'rejected', [
            'task_id'=>$taskId,'task_revision'=>$taskRevision,'resolution'=>$resolution,'evidence'=>$task['evidence'] ?? [],
        ]);
        return $result + ['writeback'=>$writeback,'review_task'=>['task_id'=>$taskId,'resolution'=>$resolution],
            'completion'=>['complete'=>true,'partial'=>false,'source_verified'=>true,'local'=>$local]];
    }

    if ($resolution === 'clear_visual_asset' || $resolution === 'reject_visual_motif') {
        $values['visual_asset_id'] = '';
        $values['visual_asset_role'] = '';
        if ($resolution === 'reject_visual_motif') $values['visual_motif'] = '';
    }
    if ($resolution === 'mark_distinct') $values['duplicate_status'] = 'distinct';
    if ($resolution === 'merge_existing') $values['duplicate_status'] = 'merge_existing';

    $updates = be_cc_review_runtime_validate_updates($source, $resolution, $values);
    $writeback = $updates ? be_cc_inbox_direct_canonical_update_verified($case, $updates, (string)$currentSource['fingerprint']) : [
        'transport'=>'none','source_verified'=>true,'written_fields'=>[],'source_payload'=>$source,
    ];
    $freshSource = is_array($writeback['source_payload'] ?? null) ? $writeback['source_payload'] : array_replace($source, $updates);

    if (!in_array($resolution, ['time_not_published','time_conflict','snooze_case'], true) && $operationId !== '') {
        be_cc_operation_finish($pdo, $operationId, 'source_written', ['writeback'=>$writeback]);
    }

    if (in_array($resolution, ['time_not_published','time_conflict','snooze_case'], true)) {
        $until = (string)($updates['next_review_at'] ?? '');
        $decision = be_cc_validate_operator_decision('snooze', [
            'decision_class'=>'snoozed','suppress_until'=>$until,
            'decision_note'=>trim((string)($values['decision_note'] ?? $task['title'] ?? 'Automatische Wiedervorlage')),
        ], ['today'=>date('Y-m-d')]);
        $decisionWriteback = be_cc_writeback_inbox_decision_direct($case, 'snooze', $decision);
        if ($operationId !== '') be_cc_operation_finish($pdo, $operationId, 'source_written', ['writeback'=>['fields'=>$writeback,'decision'=>$decisionWriteback]]);
        $payloadUpdate = $pdo->prepare('UPDATE control_cases SET source_payload_json=:payload, decision_ready=0, updated_at=NOW() WHERE id=:id');
        $payloadUpdate->execute(['payload'=>json_encode($freshSource, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'id'=>(string)$case['id']]);
        $result = be_cc_apply_action((string)$case['id'], 'snooze', [
            'until'=>$until,'review_task'=>$taskId,'resolution'=>$resolution,'writeback'=>$decisionWriteback,
        ]);
        $local = be_cc_assert_case_postcondition($pdo, (string)$case['id'], 'snoozed');
        be_cc_record_event($pdo, (string)$case['id'], 'review_task_waiting', (string)$case['state'], 'snoozed', [
            'task_id'=>$taskId,'task_revision'=>$taskRevision,'resolution'=>$resolution,'until'=>$until,'updates'=>$updates,
        ]);
        return $result + ['writeback'=>['fields'=>$writeback,'decision'=>$decisionWriteback],
            'review_task'=>['task_id'=>$taskId,'resolution'=>$resolution,'state'=>'waiting_external'],
            'completion'=>['complete'=>true,'partial'=>true,'source_verified'=>true,'local'=>$local]];
    }

    $review = be_cc_event_candidate_review_contract($freshSource);
    $targetTask = null;
    foreach ((array)($review['review_tasks'] ?? []) as $candidate) {
        if ((string)($candidate['task_id'] ?? '') === $taskId) { $targetTask = $candidate; break; }
    }
    $waiting = in_array($resolution, ['wait_for_visual_asset','merge_existing'], true);
    if (!$waiting && is_array($targetTask) && (string)($targetTask['task_revision'] ?? '') === $taskRevision) {
        throw new RuntimeException('Die Teilaktion wurde gespeichert, aber der Prüfpunkt ist unverändert offen.');
    }
    $state = $waiting ? 'waiting' : 'decision_required';
    $blockedReason = $waiting
        ? ($resolution === 'wait_for_visual_asset' ? 'Passendes Visual-Asset wird automatisch erzeugt oder geprüft.' : 'Bestehendes Event wird fachlich ergänzt.')
        : '';
    $localRow = be_cc_review_runtime_update_local($pdo, $case, $freshSource, $review, $state, $blockedReason);
    $reviewVisible = be_cc_case_is_review_visible($localRow);
    if (!$reviewVisible) throw new RuntimeException('Der offene Prüffall ist nach der Teilaktion nicht mehr sichtbar.');

    $auditAction = $waiting ? 'review_task_waiting' : 'review_task_resolved';
    be_cc_record_event($pdo, (string)$case['id'], $auditAction, (string)$case['state'], $state, [
        'task_id'=>$taskId,'task_revision'=>$taskRevision,'finding_code'=>$task['finding_code'] ?? '',
        'resolution'=>$resolution,'updates'=>$updates,'evidence'=>$task['evidence'] ?? [],
        'follow_up'=>$task['follow_up'] ?? [],'postcondition'=>$task['postcondition'] ?? [],
        'new_open_tasks'=>array_column((array)($review['decision_gate']['blockers'] ?? []), 'task_id'),
    ]);

    return [
        'id'=>(string)$case['id'], 'state'=>$state, 'decision_ready'=>!empty($review['decision_gate']['ready']),
        'writeback'=>$writeback, 'review_contract'=>$review,
        'review_task'=>['task_id'=>$taskId,'resolution'=>$resolution,'state'=>$waiting?'waiting_external':'resolved'],
        'completion'=>[
            'complete'=>true,'partial'=>true,'source_verified'=>true,
            'local'=>be_cc_review_runtime_completion($localRow, true),
            'postcondition'=>['target_task_closed'=>!is_array($targetTask) || $waiting,'review_recalculated'=>true],
        ],
    ];
}
