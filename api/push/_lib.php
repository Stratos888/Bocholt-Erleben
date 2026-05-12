<?php
declare(strict_types=1);
/* === BEGIN FILE: api/push/_lib.php | Zweck: zentrale Low-Level-Logik fuer einfache Inbox-Web-Push-Benachrichtigungen; Umfang: komplette Datei === */

function be_push_config(): array
{
    $config = be_get_config();
    $push = $config['push'] ?? [];

    if (!is_array($push)) {
        $push = [];
    }

    return [
        'enabled' => (bool)($push['enabled'] ?? false),
        'public_key' => trim((string)($push['vapid_public_key'] ?? '')),
        'private_key_pem' => trim((string)($push['vapid_private_key_pem'] ?? '')),
        'subject' => trim((string)($push['subject'] ?? 'mailto:info@bocholt-erleben.de')),
        'secret' => trim((string)($push['secret'] ?? '')),
    ];
}

function be_push_is_configured(?array $config = null): bool
{
    $config = is_array($config) ? $config : be_push_config();

    return (bool)($config['enabled'] ?? false)
        && trim((string)($config['public_key'] ?? '')) !== ''
        && trim((string)($config['private_key_pem'] ?? '')) !== '';
}

function be_push_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body is not valid JSON.');
    }

    return $decoded;
}

function be_push_base64url_encode(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

function be_push_der_length(string $der, int &$offset): int
{
    $length = ord($der[$offset]);
    $offset++;

    if ($length < 0x80) {
        return $length;
    }

    $bytes = $length & 0x7f;
    if ($bytes <= 0 || $bytes > 4 || $offset + $bytes > strlen($der)) {
        throw new RuntimeException('Invalid DER signature length.');
    }

    $length = 0;
    for ($i = 0; $i < $bytes; $i++) {
        $length = ($length << 8) | ord($der[$offset]);
        $offset++;
    }

    return $length;
}

function be_push_der_integer(string $der, int &$offset): string
{
    if ($offset >= strlen($der) || ord($der[$offset]) !== 0x02) {
        throw new RuntimeException('Invalid DER signature integer.');
    }
    $offset++;

    $length = be_push_der_length($der, $offset);
    if ($length <= 0 || $offset + $length > strlen($der)) {
        throw new RuntimeException('Invalid DER signature integer length.');
    }

    $value = substr($der, $offset, $length);
    $offset += $length;

    $value = ltrim($value, "\x00");
    if ($value === '') {
        $value = "\x00";
    }

    if (strlen($value) > 32) {
        throw new RuntimeException('Invalid DER signature integer size.');
    }

    return str_pad($value, 32, "\x00", STR_PAD_LEFT);
}

function be_push_der_signature_to_jose(string $derSignature): string
{
    $offset = 0;
    if ($derSignature === '' || ord($derSignature[$offset]) !== 0x30) {
        throw new RuntimeException('Invalid DER signature sequence.');
    }
    $offset++;

    $sequenceLength = be_push_der_length($derSignature, $offset);
    if ($sequenceLength <= 0 || $offset + $sequenceLength > strlen($derSignature)) {
        throw new RuntimeException('Invalid DER signature sequence length.');
    }

    $r = be_push_der_integer($derSignature, $offset);
    $s = be_push_der_integer($derSignature, $offset);

    return $r . $s;
}

function be_push_audience_from_endpoint(string $endpoint): string
{
    $parts = parse_url($endpoint);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $port = isset($parts['port']) ? (int)$parts['port'] : 0;

    if ($scheme !== 'https' || $host === '') {
        throw new InvalidArgumentException('Push endpoint is invalid.');
    }

    $audience = $scheme . '://' . $host;
    if ($port > 0 && !in_array($port, [443], true)) {
        $audience .= ':' . $port;
    }

    return $audience;
}

function be_push_vapid_jwt(string $audience, array $config): string
{
    $header = be_push_base64url_encode(json_encode([
        'typ' => 'JWT',
        'alg' => 'ES256',
    ], JSON_THROW_ON_ERROR));

    $payload = be_push_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 60 * 60,
        'sub' => (string)$config['subject'],
    ], JSON_THROW_ON_ERROR));

    $signingInput = $header . '.' . $payload;
    $privateKey = openssl_pkey_get_private((string)$config['private_key_pem']);
    if ($privateKey === false) {
        throw new RuntimeException('VAPID private key is invalid.');
    }

    $signature = '';
    $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok || $signature === '') {
        throw new RuntimeException('VAPID signing failed.');
    }

    return $signingInput . '.' . be_push_base64url_encode(be_push_der_signature_to_jose($signature));
}

function be_push_send_empty_push(array $subscription, array $config): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status_code' => 0,
            'error' => 'PHP cURL extension is missing.',
        ];
    }

    $endpoint = trim((string)($subscription['endpoint_url'] ?? ''));
    if ($endpoint === '') {
        return [
            'ok' => false,
            'status_code' => 0,
            'error' => 'Push endpoint is empty.',
        ];
    }

    try {
        $audience = be_push_audience_from_endpoint($endpoint);
        $jwt = be_push_vapid_jwt($audience, $config);
    } catch (Throwable $error) {
        return [
            'ok' => false,
            'status_code' => 0,
            'error' => $error->getMessage(),
        ];
    }

    $headers = [
        'TTL: 300',
        'Urgency: normal',
        'Content-Length: 0',
        'Authorization: vapid t=' . $jwt . ', k=' . (string)$config['public_key'],
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = $curlError === '' && $statusCode >= 200 && $statusCode < 300;

    return [
        'ok' => $ok,
        'status_code' => $statusCode,
        'error' => $ok ? '' : ($curlError !== '' ? $curlError : substr((string)$body, 0, 500)),
    ];
}

function be_push_mark_subscription_result(PDO $pdo, int $subscriptionId, bool $ok, int $statusCode, string $error): void
{
    if ($ok) {
        $statement = $pdo->prepare(
            'UPDATE push_subscriptions
             SET last_success_at = CURRENT_TIMESTAMP,
                 last_failure_at = NULL,
                 failure_count = 0,
                 is_active = 1
             WHERE id = :id'
        );
        $statement->execute([':id' => $subscriptionId]);
        return;
    }

    $shouldDeactivate = in_array($statusCode, [404, 410], true);

    $statement = $pdo->prepare(
        'UPDATE push_subscriptions
         SET last_failure_at = CURRENT_TIMESTAMP,
             failure_count = failure_count + 1,
             is_active = CASE WHEN :should_deactivate = 1 THEN 0 ELSE is_active END
         WHERE id = :id'
    );
    $statement->execute([
        ':id' => $subscriptionId,
        ':should_deactivate' => $shouldDeactivate ? 1 : 0,
    ]);
}

function be_push_normalize_source_type(string $sourceType): string
{
    $normalized = strtolower(trim($sourceType));
    $normalized = preg_replace('/[^a-z0-9_.:-]+/', '_', $normalized) ?? '';
    return substr($normalized !== '' ? $normalized : 'unknown', 0, 64);
}

function be_push_normalize_source_key(string $sourceKey): string
{
    $normalized = trim($sourceKey);
    if ($normalized === '') {
        $normalized = 'unknown-' . gmdate('YmdHis');
    }

    return substr($normalized, 0, 191);
}

function be_push_notify_inbox(PDO $pdo, string $sourceType, string $sourceKey, bool $force = false): array
{
    $config = be_push_config();
    if (!be_push_is_configured($config)) {
        return [
            'status' => 'skipped',
            'message' => 'Push is not configured.',
            'attempted_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
        ];
    }

    $sourceType = be_push_normalize_source_type($sourceType);
    $sourceKey = be_push_normalize_source_key($sourceKey);

    if (!$force) {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO review_notification_log (
                channel,
                source_type,
                source_key,
                status
            ) VALUES (
                "web_push",
                :source_type,
                :source_key,
                "pending"
            )'
        );
        $insert->execute([
            ':source_type' => $sourceType,
            ':source_key' => $sourceKey,
        ]);

        if ($insert->rowCount() === 0) {
            return [
                'status' => 'duplicate',
                'message' => 'Notification already recorded for this source.',
                'attempted_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
            ];
        }
    } else {
        $sourceKey = be_push_normalize_source_key($sourceKey . '-' . bin2hex(random_bytes(4)));
        $insert = $pdo->prepare(
            'INSERT INTO review_notification_log (
                channel,
                source_type,
                source_key,
                status
            ) VALUES (
                "web_push",
                :source_type,
                :source_key,
                "pending"
            )'
        );
        $insert->execute([
            ':source_type' => $sourceType,
            ':source_key' => $sourceKey,
        ]);
    }

    $subscriptions = $pdo->query(
        'SELECT id, endpoint_url
         FROM push_subscriptions
         WHERE is_active = 1
         ORDER BY last_seen_at DESC, id DESC
         LIMIT 20'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($subscriptions) || count($subscriptions) === 0) {
        $update = $pdo->prepare(
            'UPDATE review_notification_log
             SET status = "no_subscriptions",
                 attempted_count = 0,
                 success_count = 0,
                 failure_count = 0,
                 sent_at = CURRENT_TIMESTAMP
             WHERE channel = "web_push"
               AND source_type = :source_type
               AND source_key = :source_key'
        );
        $update->execute([
            ':source_type' => $sourceType,
            ':source_key' => $sourceKey,
        ]);

        return [
            'status' => 'no_subscriptions',
            'attempted_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
        ];
    }

    $attempted = 0;
    $success = 0;
    $failure = 0;
    $lastError = '';

    foreach ($subscriptions as $subscription) {
        $attempted++;
        $result = be_push_send_empty_push($subscription, $config);
        $ok = (bool)($result['ok'] ?? false);
        $statusCode = (int)($result['status_code'] ?? 0);
        $error = trim((string)($result['error'] ?? ''));

        be_push_mark_subscription_result($pdo, (int)$subscription['id'], $ok, $statusCode, $error);

        if ($ok) {
            $success++;
        } else {
            $failure++;
            $lastError = $error !== '' ? $error : ('HTTP ' . $statusCode);
        }
    }

    $status = $success > 0 && $failure === 0 ? 'sent' : ($success > 0 ? 'partial' : 'failed');

    $update = $pdo->prepare(
        'UPDATE review_notification_log
         SET status = :status,
             attempted_count = :attempted_count,
             success_count = :success_count,
             failure_count = :failure_count,
             last_error = :last_error,
             sent_at = CURRENT_TIMESTAMP
         WHERE channel = "web_push"
           AND source_type = :source_type
           AND source_key = :source_key'
    );
    $update->execute([
        ':status' => $status,
        ':attempted_count' => $attempted,
        ':success_count' => $success,
        ':failure_count' => $failure,
        ':last_error' => $lastError !== '' ? $lastError : null,
        ':source_type' => $sourceType,
        ':source_key' => $sourceKey,
    ]);

    return [
        'status' => $status,
        'attempted_count' => $attempted,
        'success_count' => $success,
        'failure_count' => $failure,
        'last_error' => $lastError,
    ];
}

function be_push_notify_inbox_best_effort(string $sourceType, string $sourceKey): void
{
    try {
        $pdo = be_db();
        be_push_notify_inbox($pdo, $sourceType, $sourceKey, false);
    } catch (Throwable $error) {
        error_log('Inbox push notification skipped: ' . $error->getMessage());
    }
}

/* === END FILE: api/push/_lib.php === */
