<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__) . '/startpartner/_gate4_domain.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']);
}

function opg4_session_token(): string
{
    $token=trim((string)($_COOKIE['be_organizer_portal_session']??''));
    if(!preg_match('/^[a-f0-9]{64}$/',$token)) throw new InvalidArgumentException('Organizer session is missing or invalid.');
    return $token;
}

try {
    $pdo=be_db();
    be_startpartner_gate4_require_schema($pdo);
    $session=$pdo->prepare("SELECT s.id AS session_id,s.organizer_id,s.expires_at,s.revoked_at,o.organization_name,o.email FROM organizer_portal_sessions s INNER JOIN organizers o ON o.id=s.organizer_id WHERE s.session_token_hash=:hash LIMIT 1");
    $session->execute(['hash'=>hash('sha256',opg4_session_token())]);
    $row=$session->fetch(PDO::FETCH_ASSOC);
    if(!is_array($row)||!empty($row['revoked_at'])||new DateTimeImmutable((string)$row['expires_at'],new DateTimeZone('UTC'))<new DateTimeImmutable('now',new DateTimeZone('UTC'))){
        be_json_response(401,['status'=>'error','message'=>'Organizer session is not active.']);
    }
    $pilotStmt=$pdo->prepare("SELECT p.*,e.id AS entitlement_id,e.status AS entitlement_status,e.starts_at AS entitlement_starts_at,e.ends_at AS entitlement_ends_at,e.event_limit_per_pilot_month,e.activity_concurrent_limit,e.is_event_unlimited FROM startpartner_pilots p INNER JOIN startpartner_pilot_entitlements e ON e.pilot_id=p.id WHERE p.organizer_id=:organizer_id AND p.status IN ('onboarding','activation_ready','active','paused','closing') ORDER BY p.created_at DESC LIMIT 2");
    $pilotStmt->execute(['organizer_id'=>(int)$row['organizer_id']]);
    $pilots=$pilotStmt->fetchAll(PDO::FETCH_ASSOC);
    if(count($pilots)>1) throw new RuntimeException('Organizer has multiple current Startpartner pilots.');
    if($pilots===[]) be_json_response(200,['status'=>'ok','data'=>['pilot'=>null]]);
    $pilot=$pilots[0];
    $readiness=be_startpartner_gate4_readiness($pdo,(string)$pilot['id']);
    $scopes=$pdo->prepare('SELECT scope_key,scope_type,status,target_plan_key,limit_value,is_unlimited,period_unit,details_json FROM startpartner_pilot_scopes WHERE pilot_id=:pilot_id ORDER BY scope_key');
    $scopes->execute(['pilot_id'=>(string)$pilot['id']]);
    $content=$pdo->prepare('SELECT pcl.id,pcl.content_type,pcl.publication_status,pcl.reporting_target_id,s.id AS submission_id,s.status AS submission_status,s.title,s.start_date FROM startpartner_pilot_content_links pcl INNER JOIN submissions s ON s.id=pcl.submission_id WHERE pcl.pilot_id=:pilot_id ORDER BY pcl.id DESC LIMIT 20');
    $content->execute(['pilot_id'=>(string)$pilot['id']]);
    $activation=$pdo->prepare('SELECT activation_date_local,timezone_name,activated_at_utc,planned_end_date FROM startpartner_pilot_activation_records WHERE pilot_id=:pilot_id LIMIT 1');
    $activation->execute(['pilot_id'=>(string)$pilot['id']]);
    be_json_response(200,['status'=>'ok','data'=>[
        'pilot'=>[
            'id'=>(string)$pilot['id'],'status'=>(string)$pilot['status'],'health'=>(string)$pilot['health'],'revision'=>(int)$pilot['revision'],
            'onboarding_started_at'=>$pilot['onboarding_started_at'],'activation_ready_at'=>$pilot['activation_ready_at'],'activated_at'=>$pilot['activated_at'],'starts_at'=>$pilot['starts_at'],'ends_at'=>$pilot['ends_at'],
            'entitlement'=>['id'=>(string)$pilot['entitlement_id'],'status'=>(string)$pilot['entitlement_status'],'starts_at'=>$pilot['entitlement_starts_at'],'ends_at'=>$pilot['entitlement_ends_at'],'event_limit_per_pilot_month'=>$pilot['event_limit_per_pilot_month']!==null?(int)$pilot['event_limit_per_pilot_month']:null,'activity_concurrent_limit'=>$pilot['activity_concurrent_limit']!==null?(int)$pilot['activity_concurrent_limit']:null,'is_event_unlimited'=>(int)$pilot['is_event_unlimited']===1],
            'scopes'=>$scopes->fetchAll(PDO::FETCH_ASSOC),'content'=>$content->fetchAll(PDO::FETCH_ASSOC),'readiness'=>$readiness,'activation'=>$activation->fetch(PDO::FETCH_ASSOC)?:null,
        ],
    ]]);
} catch (InvalidArgumentException $error) {
    be_json_response(401,['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    $status=str_starts_with($error->getMessage(),'STARTPARTNER_GATE4_SCHEMA_MISSING:')?503:409;
    be_json_response($status,['status'=>'error','message'=>$status===503?'Startpartner Gate-4 schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500,['status'=>'error','message'=>'Startpartner pilot state could not be loaded.','error_message'=>$error->getMessage()]);
}
