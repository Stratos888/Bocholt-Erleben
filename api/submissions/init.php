<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/init.php | Zweck: legt fuer den Publish-Funnel eine erste Organizer-/Submission-Kombination serverseitig an; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

function pf_read_json_body(): array
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

function pf_nullable_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function pf_required_string(array $input, string $key, string $label): string
{
    $value = pf_nullable_string($input[$key] ?? null);
    if ($value === null) {
        throw new InvalidArgumentException($label . ' is required.');
    }

    return $value;
}

function pf_normalize_email(string $email): string
{
    $normalized = mb_strtolower(trim($email));
    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('E-Mail-Adresse ist ungültig.');
    }

    return $normalized;
}

function pf_validate_model_key(string $modelKey): string
{
    $allowed = ['single', 'starter', 'active', 'unlimited'];
    if (!in_array($modelKey, $allowed, true)) {
        throw new InvalidArgumentException('Gewünschtes Modell ist ungültig.');
    }

    return $modelKey;
}

function pf_validate_date_or_null(?string $date): ?string
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

function pf_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

try {
    $input = pf_read_json_body();

    $requestedModelKey = pf_validate_model_key(
        pf_required_string($input, 'requested_model_key', 'Gewünschtes Modell')
    );

    $organizationName = pf_required_string($input, 'organization_name', 'Veranstalter / Organisation');
    $contactName = pf_nullable_string($input['contact_name'] ?? null);

    $emailInput = pf_required_string($input, 'email', 'E-Mail-Adresse');
    $emailNormalized = pf_normalize_email($emailInput);

    $eventUrl = pf_nullable_string($input['event_url'] ?? null);
    $title = pf_nullable_string($input['title'] ?? null);
    $startDate = pf_validate_date_or_null(pf_nullable_string($input['start_date'] ?? null));
    $timeText = pf_nullable_string($input['time_text'] ?? null);
    $locationName = pf_nullable_string($input['location_name'] ?? null);
    $ticketUrl = pf_nullable_string($input['ticket_url'] ?? null);
    $descriptionText = pf_nullable_string($input['description_text'] ?? null);
    $notesText = pf_nullable_string($input['notes_text'] ?? null);

    if ($eventUrl === null && ($title === null || $startDate === null || $locationName === null)) {
        throw new InvalidArgumentException('Ohne Event-Link sind mindestens Titel, Datum und Ort erforderlich.');
    }

    $paymentKind = $requestedModelKey === 'single' ? 'single' : 'subscription';
    $paymentReferenceKey = pf_uuid_v4();

    $pdo = be_db();
    $pdo->beginTransaction();

    $organizerUpsert = $pdo->prepare(
        'INSERT INTO organizers (
            organization_name,
            contact_name,
            email,
            email_normalized,
            default_plan_key
        ) VALUES (
            :organization_name,
            :contact_name,
            :email,
            :email_normalized,
            :default_plan_key
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            organization_name = VALUES(organization_name),
            contact_name = VALUES(contact_name),
            email = VALUES(email),
            default_plan_key = VALUES(default_plan_key)'
    );

    $organizerUpsert->execute([
        ':organization_name' => $organizationName,
        ':contact_name' => $contactName,
        ':email' => $emailInput,
        ':email_normalized' => $emailNormalized,
        ':default_plan_key' => $requestedModelKey,
    ]);

    $organizerId = (int)$pdo->lastInsertId();

    $submissionInsert = $pdo->prepare(
        'INSERT INTO submissions (
            organizer_id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            payment_reference_key,
            organization_name_snapshot,
            contact_name_snapshot,
            email_snapshot,
            event_url,
            title,
            start_date,
            time_text,
            location_name,
            ticket_url,
            description_text,
            notes_text
        ) VALUES (
            :organizer_id,
            :submission_kind,
            :status,
            :requested_model_key,
            :payment_kind,
            :payment_reference_key,
            :organization_name_snapshot,
            :contact_name_snapshot,
            :email_snapshot,
            :event_url,
            :title,
            :start_date,
            :time_text,
            :location_name,
            :ticket_url,
            :description_text,
            :notes_text
        )'
    );

    $submissionInsert->execute([
        ':organizer_id' => $organizerId,
        ':submission_kind' => 'event',
        ':status' => 'draft',
        ':requested_model_key' => $requestedModelKey,
        ':payment_kind' => $paymentKind,
        ':payment_reference_key' => $paymentReferenceKey,
        ':organization_name_snapshot' => $organizationName,
        ':contact_name_snapshot' => $contactName,
        ':email_snapshot' => $emailInput,
        ':event_url' => $eventUrl,
        ':title' => $title,
        ':start_date' => $startDate,
        ':time_text' => $timeText,
        ':location_name' => $locationName,
        ':ticket_url' => $ticketUrl,
        ':description_text' => $descriptionText,
        ':notes_text' => $notesText,
    ]);

    $submissionId = (int)$pdo->lastInsertId();

    $pdo->commit();

    be_json_response(201, [
        'status' => 'ok',
        'data' => [
            'organizer_id' => $organizerId,
            'submission_id' => $submissionId,
            'submission_status' => 'draft',
            'requested_model_key' => $requestedModelKey,
            'payment_kind' => $paymentKind,
            'payment_reference_key' => $paymentReferenceKey,
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
        'message' => 'Submission init failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/init.php === */
