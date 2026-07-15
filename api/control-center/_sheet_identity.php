<?php
declare(strict_types=1);

require_once __DIR__ . '/_editorial_contracts.php';

/** Side-effect-free identity resolvers for Sheet Inbox and Content Audit rows. */

function be_cc_identity_text(mixed $value): string
{
    $text = be_cc_unicode_lower(trim((string)$value));
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

function be_cc_canonical_source_url(mixed $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') return '';
    $parts = parse_url($raw);
    if (!is_array($parts) || empty($parts['host'])) return rtrim(be_cc_unicode_lower($raw), '/');
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = strtolower((string)$parts['host']);
    $path = (string)($parts['path'] ?? '/');
    $path = $path === '/' ? '/' : rtrim($path, '/');
    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    foreach (array_keys($query) as $key) {
        $lower = strtolower((string)$key);
        if (str_starts_with($lower, 'utm_') || in_array($lower, ['fbclid','gclid','ref','ref_src'], true)) unset($query[$key]);
    }
    ksort($query);
    $suffix = $query ? '?' . http_build_query($query) : '';
    return $scheme . '://' . $host . $path . $suffix;
}

function be_cc_identity_row_number(array $row, int $fallbackIndex = 0): int
{
    foreach (['_row_number','row_number','row'] as $key) {
        if (isset($row[$key]) && (int)$row[$key] > 0) return (int)$row[$key];
    }
    return $fallbackIndex > 0 ? $fallbackIndex + 1 : 0;
}

function be_cc_inbox_content_fingerprint(array $row): string
{
    $parts = [
        be_cc_identity_text(be_cc_contract_value($row, ['title','eventName'], '')),
        be_cc_contract_text($row, ['date','start_date']),
        be_cc_identity_text(be_cc_contract_value($row, ['location','venue','ort'], '')),
        be_cc_canonical_source_url(be_cc_contract_value($row, ['source_url','url','event_url'], '')),
    ];
    return hash('sha256', implode('|', $parts));
}

function be_cc_inbox_identity(array $row): array
{
    return [
        'stable_id' => be_cc_contract_text($row, ['id_suggestion','id','event_id']),
        'source_url' => be_cc_canonical_source_url(be_cc_contract_value($row, ['source_url','url','event_url'], '')),
        'date' => be_cc_contract_text($row, ['date','start_date']),
        'content_fingerprint' => be_cc_inbox_content_fingerprint($row),
        'row_number' => be_cc_identity_row_number($row),
    ];
}

function be_cc_identity_result(string $status, string $method, array $matches = [], string $message = ''): array
{
    $numbers = array_map(static fn(array $entry): int => (int)$entry['row_number'], $matches);
    return [
        'status'=>$status,
        'match_method'=>$method,
        'row_number'=>count($matches) === 1 ? (int)$matches[0]['row_number'] : null,
        'row'=>count($matches) === 1 ? $matches[0]['row'] : null,
        'candidate_row_numbers'=>$numbers,
        'message'=>$message,
    ];
}

function be_cc_resolve_unique_or_narrow(array $matches, string $method, string $fingerprint): ?array
{
    if (count($matches) === 1) return be_cc_identity_result('resolved', $method, $matches);
    if (count($matches) > 1 && $fingerprint !== '') {
        $narrowed = array_values(array_filter($matches, static fn(array $entry): bool => be_cc_inbox_content_fingerprint($entry['row']) === $fingerprint));
        if (count($narrowed) === 1) return be_cc_identity_result('resolved', $method . '+content_fingerprint', $narrowed);
        if (count($narrowed) > 1) return be_cc_identity_result('ambiguous', $method . '+content_fingerprint', $narrowed, 'Mehrere Sheet-Zeilen entsprechen derselben Identität.');
    }
    if (count($matches) > 1) return be_cc_identity_result('ambiguous', $method, $matches, 'Mehrere Sheet-Zeilen entsprechen derselben Identität.');
    return null;
}

function be_cc_resolve_inbox_row(array $rows, array $storedIdentity): array
{
    $entries = [];
    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) continue;
        $entries[] = ['row'=>$row, 'row_number'=>be_cc_identity_row_number($row, $index + 1)];
    }
    $stableId = trim((string)($storedIdentity['stable_id'] ?? ''));
    $sourceUrl = be_cc_canonical_source_url($storedIdentity['source_url'] ?? '');
    $date = trim((string)($storedIdentity['date'] ?? ''));
    $fingerprint = trim((string)($storedIdentity['content_fingerprint'] ?? ''));

    if ($stableId !== '') {
        $matches = array_values(array_filter($entries, static fn(array $entry): bool => be_cc_contract_text($entry['row'], ['id_suggestion','id','event_id']) === $stableId));
        $result = be_cc_resolve_unique_or_narrow($matches, 'stable_candidate_id', $fingerprint);
        if ($result !== null) return $result;
    }
    if ($sourceUrl !== '' && $date !== '') {
        $matches = array_values(array_filter($entries, static function(array $entry) use ($sourceUrl, $date): bool {
            return be_cc_canonical_source_url(be_cc_contract_value($entry['row'], ['source_url','url','event_url'], '')) === $sourceUrl
                && be_cc_contract_text($entry['row'], ['date','start_date']) === $date;
        }));
        $result = be_cc_resolve_unique_or_narrow($matches, 'canonical_source_url_and_date', $fingerprint);
        if ($result !== null) return $result;
    }
    if ($fingerprint !== '') {
        $matches = array_values(array_filter($entries, static fn(array $entry): bool => be_cc_inbox_content_fingerprint($entry['row']) === $fingerprint));
        if (count($matches) === 1) return be_cc_identity_result('resolved', 'content_fingerprint', $matches);
        if (count($matches) > 1) return be_cc_identity_result('ambiguous', 'content_fingerprint', $matches, 'Content-Fingerprint ist nicht eindeutig.');
    }
    $hint = (int)($storedIdentity['row_number'] ?? 0);
    if ($hint > 0) {
        foreach ($entries as $entry) {
            if ($entry['row_number'] !== $hint) continue;
            $current = be_cc_inbox_identity($entry['row']);
            $valid = ($stableId !== '' && $current['stable_id'] === $stableId)
                || ($sourceUrl !== '' && $date !== '' && $current['source_url'] === $sourceUrl && $current['date'] === $date)
                || ($fingerprint !== '' && $current['content_fingerprint'] === $fingerprint);
            if ($valid) return be_cc_identity_result('resolved', 'validated_row_hint', [$entry]);
        }
    }
    return be_cc_identity_result('not_found', 'none', [], 'Kein eindeutiger aktueller Inbox-Datensatz gefunden.');
}

function be_cc_audit_identity(array $row): array
{
    return [
        'verification_key'=>be_cc_contract_text($row, ['verification_key']),
        'content_id'=>be_cc_contract_text($row, ['content_id','object_id']),
        'issue_code'=>be_cc_contract_text($row, ['issue_code']),
        'source_fingerprint'=>be_cc_contract_text($row, ['source_fingerprint']),
        'content_fingerprint'=>be_cc_contract_text($row, ['content_fingerprint']),
        'row_number'=>be_cc_identity_row_number($row),
    ];
}

function be_cc_audit_entry_matches_full(array $row, array $identity): bool
{
    return be_cc_contract_text($row, ['content_id','object_id']) === (string)($identity['content_id'] ?? '')
        && be_cc_contract_text($row, ['issue_code']) === (string)($identity['issue_code'] ?? '')
        && be_cc_contract_text($row, ['source_fingerprint']) === (string)($identity['source_fingerprint'] ?? '')
        && be_cc_contract_text($row, ['content_fingerprint']) === (string)($identity['content_fingerprint'] ?? '');
}

function be_cc_resolve_audit_row(array $rows, array $storedIdentity): array
{
    $entries = [];
    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) continue;
        $entries[] = ['row'=>$row, 'row_number'=>be_cc_identity_row_number($row, $index + 1)];
    }
    $verificationKey = trim((string)($storedIdentity['verification_key'] ?? ''));
    if ($verificationKey !== '') {
        $matches = array_values(array_filter($entries, static fn(array $entry): bool => be_cc_contract_text($entry['row'], ['verification_key']) === $verificationKey));
        if (count($matches) === 1) return be_cc_identity_result('resolved', 'verification_key', $matches);
        if (count($matches) > 1) return be_cc_identity_result('ambiguous', 'verification_key', $matches, 'verification_key ist nicht eindeutig.');
    }
    if (($storedIdentity['content_id'] ?? '') !== '' && ($storedIdentity['issue_code'] ?? '') !== ''
        && ($storedIdentity['source_fingerprint'] ?? '') !== '' && ($storedIdentity['content_fingerprint'] ?? '') !== '') {
        $matches = array_values(array_filter($entries, static fn(array $entry): bool => be_cc_audit_entry_matches_full($entry['row'], $storedIdentity)));
        if (count($matches) === 1) return be_cc_identity_result('resolved', 'full_audit_fingerprint', $matches);
        if (count($matches) > 1) return be_cc_identity_result('ambiguous', 'full_audit_fingerprint', $matches, 'Vollständige Auditidentität ist nicht eindeutig.');
    }
    $contentId = trim((string)($storedIdentity['content_id'] ?? ''));
    $issueCode = trim((string)($storedIdentity['issue_code'] ?? ''));
    if ($contentId !== '' && $issueCode !== '') {
        $matches = array_values(array_filter($entries, static function(array $entry) use ($contentId, $issueCode): bool {
            return be_cc_contract_text($entry['row'], ['content_id','object_id']) === $contentId
                && be_cc_contract_text($entry['row'], ['issue_code']) === $issueCode;
        }));
        if (count($matches) === 1) return be_cc_identity_result('resolved', 'unique_content_id_and_issue_code', $matches);
        if (count($matches) > 1) return be_cc_identity_result('ambiguous', 'unique_content_id_and_issue_code', $matches, 'Auditfall ist nicht eindeutig.');
    }
    $hint = (int)($storedIdentity['row_number'] ?? 0);
    if ($hint > 0) {
        foreach ($entries as $entry) {
            if ($entry['row_number'] !== $hint) continue;
            $candidate = be_cc_audit_identity($entry['row']);
            if (($verificationKey !== '' && $candidate['verification_key'] === $verificationKey)
                || ($contentId !== '' && $issueCode !== '' && $candidate['content_id'] === $contentId && $candidate['issue_code'] === $issueCode)) {
                return be_cc_identity_result('resolved', 'validated_row_hint', [$entry]);
            }
        }
    }
    return be_cc_identity_result('not_found', 'none', [], 'Aktueller Auditfall wurde nicht eindeutig gefunden.');
}

function be_cc_audit_resolution_state(array $storedIdentity, string $currentEventFingerprint, array $rowResolution): array
{
    $storedFingerprint = trim((string)($storedIdentity['content_fingerprint'] ?? ''));
    if ($storedFingerprint !== '' && $currentEventFingerprint !== '' && !hash_equals($storedFingerprint, $currentEventFingerprint)) {
        return ['state'=>'content_conflict', 'write_allowed'=>false, 'resolution_kind'=>'event_changed'];
    }
    if (($rowResolution['status'] ?? '') === 'resolved') {
        return ['state'=>'current', 'write_allowed'=>true, 'resolution_kind'=>'current_audit_row'];
    }
    if (($rowResolution['status'] ?? '') === 'not_found') {
        return ['state'=>'superseded', 'write_allowed'=>true, 'resolution_kind'=>'superseded_audit_row'];
    }
    return ['state'=>'identity_conflict', 'write_allowed'=>false, 'resolution_kind'=>'ambiguous_audit_row'];
}
