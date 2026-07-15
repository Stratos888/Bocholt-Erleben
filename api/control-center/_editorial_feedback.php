<?php
declare(strict_types=1);

require_once __DIR__ . '/_decision_contract.php';

/** Side-effect-free editorial text observation and aggregation contracts. */

function be_cc_feedback_text(mixed $value): string
{
    return trim((string)preg_replace('/\s+/u', ' ', (string)$value));
}

function be_cc_feedback_lower(string $value): string
{
    return be_cc_unicode_lower(be_cc_feedback_text($value));
}

function be_cc_feedback_words(string $value): array
{
    $parts = preg_split('/[^\p{L}\p{N}]+/u', be_cc_feedback_lower($value), -1, PREG_SPLIT_NO_EMPTY);
    return is_array($parts) ? $parts : [];
}

function be_cc_feedback_word_delta(string $before, string $after): array
{
    $beforeCounts = array_count_values(be_cc_feedback_words($before));
    $afterCounts = array_count_values(be_cc_feedback_words($after));
    $added = [];
    $removed = [];
    foreach ($afterCounts as $word => $count) {
        for ($i = 0; $i < max(0, $count - (int)($beforeCounts[$word] ?? 0)); $i++) $added[] = $word;
    }
    foreach ($beforeCounts as $word => $count) {
        for ($i = 0; $i < max(0, $count - (int)($afterCounts[$word] ?? 0)); $i++) $removed[] = $word;
    }
    return ['added'=>$added, 'removed'=>$removed];
}

function be_cc_feedback_phrase_count(string $text, array $phrases): int
{
    $lower = be_cc_feedback_lower($text);
    $count = 0;
    foreach ($phrases as $phrase) $count += substr_count($lower, be_cc_feedback_lower((string)$phrase));
    return $count;
}

function be_cc_feedback_repeated_sentence_count(string $text): int
{
    $sentences = preg_split('/[.!?]+/u', be_cc_feedback_lower($text), -1, PREG_SPLIT_NO_EMPTY);
    $sentences = array_values(array_filter(array_map('trim', is_array($sentences) ? $sentences : []), static fn(string $item): bool => be_cc_unicode_length($item) >= 12));
    return count($sentences) - count(array_unique($sentences));
}

function be_cc_editorial_change_categories(string $before, string $final, array $context = []): array
{
    $categories = [];
    $promotion = ['einzigartig','unvergesslich','jetzt sichern','erleben sie','freuen sie sich','kommt vorbei','dürfen sie nicht verpassen','highlight'];
    $sourcePhrases = ['laut veranstalter','die quelle nennt','auf der website heißt es','weitere informationen gibt es'];
    $unsupported = ['garantiert','auf jeden fall','das beste','für jeden etwas'];
    if (be_cc_feedback_phrase_count($final, $promotion) < be_cc_feedback_phrase_count($before, $promotion)) $categories[] = 'advertising_language_removed';
    if (be_cc_feedback_phrase_count($final, $sourcePhrases) < be_cc_feedback_phrase_count($before, $sourcePhrases)) $categories[] = 'source_attribution_removed';
    if (be_cc_feedback_phrase_count($final, $unsupported) < be_cc_feedback_phrase_count($before, $unsupported)) $categories[] = 'unsupported_claim_removed';
    $city = be_cc_feedback_lower((string)($context['city'] ?? ''));
    $location = be_cc_feedback_lower((string)($context['location'] ?? ''));
    $beforeLower = be_cc_feedback_lower($before);
    $finalLower = be_cc_feedback_lower($final);
    if (($city !== '' && !str_contains($beforeLower, $city) && str_contains($finalLower, $city))
        || ($location !== '' && !str_contains($beforeLower, $location) && str_contains($finalLower, $location))) {
        $categories[] = 'local_relevance_added';
    }
    $targetTerms = ['kinder','familien','jugendliche','erwachsene','senioren'];
    if (be_cc_feedback_phrase_count($final, $targetTerms) > be_cc_feedback_phrase_count($before, $targetTerms)) $categories[] = 'target_group_added';
    $activityTerms = ['ausprobieren','mitmachen','entdecken','besuchen','tanzen','spielen','zuhören','anschauen'];
    if (be_cc_feedback_phrase_count($final, $activityTerms) > be_cc_feedback_phrase_count($before, $activityTerms)) $categories[] = 'concrete_activity_added';
    if (be_cc_unicode_length(be_cc_feedback_text($final)) < be_cc_unicode_length(be_cc_feedback_text($before)) * 0.8) $categories[] = 'shortened';
    $precisionPattern = '/\b(?:\d{1,2}[.:]\d{2}|\d{1,2}\.\d{1,2}\.\d{4})\b/u';
    if (preg_match($precisionPattern, $final) && !preg_match($precisionPattern, $before)) $categories[] = 'more_precise';
    if (be_cc_feedback_repeated_sentence_count($final) < be_cc_feedback_repeated_sentence_count($before)) $categories[] = 'repetition_removed';
    if (in_array('advertising_language_removed', $categories, true) || in_array('unsupported_claim_removed', $categories, true)) $categories[] = 'more_factual_tone';
    if ($before !== $final && !$categories) $categories[] = 'editorial_precision';
    return array_values(array_unique($categories));
}

function be_cc_editorial_feedback_observation(string $eventId, string $issueCode, string $current, string $suggested, string $final, array $context = []): array
{
    $current = be_cc_feedback_text($current);
    $suggested = be_cc_feedback_text($suggested);
    $final = be_cc_feedback_text($final);
    if ($eventId === '' || $final === '') throw new InvalidArgumentException('Event-ID und finale Beschreibung sind erforderlich.');
    $categories = be_cc_editorial_change_categories($suggested !== '' ? $suggested : $current, $final, $context);
    $contract = be_cc_editorial_contract_data()['editorial_feedback'] ?? [];
    return [
        'observation_id'=>hash('sha256', $eventId . '|' . $issueCode . '|' . $final),
        'event_id'=>$eventId,
        'issue_code'=>$issueCode,
        'current_text'=>$current,
        'suggested_text'=>$suggested,
        'final_text'=>$final,
        'diff'=>be_cc_feedback_word_delta($suggested !== '' ? $suggested : $current, $final),
        'categories'=>$categories,
        'decision_class'=>'corrected',
        'prompt_rule_version'=>trim((string)($context['prompt_rule_version'] ?? 'unknown')),
        'source_fingerprint'=>trim((string)($context['source_fingerprint'] ?? '')),
        'content_fingerprint'=>trim((string)($context['content_fingerprint'] ?? '')),
        'activation_state'=>'observation',
        'eligible_for_prompt_context'=>false,
        'activation_min_distinct_events'=>(int)($contract['activation_min_distinct_events'] ?? 3),
    ];
}

function be_cc_editorial_feedback_aggregate(array $observations, ?int $threshold = null): array
{
    $contract = be_cc_editorial_contract_data()['editorial_feedback'] ?? [];
    $threshold ??= (int)($contract['activation_min_distinct_events'] ?? 3);
    $groups = [];
    foreach ($observations as $observation) {
        if (!is_array($observation)) continue;
        $eventId = trim((string)($observation['event_id'] ?? ''));
        foreach (($observation['categories'] ?? []) as $category) {
            $category = trim((string)$category);
            if ($category === '' || $eventId === '') continue;
            $groups[$category]['events'][$eventId] = true;
            $groups[$category]['count'] = (int)($groups[$category]['count'] ?? 0) + 1;
            $groups[$category]['false_positive_count'] = (int)($groups[$category]['false_positive_count'] ?? 0) + (int)($observation['false_positive_count'] ?? 0);
        }
    }
    $items = [];
    foreach ($groups as $category => $group) {
        $distinct = count($group['events'] ?? []);
        $falsePositives = (int)($group['false_positive_count'] ?? 0);
        $state = $falsePositives > 0 ? 'review_required' : ($distinct >= $threshold ? 'candidate' : 'observation');
        $items[] = [
            'category'=>$category,
            'observation_count'=>(int)($group['count'] ?? 0),
            'distinct_event_count'=>$distinct,
            'false_positive_count'=>$falsePositives,
            'activation_state'=>$state,
            'eligible_for_prompt_context'=>$state === 'candidate',
            'permanent_rule_change_allowed'=>false,
        ];
    }
    usort($items, static fn(array $a, array $b): int => $b['distinct_event_count'] <=> $a['distinct_event_count'] ?: strcmp($a['category'], $b['category']));
    return ['threshold'=>$threshold, 'items'=>$items];
}
