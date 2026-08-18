<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';
// Gate-4 detail includes the authoritative Gate-3 readback through be_startpartner_gate3_state.

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']);
}

try {
    $pdo = be_db();
    $candidateId = trim((string)($_GET['candidate_id'] ?? ''));
    $pilotId = trim((string)($_GET['id'] ?? ''));
    if ($candidateId === '' && $pilotId === '') throw new InvalidArgumentException('candidate_id or pilot id is required.');
    if ($candidateId === '') {
        $stmt = $pdo->prepare('SELECT candidate_id FROM startpartner_pilots WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$pilotId]);
        $candidateId = (string)($stmt->fetchColumn() ?: '');
        if ($candidateId === '') throw new RuntimeException('Startpartner pilot not found.');
    }
    $data = be_startpartner_gate4_candidate_detail($pdo, $candidateId, true);
    if (($data['gate4']['pilot'] ?? null) === null) throw new RuntimeException('Startpartner pilot not found.');
    be_json_response(200, ['status'=>'ok','data'=>$data]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    $missing = str_starts_with($error->getMessage(), 'STARTPARTNER_');
    be_json_response($missing ? 503 : 404, ['status'=>'error','message'=>$missing?'Startpartner schema is not ready.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status'=>'error','message'=>'Startpartner pilot could not be loaded.','error_message'=>$error->getMessage()]);
}
