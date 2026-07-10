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

function be_cc_safe_source_sync(string $key, string $label, callable $callback): array
{
    try {
        $result = $callback();
        $result = is_array($result) ? $result : [];
        $result['status'] = 'ok';
        $result['label'] = $label;
        $stmt = be_db()->prepare("UPDATE control_cases SET state='done', completed_at=NOW(), updated_at=NOW() WHERE source_system='source_sync' AND source_reference=:reference AND state NOT IN ('done','rejected','parked')");
        $stmt->execute(['reference' => 'sync:' . $key]);
        return $result;
    } catch (Throwable $error) {
        $result = ['status' => 'error', 'label' => $label, 'seen' => 0, 'upserted' => 0, 'message' => $error->getMessage()];
        be_cc_upsert_source_case([
            'type' => 'task',
            'state' => 'blocked',
            'priority' => 'high',
            'title' => 'Datenquelle prüfen: ' . $label,
            'reason' => 'Die Quelle konnte nicht synchronisiert werden: ' . $error->getMessage(),
            'next_action' => 'Zugriff beziehungsweise Quelldaten prüfen und Synchronisation erneut ausführen.',
            'object_type' => 'data_source',
            'object_id' => $key,
            'object_title' => $label,
            'source_system' => 'source_sync',
            'source_reference' => 'sync:' . $key,
            'source_payload' => $result,
            'decision_ready' => false,
        ]);
        return $result;
    }
}

try {
    be_cc_ensure_schema();

    // Die führende Sheet-Inbox wird zuerst gelesen. Der JSON-Bestand wird nur
    // bei einem echten Sheet-Fehler verwendet und kann daher keine parallelen
    // Doppelvorgänge erzeugen.
    $sheetInbox = be_cc_safe_source_sync('sheet_inbox', 'Führende Sheet-Inbox', 'be_cc_sync_sheet_inbox');
    $inboxFallback = ($sheetInbox['status'] ?? '') === 'error'
        ? be_cc_safe_source_sync('inbox_feed', 'Inbox-JSON-Fallback', 'be_cc_sync_inbox_feed')
        : ['status' => 'standby', 'label' => 'Inbox-JSON-Fallback', 'seen' => 0, 'upserted' => 0, 'message' => 'Nicht benötigt, weil die führende Sheet-Inbox verfügbar ist.'];

    $sync = [
        'sheet_inbox' => $sheetInbox,
        'inbox_feed_fallback' => $inboxFallback,
        'submissions' => be_cc_safe_source_sync('submissions', 'Anbieter-Einreichungen', 'be_cc_sync_submissions'),
        'content_audit' => be_cc_safe_source_sync('content_audit', 'Content-Prüfung', 'be_cc_sync_content_audit'),
        'growth_backlog' => be_cc_safe_source_sync('growth_backlog', 'Growth-Backlog', 'be_cc_sync_growth_backlog'),
        'repo_workpacks' => be_cc_safe_source_sync('repo_workpacks', 'Repo-Workpacks', 'be_cc_sync_repo_workpacks'),
    ];

    $contentOps = be_cc_content_ops_status(be_db());
    $processHealth = be_cc_process_health($contentOps);
    be_cc_sync_process_health_cases($processHealth);

    $syncErrors = count(array_filter($sync, static fn(array $item): bool => ($item['status'] ?? '') === 'error'));
    $systemStatus = $syncErrors > 0 ? 'attention' : $processHealth['status'];
    $systemMessage = $syncErrors > 0
        ? $syncErrors . ' Datenquellen konnten nicht synchronisiert werden. Andere Bereiche bleiben verfügbar.'
        : $processHealth['message'];

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
                'status' => $systemStatus,
                'message' => $systemMessage,
                'sync_errors' => $syncErrors,
                'processes' => $processHealth['items'],
                'content_ops_available' => (bool)$contentOps['available'],
            ],
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Die Steuerzentrale konnte nicht initialisiert werden.',
        'error_message' => $error->getMessage(),
    ]);
}
