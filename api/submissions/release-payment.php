<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/release-payment.php | Zweck: gibt vorgeprüfte Einzeltermin-Submissions zur Zahlung frei und versendet einen internen Zahlungsstart-Link; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

/* === BEGIN BLOCK: SUBMISSION_REVIEW_ENDPOINT_ACCESS_GUARD_V1 | Zweck: verhindert öffentlichen Zugriff auf DB-Review-Endpunkt; Umfang: prüft Review-Passwort nach Method-Check === */
be_require_review_access();
/* === END BLOCK: SUBMISSION_REVIEW_ENDPOINT_ACCESS_GUARD_V1 === */

function srp_read_json_body(): array
{
    $raw = file_get_contents('php://input');

    if (!is_string($raw) || trim($raw) === '') {
        throw new InvalidArgumentException('Request body is empty.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be valid JSON.');
    }

    return $decoded;
}

function srp_required_positive_int(array $input, string $key, string $label): int
{
    $value = $input[$key] ?? null;

    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' is required.');
    }

    $intValue = (int)$value;
    if ($intValue <= 0) {
        throw new InvalidArgumentException($label . ' must be a positive integer.');
    }

    return $intValue;
}

function srp_app_base_url(): string
{
    $config = be_get_config();
    $app = $config['app'] ?? null;
    if (!is_array($app)) {
        throw new RuntimeException('App config is missing.');
    }

    $baseUrl = rtrim(trim((string)($app['base_url'] ?? '')), '/');
    if ($baseUrl === '') {
        throw new RuntimeException('App base URL is missing.');
    }

    return $baseUrl;
}

function srp_generate_payment_token(): string
{
    return bin2hex(random_bytes(32));
}

function srp_fetch_submission_for_update(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            intake_origin,
            payment_reference_key,
            organization_name_snapshot,
            contact_name_snapshot,
            email_snapshot,
            title,
            paid_at,
            approved_at,
            rejected_at
         FROM submissions
         WHERE id = :submission_id
         LIMIT 1
         FOR UPDATE'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
    ]);

    $submission = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($submission)) {
        throw new InvalidArgumentException('Submission wurde nicht gefunden.');
    }

    return $submission;
}

function srp_assert_releasable(array $submission): void
{
    if ((string)($submission['submission_kind'] ?? '') !== 'event') {
        throw new InvalidArgumentException('Nur Event-Submissions können zur Zahlung freigegeben werden.');
    }

    if ((string)($submission['payment_kind'] ?? '') !== 'single') {
        throw new InvalidArgumentException('Nur Einzeltermin-Submissions können per Zahlungslink freigegeben werden.');
    }

    if ((string)($submission['intake_origin'] ?? '') !== 'single_event') {
        throw new InvalidArgumentException('Diese Submission ist kein Einzeltermin.');
    }

    $status = (string)($submission['status'] ?? '');
    if (!in_array($status, ['pending_review', 'payment_released', 'checkout_started'], true)) {
        throw new InvalidArgumentException('Submission ist in diesem Status nicht zur Zahlung freigebbar.');
    }

    if (!empty($submission['paid_at'])) {
        throw new InvalidArgumentException('Submission wurde bereits bezahlt.');
    }

    if (!empty($submission['approved_at']) || !empty($submission['rejected_at'])) {
        throw new InvalidArgumentException('Submission ist bereits abgeschlossen.');
    }
}

function srp_store_payment_token(PDO $pdo, int $submissionId, string $token): string
{
    $expiresAt = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

    $statement = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            payment_start_token_hash = :payment_start_token_hash,
            payment_start_token_expires_at = :payment_start_token_expires_at,
            payment_released_at = CASE
                WHEN payment_released_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE payment_released_at
            END,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :submission_id'
    );

    $statement->execute([
        ':status' => 'payment_released',
        ':payment_start_token_hash' => hash('sha256', $token),
        ':payment_start_token_expires_at' => $expiresAt,
        ':submission_id' => $submissionId,
    ]);

    return $expiresAt;
}

function srp_mark_mail_sent(PDO $pdo, int $submissionId): void
{
    $statement = $pdo->prepare(
        'UPDATE submissions
         SET
            payment_released_mail_sent_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :submission_id'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
    ]);
}

function srp_send_payment_release_mail(array $submission, string $paymentUrl, string $expiresAt): void
{
    $email = trim((string)($submission['email_snapshot'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Submission-E-Mail ist ungültig.');
    }

    $title = trim((string)($submission['title'] ?? ''));
    $reference = trim((string)($submission['payment_reference_key'] ?? ''));

    $body = implode("\n", [
        'Hallo,',
        '',
        'deine Veranstaltung wurde grundsätzlich für Bocholt erleben vorgeprüft.',
        '',
        'Veranstaltung: ' . ($title !== '' ? $title : 'ohne Titel'),
        'Referenz: ' . $reference,
        '',
        'Du kannst den Einzeltermin jetzt über diesen Link bezahlen:',
        $paymentUrl,
        '',
        'Der Zahlungslink ist gültig bis: ' . $expiresAt,
        '',
        'Wichtig: Die Zahlung bedeutet noch keine automatische Veröffentlichung. Nach der Zahlung prüfen wir die Einreichung final und veröffentlichen sie erst nach redaktioneller Freigabe.',
        '',
        'Viele Grüße',
        'Bocholt erleben',
    ]);

    be_send_mail($email, 'Zahlung für deine Veranstaltung starten', $body);
}

try {
    $input = srp_read_json_body();
    $submissionId = srp_required_positive_int($input, 'submission_id', 'submission_id');

    $pdo = be_db();
    $pdo->beginTransaction();

    $submission = srp_fetch_submission_for_update($pdo, $submissionId);
    srp_assert_releasable($submission);

    $token = srp_generate_payment_token();
    $expiresAt = srp_store_payment_token($pdo, $submissionId, $token);
    $paymentUrl = srp_app_base_url() . '/zahlung-starten/?token=' . rawurlencode($token);

    $pdo->commit();

    srp_send_payment_release_mail($submission, $paymentUrl, $expiresAt);
    srp_mark_mail_sent(be_db(), $submissionId);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'submission_id' => $submissionId,
            'submission_status' => 'payment_released',
            'payment_start_token_expires_at' => $expiresAt,
            'mail_sent' => true,
        ],
    ]);
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    be_json_response(500, [
        'status' => 'error',
        'message' => 'Zahlungsfreigabe konnte nicht verarbeitet werden.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/release-payment.php === */
