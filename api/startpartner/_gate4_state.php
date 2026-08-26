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
           AND ml.consumed_at >= :accepted_at
           AND s.created_at >= :accepted_at
           AND LOWER(TRIM(ml.email_snapshot)) = :email
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT 1"
    );
    $statement->execute([
        'organizer_id' => $organizerId,
        'accepted_at' => $acceptedAt,
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
        && in_array((string)($entitlement['status'] ?? ''), ['pending_activation', 'active'], true);
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
    $active = $status === 'active'
        && is_array($entitlement)
        && (string)$entitlement['status'] === 'active'
        && $first !== null
        && (string)$first['status'] === 'approved';
    $ready = !$active
        && in_array($status, ['onboarding', 'activation_ready'], true)
        && $blockers === []
        && is_array($entitlement)
        && (string)$entitlement['status'] === 'pending_activation';

    return [
        'complete' => $active,
        'phase' => $active ? 'active' : ($ready ? 'activation_ready' : 'onboarding'),
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
        'active' => $active,
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
