<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

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
        $stmt = $pdo->prepare('SELECT pcl.*,s.status AS submission_status,s.title,s.start_date,s.location_name FROM startpartner_pilot_content_links pcl INNER JOIN submissions s ON s.id=pcl.submission_id WHERE pcl.pilot_id=:pilot_id ORDER BY pcl.id DESC');
        $stmt->execute(['pilot_id'=>$pilotId]);
        be_json_response(200, ['status'=>'ok','data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'readiness'=>be_startpartner_gate4_readiness($pdo,$pilotId)]]);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Request body must be valid JSON.');
    $pilotId = be_startpartner_gate4_uuid($input['pilot_id'] ?? null, 'pilot_id');
    $submissionId = filter_var($input['submission_id'] ?? null, FILTER_VALIDATE_INT);
    if ($submissionId === false || $submissionId < 1) throw new InvalidArgumentException('submission_id is required.');
    $operator = be_startpartner_gate4_text($input['operator_name'] ?? null, 'operator_name');
    $publicationStatus = strtolower(be_startpartner_gate4_text($input['publication_status'] ?? 'prepared','publication_status',32));
    if (!in_array($publicationStatus,['prepared','editorial_ready','rejected','withdrawn'],true)) throw new InvalidArgumentException('publication_status is invalid.');

    $pdo->beginTransaction();
    $pilot = be_startpartner_gate4_pilot_for_update($pdo,$pilotId);
    if ((int)($input['expected_pilot_revision'] ?? 0)!==(int)$pilot['revision']) throw new DomainException('stale pilot revision.');
    if (!in_array((string)$pilot['status'],['onboarding','activation_ready'],true)) throw new DomainException('pilot is not in onboarding.');
    $submission = $pdo->prepare('SELECT * FROM submissions WHERE id=:id LIMIT 1 FOR UPDATE');
    $submission->execute(['id'=>(int)$submissionId]);
    $row = $submission->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new DomainException('submission not found.');
    if ((int)$row['organizer_id']!==(int)$pilot['organizer_id']) throw new DomainException('submission organizer does not match pilot organizer.');
    $kind=(string)($row['submission_kind']??'event');
    if (!in_array($kind,['event','activity'],true)) throw new DomainException('submission kind is not supported.');
    if ($publicationStatus==='editorial_ready' && !in_array((string)$row['status'],['paid','in_review'],true)) throw new DomainException('submission must be paid or in_review before editorial_ready.');
    $targetId='organizer-'.substr(hash('sha256','organizer:'.(int)$pilot['organizer_id']),0,16);
    $stmt=$pdo->prepare("INSERT INTO startpartner_pilot_content_links (pilot_id,organizer_id,submission_id,content_type,content_reference,publication_status,reporting_target_type,reporting_target_id,source_reference) VALUES (:pilot_id,:organizer_id,:submission_id,:content_type,:content_reference,:publication_status,'organizer',:reporting_target_id,:source_reference) ON DUPLICATE KEY UPDATE pilot_id=VALUES(pilot_id),organizer_id=VALUES(organizer_id),content_type=VALUES(content_type),content_reference=VALUES(content_reference),publication_status=VALUES(publication_status),reporting_target_type=VALUES(reporting_target_type),reporting_target_id=VALUES(reporting_target_id),source_reference=VALUES(source_reference),approved_at=NULL");
    $stmt->execute(['pilot_id'=>$pilotId,'organizer_id'=>(int)$pilot['organizer_id'],'submission_id'=>(int)$submissionId,'content_type'=>$kind,'content_reference'=>trim((string)($input['content_reference']??''))?:null,'publication_status'=>$publicationStatus,'reporting_target_id'=>$targetId,'source_reference'=>trim((string)($input['source_reference']??''))?:null]);
    $pdo->prepare('UPDATE startpartner_pilots SET status="onboarding",activation_ready_at=NULL,revision=revision+1 WHERE id=:id AND revision=:revision')->execute(['id'=>$pilotId,'revision'=>(int)$pilot['revision']]);
    $pdo->prepare("INSERT INTO startpartner_pilot_events (pilot_id,event_type,actor_reference,payload_json) VALUES (:pilot_id,'content_link_updated',:actor,:payload)")->execute(['pilot_id'=>$pilotId,'actor'=>$operator,'payload'=>json_encode(['submission_id'=>(int)$submissionId,'publication_status'=>$publicationStatus,'reporting_target_id'=>$targetId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    be_json_response(200,['status'=>'ok','data'=>['pilot_id'=>$pilotId,'submission_id'=>(int)$submissionId,'publication_status'=>$publicationStatus,'reporting_target_id'=>$targetId,'pilot_revision'=>(int)$pilot['revision']+1]]);
} catch (InvalidArgumentException|DomainException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    be_json_response(422,['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $status=str_starts_with($error->getMessage(),'STARTPARTNER_GATE4_SCHEMA_MISSING:')?503:409;
    be_json_response($status,['status'=>'error','message'=>$status===503?'Startpartner Gate-4 schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    be_json_response(500,['status'=>'error','message'=>'Gate-4 content could not be processed.','error_message'=>$error->getMessage()]);
}
