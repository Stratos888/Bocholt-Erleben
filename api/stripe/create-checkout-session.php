<?php
declare(strict_types=1);
/* === BEGIN FILE: api/stripe/create-checkout-session.php | Zweck: erzeugt serverseitig eine Stripe-Checkout-Session fuer eine vorhandene Submission und schreibt Stripe-Referenzen in die DB; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function scs_read_json_body(): array
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

function scs_nullable_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function scs_required_positive_int(array $input, string $key, string $label): int
{
    $value = $input[$key] ?? null;

    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' is required.');
    }

    $intValue = (int)$value;
    if ($intValue <= 0) {
        throw new InvalidArgumentException($label . ' must be a positive integer.');
    }

    return $intValue;
}

function scs_get_app_config(): array
{
    $config = be_get_config();

    $app = $config['app'] ?? null;
    if (!is_array($app)) {
        throw new RuntimeException('App config is missing.');
    }

    $baseUrl = trim((string)($app['base_url'] ?? ''));
    $successUrl = trim((string)($app['success_url'] ?? ''));
    $cancelUrl = trim((string)($app['cancel_url'] ?? ''));

    if ($baseUrl === '' || $successUrl === '' || $cancelUrl === '') {
        throw new RuntimeException('App URLs are incomplete.');
    }

    return [
        'base_url' => $baseUrl,
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
    ];
}

function scs_get_stripe_config(): array
{
    $config = be_get_config();

    $stripe = $config['stripe'] ?? null;
    if (!is_array($stripe)) {
        throw new RuntimeException('Stripe config is missing.');
    }

    $secretKey = trim((string)($stripe['secret_key'] ?? ''));
    $prices = is_array($stripe['prices'] ?? null) ? $stripe['prices'] : [];

    $mappedPrices = [
        'single' => trim((string)($prices['single'] ?? '')),
        'starter' => trim((string)($prices['starter'] ?? '')),
        'active' => trim((string)($prices['active'] ?? '')),
        'unlimited' => trim((string)($prices['unlimited'] ?? '')),
    ];

    if ($secretKey === '') {
        throw new RuntimeException('Stripe secret key is missing.');
    }

    foreach ($mappedPrices as $modelKey => $priceId) {
        if ($priceId === '') {
            throw new RuntimeException('Stripe price is missing for model: ' . $modelKey);
        }
    }

    return [
        'secret_key' => $secretKey,
        'prices' => $mappedPrices,
    ];
}

function scs_fetch_submission(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            s.id,
            s.organizer_id,
            s.status,
            s.requested_model_key,
            s.payment_kind,
            s.payment_reference_key,
            s.email_snapshot,
            s.organization_name_snapshot,
            s.title,
            s.stripe_checkout_session_id,
            s.stripe_customer_id AS submission_stripe_customer_id,
            s.stripe_subscription_id,
            o.organization_name,
            o.contact_name,
            o.email,
            o.stripe_customer_id AS organizer_stripe_customer_id
        FROM submissions s
        INNER JOIN organizers o ON o.id = s.organizer_id
        WHERE s.id = :submission_id
        LIMIT 1'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
    ]);

    $submission = $statement->fetch();
    if (!is_array($submission)) {
        throw new InvalidArgumentException('Submission wurde nicht gefunden.');
    }

    return $submission;
}

function scs_assert_submission_ready(array $submission): void
{
    $status = (string)($submission['status'] ?? '');
    if (!in_array($status, ['draft', 'checkout_started'], true)) {
        throw new InvalidArgumentException('Submission ist für Checkout aktuell nicht freigegeben.');
    }

    $modelKey = (string)($submission['requested_model_key'] ?? '');
    if (!in_array($modelKey, ['single', 'starter', 'active', 'unlimited'], true)) {
        throw new InvalidArgumentException('Submission-Modell ist ungültig.');
    }

    $paymentReferenceKey = trim((string)($submission['payment_reference_key'] ?? ''));
    if ($paymentReferenceKey === '') {
        throw new RuntimeException('Payment reference key is missing.');
    }

    $email = trim((string)($submission['email_snapshot'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Submission-E-Mail ist ungültig.');
    }
}

function scs_build_success_url(string $baseUrl, string $paymentReferenceKey): string
{
    return rtrim($baseUrl, '/') . '/events-veroeffentlichen/erfolg/?submission_ref=' . rawurlencode($paymentReferenceKey) . '&session_id={CHECKOUT_SESSION_ID}';
}

function scs_build_cancel_url(string $baseUrl, string $paymentReferenceKey): string
{
    return rtrim($baseUrl, '/') . '/events-veroeffentlichen/abgebrochen/?submission_ref=' . rawurlencode($paymentReferenceKey);
}

function scs_stripe_api_request(string $secretKey, string $endpointPath, array $formData): array
{
    $url = 'https://api.stripe.com/v1/' . ltrim($endpointPath, '/');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]),
            'content' => http_build_query($formData, '', '&', PHP_QUERY_RFC3986),
            'timeout' => 30,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];

    if (!is_string($body)) {
        throw new RuntimeException('Stripe request failed without response body.');
    }

    $statusCode = 0;
    if (!empty($headers[0]) && preg_match('~HTTP/\S+\s+(\d{3})~', (string)$headers[0], $matches)) {
        $statusCode = (int)$matches[1];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe response is not valid JSON.');
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $stripeMessage = (string)($decoded['error']['message'] ?? 'Stripe request failed.');
        throw new RuntimeException($stripeMessage);
    }

    return $decoded;
}

function scs_ensure_stripe_customer(PDO $pdo, array $submission, string $secretKey): string
{
    $existingCustomerId = trim((string)($submission['submission_stripe_customer_id'] ?? ''));
    if ($existingCustomerId !== '') {
        return $existingCustomerId;
    }

    $existingCustomerId = trim((string)($submission['organizer_stripe_customer_id'] ?? ''));
    if ($existingCustomerId !== '') {
        return $existingCustomerId;
    }

    $customer = scs_stripe_api_request($secretKey, 'customers', [
        'email' => (string)$submission['email_snapshot'],
        'name' => (string)($submission['organization_name_snapshot'] ?: $submission['organization_name'] ?: 'Bocholt erleben Veranstalter'),
        'metadata[organizer_id]' => (string)$submission['organizer_id'],
    ]);

    $customerId = trim((string)($customer['id'] ?? ''));
    if ($customerId === '') {
        throw new RuntimeException('Stripe customer creation failed.');
    }

    $updateOrganizer = $pdo->prepare(
        'UPDATE organizers
         SET stripe_customer_id = :stripe_customer_id
         WHERE id = :organizer_id'
    );

    $updateOrganizer->execute([
        ':stripe_customer_id' => $customerId,
        ':organizer_id' => (int)$submission['organizer_id'],
    ]);

    return $customerId;
}

/* === BEGIN BLOCK: STRIPE_CHECKOUT_FETCH_USABLE_ACTIVE_ENTITLEMENT_V2 | Zweck: liefert fuer einen Organizer ein nutzbares aktives Abo-Kontingent und bevorzugt dabei das passende Modell; Umfang: komplette Funktion === */
function scs_fetch_usable_active_subscription_entitlement(PDO $pdo, array $submission): ?array
{
    $organizerId = (int)($submission['organizer_id'] ?? 0);
    if ($organizerId <= 0) {
        return null;
    }

    $requestedPlanKey = trim((string)($submission['requested_model_key'] ?? ''));

    $statement = $pdo->prepare(
        'SELECT
            pe.id,
            pe.organizer_id,
            pe.subscription_id,
            pe.plan_key,
            pe.included_count,
            pe.consumed_count,
            pe.is_unlimited,
            pe.period_start,
            pe.period_end,
            s.stripe_subscription_id,
            s.stripe_customer_id
         FROM publication_entitlements pe
         INNER JOIN subscriptions s ON s.id = pe.subscription_id
         WHERE pe.organizer_id = :organizer_id
           AND pe.source_type = "subscription"
           AND pe.status = "active"
           AND pe.period_start <= UTC_TIMESTAMP()
           AND pe.period_end >= UTC_TIMESTAMP()
           AND (
                pe.is_unlimited = 1
                OR pe.consumed_count < pe.included_count
           )
         ORDER BY
            CASE
                WHEN :requested_plan_key <> "" AND pe.plan_key = :requested_plan_key THEN 0
                ELSE 1
            END ASC,
            pe.is_unlimited DESC,
            pe.period_end ASC,
            pe.id ASC
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
        ':requested_plan_key' => $requestedPlanKey,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}
/* === END BLOCK: STRIPE_CHECKOUT_FETCH_USABLE_ACTIVE_ENTITLEMENT_V2 === */

function scs_build_existing_subscription_redirect_url(string $baseUrl, string $paymentReferenceKey): string
{
    return rtrim($baseUrl, '/') . '/events-veroeffentlichen/erfolg/?submission_ref=' . rawurlencode($paymentReferenceKey) . '&flow=existing-subscription';
}

/* === BEGIN BLOCK: STRIPE_CHECKOUT_MARK_PAID_FROM_ACTIVE_SUBSCRIPTION_V2 | Zweck: setzt Submission bei vorhandenem aktivem Abo sauber auf bezahlt und schreibt das effektive Modell zurueck; Umfang: komplette Funktion === */
function scs_mark_submission_paid_from_active_subscription(PDO $pdo, array $submission, array $entitlement, ?string $customerId): void
{
    $subscriptionId = isset($entitlement['subscription_id']) ? (int)$entitlement['subscription_id'] : null;
    $effectiveCustomerId = $customerId ?: trim((string)($entitlement['stripe_customer_id'] ?? ''));
    $resolvedModelKey = trim((string)($entitlement['plan_key'] ?? ''));

    $statement = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            payment_kind = :payment_kind,
            requested_model_key = CASE
                WHEN :resolved_model_key = "" THEN requested_model_key
                ELSE :resolved_model_key
            END,
            stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
            stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
            entitlement_source_type = :entitlement_source_type,
            entitlement_source_id = :entitlement_source_id,
            paid_at = COALESCE(paid_at, UTC_TIMESTAMP()),
            updated_at = UTC_TIMESTAMP()
         WHERE id = :submission_id'
    );

    $statement->execute([
        ':status' => 'paid',
        ':payment_kind' => 'subscription',
        ':resolved_model_key' => $resolvedModelKey,
        ':stripe_customer_id' => $effectiveCustomerId !== '' ? $effectiveCustomerId : null,
        ':stripe_subscription_id' => $subscriptionId !== null ? (string)$subscriptionId : null,
        ':entitlement_source_type' => 'subscription',
        ':entitlement_source_id' => isset($entitlement['id']) ? (string)$entitlement['id'] : null,
        ':submission_id' => (string)$submission['id'],
    ]);
}
/* === END BLOCK: STRIPE_CHECKOUT_MARK_PAID_FROM_ACTIVE_SUBSCRIPTION_V2 === */

function scs_create_checkout_session(array $submission, string $customerId, array $stripeConfig, array $appConfig): array
{
    $modelKey = (string)$submission['requested_model_key'];
    $priceId = (string)$stripeConfig['prices'][$modelKey];
    $mode = $modelKey === 'single' ? 'payment' : 'subscription';
    $paymentReferenceKey = (string)$submission['payment_reference_key'];

    $formData = [
        'mode' => $mode,
        'customer' => $customerId,
        'client_reference_id' => $paymentReferenceKey,
        'success_url' => scs_build_success_url($appConfig['base_url'], $paymentReferenceKey),
        'cancel_url' => scs_build_cancel_url($appConfig['base_url'], $paymentReferenceKey),
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => '1',
        'metadata[submission_id]' => (string)$submission['id'],
        'metadata[payment_reference_key]' => $paymentReferenceKey,
        'metadata[organizer_id]' => (string)$submission['organizer_id'],
        'metadata[requested_model_key]' => $modelKey,
    ];

    if ($mode === 'subscription') {
        $formData['subscription_data[metadata][submission_id]'] = (string)$submission['id'];
        $formData['subscription_data[metadata][payment_reference_key]'] = $paymentReferenceKey;
        $formData['subscription_data[metadata][organizer_id]'] = (string)$submission['organizer_id'];
        $formData['subscription_data[metadata][requested_model_key]'] = $modelKey;
    }

    $session = scs_stripe_api_request($stripeConfig['secret_key'], 'checkout/sessions', $formData);

    $sessionId = trim((string)($session['id'] ?? ''));
    $sessionUrl = trim((string)($session['url'] ?? ''));

    if ($sessionId === '' || $sessionUrl === '') {
        throw new RuntimeException('Stripe checkout session is incomplete.');
    }

    return [
        'id' => $sessionId,
        'url' => $sessionUrl,
        'price_id' => $priceId,
        'mode' => $mode,
        'checkout_required' => true,
    ];
}

/* === BEGIN BLOCK: STRIPE_CHECKOUT_ENTRYPOINT_AND_ERROR_HANDLING_V2 | Zweck: repariert den kaputten Endpoint und nutzt vorhandene aktive Abos automatisch fuer Event-Einreichungen; Umfang: kompletter Try/Catch-Entrypoint === */
try {
    $input = scs_read_json_body();
    $submissionId = scs_required_positive_int($input, 'submission_id', 'submission_id');

    $pdo = be_db();
    $stripeConfig = scs_get_stripe_config();
    $appConfig = scs_get_app_config();

    $pdo->beginTransaction();

    $submission = scs_fetch_submission($pdo, $submissionId);
    scs_assert_submission_ready($submission);

    $customerId = scs_ensure_stripe_customer($pdo, $submission, $stripeConfig['secret_key']);
    $existingSubscriptionEntitlement = scs_fetch_usable_active_subscription_entitlement($pdo, $submission);

    if (is_array($existingSubscriptionEntitlement)) {
        scs_mark_submission_paid_from_active_subscription($pdo, $submission, $existingSubscriptionEntitlement, $customerId);
        $pdo->commit();

        $resolvedModelKey = trim((string)($existingSubscriptionEntitlement['plan_key'] ?? ''));
        $effectiveModelKey = $resolvedModelKey !== ''
            ? $resolvedModelKey
            : (string)$submission['requested_model_key'];

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'submission_id' => $submissionId,
                'submission_status' => 'paid',
                'requested_model_key' => $effectiveModelKey,
                'payment_kind' => 'subscription',
                'stripe_customer_id' => $customerId,
                'checkout_required' => false,
                'redirect_url' => scs_build_existing_subscription_redirect_url(
                    $appConfig['base_url'],
                    (string)$submission['payment_reference_key']
                ),
                'used_existing_subscription' => true,
            ],
        ]);
    }

    $checkoutSession = scs_create_checkout_session($submission, $customerId, $stripeConfig, $appConfig);

    $updateSubmission = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            stripe_customer_id = :stripe_customer_id,
            stripe_checkout_session_id = :stripe_checkout_session_id,
            stripe_price_id = :stripe_price_id
         WHERE id = :submission_id'
    );

    $updateSubmission->execute([
        ':status' => 'checkout_started',
        ':stripe_customer_id' => $customerId,
        ':stripe_checkout_session_id' => $checkoutSession['id'],
        ':stripe_price_id' => $checkoutSession['price_id'],
        ':submission_id' => $submissionId,
    ]);

    $pdo->commit();

    be_json_response(201, [
        'status' => 'ok',
        'data' => [
            'submission_id' => $submissionId,
            'submission_status' => 'checkout_started',
            'requested_model_key' => (string)$submission['requested_model_key'],
            'payment_kind' => (string)$submission['payment_kind'],
            'stripe_customer_id' => $customerId,
            'stripe_checkout_session_id' => $checkoutSession['id'],
            'checkout_required' => true,
            'checkout_url' => $checkoutSession['url'],
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
        'message' => 'Checkout session creation failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}
/* === END BLOCK: STRIPE_CHECKOUT_ENTRYPOINT_AND_ERROR_HANDLING_V2 === */

/* === END FILE: api/stripe/create-checkout-session.php === */
