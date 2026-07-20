<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_runtime_preflight.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$tempRoot = sys_get_temp_dir() . '/be-preflight-' . bin2hex(random_bytes(5));
mkdir($tempRoot . '/meta', 0777, true);
file_put_contents($tempRoot . '/meta/build.txt', "abc123def456\n");
file_put_contents($tempRoot . '/meta/deploy-manifest.json', json_encode([
    'schema'=>1,
    'build'=>'abc123def456',
    'environment'=>'staging',
    'files'=>[],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$build = be_cc_preflight_build_info($tempRoot);
$assert($build['build_consistent'] === true, 'Buildmarker und Manifest müssen konsistent sein.');

$request = [
    'scheme'=>'https',
    'host'=>'staging.bocholt-erleben.de',
    'request_path'=>'/api/control-center/preflight.php',
    'method'=>'POST',
];
$context = be_cc_preflight_context_from_values('staging', $request, $build, '0123456789abcdef');
$assert(($context['resolved_environment'] ?? '') === 'staging', 'Staging muss sicher aufgelöst werden.');
$assert(($context['inbox_tab'] ?? '') === 'Inbox_Staging', 'Staging muss Inbox_Staging verwenden.');
$assert(($context['events_tab'] ?? '') === 'Events_Staging', 'Staging muss Events_Staging verwenden.');
$assert(empty($context['blockers']), 'Ein konsistenter Staging-Kontext darf keine Blocker enthalten.');

$wrongHost = $request;
$wrongHost['host'] = 'bocholt-erleben.de';
$blockedContext = be_cc_preflight_context_from_values('staging', $wrongHost, $build, '0123456789abcdef');
$assert(!empty($blockedContext['blockers']), 'Ein Live-Host im Staging-Kontext muss blockieren.');

$assert(
    be_cc_inbox_writer_name('approve', 'Events_Staging') === 'be_cc_writeback_staging_inbox_approve_verified',
    'Staging-Approve muss den isolierten Staging-Writer auswählen.'
);
$assert(
    be_cc_inbox_writer_name('approve', 'Events') === 'be_cc_writeback_inbox_approve_verified',
    'Live-Approve muss den verifizierten Live-Writer auswählen.'
);

$source = [
    'submission_kind'=>'event',
    'id_suggestion'=>'synthetic-contract-event',
    'title'=>'Synthetischer Contract-Test',
    'date'=>'2026-12-20',
    'time'=>'19:00',
    'time_status'=>'fixed_time',
    'time_details'=>'19:00–20:00 Uhr',
    'city'=>'Bocholt',
    'location'=>'Testort',
    'kategorie_suggestion'=>'Kultur & Kunst',
    'source_url'=>'https://example.invalid/synthetic-contract-event',
    'description'=>'Vollständiger synthetischer Datensatz für den lokalen Preflight-Vertrag.',
    'final_description'=>'Vollständiger synthetischer Datensatz für den lokalen Preflight-Vertrag.',
    'visual_key'=>'art_exhibition_gallery',
];
$case = [
    'id'=>'case-synthetic-contract',
    'source_system'=>'inbox_feed',
    'source_reference'=>'sheet:Inbox_Staging:synthetic-contract-event',
    'object_id'=>'synthetic-contract-event',
    'title'=>'Synthetischen Testfall prüfen',
    'source_payload_json'=>json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
];
$sourceSnapshot = [
    'tab'=>'Inbox_Staging',
    'resolution_status'=>'resolved',
    'row_number'=>2,
    'fingerprint'=>'source-fingerprint',
];
$targetSnapshot = [
    'tab'=>'Events_Staging',
    'event_id'=>'synthetic-contract-event',
    'resolution_status'=>'absent',
    'row_number'=>0,
    'identical'=>false,
];

$plan = be_cc_preflight_plan($case, 'approve', [], $context, $sourceSnapshot, $targetSnapshot);
$assert(($plan['mutation'] ?? true) === false, 'Preflight muss read-only sein.');
$assert(($plan['allowed'] ?? false) === true, 'Der konsistente Staging-Plan muss erlaubt sein.');
$assert(($plan['writer'] ?? '') === 'be_cc_writeback_staging_inbox_approve_verified', 'Plan und Action müssen denselben Writer verwenden.');
$assert(($plan['resources']['source_tab'] ?? '') === 'Inbox_Staging', 'Quelle muss Inbox_Staging sein.');
$assert(($plan['resources']['target_tab'] ?? '') === 'Events_Staging', 'Ziel muss Events_Staging sein.');
$assert(($plan['resources']['live_inbox'] ?? '') === 'not_used', 'Staging-Preflight darf Live-Inbox nicht verwenden.');
$assert(($plan['resources']['live_events'] ?? '') === 'not_used', 'Staging-Preflight darf Live-Events nicht verwenden.');

$blockedPlan = be_cc_preflight_plan($case, 'approve', [], $blockedContext, $sourceSnapshot, $targetSnapshot);
$assert(($blockedPlan['allowed'] ?? true) === false, 'Ein Hostkonflikt muss den Plan blockieren.');

$endpointSource = (string)file_get_contents(dirname(__DIR__) . '/api/control-center/preflight.php');
$actionSource = (string)file_get_contents(dirname(__DIR__) . '/api/control-center/action.php');
$assert(str_contains($endpointSource, 'be_require_review_access'), 'Preflight-Endpunkt muss authentifiziert sein.');
$assert(str_contains($endpointSource, 'REQUEST_METHOD') && str_contains($endpointSource, 'POST'), 'Preflight-Endpunkt muss POST-only sein.');
$assert(str_contains($endpointSource, 'Case and action are required.'), 'Preflight benötigt einen konkreten Fall und eine Aktion.');
$assert(!str_contains($endpointSource, "mode === 'runtime'"), 'Temporärer globaler Runtime-Modus muss entfernt sein.');
$assert(!str_contains($endpointSource, 'be_cc_operation_reserve_persistent'), 'Preflight darf keine Operation reservieren.');
$assert(!str_contains($endpointSource, 'be_cc_apply_action'), 'Preflight darf keinen Fallzustand verändern.');
$assert(str_contains($actionSource, 'be_cc_inbox_writer_name($action, $eventsTab)'), 'Action und Preflight müssen denselben Writer-Resolver verwenden.');

@unlink($tempRoot . '/meta/build.txt');
@unlink($tempRoot . '/meta/deploy-manifest.json');
@rmdir($tempRoot . '/meta');
@rmdir($tempRoot);

if ($failures) {
    fwrite(STDERR, "=== Preflight Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Preflight Contract: OK ===\n";
