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
// Vor dem einmaligen Gate-4-Lifecycle werden ausschließlich Migration 012
// und ein vorhandener Completion-Marker über separat geschützte Evidence-
// Endpunkte geprüft. Ohne Review-Zugang bleibt der normale Endpoint-Schutz
// zuständig und liefert HTTP 401.
$gate4EvidenceScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$gate4EvidenceReviewPassword = trim((string)($_SERVER['HTTP_X_BE_REVIEW_PASSWORD'] ?? ''));
if (
    str_ends_with($gate4EvidenceScript, '/evidence/gate4_staging_lifecycle_241.php')
    && $gate4EvidenceReviewPassword !== ''
) {
    $gate4EvidenceOrigin = 'https://staging.bocholt-erleben.de';
    $gate4EvidenceUserAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $gate4EvidenceRequestBody = (string)file_get_contents('php://input');
    if ($gate4EvidenceUserAgent !== 'Bocholt-Erleben-Deploy-Smoke/1.0') {
        throw new RuntimeException('Gate-4 evidence preflight is not authorized.');
    }

    $gate4EvidenceCall = static function (string $path) use (
        $gate4EvidenceOrigin,
        $gate4EvidenceUserAgent,
        $gate4EvidenceReviewPassword,
        $gate4EvidenceRequestBody
    ): array {
        $context = stream_context_create([
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
        $raw = @file_get_contents($gate4EvidenceOrigin . $path, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int)$match[1];
            }
        }
        $payload = json_decode((string)$raw, true);
        if ($status !== 200 || !is_array($payload) || ($payload['status'] ?? '') !== 'ok') {
            $message = is_array($payload)
                ? (string)($payload['error_message'] ?? $payload['message'] ?? '')
                : '';
            throw new RuntimeException(
                'Gate-4 evidence preflight failed for ' . $path . ' with HTTP ' . $status
                . ($message !== '' ? ': ' . $message : '')
            );
        }
        return $payload;
    };

    $gate4EvidenceCall('/api/startpartner/evidence/gate4_staging_migration_241.php');
    $gate4MarkerPayload = $gate4EvidenceCall(
        '/api/startpartner/evidence/gate4_staging_marker_cleanup_241.php'
    );
    $gate4MarkerData = $gate4MarkerPayload['data'] ?? null;
    if (!is_array($gate4MarkerData)) {
        throw new RuntimeException('Gate-4 marker cleanup returned no data.');
    }
    $gate4MarkerAction = (string)($gate4MarkerData['action'] ?? '');
    if ($gate4MarkerAction === 'cleaned') {
        be_json_response(200, ['status' => 'ok', 'data' => $gate4MarkerData]);
    }
    if ($gate4MarkerAction !== 'no_marker') {
        throw new RuntimeException('Gate-4 marker cleanup returned an invalid action.');
    }

    // Nur dieser synthetische Lifecycle-Request benötigt Kompatibilität mit
    // den Test-Fixture-Statements, die benannte PDO-Parameter wiederverwenden.
    // Permanente APIs laufen in separaten Requests weiterhin mit nativen Prepares.
    $gate4EvidencePdo = be_db();
    $gate4EvidencePdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
}
