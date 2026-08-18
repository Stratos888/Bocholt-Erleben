<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$domain = $read('api/startpartner/_gate3_domain.php');
$action = $read('api/startpartner/action.php');
$pilot = $read('api/startpartner/pilot.php');
$migration = $read('api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql');
$presentation = $read('api/startpartner/_gate3_presentation.php');
$frontend = $read('js/control-center/startpartner-review.js');

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach ([$action, $pilot] as $endpoint) {
    $assert(str_contains($endpoint, 'be_require_review_access'), 'Every Gate-3 endpoint must remain review protected.');
}
foreach (['confirm_pilot_terms', 'be_startpartner_gate3_confirm', 'be_startpartner_gate3_guard_gate2_action'] as $marker) {
    $assert(str_contains($action, $marker), "Protected action endpoint marker missing: {$marker}");
}
foreach (['pending_activation', 'no_automatic_paid_renewal', 'operation_id', 'payload_hash', 'expected_revision'] as $marker) {
    $assert(str_contains($domain, $marker), "Gate-3 domain boundary missing: {$marker}");
}
foreach (['Pilot-Onboarding', 'Bedingungen bestätigen und Pilot anlegen', 'confirm_pilot_terms'] as $marker) {
    $assert(str_contains($presentation . $frontend, $marker), "Gate-3 presentation marker missing: {$marker}");
}

$forbiddenDomain = [
    'mail(',
    'be_organizer_portal_send_magic_link',
    'INSERT INTO organizer_magic_links',
    'INSERT INTO organizer_portal_sessions',
    'INSERT INTO subscriptions',
    'INSERT INTO submissions',
    'INSERT INTO publication_entitlements',
    'INSERT INTO publication_consumptions',
    'stripe_checkout',
    'checkout.session',
];
foreach ($forbiddenDomain as $marker) {
    $assert(!str_contains($domain, $marker), "Gate-3 domain contains forbidden side effect: {$marker}");
}

foreach ([
    'ALTER TABLE subscriptions',
    'ALTER TABLE organizers',
    'ALTER TABLE publication_entitlements',
    'ALTER TABLE publication_consumptions',
    'CREATE TABLE IF NOT EXISTS subscriptions',
    'CREATE TABLE IF NOT EXISTS publication_entitlements',
] as $marker) {
    $assert(!str_contains($migration, $marker), "Migration 011 changes a locked owner: {$marker}");
}
foreach ([
    'startpartner_pilot_terms_acceptances',
    'startpartner_pilots',
    'startpartner_pilot_scopes',
    'startpartner_pilot_entitlements',
    'startpartner_pilot_events',
] as $table) {
    $assert(str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"), "Migration 011 owner missing: {$table}");
}

$assert(
    str_contains($migration, "status ENUM('pending_activation','active','paused','ended','revoked')"),
    'Pilot entitlement status contract is missing.'
);
$assert(
    str_contains($migration, "status <> 'pending_activation'")
        && str_contains($migration, 'starts_at IS NULL AND ends_at IS NULL'),
    'Pending pilot entitlements must remain without an active period.'
);
$assert(
    str_contains($domain, "'status' => 'pending_activation'")
        || str_contains($domain, "'pending_activation'"),
    'Domain must create a pending_activation pilot entitlement.'
);
$assert(
    !str_contains($frontend, 'Pilot aktiv') && !str_contains($frontend, 'Aufgenommen'),
    'Frontend must not overclaim activation.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-3 Side-Effect Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-3 Side-Effect Contract: OK ===\n";
