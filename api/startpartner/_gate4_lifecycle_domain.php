<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/submissions/_publication_snapshot.php';

function be_startpartner_gate4_lifecycle_event(
    PDO $pdo,
    string $pilotId,
    string $eventType,
    string $actor,
    array $payload
): void {
    $statement = $pdo->prepare(
        'INSERT INTO startpartner_pilot_events (pilot_id, event_type, actor_reference, payload_json)
         VALUES (:pilot_id, :event_type, :actor_reference, :payload_json)'
    );
    $statement->execute([
        'pilot_id' => $pilotId,
        'event_type' => $eventType,
        'actor_reference' => $actor,
        'payload_json' => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
    ]);
}

function be_startpartner_gate4_lifecycle_entitlement(PDO $pdo, string $pilotId): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_entitlements WHERE pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
    );
    $statement->execute(['pilot_id' => $pilotId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new DomainException('Pilot entitlement is missing.');
    }
    return $row;
}

function be_startpartner_gate4_lifecycle_scope(PDO $pdo, string $pilotId, string $contentType): array
{
    $scopeKey = $contentType === 'activity' ? 'activities' : 'events';
    $statement = $pdo->prepare(
        'SELECT * FROM startpartner_pilot_scopes
         WHERE pilot_id = :pilot_id AND scope_key = :scope_key LIMIT 1 FOR UPDATE'
    );
    $statement->execute(['pilot_id' => $pilotId, 'scope_key' => $scopeKey]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)$row['status'] !== 'active') {
        throw new DomainException('The required pilot content scope is not active.');
    }
    $expectedPlan = be_startpartner_gate3_scope_target_plan_key($scopeKey);
    if ((string)($row['target_plan_key'] ?? '') !== $expectedPlan) {
        throw new DomainException('Pilot scope target-plan mapping is inconsistent.');
    }
    return $row;
}

function be_startpartner_gate4_effective_pilot_window(array $pilot): array
{
    $activationDate = trim((string)($pilot['activation_date_local'] ?? ''));
    $plannedEndDate = trim((string)($pilot['planned_end_date'] ?? ''));
    if ($activationDate === '' || $plannedEndDate === '') {
        throw new DomainException('Pilot activation window is incomplete.');
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $monthIndex = be_startpartner_gate4_pilot_month_index($activationDate, $today, $plannedEndDate);
    if ($monthIndex === null) {
        throw new DomainException('Pilot is outside its effective six-month window; closeout is required.');
    }
    return [
        'today_local' => $today,
        'pilot_month_index' => $monthIndex,
        'month_window' => be_startpartner_gate4_pilot_month_window($activationDate, $monthIndex, $plannedEndDate),
    ];
}

function be_startpartner_gate4_assert_entitlement_window(array $entitlement): void
{
    $startsAt = trim((string)($entitlement['starts_at'] ?? ''));
    $endsAt = trim((string)($entitlement['ends_at'] ?? ''));
    if ($startsAt === '' || $endsAt === '') {
        throw new DomainException('Pilot entitlement window is incomplete.');
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $start = new DateTimeImmutable($startsAt, new DateTimeZone('UTC'));
    $end = new DateTimeImmutable($endsAt, new DateTimeZone('UTC'));
    if ($now < $start || $now > $end) {
        throw new DomainException('Pilot entitlement is outside its effective window; closeout is required.');
    }
}

function be_startpartner_gate4_lifecycle_window(array $pilot, array $entitlement): array
{
    if ((string)($pilot['status'] ?? '') !== 'active' || (string)($entitlement['status'] ?? '') !== 'active') {
        throw new DomainException('Pilot must be active for this action.');
    }
    be_startpartner_gate4_assert_entitlement_window($entitlement);
    return be_startpartner_gate4_effective_pilot_window($pilot);
}

function be_startpartner_gate4_lifecycle_content(PDO $pdo, string $pilotId, string $contentLinkId): array
{
    $statement = $pdo->prepare(
        'SELECT pcl.*, s.status AS submission_status, s.requested_model_key,
                s.organizer_id AS submission_organizer_id
         FROM startpartner_pilot_content_links pcl
         INNER JOIN submissions s ON s.id = pcl.submission_id
         WHERE pcl.id = :id AND pcl.pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
    );
    $statement->execute(['id' => $contentLinkId, 'pilot_id' => $pilotId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new DomainException('Pilot content link was not found.');
    }
    return $row;
}

function be_startpartner_gate4_approve_content(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.lifecycle.content.approve',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $pilotId = (string)$pilot['id'];
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $entitlement = be_startpartner_gate4_lifecycle_entitlement($pdo, $pilotId);
            $window = be_startpartner_gate4_lifecycle_window($pilot, $entitlement);
            $content = be_startpartner_gate4_lifecycle_content($pdo, $pilotId, $contentLinkId);
            $isReapproval = (string)$content['status'] === 'approved'
                && (string)$content['submission_status'] === 'in_review';
            if ((string)$content['status'] !== 'editorial_ready' && !$isReapproval) {
                throw new DomainException('Only editorially ready or approved revised pilot content can be approved.');
            }
            if (!in_array((string)$content['submission_status'], ['pending_review', 'in_review'], true)) {
                throw new DomainException('Submission is no longer in a reviewable state.');
            }
            if ((int)$content['organizer_id'] !== (int)$pilot['organizer_id']
                || (int)$content['submission_organizer_id'] !== (int)$pilot['organizer_id']) {
                throw new DomainException('Pilot content organizer attribution is inconsistent.');
            }

            $contentType = be_startpartner_gate4_content_type($content['content_type'] ?? null);
            $scope = be_startpartner_gate4_lifecycle_scope($pdo, $pilotId, $contentType);
            $expectedPlan = be_startpartner_gate3_scope_target_plan_key($contentType === 'activity' ? 'activities' : 'events');
            if ((string)$content['requested_model_key'] !== $expectedPlan) {
                throw new DomainException('Submission target plan does not match the pilot scope.');
            }

            if ($isReapproval) {
                $historicalUsage = $pdo->prepare(
                    'SELECT id, pilot_month_index, units FROM startpartner_pilot_usages
                     WHERE pilot_id = :pilot_id AND pilot_entitlement_id = :pilot_entitlement_id
                       AND content_link_id = :content_link_id AND submission_id = :submission_id
                       AND content_type = :content_type
                     ORDER BY id FOR UPDATE'
                );
                $historicalUsage->execute([
                    'pilot_id' => $pilotId,
                    'pilot_entitlement_id' => (string)$entitlement['id'],
                    'content_link_id' => $contentLinkId,
                    'submission_id' => (int)$content['submission_id'],
                    'content_type' => $contentType,
                ]);
                $usageRows = $historicalUsage->fetchAll(PDO::FETCH_ASSOC);
                if (count($usageRows) !== 1 || (int)$usageRows[0]['units'] !== 1) {
                    throw new DomainException('Re-approval requires exactly one matching historical pilot usage.');
                }
                $limitMeta = [
                    'limit_type' => 'historical_reapproval',
                    'pilot_month_index' => (int)$usageRows[0]['pilot_month_index'],
                    'used_before' => 1,
                    'limit' => null,
                    'is_unlimited' => false,
                ];
            } elseif ($contentType === 'event') {
                $unlimited = (int)($entitlement['is_event_unlimited'] ?? 0) === 1;
                if ($unlimited !== ((int)($scope['is_unlimited'] ?? 0) === 1)) {
                    throw new DomainException('Event limit contract is inconsistent.');
                }
                $usageRows = $pdo->prepare(
                    "SELECT id, units FROM startpartner_pilot_usages
                     WHERE pilot_id = :pilot_id AND content_type = 'event'
                       AND pilot_month_index = :pilot_month_index
                     ORDER BY id FOR UPDATE"
                );
                $usageRows->execute([
                    'pilot_id' => $pilotId,
                    'pilot_month_index' => (int)$window['pilot_month_index'],
                ]);
                $used = 0;
                foreach ($usageRows->fetchAll(PDO::FETCH_ASSOC) as $usageRow) {
                    $used += (int)$usageRow['units'];
                }
                $limit = $entitlement['event_limit_per_pilot_month'] !== null
                    ? (int)$entitlement['event_limit_per_pilot_month']
                    : null;
                $scopeLimit = $scope['limit_value'] !== null ? (int)$scope['limit_value'] : null;
                if (!$unlimited && ($limit === null || $limit < 1 || $scopeLimit !== $limit)) {
                    throw new DomainException('Event monthly limit is missing or inconsistent.');
                }
                if (!$unlimited && $used + 1 > $limit) {
                    throw new DomainException('Das vereinbarte Event-Limit für diesen Pilotmonat ist erreicht.');
                }
                $limitMeta = [
                    'limit_type' => 'pilot_month',
                    'pilot_month_index' => (int)$window['pilot_month_index'],
                    'used_before' => $used,
                    'limit' => $limit,
                    'is_unlimited' => $unlimited,
                    'reset_date_local' => $window['month_window']['next_start_date_local'] ?? null,
                ];
            } else {
                $limit = $entitlement['activity_concurrent_limit'] !== null
                    ? (int)$entitlement['activity_concurrent_limit']
                    : null;
                $scopeLimit = $scope['limit_value'] !== null ? (int)$scope['limit_value'] : null;
                if ($limit === null || $limit < 1 || $scopeLimit !== $limit || (int)$scope['is_unlimited'] === 1) {
                    throw new DomainException('Activity concurrent limit is missing or inconsistent.');
                }
                $occupancyRows = $pdo->prepare(
                    "SELECT id FROM startpartner_pilot_content_links
                     WHERE pilot_id = :pilot_id AND content_type = 'activity' AND status = 'approved'
                     ORDER BY id FOR UPDATE"
                );
                $occupancyRows->execute(['pilot_id' => $pilotId]);
                $occupied = count($occupancyRows->fetchAll(PDO::FETCH_ASSOC));
                if ($occupied >= $limit) {
                    throw new DomainException('Die vereinbarte gleichzeitige Aktivitätspräsenz ist bereits vollständig belegt.');
                }
                $limitMeta = [
                    'limit_type' => 'concurrent',
                    'used_before' => $occupied,
                    'limit' => $limit,
                    'is_unlimited' => false,
                ];
            }

            $submission = $pdo->prepare(
                "UPDATE submissions
                 SET status = 'approved', review_started_at = COALESCE(review_started_at, CURRENT_TIMESTAMP),
                     approved_at = COALESCE(approved_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP
                 WHERE id = :submission_id AND organizer_id = :organizer_id
                   AND payment_kind = 'startpartner_pilot'
                   AND status IN ('pending_review','in_review')"
            );
            $submission->execute([
                'submission_id' => (int)$content['submission_id'],
                'organizer_id' => (int)$pilot['organizer_id'],
            ]);
            if ($submission->rowCount() !== 1) {
                throw new DomainException('Pilot submission could not be approved atomically.');
            }
            be_replace_submission_publication_snapshot($pdo, (int)$content['submission_id']);

            if (!$isReapproval) {
                $contentUpdate = $pdo->prepare(
                "UPDATE startpartner_pilot_content_links
                 SET status = 'approved', approved_at = COALESCE(approved_at, CURRENT_TIMESTAMP)
                 WHERE id = :id AND pilot_id = :pilot_id AND status = 'editorial_ready'"
                );
                $contentUpdate->execute(['id' => $contentLinkId, 'pilot_id' => $pilotId]);
                if ($contentUpdate->rowCount() !== 1) {
                    throw new RuntimeException('Pilot content link could not be approved.');
                }
                $usage = $pdo->prepare(
                'INSERT INTO startpartner_pilot_usages (
                    pilot_id, pilot_entitlement_id, content_link_id, submission_id,
                    content_type, pilot_month_index, units
                 ) VALUES (
                    :pilot_id, :pilot_entitlement_id, :content_link_id, :submission_id,
                    :content_type, :pilot_month_index, 1
                 )'
                );
                $usage->execute([
                    'pilot_id' => $pilotId,
                    'pilot_entitlement_id' => (string)$entitlement['id'],
                    'content_link_id' => $contentLinkId,
                    'submission_id' => (int)$content['submission_id'],
                    'content_type' => $contentType,
                    'pilot_month_index' => (int)$window['pilot_month_index'],
                ]);
            }

            be_startpartner_gate4_lifecycle_event($pdo, $pilotId, 'gate4_content_approved', $operator, [
                'operation_id' => $operationId,
                'content_link_id' => $contentLinkId,
                'submission_id' => (int)$content['submission_id'],
                'content_type' => $contentType,
                'pilot_month_index' => $isReapproval
                    ? (int)$limitMeta['pilot_month_index']
                    : (int)$window['pilot_month_index'],
                'limit' => $limitMeta,
                'publication_effect' => 'public_projection_eligible',
                'payment_effect' => 'none',
            ]);
            return [
                'status_reason' => $isReapproval
                    ? 'Pilotinhalt erneut freigegeben; historische Nutzung und Kapazität bleiben unverändert.'
                    : 'Pilotinhalt redaktionell freigegeben, für die öffentliche Projektion berechtigt und einmalig dem Pilotverbrauch zugeordnet.',
                'content_link_id' => $contentLinkId,
                'submission_id' => (int)$content['submission_id'],
                'usage_units' => $isReapproval ? 0 : 1,
                'limit' => $limitMeta,
            ];
        },
        false
    );
}

function be_startpartner_gate4_reject_content(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.lifecycle.content.reject',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            if (!in_array((string)$pilot['status'], ['onboarding', 'activation_ready', 'active'], true)) {
                throw new DomainException('Pilot content cannot be rejected in the current pilot state.');
            }
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $content = be_startpartner_gate4_lifecycle_content($pdo, (string)$pilot['id'], $contentLinkId);
            $isRevision = (string)$content['status'] === 'approved'
                && (string)$content['submission_status'] === 'in_review';
            if (!in_array((string)$content['status'], ['draft', 'editorial_ready'], true) && !$isRevision) {
                throw new DomainException('Only reviewable pilot content can be rejected.');
            }
            if (!$isRevision) {
                $link = $pdo->prepare(
                    "UPDATE startpartner_pilot_content_links SET status = 'rejected'
                     WHERE id = :id AND pilot_id = :pilot_id AND status IN ('draft','editorial_ready')"
                );
                $link->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
                if ($link->rowCount() !== 1) {
                    throw new RuntimeException('Pilot content link could not be rejected.');
                }
            }
            $submission = $pdo->prepare(
                "UPDATE submissions
                 SET status = 'rejected', rejected_at = COALESCE(rejected_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP
                 WHERE id = :submission_id AND payment_kind = 'startpartner_pilot'
                   AND status IN ('pending_review','in_review')"
            );
            $submission->execute(['submission_id' => (int)$content['submission_id']]);
            be_startpartner_gate4_lifecycle_event($pdo, (string)$pilot['id'], 'gate4_content_rejected', $operator, [
                'operation_id' => $operationId,
                'content_link_id' => $contentLinkId,
                'submission_id' => (int)$content['submission_id'],
                'usage_effect' => 'none',
                'publication_effect' => 'none',
            ]);
            return [
                'status_reason' => 'Pilotinhalt redaktionell abgelehnt.',
                'content_link_id' => $contentLinkId,
            ];
        },
        false
    );
}

function be_startpartner_gate4_withdraw_content(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.lifecycle.content.withdraw',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            if (!in_array((string)$pilot['status'], ['onboarding', 'activation_ready', 'active', 'paused', 'closing'], true)) {
                throw new DomainException('Pilot content cannot be withdrawn in the current pilot state.');
            }
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $content = be_startpartner_gate4_lifecycle_content($pdo, (string)$pilot['id'], $contentLinkId);
            if (!in_array((string)$content['status'], ['draft', 'editorial_ready', 'approved'], true)) {
                throw new DomainException('Pilot content is already terminal.');
            }
            $link = $pdo->prepare(
                "UPDATE startpartner_pilot_content_links SET status = 'withdrawn'
                 WHERE id = :id AND pilot_id = :pilot_id
                   AND status IN ('draft','editorial_ready','approved')"
            );
            $link->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            if ($link->rowCount() !== 1) {
                throw new RuntimeException('Pilot content link could not be withdrawn.');
            }
            if ((string)$content['status'] === 'approved') {
                be_remove_submission_publication_snapshot($pdo, (int)$content['submission_id']);
            }
            be_startpartner_gate4_lifecycle_event($pdo, (string)$pilot['id'], 'gate4_content_withdrawn', $operator, [
                'operation_id' => $operationId,
                'content_link_id' => $contentLinkId,
                'submission_id' => (int)$content['submission_id'],
                'previous_status' => (string)$content['status'],
                'usage_effect' => 'historical_usage_retained',
                'publication_effect' => (string)$content['status'] === 'approved'
                    ? 'public_projection_withdrawn'
                    : 'none',
            ]);
            return [
                'status_reason' => 'Pilotzuordnung des Inhalts zurückgezogen; öffentliche Sichtbarkeit endet, falls der Inhalt bereits freigegeben war. Historische Freigabe-/Usage-Evidence bleibt erhalten.',
                'content_link_id' => $contentLinkId,
            ];
        },
        false
    );
}

function be_startpartner_gate4_update_scope_statuses(PDO $pdo, string $pilotId, string $transition): void
{
    [$targetStatus, $allowedFrom] = match ($transition) {
        'pause' => ['paused', ['active']],
        'resume' => ['active', ['paused']],
        'start_closeout' => ['paused', ['active']],
        'end_without_conversion', 'terminate' => ['ended', ['active', 'paused']],
        default => throw new InvalidArgumentException('Lifecycle transition is invalid.'),
    };
    $placeholders = implode(',', array_fill(0, count($allowedFrom), '?'));
    $statement = $pdo->prepare(
        "UPDATE startpartner_pilot_scopes SET status = ?
         WHERE pilot_id = ? AND status IN ($placeholders)"
    );
    $statement->execute(array_merge([$targetStatus, $pilotId], $allowedFrom));
}

function be_startpartner_gate4_transition_lifecycle(
    PDO $pdo,
    string $candidateId,
    string $transition,
    array $input
): array {
    $allowed = [
        'pause' => ['from' => ['active'], 'to' => 'paused', 'entitlement' => 'paused'],
        'resume' => ['from' => ['paused'], 'to' => 'active', 'entitlement' => 'active'],
        'start_closeout' => ['from' => ['active', 'paused'], 'to' => 'closing', 'entitlement' => 'paused'],
        'end_without_conversion' => ['from' => ['closing'], 'to' => 'ended_without_conversion', 'entitlement' => 'ended'],
        'terminate' => ['from' => ['active', 'paused', 'closing'], 'to' => 'terminated', 'entitlement' => 'revoked'],
    ];
    if (!isset($allowed[$transition])) {
        throw new InvalidArgumentException('Lifecycle transition is invalid.');
    }
    $contract = $allowed[$transition];
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.lifecycle.' . $transition,
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input) use ($transition, $contract): array {
            $pilotId = (string)$pilot['id'];
            $from = (string)$pilot['status'];
            if (!in_array($from, $contract['from'], true)) {
                throw new DomainException('Pilot lifecycle transition is not allowed from the current state.');
            }
            $entitlement = be_startpartner_gate4_lifecycle_entitlement($pdo, $pilotId);
            if ($transition === 'pause') {
                if ((string)$entitlement['status'] !== 'active') {
                    throw new DomainException('Active pilot entitlement is required for pause.');
                }
                be_startpartner_gate4_assert_entitlement_window($entitlement);
                be_startpartner_gate4_effective_pilot_window($pilot);
            }
            if ($transition === 'resume') {
                if ((string)$entitlement['status'] !== 'paused') {
                    throw new DomainException('Paused pilot entitlement is required for resume.');
                }
                be_startpartner_gate4_assert_entitlement_window($entitlement);
                be_startpartner_gate4_effective_pilot_window($pilot);
            }
            if ($transition === 'start_closeout'
                && !in_array((string)$entitlement['status'], ['active', 'paused'], true)) {
                throw new DomainException('Active or paused pilot entitlement is required for closeout.');
            }
            if ($transition === 'end_without_conversion' && (string)$entitlement['status'] !== 'paused') {
                throw new DomainException('Closing pilot must have a paused entitlement before ending.');
            }
            if ($transition === 'terminate'
                && !in_array((string)$entitlement['status'], ['active', 'paused'], true)) {
                throw new DomainException('Pilot entitlement cannot be revoked from the current state.');
            }

            $entitlementUpdate = $pdo->prepare(
                'UPDATE startpartner_pilot_entitlements
                 SET status = :status, revision = revision + 1
                 WHERE id = :id'
            );
            $entitlementUpdate->execute([
                'status' => $contract['entitlement'],
                'id' => (string)$entitlement['id'],
            ]);
            if ($entitlementUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pilot entitlement lifecycle could not be updated.');
            }

            be_startpartner_gate4_update_scope_statuses($pdo, $pilotId, $transition);

            if (in_array($transition, ['end_without_conversion', 'terminate'], true)) {
                $withdraw = $pdo->prepare(
                    "UPDATE startpartner_pilot_content_links
                     SET status = 'withdrawn'
                     WHERE pilot_id = :pilot_id AND status IN ('draft','editorial_ready')"
                );
                $withdraw->execute(['pilot_id' => $pilotId]);
            }

            $pilotUpdate = $pdo->prepare(
                'UPDATE startpartner_pilots
                 SET status = :status,
                     closed_at = CASE WHEN :terminal = 1 THEN COALESCE(closed_at, CURRENT_TIMESTAMP) ELSE closed_at END
                 WHERE id = :id AND status = :from_status'
            );
            $terminal = in_array($transition, ['end_without_conversion', 'terminate'], true) ? 1 : 0;
            $pilotUpdate->execute([
                'status' => $contract['to'],
                'terminal' => $terminal,
                'id' => $pilotId,
                'from_status' => $from,
            ]);
            if ($pilotUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pilot lifecycle could not be updated.');
            }

            be_startpartner_gate4_lifecycle_event($pdo, $pilotId, 'gate4_pilot_' . $transition, $operator, [
                'operation_id' => $operationId,
                'from_status' => $from,
                'to_status' => $contract['to'],
                'entitlement_status' => $contract['entitlement'],
                'scope_status' => match ($transition) {
                    'pause', 'start_closeout' => 'paused',
                    'resume' => 'active',
                    default => 'ended',
                },
                'usage_effect' => 'none',
                'publication_effect' => match ($transition) {
                    'pause', 'resume', 'start_closeout' => 'approved_content_retained',
                    'end_without_conversion', 'terminate' => 'active_startpartner_public_projection_ended',
                },
                'payment_effect' => 'none',
            ]);
            return [
                'status_reason' => match ($transition) {
                    'pause' => 'Startpartner-Pilot pausiert.',
                    'resume' => 'Startpartner-Pilot innerhalb der wirksamen Laufzeit fortgesetzt.',
                    'start_closeout' => 'Abschluss des Startpartner-Piloten eingeleitet.',
                    'end_without_conversion' => 'Startpartner-Pilot geordnet ohne kostenpflichtige Fortführung beendet.',
                    'terminate' => 'Startpartner-Pilot kontrolliert abgebrochen.',
                },
                'transition' => $transition,
                'from_status' => $from,
                'to_status' => $contract['to'],
            ];
        },
        false
    );
}

function be_startpartner_gate4_complete_checkpoint(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.lifecycle.checkpoint.complete',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            if (!in_array((string)$pilot['status'], ['active', 'paused', 'closing'], true)) {
                throw new DomainException('Pilot checkpoint is not available in the current state.');
            }
            $checkpointKey = be_startpartner_gate4_checkpoint_key($input['checkpoint_key'] ?? null);
            $evidenceText = be_startpartner_gate4_required_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
            $activationDate = trim((string)($pilot['activation_date_local'] ?? ''));
            $plannedEndDate = trim((string)($pilot['planned_end_date'] ?? ''));
            if ($activationDate === '' || $plannedEndDate === '') {
                throw new DomainException('Pilot checkpoint schedule is unavailable.');
            }
            $schedule = be_startpartner_gate4_checkpoint_schedule($activationDate, $plannedEndDate);
            $due = $schedule[$checkpointKey]['due_date_local'];
            $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
            if ($today < $due) {
                throw new DomainException('Dieser Pilot-Checkpoint ist noch nicht fällig.');
            }
            $events = $pdo->prepare(
                "SELECT id, payload_json FROM startpartner_pilot_events
                 WHERE pilot_id = :pilot_id AND event_type = 'gate4_checkpoint_completed'
                 ORDER BY id FOR UPDATE"
            );
            $events->execute(['pilot_id' => (string)$pilot['id']]);
            foreach ($events->fetchAll(PDO::FETCH_ASSOC) as $event) {
                $payload = json_decode((string)$event['payload_json'], true);
                if (is_array($payload) && (string)($payload['checkpoint_key'] ?? '') === $checkpointKey) {
                    throw new DomainException('Dieser Pilot-Checkpoint wurde bereits abgeschlossen.');
                }
            }
            be_startpartner_gate4_lifecycle_event($pdo, (string)$pilot['id'], 'gate4_checkpoint_completed', $operator, [
                'operation_id' => $operationId,
                'checkpoint_key' => $checkpointKey,
                'due_date_local' => $due,
                'completed_date_local' => $today,
                'evidence_text' => $evidenceText,
                'payment_effect' => 'none',
            ]);
            return [
                'status_reason' => 'Pilot-Checkpoint dokumentiert.',
                'checkpoint_key' => $checkpointKey,
                'due_date_local' => $due,
            ];
        },
        false
    );
}

function be_startpartner_gate4_set_distribution_fulfillment(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.lifecycle.distribution.fulfillment',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            if (!in_array((string)$pilot['status'], ['active', 'paused', 'closing'], true)) {
                throw new DomainException('Distribution fulfillment is not available in the current pilot state.');
            }
            $distributionId = be_startpartner_gate4_required_text($input['distribution_id'] ?? null, 36, 'distribution_id');
            $target = strtolower(be_startpartner_gate4_required_text($input['status'] ?? null, 16, 'status'));
            $evidenceText = be_startpartner_gate4_required_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
            $statement = $pdo->prepare(
                'SELECT * FROM startpartner_pilot_distribution_commitments
                 WHERE id = :id AND pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => $distributionId, 'pilot_id' => (string)$pilot['id']]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new DomainException('Distribution commitment was not found.');
            }
            $from = (string)$row['status'];
            $allowedTargets = match ($from) {
                'planned' => ['ready', 'blocked', 'cancelled'],
                'ready' => ['completed', 'blocked', 'cancelled'],
                'blocked' => ['completed', 'cancelled'],
                'completed', 'cancelled' => [],
                default => [],
            };
            if (!in_array($target, $allowedTargets, true)) {
                throw new DomainException('Distribution transition is not allowed from the current state.');
            }
            $update = $pdo->prepare(
                'UPDATE startpartner_pilot_distribution_commitments
                 SET status = :status, evidence_text = :evidence_text,
                     operator_reference = :operator, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND pilot_id = :pilot_id AND status = :from_status'
            );
            $update->execute([
                'status' => $target,
                'evidence_text' => $evidenceText,
                'operator' => $operator,
                'id' => $distributionId,
                'pilot_id' => (string)$pilot['id'],
                'from_status' => $from,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Distribution fulfillment could not be saved.');
            }
            be_startpartner_gate4_lifecycle_event($pdo, (string)$pilot['id'], 'gate4_distribution_fulfillment', $operator, [
                'operation_id' => $operationId,
                'distribution_id' => $distributionId,
                'from_status' => $from,
                'to_status' => $target,
                'evidence_text' => $evidenceText,
            ]);
            return [
                'status_reason' => 'Reichweitenbeitrag im laufenden Pilot aktualisiert.',
                'distribution_id' => $distributionId,
                'status' => $target,
            ];
        },
        false
    );
}

function be_startpartner_gate4_lifecycle_dispatch(PDO $pdo, string $candidateId, array $input): array
{
    $action = strtolower(trim((string)($input['action'] ?? '')));
    return match ($action) {
        'approve_content' => be_startpartner_gate4_approve_content($pdo, $candidateId, $input),
        'reject_content' => be_startpartner_gate4_reject_content($pdo, $candidateId, $input),
        'withdraw_content' => be_startpartner_gate4_withdraw_content($pdo, $candidateId, $input),
        'pause', 'resume', 'start_closeout', 'end_without_conversion', 'terminate'
            => be_startpartner_gate4_transition_lifecycle($pdo, $candidateId, $action, $input),
        'complete_checkpoint' => be_startpartner_gate4_complete_checkpoint($pdo, $candidateId, $input),
        'set_distribution_fulfillment' => be_startpartner_gate4_set_distribution_fulfillment($pdo, $candidateId, $input),
        default => throw new InvalidArgumentException('Lifecycle action is invalid.'),
    };
}
