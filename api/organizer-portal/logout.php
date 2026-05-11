<?php
declare(strict_types=1);
/* === BEGIN FILE: api/organizer-portal/logout.php | Zweck: beendet eine Veranstalter-Portal-Session serverseitig und loescht das Session-Cookie; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function opl_get_cookie_name(): string
{
    return 'be_organizer_portal_session';
}

function opl_clear_session_cookie(): void
{
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    setcookie(opl_get_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_COOKIE[opl_get_cookie_name()]);
}

function opl_revoke_session_if_present(PDO $pdo, string $plainSessionToken): void
{
    $plainSessionToken = trim($plainSessionToken);

    if ($plainSessionToken === '') {
        return;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $plainSessionToken)) {
        return;
    }

    $sessionTokenHash = hash('sha256', $plainSessionToken);

    $statement = $pdo->prepare(
        'UPDATE organizer_portal_sessions
         SET
            revoked_at = COALESCE(revoked_at, CURRENT_TIMESTAMP),
            updated_at = CURRENT_TIMESTAMP
         WHERE session_token_hash = :session_token_hash'
    );

    $statement->execute([
        ':session_token_hash' => $sessionTokenHash,
    ]);
}

try {
    $plainSessionToken = trim((string)($_COOKIE[opl_get_cookie_name()] ?? ''));

    if ($plainSessionToken !== '') {
        $pdo = be_db();
        opl_revoke_session_if_present($pdo, $plainSessionToken);
    }

    opl_clear_session_cookie();

    be_json_response(200, [
        'status' => 'ok',
        'message' => 'Organizer portal session ended.',
    ]);
} catch (Throwable $error) {
    opl_clear_session_cookie();

    be_json_response(500, [
        'status' => 'error',
        'message' => 'Logout failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/organizer-portal/logout.php === */
