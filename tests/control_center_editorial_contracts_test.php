<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/control-center/_editorial_contracts.php';
$failures=[];
$assert=static function(bool $condition,string $label)use(&$failures):void{if(!$condition)$failures[]=$label;};
$fixture=json_decode((string)file_get_contents(__DIR__.'/fixtures/control_center_editorial_cases.json'),true,512,JSON_THROW_ON_ERROR);
foreach($fixture['event_cases']??[] as $case){
    $result=be_cc_event_candidate_review_contract($case['payload']??[]);
    $assert(($result['decision_gate']['ready']??null)===($case['ready']??null),'Entscheidungsreife: '.($case['name']??'unbenannt'));
    if(!empty($case['blocker'])){
        $codes=array_column($result['decision_gate']['blockers']??[],'code');
        $assert(in_array($case['blocker'],$codes,true),'Blocker vorhanden: '.$case['name']);
    }
    if(!empty($case['task_id'])){
        $tasks=array_values(array_filter($result['review_tasks']??[],static fn(array $task):bool=>($task['task_id']??'')===$case['task_id']));
        $assert(count($tasks)===1,'Eindeutiger Prüfpunkt: '.$case['name']);
        $assert(!empty($tasks[0]['task_revision']??''),'Prüfpunkt besitzt Revision: '.$case['name']);
        $assert(!empty($tasks[0]['evidence']['status']??''),'Prüfpunkt besitzt Evidenz: '.$case['name']);
        $assert(!empty($tasks[0]['actions']??[]),'Prüfpunkt besitzt direkte Aktion: '.$case['name']);
        if(!empty($case['task_state']))$assert(($tasks[0]['state']??'')===$case['task_state'],'Prüfpunktzustand: '.$case['name']);
    }
}
$city=be_cc_event_candidate_review_contract($fixture['event_cases'][5]['payload']);
$cityTask=array_values(array_filter($city['review_tasks'],static fn(array $task):bool=>$task['task_id']==='visual.asset'))[0]??[];
$asset=$cityTask['evidence']['asset']??[];
$assert(($asset['id']??'')==='motif-gap-art-market-01','CityArt löst konkretes ready-Asset auf.');
$assert(($city['summary']['headline']??'')==='Noch 2 Punkte klären','CityArt zeigt exakt zwei offene Ausnahmen.');
$cityWaiting=$fixture['event_cases'][5]['payload'];$cityWaiting['visual_gap_id']='visual-gap-cityart-existing';
$cityCandidate=be_cc_event_candidate_review_contract($cityWaiting);
$cityCandidateTask=array_values(array_filter($cityCandidate['review_tasks'],static fn(array $task):bool=>$task['task_id']==='visual.asset'))[0]??[];
$assert(($cityCandidateTask['state']??'')==='candidate_ready','Ein fertiges Asset kehrt als sichtbarer Kandidat in denselben Visual-Prüfpunkt zurück.');


$base=[
    'id_suggestion'=>'matrix-base','title'=>'Kunstmarkt am Rathaus','date'=>'2026-08-08','time'=>'18:00','time_status'=>'fixed_time',
    'city'=>'Bocholt','location'=>'Markt','address'=>'Markt 1','category'=>'Kultur','source_url'=>'https://example.org/source',
    'description'=>'Sachliche Beschreibung eines öffentlichen Kunstmarkts.','visual_key'=>'art_exhibition_gallery','visual_motif'=>'art_market',
    'visual_asset_id'=>'motif-gap-art-market-01','visual_asset_role'=>'specific','_review_today'=>'2026-07-16',
];
$matrix=[
    'missing_title'=>['field.title',static function(array $p):array{$p['title']='';return $p;}],
    'missing_date'=>['field.date',static function(array $p):array{$p['date']='';return $p;}],
    'missing_location'=>['field.location',static function(array $p):array{$p['location']='';return $p;}],
    'missing_category'=>['field.category',static function(array $p):array{$p['category']='';return $p;}],
    'missing_source'=>['field.source_url',static function(array $p):array{$p['source_url']='';return $p;}],
    'missing_description'=>['field.description',static function(array $p):array{$p['description']='';return $p;}],
    'missing_scope'=>['scope.local_reference',static function(array $p):array{$p['city']='';$p['local_reference']='';return $p;}],
    'invalid_start'=>['schedule.start_date',static function(array $p):array{$p['date']='2026-99-99';return $p;}],
    'invalid_end'=>['schedule.end_date',static function(array $p):array{$p['end_date']='2026-99-99';return $p;}],
    'end_before_start'=>['schedule.range_order',static function(array $p):array{$p['end_date']='2026-08-01';return $p;}],
    'multi_day_missing_end'=>['schedule.multi_day',static function(array $p):array{$p['is_multi_day']=true;$p['end_date']='';return $p;}],
    'expired'=>['schedule.expired',static function(array $p):array{$p['date']='2020-01-01';return $p;}],
    'cancelled'=>['schedule.cancelled',static function(array $p):array{$p['event_status']='cancelled';return $p;}],
    'time_missing'=>['time.status',static function(array $p):array{$p['time']='';$p['time_status']='';return $p;}],
    'time_not_published'=>['time.status',static function(array $p):array{$p['time']='';$p['time_status']='time_not_published';$p['next_review_at']='2026-07-30';return $p;}],
    'time_conflict'=>['time.status',static function(array $p):array{$p['time']='';$p['time_status']='time_conflict';$p['time_details']='Quelle A: 10 Uhr; Quelle B: 11 Uhr';return $p;}],
    'time_details'=>['time.details',static function(array $p):array{$p['time']='';$p['time_status']='multiple_times';$p['time_details']='';return $p;}],
    'time_not_applicable'=>['time.not_applicable',static function(array $p):array{$p['time']='';$p['time_status']='time_not_applicable';return $p;}],
    'address_required'=>['location.address',static function(array $p):array{$p['address']='';$p['address_required']=true;return $p;}],
    'invalid_source'=>['source.official',static function(array $p):array{$p['source_url']='example.org';return $p;}],
    'weak_source'=>['source.quality',static function(array $p):array{$p['source_quality']='weak';return $p;}],
    'source_conflict'=>['source.quality',static function(array $p):array{$p['source_conflict']=true;return $p;}],
    'event_link'=>['source.event_link',static function(array $p):array{$p['event_link_required']=true;$p['event_url']='';return $p;}],
    'description_quality'=>['description.quality',static function(array $p):array{$p['description_status']='unsupported_claim';return $p;}],
    'invalid_ticket'=>['access.link',static function(array $p):array{$p['ticket_url']='ticket';return $p;}],
    'missing_access'=>['access.status',static function(array $p):array{$p['ticket_required']=true;$p['ticket_url']='';$p['access_status']='';return $p;}],
    'duplicate'=>['dedupe.decision',static function(array $p):array{$p['matched_event_id']='existing';return $p;}],
    'visual_key'=>['visual.key',static function(array $p):array{$p['visual_key']='';$p['visual_motif']='';$p['visual_asset_id']='';return $p;}],
    'visual_motif'=>['visual.motif',static function(array $p):array{$p['title']='Abstrakte Ausstellung';$p['description']='Eine Ausstellung.';$p['visual_motif']='';$p['visual_asset_id']='';return $p;}],
    'visual_asset'=>['visual.asset',static function(array $p):array{$p['visual_asset_id']='';return $p;}],
    'invalid_binding'=>['visual.asset',static function(array $p):array{$p['visual_asset_id']='does-not-exist';return $p;}],
    'visual_gap'=>['visual.asset',static function(array $p):array{$p['visual_key']='market_stalls';$p['visual_motif']='fabric_market';$p['visual_asset_id']='';return $p;}],
];
foreach($matrix as $name=>[$taskId,$mutate]){
    $result=be_cc_event_candidate_review_contract($mutate($base));
    $ids=array_column($result['review_tasks']??[],'task_id');
    $assert(in_array($taskId,$ids,true),'Befundmatrix enthält '.$name.' → '.$taskId);
    $task=array_values(array_filter($result['review_tasks']??[],static fn(array $item):bool=>($item['task_id']??'')===$taskId))[0]??[];
    $assert(!empty($task['persistence']['fields']??[]),'Persistenzvertrag vorhanden: '.$name);
    $assert(!empty($task['follow_up']['processes']??[]),'Folgeprozess vorhanden: '.$name);
    $assert(!empty($task['postcondition']??[]),'Postcondition vorhanden: '.$name);
    $assert(!empty($task['audit']['event']??''),'Auditwirkung vorhanden: '.$name);
}

if($failures){fwrite(STDERR,"=== Control Center Editorial Contracts: FAILED ===\n");foreach($failures as $failure)fwrite(STDERR,'- '.$failure."\n");exit(1);}echo "=== Control Center Editorial Contracts: OK ===\n";
