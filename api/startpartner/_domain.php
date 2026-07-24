<?php
declare(strict_types=1);

require_once __DIR__ . '/_repository.php';

function be_startpartner_create_candidate(
    PDO $pdo,
    array $input,
    string $actorType,
    ?string $actorReference = null
): array {
    be_startpartner_require_schema($pdo);
    $normalized = be_startpartner_normalize_intake($input);
    $actorType = be_startpartner_validate_enum_value(
        $actorType,
        ['system', 'self_service', 'operator'],
        'actor_type'
    );

    $pdo->beginTransaction();
    try {
        $replayed = be_startpartner_find_candidate_row(
            $pdo,
            'idempotency_key_hash',
            $normalized['idempotency_key_hash'],
            true
        );
        if ($replayed !== null) {
            $pdo->commit();
            return [
                'candidate' => be_startpartner_candidate_from_row($pdo, $replayed),
                'created' => false,
                'idempotent_replay' => true,
                'duplicate_identity' => false,
            ];
        }

        $duplicate = be_startpartner_find_candidate_row(
            $pdo,
            'identity_key',
            $normalized['identity_key'],
            true
        );
        if ($duplicate !== null) {
            be_startpartner_record_event(
                $pdo,
                (string)$duplicate['id'],
                'duplicate_intake_observed',
                (string)$duplicate['status'],
                (string)$duplicate['status'],
                $actorType,
                $actorReference,
                [
                    'source' => $normalized['source'],
                    'source_reference' => $normalized['source_reference'],
                ]
            );
            $pdo->commit();
            return [
                'candidate' => be_startpartner_candidate_from_row($pdo, $duplicate),
                'created' => false,
                'idempotent_replay' => false,
                'duplicate_identity' => true,
            ];
        }

        $candidateId = be_cc_uuid();
        $candidateInsert = $pdo->prepare(
            'INSERT INTO startpartner_candidates (
                id, source, source_reference,
                organization_name, organization_name_normalized,
                website_url, description_text, desired_content_scope,
                status, status_reason,
                identity_key, idempotency_key_hash,
                privacy_policy_version, form_version,
                retention_review_at, closed_at
             ) VALUES (
                :id, :source, :source_reference,
                :organization_name, :organization_name_normalized,
                :website_url, :description_text, :desired_content_scope,
                :status, NULL,
                :identity_key, :idempotency_key_hash,
                :privacy_policy_version, :form_version,
                :retention_review_at, NULL
             )'
        );
        $candidateInsert->execute([
            'id' => $candidateId,
            'source' => $normalized['source'],
            'source_reference' => $normalized['source_reference'],
            'organization_name' => $normalized['organization_name'],
            'organization_name_normalized' => $normalized['organization_name_normalized'],
            'website_url' => $normalized['website_url'],
            'description_text' => $normalized['description_text'],
            'desired_content_scope' => $normalized['desired_content_scope'],
            'status' => 'new',
            'identity_key' => $normalized['identity_key'],
            'idempotency_key_hash' => $normalized['idempotency_key_hash'],
            'privacy_policy_version' => $normalized['privacy_policy_version'],
            'form_version' => $normalized['form_version'],
            'retention_review_at' => $normalized['retention_review_at'],
        ]);

        $contactInsert = $pdo->prepare(
            'INSERT INTO startpartner_candidate_contacts (
                candidate_id, contact_name, contact_role, email, email_normalized, phone, is_primary
             ) VALUES (
                :candidate_id, :contact_name, :contact_role, :email, :email_normalized, :phone, :is_primary
             )'
        );
        foreach ($normalized['contacts'] as $contact) {
            $contactInsert->execute([
                'candidate_id' => $candidateId,
                'contact_name' => $contact['contact_name'],
                'contact_role' => $contact['contact_role'],
                'email' => $contact['email'],
                'email_normalized' => $contact['email_normalized'],
                'phone' => $contact['phone'],
                'is_primary' => $contact['is_primary'] ? 1 : null,
            ]);
        }

        be_startpartner_record_event(
            $pdo,
            $candidateId,
            'candidate_created',
            null,
            'new',
            $actorType,
            $actorReference,
            [
                'source' => $normalized['source'],
                'desired_content_scope' => $normalized['desired_content_scope'],
                'contact_count' => count($normalized['contacts']),
                'form_version' => $normalized['form_version'],
            ]
        );
        be_startpartner_upsert_control_case(
            $pdo,
            $candidateId,
            $normalized['organization_name'],
            $normalized['source'],
            $normalized['desired_content_scope'],
            'new',
            null,
            $actorReference ?? $actorType
        );
        $pdo->commit();

        $createdRow = be_startpartner_find_candidate_row($pdo, 'id', $candidateId);
        if ($createdRow === null) {
            throw new RuntimeException('Created candidate could not be read back.');
        }
        return [
            'candidate' => be_startpartner_candidate_from_row($pdo, $createdRow),
            'created' => true,
            'idempotent_replay' => false,
            'duplicate_identity' => false,
        ];
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string)$error->getCode() === '23000') {
            $replayed = be_startpartner_find_candidate_row(
                $pdo,
                'idempotency_key_hash',
                $normalized['idempotency_key_hash']
            );
            if ($replayed !== null) {
                return [
                    'candidate' => be_startpartner_candidate_from_row($pdo, $replayed),
                    'created' => false,
                    'idempotent_replay' => true,
                    'duplicate_identity' => false,
                ];
            }
            $duplicate = be_startpartner_find_candidate_row(
                $pdo,
                'identity_key',
                $normalized['identity_key']
            );
            if ($duplicate !== null) {
                return [
                    'candidate' => be_startpartner_candidate_from_row($pdo, $duplicate),
                    'created' => false,
                    'idempotent_replay' => false,
                    'duplicate_identity' => true,
                ];
            }
        }
        throw $error;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function be_startpartner_list_candidates(PDO $pdo, array $filters = []): array
{
    be_startpartner_require_schema($pdo);
    $where = [];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params['status'] = be_startpartner_validate_enum_value(
            trim((string)$filters['status']),
            BE_STARTPARTNER_STATUSES,
            'status'
        );
    }
    if (!empty($filters['source'])) {
        $where[] = 'source = :source';
        $params['source'] = be_startpartner_validate_enum_value(
            trim((string)$filters['source']),
            BE_STARTPARTNER_SOURCES,
            'source'
        );
    }

    $limit = (int)($filters['limit'] ?? 100);
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT * FROM startpartner_candidates';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY updated_at DESC, created_at DESC LIMIT ' . $limit;

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return array_map(
        static fn(array $row): array => be_startpartner_candidate_from_row($pdo, $row, false),
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
}

function be_startpartner_get_candidate(PDO $pdo, string $candidateId): array
{
    be_startpartner_require_schema($pdo);
    $row = be_startpartner_find_candidate_row($pdo, 'id', $candidateId);
    if ($row === null) {
        throw new RuntimeException('Candidate not found.');
    }
    return be_startpartner_candidate_from_row($pdo, $row, true);
}

function be_startpartner_triage_candidate(
    PDO $pdo,
    string $candidateId,
    string $toStatus,
    ?string $reason,
    string $actorReference = 'operator'
): array {
    be_startpartner_require_schema($pdo);
    $toStatus = be_startpartner_validate_enum_value($toStatus, BE_STARTPARTNER_STATUSES, 'status');
    $reason = be_startpartner_clean_text($reason, 500, 'reason');

    if (in_array($toStatus, ['needs_information', 'waitlisted', 'routed_to_regular_product', 'rejected', 'withdrawn', 'expired'], true) && $reason === null) {
        throw new InvalidArgumentException('reason is required for this status.');
    }

    $pdo->beginTransaction();
    try {
        $row = be_startpartner_find_candidate_row($pdo, 'id', $candidateId, true);
        if ($row === null) {
            throw new RuntimeException('Candidate not found.');
        }
        $fromStatus = (string)$row['status'];
        if (!be_startpartner_transition_allowed($fromStatus, $toStatus)) {
            throw new DomainException("Transition {$fromStatus} -> {$toStatus} is not allowed.");
        }

        $closedAt = in_array($toStatus, BE_STARTPARTNER_TERMINAL_STATUSES, true)
            ? gmdate('Y-m-d H:i:s')
            : null;
        $update = $pdo->prepare(
            'UPDATE startpartner_candidates
             SET status = :status,
                 status_reason = :status_reason,
                 closed_at = :closed_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $update->execute([
            'status' => $toStatus,
            'status_reason' => $reason,
            'closed_at' => $closedAt,
            'id' => $candidateId,
        ]);

        be_startpartner_record_event(
            $pdo,
            $candidateId,
            'status_changed',
            $fromStatus,
            $toStatus,
            'operator',
            $actorReference,
            ['reason' => $reason]
        );
        be_startpartner_upsert_control_case(
            $pdo,
            $candidateId,
            (string)$row['organization_name'],
            (string)$row['source'],
            (string)$row['desired_content_scope'],
            $toStatus,
            $reason,
            $actorReference
        );
        $pdo->commit();

        return be_startpartner_get_candidate($pdo, $candidateId);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
