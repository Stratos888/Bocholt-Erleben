<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_editorial_runtime.php';

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

try {
    be_cc_editorial_validate_action($case, 'reject', ['reason'=>'Nicht passend']);
    $failures[] = 'Ablehnung ohne decision_class wurde akzeptiert.';
} catch (InvalidArgumentException $expected) {}

$snooze = be_cc_editorial_validate_action($case, 'snooze', ['decision_class'=>'snoozed','suppress_until'=>'2026-08-20']);
$assert($snooze['decision_class'] === 'snoozed', 'Zurückstellung verwendet kanonische Klasse.');

$legacy = be_cc_editorial_operation_id([], $case['id'], 'approve');
$assert($legacy['legacy'] === true && str_starts_with($legacy['id'], 'legacy:'), 'Alte UI bleibt über markierte Legacy-operation_id kompatibel.');
$modern = be_cc_editorial_operation_id(['operation_id'=>'cc:test:operation:0001'], $case['id'], 'approve');
$assert($modern['legacy'] === false && $modern['id'] === 'cc:test:operation:0001', 'Explizite operation_id bleibt stabil.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Editorial Runtime Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Editorial Runtime Contract: OK ===\n";
