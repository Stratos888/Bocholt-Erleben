<?php
declare(strict_types=1);

require_once __DIR__ . '/_event_review_task_support.php';

function be_cc_event_candidate_review_contract(array $payload): array
{
    require __DIR__ . '/_event_review_task_schedule_a.php';
    require __DIR__ . '/_event_review_task_schedule_b.php';
    require __DIR__ . '/_event_review_task_details.php';
    require __DIR__ . '/_event_review_task_visual.php';
    return require __DIR__ . '/_event_review_task_result.php';
}
