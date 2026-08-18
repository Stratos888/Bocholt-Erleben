<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';

const BE_STARTPARTNER_GATE2_STATUSES = [
    'new',
    'prequalifying',
    'contact_pending',
    'awaiting_response',
    'qualifying',
    'needs_information',
    'decision_ready',
    'accepted_pending_terms',
    'waitlisted',
    'routed_to_regular_product',
    'rejected',
    'withdrawn',
    'expired',
];

const BE_STARTPARTNER_GATE2_TERMINAL_STATUSES = [
    'routed_to_regular_product',
    'rejected',
    'withdrawn',
    'expired',
];

const BE_STARTPARTNER_QUALIFICATION_DIMENSIONS = [
    'local_relevance',
    'organization_contact',
    'content_sources',
    'editorial_fit',
    'content_leverage',
    'reach_leverage',
    'user_need',
    'maintenance_capability',
    'cooperation_readiness',
    'setup_effort',
    'support_effort',
    'regular_path',
    'legal_technical',
    'required_information',
];

const BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS = [
    'local_relevance',
    'organization_contact',
    'content_sources',
    'editorial_fit',
    'legal_technical',
    'required_information',
];

const BE_STARTPARTNER_ASSESSMENTS = ['unknown', 'weak', 'adequate', 'strong'];
const BE_STARTPARTNER_CAPACITY_SOFT_STOP = 6;
const BE_STARTPARTNER_CAPACITY_HARD_STOP = 8;
const BE_STARTPARTNER_RESERVATION_MAX_DAYS = 30;

final class BeStartpartnerConflictException extends RuntimeException
{
    public function __construct(string $message, public readonly array $currentState = [])
    {
        parent::__construct($message);
    }
}

function be_startpartner_gate2_validate_status(string $status): string
{
    if ($status === 'qualified') {
        return 'decision_ready';
    }
    return be_startpartner_validate_enum_value($status, BE_STARTPARTNER_GATE2_STATUSES, 'status');
}

function be_startpartner_gate2_operator_name(mixed $value): string
{
    return (string)be_startpartner_clean_text($value, 191, 'operator_name', true);
}

function be_startpartner_gate2_operation_id(mixed $value): string
{
    $operationId = trim((string)$value);
    if (
        strlen($operationId) < 8 ||
        strlen($operationId) > 128 ||
        !preg_match('/^[A-Za-z0-9._:-]+$/', $operationId)
    ) {
        throw new InvalidArgumentException('operation_id is invalid.');
    }
    return $operationId;
}

function be_startpartner_gate2_expected_revision(mixed $value): int
{
    $revision = filter_var($value, FILTER_VALIDATE_INT);
    if ($revision === false || $revision < 1) {
        throw new InvalidArgumentException('expected_revision must be a positive integer.');
    }
    return (int)$revision;
}

function be_startpartner_gate2_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('be_startpartner_gate2_canonicalize', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = be_startpartner_gate2_canonicalize($item);
    }
    return $value;
}

function be_startpartner_gate2_payload_hash(string $candidateId, string $action, array $input): string
{
    $payload = $input;
    unset($payload['operation_id']);
    $canonical = be_startpartner_gate2_canonicalize([
        'candidate_id' => $candidateId,
        'action' => $action,
        'payload' => $payload,
    ]);
    return hash(
        'sha256',
        json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
}

function be_startpartner_gate2_candidate_row(PDO $pdo, string $candidateId, bool $forUpdate = false): array
{
    $sql = 'SELECT * FROM startpartner_candidates WHERE id = :id LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute(['id' => $candidateId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Candidate not found.');
    }
    return $row;
}

function be_startpartner_gate2_qualification_rows(PDO $pdo, string $candidateId): array
{
    $statement = $pdo->prepare(
        'SELECT dimension, assessment, reason, evidence_text, evidence_url,
                operator_reference, evaluated_at, revision, created_at, updated_at
         FROM startpartner_candidate_qualifications
         WHERE candidate_id = :candidate_id'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $byDimension = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byDimension[(string)$row['dimension']] = $row;
    }

    $result = [];
    foreach (BE_STARTPARTNER_QUALIFICATION_DIMENSIONS as $dimension) {
        $row = $byDimension[$dimension] ?? null;
        $result[] = is_array($row)
            ? $row
            : [
                'dimension' => $dimension,
                'assessment' => 'unknown',
                'reason' => null,
                'evidence_text' => null,
                'evidence_url' => null,
                'operator_reference' => null,
                'evaluated_at' => null,
                'revision' => 0,
                'created_at' => null,
                'updated_at' => null,
            ];
    }
    return $result;
}

function be_startpartner_gate2_readiness(array $qualifications): array
{
    $byDimension = [];
    foreach ($qualifications as $row) {
        $byDimension[(string)($row['dimension'] ?? '')] = $row;
    }

    $blockers = [];
    foreach (BE_STARTPARTNER_QUALIFICATION_DIMENSIONS as $dimension) {
        $assessment = (string)($byDimension[$dimension]['assessment'] ?? 'unknown');
        if ($assessment === 'unknown') {
            $blockers[] = [
                'dimension' => $dimension,
                'code' => 'not_assessed',
                'message' => 'Bewertung fehlt.',
            ];
            continue;
        }
        if (
            in_array($dimension, BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS, true) &&
            !in_array($assessment, ['adequate', 'strong'], true)
        ) {
            $blockers[] = [
                'dimension' => $dimension,
                'code' => 'minimum_not_met',
                'message' => 'Mindestanforderung nicht erfüllt.',
            ];
        }
    }

    return [
        'ready' => $blockers === [],
        'assessed_count' => count(BE_STARTPARTNER_QUALIFICATION_DIMENSIONS) - count(array_filter(
            $qualifications,
            static fn(array $row): bool => (string)($row['assessment'] ?? 'unknown') === 'unknown'
        )),
        'total_count' => count(BE_STARTPARTNER_QUALIFICATION_DIMENSIONS),
        'blockers' => $blockers,
    ];
}

function be_startpartner_gate2_capacity(PDO $pdo, bool $forUpdate = false): array
{
    $sql = "SELECT id, candidate_id, ends_at
            FROM startpartner_candidate_reservations
            WHERE status = 'active'
            ORDER BY id";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $activeCount = count($rows);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $overdueCount = 0;
    foreach ($rows as $row) {
        if (new DateTimeImmutable((string)$row['ends_at'], new DateTimeZone('UTC')) < $now) {
            $overdueCount++;
        }
    }

    $acceptedWithoutReservation = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM startpartner_candidates c
         LEFT JOIN startpartner_candidate_reservations r
           ON r.candidate_id = c.id AND r.status = 'active'
         WHERE c.status = 'accepted_pending_terms' AND r.id IS NULL"
    )->fetchColumn();
    $reservationWithoutAccepted = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM startpartner_candidate_reservations r
         JOIN startpartner_candidates c ON c.id = r.candidate_id
         WHERE r.status = 'active' AND c.status <> 'accepted_pending_terms'"
    )->fetchColumn();

    return [
        'active_reservations' => $activeCount,
        'available_slots' => max(0, BE_STARTPARTNER_CAPACITY_HARD_STOP - $activeCount),
        'soft_stop' => $activeCount >= BE_STARTPARTNER_CAPACITY_SOFT_STOP,
        'hard_stop' => $activeCount >= BE_STARTPARTNER_CAPACITY_HARD_STOP,
        'soft_stop_at' => BE_STARTPARTNER_CAPACITY_SOFT_STOP,
        'hard_stop_at' => BE_STARTPARTNER_CAPACITY_HARD_STOP,
        'overdue_active_reservations' => $overdueCount,
        'consistent' => $acceptedWithoutReservation === 0 && $reservationWithoutAccepted === 0,
        'anomalies' => [
            'accepted_without_reservation' => $acceptedWithoutReservation,
            'active_reservation_without_accepted_status' => $reservationWithoutAccepted,
        ],
    ];
}

function be_startpartner_gate2_contacts(PDO $pdo, string $candidateId): array
{
    $statement = $pdo->prepare(
        'SELECT id, contact_name, contact_role, email, phone, is_primary, created_at, updated_at
         FROM startpartner_candidate_contacts
         WHERE candidate_id = :candidate_id
         ORDER BY is_primary DESC, id ASC'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function be_startpartner_gate2_current_decision(PDO $pdo, string $candidateId): ?array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_candidate_decisions
         WHERE candidate_id = :candidate_id AND is_current = 1
         ORDER BY id DESC LIMIT 1'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }
    foreach (['qualification_snapshot_json', 'capacity_snapshot_json'] as $field) {
        $row[str_replace('_json', '', $field)] = json_decode((string)$row[$field], true);
        unset($row[$field]);
    }
    return $row;
}

function be_startpartner_gate2_reservations(PDO $pdo, string $candidateId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_candidate_reservations
         WHERE candidate_id = :candidate_id
         ORDER BY id DESC'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['capacity_snapshot'] = json_decode((string)$row['capacity_snapshot_json'], true);
        unset($row['capacity_snapshot_json']);
    }
    unset($row);
    return $rows;
}

function be_startpartner_gate2_waitlist(PDO $pdo, string $candidateId): ?array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function be_startpartner_gate2_events(PDO $pdo, string $candidateId): array
{
    $statement = $pdo->prepare(
        'SELECT id, event_type, from_status, to_status, actor_type, actor_reference,
                payload_json, created_at
         FROM startpartner_candidate_events
         WHERE candidate_id = :candidate_id
         ORDER BY id ASC'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $events = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['payload'] = $row['payload_json'] === null
            ? null
            : json_decode((string)$row['payload_json'], true);
        unset($row['payload_json']);
        $events[] = $row;
    }
    return $events;
}

function be_startpartner_gate2_candidate_detail(PDO $pdo, string $candidateId, bool $includeEvents = true): array
{
    be_startpartner_require_schema($pdo);
    $row = be_startpartner_gate2_candidate_row($pdo, $candidateId);
    $qualifications = be_startpartner_gate2_qualification_rows($pdo, $candidateId);
    $readiness = be_startpartner_gate2_readiness($qualifications);
    $reservations = be_startpartner_gate2_reservations($pdo, $candidateId);
    $activeReservation = null;
    foreach ($reservations as $reservation) {
        if ((string)$reservation['status'] === 'active') {
            $activeReservation = $reservation;
            break;
        }
    }

    return [
        'id' => (string)$row['id'],
        'source' => (string)$row['source'],
        'source_reference' => $row['source_reference'] ?? null,
        'organization_name' => (string)$row['organization_name'],
        'website_url' => $row['website_url'] ?? null,
        'description_text' => $row['description_text'] ?? null,
        'desired_content_scope' => (string)$row['desired_content_scope'],
        'status' => (string)$row['status'],
        'status_reason' => $row['status_reason'] ?? null,
        'revision' => (int)$row['revision'],
        'assigned_to' => $row['assigned_to'] ?? null,
        'next_review_at' => $row['next_review_at'] ?? null,
        'status_changed_at' => $row['status_changed_at'] ?? null,
        'retention_review_at' => $row['retention_review_at'] ?? null,
        'closed_at' => $row['closed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'contacts' => be_startpartner_gate2_contacts($pdo, $candidateId),
        'qualifications' => $qualifications,
        'readiness' => $readiness,
        'decision' => be_startpartner_gate2_current_decision($pdo, $candidateId),
        'reservations' => $reservations,
        'active_reservation' => $activeReservation,
        'waitlist' => be_startpartner_gate2_waitlist($pdo, $candidateId),
        'capacity' => be_startpartner_gate2_capacity($pdo),
        'events' => $includeEvents ? be_startpartner_gate2_events($pdo, $candidateId) : [],
    ];
}

function be_startpartner_gate2_list_candidates(PDO $pdo, array $filters = []): array
{
    be_startpartner_require_schema($pdo);
    $where = [];
    $params = [];

    if (trim((string)($filters['status'] ?? '')) !== '') {
        $where[] = 'status = :status';
        $params['status'] = be_startpartner_gate2_validate_status(trim((string)$filters['status']));
    }
    if (trim((string)($filters['source'] ?? '')) !== '') {
        $where[] = 'source = :source';
        $params['source'] = be_startpartner_validate_enum_value(
            trim((string)$filters['source']),
            BE_STARTPARTNER_SOURCES,
            'source'
        );
    }
    if (trim((string)($filters['scope'] ?? '')) !== '') {
        $where[] = 'desired_content_scope = :scope';
        $params['scope'] = be_startpartner_validate_enum_value(
            trim((string)$filters['scope']),
            BE_STARTPARTNER_CONTENT_SCOPES,
            'scope'
        );
    }
    if (trim((string)($filters['assigned_to'] ?? '')) !== '') {
        $where[] = 'assigned_to = :assigned_to';
        $params['assigned_to'] = trim((string)$filters['assigned_to']);
    }
    if (!empty($filters['overdue'])) {
        $where[] = 'next_review_at IS NOT NULL AND next_review_at < UTC_TIMESTAMP()';
    }

    $limit = max(1, min(200, (int)($filters['limit'] ?? 100)));
    $sql = 'SELECT id FROM startpartner_candidates';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(next_review_at, \'9999-12-31\'), updated_at DESC LIMIT ' . $limit;
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    $items = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $candidateId) {
        $detail = be_startpartner_gate2_candidate_detail($pdo, (string)$candidateId, false);
        if (isset($filters['decision_ready']) && $filters['decision_ready'] !== '') {
            $expected = filter_var($filters['decision_ready'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($expected === null || (bool)$detail['readiness']['ready'] !== $expected) {
                continue;
            }
        }
        $items[] = $detail;
    }
    return $items;
}

function be_startpartner_gate2_case_state(string $status): string
{
    return match ($status) {
        'new' => 'new',
        'prequalifying', 'qualifying' => 'in_progress',
        'contact_pending', 'awaiting_response', 'needs_information', 'accepted_pending_terms' => 'waiting',
        'decision_ready' => 'decision_required',
        'waitlisted', 'expired' => 'parked',
        'routed_to_regular_product' => 'done',
        'rejected', 'withdrawn' => 'rejected',
        default => throw new DomainException('Unsupported candidate status.'),
    };
}

function be_startpartner_gate2_next_action(array $candidate, array $readiness, array $capacity): string
{
    return match ((string)$candidate['status']) {
        'new' => 'Vorqualifizierung starten.',
        'prequalifying' => 'Kontakt- und Inhaltsgrundlage prüfen.',
        'contact_pending' => 'Kontaktstatus prüfen.',
        'awaiting_response' => 'Rückmeldung abwarten oder Qualifizierung fortsetzen.',
        'qualifying' => $readiness['ready'] ? 'Entscheidungsreife bestätigen.' : 'Höchsten Qualifizierungsblocker bearbeiten.',
        'needs_information' => 'Fehlende Angaben intern klären.',
        'decision_ready' => $capacity['hard_stop'] ? 'Warteliste oder regulären Weg entscheiden.' : 'Aufnahme, Warteliste oder regulären Weg entscheiden.',
        'accepted_pending_terms' => 'Reservierung und offene Bedingungen prüfen.',
        'waitlisted' => 'Neubewertungstermin und Kapazität prüfen.',
        'routed_to_regular_product' => 'Regulären Produktweg fortführen.',
        'rejected' => 'Abschluss dokumentiert.',
        'withdrawn' => 'Rückzug dokumentiert.',
        'expired' => 'Reaktivierung oder Löschprüfung entscheiden.',
        default => 'Kandidaten prüfen.',
    };
}

function be_startpartner_gate2_project_control_case(
    PDO $pdo,
    array $candidate,
    array $readiness,
    array $capacity,
    string $actor
): void {
    $candidateId = (string)$candidate['id'];
    $caseState = be_startpartner_gate2_case_state((string)$candidate['status']);
    $primaryBlocker = $readiness['blockers'][0]['message'] ?? null;
    $reason = $candidate['status_reason'] ?? $primaryBlocker;
    $payload = json_encode([
        'candidate_id' => $candidateId,
        'candidate_status' => $candidate['status'],
        'candidate_revision' => (int)$candidate['revision'],
        'candidate_source' => $candidate['source'],
        'desired_content_scope' => $candidate['desired_content_scope'],
        'assigned_to' => $candidate['assigned_to'] ?? null,
        'next_review_at' => $candidate['next_review_at'] ?? null,
        'readiness' => $readiness,
        'capacity' => $capacity,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $select = $pdo->prepare(
        "SELECT id, state FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :reference
         FOR UPDATE"
    );
    $select->execute(['reference' => $candidateId]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);
    $priority = in_array((string)$candidate['status'], ['decision_ready', 'accepted_pending_terms'], true)
        ? 'high'
        : 'normal';
    if ($capacity['hard_stop'] && (string)$candidate['status'] === 'decision_ready') {
        $priority = 'critical';
    }
    $completedAt = in_array($caseState, ['done', 'rejected'], true) ? gmdate('Y-m-d H:i:s') : null;
    $nextAction = be_startpartner_gate2_next_action($candidate, $readiness, $capacity);

    if (is_array($existing)) {
        $statement = $pdo->prepare(
            'UPDATE control_cases
             SET state = :state, priority = :priority, title = :title, reason = :reason,
                 next_action = :next_action, object_type = :object_type,
                 object_id = :object_id, object_title = :object_title,
                 source_payload_json = :payload, due_at = :due_at,
                 decision_ready = :decision_ready, completed_at = :completed_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'state' => $caseState,
            'priority' => $priority,
            'title' => 'Startpartner prüfen: ' . (string)$candidate['organization_name'],
            'reason' => $reason,
            'next_action' => $nextAction,
            'object_type' => 'startpartner_candidate',
            'object_id' => $candidateId,
            'object_title' => (string)$candidate['organization_name'],
            'payload' => $payload,
            'due_at' => $candidate['next_review_at'] ?? null,
            'decision_ready' => (string)$candidate['status'] === 'decision_ready' ? 1 : 0,
            'completed_at' => $completedAt,
            'id' => (string)$existing['id'],
        ]);
        be_cc_record_event(
            $pdo,
            (string)$existing['id'],
            'startpartner_gate2_sync',
            (string)$existing['state'],
            $caseState,
            ['candidate_revision' => (int)$candidate['revision']],
            $actor
        );
        return;
    }

    $caseId = be_cc_uuid();
    $statement = $pdo->prepare(
        'INSERT INTO control_cases (
            id, case_type, state, priority, title, reason, next_action,
            object_type, object_id, object_title,
            source_system, source_reference, source_payload_json,
            due_at, decision_ready, completed_at
         ) VALUES (
            :id, :case_type, :state, :priority, :title, :reason, :next_action,
            :object_type, :object_id, :object_title,
            :source_system, :source_reference, :payload,
            :due_at, :decision_ready, :completed_at
         )'
    );
    $statement->execute([
        'id' => $caseId,
        'case_type' => 'intake',
        'state' => $caseState,
        'priority' => $priority,
        'title' => 'Startpartner prüfen: ' . (string)$candidate['organization_name'],
        'reason' => $reason,
        'next_action' => $nextAction,
        'object_type' => 'startpartner_candidate',
        'object_id' => $candidateId,
        'object_title' => (string)$candidate['organization_name'],
        'source_system' => 'startpartner_candidate',
        'source_reference' => $candidateId,
        'payload' => $payload,
        'due_at' => $candidate['next_review_at'] ?? null,
        'decision_ready' => (string)$candidate['status'] === 'decision_ready' ? 1 : 0,
        'completed_at' => $completedAt,
    ]);
    be_cc_record_event(
        $pdo,
        $caseId,
        'startpartner_gate2_create',
        null,
        $caseState,
        ['candidate_revision' => (int)$candidate['revision']],
        $actor
    );
}

function be_startpartner_gate2_record_event(
    PDO $pdo,
    string $candidateId,
    string $eventType,
    ?string $fromStatus,
    ?string $toStatus,
    string $operatorName,
    array $payload = []
): void {
    be_startpartner_record_event(
        $pdo,
        $candidateId,
        $eventType,
        $fromStatus,
        $toStatus,
        'operator',
        $operatorName,
        $payload
    );
}

function be_startpartner_gate2_update_candidate(PDO $pdo, string $candidateId, array $updates): void
{
    $allowed = [
        'source_reference', 'organization_name', 'organization_name_normalized',
        'website_url', 'description_text', 'desired_content_scope', 'identity_key',
        'status', 'status_reason', 'assigned_to', 'next_review_at',
        'status_changed_at', 'closed_at', 'revision',
    ];
    $parts = [];
    $params = ['id' => $candidateId];
    foreach ($updates as $column => $value) {
        if (!in_array($column, $allowed, true)) {
            throw new LogicException('Unsupported candidate update column.');
        }
        $parts[] = $column . ' = :' . $column;
        $params[$column] = $value;
    }
    $parts[] = 'updated_at = CURRENT_TIMESTAMP';
    $statement = $pdo->prepare(
        'UPDATE startpartner_candidates SET ' . implode(', ', $parts) . ' WHERE id = :id'
    );
    $statement->execute($params);
}

function be_startpartner_gate2_run_operation(
    PDO $pdo,
    string $candidateId,
    string $action,
    array $input,
    callable $mutation
): array {
    be_startpartner_require_schema($pdo);
    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $operatorName = be_startpartner_gate2_operator_name($input['operator_name'] ?? null);
    $expectedRevision = be_startpartner_gate2_expected_revision($input['expected_revision'] ?? null);
    $payloadHash = be_startpartner_gate2_payload_hash($candidateId, $action, $input);

    $pdo->beginTransaction();
    try {
        $operationStatement = $pdo->prepare(
            'SELECT * FROM startpartner_candidate_operations WHERE operation_id = :operation_id FOR UPDATE'
        );
        $operationStatement->execute(['operation_id' => $operationId]);
        $existingOperation = $operationStatement->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingOperation)) {
            if (
                (string)$existingOperation['candidate_id'] !== $candidateId ||
                (string)$existingOperation['action'] !== $action ||
                !hash_equals((string)$existingOperation['payload_hash'], $payloadHash)
            ) {
                throw new BeStartpartnerConflictException('operation_id was already used with a different payload.');
            }
            if ((string)$existingOperation['status'] !== 'completed' || $existingOperation['result_json'] === null) {
                throw new BeStartpartnerConflictException('operation_id is not replayable.');
            }
            $result = json_decode((string)$existingOperation['result_json'], true, 512, JSON_THROW_ON_ERROR);
            $result['idempotent_replay'] = true;
            $pdo->commit();
            return $result;
        }

        $candidate = be_startpartner_gate2_candidate_row($pdo, $candidateId, true);
        if ((int)$candidate['revision'] !== $expectedRevision) {
            $pdo->rollBack();
            throw new BeStartpartnerConflictException(
                'Candidate was changed in the meantime.',
                be_startpartner_gate2_candidate_detail($pdo, $candidateId)
            );
        }

        $insertOperation = $pdo->prepare(
            'INSERT INTO startpartner_candidate_operations (
                operation_id, candidate_id, action, payload_hash, status,
                candidate_revision_before
             ) VALUES (
                :operation_id, :candidate_id, :action, :payload_hash, :status,
                :candidate_revision_before
             )'
        );
        $insertOperation->execute([
            'operation_id' => $operationId,
            'candidate_id' => $candidateId,
            'action' => $action,
            'payload_hash' => $payloadHash,
            'status' => 'started',
            'candidate_revision_before' => $expectedRevision,
        ]);

        $mutationResult = $mutation($pdo, $candidate, $operatorName, $input);
        if (!is_array($mutationResult)) {
            throw new LogicException('Mutation result must be an array.');
        }
        $updates = (array)($mutationResult['candidate_updates'] ?? []);
        $newRevision = $expectedRevision + 1;
        $updates['revision'] = $newRevision;
        if (isset($updates['status']) && (string)$updates['status'] !== (string)$candidate['status']) {
            $updates['status_changed_at'] = gmdate('Y-m-d H:i:s');
        }
        be_startpartner_gate2_update_candidate($pdo, $candidateId, $updates);

        foreach ((array)($mutationResult['events'] ?? []) as $event) {
            be_startpartner_gate2_record_event(
                $pdo,
                $candidateId,
                (string)$event['type'],
                $event['from_status'] ?? (string)$candidate['status'],
                $event['to_status'] ?? ($updates['status'] ?? (string)$candidate['status']),
                $operatorName,
                (array)($event['payload'] ?? [])
            );
        }

        $updatedCandidate = be_startpartner_gate2_candidate_row($pdo, $candidateId);
        $qualifications = be_startpartner_gate2_qualification_rows($pdo, $candidateId);
        $readiness = be_startpartner_gate2_readiness($qualifications);
        $capacity = be_startpartner_gate2_capacity($pdo);
        be_startpartner_gate2_project_control_case(
            $pdo,
            $updatedCandidate,
            $readiness,
            $capacity,
            $operatorName
        );

        $result = [
            'candidate' => be_startpartner_gate2_candidate_detail($pdo, $candidateId),
            'operation_id' => $operationId,
            'idempotent_replay' => false,
        ];
        if (isset($mutationResult['meta'])) {
            $result['meta'] = $mutationResult['meta'];
        }
        $resultJson = json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $completeOperation = $pdo->prepare(
            "UPDATE startpartner_candidate_operations
             SET status = 'completed', result_json = :result_json,
                 candidate_revision_after = :revision_after,
                 completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE operation_id = :operation_id"
        );
        $completeOperation->execute([
            'result_json' => $resultJson,
            'revision_after' => $newRevision,
            'operation_id' => $operationId,
        ]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function be_startpartner_gate2_profile_update(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate2_run_operation(
        $pdo,
        $candidateId,
        'profile.update',
        $input,
        static function(PDO $pdo, array $candidate, string $operatorName, array $input): array {
            $updates = [];
            if (array_key_exists('organization_name', $input)) {
                $organizationName = (string)be_startpartner_clean_text(
                    $input['organization_name'],
                    190,
                    'organization_name',
                    true
                );
                $updates['organization_name'] = $organizationName;
                $updates['organization_name_normalized'] = be_startpartner_normalize_organization($organizationName);
            }
            if (array_key_exists('website_url', $input)) {
                $updates['website_url'] = be_startpartner_normalize_url($input['website_url']);
            }
            if (array_key_exists('description_text', $input)) {
                $updates['description_text'] = be_startpartner_clean_text(
                    $input['description_text'],
                    5000,
                    'description_text'
                );
            }
            if (array_key_exists('desired_content_scope', $input)) {
                $updates['desired_content_scope'] = be_startpartner_validate_enum_value(
                    trim((string)$input['desired_content_scope']),
                    BE_STARTPARTNER_CONTENT_SCOPES,
                    'desired_content_scope'
                );
            }
            if (array_key_exists('source_reference', $input)) {
                $updates['source_reference'] = be_startpartner_clean_text(
                    $input['source_reference'],
                    191,
                    'source_reference'
                );
            }
            if (array_key_exists('assigned_to', $input)) {
                $updates['assigned_to'] = be_startpartner_clean_text($input['assigned_to'], 191, 'assigned_to');
            }
            if (array_key_exists('next_review_at', $input)) {
                $text = trim((string)$input['next_review_at']);
                $updates['next_review_at'] = $text === ''
                    ? null
                    : (new DateTimeImmutable($text))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }

            $contactsWereUpdated = array_key_exists('contacts', $input);
            if ($contactsWereUpdated) {
                $contacts = be_startpartner_normalize_contacts(['contacts' => $input['contacts']]);
                $organizationNormalized = (string)($updates['organization_name_normalized'] ?? $candidate['organization_name_normalized']);
                $identityKey = hash('sha256', $organizationNormalized . '|' . $contacts[0]['email_normalized']);
                $duplicate = $pdo->prepare(
                    'SELECT id FROM startpartner_candidates WHERE identity_key = :identity_key AND id <> :id LIMIT 1'
                );
                $duplicate->execute(['identity_key' => $identityKey, 'id' => (string)$candidate['id']]);
                if ($duplicate->fetchColumn() !== false) {
                    throw new DomainException('Candidate identity already exists.');
                }
                $updates['identity_key'] = $identityKey;
                $delete = $pdo->prepare('DELETE FROM startpartner_candidate_contacts WHERE candidate_id = :candidate_id');
                $delete->execute(['candidate_id' => (string)$candidate['id']]);
                $insert = $pdo->prepare(
                    'INSERT INTO startpartner_candidate_contacts (
                        candidate_id, contact_name, contact_role, email, email_normalized, phone, is_primary
                     ) VALUES (
                        :candidate_id, :contact_name, :contact_role, :email, :email_normalized, :phone, :is_primary
                     )'
                );
                foreach ($contacts as $contact) {
                    $insert->execute([
                        'candidate_id' => (string)$candidate['id'],
                        'contact_name' => $contact['contact_name'],
                        'contact_role' => $contact['contact_role'],
                        'email' => $contact['email'],
                        'email_normalized' => $contact['email_normalized'],
                        'phone' => $contact['phone'],
                        'is_primary' => $contact['is_primary'] ? 1 : null,
                    ]);
                }
            }

            if (isset($updates['organization_name_normalized']) && !$contactsWereUpdated) {
                $primary = $pdo->prepare(
                    'SELECT email_normalized FROM startpartner_candidate_contacts
                     WHERE candidate_id = :candidate_id AND is_primary = 1
                     ORDER BY id ASC LIMIT 1'
                );
                $primary->execute(['candidate_id' => (string)$candidate['id']]);
                $primaryEmail = $primary->fetchColumn();
                if ($primaryEmail === false) {
                    throw new DomainException('Primary contact is missing.');
                }
                $identityKey = hash(
                    'sha256',
                    (string)$updates['organization_name_normalized'] . '|' . (string)$primaryEmail
                );
                $duplicate = $pdo->prepare(
                    'SELECT id FROM startpartner_candidates WHERE identity_key = :identity_key AND id <> :id LIMIT 1'
                );
                $duplicate->execute(['identity_key' => $identityKey, 'id' => (string)$candidate['id']]);
                if ($duplicate->fetchColumn() !== false) {
                    throw new DomainException('Candidate identity already exists.');
                }
                $updates['identity_key'] = $identityKey;
            }

            if ($updates === [] && !$contactsWereUpdated) {
                throw new InvalidArgumentException('No profile fields were supplied.');
            }
            return [
                'candidate_updates' => $updates,
                'events' => [[
                    'type' => 'profile_updated',
                    'payload' => ['fields' => array_keys($updates), 'operator' => $operatorName],
                ]],
            ];
        }
    );
}

function be_startpartner_gate2_normalize_qualification(array $item): array
{
    $dimension = be_startpartner_validate_enum_value(
        trim((string)($item['dimension'] ?? '')),
        BE_STARTPARTNER_QUALIFICATION_DIMENSIONS,
        'dimension'
    );
    $assessment = be_startpartner_validate_enum_value(
        trim((string)($item['assessment'] ?? 'unknown')),
        BE_STARTPARTNER_ASSESSMENTS,
        'assessment'
    );
    $reason = be_startpartner_clean_text($item['reason'] ?? null, 5000, 'reason');
    $evidenceText = be_startpartner_clean_text($item['evidence_text'] ?? null, 10000, 'evidence_text');
    $evidenceUrl = be_startpartner_normalize_url($item['evidence_url'] ?? null);
    if ($assessment !== 'unknown' && ($reason === null || ($evidenceText === null && $evidenceUrl === null))) {
        throw new InvalidArgumentException('Assessed dimensions require reason and evidence.');
    }
    return [
        'dimension' => $dimension,
        'assessment' => $assessment,
        'reason' => $reason,
        'evidence_text' => $evidenceText,
        'evidence_url' => $evidenceUrl,
    ];
}

function be_startpartner_gate2_qualification_update(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate2_run_operation(
        $pdo,
        $candidateId,
        'qualification.update',
        $input,
        static function(PDO $pdo, array $candidate, string $operatorName, array $input): array {
            $items = $input['qualifications'] ?? null;
            if (!is_array($items) || $items === []) {
                throw new InvalidArgumentException('qualifications must be a non-empty array.');
            }
            $normalized = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('Each qualification must be an object.');
                }
                $row = be_startpartner_gate2_normalize_qualification($item);
                if (isset($normalized[$row['dimension']])) {
                    throw new InvalidArgumentException('Qualification dimensions must be unique.');
                }
                $normalized[$row['dimension']] = $row;
            }

            $statement = $pdo->prepare(
                'INSERT INTO startpartner_candidate_qualifications (
                    candidate_id, dimension, assessment, reason, evidence_text,
                    evidence_url, operator_reference, evaluated_at, revision
                 ) VALUES (
                    :candidate_id, :dimension, :assessment, :reason, :evidence_text,
                    :evidence_url, :operator_reference, CURRENT_TIMESTAMP, 1
                 )
                 ON DUPLICATE KEY UPDATE
                    assessment = VALUES(assessment), reason = VALUES(reason),
                    evidence_text = VALUES(evidence_text), evidence_url = VALUES(evidence_url),
                    operator_reference = VALUES(operator_reference), evaluated_at = CURRENT_TIMESTAMP,
                    revision = revision + 1'
            );
            foreach ($normalized as $row) {
                $statement->execute([
                    'candidate_id' => (string)$candidate['id'],
                    'dimension' => $row['dimension'],
                    'assessment' => $row['assessment'],
                    'reason' => $row['reason'],
                    'evidence_text' => $row['evidence_text'],
                    'evidence_url' => $row['evidence_url'],
                    'operator_reference' => $operatorName,
                ]);
            }

            $readiness = be_startpartner_gate2_readiness(
                be_startpartner_gate2_qualification_rows($pdo, (string)$candidate['id'])
            );
            $updates = [];
            $events = [[
                'type' => 'qualifications_updated',
                'payload' => [
                    'dimensions' => array_keys($normalized),
                    'readiness' => $readiness,
                ],
            ]];
            if ((string)$candidate['status'] === 'decision_ready' && !$readiness['ready']) {
                $updates['status'] = 'qualifying';
                $updates['status_reason'] = 'Entscheidungsreife durch geänderte Qualifikation aufgehoben.';
                $events[] = [
                    'type' => 'decision_readiness_revoked',
                    'from_status' => 'decision_ready',
                    'to_status' => 'qualifying',
                    'payload' => ['blockers' => $readiness['blockers']],
                ];
            }
            return [
                'candidate_updates' => $updates,
                'events' => $events,
                'meta' => ['readiness' => $readiness],
            ];
        }
    );
}

function be_startpartner_gate2_supersede_current_decision(PDO $pdo, string $candidateId): ?int
{
    $statement = $pdo->prepare(
        'SELECT id FROM startpartner_candidate_decisions
         WHERE candidate_id = :candidate_id AND is_current = 1
         ORDER BY id DESC LIMIT 1 FOR UPDATE'
    );
    $statement->execute(['candidate_id' => $candidateId]);
    $decisionId = $statement->fetchColumn();
    if ($decisionId === false) {
        return null;
    }
    $update = $pdo->prepare(
        'UPDATE startpartner_candidate_decisions
         SET is_current = 0, superseded_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $update->execute(['id' => (int)$decisionId]);
    return (int)$decisionId;
}

function be_startpartner_gate2_insert_decision(
    PDO $pdo,
    array $candidate,
    string $result,
    string $reason,
    string $operatorName,
    array $readiness,
    array $capacity,
    array $input
): int {
    $previousId = be_startpartner_gate2_supersede_current_decision($pdo, (string)$candidate['id']);
    $statement = $pdo->prepare(
        'INSERT INTO startpartner_candidate_decisions (
            candidate_id, result, reason, operator_reference, candidate_revision,
            qualification_snapshot_json, capacity_snapshot_json,
            regular_alternative, waitlist_or_rejection_reason
         ) VALUES (
            :candidate_id, :result, :reason, :operator_reference, :candidate_revision,
            :qualification_snapshot_json, :capacity_snapshot_json,
            :regular_alternative, :waitlist_or_rejection_reason
         )'
    );
    $statement->execute([
        'candidate_id' => (string)$candidate['id'],
        'result' => $result,
        'reason' => $reason,
        'operator_reference' => $operatorName,
        'candidate_revision' => (int)$candidate['revision'],
        'qualification_snapshot_json' => json_encode(
            [
                'readiness' => $readiness,
                'qualifications' => be_startpartner_gate2_qualification_rows($pdo, (string)$candidate['id']),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
        'capacity_snapshot_json' => json_encode(
            $capacity,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
        'regular_alternative' => be_startpartner_clean_text($input['regular_alternative'] ?? null, 500, 'regular_alternative'),
        'waitlist_or_rejection_reason' => be_startpartner_clean_text(
            $input['waitlist_or_rejection_reason'] ?? null,
            5000,
            'waitlist_or_rejection_reason'
        ),
    ]);
    $decisionId = (int)$pdo->lastInsertId();
    if ($previousId !== null) {
        $link = $pdo->prepare(
            'UPDATE startpartner_candidate_decisions
             SET superseded_by_decision_id = :new_id WHERE id = :previous_id'
        );
        $link->execute(['new_id' => $decisionId, 'previous_id' => $previousId]);
    }
    return $decisionId;
}

function be_startpartner_gate2_parse_future_datetime(mixed $value, string $field, int $maxDays = 0): string
{
    $text = trim((string)$value);
    if ($text === '') {
        throw new InvalidArgumentException("{$field} is required.");
    }
    $date = (new DateTimeImmutable($text))->setTimezone(new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($date <= $now) {
        throw new InvalidArgumentException("{$field} must be in the future.");
    }
    if ($maxDays > 0 && $date > $now->modify('+' . $maxDays . ' days')) {
        throw new InvalidArgumentException("{$field} exceeds the allowed window.");
    }
    return $date->format('Y-m-d H:i:s');
}

function be_startpartner_gate2_action(PDO $pdo, string $candidateId, array $input): array
{
    $requestedAction = trim((string)($input['action'] ?? ''));
    $allowedActions = [
        'start_prequalification', 'mark_contact_pending', 'mark_awaiting_response',
        'start_qualification', 'mark_needs_information', 'mark_decision_ready',
        'accept_pending_terms', 'waitlist', 'route_regular', 'reject',
        'withdraw', 'expire', 'reopen', 'release_reservation',
        'extend_reservation', 'update_waitlist',
    ];
    $action = be_startpartner_validate_enum_value($requestedAction, $allowedActions, 'action');

    return be_startpartner_gate2_run_operation(
        $pdo,
        $candidateId,
        'action.' . $action,
        $input,
        static function(PDO $pdo, array $candidate, string $operatorName, array $input) use ($action): array {
            $status = (string)$candidate['status'];
            $reason = be_startpartner_clean_text($input['reason'] ?? null, 5000, 'reason');
            $readiness = be_startpartner_gate2_readiness(
                be_startpartner_gate2_qualification_rows($pdo, (string)$candidate['id'])
            );
            $capacity = be_startpartner_gate2_capacity($pdo, true);
            $updates = [];
            $events = [];
            $meta = [];

            $transition = static function(array $allowedFrom, string $toStatus) use ($status, &$updates): void {
                if (!in_array($status, $allowedFrom, true)) {
                    throw new DomainException("Transition {$status} -> {$toStatus} is not allowed.");
                }
                $updates['status'] = $toStatus;
            };

            if ($action === 'start_prequalification') {
                $transition(['new'], 'prequalifying');
            } elseif ($action === 'mark_contact_pending') {
                $transition(['new', 'prequalifying', 'qualifying', 'needs_information'], 'contact_pending');
            } elseif ($action === 'mark_awaiting_response') {
                $transition(['contact_pending'], 'awaiting_response');
            } elseif ($action === 'start_qualification') {
                $transition(['new', 'prequalifying', 'contact_pending', 'awaiting_response', 'needs_information', 'waitlisted'], 'qualifying');
                if ($status === 'waitlisted') {
                    be_startpartner_gate2_supersede_current_decision($pdo, (string)$candidate['id']);
                }
                $pdo->prepare('DELETE FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id')
                    ->execute(['candidate_id' => (string)$candidate['id']]);
            } elseif ($action === 'mark_needs_information') {
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                $transition(['prequalifying', 'contact_pending', 'awaiting_response', 'qualifying', 'decision_ready'], 'needs_information');
            } elseif ($action === 'mark_decision_ready') {
                if (!$readiness['ready']) {
                    throw new DomainException('Candidate still has qualification blockers.');
                }
                $transition(['qualifying', 'needs_information'], 'decision_ready');
            } elseif ($action === 'accept_pending_terms') {
                if ($status !== 'decision_ready') {
                    throw new DomainException('Only decision-ready candidates can receive a reservation.');
                }
                if (!$readiness['ready']) {
                    throw new DomainException('Candidate still has qualification blockers.');
                }
                if ($capacity['hard_stop']) {
                    throw new DomainException('Hard capacity stop reached.');
                }
                $capacityReason = be_startpartner_clean_text(
                    $input['capacity_exception_reason'] ?? null,
                    5000,
                    'capacity_exception_reason'
                );
                if ($capacity['soft_stop'] && $capacityReason === null) {
                    throw new InvalidArgumentException('capacity_exception_reason is required at the soft stop.');
                }
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                $endsAt = be_startpartner_gate2_parse_future_datetime(
                    $input['reservation_ends_at'] ?? null,
                    'reservation_ends_at',
                    BE_STARTPARTNER_RESERVATION_MAX_DAYS
                );
                $decisionId = be_startpartner_gate2_insert_decision(
                    $pdo,
                    $candidate,
                    'accepted_pending_terms',
                    $reason,
                    $operatorName,
                    $readiness,
                    $capacity,
                    $input
                );
                $reservation = $pdo->prepare(
                    'INSERT INTO startpartner_candidate_reservations (
                        candidate_id, decision_id, status, starts_at, ends_at,
                        capacity_snapshot_json, soft_stop_exception_reason,
                        operator_reference
                     ) VALUES (
                        :candidate_id, :decision_id, \'active\', UTC_TIMESTAMP(), :ends_at,
                        :capacity_snapshot_json, :soft_stop_exception_reason,
                        :operator_reference
                     )'
                );
                $reservation->execute([
                    'candidate_id' => (string)$candidate['id'],
                    'decision_id' => $decisionId,
                    'ends_at' => $endsAt,
                    'capacity_snapshot_json' => json_encode(
                        $capacity,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                    'soft_stop_exception_reason' => $capacityReason,
                    'operator_reference' => $operatorName,
                ]);
                $reservationId = (int)$pdo->lastInsertId();
                $pdo->prepare(
                    'UPDATE startpartner_candidate_decisions
                     SET reservation_reference = :reservation_id WHERE id = :decision_id'
                )->execute(['reservation_id' => $reservationId, 'decision_id' => $decisionId]);
                $updates['status'] = 'accepted_pending_terms';
                $meta['reservation_id'] = $reservationId;
            } elseif (in_array($action, ['waitlist', 'route_regular', 'reject', 'withdraw', 'expire'], true)) {
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                if (in_array($status, BE_STARTPARTNER_GATE2_TERMINAL_STATUSES, true)) {
                    throw new DomainException('Terminal candidates must be reopened explicitly.');
                }
                $allowedDecisionStates = match ($action) {
                    'waitlist' => ['qualifying', 'needs_information', 'decision_ready'],
                    'route_regular', 'reject' => ['qualifying', 'needs_information', 'decision_ready', 'waitlisted'],
                    'withdraw', 'expire' => [
                        'new', 'prequalifying', 'contact_pending', 'awaiting_response',
                        'qualifying', 'needs_information', 'decision_ready', 'waitlisted',
                    ],
                };
                if (!in_array($status, $allowedDecisionStates, true)) {
                    throw new DomainException("Action {$action} is not allowed from {$status}.");
                }
                $result = match ($action) {
                    'waitlist' => 'waitlisted',
                    'route_regular' => 'routed_to_regular_product',
                    'reject' => 'rejected',
                    'withdraw' => 'withdrawn',
                    'expire' => 'expired',
                };
                be_startpartner_gate2_insert_decision(
                    $pdo,
                    $candidate,
                    $result,
                    $reason,
                    $operatorName,
                    $readiness,
                    $capacity,
                    $input
                );
                if ($result === 'waitlisted') {
                    $eligibilityReason = (string)be_startpartner_clean_text(
                        $input['eligibility_reason'] ?? $reason,
                        5000,
                        'eligibility_reason',
                        true
                    );
                    $priorityReason = (string)be_startpartner_clean_text(
                        $input['priority_reason'] ?? null,
                        5000,
                        'priority_reason',
                        true
                    );
                    $nextReviewAt = be_startpartner_gate2_parse_future_datetime(
                        $input['next_review_at'] ?? null,
                        'next_review_at'
                    );
                    $waitlist = $pdo->prepare(
                        'INSERT INTO startpartner_candidate_waitlist (
                            candidate_id, eligibility_reason, priority_reason, next_review_at,
                            contact_status, regular_alternative, operator_reference, revision
                         ) VALUES (
                            :candidate_id, :eligibility_reason, :priority_reason, :next_review_at,
                            :contact_status, :regular_alternative, :operator_reference, 1
                         )
                         ON DUPLICATE KEY UPDATE
                            eligibility_reason = VALUES(eligibility_reason),
                            priority_reason = VALUES(priority_reason),
                            next_review_at = VALUES(next_review_at),
                            contact_status = VALUES(contact_status),
                            regular_alternative = VALUES(regular_alternative),
                            operator_reference = VALUES(operator_reference),
                            revision = revision + 1'
                    );
                    $waitlist->execute([
                        'candidate_id' => (string)$candidate['id'],
                        'eligibility_reason' => $eligibilityReason,
                        'priority_reason' => $priorityReason,
                        'next_review_at' => $nextReviewAt,
                        'contact_status' => be_startpartner_validate_enum_value(
                            trim((string)($input['contact_status'] ?? 'not_contacted')),
                            ['not_contacted', 'contact_pending', 'contacted', 'paused'],
                            'contact_status'
                        ),
                        'regular_alternative' => be_startpartner_clean_text(
                            $input['regular_alternative'] ?? null,
                            500,
                            'regular_alternative'
                        ),
                        'operator_reference' => $operatorName,
                    ]);
                    $updates['next_review_at'] = $nextReviewAt;
                } else {
                    $pdo->prepare('DELETE FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id')
                        ->execute(['candidate_id' => (string)$candidate['id']]);
                    $updates['next_review_at'] = null;
                }
                $updates['status'] = $result;
                $updates['closed_at'] = in_array($result, BE_STARTPARTNER_GATE2_TERMINAL_STATUSES, true)
                    ? gmdate('Y-m-d H:i:s')
                    : null;
            } elseif ($action === 'reopen') {
                if (!in_array($status, BE_STARTPARTNER_GATE2_TERMINAL_STATUSES, true)) {
                    throw new DomainException('Only terminal candidates can be reopened.');
                }
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                be_startpartner_gate2_supersede_current_decision($pdo, (string)$candidate['id']);
                $updates['status'] = 'qualifying';
                $updates['closed_at'] = null;
            } elseif ($action === 'release_reservation') {
                if ($status !== 'accepted_pending_terms') {
                    throw new DomainException('Candidate has no releasable reservation state.');
                }
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                $reservation = $pdo->prepare(
                    "SELECT id FROM startpartner_candidate_reservations
                     WHERE candidate_id = :candidate_id AND status = 'active'
                     ORDER BY id DESC LIMIT 1 FOR UPDATE"
                );
                $reservation->execute(['candidate_id' => (string)$candidate['id']]);
                $reservationId = $reservation->fetchColumn();
                if ($reservationId === false) {
                    throw new DomainException('Active reservation is missing.');
                }
                $pdo->prepare(
                    "UPDATE startpartner_candidate_reservations
                     SET status = 'released', released_at = CURRENT_TIMESTAMP,
                         release_reference = :release_reference
                     WHERE id = :id"
                )->execute([
                    'release_reference' => $reason,
                    'id' => (int)$reservationId,
                ]);
                be_startpartner_gate2_supersede_current_decision($pdo, (string)$candidate['id']);
                $updates['status'] = be_startpartner_gate2_validate_status(
                    trim((string)($input['target_status'] ?? 'decision_ready'))
                );
                if (!in_array($updates['status'], ['decision_ready', 'qualifying'], true)) {
                    throw new InvalidArgumentException('target_status is invalid for reservation release.');
                }
                $meta['released_reservation_id'] = (int)$reservationId;
            } elseif ($action === 'extend_reservation') {
                if ($status !== 'accepted_pending_terms') {
                    throw new DomainException('Only reserved candidates can extend a reservation.');
                }
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                $endsAt = be_startpartner_gate2_parse_future_datetime(
                    $input['reservation_ends_at'] ?? null,
                    'reservation_ends_at',
                    BE_STARTPARTNER_RESERVATION_MAX_DAYS
                );
                $reservation = $pdo->prepare(
                    "SELECT * FROM startpartner_candidate_reservations
                     WHERE candidate_id = :candidate_id AND status = 'active'
                     ORDER BY id DESC LIMIT 1 FOR UPDATE"
                );
                $reservation->execute(['candidate_id' => (string)$candidate['id']]);
                $active = $reservation->fetch(PDO::FETCH_ASSOC);
                if (!is_array($active)) {
                    throw new DomainException('Active reservation is missing.');
                }
                $pdo->prepare(
                    "UPDATE startpartner_candidate_reservations
                     SET status = 'released', released_at = CURRENT_TIMESTAMP,
                         release_reference = :release_reference
                     WHERE id = :id"
                )->execute(['release_reference' => $reason, 'id' => (int)$active['id']]);
                $insert = $pdo->prepare(
                    'INSERT INTO startpartner_candidate_reservations (
                        candidate_id, decision_id, status, starts_at, ends_at,
                        capacity_snapshot_json, soft_stop_exception_reason,
                        operator_reference, supersedes_reservation_id
                     ) VALUES (
                        :candidate_id, :decision_id, \'active\', UTC_TIMESTAMP(), :ends_at,
                        :capacity_snapshot_json, :soft_stop_exception_reason,
                        :operator_reference, :supersedes_reservation_id
                     )'
                );
                $insert->execute([
                    'candidate_id' => (string)$candidate['id'],
                    'decision_id' => $active['decision_id'],
                    'ends_at' => $endsAt,
                    'capacity_snapshot_json' => json_encode(
                        $capacity,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                    'soft_stop_exception_reason' => $active['soft_stop_exception_reason'],
                    'operator_reference' => $operatorName,
                    'supersedes_reservation_id' => (int)$active['id'],
                ]);
                $meta['reservation_id'] = (int)$pdo->lastInsertId();
            } elseif ($action === 'update_waitlist') {
                if ($status !== 'waitlisted') {
                    throw new DomainException('Candidate is not waitlisted.');
                }
                $nextReviewAt = be_startpartner_gate2_parse_future_datetime(
                    $input['next_review_at'] ?? null,
                    'next_review_at'
                );
                $waitlist = $pdo->prepare(
                    'UPDATE startpartner_candidate_waitlist
                     SET eligibility_reason = :eligibility_reason,
                         priority_reason = :priority_reason,
                         next_review_at = :next_review_at,
                         contact_status = :contact_status,
                         regular_alternative = :regular_alternative,
                         operator_reference = :operator_reference,
                         revision = revision + 1,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE candidate_id = :candidate_id'
                );
                $waitlist->execute([
                    'eligibility_reason' => (string)be_startpartner_clean_text(
                        $input['eligibility_reason'] ?? null,
                        5000,
                        'eligibility_reason',
                        true
                    ),
                    'priority_reason' => (string)be_startpartner_clean_text(
                        $input['priority_reason'] ?? null,
                        5000,
                        'priority_reason',
                        true
                    ),
                    'next_review_at' => $nextReviewAt,
                    'contact_status' => be_startpartner_validate_enum_value(
                        trim((string)($input['contact_status'] ?? 'not_contacted')),
                        ['not_contacted', 'contact_pending', 'contacted', 'paused'],
                        'contact_status'
                    ),
                    'regular_alternative' => be_startpartner_clean_text(
                        $input['regular_alternative'] ?? null,
                        500,
                        'regular_alternative'
                    ),
                    'operator_reference' => $operatorName,
                    'candidate_id' => (string)$candidate['id'],
                ]);
                if ($waitlist->rowCount() !== 1) {
                    throw new DomainException('Waitlist owner is missing.');
                }
                $updates['next_review_at'] = $nextReviewAt;
            }

            if ($reason !== null) {
                $updates['status_reason'] = $reason;
            } elseif (isset($updates['status']) && $updates['status'] !== $status) {
                $updates['status_reason'] = null;
            }
            $events[] = [
                'type' => 'gate2_action_applied',
                'from_status' => $status,
                'to_status' => $updates['status'] ?? $status,
                'payload' => [
                    'action' => $action,
                    'reason' => $reason,
                    'readiness' => $readiness,
                    'capacity' => $capacity,
                ],
            ];
            return [
                'candidate_updates' => $updates,
                'events' => $events,
                'meta' => $meta,
            ];
        }
    );
}
