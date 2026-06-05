<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/review-list.php | Zweck: liefert DB-Submissions für die Review-Inbox inklusive Vorprüfung, Zahlungsfreigabe und bezahlter Veröffentlichungsprüfung; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

/* === BEGIN BLOCK: SUBMISSION_REVIEW_ENDPOINT_ACCESS_GUARD_V1 | Zweck: verhindert öffentlichen Zugriff auf DB-Review-Endpunkt; Umfang: prüft Review-Passwort nach Method-Check === */
be_require_review_access();
/* === END BLOCK: SUBMISSION_REVIEW_ENDPOINT_ACCESS_GUARD_V1 === */

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_LABELS_V1 | Zweck: kennzeichnet Aktivitaetspraesenzen in der Review-Inbox korrekt; Umfang: ersetzt srl_origin_label === */
function srl_origin_label(string $origin, string $submissionKind = 'event'): string
{
    if ($submissionKind === 'activity' || $origin === 'activity_presence') {
        return 'Aktivitätspräsenz';
    }

    return match ($origin) {
        'membership' => 'Mitgliedschaft',
        'ai_search' => 'KI-Suche',
        default => 'Einzelevent',
    };
}
/* === END BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_LABELS_V1 === */

/* === BEGIN BLOCK: SUBMISSION_REVIEW_STATUS_LABELS_V1 | Zweck: übersetzt Submission-Status für die Review-Inbox; Umfang: Statuslabel-Helfer === */
function srl_status_label(string $status): string
{
    return match ($status) {
        'pending_review' => 'Neu eingereicht',
        'payment_released' => 'Zur Zahlung freigegeben',
        'checkout_started' => 'Zahlung gestartet',
        'paid' => 'Bezahlt / veröffentlichungsbereit',
        'in_review' => 'In Prüfung',
        default => $status,
    };
}
/* === END BLOCK: SUBMISSION_REVIEW_STATUS_LABELS_V1 === */

function srl_build_risk_flags(array $row): array
{
    $flags = [];

    if ((int)($row['organizer_edit_count'] ?? 0) > 0) {
        $flags[] = 'Vom Veranstalter nachträglich geändert.';
    }

    if (trim((string)($row['location_address'] ?? '')) === '') {
        $flags[] = 'Adresse oder öffentlicher Treffpunkt fehlt.';
    }

    if ((int)($row['location_public_confirmed'] ?? 0) !== 1) {
        $flags[] = 'Öffentliche Ortsnennung wurde nicht bestätigt.';
    }

    return $flags;
}


/* === BEGIN BLOCK: ACTIVITY_OPENING_REVIEW_PAYLOAD_V1 | Zweck: gibt strukturierte Oeffnungszeiten fuer Aktivitaetspraesenz-Submissions an die Review-Inbox weiter; Umfang: additive JSON-Dekodierung ohne bestehende Review-Felder zu veraendern === */
function srl_decode_activity_opening($value): ?array
{
    $raw = trim((string)($value ?? ''));
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}
/* === END BLOCK: ACTIVITY_OPENING_REVIEW_PAYLOAD_V1 === */

try {
    $pdo = be_db();
    $statement = $pdo->prepare(
        'SELECT
            id,
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
            notes_text,
            activity_opening_json,
            payment_released_at,
            payment_start_token_expires_at,
            payment_started_at,
            paid_at,
            organizer_edited_at,
            organizer_edit_count,
            created_at,
            updated_at
         FROM submissions
         WHERE submission_kind IN ("event", "activity")
           AND status IN ("pending_review", "payment_released", "checkout_started", "paid", "in_review")
         ORDER BY
            CASE status
                WHEN "pending_review" THEN 1
                WHEN "paid" THEN 2
                WHEN "in_review" THEN 3
                WHEN "payment_released" THEN 4
                WHEN "checkout_started" THEN 5
                ELSE 9
            END ASC,
            COALESCE(paid_at, payment_released_at, created_at) ASC,
            id ASC
         LIMIT 150'
    );

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_EXECUTE_V1 | Zweck: führt Review-Query ohne festen Event-Parameter aus, da Event und Aktivitaet geladen werden; Umfang: ersetzt execute-Parameterblock === */
    $statement->execute();
    /* === END BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_EXECUTE_V1 === */

    $items = [];

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_ROW_KIND_V1 | Zweck: liest Submission-Art je Review-Zeile für Aktivitaets-/Event-Unterscheidung; Umfang: ersetzt Schleifenstart inklusive Origin-Initialisierung === */
    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $submissionKind = trim((string)($row['submission_kind'] ?? 'event'));
        $origin = trim((string)($row['intake_origin'] ?? ''));
    /* === END BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_ROW_KIND_V1 === */
        if ($origin === '') {
            $origin = ((string)($row['payment_kind'] ?? '') === 'subscription') ? 'membership' : 'single_event';
        }

        $eventUrl = trim((string)($row['event_url'] ?? ''));
        $ticketUrl = trim((string)($row['ticket_url'] ?? ''));
        $locationAddress = trim((string)($row['location_address'] ?? ''));
        $locationName = trim((string)($row['location_name'] ?? ''));
        $status = (string)($row['status'] ?? '');

        $items[] = [
            /* === BEGIN BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_ITEM_META_V1 | Zweck: gibt Submission-Art und passendes Herkunftslabel an die Review-Inbox weiter; Umfang: ersetzt Meta-Felder im items-Array === */
            'review_source' => 'submission_db',
            'submission_id' => (int)$row['id'],
            'submission_kind' => $submissionKind,
            'status' => $status,
            'status_label' => srl_status_label($status),
            'intake_origin' => $origin,
            'intake_origin_label' => srl_origin_label($origin, $submissionKind),
            /* === END BLOCK: ACTIVITY_PRESENCE_REVIEW_LIST_ITEM_META_V1 === */
            'payment_reference_key' => (string)($row['payment_reference_key'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'date' => (string)($row['start_date'] ?? ''),
            'endDate' => '',
            'time' => (string)($row['time_text'] ?? ''),
            'city' => '',
            'location' => $locationName,
            'location_address' => $locationAddress,
            'location_public_confirmed' => ((int)($row['location_public_confirmed'] ?? 0)) === 1,
            'kategorie_suggestion' => '',
            'source_name' => (string)($row['organization_name_snapshot'] ?? ''),
            'url' => $eventUrl !== '' ? $eventUrl : $ticketUrl,
            'source_url' => $eventUrl,
            'description' => (string)($row['description_text'] ?? ''),
            'notes' => (string)($row['notes_text'] ?? ''),
            'activity_opening' => srl_decode_activity_opening($row['activity_opening_json'] ?? null),
            'created_at' => (string)($row['created_at'] ?? ''),
            'payment_released_at' => (string)($row['payment_released_at'] ?? ''),
            'payment_start_token_expires_at' => (string)($row['payment_start_token_expires_at'] ?? ''),
            'payment_started_at' => (string)($row['payment_started_at'] ?? ''),
            'paid_at' => (string)($row['paid_at'] ?? ''),
            'organizer_edited_at' => (string)($row['organizer_edited_at'] ?? ''),
            'organizer_edit_count' => (int)($row['organizer_edit_count'] ?? 0),
            'review_risk_flags' => srl_build_risk_flags($row),
            'organizer_name' => (string)($row['organization_name_snapshot'] ?? ''),
            'organizer_email' => (string)($row['email_snapshot'] ?? ''),
            'requested_model_key' => (string)($row['requested_model_key'] ?? ''),
            'payment_kind' => (string)($row['payment_kind'] ?? ''),
        ];
    }

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'items' => $items,
            'total' => count($items),
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Submission review list failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/review-list.php === */
