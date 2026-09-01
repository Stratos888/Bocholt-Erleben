<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/startpartner/_gate4_contract.php';

$failures=[];
$assert=static function(bool $condition,string $message)use(&$failures):void{if(!$condition)$failures[]=$message;};
$expectInvalid=static function(callable $callback,string $message)use(&$failures):void{
    try{$callback();$failures[]=$message;}catch(InvalidArgumentException $expected){}
};
$expectDomain=static function(callable $callback,string $message)use(&$failures):void{
    try{$callback();$failures[]=$message;}catch(DomainException $expected){}
};

$assert(be_startpartner_gate4_reporting_target_id(42)===be_startpartner_gate4_reporting_target_id(42),'Reporting target must be deterministic.');
$assert(be_startpartner_gate4_reporting_target_id(42)!==be_startpartner_gate4_reporting_target_id(43),'Reporting target must distinguish organizers.');
$assert(be_startpartner_gate4_add_calendar_months('2026-08-31',6)==='2027-02-28','August 31 must clamp to the last February day.');
$assert(be_startpartner_gate4_add_calendar_months('2027-08-31',6)==='2028-02-29','Leap-year February must be handled.');
$window=be_startpartner_gate4_activation_window('2026-10-25');
$assert($window['activation_date_local']==='2026-10-25','Local activation date must remain stable.');
$assert($window['planned_end_date']==='2027-04-25','Current six-calendar-month contract must remain explicit until the business wording is decided.');
$assert($window['starts_at_utc']==='2026-10-24 22:00:00','Berlin summer offset must convert to UTC.');
$assert($window['ends_at_utc']==='2027-04-25 21:59:59','Berlin end date must convert with the current offset.');

$readiness=be_startpartner_gate4_onboarding_readiness([]);
$assert($readiness['total_count']===14&&$readiness['completed_count']===0,'Missing rows must produce fourteen fail-closed items.');
$complete=[];foreach(BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key){$complete[]=['item_key'=>$key,'status'=>'complete','is_required'=>1];}
$assert(be_startpartner_gate4_onboarding_readiness($complete)['ready']===true,'All required complete items must be activation ready.');

$assert(BE_STARTPARTNER_GATE4_MANUAL_ONBOARDING_ITEMS===['portal_access_tested','content_rights_cleared','activation_target_set'],'Only the three genuinely manual checks may be writable.');
$assert(be_startpartner_gate4_onboarding_item_is_manual('portal_access_tested'),'Portal access must remain a manual check.');
$assert(!be_startpartner_gate4_onboarding_item_is_manual('measurement_ready'),'Measurement readiness must be derived from the technical owner.');
$assert(be_startpartner_gate4_manual_onboarding_key('content_rights_cleared')==='content_rights_cleared','Manual onboarding keys must validate.');
$expectDomain(static fn()=>be_startpartner_gate4_manual_onboarding_key('terms_confirmed'),'Derived onboarding items must reject manual override.');
$expectInvalid(static fn()=>be_startpartner_gate4_validate_local_date('2026-02-30'),'Invalid local dates must fail.');
$expectInvalid(static fn()=>be_startpartner_gate4_content_type('place'),'Unsupported content types must fail.');

$root=dirname(__DIR__);
$casesSource=(string)file_get_contents($root.'/api/control-center/cases.php');
$detailSource=(string)file_get_contents($root.'/api/control-center/case.php');
$assert(str_contains($casesSource,"/startpartner/_gate4_domain.php"),'Control-center case list must load the Gate-4 domain.');
$assert(str_contains($casesSource,'be_startpartner_gate4_candidate_detail($pdo, $candidateId)'),'Control-center case list must enrich Startpartner cases from the authoritative Gate-4 candidate detail.');
$assert(!str_contains($casesSource,'be_startpartner_gate3_candidate_detail($pdo, $candidateId, true)'),'Control-center case list must not stop at the Gate-3 projection after a pilot exists.');
$assert(str_contains($casesSource,'STARTPARTNER_GATE4_SCHEMA_MISSING:'),'Control-center case list must fail closed when the Gate-4 schema is unavailable.');
$assert(str_contains($detailSource,'be_startpartner_gate4_candidate_detail($pdo, $candidateId)'),'Single-case readback must use the same Gate-4 candidate owner as the list projection.');

if($failures){
    fwrite(STDERR,"=== Startpartner Gate-4 Domain Contract: FAILED ===\n".implode("\n",array_map(static fn($v)=>'- '.$v,$failures))."\n");
    exit(1);
}
echo "=== Startpartner Gate-4 Domain Contract: OK ===\n";
