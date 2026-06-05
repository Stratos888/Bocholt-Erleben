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

/* === BEGIN BLOCK: MAIL_SYSTEM_RENDERER_V1 | Zweck: rendert zentrale HTML-Systemmails nach MAIL_SYSTEM.md mit Plain-Text-Fallback; Umfang: Mail-HTML-Escaping, Komponentendaten und App-Kartenlayout === */
function be_mail_escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function be_mail_public_reference(string $reference): string
{
    $clean = strtolower(trim($reference));

    if ($clean === '') {
        return '';
    }

    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $clean) === 1) {
        return 'BE-' . strtoupper(substr($clean, 0, 8) . '-' . substr($clean, 9, 4));
    }

    return trim($reference);
}

function be_mail_paragraph_html(string $text): string
{
    $paragraphs = preg_split('/\n{2,}/', trim(str_replace(["\r\n", "\r"], "\n", $text))) ?: [];

    $html = '';
    foreach ($paragraphs as $paragraph) {
        $line = nl2br(be_mail_escape_html(trim($paragraph)), false);
        if ($line !== '') {
            $html .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1F2933;">' . $line . '</p>';
        }
    }

    return $html;
}

function be_render_system_mail_html(array $data): string
{
    $title = trim((string)($data['title'] ?? ''));
    $preheader = trim((string)($data['preheader'] ?? $title));
    $greeting = trim((string)($data['greeting'] ?? 'Hallo,'));
    $intro = trim((string)($data['intro'] ?? ''));
    $details = is_array($data['details'] ?? null) ? $data['details'] : [];
    $body = trim((string)($data['body'] ?? ''));
    $noticeTitle = trim((string)($data['notice_title'] ?? ''));
    $noticeText = trim((string)($data['notice_text'] ?? ''));
    $ctaLabel = trim((string)($data['cta_label'] ?? ''));
    $ctaUrl = trim((string)($data['cta_url'] ?? ''));

    $detailsHtml = '';
    if ($details !== []) {
        $rows = '';
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $label = trim((string)($detail['label'] ?? ''));
            $value = trim((string)($detail['value'] ?? ''));

            if ($label === '' && $value === '') {
                continue;
            }

            $rows .= '<div style="margin:0 0 14px;">'
                . '<div style="margin:0 0 4px;font-size:13px;line-height:1.35;font-weight:650;color:#5F6B73;">' . be_mail_escape_html($label) . '</div>'
                . '<div style="font-size:15px;line-height:1.5;color:#1F2933;">' . be_mail_escape_html($value) . '</div>'
                . '</div>';
        }

        if ($rows !== '') {
            $detailsHtml = '<div style="margin:4px 0 22px;padding:16px 18px;background:#F9FBF6;border:1px solid #E3EBDD;border-radius:14px;">'
                . $rows
                . '</div>';
        }
    }

    $ctaHtml = '';
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $ctaHtml = '<div style="margin:6px 0 24px;">'
            . '<a href="' . be_mail_escape_html($ctaUrl) . '" style="display:inline-block;padding:12px 18px;background:#8BCF4A;border-radius:999px;color:#1F2933;font-size:15px;font-weight:650;text-decoration:none;">' . be_mail_escape_html($ctaLabel) . '</a>'
            . '</div>';
    }

    $noticeHtml = '';
    if ($noticeTitle !== '' || $noticeText !== '') {
        $noticeHtml = '<div style="margin:2px 0 24px;padding:16px 18px;background:#EAF6DB;border-radius:14px;">'
            . ($noticeTitle !== '' ? '<div style="margin:0 0 6px;font-size:13px;line-height:1.35;font-weight:650;color:#1F2933;">' . be_mail_escape_html($noticeTitle) . '</div>' : '')
            . ($noticeText !== '' ? '<div style="font-size:14px;line-height:1.55;color:#1F2933;">' . be_mail_escape_html($noticeText) . '</div>' : '')
            . '</div>';
    }

    return '<!doctype html>'
        . '<html lang="de">'
        . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . be_mail_escape_html($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F9FBF6;font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,Roboto,Arial,sans-serif;color:#1F2933;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . be_mail_escape_html($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#F9FBF6;border-collapse:collapse;">'
        . '<tr><td align="center" style="padding:28px 16px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;background:#FFFFFF;border:1px solid #E3EBDD;border-radius:20px;border-collapse:separate;">'
        . '<tr><td style="padding:28px;">'
        . '<div style="margin:0 0 18px;font-size:13px;line-height:1.35;font-weight:650;color:#5F6B73;">Bocholt erleben</div>'
        . '<h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;font-weight:700;color:#1F2933;">' . be_mail_escape_html($title) . '</h1>'
        . '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1F2933;">' . be_mail_escape_html($greeting) . '</p>'
        . be_mail_paragraph_html($intro)
        . $detailsHtml
        . be_mail_paragraph_html($body)
        . $ctaHtml
        . $noticeHtml
        . '<p style="margin:0;font-size:15px;line-height:1.6;color:#1F2933;">Viele Grüße<br>Bocholt erleben</p>'
        . '</td></tr>'
        . '</table>'
        . '<div style="max-width:560px;margin:12px auto 0;font-size:12px;line-height:1.45;color:#8A949B;text-align:center;">Diese Nachricht wurde automatisch von Bocholt erleben versendet.</div>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}
/* === END BLOCK: MAIL_SYSTEM_RENDERER_V1 === */

/* === BEGIN BLOCK: MAIL_SYSTEM_TOPIC_LAYER_V1 | Zweck: zentralisiert Betreff, Textbausteine und HTML-/Plain-Text-Erzeugung fuer wiederverwendbare Mail-Topics; Umfang: Topic-Builder fuer erste Einreichungsbestaetigungen === */
function be_mail_greeting(?string $contactName): string
{
    $safeName = trim((string)$contactName);
    return $safeName !== '' ? 'Hallo ' . $safeName . ',' : 'Hallo,';
}

function be_mail_format_datetime_label(string $value): string
{
    $clean = trim($value);
    if ($clean === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($clean))->format('d.m.Y, H:i') . ' Uhr';
    } catch (Throwable $error) {
        return $clean;
    }
}

function be_render_system_mail_text(array $data): string
{
    $lines = [];

    $greeting = trim((string)($data['greeting'] ?? 'Hallo,'));
    $intro = trim((string)($data['intro'] ?? ''));
    $details = is_array($data['details'] ?? null) ? $data['details'] : [];
    $body = trim((string)($data['body'] ?? ''));
    $noticeTitle = trim((string)($data['notice_title'] ?? ''));
    $noticeText = trim((string)($data['notice_text'] ?? ''));
    $ctaLabel = trim((string)($data['cta_label'] ?? ''));
    $ctaUrl = trim((string)($data['cta_url'] ?? ''));

    $lines[] = $greeting;

    if ($intro !== '') {
        $lines[] = '';
        $lines[] = $intro;
    }

    foreach ($details as $detail) {
        if (!is_array($detail)) {
            continue;
        }

        $label = trim((string)($detail['label'] ?? ''));
        $value = trim((string)($detail['value'] ?? ''));

        if ($label === '' && $value === '') {
            continue;
        }

        $lines[] = '';
        if ($label !== '') {
            $lines[] = $label . ':';
        }
        if ($value !== '') {
            $lines[] = $value;
        }
    }

    if ($body !== '') {
        $lines[] = '';
        $lines[] = $body;
    }

    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $lines[] = '';
        $lines[] = $ctaLabel . ':';
        $lines[] = $ctaUrl;
    }

    if ($noticeTitle !== '' || $noticeText !== '') {
        $lines[] = '';
        if ($noticeTitle !== '') {
            $lines[] = $noticeTitle . ':';
        }
        if ($noticeText !== '') {
            $lines[] = $noticeText;
        }
    }

    $lines[] = '';
    $lines[] = 'Viele Grüße';
    $lines[] = 'Bocholt erleben';

    return implode("\n", $lines);
}

function be_build_system_mail_topic(string $topic, array $context): array
{
    $contactName = trim((string)($context['contact_name'] ?? ''));
    $title = trim((string)($context['title'] ?? ''));
    $reference = be_mail_public_reference((string)($context['reference'] ?? ''));
    $paymentUrl = trim((string)($context['payment_url'] ?? ''));
    $expiresAt = be_mail_format_datetime_label((string)($context['expires_at'] ?? ''));

    $displayTitle = $title !== '' ? $title : 'ohne Titel';
    $greeting = be_mail_greeting($contactName);
    $noticeTitle = 'Hinweis zur Veröffentlichung';
    $ctaLabel = '';
    $ctaUrl = '';
    $extraDetails = [];

    switch ($topic) {
        case 'submission_received_event':
            $subject = 'Dein Einzeltermin wird geprüft';
            $detailLabel = 'Veranstaltung';
            $intro = 'Vielen Dank für deine Einreichung. Wir prüfen den Termin redaktionell und achten darauf, dass die Angaben vollständig und verständlich sind.';
            $body = 'Wenn der Termin zu Bocholt erleben passt, senden wir dir im nächsten Schritt den Zahlungslink für den Einzeltermin.';
            $noticeText = 'Die Zahlung führt nicht automatisch zur Veröffentlichung. Sichtbar wird die Veranstaltung erst nach finaler redaktioneller Freigabe.';
            break;

        case 'submission_received_activity':
            $subject = 'Deine Aktivität wird geprüft';
            $detailLabel = 'Aktivität';
            $intro = 'Vielen Dank für deine Einreichung. Wir prüfen die Aktivität redaktionell und achten darauf, dass die Angaben vollständig und verständlich sind.';
            $body = 'Wenn die Aktivität zu Bocholt erleben passt, senden wir dir im nächsten Schritt den Zahlungslink für die Aktivitätspräsenz.';
            $noticeText = 'Die Zahlung führt nicht automatisch zur Veröffentlichung. Sichtbar wird die Aktivität erst nach finaler redaktioneller Freigabe.';
            break;

        case 'payment_released_event':
            $subject = 'Nächster Schritt: Zahlung für deinen Einzeltermin';
            $detailLabel = 'Veranstaltung';
            $intro = 'Wir haben deine Einreichung geprüft. Der Termin passt zu Bocholt erleben und kann in den nächsten Schritt gehen.';
            $body = 'Über den Zahlungslink kannst du den Einzeltermin jetzt bezahlen.';
            $noticeText = 'Nach der Zahlung bereiten wir den Termin final für die Veröffentlichung vor. Sichtbar wird die Veranstaltung erst nach redaktioneller Freigabe. Du erhältst eine weitere E-Mail, sobald dein Termin bei Bocholt erleben sichtbar ist.';
            $ctaLabel = 'Zahlung starten';
            $ctaUrl = $paymentUrl;
            break;

        case 'payment_released_activity':
            $subject = 'Nächster Schritt: Zahlung für deine Aktivitätspräsenz';
            $detailLabel = 'Aktivität';
            $intro = 'Wir haben deine Einreichung geprüft. Die Aktivität passt zu Bocholt erleben und kann in den nächsten Schritt gehen.';
            $body = 'Über den Zahlungslink kannst du die Aktivitätspräsenz jetzt bezahlen.';
            $noticeText = 'Nach der Zahlung bereiten wir die Aktivität final für die Veröffentlichung vor. Sichtbar wird sie erst nach redaktioneller Freigabe. Du erhältst eine weitere E-Mail, sobald deine Aktivität bei Bocholt erleben sichtbar ist.';
            $ctaLabel = 'Zahlung starten';
            $ctaUrl = $paymentUrl;
            break;

        default:
            throw new InvalidArgumentException('Unknown mail topic: ' . $topic);
    }

    $details = [
        [
            'label' => $detailLabel,
            'value' => $displayTitle,
        ],
    ];

    if ($reference !== '') {
        $details[] = [
            'label' => 'Referenz',
            'value' => $reference,
        ];
    }

    if ($expiresAt !== '') {
        $details[] = [
            'label' => 'Gültig bis',
            'value' => $expiresAt,
        ];
    }

    foreach ($extraDetails as $detail) {
        if (is_array($detail)) {
            $details[] = $detail;
        }
    }

    $mailData = [
        'title' => $subject,
        'preheader' => $intro,
        'greeting' => $greeting,
        'intro' => $intro,
        'details' => $details,
        'body' => $body,
        'cta_label' => $ctaLabel,
        'cta_url' => $ctaUrl,
        'notice_title' => $noticeTitle,
        'notice_text' => $noticeText,
    ];

    return [
        'subject' => $subject,
        'to_name' => $contactName !== '' ? $contactName : null,
        'text_body' => be_render_system_mail_text($mailData),
        'html_body' => be_render_system_mail_html($mailData),
    ];
}
/* === END BLOCK: MAIL_SYSTEM_TOPIC_LAYER_V1 === */

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

function be_send_mail(string $toAddress, string $subject, string $textBody, ?string $toName = null, ?string $htmlBody = null): void
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
        ];

        if ($htmlBody !== null && trim($htmlBody) !== '') {
            $boundary = 'be-mail-' . bin2hex(random_bytes(16));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $messageBody = '--' . $boundary
                . "\r\nContent-Type: text/plain; charset=UTF-8"
                . "\r\nContent-Transfer-Encoding: 8bit"
                . "\r\n\r\n"
                . be_mail_normalize_body($textBody)
                . "\r\n--" . $boundary
                . "\r\nContent-Type: text/html; charset=UTF-8"
                . "\r\nContent-Transfer-Encoding: 8bit"
                . "\r\n\r\n"
                . be_mail_normalize_body($htmlBody)
                . "\r\n--" . $boundary . "--";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $messageBody = be_mail_normalize_body($textBody);
        }

        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . $messageBody
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
