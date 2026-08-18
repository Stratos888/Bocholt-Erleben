<?php
declare(strict_types=1);

function be_startpartner_gate4_scope_row(array $scopes, string $key): ?array
{
    foreach ($scopes as $scope) {
        if (is_array($scope) && (string)($scope['scope_key'] ?? '') === $key) {
            return $scope;
        }
    }
    return null;
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
    $serviceScopeReady = $contentScopes !== [] && $targetPlanKeys !== [];
    $sourceScope = be_startpartner_gate4_scope_row($scopes, 'automatic-source');
    $maintenanceScope = be_startpartner_gate4_scope_row($scopes, 'maintenance-service');

    $rows = [
        'terms_confirmed' => be_startpartner_gate4_item_row(
            'terms_confirmed',
            $termsReady,
            'Die bestätigten Pilotbedingungen sind hinterlegt. Eine automatische kostenpflichtige Verlängerung ist ausgeschlossen.',
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
            'Der vereinbarte Inhaltsumfang und die möglichen Tarife nach dem Pilot sind hinterlegt.',
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
    ?array $readyDistribution
): array {
    $automatic = array_column(be_startpartner_gate4_automatic_onboarding_items($gate3), null, 'item_key');
    $persisted = array_column(be_startpartner_gate4_required_item_rows($persistedRows), null, 'item_key');

    foreach (BE_STARTPARTNER_GATE4_MANUAL_ONBOARDING_ITEMS as $key) {
        $row = $persisted[$key] ?? be_startpartner_gate4_item_row($key, false, null, null);
        $row['is_manual'] = 1;
        $automatic[$key] = $row;
    }

    $contentReady = is_array($firstContent)
        && in_array((string)($firstContent['status'] ?? ''), ['editorial_ready', 'approved'], true);
    foreach (['first_content_ready', 'editorial_review_ready'] as $key) {
        $automatic[$key] = be_startpartner_gate4_item_row(
            $key,
            $contentReady,
            'Der erste Inhalt kann redaktionell geprüft werden.',
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
        'Der Reichweitenbeitrag ist mit Kanal und Termin vorbereitet.',
        is_array($readyDistribution) ? (string)$readyDistribution['id'] : null
    );

    $result = [];
    foreach (BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key) {
        $row = $automatic[$key] ?? be_startpartner_gate4_item_row($key, false, null, null);
        $row['is_manual'] = be_startpartner_gate4_onboarding_item_is_manual($key) ? 1 : 0;
        $result[] = $row;
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
        $reach
    );
    $onboarding = be_startpartner_gate4_onboarding_readiness($currentRows);

    $blockers = $onboarding['blockers'];
    if ($first === null) {
        $blockers[] = [
            'code' => 'first_content_not_ready',
            'message' => 'Der erste Inhalt ist noch nicht für den Pilotstart vorbereitet.',
        ];
    }
    if ($measurement === null) {
        $blockers[] = [
            'code' => 'measurement_not_ready',
            'message' => 'Die Erfolgsmessung ist noch nicht vollständig eingerichtet.',
        ];
    }
    if ($reach === null) {
        $blockers[] = [
            'code' => 'distribution_not_ready',
            'message' => 'Der Reichweitenbeitrag ist noch nicht mit Kanal und Termin vorbereitet.',
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
