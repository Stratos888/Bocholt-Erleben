<?php
declare(strict_types=1);

/**
 * Side-effect-free exception/task contract for event candidates.
 * Every unresolved finding exposes evidence, a direct action, canonical
 * persistence targets, the follow-up process and the verified end condition.
 */

const BE_CC_REVIEW_EVIDENCE_STATES = [
    'officially_verified', 'cross_checked', 'format_validated', 'system_suggestion',
    'human_required', 'unverifiable', 'conflict',
];

const BE_CC_TIME_STATUSES = [
    'fixed_time', 'all_day', 'during_opening_hours', 'multiple_times',
    'time_not_published', 'time_conflict', 'time_not_applicable',
];

const BE_CC_ACCESS_STATUSES = [
    'free_no_registration', 'free_registration', 'paid_ticket', 'registration_only',
    'not_open', 'sold_out', 'waitlist', 'not_applicable',
];

function be_cc_review_token(mixed $value): string
{
    $raw = trim(be_cc_unicode_lower((string)$value));
    $raw = strtr($raw, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','-'=>'_',' '=>'_']);
    return trim((string)preg_replace('/[^a-z0-9_]+/', '', $raw), '_');
}

function be_cc_review_text(array $payload, array $aliases): string
{
    return be_cc_contract_text($payload, $aliases);
}

function be_cc_review_bool(array $payload, array $aliases): bool
{
    return be_cc_contract_bool($payload, $aliases);
}

function be_cc_review_array(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function be_cc_review_humanize(string $token): string
{
    $labels = [
        'fixed_time'=>'Feste Uhrzeit', 'all_day'=>'Ganztägig',
        'during_opening_hours'=>'Während der Öffnungszeiten', 'multiple_times'=>'Mehrere Beginnzeiten',
        'time_not_published'=>'Uhrzeit noch nicht veröffentlicht', 'time_conflict'=>'Widersprüchliche Uhrzeiten',
        'time_not_applicable'=>'Uhrzeit nicht anwendbar',
        'free_no_registration'=>'Kostenlos, ohne Anmeldung', 'free_registration'=>'Kostenlos, Anmeldung erforderlich',
        'paid_ticket'=>'Ticket erforderlich', 'registration_only'=>'Anmeldung erforderlich',
        'not_open'=>'Anmeldung/Ticketverkauf noch nicht geöffnet', 'sold_out'=>'Ausverkauft',
        'waitlist'=>'Warteliste', 'not_applicable'=>'Kein Zugangslink erforderlich',
    ];
    if (isset($labels[$token])) return $labels[$token];
    $text = str_replace('_', ' ', $token);
    return $text === '' ? '' : ucfirst($text);
}

function be_cc_review_evidence(array $payload, string $field, mixed $value, string $fallback = 'format_validated', string $explanation = ''): array
{
    $map = be_cc_review_array($payload['field_evidence'] ?? $payload['field_evidence_json'] ?? []);
    $entry = is_array($map[$field] ?? null) ? $map[$field] : [];
    $status = be_cc_review_token($entry['status'] ?? $entry['verification_status'] ?? $fallback);
    if (!in_array($status, BE_CC_REVIEW_EVIDENCE_STATES, true)) $status = $fallback;
    $confidence = be_cc_review_token($entry['confidence'] ?? '');
    if (!in_array($confidence, ['high','medium','low'], true)) {
        $confidence = in_array($status, ['officially_verified','cross_checked'], true) ? 'high'
            : ($status === 'format_validated' ? 'medium' : 'low');
    }
    return [
        'status' => $status,
        'confidence' => $confidence,
        'source_name' => trim((string)($entry['source_name'] ?? $payload['source_name'] ?? $payload['organization_name'] ?? '')),
        'source_url' => trim((string)($entry['source_url'] ?? $payload['source_url'] ?? $payload['url'] ?? '')),
        'observed_value' => is_scalar($value) ? trim((string)$value) : '',
        'explanation' => trim((string)($entry['explanation'] ?? $explanation)),
    ];
}

function be_cc_review_action(string $key, string $label, array $fields = [], array $defaults = [], string $tone = 'primary'): array
{
    return [
        'key'=>$key,
        'label'=>$label,
        'tone'=>$tone,
        'fields'=>$fields,
        'defaults'=>$defaults,
    ];
}

function be_cc_review_field(string $name, string $label, string $type, mixed $value = '', array $options = [], bool $required = true, string $help = ''): array
{
    return [
        'name'=>$name,
        'label'=>$label,
        'type'=>$type,
        'value'=>is_scalar($value) ? (string)$value : '',
        'options'=>$options,
        'required'=>$required,
        'help'=>$help,
    ];
}

function be_cc_review_task(array $task): array
{
    $task += [
        'task_id'=>'', 'finding_code'=>'', 'field_scope'=>[], 'severity'=>'blocking',
        'state'=>'open', 'title'=>'Prüfpunkt klären', 'message'=>'', 'evidence'=>[],
        'actions'=>[], 'persistence'=>[], 'follow_up'=>[], 'postcondition'=>[],
        'audit'=>[],
    ];
    $revisionInput = [
        'task_id'=>$task['task_id'], 'finding_code'=>$task['finding_code'],
        'field_scope'=>$task['field_scope'], 'state'=>$task['state'],
        'evidence'=>$task['evidence'], 'actions'=>$task['actions'],
        'persistence'=>$task['persistence'], 'follow_up'=>$task['follow_up'],
    ];
    $task['task_revision'] = substr(hash('sha256', json_encode($revisionInput, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)), 0, 24);
    return $task;
}

function be_cc_event_visual_pool_contract(): array
{
    static $pool = null;
    if ($pool !== null) return $pool;
    $path = dirname(__DIR__, 2) . '/data/event_visual_pool.json';
    if (!is_file($path)) return $pool = ['pools'=>[]];
    $decoded = json_decode((string)file_get_contents($path), true);
    return $pool = is_array($decoded) ? $decoded : ['pools'=>[]];
}

function be_cc_event_visual_ready_assets(string $visualKey, string $visualMotif = ''): array
{
    $key = be_cc_review_token($visualKey);
    $motif = be_cc_review_token($visualMotif);
    $pool = be_cc_event_visual_pool_contract()['pools'][$key] ?? [];
    $images = is_array($pool['images'] ?? null) ? $pool['images'] : [];
    $out = [];
    foreach ($images as $image) {
        if (!is_array($image) || trim((string)($image['status'] ?? '')) !== 'ready') continue;
        $imageMotif = be_cc_review_token($image['visual_motif'] ?? '');
        if ($motif !== '' && $imageMotif !== $motif) continue;
        $out[] = [
            'id'=>trim((string)($image['id'] ?? '')),
            'src'=>trim((string)($image['src'] ?? '')),
            'alt'=>trim((string)($image['alt'] ?? '')),
            'visual_motif'=>$imageMotif,
            'rights_status'=>trim((string)($image['rights_status'] ?? '')),
            'review_status'=>trim((string)($image['review_status'] ?? '')),
            'is_symbolic'=>(bool)($image['is_symbolic'] ?? false),
            'is_documentary'=>(bool)($image['is_documentary'] ?? false),
            'role'=>str_starts_with($imageMotif, 'neutral_') ? 'fallback' : 'specific',
        ];
    }
    return array_values(array_filter($out, static fn(array $asset): bool => $asset['id'] !== '' && $asset['src'] !== ''));
}

function be_cc_event_visual_asset_by_id(string $assetId): ?array
{
    if ($assetId === '') return null;
    foreach ((array)(be_cc_event_visual_pool_contract()['pools'] ?? []) as $key => $pool) {
        foreach ((array)($pool['images'] ?? []) as $image) {
            if (!is_array($image) || trim((string)($image['id'] ?? '')) !== $assetId) continue;
            if (trim((string)($image['status'] ?? '')) !== 'ready') return null;
            return [
                'id'=>$assetId,
                'src'=>trim((string)($image['src'] ?? '')),
                'alt'=>trim((string)($image['alt'] ?? '')),
                'visual_key'=>be_cc_review_token((string)$key),
                'visual_motif'=>be_cc_review_token($image['visual_motif'] ?? ''),
                'rights_status'=>trim((string)($image['rights_status'] ?? '')),
                'review_status'=>trim((string)($image['review_status'] ?? '')),
                'is_symbolic'=>(bool)($image['is_symbolic'] ?? false),
                'is_documentary'=>(bool)($image['is_documentary'] ?? false),
            ];
        }
    }
    return null;
}

function be_cc_event_visual_key_options(): array
{
    $options = [];
    foreach ((array)(be_cc_event_visual_pool_contract()['pools'] ?? []) as $key=>$pool) {
        $token = be_cc_review_token((string)$key);
        if ($token === '') continue;
        $options[] = ['value'=>$token, 'label'=>trim((string)($pool['label'] ?? '')) ?: be_cc_review_humanize($token)];
    }
    return $options;
}

function be_cc_event_visual_motif_options(string $visualKey): array
{
    $seen = [];
    $options = [];
    foreach (be_cc_event_visual_ready_assets($visualKey) as $asset) {
        $motif = (string)$asset['visual_motif'];
        if ($motif === '' || isset($seen[$motif])) continue;
        $seen[$motif] = true;
        $options[] = ['value'=>$motif, 'label'=>be_cc_review_humanize($motif)];
    }
    return $options;
}

function be_cc_infer_safe_visual_motif(array $payload, string $visualKey): array
{
    $explicit = be_cc_review_token(be_cc_review_text($payload, ['visual_motif_suggestion','suggested_visual_motif','image_visual_motif_suggestion']));
    if ($explicit !== '') return ['motif'=>$explicit,'confidence'=>'high','reason'=>'Motivvorschlag aus dem vorgeschalteten Visual-Prozess.'];
    $hay = be_cc_unicode_lower(implode(' ', [
        be_cc_review_text($payload, ['title','eventName']),
        be_cc_review_text($payload, ['description','final_description']),
        be_cc_review_text($payload, ['category','kategorie_suggestion','kategorie']),
        be_cc_review_text($payload, ['location','venue','ort']),
    ]));
    $rules = [
        'art_exhibition_gallery'=>[
            ['/(kunstmarkt|cityart)/u','art_market','high','Titel oder Beschreibung benennt einen Kunstmarkt.'],
            ['/(kreativausstellung|kreativschmiede)/u','creative_exhibition','high','Titel oder Beschreibung benennt eine Kreativausstellung.'],
        ],
        'parade_festzug'=>[
            ['/(\bcsd\b|pride)/u','csd_pride_parade','high','Titel oder Beschreibung benennt CSD/Pride.'],
            ['/(blaskapelle|musikzug|spielmannszug|tambourcorps)/u','marching_band_procession','high','Titel oder Beschreibung benennt einen Musikzug.'],
        ],
        'market_stalls'=>[
            ['/(stoffmarkt|stoffe)/u','fabric_market','high','Titel oder Beschreibung benennt einen Stoffmarkt.'],
            ['/(flohmarkt|trödelmarkt|troedelmarkt)/u','flea_market','high','Titel oder Beschreibung benennt einen Flohmarkt.'],
            ['/(martinsmarkt)/u','seasonal_martinsmarkt','high','Titel benennt einen Martinsmarkt.'],
        ],
        'food_drink_festival'=>[
            ['/(weinfest|wine festival)/u','wine_festival','high','Titel oder Beschreibung benennt ein Weinfest.'],
            ['/(street.?food|food festival)/u','street_food_festival','high','Titel oder Beschreibung benennt ein Street-Food-Festival.'],
        ],
        'classical_music'=>[
            ['/(orgel|organ concert)/u','organ_concert','high','Titel oder Beschreibung benennt ein Orgelkonzert.'],
            ['/(chor|chorkonzert)/u','choir','high','Titel oder Beschreibung benennt einen Chor.'],
        ],
        'kids_stage_story'=>[
            ['/(puppentheater|puppenspiel|figurentheater)/u','puppet_theater','high','Titel oder Beschreibung benennt Puppen- oder Figurentheater.'],
        ],
        'business_messe_info'=>[
            ['/(vereinsmesse|vereine)/u','association_fair','high','Titel oder Beschreibung benennt eine Vereinsmesse.'],
            ['/(gesundheitsberufemesse|pflegeberufe)/u','health_career_fair','high','Titel oder Beschreibung benennt eine Gesundheitsberufemesse.'],
        ],
        'family_play_outdoor'=>[
            ['/(playfountain|wasserspiel|wasserfontäne|wasserfontaene)/u','playfountain_water_splash','high','Titel oder Beschreibung benennt eine Wasserspielfläche.'],
            ['/(kinderflohmarkt|kindertrödel)/u','children_flea_market','high','Titel oder Beschreibung benennt einen Kinderflohmarkt.'],
        ],
    ];
    foreach ($rules[$visualKey] ?? [] as [$pattern,$motif,$confidence,$reason]) {
        if (preg_match($pattern, $hay) === 1) return ['motif'=>$motif,'confidence'=>$confidence,'reason'=>$reason];
    }
    return ['motif'=>'','confidence'=>'low','reason'=>'Kein ausreichend eindeutiger Motivtreffer.'];
}

function be_cc_review_gap_id(string $stableId, string $visualKey, string $visualMotif): string
{
    return 'visual-gap-' . substr(hash('sha256', $stableId . '|' . $visualKey . '|' . $visualMotif), 0, 20);
}

function be_cc_review_add_required_task(array &$tasks, array $payload, string $field, string $value, string $title, string $message, string $label, string $type = 'text'): void
{
    if ($value !== '') return;
    $tasks[] = be_cc_review_task([
        'task_id'=>'field.' . $field,
        'finding_code'=>'missing_' . $field,
        'field_scope'=>[$field],
        'title'=>$title,
        'message'=>$message,
        'evidence'=>be_cc_review_evidence($payload, $field, '', 'human_required', 'Die Angabe fehlt in den aktuell verfügbaren Quelldaten.'),
        'actions'=>[
            be_cc_review_action('set_fields', 'Angabe speichern', [be_cc_review_field($field, $label, $type)]),
        ],
        'persistence'=>['fields'=>[$field], 'source'=>'Inbox'],
        'follow_up'=>['processes'=>['review_contract_recheck']],
        'postcondition'=>['task_absent'=>true, 'source_fields'=>[$field]],
        'audit'=>['event'=>'review_task_resolved','learning'=>'manual_field_completion'],
    ]);
}
