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

$assert(be_cc_environment_for_host('staging.bocholt-erleben.de', '/steuerzentrale/') === 'staging', 'Die Staging-Subdomain muss autoritativ Staging auflösen.');
$assert(be_cc_environment_for_host('staging.bocholt-erleben.de:443', '/steuerzentrale/') === 'staging', 'Der Staging-Host muss auch mit Port korrekt aufgelöst werden.');
$assert(be_cc_environment_for_host('bocholt-erleben.de', '/steuerzentrale/') === 'live', 'Die öffentliche Hauptdomain muss Live auflösen.');
$assert(be_cc_environment_for_host('www.bocholt-erleben.de', '/steuerzentrale/') === 'live', 'Die WWW-Hauptdomain muss Live auflösen.');
$assert(be_cc_environment_for_host('bocholt-erleben.de', '/staging/steuerzentrale/') === 'staging', 'Der historische Staging-Pfad darf nicht auf Live fallen.');
$assert(be_cc_runtime_environment('live', 'staging.bocholt-erleben.de', '/steuerzentrale/') === 'staging', 'Der Staging-Host muss eine abweichende private Umgebung sicher auf Staging begrenzen.');
$assert(be_cc_runtime_environment('staging', 'staging.bocholt-erleben.de', '/steuerzentrale/') === 'staging', 'Konsistente Staging-Konfiguration wird akzeptiert.');
$assert(be_cc_runtime_environment('test', 'localhost', '/') === 'test', 'Lokale Tests verwenden weiterhin die explizite Testumgebung.');

$liveMismatchBlocked = false;
try {
    be_cc_runtime_environment('staging', 'bocholt-erleben.de', '/steuerzentrale/');
} catch (RuntimeException) {
    $liveMismatchBlocked = true;
}
$assert($liveMismatchBlocked, 'Ein Staging-Konfigurationswert auf dem Live-Host muss fail-closed blockieren.');

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
$actionV2 = (string)file_get_contents($root . '/api/control-center/action-v2.php');
$actionShim = (string)file_get_contents($root . '/api/control-center/action.php');
$assert(str_contains($sheetSource, 'be_cc_inbox_range'), 'Sheet-Synchronisation nutzt den zentralen Inbox-Range-Vertrag nicht.');
$assert(str_contains($writeback, 'be_cc_inbox_tab_name'), 'Inbox-Writeback nutzt den zentralen Tab-Vertrag nicht.');
$assert(str_contains($stagingWriter, 'be_cc_events_tab_name'), 'Staging-Eventwriteback nutzt den zentralen Events-Tab-Vertrag nicht.');
$assert(str_contains($actionV2, 'be_cc_assert_inbox_case_environment'), 'Der Schreibendpunkt prüft Host und Fallherkunft nicht vor der Mutation.');
$assert(str_contains($actionShim, "require __DIR__ . '/action-v2.php'"), 'Der stabile Action-Endpunkt routet nicht auf die versionierte Owner-Datei.');
$assert(!str_contains($sheetSource, "be_google_sheets_values_get('Inbox!"), 'Sheet-Synchronisation enthält weiterhin einen hart verdrahteten Live-Tab.');
$assert(!str_contains($writeback, "'range'=>'Inbox!"), 'Inbox-Writeback enthält weiterhin einen hart verdrahteten Live-Tab.');
$assert(!str_contains($stagingWriter, "'Events!'"), 'Staging-Eventwriteback enthält weiterhin einen hart verdrahteten gemeinsamen Events-Tab.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Inbox Tab Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Inbox Tab Contract: OK ===\n";
