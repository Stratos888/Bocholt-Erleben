<?php
declare(strict_types=1);

require_once __DIR__ . '/_event_review_task_support.php';

/**
 * Canonical exception-review contract loader.
 * Contract markers intentionally remain discoverable here for static gates:
 * field_evidence, review_tasks, task_revision, time_not_published,
 * visual_asset_id, visual_gap_id, candidate_ready.
 */
function be_cc_event_candidate_review_contract(array $payload): array
{
    require __DIR__ . '/_event_review_task_schedule_a.php';
    require __DIR__ . '/_event_review_task_schedule_b.php';
    require __DIR__ . '/_event_review_task_details.php';
    require __DIR__ . '/_event_review_task_visual.php';
    return require __DIR__ . '/_event_review_task_result.php';
}
