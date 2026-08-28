<?php
declare(strict_types=1);

const BE_STARTPARTNER_GATE4_TERMS_V2 = 'startpartner-pilot-2026-08-v2';
const BE_STARTPARTNER_GATE4_TERMS_REFERENCE_V2 = 'system://startpartner/pilot-terms/startpartner-pilot-2026-08-v2';

function be_startpartner_gate4_scope_row(array $scopes, string $key): ?array
{
    foreach ($scopes as $scope) {
        if (is_array($scope) && (string)($scope['scope_key'] ?? '') === $key) {
            return $scope;
        }
    }
    return null;
}

function be_startpartner_gate4_scope_target_plan_mismatches(array $gate3): array
{
    $pilot = is_array($gate3['pilot'] ?? null) ? $gate3['pilot'] : [];
    $terms = is_array($gate3['terms_acceptance'] ?? null) ? $gate3['terms_acceptance'] : [];
    $serviceScope = is_array($terms['service_scope'] ?? null) ? $terms['service_scope'] : [];
    $desiredScope = trim((string)($serviceScope['desired_content_scope'] ?? ''));
    $expectedScopeKeys = match ($desiredScope) {
        'events' => ['events'],
        'activities' => ['activities'],
        'both' => ['events', 'activities'],
        default => [],
    };
    if ($expectedScopeKeys === []) {
        return [];
    }

    $scopes = array_values(array_filter((array)($gate3['scopes'] ?? []), 'is_array'));
    $targetPlanKeys = is_array($pilot['target_plan_keys'] ?? null)
        ? array_values(array_filter($pilot['target_plan_keys'], static fn(mixed $key): bool => trim((string)$key) !== ''))
        : [];
    $mismatches = [];
    foreach ($expectedScopeKeys as $scopeKey) {
        $expected = be_startpartner_gate3_scope_target_plan_key($scopeKey);
        $scope = be_startpartner_gate4_scope_row($scopes, $scopeKey);
        $actual = is_array($scope) ? trim((string)($scope['target_plan_key'] ?? '')) : '';
        if ($actual !== $expected || !in_array($expected, $targetPlanKeys, true)) {
            $mismatches[] = [
                'scope_key' => $scopeKey,
                'expected_target_plan_key' => $expected,
                'actual_target_plan_key' => $actual !== '' ? $actual : null,
                'pilot_target_plan_present' => in_array($expected, $targetPlanKeys, true),
            ];
        }
    }
    return $mismatches;
}

function be_startpartner_gate4_item_row(
    string $key,
    bool $complete,
    ?string $evidenceText,
    ?string $evidenceReference,
    string $operator = 'authoritative-readback'
): array {
    return [
        'item_key' => $key,
        'status' => $complete ? 'complete' : 'pending',
        'is_required' => 1,
        'is_hard_blocker' => 1,
        'is_manual' => be_startpartner_gate4_onboarding_item_is_manual($key) ? 1 : 0,
        'evidence_text' => $complete ? $evidenceText : null,
        'evidence_reference' => $complete ? $evidenceReference : null,
        'operator_reference' => $complete ? $operator : null,
        'completed_at' => null,
        'revision' => 0,
    ];
}

function be_startpartner_gate4_terms_v2_accepted(array $gate3): bool
{
    $terms = is_array($gate3['terms_acceptance'] ?? null) ? $gate3['terms_acceptance'] : [];
    $channel = trim((string)($terms['confirmation_channel'] ?? ''));
    return (string)($terms['terms_version'] ?? '') === BE_STARTPARTNER_GATE4_TERMS_V2
        && (string)($terms['terms_reference'] ?? '') === BE_STARTPARTNER_GATE4_TERMS_REFERENCE_V2
        && preg_match('/^[0-9a-f]{64}$/', (string)($terms['terms_digest'] ?? '')) === 1
        && trim((string)($terms['accepted_at'] ?? '')) !== ''
        && in_array($channel, ['email_reply', 'signed_document', 'portal'], true)
        && (int)($terms['no_automatic_paid_renewal'] ?? 0) === 1;
}

function be_startpartner_gate4_portal_access_readback(PDO $pdo, array $gate3): ?array
{
    $pilot = is_array($gate3['pilot'] ?? null) ? $gate3['pilot'] : [];
    $terms = is_array($gate3['terms_acceptance'] ?? null) ? $gate3['terms_acceptance'] : [];
    $organizerId = (int)($pilot['organizer_id'] ?? 0);
    $email = strtolower(trim((string)($pilot['partner_contact_email_snapshot'] ?? '')));
    $acceptedAt = trim((string)($terms['accepted_at'] ?? ''));
    if ($organizerId < 1 || $email === '' || $acceptedAt === '') {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT s.id AS portal_session_id, s.created_at AS session_created_at,
                ml.id AS magic_link_id, ml.consumed_at, ml.email_snapshot
         FROM organizer_portal_sessions s
         INNER JOIN organizer_magic_links ml ON ml.id = s.issued_from_magic_link_id
         WHERE s.organizer_id = :organizer_id
           AND ml.organizer_id = s.organizer_id
           AND s.revoked_at IS NULL
           AND ml.revoked_at IS NULL
           AND ml.intended_action = 'portal_login'
           AND ml.consumed_at IS NOT NULL
           AND ml.consumed_at >= :accepted_at_magic_link
           AND s.created_at >= :accepted_at_session
           AND LOWER(TRIM(ml.email_snapshot)) = :email
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT 1"
    );
    $statement->execute([
        'organizer_id' => $organizerId,
        'accepted_at_magic_link' => $acceptedAt,
        'accepted_at_session' => $acceptedAt,
        'email' => $email,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function be_startpartner_gate4_automatic_onboarding_items(array $gate3, string $operator = 'gate3-readback'): array
{
    $pilot = $gate3['pilot'] ?? null;
    $terms = $gate3['terms_acceptance'] ?? null;
    $organizer = $gate3['organizer'] ?? null;
    $entitlement = $gate3['entitlement'] ?? null;
    $scopes = array_values(array_filter((array)($gate3['scopes'] ?? []), 'is_array'));

    $termsReady = is_array($terms)
        && (int)($terms['id'] ?? 0) > 0
        && trim((string)($terms['accepted_at'] ?? '')) !== ''
        && (int)($terms['no_automatic_paid_renewal'] ?? 0) === 1;
    $organizerReady = is_array($organizer) && (int)($organizer['id'] ?? 0) > 0;
    $contactReference = is_array($pilot) ? trim((string)($pilot['partner_contact_email_snapshot'] ?? '')) : '';
    $entitlementReady = is_array($entitlement)
        && trim((string)($entitlement['id'] ?? '')) !== ''
        && in_array((string)($entitlement['status'] ?? ''), ['pending_activation', 'active', 'paused', 'ended', 'revoked'], true);
    $contentScopes = array_values(array_filter(
        $scopes,
        static fn(array $scope): bool => in_array((string)($scope['scope_key'] ?? ''), ['events', 'activities'], true)
    ));
    $targetPlanKeys = is_array($pilot) && is_array($pilot['target_plan_keys'] ?? null)
        ? array_values(array_filter($pilot['target_plan_keys'], static fn(mixed $key): bool => trim((string)$key) !== ''))
        : [];
    $scopeTargetPlanMismatches = be_startpartner_gate4_scope_target_plan_mismatches($gate3);
    $serviceScopeReady = $contentScopes !== [] && $targetPlanKeys !== [] && $scopeTargetPlanMismatches === [];
    $sourceScope = be_startpartner_gate4_scope_row($scopes, 'automatic-source');
    $maintenanceScope = be_startpartner_gate4_scope_row($scopes, 'maintenance-service');

    $rows = [
        'terms_confirmed' => be_startpartner_gate4_item_row(
            'terms_confirmed',
            $termsReady,
            'Die ausdrücklich bestätigten Pilotbedingungen sind gebunden hinterlegt. Eine automatische kostenpflichtige Verlängerung ist ausgeschlossen.',
            $termsReady ? (string)$terms['id'] : null,
            $operator
        ),
        'organizer_linked' => be_startpartner_gate4_item_row(
            'organizer_linked',
            $organizerReady,
            'Der Pilot ist einem eindeutigen Veranstalterzugang zugeordnet.',
            $organizerReady ? (string)$organizer['id'] : null,
            $operator
        ),
        'contact_confirmed' => be_startpartner_gate4_item_row(
            'contact_confirmed',
            $contactReference !== '',
            'Die bestätigte Ansprechperson ist für den Pilot hinterlegt.',
            $contactReference !== '' ? $contactReference : null,
            $operator
        ),
        'pilot_entitlement_readback' => be_startpartner_gate4_item_row(
            'pilot_entitlement_readback',
            $entitlementReady,
            'Die Pilotfreigabe ist angelegt und hat einen zulässigen Stand.',
            $entitlementReady ? (string)$entitlement['id'] : null,
            $operator
        ),
        'service_scope_confirmed' => be_startpartner_gate4_item_row(
            'service_scope_confirmed',
            $serviceScopeReady,
            'Der vereinbarte Inhaltsumfang und die Zielmodelle sind konsistent hinterlegt.',
            $serviceScopeReady ? 'gate3-scopes' : null,
            $operator
        ),
        'sources_recorded' => be_startpartner_gate4_item_row(
            'sources_recorded',
            is_array($sourceScope),
            'Die vereinbarten Inhaltsquellen sind hinterlegt.',
            is_array($sourceScope) ? 'automatic-source' : null,
            $operator
        ),
        'maintenance_path_agreed' => be_startpartner_gate4_item_row(
            'maintenance_path_agreed',
            is_array($maintenanceScope),
            'Die laufende Pflege und der Änderungsweg sind vereinbart.',
            is_array($maintenanceScope) ? 'maintenance-service' : null,
            $operator
        ),
    ];

    $result = [];
    foreach (BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key) {
        $result[] = $rows[$key] ?? be_startpartner_gate4_item_row($key, false, null, null, $operator);
    }
    return $result;
}

function be_startpartner_gate4_seed_onboarding_items(PDO $pdo, array $gate3, string $operator): void
{
    $pilot = $gate3['pilot'] ?? null;
    if (!is_array($pilot)) {
        throw new DomainException('Gate-3 pilot is required.');
    }
    $preview = array_column(be_startpartner_gate4_automatic_onboarding_items($gate3, $operator), null, 'item_key');
    $statement = $pdo->prepare(
        "INSERT INTO startpartner_pilot_onboarding_items (
            pilot_id,item_key,status,is_required,is_hard_blocker,evidence_text,
            evidence_reference,operator_reference,completed_at
         ) VALUES (
            :pilot_id,:item_key,:status,1,1,:evidence_text,
            :evidence_reference,:operator_reference,:completed_at
         )
         ON DUPLICATE KEY UPDATE item_key=VALUES(item_key)"
    );
    foreach (BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key) {
        $row = $preview[$key];
        $statement->execute([
            'pilot_id' => (string)$pilot['id'],
            'item_key' => $key,
            'status' => $row['status'],
            'evidence_text' => $row['evidence_text'],
            'evidence_reference' => $row['evidence_reference'],
            'operator_reference' => $row['operator_reference'],
            'completed_at' => $row['status'] === 'complete' ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }
}

function be_startpartner_gate4_onboarding_rows(PDO $pdo, string $pilotId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_onboarding_items WHERE pilot_id=:pilot_id ORDER BY id'
    );
    $statement->execute(['pilot_id' => $pilotId]);
    return be_startpartner_gate4_required_item_rows($statement->fetchAll(PDO::FETCH_ASSOC));
}

function be_startpartner_gate4_content_rows(PDO $pdo, string $pilotId): array
{
    $statement = $pdo->prepare(
        'SELECT pcl.*,s.status AS submission_status,s.title,s.start_date,s.location_name,
                s.payment_kind,s.requested_model_key,s.approved_at AS submission_approved_at
         FROM startpartner_pilot_content_links pcl
         INNER JOIN submissions s ON s.id=pcl.submission_id
         WHERE pcl.pilot_id=:pilot_id
         ORDER BY pcl.created_at,pcl.id'
    );
    $statement->execute(['pilot_id' => $pilotId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function be_startpartner_gate4_measurement_rows(PDO $pdo, string $pilotId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_measurement_preflights
         WHERE pilot_id=:pilot_id ORDER BY checked_at DESC,id DESC'
    );
    $statement->execute(['pilot_id' => $pilotId]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['evidence'] = json_decode((string)$row['evidence_json'], true);
        unset($row['evidence_json']);
    }
    unset($row);
    return $rows;
}

function be_startpartner_gate4_distribution_rows(PDO $pdo, string $pilotId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_distribution_commitments
         WHERE pilot_id=:pilot_id ORDER BY created_at DESC,id DESC'
    );
    $statement->execute(['pilot_id' => $pilotId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function be_startpartner_gate4_usage_rows(PDO $pdo, string $pilotId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_usages WHERE pilot_id=:pilot_id ORDER BY id'
    );
    $statement->execute(['pilot_id' => $pilotId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function be_startpartner_gate4_checkpoint_readback(PDO $pdo, array $pilot): array
{
    $activationDate = trim((string)($pilot['activation_date_local'] ?? ''));
    $plannedEndDate = trim((string)($pilot['planned_end_date'] ?? ''));
    if ($activationDate === '' || $plannedEndDate === '') {
        return [
            'items' => [],
            'next_review_at' => null,
            'closeout_required' => false,
        ];
    }
    $schedule = be_startpartner_gate4_checkpoint_schedule($activationDate, $plannedEndDate);
    $statement = $pdo->prepare(
        "SELECT id, actor_reference, payload_json, created_at
         FROM startpartner_pilot_events
         WHERE pilot_id = :pilot_id AND event_type = 'gate4_checkpoint_completed'
         ORDER BY id"
    );
    $statement->execute(['pilot_id' => (string)$pilot['id']]);
    $completed = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $payload = json_decode((string)$event['payload_json'], true);
        $key = is_array($payload) ? (string)($payload['checkpoint_key'] ?? '') : '';
        if (in_array($key, BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS, true)) {
            $completed[$key] = [
                'event_id' => (int)$event['id'],
                'actor_reference' => (string)$event['actor_reference'],
                'created_at' => $event['created_at'],
                'payload' => $payload,
            ];
        }
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $items = [];
    $nextReviewAt = null;
    foreach (BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS as $key) {
        $row = $schedule[$key];
        $isComplete = isset($completed[$key]);
        $status = $isComplete ? 'completed' : ($today >= $row['due_date_local'] ? 'due' : 'upcoming');
        $items[] = [
            'checkpoint_key' => $key,
            'due_date_local' => $row['due_date_local'],
            'deadline_date_local' => $row['deadline_date_local'],
            'status' => $status,
            'completed' => $completed[$key] ?? null,
        ];
        if (!$isComplete && ($nextReviewAt === null || $row['due_date_local'] < $nextReviewAt)) {
            $nextReviewAt = $row['due_date_local'];
        }
    }
    $terminal = in_array((string)$pilot['status'], BE_STARTPARTNER_GATE4_TERMINAL_PILOT_STATUSES, true);
    $closeoutRequired = !$terminal
        && in_array((string)$pilot['status'], ['active', 'paused'], true)
        && $today >= $plannedEndDate;
    return [
        'items' => $items,
        'next_review_at' => $terminal ? null : ($nextReviewAt ?? $plannedEndDate),
        'closeout_required' => $closeoutRequired,
        'today_local' => $today,
    ];
}

function be_startpartner_gate4_limit_readback(
    array $pilot,
    ?array $entitlement,
    array $scopes,
    array $content,
    array $usages
): array {
    $eventScope = be_startpartner_gate4_scope_row($scopes, 'events');
    $activityScope = be_startpartner_gate4_scope_row($scopes, 'activities');
    $activationDate = trim((string)($pilot['activation_date_local'] ?? ''));
    $plannedEndDate = trim((string)($pilot['planned_end_date'] ?? ''));
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $monthIndex = null;
    $monthWindow = null;
    if ($activationDate !== '' && $plannedEndDate !== '') {
        $monthIndex = be_startpartner_gate4_pilot_month_index($activationDate, $today, $plannedEndDate);
        if ($monthIndex !== null) {
            $monthWindow = be_startpartner_gate4_pilot_month_window($activationDate, $monthIndex, $plannedEndDate);
        }
    }

    $eventUsed = 0;
    if ($monthIndex !== null) {
        foreach ($usages as $usage) {
            if ((string)($usage['content_type'] ?? '') === 'event'
                && (int)($usage['pilot_month_index'] ?? 0) === $monthIndex) {
                $eventUsed += (int)($usage['units'] ?? 0);
            }
        }
    }
    $eventUnlimited = is_array($entitlement) && (int)($entitlement['is_event_unlimited'] ?? 0) === 1;
    $eventLimit = is_array($entitlement) && $entitlement['event_limit_per_pilot_month'] !== null
        ? (int)$entitlement['event_limit_per_pilot_month']
        : null;
    $activityUsed = 0;
    foreach ($content as $row) {
        if ((string)($row['content_type'] ?? '') === 'activity' && (string)($row['status'] ?? '') === 'approved') {
            $activityUsed++;
        }
    }
    $activityLimit = is_array($entitlement) && $entitlement['activity_concurrent_limit'] !== null
        ? (int)$entitlement['activity_concurrent_limit']
        : null;

    return [
        'event' => is_array($eventScope) ? [
            'available' => true,
            'scope_status' => (string)$eventScope['status'],
            'pilot_month_index' => $monthIndex,
            'used' => $eventUsed,
            'limit' => $eventLimit,
            'is_unlimited' => $eventUnlimited,
            'full' => !$eventUnlimited && $eventLimit !== null && $eventUsed >= $eventLimit,
            'reset_date_local' => is_array($monthWindow) ? ($monthWindow['next_start_date_local'] ?? null) : null,
        ] : ['available' => false],
        'activity' => is_array($activityScope) ? [
            'available' => true,
            'scope_status' => (string)$activityScope['status'],
            'used' => $activityUsed,
            'limit' => $activityLimit,
            'is_unlimited' => false,
            'full' => $activityLimit !== null && $activityUsed >= $activityLimit,
        ] : ['available' => false],
    ];
}

function be_startpartner_gate4_measurement_runtime_readback(PDO $pdo, array $pilot, ?array $readyMeasurement): array
{
    if (!is_array($readyMeasurement)) {
        return [
            'status' => 'technical_not_ready',
            'query_status' => 'not_run',
            'observed_actions' => null,
            'completed_bucket_count' => 0,
        ];
    }
    $targetType = trim((string)($readyMeasurement['reporting_target_type'] ?? ''));
    $targetId = trim((string)($readyMeasurement['reporting_target_id'] ?? ''));
    $expectedTargetId = be_startpartner_gate4_reporting_target_id((int)$pilot['organizer_id']);
    if ($targetType !== 'organizer' || !hash_equals($expectedTargetId, $targetId)) {
        return [
            'status' => 'query_or_attribution_problem',
            'query_status' => 'attribution_mismatch',
            'observed_actions' => null,
            'completed_bucket_count' => 0,
        ];
    }
    try {
        $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $statement = $pdo->prepare(
            "SELECT COUNT(*) AS bucket_count,
                    COALESCE(SUM(count_value), 0) AS observed_actions,
                    MAX(metric_date) AS last_completed_metric_date,
                    MAX(updated_at) AS last_metric_update
             FROM value_metric_daily
             WHERE reporting_target_type = :target_type
               AND reporting_target_id = :target_id
               AND metric_date < :today_local"
        );
        $statement->execute([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'today_local' => $today,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Measurement query returned no aggregate row.');
        }
        $bucketCount = (int)($row['bucket_count'] ?? 0);
        $observedActions = (int)($row['observed_actions'] ?? 0);
        $status = $bucketCount === 0
            ? 'no_data_yet_or_too_short'
            : ($observedActions > 0 ? 'usage_observed' : 'zero_usage');
        return [
            'status' => $status,
            'query_status' => 'ok',
            'observed_actions' => $observedActions,
            'completed_bucket_count' => $bucketCount,
            'last_completed_metric_date' => $row['last_completed_metric_date'] ?? null,
            'last_metric_update' => $row['last_metric_update'] ?? null,
            'reporting_target_type' => $targetType,
            'reporting_target_id' => $targetId,
        ];
    } catch (Throwable $error) {
        return [
            'status' => 'query_or_attribution_problem',
            'query_status' => 'error',
            'observed_actions' => null,
            'completed_bucket_count' => 0,
            'error_message' => $error->getMessage(),
        ];
    }
}

function be_startpartner_gate4_distribution_runtime_readback(array $distribution): array
{
    $current = $distribution[0] ?? null;
    if (!is_array($current)) {
        return ['status' => 'not_planned', 'commitment' => null];
    }
    $status = (string)$current['status'];
    if ($status === 'ready') {
        $plannedAt = trim((string)($current['planned_at'] ?? ''));
        $due = false;
        if ($plannedAt !== '') {
            $planned = new DateTimeImmutable($plannedAt, new DateTimeZone('UTC'));
            $due = $planned <= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        return [
            'status' => $due ? 'due' : 'planned',
            'commitment' => $current,
        ];
    }
    return [
        'status' => in_array($status, ['completed', 'blocked', 'cancelled'], true) ? $status : 'not_ready',
        'commitment' => $current,
    ];
}

function be_startpartner_gate4_current_onboarding_items(
    array $gate3,
    array $persistedRows,
    ?array $firstContent,
    ?array $readyMeasurement,
    ?array $readyDistribution,
    ?array $portalAccess = null
): array {
    $automatic = array_column(be_startpartner_gate4_automatic_onboarding_items($gate3), null, 'item_key');
    $persisted = array_column(be_startpartner_gate4_required_item_rows($persistedRows), null, 'item_key');

    foreach (BE_STARTPARTNER_GATE4_MANUAL_ONBOARDING_ITEMS as $key) {
        $row = $persisted[$key] ?? be_startpartner_gate4_item_row($key, false, null, null);
        $row['is_manual'] = 1;
        $automatic[$key] = $row;
    }

    if (be_startpartner_gate4_terms_v2_accepted($gate3)) {
        $rights = be_startpartner_gate4_item_row(
            'content_rights_cleared',
            true,
            'Die Nutzungsfreigabe für vom Partner bereitgestellte Texte und Bilder ist Bestandteil der ausdrücklich bestätigten Pilotbedingungen.',
            BE_STARTPARTNER_GATE4_TERMS_V2,
            'terms-readback'
        );
        $rights['is_manual'] = 0;
        $automatic['content_rights_cleared'] = $rights;

        $portal = be_startpartner_gate4_item_row(
            'portal_access_tested',
            is_array($portalAccess),
            'Der Partner hat nach der Bestätigung einen gebundenen Zugangslink eingelöst und eine Veranstalter-Portal-Session erzeugt.',
            is_array($portalAccess) ? 'portal-session:' . (string)$portalAccess['portal_session_id'] : null,
            'portal-session-readback'
        );
        $portal['is_manual'] = 0;
        $automatic['portal_access_tested'] = $portal;

        $activationTarget = be_startpartner_gate4_item_row(
            'activation_target_set',
            true,
            'Das lokale Startdatum wird bei der ausdrücklichen Aktion „Pilot jetzt starten“ festgelegt; ein vorgelagerter Datums-Pflegeschritt ist nicht erforderlich.',
            'activation-action-date',
            'activation-contract'
        );
        $activationTarget['is_manual'] = 0;
        $automatic['activation_target_set'] = $activationTarget;
    }

    $contentReady = is_array($firstContent)
        && in_array((string)($firstContent['status'] ?? ''), ['editorial_ready', 'approved'], true);
    foreach (['first_content_ready', 'editorial_review_ready'] as $key) {
        $automatic[$key] = be_startpartner_gate4_item_row(
            $key,
            $contentReady,
            'Der erste Inhalt ist redaktionell für den Pilotstart vorbereitet.',
            $contentReady ? (string)$firstContent['id'] : null
        );
    }
    $automatic['measurement_ready'] = be_startpartner_gate4_item_row(
        'measurement_ready',
        is_array($readyMeasurement),
        'Die Erfolgsmessung ist dem Veranstalter und dem ersten Inhalt richtig zugeordnet.',
        is_array($readyMeasurement) ? (string)$readyMeasurement['id'] : null
    );
    $automatic['distribution_ready'] = be_startpartner_gate4_item_row(
        'distribution_ready',
        is_array($readyDistribution),
        'Der Reichweitenbeitrag ist mit dem Partner vereinbart und mit Kanal und Zieltermin vorbereitet.',
        is_array($readyDistribution) ? (string)$readyDistribution['id'] : null
    );

    $result = [];
    foreach (BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key) {
        $result[] = $automatic[$key] ?? be_startpartner_gate4_item_row($key, false, null, null);
    }
    return $result;
}
function be_startpartner_gate4_next_action(
    array $pilot,
    bool $ready,
    array $checkpoints,
    array $content,
    array $measurementRuntime,
    array $distributionRuntime
): array {
    $status = (string)$pilot['status'];
    if (in_array($status, BE_STARTPARTNER_GATE4_TERMINAL_PILOT_STATUSES, true)) {
        return ['code' => 'none', 'label' => 'Pilot abgeschlossen', 'action' => null];
    }
    if ($status === 'closing') {
        return [
            'code' => 'end_without_conversion',
            'label' => 'Pilot geordnet abschließen',
            'action' => 'end_without_conversion',
        ];
    }
    if (($checkpoints['closeout_required'] ?? false) === true) {
        return [
            'code' => 'closeout_required',
            'label' => 'Pilotende jetzt entscheiden',
            'action' => 'start_closeout',
        ];
    }
    foreach ((array)($checkpoints['items'] ?? []) as $checkpoint) {
        if ((string)($checkpoint['status'] ?? '') === 'due') {
            return [
                'code' => 'checkpoint_due',
                'label' => 'Fälligen Pilot-Checkpoint abschließen',
                'action' => 'complete_checkpoint',
                'checkpoint_key' => (string)$checkpoint['checkpoint_key'],
                'due_date_local' => (string)$checkpoint['due_date_local'],
            ];
        }
    }
    if ($status === 'paused') {
        return [
            'code' => 'paused',
            'label' => 'Pilot fortsetzen oder Abschluss einleiten',
            'action' => 'resume',
        ];
    }
    if ($ready) {
        return ['code' => 'activate', 'label' => 'Pilot jetzt starten', 'action' => 'activate'];
    }
    if ($status === 'active') {
        if (in_array((string)($measurementRuntime['status'] ?? ''), ['query_or_attribution_problem', 'technical_not_ready'], true)) {
            return [
                'code' => 'measurement_problem',
                'label' => 'Technische Erfolgsmessung prüfen',
                'action' => 'measurement',
            ];
        }
        if (in_array((string)($distributionRuntime['status'] ?? ''), ['due', 'blocked'], true)
            && trim((string)($distributionRuntime['commitment']['id'] ?? '')) !== '') {
            $distributionStatus = (string)$distributionRuntime['status'];
            return [
                'code' => $distributionStatus === 'blocked' ? 'distribution_blocked' : 'distribution_due',
                'label' => $distributionStatus === 'blocked'
                    ? 'Blockierten Reichweitenbeitrag klären'
                    : 'Fälligen Reichweitenbeitrag dokumentieren',
                'action' => 'set_distribution_fulfillment',
                'distribution_id' => (string)$distributionRuntime['commitment']['id'],
            ];
        }
        foreach ($content as $row) {
            if ((string)($row['status'] ?? '') === 'draft') {
                return [
                    'code' => 'content_review',
                    'label' => 'Nächsten Pilotinhalt redaktionell prüfen',
                    'action' => 'mark_content_ready',
                    'content_link_id' => (string)$row['id'],
                ];
            }
            if ((string)($row['status'] ?? '') === 'editorial_ready') {
                return [
                    'code' => 'content_approval',
                    'label' => 'Vorbereiteten Pilotinhalt freigeben',
                    'action' => 'approve_content',
                    'content_link_id' => (string)$row['id'],
                ];
            }
        }
        return [
            'code' => 'monitor_active_pilot',
            'label' => 'Aktiven Pilot beobachten',
            'action' => null,
        ];
    }
    return [
        'code' => 'onboarding',
        'label' => 'Nächsten offenen Einrichtungsschritt bearbeiten',
        'action' => null,
    ];
}

function be_startpartner_gate4_state(PDO $pdo, string $candidateId, bool $includeEvents = true): array
{
    be_startpartner_gate4_require_schema($pdo);
    $gate3 = be_startpartner_gate3_state($pdo, $candidateId, $includeEvents);
    $pilot = $gate3['pilot'] ?? null;
    if (!is_array($pilot)) {
        return [
            'complete' => false,
            'phase' => 'gate3_required',
            'pilot' => null,
            'onboarding' => be_startpartner_gate4_onboarding_readiness([]),
            'content_links' => [],
            'measurement_preflights' => [],
            'distribution_commitments' => [],
            'usages' => [],
            'first_content' => null,
            'activation_ready' => false,
            'active' => false,
            'effective_active' => false,
            'lifecycle' => ['status' => 'gate3_required'],
            'limits' => [],
            'measurement_runtime' => ['status' => 'technical_not_ready'],
            'distribution_runtime' => ['status' => 'not_planned'],
            'next_review_at' => null,
            'next_action' => ['code' => 'gate3_required', 'action' => null],
            'blockers' => [[
                'code' => 'gate3_pilot_required',
                'message' => 'Pilotbedingungen und Veranstalterzugang müssen vor der Piloteinrichtung vollständig vorbereitet sein.',
            ]],
        ];
    }

    $pilotId = (string)$pilot['id'];
    $persistedRows = be_startpartner_gate4_onboarding_rows($pdo, $pilotId);
    $content = be_startpartner_gate4_content_rows($pdo, $pilotId);
    $measurements = be_startpartner_gate4_measurement_rows($pdo, $pilotId);
    $distribution = be_startpartner_gate4_distribution_rows($pdo, $pilotId);
    $usages = be_startpartner_gate4_usage_rows($pdo, $pilotId);
    $portalAccess = be_startpartner_gate4_portal_access_readback($pdo, $gate3);

    $first = null;
    foreach ($content as $row) {
        if (in_array((string)$row['status'], ['editorial_ready', 'approved'], true)) {
            $first = $row;
            break;
        }
    }

    $measurement = null;
    foreach ($measurements as $row) {
        if (
            (string)$row['status'] === 'ready'
            && ($first === null || (string)$row['content_link_id'] === (string)$first['id'])
        ) {
            $measurement = $row;
            break;
        }
    }

    $reach = null;
    foreach ($distribution as $row) {
        if (in_array((string)$row['status'], ['ready', 'completed'], true)) {
            $reach = $row;
            break;
        }
    }

    $currentRows = be_startpartner_gate4_current_onboarding_items(
        $gate3,
        $persistedRows,
        $first,
        $measurement,
        $reach,
        $portalAccess
    );
    $onboarding = be_startpartner_gate4_onboarding_readiness($currentRows);

    $blockers = $onboarding['blockers'];
    foreach (be_startpartner_gate4_scope_target_plan_mismatches($gate3) as $mismatch) {
        $blockers[] = [
            'code' => 'scope_target_plan_mismatch',
            'scope_key' => $mismatch['scope_key'],
            'expected_target_plan_key' => $mismatch['expected_target_plan_key'],
            'actual_target_plan_key' => $mismatch['actual_target_plan_key'],
            'message' => sprintf(
                'Die Zielmodell-Zuordnung für %s ist inkonsistent und muss vor dem Pilotstart repariert werden.',
                (string)$mismatch['scope_key']
            ),
        ];
    }
    if ($first === null) {
        $blockers[] = [
            'code' => 'first_content_not_ready',
            'message' => 'Der erste Inhalt ist noch nicht für den Pilotstart vorbereitet.',
        ];
    }
    if ($measurement === null) {
        $blockers[] = [
            'code' => 'measurement_not_ready',
            'message' => 'Die technische Erfolgsmessung ist noch nicht erfolgreich geprüft.',
        ];
    }
    if ($reach === null) {
        $blockers[] = [
            'code' => 'distribution_not_ready',
            'message' => 'Der Reichweitenbeitrag ist noch nicht mit dem Partner vereinbart und mit Kanal und Zieltermin vorbereitet.',
        ];
    }
    $entitlement = $gate3['entitlement'] ?? null;
    if (!is_array($entitlement)) {
        $blockers[] = [
            'code' => 'pilot_entitlement_missing',
            'message' => 'Die Pilotfreigabe fehlt.',
        ];
    }

    $status = (string)$pilot['status'];
    $terminal = in_array($status, BE_STARTPARTNER_GATE4_TERMINAL_PILOT_STATUSES, true);
    $checkpoints = be_startpartner_gate4_checkpoint_readback($pdo, $pilot);
    $limits = be_startpartner_gate4_limit_readback(
        $pilot,
        is_array($entitlement) ? $entitlement : null,
        (array)($gate3['scopes'] ?? []),
        $content,
        $usages
    );
    $measurementRuntime = be_startpartner_gate4_measurement_runtime_readback($pdo, $pilot, $measurement);
    $distributionRuntime = be_startpartner_gate4_distribution_runtime_readback($distribution);

    $insideWindow = false;
    $activationDate = trim((string)($pilot['activation_date_local'] ?? ''));
    $plannedEndDate = trim((string)($pilot['planned_end_date'] ?? ''));
    if ($activationDate !== '' && $plannedEndDate !== '') {
        $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $insideWindow = be_startpartner_gate4_pilot_month_index($activationDate, $today, $plannedEndDate) !== null;
    }

    $effectiveActive = $status === 'active'
        && is_array($entitlement)
        && (string)$entitlement['status'] === 'active'
        && $insideWindow
        && $first !== null
        && (string)$first['status'] === 'approved';
    $ready = !in_array($status, ['active', 'paused', 'closing'], true)
        && !$terminal
        && in_array($status, ['onboarding', 'activation_ready'], true)
        && $blockers === []
        && is_array($entitlement)
        && (string)$entitlement['status'] === 'pending_activation';
    $phase = $terminal || in_array($status, ['active', 'paused', 'closing'], true)
        ? $status
        : ($ready ? 'activation_ready' : 'onboarding');
    $nextAction = be_startpartner_gate4_next_action(
        $pilot,
        $ready,
        $checkpoints,
        $content,
        $measurementRuntime,
        $distributionRuntime
    );

    return [
        'complete' => $effectiveActive || $terminal,
        'phase' => $phase,
        'pilot' => $pilot,
        'onboarding' => $onboarding,
        'content_links' => $content,
        'measurement_preflights' => $measurements,
        'distribution_commitments' => $distribution,
        'usages' => $usages,
        'first_content' => $first,
        'ready_measurement' => $measurement,
        'ready_distribution' => $reach,
        'portal_access' => $portalAccess,
        'activation_ready' => $ready,
        'active' => $effectiveActive,
        'effective_active' => $effectiveActive,
        'lifecycle' => [
            'status' => $status,
            'entitlement_status' => is_array($entitlement) ? (string)$entitlement['status'] : null,
            'inside_effective_window' => $insideWindow,
            'terminal' => $terminal,
            'checkpoints' => $checkpoints['items'],
            'closeout_required' => (bool)($checkpoints['closeout_required'] ?? false),
        ],
        'limits' => $limits,
        'measurement_runtime' => $measurementRuntime,
        'distribution_runtime' => $distributionRuntime,
        'next_review_at' => $checkpoints['next_review_at'] ?? null,
        'next_action' => $nextAction,
        'blockers' => $blockers,
        'capacity' => be_startpartner_gate4_capacity($pdo),
    ];
}

function be_startpartner_gate4_candidate_detail(PDO $pdo, string $candidateId, bool $includeEvents = true): array
{
    $candidate = be_startpartner_gate3_candidate_detail($pdo, $candidateId, $includeEvents);
    $candidate['gate4'] = be_startpartner_gate4_state($pdo, $candidateId, $includeEvents);
    if (is_array($candidate['gate4']['pilot'] ?? null)) {
        $candidate['gate3']['complete'] = true;
        $candidate['gate3']['blockers'] = [];
    }
    $candidate['capacity'] = $candidate['gate4']['capacity'];
    return $candidate;
}