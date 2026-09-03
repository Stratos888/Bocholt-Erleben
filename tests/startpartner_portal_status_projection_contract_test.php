<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/startpartner/_gate4_projection.php';

$failures = [];
$assertSame = static function (string $expected, string $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ' Expected=' . $expected . ' Actual=' . $actual;
    }
};

$assertSame(
    'approved',
    be_startpartner_gate4_partner_content_status([
        'status' => 'approved',
        'submission_status' => 'approved',
    ]),
    'A currently approved submission must remain published in the partner projection.'
);

$assertSame(
    'in_review',
    be_startpartner_gate4_partner_content_status([
        'status' => 'approved',
        'submission_status' => 'in_review',
    ]),
    'An organizer-edited approved submission must project as in review without changing pilot approval history.'
);

$assertSame(
    'in_review',
    be_startpartner_gate4_partner_content_status([
        'status' => 'approved',
        'submission_status' => 'pending_review',
    ]),
    'A pending re-review must not be displayed as published.'
);

$assertSame(
    'rejected',
    be_startpartner_gate4_partner_content_status([
        'status' => 'approved',
        'submission_status' => 'rejected',
    ]),
    'A rejected current submission must not be displayed as published.'
);

$assertSame(
    'draft',
    be_startpartner_gate4_partner_content_status([
        'status' => 'draft',
        'submission_status' => 'in_review',
    ]),
    'Pre-approval pilot lifecycle states must remain owned by the pilot content link.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Portal Status Projection Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Portal Status Projection Contract: OK ===\n";
