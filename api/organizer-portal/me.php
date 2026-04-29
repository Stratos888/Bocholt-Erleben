<?php
declare(strict_types=1);
/* === BEGIN FILE: api/organizer-portal/me.php | Zweck: liefert fuer eine gueltige Veranstalter-Portal-Session den aktuellen Organizer-, Kontingent- und Submission-Status; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function opm_get_cookie_name(): string
{
    return 'be_organizer_portal_session';
}

function opm_read_session_token_from_cookie(): string
{
    $token = trim((string)($_COOKIE[opm_get_cookie_name()] ?? ''));
    if ($token === '') {
        throw new InvalidArgumentException('Organizer session cookie is missing.');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Organizer session cookie format is invalid.');
    }

    return $token;
}

function opm_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function opm_fetch_portal_session(PDO $pdo, string $sessionTokenHash): array
{
    $statement = $pdo->prepare(
        'SELECT
            s.id AS portal_session_id,
            s.organizer_id,
            s.expires_at,
            s.revoked_at,
            s.last_seen_at,
            o.organization_name,
            o.contact_name,
            o.email,
            o.email_normalized,
            o.stripe_customer_id,
            o.default_plan_key
         FROM organizer_portal_sessions s
         INNER JOIN organizers o ON o.id = s.organizer_id
         WHERE s.session_token_hash = :session_token_hash
         LIMIT 1'
    );

    $statement->execute([
        ':session_token_hash' => $sessionTokenHash,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new InvalidArgumentException('Organizer session is invalid.');
    }

    return $row;
}

function opm_assert_portal_session_usable(array $sessionRow): void
{
    if (!empty($sessionRow['revoked_at'])) {
        throw new InvalidArgumentException('Organizer session was revoked.');
    }

    $expiresAt = trim((string)($sessionRow['expires_at'] ?? ''));
    if ($expiresAt === '') {
        throw new RuntimeException('Organizer session expiry is missing.');
    }

    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiryUtc = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));

    if ($expiryUtc < $nowUtc) {
        throw new InvalidArgumentException('Organizer session expired.');
    }
}

function opm_touch_portal_session(PDO $pdo, int $portalSessionId): void
{
    $statement = $pdo->prepare(
        'UPDATE organizer_portal_sessions
         SET last_seen_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $statement->execute([
        ':id' => $portalSessionId,
    ]);
}

function opm_fetch_active_subscription(PDO $pdo, int $organizerId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            source_provider,
            stripe_subscription_id,
            stripe_customer_id,
            plan_key,
            status,
            current_period_start,
            current_period_end,
            cancel_at_period_end,
            canceled_at,
            created_at,
            updated_at
         FROM subscriptions
         WHERE organizer_id = :organizer_id
         ORDER BY
            CASE
                WHEN status = "active" THEN 0
                WHEN status = "trialing" THEN 1
                WHEN status = "past_due" THEN 2
                ELSE 3
            END,
            current_period_end DESC,
            id DESC
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function opm_fetch_quota_summary(PDO $pdo, int $organizerId, ?array $activeSubscription = null): array
{
    $subscriptionId = 0;
    $subscriptionStatus = strtolower(trim((string)($activeSubscription['status'] ?? '')));

    if (
        is_array($activeSubscription)
        && in_array($subscriptionStatus, ['active', 'trialing'], true)
        && (int)($activeSubscription['id'] ?? 0) > 0
    ) {
        $subscriptionId = (int)$activeSubscription['id'];
    }

    if ($subscriptionId > 0) {
        $statement = $pdo->prepare(
            'SELECT
                COUNT(*) AS entitlement_count,
                MAX(CASE WHEN is_unlimited = 1 THEN 1 ELSE 0 END) AS has_unlimited,
                COALESCE(SUM(included_publications), 0) AS included_total,
                COALESCE(SUM(consumed_publications), 0) AS consumed_total,
                MIN(period_start) AS current_period_start,
                MAX(period_end) AS current_period_end
             FROM publication_entitlements
             WHERE organizer_id = :organizer_id
               AND subscription_id = :subscription_id
               AND source_type = "subscription"
               AND status = "active"
               AND (period_start IS NULL OR period_start <= UTC_TIMESTAMP())
               AND (period_end IS NULL OR period_end >= UTC_TIMESTAMP())'
        );

        $statement->execute([
            ':organizer_id' => $organizerId,
            ':subscription_id' => $subscriptionId,
        ]);
    } else {
        $statement = $pdo->prepare(
            'SELECT
                COUNT(*) AS entitlement_count,
                MAX(CASE WHEN is_unlimited = 1 THEN 1 ELSE 0 END) AS has_unlimited,
                COALESCE(SUM(included_publications), 0) AS included_total,
                COALESCE(SUM(consumed_publications), 0) AS consumed_total,
                MIN(period_start) AS current_period_start,
                MAX(period_end) AS current_period_end
             FROM publication_entitlements
             WHERE organizer_id = :organizer_id
               AND status = "active"
               AND (period_start IS NULL OR period_start <= UTC_TIMESTAMP())
               AND (period_end IS NULL OR period_end >= UTC_TIMESTAMP())'
        );

        $statement->execute([
            ':organizer_id' => $organizerId,
        ]);
    }

    $row = $statement->fetch();

    if (!is_array($row)) {
        return [
            'entitlement_count' => 0,
            'has_unlimited' => false,
            'included_total' => 0,
            'consumed_total' => 0,
            'remaining_total' => 0,
            'current_period_start' => null,
            'current_period_end' => null,
        ];
    }

    $entitlementCount = (int)($row['entitlement_count'] ?? 0);
    $hasUnlimited = ((int)($row['has_unlimited'] ?? 0)) === 1;
    $includedTotal = (int)($row['included_total'] ?? 0);
    $consumedTotal = (int)($row['consumed_total'] ?? 0);
    $remainingTotal = $hasUnlimited ? null : max(0, $includedTotal - $consumedTotal);

    return [
        'entitlement_count' => $entitlementCount,
        'has_unlimited' => $hasUnlimited,
        'included_total' => $includedTotal,
        'consumed_total' => $consumedTotal,
        'remaining_total' => $remainingTotal,
        'current_period_start' => $row['current_period_start'] !== null ? (string)$row['current_period_start'] : null,
        'current_period_end' => $row['current_period_end'] !== null ? (string)$row['current_period_end'] : null,
    ];
}

function opm_fetch_recent_submissions(PDO $pdo, int $organizerId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            payment_reference_key,
            event_url,
            title,
            start_date,
            time_text,
            location_name,
            ticket_url,
            description_text,
            notes_text,
            stripe_checkout_session_id,
            stripe_customer_id,
            stripe_subscription_id,
            stripe_price_id,
            paid_at,
            review_started_at,
            approved_at,
            rejected_at,
            created_at,
            updated_at
         FROM submissions
         WHERE organizer_id = :organizer_id
           AND submission_kind = "event"
         ORDER BY id DESC
         LIMIT 25'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $rows = $statement->fetchAll();
    return is_array($rows) ? $rows : [];
}

try {
    $plainSessionToken = opm_read_session_token_from_cookie();
    $sessionTokenHash = opm_hash_token($plainSessionToken);

    $pdo = be_db();
    $sessionRow = opm_fetch_portal_session($pdo, $sessionTokenHash);
    opm_assert_portal_session_usable($sessionRow);
    opm_touch_portal_session($pdo, (int)$sessionRow['portal_session_id']);

    $organizerId = (int)$sessionRow['organizer_id'];
    $activeSubscription = opm_fetch_active_subscription($pdo, $organizerId);
$quotaSummary = opm_fetch_quota_summary($pdo, $organizerId, $activeSubscription);
    $recentSubmissions = opm_fetch_recent_submissions($pdo, $organizerId);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'organizer' => [
                'id' => $organizerId,
                'organization_name' => (string)$sessionRow['organization_name'],
                'contact_name' => $sessionRow['contact_name'] !== null ? (string)$sessionRow['contact_name'] : null,
                'email' => (string)$sessionRow['email'],
                'default_plan_key' => $sessionRow['default_plan_key'] !== null ? (string)$sessionRow['default_plan_key'] : null,
                'stripe_customer_id' => $sessionRow['stripe_customer_id'] !== null ? (string)$sessionRow['stripe_customer_id'] : null,
            ],
            'portal_session' => [
                'id' => (int)$sessionRow['portal_session_id'],
                'expires_at_utc' => (string)$sessionRow['expires_at'],
                'last_seen_at_utc' => $sessionRow['last_seen_at'] !== null ? (string)$sessionRow['last_seen_at'] : null,
            ],
            'subscription' => $activeSubscription,
            'quota' => $quotaSummary,
            'recent_submissions' => $recentSubmissions,
        ],
    ]);
} catch (InvalidArgumentException $error) {
    be_json_response(401, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Organizer portal state could not be loaded.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/organizer-portal/me.php === */
