<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Request body must be valid JSON.');
    $result = be_startpartner_gate4_activate(be_db(), $input);
    be_json_response(200, ['status'=>'ok','data'=>$result]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, ['status'=>'error','message'=>$error->getMessage()]);
} catch (DomainException $error) {
    $message=$error->getMessage();
    $status=(str_contains($message,'stale')||str_contains($message,'conflict')||str_contains($message,'already processing'))?409:422;
    be_json_response($status, ['status'=>'error','message'=>$message]);
} catch (RuntimeException $error) {
    $status=str_starts_with($error->getMessage(),'STARTPARTNER_GATE4_SCHEMA_MISSING:')?503:409;
    be_json_response($status,['status'=>'error','message'=>$status===503?'Startpartner Gate-4 schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500,['status'=>'error','message'=>'Gate-4 activation could not be processed.','error_message'=>$error->getMessage()]);
}
