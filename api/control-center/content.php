<?php
declare(strict_types=1);

require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_writeback.php';
require_once __DIR__ . '/_content_history.php';
require_once __DIR__ . '/_github_repo.php';
require_once __DIR__ . '/_submission_content.php';

be_require_review_access();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

function be_cc_sheet_event_items(bool $full = false): array
{
    return array_map(static function(array $item): array {
        $item['source_system'] = 'events_sheet';
        $item['source_label'] = 'Redaktionelles Event';
        return $item;
    }, be_cc_event_items($full));
}

function be_cc_all_event_items(bool $full = false): array
{
    return array_merge(be_cc_sheet_event_items($full), be_cc_submission_event_items($full));
}

function be_cc_event_items_by_source(string $source, bool $full = false): array
{
    return match ($source) {
        'sheet' => be_cc_sheet_event_items($full),
        'submissions' => be_cc_submission_event_items($full),
        'all' => be_cc_all_event_items($full),
        default => throw new InvalidArgumentException('Ungültige Eventquelle.'),
    };
}

try {
    if ($method === 'GET') {
        $type = trim((string)($_GET['type'] ?? 'events'));
        if (!in_array($type, ['events', 'activities'], true)) throw new InvalidArgumentException('Ungültiger Inhaltstyp.');
        $id = trim((string)($_GET['id'] ?? ''));
        $source = trim((string)($_GET['source'] ?? 'all'));

        if ($type === 'events' && $id !== '') {
            $source = str_starts_with($id, 'submission-') ? 'submissions' : 'sheet';
        }

        $items = $type === 'events'
            ? be_cc_event_items_by_source($source, $id !== '')
            : be_cc_activity_items($id !== '');

        if ($id !== '') {
            foreach ($items as $item) {
                if ((string)$item['id'] === $id) {
                    $history = be_cc_list_content_changes($type === 'events' ? 'event' : 'activity', $id);
                    be_json_response(200, ['status' => 'ok', 'data' => ['item' => $item, 'history' => $history]]);
                }
            }
            be_json_response(404, ['status' => 'error', 'message' => 'Inhalt wurde nicht gefunden.']);
        }

        be_json_response(200, ['status' => 'ok', 'data' => [
            'items' => $items,
            'total' => count($items),
            'source' => $type === 'events' ? $source : 'repo',
        ]]);
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) throw new InvalidArgumentException('Ungültige Anfrage.');
    $type = trim((string)($payload['type'] ?? ''));
    $id = trim((string)($payload['id'] ?? ''));
    $updates = is_array($payload['updates'] ?? null) ? $payload['updates'] : [];
    if (!in_array($type, ['events','activities'], true) || $id === '') throw new InvalidArgumentException('Inhaltstyp oder ID fehlt.');
    if (!$updates) throw new InvalidArgumentException('Keine Änderungen übergeben.');

    if ($type === 'activities') {
        if (!be_cc_activity_writeback_available()) throw new RuntimeException(be_cc_activity_writeback_status()['message']);
        $current = null;
        foreach (be_cc_activity_items(true) as $item) if ((string)$item['id'] === $id) { $current = $item; break; }
        if (!is_array($current)) throw new RuntimeException('Aktivität wurde nicht gefunden.');
        $normalized = be_cc_normalize_activity_updates($updates);
        $changeId = be_cc_create_content_change($current, $normalized, 'activity', 'activities_repo');
        try {
            $result = be_cc_update_activity_in_repo($id, $normalized);
            be_cc_update_content_change($changeId, 'deploy_started', ['written_fields' => array_keys($normalized)]);
            $change = be_cc_get_content_change($changeId);
            be_cc_upsert_publication_issue($change, 'Die Aktivität wurde versioniert im Repo gespeichert. Die öffentliche Wirkung ist noch nicht bestätigt.', 'waiting');
        } catch (Throwable $error) {
            be_cc_update_content_change($changeId, 'deploy_failed', ['error' => $error->getMessage()]);
            $change = be_cc_get_content_change($changeId);
            be_cc_upsert_publication_issue($change, 'Die Aktivitätsänderung konnte nicht sicher veröffentlicht werden: ' . $error->getMessage(), 'blocked');
            throw $error;
        }
        be_json_response(200, ['status' => 'ok', 'data' => ['saved' => true, 'deploy_started' => true, 'publication_state' => 'waiting', 'change_id' => $changeId, 'commit_sha' => $result['commit_sha'], 'message' => 'Aktivität versioniert gespeichert. Die öffentliche Aktualisierung wird geprüft.']]);
    }

    $current = null;
    $eventSource = str_starts_with($id, 'submission-') ? 'submissions' : 'sheet';
    foreach (be_cc_event_items_by_source($eventSource, true) as $item) if ((string)$item['id'] === $id) { $current = $item; break; }
    if (!is_array($current)) throw new RuntimeException('Veranstaltung wurde nicht gefunden.');

    if (($current['source_system'] ?? '') === 'submission_db') {
        $submissionId = (int)($current['submission_id'] ?? 0);
        be_cc_normalize_submission_event_updates($updates);
        $changeId = be_cc_create_content_change($current, $updates, 'event', 'submission_db');
        try {
            $writtenColumns = be_cc_update_submission_event($submissionId, $updates);
            $writtenPublicFields = [];
            $reverse = ['title'=>'title','start_date'=>'date','time_text'=>'time','location_name'=>'location','location_address'=>'address','description_text'=>'description','event_url'=>'source_url','ticket_url'=>'ticket_url'];
            foreach ($writtenColumns as $column) if (isset($reverse[$column])) $writtenPublicFields[] = $reverse[$column];
            be_cc_update_content_change($changeId, 'deploy_started', ['written_fields' => $writtenPublicFields]);
            $change = be_cc_get_content_change($changeId);
            be_cc_upsert_publication_issue($change, 'Das Anbieter-Event wurde in der führenden Datenbank gespeichert. Die öffentliche API-Wirkung wird bestätigt.', 'waiting');
        } catch (Throwable $error) {
            be_cc_update_content_change($changeId, 'verification_failed', ['error' => $error->getMessage()]);
            throw $error;
        }
        be_json_response(200, ['status' => 'ok', 'data' => ['saved' => true, 'deploy_started' => true, 'publication_state' => 'waiting', 'change_id' => $changeId, 'message' => 'Anbieter-Event gespeichert. Die öffentliche API-Wirkung wird geprüft.']]);
    }

    $changeId = be_cc_create_content_change($current, $updates, 'event', 'events_sheet');
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
            if (empty($result['ok'])) throw new RuntimeException(trim((string)($result['error'] ?? $result['detail'] ?? 'deploy_failed')));
            $deployStarted = true;
            be_cc_update_content_change($changeId, 'deploy_started');
            $change = be_cc_get_content_change($changeId);
            be_cc_upsert_publication_issue($change, 'Die Änderung ist gespeichert und das Deployment wurde gestartet. Die öffentliche Wirkung ist noch nicht bestätigt.', 'waiting');
        } catch (Throwable $error) {
            be_cc_update_content_change($changeId, 'deploy_failed', ['error' => $error->getMessage()]);
            $change = be_cc_get_content_change($changeId);
            be_cc_upsert_publication_issue($change, 'Die Änderung wurde gespeichert, aber das Deployment konnte nicht gestartet werden: ' . $error->getMessage(), 'blocked');
            be_json_response(409, ['status' => 'error', 'message' => 'Änderung gespeichert, Aktualisierung aber fehlgeschlagen. Unter Arbeit wurde ein blockierter Vorgang angelegt.', 'data' => ['change_id' => $changeId, 'publication_state' => 'deploy_failed']]);
        }
    }

    $publication = be_cc_publication_result(true, $deployStarted, false);
    be_json_response(200, ['status' => 'ok', 'data' => ['saved' => true, 'deploy_started' => $deployStarted, 'publication_state' => $publication['state'], 'change_id' => $changeId, 'message' => $publication['message']]]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(409, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Inhalt konnte nicht verarbeitet werden.', 'error_message' => $error->getMessage()]);
}
