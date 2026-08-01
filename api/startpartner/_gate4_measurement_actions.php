<?php
declare(strict_types=1);

function be_startpartner_gate4_set_measurement(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.measurement.set',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $status = strtolower(be_startpartner_gate4_required_text($input['status'] ?? null, 16, 'status'));
            if (!in_array($status, ['ready', 'blocked'], true)) {
                throw new InvalidArgumentException('measurement status must be ready or blocked.');
            }
            $link = $pdo->prepare(
                'SELECT * FROM startpartner_pilot_content_links
                 WHERE id = :id AND pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
            );
            $link->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            $content = $link->fetch(PDO::FETCH_ASSOC);
            if (!is_array($content)) {
                throw new DomainException('Pilot content link not found.');
            }
            if (!in_array((string)$content['status'], ['editorial_ready', 'approved'], true)) {
                throw new DomainException('Der Messpreflight benötigt einen redaktionell bereiten Pilotinhalt.');
            }
            $evidenceText = be_startpartner_gate4_required_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
            $technicalReadback = $status === 'ready'
                ? be_startpartner_gate4_measurement_readback($pdo, $content)
                : null;
            $id = be_startpartner_gate4_uuid();
            $statement = $pdo->prepare(
                "INSERT INTO startpartner_pilot_measurement_preflights (
                    id, pilot_id, organizer_id, content_link_id, status, metrics_owner,
                    reporting_target_type, reporting_target_id, evidence_json, checked_by
                 ) VALUES (
                    :id, :pilot_id, :organizer_id, :content_link_id, :status, 'value_metric_daily',
                    :reporting_target_type, :reporting_target_id, :evidence_json, :checked_by
                 )
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status), metrics_owner = VALUES(metrics_owner),
                    reporting_target_type = VALUES(reporting_target_type),
                    reporting_target_id = VALUES(reporting_target_id),
                    evidence_json = VALUES(evidence_json), checked_by = VALUES(checked_by),
                    checked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP"
            );
            $statement->execute([
                'id' => $id,
                'pilot_id' => (string)$pilot['id'],
                'organizer_id' => (int)$pilot['organizer_id'],
                'content_link_id' => $contentLinkId,
                'status' => $status,
                'reporting_target_type' => (string)$content['reporting_target_type'],
                'reporting_target_id' => (string)$content['reporting_target_id'],
                'evidence_json' => json_encode([
                    'evidence_text' => $evidenceText,
                    'technical_readback' => $technicalReadback,
                    'operation_id' => $operationId,
                    'pilot_id' => (string)$pilot['id'],
                    'content_link_id' => $contentLinkId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'checked_by' => $operator,
            ]);
            $item = $pdo->prepare(
                "UPDATE startpartner_pilot_onboarding_items
                 SET status = :item_status, evidence_text = :evidence_text,
                     evidence_reference = :evidence_reference, operator_reference = :operator,
                     completed_at = CASE WHEN :is_ready = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                     revision = revision + 1
                 WHERE pilot_id = :pilot_id AND item_key = 'measurement_ready'"
            );
            $item->execute([
                'item_status' => $status === 'ready' ? 'complete' : 'blocked',
                'evidence_text' => $evidenceText,
                'evidence_reference' => $contentLinkId,
                'operator' => $operator,
                'is_ready' => $status === 'ready' ? 1 : 0,
                'pilot_id' => (string)$pilot['id'],
            ]);
            return [
                'status_reason' => $status === 'ready'
                    ? 'Messpreflight technisch zurückgelesen und als bereit gespeichert.'
                    : 'Messpreflight mit Begründung blockiert.',
                'content_link_id' => $contentLinkId,
                'technical_readback' => $technicalReadback,
            ];
        }
    );
}
