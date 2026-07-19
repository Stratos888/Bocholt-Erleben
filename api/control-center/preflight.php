<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';
require_once __DIR__ . '/_runtime_resource_contract.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status'=>'error', 'message'=>'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');

    $mode = strtolower(trim((string)($input['mode'] ?? 'case')));
    if ($mode === 'runtime') {
        if (trim((string)($input['case_id'] ?? '')) !== '' || trim((string)($input['action'] ?? '')) !== '') {
            throw new InvalidArgumentException('Der Runtime-Ressourcen-Preflight akzeptiert keinen Fachfall und keine Aktion.');
        }
        $plan = be_cc_runtime_resource_contract();
        if (empty($plan['allowed'])) {
            be_json_response(409, [
                'status'=>'blocked',
                'message'=>'Der Runtime-Ressourcen-Preflight hat die Umgebung fail-closed blockiert.',
                'data'=>$plan,
            ]);
        }
        be_json_response(200, ['status'=>'ok', 'data'=>$plan]);
    }
    if ($mode !== 'case') throw new InvalidArgumentException('Unbekannter Preflight-Modus.');

    $caseId = trim((string)($input['case_id'] ?? ''));
    $action = strtolower(trim((string)($input['action'] ?? '')));
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    if ($caseId === '' || $action === '') throw new InvalidArgumentException('Case and action are required.');

    be_cc_require_schema();
    $lookup = be_db()->prepare('SELECT * FROM control_cases WHERE id = :id');
    $lookup->execute(['id'=>$caseId]);
    $case = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($case)) throw new RuntimeException('Case not found.');

    $plan = be_cc_preflight_plan($case, $action, $payload);
    if (empty($plan['allowed'])) {
        be_json_response(409, [
            'status'=>'blocked',
            'message'=>'Der Runtime-Preflight hat die Aktion fail-closed blockiert.',
            'data'=>$plan,
        ]);
    }

    be_json_response(200, [
        'status'=>'ok',
        'data'=>$plan,
    ]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(409, ['status'=>'error', 'message'=>$error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status'=>'error',
        'message'=>'Der Runtime-Preflight konnte nicht erstellt werden.',
        'error_message'=>$error->getMessage(),
    ]);
}
