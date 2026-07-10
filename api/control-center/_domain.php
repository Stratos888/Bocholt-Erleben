<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/_presentation.php';

const BE_CC_TYPES = ['intake', 'task', 'idea', 'information'];
const BE_CC_STATES = ['new', 'decision_required', 'open', 'in_progress', 'waiting', 'blocked', 'snoozed', 'done', 'rejected', 'information', 'parked'];
const BE_CC_PRIORITIES = ['low', 'normal', 'high', 'critical'];

function be_cc_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function be_cc_require_schema(): void
{
    $pdo = be_db();
    $exists = $pdo->query("SHOW TABLES LIKE 'control_cases'")->fetchColumn();
    if (!$exists) {
        be_json_response(503, [
            'status' => 'error',
            'code' => 'CONTROL_CENTER_SCHEMA_MISSING',
            'message' => 'Die Steuerzentrale ist noch nicht initialisiert.',
        ]);
    }
}

function be_cc_validate_enum(string $value, array $allowed, string $field): string
{
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException("Invalid {$field}.");
    }
    return $value;
}

function be_cc_case_from_row(array $row): array
{
    $now = new DateTimeImmutable('now');
    $due = !empty($row['due_at']) ? new DateTimeImmutable((string)$row['due_at']) : null;
    $snoozed = !empty($row['snoozed_until']) ? new DateTimeImmutable((string)$row['snoozed_until']) : null;
    $state = (string)$row['state'];
    $active = !in_array($state, ['done', 'rejected', 'information', 'parked'], true);
    $isSnoozed = $state === 'snoozed' && $snoozed instanceof DateTimeImmutable && $snoozed > $now;
    $overdue = $active && !$isSnoozed && $due instanceof DateTimeImmutable && $due < $now;

    $bucket = 'information';
    if ($active && !$isSnoozed) {
        if ($overdue || $state === 'blocked' || (string)$row['priority'] === 'critical') {
            $bucket = 'now';
        } elseif ($state === 'new' || $state === 'decision_required') {
            $bucket = 'inbox';
        } else {
            $bucket = 'next';
        }
    }

    $case = [
        'id' => (string)$row['id'],
        'type' => (string)$row['case_type'],
        'state' => $state,
        'priority' => (string)$row['priority'],
        'title' => (string)$row['title'],
        'reason' => (string)($row['reason'] ?? ''),
        'next_action' => (string)($row['next_action'] ?? ''),
        'object' => [
            'type' => (string)($row['object_type'] ?? ''),
            'id' => (string)($row['object_id'] ?? ''),
            'title' => (string)($row['object_title'] ?? ''),
        ],
        'source' => [
            'system' => (string)$row['source_system'],
            'reference' => (string)$row['source_reference'],
        ],
        'due_at' => $row['due_at'] ?? null,
        'snoozed_until' => $row['snoozed_until'] ?? null,
        'blocked_reason' => (string)($row['blocked_reason'] ?? ''),
        'decision_ready' => (bool)$row['decision_ready'],
        'overdue' => $overdue,
        'bucket' => $bucket,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'completed_at' => $row['completed_at'] ?? null,
    ];

    return array_merge($case, be_cc_case_presentation($row));
}

function be_cc_list_cases(array $filters = []): array
{
    be_cc_require_schema();
    $where = [];
    $params = [];

    if (!empty($filters['type'])) {
        $where[] = 'case_type = :type';
        $params['type'] = be_cc_validate_enum((string)$filters['type'], BE_CC_TYPES, 'type');
    }
    if (!empty($filters['state'])) {
        $where[] = 'state = :state';
        $params['state'] = be_cc_validate_enum((string)$filters['state'], BE_CC_STATES, 'state');
    }
    if (!empty($filters['active'])) {
        $where[] = "state NOT IN ('done','rejected','information','parked')";
    }

    $sql = 'SELECT * FROM control_cases';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY FIELD(priority,'critical','high','normal','low'), COALESCE(due_at,'9999-12-31'), updated_at DESC LIMIT 500";

    $stmt = be_db()->prepare($sql);
    $stmt->execute($params);
    return array_map('be_cc_case_from_row', $stmt->fetchAll());
}

function be_cc_record_event(PDO $pdo, string $caseId, string $action, ?string $from, ?string $to, array $payload = [], string $actor = 'operator'): void
{
    $stmt = $pdo->prepare('INSERT INTO control_case_events (case_id, action, from_state, to_state, actor, payload_json) VALUES (:case_id,:action,:from_state,:to_state,:actor,:payload)');
    $stmt->execute([
        'case_id' => $caseId,
        'action' => $action,
        'from_state' => $from,
        'to_state' => $to,
        'actor' => $actor,
        'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
    ]);
}

function be_cc_apply_action(string $caseId, string $action, array $payload): array
{
    be_cc_require_schema();
    $transitions = [
        'start' => ['new' => 'in_progress', 'open' => 'in_progress', 'decision_required' => 'in_progress'],
        'approve' => ['decision_required' => 'done', 'new' => 'done'],
        'reject' => ['new' => 'rejected', 'decision_required' => 'rejected', 'open' => 'rejected'],
        'complete' => ['open' => 'done', 'in_progress' => 'done', 'waiting' => 'done', 'blocked' => 'done'],
        'reopen' => ['done' => 'open', 'rejected' => 'open', 'parked' => 'open'],
        'park' => ['new' => 'parked', 'open' => 'parked'],
        'convert_to_task' => ['new' => 'open', 'decision_required' => 'open'],
        'wait' => ['open' => 'waiting', 'in_progress' => 'waiting'],
        'block' => ['open' => 'blocked', 'in_progress' => 'blocked', 'waiting' => 'blocked'],
        'snooze' => ['new' => 'snoozed', 'decision_required' => 'snoozed', 'open' => 'snoozed', 'waiting' => 'snoozed'],
        'resume' => ['snoozed' => 'open', 'waiting' => 'open', 'blocked' => 'open'],
    ];

    if (!isset($transitions[$action])) {
        throw new InvalidArgumentException('Unknown action.');
    }

    $pdo = be_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM control_cases WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $caseId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Case not found.');
        }

        $from = (string)$row['state'];
        $to = $transitions[$action][$from] ?? null;
        if ($to === null) {
            throw new DomainException('Action is not allowed for the current state.');
        }

        $fields = ['state = :state', 'updated_at = NOW()'];
        $params = ['state' => $to, 'id' => $caseId];
        if ($action === 'convert_to_task') {
            $fields[] = "case_type = 'task'";
            $fields[] = 'decision_ready = 0';
        }
        if (in_array($to, ['done', 'rejected'], true)) {
            $fields[] = 'completed_at = NOW()';
        } else {
            $fields[] = 'completed_at = NULL';
        }
        if ($action === 'snooze') {
            $until = trim((string)($payload['until'] ?? ''));
            if ($until === '') {
                throw new InvalidArgumentException('Snooze date is required.');
            }
            $fields[] = 'snoozed_until = :snoozed_until';
            $params['snoozed_until'] = (new DateTimeImmutable($until))->format('Y-m-d H:i:s');
        } elseif ($to !== 'snoozed') {
            $fields[] = 'snoozed_until = NULL';
        }
        if ($action === 'block') {
            $reason = trim((string)($payload['reason'] ?? ''));
            if ($reason === '') {
                throw new InvalidArgumentException('Block reason is required.');
            }
            $fields[] = 'blocked_reason = :blocked_reason';
            $params['blocked_reason'] = $reason;
        } elseif ($to !== 'blocked') {
            $fields[] = 'blocked_reason = NULL';
        }

        $update = $pdo->prepare('UPDATE control_cases SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $update->execute($params);
        be_cc_record_event($pdo, $caseId, $action, $from, $to, $payload);
        $pdo->commit();

        $stmt = $pdo->prepare('SELECT * FROM control_cases WHERE id = :id');
        $stmt->execute(['id' => $caseId]);
        return be_cc_case_from_row($stmt->fetch());
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
