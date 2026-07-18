<?php
declare(strict_types=1);

require_once __DIR__ . '/_staging_events_writeback.php';

const BE_CC_RUNTIME_PREFLIGHT_VERSION = '2026-07-18.1';

function be_cc_preflight_normalize_environment(string $environment): string
{
    $environment = strtolower(trim($environment));
    if ($environment === 'staging') return 'staging';
    if (in_array($environment, ['live', 'production', 'prod'], true)) return 'live';
    if (in_array($environment, ['development', 'dev', 'local', 'test'], true)) return 'staging';
    throw new RuntimeException('Die Laufzeitumgebung kann für den Preflight nicht sicher aufgelöst werden.');
}

function be_cc_preflight_expected_host(string $environment): string
{
    return match ($environment) {
        'staging' => 'staging.bocholt-erleben.de',
        'live' => 'bocholt-erleben.de',
        default => throw new RuntimeException('Für diese Umgebung ist kein autorisierter Host definiert.'),
    };
}

function be_cc_preflight_request_info(?array $server = null): array
{
    $server = $server ?? $_SERVER;
    $host = strtolower(trim((string)($server['HTTP_X_FORWARDED_HOST'] ?? $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));
    if (str_contains($host, ',')) $host = trim(explode(',', $host, 2)[0]);
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    $requestUri = trim((string)($server['REQUEST_URI'] ?? ''));
    $requestPath = $requestUri !== '' ? (string)(parse_url($requestUri, PHP_URL_PATH) ?: '') : '';
    $scheme = strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($scheme === '') $scheme = (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off') ? 'https' : 'http';

    return [
        'scheme' => $scheme,
        'host' => $host,
        'request_path' => $requestPath,
        'method' => strtoupper(trim((string)($server['REQUEST_METHOD'] ?? ''))),
    ];
}

function be_cc_preflight_build_info(?string $rootPath = null): array
{
    $rootPath = $rootPath !== null ? rtrim($rootPath, DIRECTORY_SEPARATOR) : dirname(__DIR__, 2);
    $buildPath = $rootPath . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . 'build.txt';
    $manifestPath = $rootPath . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . 'deploy-manifest.json';

    $buildId = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';
    $manifest = null;
    if (is_file($manifestPath)) {
        $decoded = json_decode((string)file_get_contents($manifestPath), true);
        if (is_array($decoded)) $manifest = $decoded;
    }

    $manifestBuild = trim((string)($manifest['build'] ?? ''));
    $manifestEnvironment = strtolower(trim((string)($manifest['environment'] ?? '')));

    return [
        'build_id' => $buildId,
        'manifest_build' => $manifestBuild,
        'manifest_environment' => $manifestEnvironment,
        'build_present' => $buildId !== '',
        'manifest_present' => is_array($manifest),
        'build_consistent' => $buildId !== '' && $manifestBuild !== '' && hash_equals($buildId, $manifestBuild),
    ];
}

function be_cc_preflight_sheet_fingerprint(?array $googleConfig = null): string
{
    $googleConfig = $googleConfig ?? be_google_config();
    $sheetId = trim((string)($googleConfig['sheet_id'] ?? ''));
    if ($sheetId === '') throw new RuntimeException('Die Google-Sheet-Ressource ist nicht konfiguriert.');
    return substr(hash('sha256', $sheetId), 0, 16);
}

function be_cc_inbox_writer_name(string $action, string $eventsTab): string
{
    $action = strtolower(trim($action));
    $eventsTab = trim($eventsTab);

    if ($action === 'approve' && $eventsTab === 'Events_Staging') return 'be_cc_writeback_staging_inbox_approve_verified';
    if ($action === 'approve' && $eventsTab === 'Events') return 'be_cc_writeback_inbox_approve_verified';
    if (in_array($action, ['reject', 'snooze'], true) && in_array($eventsTab, ['Events_Staging', 'Events'], true)) {
        return 'be_cc_writeback_inbox_decision_direct';
    }

    throw new DomainException('Für diese Inbox-Aktion ist kein sicherer Writer definiert.');
}

function be_cc_preflight_context_from_values(
    string $configuredEnvironment,
    array $request,
    array $build,
    string $sheetFingerprint
): array {
    $blockers = [];
    try {
        $resolvedEnvironment = be_cc_preflight_normalize_environment($configuredEnvironment);
        $expectedHost = be_cc_preflight_expected_host($resolvedEnvironment);
        $inboxTab = be_cc_inbox_tab_for_environment($resolvedEnvironment);
        $eventsTab = be_cc_events_tab_for_environment($resolvedEnvironment);
    } catch (Throwable $error) {
        return [
            'configured_environment' => strtolower(trim($configuredEnvironment)),
            'resolved_environment' => '',
            'expected_host' => '',
            'request' => $request,
            'build' => $build,
            'sheet_fingerprint' => $sheetFingerprint,
            'inbox_tab' => '',
            'events_tab' => '',
            'blockers' => [$error->getMessage()],
        ];
    }

    $actualHost = strtolower(trim((string)($request['host'] ?? '')));
    if ($actualHost === '' || !hash_equals($expectedHost, $actualHost)) {
        $blockers[] = sprintf('Hostabweichung: erwartet %s, erhalten %s.', $expectedHost, $actualHost !== '' ? $actualHost : 'missing');
    }
    if (trim((string)($request['request_path'] ?? '')) !== '/api/control-center/preflight.php') {
        $blockers[] = 'Der Preflight wurde nicht über den autorisierten Endpoint aufgerufen.';
    }
    if (strtoupper(trim((string)($request['method'] ?? ''))) !== 'POST') {
        $blockers[] = 'Der Preflight muss per POST aufgerufen werden.';
    }
    if (empty($build['build_present'])) $blockers[] = 'Der deployte Buildmarker fehlt.';
    if (empty($build['manifest_present'])) $blockers[] = 'Das deployte Manifest fehlt.';
    if (empty($build['build_consistent'])) $blockers[] = 'Buildmarker und Deploymanifest stimmen nicht überein.';
    $manifestEnvironment = strtolower(trim((string)($build['manifest_environment'] ?? '')));
    if ($manifestEnvironment === '' || !hash_equals($resolvedEnvironment, $manifestEnvironment)) {
        $blockers[] = 'Deploymanifest und aufgelöste Laufzeitumgebung stimmen nicht überein.';
    }
    if ($sheetFingerprint === '') $blockers[] = 'Die Sheet-Ressource besitzt keinen sicheren Fingerprint.';

    $expectedTuple = $resolvedEnvironment === 'staging'
        ? ['Inbox_Staging', 'Events_Staging']
        : ['Inbox', 'Events'];
    if ($inboxTab !== $expectedTuple[0] || $eventsTab !== $expectedTuple[1]) {
        $blockers[] = 'Inbox- und Events-Tab bilden kein autorisiertes Umgebungstupel.';
    }

    return [
        'configured_environment' => strtolower(trim($configuredEnvironment)),
        'resolved_environment' => $resolvedEnvironment,
        'expected_host' => $expectedHost,
        'request' => $request,
        'build' => $build,
        'sheet_fingerprint' => $sheetFingerprint,
        'inbox_tab' => $inboxTab,
        'events_tab' => $eventsTab,
        'blockers' => array_values(array_unique($blockers)),
    ];
}

function be_cc_preflight_runtime_context(): array
{
    $configuredEnvironment = be_app_env_value();
    $request = be_cc_preflight_request_info();
    $build = be_cc_preflight_build_info();
    $sheetFingerprint = '';
    $configError = '';
    try {
        $sheetFingerprint = be_cc_preflight_sheet_fingerprint();
    } catch (Throwable $error) {
        $configError = $error->getMessage();
    }

    $context = be_cc_preflight_context_from_values($configuredEnvironment, $request, $build, $sheetFingerprint);
    if ($configError !== '') $context['blockers'][] = $configError;
    $context['blockers'] = array_values(array_unique((array)$context['blockers']));
    return $context;
}

function be_cc_preflight_case_identity(array $case, array $source): array
{
    $sourceCopy = $source;
    unset($sourceCopy['_raw'], $sourceCopy['row_number']);
    ksort($sourceCopy);

    return [
        'case_id' => trim((string)($case['id'] ?? '')),
        'source_system' => trim((string)($case['source_system'] ?? '')),
        'source_reference' => trim((string)($case['source_reference'] ?? '')),
        'object_id' => trim((string)($case['object_id'] ?? '')),
        'title' => trim((string)($case['title'] ?? '')),
        'source_fingerprint' => hash('sha256', json_encode($sourceCopy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    ];
}

function be_cc_preflight_operations(string $action, string $environment): array
{
    if ($action === 'approve' && $environment === 'staging') {
        return [
            'read and resolve the authoritative Inbox_Staging row',
            'validate the event candidate and stable event identity',
            'upsert and read back the Events_Staging row',
            'write and read back the terminal Inbox_Staging status',
            'close and verify the local control case',
            'verify the generated staging feed after the next deploy',
        ];
    }
    if ($action === 'approve') {
        return [
            'read and resolve the authoritative Inbox row',
            'apply verified canonical source updates when required',
            'invoke the bounded live approval service',
            'read back the terminal Inbox status',
            'close and verify the local control case',
        ];
    }
    return [
        'read and resolve the authoritative inbox row',
        'write status and decision reason in one bounded batch',
        'read back the authoritative inbox status',
        'apply and verify the local case transition',
    ];
}

function be_cc_preflight_plan(
    array $case,
    string $action,
    array $payload = [],
    ?array $runtimeContext = null,
    ?array $sourceSnapshot = null,
    ?array $targetSnapshot = null
): array {
    $action = strtolower(trim($action));
    if (!in_array($action, ['approve', 'reject', 'snooze'], true)) {
        throw new InvalidArgumentException('Der Runtime-Preflight unterstützt nur approve, reject und snooze.');
    }

    $runtime = $runtimeContext ?? be_cc_preflight_runtime_context();
    $blockers = (array)($runtime['blockers'] ?? []);
    if (trim((string)($case['source_system'] ?? '')) !== 'inbox_feed') {
        $blockers[] = 'Der Fall stammt nicht aus dem autorisierten Inbox-Feed.';
    }

    $source = be_cc_editorial_payload($case);
    if ($sourceSnapshot === null) {
        try {
            $current = be_cc_inbox_direct_current_source($case);
            $source = (array)$current['source_payload'];
            $sourceSnapshot = [
                'tab' => trim((string)($current['table']['tab'] ?? '')),
                'resolution_status' => trim((string)($current['resolution']['status'] ?? '')),
                'row_number' => (int)($current['resolution']['row_number'] ?? 0),
                'fingerprint' => trim((string)($current['fingerprint'] ?? '')),
            ];
        } catch (Throwable $error) {
            $sourceSnapshot = ['tab'=>'', 'resolution_status'=>'error', 'row_number'=>0, 'fingerprint'=>''];
            $blockers[] = 'Die führende Inbox-Quelle konnte read-only nicht eindeutig aufgelöst werden: ' . $error->getMessage();
        }
    }

    $eventUpdates = is_array($payload['event_updates'] ?? null) ? $payload['event_updates'] : [];
    if ($eventUpdates) $source = be_cc_editorial_merge_updates($source, $eventUpdates);

    $eventsTab = trim((string)($runtime['events_tab'] ?? ''));
    $writer = '';
    try {
        $writer = be_cc_inbox_writer_name($action, $eventsTab);
    } catch (Throwable $error) {
        $blockers[] = $error->getMessage();
    }
    if ($writer !== '' && !function_exists($writer)) $blockers[] = 'Der ausgewählte Writer ist in dieser Runtime nicht geladen.';

    $expectedInboxTab = trim((string)($runtime['inbox_tab'] ?? ''));
    if (trim((string)($sourceSnapshot['tab'] ?? '')) !== $expectedInboxTab) {
        $blockers[] = 'Die read-only aufgelöste Inbox-Quelle stimmt nicht mit dem Environment-Vertrag überein.';
    }
    if (trim((string)($sourceSnapshot['resolution_status'] ?? '')) !== 'resolved') {
        $blockers[] = 'Die Inbox-Identität ist nicht eindeutig aufgelöst.';
    }

    if ($action === 'approve') {
        $isActivity = be_cc_decision_token($source['submission_kind'] ?? '') === 'activity';
        if (!$isActivity) {
            $review = be_cc_event_candidate_review_contract($source);
            if (empty($review['decision_gate']['ready'])) {
                $blockers[] = 'Der Event ist fachlich nicht entscheidungsreif.';
            }
        }

        if (($runtime['resolved_environment'] ?? '') === 'staging') {
            if ($targetSnapshot === null) {
                try {
                    $event = be_cc_staging_event_from_source($source);
                    $table = be_cc_staging_events_table();
                    $resolution = be_cc_staging_event_resolution((array)$table['rows'], $event);
                    $targetSnapshot = [
                        'tab' => trim((string)($table['tab'] ?? '')),
                        'event_id' => trim((string)($event['id'] ?? '')),
                        'resolution_status' => trim((string)($resolution['status'] ?? '')),
                        'row_number' => (int)($resolution['row_number'] ?? 0),
                        'identical' => !empty($resolution['identical']),
                    ];
                    if (($resolution['status'] ?? '') === 'conflict') {
                        $blockers[] = (string)($resolution['message'] ?? 'Das Eventziel enthält einen Identitätskonflikt.');
                    }
                } catch (Throwable $error) {
                    $targetSnapshot = ['tab'=>'', 'event_id'=>'', 'resolution_status'=>'error', 'row_number'=>0, 'identical'=>false];
                    $blockers[] = 'Das Staging-Eventziel konnte read-only nicht geprüft werden: ' . $error->getMessage();
                }
            }
            if (trim((string)($targetSnapshot['tab'] ?? '')) !== 'Events_Staging') {
                $blockers[] = 'Der read-only geprüfte Event-Target ist nicht Events_Staging.';
            }
        }
    }

    $environment = trim((string)($runtime['resolved_environment'] ?? ''));
    $blockers = array_values(array_unique(array_filter(array_map('trim', $blockers), static fn(string $value): bool => $value !== '')));

    return [
        'schema' => 1,
        'endpoint_version' => BE_CC_RUNTIME_PREFLIGHT_VERSION,
        'mutation' => false,
        'allowed' => !$blockers,
        'blockers' => $blockers,
        'runtime' => $runtime,
        'case' => be_cc_preflight_case_identity($case, $source),
        'action' => $action,
        'writer' => $writer,
        'resources' => [
            'sheet_fingerprint' => trim((string)($runtime['sheet_fingerprint'] ?? '')),
            'source_tab' => $expectedInboxTab,
            'target_tab' => $action === 'approve' ? $eventsTab : $expectedInboxTab,
            'live_inbox' => $environment === 'live' ? 'planned_read_write' : 'not_used',
            'live_events' => $environment === 'live' && $action === 'approve' ? 'planned_write' : 'not_used',
        ],
        'source_snapshot' => $sourceSnapshot,
        'target_snapshot' => $targetSnapshot,
        'operations' => be_cc_preflight_operations($action, $environment),
        'expected_postconditions' => $action === 'approve'
            ? ['event_target_verified', 'inbox_terminal_status_verified', 'local_case_done_verified']
            : ['inbox_terminal_status_verified', 'local_case_transition_verified'],
    ];
}
