<?php
declare(strict_types=1);

putenv('APP_ENV=staging');
require_once dirname(__DIR__) . '/api/control-center/_inbox_tab.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(be_cc_inbox_tab_name() === 'Inbox_Staging', 'Staging muss ausschließlich Inbox_Staging verwenden.');
$assert(be_cc_inbox_range('A:ZZ') === 'Inbox_Staging!A:ZZ', 'Staging-Range ist nicht korrekt gebunden.');

putenv('APP_ENV=live');
$assert(be_cc_inbox_tab_name() === 'Inbox', 'Live muss ausschließlich Inbox verwenden.');
$assert(be_cc_inbox_range('A1:B2') === 'Inbox!A1:B2', 'Live-Range ist nicht korrekt gebunden.');

putenv('APP_ENV=test');
$assert(be_cc_inbox_tab_name() === 'Inbox_Staging', 'Tests dürfen niemals auf die Live-Inbox zeigen.');

putenv('APP_ENV=unknown');
$blocked = false;
try {
    be_cc_inbox_tab_name();
} catch (RuntimeException $error) {
    $blocked = true;
}
$assert($blocked, 'Unbekannte Umgebungen müssen fail-closed blockiert werden.');

$root = dirname(__DIR__);
$sheetSource = (string)file_get_contents($root . '/api/control-center/_sheet_inbox_source.php');
$writeback = (string)file_get_contents($root . '/api/control-center/_inbox_decision_writeback.php');
$assert(str_contains($sheetSource, 'be_cc_inbox_range'), 'Sheet-Synchronisation nutzt den zentralen Inbox-Range-Vertrag nicht.');
$assert(str_contains($writeback, 'be_cc_inbox_tab_name'), 'Inbox-Writeback nutzt den zentralen Tab-Vertrag nicht.');
$assert(!str_contains($sheetSource, "be_google_sheets_values_get('Inbox!"), 'Sheet-Synchronisation enthält weiterhin einen hart verdrahteten Live-Tab.');
$assert(!str_contains($writeback, "'range'=>'Inbox!"), 'Inbox-Writeback enthält weiterhin einen hart verdrahteten Live-Tab.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Inbox Tab Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Inbox Tab Contract: OK ===\n";
