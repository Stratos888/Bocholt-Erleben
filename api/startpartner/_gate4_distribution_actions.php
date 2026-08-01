<?php
declare(strict_types=1);

function be_startpartner_gate4_set_distribution(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.distribution.set',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $distribution = be_startpartner_gate4_distribution_input($input);
            be_startpartner_gate4_supersede_distribution($pdo, (string)$pilot['id'], $operationId);

            $id = be_startpartner_gate4_uuid();
            $statement = $pdo->prepare(
                'INSERT INTO startpartner_pilot_distribution_commitments (
                    id, pilot_id, channel, planned_at, target_reference, status,
                    evidence_text, operator_reference
                 ) VALUES (
                    :id, :pilot_id, :channel, :planned_at, :target_reference, :status,
                    :evidence_text, :operator_reference
                 )'
            );
            $statement->execute([
                'id' => $id,
                'pilot_id' => (string)$pilot['id'],
                'channel' => $distribution['channel'],
                'planned_at' => $distribution['planned_at_utc'],
                'target_reference' => $distribution['target_reference'],
                'status' => $distribution['status'],
                'evidence_text' => $distribution['evidence_text'],
                'operator_reference' => $operator,
            ]);
            $item = $pdo->prepare(
                "UPDATE startpartner_pilot_onboarding_items
                 SET status = :item_status, evidence_text = :evidence_text,
                     evidence_reference = :evidence_reference, operator_reference = :operator,
                     completed_at = CASE WHEN :is_ready = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                     revision = revision + 1
                 WHERE pilot_id = :pilot_id AND item_key = 'distribution_ready'"
            );
            $item->execute([
                'item_status' => $distribution['status'] === 'ready' ? 'complete' : 'blocked',
                'evidence_text' => $distribution['evidence_text'],
                'evidence_reference' => $id,
                'operator' => $operator,
                'is_ready' => $distribution['status'] === 'ready' ? 1 : 0,
                'pilot_id' => (string)$pilot['id'],
            ]);
            return [
                'status_reason' => $distribution['status'] === 'ready'
                    ? 'Aktueller Partner-Reichweitenstart als bereit gespeichert.'
                    : 'Partner-Reichweitenstart mit Begründung blockiert.',
                'distribution_id' => $id,
                'planned_date_local' => $distribution['planned_date_local'],
            ];
        }
    );
}
