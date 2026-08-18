<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_contract.php';

const BE_STARTPARTNER_GATE4_REQUIRED_SCHEMA = [
    'startpartner_pilots' => [
        'id', 'candidate_id', 'organizer_id', 'reservation_id', 'status', 'revision',
        'activation_ready_at', 'activated_at', 'activation_date_local', 'planned_end_date',
        'starts_at', 'ends_at',
    ],
    'startpartner_pilot_scopes' => ['id', 'pilot_id', 'scope_key', 'scope_type', 'status'],
    'startpartner_pilot_entitlements' => [
        'id', 'pilot_id', 'organizer_id', 'status', 'starts_at', 'ends_at', 'revision',
    ],
    'startpartner_pilot_onboarding_items' => [
        'id', 'pilot_id', 'item_key', 'status', 'is_required', 'is_hard_blocker',
        'evidence_text', 'evidence_reference', 'operator_reference', 'completed_at', 'revision',
    ],
    'startpartner_pilot_content_links' => [
        'id', 'pilot_id', 'organizer_id', 'submission_id', 'content_type', 'status',
        'reporting_target_type', 'reporting_target_id', 'source_reference',
        'editorial_ready_at', 'approved_at',
    ],
    'startpartner_pilot_measurement_preflights' => [
        'id', 'pilot_id', 'organizer_id', 'content_link_id', 'status', 'metrics_owner',
        'reporting_target_type', 'reporting_target_id', 'evidence_json', 'checked_by', 'checked_at',
    ],
    'startpartner_pilot_distribution_commitments' => [
        'id', 'pilot_id', 'channel', 'planned_at', 'target_reference', 'status',
        'evidence_text', 'operator_reference',
    ],
    'startpartner_pilot_usages' => [
        'id', 'pilot_id', 'pilot_entitlement_id', 'content_link_id', 'submission_id',
        'content_type', 'pilot_month_index', 'units', 'consumed_at',
    ],
];

function be_startpartner_gate4_schema_gaps(PDO $pdo): array
{
    $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') return ['database' => ['No database selected.']];
    $statement = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema_name');
    $statement->execute(['schema_name' => $databaseName]);
    $present = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string)($row['TABLE_NAME'] ?? ''); $column = (string)($row['COLUMN_NAME'] ?? '');
        if ($table !== '' && $column !== '') $present[$table][$column] = true;
    }
    $gaps = [];
    foreach (BE_STARTPARTNER_GATE4_REQUIRED_SCHEMA as $table => $columns) {
        if (!isset($present[$table])) { $gaps[$table] = ['table missing']; continue; }
        foreach ($columns as $column) if (!isset($present[$table][$column])) $gaps[$table][] = $column;
    }
    return $gaps;
}

function be_startpartner_gate4_require_schema(PDO $pdo): void
{
    $gaps = be_startpartner_gate4_schema_gaps($pdo);
    if ($gaps !== []) throw new RuntimeException('STARTPARTNER_GATE4_SCHEMA_MISSING: ' . json_encode($gaps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
function be_startpartner_gate4_uuid(): string { return be_cc_uuid(); }
function be_startpartner_gate4_optional_text(mixed $value, int $max, string $field): ?string
{
    $text = trim((string)$value); if ($text === '') return null;
    if (mb_strlen($text, 'UTF-8') > $max) throw new InvalidArgumentException("{$field} is too long."); return $text;
}
function be_startpartner_gate4_required_text(mixed $value, int $max, string $field): string
{
    $text=be_startpartner_gate4_optional_text($value,$max,$field); if($text===null)throw new InvalidArgumentException("{$field} is required."); return $text;
}
function be_startpartner_gate4_expected_pilot_revision(mixed $value): int
{
    $revision=filter_var($value,FILTER_VALIDATE_INT); if($revision===false||$revision<1)throw new InvalidArgumentException('expected_pilot_revision must be a positive integer.'); return (int)$revision;
}
function be_startpartner_gate4_pilot_row(PDO $pdo,string $candidateId,bool $forUpdate=false):array
{
    $sql='SELECT * FROM startpartner_pilots WHERE candidate_id = :candidate_id LIMIT 1'.($forUpdate?' FOR UPDATE':'');$statement=$pdo->prepare($sql);$statement->execute(['candidate_id'=>$candidateId]);$row=$statement->fetch(PDO::FETCH_ASSOC);if(!is_array($row))throw new RuntimeException('Startpartner pilot not found.');return $row;
}
function be_startpartner_gate4_capacity(PDO $pdo):array
{
    be_startpartner_gate3_require_schema($pdo);$statuses="'onboarding','activation_ready','active','paused','closing'";
    $pilots=(int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilots WHERE status IN ({$statuses})")->fetchColumn();
    $reservations=(int)$pdo->query("SELECT COUNT(*) FROM startpartner_candidate_reservations r LEFT JOIN startpartner_pilots p ON p.reservation_id=r.id AND p.status IN ({$statuses}) WHERE r.status='active' AND p.id IS NULL")->fetchColumn();
    $raw=(int)$pdo->query("SELECT COUNT(*) FROM startpartner_candidate_reservations WHERE status='active'")->fetchColumn();$occupied=$pilots+$reservations;
    $orphan=(int)$pdo->query("SELECT COUNT(*) FROM startpartner_candidates c LEFT JOIN startpartner_candidate_reservations r ON r.candidate_id=c.id AND r.status='active' LEFT JOIN startpartner_pilots p ON p.candidate_id=c.id AND p.status IN ({$statuses}) WHERE c.status='accepted_pending_terms' AND r.id IS NULL AND p.id IS NULL")->fetchColumn();
    return ['occupied_slots'=>$occupied,'active_reservations'=>$occupied,'raw_active_reservations'=>$raw,'occupying_pilots'=>$pilots,'reservation_only_slots'=>$reservations,'available_slots'=>max(0,BE_STARTPARTNER_CAPACITY_HARD_STOP-$occupied),'soft_stop'=>$occupied>=BE_STARTPARTNER_CAPACITY_SOFT_STOP,'hard_stop'=>$occupied>=BE_STARTPARTNER_CAPACITY_HARD_STOP,'soft_stop_at'=>BE_STARTPARTNER_CAPACITY_SOFT_STOP,'hard_stop_at'=>BE_STARTPARTNER_CAPACITY_HARD_STOP,'consistent'=>$orphan===0,'anomalies'=>['accepted_without_capacity_owner'=>$orphan]];
}
