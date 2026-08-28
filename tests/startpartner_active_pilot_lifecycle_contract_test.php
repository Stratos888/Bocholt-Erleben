<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lifecycle = file_get_contents($root . '/api/startpartner/_gate4_lifecycle_domain.php');
$contract = file_get_contents($root . '/api/startpartner/_gate4_contract.php');
$state = file_get_contents($root . '/api/startpartner/_gate4_state.php');
$portal = file_get_contents($root . '/api/startpartner/_gate4_portal_domain.php');
$projection = file_get_contents($root . '/api/startpartner/_gate4_projection.php');
$schema = file_get_contents($root . '/api/startpartner/_gate4_schema.php');
$endpoint = file_get_contents($root . '/api/startpartner/lifecycle.php');
$manifest = file_get_contents($root . '/api/sql/000_manifest.json');

$failures = [];
$assert = static function(bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$assert(str_contains($endpoint, 'be_require_review_access()'), 'Lifecycle endpoint must remain operator/review protected.');
$assert(str_contains($endpoint, 'be_startpartner_gate4_lifecycle_dispatch'), 'Lifecycle endpoint must delegate to the canonical dispatcher.');
$assert(str_contains($lifecycle, 'be_startpartner_gate4_run_operation'), 'Lifecycle mutations must reuse the revision/idempotency operation wrapper.');
$assert(str_contains($lifecycle, "'pause' => ['from' => ['active'], 'to' => 'paused'"), 'Active -> paused transition contract is missing.');
$assert(str_contains($lifecycle, "'resume' => ['from' => ['paused'], 'to' => 'active'"), 'Paused -> active transition contract is missing.');
$assert(str_contains($lifecycle, "'start_closeout' => ['from' => ['active', 'paused'], 'to' => 'closing'"), 'Closeout transition contract is missing.');
$assert(str_contains($lifecycle, "'end_without_conversion' => ['from' => ['closing'], 'to' => 'ended_without_conversion'"), 'Orderly end transition contract is missing.');
$assert(str_contains($lifecycle, "'terminate' => ['from' => ['active', 'paused', 'closing'], 'to' => 'terminated'"), 'Termination transition contract is missing.');
$assert(!preg_match("/'converted'\s*=>/", $lifecycle), 'Workpack #344 must not implement paid conversion.');

$assert(str_contains($lifecycle, 'INSERT INTO startpartner_pilot_usages'), 'Approval must write canonical pilot usage.');
$assert(str_contains($lifecycle, "status = 'approved'"), 'Approval must move the pilot content link to approved.');
$assert(str_contains($lifecycle, 'historical_usage_retained'), 'Withdrawal must explicitly retain historical usage.');
$assert(str_contains($lifecycle, "content_type = 'activity' AND status = 'approved'"), 'Activity concurrency must derive from approved pilot links.');
$assert(str_contains($contract, 'be_startpartner_gate4_pilot_month_window'), 'Calendar pilot-month helper is missing.');
$assert(str_contains($contract, "BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS = ['day_30', 'day_90', 'month_5', 'final']"), 'Checkpoint schedule contract is incomplete.');
$assert(!str_contains($contract, 'cal_days_in_month'), 'Lifecycle calendar arithmetic must not depend on the optional PHP Calendar extension.');
$assert(str_contains($contract, "modify('last day of this month')"), 'Lifecycle calendar arithmetic must use portable DateTime month-end calculation.');

require_once $root . '/api/startpartner/_gate4_contract.php';
$calendarCases = [
    ['2026-01-31', 1, '2026-02-28'],
    ['2024-01-31', 1, '2024-02-29'],
    ['2026-08-25', 6, '2027-02-25'],
];
foreach ($calendarCases as [$sourceDate, $months, $expectedDate]) {
    $actualDate = be_startpartner_gate4_add_calendar_months($sourceDate, $months);
    $assert(
        $actualDate === $expectedDate,
        "Portable calendar arithmetic failed for {$sourceDate} + {$months} month(s): expected {$expectedDate}, got {$actualDate}."
    );
}

$assert(
    str_contains($state, 'ml.consumed_at >= :accepted_at_magic_link')
        && str_contains($state, 's.created_at >= :accepted_at_session'),
    'Portal-access readback must use distinct named placeholders under native PDO prepares.'
);
$assert(
    str_contains($state, "'accepted_at_magic_link' => \$acceptedAt")
        && str_contains($state, "'accepted_at_session' => \$acceptedAt"),
    'Portal-access readback must bind both accepted-at placeholders explicitly.'
);
$assert(
    !str_contains($state, "ml.consumed_at >= :accepted_at\n           AND s.created_at >= :accepted_at"),
    'Portal-access readback must not reuse one named placeholder with native PDO prepares.'
);

$assert(str_contains($portal, 'be_startpartner_gate4_portal_payload_hash'), 'Portal replay must be payload bound.');
$assert(str_contains($portal, 'client_reference wurde bereits mit anderen Inhaltsdaten verwendet.'), 'Changed payload under the same client reference must fail closed.');
$assert(str_contains($portal, 'be_startpartner_gate4_portal_assert_active_capacity'), 'Active portal submit must check effective window and current limit state.');

foreach (['usage_observed', 'zero_usage', 'no_data_yet_or_too_short', 'query_or_attribution_problem'] as $measurementState) {
    $assert(str_contains($state, $measurementState), "Measurement state missing: {$measurementState}");
}
$assert(str_contains($state, 'metric_date < :today_local'), 'Measurement runtime must not classify the current incomplete day as zero usage.');
$assert(str_contains($state, 'closeout_required'), 'planned_end_date must produce an explicit closeout signal.');
$assert(str_contains($state, 'next_review_at'), 'Lifecycle readback must expose the next review owner.');
$assert(str_contains($projection, "'state' => \$projectedState"), 'Control-case projection must carry lifecycle state.');
$assert(str_contains($projection, "'done'"), 'Terminal pilot must close the existing control-case projection.');
$assert(str_contains($projection, 'be_startpartner_gate4_partner_next_action'), 'Partner projection must expose one canonical next action.');

$assert(str_contains($schema, "'onboarding','activation_ready','active','paused','closing'"), 'Paused and closing pilots must continue to occupy cohort capacity.');
$assert(!str_contains($schema, "'ended_without_conversion'"), 'Terminal ended pilots must not occupy cohort capacity.');
$assert(!str_contains($schema, "'terminated'"), 'Terminated pilots must not occupy cohort capacity.');

$manifestData = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
$migrationKeys = array_map(static fn(array $row): string => (string)$row['key'], (array)($manifestData['migrations'] ?? []));
$assert(!array_filter($migrationKeys, static fn(string $key): bool => str_starts_with($key, '013')), 'Lifecycle workpack must not introduce migration 013.');

$domain = $lifecycle . "\n" . $state . "\n" . $portal . "\n" . $projection;
foreach (['stripe_checkout', 'stripe_subscription', 'be_send_mail', 'publication_consumptions', 'INSERT INTO publication_entitlements', 'UPDATE publication_entitlements'] as $forbidden) {
    $assert(!str_contains($domain, $forbidden), "Active-pilot lifecycle contains forbidden side effect: {$forbidden}");
}

if ($failures) {
    fwrite(STDERR, "=== Startpartner Active-Pilot Lifecycle Contract: FAILED ===\n" . implode("\n", array_map(static fn(string $v): string => '- ' . $v, $failures)) . "\n");
    exit(1);
}

echo "=== Startpartner Active-Pilot Lifecycle Contract: OK ===\n";
