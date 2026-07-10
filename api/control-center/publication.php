<?php
declare(strict_types=1);

require_once __DIR__ . '/_content_history.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Änderungs-ID fehlt.');
    $change = be_cc_get_content_change($id);
    $state = (string)$change['publication_state'];

    if ($state === 'confirmed') {
        be_json_response(200, ['status' => 'ok', 'data' => ['change' => $change, 'verified' => true, 'message' => 'Öffentliche Wirkung bestätigt.']]);
    }
    if (in_array($state, ['deploy_failed','verification_failed'], true)) {
        be_json_response(409, ['status' => 'error', 'message' => (string)($change['publication_error'] ?: 'Veröffentlichung fehlgeschlagen.'), 'data' => ['change' => $change]]);
    }

    $verification = be_cc_verify_public_change($change);
    if (!empty($verification['verified'])) {
        be_cc_update_content_change($id, 'confirmed');
        be_cc_complete_publication_issue((string)$change['object_type'], (string)$change['object_id']);
        $change = be_cc_get_content_change($id);
        be_json_response(200, ['status' => 'ok', 'data' => ['change' => $change, 'verified' => true, 'message' => $verification['reason']]]);
    }

    $createdAt = new DateTimeImmutable((string)$change['created_at']);
    $ageSeconds = time() - $createdAt->getTimestamp();
    if ($ageSeconds >= 300) {
        be_cc_update_content_change($id, 'verification_failed', ['error' => $verification['reason']]);
        $change = be_cc_get_content_change($id);
        be_cc_upsert_publication_issue($change, 'Die öffentliche Wirkung konnte nach fünf Minuten nicht bestätigt werden: ' . $verification['reason'], 'blocked');
        be_json_response(409, ['status' => 'error', 'message' => 'Öffentliche Wirkung konnte nicht bestätigt werden. Unter Arbeit wurde ein blockierter Vorgang angelegt.', 'data' => ['change' => $change]]);
    }

    be_cc_update_content_change($id, 'waiting', ['error' => $verification['reason']]);
    $change = be_cc_get_content_change($id);
    be_json_response(202, ['status' => 'ok', 'data' => ['change' => $change, 'verified' => false, 'message' => 'Aktualisierung läuft. Öffentliche Wirkung ist noch nicht bestätigt.']]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Veröffentlichungsstatus konnte nicht geprüft werden.', 'error_message' => $error->getMessage()]);
}
