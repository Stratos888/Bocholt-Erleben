<?php
declare(strict_types=1);
/* === BEGIN FILE: api/organizer-portal/consume-magic-link.php | Zweck: prueft einen Magic-Link-Token, verbraucht ihn einmalig, erzeugt eine Portal-Session und setzt ein sicheres Session-Cookie; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function opcl_read_json_body(): array
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

function opcl_required_token(array $input): string
{
    $token = trim((string)($input['token'] ?? ''));
    if ($token === '') {
        throw new InvalidArgumentException('Token is required.');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Token format is invalid.');
    }

    return $token;
}

function opcl_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function opcl_generate_session_token(): string
{
    return bin2hex(random_bytes(32));
}

function opcl_get_cookie_name(): string
{
    return 'be_organizer_portal_session';
}

function opcl_set_session_cookie(string $plainSessionToken, DateTimeImmutable $expiresAt): void
{
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    setcookie(opcl_get_cookie_name(), $plainSessionToken, [
        'expires' => $expiresAt->getTimestamp(),
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function opcl_fetch_magic_link(PDO $pdo, string $tokenHash): array
{
    $statement = $pdo->prepare(
        'SELECT
            ml.id,
            ml.organizer_id,
            ml.token_hash,
            ml.intended_action,
            ml.email_snapshot,
            ml.expires_at,
            ml.consumed_at,
            ml.revoked_at,
            o.organization_name,
            o.contact_name,
            o.email,
            o.email_normalized,
            o.default_plan_key
         FROM organizer_magic_links ml
         INNER JOIN organizers o ON o.id = ml.organizer_id
         WHERE ml.token_hash = :token_hash
         LIMIT 1'
    );

    $statement->execute([
        ':token_hash' => $tokenHash,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new InvalidArgumentException('Magic link is invalid.');
    }

    return $row;
}

function opcl_assert_magic_link_usable(array $magicLink): void
{
    if ((string)($magicLink['intended_action'] ?? '') !== 'portal_login') {
        throw new InvalidArgumentException('Magic link action is invalid.');
    }

    if (!empty($magicLink['revoked_at'])) {
        throw new InvalidArgumentException('Magic link was revoked.');
    }

    if (!empty($magicLink['consumed_at'])) {
        throw new InvalidArgumentException('Magic link was already used.');
    }

    $expiresAt = trim((string)($magicLink['expires_at'] ?? ''));
    if ($expiresAt === '') {
        throw new RuntimeException('Magic link expiry is missing.');
    }

    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiryUtc = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));

    if ($expiryUtc < $nowUtc) {
        throw new InvalidArgumentException('Magic link expired.');
    }
}

try {
    $input = opcl_read_json_body();
    $plainToken = opcl_required_token($input);
    $tokenHash = opcl_hash_token($plainToken);

    $pdo = be_db();
    $pdo->beginTransaction();

    $magicLink = opcl_fetch_magic_link($pdo, $tokenHash);
    opcl_assert_magic_link_usable($magicLink);

    $plainSessionToken = opcl_generate_session_token();
    $sessionTokenHash = hash('sha256', $plainSessionToken);

    $sessionExpiresAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $sessionExpiresAt = $sessionExpiresAt->modify('+14 days');
    $sessionExpiresAtSql = $sessionExpiresAt->format('Y-m-d H:i:s');

    $insertSession = $pdo->prepare(
        'INSERT INTO organizer_portal_sessions (
            organizer_id,
            session_token_hash,
            issued_from_magic_link_id,
            expires_at,
            last_seen_at
        ) VALUES (
            :organizer_id,
            :session_token_hash,
            :issued_from_magic_link_id,
            :expires_at,
            CURRENT_TIMESTAMP
        )'
    );

    $insertSession->execute([
        ':organizer_id' => (int)$magicLink['organizer_id'],
        ':session_token_hash' => $sessionTokenHash,
        ':issued_from_magic_link_id' => (int)$magicLink['id'],
        ':expires_at' => $sessionExpiresAtSql,
    ]);

    $portalSessionId = (int)$pdo->lastInsertId();

    $consumeMagicLink = $pdo->prepare(
        'UPDATE organizer_magic_links
         SET consumed_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $consumeMagicLink->execute([
        ':id' => (int)$magicLink['id'],
    ]);

    $pdo->commit();

    opcl_set_session_cookie($plainSessionToken, $sessionExpiresAt);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'organizer_id' => (int)$magicLink['organizer_id'],
            'portal_session_id' => $portalSessionId,
            'session_expires_at_utc' => $sessionExpiresAtSql,
            'organizer' => [
                'organization_name' => (string)$magicLink['organization_name'],
                'contact_name' => $magicLink['contact_name'] !== null ? (string)$magicLink['contact_name'] : null,
                'email' => (string)$magicLink['email'],
                'default_plan_key' => $magicLink['default_plan_key'] !== null ? (string)$magicLink['default_plan_key'] : null,
            ],
            'redirect_path' => '/veranstalter/',
        ],
    ]);
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
        'message' => 'Magic link consume failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/organizer-portal/consume-magic-link.php === */
