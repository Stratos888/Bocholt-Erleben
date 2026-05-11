<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/review-list.php | Zweck: liefert bezahlte Formular-Einreichungen als Review-Fälle für die Kuratier-PWA; Umfang: read-only JSON-Endpoint für submissions mit status paid/in_review === */

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

function srl_origin_label(string $origin): string
{
    return match ($origin) {
        'membership' => 'Mitgliedschaft',
        'ai_search' => 'KI-Suche',
        default => 'Einzelevent',
    };
}

function srl_build_risk_flags(array $row): array
{
    $flags = [];

    if (trim((string)($row['location_address'] ?? '')) === '') {
        $flags[] = 'Adresse oder öffentlicher Treffpunkt fehlt.';
    }

    if ((int)($row['location_public_confirmed'] ?? 0) !== 1) {
        $flags[] = 'Öffentliche Ortsnennung wurde nicht bestätigt.';
    }

    return $flags;
}

try {
    $pdo = be_db();
    $statement = $pdo->prepare(
        'SELECT
            id,
            status,
            requested_model_key,
            payment_kind,
            intake_origin,
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
            paid_at,
            created_at,
            updated_at
         FROM submissions
         WHERE submission_kind = :submission_kind
           AND status IN ("paid", "in_review")
         ORDER BY paid_at ASC, created_at ASC
         LIMIT 100'
    );

    $statement->execute([
        ':submission_kind' => 'event',
    ]);

    $items = [];

    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $origin = trim((string)($row['intake_origin'] ?? ''));
        if ($origin === '') {
            $origin = ((string)($row['payment_kind'] ?? '') === 'subscription') ? 'membership' : 'single_event';
        }

        $eventUrl = trim((string)($row['event_url'] ?? ''));
        $ticketUrl = trim((string)($row['ticket_url'] ?? ''));
        $locationAddress = trim((string)($row['location_address'] ?? ''));
        $locationName = trim((string)($row['location_name'] ?? ''));

        $items[] = [
            'review_source' => 'submission_db',
            'submission_id' => (int)$row['id'],
            'status' => (string)$row['status'],
            'intake_origin' => $origin,
            'intake_origin_label' => srl_origin_label($origin),
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
            'created_at' => (string)($row['created_at'] ?? ''),
            'paid_at' => (string)($row['paid_at'] ?? ''),
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
