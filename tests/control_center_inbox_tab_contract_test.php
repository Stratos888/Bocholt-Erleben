<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_inbox_tab.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(be_cc_inbox_tab_for_environment('staging') === 'Inbox_Staging', 'Staging muss ausschließlich Inbox_Staging verwenden.');
$assert(be_cc_inbox_tab_for_environment('live') === 'Inbox', 'Live muss ausschließlich Inbox verwenden.');
$assert(be_cc_inbox_tab_for_environment('production') === 'Inbox', 'Production muss ausschließlich Inbox verwenden.');
$assert(be_cc_inbox_tab_for_environment('test') === 'Inbox_Staging', 'Tests dürfen niemals auf die Live-Inbox zeigen.');
$assert(be_cc_inbox_tab_for_environment('local') === 'Inbox_Staging', 'Lokale Entwicklung darf niemals auf die Live-Inbox zeigen.');
$assert(be_cc_events_tab_for_environment('staging') === 'Events_Staging', 'Staging muss ausschließlich Events_Staging als Eventziel verwenden.');
$assert(be_cc_events_tab_for_environment('live') === 'Events', 'Live muss Events als Eventziel verwenden.');
$assert(be_cc_events_tab_for_environment('test') === 'Events_Staging', 'Tests dürfen niemals in Events schreiben.');
$assert(be_cc_events_tab_for_environment('local') === 'Events_Staging', 'Lokale Entwicklung darf niemals in Events schreiben.');

$blocked = false;
try {
    be_cc_inbox_tab_for_environment('unknown');
} catch (RuntimeException) {
    $blocked = true;
}
$assert($blocked, 'Unbekannte Inbox-Umgebungen müssen fail-closed blockiert werden.');

$eventsBlocked = false;
try {
    be_cc_events_tab_for_environment('unknown');
} catch (RuntimeException) {
    $eventsBlocked = true;
}
$assert($eventsBlocked, 'Unbekannte Events-Umgebungen müssen fail-closed blockiert werden.');

$root = dirname(__DIR__);
$sheetSource = (string)file_get_contents($root . '/api/control-center/_sheet_inbox_source.php');
$writeback = (string)file_get_contents($root . '/api/control-center/_inbox_decision_writeback.php');
$stagingWriter = (string)file_get_contents($root . '/api/control-center/_staging_events_writeback.php');
$assert(str_contains($sheetSource, 'be_cc_inbox_range'), 'Sheet-Synchronisation nutzt den zentralen Inbox-Range-Vertrag nicht.');
$assert(str_contains($writeback, 'be_cc_inbox_tab_name'), 'Inbox-Writeback nutzt den zentralen Tab-Vertrag nicht.');
$assert(str_contains($stagingWriter, 'be_cc_events_tab_name'), 'Staging-Eventwriteback nutzt den zentralen Events-Tab-Vertrag nicht.');
$assert(!str_contains($sheetSource, "be_google_sheets_values_get('Inbox!"), 'Sheet-Synchronisation enthält weiterhin einen hart verdrahteten Live-Tab.');
$assert(!str_contains($writeback, "'range'=>'Inbox!"), 'Inbox-Writeback enthält weiterhin einen hart verdrahteten Live-Tab.');
$assert(!str_contains($stagingWriter, "'Events!'"), 'Staging-Eventwriteback enthält weiterhin einen hart verdrahteten gemeinsamen Events-Tab.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Inbox Tab Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Inbox Tab Contract: OK ===\n";
