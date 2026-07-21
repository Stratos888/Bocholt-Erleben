<?php
    if ($visualKey === '') {
        $tasks[] = be_cc_review_task([
            'task_id'=>'visual.key','finding_code'=>'missing_visual_key','field_scope'=>['visual_key'],
            'title'=>'Bildbereich festlegen','message'=>'Für die Veranstaltung ist noch kein passender Bildbereich bestimmt.',
            'evidence'=>be_cc_review_evidence($payload, 'visual_key', '', 'system_suggestion'),
            'actions'=>[be_cc_review_action('set_fields','Bildbereich speichern',[be_cc_review_field('visual_key','Bildbereich','select','',be_cc_event_visual_key_options())])],
            'persistence'=>['fields'=>['visual_key']], 'follow_up'=>['processes'=>['visual_motif_resolution','review_contract_recheck']],
            'postcondition'=>['visual_key_present'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'visual_key_selection'],
        ]);
    } else {
        $boundAsset = be_cc_event_visual_asset_by_id($visualAssetId);
        $assetMotifMatches = $boundAsset !== null && ($visualMotif === '' || $boundAsset['visual_motif'] === $visualMotif || $visualAssetRole === 'fallback');
        if ($visualAssetId !== '' && ($boundAsset === null || $boundAsset['visual_key'] !== $visualKey || !$assetMotifMatches)) {
            $tasks[] = be_cc_review_task([
                'task_id'=>'visual.asset','finding_code'=>'invalid_visual_asset_binding','field_scope'=>['visual_key','visual_motif','visual_asset_id'],
                'state'=>'conflict','title'=>'Bildbindung korrigieren','message'=>'Das gebundene Bild ist nicht verfügbar oder passt nicht zu Key und Motiv.',
                'evidence'=>be_cc_review_evidence($payload, 'visual_asset_id', $visualAssetId, 'conflict'),
                'actions'=>[be_cc_review_action('clear_visual_asset','Bildbindung lösen',[],['visual_asset_id'=>''])],
                'persistence'=>['fields'=>['visual_asset_id']], 'follow_up'=>['processes'=>['visual_asset_resolution','review_contract_recheck']],
                'postcondition'=>['valid_asset_or_unbound'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'visual_binding_correction'],
            ]);
        } elseif ($boundAsset === null) {
            $suggestion = $visualMotif === '' ? be_cc_infer_safe_visual_motif($payload, $visualKey) : ['motif'=>$visualMotif,'confidence'=>'high','reason'=>'Bereits gespeichertes Motiv.'];
            $effectiveMotif = $visualMotif !== '' ? $visualMotif : (string)$suggestion['motif'];
            $exactAssets = $effectiveMotif !== '' ? be_cc_event_visual_ready_assets($visualKey, $effectiveMotif) : [];
            if ($effectiveMotif !== '' && $suggestion['confidence'] === 'high' && $exactAssets) {
                $asset = $exactAssets[0];
                $tasks[] = be_cc_review_task([
                    'task_id'=>'visual.asset','finding_code'=>$visualMotif===''?'visual_suggestion_ready':'visual_asset_unbound','field_scope'=>['visual_key','visual_motif','visual_asset_id'],
                    'state'=>be_cc_review_text($payload,['visual_gap_id'])!==''?'candidate_ready':'open',
                    'title'=>'Konkretes Bild bestätigen','message'=>$visualMotif===''?'Der Visual-Prozess erkennt ein passendes Motiv und besitzt bereits ein freigegebenes Bild.':'Für das bestätigte Motiv ist ein freigegebenes Bild verfügbar.',
                    'evidence'=>[
                        'status'=>'system_suggestion','confidence'=>(string)$suggestion['confidence'],'source_name'=>'Event-Visual-Pool','source_url'=>'',
                        'observed_value'=>$effectiveMotif,'explanation'=>(string)$suggestion['reason'],'asset'=>$asset,
                    ],
                    'actions'=>[
                        be_cc_review_action('use_visual_asset','Dieses Bild verwenden',[],['visual_key'=>$visualKey,'visual_motif'=>$effectiveMotif,'visual_asset_id'=>$asset['id'],'visual_asset_role'=>'specific']),
                        be_cc_review_action('choose_visual_asset','Anderes Bild anzeigen',[
                            be_cc_review_field('visual_asset_id','Alternatives Bild','asset_select','',array_map(static fn(array $item): array=>['value'=>$item['id'],'label'=>$item['alt']?:$item['id'],'asset'=>$item],$exactAssets)),
                        ],['visual_key'=>$visualKey,'visual_motif'=>$effectiveMotif,'visual_asset_role'=>'specific'],'secondary'),
                        be_cc_review_action('reject_visual_motif','Motiv passt nicht',[],['visual_motif'=>''],'secondary'),
                    ],
                    'persistence'=>['fields'=>['visual_key','visual_motif','visual_asset_id']],
                    'follow_up'=>['processes'=>['asset_availability_check','final_preview_render','review_contract_recheck']],
                    'postcondition'=>['bound_asset_ready'=>true,'preview_matches_binding'=>true],
                    'audit'=>['event'=>'review_task_resolved','learning'=>'visual_asset_selection'],
                ]);
            } elseif ($effectiveMotif === '') {
                $tasks[] = be_cc_review_task([
                    'task_id'=>'visual.motif','finding_code'=>'missing_visual_motif','field_scope'=>['visual_motif'],
                    'title'=>'Passendes Bildmotiv auswählen','message'=>'Der Bildbereich ist bekannt, aber das konkrete Motiv ist noch offen.',
                    'evidence'=>be_cc_review_evidence($payload, 'visual_motif', '', 'human_required', (string)$suggestion['reason']),
                    'actions'=>[be_cc_review_action('set_visual_motif','Motiv speichern',[be_cc_review_field('visual_motif','Bildmotiv','select','',be_cc_event_visual_motif_options($visualKey))],['visual_key'=>$visualKey])],
                    'persistence'=>['fields'=>['visual_key','visual_motif']], 'follow_up'=>['processes'=>['visual_asset_resolution','visual_gap_detection','review_contract_recheck']],
                    'postcondition'=>['visual_motif_present'=>true], 'audit'=>['event'=>'review_task_resolved','learning'=>'visual_motif_selection'],
                ]);
            } else {
                $fallbackAssets = array_values(array_filter(be_cc_event_visual_ready_assets($visualKey), static fn(array $asset): bool=>$asset['role']==='fallback'));
                $gapId = be_cc_review_gap_id($stableId, $visualKey, $effectiveMotif);
                $actions = [
                    be_cc_review_action('wait_for_visual_asset','Passendes Bild automatisch erstellen',[],['visual_key'=>$visualKey,'visual_motif'=>$effectiveMotif,'visual_gap_id'=>$gapId]),
                ];
                if ($fallbackAssets) {
                    $fallback = $fallbackAssets[0];
                    array_unshift($actions, be_cc_review_action('use_visual_fallback','Neutrales Übergangsbild verwenden',[],[
                        'visual_key'=>$visualKey,'visual_motif'=>$effectiveMotif,'visual_asset_id'=>$fallback['id'],'visual_asset_role'=>'fallback','visual_gap_id'=>$gapId,
                    ]));
                }
                $tasks[] = be_cc_review_task([
                    'task_id'=>'visual.asset','finding_code'=>'visual_asset_missing','field_scope'=>['visual_key','visual_motif','visual_asset_id','visual_gap_id'],
                    'state'=>be_cc_review_text($payload,['visual_gap_id'])!==''?'waiting_external':'open',
                    'title'=>'Passendes Bild vervollständigen','message'=>'Für das bestätigte Motiv ist noch kein freigegebenes exaktes Bild verfügbar.',
                    'evidence'=>[
                        'status'=>'unverifiable','confidence'=>'high','source_name'=>'Event-Visual-Pool','source_url'=>'',
                        'observed_value'=>$effectiveMotif,'explanation'=>'Der Pool enthält kein ready-Asset für dieses Motiv.',
                        'fallback'=>$fallbackAssets[0]??null,'gap_id'=>$gapId,
                    ],
                    'actions'=>$actions,
                    'persistence'=>['fields'=>['visual_key','visual_motif','visual_asset_id','visual_gap_id']],
                    'follow_up'=>['processes'=>['visual_gap_backlog','asset_generation_or_clearance','asset_quality_check','control_center_return']],
                    'postcondition'=>['exact_asset_candidate_returned_or_fallback_bound'=>true],
                    'audit'=>['event'=>'review_task_waiting','learning'=>'visual_gap_resolution'],
                ]);
            }
        }
    }
