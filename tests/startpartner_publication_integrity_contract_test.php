<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$eventReader = (string)file_get_contents($root . '/api/events/public.php');
$activityReader = (string)file_get_contents($root . '/api/activities/public.php');
$lifecycle = (string)file_get_contents($root . '/api/startpartner/_gate4_lifecycle_domain.php');
$state = (string)file_get_contents($root . '/api/startpartner/_gate4_state.php');
$projection = (string)file_get_contents($root . '/api/startpartner/_gate4_projection.php');
$control = (string)file_get_contents($root . '/js/control-center/startpartner-gate4.js');
$activityAdapter = (string)file_get_contents($root . '/js/activity-submission-feed.js');
$activityPage = (string)file_get_contents($root . '/aktivitaeten/index.html');

$failures = [];
$assert = static function(bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};

foreach ([
    'event' => $eventReader,
    'activity' => $activityReader,
] as $kind => $reader) {
    $assert(str_contains($reader, 's.status = :status'), "Public {$kind} reader must require canonical approved submission status.");
    $assert(str_contains($reader, 's.approved_at IS NOT NULL'), "Public {$kind} reader must require approval evidence.");
    $assert(str_contains($reader, 'NOT EXISTS ('), "Public {$kind} reader must preserve normal approved submissions without a Startpartner link.");
    $assert(str_contains($reader, 'startpartner_pilot_content_links'), "Public {$kind} reader must inspect Startpartner content links.");
    $assert(str_contains($reader, 'startpartner_pilots'), "Public {$kind} reader must inspect Startpartner lifecycle state.");
    $assert(str_contains($reader, 'pcl.status = "approved"'), "Public {$kind} reader must require an approved Startpartner content link.");
    $assert(str_contains($reader, 'sp.status IN ("active", "paused", "closing")'), "Public {$kind} reader must hide terminal Startpartner content while retaining pause/closing visibility.");
    foreach (['INSERT INTO', 'UPDATE submissions', 'DELETE FROM', 'be_send_mail(', 'stripe_'] as $forbidden) {
        $assert(!str_contains($reader, $forbidden), "Public {$kind} reader must remain read-only: {$forbidden}");
    }
}

$assert(str_contains($lifecycle, "'publication_effect' => 'public_projection_eligible'"), 'Approval audit must describe public projection eligibility.');
$assert(str_contains($lifecycle, "? 'public_projection_withdrawn'"), 'Withdrawal audit must describe the end of public projection for previously approved content.');
$assert(str_contains($lifecycle, "'pause', 'resume', 'start_closeout' => 'approved_content_retained'"), 'Pause/resume/closeout audit must document retention of already approved public content.');
$assert(str_contains($lifecycle, "'end_without_conversion', 'terminate' => 'active_startpartner_public_projection_ended'"), 'Terminal lifecycle audit must document the end of active Startpartner public projection.');
$assert(str_contains($lifecycle, 'historical_usage_retained'), 'Withdrawal must retain historical usage evidence.');
$assert(str_contains($lifecycle, "WHERE pilot_id = :pilot_id AND status IN ('draft','editorial_ready')"), 'Terminal transition must not rewrite historical approved content links merely to hide them publicly.');

$assert(str_contains($state, '&& $today >= $plannedEndDate;'), 'Backend state must own closeout due semantics on the planned end date.');
$assert(str_contains($state, "['due', 'blocked']"), 'Backend state must own due and blocked distribution priority.');
$assert(str_contains($state, "'distribution_blocked'"), 'Backend state must expose a canonical blocked-distribution next-action code.');
$distributionPos = strpos($state, "'distribution_blocked'");
$contentPos = strpos($state, "'content_review'");
$assert($distributionPos !== false && $contentPos !== false && $distributionPos < $contentPos, 'Backend next-action priority must evaluate distribution before active content review.');

$assert(!str_contains($projection, '$today >= $plannedEnd'), 'Control-case projection must not re-derive planned-end next-action semantics.');
$assert(str_contains($projection, "$next = is_array(\$gate4['next_action'] ?? null)"), 'Control-case projection must consume the backend next action.');

$assert(!str_contains($control, 'function plannedEndDue'), 'Control Center must not re-derive planned-end lifecycle semantics.');
$assert(str_contains($control, "if(['onboarding','activation_ready'].includes(phase))return preactivationNextAction(gate4);"), 'Control Center may keep preactivation presentation helpers only for preactive phases.');
$assert(str_contains($control, "return gate4.next_action||{code:'onboarding',label:gate4PriorityMessage(gate4)};"), 'Active-pilot Control Center must render the backend next action instead of recomputing lifecycle priority.');
$assert(str_contains($control, 'Freigabe mit echter Sichtbarkeitswirkung'), 'Operator approval copy must disclose the real public projection effect.');

$assert(str_contains($activityAdapter, 'fetch("/api/activities/public.php"'), 'Activity UI must load the canonical approved-submission projection.');
$assert(str_contains($activityAdapter, 'curated activity feed remains active'), 'Activity submission projection must fail soft without breaking the curated feed.');
$assert(str_contains($activityPage, '/js/activity-submission-feed.js?v=2026-08-26-publication-integrity-v1'), 'Activity page must load the approved-submission feed adapter with a versioned asset key.');

foreach ([$lifecycle, $state, $projection] as $domainSource) {
    foreach (['stripe_checkout', 'stripe_subscription', 'INSERT INTO subscriptions', 'UPDATE subscriptions'] as $forbidden) {
        $assert(!str_contains($domainSource, $forbidden), "Product-integrity work must not introduce payment/subscription side effects: {$forbidden}");
    }
}

if ($failures) {
    fwrite(STDERR, "=== Startpartner Publication Integrity Contract: FAILED ===\n" . implode("\n", array_map(static fn(string $v): string => '- ' . $v, $failures)) . "\n");
    exit(1);
}

echo "=== Startpartner Publication Integrity Contract: OK ===\n";
