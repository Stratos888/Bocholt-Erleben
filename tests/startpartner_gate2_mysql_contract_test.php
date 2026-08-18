<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_schema.php';
require_once dirname(__DIR__) . '/api/startpartner/_schema.php';

$dsn = getenv('STARTPARTNER_TEST_DSN') ?: '';
$user = getenv('STARTPARTNER_TEST_USER') ?: '';
$password = getenv('STARTPARTNER_TEST_PASSWORD') ?: '';
if ($dsn === '' || $user === '') exit(2);

$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$failures = [];
$assert = static function(bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};
$count = static function(string $table, string $where = '1=1', array $params = []) use ($db): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
};
$expectDbFailure = static function(callable $operation, string $message) use (&$failures): void {
    try {
        $operation();
        $failures[] = $message;
    } catch (PDOException $expected) {
    }
};
$canonical = static function(array|false $row): array|false {
    return $row === false ? false : array_map(
        static fn(mixed $value): ?string => $value === null ? null : (string)$value,
        $row
    );
};

$assert(be_cc_schema_gaps($db) === [], 'Control-Center schema gaps');
$assert(be_startpartner_schema_gaps($db) === [], 'Startpartner schema gaps');
$ccSchema = strtoupper((string)file_get_contents(dirname(__DIR__) . '/api/control-center/_schema.php'));
$assert(!str_contains($ccSchema, 'CREATE TABLE'), 'Control-Center runtime DDL create');
$assert(!str_contains($ccSchema, 'ALTER TABLE'), 'Control-Center runtime DDL alter');

$migrations = $db->query(
    "SELECT migration_key FROM app_schema_migrations
     WHERE migration_key IN (
       '007_runtime_schema_reconciliation',
       '008_startpartner_candidates',
       '009_control_center_runtime_schema',
       '010_startpartner_gate2_qualification_capacity'
     ) ORDER BY migration_key"
)->fetchAll(PDO::FETCH_COLUMN);
$assert($migrations === [
    '007_runtime_schema_reconciliation',
    '008_startpartner_candidates',
    '009_control_center_runtime_schema',
    '010_startpartner_gate2_qualification_capacity',
], 'Migration registry 007-010');

$statusType = (string)$db->query(
    "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'startpartner_candidates'
       AND COLUMN_NAME = 'status'"
)->fetchColumn();
$expectedStatuses = [
    'new', 'prequalifying', 'contact_pending', 'awaiting_response',
    'qualifying', 'needs_information', 'decision_ready',
    'accepted_pending_terms', 'waitlisted', 'routed_to_regular_product',
    'rejected', 'withdrawn', 'expired',
];
foreach ($expectedStatuses as $status) {
    $assert(str_contains($statusType, "'{$status}'"), "Candidate status {$status}");
}
$assert(!str_contains($statusType, "'qualified'"), 'Legacy qualified status removed');

$foreignKeys = $db->query(
    "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()"
)->fetchAll();
$fkMap = [];
foreach ($foreignKeys as $fk) $fkMap[(string)$fk['CONSTRAINT_NAME']] = $fk;
foreach ([
    'fk_startpartner_contacts_candidate',
    'fk_startpartner_events_candidate',
    'fk_startpartner_qualifications_candidate',
    'fk_startpartner_decisions_candidate',
    'fk_startpartner_reservations_candidate',
    'fk_startpartner_waitlist_candidate',
    'fk_startpartner_operations_candidate',
] as $name) {
    $assert(($fkMap[$name]['REFERENCED_TABLE_NAME'] ?? '') === 'startpartner_candidates', "FK target {$name}");
    $assert(($fkMap[$name]['DELETE_RULE'] ?? '') === 'CASCADE', "FK cascade {$name}");
}
foreach (['fk_control_case_events_case', 'fk_control_operations_case', 'fk_control_editorial_feedback_case'] as $name) {
    $assert(($fkMap[$name]['REFERENCED_TABLE_NAME'] ?? '') === 'control_cases', "FK target {$name}");
    $assert(($fkMap[$name]['DELETE_RULE'] ?? '') === 'CASCADE', "FK cascade {$name}");
}

$sentinels = [
    'organizers' => [
        "SELECT organization_name, email, stripe_customer_id, default_plan_key FROM organizers WHERE id=900001",
        ['organization_name'=>'GATE2_SCHEMA_SENTINEL','email'=>'gate2-schema-sentinel@example.org','stripe_customer_id'=>'cus_gate2_schema_sentinel','default_plan_key'=>'active'],
    ],
    'submissions' => [
        "SELECT organizer_id, status, intake_origin, location_public_confirmed, title, notes_text FROM submissions WHERE id=900001",
        ['organizer_id'=>900001,'status'=>'draft','intake_origin'=>'single_event','location_public_confirmed'=>1,'title'=>'Sentinel event','notes_text'=>'Must remain unchanged'],
    ],
    'subscriptions' => [
        "SELECT organizer_id, stripe_subscription_id, plan_key, status FROM subscriptions WHERE id=900001",
        ['organizer_id'=>900001,'stripe_subscription_id'=>'sub_gate2_schema_sentinel','plan_key'=>'active','status'=>'active'],
    ],
    'publication_entitlements' => [
        "SELECT organizer_id, source_reference, source_submission_id, subscription_id, included_publications, consumed_publications FROM publication_entitlements WHERE id=900001",
        ['organizer_id'=>900001,'source_reference'=>'gate2-schema-sentinel','source_submission_id'=>900001,'subscription_id'=>900001,'included_publications'=>3,'consumed_publications'=>1],
    ],
    'publication_consumptions' => [
        "SELECT organizer_id, entitlement_id, submission_id, units FROM publication_consumptions WHERE id=900001",
        ['organizer_id'=>900001,'entitlement_id'=>900001,'submission_id'=>900001,'units'=>1],
    ],
];
$checkSentinels = static function(string $phase) use ($db, $assert, $count, $canonical, $sentinels): void {
    foreach ($sentinels as $table => [$sql, $expected]) {
        $assert($canonical($db->query($sql)->fetch()) === $canonical($expected), "Locked sentinel {$phase}: {$table}");
        $assert($count($table) === 1, "Locked count {$phase}: {$table}");
    }
};
$checkSentinels('before');

$candidateId = '00000000-0000-0000-0000-000000000199';
$db->prepare(
    "INSERT INTO startpartner_candidates (
       id, source, organization_name, organization_name_normalized,
       desired_content_scope, status, identity_key, idempotency_key_hash,
       form_version, retention_review_at
     ) VALUES (
       :id, 'targeted_outreach', 'GATE2_SCHEMA_CANDIDATE',
       'gate2_schema_candidate', 'both', 'qualifying', :identity, :idempotency,
       'gate2-schema', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY)
     )"
)->execute([
    'id'=>$candidateId,
    'identity'=>hash('sha256', 'gate2-schema-identity'),
    'idempotency'=>hash('sha256', 'gate2-schema-idempotency'),
]);
$candidate = $db->query("SELECT revision, status_changed_at FROM startpartner_candidates WHERE id='{$candidateId}'")->fetch();
$assert((int)($candidate['revision'] ?? 0) === 1, 'Candidate revision owner');
$assert(($candidate['status_changed_at'] ?? null) !== null, 'Candidate status timestamp owner');

$db->prepare("INSERT INTO startpartner_candidate_contacts (candidate_id,email,email_normalized,is_primary) VALUES (:id,'schema@example.org','schema@example.org',1)")->execute(['id'=>$candidateId]);
$db->prepare("INSERT INTO startpartner_candidate_events (candidate_id,event_type,actor_type,actor_reference) VALUES (:id,'schema.created','operator','contract')")->execute(['id'=>$candidateId]);
$db->prepare("INSERT INTO startpartner_candidate_qualifications (candidate_id,dimension,assessment,reason,evidence_text,operator_reference) VALUES (:id,'local_relevance','adequate','Local contract','Evidence','contract')")->execute(['id'=>$candidateId]);
$expectDbFailure(static function() use ($db, $candidateId): void {
    $db->prepare("INSERT INTO startpartner_candidate_qualifications (candidate_id,dimension,assessment,operator_reference) VALUES (:id,'local_relevance','strong','contract')")->execute(['id'=>$candidateId]);
}, 'Qualification current owner uniqueness');

$db->prepare("INSERT INTO startpartner_candidate_decisions (candidate_id,result,reason,operator_reference,candidate_revision,qualification_snapshot_json,capacity_snapshot_json) VALUES (:id,'waitlisted','Capacity','contract',1,'{}','{}')")->execute(['id'=>$candidateId]);
$decisionId = (int)$db->lastInsertId();
$expectDbFailure(static function() use ($db, $candidateId): void {
    $db->prepare("INSERT INTO startpartner_candidate_decisions (candidate_id,result,reason,operator_reference,candidate_revision,qualification_snapshot_json,capacity_snapshot_json) VALUES (:id,'rejected','Duplicate current','contract',1,'{}','{}')")->execute(['id'=>$candidateId]);
}, 'Single current decision');

$db->prepare("INSERT INTO startpartner_candidate_reservations (candidate_id,decision_id,starts_at,ends_at,capacity_snapshot_json,operator_reference) VALUES (:id,:decision,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),'{}','contract')")->execute(['id'=>$candidateId,'decision'=>$decisionId]);
$expectDbFailure(static function() use ($db, $candidateId, $decisionId): void {
    $db->prepare("INSERT INTO startpartner_candidate_reservations (candidate_id,decision_id,starts_at,ends_at,capacity_snapshot_json,operator_reference) VALUES (:id,:decision,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'{}','contract')")->execute(['id'=>$candidateId,'decision'=>$decisionId]);
}, 'Single active reservation');
$expectDbFailure(static function() use ($db, $candidateId, $decisionId): void {
    $db->prepare("INSERT INTO startpartner_candidate_reservations (candidate_id,decision_id,status,starts_at,ends_at,capacity_snapshot_json,operator_reference) VALUES (:id,:decision,'released',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 31 DAY),'{}','contract')")->execute(['id'=>$candidateId,'decision'=>$decisionId]);
}, 'Reservation maximum 30 days');

$db->prepare("INSERT INTO startpartner_candidate_waitlist (candidate_id,eligibility_reason,priority_reason,next_review_at,operator_reference) VALUES (:id,'Suitable','Capacity',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 14 DAY),'contract')")->execute(['id'=>$candidateId]);
$db->prepare("INSERT INTO startpartner_candidate_operations (operation_id,candidate_id,action,payload_hash,status,result_json,candidate_revision_before,candidate_revision_after,completed_at) VALUES ('gate2:199:schema',:id,'schema.check',:hash,'completed','{}',1,1,UTC_TIMESTAMP())")->execute(['id'=>$candidateId,'hash'=>hash('sha256','schema')]);
$expectDbFailure(static function() use ($db, $candidateId): void {
    $db->prepare("INSERT INTO startpartner_candidate_operations (operation_id,candidate_id,action,payload_hash,candidate_revision_before) VALUES ('gate2:199:schema',:id,'schema.conflict',:hash,1)")->execute(['id'=>$candidateId,'hash'=>hash('sha256','different')]);
}, 'Operation id uniqueness');

$db->prepare('DELETE FROM startpartner_candidates WHERE id=:id')->execute(['id'=>$candidateId]);
foreach ([
    'startpartner_candidate_contacts', 'startpartner_candidate_events',
    'startpartner_candidate_qualifications', 'startpartner_candidate_decisions',
    'startpartner_candidate_reservations', 'startpartner_candidate_waitlist',
    'startpartner_candidate_operations',
] as $table) {
    $assert($count($table, 'candidate_id=:id', ['id'=>$candidateId]) === 0, "Candidate cascade {$table}");
}

$caseId = '00000000-0000-0000-0000-000000000299';
$db->prepare("INSERT INTO control_cases (id,case_type,state,title,source_system,source_reference) VALUES (:id,'task','new','Schema case','schema_contract','gate2:199')")->execute(['id'=>$caseId]);
$db->prepare("INSERT INTO control_case_events (case_id,action) VALUES (:id,'schema.check')")->execute(['id'=>$caseId]);
$db->prepare("INSERT INTO control_operations (operation_id,case_id,action,payload_hash) VALUES ('gate2:199:control',:id,'schema.check',:hash)")->execute(['id'=>$caseId,'hash'=>hash('sha256','control')]);
$db->prepare("INSERT INTO control_editorial_feedback (id,case_id,final_text,decision_class) VALUES ('00000000-0000-0000-0000-000000000399',:id,'Feedback','schema_contract')")->execute(['id'=>$caseId]);
$db->prepare('DELETE FROM control_cases WHERE id=:id')->execute(['id'=>$caseId]);
$assert($count('control_case_events','case_id=:id',['id'=>$caseId]) === 0, 'Control event cascade');
$assert($count('control_operations','case_id=:id',['id'=>$caseId]) === 0, 'Control operation cascade');
$assert($count('control_editorial_feedback','case_id=:id',['id'=>$caseId]) === 0, 'Control feedback cascade');
$checkSentinels('after');

if ($failures !== []) {
    fwrite(STDERR, "FAILED: " . implode(', ', $failures) . "\n");
    exit(1);
}
echo "=== Startpartner Gate-2 MariaDB Schema Contract: OK ===\n";
