<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_domain.php';
require_once __DIR__ . '/_gate4_contract.php';
require_once __DIR__ . '/_gate4_schema.php';
require_once __DIR__ . '/_gate4_state.php';
require_once __DIR__ . '/_gate4_projection.php';
require_once __DIR__ . '/_gate4_operation.php';
require_once __DIR__ . '/_gate4_readiness_actions.php';
require_once __DIR__ . '/_gate4_activation_domain.php';
require_once __DIR__ . '/_gate4_portal_domain.php';

// Temporäre, staginggebundene Evidence-Verkettung für Workpack #241:
// Vor dem einmaligen Gate-4-Lifecycle wird ausschließlich Migration 012 über
// ihren separat geschützten Writer verifiziert beziehungsweise angewendet.
// Ohne Review-Zugang bleibt der normale Endpoint-Schutz zuständig und liefert 401.
$gate4EvidenceScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$gate4EvidenceReviewPassword = trim((string)($_SERVER['HTTP_X_BE_REVIEW_PASSWORD'] ?? ''));
if (
    str_ends_with($gate4EvidenceScript, '/evidence/gate4_staging_lifecycle_241.php')
    && $gate4EvidenceReviewPassword !== ''
) {
    $gate4EvidenceOrigin = 'https://staging.bocholt-erleben.de';
    $gate4EvidenceMigrationPath = '/api/startpartner/evidence/gate4_staging_migration_241.php';
    $gate4EvidenceUserAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $gate4EvidenceRequestBody = (string)file_get_contents('php://input');
    if ($gate4EvidenceUserAgent !== 'Bocholt-Erleben-Deploy-Smoke/1.0') {
        throw new RuntimeException('Gate-4 evidence migration preflight is not authorized.');
    }
    $gate4EvidenceContext = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: ' . $gate4EvidenceUserAgent,
                'X-BE-Review-Password: ' . $gate4EvidenceReviewPassword,
            ]),
            'content' => $gate4EvidenceRequestBody,
            'ignore_errors' => true,
            'timeout' => 300,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $gate4EvidenceRaw = @file_get_contents(
        $gate4EvidenceOrigin . $gate4EvidenceMigrationPath,
        false,
        $gate4EvidenceContext
    );
    $gate4EvidenceStatus = 0;
    foreach (($http_response_header ?? []) as $gate4EvidenceHeader) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $gate4EvidenceHeader, $gate4EvidenceMatch) === 1) {
            $gate4EvidenceStatus = (int)$gate4EvidenceMatch[1];
        }
    }
    $gate4EvidencePayload = json_decode((string)$gate4EvidenceRaw, true);
    if (
        $gate4EvidenceStatus !== 200
        || !is_array($gate4EvidencePayload)
        || ($gate4EvidencePayload['status'] ?? '') !== 'ok'
    ) {
        $gate4EvidenceMessage = is_array($gate4EvidencePayload)
            ? (string)($gate4EvidencePayload['error_message'] ?? $gate4EvidencePayload['message'] ?? '')
            : '';
        throw new RuntimeException(
            'Gate-4 migration preflight failed with HTTP ' . $gate4EvidenceStatus
            . ($gate4EvidenceMessage !== '' ? ': ' . $gate4EvidenceMessage : '')
        );
    }

    // Nur dieser synthetische Lifecycle-Request benötigt Kompatibilität mit
    // den Test-Fixture-Statements, die benannte PDO-Parameter wiederverwenden.
    // Permanente APIs laufen in separaten Requests weiterhin mit nativen Prepares.
    $gate4EvidencePdo = be_db();
    $gate4EvidencePdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    // Nach dem erfolgreichen Einmal-Nachweis wird bei vorhandenem Marker nur
    // dessen kontrollierter Rückbau ausgeführt. Der Lifecycle selbst läuft in
    // diesem Request nicht erneut an.
    $gate4EvidenceMarker = '241_gate4_staging_lifecycle_completed';
    $gate4EvidenceLock = 'bocholt_gate4_staging_final_241';
    $gate4EvidenceCandidateId = '24100000-0000-4000-8000-000000000101';
    $gate4EvidencePilotId = '24100000-0000-4000-8000-000000000102';
    $gate4EvidenceEmail = 'gate4-241-staging@example.invalid';
    $gate4EvidenceSessionToken = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    $gate4EvidenceCount = static function (
        PDO $pdo,
        string $table,
        string $where = '1=1',
        array $params = []
    ): int {
        $safeTable = str_replace('`', '``', $table);
        $statement = $pdo->prepare("SELECT COUNT(*) FROM `{$safeTable}` WHERE {$where}");
        $statement->execute($params);
        return (int)$statement->fetchColumn();
    };
    $gate4EvidenceLockedCounts = static function (PDO $pdo) use ($gate4EvidenceCount): array {
        $counts = [];
        foreach ([
            'organizers',
            'organizer_magic_links',
            'organizer_portal_sessions',
            'submissions',
            'subscriptions',
            'publication_entitlements',
            'publication_consumptions',
        ] as $table) {
            $counts[$table] = $gate4EvidenceCount($pdo, $table);
        }
        return $counts;
    };
    $gate4EvidenceResidue = static function (PDO $pdo) use (
        $gate4EvidenceCount,
        $gate4EvidenceCandidateId,
        $gate4EvidencePilotId,
        $gate4EvidenceEmail,
        $gate4EvidenceSessionToken
    ): array {
        $caseStatement = $pdo->prepare(
            "SELECT id FROM control_cases
             WHERE source_system = 'startpartner_candidate' AND source_reference = :candidate_id"
        );
        $caseStatement->execute(['candidate_id' => $gate4EvidenceCandidateId]);
        $caseIds = array_map('strval', $caseStatement->fetchAll(PDO::FETCH_COLUMN));
        $caseChildren = 0;
        foreach ($caseIds as $caseId) {
            foreach (['control_case_events', 'control_operations', 'control_editorial_feedback'] as $table) {
                $caseChildren += $gate4EvidenceCount(
                    $pdo,
                    $table,
                    'case_id = :case_id',
                    ['case_id' => $caseId]
                );
            }
        }
        $counts = [
            'candidate' => $gate4EvidenceCount($pdo, 'startpartner_candidates', 'id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'contacts' => $gate4EvidenceCount($pdo, 'startpartner_candidate_contacts', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'qualifications' => $gate4EvidenceCount($pdo, 'startpartner_candidate_qualifications', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'decisions' => $gate4EvidenceCount($pdo, 'startpartner_candidate_decisions', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'reservations' => $gate4EvidenceCount($pdo, 'startpartner_candidate_reservations', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'waitlist' => $gate4EvidenceCount($pdo, 'startpartner_candidate_waitlist', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'candidate_operations' => $gate4EvidenceCount($pdo, 'startpartner_candidate_operations', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'candidate_events' => $gate4EvidenceCount($pdo, 'startpartner_candidate_events', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'terms' => $gate4EvidenceCount($pdo, 'startpartner_pilot_terms_acceptances', 'candidate_id = :candidate_id', ['candidate_id' => $gate4EvidenceCandidateId]),
            'pilots' => $gate4EvidenceCount(
                $pdo,
                'startpartner_pilots',
                'id = :pilot_id OR candidate_id = :candidate_id',
                ['pilot_id' => $gate4EvidencePilotId, 'candidate_id' => $gate4EvidenceCandidateId]
            ),
            'scopes' => $gate4EvidenceCount($pdo, 'startpartner_pilot_scopes', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'pilot_entitlements' => $gate4EvidenceCount($pdo, 'startpartner_pilot_entitlements', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'pilot_events' => $gate4EvidenceCount($pdo, 'startpartner_pilot_events', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'onboarding_items' => $gate4EvidenceCount($pdo, 'startpartner_pilot_onboarding_items', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'content_links' => $gate4EvidenceCount($pdo, 'startpartner_pilot_content_links', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'measurements' => $gate4EvidenceCount($pdo, 'startpartner_pilot_measurement_preflights', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'distributions' => $gate4EvidenceCount($pdo, 'startpartner_pilot_distribution_commitments', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'usages' => $gate4EvidenceCount($pdo, 'startpartner_pilot_usages', 'pilot_id = :pilot_id', ['pilot_id' => $gate4EvidencePilotId]),
            'portal_sessions' => $gate4EvidenceCount(
                $pdo,
                'organizer_portal_sessions',
                'session_token_hash = :session_hash',
                ['session_hash' => hash('sha256', $gate4EvidenceSessionToken)]
            ),
            'submissions' => $gate4EvidenceCount(
                $pdo,
                'submissions',
                "email_snapshot = :email AND payment_kind = 'startpartner_pilot'",
                ['email' => $gate4EvidenceEmail]
            ),
            'control_cases' => count($caseIds),
            'control_case_children' => $caseChildren,
            'organizer' => $gate4EvidenceCount(
                $pdo,
                'organizers',
                'email_normalized = :email',
                ['email' => $gate4EvidenceEmail]
            ),
        ];
        $counts['total'] = array_sum($counts);
        return $counts;
    };

    $gate4EvidenceLockStatement = $gate4EvidencePdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $gate4EvidenceLockStatement->execute(['lock_name' => $gate4EvidenceLock]);
    $gate4EvidenceLockHeld = (int)$gate4EvidenceLockStatement->fetchColumn() === 1;
    if (!$gate4EvidenceLockHeld) {
        throw new RuntimeException('Gate-4 marker cleanup lock is already held.');
    }

    try {
        $gate4EvidenceMarkerBefore = $gate4EvidenceCount(
            $gate4EvidencePdo,
            'app_schema_migrations',
            'migration_key = :marker_key',
            ['marker_key' => $gate4EvidenceMarker]
        );
        if ($gate4EvidenceMarkerBefore === 1) {
            $gate4EvidenceResidueBefore = $gate4EvidenceResidue($gate4EvidencePdo);
            if ((int)$gate4EvidenceResidueBefore['total'] !== 0) {
                throw new RuntimeException('Gate-4 completion marker exists together with synthetic residue.');
            }
            $gate4EvidenceLockedBefore = $gate4EvidenceLockedCounts($gate4EvidencePdo);
            $gate4EvidenceCapacityBefore = be_startpartner_gate4_capacity($gate4EvidencePdo);

            $gate4EvidenceDelete = $gate4EvidencePdo->prepare(
                'DELETE FROM app_schema_migrations WHERE migration_key = :marker_key'
            );
            $gate4EvidenceDelete->execute(['marker_key' => $gate4EvidenceMarker]);
            if ($gate4EvidenceDelete->rowCount() !== 1) {
                throw new RuntimeException('Gate-4 completion marker was not removed exactly once.');
            }

            $gate4EvidenceMarkerAfter = $gate4EvidenceCount(
                $gate4EvidencePdo,
                'app_schema_migrations',
                'migration_key = :marker_key',
                ['marker_key' => $gate4EvidenceMarker]
            );
            $gate4EvidenceResidueAfter = $gate4EvidenceResidue($gate4EvidencePdo);
            $gate4EvidenceLockedAfter = $gate4EvidenceLockedCounts($gate4EvidencePdo);
            $gate4EvidenceCapacityAfter = be_startpartner_gate4_capacity($gate4EvidencePdo);
            if ($gate4EvidenceMarkerAfter !== 0) {
                throw new RuntimeException('Gate-4 completion marker remains after cleanup.');
            }
            if ((int)$gate4EvidenceResidueAfter['total'] !== 0) {
                throw new RuntimeException('Synthetic Gate-4 residue exists after marker cleanup.');
            }
            if ($gate4EvidenceLockedAfter !== $gate4EvidenceLockedBefore) {
                throw new RuntimeException('Locked owner counts changed during marker cleanup.');
            }
            if ($gate4EvidenceCapacityAfter !== $gate4EvidenceCapacityBefore) {
                throw new RuntimeException('Startpartner capacity changed during marker cleanup.');
            }

            $gate4EvidenceRelease = $gate4EvidencePdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $gate4EvidenceRelease->execute(['lock_name' => $gate4EvidenceLock]);
            $gate4EvidenceLockHeld = false;
            $gate4EvidenceInput = json_decode($gate4EvidenceRequestBody, true);
            be_json_response(200, [
                'status' => 'ok',
                'data' => [
                    'already_completed' => true,
                    'marker' => $gate4EvidenceMarker,
                    'build' => is_array($gate4EvidenceInput)
                        ? trim((string)($gate4EvidenceInput['expected_build'] ?? ''))
                        : '',
                    'marker_cleanup' => [
                        'before' => $gate4EvidenceMarkerBefore,
                        'after' => $gate4EvidenceMarkerAfter,
                        'residue_before' => $gate4EvidenceResidueBefore,
                        'residue_after' => $gate4EvidenceResidueAfter,
                        'locked_before' => $gate4EvidenceLockedBefore,
                        'locked_after' => $gate4EvidenceLockedAfter,
                        'capacity_before' => $gate4EvidenceCapacityBefore,
                        'capacity_after' => $gate4EvidenceCapacityAfter,
                    ],
                    'residue' => $gate4EvidenceResidueAfter,
                ],
            ]);
        }
    } finally {
        if ($gate4EvidenceLockHeld) {
            $gate4EvidenceRelease = $gate4EvidencePdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $gate4EvidenceRelease->execute(['lock_name' => $gate4EvidenceLock]);
        }
    }
}
