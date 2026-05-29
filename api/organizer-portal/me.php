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

/* === BEGIN BLOCK: ORGANIZER_PORTAL_SUBSCRIPTION_OVERVIEW_SOURCE_V1 | Zweck: liefert neben der bisherigen Haupt-Subscription auch alle aktiven Tarif-Subscriptions und berechenbare Monatswerte; Umfang: ersetzt opm_fetch_active_subscription und ergänzt Subscription-Helper === */
function opm_plan_label(string $planKey): string
{
    return match ($planKey) {
        'starter' => 'Starter',
        'active' => 'Aktiv',
        'unlimited' => 'Dauerhaft',
        'activity_basic' => 'Aktivitätspräsenz Basis',
        'activity_plus' => 'Aktivitätspräsenz Plus',
        default => $planKey,
    };
}

function opm_plan_monthly_amount_cents(string $planKey): int
{
    return match ($planKey) {
        'starter' => 999,
        'active' => 1999,
        'unlimited' => 2999,
        'activity_basic' => 999,
        'activity_plus' => 1999,
        default => 0,
    };
}

function opm_format_eur_monthly(int $amountCents): string
{
    return number_format($amountCents / 100, 2, ',', '.') . ' € / Monat';
}

function opm_fetch_active_subscriptions(PDO $pdo, int $organizerId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            source_provider,
            stripe_subscription_id,
            stripe_customer_id,
            plan_key,
            pending_plan_key,
            status,
            current_period_start,
            current_period_end,
            pending_change_effective_at,
            cancel_at_period_end,
            canceled_at,
            created_at,
            updated_at
         FROM subscriptions
         WHERE organizer_id = :organizer_id
           AND status IN ("active", "trialing", "past_due")
         ORDER BY
            CASE
                WHEN status = "active" THEN 0
                WHEN status = "trialing" THEN 1
                WHEN status = "past_due" THEN 2
                ELSE 3
            END,
            CASE
                WHEN plan_key IN ("starter", "active", "unlimited") THEN 0
                WHEN plan_key IN ("activity_basic", "activity_plus") THEN 1
                ELSE 2
            END,
            current_period_end DESC,
            id DESC'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $rows = $statement->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        $planKey = trim((string)($row['plan_key'] ?? ''));
        $amountCents = opm_plan_monthly_amount_cents($planKey);
        $row['plan_label'] = opm_plan_label($planKey);
        $row['monthly_amount_cents'] = $amountCents;
        $row['monthly_amount_label'] = $amountCents > 0 ? opm_format_eur_monthly($amountCents) : null;
        return $row;
    }, $rows);
}

function opm_fetch_active_subscription(PDO $pdo, int $organizerId): ?array
{
    $subscriptions = opm_fetch_active_subscriptions($pdo, $organizerId);
    return $subscriptions[0] ?? null;
}

function opm_build_billing_summary(array $subscriptions): array
{
    $items = [];
    $monthlyTotalCents = 0;

    foreach ($subscriptions as $subscription) {
        if (!is_array($subscription)) {
            continue;
        }

        $status = strtolower(trim((string)($subscription['status'] ?? '')));
        if (!in_array($status, ['active', 'trialing', 'past_due'], true)) {
            continue;
        }

        $planKey = trim((string)($subscription['plan_key'] ?? ''));
        $amountCents = opm_plan_monthly_amount_cents($planKey);
        $monthlyTotalCents += $amountCents;

        $items[] = [
            'subscription_id' => (int)($subscription['id'] ?? 0),
            'plan_key' => $planKey,
            'plan_label' => opm_plan_label($planKey),
            'status' => $status,
            'monthly_amount_cents' => $amountCents,
            'monthly_amount_label' => $amountCents > 0 ? opm_format_eur_monthly($amountCents) : null,
            'current_period_start' => $subscription['current_period_start'] ?? null,
            'current_period_end' => $subscription['current_period_end'] ?? null,
            'cancel_at_period_end' => (int)($subscription['cancel_at_period_end'] ?? 0),
            'pending_plan_key' => $subscription['pending_plan_key'] ?? null,
            'pending_change_effective_at' => $subscription['pending_change_effective_at'] ?? null,
        ];
    }

    return [
        'currency' => 'EUR',
        'subscription_count' => count($items),
        'monthly_total_cents' => $monthlyTotalCents,
        'monthly_total_label' => opm_format_eur_monthly($monthlyTotalCents),
        'items' => $items,
    ];
}
/* === END BLOCK: ORGANIZER_PORTAL_SUBSCRIPTION_OVERVIEW_SOURCE_V1 === */

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
        $entitlementStatement = $pdo->prepare(
            'SELECT
                COUNT(*) AS entitlement_count,
                MAX(CASE WHEN is_unlimited = 1 THEN 1 ELSE 0 END) AS has_unlimited,
                COALESCE(SUM(included_publications), 0) AS included_total,
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

        $entitlementStatement->execute([
            ':organizer_id' => $organizerId,
            ':subscription_id' => $subscriptionId,
        ]);

        $consumptionStatement = $pdo->prepare(
            'SELECT
                COALESCE(SUM(pc.units), 0) AS consumed_total
             FROM publication_consumptions pc
             INNER JOIN publication_entitlements pe ON pe.id = pc.entitlement_id
             WHERE pe.organizer_id = :organizer_id
               AND pe.subscription_id = :subscription_id
               AND pe.source_type = "subscription"
               AND pe.status = "active"
               AND (pe.period_start IS NULL OR pe.period_start <= UTC_TIMESTAMP())
               AND (pe.period_end IS NULL OR pe.period_end >= UTC_TIMESTAMP())'
        );

        $consumptionStatement->execute([
            ':organizer_id' => $organizerId,
            ':subscription_id' => $subscriptionId,
        ]);
    } else {
        $entitlementStatement = $pdo->prepare(
            'SELECT
                COUNT(*) AS entitlement_count,
                MAX(CASE WHEN is_unlimited = 1 THEN 1 ELSE 0 END) AS has_unlimited,
                COALESCE(SUM(included_publications), 0) AS included_total,
                MIN(period_start) AS current_period_start,
                MAX(period_end) AS current_period_end
             FROM publication_entitlements
             WHERE organizer_id = :organizer_id
               AND status = "active"
               AND (period_start IS NULL OR period_start <= UTC_TIMESTAMP())
               AND (period_end IS NULL OR period_end >= UTC_TIMESTAMP())'
        );

        $entitlementStatement->execute([
            ':organizer_id' => $organizerId,
        ]);

        $consumptionStatement = $pdo->prepare(
            'SELECT
                COALESCE(SUM(pc.units), 0) AS consumed_total
             FROM publication_consumptions pc
             INNER JOIN publication_entitlements pe ON pe.id = pc.entitlement_id
             WHERE pe.organizer_id = :organizer_id
               AND pe.status = "active"
               AND (pe.period_start IS NULL OR pe.period_start <= UTC_TIMESTAMP())
               AND (pe.period_end IS NULL OR pe.period_end >= UTC_TIMESTAMP())'
        );

        $consumptionStatement->execute([
            ':organizer_id' => $organizerId,
        ]);
    }

    $entitlementRow = $entitlementStatement->fetch();
    $consumptionRow = $consumptionStatement->fetch();

    if (!is_array($entitlementRow)) {
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

    $entitlementCount = (int)($entitlementRow['entitlement_count'] ?? 0);
    $hasUnlimited = ((int)($entitlementRow['has_unlimited'] ?? 0)) === 1;
    $includedTotal = (int)($entitlementRow['included_total'] ?? 0);
    $consumedTotal = is_array($consumptionRow) ? (int)($consumptionRow['consumed_total'] ?? 0) : 0;
    $remainingTotal = $hasUnlimited ? null : max(0, $includedTotal - $consumedTotal);

    return [
        'entitlement_count' => $entitlementCount,
        'has_unlimited' => $hasUnlimited,
        'included_total' => $includedTotal,
        'consumed_total' => $consumedTotal,
        'remaining_total' => $remainingTotal,
        'current_period_start' => $entitlementRow['current_period_start'] !== null ? (string)$entitlementRow['current_period_start'] : null,
        'current_period_end' => $entitlementRow['current_period_end'] !== null ? (string)$entitlementRow['current_period_end'] : null,
    ];
}
/* === BEGIN BLOCK: ORGANIZER_PORTAL_QUOTA_BY_PLAN_V1 | Zweck: liefert Kontingente nach Tarifgruppe, damit Event- und Aktivitaetspraesenz-Tarife getrennt und zusammen darstellbar sind; Umfang: neue additive Quota-Auswertung === */
function opm_fetch_quota_summary_by_plan(PDO $pdo, int $organizerId): array
{
    $statement = $pdo->prepare(
        'SELECT
            pe.plan_key,
            COUNT(*) AS entitlement_count,
            MAX(CASE WHEN pe.is_unlimited = 1 THEN 1 ELSE 0 END) AS has_unlimited,
            COALESCE(SUM(pe.included_publications), 0) AS included_total,
            COALESCE(SUM(COALESCE(pcsum.consumed_units, 0)), 0) AS consumed_total,
            MIN(pe.period_start) AS current_period_start,
            MAX(pe.period_end) AS current_period_end
         FROM publication_entitlements pe
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
           AND (pe.period_start IS NULL OR pe.period_start <= UTC_TIMESTAMP())
           AND (pe.period_end IS NULL OR pe.period_end >= UTC_TIMESTAMP())
         GROUP BY pe.plan_key
         ORDER BY
            CASE
                WHEN pe.plan_key IN ("starter", "active", "unlimited") THEN 0
                WHEN pe.plan_key IN ("activity_basic", "activity_plus") THEN 1
                ELSE 2
            END,
            pe.plan_key ASC'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $rows = $statement->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        $planKey = trim((string)($row['plan_key'] ?? ''));
        $hasUnlimited = ((int)($row['has_unlimited'] ?? 0)) === 1;
        $includedTotal = (int)($row['included_total'] ?? 0);
        $consumedTotal = (int)($row['consumed_total'] ?? 0);

        return [
            'plan_key' => $planKey,
            'plan_label' => opm_plan_label($planKey),
            'entitlement_count' => (int)($row['entitlement_count'] ?? 0),
            'has_unlimited' => $hasUnlimited,
            'included_total' => $includedTotal,
            'consumed_total' => $consumedTotal,
            'remaining_total' => $hasUnlimited ? null : max(0, $includedTotal - $consumedTotal),
            'current_period_start' => $row['current_period_start'] !== null ? (string)$row['current_period_start'] : null,
            'current_period_end' => $row['current_period_end'] !== null ? (string)$row['current_period_end'] : null,
        ];
    }, $rows);
}
/* === END BLOCK: ORGANIZER_PORTAL_QUOTA_BY_PLAN_V1 === */
/* === BEGIN BLOCK: ORGANIZER_PORTAL_FETCH_CURRENT_SUBMISSIONS_V1 | Zweck: liefert fuer das Dashboard nur aktuelle/relevante Einreichungen, sortiert nach neuester Einreichung; Umfang: ersetzt opm_fetch_recent_submissions() === */
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
            location_address,
            location_public_confirmed,
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
            organizer_edited_at,
            organizer_edit_count,
            created_at,
            updated_at
         FROM submissions
         WHERE organizer_id = :organizer_id
           AND submission_kind IN ("event", "activity")
           AND (
                status IN ("pending_review", "payment_released", "draft", "checkout_started", "paid", "in_review", "approved", "rejected")
                OR (submission_kind = "event" AND start_date IS NOT NULL AND start_date >= CURRENT_DATE())
           )
         ORDER BY
            COALESCE(created_at, updated_at, paid_at, approved_at, rejected_at) DESC,
            id DESC
         LIMIT 10'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $rows = $statement->fetchAll();
    return is_array($rows) ? $rows : [];
}
/* === END BLOCK: ORGANIZER_PORTAL_FETCH_CURRENT_SUBMISSIONS_V1 === */

/* === BEGIN BLOCK: ORGANIZER_PORTAL_IMPACT_SUMMARY_V1 | Zweck: liefert sicher zugeordnete Nutzwert-Metriken fuer den eingeloggten Anbieter; Umfang: additive Helfer fuer Dashboard-Wertzentrum === */
function opm_impact_periods(): array
{
    $currentEnd = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $currentStart = $currentEnd->modify('-27 days');
    $previousEnd = $currentStart->modify('-1 day');
    $previousStart = $previousEnd->modify('-27 days');

    return [
        'current' => [
            'start' => $currentStart,
            'end' => $currentEnd,
        ],
        'previous' => [
            'start' => $previousStart,
            'end' => $previousEnd,
        ],
    ];
}

function opm_organizer_reporting_target_id(int $organizerId): string
{
    return 'organizer-' . substr(hash('sha256', 'organizer:' . $organizerId), 0, 16);
}

function opm_empty_impact_metrics(): array
{
    return [
        'website_clicks' => 0,
        'maps_clicks' => 0,
        'location_clicks' => 0,
        'organizer_cta_clicks' => 0,
        'detail_views' => 0,
        'total_interactions' => 0,
        'item_count' => 0,
    ];
}

function opm_value_metrics_table_exists(PDO $pdo): bool
{
    $statement = $pdo->query("SHOW TABLES LIKE 'value_metric_daily'");
    if ($statement === false) {
        return false;
    }

    return $statement->fetchColumn() !== false;
}

function opm_empty_impact_summary(int $organizerId, string $organizationName, string $status = 'ok', string $message = ''): array
{
    $periods = opm_impact_periods();

    return [
        'status' => $status,
        'generated_at' => gmdate('c'),
        'period' => [
            'start_date' => $periods['current']['start']->format('Y-m-d'),
            'end_date' => $periods['current']['end']->format('Y-m-d'),
            'days' => 28,
        ],
        'previous_period' => [
            'start_date' => $periods['previous']['start']->format('Y-m-d'),
            'end_date' => $periods['previous']['end']->format('Y-m-d'),
            'days' => 28,
        ],
        'reporting_target' => [
            'type' => 'organizer',
            'id' => opm_organizer_reporting_target_id($organizerId),
            'title' => $organizationName !== '' ? $organizationName : 'Anbieter',
        ],
        'metrics' => opm_empty_impact_metrics(),
        'previous_metrics' => opm_empty_impact_metrics(),
        'message' => $message,
    ];
}

function opm_fetch_impact_metrics(PDO $pdo, string $targetId, string $startDate, string $endDate): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT
    COALESCE(SUM(CASE WHEN metric_key IN ('event_detail_view', 'activity_detail_view') THEN count_value ELSE 0 END), 0) AS detail_views,
    COALESCE(SUM(CASE WHEN metric_key = 'website_click' THEN count_value ELSE 0 END), 0) AS website_clicks,
    COALESCE(SUM(CASE WHEN metric_key = 'maps_click' THEN count_value ELSE 0 END), 0) AS maps_clicks,
    COALESCE(SUM(CASE WHEN metric_key = 'location_click' THEN count_value ELSE 0 END), 0) AS location_clicks,
    COALESCE(SUM(CASE WHEN metric_key = 'organizer_cta_click' THEN count_value ELSE 0 END), 0) AS organizer_cta_clicks,
    COALESCE(SUM(count_value), 0) AS total_interactions,
    COUNT(DISTINCT CASE WHEN entity_type <> '' AND entity_id <> '' THEN CONCAT(entity_type, ':', entity_id) ELSE NULL END) AS item_count
FROM value_metric_daily
WHERE metric_date BETWEEN :start_date AND :end_date
  AND reporting_target_type = 'organizer'
  AND reporting_target_id = :target_id
SQL);

    $statement->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':target_id' => $targetId,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return opm_empty_impact_metrics();
    }

    return [
        'website_clicks' => (int)($row['website_clicks'] ?? 0),
        'maps_clicks' => (int)($row['maps_clicks'] ?? 0),
        'location_clicks' => (int)($row['location_clicks'] ?? 0),
        'organizer_cta_clicks' => (int)($row['organizer_cta_clicks'] ?? 0),
        'detail_views' => (int)($row['detail_views'] ?? 0),
        'total_interactions' => (int)($row['total_interactions'] ?? 0),
        'item_count' => (int)($row['item_count'] ?? 0),
    ];
}

function opm_fetch_impact_summary(PDO $pdo, int $organizerId, string $organizationName): array
{
    $summary = opm_empty_impact_summary($organizerId, $organizationName);

    if ($organizerId <= 0) {
        return opm_empty_impact_summary($organizerId, $organizationName, 'not_available', 'Kein Anbieter-Kontext verfügbar.');
    }

    if (!opm_value_metrics_table_exists($pdo)) {
        return opm_empty_impact_summary($organizerId, $organizationName, 'not_configured', 'Nutzwert-Messung ist noch nicht initialisiert.');
    }

    $targetId = (string)$summary['reporting_target']['id'];

    $summary['metrics'] = opm_fetch_impact_metrics(
        $pdo,
        $targetId,
        (string)$summary['period']['start_date'],
        (string)$summary['period']['end_date']
    );

    $summary['previous_metrics'] = opm_fetch_impact_metrics(
        $pdo,
        $targetId,
        (string)$summary['previous_period']['start_date'],
        (string)$summary['previous_period']['end_date']
    );

    return $summary;
}
/* === END BLOCK: ORGANIZER_PORTAL_IMPACT_SUMMARY_V1 === */

try {
    $plainSessionToken = opm_read_session_token_from_cookie();
    $sessionTokenHash = opm_hash_token($plainSessionToken);

    $pdo = be_db();
    $sessionRow = opm_fetch_portal_session($pdo, $sessionTokenHash);
    opm_assert_portal_session_usable($sessionRow);
    opm_touch_portal_session($pdo, (int)$sessionRow['portal_session_id']);

    $organizerId = (int)$sessionRow['organizer_id'];
    /* === BEGIN BLOCK: ORGANIZER_PORTAL_MULTI_TARIFF_PAYLOAD_V1 | Zweck: liefert Hauptsubscription weiterhin kompatibel und zusaetzlich alle aktiven Tarife, Tarif-Kontingente und Monatsgesamtsumme; Umfang: ersetzt Subscription-/Quota-Ermittlung im Response-Aufbau === */
    $activeSubscriptions = opm_fetch_active_subscriptions($pdo, $organizerId);
    $activeSubscription = $activeSubscriptions[0] ?? null;
    $quotaSummary = opm_fetch_quota_summary($pdo, $organizerId, $activeSubscription);
    $quotaByPlan = opm_fetch_quota_summary_by_plan($pdo, $organizerId);
    $billingSummary = opm_build_billing_summary($activeSubscriptions);
    /* === END BLOCK: ORGANIZER_PORTAL_MULTI_TARIFF_PAYLOAD_V1 === */
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
            'active_subscriptions' => $activeSubscriptions,
            'quota' => $quotaSummary,
            'quota_by_plan' => $quotaByPlan,
            'billing_summary' => $billingSummary,
            'impact_summary' => $impactSummary,
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
