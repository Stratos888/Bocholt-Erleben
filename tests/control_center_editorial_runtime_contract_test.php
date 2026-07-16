<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_editorial_runtime.php';
require_once dirname(__DIR__) . '/api/control-center/_inbox_decision_writeback.php';

$failures = [];
$assert = static function(bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};

$complete = [
    'id_suggestion'=>'fixture-event-1',
    'title'=>'Fixture Event',
    'date'=>'2026-08-15',
    'time'=>'18:00',
    'city'=>'Bocholt',
    'location'=>'Marktplatz',
    'kategorie_suggestion'=>'Kultur & Kunst',
    'source_url'=>'https://example.org/events/fixture',
    'description'=>'Ein öffentlicher Termin mit belastbaren Angaben.',
    'visual_key'=>'kultur',
    'visual_motif'=>'buehne',
];
$case = [
    'id'=>'00000000-0000-4000-8000-000000000001',
    'object_id'=>'fixture-event-1',
    'source_system'=>'inbox_feed',
    'source_payload_json'=>json_encode($complete, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
];

$accepted = be_cc_editorial_validate_action($case, 'approve', []);
$assert($accepted['decision_class'] === 'accepted', 'Vollständiger unveränderter Kandidat wird accepted.');
$assert(($accepted['review_contract']['decision_gate']['ready'] ?? false) === true, 'Serverseitiges Entscheidungsgate ist grün.');

$corrected = be_cc_editorial_validate_action($case, 'approve', [
    'event_updates'=>['description'=>'Eine sachlich überarbeitete finale Beschreibung.'],
]);
$assert($corrected['decision_class'] === 'corrected', 'Bearbeitete Endfassung wird corrected.');

$blockedCase = $case;
$blocked = $complete;
$blocked['source_url'] = '';
$blockedCase['source_payload_json'] = json_encode($blocked, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
try {
    be_cc_editorial_validate_action($blockedCase, 'approve', []);
    $failures[] = 'Fehlende Quelle blockiert die Runtime-Übernahme nicht.';
} catch (DomainException $expected) {
    $assert(str_contains($expected->getMessage(), 'nicht entscheidungsreif'), 'Blocker liefert verständlichen Konflikt.');
}

$activityCase = $case;
$activityCase['object_id'] = 'fixture-activity-1';
$activityCase['source_payload_json'] = json_encode([
    'submission_kind'=>'activity',
    'id_suggestion'=>'fixture-activity-1',
    'title'=>'Dauerhafte Aktivität',
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$activityAccepted = be_cc_editorial_validate_action($activityCase, 'approve', []);
$assert($activityAccepted['decision_class'] === 'accepted', 'Activity-Kandidat bleibt vom Event-Pflichtfeldgate getrennt.');
$assert(!isset($activityAccepted['review_contract']), 'Activity-Kandidat erhält keinen falschen Eventvertrag.');

try {
    be_cc_editorial_validate_action($case, 'reject', ['reason'=>'Nicht passend']);
    $failures[] = 'Ablehnung ohne decision_class wurde akzeptiert.';
} catch (InvalidArgumentException $expected) {}

$duplicate = be_cc_editorial_validate_action($case, 'reject', ['decision_class'=>'duplicate']);
$expectedDuplicateWriteback = be_cc_inbox_expected_writeback('reject', $duplicate);
$assert($expectedDuplicateWriteback['status'] === 'verworfen', 'Ablehnung schreibt den kanonischen Inbox-Status verworfen.');
$assert($expectedDuplicateWriteback['reason'] === 'Dublette', 'Ablehnung ohne Freitext schreibt das menschenlesbare Klassenlabel.');
$directDuplicateUpdates = be_cc_inbox_direct_updates($expectedDuplicateWriteback);
$assert($directDuplicateUpdates === ['status'=>'verworfen','ablehnungsgrund'=>'Dublette'], 'Direkter Ablehnungsplan schreibt Status und Grund atomar in die führende Inbox.');
$verifiedDuplicate = be_cc_inbox_verify_writeback_row([
    'status'=>'verworfen',
    'ablehnungsgrund'=>'Dublette',
], $expectedDuplicateWriteback);
$assert(($verifiedDuplicate['verified'] ?? false) === true, 'Kanonischer Status und Grund werden als bestätigter Writeback erkannt.');
try {
    be_cc_inbox_verify_writeback_row([
        'status'=>'review',
        'ablehnungsgrund'=>'',
    ], $expectedDuplicateWriteback);
    $failures[] = 'Unveränderte Inbox-Zeile wurde fälschlich als bestätigter Writeback akzeptiert.';
} catch (RuntimeException $expected) {}
try {
    be_cc_inbox_verify_writeback_row([
        'status'=>'verworfen',
        'ablehnungsgrund'=>'Redaktionell zu schwach',
    ], $expectedDuplicateWriteback);
    $failures[] = 'Abweichender Ablehnungsgrund wurde fälschlich als bestätigter Writeback akzeptiert.';
} catch (RuntimeException $expected) {}

$snooze = be_cc_editorial_validate_action($case, 'snooze', ['decision_class'=>'snoozed','suppress_until'=>'2026-08-20']);
$assert($snooze['decision_class'] === 'snoozed', 'Zurückstellung verwendet kanonische Klasse.');
$expectedSnoozeWriteback = be_cc_inbox_expected_writeback('snooze', $snooze);
$directSnoozeUpdates = be_cc_inbox_direct_updates($expectedSnoozeWriteback);
$assert($directSnoozeUpdates['status'] === 'später prüfen', 'Wiedervorlage nutzt ebenfalls den direkten kanonischen Statuspfad.');

$legacy = be_cc_editorial_operation_id([], $case['id'], 'approve');
$assert($legacy['legacy'] === true && str_starts_with($legacy['id'], 'legacy:'), 'Alte UI bleibt über markierte Legacy-operation_id kompatibel.');
$modern = be_cc_editorial_operation_id(['operation_id'=>'cc:test:operation:0001'], $case['id'], 'approve');
$assert($modern['legacy'] === false && $modern['id'] === 'cc:test:operation:0001', 'Explizite operation_id bleibt stabil.');

$fingerprintBase = [
    'id'=>'event-1','title'=>'Titel','date'=>'2026-08-15','end_date'=>'','time'=>'18:00','city'=>'Bocholt',
    'location'=>'Marktplatz','address'=>'Markt 1','category'=>'Kultur','source_url'=>'https://example.org/event',
];
$fingerprintChanged = $fingerprintBase;
$fingerprintChanged['location'] = 'Andere Halle';
$assert(be_cc_runtime_event_fingerprint($fingerprintBase) !== be_cc_runtime_event_fingerprint($fingerprintChanged), 'Kernänderung verändert den Audit-Content-Fingerprint.');
$description = 'Aktuelle Beschreibung';
$descriptionHash = hash('sha256', $description);
$assert(be_cc_description_matches($descriptionHash, $description), 'Unveränderte Beschreibung wird akzeptiert.');
$assert(!be_cc_description_matches($descriptionHash, 'Zwischenzeitlich geändert'), 'Zwischenzeitliche Beschreibungsänderung wird erkannt.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Editorial Runtime Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Editorial Runtime Contract: OK ===\n";
