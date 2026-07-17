<?php
declare(strict_types=1);
function be_cc_column_letter(int $index): string { $index++; $letters=''; while($index>0){$mod=($index-1)%26;$letters=chr(65+$mod).$letters;$index=intdiv($index-1,26);} return $letters; }
function be_cc_identity_text(mixed $value): string { return strtolower(trim((string)$value)); }
require_once dirname(__DIR__).'/api/control-center/_editorial_contracts.php';
require_once dirname(__DIR__).'/api/control-center/_inbox_decision_writeback.php';
require_once dirname(__DIR__).'/api/control-center/_event_review_writeback.php';
$failures=[];$assert=static function(bool $condition,string $label)use(&$failures):void{if(!$condition)$failures[]=$label;};$throws=static function(callable $callback,string $needle,string $label)use(&$failures):void{try{$callback();$failures[]=$label.' (keine Exception)';}catch(Throwable $error){if(!str_contains($error->getMessage(),$needle))$failures[]=$label.' ('.$error->getMessage().')';}};
$values=[['status','title','date','id_suggestion','source_url'],['review','CityArt','2026-08-30','cityart-2026','https://example.org/cityart']];
$table=be_cc_inbox_direct_table_from_values($values, 'Inbox_Staging');
$plan=be_cc_inbox_direct_canonical_plan($table,2,['time_status'=>'all_day','visual_asset_id'=>'motif-gap-art-market-01']);
$assert(count($plan['new_headers'])===2,'fehlende kanonische Spalten werden geplant');
$assert(count($plan['data'])===4,'Header und Werte werden atomar in einem Batch geplant');
$assert(($plan['columns']['time_status']['column_name']??'')==='time_status','kanonischer Zeitstatus erhält stabile Spalte');

$city=be_cc_event_candidate_review_contract([
    'id_suggestion'=>'cityart-2026','title'=>'Kunstmarkt CityArt','date'=>'2026-08-30','city'=>'Bocholt','location'=>'Markt',
    'category'=>'Kultur','source_url'=>'https://example.org/cityart','description'=>'Lokaler Kunstmarkt.','visual_key'=>'art_exhibition_gallery','_review_today'=>'2026-07-16',
]);
$task=be_cc_review_runtime_find_task($city,'visual.asset');
$action=be_cc_review_runtime_find_action($task,'use_visual_asset');
$updates=be_cc_review_runtime_values($action,[]);
$normalized=be_cc_review_runtime_validate_updates([], 'use_visual_asset', $updates);
$assert(($normalized['visual_asset_id']??'')==='motif-gap-art-market-01','konkretes CityArt-Asset wird akzeptiert');
$assert(($normalized['visual_asset_role']??'')==='specific','konkretes Asset wird als spezifisch gebunden');

$fallback=be_cc_review_runtime_validate_updates([], 'use_visual_fallback', [
    'visual_key'=>'art_exhibition_gallery','visual_motif'=>'art_market','visual_asset_id'=>'art-exhibition-gallery-01','visual_asset_role'=>'fallback','visual_gap_id'=>'visual-gap-test',
]);
$assert(($fallback['visual_asset_role']??'')==='fallback','neutraler Fallback darf trotz abweichendem Motiv gebunden werden');
$throws(static fn()=>be_cc_review_runtime_validate_updates([], 'set_time_status', ['time_status'=>'multiple_times']), 'Zeitraum', 'mehrere Zeiten ohne Details werden blockiert');
$throws(static fn()=>be_cc_review_runtime_validate_updates([], 'set_time_status', ['time_status'=>'time_not_applicable']), 'nicht als sichere Ausnahme', 'nicht anwendbare Uhrzeit braucht Freigabe');
$allowedNotApplicable=be_cc_review_runtime_validate_updates(['time_not_applicable_allowed'=>'true'],'set_time_status',['time_status'=>'time_not_applicable','time_details'=>'Für diesen offenen Dauerzustand fachlich nicht relevant.']);
$assert(($allowedNotApplicable['time_status']??'')==='time_not_applicable','explizit erlaubte nicht anwendbare Uhrzeit wird akzeptiert');
$future=be_cc_review_runtime_validate_updates([], 'time_not_published', ['time_status'=>'time_not_published','next_review_at'=>'2099-01-01']);
$assert(($future['next_review_at']??'')==='2099-01-01','Wiedervorlage wird typisiert validiert');

if($failures){fwrite(STDERR,"=== Exception Review Runtime: FAILED ===\n");foreach($failures as $failure)fwrite(STDERR,'- '.$failure."\n");exit(1);}echo "=== Exception Review Runtime: OK ===\n";
