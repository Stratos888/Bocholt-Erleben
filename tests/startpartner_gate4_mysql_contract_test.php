<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/startpartner/_gate4_domain.php';

$dsn=getenv('STARTPARTNER_TEST_DSN')?:'';
$user=getenv('STARTPARTNER_TEST_USER')?:'';
$password=getenv('STARTPARTNER_TEST_PASSWORD')?:'';
if($dsn===''){fwrite(STDERR,"STARTPARTNER_TEST_DSN is required.\n");exit(2);}
$pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$failures=[];
$assert=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
$exec=static function(string $sql,array $params=[])use($pdo):void{$stmt=$pdo->prepare($sql);$stmt->execute($params);};
$candidate='24100000-0000-4000-8000-000000000001';
$pilot='24100000-0000-4000-8000-000000000002';
$entitlement='24100000-0000-4000-8000-000000000003';
$email='gate4-241@example.invalid';
$token=str_repeat('a',64);
try{
  $exec("INSERT INTO organizers(organization_name,contact_name,email,email_normalized) VALUES('Gate 4 Verein','Erika Test',:email,:email)",['email'=>$email]);$organizer=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_candidates(id,source,organization_name,organization_name_normalized,desired_content_scope,status,status_reason,identity_key,idempotency_key_hash,form_version,retention_review_at,revision,assigned_to,status_changed_at) VALUES(:id,'targeted_outreach','Gate 4 Verein','gate 4 verein','both','accepted_pending_terms','Gate 3 complete',:identity,:idem,'gate4-test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 90 DAY),1,'Contract',UTC_TIMESTAMP())",['id'=>$candidate,'identity'=>hash('sha256','gate4-identity'),'idem'=>hash('sha256','gate4-idem')]);
  $exec("INSERT INTO startpartner_candidate_contacts(candidate_id,contact_name,email,email_normalized,is_primary) VALUES(:candidate,'Erika Test',:email,:email,1)",['candidate'=>$candidate,'email'=>$email]);
  $exec("INSERT INTO startpartner_candidate_decisions(candidate_id,result,reason,operator_reference,candidate_revision,qualification_snapshot_json,capacity_snapshot_json,is_current) VALUES(:candidate,'accepted_pending_terms','Contract fixture','Contract',1,JSON_OBJECT(),JSON_OBJECT(),1)",['candidate'=>$candidate]);$decision=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_candidate_reservations(candidate_id,decision_id,status,starts_at,ends_at,capacity_snapshot_json,operator_reference) VALUES(:candidate,:decision,'active',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 DAY),JSON_OBJECT(),'Contract')",['candidate'=>$candidate,'decision'=>$decision]);$reservation=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_pilot_terms_acceptances(candidate_id,decision_id,terms_version,terms_reference,terms_digest,accepting_person,accepting_organization,accepted_at,confirmation_channel,service_scope_json,source_care_json,reach_contribution_json,no_automatic_paid_renewal,operator_reference) VALUES(:candidate,:decision,'v1','repo://gate4',:digest,'Erika Test','Gate 4 Verein',UTC_TIMESTAMP(),'operator_recorded',JSON_OBJECT(),JSON_OBJECT(),JSON_OBJECT(),1,'Contract')",['candidate'=>$candidate,'decision'=>$decision,'digest'=>hash('sha256','gate4')]);$terms=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_pilots(id,candidate_id,organizer_id,terms_acceptance_id,reservation_id,cohort_key,status,target_plan_keys_json,internal_owner,partner_contact_name_snapshot,partner_contact_email_snapshot,revision) VALUES(:id,:candidate,:organizer,:terms,:reservation,'gate4-241','onboarding',JSON_ARRAY('active','activity_basic'),'Contract','Erika Test',:email,1)",['id'=>$pilot,'candidate'=>$candidate,'organizer'=>$organizer,'terms'=>$terms,'reservation'=>$reservation,'email'=>$email]);
  foreach([
    ['events','events','active',8,'pilot_month'],['activities','activities','activity_basic',1,'concurrent'],
    ['automatic-source','automatic_source',null,null,'not_applicable'],['maintenance-service','maintenance_service',null,null,'not_applicable'],
    ['provider-portal','provider_portal',null,null,'not_applicable'],['measurement','measurement',null,null,'not_applicable'],
    ['reach-contribution','reach_contribution',null,null,'not_applicable']
  ] as $scope){$exec("INSERT INTO startpartner_pilot_scopes(pilot_id,scope_key,scope_type,status,target_plan_key,limit_value,is_unlimited,period_unit,details_json) VALUES(:pilot,:key,:type,'planned',:plan,:limit,0,:period,JSON_OBJECT())",['pilot'=>$pilot,'key'=>$scope[0],'type'=>$scope[1],'plan'=>$scope[2],'limit'=>$scope[3],'period'=>$scope[4]]);}
  $exec("INSERT INTO startpartner_pilot_entitlements(id,pilot_id,organizer_id,source_reference,status,target_plan_keys_json,event_limit_per_pilot_month,activity_concurrent_limit,is_event_unlimited,source_scope_json,audit_json,revision) VALUES(:id,:pilot,:organizer,:pilot,'pending_activation',JSON_ARRAY('active','activity_basic'),8,1,0,JSON_OBJECT(),JSON_OBJECT(),1)",['id'=>$entitlement,'pilot'=>$pilot,'organizer'=>$organizer]);
  $exec("INSERT INTO organizer_portal_sessions(organizer_id,session_token_hash,expires_at,last_seen_at) VALUES(:organizer,:hash,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP())",['organizer'=>$organizer,'hash'=>hash('sha256',$token)]);
  $_COOKIE['be_organizer_portal_session']=$token;

  $session=be_startpartner_gate4_portal_session($pdo);
  $created=be_startpartner_gate4_create_portal_submission($pdo,$session,['content_type'=>'event','client_reference'=>'gate4-241-first','title'=>'Gate 4 Testevent','start_date'=>'2026-09-10','location_name'=>'Bocholt','location_address'=>'Testweg 1','location_public_confirmed'=>true]);
  $contentId=(string)$created['content_link']['id'];
  $detail=be_startpartner_gate4_candidate_detail($pdo,$candidate);$cr=(int)$detail['revision'];$pr=(int)$detail['gate4']['pilot']['revision'];
  $mutate=static function(callable $fn,array $payload)use($pdo,$candidate,&$cr,&$pr):array{$payload+=['operation_id'=>'gate4:241:'.bin2hex(random_bytes(6)),'operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr];$result=$fn($pdo,$candidate,$payload);$cr=(int)$result['candidate']['revision'];$pr=(int)$result['candidate']['gate4']['pilot']['revision'];return$result;};
  foreach([['portal_access_tested','Synthetic portal session readback.'],['content_rights_cleared','Synthetic rights evidence.'],['activation_target_set','Activation target set.']] as [$key,$evidence]){$mutate('be_startpartner_gate4_update_onboarding',['item_key'=>$key,'status'=>'complete','evidence_text'=>$evidence]);}
  $mutate('be_startpartner_gate4_mark_content_ready',['content_link_id'=>$contentId]);
  $mutate('be_startpartner_gate4_set_measurement',['content_link_id'=>$contentId,'status'=>'ready','evidence_text'=>'Stable organizer, pilot and content attribution.']);
  $mutate('be_startpartner_gate4_set_distribution',['channel'=>'newsletter','target_reference'=>'https://example.invalid/gate4','planned_at'=>'2026-08-31','status'=>'ready','evidence_text'=>'Synthetic distribution commitment.']);
  $ready=be_startpartner_gate4_candidate_detail($pdo,$candidate);$assert($ready['gate4']['activation_ready']===true,'Pilot must become activation ready.');
  $before=[(int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn()];
  $activated=$mutate('be_startpartner_gate4_activate',['activation_date_local'=>'2026-08-31']);$state=$activated['candidate']['gate4'];
  $assert($state['active']===true,'Pilot must be active.');
  $assert((string)$state['pilot']['planned_end_date']==='2027-02-28','Month-end must clamp.');
  $assert((string)$state['first_content']['status']==='approved','First content must be approved atomically.');
  $assert((int)$state['capacity']['occupied_slots']===1,'Occupied capacity must remain one.');
  $after=[(int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn()];
  $assert($before===$after,'Locked subscription and regular entitlement owners must remain unchanged.');
  $replay=be_startpartner_gate4_activate($pdo,$candidate,['activation_date_local'=>'2026-08-31','operation_id'=>$activated['operation_id'],'operator_name'=>'Contract','expected_revision'=>$cr-1,'expected_pilot_revision'=>$pr-1]);
  $assert($replay['idempotent_replay']===true,'Activation retry must replay.');
}catch(Throwable $error){$failures[]=$error::class.': '.$error->getMessage();}
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach(['startpartner_candidate_operations','startpartner_pilot_events','startpartner_pilot_usages','startpartner_pilot_measurement_preflights','startpartner_pilot_distribution_commitments','startpartner_pilot_onboarding_items','startpartner_pilot_content_links','organizer_portal_sessions','submissions','startpartner_pilot_entitlements','startpartner_pilot_scopes','startpartner_pilots','startpartner_pilot_terms_acceptances','startpartner_candidate_reservations','startpartner_candidate_decisions','startpartner_candidate_contacts','control_case_events','control_cases','startpartner_candidates','organizers'] as $table){$pdo->exec("DELETE FROM {$table}");}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$assert((int)$pdo->query('SELECT COUNT(*) FROM startpartner_candidates')->fetchColumn()===0,'Cleanup must leave zero candidate residue.');
$assert((int)be_startpartner_gate4_capacity($pdo)['occupied_slots']===0,'Cleanup must leave capacity zero.');
if($failures){fwrite(STDERR,"=== Startpartner Gate-4 MySQL Contract: FAILED ===\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "=== Startpartner Gate-4 MySQL Contract: OK ===\n";
