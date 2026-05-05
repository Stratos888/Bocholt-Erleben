<?php
declare(strict_types=1);
/* === BEGIN FILE: api/organizer-portal/create-billing-portal-session.php | Zweck: erstellt fuer eingeloggte Veranstalter eine Stripe-Billing-Portal-Session zum Abo-Aendern oder Kuendigen; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function obp_get_cookie_name(): string
{
    return 'be_organizer_portal_session';
}

function obp_read_session_token_from_cookie(): string
{
    $token = trim((string)($_COOKIE[obp_get_cookie_name()] ?? ''));
    if ($token === '') {
        throw new InvalidArgumentException('Organizer session cookie is missing.');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Organizer session cookie format is invalid.');
    }

    return $token;
}

function obp_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function obp_fetch_portal_session(PDO $pdo, string $sessionTokenHash): array
{
    $statement = $pdo->prepare(
        'SELECT
            s.id AS portal_session_id,
            s.organizer_id,
            s.expires_at,
            s.revoked_at,
            o.organization_name,
            o.email,
            o.stripe_customer_id,
            o.default_plan_key
         FROM organizer_portal_sessions s
         INNER JOIN organizers o ON o.id = s.organizer_id
         WHERE s.session_token_hash = :session_token_hash
         LIMIT 1'
    );

    $statement->execute([
        ':session_token_hash' => $sessionTokenHash,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new InvalidArgumentException('Organizer session was not found.');
    }

    if (!empty($row['revoked_at'])) {
        throw new InvalidArgumentException('Organizer session is revoked.');
    }

    $expiresAt = strtotime((string)$row['expires_at']);
    if ($expiresAt !== false && $expiresAt < time()) {
        throw new InvalidArgumentException('Organizer session is expired.');
    }

    $touch = $pdo->prepare(
        'UPDATE organizer_portal_sessions
         SET last_seen_at = UTC_TIMESTAMP()
         WHERE id = :id'
    );

    $touch->execute([
        ':id' => (string)$row['portal_session_id'],
    ]);

    return $row;
}

function obp_get_stripe_config(): array
{
    $config = be_get_config();
    $stripe = isset($config['stripe']) && is_array($config['stripe']) ? $config['stripe'] : [];

    $secretKey = trim((string)($stripe['secret_key'] ?? ''));
    if ($secretKey === '') {
        throw new RuntimeException('Stripe secret key is missing.');
    }

    return [
        'secret_key' => $secretKey,
    ];
}

function obp_get_app_base_url(): string
{
    $config = be_get_config();
    $app = isset($config['app']) && is_array($config['app']) ? $config['app'] : [];
    $baseUrl = rtrim(trim((string)($app['base_url'] ?? '')), '/');

    if ($baseUrl === '') {
        throw new RuntimeException('App base_url is missing.');
    }

    return $baseUrl;
}

function obp_fetch_manageable_customer(PDO $pdo, int $organizerId): array
{
    $statement = $pdo->prepare(
        'SELECT
            o.id AS organizer_id,
            o.stripe_customer_id AS organizer_stripe_customer_id,
            s.id AS subscription_row_id,
            s.status AS subscription_status,
            s.plan_key AS subscription_plan_key,
            s.stripe_subscription_id,
            s.stripe_customer_id AS subscription_stripe_customer_id
         FROM organizers o
         LEFT JOIN subscriptions s
           ON s.organizer_id = o.id
          AND s.status IN ("active", "trialing", "past_due")
         WHERE o.id = :organizer_id
         ORDER BY
            CASE
                WHEN s.status = "active" THEN 0
                WHEN s.status = "trialing" THEN 1
                WHEN s.status = "past_due" THEN 2
                ELSE 3
            END ASC,
            s.id DESC
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new InvalidArgumentException('Organizer was not found.');
    }

    $customerId = trim((string)($row['subscription_stripe_customer_id'] ?? ''));
    if ($customerId === '') {
        $customerId = trim((string)($row['organizer_stripe_customer_id'] ?? ''));
    }

    if ($customerId === '') {
        throw new InvalidArgumentException('Für diese Mitgliedschaft ist aktuell keine Verwaltung verfügbar.');
    }

    $row['effective_stripe_customer_id'] = $customerId;

    return $row;
}

function obp_stripe_api_request(string $secretKey, string $path, array $payload): array
{
    $endpoint = 'https://api.stripe.com/v1/' . ltrim($path, '/');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]),
            'content' => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
            'timeout' => 30,
        ],
    ]);

    $responseBody = @file_get_contents($endpoint, false, $context);
    $headers = $http_response_header ?? [];

    if (!is_string($responseBody)) {
        throw new RuntimeException('Stripe API request failed without response body.');
    }

    $statusCode = 0;
    if (!empty($headers[0]) && preg_match('~HTTP/\S+\s+(\d{3})~', (string)$headers[0], $matches) === 1) {
        $statusCode = (int)$matches[1];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe API returned invalid JSON.');
    }

    if ($statusCode >= 400) {
        $message = trim((string)($decoded['error']['message'] ?? 'Stripe API error.'));
        throw new RuntimeException($message);
    }

    return $decoded;
}

try {
    $pdo = be_db();

    $sessionToken = obp_read_session_token_from_cookie();
    $sessionRow = obp_fetch_portal_session($pdo, obp_hash_token($sessionToken));
    $billingData = obp_fetch_manageable_customer($pdo, (int)$sessionRow['organizer_id']);

    $stripe = obp_get_stripe_config();
    $baseUrl = obp_get_app_base_url();

    $portalSession = obp_stripe_api_request(
        $stripe['secret_key'],
        'billing_portal/sessions',
        [
            'customer' => (string)$billingData['effective_stripe_customer_id'],
            'return_url' => $baseUrl . '/fuer-veranstalter/dashboard/',
        ]
    );

    $redirectUrl = trim((string)($portalSession['url'] ?? ''));
    if ($redirectUrl === '') {
        throw new RuntimeException('Stripe billing portal session is incomplete.');
    }

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'redirect_url' => $redirectUrl,
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
        'message' => 'Billing portal session creation failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/organizer-portal/create-billing-portal-session.php === */
