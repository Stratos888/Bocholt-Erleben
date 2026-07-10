<?php
declare(strict_types=1);

require __DIR__ . '/_submission_writeback.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');

    $caseId = trim((string)($input['case_id'] ?? ''));
    $action = trim((string)($input['action'] ?? ''));
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    if ($caseId === '' || $action === '') throw new InvalidArgumentException('Case and action are required.');

    be_cc_require_schema();
    $lookup = be_db()->prepare('SELECT * FROM control_cases WHERE id = :id');
    $lookup->execute(['id' => $caseId]);
    $case = $lookup->fetch();
    if (!$case) throw new RuntimeException('Case not found.');

    $sourceSystem = (string)$case['source_system'];
    $effectiveAction = $action;
    if ($sourceSystem === 'submission_db') {
        $effectiveAction = be_cc_writeback_submission($case, $action, $payload);
    } elseif ($sourceSystem === 'growth_backlog' && in_array($action, ['edit_source','convert_to_task','complete','reject'], true)) {
        be_cc_apply_source_writeback($case, $action, $payload);
        if ($action === 'edit_source') {
            $title = trim((string)($payload['title'] ?? $case['title']));
            $reason = trim((string)($payload['description'] ?? $case['reason']));
            $priority = match (mb_strtolower(trim((string)($payload['priority'] ?? '')), 'UTF-8')) {
                'hoch' => 'high', 'niedrig' => 'low', 'mittel' => 'normal', default => (string)$case['priority'],
            };
            $update = be_db()->prepare('UPDATE control_cases SET title=:title, reason=:reason, priority=:priority, updated_at=NOW() WHERE id=:id');
            $update->execute(['title' => $title ?: (string)$case['title'], 'reason' => $reason ?: null, 'priority' => $priority, 'id' => $caseId]);
            be_cc_record_event(be_db(), $caseId, 'edit_source', (string)$case['state'], (string)$case['state'], $payload);
            $lookup->execute(['id' => $caseId]);
            be_json_response(200, ['status' => 'ok', 'data' => be_cc_case_from_row($lookup->fetch())]);
        }
    } elseif (in_array($action, ['approve', 'reject', 'snooze'], true)) {
        be_cc_apply_source_writeback($case, $action, $payload);
    }

    if ($effectiveAction === 'wait' && (string)$case['case_type'] === 'intake') {
        be_cc_apply_action($caseId, 'convert_to_task', []);
        $result = be_cc_apply_action($caseId, 'wait', $payload);
    } else {
        $result = be_cc_apply_action($caseId, $effectiveAction, $payload);
    }

    be_json_response(200, ['status' => 'ok', 'data' => $result]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(409, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Die Aktion konnte nicht ausgeführt werden.',
        'error_message' => $error->getMessage(),
    ]);
}
