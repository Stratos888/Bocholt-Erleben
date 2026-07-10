<?php
declare(strict_types=1);

require __DIR__ . '/_schema.php';
require __DIR__ . '/_submission_source.php';

be_require_review_access();

try {
    be_cc_ensure_schema();

    $sync = [
        'inbox' => be_cc_sync_inbox_feed(),
        'submissions' => be_cc_sync_submissions(),
        'content_audit' => be_cc_sync_content_audit(),
        'growth_backlog' => be_cc_sync_growth_backlog(),
    ];

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
                'status' => 'ok',
                'message' => 'Alle relevanten Quellen sind synchronisiert. Keine bekannte Störung mit Auswirkung.',
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
