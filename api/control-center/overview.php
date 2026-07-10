<?php
declare(strict_types=1);

require __DIR__ . '/_schema.php';
require __DIR__ . '/_submission_source.php';
require __DIR__ . '/_sheet_inbox_source.php';
require __DIR__ . '/_content_ops.php';

be_require_review_access();

function be_cc_sync_process_health_cases(array $processHealth): void
{
    $pdo = be_db();
    foreach ((array)($processHealth['items'] ?? []) as $item) {
        $key = trim((string)($item['key'] ?? ''));
        if ($key === '') continue;
        $reference = 'process:' . $key;
        $status = (string)($item['status'] ?? 'unknown');
        if ($status === 'attention') {
            be_cc_upsert_source_case([
                'type' => 'task',
                'state' => 'blocked',
                'priority' => 'high',
                'title' => 'Prozess prüfen: ' . (string)($item['label'] ?? $key),
                'reason' => (string)($item['message'] ?? 'Der letzte Prozesslauf benötigt Aufmerksamkeit.'),
                'next_action' => 'Letzten Lauf öffnen, Ursache beheben und Prozesswirkung erneut bestätigen.',
                'object_type' => 'automation_process',
                'object_id' => $key,
                'object_title' => (string)($item['label'] ?? $key),
                'source_system' => 'process_health',
                'source_reference' => $reference,
                'source_payload' => $item,
                'decision_ready' => false,
            ]);
            continue;
        }
        if ($status === 'ok') {
            $stmt = $pdo->prepare("UPDATE control_cases SET state='done', completed_at=NOW(), updated_at=NOW() WHERE source_system='process_health' AND source_reference=:reference AND state NOT IN ('done','rejected','parked')");
            $stmt->execute(['reference' => $reference]);
        }
    }
}

try {
    be_cc_ensure_schema();

    // Das führende Sheet wird zuerst gelesen. Der JSON-Feed bleibt nur als
    // kompatibler, deduplizierter Fallback und erzeugt wegen identischer
    // Quellidentität keine Doppelvorgänge.
    $sync = [
        'sheet_inbox' => be_cc_sync_sheet_inbox(),
        'inbox_feed_fallback' => be_cc_sync_inbox_feed(),
        'submissions' => be_cc_sync_submissions(),
        'content_audit' => be_cc_sync_content_audit(),
        'growth_backlog' => be_cc_sync_growth_backlog(),
        'repo_workpacks' => be_cc_sync_repo_workpacks(),
    ];

    $contentOps = be_cc_content_ops_status(be_db());
    $processHealth = be_cc_process_health($contentOps);
    be_cc_sync_process_health_cases($processHealth);

    $cases = be_cc_list_cases(['active' => '1']);
    $groups = ['now' => [], 'next' => [], 'inbox' => [], 'information' => []];
    foreach ($cases as $case) {
        $bucket = $case['bucket'] ?? 'information';
        if (!isset($groups[$bucket])) $bucket = 'information';
        $groups[$bucket][] = $case;
    }

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'groups' => $groups,
            'counts' => array_map('count', $groups),
            'sync' => $sync,
            'system' => [
                'status' => $processHealth['status'],
                'message' => $processHealth['message'],
                'processes' => $processHealth['items'],
                'content_ops_available' => (bool)$contentOps['available'],
            ],
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Die Übersicht konnte nicht geladen werden.',
        'error_message' => $error->getMessage(),
    ]);
}
