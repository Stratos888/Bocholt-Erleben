<?php
    if ($addressRequired && $address === '') {
        $tasks[] = be_cc_review_task([
            'task_id'=>'location.address','finding_code'=>'missing_address','field_scope'=>['address','address_status'],
            'title'=>'Adresse für Navigation klären','message'=>'Für diesen Veranstaltungsort wird eine navigierbare Adresse benötigt.',
            'evidence'=>be_cc_review_evidence($payload, 'address', '', 'human_required'),
            'actions'=>[
                be_cc_review_action('set_fields','Adresse speichern',[be_cc_review_field('address','Adresse','text')]),
                be_cc_review_action('set_fields','Adresse nicht erforderlich',[be_cc_review_field('address_status','Begründung','select','not_required',[['value'=>'central_unique_place','label'=>'Zentraler eindeutiger Ort'],['value'=>'route_not_applicable','label'=>'Keine Navigation erforderlich']])]),
            ],
            'persistence'=>['fields'=>['address','address_status']], 'follow_up'=>['processes'=>['geocoding','routing_check','review_contract_recheck']],
            'postcondition'=>['navigable_or_exception'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'address_resolution'],
        ]);
    }

    if ($sourceUrl !== '' && !be_cc_contract_url_valid($sourceUrl)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'source.official','finding_code'=>'invalid_source_url','field_scope'=>['source_url'],
            'title'=>'Prüfquelle korrigieren','message'=>'Die gespeicherte Prüfquelle ist keine gültige HTTP(S)-Adresse.',
            'evidence'=>be_cc_review_evidence($payload, 'source_url', $sourceUrl, 'conflict'),
            'actions'=>[be_cc_review_action('set_fields','Quelle speichern',[be_cc_review_field('source_url','Offizielle Prüfquelle','url',$sourceUrl)])],
            'persistence'=>['fields'=>['source_url']], 'follow_up'=>['processes'=>['source_fetch','fact_check','review_contract_recheck']],
            'postcondition'=>['valid_http_url'=>'source_url'], 'audit'=>['event'=>'review_task_resolved','learning'=>'source_correction'],
        ]);
    }
    if ($weakSource || $sourceConflict) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'source.quality','finding_code'=>$sourceConflict?'source_conflict':'weak_source','field_scope'=>['source_url','source_quality'],
            'state'=>$sourceConflict?'conflict':'open','title'=>$sourceConflict?'Quellenkonflikt auflösen':'Belastbare Quelle ergänzen',
            'message'=>$sourceConflict?'Die verfügbaren Quellen widersprechen sich.':'Die vorhandene Quelle reicht für eine sichere Veröffentlichung nicht aus.',
            'evidence'=>be_cc_review_evidence($payload, 'source_url', $sourceUrl, $sourceConflict?'conflict':'unverifiable'),
            'actions'=>[
                be_cc_review_action('set_fields','Belastbare Quelle speichern',[be_cc_review_field('source_url','Offizielle Prüfquelle','url',$sourceUrl),be_cc_review_field('source_quality','Quellenstatus','hidden','official')]),
                be_cc_review_action('snooze_case','Später erneut prüfen',[be_cc_review_field('suppress_until','Wiedervorlage','date')]),
                be_cc_review_action('reject_weak_source','Wegen schwacher Quelle ablehnen',[],['decision_class'=>'rejected_source_weak'],'danger'),
            ],
            'persistence'=>['fields'=>['source_url','source_quality','status','next_review_at']],
            'follow_up'=>['processes'=>['source_fetch','fact_check','review_contract_recheck']],
            'postcondition'=>['source_quality_not_weak'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'source_quality_resolution'],
        ]);
    }

    if (be_cc_review_bool($payload, ['event_link_required']) && $eventUrl === '') {
        $tasks[] = be_cc_review_task([
            'task_id'=>'source.event_link','finding_code'=>'missing_event_url','field_scope'=>['event_url'],
            'severity'=>'warning','title'=>'Öffentlichen Eventlink ergänzen','message'=>'Prüfquelle und öffentlicher Ziel-Link sollen getrennt gespeichert werden.',
            'evidence'=>be_cc_review_evidence($payload, 'event_url', '', 'human_required'),
            'actions'=>[be_cc_review_action('set_fields','Eventlink speichern',[be_cc_review_field('event_url','Öffentlicher Eventlink','url')])],
            'persistence'=>['fields'=>['event_url']], 'follow_up'=>['processes'=>['link_check','review_contract_recheck']],
            'postcondition'=>['valid_http_url'=>'event_url'], 'audit'=>['event'=>'review_task_resolved','learning'=>'event_link_completion'],
        ]);
    }

    $descriptionStatus = be_cc_review_token(be_cc_review_text($payload, ['description_status','content_quality_status']));
    if (in_array($descriptionStatus, ['promotional','generic','too_short','unsupported_claim','review'], true)) {
        $suggested = be_cc_review_text($payload, ['suggested_description','replacement_text']);
        $tasks[] = be_cc_review_task([
            'task_id'=>'description.quality','finding_code'=>'description_' . $descriptionStatus,'field_scope'=>['description'],
            'title'=>'Beschreibung fachlich überarbeiten','message'=>'Die Beschreibung enthält einen offenen Qualitäts- oder Belegbefund.',
            'evidence'=>be_cc_review_evidence($payload, 'description', $description, $descriptionStatus==='unsupported_claim'?'conflict':'system_suggestion'),
            'actions'=>[be_cc_review_action('set_fields','Beschreibung speichern',[be_cc_review_field('description','Finale Beschreibung','textarea',$suggested!==''?$suggested:$description)])],
            'persistence'=>['fields'=>['description']], 'follow_up'=>['processes'=>['description_quality_check','claim_check','review_contract_recheck']],
            'postcondition'=>['quality_check_passed'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'description_correction'],
        ]);
    }

    if ($ticketUrl !== '' && !be_cc_contract_url_valid($ticketUrl)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'access.link','finding_code'=>'invalid_ticket_url','field_scope'=>['ticket_url'],
            'title'=>'Ticket- oder Anmeldelink korrigieren','message'=>'Der gespeicherte Zugangslink ist nicht gültig.',
            'evidence'=>be_cc_review_evidence($payload, 'ticket_url', $ticketUrl, 'conflict'),
            'actions'=>[be_cc_review_action('set_fields','Link speichern',[be_cc_review_field('ticket_url','Ticket-/Anmeldelink','url',$ticketUrl)])],
            'persistence'=>['fields'=>['ticket_url']], 'follow_up'=>['processes'=>['link_check','review_contract_recheck']],
            'postcondition'=>['valid_http_url'=>'ticket_url'], 'audit'=>['event'=>'review_task_resolved','learning'=>'access_link_correction'],
        ]);
    }
    if ($ticketRequired && $ticketUrl === '' && !in_array($accessStatus, ['free_no_registration','not_applicable'], true)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'access.status','finding_code'=>'missing_access_path','field_scope'=>['access_status','ticket_url'],
            'title'=>'Zugang zur Veranstaltung klären','message'=>'Für die Teilnahme ist ein Ticket oder eine Anmeldung erforderlich, aber der Zugangsweg fehlt.',
            'evidence'=>be_cc_review_evidence($payload, 'ticket_url', '', 'human_required'),
            'actions'=>[
                be_cc_review_action('set_fields','Zugang speichern',[
                    be_cc_review_field('access_status','Zugangsart','select',$accessStatus,array_map(static fn(string $value): array=>['value'=>$value,'label'=>be_cc_review_humanize($value)], BE_CC_ACCESS_STATUSES)),
                    be_cc_review_field('ticket_url','Ticket-/Anmeldelink','url',$ticketUrl,[],false),
                ]),
                be_cc_review_action('snooze_case','Verkaufsstart abwarten',[be_cc_review_field('suppress_until','Erneut prüfen am','date')],['access_status'=>'not_open']),
            ],
            'persistence'=>['fields'=>['access_status','ticket_url','status','next_review_at']],
            'follow_up'=>['processes'=>['link_check','scheduled_source_recheck','review_contract_recheck']],
            'postcondition'=>['access_path_resolved'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'access_resolution'],
        ]);
    }

    if ($hardDuplicate) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'dedupe.decision','finding_code'=>'hard_duplicate','field_scope'=>['matched_event_id','duplicate_status'],
            'title'=>'Mögliche Dublette entscheiden','message'=>'Ein vorhandenes Event stimmt in wesentlichen Merkmalen überein.',
            'evidence'=>[
                'status'=>'conflict','confidence'=>be_cc_review_token($payload['duplicate_confidence'] ?? 'high') ?: 'high',
                'source_name'=>'Bestehender Eventbestand','source_url'=>be_cc_review_text($payload,['matched_event_url','duplicate_event_url']),
                'observed_value'=>$matchedEventId,'explanation'=>be_cc_review_text($payload,['duplicate_reason','dedupe_reason','match_explanation']),
                'match_type'=>be_cc_review_text($payload,['duplicate_match_type','match_type']),
                'score'=>be_cc_review_text($payload,['duplicate_score','match_score']),
                'matched_title'=>be_cc_review_text($payload,['matched_event_title','duplicate_title']),
                'matched_date'=>be_cc_review_text($payload,['matched_event_date','duplicate_date']),
                'matched_location'=>be_cc_review_text($payload,['matched_event_location','duplicate_location']),
            ],
            'actions'=>[
                be_cc_review_action('reject_duplicate','Dublette ablehnen',[],['decision_class'=>'duplicate'],'danger'),
                be_cc_review_action('mark_distinct','Eigenständiger Termin',[be_cc_review_field('decision_note','Begründung','textarea')],['duplicate_status'=>'distinct']),
                be_cc_review_action('merge_existing','Bestehendes Event ergänzen',[be_cc_review_field('decision_note','Zu übernehmende Ergänzung','textarea')],['duplicate_status'=>'merge_existing'],'secondary'),
                be_cc_review_action('snooze_case','Unklar – zurückstellen',[be_cc_review_field('suppress_until','Wiedervorlage','date')]),
            ],
            'persistence'=>['fields'=>['duplicate_status','matched_event_id','status']],
            'follow_up'=>['processes'=>['dedupe_recheck','existing_event_enrichment']],
            'postcondition'=>['terminal_distinct_or_merge_waiting'=>true],
            'audit'=>['event'=>'review_task_resolved','learning'=>'dedupe_classification'],
        ]);
    }
