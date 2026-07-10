<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';

function be_cc_upsert_source_case(array $case): string
{
    be_cc_require_schema();
    $pdo = be_db();
    $sourceSystem = trim((string)($case['source_system'] ?? ''));
    $sourceReference = trim((string)($case['source_reference'] ?? ''));
    if ($sourceSystem === '' || $sourceReference === '') throw new InvalidArgumentException('Source identity is required.');

    $type = be_cc_validate_enum((string)($case['type'] ?? 'intake'), BE_CC_TYPES, 'type');
    $state = be_cc_validate_enum((string)($case['state'] ?? 'new'), BE_CC_STATES, 'state');
    $priority = be_cc_validate_enum((string)($case['priority'] ?? 'normal'), BE_CC_PRIORITIES, 'priority');
    $title = trim((string)($case['title'] ?? '')) ?: 'Unbenannter Vorgang';

    $lookup = $pdo->prepare('SELECT id, state, case_type, snoozed_until FROM control_cases WHERE source_system = :system AND source_reference = :reference');
    $lookup->execute(['system' => $sourceSystem, 'reference' => $sourceReference]);
    $existing = $lookup->fetch();

    if ($existing) {
        $id = (string)$existing['id'];
        $existingState = (string)$existing['state'];
        if (in_array($existingState, ['done', 'rejected'], true)) return $id;

        $preserveState = in_array($existingState, ['in_progress','waiting','blocked'], true);
        if ($existingState === 'snoozed' && !empty($existing['snoozed_until'])) {
            $preserveState = new DateTimeImmutable((string)$existing['snoozed_until']) > new DateTimeImmutable('now');
        }
        $effectiveState = $preserveState ? $existingState : $state;
        $effectiveType = ((string)$existing['case_type'] === 'task' && $preserveState) ? 'task' : $type;

        $stmt = $pdo->prepare(
            'UPDATE control_cases SET case_type=:case_type,state=:state,priority=:priority,title=:title,reason=:reason,next_action=:next_action,
             object_type=:object_type,object_id=:object_id,object_title=:object_title,source_payload_json=:payload,decision_ready=:decision_ready,updated_at=NOW()
             WHERE id=:id'
        );
    } else {
        $id = be_cc_uuid();
        $effectiveState = $state;
        $effectiveType = $type;
        $stmt = $pdo->prepare(
            'INSERT INTO control_cases (id,case_type,state,priority,title,reason,next_action,object_type,object_id,object_title,source_system,source_reference,source_payload_json,decision_ready)
             VALUES (:id,:case_type,:state,:priority,:title,:reason,:next_action,:object_type,:object_id,:object_title,:source_system,:source_reference,:payload,:decision_ready)'
        );
    }

    $params = [
        'id' => $id,
        'case_type' => $effectiveType,
        'state' => $effectiveState,
        'priority' => $priority,
        'title' => $title,
        'reason' => trim((string)($case['reason'] ?? '')) ?: null,
        'next_action' => trim((string)($case['next_action'] ?? '')) ?: null,
        'object_type' => trim((string)($case['object_type'] ?? '')) ?: null,
        'object_id' => trim((string)($case['object_id'] ?? '')) ?: null,
        'object_title' => trim((string)($case['object_title'] ?? '')) ?: null,
        'payload' => json_encode($case['source_payload'] ?? $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'decision_ready' => !empty($case['decision_ready']) ? 1 : 0,
    ];
    if (!$existing) {
        $params['source_system'] = $sourceSystem;
        $params['source_reference'] = $sourceReference;
    }
    $stmt->execute($params);
    if (!$existing) be_cc_record_event($pdo, $id, 'source_import', null, $state, ['source_system' => $sourceSystem], 'system');
    return $id;
}

function be_cc_sync_inbox_feed(): array
{
    $path = dirname(__DIR__, 2) . '/data/inbox.json';
    if (!is_file($path) || trim((string)file_get_contents($path)) === '') return ['seen' => 0, 'upserted' => 0];
    $payload = json_decode((string)file_get_contents($path), true);
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : (is_array($payload) ? $payload : []);
    $count = 0;
    foreach ($items as $index => $item) {
        if (!is_array($item)) continue;
        $status = strtolower(trim((string)($item['status'] ?? 'review')));
        if (!in_array($status, ['review','open','new','pending'], true)) continue;
        $reference = trim((string)($item['id'] ?? $item['event_id'] ?? $item['source_url'] ?? $index));
        be_cc_upsert_source_case([
            'type' => 'intake', 'state' => 'decision_required', 'priority' => 'normal',
            'title' => trim((string)($item['title'] ?? 'Neuen Inhalt prüfen')),
            'reason' => trim((string)($item['description'] ?? $item['reason'] ?? 'Neuer Kandidat wartet auf Prüfung.')),
            'next_action' => 'Übernehmen, ablehnen oder zurückstellen.',
            'object_type' => 'event_candidate', 'object_id' => $reference,
            'object_title' => trim((string)($item['title'] ?? '')),
            'source_system' => 'inbox_feed', 'source_reference' => $reference,
            'source_payload' => $item, 'decision_ready' => true,
        ]);
        $count++;
    }
    return ['seen' => count($items), 'upserted' => $count];
}

function be_cc_sync_content_audit(): array
{
    $tab = be_content_audit_tab_name();
    $response = be_google_sheets_values_get($tab . '!A:AZ');
    $values = $response['values'] ?? [];
    if (!is_array($values) || count($values) < 4) return ['seen' => 0, 'upserted' => 0];
    $header = array_map(static fn($value) => trim((string)$value), is_array($values[2] ?? null) ? $values[2] : []);
    $index = array_flip($header);
    $cell = static function(array $row, string $name) use ($index): string {
        $position = $index[$name] ?? null;
        return is_int($position) && isset($row[$position]) ? trim((string)$row[$position]) : '';
    };
    $hidden = ['done','checked','verified','corrected','resolved','ignored','archived','snoozed'];
    $count = 0;
    for ($i = 3; $i < count($values); $i++) {
        $row = is_array($values[$i]) ? $values[$i] : [];
        $status = strtolower($cell($row, 'status') ?: 'open');
        $severity = strtolower($cell($row, 'severity'));
        $category = strtolower($cell($row, 'process_category'));
        $policy = strtolower($cell($row, 'automation_policy'));
        if (in_array($status, $hidden, true)) continue;
        if (in_array($category, ['visual_backlog_observation','visual_auto_patch_candidate'], true)) continue;
        if (in_array($policy, ['backlog_only_no_content_inbox','auto_patch_candidate_no_manual_inbox'], true)) continue;
        if (!in_array($severity, ['critical','review_needed'], true)) continue;
        $contentId = $cell($row, 'content_id');
        $issueCode = $cell($row, 'issue_code') ?: 'unknown';
        $reference = ($contentId ?: 'row-' . ($i + 1)) . ':' . $issueCode;
        $sourcePayload = array_combine($header, array_pad($row, count($header), '')) ?: [];
        $sourcePayload['row_number'] = $i + 1;
        be_cc_upsert_source_case([
            'type' => 'intake', 'state' => 'decision_required',
            'priority' => $severity === 'critical' ? 'critical' : 'normal',
            'title' => $cell($row, 'title') ?: 'Inhalt prüfen',
            'reason' => $cell($row, 'issue_text'),
            'next_action' => $cell($row, 'recommended_action') ?: 'Befund fachlich prüfen und entscheiden.',
            'object_type' => $cell($row, 'content_type') ?: 'content',
            'object_id' => $contentId, 'object_title' => $cell($row, 'title'),
            'source_system' => 'content_audit', 'source_reference' => $reference,
            'source_payload' => $sourcePayload, 'decision_ready' => true,
        ]);
        $count++;
    }
    return ['seen' => max(0, count($values) - 3), 'upserted' => $count];
}
