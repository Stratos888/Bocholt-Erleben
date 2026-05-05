<?php
declare(strict_types=1);
/* === BEGIN FILE: api/subscriptions/start-membership.php | Zweck: startet eine Mitgliedschaft getrennt vom Event-Funnel, legt Organizer und Membership-Submission an und erzeugt bei Bedarf eine Stripe-Checkout-Session; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function asm_read_json_body(): array
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

function asm_nullable_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function asm_required_string(array $input, string $key, string $label): string
{
    $value = asm_nullable_string($input[$key] ?? null);
    if ($value === null) {
        throw new InvalidArgumentException($label . ' is required.');
    }

    return $value;
}

function asm_normalize_email(string $email): string
{
    $normalized = mb_strtolower(trim($email));

    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('E-Mail-Adresse ist ungültig.');
    }

    return $normalized;
}

function asm_validate_plan_key(string $planKey): string
{
    $allowed = ['starter', 'active', 'unlimited'];

    if (!in_array($planKey, $allowed, true)) {
        throw new InvalidArgumentException('Mitgliedschaftsmodell ist ungültig.');
    }

    return $planKey;
}

function asm_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function asm_get_app_config(): array
{
    $config = be_get_config();
    $app = $config['app'] ?? null;

    if (!is_array($app)) {
        throw new RuntimeException('App config is missing.');
    }

    $baseUrl = rtrim(trim((string)($app['base_url'] ?? '')), '/');
    if ($baseUrl === '') {
        throw new RuntimeException('App base URL is missing.');
    }

    return [
        'base_url' => $baseUrl,
    ];
}

function asm_get_stripe_config(): array
{
    $config = be_get_config();
    $stripe = $config['stripe'] ?? null;

    if (!is_array($stripe)) {
        throw new RuntimeException('Stripe config is missing.');
    }

    $secretKey = trim((string)($stripe['secret_key'] ?? ''));
    $prices = is_array($stripe['prices'] ?? null) ? $stripe['prices'] : [];

    $mappedPrices = [
        'starter' => trim((string)($prices['starter'] ?? '')),
        'active' => trim((string)($prices['active'] ?? '')),
        'unlimited' => trim((string)($prices['unlimited'] ?? '')),
    ];

    if ($secretKey === '') {
        throw new RuntimeException('Stripe secret key is missing.');
    }

    foreach ($mappedPrices as $planKey => $priceId) {
        if ($priceId === '') {
            throw new RuntimeException('Stripe price is missing for plan: ' . $planKey);
        }
    }

    return [
        'secret_key' => $secretKey,
        'prices' => $mappedPrices,
    ];
}

function asm_stripe_api_request(string $secretKey, string $endpointPath, array $formData): array
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
        $message = (string)($decoded['error']['message'] ?? 'Stripe request failed.');
        throw new RuntimeException($message);
    }

    return $decoded;
}

function asm_fetch_active_subscription(PDO $pdo, int $organizerId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            stripe_subscription_id,
            plan_key,
            status
         FROM subscriptions
         WHERE organizer_id = :organizer_id
           AND status IN ("active", "trialing", "past_due")
         ORDER BY id DESC
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function asm_find_organizer_by_email(PDO $pdo, string $emailNormalized): ?array
{
    /* === BEGIN FUNCTION: asm_find_organizer_by_email | Zweck: findet bestehende Veranstalter eindeutig ueber normalisierte E-Mail; Umfang: reine Lookup-Funktion fuer Membership-Start === */
    $statement = $pdo->prepare(
        'SELECT
            id,
            organization_name,
            email,
            email_normalized
         FROM organizers
         WHERE email_normalized = :email_normalized
         LIMIT 1'
    );

    $statement->execute([
        ':email_normalized' => $emailNormalized,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
    /* === END FUNCTION: asm_find_organizer_by_email === */
}

function asm_insert_organizer(PDO $pdo, string $organizationName, ?string $contactName, string $emailInput, string $emailNormalized): int
{
    /* === BEGIN FUNCTION: asm_insert_organizer | Zweck: legt neue Veranstalter ohne Upsert/Overwrite an; Umfang: Insert-only fuer Membership-Start === */
    $insert = $pdo->prepare(
        'INSERT INTO organizers (
            organization_name,
            contact_name,
            email,
            email_normalized
        ) VALUES (
            :organization_name,
            :contact_name,
            :email,
            :email_normalized
        )'
    );

    $insert->execute([
        ':organization_name' => $organizationName,
        ':contact_name' => $contactName,
        ':email' => $emailInput,
        ':email_normalized' => $emailNormalized,
    ]);

    return (int)$pdo->lastInsertId();
    /* === END FUNCTION: asm_insert_organizer === */
}

function asm_fetch_organizer(PDO $pdo, int $organizerId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organization_name,
            email,
            stripe_customer_id
         FROM organizers
         WHERE id = :organizer_id
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Organizer was not found.');
    }

    return $row;
}

function asm_ensure_stripe_customer(PDO $pdo, array $organizer, string $secretKey): string
{
    $existingCustomerId = trim((string)($organizer['stripe_customer_id'] ?? ''));
    if ($existingCustomerId !== '') {
        return $existingCustomerId;
    }

    $customer = asm_stripe_api_request($secretKey, 'customers', [
        'email' => (string)$organizer['email'],
        'name' => (string)$organizer['organization_name'],
        'metadata[organizer_id]' => (string)$organizer['id'],
    ]);

    $customerId = trim((string)($customer['id'] ?? ''));
    if ($customerId === '') {
        throw new RuntimeException('Stripe customer creation failed.');
    }

    $update = $pdo->prepare(
        'UPDATE organizers
         SET stripe_customer_id = :stripe_customer_id
         WHERE id = :organizer_id'
    );

    $update->execute([
        ':stripe_customer_id' => $customerId,
        ':organizer_id' => (int)$organizer['id'],
    ]);

    return $customerId;
}

function asm_insert_membership_submission(PDO $pdo, int $organizerId, string $planKey, string $organizationName, ?string $contactName, string $emailInput): array
{
    $paymentReferenceKey = asm_uuid_v4();

    $insert = $pdo->prepare(
        'INSERT INTO submissions (
            organizer_id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            payment_reference_key,
            organization_name_snapshot,
            contact_name_snapshot,
            email_snapshot
        ) VALUES (
            :organizer_id,
            :submission_kind,
            :status,
            :requested_model_key,
            :payment_kind,
            :payment_reference_key,
            :organization_name_snapshot,
            :contact_name_snapshot,
            :email_snapshot
        )'
    );

    $insert->execute([
        ':organizer_id' => $organizerId,
        ':submission_kind' => 'membership',
        ':status' => 'draft',
        ':requested_model_key' => $planKey,
        ':payment_kind' => 'subscription',
        ':payment_reference_key' => $paymentReferenceKey,
        ':organization_name_snapshot' => $organizationName,
        ':contact_name_snapshot' => $contactName,
        ':email_snapshot' => $emailInput,
    ]);

    return [
        'submission_id' => (int)$pdo->lastInsertId(),
        'payment_reference_key' => $paymentReferenceKey,
    ];
}

function asm_build_success_url(string $baseUrl, string $paymentReferenceKey, string $emailInput): string
{
    return $baseUrl
        . '/fuer-veranstalter/login/?membership_started=1'
        . '&submission_ref=' . rawurlencode($paymentReferenceKey)
        . '&email=' . rawurlencode($emailInput);
}

function asm_build_cancel_url(string $baseUrl): string
{
    return $baseUrl . '/fuer-veranstalter/?cancelled=1';
}

try {
    $input = asm_read_json_body();

    $planKey = asm_validate_plan_key(
        asm_required_string($input, 'requested_model_key', 'Mitgliedschaftsmodell')
    );
    $organizationName = asm_required_string($input, 'organization_name', 'Veranstalter / Organisation');
    $contactName = asm_nullable_string($input['contact_name'] ?? null);
    $emailInput = asm_required_string($input, 'email', 'E-Mail-Adresse');
    $emailNormalized = asm_normalize_email($emailInput);

    $pdo = be_db();
    $stripeConfig = asm_get_stripe_config();
    $appConfig = asm_get_app_config();

    /* === BEGIN BLOCK: MEMBERSHIP_START_EXISTING_EMAIL_GUARD_V2 | Zweck: verhindert stilles Ueberschreiben bestehender Veranstalter bei gleicher E-Mail; Umfang: Organizer-Ermittlung vor Checkout === */
    $pdo->beginTransaction();

    $existingOrganizer = asm_find_organizer_by_email($pdo, $emailNormalized);
    if (is_array($existingOrganizer)) {
        $pdo->rollBack();

        be_json_response(409, [
            'status' => 'error',
            'message' => 'Für diese E-Mail gibt es bereits eine Einreichung oder Mitgliedschaft. Bitte öffne deinen Bereich per E-Mail-Link.',
            'data' => [
                'existing_organizer' => true,
                'login_url' => '/fuer-veranstalter/login/?email=' . rawurlencode($emailInput),
            ],
        ]);
    }

    $organizerId = asm_insert_organizer($pdo, $organizationName, $contactName, $emailInput, $emailNormalized);
    $organizer = asm_fetch_organizer($pdo, $organizerId);
    /* === END BLOCK: MEMBERSHIP_START_EXISTING_EMAIL_GUARD_V2 === */
    $customerId = asm_ensure_stripe_customer($pdo, $organizer, $stripeConfig['secret_key']);
    $membershipSubmission = asm_insert_membership_submission(
        $pdo,
        $organizerId,
        $planKey,
        $organizationName,
        $contactName,
        $emailInput
    );

    $session = asm_stripe_api_request($stripeConfig['secret_key'], 'checkout/sessions', [
        'mode' => 'subscription',
        'customer' => $customerId,
        'client_reference_id' => $membershipSubmission['payment_reference_key'],
'success_url' => asm_build_success_url(
    $appConfig['base_url'],
    $membershipSubmission['payment_reference_key'],
    $emailInput
),
        'cancel_url' => asm_build_cancel_url($appConfig['base_url']),
        'line_items[0][price]' => (string)$stripeConfig['prices'][$planKey],
        'line_items[0][quantity]' => '1',
        'metadata[submission_id]' => (string)$membershipSubmission['submission_id'],
        'metadata[payment_reference_key]' => $membershipSubmission['payment_reference_key'],
        'metadata[organizer_id]' => (string)$organizerId,
        'metadata[requested_model_key]' => $planKey,
        'subscription_data[metadata][submission_id]' => (string)$membershipSubmission['submission_id'],
        'subscription_data[metadata][payment_reference_key]' => $membershipSubmission['payment_reference_key'],
        'subscription_data[metadata][organizer_id]' => (string)$organizerId,
        'subscription_data[metadata][requested_model_key]' => $planKey,
    ]);

    $sessionId = trim((string)($session['id'] ?? ''));
    $checkoutUrl = trim((string)($session['url'] ?? ''));

    if ($sessionId === '' || $checkoutUrl === '') {
        throw new RuntimeException('Stripe checkout session is incomplete.');
    }

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
        ':stripe_checkout_session_id' => $sessionId,
        ':stripe_price_id' => (string)$stripeConfig['prices'][$planKey],
        ':submission_id' => $membershipSubmission['submission_id'],
    ]);

    $pdo->commit();

    be_json_response(201, [
        'status' => 'ok',
        'data' => [
            'organizer_id' => $organizerId,
            'submission_id' => $membershipSubmission['submission_id'],
            'checkout_required' => true,
            'checkout_url' => $checkoutUrl,
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
        'message' => 'Membership start failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/subscriptions/start-membership.php === */
