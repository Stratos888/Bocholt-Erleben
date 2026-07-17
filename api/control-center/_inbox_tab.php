<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

/**
 * Resolve authoritative environment-specific Google Sheet tabs. Unknown
 * environments fail closed so staging can never silently fall back to live.
 */
function be_cc_inbox_tab_for_environment(string $environment): string
{
    $environment = strtolower(trim($environment));

    if ($environment === 'staging') return 'Inbox_Staging';
    if (in_array($environment, ['live', 'production', 'prod'], true)) return 'Inbox';
    if (in_array($environment, ['development', 'dev', 'local', 'test'], true)) return 'Inbox_Staging';

    throw new RuntimeException('Der Inbox-Tab kann für diese Laufzeitumgebung nicht sicher bestimmt werden.');
}

function be_cc_events_tab_for_environment(string $environment): string
{
    $environment = strtolower(trim($environment));

    if ($environment === 'staging') return 'Events_Staging';
    if (in_array($environment, ['live', 'production', 'prod'], true)) return 'Events';
    if (in_array($environment, ['development', 'dev', 'local', 'test'], true)) return 'Events_Staging';

    throw new RuntimeException('Der Events-Tab kann für diese Laufzeitumgebung nicht sicher bestimmt werden.');
}

function be_cc_inbox_tab_name(): string
{
    return be_cc_inbox_tab_for_environment(be_app_env_value());
}

function be_cc_events_tab_name(): string
{
    return be_cc_events_tab_for_environment(be_app_env_value());
}

function be_cc_inbox_range(string $cells = 'A:ZZ'): string
{
    $cells = trim($cells);
    if ($cells === '' || str_contains($cells, '!')) {
        throw new InvalidArgumentException('Der Inbox-Zellbereich ist ungültig.');
    }
    return be_cc_inbox_tab_name() . '!' . $cells;
}

function be_cc_events_range(string $cells = 'A:ZZ'): string
{
    $cells = trim($cells);
    if ($cells === '' || str_contains($cells, '!')) {
        throw new InvalidArgumentException('Der Events-Zellbereich ist ungültig.');
    }
    return be_cc_events_tab_name() . '!' . $cells;
}
