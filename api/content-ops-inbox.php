<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-inbox.php | Zweck: liefert Inbox-Einzelfälle fuer das interne mobile Dashboard; Umfang: read-only, review-passwortgeschuetzter JSON-Endpunkt auf Basis data/inbox.json === */
require __DIR__ . '/_bootstrap.php';

function inbox_text(mixed $value, int $max = 700): string {
    $text = trim(preg_replace('/\s+/', ' ', (string)($value ?? '')) ?? '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

function inbox_key(mixed $value): string {
    $key = strtolower(trim((string)($value ?? '')));
    $key = preg_replace('/[^a-z0-9_\-.]+/', '_', $key) ?? '';
    return trim($key, '_-.');
}

function inbox_is_final_status(string $status): bool {
    $s = inbox_key($status);
    if ($s === '') { return false; }
    foreach (['done', 'final', 'approved', 'rejected', 'archived', 'imported', 'published', 'closed', 'abgeschlossen', 'erledigt', 'uebernommen', 'übernommen', 'abgelehnt'] as $needle) {
        if (str_contains($s, inbox_key($needle))) { return true; }
    }
    return false;
}

function inbox_bucket(array $item): string {
    $status = inbox_key($item['status'] ?? '');
    $text = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    if (inbox_is_final_status($status)) { return 'done'; }
    if (str_contains($status, 'later') || str_contains($status, 'defer') || str_contains($status, 'zurueck') || str_contains($status, 'zurück') || str_contains($status, 'wait')) { return 'later'; }
    if (str_contains($text, 'visual') || str_contains($text, 'bild') || str_contains($text, 'motif') || str_contains($text, 'motiv')) { return 'visual'; }
    if (str_contains($text, 'source') || str_contains($text, 'quelle') || str_contains($text, 'evidence')) { return 'source'; }
    if (str_contains($text, 'ki') || str_contains($text, 'ai') || str_contains($text, 'candidate') || str_contains($text, 'kandidat')) { return 'ki'; }
    return 'open';
}

function inbox_primary_url(array $item): string {
    $url = trim((string)($item['source_url'] ?? ''));
    if ($url === '') { $url = trim((string)($item['url'] ?? '')); }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']); }
be_require_review_access();

try {
    $path = dirname(__DIR__) . '/data/inbox.json';
    $raw = is_file($path) ? file_get_contents($path) : '[]';
    $decoded = json_decode($raw ?: '[]', true);
    $rows = is_array($decoded) ? $decoded : [];
    $items = [];
    $counts = ['open' => 0, 'source' => 0, 'visual' => 0, 'ki' => 0, 'later' => 0, 'done' => 0];

    foreach ($rows as $row) {
        if (!is_array($row)) { continue; }
        $bucket = inbox_bucket($row);
        if (!isset($counts[$bucket])) { $counts[$bucket] = 0; }
        $counts[$bucket]++;
        $isFinal = $bucket === 'done';
        $items[] = [
            'row_number' => (int)($row['row_number'] ?? 0),
            'status' => inbox_text($row['status'] ?? '', 80),
            'bucket' => $bucket,
            'is_open' => !$isFinal,
            'title' => inbox_text($row['title'] ?? 'Ohne Titel', 180),
            'date' => inbox_text($row['date'] ?? '', 32),
            'endDate' => inbox_text($row['endDate'] ?? '', 32),
            'time' => inbox_text($row['time'] ?? '', 32),
            'city' => inbox_text($row['city'] ?? '', 80),
            'location' => inbox_text($row['location'] ?? '', 140),
            'category' => inbox_text($row['kategorie_suggestion'] ?? '', 100),
            'description' => inbox_text($row['description'] ?? '', 360),
            'source_name' => inbox_text($row['source_name'] ?? '', 140),
            'source_url' => inbox_primary_url($row),
            'notes' => inbox_text($row['notes'] ?? '', 360),
            'visual_key' => inbox_text($row['visual_key'] ?? '', 100),
            'visual_motif' => inbox_text($row['visual_motif'] ?? '', 160),
            'visual_asset_status' => inbox_text($row['visual_asset_status'] ?? '', 80),
            'created_at' => inbox_text($row['created_at'] ?? '', 40),
            'why' => match ($bucket) {
                'source' => 'Quelle oder Nachweis prüfen.',
                'visual' => 'Bild/Motiv prüfen.',
                'ki' => 'KI-Fund fachlich entscheiden.',
                'later' => 'Zurückgestellter Fall.',
                'done' => 'Bereits abgeschlossen.',
                default => 'Offene Inbox-Entscheidung.',
            },
            'next_action' => $isFinal ? 'Keine Aktion nötig.' : 'Im Dashboard prüfen und dann in der bestehenden Inbox-Logik entscheiden.',
        ];
    }

    $openTotal = 0;
    foreach ($counts as $bucket => $count) { if ($bucket !== 'done') { $openTotal += (int)$count; } }

    be_json_response(200, [
        'status' => 'ok',
        'generated_at_utc' => gmdate('c'),
        'source' => 'data/inbox.json',
        'counts' => array_merge(['total' => count($items), 'open_total' => $openTotal], $counts),
        'items' => $items,
        'note' => 'Diese API ist read-only. Die Inbox ist im Dashboard sichtbar; Writeback-Aktionen bleiben an die bestehende Inbox-Entscheidungslogik gekoppelt.',
    ]);
} catch (Throwable $e) {
    be_json_response(500, ['status' => 'error', 'message' => 'Inbox konnte nicht geladen werden.', 'error_class' => get_class($e), 'error_message' => $e->getMessage()]);
}
/* === END FILE: api/content-ops-inbox.php === */
