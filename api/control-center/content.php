<?php
declare(strict_types=1);

require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_writeback.php';
require_once __DIR__ . '/_content_history.php';

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
                    $history = be_cc_list_content_changes($type === 'events' ? 'event' : 'activity', $id);
                    be_json_response(200, ['status' => 'ok', 'data' => ['item' => $item, 'history' => $history]]);
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

    $currentItems = be_cc_event_items(true);
    $current = null;
    foreach ($currentItems as $item) {
        if ((string)$item['id'] === $id) { $current = $item; break; }
    }
    if (!is_array($current)) throw new RuntimeException('Veranstaltung wurde nicht gefunden.');

    $changeId = be_cc_create_content_change($current, $updates);
    try {
        $writtenFields = be_cc_update_event_fields($id, $updates);
        be_cc_update_content_change($changeId, 'saved', ['written_fields' => $writtenFields]);
    } catch (Throwable $error) {
        be_cc_update_content_change($changeId, 'verification_failed', ['error' => $error->getMessage()]);
        throw $error;
    }

    $deployStarted = false;
    if (!empty($payload['deploy'])) {
        try {
            $token = be_cc_inbox_token();
            $result = be_cc_jsonp_request(['action' => 'deploy', 'token' => $token]);
            if (empty($result['ok'])) {
                throw new RuntimeException(trim((string)($result['error'] ?? $result['detail'] ?? 'deploy_failed')));
            }
            $deployStarted = true;
            be_cc_update_content_change($changeId, 'deploy_started');
        } catch (Throwable $error) {
            be_cc_update_content_change($changeId, 'deploy_failed', ['error' => $error->getMessage()]);
            $change = be_cc_get_content_change($changeId);
            be_cc_upsert_publication_issue($change, 'Die Änderung wurde gespeichert, aber das Deployment konnte nicht gestartet werden: ' . $error->getMessage(), 'blocked');
            be_json_response(409, ['status' => 'error', 'message' => 'Änderung gespeichert, Aktualisierung aber fehlgeschlagen. Unter Arbeit wurde ein blockierter Vorgang angelegt.', 'data' => ['change_id' => $changeId, 'publication_state' => 'deploy_failed']]);
        }
    }

    $publication = be_cc_publication_result(true, $deployStarted, false);
    be_json_response(200, ['status' => 'ok', 'data' => [
        'saved' => true,
        'deploy_started' => $deployStarted,
        'publication_state' => $publication['state'],
        'change_id' => $changeId,
        'message' => $publication['message'],
    ]]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(409, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Inhalt konnte nicht verarbeitet werden.', 'error_message' => $error->getMessage()]);
}
