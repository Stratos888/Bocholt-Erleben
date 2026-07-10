<?php
declare(strict_types=1);

require __DIR__ . '/_domain.php';

be_require_review_access();

try {
    $cases = be_cc_list_cases(['active' => '1']);
    $groups = ['now' => [], 'next' => [], 'inbox' => [], 'information' => []];
    foreach ($cases as $case) {
        $bucket = $case['bucket'] ?? 'information';
        if (!isset($groups[$bucket])) {
            $bucket = 'information';
        }
        $groups[$bucket][] = $case;
    }

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'groups' => $groups,
            'counts' => array_map('count', $groups),
            'system' => [
                'status' => 'ok',
                'message' => 'Automatisierungen laufen – keine bekannte Störung mit Auswirkung.',
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
