<?php
declare(strict_types=1);
/* === BEGIN FILE: api/organizer-portal/request-magic-link.php | Zweck: legt fuer einen vorhandenen Veranstalter einen Magic-Link fuer den Portalzugang an; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function opml_read_json_body(): array
{
    $raw = file_get_contents('php://input');

    if (!is_string($raw) || trim($raw) === '') {
        throw new InvalidArgumentException('Request body is empty.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be valid JSON.');
    }

    return $decoded;
}

function opml_nullable_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function opml_normalize_email(string $email): string
{
    $normalized = mb_strtolower(trim($email));

    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('E-Mail-Adresse ist ungültig.');
    }

    return $normalized;
}

function opml_base_url(): string
{
    $config = be_get_config();
    $app = $config['app'] ?? null;

    if (!is_array($app)) {
        throw new RuntimeException('App config is missing.');
    }

    $baseUrl = trim((string)($app['base_url'] ?? ''));
    if ($baseUrl === '') {
        throw new RuntimeException('App base URL is missing.');
    }

    return rtrim($baseUrl, '/');
}

function opml_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function opml_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function opml_build_magic_link_url(string $baseUrl, string $token): string
{
return $baseUrl . '/fuer-veranstalter/login/?token=' . rawurlencode($token);
}

try {
    $input = opml_read_json_body();
    $email = opml_normalize_email((string)($input['email'] ?? ''));

    $pdo = be_db();

    $findOrganizer = $pdo->prepare(
        'SELECT
            id,
            organization_name,
            email,
            email_normalized
         FROM organizers
         WHERE email_normalized = :email_normalized
         LIMIT 1'
    );

    $findOrganizer->execute([
        ':email_normalized' => $email,
    ]);

    $organizer = $findOrganizer->fetch();

    if (!is_array($organizer)) {
        throw new InvalidArgumentException('Für diese E-Mail-Adresse wurde noch kein Veranstalter gefunden.');
    }

    $organizerId = (int)$organizer['id'];
    $token = opml_generate_token();
    $tokenHash = opml_hash_token($token);

    $requestedIp = opml_nullable_string($_SERVER['REMOTE_ADDR'] ?? null);
    $userAgent = opml_nullable_string($_SERVER['HTTP_USER_AGENT'] ?? null);

    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+30 minutes')
        ->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    $revokeExisting = $pdo->prepare(
        'UPDATE organizer_magic_links
         SET revoked_at = CURRENT_TIMESTAMP
         WHERE organizer_id = :organizer_id
           AND consumed_at IS NULL
           AND revoked_at IS NULL
           AND expires_at > CURRENT_TIMESTAMP'
    );

    $revokeExisting->execute([
        ':organizer_id' => $organizerId,
    ]);

    $insertMagicLink = $pdo->prepare(
        'INSERT INTO organizer_magic_links (
            organizer_id,
            token_hash,
            intended_action,
            email_snapshot,
            requested_ip,
            user_agent_text,
            expires_at,
            last_sent_at
        ) VALUES (
            :organizer_id,
            :token_hash,
            :intended_action,
            :email_snapshot,
            :requested_ip,
            :user_agent_text,
            :expires_at,
            CURRENT_TIMESTAMP
        )'
    );

    $insertMagicLink->execute([
        ':organizer_id' => $organizerId,
        ':token_hash' => $tokenHash,
        ':intended_action' => 'portal_login',
        ':email_snapshot' => (string)$organizer['email'],
        ':requested_ip' => $requestedIp,
        ':user_agent_text' => $userAgent,
        ':expires_at' => $expiresAt,
    ]);

    $magicLinkId = (int)$pdo->lastInsertId();

    $pdo->commit();

    $baseUrl = opml_base_url();
    $magicLinkUrl = opml_build_magic_link_url($baseUrl, $token);

    $config = be_get_config();
    $appEnv = (string)($config['app_env'] ?? 'unknown');

    $response = [
        'status' => 'ok',
        'data' => [
            'organizer_id' => $organizerId,
            'magic_link_id' => $magicLinkId,
            'expires_at_utc' => $expiresAt,
        ],
    ];

    if ($appEnv !== 'live') {
        $response['data']['magic_link_url'] = $magicLinkUrl;
        $response['data']['debug_token'] = $token;
    }

    be_json_response(201, $response);
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    be_json_response(500, [
        'status' => 'error',
        'message' => 'Magic link request failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/organizer-portal/request-magic-link.php === */
