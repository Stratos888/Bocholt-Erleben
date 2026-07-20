<?php
declare(strict_types=1);

/**
 * Canonical, side-effect-free event identity contract. The same JSON weights,
 * stopwords and thresholds are consumed by the Python intake matcher.
 */
function be_cc_event_identity_contract(): array
{
    static $contract = null;
    if (is_array($contract)) return $contract;
    $path = dirname(__DIR__, 2) . '/data/event_identity_contract.json';
    if (!is_file($path)) throw new RuntimeException('Der Event-Identitätsvertrag fehlt.');
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) throw new RuntimeException('Der Event-Identitätsvertrag ist ungültig.');
    $weights = is_array($decoded['weights'] ?? null) ? $decoded['weights'] : [];
    $sum = 0.0;
    foreach (['title','location','city','source_host'] as $key) $sum += (float)($weights[$key] ?? 0.0);
    if (abs($sum - 1.0) > 0.000000001) throw new RuntimeException('Die Event-Identitätsgewichte müssen 1 ergeben.');
    return $contract = $decoded;
}

function be_cc_event_identity_text(mixed $value): string
{
    $raw = strtr(trim((string)$value), ['Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
    $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw) : false;
    $raw = strtolower(is_string($ascii) && $ascii !== '' ? $ascii : $raw);
    $raw = (string)preg_replace('/[^a-z0-9]+/u', ' ', $raw);
    return trim((string)preg_replace('/\s+/u', ' ', $raw));
}

function be_cc_event_identity_value(array $item, array $aliases): string
{
    foreach ($aliases as $alias) {
        $value = trim((string)($item[$alias] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function be_cc_event_identity_fields(array $item): array
{
    return [
        'id'=>be_cc_event_identity_value($item, ['id_suggestion','id','event_id']),
        'title'=>be_cc_event_identity_value($item, ['title','eventName']),
        'date'=>be_cc_event_identity_value($item, ['date','start_date']),
        'end_date'=>be_cc_event_identity_value($item, ['endDate','end_date','enddate']),
        'city'=>be_cc_event_identity_value($item, ['city','stadt']),
        'location'=>be_cc_event_identity_value($item, ['location','venue','ort']),
        'url'=>be_cc_event_identity_value($item, ['source_url','url','event_url','official_source_url']),
    ];
}

function be_cc_event_identity_url(mixed $raw, ?array $contract = null): string
{
    $value = (string)preg_replace('/\s+/u', '', trim((string)$raw));
    if ($value === '') return '';
    if (!str_contains($value, '://')) $value = 'https://' . $value;
    $parts = parse_url($value);
    if (!is_array($parts)) return rtrim($value, '/');
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '') return rtrim($value, '/');
    $port = isset($parts['port']) ? ':' . (string)$parts['port'] : '';
    $path = (string)($parts['path'] ?? '');
    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    $tracking = array_fill_keys(array_map('strval', (array)(($contract ?? be_cc_event_identity_contract())['tracking_parameters'] ?? [])), true);
    foreach (array_keys($query) as $key) {
        $lower = strtolower(trim((string)$key));
        if (str_starts_with($lower, 'utm_') || isset($tracking[$lower])) unset($query[$key]);
    }
    $queryText = $query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';
    return rtrim($scheme . '://' . $host . $port . $path . $queryText, '/');
}

function be_cc_event_identity_host(mixed $raw, ?array $contract = null): string
{
    $url = be_cc_event_identity_url($raw, $contract);
    if ($url === '') return '';
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
}

function be_cc_event_identity_day(string $raw): ?DateTimeImmutable
{
    if ($raw === '') return null;
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$day || (is_array($errors) && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))) return null;
    return $day->format('Y-m-d') === $raw ? $day : null;
}

function be_cc_event_identity_ranges_overlap(array $a, array $b): bool
{
    $aStart = be_cc_event_identity_day((string)($a['date'] ?? ''));
    $bStart = be_cc_event_identity_day((string)($b['date'] ?? ''));
    if (!$aStart || !$bStart) return false;
    $aEnd = be_cc_event_identity_day((string)($a['end_date'] ?? '')) ?: $aStart;
    $bEnd = be_cc_event_identity_day((string)($b['end_date'] ?? '')) ?: $bStart;
    return $aStart <= $bEnd && $bStart <= $aEnd;
}

function be_cc_event_identity_tokens(string $value, array $contract, bool $title = false): array
{
    $stopwords = $title ? array_fill_keys(array_map('strval', (array)($contract['stopwords'] ?? [])), true) : [];
    $aliases = is_array($contract['token_aliases'] ?? null) ? $contract['token_aliases'] : [];
    $tokens = [];
    foreach (explode(' ', be_cc_event_identity_text($value)) as $token) {
        $token = trim((string)($aliases[$token] ?? $token));
        if ($token === '' || isset($stopwords[$token])) continue;
        if ($title && preg_match('/^20\d{2}$/', $token) === 1) continue;
        $tokens[$token] = true;
    }
    return array_keys($tokens);
}

function be_cc_event_identity_token_similarity(string $a, string $b, array $contract, bool $title = false): array
{
    $aNorm = be_cc_event_identity_text($a);
    $bNorm = be_cc_event_identity_text($b);
    if ($aNorm !== '' && $aNorm === $bNorm) {
        return ['score'=>1.0, 'shared'=>max(1, count(be_cc_event_identity_tokens($a, $contract, $title)))];
    }
    $aTokens = be_cc_event_identity_tokens($a, $contract, $title);
    $bTokens = be_cc_event_identity_tokens($b, $contract, $title);
    if (!$aTokens || !$bTokens) return ['score'=>0.0, 'shared'=>0];
    $shared = count(array_intersect($aTokens, $bTokens));
    if ($shared < 1) return ['score'=>0.0, 'shared'=>0];
    $dice = (2.0 * $shared) / (count($aTokens) + count($bTokens));
    $containment = $shared / min(count($aTokens), count($bTokens));
    $score = max($dice, $containment);
    if ($title && $shared < (int)($contract['min_shared_title_tokens'] ?? 2)) {
        $score = min($score, (float)($contract['single_token_title_cap'] ?? 0.58));
    }
    return ['score'=>$score, 'shared'=>$shared];
}

function be_cc_event_identity_score(array $candidate, array $existing, array $contract): array
{
    $title = be_cc_event_identity_token_similarity((string)$candidate['title'], (string)$existing['title'], $contract, true);
    $location = be_cc_event_identity_token_similarity((string)$candidate['location'], (string)$existing['location'], $contract, false);
    $candidateCity = be_cc_event_identity_text((string)$candidate['city']);
    $existingCity = be_cc_event_identity_text((string)$existing['city']);
    $cityScore = $candidateCity !== '' && $candidateCity === $existingCity ? 1.0 : 0.0;
    $candidateHost = be_cc_event_identity_host((string)$candidate['url'], $contract);
    $existingHost = be_cc_event_identity_host((string)$existing['url'], $contract);
    $hostScore = $candidateHost !== '' && $candidateHost === $existingHost ? 1.0 : 0.0;
    $weights = (array)$contract['weights'];
    $score = (float)$weights['title'] * (float)$title['score']
        + (float)$weights['location'] * (float)$location['score']
        + (float)$weights['city'] * $cityScore
        + (float)$weights['source_host'] * $hostScore;
    return [
        'score'=>$score,
        'title_score'=>(float)$title['score'],
        'shared_title_tokens'=>(int)$title['shared'],
        'location_score'=>(float)$location['score'],
        'city_score'=>$cityScore,
        'source_host_score'=>$hostScore,
    ];
}

function be_cc_event_identity_empty_result(): array
{
    return [
        'status'=>'none','matched_event_id'=>'','matched_event_title'=>'','matched_event_date'=>'',
        'matched_event_location'=>'','matched_event_url'=>'','score'=>0.0,'match_type'=>'',
        'confidence'=>'','reason'=>'',
    ];
}

function be_cc_event_identity_compare(array $candidateItem, array $existingItem, ?array $contract = null): array
{
    $cfg = $contract ?? be_cc_event_identity_contract();
    $candidate = be_cc_event_identity_fields($candidateItem);
    $existing = be_cc_event_identity_fields($existingItem);
    $sameId = $candidate['id'] !== '' && $existing['id'] !== '' && be_cc_event_identity_text($candidate['id']) === be_cc_event_identity_text($existing['id']);
    $dateOverlap = be_cc_event_identity_ranges_overlap($candidate, $existing);
    if (!$sameId && !$dateOverlap) return be_cc_event_identity_empty_result();

    $details = be_cc_event_identity_score($candidate, $existing, $cfg);
    $candidateUrl = be_cc_event_identity_url($candidate['url'], $cfg);
    $existingUrl = be_cc_event_identity_url($existing['url'], $cfg);
    $sameUrl = $candidateUrl !== '' && $candidateUrl === $existingUrl;
    $sameTitle = be_cc_event_identity_text($candidate['title']) !== '' && be_cc_event_identity_text($candidate['title']) === be_cc_event_identity_text($existing['title']);
    $sameLocation = be_cc_event_identity_text($candidate['location']) !== '' && be_cc_event_identity_text($candidate['location']) === be_cc_event_identity_text($existing['location']);

    $status = 'none';
    $matchType = '';
    $reason = '';
    $confidence = '';
    $score = (float)$details['score'];
    if ($sameId) {
        if ($dateOverlap && ($sameUrl || ((float)$details['title_score'] >= (float)$cfg['review_threshold'] && $score >= (float)$cfg['review_threshold']))) {
            $status = 'same_identity';
            $matchType = 'same_event_id';
            $reason = 'Die stabile Event-ID ist bereits vorhanden und die fachlichen Merkmale passen zum selben Event.';
            $confidence = 'high';
        } else {
            $status = 'identity_conflict';
            $matchType = 'event_id_conflict';
            $reason = 'Die stabile Event-ID ist bereits belegt, aber Titel, Termin oder Quelle passen nicht sicher zusammen.';
            $confidence = 'high';
        }
    } elseif ($sameTitle && ($sameLocation || $sameUrl)) {
        $status = 'exact';
        $matchType = 'same_title_and_date';
        $reason = 'Titel und Termin stimmen mit einem vorhandenen Event überein.';
        $confidence = 'high';
        $score = max($score, 0.99);
    } elseif ($sameUrl && (int)$details['shared_title_tokens'] >= 1) {
        $status = 'possible';
        $matchType = 'same_source_url_and_date';
        $reason = 'Dieselbe kanonische Quelle und derselbe Termin sind bereits vorhanden; die abweichende Bezeichnung muss fachlich geprüft werden.';
        $confidence = 'high';
        $score = max($score, (float)$cfg['review_threshold']);
    } elseif ($score >= (float)$cfg['review_threshold'] && (int)$details['shared_title_tokens'] >= (int)($cfg['min_shared_title_tokens'] ?? 2)) {
        $status = 'possible';
        $matchType = 'semantic_title_date_context';
        $reason = 'Termin und prägende Titelbegriffe stimmen stark überein; Ort, Stadt oder Quellkontext stützen den Verdacht.';
        $confidence = $score >= 0.86 ? 'high' : 'medium';
    }

    if ($status === 'none') return be_cc_event_identity_empty_result();
    return [
        'status'=>$status,
        'matched_event_id'=>$existing['id'],
        'matched_event_title'=>$existing['title'],
        'matched_event_date'=>$existing['date'],
        'matched_event_location'=>$existing['location'],
        'matched_event_url'=>$existingUrl,
        'score'=>round(min(1.0, max(0.0, $score)), 3),
        'match_type'=>$matchType,
        'confidence'=>$confidence,
        'reason'=>$reason,
        'details'=>$details,
    ];
}

function be_cc_event_identity_find_best(array $candidate, array $events, ?array $contract = null): array
{
    $cfg = $contract ?? be_cc_event_identity_contract();
    $ranks = ['none'=>0,'possible'=>1,'same_identity'=>2,'exact'=>3,'identity_conflict'=>4];
    $best = be_cc_event_identity_empty_result();
    foreach ($events as $existing) {
        if (!is_array($existing)) continue;
        $result = be_cc_event_identity_compare($candidate, $existing, $cfg);
        $current = [$ranks[$result['status']] ?? 0, (float)($result['score'] ?? 0.0), (string)($result['matched_event_id'] ?? '')];
        $previous = [$ranks[$best['status']] ?? 0, (float)($best['score'] ?? 0.0), (string)($best['matched_event_id'] ?? '')];
        if ($current > $previous) $best = $result;
    }
    return $best;
}

function be_cc_event_identity_enrich(array $candidate, array $events, bool $allowSameIdentity = true): array
{
    $match = be_cc_event_identity_find_best($candidate, $events);
    $status = trim((string)($match['status'] ?? 'none'));
    if ($status === 'none' || ($status === 'same_identity' && $allowSameIdentity)) {
        foreach ([
            'matched_event_id','match_score','duplicate_score','duplicate_confidence','duplicate_reason',
            'duplicate_match_type','matched_event_title','matched_event_date','matched_event_location','matched_event_url',
        ] as $field) $candidate[$field] = '';
        if (be_cc_event_identity_text($candidate['duplicate_status'] ?? '') === 'review') $candidate['duplicate_status'] = '';
        $candidate['hard_duplicate'] = false;
        $candidate['event_identity_status'] = $status;
        return $candidate;
    }

    $matchedId = trim((string)($match['matched_event_id'] ?? ''));
    $oldStatus = be_cc_event_identity_text($candidate['duplicate_status'] ?? '');
    $oldMatch = trim((string)($candidate['matched_event_id'] ?? ''));
    $humanDistinct = $oldStatus === 'distinct' && $oldMatch !== '' && $oldMatch === $matchedId;
    $score = number_format((float)($match['score'] ?? 0.0), 3, '.', '');
    $candidate = array_replace($candidate, [
        'matched_event_id'=>$matchedId,
        'match_score'=>$score,
        'duplicate_score'=>$score,
        'duplicate_confidence'=>(string)($match['confidence'] ?? ''),
        'duplicate_reason'=>(string)($match['reason'] ?? ''),
        'duplicate_match_type'=>(string)($match['match_type'] ?? ''),
        'matched_event_title'=>(string)($match['matched_event_title'] ?? ''),
        'matched_event_date'=>(string)($match['matched_event_date'] ?? ''),
        'matched_event_location'=>(string)($match['matched_event_location'] ?? ''),
        'matched_event_url'=>(string)($match['matched_event_url'] ?? ''),
        'event_identity_status'=>$status,
        'hard_duplicate'=>!$humanDistinct,
    ]);
    if (!$humanDistinct) $candidate['duplicate_status'] = 'review';
    return $candidate;
}

function be_cc_event_identity_table_rows(string $tab): array
{
    $response = be_google_sheets_values_get($tab . '!A:AZ');
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    $headerOffset = -1;
    $index = [];
    foreach ($values as $offset=>$row) {
        if (!is_array($row)) continue;
        $normalized = array_map(static fn($value): string => be_cc_event_identity_text($value), $row);
        if (!in_array('title', $normalized, true) || !in_array('date', $normalized, true)) continue;
        if (!in_array('id', $normalized, true) && !in_array('event id', $normalized, true) && !in_array('event_id', $normalized, true)) continue;
        $headerOffset = (int)$offset;
        foreach ($normalized as $position=>$name) if ($name !== '') $index[$name] = (int)$position;
        break;
    }
    if ($headerOffset < 0) throw new RuntimeException('Der Event-Header wurde nicht gefunden: ' . $tab);
    $rows = [];
    for ($i=$headerOffset+1;$i<count($values);$i++) {
        $raw = is_array($values[$i] ?? null) ? $values[$i] : [];
        if (!array_filter($raw, static fn($value): bool => trim((string)$value) !== '')) continue;
        $row = [];
        foreach ($index as $name=>$position) $row[$name] = trim((string)($raw[$position] ?? ''));
        if (isset($row['event id']) && !isset($row['event_id'])) $row['event_id'] = $row['event id'];
        $fields = be_cc_event_identity_fields($row);
        if ($fields['id'] === '') throw new RuntimeException('Der Eventbestand enthält eine Zeile ohne stabile ID: ' . $tab . '.');
        if ($fields['title'] === '' || $fields['date'] === '') throw new RuntimeException('Der Eventbestand enthält eine Zeile ohne Titel oder Datum: ' . $tab . '.');
        $rows[] = $row;
    }
    return $rows;
}

function be_cc_event_identity_merge_event_rows(array $baseRows, array $overlayRows): array
{
    $byKey = [];
    $order = [];
    $overlayKeys = [];
    $add = static function(array $row, bool $overlay) use (&$byKey, &$order, &$overlayKeys): void {
        $fields = be_cc_event_identity_fields($row);
        $id = be_cc_event_identity_text($fields['id']);
        if ($id === '') throw new RuntimeException('Der Eventbestand enthält eine Zeile ohne stabile ID.');
        $key = 'id:' . $id;
        if (!isset($byKey[$key])) {
            $order[] = $key;
        } elseif (!$overlay || isset($overlayKeys[$key])) {
            throw new RuntimeException('Der Eventbestand enthält eine doppelte stabile ID.');
        }
        if ($overlay) $overlayKeys[$key] = true;
        $byKey[$key] = $row;
    };
    foreach ($baseRows as $row) if (is_array($row)) $add($row, false);
    foreach ($overlayRows as $row) if (is_array($row)) $add($row, true);
    return array_map(static fn(string $key): array => $byKey[$key], $order);
}

function be_cc_event_identity_current_events(): array
{
    static $cache = [];
    $environment = strtolower(trim(be_app_env_value()));
    if (isset($cache[$environment])) return $cache[$environment];
    $base = be_cc_event_identity_table_rows('Events');
    if (in_array($environment, ['staging','development','dev','local','test'], true)) {
        $overlay = be_cc_event_identity_table_rows('Events_Staging');
        return $cache[$environment] = be_cc_event_identity_merge_event_rows($base, $overlay);
    }
    if (in_array($environment, ['live','production','prod'], true)) return $cache[$environment] = $base;
    throw new RuntimeException('Der Eventbestand kann für diese Laufzeitumgebung nicht sicher bestimmt werden.');
}

function be_cc_event_identity_enrich_current(array $candidate, bool $allowSameIdentity = true): array
{
    return be_cc_event_identity_enrich($candidate, be_cc_event_identity_current_events(), $allowSameIdentity);
}
