<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$domain='';foreach(glob($root.'/api/startpartner/_gate4_*.php')?:[] as $file){$domain.=file_get_contents($file)."\n";}
$content=file_get_contents($root.'/api/startpartner/content.php');
$onboarding=file_get_contents($root.'/api/startpartner/onboarding.php');
$portal=file_get_contents($root.'/api/organizer-portal/pilot.php');
$dashboard=file_get_contents($root.'/js/organizer-pilot.js');
$controlCenter=file_get_contents($root.'/js/control-center/startpartner-gate4.js');
$failures=[];$assert=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
foreach(["payment_kind' => 'startpartner_pilot'","status' => 'in_review'","mail_effect' => 'none'","stripe_effect' => 'none'",'startpartner_pilot_content_links'] as $marker){$assert(str_contains($domain,$marker),"Pilot submission contract missing: {$marker}");}
$assert(str_contains($content,'be_startpartner_gate4_portal_session'),'Pilot content endpoint must require the existing portal session.');
$assert(!str_contains($content,'be_send_mail')&&!str_contains($content,'stripe'),'Pilot content endpoint must not send mail or invoke Stripe.');
$assert(str_contains($domain,'be_startpartner_gate3_scope_target_plan_key'),'Gate 4 must derive requested models from the scope-specific Gate-3 contract.');
$assert(!str_contains($domain,"targetPlans[0]"),'Portal submissions must never use the first pilot target plan as a generic model.');
$assert(str_contains($domain,'scope_target_plan_mismatch'),'Gate-4 readiness must hard-block inconsistent persisted scope target plans.');
$assert(str_contains($domain,"'gate4.scope.repair'"),'Gate 4 must expose an audited repair operation for persisted scope mappings.');
$assert(str_contains($onboarding,"'repair_scope_target_plans'"),'The review-protected onboarding endpoint must route the scope repair action.');
$assert(str_contains($controlCenter,'gate4:repair-scope'),'Control Center must expose the repair only as an explicit operator action.');
$assert(str_contains($portal,'be_startpartner_gate4_portal_candidate'),'Portal status must read the canonical pilot owner.');
$assert(str_contains($dashboard,'/api/startpartner/content.php'),'Organizer dashboard must use the permanent pilot submission endpoint.');
$assert(str_contains($dashboard,'Die Einreichung ist kostenlos und löst keine Zahlung aus.'),'Organizer UI must state the fail-closed payment boundary in plain language.');
if($failures){fwrite(STDERR,"=== Startpartner Gate-4 Submission Contract: FAILED ===\n".implode("\n",array_map(static fn($v)=>'- '.$v,$failures))."\n");exit(1);}echo "=== Startpartner Gate-4 Submission Contract: OK ===\n";
