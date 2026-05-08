<?php
declare(strict_types=1);
/* === BEGIN FILE: api/_bootstrap.php | Zweck: zentraler Backend-Bootstrap fuer private Config, JSON-Antworten, PDO-DB-Zugriff und SMTP-Mailversand; Umfang: komplette Datei === */

function be_base_path(string ...$segments): string
{
    $path = __DIR__;

    foreach ($segments as $segment) {
        $path .= DIRECTORY_SEPARATOR . ltrim($segment, DIRECTORY_SEPARATOR);
    }

    return $path;
}

function be_get_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $configPath = be_base_path('_config.php');
    if (!is_file($configPath)) {
        throw new RuntimeException('Private app config is missing.');
    }

    $loaded = require $configPath;
    if (!is_array($loaded)) {
        throw new RuntimeException('Private app config is invalid.');
    }

    $config = $loaded;
    return $config;
}

function be_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = be_get_config();
    $db = $config['db'] ?? null;

    if (!is_array($db)) {
        throw new RuntimeException('Database config is missing.');
    }

    $host = trim((string)($db['host'] ?? ''));
    $port = (int)($db['port'] ?? 3306);
    $name = trim((string)($db['name'] ?? ''));
    $user = trim((string)($db['user'] ?? ''));
    $password = (string)($db['password'] ?? '');
    $charset = trim((string)($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Database credentials are incomplete.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $name,
        $charset
    );

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function be_mail_config(): array
{
    $config = be_get_config();
    $mail = $config['mail'] ?? null;

    if (!is_array($mail)) {
        throw new RuntimeException('Mail config is missing.');
    }

    $fromName = trim((string)($mail['from_name'] ?? ''));
    $fromAddress = trim((string)($mail['from_address'] ?? ''));
    $smtpHost = trim((string)($mail['smtp_host'] ?? ''));
    $smtpPort = (int)($mail['smtp_port'] ?? 0);
    $smtpEncryption = strtolower(trim((string)($mail['smtp_encryption'] ?? '')));
    $smtpUsername = trim((string)($mail['smtp_username'] ?? ''));
    $smtpPassword = (string)($mail['smtp_password'] ?? '');

    if (
        $fromName === '' ||
        $fromAddress === '' ||
        $smtpHost === '' ||
        $smtpPort <= 0 ||
        $smtpEncryption === '' ||
        $smtpUsername === '' ||
        $smtpPassword === ''
    ) {
        throw new RuntimeException('Mail config is incomplete.');
    }

    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Mail from address is invalid.');
    }

    if (!in_array($smtpEncryption, ['ssl', 'tls', 'none'], true)) {
        throw new RuntimeException('Mail encryption is invalid.');
    }

    return [
        'from_name' => $fromName,
        'from_address' => $fromAddress,
        'smtp_host' => $smtpHost,
        'smtp_port' => $smtpPort,
        'smtp_encryption' => $smtpEncryption,
        'smtp_username' => $smtpUsername,
        'smtp_password' => $smtpPassword,
    ];
}

/* === BEGIN BLOCK: BACKEND_JSON_RESPONSE_DIAGNOSTIC_SANITIZER_V1 | Zweck: blendet technische Fehlerdetails außerhalb Staging/Development zentral aus; Umfang: ersetzt be_json_response und ergänzt sichere Diagnose-Env-Helfer === */
function be_app_env_value(): string
{
    try {
        $config = be_get_config();
        $configuredEnv = strtolower(trim((string)($config['app_env'] ?? '')));
        if ($configuredEnv !== '') {
            return $configuredEnv;
        }
    } catch (Throwable $error) {
        // Config kann bei Health-/Bootstrap-Fehlern fehlen. Dann nicht scheitern, sondern sicher fortfahren.
    }

    return strtolower(trim((string)(getenv('APP_ENV') ?: getenv('BE_APP_ENV') ?: '')));
}

function be_should_expose_diagnostics(): bool
{
    return in_array(be_app_env_value(), ['staging', 'development', 'dev', 'local', 'test'], true);
}

function be_sanitize_json_payload_for_public_response(array $payload): array
{
    if (be_should_expose_diagnostics()) {
        return $payload;
    }

    $technicalKeys = [
        'error_class',
        'error_message',
        'error_file',
        'error_line',
        'error_trace',
        'exception',
        'debug',
        'trace',
    ];

    foreach ($technicalKeys as $key) {
        unset($payload[$key]);
    }

    return $payload;
}

function be_json_response(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(be_sanitize_json_payload_for_public_response($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
/* === END BLOCK: BACKEND_JSON_RESPONSE_DIAGNOSTIC_SANITIZER_V1 === */

/* === BEGIN BLOCK: BACKEND_REVIEW_ACCESS_GUARD_V1 | Zweck: schützt Kuratier-DB-Endpunkte mit serverseitig konfiguriertem Review-Passwort; Umfang: ergänzt Helper für review-list/approve/reject === */
function be_review_password(): string
{
    $config = be_get_config();

    $password = trim((string)($config['review']['password'] ?? ''));
    if ($password === '') {
        $password = trim((string)($config['security']['review_password'] ?? ''));
    }
    if ($password === '') {
        $password = trim((string)($config['review_password'] ?? ''));
    }
    if ($password === '') {
        $password = trim((string)getenv('BE_REVIEW_PASSWORD'));
    }

    if ($password === '') {
        throw new RuntimeException('Review password is not configured.');
    }

    return $password;
}

function be_request_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$serverKey] ?? ''));
}

function be_require_review_access(): void
{
    try {
        $expectedPassword = be_review_password();
    } catch (Throwable $error) {
        be_json_response(503, [
            'status' => 'error',
            'message' => 'Review access is not configured.',
        ]);
    }

    $submittedPassword = be_request_header('X-BE-Review-Password');

    if ($submittedPassword === '' || !hash_equals($expectedPassword, $submittedPassword)) {
        be_json_response(401, [
            'status' => 'error',
            'message' => 'Review access denied.',
        ]);
    }
}
/* === END BLOCK: BACKEND_REVIEW_ACCESS_GUARD_V1 === */

function be_mail_encode_header(string $value): string
{
    $clean = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');

    if ($clean === '') {
        return '';
    }

    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($clean, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($clean) . '?=';
}

function be_mail_format_address(string $email, ?string $name = null): string
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Mail address is invalid.');
    }

    $safeName = trim((string)$name);
    if ($safeName === '') {
        return $email;
    }

    return be_mail_encode_header($safeName) . ' <' . $email . '>';
}

function be_mail_normalize_body(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $normalized);

    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

function be_smtp_read_response($socket): array
{
    $lines = [];
    $code = null;

    while (($line = fgets($socket, 515)) !== false) {
        $lines[] = rtrim($line, "\r\n");

        if (preg_match('/^(\d{3})([ -])(.*)$/', $line, $matches) === 1) {
            $code = (int)$matches[1];

            if ($matches[2] === ' ') {
                break;
            }
        }
    }

    if ($code === null) {
        throw new RuntimeException('SMTP server did not return a valid response.');
    }

    return [
        'code' => $code,
        'message' => implode("\n", $lines),
    ];
}

function be_smtp_expect($socket, array $expectedCodes): array
{
    $response = be_smtp_read_response($socket);

    if (!in_array($response['code'], $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . $response['message']);
    }

    return $response;
}

function be_smtp_command($socket, string $command, array $expectedCodes): array
{
    $written = fwrite($socket, $command . "\r\n");
    if ($written === false) {
        throw new RuntimeException('SMTP command could not be written.');
    }

    return be_smtp_expect($socket, $expectedCodes);
}

function be_send_mail(string $toAddress, string $subject, string $textBody, ?string $toName = null): void
{
    if (!filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Recipient e-mail address is invalid.');
    }

    $mail = be_mail_config();

    $transportHost = $mail['smtp_host'];
    $transportPrefix = '';

    if ($mail['smtp_encryption'] === 'ssl') {
        $transportPrefix = 'ssl://';
    }

    $socket = @stream_socket_client(
        $transportPrefix . $transportHost . ':' . $mail['smtp_port'],
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        throw new RuntimeException('SMTP connection failed: ' . $errorMessage . ' (' . $errorNumber . ')');
    }

    stream_set_timeout($socket, 20);

    try {
        be_smtp_expect($socket, [220]);
        be_smtp_command($socket, 'EHLO bocholt-erleben.de', [250]);

        if ($mail['smtp_encryption'] === 'tls') {
            be_smtp_command($socket, 'STARTTLS', [220]);

            $cryptoEnabled = stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoEnabled !== true) {
                throw new RuntimeException('SMTP TLS negotiation failed.');
            }

            be_smtp_command($socket, 'EHLO bocholt-erleben.de', [250]);
        }

        be_smtp_command($socket, 'AUTH LOGIN', [334]);
        be_smtp_command($socket, base64_encode($mail['smtp_username']), [334]);
        be_smtp_command($socket, base64_encode($mail['smtp_password']), [235]);

        be_smtp_command($socket, 'MAIL FROM:<' . $mail['from_address'] . '>', [250]);
        be_smtp_command($socket, 'RCPT TO:<' . $toAddress . '>', [250, 251]);
        be_smtp_command($socket, 'DATA', [354]);

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'From: ' . be_mail_format_address($mail['from_address'], $mail['from_name']),
            'To: ' . be_mail_format_address($toAddress, $toName),
            'Subject: ' . be_mail_encode_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . be_mail_normalize_body($textBody)
            . "\r\n.";

        $written = fwrite($socket, $message . "\r\n");
        if ($written === false) {
            throw new RuntimeException('SMTP message body could not be written.');
        }

        be_smtp_expect($socket, [250]);
        be_smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
/* === END FILE: api/_bootstrap.php === */
