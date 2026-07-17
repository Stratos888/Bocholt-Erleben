<?php
declare(strict_types=1);

function be_cc_column_letter(int $index): string
{
    $index++;
    $letters = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function be_cc_identity_text(mixed $value): string
{
    return strtolower(trim((string)$value));
}

require_once dirname(__DIR__) . '/api/control-center/_editorial_contracts.php';
require_once dirname(__DIR__) . '/api/control-center/_inbox_schema.php';
require_once dirname(__DIR__) . '/api/control-center/_inbox_decision_writeback.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$throws = static function(callable $callback, array $needles, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message . ' (keine Exception)';
    } catch (Throwable $error) {
        foreach ($needles as $needle) {
            if (!str_contains($error->getMessage(), $needle)) {
                $failures[] = $message . ' (Fehlender Diagnosebestandteil: ' . $needle . '; Ist: ' . $error->getMessage() . ')';
            }
        }
    }
};

$baseHeader = [
    'status','ablehnungsgrund','id_suggestion','title','date','endDate','time','city','location',
    'kategorie_suggestion','url','description','source_name','source_url','match_score','matched_event_id','notes','created_at',
];
$cityArtBase = [
    'review','','','Bocholter Kulturtage 2026 - Kunstmarkt CityArt und künstlerische Mitmach-Stände für Kinder und Jugendliche',
    '2026-08-30','','11:00','Bocholt','Markt vor dem Historischen Rathaus','Kinder & Familie',
    'https://www.bocholt.de/veranstaltungskalender/cityart?event=90900',
    'Offener Kunstmarkt mit Mitmachaktionen für Kinder und Jugendliche im Rahmen der Kulturtage.',
    'Stadt Bocholt','https://www.bocholt.de/veranstaltungskalender/cityart?event=90900','','',
    'staging acceptance; official time 11:00-18:00','2026-07-16 20:10:00',
];
$cityArtTail = ['art_exhibition_gallery','art_market','motif-gap-art-market-01','specific','fixed_time','11:00–18:00 Uhr'];

$throws(
    static fn() => be_cc_inbox_table_from_values([$baseHeader, array_merge($cityArtBase, $cityArtTail)], 'Inbox_Staging'),
    ['Inbox_Staging-Schema ungültig', 'S (Zeile 2)', 'X (Zeile 2)', 'unvollständiger fachlicher Payload'],
    'Werte unter leeren Headern müssen vor der Fallbildung fail-closed blockieren.'
);

$duplicateHeader = ['status','title','date','source_url','title'];
$duplicateRow = ['review','CityArt','2026-08-30','https://example.org/cityart','Doppelt'];
$throws(
    static fn() => be_cc_inbox_table_from_values([$duplicateHeader, $duplicateRow], 'Inbox_Staging'),
    ['Doppelte Header', 'title (B, E)'],
    'Doppelte Header müssen eindeutig diagnostiziert werden.'
);

$repairedHeader = array_merge($baseHeader, ['visual_key','visual_motif','visual_asset_id','visual_asset_role','time_status','time_details']);
$repairedTable = be_cc_inbox_table_from_values([$repairedHeader, array_merge($cityArtBase, $cityArtTail)], 'Inbox_Staging');
$assert(count($repairedTable['rows']) === 1, 'Reparierter CityArt-Rohzustand muss genau eine Zeile liefern.');
$repairedPayload = $repairedTable['rows'][0] ?? [];
unset($repairedPayload['_raw']);
$review = be_cc_event_candidate_review_contract($repairedPayload);
$assert(($review['facts']['time'] ?? '') === '11:00', 'CityArt-Beginnzeit muss aus der benannten Spalte gelesen werden.');
$assert(($review['facts']['time_status'] ?? '') === 'fixed_time', 'CityArt-Zeitstatus muss aus W gelesen werden.');
$assert(($review['facts']['time_details'] ?? '') === '11:00–18:00 Uhr', 'CityArt-Zeitspanne muss aus X gelesen werden.');
$assert(($review['visual']['key'] ?? '') === 'art_exhibition_gallery', 'CityArt-Visual-Key muss aus S gelesen werden.');
$assert(($review['visual']['motif'] ?? '') === 'art_market', 'CityArt-Motiv muss aus T gelesen werden.');
$assert(($review['visual']['asset_id'] ?? '') === 'motif-gap-art-market-01', 'CityArt-Asset muss aus U gelesen werden.');
$assert(!empty($review['decision_gate']['ready']), 'Vollständig reparierter CityArt-Datensatz muss entscheidungsreif sein.');

$blockedReview = be_cc_event_candidate_review_contract([
    'title'=>'CityArt ohne Visual','date'=>'2026-08-30','time'=>'11:00','city'=>'Bocholt','location'=>'Markt',
    'category'=>'Kultur','source_url'=>'https://example.org/cityart','description'=>'Lokaler Kunstmarkt.',
]);
$blockedActions = array_column((array)($blockedReview['actions'] ?? []), 'enabled', 'key');
$readyActions = array_column((array)($review['actions'] ?? []), 'enabled', 'key');
$assert(($blockedActions['edit_and_approve'] ?? true) === false, 'Gesamtbearbeitung darf bei offenen Blockern backendseitig nicht aktiviert sein.');
$assert(($readyActions['edit_and_approve'] ?? false) === true, 'Gesamtbearbeitung darf im Ready-Zustand aktiviert sein.');

$minimalValues = [
    ['status','title','date','id_suggestion','source_url'],
    ['review','CityArt','2026-08-30','cityart-2026','https://example.org/cityart'],
];
$minimalTable = be_cc_inbox_direct_table_from_values($minimalValues, 'Inbox_Staging');
$plan = be_cc_inbox_direct_canonical_plan($minimalTable, 2, ['time_status'=>'fixed_time','visual_asset_id'=>'motif-gap-art-market-01']);
$assert(count($plan['new_headers']) === 2, 'Kanonischer Writeback muss fehlende benannte Spalten weiterhin atomar planen.');
$assert(count($plan['data']) === 4, 'Header und Werte müssen in einem Batch geplant werden.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Inbox Schema Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Inbox Schema Contract: OK ===\n";
