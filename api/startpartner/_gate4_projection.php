<?php
declare(strict_types=1);

function be_startpartner_gate4_project_control_case(PDO $pdo, array $candidate, array $gate4, string $actor): void
{
    $candidateId = (string)$candidate['id'];
    $phase = (string)($gate4['phase'] ?? 'onboarding');
    $firstBlocker = (string)($gate4['blockers'][0]['message'] ?? 'Onboarding prüfen.');
    $nextAction = match ($phase) {
        'activation_ready' => 'Pilot mit erstem Inhalt aktivieren.',
        'active' => 'Aktiven Pilotstatus und erste Wirkung beobachten.',
        default => $firstBlocker,
    };
    $reason = match ($phase) {
        'activation_ready' => 'Alle Aktivierungsbedingungen sind belegt.',
        'active' => 'Pilot, Berechtigung und erster Inhalt sind aktiv.',
        default => $firstBlocker,
    };
    $payload = json_encode([
        'candidate_id' => $candidateId,
        'candidate_status' => $candidate['status'],
        'candidate_revision' => (int)$candidate['revision'],
        'candidate_source' => $candidate['source'],
        'desired_content_scope' => $candidate['desired_content_scope'],
        'assigned_to' => $candidate['assigned_to'] ?? null,
        'next_review_at' => $candidate['next_review_at'] ?? null,
        'readiness' => $candidate['readiness'],
        'capacity' => $gate4['capacity'],
        'gate3' => $candidate['gate3'],
        'gate4' => $gate4,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $select = $pdo->prepare(
        "SELECT id, state FROM control_cases
         WHERE source_system = 'startpartner_candidate' AND source_reference = :reference
         FOR UPDATE"
    );
    $select->execute(['reference' => $candidateId]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);
    if (!is_array($existing)) {
        be_startpartner_gate3_project_control_case(
            $pdo,
            be_startpartner_gate2_candidate_row($pdo, $candidateId),
            $candidate['readiness'],
            $gate4['capacity'],
            $candidate['gate3'],
            $actor
        );
        $select->execute(['reference' => $candidateId]);
        $existing = $select->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($existing)) {
        throw new RuntimeException('Control-case projection is missing.');
    }
    $statement = $pdo->prepare(
        'UPDATE control_cases
         SET state = :state, priority = :priority, title = :title, reason = :reason,
             next_action = :next_action, source_payload_json = :payload,
             decision_ready = :decision_ready, completed_at = NULL, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $statement->execute([
        'state' => 'in_progress',
        'priority' => $phase === 'activation_ready' ? 'high' : 'normal',
        'title' => 'Startpartner-Pilot: ' . (string)$candidate['organization_name'],
        'reason' => $reason,
        'next_action' => $nextAction,
        'payload' => $payload,
        'decision_ready' => $phase === 'activation_ready' ? 1 : 0,
        'id' => (string)$existing['id'],
    ]);
    be_cc_record_event(
        $pdo,
        (string)$existing['id'],
        'startpartner_gate4_sync',
        (string)$existing['state'],
        'in_progress',
        ['candidate_revision' => (int)$candidate['revision'], 'phase' => $phase],
        $actor
    );
}

function be_startpartner_gate4_sync_activation_ready(PDO $pdo, string $candidateId): void
{
    $gate4 = be_startpartner_gate4_state($pdo, $candidateId, false);
    $pilot = $gate4['pilot'];
    if (!is_array($pilot)) {
        return;
    }
    $status = (string)$pilot['status'];
    if ($gate4['activation_ready'] && $status === 'onboarding') {
        $statement = $pdo->prepare(
            "UPDATE startpartner_pilots
             SET status = 'activation_ready', activation_ready_at = COALESCE(activation_ready_at, CURRENT_TIMESTAMP)
             WHERE id = :id"
        );
        $statement->execute(['id' => (string)$pilot['id']]);
    } elseif (!$gate4['activation_ready'] && $status === 'activation_ready') {
        $statement = $pdo->prepare(
            "UPDATE startpartner_pilots SET status = 'onboarding', activation_ready_at = NULL WHERE id = :id"
        );
        $statement->execute(['id' => (string)$pilot['id']]);
    }
}

function be_startpartner_gate4_portal_projection(array $candidate): array
{
    $gate4 = is_array($candidate['gate4'] ?? null) ? $candidate['gate4'] : [];
    $pilot = is_array($gate4['pilot'] ?? null) ? $gate4['pilot'] : [];
    $gate3 = is_array($candidate['gate3'] ?? null) ? $candidate['gate3'] : [];

    $scopes = [];
    foreach ((array)($gate3['scopes'] ?? []) as $scope) {
        if (!is_array($scope) || !in_array((string)($scope['scope_key'] ?? ''), ['events', 'activities'], true)) {
            continue;
        }
        $scopes[] = [
            'scope_key' => (string)$scope['scope_key'],
            'status' => (string)($scope['status'] ?? 'planned'),
            'limit_value' => isset($scope['limit_value']) ? (int)$scope['limit_value'] : null,
            'is_unlimited' => (int)($scope['is_unlimited'] ?? 0) === 1,
            'period_unit' => (string)($scope['period_unit'] ?? ''),
        ];
    }

    $contentLinks = [];
    foreach ((array)($gate4['content_links'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $contentLinks[] = [
            'submission_id' => (int)($row['submission_id'] ?? 0),
            'content_type' => (string)($row['content_type'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'start_date' => $row['start_date'] ?? null,
            'location_name' => $row['location_name'] ?? null,
        ];
    }

    $onboarding = is_array($gate4['onboarding'] ?? null) ? $gate4['onboarding'] : [];
    return [
        'phase' => (string)($gate4['phase'] ?? 'onboarding'),
        'active' => !empty($gate4['active']),
        'activation_ready' => !empty($gate4['activation_ready']),
        'pilot' => [
            'status' => (string)($pilot['status'] ?? 'onboarding'),
            'activation_date_local' => $pilot['activation_date_local'] ?? null,
            'planned_end_date' => $pilot['planned_end_date'] ?? null,
        ],
        'scopes' => $scopes,
        'onboarding' => [
            'complete_count' => (int)($onboarding['completed_count'] ?? 0),
            'total_count' => (int)($onboarding['total_count'] ?? 14),
        ],
        'content_links' => $contentLinks,
    ];
}
