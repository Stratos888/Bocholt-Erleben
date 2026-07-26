<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate2_domain.php';

const BE_GATE2_FINAL_PREFIX = 'GATE2_SYNTHETIC_199_FINAL_';
const BE_GATE2_FINAL_OPERATION_PREFIX = 'gate2:199:staging-final-';
const BE_GATE2_FINAL_ORIGIN = 'https://staging.bocholt-erleben.de';
const BE_GATE2_FINAL_LOCK = 'bocholt_gate2_staging_final_199';
const BE_GATE2_FINAL_MARKER = '199_gate2_staging_lifecycle_completed';
const BE_GATE2_FINAL_SMOKE_UA = 'Bocholt-Erleben-Deploy-Smoke/1.0';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_gate2_final_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function be_gate2_final_scalar(PDOStatement $statement): mixed
{
    $value = $statement->fetchColumn();
    $statement->closeCursor();
    return $value;
}

function be_gate2_final_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)be_gate2_final_scalar($statement) === 1;
}

function be_gate2_final_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!be_gate2_final_table_exists($pdo, $table)) {
        return -1;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where
    );
    $statement->execute($params);
    return (int)be_gate2_final_scalar($statement);
}

function be_gate2_final_marker_exists(PDO $pdo): bool
{
    return be_gate2_final_count(
        $pdo,
        'app_schema_migrations',
        'migration_key = :marker_key',
        ['marker_key' => BE_GATE2_FINAL_MARKER]
    ) === 1;
}

function be_gate2_final_write_marker(PDO $pdo, array $evidence): void
{
    $description = 'Gate 2 staging lifecycle completed; evidence sha256=' . hash(
        'sha256',
        json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
    $statement = $pdo->prepare(
        'INSERT INTO app_schema_migrations (migration_key, description)
         VALUES (:marker_key, :description)
         ON DUPLICATE KEY UPDATE description = VALUES(description)'
    );
    $statement->execute([
        'marker_key' => BE_GATE2_FINAL_MARKER,
        'description' => $description,
    ]);
}

function be_gate2_final_locked_counts(PDO $pdo): array
{
    $tables = [
        'organizers',
        'submissions',
        'subscriptions',
        'publication_' . 'entitlements',
        'publication_' . 'consumptions',
    ];
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = be_gate2_final_count($pdo, $table);
    }
    return $counts;
}

function be_gate2_final_http(
    string $method,
    string $path,
    ?array $body,
    string $reviewPassword,
    array $extraHeaders = []
): array {
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-BE-Review-Password: ' . $reviewPassword,
        ...$extraHeaders,
    ];
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 45,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ];
    if ($body !== null) {
        $options['http']['content'] = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    $context = stream_context_create($options);
    $raw = @file_get_contents(BE_GATE2_FINAL_ORIGIN . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
            $status = (int)$match[1];
        }
    }
    if ($raw === false && $status === 0) {
        throw new RuntimeException('HTTP request failed without response: ' . $method . ' ' . $path);
    }
    $decoded = json_decode((string)$raw, true);
    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : ['raw' => (string)$raw],
    ];
}

function be_gate2_final_expect(array $response, array $statuses, string $label): array
{
    be_gate2_final_assert(
        in_array((int)$response['status'], $statuses, true),
        $label . ' returned HTTP ' . (int)$response['status'] . ': ' . json_encode($response['body'])
    );
    return $response['body'];
}

function be_gate2_final_operation(string $suffix, int $revision): array
{
    return [
        'operation_id' => BE_GATE2_FINAL_OPERATION_PREFIX . $suffix,
        'expected_revision' => $revision,
        'operator_name' => 'Gate 2 Final Staging Lifecycle #199',
    ];
}

function be_gate2_final_intake(string $suffix, string $reviewPassword): array
{
    $payload = [
        'source' => 'targeted_outreach',
        'source_reference' => 'issue-199:final:' . $suffix,
        'organization_name' => BE_GATE2_FINAL_PREFIX . strtoupper($suffix),
        'website_url' => 'https://example.org/gate2-final-' . rawurlencode($suffix),
        'description_text' => 'Synthetischer finaler Gate-2-Stagingnachweis ohne externe Kommunikation.',
        'desired_content_scope' => 'both',
        'form_version' => 'gate2-staging-final-199-v1',
        'retention_review_at' => (new DateTimeImmutable(
            '+60 days',
            new DateTimeZone('UTC')
        ))->format(DateTimeInterface::ATOM),
        'contacts' => [[
            'contact_name' => 'Synthetischer finaler Kontakt ' . $suffix,
            'contact_role' => 'Testkontakt',
            'email' => 'gate2-199-final-' . strtolower($suffix) . '@example.org',
            'phone' => '0000 199',
            'is_primary' => true,
        ]],
    ];
    $response = be_gate2_final_http(
        'POST',
        '/api/startpartner/intake.php',
        $payload,
        $reviewPassword,
        ['Idempotency-Key: ' . BE_GATE2_FINAL_OPERATION_PREFIX . 'intake-' . $suffix]
    );
    $body = be_gate2_final_expect($response, [200, 201], 'Intake ' . $suffix);
    $candidate = $body['data']['candidate'] ?? null;
    be_gate2_final_assert(
        is_array($candidate) && !empty($candidate['id']),
        'Intake read-back is missing candidate.'
    );
    return ['payload' => $payload, 'body' => $body, 'candidate' => $candidate];
}

function be_gate2_final_qualifications(): array
{
    $hard = [
        'local_relevance',
        'organization_contact',
        'content_sources',
        'editorial_fit',
        'legal_technical',
        'required_information',
    ];
    $dimensions = [
        'local_relevance',
        'organization_contact',
        'content_sources',
        'editorial_fit',
        'content_leverage',
        'reach_leverage',
        'user_need',
        'maintenance_capability',
        'cooperation_readiness',
        'setup_effort',
        'support_effort',
        'regular_path',
        'legal_technical',
        'required_information',
    ];
    return array_map(
        static fn(string $dimension): array => [
            'dimension' => $dimension,
            'assessment' => in_array($dimension, $hard, true) ? 'adequate' : 'weak',
            'reason' => 'Synthetische finale Begründung für ' . $dimension,
            'evidence_text' => 'Synthetische finale Evidence für ' . $dimension,
        ],
        $dimensions
    );
}

function be_gate2_final_ready_candidate(string $suffix, string $reviewPassword): array
{
    $intake = be_gate2_final_intake($suffix, $reviewPassword);
    $candidate = $intake['candidate'];
    $id = (string)$candidate['id'];

    $qualification = be_gate2_final_expect(
        be_gate2_final_http(
            'POST',
            '/api/startpartner/qualification.php',
            [
                'candidate_id' => $id,
                ...be_gate2_final_operation($suffix . '-qualification', 1),
                'qualifications' => be_gate2_final_qualifications(),
            ],
            $reviewPassword
        ),
        [200],
        'Qualification ' . $suffix
    );
    be_gate2_final_assert(
        ($qualification['data']['candidate']['readiness']['ready'] ?? false) === true,
        'Candidate is not ready after qualification.'
    );

    be_gate2_final_expect(
        be_gate2_final_http(
            'POST',
            '/api/startpartner/action.php',
            [
                'candidate_id' => $id,
                ...be_gate2_final_operation($suffix . '-start-qualification', 2),
                'action' => 'start_qualification',
            ],
            $reviewPassword
        ),
        [200],
        'Start qualification ' . $suffix
    );

    $ready = be_gate2_final_expect(
        be_gate2_final_http(
            'POST',
            '/api/startpartner/action.php',
            [
                'candidate_id' => $id,
                ...be_gate2_final_operation($suffix . '-decision-ready', 3),
                'action' => 'mark_decision_ready',
            ],
            $reviewPassword
        ),
        [200],
        'Decision ready ' . $suffix
    );
    be_gate2_final_assert(
        ($ready['data']['candidate']['status'] ?? '') === 'decision_ready',
        'Candidate did not reach decision_ready.'
    );
    return $ready['data']['candidate'];
}

function be_gate2_final_seed_reservation(PDO $pdo, int $sequence): string
{
    $id = sprintf('19900000-0000-0000-0002-%012d', $sequence);
    $organization = BE_GATE2_FINAL_PREFIX . 'CAPACITY_' . $sequence;
    $email = 'gate2-199-final-capacity-' . $sequence . '@example.org';

    $candidate = $pdo->prepare(
        'INSERT INTO startpartner_candidates (
            id, source, source_reference, organization_name, organization_name_normalized,
            desired_content_scope, status, identity_key, idempotency_key_hash,
            form_version, retention_review_at, revision, assigned_to
         ) VALUES (
            :id, \'targeted_outreach\', :source_reference, :organization_name,
            :organization_name_normalized, \'both\', \'accepted_pending_terms\',
            :identity_key, :idempotency_key_hash, \'gate2-staging-final-199-v1\',
            DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 DAY), 1,
            \'Gate 2 Final Staging Lifecycle #199\'
         )'
    );
    $candidate->execute([
        'id' => $id,
        'source_reference' => 'issue-199:final-capacity-' . $sequence,
        'organization_name' => $organization,
        'organization_name_normalized' => strtolower($organization),
        'identity_key' => hash('sha256', 'gate2-199-final-capacity-identity-' . $sequence),
        'idempotency_key_hash' => hash(
            'sha256',
            'gate2-199-final-capacity-idempotency-' . $sequence
        ),
    ]);

    $contact = $pdo->prepare(
        'INSERT INTO startpartner_candidate_contacts (
            candidate_id, contact_name, contact_role, email, email_normalized, is_primary
         ) VALUES (
            :candidate_id, \'Synthetischer finaler Kapazitätskontakt\',
            \'Testkontakt\', :email, :email_normalized, 1
         )'
    );
    $contact->execute([
        'candidate_id' => $id,
        'email' => $email,
        'email_normalized' => strtolower($email),
    ]);

    $reservation = $pdo->prepare(
        'INSERT INTO startpartner_candidate_reservations (
            candidate_id, status, starts_at, ends_at,
            capacity_snapshot_json, operator_reference
         ) VALUES (
            :candidate_id, \'active\', UTC_TIMESTAMP(),
            DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 DAY),
            :capacity_snapshot_json, \'Gate 2 Final Staging Lifecycle #199\'
         )'
    );
    $reservation->execute([
        'candidate_id' => $id,
        'capacity_snapshot_json' => '{}',
    ]);
    return $id;
}

function be_gate2_final_candidate_ids(PDO $pdo): array
{
    if (!be_gate2_final_table_exists($pdo, 'startpartner_candidates')) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT id FROM startpartner_candidates
         WHERE organization_name LIKE :candidate_prefix ORDER BY id'
    );
    $statement->execute(['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']);
    $ids = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    $statement->closeCursor();
    return $ids;
}

function be_gate2_final_residue(PDO $pdo): array
{
    $candidateSubquery = 'candidate_id IN (
        SELECT id FROM startpartner_candidates
        WHERE organization_name LIKE :candidate_prefix
    )';
    $residue = [
        'candidates' => be_gate2_final_count(
            $pdo,
            'startpartner_candidates',
            'organization_name LIKE :candidate_prefix',
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'contacts' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_contacts',
            $candidateSubquery,
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'events' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_events',
            $candidateSubquery,
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'qualifications' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_qualifications',
            $candidateSubquery,
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'decisions' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_decisions',
            $candidateSubquery,
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'reservations' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_reservations',
            $candidateSubquery,
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'waitlist' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_waitlist',
            $candidateSubquery,
            ['candidate_prefix' => BE_GATE2_FINAL_PREFIX . '%']
        ),
        'operations' => be_gate2_final_count(
            $pdo,
            'startpartner_candidate_operations',
            'operation_id LIKE :operation_prefix',
            ['operation_prefix' => BE_GATE2_FINAL_OPERATION_PREFIX . '%']
        ),
        'control_cases' => be_gate2_final_count(
            $pdo,
            'control_cases',
            "source_system = 'startpartner_candidate'
             AND (
                title LIKE :title_prefix
                OR object_title LIKE :object_title_prefix
             )",
            [
                'title_prefix' => '%Startpartner prüfen: ' . BE_GATE2_FINAL_PREFIX . '%',
                'object_title_prefix' => BE_GATE2_FINAL_PREFIX . '%',
            ]
        ),
    ];
    $residue['total'] = array_sum(array_filter(
        $residue,
        static fn(int $count): bool => $count > 0
    ));
    return $residue;
}

function be_gate2_final_cleanup(PDO $pdo): array
{
    $ids = be_gate2_final_candidate_ids($pdo);
    if ($ids !== [] && be_gate2_final_table_exists($pdo, 'control_cases')) {
        $placeholdersA = implode(',', array_fill(0, count($ids), '?'));
        $placeholdersB = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare(
            "DELETE FROM control_cases
             WHERE source_system = 'startpartner_candidate'
               AND (
                   source_reference IN ({$placeholdersA})
                   OR object_id IN ({$placeholdersB})
               )"
        );
        $statement->execute([...$ids, ...$ids]);
    }
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare(
            "DELETE FROM startpartner_candidates WHERE id IN ({$placeholders})"
        );
        $statement->execute($ids);
    }

    return [
        'deleted_candidate_ids' => $ids,
        'residue' => be_gate2_final_residue($pdo),
    ];
}

function be_gate2_final_run(PDO $pdo): array
{
    if (be_gate2_final_marker_exists($pdo)) {
        return [
            'status' => 'ALREADY_COMPLETED',
            'workpack_issue' => 199,
            'marker' => BE_GATE2_FINAL_MARKER,
            'residue' => be_gate2_final_residue($pdo),
            'checked_at' => gmdate(DateTimeInterface::ATOM),
        ];
    }

    $lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $lockStatement->execute(['lock_name' => BE_GATE2_FINAL_LOCK]);
    if ((int)be_gate2_final_scalar($lockStatement) !== 1) {
        return [
            'status' => 'LOCKED',
            'workpack_issue' => 199,
            'message' => 'Final Gate 2 lifecycle is already running.',
            'checked_at' => gmdate(DateTimeInterface::ATOM),
        ];
    }

    $evidence = [
        'status' => 'FAIL',
        'workpack_issue' => 199,
        'attempt' => 2,
        'environment' => be_app_env_value(),
        'prefix' => BE_GATE2_FINAL_PREFIX,
        'operation_prefix' => BE_GATE2_FINAL_OPERATION_PREFIX,
        'marker' => BE_GATE2_FINAL_MARKER,
        'started_at' => gmdate(DateTimeInterface::ATOM),
    ];
    $failure = null;

    try {
        be_startpartner_require_schema($pdo);
        $beforeResidue = be_gate2_final_residue($pdo);
        be_gate2_final_assert(
            ($beforeResidue['total'] ?? -1) === 0,
            'Final synthetic before-state is not empty.'
        );
        be_gate2_final_assert(
            be_gate2_final_count(
                $pdo,
                'app_schema_migrations',
                'migration_key = :migration_key',
                ['migration_key' => '009_control_center_runtime_schema']
            ) === 1,
            'Migration 009 is not registered.'
        );
        be_gate2_final_assert(
            be_gate2_final_count(
                $pdo,
                'app_schema_migrations',
                'migration_key = :migration_key',
                ['migration_key' => '010_startpartner_gate2_qualification_capacity']
            ) === 1,
            'Migration 010 is not registered.'
        );

        $evidence['before'] = [
            'residue' => $beforeResidue,
            'locked_counts' => be_gate2_final_locked_counts($pdo),
            'capacity' => be_startpartner_gate2_capacity($pdo),
        ];
        be_gate2_final_assert(
            (int)($evidence['before']['capacity']['active_reservations'] ?? -1) === 0,
            'Staging capacity is not empty before the final synthetic lifecycle.'
        );

        $reviewPassword = be_review_password();
        $primaryIntake = be_gate2_final_intake('primary', $reviewPassword);
        $primaryId = (string)$primaryIntake['candidate']['id'];
        be_gate2_final_assert(
            ($primaryIntake['body']['data']['created'] ?? false) === true,
            'Primary candidate was not created.'
        );

        $intakeReplay = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/intake.php',
                $primaryIntake['payload'],
                $reviewPassword,
                ['Idempotency-Key: ' . BE_GATE2_FINAL_OPERATION_PREFIX . 'intake-primary']
            ),
            [200],
            'Intake replay'
        );
        be_gate2_final_assert(
            ($intakeReplay['data']['idempotent_replay'] ?? false) === true,
            'Intake replay was not idempotent.'
        );
        be_gate2_final_assert(
            ($intakeReplay['data']['candidate']['id'] ?? '') === $primaryId,
            'Intake replay returned another candidate.'
        );

        $conflictingPayload = $primaryIntake['payload'];
        $conflictingPayload['description_text'] = 'Abweichender finaler Payload.';
        $payloadConflict = be_gate2_final_http(
            'POST',
            '/api/startpartner/intake.php',
            $conflictingPayload,
            $reviewPassword,
            ['Idempotency-Key: ' . BE_GATE2_FINAL_OPERATION_PREFIX . 'intake-primary']
        );
        be_gate2_final_expect($payloadConflict, [422], 'Intake payload conflict');

        $profilePayload = [
            'candidate_id' => $primaryId,
            ...be_gate2_final_operation('primary-profile', 1),
            'assigned_to' => 'Gate 2 Final Staging Lifecycle #199',
            'website_url' => 'example.org/gate2-final-primary-updated',
            'next_review_at' => (new DateTimeImmutable(
                '+7 days',
                new DateTimeZone('UTC')
            ))->format(DateTimeInterface::ATOM),
        ];
        $profile = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/profile.php',
                $profilePayload,
                $reviewPassword
            ),
            [200],
            'Profile update'
        );
        be_gate2_final_assert(
            (int)($profile['data']['candidate']['revision'] ?? 0) === 2,
            'Profile update did not produce revision 2.'
        );
        $profileReplay = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/profile.php',
                $profilePayload,
                $reviewPassword
            ),
            [200],
            'Profile replay'
        );
        be_gate2_final_assert(
            ($profileReplay['data']['idempotent_replay'] ?? false) === true,
            'Profile replay was not idempotent.'
        );

        $stale = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/profile.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-stale', 1),
                    'assigned_to' => 'Stale final write',
                ],
                $reviewPassword
            ),
            [409],
            'Stale revision'
        );
        be_gate2_final_assert(
            ($stale['code'] ?? '') === 'STARTPARTNER_CONFLICT',
            'Stale revision did not return the stable conflict code.'
        );

        $qualification = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/qualification.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-qualification', 2),
                    'qualifications' => be_gate2_final_qualifications(),
                ],
                $reviewPassword
            ),
            [200],
            'Primary qualification'
        );
        be_gate2_final_assert(
            (int)($qualification['data']['candidate']['revision'] ?? 0) === 3,
            'Qualification did not produce revision 3.'
        );
        be_gate2_final_assert(
            ($qualification['data']['candidate']['readiness']['ready'] ?? false) === true,
            'Primary candidate is not ready after qualification.'
        );
        be_gate2_final_assert(
            count($qualification['data']['candidate']['qualifications'] ?? []) === 14,
            'Primary qualification does not contain 14 dimensions.'
        );

        be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-start', 3),
                    'action' => 'start_qualification',
                ],
                $reviewPassword
            ),
            [200],
            'Primary start qualification'
        );
        be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-ready', 4),
                    'action' => 'mark_decision_ready',
                ],
                $reviewPassword
            ),
            [200],
            'Primary decision ready'
        );

        $accepted = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-accept', 5),
                    'action' => 'accept_pending_terms',
                    'reason' => 'Synthetische finale Eignung.',
                    'reservation_ends_at' => (new DateTimeImmutable(
                        '+20 days',
                        new DateTimeZone('UTC')
                    ))->format(DateTimeInterface::ATOM),
                ],
                $reviewPassword
            ),
            [200],
            'Primary reservation'
        );
        be_gate2_final_assert(
            ($accepted['data']['candidate']['status'] ?? '') === 'accepted_pending_terms',
            'Primary reservation status is wrong.'
        );
        be_gate2_final_assert(
            ($accepted['data']['candidate']['decision']['result'] ?? '') ===
                'accepted_pending_terms',
            'Primary decision read-back is missing.'
        );
        be_gate2_final_assert(
            is_array($accepted['data']['candidate']['active_reservation'] ?? null),
            'Primary active reservation is missing.'
        );

        $extended = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-extend', 6),
                    'action' => 'extend_reservation',
                    'reason' => 'Synthetische finale Verlängerung.',
                    'reservation_ends_at' => (new DateTimeImmutable(
                        '+25 days',
                        new DateTimeZone('UTC')
                    ))->format(DateTimeInterface::ATOM),
                ],
                $reviewPassword
            ),
            [200],
            'Primary reservation extension'
        );
        be_gate2_final_assert(
            count($extended['data']['candidate']['reservations'] ?? []) === 2,
            'Reservation history does not contain two rows.'
        );

        $released = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-release', 7),
                    'action' => 'release_reservation',
                    'reason' => 'Synthetische finale Freigabe.',
                    'target_status' => 'decision_ready',
                ],
                $reviewPassword
            ),
            [200],
            'Primary reservation release'
        );
        be_gate2_final_assert(
            ($released['data']['candidate']['status'] ?? '') === 'decision_ready',
            'Released candidate did not return to decision_ready.'
        );
        be_gate2_final_assert(
            ($released['data']['candidate']['active_reservation'] ?? null) === null,
            'Released candidate still has an active reservation.'
        );

        $downgrade = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/qualification.php',
                [
                    'candidate_id' => $primaryId,
                    ...be_gate2_final_operation('primary-downgrade', 8),
                    'qualifications' => [[
                        'dimension' => 'local_relevance',
                        'assessment' => 'weak',
                        'reason' => 'Synthetischer finaler Mindestblocker.',
                        'evidence_text' => 'Synthetische finale geänderte Evidence.',
                    ]],
                ],
                $reviewPassword
            ),
            [200],
            'Primary readiness revocation'
        );
        be_gate2_final_assert(
            ($downgrade['data']['candidate']['status'] ?? '') === 'qualifying',
            'Readiness was not revoked fail-closed.'
        );
        be_gate2_final_assert(
            ($downgrade['data']['candidate']['readiness']['ready'] ?? true) === false,
            'Downgraded candidate is still ready.'
        );

        $waitCandidate = be_gate2_final_ready_candidate('waitlist', $reviewPassword);
        $waitlist = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $waitCandidate['id'],
                    ...be_gate2_final_operation('waitlist-action', 4),
                    'action' => 'waitlist',
                    'reason' => 'Synthetische finale Kapazitätsentscheidung.',
                    'eligibility_reason' => 'Synthetisch final geeignet.',
                    'priority_reason' => 'Synthetischer finaler lokaler Mehrwert.',
                    'next_review_at' => (new DateTimeImmutable(
                        '+14 days',
                        new DateTimeZone('UTC')
                    ))->format(DateTimeInterface::ATOM),
                    'contact_status' => 'paused',
                    'regular_alternative' => 'Synthetischer finaler regulärer Alternativweg',
                ],
                $reviewPassword
            ),
            [200],
            'Waitlist action'
        );
        be_gate2_final_assert(
            ($waitlist['data']['candidate']['status'] ?? '') === 'waitlisted',
            'Waitlist candidate status is wrong.'
        );
        be_gate2_final_assert(
            is_array($waitlist['data']['candidate']['waitlist'] ?? null),
            'Waitlist owner is missing.'
        );
        be_gate2_final_assert(
            ($waitlist['data']['candidate']['decision']['result'] ?? '') === 'waitlisted',
            'Waitlist decision is missing.'
        );

        $capacityBefore = be_gate2_final_expect(
            be_gate2_final_http(
                'GET',
                '/api/startpartner/capacity.php',
                null,
                $reviewPassword
            ),
            [200],
            'Capacity before fixtures'
        );
        be_gate2_final_assert(
            (int)($capacityBefore['data']['active_reservations'] ?? -1) === 0,
            'Capacity before fixtures is not zero.'
        );

        $fixtureIds = [];
        for ($sequence = 1; $sequence <= 6; $sequence++) {
            $fixtureIds[] = be_gate2_final_seed_reservation($pdo, $sequence);
        }

        $softCandidate = be_gate2_final_ready_candidate('soft-stop', $reviewPassword);
        be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $softCandidate['id'],
                    ...be_gate2_final_operation('soft-stop-rejected', 4),
                    'action' => 'accept_pending_terms',
                    'reason' => 'Synthetische finale Eignung.',
                    'reservation_ends_at' => (new DateTimeImmutable(
                        '+10 days',
                        new DateTimeZone('UTC')
                    ))->format(DateTimeInterface::ATOM),
                ],
                $reviewPassword
            ),
            [422],
            'Soft stop without exception reason'
        );

        $softAccepted = be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $softCandidate['id'],
                    ...be_gate2_final_operation('soft-stop-accepted', 4),
                    'action' => 'accept_pending_terms',
                    'reason' => 'Synthetische finale Eignung.',
                    'capacity_exception_reason' =>
                        'Kontrollierte siebte synthetische Reservierung.',
                    'reservation_ends_at' => (new DateTimeImmutable(
                        '+10 days',
                        new DateTimeZone('UTC')
                    ))->format(DateTimeInterface::ATOM),
                ],
                $reviewPassword
            ),
            [200],
            'Soft stop with exception reason'
        );
        be_gate2_final_assert(
            (int)($softAccepted['data']['candidate']['capacity']['active_reservations'] ?? 0)
                === 7,
            'Soft-stop acceptance did not create the seventh reservation.'
        );

        $fixtureIds[] = be_gate2_final_seed_reservation($pdo, 7);
        $hardCandidate = be_gate2_final_ready_candidate('hard-stop', $reviewPassword);
        be_gate2_final_expect(
            be_gate2_final_http(
                'POST',
                '/api/startpartner/action.php',
                [
                    'candidate_id' => $hardCandidate['id'],
                    ...be_gate2_final_operation('hard-stop-rejected', 4),
                    'action' => 'accept_pending_terms',
                    'reason' => 'Muss an der finalen harten Grenze scheitern.',
                    'capacity_exception_reason' => 'Darf Hard-Stop nicht überschreiben.',
                    'reservation_ends_at' => (new DateTimeImmutable(
                        '+10 days',
                        new DateTimeZone('UTC')
                    ))->format(DateTimeInterface::ATOM),
                ],
                $reviewPassword
            ),
            [422],
            'Hard stop'
        );

        $capacityHard = be_gate2_final_expect(
            be_gate2_final_http(
                'GET',
                '/api/startpartner/capacity.php',
                null,
                $reviewPassword
            ),
            [200],
            'Capacity at hard stop'
        );
        be_gate2_final_assert(
            (int)($capacityHard['data']['active_reservations'] ?? 0) === 8,
            'Hard-stop capacity is not eight.'
        );
        be_gate2_final_assert(
            ($capacityHard['data']['hard_stop'] ?? false) === true,
            'Hard stop is not active.'
        );

        $candidateDetail = be_gate2_final_expect(
            be_gate2_final_http(
                'GET',
                '/api/startpartner/candidates.php?id=' . rawurlencode($primaryId),
                null,
                $reviewPassword
            ),
            [200],
            'Candidate detail'
        );
        be_gate2_final_assert(
            count($candidateDetail['data']['qualifications'] ?? []) === 14,
            'Candidate detail does not expose all qualifications.'
        );
        be_gate2_final_assert(
            count($candidateDetail['data']['events'] ?? []) >= 8,
            'Candidate audit stream is incomplete.'
        );

        $caseStatement = $pdo->prepare(
            "SELECT id, source_payload_json FROM control_cases
             WHERE source_system = 'startpartner_candidate'
               AND source_reference = :candidate_id
             LIMIT 1"
        );
        $caseStatement->execute(['candidate_id' => $primaryId]);
        $caseRow = $caseStatement->fetch(PDO::FETCH_ASSOC);
        $caseStatement->closeCursor();
        be_gate2_final_assert(is_array($caseRow), 'Primary Control Center projection is missing.');
        $projectionPayload = json_decode(
            (string)($caseRow['source_payload_json'] ?? ''),
            true
        );
        be_gate2_final_assert(
            is_array($projectionPayload),
            'Primary Control Center projection payload is invalid.'
        );
        be_gate2_final_assert(
            (int)($projectionPayload['candidate_revision'] ?? 0) ===
                (int)($candidateDetail['data']['revision'] ?? -1),
            'Control Center projection revision is stale.'
        );

        $caseDetail = be_gate2_final_expect(
            be_gate2_final_http(
                'GET',
                '/api/control-center/case.php?id=' .
                    rawurlencode((string)$caseRow['id']),
                null,
                $reviewPassword
            ),
            [200],
            'Control Center detail'
        );
        be_gate2_final_assert(
            ($caseDetail['data']['case_kind'] ?? '') === 'startpartner_candidate',
            'Control Center detail has the wrong case kind.'
        );
        be_gate2_final_assert(
            is_array($caseDetail['data']['startpartner_candidate'] ?? null),
            'Control Center detail is missing Startpartner read-back.'
        );
        be_gate2_final_assert(
            (int)($caseDetail['data']['startpartner_candidate']['revision'] ?? 0) ===
                (int)($candidateDetail['data']['revision'] ?? -1),
            'Control Center detail has a stale candidate revision.'
        );

        $lockedDuring = be_gate2_final_locked_counts($pdo);
        be_gate2_final_assert(
            $lockedDuring === $evidence['before']['locked_counts'],
            'A locked side-effect table changed during the lifecycle.'
        );

        $evidence['readback'] = [
            'primary_candidate_id' => $primaryId,
            'primary_revision' => (int)($candidateDetail['data']['revision'] ?? 0),
            'primary_status' => (string)($candidateDetail['data']['status'] ?? ''),
            'qualification_count' => count(
                $candidateDetail['data']['qualifications'] ?? []
            ),
            'event_count' => count($candidateDetail['data']['events'] ?? []),
            'decision_result' => (string)(
                $candidateDetail['data']['decision']['result'] ?? ''
            ),
            'reservation_history_count' => count(
                $candidateDetail['data']['reservations'] ?? []
            ),
            'readiness_revoked' =>
                ($candidateDetail['data']['readiness']['ready'] ?? true) === false,
            'waitlist_candidate_id' => (string)$waitCandidate['id'],
            'waitlist_status' => (string)(
                $waitlist['data']['candidate']['status'] ?? ''
            ),
            'soft_stop_candidate_id' => (string)$softCandidate['id'],
            'soft_stop_active_after_six' => true,
            'seventh_reservation_with_reason' =>
                (int)($softAccepted['data']['candidate']['capacity']['active_reservations']
                    ?? 0) === 7,
            'hard_stop_candidate_id' => (string)$hardCandidate['id'],
            'capacity_active' => (int)(
                $capacityHard['data']['active_reservations'] ?? 0
            ),
            'capacity_hard_stop' => (bool)($capacityHard['data']['hard_stop'] ?? false),
            'control_center_case_id' => (string)$caseRow['id'],
            'control_center_case_kind' => (string)(
                $caseDetail['data']['case_kind'] ?? ''
            ),
            'control_center_revision_current' => true,
            'intake_replay' => (bool)(
                $intakeReplay['data']['idempotent_replay'] ?? false
            ),
            'profile_replay' => (bool)(
                $profileReplay['data']['idempotent_replay'] ?? false
            ),
            'payload_conflict_http' => (int)$payloadConflict['status'],
            'stale_conflict_code' => (string)($stale['code'] ?? ''),
            'locked_counts_unchanged_during_run' => true,
            'fixture_ids' => $fixtureIds,
        ];
        $evidence['status'] = 'PASS';
    } catch (Throwable $error) {
        $failure = $error;
        $evidence['error'] = [
            'class' => $error::class,
            'message' => $error->getMessage(),
            'file' => basename($error->getFile()),
            'line' => $error->getLine(),
        ];
    } finally {
        try {
            $evidence['cleanup'] = be_gate2_final_cleanup($pdo);
            $evidence['after'] = [
                'locked_counts' => be_gate2_final_locked_counts($pdo),
                'capacity' => be_startpartner_gate2_capacity($pdo),
                'residue' => be_gate2_final_residue($pdo),
            ];
            if (isset($evidence['before']['locked_counts'])) {
                be_gate2_final_assert(
                    $evidence['after']['locked_counts'] ===
                        $evidence['before']['locked_counts'],
                    'Locked counts changed after cleanup.'
                );
            }
            be_gate2_final_assert(
                ($evidence['cleanup']['residue']['total'] ?? -1) === 0,
                'Final synthetic cleanup left residue.'
            );
            be_gate2_final_assert(
                (int)($evidence['after']['capacity']['active_reservations'] ?? -1) === 0,
                'Capacity was not restored after cleanup.'
            );

            if ($failure === null && $evidence['status'] === 'PASS') {
                be_gate2_final_write_marker($pdo, $evidence);
                be_gate2_final_assert(
                    be_gate2_final_marker_exists($pdo),
                    'Completion marker was not persisted.'
                );
                $evidence['completion_marker_written'] = true;
            }
        } catch (Throwable $cleanupError) {
            $evidence['status'] = 'FAIL';
            $evidence['cleanup_error'] = [
                'class' => $cleanupError::class,
                'message' => $cleanupError->getMessage(),
            ];
            if ($failure === null) {
                $failure = $cleanupError;
            }
        }

        $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $releaseStatement->execute(['lock_name' => BE_GATE2_FINAL_LOCK]);
        be_gate2_final_scalar($releaseStatement);
        $evidence['finished_at'] = gmdate(DateTimeInterface::ATOM);
    }

    return $evidence;
}

function be_gate2_final_is_direct_request(): bool
{
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    return $script !== false && $script === __FILE__;
}

if (be_gate2_final_is_direct_request()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
    }
    if (be_app_env_value() !== 'staging') {
        be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
    }
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $expectedBuild = trim((string)($_SERVER['HTTP_X_BE_EXPECTED_BUILD'] ?? ''));
    $buildPath = dirname(__DIR__, 2) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath)
        ? trim((string)file_get_contents($buildPath))
        : '';
    if (
        $userAgent !== BE_GATE2_FINAL_SMOKE_UA ||
        $expectedBuild === '' ||
        $deployedBuild === '' ||
        !hash_equals($deployedBuild, $expectedBuild)
    ) {
        be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
    }

    $result = be_gate2_final_run(be_db());
    $httpStatus = $result['status'] === 'PASS'
        ? 200
        : ($result['status'] === 'ALREADY_COMPLETED' ? 200 : 500);
    be_json_response($httpStatus, $result);
}
