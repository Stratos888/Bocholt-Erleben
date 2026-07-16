<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_editorial_contracts.php';
require_once dirname(__DIR__) . '/api/control-center/_operations.php';
require_once dirname(__DIR__) . '/api/control-center/_editorial_feedback.php';

$failures = [];
$assert = static function(bool $condition, string $label) use (&$failures): void { if (!$condition) $failures[] = $label; };
$throws = static function(callable $callback, string $needle, string $label) use (&$failures): void {
    try { $callback(); $failures[] = $label . ' (keine Exception)'; }
    catch (Throwable $error) { if (!str_contains($error->getMessage(), $needle)) $failures[] = $label . ' (' . $error->getMessage() . ')'; }
};

$fixturePath = __DIR__ . '/fixtures/control_center_editorial_cases.json';
$fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
foreach ($fixture['event_cases'] ?? [] as $case) {
    $result = be_cc_event_candidate_review_contract($case['payload'] ?? []);
    $assert(($result['decision_gate']['ready'] ?? null) === ($case['ready'] ?? null), 'Entscheidungsreife: ' . ($case['name'] ?? 'unbenannt'));
    if (!empty($case['blocker'])) {
        $codes = array_column($result['decision_gate']['blockers'] ?? [], 'code');
        $assert(in_array($case['blocker'], $codes, true), 'Blocker vorhanden: ' . $case['name']);
    }
    if (!empty($case['warning'])) {
        $codes = array_column($result['decision_gate']['warnings'] ?? [], 'code');
        $assert(in_array($case['warning'], $codes, true), 'Warnung vorhanden: ' . $case['name']);
    }
}

$accepted = be_cc_validate_operator_decision('approve', []);
$assert($accepted['decision_class'] === 'accepted', 'Unveränderte Übernahme wird accepted.');
$corrected = be_cc_validate_operator_decision('approve', ['event_updates'=>['description'=>'Final']]);
$assert($corrected['decision_class'] === 'corrected', 'Änderung wird corrected.');
$rejected = be_cc_validate_operator_decision('reject', ['decision_class'=>'duplicate','decision_note'=>'Bereits vorhanden']);
$assert($rejected['decision_class'] === 'duplicate', 'Typisierte Ablehnung wird akzeptiert.');
$throws(static fn() => be_cc_validate_operator_decision('reject', ['reason'=>'ohne Klasse']), 'decision_class', 'Ablehnung ohne decision_class wird blockiert.');
$throws(static fn() => be_cc_validate_operator_decision('snooze', ['decision_class'=>'snoozed']), 'Wiedervorlagedatum', 'Snooze ohne Datum wird blockiert.');
$snoozed = be_cc_validate_operator_decision('snooze', ['decision_class'=>'snoozed','suppress_until'=>'2026-08-01'], ['today'=>'2026-07-15']);
$assert($snoozed['suppress_until'] === '2026-08-01', 'Snooze-Datum wird normalisiert.');

$payload = ['decision_class'=>'duplicate','decision_note'=>'Doppelt'];
$reserve = be_cc_operation_reservation_decision([], 'op-test-0001', 'case-1', 'reject', $payload);
$assert($reserve['decision'] === 'reserve', 'Neue Operation wird reserviert.');
$existing = [[
    'operation_id'=>'op-test-0001','case_id'=>'case-1','action'=>'reject',
    'payload_hash'=>$reserve['payload_hash'],'status'=>'completed','result'=>['ok'=>true],
]];
$replay = be_cc_operation_reservation_decision($existing, 'op-test-0001', 'case-1', 'reject', $payload);
$assert($replay['decision'] === 'replay', 'Gleiche Operation wird wiederverwendet.');
$conflict = be_cc_operation_reservation_decision($existing, 'op-test-0001', 'case-1', 'reject', ['decision_class'=>'rejected_low_value']);
$assert($conflict['decision'] === 'conflict', 'Gleiche operation_id mit anderem Payload wird blockiert.');

$observation = be_cc_editorial_feedback_observation(
    'event-1',
    'description_quality',
    'Erleben Sie ein einzigartiges Highlight. Laut Veranstalter gibt es weitere Informationen.',
    'Erleben Sie ein einzigartiges Highlight. Laut Veranstalter gibt es weitere Informationen.',
    'Das Fest bietet Mitmachaktionen für Kinder auf dem Marktplatz in Bocholt.',
    ['city'=>'Bocholt','location'=>'Marktplatz','prompt_rule_version'=>'weekly-v1']
);
$assert($observation['activation_state'] === 'observation', 'Einzeledit bleibt Beobachtung.');
$assert($observation['eligible_for_prompt_context'] === false, 'Einzeledit aktiviert keine Regel.');
$assert(in_array('advertising_language_removed', $observation['categories'], true), 'Werbesprache wird klassifiziert.');
$assert(in_array('source_attribution_removed', $observation['categories'], true), 'Quellenherleitung wird klassifiziert.');
$assert(in_array('local_relevance_added', $observation['categories'], true), 'Lokale Relevanz wird klassifiziert.');

$observations = [];
foreach (['event-1','event-2','event-3'] as $eventId) {
    $item = $observation;
    $item['event_id'] = $eventId;
    $observations[] = $item;
}
$aggregate = be_cc_editorial_feedback_aggregate($observations, 3);
$candidate = array_values(array_filter($aggregate['items'], static fn(array $item): bool => $item['category'] === 'advertising_language_removed'))[0] ?? [];
$assert(($candidate['activation_state'] ?? '') === 'candidate', 'Wiederkehrendes Muster wird nur Regelkandidat.');
$assert(($candidate['permanent_rule_change_allowed'] ?? true) === false, 'Auch Regelkandidat ändert kein permanentes Regelwerk automatisch.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Editorial Contracts: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}
echo "=== Control Center Editorial Contracts: OK ===\n";
