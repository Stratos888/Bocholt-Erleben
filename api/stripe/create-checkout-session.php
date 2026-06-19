<?php
declare(strict_types=1);
/* === BEGIN FILE: api/stripe/create-checkout-session.php | Zweck: erzeugt serverseitig Stripe-Checkout-Sessions für gültige interne Zahlungsstart-Links und bestehende Legacy-Submissions; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';
require dirname(__DIR__) . '/push/_lib.php';
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
/* === BEGIN BLOCK: STRIPE_CHECKOUT_PAYMENT_TOKEN_INPUT_V1 | Zweck: liest interne Zahlungsstart-Tokens ohne direkte Submission-ID für Einzeltermin-Zahlungen; Umfang: Token-Helfer nach Positive-Int-Validierung === */
function scs_payment_start_token_or_null(array $input): ?string
{
    $token = trim((string)($input['payment_start_token'] ?? ''));
    if ($token === '') {
        return null;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Zahlungslink ist ungültig.');
    }

    return $token;
}
/* === END BLOCK: STRIPE_CHECKOUT_PAYMENT_TOKEN_INPUT_V1 === */
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
        'activity_basic' => trim((string)($prices['activity_basic'] ?? '')),
        'activity_plus' => trim((string)($prices['activity_plus'] ?? '')),
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
function scs_notify_paid_submission_inbox_best_effort(int $submissionId, string $submissionKind): void
{
    /* === BEGIN FUNCTION: scs_notify_paid_submission_inbox_best_effort | Zweck: sendet nach Abo-Reuse die passende Inbox-Pushmeldung fuer Event- oder Aktivitaetseinreichungen; Umfang: best-effort Zusatzlogik === */
    if ($submissionId <= 0) {
        return;
    }

    $eventType = $submissionKind === 'activity' ? 'activity_submission' : 'event_submission';

    try {
        be_push_notify_inbox_best_effort($eventType, 'submission:' . $submissionId . ':paid');
    } catch (Throwable $error) {
        error_log('Checkout push notification skipped: ' . $error->getMessage());
    }
    /* === END FUNCTION: scs_notify_paid_submission_inbox_best_effort === */
}

function scs_fetch_submission(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            s.id,
            s.organizer_id,
            s.submission_kind,
            s.status,
            s.requested_model_key,
            s.payment_kind,
            s.intake_origin,
            s.payment_reference_key,
            s.payment_start_token_hash,
            s.payment_start_token_expires_at,
            s.email_snapshot,
            s.organization_name_snapshot,
            s.title,
            s.paid_at,
            s.approved_at,
            s.rejected_at,
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

/* === BEGIN BLOCK: STRIPE_CHECKOUT_FETCH_BY_PAYMENT_TOKEN_V1 | Zweck: lädt eine Einzelevent-Submission anhand des internen Zahlungsstart-Tokens; Umfang: Token-Hash-Abfrage für Checkout-Start === */
function scs_fetch_submission_by_payment_start_token(PDO $pdo, string $token): array
{
    $statement = $pdo->prepare(
        'SELECT
            s.id,
            s.organizer_id,
            s.submission_kind,
            s.status,
            s.requested_model_key,
            s.payment_kind,
            s.intake_origin,
            s.payment_reference_key,
            s.payment_start_token_hash,
            s.payment_start_token_expires_at,
            s.email_snapshot,
            s.organization_name_snapshot,
            s.title,
            s.paid_at,
            s.approved_at,
            s.rejected_at,
            s.stripe_checkout_session_id,
            s.stripe_customer_id AS submission_stripe_customer_id,
            s.stripe_subscription_id,
            o.organization_name,
            o.contact_name,
            o.email,
            o.stripe_customer_id AS organizer_stripe_customer_id
        FROM submissions s
        INNER JOIN organizers o ON o.id = s.organizer_id
        WHERE s.payment_start_token_hash = :payment_start_token_hash
        LIMIT 1'
    );

    $statement->execute([
        ':payment_start_token_hash' => hash('sha256', $token),
    ]);

    $submission = $statement->fetch();
    if (!is_array($submission)) {
        throw new InvalidArgumentException('Zahlungslink wurde nicht gefunden.');
    }

    return $submission;
}
/* === END BLOCK: STRIPE_CHECKOUT_FETCH_BY_PAYMENT_TOKEN_V1 === */

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_CHECKOUT_PAYMENT_TOKEN_READY_V1 | Zweck: erlaubt Zahlungsstart-Token fuer Einzeltermin und Aktivitaetspraesenz; Umfang: ersetzt STRIPE_CHECKOUT_ASSERT_PAYMENT_TOKEN_READY_V1 === */
function scs_assert_payment_start_token_ready(array $submission): void
{
    $submissionKind = (string)($submission['submission_kind'] ?? '');
    $paymentKind = (string)($submission['payment_kind'] ?? '');
    $intakeOrigin = (string)($submission['intake_origin'] ?? '');
    $modelKey = (string)($submission['requested_model_key'] ?? '');

    $isSingleEvent = $submissionKind === 'event' && $paymentKind === 'single' && $intakeOrigin === 'single_event';
    $isActivityPresence = $submissionKind === 'activity'
        && $paymentKind === 'subscription'
        && $intakeOrigin === 'activity_presence'
        && in_array($modelKey, ['activity_basic', 'activity_plus'], true);

    if (!$isSingleEvent && !$isActivityPresence) {
        throw new InvalidArgumentException('Dieser Zahlungslink ist für diese Einreichung nicht gültig.');
    }

    $status = (string)($submission['status'] ?? '');
    if ($status !== 'payment_released') {
        throw new InvalidArgumentException('Diese Einreichung ist aktuell nicht zur Zahlung freigegeben.');
    }

    if (!empty($submission['paid_at'])) {
        throw new InvalidArgumentException('Diese Einreichung wurde bereits bezahlt.');
    }

    if (!empty($submission['approved_at']) || !empty($submission['rejected_at'])) {
        throw new InvalidArgumentException('Diese Einreichung ist bereits abgeschlossen.');
    }

    $expiresAtRaw = trim((string)($submission['payment_start_token_expires_at'] ?? ''));
    if ($expiresAtRaw === '') {
        throw new InvalidArgumentException('Zahlungslink ist nicht vollständig eingerichtet.');
    }

    $expiresAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiresAtRaw);
    if (!$expiresAt instanceof DateTimeImmutable || $expiresAt < new DateTimeImmutable('now')) {
        throw new InvalidArgumentException('Zahlungslink ist abgelaufen.');
    }
}
/* === END BLOCK: ACTIVITY_PRESENCE_CHECKOUT_PAYMENT_TOKEN_READY_V1 === */

function scs_assert_submission_ready(array $submission): void
{
    $modelKey = (string)($submission['requested_model_key'] ?? '');
    if ($modelKey === 'single' || (string)($submission['payment_kind'] ?? '') === 'single') {
        throw new InvalidArgumentException('Einzeltermin-Zahlungen müssen über den internen Zahlungslink gestartet werden.');
    }

    $status = (string)($submission['status'] ?? '');
    if (!in_array($status, ['draft', 'checkout_started'], true)) {
        throw new InvalidArgumentException('Submission ist für Checkout aktuell nicht freigegeben.');
    }

    if (!in_array($modelKey, ['starter', 'active', 'unlimited'], true)) {
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

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_CHECKOUT_RETURN_URLS_V2 | Zweck: koppelt Aktivitaets-Zahlungsrueckspruenge an die E-Mail der Einreichung, damit der Anbieterbereich den passenden Account-Kontext vorfuellen kann; Umfang: ersetzt scs_build_success_url und scs_build_cancel_url === */
function scs_activity_email_query_part(array $submission): string
{
    $email = trim((string)($submission['email_snapshot'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return '&email=' . rawurlencode($email);
}

function scs_build_success_url(string $baseUrl, array $submission): string
{
    $paymentReferenceKey = (string)$submission['payment_reference_key'];
    $isActivity = (string)($submission['submission_kind'] ?? 'event') === 'activity';
    $path = $isActivity ? '/aktivitaeten/sichtbar-werden/erfolg/' : '/events-veroeffentlichen/erfolg/';
    $emailPart = $isActivity ? scs_activity_email_query_part($submission) : '';

    return rtrim($baseUrl, '/') . $path . '?submission_ref=' . rawurlencode($paymentReferenceKey) . '&session_id={CHECKOUT_SESSION_ID}' . $emailPart;
}

function scs_build_cancel_url(string $baseUrl, array $submission): string
{
    $paymentReferenceKey = (string)$submission['payment_reference_key'];
    $isActivity = (string)($submission['submission_kind'] ?? 'event') === 'activity';

    if ($isActivity) {
        return rtrim($baseUrl, '/') . '/aktivitaeten/sichtbar-werden/?cancelled=1&submission_ref=' . rawurlencode($paymentReferenceKey) . scs_activity_email_query_part($submission);
    }

    return rtrim($baseUrl, '/') . '/events-veroeffentlichen/abgebrochen/?submission_ref=' . rawurlencode($paymentReferenceKey);
}
/* === END BLOCK: ACTIVITY_PRESENCE_CHECKOUT_RETURN_URLS_V2 === */

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

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_FETCH_USABLE_ACTIVE_ENTITLEMENT_V1 | Zweck: trennt Event- und Aktivitaetspraesenz-Kontingente beim Checkout-/Abo-Reuse; Umfang: ersetzt STRIPE_CHECKOUT_FETCH_USABLE_ACTIVE_ENTITLEMENT_V4 === */
function scs_subscription_plan_keys_for_submission(array $submission): array
{
    $submissionKind = trim((string)($submission['submission_kind'] ?? 'event'));

    if ($submissionKind === 'activity') {
        return ['activity_basic', 'activity_plus'];
    }

    return ['starter', 'active', 'unlimited'];
}

function scs_fetch_usable_active_subscription_entitlement(PDO $pdo, array $submission): ?array
{
    $organizerId = (int)($submission['organizer_id'] ?? 0);
    if ($organizerId <= 0) {
        return null;
    }

    $requestedPlanKey = trim((string)($submission['requested_model_key'] ?? ''));
    $planKeys = scs_subscription_plan_keys_for_submission($submission);
    $planPlaceholders = [];
    $params = [
        ':organizer_id' => $organizerId,
        ':requested_plan_key_check' => $requestedPlanKey,
        ':requested_plan_key_value' => $requestedPlanKey,
    ];

    foreach ($planKeys as $index => $planKey) {
        $placeholder = ':plan_key_' . $index;
        $planPlaceholders[] = $placeholder;
        $params[$placeholder] = $planKey;
    }

    $statement = $pdo->prepare(
        'SELECT
            pe.id,
            pe.organizer_id,
            pe.subscription_id,
            pe.plan_key,
            pe.included_publications,
            COALESCE(pcsum.consumed_units, 0) AS consumed_units,
            pe.is_unlimited,
            pe.period_start,
            pe.period_end,
            s.stripe_subscription_id,
            s.stripe_customer_id,
            s.status AS subscription_status
         FROM publication_entitlements pe
         INNER JOIN subscriptions s ON s.id = pe.subscription_id
         LEFT JOIN (
            SELECT
                entitlement_id,
                COALESCE(SUM(units), 0) AS consumed_units
            FROM publication_consumptions
            GROUP BY entitlement_id
         ) pcsum ON pcsum.entitlement_id = pe.id
         WHERE pe.organizer_id = :organizer_id
           AND pe.source_type = "subscription"
           AND pe.status = "active"
           AND pe.plan_key IN (' . implode(', ', $planPlaceholders) . ')
           AND s.status IN ("active", "trialing")
           AND (pe.period_start IS NULL OR pe.period_start <= UTC_TIMESTAMP())
           AND (pe.period_end IS NULL OR pe.period_end >= UTC_TIMESTAMP())
           AND (
                pe.is_unlimited = 1
                OR COALESCE(pcsum.consumed_units, 0) < pe.included_publications
           )
         ORDER BY
            CASE
                WHEN :requested_plan_key_check <> "" AND pe.plan_key = :requested_plan_key_value THEN 0
                ELSE 1
            END ASC,
            pe.is_unlimited DESC,
            pe.period_end ASC,
            pe.id ASC
         LIMIT 1'
    );

    $statement->execute($params);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}
/* === END BLOCK: ACTIVITY_PRESENCE_FETCH_USABLE_ACTIVE_ENTITLEMENT_V1 === */

function scs_build_existing_subscription_redirect_url(string $baseUrl, array $submission): string
{
    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_EXISTING_SUBSCRIPTION_REDIRECT_EMAIL_V1 | Zweck: fuehrt bestehende Aktivitaetspraesenz-Rueckspruenge mit E-Mail-Kontext in den passenden Anbieterbereich; Umfang: ersetzt nur den Existing-Subscription-Redirect-Builder === */
    $paymentReferenceKey = (string)$submission['payment_reference_key'];
    $isActivity = (string)($submission['submission_kind'] ?? 'event') === 'activity';
    $path = $isActivity ? '/aktivitaeten/sichtbar-werden/erfolg/' : '/events-veroeffentlichen/erfolg/';
    $emailPart = $isActivity ? scs_activity_email_query_part($submission) : '';

    return rtrim($baseUrl, '/') . $path . '?submission_ref=' . rawurlencode($paymentReferenceKey) . '&flow=existing-subscription' . $emailPart;
    /* === END BLOCK: ACTIVITY_PRESENCE_EXISTING_SUBSCRIPTION_REDIRECT_EMAIL_V1 === */
}

/* === BEGIN BLOCK: STRIPE_CHECKOUT_MARK_PAID_FROM_ACTIVE_SUBSCRIPTION_V5 | Zweck: setzt Abo-Reuse fuer Events und Aktivitaetspraesenzen mit korrekter Herkunft auf bezahlt; Umfang: komplette Funktion === */
function scs_mark_submission_paid_from_active_subscription(PDO $pdo, array $submission, array $entitlement, string $customerId): void
{
    $stripeSubscriptionId = trim((string)($entitlement['stripe_subscription_id'] ?? ''));
    $effectiveCustomerId = trim((string)($entitlement['stripe_customer_id'] ?? ''));
    $resolvedModelKey = trim((string)($entitlement['plan_key'] ?? ''));
    $submissionKind = trim((string)($submission['submission_kind'] ?? 'event'));
    $resolvedIntakeOrigin = $submissionKind === 'activity' ? 'activity_presence' : 'membership';

    if ($effectiveCustomerId === '') {
        $effectiveCustomerId = $customerId;
    }

    $statement = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            payment_kind = :payment_kind,
            intake_origin = :intake_origin,
            requested_model_key = CASE
                WHEN :resolved_model_key_check = "" THEN requested_model_key
                ELSE :resolved_model_key_value
            END,
            stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
            stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
            paid_at = CASE
                WHEN paid_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE paid_at
            END,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :submission_id'
    );

    $statement->bindValue(':status', 'paid', PDO::PARAM_STR);
    $statement->bindValue(':payment_kind', 'subscription', PDO::PARAM_STR);
    $statement->bindValue(':intake_origin', $resolvedIntakeOrigin, PDO::PARAM_STR);
    $statement->bindValue(':resolved_model_key_check', $resolvedModelKey, PDO::PARAM_STR);
    $statement->bindValue(':resolved_model_key_value', $resolvedModelKey, PDO::PARAM_STR);
    $statement->bindValue(':submission_id', (int)$submission['id'], PDO::PARAM_INT);

    if ($effectiveCustomerId === '') {
        $statement->bindValue(':stripe_customer_id', null, PDO::PARAM_NULL);
    } else {
        $statement->bindValue(':stripe_customer_id', $effectiveCustomerId, PDO::PARAM_STR);
    }

    if ($stripeSubscriptionId === '') {
        $statement->bindValue(':stripe_subscription_id', null, PDO::PARAM_NULL);
    } else {
        $statement->bindValue(':stripe_subscription_id', $stripeSubscriptionId, PDO::PARAM_STR);
    }

    $statement->execute();
}
/* === END BLOCK: STRIPE_CHECKOUT_MARK_PAID_FROM_ACTIVE_SUBSCRIPTION_V5 === */

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
        'success_url' => scs_build_success_url($appConfig['base_url'], $submission),
        'cancel_url' => scs_build_cancel_url($appConfig['base_url'], $submission),
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => '1',
        'metadata[submission_id]' => (string)$submission['id'],
        'metadata[payment_reference_key]' => $paymentReferenceKey,
        'metadata[organizer_id]' => (string)$submission['organizer_id'],
        'metadata[requested_model_key]' => $modelKey,
        'metadata[submission_kind]' => (string)($submission['submission_kind'] ?? 'event'),
    ];

    if ($mode === 'subscription') {
        $formData['subscription_data[metadata][submission_id]'] = (string)$submission['id'];
        $formData['subscription_data[metadata][payment_reference_key]'] = $paymentReferenceKey;
        $formData['subscription_data[metadata][organizer_id]'] = (string)$submission['organizer_id'];
        $formData['subscription_data[metadata][requested_model_key]'] = $modelKey;
        $formData['subscription_data[metadata][submission_kind]'] = (string)($submission['submission_kind'] ?? 'event');
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

try {
    $input = scs_read_json_body();
    $paymentStartToken = scs_payment_start_token_or_null($input);

    $pdo = be_db();
    $stripeConfig = scs_get_stripe_config();
    $appConfig = scs_get_app_config();

    $pdo->beginTransaction();

    if ($paymentStartToken !== null) {
        $submission = scs_fetch_submission_by_payment_start_token($pdo, $paymentStartToken);
        scs_assert_payment_start_token_ready($submission);
    } else {
        $submissionId = scs_required_positive_int($input, 'submission_id', 'submission_id');
        $submission = scs_fetch_submission($pdo, $submissionId);
        scs_assert_submission_ready($submission);
    }

    $submissionId = (int)$submission['id'];

    $customerId = scs_ensure_stripe_customer($pdo, $submission, $stripeConfig['secret_key']);
    $existingSubscriptionEntitlement = ((string)($submission['payment_kind'] ?? '') === 'subscription')
        ? scs_fetch_usable_active_subscription_entitlement($pdo, $submission)
        : null;

    if (is_array($existingSubscriptionEntitlement)) {
        /* === BEGIN BLOCK: CHECKOUT_EXISTING_SUBSCRIPTION_REUSE_RESTORE_V3 | Zweck: stellt den bestehenden Abo-Reuse-Zweig wieder her und verhindert den Parse-Fatal im Checkout-Endpoint; Umfang: ersetzt nur den Existing-Subscription-Zweig nach Entitlement-Ermittlung === */
        scs_mark_submission_paid_from_active_subscription($pdo, $submission, $existingSubscriptionEntitlement, $customerId);
        $pdo->commit();

        // === BEGIN BLOCK: CHECKOUT_ACTIVE_SUBSCRIPTION_INBOX_PUSH_V2 | Zweck: neue per aktivem Tarif bezahlte Event- oder Aktivitaetseinreichung best-effort per Push melden; Umfang: keine Änderung am Response-/Checkout-Verhalten ===
        scs_notify_paid_submission_inbox_best_effort($submissionId, (string)($submission['submission_kind'] ?? 'event'));
        // === END BLOCK: CHECKOUT_ACTIVE_SUBSCRIPTION_INBOX_PUSH_V2 ===

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
                    $submission
                ),
                'used_existing_subscription' => true,
            ],
        ]);
        /* === END BLOCK: CHECKOUT_EXISTING_SUBSCRIPTION_REUSE_RESTORE_V3 === */
    }

    $checkoutSession = scs_create_checkout_session($submission, $customerId, $stripeConfig, $appConfig);

    $updateSubmission = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            stripe_customer_id = :stripe_customer_id,
            stripe_checkout_session_id = :stripe_checkout_session_id,
            stripe_price_id = :stripe_price_id,
            payment_started_at = CASE
                WHEN payment_started_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE payment_started_at
            END,
            updated_at = CURRENT_TIMESTAMP
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
/* === END BLOCK: STRIPE_CHECKOUT_ACTIVE_SUBSCRIPTION_REUSE_V3 === */

/* === END FILE: api/stripe/create-checkout-session.php === */
