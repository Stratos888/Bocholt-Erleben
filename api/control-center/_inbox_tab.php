<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

/**
 * Resolve the authoritative Inbox tab without ever allowing a staging runtime
 * to read from or write to the live Inbox tab.
 */
function be_cc_inbox_tab_name(): string
{
    $environment = be_app_env_value();

    if ($environment === 'staging') return 'Inbox_Staging';
    if (in_array($environment, ['live', 'production', 'prod'], true)) return 'Inbox';
    if (in_array($environment, ['development', 'dev', 'local', 'test'], true)) return 'Inbox_Staging';

    throw new RuntimeException('Der Inbox-Tab kann für diese Laufzeitumgebung nicht sicher bestimmt werden.');
}

function be_cc_inbox_range(string $cells = 'A:ZZ'): string
{
    $cells = trim($cells);
    if ($cells === '' || str_contains($cells, '!')) {
        throw new InvalidArgumentException('Der Inbox-Zellbereich ist ungültig.');
    }
    return be_cc_inbox_tab_name() . '!' . $cells;
}
