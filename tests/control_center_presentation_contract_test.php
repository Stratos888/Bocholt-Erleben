<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/control-center/_presentation.php';
function assert_true(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$base = [
    'id'=>'case-1','source_system'=>'inbox_feed','state'=>'decision_required','case_type'=>'intake',
    'source_payload_json'=>json_encode([
        'id_suggestion'=>'event-1','title'=>'Testevent','date'=>'2026-08-15','time'=>'18:00','city'=>'Bocholt','location'=>'Testhalle',
        'kategorie_suggestion'=>'Kultur','source_url'=>'https://example.org/event','description'=>'Sachliche Beschreibung des Testevents.',
        'visual_key'=>'culture','visual_motif'=>'Bühne',
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
];
$ready=be_cc_case_presentation($base);
assert_true(($ready['review_contract']['decision_gate']['ready']??false)===true,'vollständiger Event muss entscheidungsreif sein');
assert_true(($ready['primary_action']['key']??'')==='approve','vollständiger Event muss direkt übernehmbar sein');
assert_true(in_array('edit_and_approve',array_column($ready['secondary_actions'],'key'),true),'Bearbeiten-und-Übernehmen muss zusätzlich angeboten werden');
$incomplete=json_decode((string)$base['source_payload_json'],true); unset($incomplete['source_url']); $base['source_payload_json']=json_encode($incomplete,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$blocked=be_cc_case_presentation($base);
assert_true(($blocked['review_contract']['decision_gate']['ready']??true)===false,'fehlende Quelle muss blockieren');
assert_true(($blocked['primary_action']['key']??'')==='edit_and_approve','blockierter Event muss in den Editor führen');
$audit=be_cc_case_presentation(['source_system'=>'content_audit','state'=>'decision_required','case_type'=>'intake','source_payload_json'=>json_encode(['issue_code'=>'event_description_promotional'],JSON_UNESCAPED_UNICODE)]);
assert_true(($audit['primary_action']['key']??'')==='edit_and_approve','Beschreibungskorrektur muss in den konsolidierten Editor führen');
echo "=== Control Center Presentation Contract: OK ===\n";
