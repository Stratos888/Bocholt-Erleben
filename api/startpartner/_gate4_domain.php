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
}
