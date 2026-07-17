<?php
declare(strict_types=1);

require __DIR__ . '/_submission_writeback.php';
require_once __DIR__ . '/_sources.php';
require_once __DIR__ . '/_sheet_inbox_source.php';
require_once __DIR__ . '/_submission_source.php';
require_once __DIR__ . '/_process_chain.php';
require_once __DIR__ . '/_editorial_runtime.php';
require_once __DIR__ . '/_inbox_decision_writeback.php';
require_once __DIR__ . '/_verified_source_writeback.php';
require_once __DIR__ . '/_staging_events_writeback.php';
require_once __DIR__ . '/_activity_audit_writeback.php';
require_once __DIR__ . '/_source_reconciliation.php';
require_once __DIR__ . '/_event_review_writeback.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

function be_cc_recheck_source_case(array $case): array
{
    $source = (string)($case['source_system'] ?? '');
    $reference = (string)($case['source_reference'] ?? '');
    if ($source === 'source_sync') {
        $key = str_starts_with($reference, 'sync:') ? substr($reference, 5) : trim((string)($case['object_id'] ?? ''));
        $callbacks = [
            'sheet_inbox'=>'be_cc_sync_sheet_inbox','inbox_feed'=>'be_cc_sync_inbox_feed','submissions'=>'be_cc_sync_submissions',
            'content_audit'=>'be_cc_sync_content_audit','growth_backlog'=>'be_cc_sync_growth_backlog',
        ];
        $callback = $callbacks[$key] ?? null;
        if ($callback === null || !is_callable($callback)) throw new RuntimeException('Für diese Datenquelle ist keine sichere Neuprüfung definiert.');
        $result = $callback();
        if (!is_array($result)) throw new RuntimeException('Die Datenquelle lieferte kein belastbares Prüfergebnis.');
        return ['verified'=>true,'message'=>'Datenquelle erfolgreich erneut synchronisiert.','evidence'=>$result];
    }
    if ($source === 'process_health') {
        $key = str_starts_with($reference, 'process:') ? substr($reference, 8) : trim((string)($case['object_id'] ?? ''));
        $health = be_cc_integrated_process_health(be_cc_content_ops_status(be_db()));
        foreach ((array)($health['items'] ?? []) as $item) {
            if ((string)($item['key'] ?? '') !== $key) continue;
            if ((string)($item['status'] ?? 'unknown') !== 'ok') throw new RuntimeException((string)($item['message'] ?? 'Die erneute Prozessprüfung zeigt weiterhin Handlungsbedarf.'));
            return ['verified'=>true,'message'=>(string)($item['message'] ?? 'Prozess erfolgreich erneut geprüft.'),'evidence'=>$item];
        }
        throw new RuntimeException('Der zugehörige Prozess konnte nicht eindeutig erneut geprüft werden.');
    }
    throw new RuntimeException('Dieser Systemfall unterstützt keine automatische Neuprüfung.');
}

$operationId = '';
try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');
    $caseId = trim((string)($input['case_id'] ?? ''));
    $action = trim((string)($input['action'] ?? ''));
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    if ($caseId === '' || $action === '') throw new InvalidArgumentException('Case and action are required.');

    be_cc_require_schema();
    $pdo = be_db();
    $lookup = $pdo->prepare('SELECT * FROM control_cases WHERE id = :id');
    $lookup->execute(['id'=>$caseId]);
    $case = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$case) throw new RuntimeException('Case not found.');

    if ($action === 'recheck') {
        $verification = be_cc_recheck_source_case($case);
        $result = be_cc_apply_action($caseId, 'complete', ['verification'=>$verification]);
        $local = be_cc_assert_case_postcondition($pdo, $caseId, 'done');
        be_json_response(200, ['status'=>'ok','data'=>$result + ['verification'=>$verification,'completion'=>['complete'=>true,'source_verified'=>true,'local'=>$local]]]);
    }

    $sourceSystem = (string)$case['source_system'];
    $editorialAction = in_array($action, ['approve','reject','snooze'], true);
    $decision = $editorialAction ? be_cc_editorial_validate_action($case, $action, $payload) : null;
    if (is_array($decision)) {
        $payload = array_merge($payload, [
            'decision_class'=>$decision['decision_class'],'decision_note'=>$decision['decision_note'],
            'suppress_until'=>$decision['suppress_until'],'recheck_at'=>$decision['recheck_at'],
            'reopen_policy'=>$decision['reopen_policy'],'source_fingerprint'=>$decision['source_fingerprint'],
            'content_fingerprint'=>$decision['content_fingerprint'],'event_updates'=>$decision['event_updates'],
        ]);
    }

    $operation = be_cc_editorial_operation_id($input, $caseId, $action);
    $operationId = $operation['id'];
    $payload['operation_id'] = $operationId;
    $payload['legacy_operation_id'] = $operation['legacy'];
    $reservation = be_cc_operation_reserve_persistent($pdo, $operationId, $caseId, $action, $payload);
    if ($reservation['decision'] === 'replay') {
        $row = $reservation['row'];
        if ((string)$row['status'] === 'completed' && !empty($row['result_json'])) {
            $stored = json_decode((string)$row['result_json'], true);
            be_json_response(200, ['status'=>'ok','data'=>is_array($stored) ? $stored : [],'idempotent_replay'=>true]);
        }
        if ((string)$row['status'] === 'failed') throw new RuntimeException((string)($row['error_text'] ?? 'Der frühere Vorgang ist fehlgeschlagen.'));
        throw new RuntimeException('Dieser Vorgang wird bereits verarbeitet.');
    }

    if ($sourceSystem === 'inbox_feed' && $action === 'resolve_review_task') {
        $result = be_cc_resolve_event_review_task($pdo, $case, $payload, $operationId);
        if (empty($result['completion']['source_verified'])) throw new RuntimeException('Die Event-Teilaktion wurde von der führenden Quelle nicht bestätigt.');
        be_cc_operation_finish($pdo, $operationId, 'completed', $result);
        be_json_response(200, ['status'=>'ok','data'=>$result]);
    }

    $effectiveAction = $action;
    $writebackMeta = ['source_verified' => true, 'transport' => 'local_only'];
    if ($sourceSystem === 'submission_db') {
        $writebackMeta = be_cc_writeback_submission($case, $action, $payload);
        $effectiveAction = trim((string)($writebackMeta['effective_action'] ?? $action));
    } elseif ($sourceSystem === 'growth_backlog' && in_array($action, ['edit_source','complete','reject'], true)) {
        be_cc_apply_source_writeback($case, $action, $payload);
        if ($action === 'edit_source') {
            $title = trim((string)($payload['title'] ?? $case['title']));
            $reason = trim((string)($payload['description'] ?? $case['reason']));
            $priority = match (mb_strtolower(trim((string)($payload['priority'] ?? '')), 'UTF-8')) {
                'hoch'=>'high','niedrig'=>'low','mittel'=>'normal',default=>(string)$case['priority'],
            };
            $update = $pdo->prepare('UPDATE control_cases SET title=:title,reason=:reason,priority=:priority,updated_at=NOW() WHERE id=:id');
            $update->execute(['title'=>$title ?: (string)$case['title'],'reason'=>$reason ?: null,'priority'=>$priority,'id'=>$caseId]);
            be_cc_record_event($pdo, $caseId, 'edit_source', (string)$case['state'], (string)$case['state'], $payload);
            $lookup->execute(['id'=>$caseId]);
            $result = be_cc_case_from_row($lookup->fetch());
            be_cc_operation_finish($pdo, $operationId, 'completed', $result);
            be_json_response(200, ['status'=>'ok','data'=>$result]);
        }
    } elseif ($sourceSystem === 'inbox_feed' && $editorialAction) {
        if ($action === 'approve') {
            $eventsTab = be_cc_events_tab_name();
            if ($eventsTab === 'Events_Staging') {
                $writebackMeta = be_cc_writeback_staging_inbox_approve_verified($case, $payload, $decision);
            } elseif ($eventsTab === 'Events') {
                $writebackMeta = be_cc_writeback_inbox_approve_verified($case, $payload, $decision);
            } else {
                throw new DomainException('Für diese Umgebung ist kein sicherer Event-Freigabepfad definiert.');
            }
        } else {
            $writebackMeta = be_cc_writeback_inbox_decision_direct($case, $action, $decision);
            $writebackMeta['source_verified'] = true;
        }
    } elseif ($sourceSystem === 'content_audit' && $editorialAction) {
        $auditSource = be_cc_editorial_payload($case);
        $contentType = be_cc_source_state_token($auditSource['content_type'] ?? $case['object_type'] ?? 'event');
        $writebackMeta = $contentType === 'activity'
            ? be_cc_writeback_activity_audit_verified($case, $action, $payload, $decision)
            : be_cc_writeback_audit_verified($case, $action, $payload, $decision);
        be_cc_record_editorial_feedback($pdo, $case, $decision, $payload);
    } elseif ($editorialAction) {
        be_cc_apply_source_writeback($case, $action, $payload);
    }

    if (empty($writebackMeta['source_verified'])) throw new RuntimeException('Der Quell-Writeback wurde nicht bestätigt.');
    be_cc_operation_finish($pdo, $operationId, 'source_written', ['writeback'=>$writebackMeta]);
    $payload['writeback'] = $writebackMeta;
    if ($effectiveAction === 'wait' && (string)$case['case_type'] === 'intake') {
        be_cc_apply_action($caseId, 'convert_to_task', []);
        $result = be_cc_apply_action($caseId, 'wait', $payload);
    } else {
        $result = be_cc_apply_action($caseId, $effectiveAction, $payload);
    }

    $expectedState = be_cc_action_expected_local_state($action, $effectiveAction);
    $local = $expectedState !== null ? be_cc_assert_case_postcondition($pdo, $caseId, $expectedState) : ['verified'=>true,'state'=>$result['state'] ?? ''];
    $result['writeback'] = $writebackMeta;
    $result['completion'] = ['complete'=>true,'source_verified'=>true,'local'=>$local];
    be_cc_operation_finish($pdo, $operationId, 'completed', $result);
    be_json_response(200, ['status'=>'ok','data'=>$result]);
} catch (InvalidArgumentException|DomainException $error) {
    if ($operationId !== '') { try { be_cc_operation_finish(be_db(), $operationId, 'failed', null, $error->getMessage()); } catch (Throwable) {} }
    be_json_response(422, ['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    if ($operationId !== '') { try { be_cc_operation_finish(be_db(), $operationId, 'failed', null, $error->getMessage()); } catch (Throwable) {} }
    be_json_response(409, ['status'=>'error','message'=>$error->getMessage()]);
} catch (Throwable $error) {
    if ($operationId !== '') { try { be_cc_operation_finish(be_db(), $operationId, 'failed', null, $error->getMessage()); } catch (Throwable) {} }
    be_json_response(500, ['status'=>'error','message'=>'Die Aktion konnte nicht ausgeführt werden.','error_message'=>$error->getMessage()]);
}
