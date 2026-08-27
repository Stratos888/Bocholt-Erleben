<?php
declare(strict_types=1);

function be_startpartner_gate4_control_case_due_at(?string $localDate): ?string
{
    $date = trim((string)$localDate);
    if ($date === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($date . ' 12:00:00', new DateTimeZone('Europe/Berlin')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function be_startpartner_gate4_project_control_case(PDO $pdo, array $candidate, array $gate4, string $actor): void
{
    $candidateId = (string)$candidate['id'];
    $phase = (string)($gate4['phase'] ?? 'onboarding');
    $firstBlocker = (string)($gate4['blockers'][0]['message'] ?? 'Onboarding prüfen.');
    $next = is_array($gate4['next_action'] ?? null) ? $gate4['next_action'] : [];
    $nextAction = trim((string)($next['label'] ?? '')) !== ''
        ? (string)$next['label']
        : $firstBlocker;
    $nextCode = (string)($next['code'] ?? 'onboarding');
    $terminal = in_array($phase, BE_STARTPARTNER_GATE4_TERMINAL_PILOT_STATUSES, true);
    $reason = match ($phase) {
        'activation_ready' => 'Alle Aktivierungsbedingungen sind belegt.',
        'active' => !empty($gate4['effective_active'])
            ? 'Der Startpartner-Pilot läuft innerhalb seiner wirksamen Laufzeit.'
            : 'Der gespeicherte Pilotstatus ist aktiv, der wirksame Betriebsvertrag verlangt aber eine Abschlussentscheidung.',
        'paused' => 'Der Startpartner-Pilot ist pausiert; Berechtigung und Scopes sind ebenfalls pausiert.',
        'closing' => 'Der Startpartner-Pilot befindet sich im geschützten Abschlusszustand.',
        'ended_without_conversion' => 'Der Startpartner-Pilot wurde geordnet ohne kostenpflichtige Fortführung beendet.',
        'terminated' => 'Der Startpartner-Pilot wurde kontrolliert abgebrochen.',
        'converted' => 'Der Startpartner-Pilot besitzt einen späteren dokumentierten Folgezustand.',
        default => $firstBlocker,
    };
    $priority = match (true) {
        $nextCode === 'closeout_required' => 'critical',
        in_array($nextCode, ['checkpoint_due', 'end_without_conversion', 'distribution_due', 'distribution_blocked', 'activate'], true) => 'high',
        default => 'normal',
    };
    $projectedState = $terminal ? 'done' : 'in_progress';
    $nextReviewAt = $gate4['next_review_at'] ?? null;

    $payload = json_encode([
        'candidate_id' => $candidateId,
        'candidate_status' => $candidate['status'],
        'candidate_revision' => (int)$candidate['revision'],
        'candidate_source' => $candidate['source'],
        'desired_content_scope' => $candidate['desired_content_scope'],
        'assigned_to' => $candidate['assigned_to'] ?? null,
        'next_review_at' => $nextReviewAt,
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
             next_action = :next_action, source_payload_json = :payload, due_at = :due_at,
             decision_ready = :decision_ready,
             completed_at = CASE WHEN :is_terminal = 1 THEN COALESCE(completed_at, CURRENT_TIMESTAMP) ELSE NULL END,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $statement->execute([
        'state' => $projectedState,
        'priority' => $priority,
        'title' => 'Startpartner-Pilot: ' . (string)$candidate['organization_name'],
        'reason' => $reason,
        'next_action' => $terminal ? null : $nextAction,
        'payload' => $payload,
        'due_at' => $terminal ? null : be_startpartner_gate4_control_case_due_at(
            is_string($nextReviewAt) ? $nextReviewAt : null
        ),
        'decision_ready' => in_array($nextCode, ['activate', 'closeout_required', 'end_without_conversion'], true) ? 1 : 0,
        'is_terminal' => $terminal ? 1 : 0,
        'id' => (string)$existing['id'],
    ]);
    be_cc_record_event(
        $pdo,
        (string)$existing['id'],
        'startpartner_gate4_sync',
        (string)$existing['state'],
        $projectedState,
        [
            'candidate_revision' => (int)$candidate['revision'],
            'phase' => $phase,
            'next_action_code' => $nextCode,
            'next_review_at' => $nextReviewAt,
        ],
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

function be_startpartner_gate4_partner_next_action(array $candidate, array $gate4): array
{
    $phase = (string)($gate4['phase'] ?? 'onboarding');
    if (in_array($phase, BE_STARTPARTNER_GATE4_TERMINAL_PILOT_STATUSES, true)) {
        return ['code' => 'none', 'label' => 'Pilot abgeschlossen', 'content_type' => null];
    }
    if ($phase === 'closing') {
        return ['code' => 'closeout', 'label' => 'Der Pilot befindet sich im Abschluss.', 'content_type' => null];
    }
    if ($phase === 'paused') {
        return ['code' => 'paused', 'label' => 'Der Pilot ist aktuell pausiert.', 'content_type' => null];
    }
    if ($phase === 'active' && empty($gate4['effective_active'])) {
        return ['code' => 'closeout_due', 'label' => 'Die Pilotlaufzeit ist beendet; neue Inhalte sind gesperrt.', 'content_type' => null];
    }

    $contentLinks = array_values(array_filter((array)($gate4['content_links'] ?? []), 'is_array'));
    if ($phase !== 'active') {
        foreach ($contentLinks as $row) {
            if (in_array((string)($row['status'] ?? ''), ['draft', 'editorial_ready', 'approved'], true)) {
                return [
                    'code' => 'wait_for_operator',
                    'label' => 'Deine Einreichung wird von Bocholt erleben weiterbearbeitet.',
                    'content_type' => null,
                ];
            }
        }
    }

    $limits = is_array($gate4['limits'] ?? null) ? $gate4['limits'] : [];
    $event = is_array($limits['event'] ?? null) ? $limits['event'] : ['available' => false];
    $activity = is_array($limits['activity'] ?? null) ? $limits['activity'] : ['available' => false];
    $desired = (string)($candidate['desired_content_scope'] ?? '');
    $allowEvent = !empty($event['available']) && empty($event['full']);
    $allowActivity = !empty($activity['available']) && empty($activity['full']);

    if (($desired === 'events' || $desired === 'both') && $allowEvent) {
        return ['code' => 'submit_content', 'label' => 'Nächsten Termin einreichen', 'content_type' => 'event'];
    }
    if (($desired === 'activities' || $desired === 'both') && $allowActivity) {
        return ['code' => 'submit_content', 'label' => 'Nächste Aktivität einreichen', 'content_type' => 'activity'];
    }
    if (!empty($event['available']) && !empty($event['full'])) {
        $reset = trim((string)($event['reset_date_local'] ?? ''));
        return [
            'code' => 'event_limit_full',
            'label' => $reset !== ''
                ? 'Event-Limit erreicht; neuer Pilotmonat ab ' . $reset . '.'
                : 'Event-Limit für diesen Pilotmonat erreicht.',
            'content_type' => null,
        ];
    }
    if (!empty($activity['available']) && !empty($activity['full'])) {
        return [
            'code' => 'activity_limit_full',
            'label' => 'Die vereinbarte gleichzeitige Aktivitätspräsenz ist vollständig belegt.',
            'content_type' => null,
        ];
    }
    return ['code' => 'status_only', 'label' => 'Aktuellen Pilotstatus ansehen', 'content_type' => null];
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
            'id' => (string)($row['id'] ?? ''),
            'submission_id' => (int)($row['submission_id'] ?? 0),
            'content_type' => (string)($row['content_type'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'start_date' => $row['start_date'] ?? null,
            'location_name' => $row['location_name'] ?? null,
        ];
    }

    $safeLimits = [];
    foreach (['event', 'activity'] as $key) {
        $row = is_array($gate4['limits'][$key] ?? null) ? $gate4['limits'][$key] : null;
        if (!is_array($row)) {
            continue;
        }
        $safeLimits[$key] = [
            'available' => !empty($row['available']),
            'used' => isset($row['used']) ? (int)$row['used'] : 0,
            'limit' => isset($row['limit']) ? (int)$row['limit'] : null,
            'is_unlimited' => !empty($row['is_unlimited']),
            'full' => !empty($row['full']),
            'pilot_month_index' => isset($row['pilot_month_index']) ? (int)$row['pilot_month_index'] : null,
            'reset_date_local' => $row['reset_date_local'] ?? null,
        ];
    }

    $measurement = is_array($gate4['measurement_runtime'] ?? null) ? $gate4['measurement_runtime'] : [];
    $safeMeasurement = [
        'status' => (string)($measurement['status'] ?? 'technical_not_ready'),
        'observed_actions' => isset($measurement['observed_actions']) ? (int)$measurement['observed_actions'] : null,
        'completed_bucket_count' => isset($measurement['completed_bucket_count']) ? (int)$measurement['completed_bucket_count'] : 0,
        'last_completed_metric_date' => $measurement['last_completed_metric_date'] ?? null,
    ];

    $distribution = is_array($gate4['distribution_runtime'] ?? null) ? $gate4['distribution_runtime'] : [];
    $commitment = is_array($distribution['commitment'] ?? null) ? $distribution['commitment'] : null;
    $safeDistribution = [
        'status' => (string)($distribution['status'] ?? 'not_planned'),
        'commitment' => is_array($commitment) ? [
            'id' => (string)($commitment['id'] ?? ''),
            'channel' => (string)($commitment['channel'] ?? ''),
            'planned_at' => $commitment['planned_at'] ?? null,
        ] : null,
    ];

    $lifecycle = is_array($gate4['lifecycle'] ?? null) ? $gate4['lifecycle'] : [];
    $safeLifecycle = [
        'status' => (string)($lifecycle['status'] ?? ($pilot['status'] ?? 'onboarding')),
        'inside_effective_window' => !empty($lifecycle['inside_effective_window']),
        'terminal' => !empty($lifecycle['terminal']),
        'closeout_required' => !empty($lifecycle['closeout_required']),
    ];

    $onboarding = is_array($gate4['onboarding'] ?? null) ? $gate4['onboarding'] : [];
    return [
        'phase' => (string)($gate4['phase'] ?? 'onboarding'),
        'active' => !empty($gate4['active']),
        'effective_active' => !empty($gate4['effective_active']),
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
        'limits' => $safeLimits,
        'lifecycle' => $safeLifecycle,
        'measurement' => $safeMeasurement,
        'distribution' => $safeDistribution,
        'next_review_at' => $gate4['next_review_at'] ?? null,
        'next_action' => be_startpartner_gate4_partner_next_action($candidate, $gate4),
    ];
}