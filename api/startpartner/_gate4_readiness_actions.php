<?php
declare(strict_types=1);

function be_startpartner_gate4_mark_content_ready(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.content.editorial_ready',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $statement = $pdo->prepare(
                "UPDATE startpartner_pilot_content_links pcl
                 INNER JOIN submissions s ON s.id = pcl.submission_id
                 SET pcl.status = 'editorial_ready', pcl.editorial_ready_at = CURRENT_TIMESTAMP,
                     s.status = 'in_review', s.review_started_at = COALESCE(s.review_started_at, CURRENT_TIMESTAMP)
                 WHERE pcl.id = :id AND pcl.pilot_id = :pilot_id
                   AND pcl.status IN ('draft','editorial_ready')
                   AND s.status IN ('pending_review','in_review')"
            );
            $statement->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            if ($statement->rowCount() < 1) {
                $check = $pdo->prepare(
                    "SELECT status FROM startpartner_pilot_content_links WHERE id = :id AND pilot_id = :pilot_id"
                );
                $check->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
                if ((string)$check->fetchColumn() !== 'editorial_ready') {
                    throw new DomainException('Pilot content cannot be marked editorially ready.');
                }
            }
            foreach (['first_content_ready', 'editorial_review_ready'] as $itemKey) {
                $item = $pdo->prepare(
                    "UPDATE startpartner_pilot_onboarding_items
                     SET status = 'complete', evidence_text = :evidence_text,
                         evidence_reference = :evidence_reference, operator_reference = :operator,
                         completed_at = CURRENT_TIMESTAMP, revision = revision + 1
                     WHERE pilot_id = :pilot_id AND item_key = :item_key"
                );
                $item->execute([
                    'evidence_text' => 'Linked submission passed editorial readiness review.',
                    'evidence_reference' => $contentLinkId,
                    'operator' => $operator,
                    'pilot_id' => (string)$pilot['id'],
                    'item_key' => $itemKey,
                ]);
            }
            return ['status_reason' => 'Erster Pilotinhalt ist redaktionell bereit.', 'content_link_id' => $contentLinkId];
        }
    );
}

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
                'SELECT * FROM startpartner_pilot_content_links WHERE id = :id AND pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
            );
            $link->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            $content = $link->fetch(PDO::FETCH_ASSOC);
            if (!is_array($content)) {
                throw new DomainException('Pilot content link not found.');
            }
            $evidenceText = be_startpartner_gate4_required_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
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
            return ['status_reason' => 'Messpreflight aktualisiert.', 'content_link_id' => $contentLinkId];
        }
    );
}

function be_startpartner_gate4_set_distribution(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.distribution.set',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $channel = be_startpartner_gate4_required_text($input['channel'] ?? null, 64, 'channel');
            $targetReference = be_startpartner_gate4_required_text($input['target_reference'] ?? null, 2048, 'target_reference');
            $plannedAtText = be_startpartner_gate4_required_text($input['planned_at'] ?? null, 64, 'planned_at');
            $plannedAt = (new DateTimeImmutable($plannedAtText))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $status = strtolower(be_startpartner_gate4_required_text($input['status'] ?? null, 16, 'status'));
            if (!in_array($status, ['ready', 'blocked'], true)) {
                throw new InvalidArgumentException('distribution status must be ready or blocked.');
            }
            $evidenceText = be_startpartner_gate4_optional_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
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
                'channel' => $channel,
                'planned_at' => $plannedAt,
                'target_reference' => $targetReference,
                'status' => $status,
                'evidence_text' => $evidenceText,
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
                'item_status' => $status === 'ready' ? 'complete' : 'blocked',
                'evidence_text' => $evidenceText ?? ($channel . ': ' . $targetReference),
                'evidence_reference' => $id,
                'operator' => $operator,
                'is_ready' => $status === 'ready' ? 1 : 0,
                'pilot_id' => (string)$pilot['id'],
            ]);
            return ['status_reason' => 'Partnerdistribution aktualisiert.', 'distribution_id' => $id];
        }
    );
}
