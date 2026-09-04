<?php
declare(strict_types=1);
/* === BEGIN FILE: api/events/public.php | Zweck: liefert final freigegebene DB-Submissions als öffentliche Eventdaten zusätzlich zum Sheet-Feed; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function public_events_category_for_submission(array $row): string
{
    $combined = mb_strtolower(trim((string)($row['title'] ?? '')) . ' ' . trim((string)($row['description_text'] ?? '')));
    if (preg_match('/\b(konzert|musik|band|chor|dj|live)\b/u', $combined)) return 'Musik & Bühne';
    if (preg_match('/\b(kinder|familie|familien|kind)\b/u', $combined)) return 'Kinder & Familie';
    if (preg_match('/\b(fest|markt|festival|kirmes|messe)\b/u', $combined)) return 'Märkte & Feste';
    if (preg_match('/\b(sport|lauf|rad|bewegung|turnier)\b/u', $combined)) return 'Sport & Bewegung';
    if (preg_match('/\b(natur|wald|wander|draußen|draussen|führung|fuehrung)\b/u', $combined)) return 'Natur & Draußen';
    if (preg_match('/\b(kunst|kultur|lesung|ausstellung|theater|comedy|kabarett)\b/u', $combined)) return 'Kultur & Kunst';
    return 'Sonstiges';
}

function public_events_visual_fields_for_submission(array $row): array
{
    $combined = mb_strtolower(trim((string)($row['title'] ?? '')) . ' ' . trim((string)($row['description_text'] ?? '')));
    if (preg_match('/\b(playfountain|play fountain|wasserspaß|wasserspass|wasserspiel|wasserspielfläche|wasserspielflaeche)\b/u', $combined)) {
        return ['visual_key' => 'family_play_outdoor', 'visual_motif' => 'playfountain_water_splash'];
    }
    return ['visual_key' => 'default_city', 'visual_motif' => 'default_city'];
}

function public_events_city_from_address(?string $address): string
{
    $text = trim((string)$address);
    if ($text === '') return 'Bocholt';
    if (preg_match('/\b\d{5}\s+([^,]+)$/u', $text, $match)) return trim((string)$match[1]) ?: 'Bocholt';
    return 'Bocholt';
}

function public_events_location_label(array $row): string
{
    $name = trim((string)($row['location_name'] ?? ''));
    $address = trim((string)($row['location_address'] ?? ''));
    if ($name !== '' && $address !== '' && mb_strtolower($name) !== mb_strtolower($address)) return $name . ' · ' . $address;
    return $name !== '' ? $name : $address;
}

function public_events_reporting_target(array $row): array
{
    $organizerId = (int)($row['organizer_id'] ?? 0);
    $organizationName = trim((string)($row['organization_name_snapshot'] ?? ''));
    if ($organizerId <= 0 || $organizationName === '') return [];
    return ['reporting_target_type' => 'organizer', 'reporting_target_id' => 'organizer-' . substr(hash('sha256', 'organizer:' . $organizerId), 0, 16), 'reporting_target_title' => $organizationName];
}

function public_events_normalize_row(array $row): array
{
    $sourceUrl = trim((string)($row['event_url'] ?? ''));
    $ticketUrl = trim((string)($row['ticket_url'] ?? ''));
    $address = trim((string)($row['location_address'] ?? ''));
    $event = [
        'id' => 'submission-' . (string)$row['id'],
        'source' => 'submission_db',
        'submission_id' => (int)$row['id'],
        'title' => trim((string)($row['title'] ?? '')),
        'date' => trim((string)($row['start_date'] ?? '')),
        'time' => trim((string)($row['time_text'] ?? '')),
        'city' => public_events_city_from_address($address),
        'location' => public_events_location_label($row),
        'address' => $address,
        'kategorie' => public_events_category_for_submission($row),
        'description' => trim((string)($row['description_text'] ?? '')),
        'source_url' => $sourceUrl,
        'ticket_url' => $ticketUrl,
        'admission_status' => 'unknown',
        'price' => '',
        'price_currency' => '',
        'availability' => '',
        'valid_from' => '',
        'organizer_name' => trim((string)($row['organization_name_snapshot'] ?? '')),
        'organizer_url' => '',
        'performer_name' => '',
        'performer_url' => '',
        'offer_verified_at' => '',
        'offer_source_url' => '',
        'schema_eligible' => false,
    ];
    if ($sourceUrl !== '') $event['url'] = $sourceUrl;
    $visualFields = public_events_visual_fields_for_submission($row);
    if ($visualFields !== []) $event += $visualFields;
    $reportingTarget = public_events_reporting_target($row);
    if ($reportingTarget !== []) $event += $reportingTarget;
    return $event;
}

try {
    $stmt = be_db()->prepare(
        'SELECT ps.submission_id AS id, ps.organizer_id, ps.organization_name_snapshot, ps.title,
                ps.start_date, ps.time_text, ps.location_name, ps.location_address,
                ps.location_public_confirmed, ps.event_url, ps.ticket_url,
                ps.description_text, ps.approved_at, ps.published_at AS updated_at
         FROM submission_publication_snapshots ps
         WHERE ps.submission_kind = :submission_kind
           AND ps.start_date IS NOT NULL
           AND ps.start_date >= CURRENT_DATE()
           AND ps.title IS NOT NULL AND ps.title <> ""
           AND ps.location_name IS NOT NULL AND ps.location_name <> ""
           AND ps.location_public_confirmed = 1
           AND (
                NOT EXISTS (
                    SELECT 1 FROM startpartner_pilot_content_links pcl_any
                    WHERE pcl_any.submission_id = ps.submission_id
                )
                OR EXISTS (
                    SELECT 1 FROM startpartner_pilot_content_links pcl
                    INNER JOIN startpartner_pilots sp ON sp.id = pcl.pilot_id
                    WHERE pcl.submission_id = ps.submission_id
                      AND pcl.status = "approved"
                      AND sp.status IN ("active", "paused", "closing")
                )
           )
         ORDER BY ps.start_date ASC, ps.time_text ASC, ps.submission_id ASC
         LIMIT 250'
    );
    $stmt->execute(['submission_kind' => 'event']);
    $events = [];
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) $events[] = public_events_normalize_row($row);
    be_json_response(200, ['status' => 'ok', 'data' => ['events' => $events, 'total' => count($events), 'source' => 'submission_db_approved']]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Public events could not be loaded.', 'error_class' => get_class($error), 'error_message' => $error->getMessage()]);
}

/* === END FILE: api/events/public.php === */