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
$expectDomain=static function(callable $callback,string $message)use(&$failures):void{try{$callback();$failures[]=$message;}catch(DomainException|InvalidArgumentException $expected){}};
$candidate='24100000-0000-4000-8000-000000000001';
$pilot='24100000-0000-4000-8000-000000000002';
$entitlement='24100000-0000-4000-8000-000000000003';
$email='gate4-241@example.invalid';
$token=str_repeat('a',64);
$timezone=new DateTimeZone('Europe/Berlin');
$activationDate=(new DateTimeImmutable('today',$timezone))->format('Y-m-d');
$futureActivationDate=(new DateTimeImmutable('tomorrow',$timezone))->format('Y-m-d');
$distributionDate=(new DateTimeImmutable('+7 days',$timezone))->format('Y-m-d');
$pastDistributionDate=(new DateTimeImmutable('yesterday',$timezone))->format('Y-m-d');
try{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS value_metric_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_date DATE NOT NULL,
    metric_key VARCHAR(64) NOT NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT '',
    entity_id VARCHAR(191) NOT NULL DEFAULT '',
    entity_title VARCHAR(255) NULL,
    destination_url VARCHAR(1024) NULL,
    reporting_target_type VARCHAR(40) NOT NULL DEFAULT '',
    reporting_target_id VARCHAR(191) NOT NULL DEFAULT '',
    reporting_target_title VARCHAR(255) NULL,
    page_path VARCHAR(255) NULL,
    source_context VARCHAR(64) NOT NULL DEFAULT '',
    bucket_hash CHAR(64) NOT NULL,
    count_value INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_value_metric_daily_bucket (bucket_hash),
    KEY idx_value_metric_daily_reporting_target (metric_date, reporting_target_type, reporting_target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

  $exec("INSERT INTO organizers(organization_name,contact_name,email,email_normalized) VALUES('Gate 4 Verein','Erika Test',:email,:email)",['email'=>$email]);$organizer=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_candidates(id,source,organization_name,organization_name_normalized,desired_content_scope,status,status_reason,identity_key,idempotency_key_hash,form_version,retention_review_at,revision,assigned_to,status_changed_at) VALUES(:id,'targeted_outreach','Gate 4 Verein','gate 4 verein','both','accepted_pending_terms','Gate 3 complete',:identity,:idem,'gate4-test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 90 DAY),1,'Contract',UTC_TIMESTAMP())",['id'=>$candidate,'identity'=>hash('sha256','gate4-identity'),'idem'=>hash('sha256','gate4-idem')]);
  $exec("INSERT INTO startpartner_candidate_contacts(candidate_id,contact_name,email,email_normalized,is_primary) VALUES(:candidate,'Erika Test',:email,:email,1)",['candidate'=>$candidate,'email'=>$email]);
  $exec("INSERT INTO startpartner_candidate_decisions(candidate_id,result,reason,operator_reference,candidate_revision,qualification_snapshot_json,capacity_snapshot_json,is_current) VALUES(:candidate,'accepted_pending_terms','Contract fixture','Contract',1,JSON_OBJECT(),JSON_OBJECT(),1)",['candidate'=>$candidate]);$decision=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_candidate_reservations(candidate_id,decision_id,status,starts_at,ends_at,capacity_snapshot_json,operator_reference) VALUES(:candidate,:decision,'active',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 DAY),JSON_OBJECT(),'Contract')",['candidate'=>$candidate,'decision'=>$decision]);$reservation=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_pilot_terms_acceptances(candidate_id,decision_id,terms_version,terms_reference,terms_digest,accepting_person,accepting_organization,accepted_at,confirmation_channel,service_scope_json,source_care_json,reach_contribution_json,no_automatic_paid_renewal,operator_reference) VALUES(:candidate,:decision,'v1','repo://gate4',:digest,'Erika Test','Gate 4 Verein',UTC_TIMESTAMP(),'operator_recorded',JSON_OBJECT('desired_content_scope','both','target_plan_keys',JSON_ARRAY('active','activity_basic')),JSON_OBJECT('description','Automatische Quelle'),JSON_OBJECT('description','Newsletter'),1,'Contract')",['candidate'=>$candidate,'decision'=>$decision,'digest'=>hash('sha256','gate4')]);$terms=(int)$pdo->lastInsertId();
  $exec("INSERT INTO startpartner_pilots(id,candidate_id,organizer_id,terms_acceptance_id,reservation_id,cohort_key,status,target_plan_keys_json,internal_owner,partner_contact_name_snapshot,partner_contact_email_snapshot,revision) VALUES(:id,:candidate,:organizer,:terms,:reservation,'gate4-241','onboarding',JSON_ARRAY('active','activity_basic'),'Contract','Erika Test',:email,1)",['id'=>$pilot,'candidate'=>$candidate,'organizer'=>$organizer,'terms'=>$terms,'reservation'=>$reservation,'email'=>$email]);
  foreach([
    ['events','events','active',8,'pilot_month'],['activities','activities','active',1,'concurrent'],
    ['automatic-source','automatic_source',null,null,'not_applicable'],['maintenance-service','maintenance_service',null,null,'not_applicable'],
    ['provider-portal','provider_portal',null,null,'not_applicable'],['measurement','measurement',null,null,'not_applicable'],
    ['reach-contribution','reach_contribution',null,null,'not_applicable']
  ] as $scope){$exec("INSERT INTO startpartner_pilot_scopes(pilot_id,scope_key,scope_type,status,target_plan_key,limit_value,is_unlimited,period_unit,details_json) VALUES(:pilot,:key,:type,'planned',:plan,:limit,0,:period,JSON_OBJECT('fixture',true))",['pilot'=>$pilot,'key'=>$scope[0],'type'=>$scope[1],'plan'=>$scope[2],'limit'=>$scope[3],'period'=>$scope[4]]);}
  $exec("INSERT INTO startpartner_pilot_entitlements(id,pilot_id,organizer_id,source_reference,status,target_plan_keys_json,event_limit_per_pilot_month,activity_concurrent_limit,is_event_unlimited,source_scope_json,audit_json,revision) VALUES(:id,:pilot,:organizer,:pilot,'pending_activation',JSON_ARRAY('active','activity_basic'),8,1,0,JSON_OBJECT(),JSON_OBJECT(),1)",['id'=>$entitlement,'pilot'=>$pilot,'organizer'=>$organizer]);
  $exec("INSERT INTO organizer_portal_sessions(organizer_id,session_token_hash,expires_at,last_seen_at) VALUES(:organizer,:hash,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP())",['organizer'=>$organizer,'hash'=>hash('sha256',$token)]);
  $_COOKIE['be_organizer_portal_session']=$token;

  $session=be_startpartner_gate4_portal_session($pdo);
  $preRepair=be_startpartner_gate4_candidate_detail($pdo,$candidate);
  $preRepairCodes=array_column((array)$preRepair['gate4']['blockers'],'code');
  $assert(in_array('scope_target_plan_mismatch',$preRepairCodes,true),'Inconsistent activities target plan must be a hard Gate-4 blocker.');
  $serviceBefore=array_values(array_filter((array)$preRepair['gate4']['onboarding']['items'],static fn(array $row):bool=>($row['item_key']??'')==='service_scope_confirmed'))[0]??null;
  $assert(is_array($serviceBefore)&&($serviceBefore['status']??'')==='pending','Service scope must not be reported complete while scope target plans disagree.');
  $expectDomain(static function()use($pdo,$session):void{be_startpartner_gate4_create_portal_submission($pdo,$session,['content_type'=>'activity','client_reference'=>'gate4-241-blocked-activity','title'=>'Blocked activity','location_name'=>'Bocholt','location_address'=>'Testweg 1','location_public_confirmed'=>true]);},'Portal activity submission must fail closed while its scope target-plan mapping is inconsistent.');

  $cr=(int)$preRepair['revision'];$pr=(int)$preRepair['gate4']['pilot']['revision'];
  $repairPayload=['operation_id'=>'gate4:241:scope-repair','operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr];
  $repair=be_startpartner_gate4_repair_scope_target_plans($pdo,$candidate,$repairPayload);
  $assert(($repair['idempotent_replay']??true)===false,'First scope repair must be a real mutation.');
  $changes=$repair['meta']['changes']??[];
  $assert(count($changes)===1&&($changes[0]['scope_key']??'')==='activities'&&($changes[0]['to_target_plan_key']??'')==='activity_basic','Scope repair must change only activities to activity_basic.');
  $repairReplay=be_startpartner_gate4_repair_scope_target_plans($pdo,$candidate,$repairPayload);
  $assert(($repairReplay['idempotent_replay']??false)===true,'Identical scope repair retry must replay idempotently.');
  $repaired=$repair['candidate'];
  $assert(!in_array('scope_target_plan_mismatch',array_column((array)$repaired['gate4']['blockers'],'code'),true),'Scope mismatch blocker must disappear after repair.');
  $assert((string)$pdo->query("SELECT target_plan_key FROM startpartner_pilot_scopes WHERE pilot_id='24100000-0000-4000-8000-000000000002' AND scope_key='activities'")->fetchColumn()==='activity_basic','Persisted activities scope must be repaired to activity_basic.');

  $created=be_startpartner_gate4_create_portal_submission($pdo,$session,['content_type'=>'event','client_reference'=>'gate4-241-first','title'=>'Gate 4 Testevent','start_date'=>'2026-09-10','location_name'=>'Bocholt','location_address'=>'Testweg 1','location_public_confirmed'=>true]);
  $contentId=(string)$created['content_link']['id'];
  $activityCreated=be_startpartner_gate4_create_portal_submission($pdo,$session,['content_type'=>'activity','client_reference'=>'gate4-241-activity','title'=>'Gate 4 Testaktivität','location_name'=>'Bocholt','location_address'=>'Testweg 2','location_public_confirmed'=>true]);
  $activitySubmission=(int)$activityCreated['submission_id'];
  $activityModel=$pdo->prepare('SELECT requested_model_key FROM submissions WHERE id=:id');$activityModel->execute(['id'=>$activitySubmission]);
  $assert((string)$activityModel->fetchColumn()==='activity_basic','Portal activity submission must use requested_model_key=activity_basic.');

  $detail=be_startpartner_gate4_candidate_detail($pdo,$candidate);$cr=(int)$detail['revision'];$pr=(int)$detail['gate4']['pilot']['revision'];
  $mutate=static function(callable $fn,array $payload)use($pdo,$candidate,&$cr,&$pr):array{$payload+=['operation_id'=>'gate4:241:'.bin2hex(random_bytes(6)),'operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr];$result=$fn($pdo,$candidate,$payload);$cr=(int)$result['candidate']['revision'];$pr=(int)$result['candidate']['gate4']['pilot']['revision'];return$result;};

  $expectDomain(static function()use($pdo,$candidate,&$cr,&$pr):void{be_startpartner_gate4_update_onboarding($pdo,$candidate,['operation_id'=>'gate4:241:derived','operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr,'item_key'=>'terms_confirmed','status'=>'complete','evidence_text'=>'Must not be writable.']);},'Derived onboarding items must reject manual writeback.');
  $expectDomain(static function()use($pdo,$candidate,&$cr,&$pr):void{be_startpartner_gate4_update_onboarding($pdo,$candidate,['operation_id'=>'gate4:241:block-no-evidence','operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr,'item_key'=>'portal_access_tested','status'=>'blocked']);},'Blocked manual items must require evidence.');

  foreach([['portal_access_tested','Synthetic portal session readback.'],['content_rights_cleared','Synthetic rights evidence.'],['activation_target_set','Activation target set.']] as [$key,$evidence]){$mutate('be_startpartner_gate4_update_onboarding',['item_key'=>$key,'status'=>'complete','evidence_text'=>$evidence]);}
  $manualState=be_startpartner_gate4_candidate_detail($pdo,$candidate);
  $assert((int)$manualState['gate4']['onboarding']['completed_count']===10,'Seven authoritative and three manual checks must be complete before content readiness.');

  $mutate('be_startpartner_gate4_mark_content_ready',['content_link_id'=>$contentId]);
  $mutate('be_startpartner_gate4_set_measurement',['content_link_id'=>$contentId,'status'=>'ready','evidence_text'=>'Stable organizer, pilot and content attribution.']);
  $measurementState=be_startpartner_gate4_candidate_detail($pdo,$candidate);
  $technical=$measurementState['gate4']['ready_measurement']['evidence']['technical_readback']??null;
  $assert(is_array($technical)&&($technical['query_status']??'')==='ok','Measurement readiness must contain a real read-only owner readback.');
  $assert(($technical['reporting_target_id']??'')===be_startpartner_gate4_reporting_target_id($organizer),'Measurement readback must use the deterministic organizer target.');

  $expectDomain(static function()use($pdo,$candidate,&$cr,&$pr,$pastDistributionDate):void{be_startpartner_gate4_set_distribution($pdo,$candidate,['operation_id'=>'gate4:241:past-distribution','operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr,'channel'=>'newsletter','target_reference'=>'https://example.invalid/past','planned_at'=>$pastDistributionDate,'status'=>'ready','evidence_text'=>'Must fail.']);},'Ready distribution in the past must fail closed.');

  $mutate('be_startpartner_gate4_set_distribution',['channel'=>'newsletter','target_reference'=>'https://example.invalid/gate4','planned_at'=>$distributionDate,'status'=>'ready','evidence_text'=>'Synthetic distribution commitment.']);
  $ready=be_startpartner_gate4_candidate_detail($pdo,$candidate);$assert($ready['gate4']['activation_ready']===true,'Pilot must become activation ready.');

  $mutate('be_startpartner_gate4_set_distribution',['channel'=>'newsletter','target_reference'=>'https://example.invalid/gate4','planned_at'=>$distributionDate,'status'=>'blocked','evidence_text'=>'Optional distribution cooperation is currently blocked.']);
  $blocked=be_startpartner_gate4_candidate_detail($pdo,$candidate);
  $assert($blocked['gate4']['activation_ready']===true,'A blocked optional reach cooperation must not withdraw activation readiness.');
  $cancelled=(int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_distribution_commitments WHERE status='cancelled'")->fetchColumn();
  $assert($cancelled>=1,'Older ready distribution must be superseded instead of remaining authoritative.');

  $mutate('be_startpartner_gate4_set_distribution',['channel'=>'newsletter','target_reference'=>'https://example.invalid/gate4-final','planned_at'=>$distributionDate,'status'=>'ready','evidence_text'=>'Optional distribution cooperation planned.']);
  $readyAgain=be_startpartner_gate4_candidate_detail($pdo,$candidate);$assert($readyAgain['gate4']['activation_ready']===true,'Updating an optional reach cooperation must keep activation readiness unchanged.');

  $portalProjection=be_startpartner_gate4_portal_projection($readyAgain);
  $assert(!array_key_exists('capacity',$portalProjection),'Portal projection must not expose internal capacity.');
  $assert(!array_key_exists('measurement_preflights',$portalProjection),'Portal projection must not expose internal measurement evidence.');

  $expectDomain(static function()use($pdo,$candidate,&$cr,&$pr,$futureActivationDate):void{be_startpartner_gate4_activate($pdo,$candidate,['operation_id'=>'gate4:241:future-activation','operator_name'=>'Contract','expected_revision'=>$cr,'expected_pilot_revision'=>$pr,'activation_date_local'=>$futureActivationDate]);},'Immediate activation must reject a future local start date.');

  $before=[(int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn()];
  $activated=$mutate('be_startpartner_gate4_activate',['activation_date_local'=>$activationDate]);$state=$activated['candidate']['gate4'];
  $assert($state['active']===true,'Pilot must be active.');
  $assert((string)$state['pilot']['planned_end_date']===be_startpartner_gate4_add_calendar_months($activationDate,6),'Planned end date must use the explicit six-calendar-month rule.');
  $assert((string)$state['first_content']['status']==='approved','First content must be approved atomically.');
  $activityStatus=$pdo->prepare('SELECT status FROM startpartner_pilot_content_links WHERE submission_id=:id');$activityStatus->execute(['id'=>$activitySubmission]);
  $assert((string)$activityStatus->fetchColumn()==='draft','Second activity submission must remain draft and must not be published automatically.');
  $assert((int)$state['capacity']['occupied_slots']===1,'Occupied capacity must remain one.');
  $after=[(int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn()];
  $assert($before===$after,'Locked subscription and regular entitlement owners must remain unchanged.');
  $replay=be_startpartner_gate4_activate($pdo,$candidate,['activation_date_local'=>$activationDate,'operation_id'=>$activated['operation_id'],'operator_name'=>'Contract','expected_revision'=>$cr-1,'expected_pilot_revision'=>$pr-1]);
  $assert($replay['idempotent_replay']===true,'Activation retry must replay.');
}catch(Throwable $error){$failures[]=$error::class.': '.$error->getMessage();}
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach(['startpartner_candidate_operations','startpartner_pilot_events','startpartner_pilot_usages','startpartner_pilot_measurement_preflights','startpartner_pilot_distribution_commitments','startpartner_pilot_onboarding_items','startpartner_pilot_content_links','organizer_portal_sessions','submissions','startpartner_pilot_entitlements','startpartner_pilot_scopes','startpartner_pilots','startpartner_pilot_terms_acceptances','startpartner_candidate_reservations','startpartner_candidate_decisions','startpartner_candidate_contacts','control_case_events','control_cases','startpartner_candidates','organizers','value_metric_daily'] as $table){$pdo->exec("DELETE FROM {$table}");}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$assert((int)$pdo->query('SELECT COUNT(*) FROM startpartner_candidates')->fetchColumn()===0,'Cleanup must leave zero candidate residue.');
$assert((int)be_startpartner_gate4_capacity($pdo)['occupied_slots']===0,'Cleanup must leave capacity zero.');
if($failures){fwrite(STDERR,"=== Startpartner Gate-4 MySQL Contract: FAILED ===\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "=== Startpartner Gate-4 MySQL Contract: OK ===\n";
