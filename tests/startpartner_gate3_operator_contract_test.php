<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate3_delivery_retry.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$expect = static function(callable $callback, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message;
    } catch (InvalidArgumentException|DomainException $expected) {
    }
};

$candidate = [
    'id' => 'candidate-309',
    'organization_name' => 'Bocholt Kulturverein',
    'desired_content_scope' => 'both',
    'privacy_policy_version' => 'privacy-v1',
    'revision' => 7,
];
$contact = [
    'contact_name' => 'Erika Beispiel',
    'email' => 'erika@example.org',
    'is_primary' => 1,
];

$assert(function_exists('be_startpartner_gate3_resend_terms'), 'Controlled Gate-3 resend function must exist.');

$snapshot = be_startpartner_gate3_terms_snapshot($candidate);
$assert($snapshot['terms_version'] === BE_STARTPARTNER_GATE3_TERMS_VERSION, 'Canonical terms version must be system owned.');
$assert(preg_match('/^[0-9a-f]{64}$/', (string)$snapshot['terms_digest']) === 1, 'Canonical terms digest must be a SHA-256.');
$assert($snapshot['target_plan_keys'] === ['active', 'activity_basic'], 'Combined scope must derive the two standard target plans.');
$assert($snapshot['event_limit_per_pilot_month'] === 8, 'Combined scope must derive 8 events per pilot month.');
$assert($snapshot['activity_concurrent_limit'] === 1, 'Combined scope must derive one concurrent activity.');
$assert($snapshot['planned_activation_start'] === null && $snapshot['planned_activation_end'] === null, 'Gate 3 must not pre-plan the six-month activation window.');
$assert($snapshot['no_automatic_paid_renewal'] === true, 'No automatic paid renewal must be part of the canonical sent terms.');
$assert(count((array)$snapshot['terms_clauses']) >= 6, 'The exact sent terms clauses must be part of the digested snapshot.');

$confirmation = be_startpartner_gate3_simple_confirmation_input(
    $candidate,
    $contact,
    $snapshot,
    [
        'partner_acceptance_confirmed' => true,
        'confirmation_channel' => 'email_reply',
        'accepted_at' => '2026-08-21T13:15:00+00:00',
        'terms_version' => 'operator-must-not-override-this',
        'event_limit_per_pilot_month' => 999,
        'planned_activation_start' => '2026-09-01',
    ]
);
$assert($confirmation['terms_version'] === BE_STARTPARTNER_GATE3_TERMS_VERSION, 'Operator input must not override canonical terms version.');
$assert($confirmation['event_limit_per_pilot_month'] === 8, 'Operator input must not override the standard event limit.');
$assert($confirmation['planned_activation_start'] === '', 'Simplified confirmation must keep Gate-3 activation start empty.');
$assert($confirmation['planned_activation_end'] === '', 'Simplified confirmation must keep Gate-3 activation end empty.');
$assert($confirmation['accepting_person'] === 'Erika Beispiel', 'Accepting person must derive from the primary contact.');
$assert($confirmation['accepting_organization'] === 'Bocholt Kulturverein', 'Accepting organization must derive from the candidate.');
$assert($confirmation['no_automatic_paid_renewal'] === true, 'Acceptance must inherit the no-auto-paid-renewal term.');
$assert($confirmation['confirmation_channel'] === 'email_reply', 'Default operator path must preserve the explicit confirmation channel.');

$changedCandidate = $candidate;
$changedCandidate['revision'] = 8;
$expect(
    static fn() => be_startpartner_gate3_simple_confirmation_input(
        $changedCandidate,
        $contact,
        $snapshot,
        ['partner_acceptance_confirmed' => true]
    ),
    'A candidate changed after terms send must require a new terms send.'
);
$expect(
    static fn() => be_startpartner_gate3_simple_confirmation_input(
        $candidate,
        $contact,
        $snapshot,
        ['partner_acceptance_confirmed' => false]
    ),
    'Missing explicit operator acknowledgement must be rejected.'
);

$eventsCandidate = $candidate;
$eventsCandidate['contacts'] = [$contact];
$eventsCandidate['events'] = [[
    'event_type' => 'pilot_terms_sent',
    'payload' => ['terms_snapshot' => $snapshot],
]];
require_once dirname(__DIR__) . '/api/startpartner/_gate3_presentation.php';
$item = be_startpartner_gate3_present_case([
    'primary_action' => null,
    'secondary_actions' => [],
], array_merge($eventsCandidate, [
    'status' => 'accepted_pending_terms',
    'readiness' => [],
    'capacity' => [],
    'assigned_to' => null,
    'next_review_at' => null,
    'gate3' => ['complete' => false, 'blockers' => []],
]));
$assert(($item['primary_action']['key'] ?? null) === 'confirm_pilot_terms_simple', 'After terms send, the primary action must be simplified partner confirmation.');
$retryActions = array_values(array_filter(
    (array)($item['secondary_actions'] ?? []),
    static fn(array $action): bool => ($action['key'] ?? null) === 'resend_pilot_terms'
));
$assert(count($retryActions) === 1, 'After terms send, exactly one controlled resend action must be available.');
$assert(str_contains((string)($retryActions[0]['label'] ?? ''), 'erika@example.org'), 'Resend action must expose the current target address.');
$assert(($item['decision_context']['gate3_delivery']['transport_status'] ?? null) === 'smtp_accepted', 'Readback must distinguish SMTP acceptance from external delivery.');
$assert(($item['decision_context']['gate3_delivery']['smtp_data_code'] ?? null) === 250, 'Readback must expose final SMTP DATA acceptance code 250.');

$resentCandidate = $eventsCandidate;
$resentCandidate['events'] = [[
    'event_type' => 'pilot_terms_resent',
    'payload' => [
        'terms_snapshot' => $snapshot,
        'recipient_address' => 'erika@example.org',
        'transport_status' => 'smtp_accepted',
        'smtp_data_code' => 250,
    ],
]];
$itemResent = be_startpartner_gate3_present_case([
    'primary_action' => null,
    'secondary_actions' => [],
], array_merge($resentCandidate, [
    'status' => 'accepted_pending_terms',
    'readiness' => [],
    'capacity' => [],
    'assigned_to' => null,
    'next_review_at' => null,
    'gate3' => ['complete' => false, 'blockers' => []],
]));
$assert(($itemResent['primary_action']['key'] ?? null) === 'confirm_pilot_terms_simple', 'A successful resend must remain in confirmation-waiting state.');

$unsent = $eventsCandidate;
$unsent['events'] = [];
$itemUnsent = be_startpartner_gate3_present_case([
    'primary_action' => null,
    'secondary_actions' => [],
], array_merge($unsent, [
    'status' => 'accepted_pending_terms',
    'readiness' => [],
    'capacity' => [],
    'assigned_to' => null,
    'next_review_at' => null,
    'gate3' => ['complete' => false, 'blockers' => []],
]));
$assert(($itemUnsent['primary_action']['key'] ?? null) === 'send_pilot_terms', 'Before terms send, the primary action must be send_pilot_terms.');
$assert(
    !in_array('resend_pilot_terms', array_column((array)($itemUnsent['secondary_actions'] ?? []), 'key'), true),
    'Before a successful terms send, no resend action may be exposed.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-3 Operator Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-3 Operator Contract: OK ===\n";
