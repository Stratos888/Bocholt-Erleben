<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

be_startpartner_require_gate1_environment();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $pdo = be_db();
} catch (Throwable $error) {
    be_json_response(503, [
        'status' => 'error',
        'message' => 'Der Startpartner-Bereich ist technisch gerade nicht erreichbar.',
    ]);
}

try {
    $session = be_startpartner_gate4_portal_session($pdo);
} catch (InvalidArgumentException $error) {
    be_json_response(401, [
        'status' => 'error',
        'message' => 'Dein Veranstalterzugang ist nicht mehr gültig. Bitte fordere einen neuen Zugangslink an.',
    ]);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }
    $result = be_startpartner_gate4_create_portal_submission($pdo, $session, $input);
    be_json_response(
        !empty($result['idempotent_replay']) ? 200 : 201,
        ['status' => 'ok', 'data' => $result]
    );
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    $missing = str_starts_with($error->getMessage(), 'STARTPARTNER_');
    be_json_response(
        $missing ? 503 : 500,
        [
            'status' => 'error',
            'message' => $missing
                ? 'Der Startpartner-Bereich ist technisch noch nicht vollständig bereit.'
                : 'Der Pilotinhalt konnte gerade nicht gespeichert werden.',
        ]
    );
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Der Pilotinhalt konnte gerade nicht gespeichert werden.',
    ]);
}
