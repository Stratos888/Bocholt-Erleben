<?php
declare(strict_types=1);
/* === BEGIN FILE: api/stripe/webhook.php | Zweck: verarbeitet Stripe-Webhook-Events fuer Checkout-Abschluss, speichert Webhook-Events und materialisiert bezahlte Entitlements; Umfang: komplette Datei === */

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

    $prices = is_array($stripe['prices'] ?? null) ? $stripe['prices'] : [];

    return [
        'webhook_secret' => $secret,
        'prices' => [
            'single' => trim((string)($prices['single'] ?? '')),
            'starter' => trim((string)($prices['starter'] ?? '')),
            'active' => trim((string)($prices['active'] ?? '')),
            'unlimited' => trim((string)($prices['unlimited'] ?? '')),
        ],
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

function swh_get_plan_quota_config(string $planKey): array
{
    return match ($planKey) {
        'single' => [
            'included_publications' => 1,
            'is_unlimited' => 0,
        ],
        'starter' => [
            'included_publications' => 3,
            'is_unlimited' => 0,
        ],
        'active' => [
            'included_publications' => 8,
            'is_unlimited' => 0,
        ],
        'unlimited' => [
            'included_publications' => 0,
            'is_unlimited' => 1,
        ],
        default => throw new RuntimeException('Unsupported plan key for entitlement handling.'),
    };
}

function swh_fetch_submission_for_checkout_handling(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            requested_model_key,
            payment_kind,
            payment_reference_key,
            stripe_checkout_session_id,
            stripe_customer_id,
            stripe_subscription_id
         FROM submissions
         WHERE id = :submission_id
         LIMIT 1'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Submission for checkout handling was not found.');
    }

    return $row;
}

function swh_upsert_single_entitlement(PDO $pdo, array $submission): void
{
    $paymentReferenceKey = trim((string)($submission['payment_reference_key'] ?? ''));
    if ($paymentReferenceKey === '') {
        throw new RuntimeException('Single entitlement source reference is missing.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO publication_entitlements (
            organizer_id,
            source_type,
            source_reference,
            source_submission_id,
            subscription_id,
            plan_key,
            status,
            period_start,
            period_end,
            included_publications,
            consumed_publications,
            is_unlimited
        ) VALUES (
            :organizer_id,
            :source_type,
            :source_reference,
            :source_submission_id,
            NULL,
            :plan_key,
            :status,
            NULL,
            NULL,
            :included_publications,
            0,
            0
        )
        ON DUPLICATE KEY UPDATE
            organizer_id = VALUES(organizer_id),
            source_submission_id = COALESCE(source_submission_id, VALUES(source_submission_id)),
            plan_key = VALUES(plan_key),
            status = VALUES(status),
            updated_at = CURRENT_TIMESTAMP'
    );

    $insert->execute([
        ':organizer_id' => (int)$submission['organizer_id'],
        ':source_type' => 'single_submission',
        ':source_reference' => $paymentReferenceKey,
        ':source_submission_id' => (int)$submission['id'],
        ':plan_key' => 'single',
        ':status' => 'active',
        ':included_publications' => 1,
    ]);
}

function swh_upsert_subscription_record(PDO $pdo, array $submission, string $subscriptionId, string $customerId): int
{
    $planKey = trim((string)($submission['requested_model_key'] ?? ''));
    if ($planKey === '') {
        throw new RuntimeException('Subscription plan key is missing.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO subscriptions (
            organizer_id,
            source_provider,
            stripe_subscription_id,
            stripe_customer_id,
            plan_key,
            status,
            current_period_start,
            current_period_end,
            cancel_at_period_end,
            canceled_at
        ) VALUES (
            :organizer_id,
            :source_provider,
            :stripe_subscription_id,
            :stripe_customer_id,
            :plan_key,
            :status,
            NULL,
            NULL,
            0,
            NULL
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            organizer_id = VALUES(organizer_id),
            stripe_customer_id = VALUES(stripe_customer_id),
            plan_key = VALUES(plan_key),
            status = VALUES(status),
            cancel_at_period_end = 0,
            canceled_at = NULL,
            updated_at = CURRENT_TIMESTAMP'
    );

    $insert->execute([
        ':organizer_id' => (int)$submission['organizer_id'],
        ':source_provider' => 'stripe',
        ':stripe_subscription_id' => $subscriptionId,
        ':stripe_customer_id' => $customerId !== '' ? $customerId : null,
        ':plan_key' => $planKey,
        ':status' => 'active',
    ]);

    return (int)$pdo->lastInsertId();
}

function swh_upsert_subscription_entitlement(PDO $pdo, array $submission, int $subscriptionRowId, string $subscriptionId): void
{
    $planKey = trim((string)($submission['requested_model_key'] ?? ''));
    $quotaConfig = swh_get_plan_quota_config($planKey);

    $insert = $pdo->prepare(
        'INSERT INTO publication_entitlements (
            organizer_id,
            source_type,
            source_reference,
            source_submission_id,
            subscription_id,
            plan_key,
            status,
            period_start,
            period_end,
            included_publications,
            consumed_publications,
            is_unlimited
        ) VALUES (
            :organizer_id,
            :source_type,
            :source_reference,
            :source_submission_id,
            :subscription_id,
            :plan_key,
            :status,
            NULL,
            NULL,
            :included_publications,
            0,
            :is_unlimited
        )
        ON DUPLICATE KEY UPDATE
            organizer_id = VALUES(organizer_id),
            source_submission_id = COALESCE(source_submission_id, VALUES(source_submission_id)),
            subscription_id = VALUES(subscription_id),
            plan_key = VALUES(plan_key),
            status = VALUES(status),
            is_unlimited = VALUES(is_unlimited),
            updated_at = CURRENT_TIMESTAMP'
    );

    $insert->execute([
        ':organizer_id' => (int)$submission['organizer_id'],
        ':source_type' => 'subscription',
        ':source_reference' => $subscriptionId,
        ':source_submission_id' => (int)$submission['id'],
        ':subscription_id' => $subscriptionRowId,
        ':plan_key' => $planKey,
        ':status' => 'active',
        ':included_publications' => (int)$quotaConfig['included_publications'],
        ':is_unlimited' => (int)$quotaConfig['is_unlimited'],
    ]);
}

function swh_materialize_paid_entitlements(PDO $pdo, array $submission, string $customerId, string $subscriptionId): void
{
    $planKey = trim((string)($submission['requested_model_key'] ?? ''));

    if ($planKey === 'single') {
        swh_upsert_single_entitlement($pdo, $submission);
        return;
    }

    if ($subscriptionId === '') {
        throw new RuntimeException('Stripe subscription id is missing for subscription entitlement.');
    }

    $subscriptionRowId = swh_upsert_subscription_record($pdo, $submission, $subscriptionId, $customerId);
    swh_upsert_subscription_entitlement($pdo, $submission, $subscriptionRowId, $subscriptionId);
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

    $submission = swh_fetch_submission_for_checkout_handling($pdo, $submissionId);

    $newStatus = $paymentStatus === 'paid' ? 'paid' : 'checkout_started';
    $paidFlag = $paymentStatus === 'paid' ? 1 : 0;

    $customerIdValue = $customerId !== '' ? $customerId : null;
    $subscriptionIdValue = $subscriptionId !== '' ? $subscriptionId : null;

    $update = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            stripe_checkout_session_id = :stripe_checkout_session_id,
            stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
            stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
            paid_at = CASE WHEN :paid_flag = 1 THEN CURRENT_TIMESTAMP ELSE paid_at END
         WHERE id = :submission_id'
    );

    $update->bindValue(':status', $newStatus, PDO::PARAM_STR);
    $update->bindValue(':stripe_checkout_session_id', $sessionId, PDO::PARAM_STR);

    if ($customerIdValue === null) {
        $update->bindValue(':stripe_customer_id', null, PDO::PARAM_NULL);
    } else {
        $update->bindValue(':stripe_customer_id', $customerIdValue, PDO::PARAM_STR);
    }

    if ($subscriptionIdValue === null) {
        $update->bindValue(':stripe_subscription_id', null, PDO::PARAM_NULL);
    } else {
        $update->bindValue(':stripe_subscription_id', $subscriptionIdValue, PDO::PARAM_STR);
    }

    $update->bindValue(':paid_flag', $paidFlag, PDO::PARAM_INT);
    $update->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
    $update->execute();

    if ($paidFlag === 1) {
        $submission['stripe_checkout_session_id'] = $sessionId;
        if ($customerId !== '') {
            $submission['stripe_customer_id'] = $customerId;
        }
        if ($subscriptionId !== '') {
            $submission['stripe_subscription_id'] = $subscriptionId;
        }

        swh_materialize_paid_entitlements($pdo, $submission, $customerId, $subscriptionId);
    }
}
function swh_plan_key_from_subscription_object(array $subscriptionObject, array $stripeConfig): string
{
    $items = $subscriptionObject['items']['data'] ?? [];
    $priceId = '';

    if (is_array($items) && isset($items[0]) && is_array($items[0])) {
        $priceId = trim((string)($items[0]['price']['id'] ?? ''));
    }

    foreach (($stripeConfig['prices'] ?? []) as $planKey => $configuredPriceId) {
        if ($planKey === 'single') {
            continue;
        }

        if ($priceId !== '' && $priceId === trim((string)$configuredPriceId)) {
            return (string)$planKey;
        }
    }

    return '';
}

function swh_timestamp_to_mysql_datetime(mixed $value): ?string
{
    if (!is_numeric($value)) {
        return null;
    }

    $timestamp = (int)$value;
    if ($timestamp <= 0) {
        return null;
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}

function swh_plan_key_from_price_id(string $priceId, array $stripeConfig): string
{
    $normalizedPriceId = trim($priceId);
    if ($normalizedPriceId === '') {
        return '';
    }

    foreach (($stripeConfig['prices'] ?? []) as $planKey => $configuredPriceId) {
        if ($planKey === 'single') {
            continue;
        }

        if ($normalizedPriceId === trim((string)$configuredPriceId)) {
            return (string)$planKey;
        }
    }

    return '';
}

function swh_extract_subscription_period_bounds(array $subscriptionObject): array
{
    $currentPeriodStart = $subscriptionObject['current_period_start'] ?? null;
    $currentPeriodEnd = $subscriptionObject['current_period_end'] ?? null;

    $missingStart = !is_numeric($currentPeriodStart) || (int)$currentPeriodStart <= 0;
    $missingEnd = !is_numeric($currentPeriodEnd) || (int)$currentPeriodEnd <= 0;

    if ($missingStart || $missingEnd) {
        $firstItem = $subscriptionObject['items']['data'][0] ?? null;

        if (is_array($firstItem)) {
            if ($missingStart) {
                $currentPeriodStart = $firstItem['current_period_start'] ?? null;
            }

            if ($missingEnd) {
                $currentPeriodEnd = $firstItem['current_period_end'] ?? null;
            }
        }
    }

    return [
        'current_period_start' => swh_timestamp_to_mysql_datetime($currentPeriodStart),
        'current_period_end' => swh_timestamp_to_mysql_datetime($currentPeriodEnd),
    ];
}

function swh_sync_subscription_schedule_from_stripe_event(PDO $pdo, array $event, array $stripeConfig): void
{
    $object = $event['data']['object'] ?? null;
    if (!is_array($object)) {
        return;
    }

    $stripeSubscriptionId = trim((string)($object['subscription'] ?? ''));
    if ($stripeSubscriptionId === '') {
        return;
    }

    $find = $pdo->prepare(
        'SELECT
            id
         FROM subscriptions
         WHERE stripe_subscription_id = :stripe_subscription_id
         LIMIT 1'
    );

    $find->execute([
        ':stripe_subscription_id' => $stripeSubscriptionId,
    ]);

    $subscriptionRow = $find->fetch(PDO::FETCH_ASSOC);
    if (!is_array($subscriptionRow)) {
        return;
    }

    $subscriptionRowId = (int)$subscriptionRow['id'];
    $phases = is_array($object['phases'] ?? null) ? $object['phases'] : [];
    $nowTs = time();

    $pendingPlanKey = null;
    $pendingEffectiveAt = null;

    foreach ($phases as $phase) {
        if (!is_array($phase)) {
            continue;
        }

        $phaseStart = (int)($phase['start_date'] ?? 0);
        if ($phaseStart <= $nowTs) {
            continue;
        }

        $phaseItems = is_array($phase['items'] ?? null) ? $phase['items'] : [];
        $firstItem = $phaseItems[0] ?? null;
        if (!is_array($firstItem)) {
            continue;
        }

        $priceId = trim((string)($firstItem['price'] ?? ''));
        if ($priceId === '') {
            $priceId = trim((string)($firstItem['plan'] ?? ''));
        }

        $resolvedPlanKey = swh_plan_key_from_price_id($priceId, $stripeConfig);
        if ($resolvedPlanKey === '') {
            continue;
        }

        $pendingPlanKey = $resolvedPlanKey;
        $pendingEffectiveAt = swh_timestamp_to_mysql_datetime($phaseStart);
        break;
    }

    $update = $pdo->prepare(
        'UPDATE subscriptions
         SET
            pending_plan_key = :pending_plan_key,
            pending_change_effective_at = :pending_change_effective_at,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $update->bindValue(':pending_plan_key', $pendingPlanKey, $pendingPlanKey === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $update->bindValue(':pending_change_effective_at', $pendingEffectiveAt, $pendingEffectiveAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $update->bindValue(':id', $subscriptionRowId, PDO::PARAM_INT);
    $update->execute();
}

function swh_sync_subscription_from_stripe_event(PDO $pdo, array $event, array $stripeConfig): void
{
    /* === BEGIN FUNCTION: swh_sync_subscription_from_stripe_event | Zweck: synchronisiert Stripe-Subscription-Status inkl. geplanter Kuendigung ueber cancel_at; Umfang: komplette Funktion === */
    $object = $event['data']['object'] ?? null;
    if (!is_array($object)) {
        throw new RuntimeException('Stripe subscription object is missing.');
    }

    $stripeSubscriptionId = trim((string)($object['id'] ?? ''));
    if ($stripeSubscriptionId === '') {
        throw new RuntimeException('Stripe subscription id is missing.');
    }

    $status = trim((string)($object['status'] ?? ''));
    if ($status === '') {
        $status = 'unknown';
    }

    $planKey = swh_plan_key_from_subscription_object($object, $stripeConfig);
    $customerId = trim((string)($object['customer'] ?? ''));

    $periodBounds = swh_extract_subscription_period_bounds($object);
    $currentPeriodStart = $periodBounds['current_period_start'];
    $currentPeriodEnd = $periodBounds['current_period_end'];

    $cancelAtRaw = $object['cancel_at'] ?? null;
    $cancelAtTimestamp = is_numeric($cancelAtRaw) ? (int)$cancelAtRaw : 0;
    $hasFutureCancelAt = $cancelAtTimestamp > time();
    $isStillServiceActive = in_array($status, ['active', 'trialing'], true);

    $isScheduledCancellation = !empty($object['cancel_at_period_end'])
        || ($isStillServiceActive && $hasFutureCancelAt);

    $cancelAtPeriodEnd = $isScheduledCancellation ? 1 : 0;
    $canceledAt = swh_timestamp_to_mysql_datetime($object['canceled_at'] ?? null);

    $find = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            plan_key
         FROM subscriptions
         WHERE stripe_subscription_id = :stripe_subscription_id
         LIMIT 1'
    );

    $find->execute([
        ':stripe_subscription_id' => $stripeSubscriptionId,
    ]);

    $subscriptionRow = $find->fetch(PDO::FETCH_ASSOC);
    if (!is_array($subscriptionRow)) {
        return;
    }

    $subscriptionRowId = (int)$subscriptionRow['id'];
    $effectivePlanKey = $planKey !== '' ? $planKey : (string)$subscriptionRow['plan_key'];
    $quotaConfig = swh_get_plan_quota_config($effectivePlanKey);
    $entitlementStatus = in_array($status, ['active', 'trialing'], true) ? 'active' : 'paused';

    $updateSubscription = $pdo->prepare(
        'UPDATE subscriptions
         SET
            stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
            plan_key = :plan_key,
            status = :status,
            current_period_start = :current_period_start,
            current_period_end = :current_period_end,
            cancel_at_period_end = :cancel_at_period_end,
            canceled_at = :canceled_at,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    if ($customerId === '') {
        $updateSubscription->bindValue(':stripe_customer_id', null, PDO::PARAM_NULL);
    } else {
        $updateSubscription->bindValue(':stripe_customer_id', $customerId, PDO::PARAM_STR);
    }

    $updateSubscription->bindValue(':plan_key', $effectivePlanKey, PDO::PARAM_STR);
    $updateSubscription->bindValue(':status', $status, PDO::PARAM_STR);
    $updateSubscription->bindValue(':current_period_start', $currentPeriodStart, $currentPeriodStart === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $updateSubscription->bindValue(':current_period_end', $currentPeriodEnd, $currentPeriodEnd === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $updateSubscription->bindValue(':cancel_at_period_end', $cancelAtPeriodEnd, PDO::PARAM_INT);
    $updateSubscription->bindValue(':canceled_at', $canceledAt, $canceledAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $updateSubscription->bindValue(':id', $subscriptionRowId, PDO::PARAM_INT);
    $updateSubscription->execute();

    if ($isScheduledCancellation) {
        $clearPending = $pdo->prepare(
            'UPDATE subscriptions
             SET
                pending_plan_key = NULL,
                pending_change_effective_at = NULL,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $clearPending->execute([
            ':id' => $subscriptionRowId,
        ]);
    }

    $updateEntitlement = $pdo->prepare(
        'UPDATE publication_entitlements
         SET
            plan_key = :plan_key,
            status = :status,
            period_start = :period_start,
            period_end = :period_end,
            included_publications = :included_publications,
            is_unlimited = :is_unlimited,
            updated_at = CURRENT_TIMESTAMP
         WHERE subscription_id = :subscription_id
           AND source_type = "subscription"'
    );

    $updateEntitlement->bindValue(':plan_key', $effectivePlanKey, PDO::PARAM_STR);
    $updateEntitlement->bindValue(':status', $entitlementStatus, PDO::PARAM_STR);
    $updateEntitlement->bindValue(':period_start', $currentPeriodStart, $currentPeriodStart === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $updateEntitlement->bindValue(':period_end', $currentPeriodEnd, $currentPeriodEnd === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $updateEntitlement->bindValue(':included_publications', (int)$quotaConfig['included_publications'], PDO::PARAM_INT);
    $updateEntitlement->bindValue(':is_unlimited', (int)$quotaConfig['is_unlimited'], PDO::PARAM_INT);
    $updateEntitlement->bindValue(':subscription_id', $subscriptionRowId, PDO::PARAM_INT);
    $updateEntitlement->execute();
    /* === END FUNCTION: swh_sync_subscription_from_stripe_event === */
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

    if (in_array($eventType, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
        swh_sync_subscription_from_stripe_event($pdo, $event, $stripeConfig);
    }

    if (in_array($eventType, ['subscription_schedule.created', 'subscription_schedule.updated', 'subscription_schedule.released', 'subscription_schedule.canceled', 'subscription_schedule.completed'], true)) {
        swh_sync_subscription_schedule_from_stripe_event($pdo, $event, $stripeConfig);
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
