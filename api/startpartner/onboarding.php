<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_resources.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']);
}

try {
    $pdo = be_db();
    be_startpartner_gate4_require_schema($pdo);
    if ($method === 'GET') {
        $pilotId = be_startpartner_gate4_uuid($_GET['pilot_id'] ?? null, 'pilot_id');
        $distribution=$pdo->prepare('SELECT * FROM startpartner_pilot_distribution_commitments WHERE pilot_id=:pilot_id ORDER BY planned_at,id');
        $distribution->execute(['pilot_id'=>$pilotId]);
        $readiness=be_startpartner_gate4_readiness($pdo,$pilotId);
        $readiness['distribution']=$distribution->fetchAll(PDO::FETCH_ASSOC);
        be_json_response(200, ['status'=>'ok','data'=>$readiness]);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Request body must be valid JSON.');
    $pilotId = be_startpartner_gate4_uuid($input['pilot_id'] ?? null, 'pilot_id');
    $resource=strtolower(trim((string)($input['resource']??'item')));
    if(!in_array($resource,['item','measurement','distribution'],true))throw new InvalidArgumentException('resource is invalid.');
    $pdo->beginTransaction();
    $pilot = be_startpartner_gate4_pilot_for_update($pdo, $pilotId);
    if (!in_array((string)$pilot['status'], ['onboarding','activation_ready'], true)) {
        throw new DomainException('Only onboarding or activation_ready pilots can be changed.');
    }
    if ((int)($input['expected_pilot_revision'] ?? 0) !== (int)$pilot['revision']) {
        throw new DomainException('stale pilot revision.');
    }
    $data=match($resource){
        'measurement'=>be_startpartner_gate4_upsert_measurement($pdo,$pilotId,$input),
        'distribution'=>be_startpartner_gate4_upsert_distribution($pdo,$pilotId,$input),
        default=>be_startpartner_gate4_upsert_onboarding_item($pdo,$pilotId,$input),
    };
    $nextStatus = $data['ready'] ? 'activation_ready' : 'onboarding';
    $update = $pdo->prepare("UPDATE startpartner_pilots SET status=:status,activation_ready_at=CASE WHEN :status_ready='activation_ready' THEN COALESCE(activation_ready_at,UTC_TIMESTAMP()) ELSE NULL END,revision=revision+1 WHERE id=:id AND revision=:revision");
    $update->execute(['status'=>$nextStatus,'status_ready'=>$nextStatus,'id'=>$pilotId,'revision'=>(int)$pilot['revision']]);
    if ($update->rowCount() !== 1) throw new DomainException('stale pilot revision.');
    $pdo->prepare("INSERT INTO startpartner_pilot_events (pilot_id,event_type,actor_reference,payload_json) VALUES (:pilot_id,:event_type,:actor,:payload)")
        ->execute(['pilot_id'=>$pilotId,'event_type'=>'gate4_'.$resource.'_updated','actor'=>be_startpartner_gate4_text($input['operator_name'] ?? null,'operator_name'),'payload'=>json_encode(['resource'=>$resource,'input_key'=>$input['item_key']??$input['content_link_id']??$input['commitment_id']??null,'status'=>$input['status']??null,'readiness'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    $data['pilot_status']=$nextStatus;
    $data['pilot_revision']=(int)$pilot['revision']+1;
    be_json_response(200, ['status'=>'ok','data'=>$data]);
} catch (InvalidArgumentException|DomainException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    be_json_response(422, ['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $status = str_starts_with($error->getMessage(), 'STARTPARTNER_GATE4_SCHEMA_MISSING:') ? 503 : 409;
    be_json_response($status, ['status'=>'error','message'=>$status===503?'Startpartner Gate-4 schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    be_json_response(500, ['status'=>'error','message'=>'Gate-4 onboarding could not be processed.','error_message'=>$error->getMessage()]);
}
