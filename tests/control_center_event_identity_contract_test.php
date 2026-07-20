<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_event_identity.php';
require_once dirname(__DIR__) . '/api/control-center/_editorial_contracts.php';

$fixture = json_decode((string)file_get_contents(__DIR__ . '/fixtures/event_identity_cases.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ((array)($fixture['cases'] ?? []) as $case) {
    $match = be_cc_event_identity_find_best((array)$case['candidate'], (array)$case['existing']);
    if (($match['status'] ?? '') !== ($case['expected_status'] ?? '')) {
        throw new RuntimeException(($case['name'] ?? 'unknown') . ': unexpected status ' . json_encode($match, JSON_UNESCAPED_UNICODE));
    }
    if (($match['matched_event_id'] ?? '') !== ($case['expected_id'] ?? '')) {
        throw new RuntimeException(($case['name'] ?? 'unknown') . ': unexpected matched event id');
    }
    if ((float)($match['score'] ?? 0.0) + 0.000001 < (float)($case['minimum_score'] ?? 0.0)) {
        throw new RuntimeException(($case['name'] ?? 'unknown') . ': score below contract');
    }
}

$cityart = $fixture['cases'][0];
$enriched = be_cc_event_identity_enrich($cityart['candidate'], $cityart['existing'], false);
if (empty($enriched['hard_duplicate']) || ($enriched['duplicate_status'] ?? '') !== 'review') {
    throw new RuntimeException('Semantic match must open the duplicate review task.');
}
$review = be_cc_event_candidate_review_contract($enriched);
$duplicateTasks = array_values(array_filter(
    (array)($review['review_tasks'] ?? []),
    static fn(array $task): bool => ($task['task_id'] ?? '') === 'dedupe.decision'
));
if (count($duplicateTasks) !== 1 || ($duplicateTasks[0]['severity'] ?? '') !== 'blocking') {
    throw new RuntimeException('Semantic match must activate the blocking duplicate review task.');
}
$distinct = $cityart['candidate'];
$distinct['matched_event_id'] = $cityart['expected_id'];
$distinct['duplicate_status'] = 'distinct';
$distinct = be_cc_event_identity_enrich($distinct, $cityart['existing'], false);
if (!empty($distinct['hard_duplicate']) || ($distinct['duplicate_status'] ?? '') !== 'distinct') {
    throw new RuntimeException('A human distinct decision must survive the same current match.');
}
$distinctReview = be_cc_event_candidate_review_contract($distinct);
$distinctTasks = array_values(array_filter(
    (array)($distinctReview['review_tasks'] ?? []),
    static fn(array $task): bool => ($task['task_id'] ?? '') === 'dedupe.decision'
));
if ($distinctTasks !== []) {
    throw new RuntimeException('Human distinct decision must resolve the duplicate review task.');
}

$resume = current(array_filter($fixture['cases'], static fn(array $case): bool => ($case['name'] ?? '') === 'same_id_resume'));
if (!is_array($resume)) throw new RuntimeException('Same-ID resume fixture missing.');
$staleResume = array_replace($resume['candidate'], ['matched_event_id'=>'stale','duplicate_status'=>'review','duplicate_reason'=>'stale']);
$resumeEnriched = be_cc_event_identity_enrich($staleResume, $resume['existing'], true);
if (!empty($resumeEnriched['hard_duplicate']) || ($resumeEnriched['matched_event_id'] ?? 'x') !== '' || ($resumeEnriched['duplicate_status'] ?? 'x') !== '') {
    throw new RuntimeException('Idempotent same-ID resume must clear stale duplicate evidence.');
}
$noMatch = be_cc_event_identity_enrich(['matched_event_id'=>'stale','duplicate_status'=>'review'], [], true);
if (($noMatch['matched_event_id'] ?? 'x') !== '' || ($noMatch['duplicate_status'] ?? 'x') !== '') {
    throw new RuntimeException('Missing current matches must clear stale duplicate evidence.');
}

$base = [['id'=>'a','title'=>'Base','date'=>'2026-01-01']];
$overlay = [['id'=>'a','title'=>'Overlay','date'=>'2026-01-01'],['id'=>'b','title'=>'New','date'=>'2026-01-02']];
$merged = be_cc_event_identity_merge_event_rows($base, $overlay);
if (count($merged) !== 2 || ($merged[0]['title'] ?? '') !== 'Overlay') {
    throw new RuntimeException('Staging overlay must replace the base event with the same stable ID.');
}

$duplicateOverlayRejected = false;
try {
    be_cc_event_identity_merge_event_rows([], [
        ['id'=>'dup','title'=>'A','date'=>'2026-01-01'],
        ['id'=>'dup','title'=>'B','date'=>'2026-01-01'],
    ]);
} catch (RuntimeException) {
    $duplicateOverlayRejected = true;
}
if (!$duplicateOverlayRejected) throw new RuntimeException('Duplicate overlay IDs must fail closed.');

echo "Control Center event identity contract: OK\n";
