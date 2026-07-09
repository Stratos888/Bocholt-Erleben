<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-cases.php | Zweck: liefert konkrete Aufgaben-Fälle aus Inbox und Content-Ops-Findings fuer das interne Dashboard; Umfang: read-only, review-passwortgeschuetzt === */
require __DIR__ . '/_bootstrap.php';

function co_case_text(mixed $value, int $max = 700): string {
    $text = trim(preg_replace('/\s+/', ' ', (string)($value ?? '')) ?? '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}
function co_case_key(mixed $value): string {
    $key = strtolower(trim((string)($value ?? '')));
    $key = preg_replace('/[^a-z0-9_\-.]+/', '_', $key) ?? '';
    return trim($key, '_-.');
}
function co_case_json(?string $raw): array {
    if ($raw === null || trim($raw) === '') { return []; }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function co_case_final_status(string $status): bool {
    $s = co_case_key($status);
    if ($s === '') { return false; }
    foreach (['done','final','approved','rejected','archived','imported','published','closed','abgeschlossen','erledigt','uebernommen','abgelehnt'] as $needle) {
        if (str_contains($s, co_case_key($needle))) { return true; }
    }
    return false;
}
function co_case_primary_url(array $item): string {
    $url = trim((string)($item['source_url'] ?? ''));
    if ($url === '') { $url = trim((string)($item['url'] ?? '')); }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}
function co_case_bucket_from_inbox(array $item): string {
    $status = co_case_key($item['status'] ?? '');
    $text = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    if (co_case_final_status($status)) { return 'done'; }
    if (str_contains($status, 'later') || str_contains($status, 'defer') || str_contains($status, 'zurueck') || str_contains($status, 'wait')) { return 'later'; }
    if (str_contains($text, 'visual') || str_contains($text, 'bild') || str_contains($text, 'motif') || str_contains($text, 'motiv')) { return 'visual'; }
    if (str_contains($text, 'source') || str_contains($text, 'quelle') || str_contains($text, 'evidence')) { return 'source'; }
    if (str_contains($text, 'ki') || str_contains($text, 'ai') || str_contains($text, 'candidate') || str_contains($text, 'kandidat')) { return 'ki'; }
    return 'inbox';
}
function co_case_bucket_from_finding(array $finding): string {
    $details = is_array($finding['details'] ?? null) ? $finding['details'] : [];
    $decision = co_case_key($details['decision_class'] ?? '');
    $type = co_case_key($finding['finding_type'] ?? '');
    $safe = co_case_key($finding['safe_action'] ?? '');
    if ($decision === 'done') { return 'done'; }
    if ($decision === 'needs_visual_fix' || str_contains($type, 'visual') || str_contains($safe, 'visual')) { return 'visual'; }
    if ($decision === 'needs_source' || str_contains($type, 'source') || str_contains($safe, 'source')) { return 'source'; }
    if ($decision === 'watch') { return 'watch'; }
    if ($decision === 'needs_patch') { return 'content'; }
    return 'content';
}
function co_case_why(string $bucket, string $origin): string {
    if ($origin === 'inbox') {
        return match ($bucket) {
            'source' => 'Inbox-Fall mit Quellenbezug.',
            'visual' => 'Inbox-Fall mit Bild-/Motivbezug.',
            'ki' => 'Inbox-Fall aus KI-/Kandidatenprozess.',
            'later' => 'Zurückgestellter Inbox-Fall.',
            'done' => 'Bereits abgeschlossener Inbox-Fall.',
            default => 'Offene Inbox-Entscheidung.',
        };
    }
    return match ($bucket) {
        'source' => 'Content-Prüfung braucht eine belastbare Quelle.',
        'visual' => 'Content-Prüfung meldet Bild-/Motivproblem.',
        'watch' => 'Content-Prüfung empfiehlt Beobachten.',
        'done' => 'Unkritischer oder bereits erledigter Prüffall.',
        default => 'Content-Prüfung meldet fachlichen Korrekturbedarf.',
    };
}
function co_case_add_count(array &$counts, string $bucket, string $origin, bool $open): void {
    $counts['total']++;
    $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    if ($origin === 'inbox') { $counts['inbox']++; }
    if ($origin === 'content_audit') { $counts['content_audit']++; }
    if ($open) { $counts['open_total']++; }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']); }
be_require_review_access();
$env = co_case_key($_GET['env'] ?? 'staging') ?: 'staging';

try {
    $items = [];
    $counts = ['total'=>0,'open_total'=>0,'inbox'=>0,'content_audit'=>0,'content'=>0,'source'=>0,'visual'=>0,'ki'=>0,'watch'=>0,'later'=>0,'done'=>0];

    $inboxPath = dirname(__DIR__) . '/data/inbox.json';
    $inboxRows = co_case_json(is_file($inboxPath) ? file_get_contents($inboxPath) : '[]');
    foreach ($inboxRows as $row) {
        if (!is_array($row)) { continue; }
        $bucket = co_case_bucket_from_inbox($row);
        $open = $bucket !== 'done';
        co_case_add_count($counts, $bucket, 'inbox', $open);
        $meta = array_filter([$row['date'] ?? '', $row['time'] ?? '', $row['location'] ?? ($row['city'] ?? ''), $row['kategorie_suggestion'] ?? '']);
        $items[] = [
            'id' => 'inbox-' . (int)($row['row_number'] ?? 0),
            'origin' => 'inbox',
            'bucket' => $bucket,
            'is_open' => $open,
            'title' => co_case_text($row['title'] ?? 'Ohne Titel', 180),
            'meta' => co_case_text(implode(' · ', $meta), 220),
            'description' => co_case_text($row['description'] ?? '', 360),
            'notes' => co_case_text($row['notes'] ?? '', 360),
            'source_url' => co_case_primary_url($row),
            'row_number' => (int)($row['row_number'] ?? 0),
            'status' => co_case_text($row['status'] ?? '', 80),
            'why' => co_case_why($bucket, 'inbox'),
            'next_action' => $open ? 'Einzelfall prüfen und anschließend entscheiden.' : 'Keine Aktion nötig.',
            'visual' => co_case_text(trim(($row['visual_key'] ?? '') . ' ' . ($row['visual_asset_status'] ?? '')), 180),
        ];
    }

    $pdo = be_db();
    $stmt = $pdo->prepare("SELECT generated_at_utc, github_run_url, findings_json FROM content_ops_run WHERE environment = ? AND source_mode = 'content_quality_audit' ORDER BY generated_at_utc DESC, id DESC LIMIT 1");
    $stmt->execute([$env]);
    $run = $stmt->fetch() ?: null;
    $findings = $run ? co_case_json((string)($run['findings_json'] ?? '')) : [];

    $limit = 220;
    foreach ($findings as $idx => $finding) {
        if (!is_array($finding)) { continue; }
        $bucket = co_case_bucket_from_finding($finding);
        $details = is_array($finding['details'] ?? null) ? $finding['details'] : [];
        $decision = co_case_key($details['decision_class'] ?? '');
        $open = $bucket !== 'done' && ($decision !== 'done');
        if (!$open && $bucket !== 'done') { $open = true; }
        co_case_add_count($counts, $bucket, 'content_audit', $open);
        if (count($items) >= $limit) { continue; }
        $meta = array_filter([$finding['entity_type'] ?? '', $details['date'] ?? '', $details['process_category'] ?? '', $details['correction_owner'] ?? '']);
        $sourceUrl = trim((string)($details['source_url'] ?? ''));
        if ($sourceUrl === '') { $sourceUrl = trim((string)($details['suggested_url'] ?? '')); }
        $items[] = [
            'id' => 'audit-' . ($finding['entity_id'] ?? $idx),
            'origin' => 'content_audit',
            'bucket' => $bucket,
            'is_open' => $open,
            'title' => co_case_text($finding['title'] ?? 'Content-Prüffall', 180),
            'meta' => co_case_text(implode(' · ', $meta), 220),
            'description' => co_case_text($details['recommended_action'] ?? ($finding['finding_type'] ?? ''), 360),
            'notes' => co_case_text($details['decision_note'] ?? '', 360),
            'source_url' => filter_var($sourceUrl, FILTER_VALIDATE_URL) ? $sourceUrl : '',
            'row_number' => 0,
            'status' => co_case_text($decision ?: ($finding['severity'] ?? ''), 80),
            'why' => co_case_why($bucket, 'content_audit'),
            'next_action' => match ($bucket) {
                'source' => 'Quelle prüfen oder zur KI-Recherche geben.',
                'visual' => 'Bild-/Motivfall prüfen und Visual-Prozess nachschärfen.',
                'watch' => 'Im Monatsreview beobachten.',
                default => 'Fachlich prüfen und bei Bedarf korrigieren.',
            },
            'visual' => co_case_text(trim(($details['suggested_visual_key'] ?? '') . ' ' . ($details['visual_asset_status'] ?? '')), 180),
            'github_run_url' => $run['github_run_url'] ?? '',
        ];
    }

    be_json_response(200, [
        'status' => 'ok',
        'environment' => $env,
        'generated_at_utc' => gmdate('c'),
        'counts' => $counts,
        'items' => $items,
        'content_audit_generated_at_utc' => $run['generated_at_utc'] ?? '',
        'note' => 'Read-only Fälle-Feed aus Inbox und Content-Ops-Findings. Schreibaktionen werden separat angebunden.',
    ]);
} catch (Throwable $e) {
    be_json_response(500, ['status'=>'error','message'=>'Fälle konnten nicht geladen werden.','error_class'=>get_class($e),'error_message'=>$e->getMessage()]);
}
/* === END FILE: api/content-ops-cases.php === */
