<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate2_domain.php';

const BE_GATE2_SMOKE_TOKEN_HASH = 'c795e3b41560c3154d3076fd0cb1e04a8882db56bf363e6abcc1e60c3678790e';
const BE_GATE2_SMOKE_PREFIX = 'GATE2_SYNTHETIC_199_';
const BE_GATE2_SMOKE_OPERATION_PREFIX = 'gate2:199:staging-';
const BE_GATE2_SMOKE_ORIGIN = 'https://staging.bocholt-erleben.de';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function be_gate2_smoke_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function be_gate2_smoke_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() === 1;
}

function be_gate2_smoke_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where);
    $statement->execute($params);
    return (int)$statement->fetchColumn();
}

function be_gate2_smoke_locked_counts(PDO $pdo): array
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
        $counts[$table] = be_gate2_smoke_count($pdo, $table);
    }
    return $counts;
}

function be_gate2_smoke_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($index = 0; $index < $length; $index++) {
        $char = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= "\n";
            }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $index++;
            }
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $index++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote && $quote !== '`') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }
                $quote = null;
            }
            continue;
        }

        if ($char === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
            $lineComment = true;
            $index++;
            continue;
        }
        if ($char === '#') {
            $lineComment = true;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $blockComment = true;
            $index++;
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

function be_gate2_smoke_apply_migration(PDO $pdo, string $filename): array
{
    $path = dirname(__DIR__) . '/sql/' . $filename;
    $sql = file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Migration could not be read: ' . $filename);
    }
    $statements = be_gate2_smoke_split_sql($sql);
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    return ['file' => $filename, 'statements' => count($statements)];
}

function be_gate2_smoke_http(string $method, string $path, ?array $body, string $reviewPassword, array $extraHeaders = []): array
{
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
    $raw = @file_get_contents(BE_GATE2_SMOKE_ORIGIN . $path, false, $context);
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

function be_gate2_smoke_expect(array $response, array $statuses, string $label): array
{
    be_gate2_smoke_assert(
        in_array((int)$response['status'], $statuses, true),
        $label . ' returned HTTP ' . (int)$response['status'] . ': ' . json_encode($response['body'])
    );
    return $response['body'];
}

function be_gate2_smoke_operation(string $suffix, int $revision): array
{
    return [
        'operation_id' => BE_GATE2_SMOKE_OPERATION_PREFIX . $suffix,
        'expected_revision' => $revision,
        'operator_name' => 'Gate 2 Staging Smoke #199',
    ];
}

function be_gate2_smoke_intake(string $suffix, string $reviewPassword): array
{
    $payload = [
        'source' => 'targeted_outreach',
        'source_reference' => 'issue-199:' . $suffix,
        'organization_name' => BE_GATE2_SMOKE_PREFIX . strtoupper($suffix),
        'website_url' => 'https://example.org/gate2-' . rawurlencode($suffix),
        'description_text' => 'Synthetischer Gate-2-Stagingnachweis ohne externe Kommunikation.',
        'desired_content_scope' => 'both',
        'form_version' => 'gate2-staging-smoke-199-v1',
        'retention_review_at' => (new DateTimeImmutable('+60 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        'contacts' => [[
            'contact_name' => 'Synthetischer Kontakt ' . $suffix,
            'contact_role' => 'Testkontakt',
            'email' => 'gate2-199-' . strtolower($suffix) . '@example.org',
            'phone' => '0000 199',
            'is_primary' => true,
        ]],
    ];
    $response = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/intake.php',
        $payload,
        $reviewPassword,
        ['Idempotency-Key: ' . BE_GATE2_SMOKE_OPERATION_PREFIX . 'intake-' . $suffix]
    );
    $body = be_gate2_smoke_expect($response, [200, 201], 'Intake ' . $suffix);
    $candidate = $body['data']['candidate'] ?? null;
    be_gate2_smoke_assert(is_array($candidate) && !empty($candidate['id']), 'Intake read-back is missing candidate.');
    return ['payload' => $payload, 'body' => $body, 'candidate' => $candidate];
}

function be_gate2_smoke_qualifications(): array
{
    $hard = [
        'local_relevance', 'organization_contact', 'content_sources',
        'editorial_fit', 'legal_technical', 'required_information',
    ];
    $dimensions = [
        'local_relevance', 'organization_contact', 'content_sources', 'editorial_fit',
        'content_leverage', 'reach_leverage', 'user_need', 'maintenance_capability',
        'cooperation_readiness', 'setup_effort', 'support_effort', 'regular_path',
        'legal_technical', 'required_information',
    ];
    return array_map(
        static fn(string $dimension): array => [
            'dimension' => $dimension,
            'assessment' => in_array($dimension, $hard, true) ? 'adequate' : 'weak',
            'reason' => 'Synthetische Begründung für ' . $dimension,
            'evidence_text' => 'Synthetische Evidence für ' . $dimension,
        ],
        $dimensions
    );
}

function be_gate2_smoke_ready_candidate(string $suffix, string $reviewPassword): array
{
    $intake = be_gate2_smoke_intake($suffix, $reviewPassword);
    $candidate = $intake['candidate'];
    $id = (string)$candidate['id'];

    $qualification = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/qualification.php',
        ['candidate_id' => $id, ...be_gate2_smoke_operation($suffix . '-qualification', 1), 'qualifications' => be_gate2_smoke_qualifications()],
        $reviewPassword
    );
    $qualificationBody = be_gate2_smoke_expect($qualification, [200], 'Qualification ' . $suffix);
    be_gate2_smoke_assert(($qualificationBody['data']['candidate']['readiness']['ready'] ?? false) === true, 'Candidate is not ready after qualification.');

    $start = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/action.php',
        ['candidate_id' => $id, ...be_gate2_smoke_operation($suffix . '-start-qualification', 2), 'action' => 'start_qualification'],
        $reviewPassword
    );
    be_gate2_smoke_expect($start, [200], 'Start qualification ' . $suffix);

    $ready = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/action.php',
        ['candidate_id' => $id, ...be_gate2_smoke_operation($suffix . '-decision-ready', 3), 'action' => 'mark_decision_ready'],
        $reviewPassword
    );
    $readyBody = be_gate2_smoke_expect($ready, [200], 'Decision ready ' . $suffix);
    be_gate2_smoke_assert(($readyBody['data']['candidate']['status'] ?? '') === 'decision_ready', 'Candidate did not reach decision_ready.');
    return $readyBody['data']['candidate'];
}

function be_gate2_smoke_seed_reservation(PDO $pdo, int $sequence): string
{
    $id = sprintf('19900000-0000-0000-0001-%012d', $sequence);
    $organization = BE_GATE2_SMOKE_PREFIX . 'CAPACITY_' . $sequence;
    $email = 'gate2-199-capacity-' . $sequence . '@example.org';
    $candidate = $pdo->prepare(
        'INSERT INTO startpartner_candidates (
            id, source, source_reference, organization_name, organization_name_normalized,
            desired_content_scope, status, identity_key, idempotency_key_hash,
            form_version, retention_review_at, revision, assigned_to
         ) VALUES (
            :id, \'targeted_outreach\', :source_reference, :organization_name, :organization_name_normalized,
            \'both\', \'accepted_pending_terms\', :identity_key, :idempotency_key_hash,
            \'gate2-staging-smoke-199-v1\', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 DAY), 1, \'Gate 2 Staging Smoke #199\'
         )'
    );
    $candidate->execute([
        'id' => $id,
        'source_reference' => 'issue-199:capacity-' . $sequence,
        'organization_name' => $organization,
        'organization_name_normalized' => strtolower($organization),
        'identity_key' => hash('sha256', 'gate2-199-capacity-identity-' . $sequence),
        'idempotency_key_hash' => hash('sha256', 'gate2-199-capacity-idempotency-' . $sequence),
    ]);
    $pdo->prepare(
        'INSERT INTO startpartner_candidate_contacts (
            candidate_id, contact_name, contact_role, email, email_normalized, is_primary
         ) VALUES (:candidate_id, \'Synthetischer Kapazitätskontakt\', \'Testkontakt\', :email, :email, 1)'
    )->execute(['candidate_id' => $id, 'email' => $email]);
    $pdo->prepare(
        'INSERT INTO startpartner_candidate_reservations (
            candidate_id, status, starts_at, ends_at, capacity_snapshot_json, operator_reference
         ) VALUES (
            :candidate_id, \'active\', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 DAY),
            :capacity_snapshot_json, \'Gate 2 Staging Smoke #199\'
         )'
    )->execute(['candidate_id' => $id, 'capacity_snapshot_json' => '{}']);
    return $id;
}

function be_gate2_smoke_candidate_ids(PDO $pdo): array
{
    if (!be_gate2_smoke_table_exists($pdo, 'startpartner_candidates')) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT id FROM startpartner_candidates WHERE organization_name LIKE :prefix ORDER BY id'
    );
    $statement->execute(['prefix' => BE_GATE2_SMOKE_PREFIX . '%']);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function be_gate2_smoke_cleanup(PDO $pdo): array
{
    $ids = be_gate2_smoke_candidate_ids($pdo);
    if ($ids !== [] && be_gate2_smoke_table_exists($pdo, 'control_cases')) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare(
            "DELETE FROM control_cases
             WHERE source_system = 'startpartner_candidate'
               AND (source_reference IN ({$placeholders}) OR object_id IN ({$placeholders}))"
        )->execute([...$ids, ...$ids]);
    }
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM startpartner_candidates WHERE id IN ({$placeholders})")->execute($ids);
    }

    $residue = [
        'candidates' => be_gate2_smoke_table_exists($pdo, 'startpartner_candidates')
            ? be_gate2_smoke_count($pdo, 'startpartner_candidates', 'organization_name LIKE :prefix', ['prefix' => BE_GATE2_SMOKE_PREFIX . '%'])
            : 0,
        'control_cases' => be_gate2_smoke_table_exists($pdo, 'control_cases')
            ? be_gate2_smoke_count($pdo, 'control_cases', "source_system = 'startpartner_candidate' AND title LIKE :prefix", ['prefix' => '%' . BE_GATE2_SMOKE_PREFIX . '%'])
            : 0,
        'operations' => be_gate2_smoke_table_exists($pdo, 'startpartner_candidate_operations')
            ? be_gate2_smoke_count($pdo, 'startpartner_candidate_operations', 'operation_id LIKE :prefix', ['prefix' => BE_GATE2_SMOKE_OPERATION_PREFIX . '%'])
            : 0,
    ];
    $residue['total'] = array_sum($residue);
    return ['deleted_candidate_ids' => $ids, 'residue' => $residue];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}
if (be_app_env_value() !== 'staging') {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
}
$token = trim((string)($_GET['token'] ?? ''));
if ($token === '' || !hash_equals(BE_GATE2_SMOKE_TOKEN_HASH, hash('sha256', $token))) {
    be_json_response(401, ['status' => 'error', 'message' => 'Smoke access denied.']);
}

$pdo = be_db();
$lockStatement = $pdo->query("SELECT GET_LOCK('bocholt_gate2_staging_smoke_199', 0)");
if ((int)$lockStatement->fetchColumn() !== 1) {
    be_json_response(409, ['status' => 'error', 'message' => 'Smoke lifecycle is already running.']);
}

$evidence = [
    'status' => 'FAIL',
    'workpack_issue' => 199,
    'environment' => be_app_env_value(),
    'prefix' => BE_GATE2_SMOKE_PREFIX,
    'started_at' => gmdate(DateTimeInterface::ATOM),
];
$failure = null;

try {
    $initialIds = be_gate2_smoke_candidate_ids($pdo);
    $initialControlCases = be_gate2_smoke_table_exists($pdo, 'control_cases')
        ? be_gate2_smoke_count($pdo, 'control_cases', "source_system = 'startpartner_candidate' AND title LIKE :prefix", ['prefix' => '%' . BE_GATE2_SMOKE_PREFIX . '%'])
        : 0;
    be_gate2_smoke_assert($initialIds === [] && $initialControlCases === 0, 'Synthetic before-state is not empty.');

    $evidence['before'] = [
        'synthetic_candidate_ids' => $initialIds,
        'synthetic_control_cases' => $initialControlCases,
        'locked_counts' => be_gate2_smoke_locked_counts($pdo),
        'migration_009' => be_gate2_smoke_count($pdo, 'app_schema_migrations', 'migration_key = :key', ['key' => '009_control_center_runtime_schema']),
        'migration_010' => be_gate2_smoke_count($pdo, 'app_schema_migrations', 'migration_key = :key', ['key' => '010_startpartner_gate2_qualification_capacity']),
    ];

    $evidence['migrations'] = [
        be_gate2_smoke_apply_migration($pdo, '009_control_center_runtime_schema.sql'),
        be_gate2_smoke_apply_migration($pdo, '010_startpartner_gate2_qualification_capacity.sql'),
    ];
    be_startpartner_require_schema($pdo);
    be_gate2_smoke_assert(
        be_gate2_smoke_count($pdo, 'app_schema_migrations', 'migration_key = :key', ['key' => '009_control_center_runtime_schema']) === 1,
        'Migration 009 was not recorded.'
    );
    be_gate2_smoke_assert(
        be_gate2_smoke_count($pdo, 'app_schema_migrations', 'migration_key = :key', ['key' => '010_startpartner_gate2_qualification_capacity']) === 1,
        'Migration 010 was not recorded.'
    );

    $reviewPassword = be_review_password();
    $primaryIntake = be_gate2_smoke_intake('primary', $reviewPassword);
    $primaryId = (string)$primaryIntake['candidate']['id'];
    be_gate2_smoke_assert(($primaryIntake['body']['data']['created'] ?? false) === true, 'Primary candidate was not created.');

    $replay = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/intake.php',
        $primaryIntake['payload'],
        $reviewPassword,
        ['Idempotency-Key: ' . BE_GATE2_SMOKE_OPERATION_PREFIX . 'intake-primary']
    );
    $replayBody = be_gate2_smoke_expect($replay, [200], 'Intake replay');
    be_gate2_smoke_assert(($replayBody['data']['idempotent_replay'] ?? false) === true, 'Intake replay was not idempotent.');
    be_gate2_smoke_assert(($replayBody['data']['candidate']['id'] ?? '') === $primaryId, 'Intake replay returned another candidate.');

    $conflictingIntakePayload = $primaryIntake['payload'];
    $conflictingIntakePayload['description_text'] = 'Abweichender Payload muss abgelehnt werden.';
    $conflictIntake = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/intake.php',
        $conflictingIntakePayload,
        $reviewPassword,
        ['Idempotency-Key: ' . BE_GATE2_SMOKE_OPERATION_PREFIX . 'intake-primary']
    );
    be_gate2_smoke_expect($conflictIntake, [422], 'Intake payload conflict');

    $profilePayload = [
        'candidate_id' => $primaryId,
        ...be_gate2_smoke_operation('primary-profile', 1),
        'assigned_to' => 'Gate 2 Staging Smoke #199',
        'website_url' => 'example.org/gate2-primary-updated',
        'next_review_at' => (new DateTimeImmutable('+7 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    ];
    $profile = be_gate2_smoke_http('POST', '/api/startpartner/profile.php', $profilePayload, $reviewPassword);
    $profileBody = be_gate2_smoke_expect($profile, [200], 'Profile update');
    be_gate2_smoke_assert((int)($profileBody['data']['candidate']['revision'] ?? 0) === 2, 'Profile update did not produce revision 2.');

    $profileReplay = be_gate2_smoke_http('POST', '/api/startpartner/profile.php', $profilePayload, $reviewPassword);
    $profileReplayBody = be_gate2_smoke_expect($profileReplay, [200], 'Profile replay');
    be_gate2_smoke_assert(($profileReplayBody['data']['idempotent_replay'] ?? false) === true, 'Profile replay was not idempotent.');

    $stale = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/profile.php',
        ['candidate_id' => $primaryId, ...be_gate2_smoke_operation('primary-stale', 1), 'assigned_to' => 'Stale write'],
        $reviewPassword
    );
    $staleBody = be_gate2_smoke_expect($stale, [409], 'Stale revision');
    be_gate2_smoke_assert(($staleBody['code'] ?? '') === 'STARTPARTNER_CONFLICT', 'Stale revision did not return the stable conflict code.');

    $qualification = be_gate2_smoke_http(
        'POST',
        '/api/startpartner/qualification.php',
        ['candidate_id' => $primaryId, ...be_gate2_smoke_operation('primary-qualification', 2), 'qualifications' => be_gate2_smoke_qualifications()],
        $reviewPassword
    );
    $qualificationBody = be_gate2_smoke_expect($qualification, [200], 'Primary qualification');
    be_gate2_smoke_assert((int)($qualificationBody['data']['candidate']['revision'] ?? 0) === 3, 'Qualification did not produce revision 3.');
    be_gate2_smoke_assert(($qualificationBody['data']['candidate']['readiness']['ready'] ?? false) === true, 'Primary candidate is not ready after qualification.');

    be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', ['candidate_id' => $primaryId, ...be_gate2_smoke_operation('primary-start', 3), 'action' => 'start_qualification'], $reviewPassword),
        [200],
        'Primary start qualification'
    );
    be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', ['candidate_id' => $primaryId, ...be_gate2_smoke_operation('primary-ready', 4), 'action' => 'mark_decision_ready'], $reviewPassword),
        [200],
        'Primary decision ready'
    );
    $accepted = be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
            'candidate_id' => $primaryId,
            ...be_gate2_smoke_operation('primary-accept', 5),
            'action' => 'accept_pending_terms',
            'reason' => 'Synthetische Eignung.',
            'reservation_ends_at' => (new DateTimeImmutable('+20 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        ], $reviewPassword),
        [200],
        'Primary reservation'
    );
    be_gate2_smoke_assert(($accepted['data']['candidate']['status'] ?? '') === 'accepted_pending_terms', 'Primary reservation status is wrong.');
    be_gate2_smoke_assert(is_array($accepted['data']['candidate']['active_reservation'] ?? null), 'Primary active reservation is missing.');

    $extended = be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
            'candidate_id' => $primaryId,
            ...be_gate2_smoke_operation('primary-extend', 6),
            'action' => 'extend_reservation',
            'reason' => 'Synthetische Verlängerung.',
            'reservation_ends_at' => (new DateTimeImmutable('+25 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        ], $reviewPassword),
        [200],
        'Primary reservation extension'
    );
    be_gate2_smoke_assert(count($extended['data']['candidate']['reservations'] ?? []) === 2, 'Reservation history does not contain two rows.');

    $released = be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
            'candidate_id' => $primaryId,
            ...be_gate2_smoke_operation('primary-release', 7),
            'action' => 'release_reservation',
            'reason' => 'Synthetische Freigabe.',
            'target_status' => 'decision_ready',
        ], $reviewPassword),
        [200],
        'Primary reservation release'
    );
    be_gate2_smoke_assert(($released['data']['candidate']['status'] ?? '') === 'decision_ready', 'Released candidate did not return to decision_ready.');

    $downgrade = be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/qualification.php', [
            'candidate_id' => $primaryId,
            ...be_gate2_smoke_operation('primary-downgrade', 8),
            'qualifications' => [[
                'dimension' => 'local_relevance',
                'assessment' => 'weak',
                'reason' => 'Synthetischer Mindestblocker.',
                'evidence_text' => 'Synthetische geänderte Evidence.',
            ]],
        ], $reviewPassword),
        [200],
        'Primary readiness revocation'
    );
    be_gate2_smoke_assert(($downgrade['data']['candidate']['status'] ?? '') === 'qualifying', 'Readiness was not revoked fail-closed.');
    be_gate2_smoke_assert(($downgrade['data']['candidate']['readiness']['ready'] ?? true) === false, 'Downgraded candidate is still ready.');

    $waitCandidate = be_gate2_smoke_ready_candidate('waitlist', $reviewPassword);
    $waitlist = be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
            'candidate_id' => $waitCandidate['id'],
            ...be_gate2_smoke_operation('waitlist-action', 4),
            'action' => 'waitlist',
            'reason' => 'Synthetische Kapazitätsentscheidung.',
            'eligibility_reason' => 'Synthetisch geeignet.',
            'priority_reason' => 'Synthetischer lokaler Mehrwert.',
            'next_review_at' => (new DateTimeImmutable('+14 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
            'contact_status' => 'paused',
            'regular_alternative' => 'Synthetischer regulärer Alternativweg',
        ], $reviewPassword),
        [200],
        'Waitlist action'
    );
    be_gate2_smoke_assert(($waitlist['data']['candidate']['status'] ?? '') === 'waitlisted', 'Waitlist candidate status is wrong.');
    be_gate2_smoke_assert(is_array($waitlist['data']['candidate']['waitlist'] ?? null), 'Waitlist owner is missing.');

    $capacityBefore = be_gate2_smoke_expect(
        be_gate2_smoke_http('GET', '/api/startpartner/capacity.php', null, $reviewPassword),
        [200],
        'Capacity before fixtures'
    );
    be_gate2_smoke_assert((int)($capacityBefore['data']['active_reservations'] ?? -1) === 0, 'Capacity before fixtures is not zero.');

    $fixtureIds = [];
    for ($sequence = 1; $sequence <= 6; $sequence++) {
        $fixtureIds[] = be_gate2_smoke_seed_reservation($pdo, $sequence);
    }
    $softCandidate = be_gate2_smoke_ready_candidate('soft-stop', $reviewPassword);
    $softRejected = be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
        'candidate_id' => $softCandidate['id'],
        ...be_gate2_smoke_operation('soft-stop-rejected', 4),
        'action' => 'accept_pending_terms',
        'reason' => 'Synthetische Eignung.',
        'reservation_ends_at' => (new DateTimeImmutable('+10 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    ], $reviewPassword);
    be_gate2_smoke_expect($softRejected, [422], 'Soft stop without exception reason');

    $softAccepted = be_gate2_smoke_expect(
        be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
            'candidate_id' => $softCandidate['id'],
            ...be_gate2_smoke_operation('soft-stop-accepted', 4),
            'action' => 'accept_pending_terms',
            'reason' => 'Synthetische Eignung.',
            'capacity_exception_reason' => 'Kontrollierte siebte synthetische Reservierung.',
            'reservation_ends_at' => (new DateTimeImmutable('+10 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        ], $reviewPassword),
        [200],
        'Soft stop with exception reason'
    );
    be_gate2_smoke_assert(($softAccepted['data']['candidate']['capacity']['active_reservations'] ?? 0) === 7, 'Soft-stop acceptance did not create the seventh reservation.');

    $fixtureIds[] = be_gate2_smoke_seed_reservation($pdo, 7);
    $hardCandidate = be_gate2_smoke_ready_candidate('hard-stop', $reviewPassword);
    $hardRejected = be_gate2_smoke_http('POST', '/api/startpartner/action.php', [
        'candidate_id' => $hardCandidate['id'],
        ...be_gate2_smoke_operation('hard-stop-rejected', 4),
        'action' => 'accept_pending_terms',
        'reason' => 'Muss an der harten Grenze scheitern.',
        'capacity_exception_reason' => 'Darf Hard-Stop nicht überschreiben.',
        'reservation_ends_at' => (new DateTimeImmutable('+10 days', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    ], $reviewPassword);
    be_gate2_smoke_expect($hardRejected, [422], 'Hard stop');

    $capacityHard = be_gate2_smoke_expect(
        be_gate2_smoke_http('GET', '/api/startpartner/capacity.php', null, $reviewPassword),
        [200],
        'Capacity at hard stop'
    );
    be_gate2_smoke_assert((int)($capacityHard['data']['active_reservations'] ?? 0) === 8, 'Hard-stop capacity is not eight.');
    be_gate2_smoke_assert(($capacityHard['data']['hard_stop'] ?? false) === true, 'Hard stop is not active.');

    $candidateDetail = be_gate2_smoke_expect(
        be_gate2_smoke_http('GET', '/api/startpartner/candidates.php?id=' . rawurlencode($primaryId), null, $reviewPassword),
        [200],
        'Candidate detail'
    );
    be_gate2_smoke_assert(count($candidateDetail['data']['qualifications'] ?? []) === 14, 'Candidate detail does not expose all qualifications.');
    be_gate2_smoke_assert(count($candidateDetail['data']['events'] ?? []) >= 8, 'Candidate audit stream is incomplete.');

    $cases = be_gate2_smoke_expect(
        be_gate2_smoke_http('GET', '/api/control-center/cases.php?active=1', null, $reviewPassword),
        [200],
        'Control Center list'
    );
    $syntheticCases = array_values(array_filter(
        $cases['data']['items'] ?? [],
        static fn(array $item): bool => ($item['source']['system'] ?? $item['source_system'] ?? '') === 'startpartner_candidate'
            && str_starts_with((string)($item['title'] ?? ''), 'Startpartner prüfen: ' . BE_GATE2_SMOKE_PREFIX)
    ));
    be_gate2_smoke_assert(count($syntheticCases) >= 4, 'Control Center list does not expose the synthetic candidates.');
    $caseId = (string)($syntheticCases[0]['id'] ?? '');
    be_gate2_smoke_assert($caseId !== '', 'Synthetic Control Center case id is missing.');
    $caseDetail = be_gate2_smoke_expect(
        be_gate2_smoke_http('GET', '/api/control-center/case.php?id=' . rawurlencode($caseId), null, $reviewPassword),
        [200],
        'Control Center detail'
    );
    be_gate2_smoke_assert(($caseDetail['data']['case_kind'] ?? '') === 'startpartner_candidate', 'Control Center detail has the wrong case kind.');
    be_gate2_smoke_assert(is_array($caseDetail['data']['startpartner_candidate'] ?? null), 'Control Center detail is missing Startpartner read-back.');

    $lockedDuring = be_gate2_smoke_locked_counts($pdo);
    be_gate2_smoke_assert($lockedDuring === $evidence['before']['locked_counts'], 'A locked side-effect table changed during the lifecycle.');

    $evidence['readback'] = [
        'primary_candidate_id' => $primaryId,
        'primary_revision' => (int)($candidateDetail['data']['revision'] ?? 0),
        'primary_status' => (string)($candidateDetail['data']['status'] ?? ''),
        'qualification_count' => count($candidateDetail['data']['qualifications'] ?? []),
        'event_count' => count($candidateDetail['data']['events'] ?? []),
        'waitlist_candidate_id' => (string)$waitCandidate['id'],
        'soft_stop_candidate_id' => (string)$softCandidate['id'],
        'hard_stop_candidate_id' => (string)$hardCandidate['id'],
        'capacity_active' => (int)($capacityHard['data']['active_reservations'] ?? 0),
        'capacity_hard_stop' => (bool)($capacityHard['data']['hard_stop'] ?? false),
        'control_center_case_count' => count($syntheticCases),
        'control_center_case_kind' => (string)($caseDetail['data']['case_kind'] ?? ''),
        'intake_replay' => (bool)($replayBody['data']['idempotent_replay'] ?? false),
        'profile_replay' => (bool)($profileReplayBody['data']['idempotent_replay'] ?? false),
        'stale_conflict_code' => (string)($staleBody['code'] ?? ''),
        'locked_counts_unchanged_during_run' => true,
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
        $evidence['cleanup'] = be_gate2_smoke_cleanup($pdo);
        $evidence['after'] = [
            'locked_counts' => be_gate2_smoke_locked_counts($pdo),
            'migration_009' => be_gate2_smoke_count($pdo, 'app_schema_migrations', 'migration_key = :key', ['key' => '009_control_center_runtime_schema']),
            'migration_010' => be_gate2_smoke_count($pdo, 'app_schema_migrations', 'migration_key = :key', ['key' => '010_startpartner_gate2_qualification_capacity']),
        ];
        if (isset($evidence['before']['locked_counts'])) {
            be_gate2_smoke_assert($evidence['after']['locked_counts'] === $evidence['before']['locked_counts'], 'Locked counts changed after cleanup.');
        }
        be_gate2_smoke_assert(($evidence['cleanup']['residue']['total'] ?? -1) === 0, 'Synthetic cleanup left residue.');
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
    $pdo->query("SELECT RELEASE_LOCK('bocholt_gate2_staging_smoke_199')");
    $evidence['finished_at'] = gmdate(DateTimeInterface::ATOM);
}

be_json_response($failure === null && $evidence['status'] === 'PASS' ? 200 : 500, $evidence);
