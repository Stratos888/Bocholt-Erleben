<?php
    $facts = [
        'title'=>$title,'date'=>$date,'end_date'=>$endDate,'schedule_type'=>$scheduleType,
        'time'=>$time,'time_status'=>$timeStatus!==''?$timeStatus:($time!==''?'fixed_time':''),'time_details'=>$timeDetails,
        'city'=>$city,'local_reference'=>$localReference,'location'=>$location,'address'=>$address,
    ];
    foreach ($facts as $field=>$value) {
        if ($value === '') continue;
        $fallback = 'format_validated';
        if (in_array($sourceQuality, ['official','primary','verified'], true) && $sourceUrl !== '') $fallback = 'officially_verified';
        $fieldEvidence[$field] = be_cc_review_evidence($payload, $field, $value, $fallback);
    }
    foreach (['category'=>$category,'source_url'=>$sourceUrl,'event_url'=>$eventUrl,'ticket_url'=>$ticketUrl,'description'=>$description,'visual_key'=>$visualKey,'visual_motif'=>$visualMotif,'visual_asset_id'=>$visualAssetId] as $field=>$value) {
        if ($value === '') continue;
        $fieldEvidence[$field] = be_cc_review_evidence($payload, $field, $value, $field==='visual_asset_id'?'officially_verified':'format_validated');
    }

    $blockers = [];
    $warnings = [];
    foreach ($tasks as $task) {
        $issue = ['code'=>$task['finding_code'],'field'=>(string)($task['field_scope'][0]??''),'message'=>$task['message'],'task_id'=>$task['task_id'],'state'=>$task['state']];
        if (($task['severity']??'blocking') === 'blocking') $blockers[] = $issue; else $warnings[] = $issue;
    }
    $ready = $blockers === [];
    $stateCounts = [];
    foreach ($tasks as $task) $stateCounts[$task['state']] = ($stateCounts[$task['state']]??0)+1;

    $asset = $visualAssetId !== '' ? be_cc_event_visual_asset_by_id($visualAssetId) : null;
    return [
        'identity'=>['stable_id'=>$stableId],
        'facts'=>$facts,
        'description'=>['final'=>$description],
        'source'=>[
            'name'=>$sourceName,'url'=>$sourceUrl,'event_url'=>$eventUrl,'ticket_url'=>$ticketUrl,
            'quality'=>$sourceQuality,'weak'=>$weakSource,'conflict'=>$sourceConflict,
        ],
        'classification'=>['category'=>$category],
        'access'=>['status'=>$accessStatus,'ticket_required'=>$ticketRequired],
        'visual'=>[
            'key'=>$visualKey,'motif'=>$visualMotif,'asset_id'=>$visualAssetId,'asset_role'=>$visualAssetRole,'asset'=>$asset,
            'gap_id'=>be_cc_review_text($payload,['visual_gap_id']),
        ],
        'dedupe'=>[
            'hard_conflict'=>$hardDuplicate,'matched_event_id'=>$matchedEventId,'status'=>$duplicateStatus,
            'score'=>be_cc_review_text($payload,['duplicate_score','match_score']),
            'reason'=>be_cc_review_text($payload,['duplicate_reason','dedupe_reason','match_explanation']),
            'url'=>be_cc_review_text($payload,['matched_event_url','duplicate_event_url']),
        ],
        'field_evidence'=>$fieldEvidence,
        'review_tasks'=>$tasks,
        'quality'=>['warnings'=>$warnings],
        'summary'=>[
            'open_task_count'=>count($blockers),
            'warning_count'=>count($warnings),
            'task_state_counts'=>$stateCounts,
            'status'=>$ready?'ready':'exceptions_open',
            'headline'=>$ready?'Event entscheidungsreif':('Noch ' . count($blockers) . (count($blockers)===1?' Punkt':' Punkte') . ' klären'),
            'final_review_required'=>true,
        ],
        'decision_gate'=>[
            'ready'=>$ready,'blockers'=>$blockers,'warnings'=>$warnings,
            'required_fields'=>array_values(array_unique(array_map(static fn(array $task): string=>(string)($task['field_scope'][0]??''),array_filter($tasks,static fn(array $task):bool=>str_starts_with((string)$task['finding_code'],'missing_'))))),
            'conditional_fields'=>[],
        ],
        'actions'=>[
            ['key'=>'approve','enabled'=>$ready],
            ['key'=>'resolve_review_task','enabled'=>!$ready],
            ['key'=>'edit_and_approve','enabled'=>true],
            ['key'=>'snooze','enabled'=>true],
            ['key'=>'reject','enabled'=>true],
        ],
    ];
