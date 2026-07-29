<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

$method=(string)($_SERVER['REQUEST_METHOD']??'GET');
try {
    $pdo=be_db();
    if ($method==='GET') {
        $pilotId=trim((string)($_GET['pilot_id']??''));
        if ($pilotId==='') throw new InvalidArgumentException('pilot_id is required.');
        be_json_response(200,['status'=>'ok','data'=>be_startpartner_gate4_state($pdo,$pilotId)]);
    }
    if ($method!=='POST') { header('Allow: GET, POST'); be_json_response(405,['status'=>'error','message'=>'Method not allowed.']); }
    $input=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');
    $pilotId=trim((string)($input['pilot_id']??''));
    if ($pilotId==='') throw new InvalidArgumentException('pilot_id is required.');
    $action=trim((string)($input['action']??''));
    $result=match($action) {
        'update_item' => be_startpartner_gate4_update_item($pdo,$pilotId,$input),
        'record_portal_proof' => be_startpartner_gate4_portal_proof($pdo,$pilotId,$input),
        'record_measurement' => be_startpartner_gate4_measurement($pdo,$pilotId,$input),
        'record_distribution' => be_startpartner_gate4_distribution($pdo,$pilotId,$input),
        default => throw new InvalidArgumentException('Unsupported Gate-4 onboarding action.'),
    };
    be_json_response(200,['status'=>'ok','data'=>$result]);
} catch (BeStartpartnerConflictException $error) {
    be_json_response(409,['status'=>'error','code'=>'STARTPARTNER_CONFLICT','message'=>'Zwischenzeitlich geändert.','current'=>$error->currentState,'error_message'=>$error->getMessage()]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422,['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    $schemaMissing=str_starts_with($error->getMessage(),'STARTPARTNER_GATE4_SCHEMA_MISSING:');
    be_json_response($schemaMissing?503:404,['status'=>'error','message'=>$schemaMissing?'Startpartner Gate-4 schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500,['status'=>'error','message'=>'Startpartner onboarding action failed.','error_message'=>$error->getMessage()]);
}
