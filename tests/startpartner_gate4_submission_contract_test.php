<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$domain = '';
foreach (glob($root . '/api/startpartner/_gate4_*.php') ?: [] as $file) {
    $domain .= file_get_contents($file) . "\n";
}
$content = (string)file_get_contents($root . '/api/startpartner/content.php');
$onboarding = (string)file_get_contents($root . '/api/startpartner/onboarding.php');
$portal = (string)file_get_contents($root . '/api/organizer-portal/pilot.php');
$dashboard = (string)file_get_contents($root . '/js/organizer-pilot.js');
$controlCenter = (string)file_get_contents($root . '/js/control-center/startpartner-gate4.js');
$submissionSource = (string)file_get_contents($root . '/api/control-center/_submission_source.php');
$submissionWriteback = (string)file_get_contents($root . '/api/control-center/_submission_writeback.php');
$normalApproval = (string)file_get_contents($root . '/api/submissions/approve.php');
$presentation = (string)file_get_contents($root . '/api/startpartner/_gate3_presentation.php');

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};

foreach ([
    "payment_kind' => 'startpartner_pilot'",
    "status' => 'in_review'",
    "mail_effect' => 'none'",
    "stripe_effect' => 'none'",
    'startpartner_pilot_content_links',
] as $marker) {
    $assert(str_contains($domain, $marker), "Pilot submission contract missing: {$marker}");
}

$assert(str_contains($content, 'be_startpartner_gate4_portal_session'), 'Pilot content endpoint must require the existing portal session.');
$assert(!str_contains($content, 'be_send_mail') && !str_contains($content, 'stripe'), 'Pilot content endpoint must not send mail or invoke Stripe.');
$assert(str_contains($domain, 'be_startpartner_gate3_scope_target_plan_key'), 'Gate 4 must derive requested models from the scope-specific Gate-3 contract.');
$assert(!str_contains($domain, 'targetPlans[0]'), 'Portal submissions must never use the first pilot target plan as a generic model.');
$assert(str_contains($domain, 'scope_target_plan_mismatch'), 'Gate-4 readiness must hard-block inconsistent persisted scope target plans.');
$assert(str_contains($domain, "'gate4.scope.repair'"), 'Gate 4 must expose an audited repair operation for persisted scope mappings.');
$assert(str_contains($onboarding, "'repair_scope_target_plans'"), 'The review-protected onboarding endpoint must route the scope repair action.');
$assert(str_contains($controlCenter, 'gate4:repair-scope'), 'Control Center must expose the repair only as an explicit operator action.');
$assert(str_contains($portal, 'be_startpartner_gate4_portal_candidate'), 'Portal status must read the canonical pilot owner.');
$assert(str_contains($dashboard, '/api/startpartner/content.php'), 'Organizer dashboard must use the permanent pilot submission endpoint.');
$assert(str_contains($dashboard, 'Die Einreichung ist kostenlos und löst keine Zahlung aus.'), 'Organizer UI must state the fail-closed payment boundary in plain language.');

// Startpartner pilot submissions are domain-owned and must never enter the generic paid-submission review path.
$assert(
    str_contains($submissionSource, 'be_cc_reconcile_domain_owned_startpartner_submission_cases'),
    'Control Center must reconcile stale generic cases for Startpartner pilot submissions.'
);
$assert(
    str_contains($submissionSource, "s.payment_kind='startpartner_pilot'")
        && str_contains($submissionSource, "COALESCE(payment_kind, \"\") <> \"startpartner_pilot\""),
    'Generic submission sync must exclude Startpartner pilot submissions while retaining cleanup of existing duplicates.'
);
$assert(
    str_contains($submissionSource, "'ownership' => 'startpartner_gate4'")
        && str_contains($submissionSource, "'reason' => 'domain_owned_projection'"),
    'Reconciliation must record why a generic Startpartner projection was retired.'
);

$paymentKindGuard = strpos($submissionWriteback, "if (\$paymentKind === 'startpartner_pilot')");
$genericApproveCall = strpos($submissionWriteback, '/api/submissions/approve.php');
$assert(
    $paymentKindGuard !== false && $genericApproveCall !== false && $paymentKindGuard < $genericApproveCall,
    'Generic submission writeback must fail closed for Startpartner pilot content before any normal paid approval call.'
);
$assert(
    str_contains($submissionWriteback, 'be_cc_submission_current_payment_kind'),
    'Startpartner routing guard must use authoritative database payment-kind readback rather than a stale case payload.'
);
$assert(
    str_contains($normalApproval, "trim((string)(\$submission['paid_at'] ?? '')) === ''"),
    'Normal paid-submission approval must retain its paid_at guard.'
);

// The canonical lifecycle keeps first_content reserved for ready/approved content; operator presentation may surface a draft.
$assert(
    str_contains($presentation, 'be_startpartner_gate3_present_operator_first_content')
        && str_contains($presentation, "['draft', 'editorial_ready', 'approved']"),
    'Control Center presentation must recognize a submitted draft as the first operator-visible pilot content.'
);
require_once $root . '/api/startpartner/_gate3_presentation.php';
$draftCandidate = [
    'gate4' => [
        'first_content' => null,
        'content_links' => [[
            'id' => 'draft-content-link',
            'status' => 'draft',
            'submission_id' => 51,
        ]],
    ],
];
$draftPresented = be_startpartner_gate3_present_operator_first_content($draftCandidate);
$assert(
    (string)($draftPresented['gate4']['first_content']['id'] ?? '') === 'draft-content-link',
    'Operator presentation must expose the submitted draft without changing canonical lifecycle persistence.'
);
$readyCandidate = [
    'gate4' => [
        'first_content' => ['id' => 'ready-content-link', 'status' => 'editorial_ready'],
        'content_links' => [['id' => 'draft-content-link', 'status' => 'draft']],
    ],
];
$readyPresented = be_startpartner_gate3_present_operator_first_content($readyCandidate);
$assert(
    (string)($readyPresented['gate4']['first_content']['id'] ?? '') === 'ready-content-link',
    'Operator presentation must never replace an authoritative ready first_content.'
);

if ($failures) {
    fwrite(STDERR, "=== Startpartner Gate-4 Submission Contract: FAILED ===\n" . implode("\n", array_map(static fn($value) => '- ' . $value, $failures)) . "\n");
    exit(1);
}

echo "=== Startpartner Gate-4 Submission Contract: OK ===\n";
