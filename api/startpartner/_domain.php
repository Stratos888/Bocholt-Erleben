<?php
declare(strict_types=1);

require_once __DIR__ . '/_repository.php';

function be_startpartner_existing_request_fingerprint(PDO $pdo, array $row): string
{
    $contactsStatement = $pdo->prepare(
        'SELECT contact_name, contact_role, email_normalized, phone, is_primary
         FROM startpartner_candidate_contacts
         WHERE candidate_id = :candidate_id
         ORDER BY is_primary DESC, id ASC'
    );
    $contactsStatement->execute(['candidate_id' => (string)$row['id']]);
    $contacts = array_map(
        static fn(array $contact): array => [
            'contact_name' => $contact['contact_name'] ?? null,
            'contact_role' => $contact['contact_role'] ?? null,
            'email_normalized' => (string)$contact['email_normalized'],
            'phone' => $contact['phone'] ?? null,
            'is_primary' => (bool)$contact['is_primary'],
        ],
        $contactsStatement->fetchAll(PDO::FETCH_ASSOC)
    );

    $payload = [
        'source' => (string)$row['source'],
        'source_reference' => $row['source_reference'] ?? null,
        'organization_name_normalized' => (string)$row['organization_name_normalized'],
        'contacts' => $contacts,
        'website_url' => $row['website_url'] ?? null,
        'description_text' => $row['description_text'] ?? null,
        'desired_content_scope' => (string)$row['desired_content_scope'],
        'privacy_policy_version' => $row['privacy_policy_version'] ?? null,
        'form_version' => (string)$row['form_version'],
        'retention_review_at' => (string)$row['retention_review_at'],
    ];

    return hash(
        'sha256',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
}

function be_startpartner_assert_idempotent_replay_matches(PDO $pdo, array $row, array $normalized): void
{
    $existingFingerprint = be_startpartner_existing_request_fingerprint($pdo, $row);
    if (!hash_equals($existingFingerprint, (string)$normalized['request_fingerprint'])) {
        throw new DomainException('Idempotency-Key was already used with a different request payload.');
    }
}

function be_startpartner_record_duplicate_after_race(
    PDO $pdo,
    array $normalized,
    string $actorType,
    ?string $actorReference
): ?array {
    $pdo->beginTransaction();
    try {
        $duplicate = be_startpartner_find_candidate_row(
            $pdo,
            'identity_key',
            (string)$normalized['identity_key'],
            true
        );
        if ($duplicate === null) {
            $pdo->rollBack();
            return null;
        }

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
                'detected_after_unique_conflict' => true,
            ]
        );
        $pdo->commit();
        return $duplicate;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

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
            be_startpartner_assert_idempotent_replay_matches($pdo, $replayed, $normalized);
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
                    'detected_after_unique_conflict' => false,
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
                be_startpartner_assert_idempotent_replay_matches($pdo, $replayed, $normalized);
                return [
                    'candidate' => be_startpartner_candidate_from_row($pdo, $replayed),
                    'created' => false,
                    'idempotent_replay' => true,
                    'duplicate_identity' => false,
                ];
            }

            $duplicate = be_startpartner_record_duplicate_after_race(
                $pdo,
                $normalized,
                $actorType,
                $actorReference
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
        $requestedStatus = trim((string)$filters['status']);
        if ($requestedStatus === 'decision_ready') {
            $persistedStatus = 'decision_ready';
        } else {
            $legacyStatus = be_startpartner_validate_enum_value(
                $requestedStatus,
                BE_STARTPARTNER_STATUSES,
                'status'
            );
            $persistedStatus = $legacyStatus === 'qualified' ? 'decision_ready' : $legacyStatus;
        }
        $where[] = 'status = :status';
        $params['status'] = $persistedStatus;
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
    $requestedStatus = be_startpartner_validate_enum_value($toStatus, BE_STARTPARTNER_STATUSES, 'status');
    $persistedStatus = $requestedStatus === 'qualified' ? 'decision_ready' : $requestedStatus;
    $reason = be_startpartner_clean_text($reason, 500, 'reason');

    if (in_array($requestedStatus, ['needs_information', 'waitlisted', 'routed_to_regular_product', 'rejected', 'withdrawn', 'expired'], true) && $reason === null) {
        throw new InvalidArgumentException('reason is required for this status.');
    }

    $pdo->beginTransaction();
    try {
        $row = be_startpartner_find_candidate_row($pdo, 'id', $candidateId, true);
        if ($row === null) {
            throw new RuntimeException('Candidate not found.');
        }
        $fromStatus = (string)$row['status'];
        $legacyFromStatus = $fromStatus === 'decision_ready' ? 'qualified' : $fromStatus;
        if (!be_startpartner_transition_allowed($legacyFromStatus, $requestedStatus)) {
            throw new DomainException("Transition {$fromStatus} -> {$persistedStatus} is not allowed.");
        }

        $closedAt = in_array($requestedStatus, BE_STARTPARTNER_TERMINAL_STATUSES, true)
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
            'status' => $persistedStatus,
            'status_reason' => $reason,
            'closed_at' => $closedAt,
            'id' => $candidateId,
        ]);

        be_startpartner_record_event(
            $pdo,
            $candidateId,
            'status_changed',
            $fromStatus,
            $persistedStatus,
            'operator',
            $actorReference,
            ['reason' => $reason, 'legacy_requested_status' => $requestedStatus]
        );
        be_startpartner_upsert_control_case(
            $pdo,
            $candidateId,
            (string)$row['organization_name'],
            (string)$row['source'],
            (string)$row['desired_content_scope'],
            $requestedStatus,
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
