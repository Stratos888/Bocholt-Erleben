<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/reject.php | Zweck: markiert bezahlte Formular-Einreichungen nach manueller Kuratier-Entscheidung als abgelehnt; Umfang: JSON-POST-Endpoint ohne Kontingentverbrauch === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function srj_read_json_body(): array
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

function srj_required_positive_int(array $input, string $key, string $label): int
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

try {
    $input = srj_read_json_body();
    $submissionId = srj_required_positive_int($input, 'submission_id', 'submission_id');
    $reason = trim((string)($input['reason'] ?? ''));

    $pdo = be_db();
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
           AND submission_kind = :submission_kind
           AND status IN ("paid", "in_review")'
    );

    $statement->bindValue(':status', 'rejected', PDO::PARAM_STR);
    $statement->bindValue(':reason', $reason, PDO::PARAM_STR);
    $statement->bindValue(':reason_value_new', $reason, PDO::PARAM_STR);
    $statement->bindValue(':reason_value_append', $reason, PDO::PARAM_STR);
    $statement->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
    $statement->bindValue(':submission_kind', 'event', PDO::PARAM_STR);
    $statement->execute();

    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Submission ist aktuell nicht ablehnbar oder wurde nicht gefunden.');
    }

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
        'message' => 'Submission rejection failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/reject.php === */
