<?php
declare(strict_types=1);

require_once __DIR__ . '/_decision_contract.php';

/** Side-effect-free normalized review contract for event candidates. */

function be_cc_contract_value(array $row, array $aliases, mixed $default = ''): mixed
{
    foreach ($aliases as $alias) {
        if (array_key_exists($alias, $row)) return $row[$alias];
    }
    return $default;
}

function be_cc_contract_text(array $row, array $aliases): string
{
    $value = be_cc_contract_value($row, $aliases, '');
    return is_array($value) ? trim(implode(', ', array_map('strval', $value))) : trim((string)$value);
}

function be_cc_contract_bool(array $row, array $aliases): bool
{
    $value = be_cc_contract_value($row, $aliases, false);
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','ja','hard','required'], true);
}

function be_cc_contract_url_valid(string $value): bool
{
    return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('/^https?:\/\//i', $value) === 1;
}

function be_cc_contract_add_issue(array &$issues, string $code, string $field, string $message): void
{
    $issues[] = ['code'=>$code, 'field'=>$field, 'message'=>$message];
}

function be_cc_event_candidate_review_contract(array $payload): array
{
    $title = be_cc_contract_text($payload, ['title','eventName']);
    $date = be_cc_contract_text($payload, ['date','start_date']);
    $endDate = be_cc_contract_text($payload, ['endDate','end_date','enddate']);
    $time = be_cc_contract_text($payload, ['time','start_time']);
    $timeReason = be_cc_contract_text($payload, ['time_reason','time_status','time_details','schedule_text']);
    $city = be_cc_contract_text($payload, ['city','stadt']);
    $localReference = be_cc_contract_text($payload, ['local_reference','local_scope']);
    $location = be_cc_contract_text($payload, ['location','venue','ort']);
    $address = be_cc_contract_text($payload, ['address','location_address','adresse']);
    $category = be_cc_contract_text($payload, ['kategorie_suggestion','kategorie','category']);
    $sourceUrl = be_cc_contract_text($payload, ['source_url','url','event_url']);
    $sourceName = be_cc_contract_text($payload, ['source_name','organization_name','veranstalter']);
    $ticketUrl = be_cc_contract_text($payload, ['ticket_url','booking_url']);
    $description = be_cc_contract_text($payload, ['final_description','description','description_text']);
    $visualKey = be_cc_contract_text($payload, ['visual_key']);
    $visualMotif = be_cc_contract_text($payload, ['visual_motif','image_visual_motif']);
    $stableId = be_cc_contract_text($payload, ['id_suggestion','id','event_id']);
    $matchedEventId = be_cc_contract_text($payload, ['matched_event_id','duplicate_event_id']);
    $isMultiDay = be_cc_contract_bool($payload, ['is_multi_day','multi_day']);
    $addressRequired = be_cc_contract_bool($payload, ['address_required','route_enabled']);
    $ticketRequired = be_cc_contract_bool($payload, ['ticket_required','booking_required']);
    $weakSource = in_array(strtolower(be_cc_contract_text($payload, ['source_quality','source_status'])), ['weak','insufficient','unverified'], true);
    $hardDuplicate = $matchedEventId !== '' || be_cc_contract_bool($payload, ['hard_duplicate','duplicate_conflict']);

    $blockers = [];
    $warnings = [];
    $requiredFields = [];
    $conditionalFields = [];

    foreach ([
        'title'=>$title,
        'date'=>$date,
        'location'=>$location,
        'category'=>$category,
        'source_url'=>$sourceUrl,
        'description'=>$description,
        'visual_key'=>$visualKey,
        'visual_motif'=>$visualMotif,
    ] as $field => $value) {
        if ($value === '') {
            $requiredFields[] = $field;
            be_cc_contract_add_issue($blockers, 'missing_required', $field, 'Pflichtangabe fehlt: ' . $field . '.');
        }
    }
    if ($city === '' && $localReference === '') {
        $requiredFields[] = 'city_or_local_reference';
        be_cc_contract_add_issue($blockers, 'missing_required', 'city_or_local_reference', 'Stadt oder eindeutiger lokaler Bezug fehlt.');
    }

    if ($date !== '' && !be_cc_valid_iso_day($date)) {
        be_cc_contract_add_issue($blockers, 'invalid_date', 'date', 'Startdatum ist nicht gültig.');
    }
    if ($endDate !== '' && !be_cc_valid_iso_day($endDate)) {
        be_cc_contract_add_issue($blockers, 'invalid_end_date', 'end_date', 'Enddatum ist nicht gültig.');
    }
    if ($date !== '' && $endDate !== '' && be_cc_valid_iso_day($date) && be_cc_valid_iso_day($endDate) && $endDate < $date) {
        be_cc_contract_add_issue($blockers, 'end_before_start', 'end_date', 'Enddatum liegt vor dem Startdatum.');
    }
    if ($isMultiDay && $endDate === '') {
        $conditionalFields[] = 'end_date';
        be_cc_contract_add_issue($blockers, 'missing_conditional', 'end_date', 'Mehrtagesevent benötigt ein Enddatum.');
    }
    if ($time === '') {
        $conditionalFields[] = 'time_reason';
        if ($timeReason === '') {
            be_cc_contract_add_issue($blockers, 'missing_time_reason', 'time_reason', 'Fehlende Uhrzeit muss fachlich erklärt sein.');
        } else {
            be_cc_contract_add_issue($warnings, 'time_not_fixed', 'time', $timeReason);
        }
    }
    if ($addressRequired && $address === '') {
        $conditionalFields[] = 'address';
        be_cc_contract_add_issue($blockers, 'missing_conditional', 'address', 'Adresse ist für diesen Event erforderlich.');
    }
    if ($ticketRequired && $ticketUrl === '') {
        $conditionalFields[] = 'ticket_url';
        be_cc_contract_add_issue($blockers, 'missing_conditional', 'ticket_url', 'Ticketlink ist für diesen Event erforderlich.');
    }
    if ($sourceUrl !== '' && !be_cc_contract_url_valid($sourceUrl)) {
        be_cc_contract_add_issue($blockers, 'invalid_source_url', 'source_url', 'Offizielle Quelle ist keine gültige HTTP(S)-URL.');
    }
    if ($ticketUrl !== '' && !be_cc_contract_url_valid($ticketUrl)) {
        be_cc_contract_add_issue($blockers, 'invalid_ticket_url', 'ticket_url', 'Ticketlink ist keine gültige HTTP(S)-URL.');
    }
    if ($weakSource) be_cc_contract_add_issue($blockers, 'weak_source', 'source_url', 'Quelle ist nicht belastbar genug.');
    if ($hardDuplicate) be_cc_contract_add_issue($blockers, 'hard_duplicate', 'dedupe', 'Harter Dublettenkonflikt ist offen.');

    $ready = $blockers === [];
    return [
        'identity' => ['stable_id'=>$stableId],
        'facts' => [
            'title'=>$title, 'date'=>$date, 'end_date'=>$endDate, 'time'=>$time,
            'time_reason'=>$timeReason, 'city'=>$city, 'local_reference'=>$localReference,
            'location'=>$location, 'address'=>$address,
        ],
        'description' => ['final'=>$description],
        'source' => ['name'=>$sourceName, 'url'=>$sourceUrl, 'ticket_url'=>$ticketUrl, 'weak'=>$weakSource],
        'classification' => ['category'=>$category],
        'visual' => ['key'=>$visualKey, 'motif'=>$visualMotif],
        'dedupe' => ['hard_conflict'=>$hardDuplicate, 'matched_event_id'=>$matchedEventId],
        'quality' => ['warnings'=>$warnings],
        'decision_gate' => [
            'ready'=>$ready,
            'blockers'=>$blockers,
            'warnings'=>$warnings,
            'required_fields'=>array_values(array_unique($requiredFields)),
            'conditional_fields'=>array_values(array_unique($conditionalFields)),
        ],
        'actions' => [
            ['key'=>'approve','enabled'=>$ready],
            ['key'=>'edit_and_approve','enabled'=>true],
            ['key'=>'snooze','enabled'=>true],
            ['key'=>'reject','enabled'=>true],
        ],
    ];
}
