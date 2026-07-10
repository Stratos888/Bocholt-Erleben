<?php
declare(strict_types=1);

require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_writeback.php';

be_require_review_access();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    if ($method === 'GET') {
        $type = trim((string)($_GET['type'] ?? 'events'));
        if (!in_array($type, ['events', 'activities'], true)) throw new InvalidArgumentException('Ungültiger Inhaltstyp.');
        $id = trim((string)($_GET['id'] ?? ''));
        $items = $type === 'events' ? be_cc_event_items($id !== '') : be_cc_activity_items($id !== '');
        if ($id !== '') {
            foreach ($items as $item) {
                if ((string)$item['id'] === $id) {
                    be_json_response(200, ['status' => 'ok', 'data' => ['item' => $item]]);
                }
            }
            be_json_response(404, ['status' => 'error', 'message' => 'Inhalt wurde nicht gefunden.']);
        }
        be_json_response(200, ['status' => 'ok', 'data' => ['items' => $items, 'total' => count($items)]]);
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) throw new InvalidArgumentException('Ungültige Anfrage.');
    $type = trim((string)($payload['type'] ?? ''));
    $id = trim((string)($payload['id'] ?? ''));
    $updates = is_array($payload['updates'] ?? null) ? $payload['updates'] : [];
    if ($type !== 'events' || $id === '') {
        throw new InvalidArgumentException('Nur Veranstaltungen können aktuell dauerhaft live bearbeitet werden.');
    }
    if (!$updates) throw new InvalidArgumentException('Keine Änderungen übergeben.');

    be_cc_update_event_fields($id, $updates);

    $deployStarted = false;
    $deployMessage = 'Änderung wurde in der führenden Quelle gespeichert.';
    if (!empty($payload['deploy'])) {
        $token = be_cc_inbox_token();
        $result = be_cc_jsonp_request(['action' => 'deploy', 'token' => $token]);
        if (empty($result['ok'])) {
            throw new RuntimeException('Änderung gespeichert, Deployment aber fehlgeschlagen: ' . trim((string)($result['error'] ?? $result['detail'] ?? 'deploy_failed')));
        }
        $deployStarted = true;
        $deployMessage = 'Änderung gespeichert und Deployment gestartet.';
    }

    be_json_response(200, ['status' => 'ok', 'data' => [
        'saved' => true,
        'deploy_started' => $deployStarted,
        'message' => $deployMessage,
    ]]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(409, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Inhalt konnte nicht verarbeitet werden.', 'error_message' => $error->getMessage()]);
}
