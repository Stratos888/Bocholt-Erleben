<?php
declare(strict_types=1);
/* === BEGIN FILE: api/mail-topic-test.php | Zweck: geschützter interner Testversand zentraler Mail-Topics ohne DB-, Stripe- oder Inbox-Statusänderung; Umfang: kompletter Endpoint === */

require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

function bmt_read_json_body(): array
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

function bmt_string(array $input, string $key, string $fallback = ''): string
{
    return trim((string)($input[$key] ?? $fallback));
}

try {
    $input = bmt_read_json_body();

    $topic = bmt_string($input, 'topic');
    $to = bmt_string($input, 'to');

    $allowedTopics = [
        'submission_received_event',
        'submission_received_activity',
        'payment_released_event',
        'payment_released_activity',
        'publication_approved_event',
        'publication_approved_activity',
    ];

    if (!in_array($topic, $allowedTopics, true)) {
        throw new InvalidArgumentException('Unknown or disallowed mail topic.');
    }

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Valid recipient address is required.');
    }

    $mail = be_build_system_mail_topic($topic, [
        'title' => bmt_string($input, 'title', 'Mail-Topic-Test'),
        'reference' => bmt_string($input, 'reference', '2e179920-e035-42cf-9642-2d32ef67b683'),
        'contact_name' => bmt_string($input, 'contact_name', 'Mathias Stöckert'),
        'payment_url' => bmt_string($input, 'payment_url', 'https://www.bocholt-erleben.de/zahlung-starten/?token=test'),
        'expires_at' => bmt_string($input, 'expires_at', '+14 days'),
    ]);

    be_send_mail(
        $to,
        $mail['subject'],
        $mail['text_body'],
        $mail['to_name'],
        $mail['html_body']
    );

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'sent' => true,
            'topic' => $topic,
            'to' => $to,
            'subject' => $mail['subject'],
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
        'message' => 'Mail topic test failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/mail-topic-test.php === */
