<?php
declare(strict_types=1);
/* === BEGIN FILE: api/growth-backlog/update.php | Zweck: bearbeitet kanonische Growth-Backlog-Punkte und wechselt ausschliesslich zwischen offen und abgeschlossen; Umfang: geschuetzter Writeback mit Konfliktschutz und Historienerhalt === */

require dirname(__DIR__) . '/_bootstrap.php';
require dirname(__DIR__) . '/growth-backlog-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

be_require_review_access();
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];
$id = trim((string)($payload['id'] ?? ''));
$action = trim((string)($payload['action'] ?? ''));
$allowedActions = ['edit', 'complete', 'reopen'];
$allowedPriorities = ['hoch', 'mittel', 'niedrig'];
$priority = mb_strtolower(trim((string)($payload['priority'] ?? '')), 'UTF-8');
$expectedUpdatedAt = trim((string)($payload['expected_updated_at'] ?? ''));

if ($id === '' || !in_array($action, $allowedActions, true)) {
    be_json_response(422, ['status' => 'error', 'message' => 'Ungültige Backlog-Aktion.']);
}
if ($priority !== '' && !in_array($priority, $allowedPriorities, true)) {
    be_json_response(422, ['status' => 'error', 'message' => 'Ungültige Priorität.']);
}

try {
    [$header, $index, $rows] = gbl_read_table();
    foreach ($rows as $row) {
        if ((string)($row['id'] ?? '') !== $id) continue;

        if ($expectedUpdatedAt !== '' && (string)($row['updated_at'] ?? '') !== $expectedUpdatedAt) {
            be_json_response(409, [
                'status' => 'error',
                'message' => 'Der Backlogpunkt wurde zwischenzeitlich geändert. Bitte neu laden und erneut bearbeiten.',
                'data' => ['current' => gbl_public_item($row)],
            ]);
        }

        $now = gbl_now_iso();
        if ($action === 'edit') {
            $updates = ['updated_at' => $now];
            $title = array_key_exists('title', $payload) ? trim((string)$payload['title']) : (string)($row['title'] ?? '');
            $type = array_key_exists('type', $payload) ? trim((string)$payload['type']) : (string)($row['type'] ?? 'Sonstiges');
            if ($title === '' || $type === '') {
                be_json_response(422, ['status' => 'error', 'message' => 'Titel und Themenbereich sind erforderlich.']);
            }
            if (array_key_exists('title', $payload)) $updates['title'] = $title;
            if (array_key_exists('type', $payload)) $updates['type'] = $type;
            if ($priority !== '') $updates['priority'] = $priority;
            foreach (['why_relevant', 'recommended_action', 'expected_benefit', 'acquisition_note'] as $field) {
                if (array_key_exists($field, $payload)) $updates[$field] = trim((string)$payload[$field]);
            }
            if (array_key_exists('short_reason', $payload)) {
                $updates['short_reason'] = mb_substr(trim((string)$payload['short_reason']), 0, 220, 'UTF-8');
            } elseif (array_key_exists('recommended_action', $updates) || array_key_exists('why_relevant', $updates)) {
                $preview = trim((string)($updates['recommended_action'] ?? ''));
                if ($preview === '') $preview = trim((string)($updates['why_relevant'] ?? ''));
                $updates['short_reason'] = mb_substr($preview, 0, 220, 'UTF-8');
            }
            if ($title !== (string)($row['title'] ?? '') || $type !== (string)($row['type'] ?? '')) {
                $updates['cluster_key'] = gbl_cluster_key($title, $type);
            }
            gbl_update_cells($header, $index, (int)$row['_sheet_row'], $updates);
            be_json_response(200, ['status' => 'ok', 'data' => ['item' => gbl_public_item(array_merge($row, $updates))]]);
        }

        $updates = $action === 'complete'
            ? [
                'status' => 'completed',
                'updated_at' => $now,
                'closed_at' => $now,
                'decision_note' => trim((string)($payload['decision_note'] ?? '')),
            ]
            : [
                'status' => 'open',
                'updated_at' => $now,
                'closed_at' => '',
                'decision_note' => '',
            ];
        gbl_update_cells($header, $index, (int)$row['_sheet_row'], $updates);
        be_json_response(200, ['status' => 'ok', 'data' => ['item' => gbl_public_item(array_merge($row, $updates))]]);
    }
    be_json_response(404, ['status' => 'error', 'message' => 'Backlog-Punkt nicht gefunden.']);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Backlog-Punkt konnte nicht aktualisiert werden.', 'error_message' => $error->getMessage()]);
}
