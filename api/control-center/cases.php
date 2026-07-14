<?php
declare(strict_types=1);

require __DIR__ . '/_schema.php';
require_once __DIR__ . '/_domain.php';

be_require_review_access();

function be_cc_case_priority_rank(string $priority): int
{
    return match ($priority) {
        'critical' => 0,
        'high' => 1,
        'normal' => 2,
        'low' => 3,
        default => 4,
    };
}

function be_cc_presented_cases(array $cases): array
{
    $result = [];
    $seenSourceIdentities = [];
    foreach ($cases as $case) {
        if (!is_array($case)) continue;
        if (($case['case_kind'] ?? '') === 'backlog_item' && ($case['source_system'] ?? '') !== 'growth_backlog') continue;
        $identity = trim((string)($case['source_system'] ?? '')) . ':' . trim((string)($case['source_reference'] ?? ''));
        if ($identity !== ':' && isset($seenSourceIdentities[$identity])) continue;
        if ($identity !== ':') $seenSourceIdentities[$identity] = true;
        if (($case['case_kind'] ?? '') === 'backlog_item' && trim((string)($case['next_action'] ?? '')) === 'Priorisieren oder als konkrete Aufgabe starten.') {
            $case['next_action'] = 'Priorisieren, konkretisieren oder als abgeschlossen markieren.';
        }
        $result[] = $case;
    }
    usort($result, static function(array $left, array $right): int {
        $leftBacklog = ($left['case_kind'] ?? '') === 'backlog_item';
        $rightBacklog = ($right['case_kind'] ?? '') === 'backlog_item';
        if ($leftBacklog && $rightBacklog) {
            $priority = be_cc_case_priority_rank((string)($left['priority'] ?? 'normal')) <=> be_cc_case_priority_rank((string)($right['priority'] ?? 'normal'));
            if ($priority !== 0) return $priority;
            return strcmp((string)($left['created_at'] ?? ''), (string)($right['created_at'] ?? ''));
        }
        return 0;
    });
    return $result;
}

try {
    be_cc_ensure_schema();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $cases = be_cc_presented_cases(be_cc_list_cases([
            'type' => trim((string)($_GET['type'] ?? '')),
            'state' => trim((string)($_GET['state'] ?? '')),
            'active' => trim((string)($_GET['active'] ?? '')),
        ]));

        be_json_response(200, [
            'status' => 'ok',
            'data' => ['items' => $cases, 'total' => count($cases)],
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');

        $type = be_cc_validate_enum(trim((string)($input['type'] ?? '')), BE_CC_TYPES, 'type');
        $state = be_cc_validate_enum(trim((string)($input['state'] ?? 'new')), BE_CC_STATES, 'state');
        $priority = be_cc_validate_enum(trim((string)($input['priority'] ?? 'normal')), BE_CC_PRIORITIES, 'priority');
        $title = trim((string)($input['title'] ?? ''));
        $sourceSystem = trim((string)($input['source_system'] ?? 'manual')) ?: 'manual';
        $sourceReference = trim((string)($input['source_reference'] ?? '')) ?: 'manual:' . be_cc_uuid();
        if ($title === '') throw new InvalidArgumentException('Title is required.');

        $id = be_cc_uuid();
        $pdo = be_db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO control_cases (
                    id, case_type, state, priority, title, reason, next_action,
                    object_type, object_id, object_title,
                    source_system, source_reference, source_payload_json,
                    due_at, snoozed_until, blocked_reason, decision_ready
                 ) VALUES (
                    :id,:case_type,:state,:priority,:title,:reason,:next_action,
                    :object_type,:object_id,:object_title,
                    :source_system,:source_reference,:source_payload_json,
                    :due_at,:snoozed_until,:blocked_reason,:decision_ready
                 )'
            );
            $stmt->execute([
                'id' => $id,
                'case_type' => $type,
                'state' => $state,
                'priority' => $priority,
                'title' => $title,
                'reason' => trim((string)($input['reason'] ?? '')) ?: null,
                'next_action' => trim((string)($input['next_action'] ?? '')) ?: null,
                'object_type' => trim((string)($input['object_type'] ?? '')) ?: null,
                'object_id' => trim((string)($input['object_id'] ?? '')) ?: null,
                'object_title' => trim((string)($input['object_title'] ?? '')) ?: null,
                'source_system' => $sourceSystem,
                'source_reference' => $sourceReference,
                'source_payload_json' => isset($input['source_payload'])
                    ? json_encode($input['source_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : null,
                'due_at' => !empty($input['due_at']) ? (new DateTimeImmutable((string)$input['due_at']))->format('Y-m-d H:i:s') : null,
                'snoozed_until' => !empty($input['snoozed_until']) ? (new DateTimeImmutable((string)$input['snoozed_until']))->format('Y-m-d H:i:s') : null,
                'blocked_reason' => trim((string)($input['blocked_reason'] ?? '')) ?: null,
                'decision_ready' => !empty($input['decision_ready']) ? 1 : 0,
            ]);
            be_cc_record_event($pdo, $id, 'create', null, $state, $input, 'operator');
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        $stmt = be_db()->prepare('SELECT * FROM control_cases WHERE id = :id');
        $stmt->execute(['id' => $id]);
        be_json_response(201, ['status' => 'ok', 'data' => be_cc_case_from_row($stmt->fetch())]);
    }

    header('Allow: GET, POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Die Vorgänge konnten nicht verarbeitet werden.',
        'error_message' => $error->getMessage(),
    ]);
}
