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
            return [
                'status_reason' => 'Erster Pilotinhalt ist redaktionell bereit.',
                'content_link_id' => $contentLinkId,
            ];
        }
    );
}
