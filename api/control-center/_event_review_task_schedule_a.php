<?php
    $title = be_cc_review_text($payload, ['title','eventName']);
    $date = be_cc_review_text($payload, ['date','start_date']);
    $endDate = be_cc_review_text($payload, ['endDate','end_date','enddate']);
    $time = be_cc_review_text($payload, ['time','start_time']);
    $timeStatus = be_cc_review_token(be_cc_review_text($payload, ['time_status','schedule_status']));
    $legacyTimeReason = be_cc_review_text($payload, ['time_reason']);
    $timeDetails = be_cc_review_text($payload, ['time_details','schedule_text']);
    if ($timeDetails === '' && $timeStatus !== '' && $legacyTimeReason !== '' && be_cc_review_token($legacyTimeReason) !== $timeStatus) $timeDetails = $legacyTimeReason;
    if ($timeStatus === '' && in_array(be_cc_review_token($legacyTimeReason), BE_CC_TIME_STATUSES, true)) $timeStatus = be_cc_review_token($legacyTimeReason);
    $city = be_cc_review_text($payload, ['city','stadt']);
    $localReference = be_cc_review_text($payload, ['local_reference','local_scope']);
    $location = be_cc_review_text($payload, ['location','venue','ort']);
    $address = be_cc_review_text($payload, ['address','location_address','adresse']);
    $category = be_cc_review_text($payload, ['kategorie_suggestion','kategorie','category']);
    $sourceUrl = be_cc_review_text($payload, ['source_url','official_source_url','url']);
    $eventUrl = be_cc_review_text($payload, ['event_url','target_url','listing_url']);
    $sourceName = be_cc_review_text($payload, ['source_name','organization_name','veranstalter']);
    $ticketUrl = be_cc_review_text($payload, ['ticket_url','booking_url','registration_url']);
    $description = be_cc_review_text($payload, ['final_description','description','description_text']);
    $visualKey = be_cc_review_token(be_cc_review_text($payload, ['visual_key','image_visual_key']));
    $visualMotif = be_cc_review_token(be_cc_review_text($payload, ['visual_motif','image_visual_motif']));
    $visualAssetId = be_cc_review_text($payload, ['visual_asset_id','image_asset_id','visual_override_asset_id']);
    $visualAssetRole = be_cc_review_token(be_cc_review_text($payload, ['visual_asset_role','image_asset_role']));
    $stableId = be_cc_review_text($payload, ['id_suggestion','id','event_id']);
    if ($stableId === '') $stableId = substr(hash('sha256', $title . '|' . $date . '|' . $sourceUrl), 0, 24);
    $matchedEventId = be_cc_review_text($payload, ['matched_event_id','duplicate_event_id']);
    $duplicateStatus = be_cc_review_token(be_cc_review_text($payload, ['duplicate_status','dedupe_status']));
    $scheduleType = be_cc_review_token(be_cc_review_text($payload, ['schedule_type','event_schedule_type']));
    $accessStatus = be_cc_review_token(be_cc_review_text($payload, ['access_status','ticket_status','registration_status']));
    $sourceQuality = be_cc_review_token(be_cc_review_text($payload, ['source_quality','source_status']));
    $currentStatus = be_cc_review_token(be_cc_review_text($payload, ['event_status','current_status','cancellation_status']));
    $isMultiDay = be_cc_review_bool($payload, ['is_multi_day','multi_day']) || $scheduleType === 'multi_day';
    $addressRequired = be_cc_review_bool($payload, ['address_required','route_enabled']);
    $ticketRequired = be_cc_review_bool($payload, ['ticket_required','booking_required']);
    $weakSource = in_array($sourceQuality, ['weak','insufficient','unverified'], true);
    $sourceConflict = be_cc_review_bool($payload, ['source_conflict','facts_conflict']) || $sourceQuality === 'conflict';
    $hardDuplicate = ($matchedEventId !== '' || be_cc_review_bool($payload, ['hard_duplicate','duplicate_conflict'])) && $duplicateStatus !== 'distinct';
    $today = be_cc_review_text($payload, ['_review_today']) ?: date('Y-m-d');

    $tasks = [];
    $fieldEvidence = [];

    be_cc_review_add_required_task($tasks, $payload, 'title', $title, 'Titel ergänzen', 'Ein verständlicher Veranstaltungstitel fehlt.', 'Titel');
    be_cc_review_add_required_task($tasks, $payload, 'date', $date, 'Termin ergänzen', 'Das Startdatum der Veranstaltung fehlt.', 'Startdatum', 'date');
    be_cc_review_add_required_task($tasks, $payload, 'location', $location, 'Veranstaltungsort ergänzen', 'Der konkrete Veranstaltungsort fehlt.', 'Veranstaltungsort');
    be_cc_review_add_required_task($tasks, $payload, 'category', $category, 'Kategorie auswählen', 'Die Veranstaltung ist noch keiner Kategorie zugeordnet.', 'Kategorie');
    be_cc_review_add_required_task($tasks, $payload, 'source_url', $sourceUrl, 'Offizielle Prüfquelle ergänzen', 'Für die fachliche Prüfung fehlt eine belastbare Quelle.', 'Offizielle Prüfquelle', 'url');
    be_cc_review_add_required_task($tasks, $payload, 'description', $description, 'Beschreibung ergänzen', 'Eine sachliche Beschreibung der Veranstaltung fehlt.', 'Finale Beschreibung', 'textarea');

    if ($city === '' && $localReference === '') {
        $tasks[] = be_cc_review_task([
            'task_id'=>'scope.local_reference','finding_code'=>'missing_local_scope','field_scope'=>['city','local_reference'],
            'title'=>'Lokalen Bezug klären','message'=>'Stadt oder eindeutiger lokaler Bezug fehlen.',
            'evidence'=>be_cc_review_evidence($payload, 'city', '', 'human_required', 'Aus den vorliegenden Daten lässt sich der lokale Bezug nicht belastbar ableiten.'),
            'actions'=>[
                be_cc_review_action('set_fields','Stadt speichern',[be_cc_review_field('city','Stadt','text')]),
                be_cc_review_action('set_fields','Lokalen Bezug begründen',[be_cc_review_field('local_reference','Lokaler Bezug','textarea')]),
                be_cc_review_action('reject_not_local','Außerhalb des Gebiets ablehnen',[],['decision_class'=>'rejected_not_local'],'danger'),
            ],
            'persistence'=>['fields'=>['city','local_reference']],
            'follow_up'=>['processes'=>['scope_check','review_contract_recheck']],
            'postcondition'=>['task_absent'=>true],
            'audit'=>['event'=>'review_task_resolved','learning'=>'scope_resolution'],
        ]);
    }

    if ($date !== '' && !be_cc_valid_iso_day($date)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'schedule.start_date','finding_code'=>'invalid_date','field_scope'=>['date'],
            'title'=>'Startdatum korrigieren','message'=>'Das gespeicherte Startdatum ist nicht gültig.',
            'evidence'=>be_cc_review_evidence($payload, 'date', $date, 'conflict', 'Das Datum entspricht keinem gültigen ISO-Kalendertag.'),
            'actions'=>[be_cc_review_action('set_fields','Datum korrigieren',[be_cc_review_field('date','Startdatum','date',$date)])],
            'persistence'=>['fields'=>['date']], 'follow_up'=>['processes'=>['expiry_check','dedupe_check','review_contract_recheck']],
            'postcondition'=>['valid_iso_day'=>'date','task_absent'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'date_correction'],
        ]);
    }
    if ($endDate !== '' && !be_cc_valid_iso_day($endDate)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'schedule.end_date','finding_code'=>'invalid_end_date','field_scope'=>['end_date'],
            'title'=>'Enddatum korrigieren','message'=>'Das gespeicherte Enddatum ist nicht gültig.',
            'evidence'=>be_cc_review_evidence($payload, 'end_date', $endDate, 'conflict'),
            'actions'=>[be_cc_review_action('set_fields','Enddatum korrigieren',[be_cc_review_field('end_date','Enddatum','date',$endDate)])],
            'persistence'=>['fields'=>['end_date']], 'follow_up'=>['processes'=>['expiry_check','dedupe_check','review_contract_recheck']],
            'postcondition'=>['valid_iso_day'=>'end_date','task_absent'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'date_correction'],
        ]);
    }
    if ($date !== '' && $endDate !== '' && be_cc_valid_iso_day($date) && be_cc_valid_iso_day($endDate) && $endDate < $date) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'schedule.range_order','finding_code'=>'end_before_start','field_scope'=>['date','end_date'],
            'title'=>'Zeitraum korrigieren','message'=>'Das Enddatum liegt vor dem Startdatum.',
            'evidence'=>be_cc_review_evidence($payload, 'end_date', $endDate, 'conflict', 'Start und Ende widersprechen sich.'),
            'actions'=>[be_cc_review_action('set_fields','Zeitraum speichern',[
                be_cc_review_field('date','Startdatum','date',$date), be_cc_review_field('end_date','Enddatum','date',$endDate),
            ])],
            'persistence'=>['fields'=>['date','end_date']], 'follow_up'=>['processes'=>['expiry_check','dedupe_check','review_contract_recheck']],
            'postcondition'=>['end_not_before_start'=>true,'task_absent'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'date_range_correction'],
        ]);
    }
    if ($isMultiDay && $endDate === '') {
        $tasks[] = be_cc_review_task([
            'task_id'=>'schedule.multi_day','finding_code'=>'missing_end_date','field_scope'=>['schedule_type','end_date'],
            'title'=>'Mehrtagesevent vervollständigen','message'=>'Für den mehrtägigen Termin fehlt das Enddatum.',
            'evidence'=>be_cc_review_evidence($payload, 'end_date', '', 'human_required'),
            'actions'=>[be_cc_review_action('set_fields','Zeitraum speichern',[
                be_cc_review_field('schedule_type','Terminart','select','multi_day',[
                    ['value'=>'single','label'=>'Einzeltermin'],['value'=>'multi_day','label'=>'Mehrtägig'],['value'=>'series','label'=>'Terminserie'],
                ]),
                be_cc_review_field('end_date','Enddatum','date',$endDate),
            ])],
            'persistence'=>['fields'=>['schedule_type','end_date']], 'follow_up'=>['processes'=>['series_check','dedupe_check','review_contract_recheck']],
            'postcondition'=>['end_date_present'=>true,'task_absent'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'schedule_type_resolution'],
        ]);
    }

    $effectiveEnd = $endDate !== '' ? $endDate : $date;
    if ($effectiveEnd !== '' && be_cc_valid_iso_day($effectiveEnd) && be_cc_valid_iso_day($today) && $effectiveEnd < $today && !in_array($currentStatus, ['cancelled','canceled','archived'], true)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'schedule.expired','finding_code'=>'expired_event','field_scope'=>['date','end_date'],
            'title'=>'Vergangenen Termin klären','message'=>'Der gespeicherte Termin liegt bereits in der Vergangenheit.',
            'evidence'=>be_cc_review_evidence($payload, 'end_date', $effectiveEnd, 'officially_verified', 'Der Termin endet vor dem aktuellen Prüftag.'),
            'actions'=>[
                be_cc_review_action('reject_expired','Als abgelaufen ablehnen',[],['decision_class'=>'rejected_not_event'],'danger'),
                be_cc_review_action('set_fields','Neuen Termin speichern',[be_cc_review_field('date','Startdatum','date',$date),be_cc_review_field('end_date','Enddatum','date',$endDate,[],false)]),
                be_cc_review_action('snooze_case','Quelle später erneut prüfen',[be_cc_review_field('suppress_until','Wiedervorlage','date')]),
            ],
            'persistence'=>['fields'=>['date','end_date','status']], 'follow_up'=>['processes'=>['dedupe_check','source_recheck']],
            'postcondition'=>['future_or_terminal'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'expiry_resolution'],
        ]);
    }

    if (in_array($currentStatus, ['cancelled','canceled'], true)) {
        $tasks[] = be_cc_review_task([
            'task_id'=>'schedule.cancelled','finding_code'=>'event_cancelled','field_scope'=>['event_status'],
            'title'=>'Absage bestätigen','message'=>'Die Quelle kennzeichnet die Veranstaltung als abgesagt.',
            'evidence'=>be_cc_review_evidence($payload, 'event_status', $currentStatus, 'officially_verified'),
            'actions'=>[
                be_cc_review_action('reject_cancelled','Absage bestätigen und schließen',[],['decision_class'=>'rejected_not_event'],'danger'),
                be_cc_review_action('set_fields','Status korrigieren',[be_cc_review_field('event_status','Aktualitätsstatus','select',$currentStatus,[['value'=>'scheduled','label'=>'Findet statt'],['value'=>'postponed','label'=>'Verschoben'],['value'=>'provisional','label'=>'Unter Vorbehalt']])]),
            ],
            'persistence'=>['fields'=>['event_status','status']], 'follow_up'=>['processes'=>['source_recheck']],
            'postcondition'=>['terminal_or_status_changed'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'cancellation_resolution'],
        ]);
    }
