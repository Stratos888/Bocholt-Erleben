<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/reject.php | Zweck: lehnt DB-Submissions in der Review-Inbox ab und informiert den Einreicher; Umfang: komplette Datei === */

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

function sr_read_json_body(): array
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

function sr_required_positive_int(array $input, string $key, string $label): int
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

function sr_required_text(array $input, string $key, string $label): string
{
    $value = trim((string)($input[$key] ?? ''));

    if ($value === '') {
        throw new InvalidArgumentException($label . ' ist erforderlich.');
    }

    return $value;
}

function sr_fetch_submission(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            submission_kind,
            status,
            payment_kind,
            intake_origin,
            payment_reference_key,
            contact_name_snapshot,
            email_snapshot,
            title
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

function sr_assert_rejectable(array $submission): void
{
    $status = (string)($submission['status'] ?? '');
    if (!in_array($status, ['pending_review', 'payment_released', 'checkout_started', 'paid', 'in_review'], true)) {
        throw new InvalidArgumentException('Submission kann in diesem Status nicht abgelehnt werden.');
    }
}

function sr_send_rejection_mail(array $submission, ?string $reason): void
{
    $email = trim((string)($submission['email_snapshot'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    /* === BEGIN BLOCK: MAIL_SYSTEM_REJECTION_TOPIC_V1 | Zweck: nutzt zentrale Mail-Topics fuer Ablehnungs-Mails von Einzelterminen und Aktivitaeten; Umfang: ersetzt lokale Plain-Text-Mail in sr_send_rejection_mail === */
    $isActivity = trim((string)($submission['submission_kind'] ?? 'event')) === 'activity';

    $mail = be_build_system_mail_topic(
        $isActivity ? 'rejection_activity' : 'rejection_event',
        [
            'title' => trim((string)($submission['title'] ?? '')),
            'reference' => trim((string)($submission['payment_reference_key'] ?? '')),
            'contact_name' => trim((string)($submission['contact_name_snapshot'] ?? '')),
            'reason' => trim((string)($reason ?? '')),
        ]
    );

    try {
        be_send_mail(
            $email,
            $mail['subject'],
            $mail['text_body'],
            $mail['to_name'],
            $mail['html_body']
        );
    /* === END BLOCK: MAIL_SYSTEM_REJECTION_TOPIC_V1 === */
    } catch (Throwable $error) {
        error_log('Submission rejection mail failed: ' . $error->getMessage());
    }
}

try {
    $input = sr_read_json_body();
    $submissionId = sr_required_positive_int($input, 'submission_id', 'submission_id');
    $reason = sr_required_text($input, 'reason', 'Ablehnungsgrund');

    $pdo = be_db();
    $submission = sr_fetch_submission($pdo, $submissionId);
    sr_assert_rejectable($submission);

    $statement = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            review_started_at = CASE
                WHEN review_started_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE review_started_at
            END,
            rejected_at = CASE
                WHEN rejected_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE rejected_at
            END,
            notes_text = CASE
                WHEN :reason = "" THEN notes_text
                WHEN notes_text IS NULL OR notes_text = "" THEN CONCAT("Ablehnungsgrund: ", :reason_value_new)
                ELSE CONCAT(notes_text, "\nAblehnungsgrund: ", :reason_value_append)
            END,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :submission_id
           AND submission_kind IN ("event", "activity")
           AND status IN ("pending_review", "payment_released", "checkout_started", "paid", "in_review")'
    );

    $reasonValue = $reason ?? '';
    $statement->bindValue(':status', 'rejected', PDO::PARAM_STR);
    $statement->bindValue(':reason', $reasonValue, PDO::PARAM_STR);
    $statement->bindValue(':reason_value_new', $reasonValue, PDO::PARAM_STR);
    $statement->bindValue(':reason_value_append', $reasonValue, PDO::PARAM_STR);
    $statement->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
    $statement->execute();

    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Submission ist aktuell nicht ablehnbar oder wurde nicht gefunden.');
    }

    sr_send_rejection_mail($submission, $reason);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'submission_id' => $submissionId,
            'submission_status' => 'rejected',
        ],
    ]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Submission konnte nicht abgelehnt werden.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/reject.php === */
