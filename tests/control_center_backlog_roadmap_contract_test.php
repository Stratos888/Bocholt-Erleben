<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/growth-backlog-lib.php';

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$rows = [
    ['_sheet_row'=>2, 'id'=>'a', 'status'=>'open', 'priority'=>'mittel', 'title'=>'Zweiter offener Punkt'],
    ['_sheet_row'=>3, 'id'=>'b', 'status'=>'abgeschlossen', 'priority'=>'hoch', 'title'=>'Abgeschlossener Punkt', 'decision_note'=>'Erledigt'],
    ['_sheet_row'=>4, 'id'=>'c', 'status'=>'', 'priority'=>'hoch', 'title'=>'Erster offener Punkt'],
    ['_sheet_row'=>5, 'id'=>'d', 'status'=>'unbekannt', 'priority'=>'niedrig', 'title'=>'Dritter offener Punkt'],
];

$snapshot = gbl_backlog_snapshot_from_rows($rows);
$items = $snapshot['items'];
$counts = $snapshot['counts'];

expect_true($counts === ['total'=>4, 'open'=>3, 'completed'=>1], 'counts must include all open and completed items');
expect_true(array_column($items, 'title') === ['Erster offener Punkt', 'Zweiter offener Punkt', 'Dritter offener Punkt', 'Abgeschlossener Punkt'], 'open items must be ordered by priority and source order, completed items last');
expect_true(array_column($items, 'status') === ['open', 'open', 'open', 'completed'], 'only the two canonical statuses may be emitted');
expect_true(array_column($items, 'recommended_order') === [1, 2, 3, null], 'only open items receive a recommended order');
expect_true(gbl_status_normalize('erledigt') === 'completed', 'German completed status must normalize');
expect_true(gbl_status_normalize('unexpected') === 'open', 'unknown source status must stay visible as open');

fwrite(STDOUT, "=== Control Center Backlog Roadmap Contract: OK ===\n");
