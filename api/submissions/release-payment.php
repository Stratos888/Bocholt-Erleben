<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/release-payment.php | Zweck: gibt vorgeprüfte Einzeltermin- und Aktivitaetspraesenz-Submissions zur Zahlung frei und versendet einen internen Zahlungsstart-Link; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

be_require_review_access();

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

function srp_get_app_base_url(): string
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

function srp_fetch_submission(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            intake_origin,
            payment_reference_key,
            payment_start_token_hash,
            organization_name_snapshot,
            contact_name_snapshot,
            email_snapshot,
            title,
            rejected_at,
            approved_at
         FROM submissions
         WHERE id = :submission_id
         LIMIT 1'
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

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_PAYMENT_RELEASE_ASSERT_V1 | Zweck: erlaubt Zahlungsfreigabe fuer Einzeltermine und Aktivitaetspraesenzen, blockiert aber andere Produktarten; Umfang: ersetzt srp_assert_releasable === */
function srp_assert_releasable(array $submission): void
{
    $submissionKind = (string)($submission['submission_kind'] ?? '');
    $paymentKind = (string)($submission['payment_kind'] ?? '');
    $intakeOrigin = (string)($submission['intake_origin'] ?? '');
    $modelKey = (string)($submission['requested_model_key'] ?? '');

    $isSingleEvent = $submissionKind === 'event' && $paymentKind === 'single' && $intakeOrigin === 'single_event';
    $isActivityPresence = $submissionKind === 'activity'
        && $paymentKind === 'subscription'
        && $intakeOrigin === 'activity_presence'
        && in_array($modelKey, ['activity_basic', 'activity_plus'], true);

    if (!$isSingleEvent && !$isActivityPresence) {
        throw new InvalidArgumentException('Diese Einreichung kann nicht über diesen Schritt zur Zahlung freigegeben werden.');
    }

    $status = (string)($submission['status'] ?? '');
    if (!in_array($status, ['pending_review', 'payment_released'], true)) {
        throw new InvalidArgumentException('Submission ist aktuell nicht zur Zahlungsfreigabe bereit.');
    }

    if (trim((string)($submission['rejected_at'] ?? '')) !== '') {
        throw new InvalidArgumentException('Submission wurde bereits abgelehnt.');
    }

    if (trim((string)($submission['approved_at'] ?? '')) !== '') {
        throw new InvalidArgumentException('Submission ist bereits abgeschlossen.');
    }
}
/* === END BLOCK: ACTIVITY_PRESENCE_PAYMENT_RELEASE_ASSERT_V1 === */

function srp_store_payment_token(PDO $pdo, int $submissionId, string $token): string
{
    $expiresAt = (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s');

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

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Zahlungsfreigabe konnte nicht gespeichert werden.');
    }

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

function srp_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function srp_build_payment_start_url(string $baseUrl, string $token): string
{
    return rtrim($baseUrl, '/') . '/zahlung-starten/?token=' . rawurlencode($token);
}

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_PAYMENT_RELEASE_MAIL_V1 | Zweck: versendet passende Zahlungsfreigabe-Mail fuer Einzeltermin oder Aktivitaetspraesenz; Umfang: ersetzt srp_send_payment_release_mail === */
function srp_send_payment_release_mail(array $submission, string $paymentUrl, string $expiresAt): void
{
    $email = trim((string)($submission['email_snapshot'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Submission hat keine gültige E-Mail-Adresse.');
    }

    $title = trim((string)($submission['title'] ?? ''));
    $reference = trim((string)($submission['payment_reference_key'] ?? ''));
    $isActivity = (string)($submission['submission_kind'] ?? 'event') === 'activity';

    $body = implode("\n", [
        'Hallo,',
        '',
        $isActivity
            ? 'deine Einreichung für eine Aktivitätspräsenz bei Bocholt erleben ist grundsätzlich geeignet.'
            : 'deine Veranstaltung wurde grundsätzlich für Bocholt erleben vorgeprüft.',
        '',
        ($isActivity ? 'Aktivität: ' : 'Veranstaltung: ') . ($title !== '' ? $title : 'ohne Titel'),
        'Referenz: ' . $reference,
        '',
        $isActivity
            ? 'Du kannst die Aktivitätspräsenz jetzt über diesen Link bezahlen:'
            : 'Du kannst den Einzeltermin jetzt über diesen Link bezahlen:',
        $paymentUrl,
        '',
        'Der Zahlungslink ist gültig bis: ' . $expiresAt,
        '',
        $isActivity
            ? 'Wichtig: Die Zahlung bedeutet noch keine automatische Veröffentlichung. Nach der Zahlung bereiten wir die Aktivität redaktionell auf und prüfen sie final vor der Veröffentlichung. Die Belegung im Tarif zählt erst ab Veröffentlichung.'
            : 'Wichtig: Die Zahlung bedeutet noch keine automatische Veröffentlichung. Nach der Zahlung prüfen wir die Einreichung final und veröffentlichen sie erst nach redaktioneller Freigabe.',
        '',
        'Viele Grüße',
        'Bocholt erleben',
    ]);

    be_send_mail($email, $isActivity ? 'Zahlung für deine Aktivitätspräsenz starten' : 'Zahlung für deine Veranstaltung starten', $body);
}
/* === END BLOCK: ACTIVITY_PRESENCE_PAYMENT_RELEASE_MAIL_V1 === */

try {
    $input = srp_read_json_body();
    $submissionId = srp_required_positive_int($input, 'submission_id', 'submission_id');
    $baseUrl = srp_get_app_base_url();

    $pdo = be_db();
    $pdo->beginTransaction();

    $submission = srp_fetch_submission($pdo, $submissionId);
    srp_assert_releasable($submission);

    $token = srp_generate_token();
    $expiresAt = srp_store_payment_token($pdo, $submissionId, $token);

    $pdo->commit();

    $paymentUrl = srp_build_payment_start_url($baseUrl, $token);
    srp_send_payment_release_mail($submission, $paymentUrl, $expiresAt);
    srp_mark_mail_sent($pdo, $submissionId);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'submission_id' => $submissionId,
            'submission_status' => 'payment_released',
            'payment_start_url' => $paymentUrl,
            'payment_start_token_expires_at' => $expiresAt,
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
        'message' => 'Payment release failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/release-payment.php === */
