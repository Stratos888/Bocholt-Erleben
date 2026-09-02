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
$assert($readiness['total_count']===13&&$readiness['completed_count']===0,'Missing rows must produce thirteen fail-closed required items.');
$assert(!in_array('distribution_ready',BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS,true),'Optional reach cooperation must not be an activation onboarding item.');
$complete=[];foreach(BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key){$complete[]=['item_key'=>$key,'status'=>'complete','is_required'=>1];}
$assert(be_startpartner_gate4_onboarding_readiness($complete)['ready']===true,'All required complete items must be activation ready without a reach commitment.');

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
$stateSource=(string)file_get_contents($root.'/api/startpartner/_gate4_state.php');
$termsSource=(string)file_get_contents($root.'/api/startpartner/_gate3_communication.php');
$activationSource=(string)file_get_contents($root.'/api/startpartner/_gate4_activation_domain.php');
$assert(str_contains($casesSource,"/startpartner/_gate4_domain.php"),'Control-center case list must load the Gate-4 domain.');
$assert(str_contains($casesSource,'be_startpartner_gate4_candidate_detail($pdo, $candidateId)'),'Control-center case list must enrich Startpartner cases from the authoritative Gate-4 candidate detail.');
$assert(!str_contains($casesSource,'be_startpartner_gate3_candidate_detail($pdo, $candidateId, true)'),'Control-center case list must not stop at the Gate-3 projection after a pilot exists.');
$assert(str_contains($casesSource,'STARTPARTNER_GATE4_SCHEMA_MISSING:'),'Control-center case list must fail closed when the Gate-4 schema is unavailable.');
$assert(str_contains($detailSource,'be_startpartner_gate4_candidate_detail('),'Single-case readback must use the same Gate-4 candidate owner as the list projection.');
$assert(!str_contains($stateSource,"'code' => 'distribution_not_ready'"),'Missing optional reach cooperation must not create a Gate-4 blocker.');
$assert(str_contains($stateSource,"BE_STARTPARTNER_GATE4_TERMS_V3"),'Gate 4 must recognize the current value-first terms version.');
$assert(str_contains($stateSource,"BE_STARTPARTNER_GATE4_TERMS_V2"),'Gate 4 must preserve compatibility with already accepted v2 terms.');
$assert(str_contains($termsSource,"distribution_commitment_rule' => 'optional_not_required_for_activation'"),'Current terms must encode reach cooperation as optional and non-gating.');
$assert(str_contains($termsSource,'keine Voraussetzung für Pilotstart oder Veröffentlichung'),'Current terms must explain the optional reach cooperation in partner-facing language.');
$assert(str_contains($activationSource,"in_array(\$pilotStatusBefore, ['onboarding', 'activation_ready'], true)"),'Activation must accept a safely derived activation-ready legacy pilot whose persisted status is still onboarding.');
$assert(str_contains($activationSource,"SET status = 'activation_ready'"),'Activation must synchronize a stale onboarding pilot to the canonical activation-ready state inside the activation transaction.');
$assert(str_contains($activationSource,"if (!is_array(\$measurement))"),'Measurement readiness must remain a hard activation requirement.');
$assert(!str_contains($activationSource,"!is_array(\$measurement) || !is_array(\$distribution)"),'Optional reach cooperation must not remain a hidden activation requirement.');
$assert(str_contains($activationSource,"'distribution_id' => is_array(\$distribution) ? (string)\$distribution['id'] : null"),'Activation audit must tolerate a missing optional reach cooperation without fabricating evidence.');
$assert(str_contains($activationSource,"'distribution_requirement' => 'optional_not_required_for_activation'"),'Activation audit must record the value-first optional reach contract explicitly.');

if($failures){
    fwrite(STDERR,"=== Startpartner Gate-4 Domain Contract: FAILED ===\n".implode("\n",array_map(static fn($v)=>'- '.$v,$failures))."\n");
    exit(1);
}
echo "=== Startpartner Gate-4 Domain Contract: OK ===\n";