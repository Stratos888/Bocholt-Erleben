<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/control-center/_presentation.php';
function assert_true(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
function assert_contains_text(string $haystack,string $needle,string $message):void{assert_true(str_contains($haystack,$needle),$message);}
$payload=[
    'id_suggestion'=>'event-1','title'=>'Kunstmarkt','date'=>'2026-08-15','time'=>'18:00','time_status'=>'fixed_time','time_details'=>'18:00–21:00 Uhr',
    'city'=>'Bocholt','location'=>'Testhalle','kategorie_suggestion'=>'Kultur','source_url'=>'https://example.org/source',
    'event_url'=>'https://example.org/event','description'=>'Sachliche Beschreibung des Testevents.',
    'visual_key'=>'art_exhibition_gallery','visual_motif'=>'art_market','visual_asset_id'=>'motif-gap-art-market-01','visual_asset_role'=>'specific',
];
$base=['id'=>'case-1','source_system'=>'inbox_feed','state'=>'decision_required','case_type'=>'intake','source_payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
$ready=be_cc_case_presentation($base);
assert_true(($ready['review_contract']['decision_gate']['ready']??false)===true,'vollständiger Event muss entscheidungsreif sein');
assert_true(($ready['display_status']??'')==='Entscheidungsreif','entscheidungsreifer Event darf nicht weiter als Entscheidung erforderlich erscheinen');
assert_true(($ready['primary_action']['key']??'')==='approve','vollständiger Event muss direkt übernehmbar sein');
assert_true(count($ready['source_links']??[])===2,'Eventseite und Prüfquelle müssen getrennt angeboten werden');
assert_true(($ready['review_contract']['facts']['time_details']??'')==='18:00–21:00 Uhr','vollständige Zeitspanne muss im Reviewvertrag erhalten bleiben');
assert_true(($ready['review_contract']['visual']['key_label']??'')==='Kunst, Galerie & Kreativausstellung','Bildbereich muss aus dem zentralen Visual-Pool lesbar bezeichnet werden');
assert_true(($ready['review_contract']['visual']['motif_label']??'')==='Kunstmarkt','Motiv darf nicht als technischer Token präsentiert werden');
assert_true(($ready['review_contract']['visual']['asset_label']??'')==='Freigegebenes Eventbild','gebundenes Asset braucht einen fachlichen Status statt der technischen ID');
unset($payload['visual_asset_id']);$base['source_payload_json']=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$blocked=be_cc_case_presentation($base);
assert_true(($blocked['review_contract']['decision_gate']['ready']??true)===false,'fehlende Asset-Bindung muss blockieren');
assert_true(($blocked['display_status']??'')==='Entscheidung erforderlich','blockierter Event muss seinen Arbeitsstatus behalten');
assert_true(($blocked['primary_action']['key']??'')==='resolve_review_task','blockierter Event muss in die fokussierte Ausnahmeprüfung führen');
assert_true(!empty($blocked['decision_context']['review_tasks']??[]),'Präsentation muss direkte Prüfaufgaben liefern');
$audit=be_cc_case_presentation(['source_system'=>'content_audit','state'=>'decision_required','case_type'=>'intake','source_payload_json'=>json_encode(['issue_code'=>'event_description_promotional'],JSON_UNESCAPED_UNICODE)]);
assert_true(($audit['primary_action']['key']??'')==='edit_and_approve','Beschreibungskorrektur bleibt im konsolidierten Audit-Editor');

$html=(string)file_get_contents(__DIR__ . '/../steuerzentrale/index.html');
$loader=(string)file_get_contents(__DIR__ . '/../js/control-center.js');
$app=(string)file_get_contents(__DIR__ . '/../js/control-center/app.js');
$reviewModule=(string)file_get_contents(__DIR__ . '/../js/control-center/review.js');
$renderer=(string)file_get_contents(__DIR__ . '/../js/control-center/review-render.js');
$cacheKey='2026-07-17-review-presentation-v1';
assert_contains_text($html,'control-center.js?v=' . $cacheKey,'Top-Level-Cachekey muss den Premium-Präsentationsstand laden');
assert_contains_text($loader,'control-center/app.js?v=' . $cacheKey,'Loader muss den aktuellen App-Build laden');
assert_contains_text($app,'review.js?v=' . $cacheKey,'App muss das aktuelle Review-Modul laden');
assert_contains_text($reviewModule,'review-render.js?v=' . $cacheKey,'Review-Modul muss den aktuellen Renderer laden');
assert_contains_text($renderer,'facts.time_details||facts.time','vollständige Zeitangabe muss vor der Startzeit priorisiert werden');
assert_contains_text($renderer,'visual.key_label||visual.key','lesbarer Bildbereich muss vor dem technischen Token stehen');
assert_contains_text($renderer,'visual.motif_label||visual.motif','lesbares Motiv muss vor dem technischen Token stehen');
assert_contains_text($renderer,"visual.asset_label||(asset?'Freigegebenes Eventbild':'')",'Bildstatus muss die technische Asset-ID ersetzen');
assert_contains_text($renderer,'confidenceLabels','Evidenzstufen müssen deutsch präsentiert werden');
assert_true(!str_contains($renderer,"asset.id||'Bildvorschlag'"),'Asset-ID darf nicht mehr die Hauptbeschriftung des Bildes sein');
echo "=== Control Center Presentation Contract: OK ===\n";
