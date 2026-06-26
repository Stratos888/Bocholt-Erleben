<?php
declare(strict_types=1);
/* === BEGIN FILE: api/growth-backlog/update.php | Zweck: schliesst oder verwirft Growth-Backlog-Punkte aus der privaten Inbox; Umfang: geschuetzter Status-Writeback mit Historienerhalt gegen Wiederauftauchen === */

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
$status = match ($action) {
    'complete' => 'completed',
    'reject' => 'rejected',
    default => '',
};
$allowedPriorities = ['hoch', 'mittel', 'niedrig'];
$priority = mb_strtolower(trim((string)($payload['priority'] ?? '')), 'UTF-8');
$editAction = $action === 'edit';
if ($id === '' || ($status === '' && !$editAction)) {
    be_json_response(422, ['status' => 'error', 'message' => 'Ungültige Backlog-Aktion.']);
}
if ($priority !== '' && !in_array($priority, $allowedPriorities, true)) {
    be_json_response(422, ['status' => 'error', 'message' => 'Ungültige Priorität.']);
}

try {
    [$header, $index, $rows] = gbl_read_table();
    foreach ($rows as $row) {
        if ((string)($row['id'] ?? '') !== $id) continue;
        $now = gbl_now_iso();
        if ($editAction) {
            $updates = ['updated_at' => $now];
            if (array_key_exists('title', $payload)) {
                $updates['title'] = trim((string)$payload['title']);
            }
            if (array_key_exists('description', $payload)) {
                $description = trim((string)$payload['description']);
                $updates['short_reason'] = mb_substr($description, 0, 220, 'UTF-8');
                $updates['why_relevant'] = $description;
                $updates['recommended_action'] = '';
                $updates['expected_benefit'] = '';
                $updates['acquisition_note'] = '';
            }
            if ($priority !== '') $updates['priority'] = $priority;
            gbl_update_cells($header, $index, (int)$row['_sheet_row'], $updates);
            be_json_response(200, ['status' => 'ok', 'data' => ['item' => gbl_public_item(array_merge($row, $updates))]]);
        }

        gbl_update_cells($header, $index, (int)$row['_sheet_row'], [
            'status' => $status,
            'updated_at' => $now,
            'closed_at' => $now,
            'decision_note' => trim((string)($payload['decision_note'] ?? '')),
        ]);
        be_json_response(200, ['status' => 'ok']);
    }
    be_json_response(404, ['status' => 'error', 'message' => 'Backlog-Punkt nicht gefunden.']);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Backlog-Punkt konnte nicht aktualisiert werden.', 'error_message' => $error->getMessage()]);
}
