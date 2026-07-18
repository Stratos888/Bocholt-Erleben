<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_runtime_preflight.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$tempRoot = sys_get_temp_dir() . '/be-runtime-preflight-' . bin2hex(random_bytes(5));
mkdir($tempRoot . '/meta', 0777, true);
file_put_contents($tempRoot . '/meta/build.txt', "abc123def456\n");
file_put_contents($tempRoot . '/meta/deploy-manifest.json', json_encode([
    'schema'=>1,
    'build'=>'abc123def456',
    'environment'=>'staging',
    'files'=>[],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$build = be_cc_preflight_build_info($tempRoot);
$assert($build['build_present'] === true, 'Buildmarker muss erkannt werden.');
$assert($build['manifest_present'] === true, 'Deploymanifest muss erkannt werden.');
$assert($build['build_consistent'] === true, 'Buildmarker und Manifest müssen konsistent sein.');
$assert($build['manifest_environment'] === 'staging', 'Manifest-Environment muss sichtbar sein.');

$request = [
    'scheme'=>'https',
    'host'=>'staging.bocholt-erleben.de',
    'request_path'=>'/api/control-center/preflight.php',
    'method'=>'POST',
];
$context = be_cc_preflight_context_from_values('staging', $request, $build, '0123456789abcdef');
$assert(($context['resolved_environment'] ?? '') === 'staging', 'Staging muss als Staging aufgelöst werden.');
$assert(($context['inbox_tab'] ?? '') === 'Inbox_Staging', 'Staging muss Inbox_Staging verwenden.');
$assert(($context['events_tab'] ?? '') === 'Events_Staging', 'Staging muss Events_Staging verwenden.');
$assert(empty($context['blockers']), 'Ein konsistenter Staging-Kontext darf keine Blocker enthalten.');

$wrongHost = $request;
$wrongHost['host'] = 'bocholt-erleben.de';
$blockedContext = be_cc_preflight_context_from_values('staging', $wrongHost, $build, '0123456789abcdef');
$assert(!empty($blockedContext['blockers']), 'Ein Live-Host im Staging-Kontext muss fail-closed blockieren.');

$assert(
    be_cc_inbox_writer_name('approve', 'Events_Staging') === 'be_cc_writeback_staging_inbox_approve_verified',
    'Staging-Approve muss den isolierten Staging-Writer auswählen.'
);
$assert(
    be_cc_inbox_writer_name('approve', 'Events') === 'be_cc_writeback_inbox_approve_verified',
    'Live-Approve muss den bestehenden verifizierten Live-Writer auswählen.'
);
$assert(
    be_cc_inbox_writer_name('reject', 'Events_Staging') === 'be_cc_writeback_inbox_decision_direct',
    'Reject muss den direkten verifizierten Entscheidungs-Writer auswählen.'
);

/*
 * Der Planvertrag wird mit einem Activity-Kandidaten getestet, damit dieser
 * isolierte Test ausschließlich Runtime-, Resolver- und Mutationsfreiheit
 * prüft. Der separate Event-Review-Vertrag besitzt eigene vollständige Tests.
 */
$source = [
    'submission_kind'=>'activity',
    'id_suggestion'=>'cityart-2026',
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
    'final_description'=>'Offener Kunstmarkt mit Mitmachaktionen für Kinder und Jugendliche.',
    'visual_key'=>'art_exhibition_gallery',
];
$case = [
    'id'=>'case-cityart-runtime-preflight',
    'source_system'=>'inbox_feed',
    'source_reference'=>'sheet:Inbox_Staging:cityart-2026',
    'object_id'=>'cityart-2026',
    'title'=>'CityArt prüfen',
    'source_payload_json'=>json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
];
$sourceSnapshot = [
    'tab'=>'Inbox_Staging',
    'resolution_status'=>'resolved',
    'row_number'=>7,
    'fingerprint'=>'source-fingerprint',
];
$targetSnapshot = [
    'tab'=>'Events_Staging',
    'event_id'=>'cityart-2026',
    'resolution_status'=>'absent',
    'row_number'=>0,
    'identical'=>false,
];
$plan = be_cc_preflight_plan($case, 'approve', [], $context, $sourceSnapshot, $targetSnapshot);
$assert(($plan['mutation'] ?? true) === false, 'Der Preflight-Vertrag muss Mutation hart auf false setzen.');
$assert(($plan['allowed'] ?? false) === true, 'Der konsistente Staging-Plan muss freigegeben werden.');
$assert(($plan['writer'] ?? '') === 'be_cc_writeback_staging_inbox_approve_verified', 'Plan und Action müssen denselben Staging-Writer verwenden.');
$assert(($plan['resources']['source_tab'] ?? '') === 'Inbox_Staging', 'Preflight muss Inbox_Staging als Quelle ausweisen.');
$assert(($plan['resources']['target_tab'] ?? '') === 'Events_Staging', 'Preflight muss Events_Staging als Ziel ausweisen.');
$assert(($plan['resources']['live_inbox'] ?? '') === 'not_used', 'Staging-Preflight darf Live-Inbox nicht verwenden.');
$assert(($plan['resources']['live_events'] ?? '') === 'not_used', 'Staging-Preflight darf Live-Events nicht verwenden.');

$blockedPlan = be_cc_preflight_plan($case, 'approve', [], $blockedContext, $sourceSnapshot, $targetSnapshot);
$assert(($blockedPlan['allowed'] ?? true) === false, 'Ein Hostkonflikt muss den Gesamtplan blockieren.');

$endpointSource = (string)file_get_contents(dirname(__DIR__) . '/api/control-center/preflight.php');
$actionSource = (string)file_get_contents(dirname(__DIR__) . '/api/control-center/action.php');
$assert(str_contains($endpointSource, 'be_require_review_access'), 'Preflight-Endpunkt muss review-authentifiziert sein.');
$assert(str_contains($endpointSource, "REQUEST_METHOD") && str_contains($endpointSource, "POST"), 'Preflight-Endpunkt muss POST-only sein.');
$assert(!str_contains($endpointSource, 'be_cc_operation_reserve_persistent'), 'Preflight darf keine persistente Operation reservieren.');
$assert(!str_contains($endpointSource, 'be_cc_apply_action'), 'Preflight darf keinen lokalen Fallzustand verändern.');
$assert(str_contains($actionSource, 'be_cc_inbox_writer_name($action, $eventsTab)'), 'Action-Endpunkt muss denselben Writer-Resolver wie der Preflight verwenden.');

@unlink($tempRoot . '/meta/build.txt');
@unlink($tempRoot . '/meta/deploy-manifest.json');
@rmdir($tempRoot . '/meta');
@rmdir($tempRoot);

if ($failures) {
    fwrite(STDERR, "=== Runtime Preflight Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Runtime Preflight Contract: OK ===\n";
