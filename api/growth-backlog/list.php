<?php
declare(strict_types=1);
/* === BEGIN FILE: api/growth-backlog/list.php | Zweck: liefert die vollstaendige kanonische Growth-Roadmap fuer die private Steuerzentrale; Umfang: alle Punkte mit genau den Status offen und abgeschlossen === */

require dirname(__DIR__) . '/_bootstrap.php';
require dirname(__DIR__) . '/growth-backlog-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

be_require_review_access();

try {
    $snapshot = gbl_read_snapshot();
    be_json_response(200, ['status' => 'ok', 'data' => $snapshot]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Growth-Backlog konnte nicht geladen werden.',
        'error_message' => $error->getMessage(),
    ]);
}
