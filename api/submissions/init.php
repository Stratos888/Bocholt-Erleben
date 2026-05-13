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

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_MODEL_VALIDATION_V1 | Zweck: erweitert Einreichungen um Aktivitaetspraesenzen mit eigenen Modellschluesseln; Umfang: ersetzt pf_validate_model_key und ergänzt pf_validate_submission_kind === */
function pf_validate_submission_kind(?string $submissionKind): string
{
    $normalized = $submissionKind === null ? 'event' : mb_strtolower(trim($submissionKind));
    $allowed = ['event', 'activity'];

    if (!in_array($normalized, $allowed, true)) {
        throw new InvalidArgumentException('Einreichungsart ist ungültig.');
    }

    return $normalized;
}

function pf_validate_model_key(string $modelKey, string $submissionKind): string
{
    $allowed = $submissionKind === 'activity'
        ? ['activity_basic', 'activity_plus']
        : ['single', 'starter', 'active', 'unlimited'];

    if (!in_array($modelKey, $allowed, true)) {
        throw new InvalidArgumentException('Einreichungsweg ist ungültig.');
    }

    return $modelKey;
}
/* === END BLOCK: ACTIVITY_PRESENCE_MODEL_VALIDATION_V1 === */

/* === BEGIN BLOCK: PUBLISH_SUBMISSION_REVIEW_FIELD_HELPERS_V1 | Zweck: validiert optionale Datumswerte und normalisiert Boolean-Werte für prüfpflichtige Ortsfreigaben; Umfang: ersetzt pf_validate_date_or_null und ergänzt pf_boolish_to_int === */
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

function pf_boolish_to_int(mixed $value): int
{
    if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on' || $value === 'yes') {
        return 1;
    }

    return 0;
}
/* === END BLOCK: PUBLISH_SUBMISSION_REVIEW_FIELD_HELPERS_V1 === */

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

function pf_get_portal_cookie_name(): string
{
    /* === BEGIN FUNCTION: pf_get_portal_cookie_name | Zweck: zentraler Cookie-Name fuer optionale Portal-Session im Einreichungs-Flow; Umfang: reine Konstantenfunktion === */
    return 'be_organizer_portal_session';
    /* === END FUNCTION: pf_get_portal_cookie_name === */
}

function pf_read_portal_session_token_or_null(): ?string
{
    /* === BEGIN FUNCTION: pf_read_portal_session_token_or_null | Zweck: liest optionale Portal-Session ohne oeffentliche Einreichungen zu blockieren; Umfang: Cookie-Validierung === */
    $token = trim((string)($_COOKIE[pf_get_portal_cookie_name()] ?? ''));
    if ($token === '') {
        return null;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    return $token;
    /* === END FUNCTION: pf_read_portal_session_token_or_null === */
}

function pf_fetch_portal_session_or_null(PDO $pdo): ?array
{
    /* === BEGIN FUNCTION: pf_fetch_portal_session_or_null | Zweck: ermittelt gueltige eingeloggte Veranstalter-Session fuer Abo-Reuse; Umfang: Session- und Organizer-Lookup === */
    $plainSessionToken = pf_read_portal_session_token_or_null();
    if ($plainSessionToken === null) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT
            s.id AS portal_session_id,
            s.organizer_id,
            s.expires_at,
            s.revoked_at,
            o.organization_name,
            o.contact_name,
            o.email,
            o.email_normalized,
            o.default_plan_key
         FROM organizer_portal_sessions s
         INNER JOIN organizers o ON o.id = s.organizer_id
         WHERE s.session_token_hash = :session_token_hash
         LIMIT 1'
    );

    $statement->execute([
        ':session_token_hash' => hash('sha256', $plainSessionToken),
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !empty($row['revoked_at'])) {
        return null;
    }

    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    if ($expiresAt === '') {
        return null;
    }

    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiryUtc = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
    if ($expiryUtc < $nowUtc) {
        return null;
    }

    return $row;
    /* === END FUNCTION: pf_fetch_portal_session_or_null === */
}

function pf_fetch_active_subscription_or_null(PDO $pdo, int $organizerId): ?array
{
    /* === BEGIN FUNCTION: pf_fetch_active_subscription_or_null | Zweck: findet nutzbare aktive Mitgliedschaft fuer eingeloggte Abo-Einreichungen; Umfang: Subscription-Lookup === */
    $statement = $pdo->prepare(
        'SELECT
            id,
            plan_key,
            status
         FROM subscriptions
         WHERE organizer_id = :organizer_id
           AND status IN ("active", "trialing")
         ORDER BY id DESC
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
    /* === END FUNCTION: pf_fetch_active_subscription_or_null === */
}

function pf_find_organizer_by_email(PDO $pdo, string $emailNormalized): ?array
{
    /* === BEGIN FUNCTION: pf_find_organizer_by_email | Zweck: verhindert stilles Ueberschreiben bestehender Veranstalterdaten; Umfang: reine E-Mail-Lookup-Funktion === */
    $statement = $pdo->prepare(
        'SELECT
            id,
            organization_name,
            contact_name,
            email,
            email_normalized,
            default_plan_key
         FROM organizers
         WHERE email_normalized = :email_normalized
         LIMIT 1'
    );

    $statement->execute([
        ':email_normalized' => $emailNormalized,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
    /* === END FUNCTION: pf_find_organizer_by_email === */
}
/* === BEGIN BLOCK: PUBLISH_SUBMISSION_RECEIVED_MAIL_V1 | Zweck: sendet nach Einzeltermin-Einreichung eine Eingangsbestätigung ohne Zahlungsaufforderung; Umfang: Mail-Helfer für init.php === */
function pf_send_submission_received_mail(array $submissionData): void
{
    $to = trim((string)($submissionData['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $title = trim((string)($submissionData['title'] ?? ''));
    $reference = trim((string)($submissionData['payment_reference_key'] ?? ''));

    $body = implode("\n", [
        'Hallo,',
        '',
        'deine Veranstaltung wurde bei Bocholt erleben zur Prüfung eingereicht.',
        '',
        'Veranstaltung: ' . ($title !== '' ? $title : 'ohne Titel'),
        'Referenz: ' . $reference,
        '',
        'Wir prüfen jetzt, ob der Termin grundsätzlich zu Bocholt erleben passt.',
        'Wenn die Veranstaltung grundsätzlich passt, erhältst du anschließend einen Zahlungslink für den Einzeltermin.',
        'Die Zahlung bedeutet noch keine automatische Veröffentlichung. Die Veröffentlichung erfolgt erst nach redaktioneller Freigabe.',
        '',
        'Viele Grüße',
        'Bocholt erleben',
    ]);

    try {
        be_send_mail($to, 'Deine Veranstaltung wurde zur Prüfung eingereicht', $body);
    } catch (Throwable $error) {
        error_log('Submission received mail failed: ' . $error->getMessage());
    }
}
/* === END BLOCK: PUBLISH_SUBMISSION_RECEIVED_MAIL_V1 === */
function pf_insert_organizer(PDO $pdo, string $organizationName, ?string $contactName, string $emailInput, string $emailNormalized, string $defaultPlanKey): int
{
    /* === BEGIN FUNCTION: pf_insert_organizer | Zweck: legt neue Veranstalter ohne ON DUPLICATE UPDATE an; Umfang: Insert-only fuer neue E-Mail-Adressen === */
    $statement = $pdo->prepare(
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
        )'
    );

    $statement->execute([
        ':organization_name' => $organizationName,
        ':contact_name' => $contactName,
        ':email' => $emailInput,
        ':email_normalized' => $emailNormalized,
        ':default_plan_key' => $defaultPlanKey,
    ]);

    return (int)$pdo->lastInsertId();
    /* === END FUNCTION: pf_insert_organizer === */
}

try {
    /* === BEGIN BLOCK: PUBLISH_SUBMISSION_INIT_IDENTITY_GUARD_V2 | Zweck: verhindert Veranstalter-Overwrite und erzwingt unveraenderbare Abo-Reuse-Daten serverseitig; Umfang: kompletter try-Hauptblock bis vor Error-Handling === */
    $input = pf_read_json_body();

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_SUBMISSION_KIND_PARSE_V1 | Zweck: liest Einreichungsart vor Tarifvalidierung, damit Aktivitaetspraesenzen eigene Modellschluessel nutzen koennen; Umfang: ersetzt requestedModelKey-Parsing === */
    $submissionKind = pf_validate_submission_kind(
        pf_nullable_string($input['submission_kind'] ?? null)
    );

    $requestedModelKey = pf_validate_model_key(
        pf_required_string($input, 'requested_model_key', 'Einreichungsweg'),
        $submissionKind
    );
    /* === END BLOCK: ACTIVITY_PRESENCE_SUBMISSION_KIND_PARSE_V1 === */

    $organizationName = pf_required_string($input, 'organization_name', 'Veranstalter / Organisation');
    $contactName = pf_nullable_string($input['contact_name'] ?? null);

    $emailInput = pf_required_string($input, 'email', 'E-Mail-Adresse');
    $emailNormalized = pf_normalize_email($emailInput);

    /* === BEGIN BLOCK: PUBLISH_SUBMISSION_REVIEW_FIELDS_FROM_INPUT_V1 | Zweck: übernimmt Review-Herkunftsfelder und prüfpflichtige Ortsangaben aus dem Formular, ohne bestehende Link-/Fallback-Validierung zu verschärfen; Umfang: ersetzt Event-Feld-Parsing bis payment_reference_key === */
    $eventUrl = pf_nullable_string($input['event_url'] ?? null);
    $title = pf_nullable_string($input['title'] ?? null);
    $startDate = pf_validate_date_or_null(pf_nullable_string($input['start_date'] ?? null));
    $timeText = pf_nullable_string($input['time_text'] ?? null);
    $locationName = pf_nullable_string($input['location_name'] ?? null);
    $locationAddress = pf_nullable_string($input['location_address'] ?? null);
    $locationPublicConfirmed = pf_boolish_to_int($input['location_public_confirmed'] ?? null);
    $ticketUrl = pf_nullable_string($input['ticket_url'] ?? null);
    $descriptionText = pf_nullable_string($input['description_text'] ?? null);
    $notesText = pf_nullable_string($input['notes_text'] ?? null);

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_INPUT_VALIDATION_V1 | Zweck: trennt Event-Pflichtlogik von Aktivitaetspraesenz-Pflichtlogik und setzt passenden Startstatus; Umfang: ersetzt Validierung, paymentKind/intakeOrigin und paymentReferenceKey-Zuweisung === */
    if ($submissionKind === 'activity') {
        if ($title === null || $locationName === null || $locationAddress === null || $descriptionText === null) {
            throw new InvalidArgumentException('Für eine Aktivitätspräsenz sind mindestens Name, Anbieter/Ort, Adresse oder Treffpunkt und Beschreibung erforderlich.');
        }

        if ($locationPublicConfirmed !== 1) {
            throw new InvalidArgumentException('Bitte bestätige, dass die Aktivität eingereicht werden darf und der Ort öffentlich genannt werden darf.');
        }

        $eventUrl = null;
        $startDate = null;
        $paymentKind = 'subscription';
        $intakeOrigin = 'activity_presence';
        $initialStatus = 'pending_review';
        $checkoutRequired = false;
    } else {
        if ($eventUrl === null && ($title === null || $startDate === null || $locationName === null)) {
            throw new InvalidArgumentException('Ohne Event-Link sind mindestens Titel, Datum und Ort erforderlich.');
        }

        $paymentKind = $requestedModelKey === 'single' ? 'single' : 'subscription';
        $intakeOrigin = $paymentKind === 'subscription' ? 'membership' : 'single_event';
        $initialStatus = $paymentKind === 'single' ? 'pending_review' : 'draft';
        $checkoutRequired = $paymentKind !== 'single';
    }

    $paymentReferenceKey = pf_uuid_v4();
    /* === END BLOCK: ACTIVITY_PRESENCE_INPUT_VALIDATION_V1 === */
    /* === END BLOCK: PUBLISH_SUBMISSION_REVIEW_FIELDS_FROM_INPUT_V1 === */

    $pdo = be_db();
    $pdo->beginTransaction();

    $portalSession = pf_fetch_portal_session_or_null($pdo);
    $activeSubscription = null;
    $organizerId = 0;

    if (is_array($portalSession)) {
        $organizerId = (int)$portalSession['organizer_id'];
        $activeSubscription = pf_fetch_active_subscription_or_null($pdo, $organizerId);

        /* === BEGIN BLOCK: EVENT_ONLY_MEMBERSHIP_REUSE_GUARD_V1 | Zweck: bestehende Event-Abo-Reuse-Logik unveraendert lassen und Aktivitaetspraesenzen nicht auf Event-Mitgliedschaften umbiegen; Umfang: ersetzt Abo-Reuse-if === */
        if ($submissionKind === 'event' && $requestedModelKey !== 'single' && is_array($activeSubscription)) {
            $organizationName = trim((string)$portalSession['organization_name']);
            $contactName = pf_nullable_string($portalSession['contact_name'] ?? null);
            $emailInput = trim((string)$portalSession['email']);
            $emailNormalized = trim((string)$portalSession['email_normalized']);
            $requestedModelKey = trim((string)$activeSubscription['plan_key']);
            $paymentKind = 'subscription';
            $intakeOrigin = 'membership';
            $initialStatus = 'draft';
            $checkoutRequired = true;
        }
        /* === END BLOCK: EVENT_ONLY_MEMBERSHIP_REUSE_GUARD_V1 === */
    }

    if ($organizerId <= 0) {
        $existingOrganizer = pf_find_organizer_by_email($pdo, $emailNormalized);

        if (is_array($existingOrganizer)) {
            /* === BEGIN BLOCK: EVENT_ONLY_EXISTING_ORGANIZER_BLOCK_V1 | Zweck: blockiert weiterhin neue Event-Mitgliedschaften mit vorhandener E-Mail, erlaubt aber Aktivitaetspraesenz-Einreichungen fuer bestehende Anbieter; Umfang: ersetzt existingOrganizer-Abzweig === */
            if ($submissionKind === 'event' && $requestedModelKey !== 'single') {
                $pdo->rollBack();

                be_json_response(409, [
                    'status' => 'error',
                    'message' => 'Für diese E-Mail gibt es bereits eine Einreichung oder Mitgliedschaft. Bitte öffne deinen Bereich per E-Mail-Link.',
                    'data' => [
                        'existing_organizer' => true,
                        'login_url' => '/fuer-veranstalter/login/?email=' . rawurlencode($emailInput),
                    ],
                ]);
            }
            /* === END BLOCK: EVENT_ONLY_EXISTING_ORGANIZER_BLOCK_V1 === */

            $organizerId = (int)$existingOrganizer['id'];
        } else {
            $organizerId = pf_insert_organizer(
                $pdo,
                $organizationName,
                $contactName,
                $emailInput,
                $emailNormalized,
                $requestedModelKey
            );
        }
    }

    $submissionInsert = $pdo->prepare(
        'INSERT INTO submissions (
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
            event_url,
            title,
            start_date,
            time_text,
            location_name,
            location_address,
            location_public_confirmed,
            ticket_url,
            description_text,
            notes_text
        ) VALUES (
            :organizer_id,
            :submission_kind,
            :status,
            :requested_model_key,
            :payment_kind,
            :intake_origin,
            :payment_reference_key,
            :organization_name_snapshot,
            :contact_name_snapshot,
            :email_snapshot,
            :event_url,
            :title,
            :start_date,
            :time_text,
            :location_name,
            :location_address,
            :location_public_confirmed,
            :ticket_url,
            :description_text,
            :notes_text
        )'
    );

    $submissionInsert->execute([
        ':organizer_id' => $organizerId,
        ':submission_kind' => $submissionKind,
        ':status' => $initialStatus,
        ':requested_model_key' => $requestedModelKey,
        ':payment_kind' => $paymentKind,
        ':intake_origin' => $intakeOrigin,
        ':payment_reference_key' => $paymentReferenceKey,
        ':organization_name_snapshot' => $organizationName,
        ':contact_name_snapshot' => $contactName,
        ':email_snapshot' => $emailInput,
        ':event_url' => $eventUrl,
        ':title' => $title,
        ':start_date' => $startDate,
        ':time_text' => $timeText,
        ':location_name' => $locationName,
        ':location_address' => $locationAddress,
        ':location_public_confirmed' => $locationPublicConfirmed,
        ':ticket_url' => $ticketUrl,
        ':description_text' => $descriptionText,
        ':notes_text' => $notesText,
    ]);

    $submissionId = (int)$pdo->lastInsertId();

    $pdo->commit();

    if ($paymentKind === 'single' || $submissionKind === 'activity') {
        pf_send_submission_received_mail([
            'email' => $emailInput,
            'title' => $title,
            'payment_reference_key' => $paymentReferenceKey,
        ]);
    }
    be_json_response(201, [
        'status' => 'ok',
        'data' => [
            'organizer_id' => $organizerId,
            'submission_id' => $submissionId,
            'submission_status' => $initialStatus,
            'checkout_required' => $checkoutRequired,
            'requested_model_key' => $requestedModelKey,
            'payment_kind' => $paymentKind,
            'intake_origin' => $intakeOrigin,
            'payment_reference_key' => $paymentReferenceKey,
        ],
    ]);
    /* === END BLOCK: PUBLISH_SUBMISSION_INIT_IDENTITY_GUARD_V2 === */
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
