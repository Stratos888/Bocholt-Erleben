<?php
declare(strict_types=1);
/* === BEGIN FILE: api/growth-backlog/list.php | Zweck: liefert offene Growth-/Acquisition-Backlog-Punkte fuer die private Inbox; Umfang: geschuetzter Read-Endpunkt mit einfacher Dedupe-Sicht === */

require dirname(__DIR__) . '/_bootstrap.php';
require dirname(__DIR__) . '/growth-backlog-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

be_require_review_access();

try {
    [, , $rows] = gbl_read_table();
    $open = [];
    $seen = [];
    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? 'open')));
        if (!gbl_status_is_open($status)) continue;
        $key = (string)($row['cluster_key'] ?? '');
        if ($key !== '' && isset($seen[$key])) continue;
        if ($key !== '') $seen[$key] = true;
        $open[] = gbl_public_item($row);
    }

    usort($open, static function (array $a, array $b): int {
        $rank = ['hoch' => 0, 'mittel' => 1, 'niedrig' => 2];
        $pa = $rank[mb_strtolower((string)($a['priority'] ?? ''), 'UTF-8')] ?? 3;
        $pb = $rank[mb_strtolower((string)($b['priority'] ?? ''), 'UTF-8')] ?? 3;
        return ($pa <=> $pb) ?: strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    be_json_response(200, ['status' => 'ok', 'data' => ['items' => $open, 'total' => count($open)]]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Growth-Backlog konnte nicht geladen werden.', 'error_message' => $error->getMessage()]);
}
