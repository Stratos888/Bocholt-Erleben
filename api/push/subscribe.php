<?php
declare(strict_types=1);
/* === BEGIN FILE: api/push/subscribe.php | Zweck: registriert ein internes Browser-Push-Abo fuer neue Inbox-Elemente; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';
require __DIR__ . '/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

try {
    $config = be_push_config();
    if (!be_push_is_configured($config)) {
        be_json_response(503, [
            'status' => 'error',
            'message' => 'Push is not configured.',
        ]);
    }

    $input = be_push_json_input();
    $subscription = is_array($input['subscription'] ?? null) ? $input['subscription'] : $input;

    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));

    if ($endpoint === '' || !str_starts_with($endpoint, 'https://')) {
        throw new InvalidArgumentException('Push endpoint is invalid.');
    }

    $endpointHash = hash('sha256', $endpoint);
    $userAgent = substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 512);

    $pdo = be_db();
    $statement = $pdo->prepare(
        'INSERT INTO push_subscriptions (
            endpoint_hash,
            endpoint_url,
            p256dh_key,
            auth_key,
            user_agent_text,
            is_active,
            last_seen_at
        ) VALUES (
            :endpoint_hash,
            :endpoint_url,
            :p256dh_key,
            :auth_key,
            :user_agent_text,
            1,
            CURRENT_TIMESTAMP
        )
        ON DUPLICATE KEY UPDATE
            endpoint_url = VALUES(endpoint_url),
            p256dh_key = VALUES(p256dh_key),
            auth_key = VALUES(auth_key),
            user_agent_text = VALUES(user_agent_text),
            is_active = 1,
            last_seen_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP'
    );

    $statement->execute([
        ':endpoint_hash' => $endpointHash,
        ':endpoint_url' => $endpoint,
        ':p256dh_key' => $p256dh !== '' ? $p256dh : null,
        ':auth_key' => $auth !== '' ? $auth : null,
        ':user_agent_text' => $userAgent !== '' ? $userAgent : null,
    ]);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'registered' => true,
        ],
    ]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Push subscription failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/push/subscribe.php === */
