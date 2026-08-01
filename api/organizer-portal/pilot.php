<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__) . '/startpartner/_gate4_domain.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']);
}

try {
    $pdo = be_db();
    $session = be_startpartner_gate4_portal_session($pdo);
    $candidate = be_startpartner_gate4_portal_candidate($pdo, (int)$session['organizer_id']);
    be_json_response(200, ['status'=>'ok','data'=>[
        'portal_session_id'=>(int)$session['portal_session_id'],
        'organizer_id'=>(int)$session['organizer_id'],
        'candidate'=>$candidate,
        'gate4'=>$candidate['gate4'],
    ]]);
} catch (DomainException $error) {
    be_json_response(404, ['status'=>'error','message'=>$error->getMessage()]);
} catch (InvalidArgumentException|RuntimeException $error) {
    be_json_response(401, ['status'=>'error','message'=>'Organizer session is required.','error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status'=>'error','message'=>'Pilot status could not be loaded.','error_message'=>$error->getMessage()]);
}
