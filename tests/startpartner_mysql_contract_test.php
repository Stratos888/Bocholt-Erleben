<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_domain.php';

$dsn = getenv('STARTPARTNER_TEST_DSN') ?: '';
$user = getenv('STARTPARTNER_TEST_USER') ?: '';
$password = getenv('STARTPARTNER_TEST_PASSWORD') ?: '';
if ($dsn === '' || $user === '') {
    exit(2);
}

$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$countRows = static function(string $table, string $where = '1=1', array $params = []) use ($db): int {
    $statement = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $statement->execute($params);
    return (int)$statement->fetchColumn();
};

$marker = 'GATE1_SYNTHETIC_194_' . strtoupper(bin2hex(random_bytes(4)));
$idempotencyKey = strtolower($marker) . '-key';
$retentionReviewAt = (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
$input = [
    'source' => 'targeted_outreach',
    'source_reference' => 'contract',
    'organization_name' => $marker,
    'contacts' => [
        ['email' => strtolower($marker) . '@example.org', 'is_primary' => true],
        ['email' => 'second-' . strtolower($marker) . '@example.org'],
    ],
    'website_url' => 'https://example.org/' . strtolower($marker),
    'description_text' => 'Synthetic candidate',
    'desired_content_scope' => 'both',
    'form_version' => 'gate1-v1',
    'retention_review_at' => $retentionReviewAt,
    'idempotency_key' => $idempotencyKey,
];

$lockedTables = [
    'organizers',
    'submissions',
    'subscriptions',
    'publication_entitlements',
    'publication_consumptions',
];
$before = [];
foreach ($lockedTables as $table) {
    $before[$table] = $countRows($table);
}

$result = be_startpartner_create_candidate($db, $input, 'operator', 'mysql-contract');
$candidateId = (string)($result['candidate']['id'] ?? '');
$assert($result['created'] === true && $candidateId !== '', 'create');
$assert(count($result['candidate']['contacts'] ?? []) === 2, 'contacts');
$assert($result['candidate']['retention_review_at'] === (new DateTimeImmutable($retentionReviewAt))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'retention review');
$assert($countRows('startpartner_candidates', 'id = :id', ['id' => $candidateId]) === 1, 'candidate');
$assert($countRows('startpartner_candidate_contacts', 'candidate_id = :id', ['id' => $candidateId]) === 2, 'contact rows');
$assert($countRows('startpartner_candidate_events', 'candidate_id = :id', ['id' => $candidateId]) === 1, 'create event');
$assert($countRows('control_cases', "source_system = 'startpartner_candidate' AND source_reference = :id", ['id' => $candidateId]) === 1, 'case');

$replay = be_startpartner_create_candidate($db, $input, 'operator', 'mysql-contract');
$assert(!$replay['created'] && $replay['idempotent_replay'] && (string)$replay['candidate']['id'] === $candidateId, 'replay');
$assert($countRows('startpartner_candidate_events', 'candidate_id = :id', ['id' => $candidateId]) === 1, 'replay event');

$conflictingReplay = $input;
$conflictingReplay['description_text'] = 'Different synthetic payload';
try {
    be_startpartner_create_candidate($db, $conflictingReplay, 'operator', 'mysql-contract');
    $failures[] = 'idempotency conflict not rejected';
} catch (DomainException $expected) {
}
$assert($countRows('startpartner_candidates', 'id = :id', ['id' => $candidateId]) === 1, 'idempotency conflict candidate');
$assert($countRows('startpartner_candidate_events', 'candidate_id = :id', ['id' => $candidateId]) === 1, 'idempotency conflict event');

$duplicateInput = $input;
$duplicateInput['idempotency_key'] .= '-other';
$duplicate = be_startpartner_create_candidate($db, $duplicateInput, 'operator', 'mysql-contract');
$assert($duplicate['duplicate_identity'] && (string)$duplicate['candidate']['id'] === $candidateId, 'duplicate');
$assert($countRows('startpartner_candidate_events', 'candidate_id = :id', ['id' => $candidateId]) === 2, 'duplicate event');

be_startpartner_triage_candidate($db, $candidateId, 'qualified', null, 'mysql-contract');
be_startpartner_triage_candidate($db, $candidateId, 'waitlisted', 'Capacity reserved.', 'mysql-contract');
$caseStatement = $db->prepare(
    "SELECT state, decision_ready
     FROM control_cases
     WHERE source_system = 'startpartner_candidate'
       AND source_reference = :id"
);
$caseStatement->execute(['id' => $candidateId]);
$case = $caseStatement->fetch();
$assert(($case['state'] ?? '') === 'parked' && (int)($case['decision_ready'] ?? 1) === 0, 'projection');
foreach ($lockedTables as $table) {
    $assert($countRows($table) === $before[$table], "side effect {$table}");
}

$rollbackMarker = $marker . '_ROLLBACK';
$db->exec(
    "CREATE TRIGGER gate1_contract_failure
     BEFORE INSERT ON control_cases
     FOR EACH ROW
     BEGIN
       IF NEW.object_title = " . $db->quote($rollbackMarker) . " THEN
         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic failure';
       END IF;
     END"
);
try {
    $rollbackInput = $input;
    $rollbackInput['organization_name'] = $rollbackMarker;
    $rollbackInput['contacts'][0]['email'] = 'r-' . strtolower($marker) . '@example.org';
    $rollbackInput['contacts'][1]['email'] = 'r2-' . strtolower($marker) . '@example.org';
    $rollbackInput['idempotency_key'] .= '-rollback';
    try {
        be_startpartner_create_candidate($db, $rollbackInput, 'operator', 'mysql-contract');
        $failures[] = 'rollback not triggered';
    } catch (Throwable $expected) {
    }
    $assert($countRows('startpartner_candidates', 'organization_name = :name', ['name' => $rollbackMarker]) === 0, 'rollback residue');
} finally {
    $db->exec('DROP TRIGGER IF EXISTS gate1_contract_failure');
}

$deleteCase = $db->prepare(
    "DELETE FROM control_cases
     WHERE source_system = 'startpartner_candidate'
       AND source_reference = :id"
);
$deleteCase->execute(['id' => $candidateId]);
$deleteCandidate = $db->prepare('DELETE FROM startpartner_candidates WHERE id = :id');
$deleteCandidate->execute(['id' => $candidateId]);

$assert($countRows('startpartner_candidates', 'id = :id', ['id' => $candidateId]) === 0, 'cleanup candidate');
$assert($countRows('startpartner_candidate_contacts', 'candidate_id = :id', ['id' => $candidateId]) === 0, 'cleanup contacts');
$assert($countRows('startpartner_candidate_events', 'candidate_id = :id', ['id' => $candidateId]) === 0, 'cleanup events');
$assert($countRows('control_cases', "source_system = 'startpartner_candidate' AND source_reference = :id", ['id' => $candidateId]) === 0, 'cleanup case');

if ($failures !== []) {
    fwrite(STDERR, "FAILED: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "=== Startpartner MySQL Contract: OK ===\n";
