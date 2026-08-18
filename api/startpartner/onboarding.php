<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Diese Anfrageart wird nicht unterstützt.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Die übermittelten Angaben konnten nicht gelesen werden.');
    $candidateId = trim((string)($input['candidate_id'] ?? ''));
    if ($candidateId === '') throw new InvalidArgumentException('Der Startpartner-Fall fehlt.');
    $action = trim((string)($input['action'] ?? 'update_item'));
    $pdo = be_db();
    $result = match ($action) {
        'update_item' => be_startpartner_gate4_update_onboarding($pdo, $candidateId, $input),
        'mark_content_ready' => be_startpartner_gate4_mark_content_ready($pdo, $candidateId, $input),
        'set_measurement' => be_startpartner_gate4_set_measurement($pdo, $candidateId, $input),
        'set_distribution' => be_startpartner_gate4_set_distribution($pdo, $candidateId, $input),
        default => throw new InvalidArgumentException('Diese Aktion gehört nicht zur Piloteinrichtung.'),
    };
    be_json_response(200, ['status' => 'ok', 'data' => $result]);
} catch (BeStartpartnerConflictException $error) {
    be_json_response(409, ['status'=>'error','code'=>'STARTPARTNER_CONFLICT','message'=>'Zwischenzeitlich geändert.','current'=>$error->currentState,'error_message'=>$error->getMessage()]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status'=>'error','message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    $missing = str_starts_with($error->getMessage(), 'STARTPARTNER_');
    be_json_response($missing ? 503 : 404, ['status'=>'error','message'=>$missing?'Die Piloteinrichtung ist technisch noch nicht verfügbar.':$error->getMessage(),'error_message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status'=>'error','message'=>'Die Änderung an der Piloteinrichtung konnte gerade nicht gespeichert werden.','error_message'=>$error->getMessage()]);
}
