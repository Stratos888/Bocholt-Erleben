<?php
    $timeStatusChoices = ['all_day','during_opening_hours','multiple_times'];
    if (be_cc_review_bool($payload, ['time_not_applicable_allowed'])) $timeStatusChoices[] = 'time_not_applicable';

    if ($time === '') {
        if ($timeStatus === '') {
            $tasks[] = be_cc_review_task([
                'task_id'=>'time.status','finding_code'=>'time_missing','field_scope'=>['time','time_status','time_details'],
                'title'=>'Beginnzeit klären','message'=>'In den führenden Eventdaten ist noch keine typisierte Beginnzeit gespeichert. Prüfe die verlinkte offizielle Quelle, bevor du eine Uhrzeit oder einen Zeitstatus festlegst.',
                'evidence'=>be_cc_review_evidence($payload, 'time', $legacyTimeReason, $legacyTimeReason !== '' ? 'human_required' : 'unverifiable', $legacyTimeReason !== '' ? 'Ein vorhandener Freitext ist noch kein verlässlicher Zeitstatus.' : 'Keine Beginnzeit in den führenden Eventdaten gefunden.'),
                'actions'=>[
                    be_cc_review_action('set_fixed_time','Uhrzeit eintragen',[be_cc_review_field('time','Beginn','time')],['time_status'=>'fixed_time']),
                    be_cc_review_action('set_time_status','Zeitstatus speichern',[
                        be_cc_review_field('time_status','Zeitstatus','select','',array_map(static fn(string $value): array=>['value'=>$value,'label'=>be_cc_review_humanize($value)], $timeStatusChoices)),
                        be_cc_review_field('time_details','Erklärung / Zeitraum','textarea',$timeDetails,[],false),
                    ]),
                    be_cc_review_action('time_not_published','Noch nicht veröffentlicht',[
                        be_cc_review_field('suppress_until','Erneut prüfen am','date'),
                    ],['time_status'=>'time_not_published']),
                    be_cc_review_action('time_conflict','Widerspruch dokumentieren',[
                        be_cc_review_field('time_details','Widersprüchliche Angaben','textarea',$timeDetails),
                        be_cc_review_field('suppress_until','Erneut prüfen am','date'),
                    ],['time_status'=>'time_conflict'],'secondary'),
                ],
                'persistence'=>['fields'=>['time','time_status','time_details','status','next_review_at']],
                'follow_up'=>['processes'=>['source_recheck','review_contract_recheck']],
                'postcondition'=>['typed_time_status'=>true],
                'audit'=>['event'=>'review_task_resolved','learning'=>'time_status_resolution'],
            ]);
        } elseif ($timeStatus === 'time_not_published') {
            $tasks[] = be_cc_review_task([
                'task_id'=>'time.status','finding_code'=>'time_not_published','field_scope'=>['time_status','next_review_at'],
                'state'=>'waiting_external','title'=>'Auf veröffentlichte Uhrzeit warten','message'=>'Die Quelle veröffentlicht die Beginnzeit erst später. Der Fall bleibt bis zur erneuten Quellenprüfung offen.',
                'evidence'=>be_cc_review_evidence($payload, 'time_status', $timeStatus, 'officially_verified'),
                'actions'=>[
                    be_cc_review_action('set_fixed_time','Uhrzeit jetzt eintragen',[be_cc_review_field('time','Beginn','time')],['time_status'=>'fixed_time']),
                    be_cc_review_action('time_not_published','Wiedervorlage ändern',[be_cc_review_field('suppress_until','Erneut prüfen am','date')],['time_status'=>'time_not_published']),
                ],
                'persistence'=>['fields'=>['time','time_status','next_review_at']],
                'follow_up'=>['processes'=>['scheduled_source_recheck']],
                'postcondition'=>['waiting_until_recheck'=>true],
                'audit'=>['event'=>'review_task_waiting','learning'=>'time_publication_delay'],
            ]);
        } elseif ($timeStatus === 'time_conflict') {
            $tasks[] = be_cc_review_task([
                'task_id'=>'time.status','finding_code'=>'time_conflict','field_scope'=>['time','time_status','time_details'],
                'state'=>'conflict','title'=>'Widersprüchliche Uhrzeiten auflösen','message'=>'Die verfügbaren Quellen nennen unterschiedliche Beginnzeiten.',
                'evidence'=>be_cc_review_evidence($payload, 'time_status', $timeDetails, 'conflict'),
                'actions'=>[
                    be_cc_review_action('set_fixed_time','Bestätigte Uhrzeit speichern',[be_cc_review_field('time','Beginn','time')],['time_status'=>'fixed_time']),
                    be_cc_review_action('time_conflict','Wiedervorlage setzen',[be_cc_review_field('time_details','Widerspruch','textarea',$timeDetails),be_cc_review_field('suppress_until','Erneut prüfen am','date')],['time_status'=>'time_conflict']),
                ],
                'persistence'=>['fields'=>['time','time_status','time_details','next_review_at']],
                'follow_up'=>['processes'=>['scheduled_source_recheck']],
                'postcondition'=>['conflict_resolved_or_scheduled'=>true],
                'audit'=>['event'=>'review_task_conflict','learning'=>'time_conflict_resolution'],
            ]);
        } elseif (in_array($timeStatus, ['during_opening_hours','multiple_times'], true) && $timeDetails === '') {
            $tasks[] = be_cc_review_task([
                'task_id'=>'time.details','finding_code'=>'missing_time_details','field_scope'=>['time_status','time_details'],
                'title'=>'Zeitangabe vervollständigen','message'=>'Der gewählte Zeitstatus benötigt einen verständlichen Zeitraum oder Programmhinweis.',
                'evidence'=>be_cc_review_evidence($payload, 'time_status', $timeStatus, 'format_validated'),
                'actions'=>[be_cc_review_action('set_time_status','Zeitdetails speichern',[be_cc_review_field('time_status','Zeitstatus','hidden',$timeStatus),be_cc_review_field('time_details','Zeitraum / Programmhinweis','textarea')])],
                'persistence'=>['fields'=>['time_status','time_details']], 'follow_up'=>['processes'=>['review_contract_recheck']],
                'postcondition'=>['time_details_present'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'time_details_completion'],
            ]);
        } elseif ($timeStatus === 'time_not_applicable' && !be_cc_review_bool($payload, ['time_not_applicable_allowed'])) {
            $tasks[] = be_cc_review_task([
                'task_id'=>'time.not_applicable','finding_code'=>'time_not_applicable_unverified','field_scope'=>['time_status','time_details'],
                'title'=>'Ausnahme ohne Uhrzeit begründen','message'=>'„Uhrzeit nicht anwendbar“ ist für diesen Eventtyp noch nicht belegt.',
                'evidence'=>be_cc_review_evidence($payload, 'time_status', $timeStatus, 'human_required'),
                'actions'=>[
                    be_cc_review_action('set_fixed_time','Uhrzeit eintragen',[be_cc_review_field('time','Beginn','time')],['time_status'=>'fixed_time']),
                    be_cc_review_action('set_time_status','Ausnahme begründen',[be_cc_review_field('time_status','Zeitstatus','hidden','time_not_applicable'),be_cc_review_field('time_details','Fachliche Begründung','textarea')],['time_not_applicable_allowed'=>'true']),
                ],
                'persistence'=>['fields'=>['time','time_status','time_details','time_not_applicable_allowed']],
                'follow_up'=>['processes'=>['review_contract_recheck']], 'postcondition'=>['exception_verified'=>true],
                'audit'=>['event'=>'review_task_resolved','learning'=>'time_exception_resolution'],
            ]);
        }
    }
