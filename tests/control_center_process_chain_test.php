<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_process_chain.php';

$failures = [];
$assert = static function(bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};
$now = new DateTimeImmutable('now');
$weeklyAt = $now->modify('-10 minutes')->format('Y-m-d H:i:s');
$intakeAfter = $now->modify('-5 minutes')->format('Y-m-d H:i:s');
$intakeBefore = $now->modify('-20 minutes')->format('Y-m-d H:i:s');

$base = [
    'available' => true,
    'processes' => [
        'weekly_ki_websearch' => [
            'status' => 'manual_candidates_created',
            'action_required' => 1,
            'generated_at_utc' => $weeklyAt,
            'github_run_url' => '',
        ],
        'content_quality_audit' => [
            'status' => 'no_manual_action_from_audit',
            'action_required' => 0,
            'generated_at_utc' => $weeklyAt,
            'github_run_url' => '',
        ],
        'growth_intelligence' => [
            'status' => 'growth_ok',
            'action_required' => 0,
            'generated_at_utc' => $weeklyAt,
            'github_run_url' => '',
        ],
    ],
];

$missingIntake = be_cc_integrated_process_health($base);
$assert($missingIntake['status'] === 'attention', 'Kandidaten ohne nachgelagerten Intake werden erkannt.');

$olderIntake = $base;
$olderIntake['processes']['manual_ki_intake'] = [
    'status' => 'inbox_rows_appended',
    'action_required' => 1,
    'generated_at_utc' => $intakeBefore,
    'github_run_url' => '',
];
$olderResult = be_cc_integrated_process_health($olderIntake);
$assert($olderResult['status'] === 'attention', 'Ein älterer Intake bestätigt den neuen Suchlauf nicht.');

$confirmed = $base;
$confirmed['processes']['manual_ki_intake'] = [
    'status' => 'inbox_rows_appended',
    'action_required' => 1,
    'generated_at_utc' => $intakeAfter,
    'github_run_url' => '',
];
$confirmedResult = be_cc_integrated_process_health($confirmed);
$assert($confirmedResult['status'] === 'ok', 'Ein nachgelagerter Intake bestätigt die Suchkette.');
$intakeItem = array_values(array_filter(
    $confirmedResult['items'],
    static fn(array $item): bool => ($item['key'] ?? '') === 'manual_ki_intake'
))[0] ?? [];
$assert(($intakeItem['depends_on'] ?? '') === 'weekly_ki_websearch', 'Abhängigkeit wird sichtbar ausgegeben.');

$noCandidates = $base;
$noCandidates['processes']['weekly_ki_websearch']['status'] = 'no_manual_candidates_created';
$noCandidates['processes']['weekly_ki_websearch']['action_required'] = 0;
$noCandidateResult = be_cc_integrated_process_health($noCandidates);
$assert($noCandidateResult['status'] === 'ok', 'Ohne Kandidaten ist kein Intake-Lauf erforderlich.');

if ($failures) {
    fwrite(STDERR, "=== Control Center Process Chain Simulation: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center Process Chain Simulation: OK ===\n";
