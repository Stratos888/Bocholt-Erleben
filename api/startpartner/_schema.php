<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

const BE_STARTPARTNER_REQUIRED_SCHEMA = [
    'startpartner_candidates' => [
        'id', 'source', 'organization_name', 'organization_name_normalized',
        'desired_content_scope', 'status', 'status_reason', 'revision',
        'assigned_to', 'next_review_at', 'status_changed_at', 'identity_key',
        'idempotency_key_hash', 'form_version', 'retention_review_at',
    ],
    'startpartner_candidate_contacts' => [
        'id', 'candidate_id', 'email', 'email_normalized', 'is_primary',
    ],
    'startpartner_candidate_events' => [
        'id', 'candidate_id', 'event_type', 'actor_type', 'created_at',
    ],
    'startpartner_candidate_qualifications' => [
        'id', 'candidate_id', 'dimension', 'assessment', 'reason',
        'evidence_text', 'evidence_url', 'operator_reference', 'evaluated_at',
        'revision', 'created_at', 'updated_at',
    ],
    'startpartner_candidate_decisions' => [
        'id', 'candidate_id', 'result', 'reason', 'operator_reference',
        'decided_at', 'candidate_revision', 'qualification_snapshot_json',
        'capacity_snapshot_json', 'reservation_reference', 'is_current',
        'current_guard', 'superseded_at', 'superseded_by_decision_id',
    ],
    'startpartner_candidate_reservations' => [
        'id', 'candidate_id', 'decision_id', 'status', 'active_guard',
        'starts_at', 'ends_at', 'capacity_snapshot_json',
        'soft_stop_exception_reason', 'operator_reference',
        'supersedes_reservation_id', 'released_at', 'release_reference',
    ],
    'startpartner_candidate_waitlist' => [
        'candidate_id', 'eligibility_reason', 'priority_reason',
        'next_review_at', 'contact_status', 'regular_alternative',
        'operator_reference', 'revision', 'created_at', 'updated_at',
    ],
    'startpartner_candidate_operations' => [
        'operation_id', 'candidate_id', 'action', 'payload_hash', 'status',
        'result_json', 'candidate_revision_before', 'candidate_revision_after',
        'created_at', 'updated_at', 'completed_at',
    ],
    'control_cases' => [
        'id', 'case_type', 'state', 'source_system', 'source_reference',
        'object_type', 'object_id',
    ],
    'control_case_events' => ['id', 'case_id', 'action', 'created_at'],
];

function be_startpartner_schema_gaps(PDO $pdo): array
{
    $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        return ['database' => ['No database selected.']];
    }

    $statement = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema_name'
    );
    $statement->execute(['schema_name' => $databaseName]);

    $present = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string)($row['TABLE_NAME'] ?? '');
        $column = (string)($row['COLUMN_NAME'] ?? '');
        if ($table !== '' && $column !== '') {
            $present[$table][$column] = true;
        }
    }

    $gaps = [];
    foreach (BE_STARTPARTNER_REQUIRED_SCHEMA as $table => $columns) {
        if (!isset($present[$table])) {
            $gaps[$table] = ['table missing'];
            continue;
        }
        foreach ($columns as $column) {
            if (!isset($present[$table][$column])) {
                $gaps[$table][] = $column;
            }
        }
    }

    return $gaps;
}

function be_startpartner_require_schema(PDO $pdo): void
{
    $gaps = be_startpartner_schema_gaps($pdo);
    if ($gaps !== []) {
        throw new RuntimeException(
            'STARTPARTNER_SCHEMA_MISSING: ' . json_encode(
                $gaps,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
    }
}
