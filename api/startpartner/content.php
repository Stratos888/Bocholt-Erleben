<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') { header('Allow: POST'); be_json_response(405,['status'=>'error','message'=>'Method not allowed.']); }
try {
    $input=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');
    $pilotId=trim((string)($input['pilot_id']??''));
    if ($pilotId==='') throw new InvalidArgumentException('pilot_id is required.');
    be_json_response(200,['status'=>'ok','data'=>be_startpartner_gate4_link_content(be_db(),$pilotId,$input)]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422,['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    $schemaMissing=str_starts_with($error->getMessage(),'STARTPARTNER_GATE4_SCHEMA_MISSING:');
    be_json_response($schemaMissing?503:404,['status'=>'error','message'=>$schemaMissing?'Startpartner Gate-4 schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500,['status'=>'error','message'=>'Startpartner content link failed.','error_message'=>$error->getMessage()]);
}
