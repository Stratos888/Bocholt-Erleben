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
if ($id === '' || $status === '') {
    be_json_response(422, ['status' => 'error', 'message' => 'Ungültige Backlog-Aktion.']);
}

try {
    [$header, $index, $rows] = gbl_read_table();
    foreach ($rows as $row) {
        if ((string)($row['id'] ?? '') !== $id) continue;
        $now = gbl_now_iso();
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
