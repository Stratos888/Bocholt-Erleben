<?php
declare(strict_types=1);
/* === BEGIN FILE: api/stripe/webhook.php | Zweck: verarbeitet Stripe-Webhook-Events fuer Checkout-Abschluss und markiert Submissions serverseitig als bezahlt; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function swh_get_stripe_config(): array
{
    $config = be_get_config();
    $stripe = $config['stripe'] ?? null;

    if (!is_array($stripe)) {
        throw new RuntimeException('Stripe config is missing.');
    }

    $secret = trim((string)($stripe['webhook_secret'] ?? ''));
    if ($secret === '') {
        throw new RuntimeException('Stripe webhook secret is missing.');
    }

    return [
        'webhook_secret' => $secret,
    ];
}

function swh_get_header(string $name): ?string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? null;

    if ($value === null || trim((string)$value) === '') {
        return null;
    }

    return trim((string)$value);
}

function swh_verify_signature(string $payload, string $signatureHeader, string $secret): void
{
    $parts = [];
    foreach (explode(',', $signatureHeader) as $segment) {
        $pair = explode('=', trim($segment), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]][] = $pair[1];
        }
    }

    $timestamp = $parts['t'][0] ?? null;
    $signatures = $parts['v1'] ?? [];

    if ($timestamp === null || $signatures === []) {
        throw new RuntimeException('Invalid Stripe signature header.');
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    $valid = false;
    foreach ($signatures as $candidate) {
        if (hash_equals($expected, $candidate)) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        throw new RuntimeException('Stripe signature verification failed.');
    }

    $age = abs(time() - (int)$timestamp);
    if ($age > 300) {
        throw new RuntimeException('Stripe signature timestamp is too old.');
    }
}

function swh_store_event(PDO $pdo, array $event, string $payload): int
{
    $providerEventId = trim((string)($event['id'] ?? ''));
    $eventType = trim((string)($event['type'] ?? ''));

    if ($providerEventId === '' || $eventType === '') {
        throw new RuntimeException('Stripe event is incomplete.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO webhook_events (
            provider,
            provider_event_id,
            event_type,
            is_processed,
            payload_text
        ) VALUES (
            :provider,
            :provider_event_id,
            :event_type,
            0,
            :payload_text
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            updated_at = CURRENT_TIMESTAMP'
    );

    $insert->execute([
        ':provider' => 'stripe',
        ':provider_event_id' => $providerEventId,
        ':event_type' => $eventType,
        ':payload_text' => $payload,
    ]);

    return (int)$pdo->lastInsertId();
}

function swh_mark_event_processed(PDO $pdo, int $webhookEventId): void
{
    $update = $pdo->prepare(
        'UPDATE webhook_events
         SET is_processed = 1,
             processed_at = CURRENT_TIMESTAMP,
             error_message = NULL
         WHERE id = :id'
    );

    $update->execute([
        ':id' => $webhookEventId,
    ]);
}

function swh_mark_event_failed(PDO $pdo, int $webhookEventId, string $message): void
{
    $update = $pdo->prepare(
        'UPDATE webhook_events
         SET is_processed = 0,
             error_message = :error_message
         WHERE id = :id'
    );

    $update->execute([
        ':id' => $webhookEventId,
        ':error_message' => $message,
    ]);
}

function swh_handle_checkout_completed(PDO $pdo, array $event): void
{
    $object = $event['data']['object'] ?? null;
    if (!is_array($object)) {
        throw new RuntimeException('Stripe checkout object is missing.');
    }

    $sessionId = trim((string)($object['id'] ?? ''));
    $customerId = trim((string)($object['customer'] ?? ''));
    $paymentStatus = trim((string)($object['payment_status'] ?? ''));
    $subscriptionId = trim((string)($object['subscription'] ?? ''));
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
    $submissionId = (int)($metadata['submission_id'] ?? 0);

    if ($sessionId === '' || $submissionId <= 0) {
        throw new RuntimeException('Stripe checkout session metadata is incomplete.');
    }

    $newStatus = $paymentStatus === 'paid' ? 'paid' : 'checkout_started';

    $update = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            stripe_checkout_session_id = :stripe_checkout_session_id,
            stripe_customer_id = CASE WHEN :stripe_customer_id = "" THEN stripe_customer_id ELSE :stripe_customer_id END,
            stripe_subscription_id = CASE WHEN :stripe_subscription_id = "" THEN stripe_subscription_id ELSE :stripe_subscription_id END,
            paid_at = CASE WHEN :paid_flag = 1 THEN CURRENT_TIMESTAMP ELSE paid_at END
         WHERE id = :submission_id'
    );

    $update->execute([
        ':status' => $newStatus,
        ':stripe_checkout_session_id' => $sessionId,
        ':stripe_customer_id' => $customerId,
        ':stripe_subscription_id' => $subscriptionId,
        ':paid_flag' => $paymentStatus === 'paid' ? 1 : 0,
        ':submission_id' => $submissionId,
    ]);
}

$pdo = null;
$webhookEventId = null;

try {
    $payload = file_get_contents('php://input');
    if (!is_string($payload) || trim($payload) === '') {
        throw new InvalidArgumentException('Webhook body is empty.');
    }

    $signatureHeader = swh_get_header('Stripe-Signature');
    if ($signatureHeader === null) {
        throw new InvalidArgumentException('Stripe-Signature header is missing.');
    }

    $stripeConfig = swh_get_stripe_config();
    swh_verify_signature($payload, $signatureHeader, $stripeConfig['webhook_secret']);

    $event = json_decode($payload, true);
    if (!is_array($event)) {
        throw new InvalidArgumentException('Webhook payload is not valid JSON.');
    }

    $pdo = be_db();
    $pdo->beginTransaction();

    $webhookEventId = swh_store_event($pdo, $event, $payload);

    $eventType = trim((string)($event['type'] ?? ''));

    if ($eventType === 'checkout.session.completed') {
        swh_handle_checkout_completed($pdo, $event);
    }

    swh_mark_event_processed($pdo, $webhookEventId);
    $pdo->commit();

    be_json_response(200, [
        'status' => 'ok',
        'received' => true,
        'event_type' => $eventType,
    ]);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($pdo instanceof PDO && is_int($webhookEventId) && $webhookEventId > 0) {
        try {
            swh_mark_event_failed($pdo, $webhookEventId, $error->getMessage());
        } catch (Throwable) {
        }
    }

    be_json_response(400, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/stripe/webhook.php === */
