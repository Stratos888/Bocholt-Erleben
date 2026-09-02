<?php
declare(strict_types=1);

function be_startpartner_gate4_activate(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.activate',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $state = be_startpartner_gate4_state($pdo, (string)$candidate['id'], false);
            $pilotStatusBefore = (string)$pilot['status'];
            if (
                !$state['activation_ready']
                || !in_array($pilotStatusBefore, ['onboarding', 'activation_ready'], true)
            ) {
                throw new DomainException('Pilot is not activation ready.');
            }
            $window = be_startpartner_gate4_activation_window((string)($input['activation_date_local'] ?? ''));
            $timezone = new DateTimeZone('Europe/Berlin');
            $activationLocal = new DateTimeImmutable($window['activation_date_local'] . ' 00:00:00', $timezone);
            $todayLocal = new DateTimeImmutable('today', $timezone);
            if ($activationLocal > $todayLocal) {
                throw new DomainException('Das Aktivierungsdatum darf bei einer sofort ausgeführten Aktivierung nicht in der Zukunft liegen.');
            }
            $content = $state['first_content'];
            $measurement = $state['ready_measurement'];
            $distribution = $state['ready_distribution'];
            if (!is_array($content) || (string)$content['status'] !== 'editorial_ready') {
                throw new DomainException('First pilot content is not editorially ready.');
            }
            if (!is_array($measurement)) {
                throw new DomainException('Measurement readiness is required.');
            }
            $entitlementStatement = $pdo->prepare(
                'SELECT * FROM startpartner_pilot_entitlements WHERE pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
            );
            $entitlementStatement->execute(['pilot_id' => (string)$pilot['id']]);
            $entitlement = $entitlementStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($entitlement) || (string)$entitlement['status'] !== 'pending_activation') {
                throw new DomainException('Pilot entitlement is not pending activation.');
            }
            $reservation = $pdo->prepare(
                "SELECT * FROM startpartner_candidate_reservations
                 WHERE id = :id AND candidate_id = :candidate_id AND status = 'active'
                 LIMIT 1 FOR UPDATE"
            );
            $reservation->execute([
                'id' => (int)$pilot['reservation_id'],
                'candidate_id' => (string)$candidate['id'],
            ]);
            $reservationRow = $reservation->fetch(PDO::FETCH_ASSOC);
            if (!is_array($reservationRow)) {
                throw new DomainException('Active pilot reservation is missing.');
            }

            if ($pilotStatusBefore === 'onboarding') {
                $readyStatusUpdate = $pdo->prepare(
                    "UPDATE startpartner_pilots
                     SET status = 'activation_ready'
                     WHERE id = :id AND status = 'onboarding'"
                );
                $readyStatusUpdate->execute(['id' => (string)$pilot['id']]);
                if ($readyStatusUpdate->rowCount() !== 1) {
                    throw new RuntimeException('Pilot readiness state could not be synchronized for activation.');
                }
            }

            $beforeCapacity = be_startpartner_gate4_capacity($pdo);

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
                throw new DomainException('First pilot submission could not be approved atomically.');
            }
            $contentUpdate = $pdo->prepare(
                "UPDATE startpartner_pilot_content_links
                 SET status = 'approved', approved_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND pilot_id = :pilot_id AND status = 'editorial_ready'"
            );
            $contentUpdate->execute(['id' => (string)$content['id'], 'pilot_id' => (string)$pilot['id']]);
            if ($contentUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pilot content link could not be activated.');
            }
            $usage = $pdo->prepare(
                'INSERT INTO startpartner_pilot_usages (
                    pilot_id, pilot_entitlement_id, content_link_id, submission_id,
                    content_type, pilot_month_index, units
                 ) VALUES (
                    :pilot_id, :pilot_entitlement_id, :content_link_id, :submission_id,
                    :content_type, 1, 1
                 )'
            );
            $usage->execute([
                'pilot_id' => (string)$pilot['id'],
                'pilot_entitlement_id' => (string)$entitlement['id'],
                'content_link_id' => (string)$content['id'],
                'submission_id' => (int)$content['submission_id'],
                'content_type' => (string)$content['content_type'],
            ]);
            $scopeUpdate = $pdo->prepare(
                "UPDATE startpartner_pilot_scopes SET status = 'active'
                 WHERE pilot_id = :pilot_id AND status = 'planned'"
            );
            $scopeUpdate->execute(['pilot_id' => (string)$pilot['id']]);
            $entitlementUpdate = $pdo->prepare(
                "UPDATE startpartner_pilot_entitlements
                 SET status = 'active', starts_at = :starts_at, ends_at = :ends_at,
                     revision = revision + 1,
                     audit_json = JSON_SET(audit_json,
                         '$.activated_by', :activated_by,
                         '$.activation_operation_id', :operation_id,
                         '$.activation_date_local', :activation_date_local,
                         '$.planned_end_date', :planned_end_date
                     )
                 WHERE id = :id AND status = 'pending_activation'"
            );
            $entitlementUpdate->execute([
                'starts_at' => $window['starts_at_utc'],
                'ends_at' => $window['ends_at_utc'],
                'activated_by' => $operator,
                'operation_id' => $operationId,
                'activation_date_local' => $window['activation_date_local'],
                'planned_end_date' => $window['planned_end_date'],
                'id' => (string)$entitlement['id'],
            ]);
            if ($entitlementUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pilot entitlement could not be activated.');
            }
            $pilotUpdate = $pdo->prepare(
                "UPDATE startpartner_pilots
                 SET status = 'active', activated_at = CURRENT_TIMESTAMP,
                     activation_date_local = :activation_date_local,
                     planned_end_date = :planned_end_date,
                     starts_at = :starts_at, ends_at = :ends_at
                 WHERE id = :id AND status = 'activation_ready'"
            );
            $pilotUpdate->execute([
                'activation_date_local' => $window['activation_date_local'],
                'planned_end_date' => $window['planned_end_date'],
                'starts_at' => $window['starts_at_utc'],
                'ends_at' => $window['ends_at_utc'],
                'id' => (string)$pilot['id'],
            ]);
            if ($pilotUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pilot could not be activated.');
            }
            $reservationUpdate = $pdo->prepare(
                "UPDATE startpartner_candidate_reservations
                 SET status = 'released', released_at = CURRENT_TIMESTAMP,
                     release_reference = :release_reference
                 WHERE id = :id AND status = 'active'"
            );
            $reservationUpdate->execute([
                'release_reference' => $operationId,
                'id' => (int)$reservationRow['id'],
            ]);
            if ($reservationUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pilot reservation could not be ended.');
            }
            $pilotEvent = $pdo->prepare(
                'INSERT INTO startpartner_pilot_events (pilot_id, event_type, actor_reference, payload_json)
                 VALUES (:pilot_id, :event_type, :actor_reference, :payload_json)'
            );
            $pilotEvent->execute([
                'pilot_id' => (string)$pilot['id'],
                'event_type' => 'gate4_pilot_activated',
                'actor_reference' => $operator,
                'payload_json' => json_encode([
                    'operation_id' => $operationId,
                    'activation_date_local' => $window['activation_date_local'],
                    'planned_end_date' => $window['planned_end_date'],
                    'content_link_id' => (string)$content['id'],
                    'submission_id' => (int)$content['submission_id'],
                    'measurement_preflight_id' => (string)$measurement['id'],
                    'distribution_id' => is_array($distribution) ? (string)$distribution['id'] : null,
                    'distribution_requirement' => 'optional_not_required_for_activation',
                    'pilot_status_before_activation' => $pilotStatusBefore,
                    'reservation_id' => (int)$reservationRow['id'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            $afterCapacity = be_startpartner_gate4_capacity($pdo);
            if ((int)$afterCapacity['occupied_slots'] !== (int)$beforeCapacity['occupied_slots']) {
                throw new RuntimeException('Occupied pilot capacity changed during activation.');
            }
            return [
                'status_reason' => 'Startpartner-Pilot aktiv; sechsmonatige Laufzeit gestartet.',
                'activation_window' => $window,
                'content_link_id' => (string)$content['id'],
                'submission_id' => (int)$content['submission_id'],
                'capacity_before' => $beforeCapacity,
                'capacity_after' => $afterCapacity,
            ];
        },
        false
    );
}
