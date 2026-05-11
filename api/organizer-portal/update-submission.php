<?php
declare(strict_types=1);
/* === BEGIN FILE: api/organizer-portal/update-submission.php | Zweck: erlaubt eingeloggten Veranstaltern das Ändern prüfbarer Event-Einreichungen und setzt bereits freigegebene Zukunftstermine nach Änderung zurück in die Prüfung; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function osu_read_json_body(): array
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

function osu_cookie_name(): string
{
    return 'be_organizer_portal_session';
}

function osu_read_session_token_from_cookie(): string
{
    $token = trim((string)($_COOKIE[osu_cookie_name()] ?? ''));
    if ($token === '') {
        throw new InvalidArgumentException('Organizer session cookie is missing.');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Organizer session cookie format is invalid.');
    }

    return $token;
}

function osu_fetch_portal_session(PDO $pdo, string $sessionTokenHash): array
{
    $statement = $pdo->prepare(
        'SELECT
            s.id AS portal_session_id,
            s.organizer_id,
            s.expires_at,
            s.revoked_at
         FROM organizer_portal_sessions s
         WHERE s.session_token_hash = :session_token_hash
         LIMIT 1'
    );

    $statement->execute([
        ':session_token_hash' => $sessionTokenHash,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new InvalidArgumentException('Organizer session is invalid.');
    }

    if (!empty($row['revoked_at'])) {
        throw new InvalidArgumentException('Organizer session was revoked.');
    }

    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    if ($expiresAt === '') {
        throw new RuntimeException('Organizer session expiry is missing.');
    }

    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiryUtc = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
    if ($expiryUtc < $nowUtc) {
        throw new InvalidArgumentException('Organizer session expired.');
    }

    return $row;
}

function osu_touch_portal_session(PDO $pdo, int $portalSessionId): void
{
    $statement = $pdo->prepare(
        'UPDATE organizer_portal_sessions
         SET last_seen_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $statement->execute([
        ':id' => $portalSessionId,
    ]);
}

function osu_nullable_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function osu_required_string(array $input, string $key, string $label): string
{
    $value = osu_nullable_string($input[$key] ?? null);
    if ($value === null) {
        throw new InvalidArgumentException($label . ' ist erforderlich.');
    }

    return $value;
}

function osu_required_positive_int(array $input, string $key, string $label): int
{
    $value = $input[$key] ?? null;
    if (!is_int($value) && !is_string($value) && !is_float($value)) {
        throw new InvalidArgumentException($label . ' ist erforderlich.');
    }

    $intValue = (int)$value;
    if ($intValue <= 0) {
        throw new InvalidArgumentException($label . ' ist ungültig.');
    }

    return $intValue;
}

function osu_boolish_to_int(mixed $value): int
{
    if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on' || $value === 'yes') {
        return 1;
    }

    return 0;
}

function osu_assert_max_length(?string $value, int $maxLength, string $label): void
{
    if ($value !== null && strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' ist zu lang.');
    }
}

function osu_validate_date_or_null(?string $date): ?string
{
    if ($date === null) {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$dt || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0 || $dt->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Datum muss im Format YYYY-MM-DD vorliegen.');
    }

    return $date;
}

function osu_validate_url_or_null(?string $url): ?string
{
    if ($url === null) {
        return null;
    }

    osu_assert_max_length($url, 2048, 'Link zur Veranstaltungsseite');

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Link zur Veranstaltungsseite ist ungültig.');
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Link zur Veranstaltungsseite muss mit http oder https beginnen.');
    }

    return $url;
}

/* === BEGIN BLOCK: ORGANIZER_SUBMISSION_EDIT_ELIGIBILITY_APPROVED_FUTURE_V2 | Zweck: erlaubt Änderungen an Entwürfen, offenen Einreichungen und bereits freigegebenen Zukunftsterminen; Umfang: komplette Editierbarkeitsprüfung für eine Submission === */
function osu_fetch_editable_submission(PDO $pdo, int $organizerId, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            submission_kind,
            status,
            start_date,
            organizer_edit_count
         FROM submissions
         WHERE id = :submission_id
           AND organizer_id = :organizer_id
           AND submission_kind = "event"
         LIMIT 1'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new InvalidArgumentException('Einreichung wurde nicht gefunden.');
    }

    $status = trim((string)($row['status'] ?? ''));
    if (in_array($status, ['draft', 'checkout_started', 'paid', 'in_review'], true)) {
        return $row;
    }

    if ($status === 'approved') {
        $startDate = trim((string)($row['start_date'] ?? ''));
        $todayUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

        if ($startDate !== '' && $startDate >= $todayUtc) {
            return $row;
        }
    }

    throw new InvalidArgumentException('Diese Einreichung kann im Veranstalterbereich nicht mehr geändert werden.');
}
/* === END BLOCK: ORGANIZER_SUBMISSION_EDIT_ELIGIBILITY_APPROVED_FUTURE_V2 === */

function osu_fetch_submission_response(PDO $pdo, int $organizerId, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            status,
            event_url,
            title,
            start_date,
            time_text,
            location_name,
            location_address,
            location_public_confirmed,
            description_text,
            organizer_edited_at,
            organizer_edit_count,
            updated_at
         FROM submissions
         WHERE id = :submission_id
           AND organizer_id = :organizer_id
         LIMIT 1'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Updated submission could not be loaded.');
    }

    return $row;
}

try {
    $input = osu_read_json_body();

    /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_UPDATE_INPUT_VALIDATION_V4 | Zweck: macht Adresse und Ortsbestätigung beim Ändern optional, damit bestehende freigegebene Zukunftstermine mit älteren Daten nicht blockieren; Umfang: Eingabe-Normalisierung und Pflichtfeldprüfung im Update-Endpunkt === */
    $submissionId = osu_required_positive_int($input, 'submission_id', 'Einreichung');
    $title = osu_required_string($input, 'title', 'Titel der Veranstaltung');
    $startDate = osu_validate_date_or_null(osu_required_string($input, 'start_date', 'Datum'));
    $timeText = osu_nullable_string($input['time_text'] ?? null);
    $locationName = osu_required_string($input, 'location_name', 'Veranstaltungsort / Location');
    $locationAddress = osu_nullable_string($input['location_address'] ?? null);
    $eventUrl = osu_validate_url_or_null(osu_nullable_string($input['event_url'] ?? null));
    $descriptionText = osu_nullable_string($input['description_text'] ?? null);
    $locationPublicConfirmed = osu_boolish_to_int($input['location_public_confirmed'] ?? null);

    osu_assert_max_length($title, 255, 'Titel der Veranstaltung');
    osu_assert_max_length($timeText, 64, 'Uhrzeit');
    osu_assert_max_length($locationName, 255, 'Veranstaltungsort / Location');
    osu_assert_max_length($locationAddress, 255, 'Straße, Hausnummer oder offizieller Treffpunkt');
    /* === END BLOCK: ORGANIZER_SUBMISSION_UPDATE_INPUT_VALIDATION_V4 === */

    $plainSessionToken = osu_read_session_token_from_cookie();
    $sessionTokenHash = hash('sha256', $plainSessionToken);

    $pdo = be_db();
    $pdo->beginTransaction();

    $sessionRow = osu_fetch_portal_session($pdo, $sessionTokenHash);
    osu_touch_portal_session($pdo, (int)$sessionRow['portal_session_id']);

    $organizerId = (int)$sessionRow['organizer_id'];
    $submission = osu_fetch_editable_submission($pdo, $organizerId, $submissionId);
    $nextEditCount = ((int)($submission['organizer_edit_count'] ?? 0)) + 1;

    /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_UPDATE_REOPEN_APPROVED_FUTURE_V4 | Zweck: speichert Änderungen und setzt bereits freigegebene Zukunftstermine zuverlässig zurück in die Kuratierprüfung; Umfang: komplettes UPDATE-Statement für Veranstalteränderungen === */
    $update = $pdo->prepare(
        'UPDATE submissions
         SET
            event_url = :event_url,
            title = :title,
            start_date = :start_date,
            time_text = :time_text,
            location_name = :location_name,
            location_address = :location_address,
            location_public_confirmed = :location_public_confirmed,
            description_text = :description_text,
            approved_at = CASE WHEN status = "approved" THEN NULL ELSE approved_at END,
            review_started_at = CASE WHEN status = "approved" THEN NULL ELSE review_started_at END,
            status = CASE WHEN status = "approved" THEN "in_review" ELSE status END,
            organizer_edited_at = UTC_TIMESTAMP(),
            organizer_edit_count = :organizer_edit_count,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :submission_id
           AND organizer_id = :organizer_id
           AND submission_kind = "event"
           AND (
                status IN ("draft", "checkout_started", "paid", "in_review")
                OR (status = "approved" AND start_date IS NOT NULL AND start_date >= CURRENT_DATE())
           )'
    );
    /* === END BLOCK: ORGANIZER_SUBMISSION_UPDATE_REOPEN_APPROVED_FUTURE_V4 === */

    $update->execute([
        ':event_url' => $eventUrl,
        ':title' => $title,
        ':start_date' => $startDate,
        ':time_text' => $timeText,
        ':location_name' => $locationName,
        ':location_address' => $locationAddress,
        ':location_public_confirmed' => $locationPublicConfirmed,
        ':description_text' => $descriptionText,
        ':organizer_edit_count' => $nextEditCount,
        ':submission_id' => $submissionId,
        ':organizer_id' => $organizerId,
    ]);

    /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_UPDATE_AFFECTED_ROWS_NOTE_V4 | Zweck: verhindert Fehlalarm, wenn MySQL bei identischen Eingaben 0 geänderte Zeilen meldet; Umfang: ersetzte rowCount-Fehlerprüfung === */
    // MySQL kann 0 geänderte Zeilen melden, wenn eingereichte Werte bereits gespeichert waren.
    // Die Berechtigungsprüfung ist vorher erfolgt; die Antwort wird aus der erneut geladenen Einreichung gebaut.
    /* === END BLOCK: ORGANIZER_SUBMISSION_UPDATE_AFFECTED_ROWS_NOTE_V4 === */

    $updatedSubmission = osu_fetch_submission_response($pdo, $organizerId, $submissionId);

    $pdo->commit();

    be_json_response(200, [
        'status' => 'ok',
        'message' => 'Änderung gespeichert. Wir prüfen die aktuelle Version deiner Einreichung.',
        'data' => [
            'submission' => $updatedSubmission,
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
        'message' => 'Submission update failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/organizer-portal/update-submission.php === */
