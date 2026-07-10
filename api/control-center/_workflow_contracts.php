<?php
declare(strict_types=1);

function be_cc_transition_map(): array
{
    return [
        'start' => ['new'=>'in_progress','open'=>'in_progress','decision_required'=>'in_progress'],
        'approve' => ['decision_required'=>'done','new'=>'done'],
        'reject' => ['new'=>'rejected','decision_required'=>'rejected','open'=>'rejected'],
        'complete' => ['open'=>'done','in_progress'=>'done','waiting'=>'done','blocked'=>'done'],
        'reopen' => ['done'=>'open','rejected'=>'open','parked'=>'open'],
        'park' => ['new'=>'parked','open'=>'parked'],
        'convert_to_task' => ['new'=>'open','decision_required'=>'open','open'=>'open'],
        'wait' => ['open'=>'waiting','in_progress'=>'waiting'],
        'block' => ['open'=>'blocked','in_progress'=>'blocked','waiting'=>'blocked'],
        'snooze' => ['new'=>'snoozed','decision_required'=>'snoozed','open'=>'snoozed','waiting'=>'snoozed'],
        'resume' => ['snoozed'=>'open','waiting'=>'open','blocked'=>'open'],
    ];
}

function be_cc_transition_target(string $action, string $state): string
{
    $map = be_cc_transition_map();
    if (!isset($map[$action])) throw new InvalidArgumentException('Unknown action.');
    $target = $map[$action][$state] ?? null;
    if ($target === null) throw new DomainException('Action is not allowed for the current state.');
    return $target;
}
