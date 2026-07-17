<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_staging_events_writeback.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(be_cc_events_tab_for_environment('staging') === 'Events_Staging', 'Staging muss ausschließlich Events_Staging als schreibbares Eventziel verwenden.');
$assert(be_cc_events_tab_for_environment('live') === 'Events', 'Live muss Events verwenden.');
$assert(be_cc_events_tab_for_environment('test') === 'Events_Staging', 'Tests dürfen niemals auf Events schreiben.');

$blocked = false;
try {
    be_cc_events_tab_for_environment('unknown');
} catch (RuntimeException) {
    $blocked = true;
}
$assert($blocked, 'Unbekannte Events-Umgebungen müssen fail-closed blockiert werden.');

$source = [
    'title'=>'Bocholter Kulturtage 2026 - Kunstmarkt CityArt und künstlerische Mitmach-Stände für Kinder und Jugendliche',
    'date'=>'2026-08-30',
    'time'=>'11:00',
    'time_status'=>'fixed_time',
    'time_details'=>'11:00–18:00 Uhr',
    'city'=>'Bocholt',
    'location'=>'Markt vor dem Historischen Rathaus',
    'kategorie_suggestion'=>'Kinder & Familie',
    'source_url'=>'https://www.bocholt.de/veranstaltungskalender/cityart?event=90900',
    'description'=>'Offener Kunstmarkt mit Mitmachaktionen für Kinder und Jugendliche.',
    'visual_key'=>'art_exhibition_gallery',
];
$event = be_cc_staging_event_from_source($source);
$assert($event['id'] === 'bocholter-kulturtage-2026-kunstmarkt-cityart-und-kunstlerische-mitmach-stande-fur-kinder-und-jugendliche-2026-08-30', 'CityArt erhält dieselbe deterministische Event-ID wie der bestehende Importvertrag.');
$assert($event['time'] === '11:00–18:00 Uhr', 'Die vollständige offizielle Zeitspanne muss in Events_Staging landen.');
$assert($event['kategorie'] === 'Kinder & Familie', 'Die redaktionelle Kategorie wird übernommen.');
$assert($event['visual_key'] === 'art_exhibition_gallery', 'Der bestätigte Bildbereich wird übernommen.');
$assert(count(be_cc_staging_event_row($event)) === count(BE_CC_STAGING_EVENT_COLUMNS), 'Eventzeile entspricht dem kanonischen Header.');

be_cc_staging_event_validate_header(BE_CC_STAGING_EVENT_COLUMNS);
$headerBlocked = false;
try {
    be_cc_staging_event_validate_header(array_slice(BE_CC_STAGING_EVENT_COLUMNS, 0, -1));
} catch (RuntimeException) {
    $headerBlocked = true;
}
$assert($headerBlocked, 'Abweichende Events_Staging-Header müssen blockieren.');

$absent = be_cc_staging_event_resolution([], $event);
$assert(($absent['status'] ?? '') === 'absent', 'Ein neuer Event wird als fehlend erkannt.');

$tableRow = [];
foreach (BE_CC_STAGING_EVENT_COLUMNS as $column) $tableRow[be_cc_unicode_lower($column)] = $event[$column];
$tableRow['row_number'] = 2;
$resolved = be_cc_staging_event_resolution([$tableRow], $event);
$assert(($resolved['status'] ?? '') === 'resolved' && !empty($resolved['identical']), 'Identischer Retry muss idempotent bestätigt werden.');

$changed = $event;
$changed['description'] = 'Überarbeitete sachliche Beschreibung.';
$resolvedChanged = be_cc_staging_event_resolution([$tableRow], $changed);
$assert(($resolvedChanged['status'] ?? '') === 'resolved' && empty($resolvedChanged['identical']), 'Fachliche Änderung muss als kontrolliertes Update erkannt werden.');

$other = $tableRow;
$other['id'] = 'anderer-event';
$other['url'] = 'https://example.org/anderer-event';
$other['row_number'] = 3;
$conflicting = $event;
$conflicting['url'] = $other['url'];
$conflict = be_cc_staging_event_resolution([$tableRow, $other], $conflicting);
$assert(($conflict['status'] ?? '') === 'conflict', 'Widersprüchliche ID-/URL-Treffer müssen blockieren.');

$actionSource = (string)file_get_contents(dirname(__DIR__) . '/api/control-center/action.php');
$writerSource = (string)file_get_contents(dirname(__DIR__) . '/api/control-center/_staging_events_writeback.php');
$assert(str_contains($actionSource, "\$eventsTab === 'Events_Staging'"), 'Action-Endpunkt routet Staging nicht explizit auf Events_Staging.');
$assert(str_contains($actionSource, 'be_cc_writeback_staging_inbox_approve_verified'), 'Isolierter Staging-Writer ist nicht verdrahtet.');
$assert(str_contains($writerSource, "'events_tab'=>'Events_Staging'"), 'Writer weist das isolierte Ziel nicht aus.');
$assert(!str_contains($writerSource, "'Events!'"), 'Staging-Writer enthält einen hart verdrahteten gemeinsamen Events-Tab.');

if ($failures) {
    fwrite(STDERR, "=== Staging Events Isolation Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Staging Events Isolation Contract: OK ===\n";
