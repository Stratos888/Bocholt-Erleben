<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate3_domain.php';
require_once dirname(__DIR__) . '/api/startpartner/_gate3_presentation.php';

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
    'organization_name' => 'Bocholt Kulturverein',
    'organization_name_normalized' => be_startpartner_normalize_organization('Bocholt Kulturverein'),
    'desired_content_scope' => 'both',
];
$contact = [
    'contact_name' => 'Erika Beispiel',
    'email' => 'erika@example.org',
    'email_normalized' => 'erika@example.org',
];
$input = [
    'terms_version' => 'pilot-terms-v1',
    'terms_reference' => 'repo://docs/pilot-terms-v1',
    'terms_digest' => hash('sha256', 'pilot-terms-v1'),
    'accepting_person' => 'Erika Beispiel',
    'accepting_organization' => 'Bocholt Kulturverein',
    'accepted_at' => '2026-07-27T20:00:00+00:00',
    'confirmation_channel' => 'operator_recorded',
    'target_plan_keys' => ['active', 'activity_basic', 'active'],
    'cohort_key' => 'pilot-2026-a',
    'event_limit_per_pilot_month' => 8,
    'activity_concurrent_limit' => 1,
    'is_event_unlimited' => false,
    'source_care_text' => 'Freigegebene Vereinsquelle wird intern gepflegt.',
    'maintenance_scope_text' => 'Bocholt erleben übernimmt die vereinbarte Pilotpflege.',
    'reach_contribution_text' => 'Der Partner verweist auf die freigegebenen Inhalte.',
    'privacy_notice_version' => 'privacy-v1',
    'communication_notice_version' => 'communication-v1',
    'planned_activation_start' => '2026-08-10',
    'planned_activation_end' => '2027-02-10',
    'no_automatic_paid_renewal' => true,
];

$normalized = be_startpartner_gate3_normalize_confirmation($candidate, $contact, $input);
$assert($normalized['terms_digest'] === hash('sha256', 'pilot-terms-v1'), 'Terms digest must remain stable.');
$assert($normalized['target_plan_keys'] === ['active', 'activity_basic'], 'Both-scope target plan keys must retain active and activity_basic exactly once.');
$assert($normalized['event_limit_per_pilot_month'] === 8, 'Event limit must be preserved.');
$assert($normalized['activity_concurrent_limit'] === 1, 'Activity limit must be preserved.');
$assert($normalized['planned_activation_start'] === '2026-08-10', 'Planned start must remain a date only.');
$assert($normalized['planned_activation_end'] === '2027-02-10', 'Planned end must remain a date only.');

$eventsOnly = $candidate;
$eventsOnly['desired_content_scope'] = 'events';
$eventsInput = $input;
$eventsInput['target_plan_keys'] = ['active'];
unset($eventsInput['activity_concurrent_limit']);
$eventsNormalized = be_startpartner_gate3_normalize_confirmation($eventsOnly, $contact, $eventsInput);
$assert($eventsNormalized['activity_concurrent_limit'] === null, 'Events-only candidates must not require an activity limit.');

$activitiesOnly = $candidate;
$activitiesOnly['desired_content_scope'] = 'activities';
$activitiesInput = $input;
$activitiesInput['target_plan_keys'] = ['activity_basic'];
unset($activitiesInput['event_limit_per_pilot_month']);
$activitiesNormalized = be_startpartner_gate3_normalize_confirmation($activitiesOnly, $contact, $activitiesInput);
$assert($activitiesNormalized['event_limit_per_pilot_month'] === null, 'Activities-only candidates must not require an event limit.');

$expect(
    static fn() => be_startpartner_gate3_normalize_confirmation(
        $candidate,
        $contact,
        array_merge($input, ['target_plan_keys' => ['active']])
    ),
    'Both-scope confirmation must reject a contract without activity_basic.'
);
$expect(
    static fn() => be_startpartner_gate3_normalize_confirmation(
        $candidate,
        $contact,
        array_merge($input, ['target_plan_keys' => ['activity_basic']])
    ),
    'Both-scope confirmation must reject a contract without active.'
);

$unlimitedInput = $input;
$unlimitedInput['is_event_unlimited'] = true;
unset($unlimitedInput['event_limit_per_pilot_month']);
$unlimited = be_startpartner_gate3_normalize_confirmation($candidate, $contact, $unlimitedInput);
$assert($unlimited['is_event_unlimited'] === true, 'Explicit unlimited event scope must be retained.');
$assert($unlimited['event_limit_per_pilot_month'] === null, 'Unlimited event scope must not carry a numeric limit.');

$sentTermsSnapshot = [
    'candidate_revision' => 7,
];
$presentationCandidate = [
    'id' => 'candidate-309-revision-regression',
    'organization_name' => 'Testpuper',
    'desired_content_scope' => 'both',
    'revision' => 7,
    'status' => 'accepted_pending_terms',
    'readiness' => [],
    'capacity' => [],
    'assigned_to' => null,
    'next_review_at' => null,
    'gate3' => ['complete' => false, 'blockers' => []],
    'contacts' => [[
        'contact_name' => 'Erika Beispiel',
        'email' => 'erika@example.org',
        'is_primary' => 1,
    ]],
    'events' => [[
        'event_type' => 'pilot_terms_sent',
        'payload' => ['terms_snapshot' => $sentTermsSnapshot],
    ]],
];
$assert(
    be_startpartner_gate3_terms_were_sent($presentationCandidate) === true,
    'Terms sent for the current candidate revision must remain valid for presentation.'
);
$currentRevisionItem = be_startpartner_gate3_present_case(
    ['primary_action' => null, 'secondary_actions' => []],
    $presentationCandidate
);
$assert(
    ($currentRevisionItem['primary_action']['key'] ?? null) === 'confirm_pilot_terms_simple',
    'Current-revision terms must expose partner confirmation as the primary action.'
);

$stalePresentationCandidate = $presentationCandidate;
$stalePresentationCandidate['revision'] = 8;
$assert(
    be_startpartner_gate3_terms_were_sent($stalePresentationCandidate) === false,
    'Historical terms sent for an older candidate revision must be stale.'
);
$staleRevisionItem = be_startpartner_gate3_present_case(
    ['primary_action' => null, 'secondary_actions' => []],
    $stalePresentationCandidate
);
$assert(
    ($staleRevisionItem['primary_action']['key'] ?? null) === 'send_pilot_terms',
    'After a candidate revision change, the operator must be offered a fresh terms send.'
);
$assert(
    !in_array('resend_pilot_terms', array_column((array)($staleRevisionItem['secondary_actions'] ?? []), 'key'), true),
    'After a candidate revision change, retrying the stale terms snapshot must not be offered.'
);
$assert(
    ($staleRevisionItem['display_status'] ?? null) === 'Platz reserviert · Bedingungen offen',
    'After a candidate revision change, presentation must return to the terms-open state.'
);

$expect(
    static fn() => be_startpartner_gate3_digest('not-a-digest'),
    'Invalid terms digest must be rejected.'
);
$expect(
    static fn() => be_startpartner_gate3_normalize_confirmation(
        $candidate,
        $contact,
        array_merge($input, ['accepting_organization' => 'Andere Organisation'])
    ),
    'Accepting organization mismatch must be rejected.'
);
$expect(
    static fn() => be_startpartner_gate3_normalize_confirmation(
        $candidate,
        $contact,
        array_merge($input, ['no_automatic_paid_renewal' => false])
    ),
    'Missing no-automatic-renewal acknowledgement must be rejected.'
);
$expect(
    static fn() => be_startpartner_gate3_normalize_confirmation(
        $candidate,
        $contact,
        array_merge($input, [
            'planned_activation_start' => '2027-02-11',
            'planned_activation_end' => '2027-02-10',
        ])
    ),
    'Invalid planned activation window must be rejected.'
);
$expect(
    static fn() => be_startpartner_gate3_plan_keys(['bad key']),
    'Invalid target-plan key must be rejected.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-3 Domain Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-3 Domain Contract: OK ===\n";
