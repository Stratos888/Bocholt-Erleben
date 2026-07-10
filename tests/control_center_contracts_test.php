<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_contracts.php';
require_once dirname(__DIR__) . '/api/control-center/_github_repo.php';
require_once dirname(__DIR__) . '/api/control-center/_workflow_contracts.php';

$failures = [];
$assert = static function(bool $condition, string $label) use (&$failures): void { if (!$condition) $failures[] = $label; };
$throws = static function(callable $callback, string $expected, string $label) use (&$failures): void {
    try { $callback(); $failures[] = $label . ' (keine Exception)'; }
    catch (Throwable $error) { if (!str_contains($error->getMessage(), $expected)) $failures[] = $label . ' (' . $error->getMessage() . ')'; }
};

$index = ['id'=>0,'title'=>1,'start_date'=>2,'enddate'=>3,'venue'=>4,'category'=>5,'url'=>6,'description'=>7];
$row = ['event-1','Alter Titel','2026-08-01','','Innenstadt','Kultur','https://example.org/alt','Alt'];
$plan = be_cc_validate_event_update($index, $row, ['title'=>'Neuer Titel','date'=>'2026-08-02','location'=>'TextilWerk','kategorie'=>'Musik & Bühne','source_url'=>'https://example.org/neu']);
$assert(($plan['location']['column_name'] ?? '') === 'venue', 'Alias location -> venue');
$assert(($plan['category']['column_name'] ?? '') === 'category', 'Alias kategorie -> category');
$assert(($plan['source_url']['column_name'] ?? '') === 'url', 'Alias source_url -> url');
$assert(($plan['date']['column_name'] ?? '') === 'start_date', 'Alias date -> start_date');
$throws(static fn() => be_cc_validate_event_update($index, $row, ['title'=>'']), 'Titel darf nicht leer sein', 'Leerer Titel wird verhindert');
$throws(static fn() => be_cc_validate_event_update($index, $row, ['date'=>'02.08.2026']), 'gültiges Startdatum', 'Ungültiges Datum wird verhindert');
$throws(static fn() => be_cc_validate_event_update($index, $row, ['source_url'=>'javascript:alert(1)']), 'Ungültige URL', 'Unsichere URL wird verhindert');
$throws(static fn() => be_cc_validate_event_update($index, $row, ['ticket_url'=>'https://tickets.example.org']), 'einer Sheet-Spalte', 'Nicht vorhandene Spalte erzeugt keinen falschen Erfolg');

$publicEvent = ['id'=>'event-1','eventName'=>'Neuer Titel','datum'=>'2026-08-02','ort'=>'TextilWerk','category'=>'Musik & Bühne','url'=>'https://example.org/neu'];
$publicOk = be_cc_compare_public_event_change($publicEvent, ['title'=>'Neuer Titel','date'=>'2026-08-02','location'=>'TextilWerk','kategorie'=>'Musik & Bühne','source_url'=>'https://example.org/neu'], ['title','date','location','category','source_url']);
$assert($publicOk['verified'] === true, 'Öffentliche Aliaswerte werden als bestätigt erkannt');
$publicMismatch = be_cc_compare_public_event_change($publicEvent, ['title'=>'Anderer Titel','date'=>'2026-08-02'], ['title','date']);
$assert($publicMismatch['verified'] === false, 'Abweichender öffentlicher Wert wird erkannt');
$assert(($publicMismatch['field'] ?? '') === 'title', 'Abweichendes Feld wird benannt');
$writtenOnly = be_cc_compare_public_event_change($publicEvent, ['title'=>'Neuer Titel','ticket_url'=>'https://tickets.example.org'], ['title']);
$assert($writtenOnly['verified'] === true, 'Nicht geschriebene optionale Felder blockieren die Bestätigung nicht');

$activity = be_cc_normalize_activity_updates(['title'=>'Aasee erleben','description'=>'Aktualisiert','url'=>'https://www.bocholt.de/aasee','unsupported'=>'ignorieren']);
$assert(($activity['title'] ?? '') === 'Aasee erleben', 'Aktivitätstitel wird normalisiert');
$assert(!array_key_exists('unsupported', $activity), 'Nicht erlaubte Aktivitätsfelder werden verworfen');
$throws(static fn() => be_cc_normalize_activity_updates(['title'=>'']), 'Titel darf nicht leer sein', 'Leerer Aktivitätstitel wird verhindert');
$throws(static fn() => be_cc_normalize_activity_updates(['url'=>'javascript:alert(1)']), 'Ungültige Aktivitäts-URL', 'Unsichere Aktivitäts-URL wird verhindert');
$assert(be_cc_activity_writeback_available() === false, 'Aktivitätseditor bleibt ohne explizite Serverfreigabe gesperrt');
$assert(in_array(be_cc_activity_writeback_status()['code'], ['not_configured','not_enabled'], true), 'Aktivitäts-Gate erklärt den Sperrgrund');

$lifecycle = [
    ['start','open','in_progress'], ['wait','in_progress','waiting'], ['resume','waiting','open'],
    ['block','open','blocked'], ['resume','blocked','open'], ['snooze','open','snoozed'],
    ['resume','snoozed','open'], ['complete','open','done'], ['reopen','done','open'],
    ['convert_to_task','new','open'], ['approve','decision_required','done'], ['reject','new','rejected'],
];
foreach ($lifecycle as [$action,$from,$expected]) {
    $assert(be_cc_transition_target($action, $from) === $expected, "Übergang {$action}: {$from} -> {$expected}");
}
$throws(static fn() => be_cc_transition_target('approve','blocked'), 'not allowed', 'Unzulässiger Übergang wird verhindert');
$throws(static fn() => be_cc_transition_target('unknown','open'), 'Unknown action', 'Unbekannte Aktion wird verhindert');

$baseline = be_cc_development_assessment(['missing_description'=>0,'missing_source'=>0,'missing_date'=>0,'open_quality_reviews'=>0], ['blocked_tasks'=>0], false);
$assert($baseline['status'] === 'baseline', 'Ohne Zeitreihe kein erfundener Stabilitätstrend');
$assert($baseline['trend_available'] === false, 'Trenddaten ehrlich als fehlend markiert');
$attention = be_cc_development_assessment(['missing_description'=>2,'missing_source'=>1,'missing_date'=>0,'open_quality_reviews'=>3], ['blocked_tasks'=>1], false);
$assert($attention['status'] === 'attention', 'Aktuelle Qualitätsprobleme erzeugen Aufmerksamkeit');

$assert(be_cc_publication_result(false,false,false)['state'] === 'failed', 'Quellfehler bleibt Fehler');
$assert(be_cc_publication_result(true,false,false)['state'] === 'blocked', 'Deployfehler wird blockiert');
$assert(be_cc_publication_result(true,true,false)['state'] === 'waiting', 'Unbestätigte Veröffentlichung bleibt wartend');
$assert(be_cc_publication_result(true,true,true)['state'] === 'confirmed', 'Nur bestätigte Wirkung gilt als veröffentlicht');

if ($failures) {
    fwrite(STDERR, "=== Control Center Contract Simulation: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}
echo "=== Control Center Contract Simulation: OK ===\n";
