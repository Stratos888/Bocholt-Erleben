<?php
declare(strict_types=1);

require_once __DIR__ . '/_runtime_preflight.php';

const BE_CC_RUNTIME_RESOURCE_CONTRACT_VERSION = '2026-07-19.1';

/**
 * Read-only E3 contract for the deployed staging runtime and its authoritative
 * Sheet resources. It intentionally has no case identity and cannot authorize
 * or execute an editorial action.
 */
function be_cc_runtime_resource_contract(
    ?array $runtimeContext = null,
    ?array $inboxSnapshot = null,
    ?array $eventsSnapshot = null
): array {
    $runtime = $runtimeContext ?? be_cc_preflight_runtime_context();
    $blockers = (array)($runtime['blockers'] ?? []);
    $environment = trim((string)($runtime['resolved_environment'] ?? ''));
    $inboxTab = trim((string)($runtime['inbox_tab'] ?? ''));
    $eventsTab = trim((string)($runtime['events_tab'] ?? ''));

    if ($environment !== 'staging') {
        $blockers[] = 'Der Runtime-Ressourcen-Preflight ist ausschließlich für Staging freigegeben.';
    }
    if ($inboxTab !== 'Inbox_Staging' || $eventsTab !== 'Events_Staging') {
        $blockers[] = 'Der Runtime-Ressourcen-Preflight hat kein isoliertes Staging-Ressourcentupel aufgelöst.';
    }

    $writer = '';
    try {
        $writer = be_cc_inbox_writer_name('approve', $eventsTab);
    } catch (Throwable $error) {
        $blockers[] = $error->getMessage();
    }
    if ($writer !== '' && !function_exists($writer)) {
        $blockers[] = 'Der erwartete Staging-Writer ist in dieser Runtime nicht geladen.';
    }

    if ($inboxSnapshot === null) {
        try {
            $response = be_google_sheets_values_get(be_cc_inbox_range('A:ZZ'));
            $values = is_array($response['values'] ?? null) ? $response['values'] : [];
            $table = be_cc_inbox_table_from_values($values, $inboxTab);
            $inboxSnapshot = [
                'tab' => trim((string)($table['tab'] ?? '')),
                'schema' => 'valid',
                'header_row' => (int)($table['header_row'] ?? 0),
                'row_count' => count((array)($table['rows'] ?? [])),
                'header_fingerprint' => substr(hash('sha256', json_encode((array)($table['header'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 0, 16),
            ];
        } catch (Throwable $error) {
            $inboxSnapshot = ['tab'=>'', 'schema'=>'error', 'header_row'=>0, 'row_count'=>0, 'header_fingerprint'=>''];
            $blockers[] = 'Inbox_Staging konnte read-only nicht validiert werden: ' . $error->getMessage();
        }
    }

    if ($eventsSnapshot === null) {
        try {
            $table = be_cc_staging_events_table();
            $eventsSnapshot = [
                'tab' => trim((string)($table['tab'] ?? '')),
                'schema' => 'valid',
                'header_row' => 1,
                'row_count' => count((array)($table['rows'] ?? [])),
                'header_fingerprint' => substr(hash('sha256', json_encode((array)($table['header'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 0, 16),
            ];
        } catch (Throwable $error) {
            $eventsSnapshot = ['tab'=>'', 'schema'=>'error', 'header_row'=>0, 'row_count'=>0, 'header_fingerprint'=>''];
            $blockers[] = 'Events_Staging konnte read-only nicht validiert werden: ' . $error->getMessage();
        }
    }

    if (trim((string)($inboxSnapshot['tab'] ?? '')) !== 'Inbox_Staging' || ($inboxSnapshot['schema'] ?? '') !== 'valid') {
        $blockers[] = 'Inbox_Staging besitzt keinen bestätigten read-only Ressourcenvertrag.';
    }
    if (trim((string)($eventsSnapshot['tab'] ?? '')) !== 'Events_Staging' || ($eventsSnapshot['schema'] ?? '') !== 'valid') {
        $blockers[] = 'Events_Staging besitzt keinen bestätigten read-only Ressourcenvertrag.';
    }

    $blockers = array_values(array_unique(array_filter(array_map('trim', $blockers), static fn(string $value): bool => $value !== '')));

    return [
        'schema' => 1,
        'endpoint_version' => BE_CC_RUNTIME_RESOURCE_CONTRACT_VERSION,
        'mode' => 'runtime',
        'scope' => 'runtime_and_resource_contract_only',
        'mutation' => false,
        'allowed' => !$blockers,
        'blockers' => $blockers,
        'runtime' => $runtime,
        'writer' => $writer,
        'resources' => [
            'sheet_fingerprint' => trim((string)($runtime['sheet_fingerprint'] ?? '')),
            'source_tab' => $inboxTab,
            'target_tab' => $eventsTab,
            'live_inbox' => 'not_used',
            'live_events' => 'not_used',
        ],
        'inbox_snapshot' => $inboxSnapshot,
        'events_snapshot' => $eventsSnapshot,
        'operations' => [
            'read and validate the deployed build and environment',
            'read and validate the Inbox_Staging schema without returning row data',
            'read and validate the Events_Staging schema without returning row data',
            'resolve the bounded staging writer without invoking it',
        ],
        'expected_postconditions' => [
            'runtime_contract_verified',
            'staging_resource_tuple_verified',
            'no_case_selected',
            'no_mutation_performed',
        ],
    ];
}
