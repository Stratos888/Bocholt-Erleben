<?php
declare(strict_types=1);

require_once __DIR__ . '/_editorial_contracts.php';

function be_cc_decode_payload(array $row): array
{
    $raw = (string)($row['source_payload_json'] ?? '');
    if ($raw === '') return [];
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

function be_cc_display_status(string $state): string
{
    return match ($state) {
        'new'=>'Neu','decision_required'=>'Entscheidung erforderlich','open'=>'Offen','in_progress'=>'In Arbeit',
        'waiting'=>'Wartet','blocked'=>'Blockiert','snoozed'=>'Zurückgestellt','done'=>'Erledigt','rejected'=>'Abgelehnt',
        'information'=>'Information','parked'=>'Geparkt',default=>'Unbekannt',
    };
}

function be_cc_action(string $key, string $label, bool $requiresInput = false, bool $destructive = false, bool $enabled = true): array
{
    return ['key'=>$key,'label'=>$label,'requires_input'=>$requiresInput,'destructive'=>$destructive,'enabled'=>$enabled];
}

function be_cc_case_presentation(array $row): array
{
    $source = (string)($row['source_system'] ?? '');
    $state = (string)($row['state'] ?? 'new');
    $type = (string)($row['case_type'] ?? 'intake');
    $payload = be_cc_decode_payload($row);
    $kind = 'other'; $group = 'other'; $primary = null; $secondary = []; $context = []; $links = []; $waitingFor = ''; $reviewContract = null;

    if ($source === 'inbox_feed') {
        $kind = (($payload['submission_kind'] ?? '') === 'activity') ? 'activity_candidate' : 'event_candidate';
        $group = 'new_content';
        if ($kind === 'event_candidate') {
            $reviewContract = be_cc_event_candidate_review_contract($payload);
            $ready = (bool)($reviewContract['decision_gate']['ready'] ?? false);
            $openTasks = count((array)($reviewContract['decision_gate']['blockers'] ?? []));
            $primary = $ready
                ? be_cc_action('approve', 'Event übernehmen')
                : be_cc_action('resolve_review_task', $openTasks === 1 ? 'Offenen Punkt klären' : 'Offene Punkte klären');
            $secondary = [];
            if ($ready) $secondary[] = be_cc_action('edit_and_approve', 'Gesamtfassung bearbeiten', true);
            $secondary[] = be_cc_action('snooze', 'Gesamten Fall zurückstellen', true);
            $secondary[] = be_cc_action('reject', 'Event ablehnen', true, true);
            $secondary[] = be_cc_action('details', 'Quelldaten');
            $context = [
                'review_contract'=>$reviewContract,
                'description'=>(string)($reviewContract['description']['final'] ?? ''),
                'date'=>(string)($reviewContract['facts']['date'] ?? ''),'end_date'=>(string)($reviewContract['facts']['end_date'] ?? ''),
                'time'=>(string)($reviewContract['facts']['time'] ?? ''),'time_status'=>(string)($reviewContract['facts']['time_status'] ?? ''),
                'time_details'=>(string)($reviewContract['facts']['time_details'] ?? ''),
                'location'=>(string)($reviewContract['facts']['location'] ?? ''),'address'=>(string)($reviewContract['facts']['address'] ?? ''),
                'city'=>(string)($reviewContract['facts']['city'] ?? ''),'category'=>(string)($reviewContract['classification']['category'] ?? ''),
                'source_name'=>(string)($reviewContract['source']['name'] ?? ''),'source_url'=>(string)($reviewContract['source']['url'] ?? ''),
                'event_url'=>(string)($reviewContract['source']['event_url'] ?? ''),'ticket_url'=>(string)($reviewContract['source']['ticket_url'] ?? ''),
                'visual_key'=>(string)($reviewContract['visual']['key'] ?? ''),'visual_motif'=>(string)($reviewContract['visual']['motif'] ?? ''),
                'visual_asset_id'=>(string)($reviewContract['visual']['asset_id'] ?? ''),
                'duplicate_hint'=>(string)($reviewContract['dedupe']['matched_event_id'] ?? ''),
                'decision_gate'=>$reviewContract['decision_gate'] ?? [],'review_tasks'=>$reviewContract['review_tasks'] ?? [],
            ];
            if ($state === 'waiting') $waitingFor = (string)($row['blocked_reason'] ?? '') ?: 'Ein automatischer Folgeprozess ist noch offen.';
        } else {
            $primary = be_cc_action('approve', 'Übernehmen');
            $secondary = [be_cc_action('snooze','Zurückstellen',true),be_cc_action('reject','Ablehnen',true,true),be_cc_action('details','Quelldaten')];
            $context = ['description'=>(string)($payload['description'] ?? '')];
        }
        $sourceUrl = trim((string)($payload['source_url'] ?? $payload['url'] ?? ''));
        $eventUrl = trim((string)($payload['event_url'] ?? $payload['target_url'] ?? ''));
        if ($eventUrl !== '') $links[] = ['label'=>'Eventseite','url'=>$eventUrl];
        if ($sourceUrl !== '' && $sourceUrl !== $eventUrl) $links[] = ['label'=>'Offizielle Prüfquelle','url'=>$sourceUrl];
    } elseif ($source === 'submission_db') {
        $submissionStatus = (string)($payload['status'] ?? '');
        if ($submissionStatus === 'pending_review') { $kind='submission_pre_payment'; $group='provider'; $primary=be_cc_action('approve','Zahlung freigeben'); }
        elseif (in_array($submissionStatus,['paid','in_review'],true)) { $kind='submission_publish'; $group='approvals'; $primary=be_cc_action('approve','Veröffentlichen'); }
        else { $kind='submission_waiting_payment'; $group='provider'; $waitingFor='Zahlungsbestätigung'; }
        if ($primary !== null) $secondary=[be_cc_action('snooze','Zurückstellen',true),be_cc_action('reject','Ablehnen',true,true),be_cc_action('details','Details')];
        $context=['submission_status'=>$submissionStatus,'submission_kind'=>(string)($payload['submission_kind']??''),'date'=>(string)($payload['start_date']??''),'time'=>(string)($payload['time_text']??''),'location'=>(string)($payload['location_name']??''),'organization'=>(string)($payload['organization_name_snapshot']??'')];
        $url=trim((string)($payload['event_url']??$payload['ticket_url']??'')); if($url!=='')$links[]=['label'=>'Einreichungsquelle','url'=>$url];
    } elseif ($source === 'content_audit') {
        $issueCode=strtolower(trim((string)($payload['issue_code']??''))); $group='quality';
        if(str_contains($issueCode,'description')){$kind='content_description_correction';$primary=be_cc_action('edit_and_approve','Korrigieren und übernehmen',true);}
        elseif(str_contains($issueCode,'source')||str_contains($issueCode,'ticket_url')){$kind='content_source_correction';$primary=be_cc_action('edit_and_approve','Quelle korrigieren',true);}
        elseif(str_contains($issueCode,'fact')||str_contains($issueCode,'evidence')){$kind='content_fact_check';$primary=be_cc_action('approve','Als korrekt bestätigen');}
        else{$kind='content_quality_review';$primary=be_cc_action('approve','Prüfung abschließen');}
        $secondary=[be_cc_action('snooze','Zurückstellen',true),be_cc_action('reject','Ablehnen',true,true),be_cc_action('details','Details')];
        $context=['issue_code'=>(string)($payload['issue_code']??''),'issue_text'=>(string)($payload['issue_text']??''),'recommended_action'=>(string)($payload['recommended_action']??''),'current_description'=>(string)($payload['description']??''),'suggested_description'=>(string)($payload['suggested_description']??$payload['replacement_text']??''),'suggested_url'=>(string)($payload['suggested_url']??''),'source_url'=>(string)($payload['source_url']??''),'content_type'=>(string)($payload['content_type']??'')];
        $url=trim((string)($payload['suggested_url']??$payload['source_url']??'')); if($url!=='')$links[]=['label'=>'Quelle','url'=>$url];
    } elseif ($source === 'growth_backlog' && $type !== 'task') {
        $kind='backlog_item';$group='backlog';$primary=be_cc_action('edit_source','Bearbeiten',true);$secondary=[be_cc_action('complete','Abschließen'),be_cc_action('reject','Verwerfen',true,true)];
        $context=['backlog_type'=>(string)($payload['type']??''),'source'=>(string)($payload['source']??$payload['source_document']??''),'recommended_action'=>(string)($payload['recommended_action']??$payload['next_action']??''),'expected_benefit'=>(string)($payload['expected_benefit']??'')];
    } elseif ($type === 'task' && in_array($source,['process_health','source_sync'],true)) {
        $kind='system_check';$group='system';$primary=be_cc_action('recheck','Erneut prüfen');
        $context=['issue_text'=>(string)($row['reason']??''),'recommended_action'=>'Die zugrunde liegende Prüfung wird erneut ausgeführt. Der Fall schließt nur bei nachgewiesenem Erfolg.'];
        $runUrl=trim((string)($payload['run_url']??''));if($runUrl!=='')$links[]=['label'=>'Lauf öffnen','url'=>$runUrl];
    } elseif ($type === 'task') {
        $kind='manual_task';$group='system';
        if($state==='open'||$state==='new'){$primary=be_cc_action('start','Starten');$secondary=[be_cc_action('wait','Auf Rückmeldung warten',true),be_cc_action('block','Blockieren',true),be_cc_action('snooze','Zurückstellen',true)];}
        elseif($state==='in_progress'){$primary=be_cc_action('complete','Erledigen');$secondary=[be_cc_action('wait','Auf Rückmeldung warten',true),be_cc_action('block','Blockieren',true),be_cc_action('snooze','Zurückstellen',true)];}
        elseif($state==='waiting'){$waitingFor=(string)($row['blocked_reason']??'')?:'Externe Rückmeldung oder Ergebnis';$primary=be_cc_action('resume','Fortsetzen');$secondary=[be_cc_action('block','Blockieren',true),be_cc_action('snooze','Zurückstellen',true)];}
        elseif($state==='blocked'){$waitingFor=(string)($row['blocked_reason']??'')?:'Blockade muss geklärt werden';$primary=be_cc_action('resume','Blockade aufheben');$secondary=[be_cc_action('wait','Auf Rückmeldung warten',true),be_cc_action('snooze','Zurückstellen',true)];}
        elseif($state==='snoozed')$primary=be_cc_action('resume','Jetzt fortsetzen');
    } elseif ($type === 'information') { $kind='system_information';$group='information'; }

    return [
        'case_kind'=>$kind,'queue_group'=>$group,'display_status'=>be_cc_display_status($state),
        'primary_action'=>$primary,'secondary_actions'=>$secondary,'decision_context'=>$context,
        'review_contract'=>$reviewContract,'source_links'=>$links,'waiting_for'=>$waitingFor,
    ];
}
