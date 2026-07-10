<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_domain.php';
require_once __DIR__ . '/_contracts.php';

function be_cc_json_encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function be_cc_create_content_change(array $item, array $updates, string $objectType = 'event', string $sourceSystem = 'events_sheet'): string
{
    if (!in_array($objectType, ['event','activity'], true)) throw new InvalidArgumentException('Ungültiger Inhaltstyp für den Änderungsverlauf.');
    be_cc_ensure_schema();
    $id = be_cc_uuid();
    $stmt = be_db()->prepare('INSERT INTO control_content_changes (id, object_type, object_id, object_title, source_system, before_json, updates_json, publication_state, public_url) VALUES (:id,:object_type,:object_id,:object_title,:source_system,:before_json,:updates_json,:state,:public_url)');
    $stmt->execute(['id' => $id, 'object_type' => $objectType, 'object_id' => (string)$item['id'], 'object_title' => (string)$item['title'], 'source_system' => $sourceSystem, 'before_json' => be_cc_json_encode($item), 'updates_json' => be_cc_json_encode($updates), 'state' => 'saved', 'public_url' => (string)($item['public_url'] ?? '')]);
    return $id;
}

function be_cc_update_content_change(string $id, string $state, array $extra = []): void
{
    $allowed = ['saved','deploy_started','deploy_failed','waiting','confirmed','verification_failed'];
    if (!in_array($state, $allowed, true)) throw new InvalidArgumentException('Ungültiger Veröffentlichungsstatus.');
    $fields = ['publication_state = :state', 'updated_at = NOW()'];
    $params = ['state' => $state, 'id' => $id];
    if (array_key_exists('written_fields', $extra)) { $fields[] = 'written_fields_json = :written_fields'; $params['written_fields'] = be_cc_json_encode((array)$extra['written_fields']); }
    if (array_key_exists('error', $extra)) { $fields[] = 'publication_error = :error'; $params['error'] = trim((string)$extra['error']); }
    if ($state === 'confirmed') $fields[] = 'confirmed_at = NOW()';
    $stmt = be_db()->prepare('UPDATE control_content_changes SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $stmt->execute($params);
}

function be_cc_get_content_change(string $id): array
{
    be_cc_ensure_schema();
    $stmt = be_db()->prepare('SELECT * FROM control_content_changes WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Änderung wurde nicht gefunden.');
    foreach (['before_json','updates_json','written_fields_json'] as $field) { $decoded = json_decode((string)($row[$field] ?? ''), true); $row[$field] = is_array($decoded) ? $decoded : []; }
    return $row;
}

function be_cc_list_content_changes(string $objectType, string $objectId, int $limit = 20): array
{
    be_cc_ensure_schema();
    $limit = max(1, min(50, $limit));
    $stmt = be_db()->prepare('SELECT * FROM control_content_changes WHERE object_type = :object_type AND object_id = :object_id ORDER BY created_at DESC LIMIT ' . $limit);
    $stmt->execute(['object_type' => $objectType, 'object_id' => $objectId]);
    return array_map(static function(array $row): array { foreach (['before_json','updates_json','written_fields_json'] as $field) { $decoded = json_decode((string)($row[$field] ?? ''), true); $row[$field] = is_array($decoded) ? $decoded : []; } return $row; }, $stmt->fetchAll());
}

function be_cc_upsert_publication_issue(array $change, string $reason, string $state = 'blocked'): void
{
    $objectType = in_array((string)($change['object_type'] ?? ''), ['event','activity'], true) ? (string)$change['object_type'] : 'event';
    $sourceReference = $objectType . ':' . (string)$change['object_id'];
    $id = be_cc_uuid();
    $stmt = be_db()->prepare("INSERT INTO control_cases (id, case_type, state, priority, title, reason, next_action, object_type, object_id, object_title, source_system, source_reference, source_payload_json, decision_ready) VALUES (:id,'task',:state,'high',:title,:reason,:next_action,:object_type,:object_id,:object_title,'publication_pipeline',:source_reference,:payload,0) ON DUPLICATE KEY UPDATE case_type='task', state=VALUES(state), priority='high', title=VALUES(title), reason=VALUES(reason), next_action=VALUES(next_action), object_type=VALUES(object_type), object_id=VALUES(object_id), object_title=VALUES(object_title), source_payload_json=VALUES(source_payload_json), completed_at=NULL, updated_at=NOW()");
    $stmt->execute(['id' => $id, 'state' => $state, 'title' => 'Veröffentlichung prüfen: ' . (string)$change['object_title'], 'reason' => $reason, 'next_action' => 'Deployment beziehungsweise öffentliche Datenwirkung erneut prüfen und abschließen.', 'object_type' => $objectType, 'object_id' => (string)$change['object_id'], 'object_title' => (string)$change['object_title'], 'source_reference' => $sourceReference, 'payload' => be_cc_json_encode(['change_id' => $change['id'], 'publication_state' => $change['publication_state']])]);
}

function be_cc_complete_publication_issue(string $objectType, string $objectId): void
{
    $stmt = be_db()->prepare("UPDATE control_cases SET state='done', completed_at=NOW(), updated_at=NOW() WHERE source_system='publication_pipeline' AND source_reference=:source_reference AND state NOT IN ('done','rejected','parked')");
    $stmt->execute(['source_reference' => $objectType . ':' . $objectId]);
}

function be_cc_public_feed_url(string $objectType, string $sourceSystem = ''): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') throw new RuntimeException('Öffentlicher Host ist nicht bekannt.');
    if ($sourceSystem === 'submission_db') $path = '/api/events/public.php';
    else $path = $objectType === 'activity' ? '/data/offers.json' : '/data/events.json';
    return 'https://' . $host . $path . '?verify=' . rawurlencode((string)microtime(true));
}

function be_cc_verify_public_change(array $change): array
{
    $objectType = (string)($change['object_type'] ?? 'event');
    $sourceSystem = (string)($change['source_system'] ?? '');
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15, 'ignore_errors' => true, 'header' => "Cache-Control: no-cache\r\n"]]);
    $raw = file_get_contents(be_cc_public_feed_url($objectType, $sourceSystem), false, $context);
    if (!is_string($raw) || trim($raw) === '') return ['verified' => false, 'reason' => 'Der öffentliche Datenbestand konnte nicht geladen werden.'];
    $payload = json_decode($raw, true);
    if (!is_array($payload)) return ['verified' => false, 'reason' => 'Der öffentliche Datenbestand enthält kein gültiges JSON.'];

    if ($sourceSystem === 'submission_db') {
        $items = is_array($payload['data']['events'] ?? null) ? $payload['data']['events'] : [];
    } elseif ($objectType === 'activity') {
        $items = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
    } else {
        $items = is_array($payload['events'] ?? null) ? $payload['events'] : (array_is_list($payload) ? $payload : []);
    }

    $item = null;
    foreach ($items as $candidate) if (is_array($candidate) && trim((string)($candidate['id'] ?? '')) === (string)$change['object_id']) { $item = $candidate; break; }
    if (!is_array($item)) return ['verified' => false, 'reason' => 'Der Inhalt ist im öffentlichen Datenbestand noch nicht vorhanden.'];
    if ($objectType === 'activity') {
        foreach ((array)$change['updates_json'] as $field => $expected) {
            if (!array_key_exists($field, $item) || trim((string)$item[$field]) !== trim((string)$expected)) return ['verified' => false, 'reason' => 'Öffentlicher Aktivitätswert noch nicht aktuell: ' . $field . '.'];
        }
        return ['verified' => true, 'reason' => 'Öffentliche Aktivitätsdaten entsprechen der gespeicherten Änderung.'];
    }
    return be_cc_compare_public_event_change($item, (array)$change['updates_json'], (array)$change['written_fields_json']);
}
