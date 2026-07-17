<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

function be_cc_normalize_environment(string $environment): string
{
    $environment = strtolower(trim($environment));
    return match ($environment) {
        'production', 'prod' => 'live',
        'development', 'dev', 'local', 'test' => $environment,
        default => $environment,
    };
}

/**
 * Public host routing is authoritative for deploy environments. This prevents
 * a stale or locally overridden private config from selecting the live writer
 * on the staging host.
 */
function be_cc_environment_for_host(?string $host = null, ?string $requestUri = null): string
{
    $host = strtolower(trim((string)($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
    $host = preg_replace('/:\d+$/', '', rtrim($host, '.')) ?? $host;
    $requestUri = (string)($requestUri ?? ($_SERVER['REQUEST_URI'] ?? ''));

    if ($host === 'staging.bocholt-erleben.de') return 'staging';
    if (in_array($host, ['bocholt-erleben.de', 'www.bocholt-erleben.de'], true)) {
        return str_starts_with($requestUri, '/staging/') ? 'staging' : 'live';
    }
    return '';
}

function be_cc_runtime_environment(
    ?string $configuredEnvironment = null,
    ?string $host = null,
    ?string $requestUri = null
): string {
    $configured = be_cc_normalize_environment((string)($configuredEnvironment ?? be_app_env_value()));
    $hostEnvironment = be_cc_environment_for_host($host, $requestUri);

    // On the staging host, staging is always the only safe write target.
    if ($hostEnvironment === 'staging') return 'staging';

    // Live must never continue with a staging or unknown deployed config.
    if ($hostEnvironment === 'live') {
        if ($configured !== '' && $configured !== 'live') {
            throw new RuntimeException('Laufzeitumgebung und öffentlicher Host widersprechen sich. Schreibzugriff wurde blockiert.');
        }
        return 'live';
    }

    if (in_array($configured, ['staging', 'live', 'development', 'dev', 'local', 'test'], true)) {
        return $configured;
    }

    throw new RuntimeException('Die Laufzeitumgebung kann nicht sicher bestimmt werden.');
}

/** Resolve authoritative environment-specific Google Sheet tabs. */
function be_cc_inbox_tab_for_environment(string $environment): string
{
    $environment = be_cc_normalize_environment($environment);
    if ($environment === 'staging') return 'Inbox_Staging';
    if ($environment === 'live') return 'Inbox';
    if (in_array($environment, ['development', 'dev', 'local', 'test'], true)) return 'Inbox_Staging';
    throw new RuntimeException('Der Inbox-Tab kann für diese Laufzeitumgebung nicht sicher bestimmt werden.');
}

function be_cc_events_tab_for_environment(string $environment): string
{
    $environment = be_cc_normalize_environment($environment);
    if ($environment === 'staging') return 'Events_Staging';
    if ($environment === 'live') return 'Events';
    if (in_array($environment, ['development', 'dev', 'local', 'test'], true)) return 'Events_Staging';
    throw new RuntimeException('Der Events-Tab kann für diese Laufzeitumgebung nicht sicher bestimmt werden.');
}

function be_cc_inbox_tab_name(): string
{
    return be_cc_inbox_tab_for_environment(be_cc_runtime_environment());
}

function be_cc_events_tab_name(): string
{
    return be_cc_events_tab_for_environment(be_cc_runtime_environment());
}

function be_cc_assert_inbox_case_environment(array $source): array
{
    $environment = be_cc_runtime_environment();
    $expectedInbox = be_cc_inbox_tab_for_environment($environment);
    $expectedEvents = be_cc_events_tab_for_environment($environment);
    $sourceTab = trim((string)($source['sheet_tab'] ?? ''));

    if ($sourceTab !== '' && $sourceTab !== $expectedInbox) {
        throw new DomainException(sprintf(
            'Der Fall gehört zu %s, die aktuelle Laufzeit ist jedoch an %s gebunden.',
            $sourceTab,
            $expectedInbox
        ));
    }

    return [
        'environment'=>$environment,
        'configured_environment'=>be_cc_normalize_environment(be_app_env_value()),
        'host_environment'=>be_cc_environment_for_host(),
        'inbox_tab'=>$expectedInbox,
        'events_tab'=>$expectedEvents,
        'source_tab'=>$sourceTab,
    ];
}

function be_cc_inbox_range(string $cells = 'A:ZZ'): string
{
    $cells = trim($cells);
    if ($cells === '' || str_contains($cells, '!')) throw new InvalidArgumentException('Der Inbox-Zellbereich ist ungültig.');
    return be_cc_inbox_tab_name() . '!' . $cells;
}

function be_cc_events_range(string $cells = 'A:ZZ'): string
{
    $cells = trim($cells);
    if ($cells === '' || str_contains($cells, '!')) throw new InvalidArgumentException('Der Events-Zellbereich ist ungültig.');
    return be_cc_events_tab_name() . '!' . $cells;
}
