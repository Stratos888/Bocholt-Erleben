<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';

function be_cc_secure_cookie(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function be_cc_start_named_session(string $name): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => be_cc_secure_cookie(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function be_cc_destroy_named_session(string $name): void
{
    be_cc_start_named_session($name);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie($name, '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'DELETE') {
    be_cc_destroy_named_session('BE_INTERNAL_SEO_DASHBOARD');
    be_json_response(200, ['status' => 'ok', 'data' => ['authenticated' => false]]);
}

if ($method !== 'POST') {
    header('Allow: POST, DELETE');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $raw = file_get_contents('php://input');
    $input = json_decode(is_string($raw) ? $raw : '', true);
    $input = is_array($input) ? $input : [];
    $submitted = trim((string)($input['password'] ?? be_request_header('X-BE-Review-Password')));
    $expected = be_review_password();
    if ($submitted === '' || !hash_equals($expected, $submitted)) {
        be_json_response(401, ['status' => 'error', 'message' => 'Review access denied.']);
    }

    be_cc_start_named_session('BE_INTERNAL_SEO_DASHBOARD');
    session_regenerate_id(true);
    $_SESSION['be_internal_seo_dashboard_unlocked'] = true;
    $_SESSION['be_shared_review_authenticated_at'] = time();
    session_write_close();

    be_json_response(200, ['status' => 'ok', 'data' => ['authenticated' => true]]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Anmeldung konnte nicht initialisiert werden.', 'error_message' => $error->getMessage()]);
}
