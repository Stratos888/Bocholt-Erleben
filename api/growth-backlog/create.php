<?php
declare(strict_types=1);
/* === BEGIN FILE: api/growth-backlog/create.php | Zweck: legt manuelle Backlogpunkte strukturiert und dedupliziert im Growth_Backlog-Sheet an; Umfang: geschuetzter Create-Endpunkt ohne automatische Umsetzung === */

require dirname(__DIR__) . '/_bootstrap.php';
require dirname(__DIR__) . '/growth-backlog-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

be_require_review_access();
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];

$title = trim((string)($payload['title'] ?? ''));
$type = trim((string)($payload['type'] ?? 'Sonstiges')) ?: 'Sonstiges';
$priority = mb_strtolower(trim((string)($payload['priority'] ?? 'mittel')), 'UTF-8');
$whyRelevant = trim((string)($payload['why_relevant'] ?? $payload['note'] ?? ''));
$recommendedAction = trim((string)($payload['recommended_action'] ?? ''));
$expectedBenefit = trim((string)($payload['expected_benefit'] ?? ''));
$acquisitionNote = trim((string)($payload['acquisition_note'] ?? ''));
$shortReason = trim((string)($payload['short_reason'] ?? ''));
if ($shortReason === '') {
    $shortReason = $recommendedAction !== '' ? $recommendedAction : ($whyRelevant !== '' ? $whyRelevant : 'Manuell notiert');
}
$shortReason = mb_substr($shortReason, 0, 220, 'UTF-8');

if (!in_array($priority, ['hoch', 'mittel', 'niedrig'], true)) $priority = 'mittel';
if ($title === '') {
    be_json_response(422, ['status' => 'error', 'message' => 'Titel fehlt.']);
}

try {
    [$header, , $rows] = gbl_read_table();
    $expected = gbl_required_header();
    foreach ($expected as $name) {
        if (!in_array($name, $header, true)) $header[] = $name;
    }

    $clusterKey = gbl_cluster_key($title, $type);
    foreach ($rows as $row) {
        if ((string)($row['cluster_key'] ?? '') === $clusterKey && gbl_status_is_open(strtolower((string)($row['status'] ?? 'open')))) {
            be_json_response(200, ['status' => 'ok', 'data' => ['deduped' => true, 'item' => gbl_public_item($row)]]);
        }
    }

    $now = gbl_now_iso();
    $row = [
        'id' => 'manual-' . gmdate('YmdHis') . '-' . substr(hash('sha256', $clusterKey), 0, 8),
        'cluster_key' => $clusterKey,
        'status' => 'open',
        'priority' => $priority,
        'type' => $type,
        'title' => $title,
        'short_reason' => $shortReason,
        'why_relevant' => $whyRelevant,
        'recommended_action' => $recommendedAction,
        'expected_benefit' => $expectedBenefit,
        'acquisition_note' => $acquisitionNote,
        'source' => 'manual',
        'signals_json' => '{}',
        'created_at' => $now,
        'updated_at' => $now,
        'closed_at' => '',
        'decision_note' => '',
    ];
    gbl_append_row($header, $row);
    be_json_response(200, ['status' => 'ok', 'data' => ['deduped' => false, 'item' => gbl_public_item($row)]]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Backlog-Punkt konnte nicht gespeichert werden.', 'error_message' => $error->getMessage()]);
}
