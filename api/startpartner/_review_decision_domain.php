<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_domain.php';

const BE_STARTPARTNER_REVIEW_DECISIONS = ['approve', 'needs_information', 'reject', 'waitlist'];
const BE_STARTPARTNER_REVIEW_ACTIVE_STATUSES = [
    'new', 'prequalifying', 'contact_pending', 'awaiting_response',
    'qualifying', 'needs_information', 'decision_ready', 'waitlisted',
];

function be_startpartner_review_decision(PDO $pdo, string $candidateId, array $input): array
{
    $decision = be_startpartner_validate_enum_value(
        trim((string)($input['decision'] ?? '')),
        BE_STARTPARTNER_REVIEW_DECISIONS,
        'decision'
    );

    return be_startpartner_gate2_run_operation(
        $pdo,
        $candidateId,
        'review_decision.' . $decision,
        $input,
        static function(PDO $pdo, array $candidate, string $operatorName, array $input) use ($decision): array {
            $status = (string)$candidate['status'];
            if (!in_array($status, BE_STARTPARTNER_REVIEW_ACTIVE_STATUSES, true)) {
                throw new DomainException("Review decision is not allowed from {$status}.");
            }

            $readiness = be_startpartner_gate2_readiness(
                be_startpartner_gate2_qualification_rows($pdo, (string)$candidate['id'])
            );
            $capacity = be_startpartner_gate2_capacity($pdo, true);
            $reason = be_startpartner_clean_text($input['reason'] ?? null, 5000, 'reason');
            $updates = ['closed_at' => null];
            $meta = [
                'review_mode' => 'ai_assisted_human_decision',
                'legacy_readiness' => $readiness,
            ];

            if ($decision === 'approve') {
                if ($capacity['hard_stop']) {
                    throw new DomainException('Hard capacity stop reached.');
                }
                $capacityReason = be_startpartner_clean_text(
                    $input['capacity_exception_reason'] ?? null,
                    5000,
                    'capacity_exception_reason'
                );
                if ($capacity['soft_stop'] && $capacityReason === null) {
                    throw new InvalidArgumentException('capacity_exception_reason is required at the soft stop.');
                }
                if ($reason === null) {
                    $reason = 'KI-gestützte Prüfung durchgeführt; Aufnahme durch den Betreiber bestätigt.';
                }
                $endsAt = be_startpartner_gate2_parse_future_datetime(
                    $input['reservation_ends_at'] ?? null,
                    'reservation_ends_at',
                    BE_STARTPARTNER_RESERVATION_MAX_DAYS
                );
                $decisionId = be_startpartner_gate2_insert_decision(
                    $pdo,
                    $candidate,
                    'accepted_pending_terms',
                    $reason,
                    $operatorName,
                    $readiness,
                    $capacity,
                    ['waitlist_or_rejection_reason' => 'KI-gestützte Vorprüfung; verbindliche Aufnahmeentscheidung durch Betreiber.']
                );
                $reservation = $pdo->prepare(
                    'INSERT INTO startpartner_candidate_reservations (
                        candidate_id, decision_id, status, starts_at, ends_at,
                        capacity_snapshot_json, soft_stop_exception_reason, operator_reference
                     ) VALUES (
                        :candidate_id, :decision_id, \'active\', UTC_TIMESTAMP(), :ends_at,
                        :capacity_snapshot_json, :soft_stop_exception_reason, :operator_reference
                     )'
                );
                $reservation->execute([
                    'candidate_id' => (string)$candidate['id'],
                    'decision_id' => $decisionId,
                    'ends_at' => $endsAt,
                    'capacity_snapshot_json' => json_encode(
                        $capacity,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                    'soft_stop_exception_reason' => $capacityReason,
                    'operator_reference' => $operatorName,
                ]);
                $reservationId = (int)$pdo->lastInsertId();
                $pdo->prepare(
                    'UPDATE startpartner_candidate_decisions
                     SET reservation_reference = :reservation_id WHERE id = :decision_id'
                )->execute(['reservation_id' => $reservationId, 'decision_id' => $decisionId]);
                $pdo->prepare('DELETE FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id')
                    ->execute(['candidate_id' => (string)$candidate['id']]);
                $updates['status'] = 'accepted_pending_terms';
                $updates['status_reason'] = $reason;
                $updates['next_review_at'] = null;
                $meta['reservation_id'] = $reservationId;
            } elseif ($decision === 'needs_information') {
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                if ($status === 'waitlisted') {
                    be_startpartner_gate2_supersede_current_decision($pdo, (string)$candidate['id']);
                    $pdo->prepare('DELETE FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id')
                        ->execute(['candidate_id' => (string)$candidate['id']]);
                }
                $updates['status'] = 'needs_information';
                $updates['status_reason'] = $reason;
                $updates['next_review_at'] = null;
            } elseif ($decision === 'reject') {
                if ($reason === null) {
                    throw new InvalidArgumentException('reason is required.');
                }
                be_startpartner_gate2_insert_decision(
                    $pdo,
                    $candidate,
                    'rejected',
                    $reason,
                    $operatorName,
                    $readiness,
                    $capacity,
                    ['waitlist_or_rejection_reason' => $reason]
                );
                $pdo->prepare('DELETE FROM startpartner_candidate_waitlist WHERE candidate_id = :candidate_id')
                    ->execute(['candidate_id' => (string)$candidate['id']]);
                $updates['status'] = 'rejected';
                $updates['status_reason'] = $reason;
                $updates['next_review_at'] = null;
                $updates['closed_at'] = gmdate('Y-m-d H:i:s');
            } else {
                if (!$capacity['hard_stop']) {
                    throw new DomainException('Waitlist is only offered when the hard capacity stop is reached.');
                }
                $reason ??= 'Fachlich geeigneter Kandidat; aktuell ist kein Startpartnerplatz frei.';
                $nextReviewAt = be_startpartner_gate2_parse_future_datetime(
                    $input['next_review_at'] ?? null,
                    'next_review_at'
                );
                be_startpartner_gate2_insert_decision(
                    $pdo,
                    $candidate,
                    'waitlisted',
                    $reason,
                    $operatorName,
                    $readiness,
                    $capacity,
                    ['waitlist_or_rejection_reason' => $reason]
                );
                $waitlist = $pdo->prepare(
                    'INSERT INTO startpartner_candidate_waitlist (
                        candidate_id, eligibility_reason, priority_reason, next_review_at,
                        contact_status, regular_alternative, operator_reference, revision
                     ) VALUES (
                        :candidate_id, :eligibility_reason, :priority_reason, :next_review_at,
                        \'not_contacted\', NULL, :operator_reference, 1
                     )
                     ON DUPLICATE KEY UPDATE
                        eligibility_reason = VALUES(eligibility_reason),
                        priority_reason = VALUES(priority_reason),
                        next_review_at = VALUES(next_review_at),
                        contact_status = VALUES(contact_status),
                        regular_alternative = VALUES(regular_alternative),
                        operator_reference = VALUES(operator_reference),
                        revision = revision + 1'
                );
                $waitlist->execute([
                    'candidate_id' => (string)$candidate['id'],
                    'eligibility_reason' => 'KI-gestützte Prüfung abgeschlossen; Kandidat grundsätzlich geeignet.',
                    'priority_reason' => $reason,
                    'next_review_at' => $nextReviewAt,
                    'operator_reference' => $operatorName,
                ]);
                $updates['status'] = 'waitlisted';
                $updates['status_reason'] = $reason;
                $updates['next_review_at'] = $nextReviewAt;
            }

            return [
                'candidate_updates' => $updates,
                'events' => [[
                    'type' => 'ai_assisted_review_decision',
                    'from_status' => $status,
                    'to_status' => $updates['status'],
                    'payload' => [
                        'decision' => $decision,
                        'reason' => $reason,
                        'review_mode' => 'ai_assisted_human_decision',
                        'legacy_readiness' => $readiness,
                        'capacity' => $capacity,
                    ],
                ]],
                'meta' => $meta,
            ];
        }
    );
}
