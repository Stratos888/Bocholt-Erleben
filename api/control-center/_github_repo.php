<?php
declare(strict_types=1);

function be_cc_github_repo_config(): array
{
    $token = trim((string)(getenv('BE_GITHUB_REPO_TOKEN') ?: ''));
    return [
        'token' => $token,
        'repository' => trim((string)(getenv('BE_GITHUB_REPOSITORY') ?: 'Stratos888/Bocholt-Erleben')),
        'branch' => trim((string)(getenv('BE_GITHUB_BRANCH') ?: 'staging')),
        'available' => $token !== '',
    ];
}

function be_cc_activity_writeback_available(): bool
{
    return (bool)be_cc_github_repo_config()['available'];
}

function be_cc_github_request(string $method, string $path, ?array $body = null): array
{
    $config = be_cc_github_repo_config();
    if (!$config['available']) throw new RuntimeException('Der sichere Repo-Writeback ist serverseitig noch nicht konfiguriert.');
    if (!preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $config['repository'])) throw new RuntimeException('Ungültige GitHub-Repository-Konfiguration.');
    $url = 'https://api.github.com/repos/' . $config['repository'] . $path;
    $headers = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $config['token'],
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: Bocholt-Erleben-Control-Center',
    ];
    $options = [
        'http' => [
            'method' => $method,
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
    ];
    if ($body !== null) {
        $options['http']['header'] .= "\r\nContent-Type: application/json";
        $options['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    $raw = file_get_contents($url, false, stream_context_create($options));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) $status = (int)$match[1];
    }
    $payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($payload)) {
        $message = is_array($payload) ? trim((string)($payload['message'] ?? 'GitHub request failed.')) : 'GitHub request failed.';
        throw new RuntimeException($message . ($status ? ' (HTTP ' . $status . ')' : ''));
    }
    return $payload;
}

function be_cc_update_activity_in_repo(string $activityId, array $updates): array
{
    $config = be_cc_github_repo_config();
    $path = '/contents/data/offers.json?ref=' . rawurlencode($config['branch']);
    $current = be_cc_github_request('GET', $path);
    $sha = trim((string)($current['sha'] ?? ''));
    $encoded = (string)($current['content'] ?? '');
    $json = base64_decode(str_replace(["\r", "\n"], '', $encoded), true);
    $payload = is_string($json) ? json_decode($json, true) : null;
    if ($sha === '' || !is_array($payload) || !is_array($payload['offers'] ?? null)) throw new RuntimeException('Aktivitätsbestand konnte nicht sicher geladen werden.');

    $allowed = ['title','description','kategorie','location','maps_query','url','duration','mode','price','season','visual_key'];
    $normalized = [];
    foreach ($updates as $field => $value) {
        if (!in_array($field, $allowed, true)) continue;
        $normalized[$field] = trim((string)$value);
    }
    if (!$normalized) throw new InvalidArgumentException('Keine unterstützten Aktivitätsfelder übergeben.');
    if (array_key_exists('title', $normalized) && $normalized['title'] === '') throw new InvalidArgumentException('Titel darf nicht leer sein.');
    if (array_key_exists('url', $normalized) && $normalized['url'] !== '' && (!filter_var($normalized['url'], FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $normalized['url']))) {
        throw new InvalidArgumentException('Ungültige Aktivitäts-URL.');
    }

    $found = false;
    $before = [];
    foreach ($payload['offers'] as &$offer) {
        if (!is_array($offer) || trim((string)($offer['id'] ?? '')) !== $activityId) continue;
        $before = $offer;
        foreach ($normalized as $field => $value) $offer[$field] = $value;
        if (isset($offer['opening_status']) && is_array($offer['opening_status'])) {
            $offer['opening_status']['checked_at'] = gmdate('Y-m-d');
            if (!empty($normalized['url'])) $offer['opening_status']['source_url'] = $normalized['url'];
        }
        $found = true;
        break;
    }
    unset($offer);
    if (!$found) throw new RuntimeException('Aktivität wurde im Repo nicht gefunden.');
    if (isset($payload['meta']) && is_array($payload['meta'])) $payload['meta']['generatedAt'] = gmdate('c');

    $result = be_cc_github_request('PUT', '/contents/data/offers.json', [
        'message' => 'Aktualisiere Aktivität ' . $activityId . ' über Steuerzentrale',
        'content' => base64_encode(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"),
        'sha' => $sha,
        'branch' => $config['branch'],
    ]);
    return [
        'before' => $before,
        'updates' => $normalized,
        'commit_sha' => trim((string)($result['commit']['sha'] ?? '')),
    ];
}
