<?php
declare(strict_types=1);

function be_startpartner_gate4_run_operation(
    PDO $pdo,
    string $candidateId,
    string $action,
    array $input,
    callable $mutation,
    bool $syncReadiness = true
): array {
    be_startpartner_require_schema($pdo);
    be_startpartner_gate4_require_schema($pdo);
    $operationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $operatorName = be_startpartner_gate2_operator_name($input['operator_name'] ?? null);
    $expectedCandidateRevision = be_startpartner_gate2_expected_revision($input['expected_revision'] ?? null);
    $expectedPilotRevision = be_startpartner_gate4_expected_pilot_revision($input['expected_pilot_revision'] ?? null);
    $payloadHash = be_startpartner_gate2_payload_hash($candidateId, $action, $input);

    $pdo->beginTransaction();
    try {
        $operationStatement = $pdo->prepare(
            'SELECT * FROM startpartner_candidate_operations WHERE operation_id = :operation_id FOR UPDATE'
        );
        $operationStatement->execute(['operation_id' => $operationId]);
        $existingOperation = $operationStatement->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingOperation)) {
            if (
                (string)$existingOperation['candidate_id'] !== $candidateId
                || (string)$existingOperation['action'] !== $action
                || !hash_equals((string)$existingOperation['payload_hash'], $payloadHash)
            ) {
                throw new BeStartpartnerConflictException('Diese Änderung wurde bereits mit anderen Angaben verwendet.');
            }
            if ((string)$existingOperation['status'] !== 'completed' || $existingOperation['result_json'] === null) {
                throw new BeStartpartnerConflictException('Diese Änderung kann nicht erneut ausgeführt werden.');
            }
            $result = json_decode((string)$existingOperation['result_json'], true, 512, JSON_THROW_ON_ERROR);
            $result['idempotent_replay'] = true;
            $pdo->commit();
            return $result;
        }

        $candidate = be_startpartner_gate2_candidate_row($pdo, $candidateId, true);
        $pilot = be_startpartner_gate4_pilot_row($pdo, $candidateId, true);
        if ((int)$candidate['revision'] !== $expectedCandidateRevision || (int)$pilot['revision'] !== $expectedPilotRevision) {
            $pdo->rollBack();
            throw new BeStartpartnerConflictException(
                'Der Startpartner-Fall wurde zwischenzeitlich geändert.',
                be_startpartner_gate4_candidate_detail($pdo, $candidateId)
            );
        }
        $insertOperation = $pdo->prepare(
            'INSERT INTO startpartner_candidate_operations (
                operation_id, candidate_id, action, payload_hash, status, candidate_revision_before
             ) VALUES (
                :operation_id, :candidate_id, :action, :payload_hash, :status, :candidate_revision_before
             )'
        );
        $insertOperation->execute([
            'operation_id' => $operationId,
            'candidate_id' => $candidateId,
            'action' => $action,
            'payload_hash' => $payloadHash,
            'status' => 'started',
            'candidate_revision_before' => $expectedCandidateRevision,
        ]);

        $gate3 = be_startpartner_gate3_state($pdo, $candidateId, false);
        be_startpartner_gate4_seed_onboarding_items($pdo, $gate3, $operatorName);
        $meta = $mutation($pdo, $candidate, $pilot, $operatorName, $operationId, $input);
        if ($syncReadiness) {
            be_startpartner_gate4_sync_activation_ready($pdo, $candidateId);
        }

        $newCandidateRevision = $expectedCandidateRevision + 1;
        $newPilotRevision = $expectedPilotRevision + 1;
        be_startpartner_gate2_update_candidate($pdo, $candidateId, [
            'revision' => $newCandidateRevision,
            'status_reason' => (string)($meta['status_reason'] ?? 'Piloteinrichtung aktualisiert.'),
        ]);
        $pilotRevision = $pdo->prepare(
            'UPDATE startpartner_pilots SET revision = :revision WHERE id = :id'
        );
        $pilotRevision->execute(['revision' => $newPilotRevision, 'id' => (string)$pilot['id']]);

        $detail = be_startpartner_gate4_candidate_detail($pdo, $candidateId);
        be_startpartner_gate4_project_control_case($pdo, $detail, $detail['gate4'], $operatorName);
        $result = [
            'candidate' => be_startpartner_gate4_candidate_detail($pdo, $candidateId),
            'operation_id' => $operationId,
            'idempotent_replay' => false,
            'meta' => is_array($meta) ? $meta : [],
        ];
        $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $complete = $pdo->prepare(
            "UPDATE startpartner_candidate_operations
             SET status = 'completed', result_json = :result_json,
                 candidate_revision_after = :revision_after,
                 completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE operation_id = :operation_id"
        );
        $complete->execute([
            'result_json' => $resultJson,
            'revision_after' => $newCandidateRevision,
            'operation_id' => $operationId,
        ]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function be_startpartner_gate4_update_onboarding(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.onboarding.item',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $itemKey = be_startpartner_gate4_manual_onboarding_key($input['item_key'] ?? null);
            $status = be_startpartner_gate4_item_status($input['status'] ?? null);
            if (!in_array($status, ['pending', 'complete', 'blocked'], true)) {
                throw new InvalidArgumentException('Für einen manuellen Schritt sind nur offen, erledigt oder Klärung nötig zulässig.');
            }
            $evidenceText = be_startpartner_gate4_optional_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
            $evidenceReference = be_startpartner_gate4_optional_text($input['evidence_reference'] ?? null, 2048, 'evidence_reference');
            if (in_array($status, ['complete', 'blocked'], true) && $evidenceText === null && $evidenceReference === null) {
                throw new InvalidArgumentException('Für einen erledigten oder offenen Schritt ist ein Nachweis beziehungsweise eine Begründung erforderlich.');
            }
            $statement = $pdo->prepare(
                "UPDATE startpartner_pilot_onboarding_items
                 SET status = :status, evidence_text = :evidence_text,
                     evidence_reference = :evidence_reference,
                     operator_reference = :operator_reference,
                     completed_at = CASE WHEN :status_complete = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                     revision = revision + 1
                 WHERE pilot_id = :pilot_id AND item_key = :item_key"
            );
            $statement->execute([
                'status' => $status,
                'evidence_text' => $evidenceText,
                'evidence_reference' => $evidenceReference,
                'operator_reference' => $operator,
                'status_complete' => $status === 'complete' ? 1 : 0,
                'pilot_id' => (string)$pilot['id'],
                'item_key' => $itemKey,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Der Schritt der Piloteinrichtung konnte nicht gespeichert werden.');
            }
            $event = $pdo->prepare(
                'INSERT INTO startpartner_pilot_events (pilot_id, event_type, actor_reference, payload_json)
                 VALUES (:pilot_id, :event_type, :actor_reference, :payload_json)'
            );
            $event->execute([
                'pilot_id' => (string)$pilot['id'],
                'event_type' => 'gate4_onboarding_item_updated',
                'actor_reference' => $operator,
                'payload_json' => json_encode([
                    'operation_id' => $operationId,
                    'item_key' => $itemKey,
                    'status' => $status,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            return [
                'status_reason' => 'Schritt der Piloteinrichtung aktualisiert.',
                'item_key' => $itemKey,
            ];
        }
    );
}
