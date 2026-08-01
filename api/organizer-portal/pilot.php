<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__) . '/startpartner/_gate4_domain.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $pdo = be_db();
    $session = be_startpartner_gate4_portal_session($pdo);
    $candidate = be_startpartner_gate4_portal_candidate($pdo, (int)$session['organizer_id']);
    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'organizer_id' => (int)$session['organizer_id'],
            'gate4' => be_startpartner_gate4_portal_projection($candidate),
        ],
    ]);
} catch (InvalidArgumentException $error) {
    be_json_response(401, [
        'status' => 'error',
        'message' => 'Für den Startpartner-Pilot ist ein gültiger Veranstalterzugang erforderlich.',
    ]);
} catch (DomainException $error) {
    be_json_response(404, [
        'status' => 'error',
        'message' => 'Für diesen Veranstalter ist aktuell kein eindeutiger Startpartner-Pilot verfügbar.',
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Der Pilotstatus konnte gerade nicht geladen werden.',
    ]);
}
