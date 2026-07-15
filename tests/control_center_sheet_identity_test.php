<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_sheet_identity.php';

$failures = [];
$assert = static function(bool $condition, string $label) use (&$failures): void { if (!$condition) $failures[] = $label; };

$target = [
    '_row_number'=>8,
    'id_suggestion'=>'candidate-42',
    'title'=>'Bocholter Sommerfest',
    'date'=>'2026-08-15',
    'location'=>'Marktplatz',
    'source_url'=>'https://example.org/sommerfest?utm_source=test',
];
$stored = be_cc_inbox_identity($target);
$stored['row_number'] = 8;
$movedRows = [
    ['_row_number'=>8,'id_suggestion'=>'other','title'=>'Anderer Termin','date'=>'2026-08-10','location'=>'Aasee','source_url'=>'https://example.org/other'],
    array_merge($target, ['_row_number'=>14]),
];
$moved = be_cc_resolve_inbox_row($movedRows, $stored);
$assert($moved['status'] === 'resolved' && $moved['row_number'] === 14, 'Verschobene Inbox-Zeile wird über stabile Identität gefunden.');
$assert($moved['match_method'] === 'stable_candidate_id', 'Stabile Kandidaten-ID hat Vorrang.');

$urlOnlyStored = $stored;
$urlOnlyStored['stable_id'] = '';
$urlOnly = be_cc_resolve_inbox_row($movedRows, $urlOnlyStored);
$assert($urlOnly['status'] === 'resolved' && $urlOnly['row_number'] === 14, 'Kanonische URL plus Datum löst trotz Trackingparameter auf.');

$ambiguousRows = [
    array_merge($target, ['_row_number'=>20,'id_suggestion'=>'']),
    array_merge($target, ['_row_number'=>21,'id_suggestion'=>'']),
];
$ambiguousStored = $urlOnlyStored;
$ambiguousStored['content_fingerprint'] = '';
$ambiguous = be_cc_resolve_inbox_row($ambiguousRows, $ambiguousStored);
$assert($ambiguous['status'] === 'ambiguous', 'Mehrere Inbox-Treffer werden nicht beschrieben.');

$notFound = be_cc_resolve_inbox_row($movedRows, [
    'stable_id'=>'missing','source_url'=>'https://example.org/missing','date'=>'2026-08-30',
    'content_fingerprint'=>hash('sha256','missing'),'row_number'=>8,
]);
$assert($notFound['status'] === 'not_found', 'Falscher Zeilenhinweis wird nicht blind verwendet.');

$auditStored = [
    'verification_key'=>'verify-abc',
    'content_id'=>'event-7',
    'issue_code'=>'description_quality',
    'source_fingerprint'=>'source-fp',
    'content_fingerprint'=>'content-fp',
    'row_number'=>4,
];
$auditRows = [
    ['_row_number'=>4,'verification_key'=>'other','content_id'=>'event-x','issue_code'=>'source'],
    ['_row_number'=>19,'verification_key'=>'verify-abc','content_id'=>'event-7','issue_code'=>'description_quality','source_fingerprint'=>'source-fp','content_fingerprint'=>'content-fp'],
];
$auditMoved = be_cc_resolve_audit_row($auditRows, $auditStored);
$assert($auditMoved['status'] === 'resolved' && $auditMoved['row_number'] === 19, 'Verschobene Auditzeile wird per verification_key gefunden.');

$superseded = be_cc_audit_resolution_state($auditStored, 'content-fp', be_cc_resolve_audit_row([], $auditStored));
$assert($superseded['state'] === 'superseded' && $superseded['write_allowed'] === true, 'Fehlende Auditzeile bei unverändertem Event gilt als überholt, aber schreibbar.');
$conflict = be_cc_audit_resolution_state($auditStored, 'new-content-fp', $auditMoved);
$assert($conflict['state'] === 'content_conflict' && $conflict['write_allowed'] === false, 'Zwischenzeitlich geänderter Event blockiert Überschreiben.');

$duplicateAudit = [
    ['_row_number'=>3,'content_id'=>'event-7','issue_code'=>'description_quality'],
    ['_row_number'=>4,'content_id'=>'event-7','issue_code'=>'description_quality'],
];
$auditAmbiguous = be_cc_resolve_audit_row($duplicateAudit, ['content_id'=>'event-7','issue_code'=>'description_quality']);
$assert($auditAmbiguous['status'] === 'ambiguous', 'Nicht eindeutiger Auditfall wird blockiert.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Sheet Identity: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}
echo "=== Control Center Sheet Identity: OK ===\n";
