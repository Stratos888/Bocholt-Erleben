<?php
declare(strict_types=1);
/* === BEGIN FILE: api/activities/public.php | Zweck: liefert final freigegebene DB-Submissions als öffentliche Aktivitätsdaten zusätzlich zum kuratierten JSON-Feed; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function public_activities_category_for_submission(array $row): string
{
    $combined = mb_strtolower(trim((string)($row['title'] ?? '')) . ' ' . trim((string)($row['description_text'] ?? '')));
    if (preg_match('/\b(kinder|familie|familien|kind|spielplatz|spielen)\b/u', $combined)) return 'Familie & Spielen';
    if (preg_match('/\b(rad|radfahren|fahrrad|bike)\b/u', $combined)) return 'Radfahren';
    if (preg_match('/\b(wander|spazier|rundweg|weg)\b/u', $combined)) return 'Wandern & Spazieren';
    if (preg_match('/\b(sport|bewegung|klettern|schwimmen|baden)\b/u', $combined)) return 'Sport & Bewegung';
    if (preg_match('/\b(kunst|kultur|museum|ausstellung|geschichte)\b/u', $combined)) return 'Kultur';
    if (preg_match('/\b(natur|wald|tier|see|wasser|park|draußen|draussen)\b/u', $combined)) return 'Natur & Draußen';
    return 'Freizeit & Ausflug';
}

function public_activities_location_label(array $row): string
{
    $name = trim((string)($row['location_name'] ?? ''));
    $address = trim((string)($row['location_address'] ?? ''));
    if ($name !== '' && $address !== '' && mb_strtolower($name) !== mb_strtolower($address)) return $name . ' · ' . $address;
    return $name !== '' ? $name : $address;
}

function public_activities_reporting_target(array $row): array
{
    $organizerId = (int)($row['organizer_id'] ?? 0);
    $organizationName = trim((string)($row['organization_name_snapshot'] ?? ''));
    if ($organizerId <= 0 || $organizationName === '') return [];
    return [
        'type' => 'organizer',
        'id' => 'organizer-' . substr(hash('sha256', 'organizer:' . $organizerId), 0, 16),
        'title' => $organizationName,
    ];
}

function public_activities_normalize_row(array $row): array
{
    $sourceUrl = trim((string)($row['event_url'] ?? ''));
    $location = public_activities_location_label($row);
    $address = trim((string)($row['location_address'] ?? ''));
    $offer = [
        'id' => 'submission-' . (string)$row['id'],
        'source' => 'submission_db',
        'submission_id' => (int)$row['id'],
        'title' => trim((string)($row['title'] ?? '')),
        'kategorie' => public_activities_category_for_submission($row),
        'location' => $location,
        'description' => trim((string)($row['description_text'] ?? '')),
        'maps_query' => $address !== '' ? $address : $location,
        'website_label' => 'Mehr erfahren',
        'tags' => [],
        'filter_tags' => [],
        'audience' => [],
        'cardFacts' => [],
        'organization_name' => trim((string)($row['organization_name_snapshot'] ?? '')),
    ];
    if ($sourceUrl !== '') $offer['url'] = $sourceUrl;
    $reportingTarget = public_activities_reporting_target($row);
    if ($reportingTarget !== []) $offer['reporting_target'] = $reportingTarget;
    return $offer;
}

try {
    $stmt = be_db()->prepare(
        'SELECT s.id, s.organizer_id, s.organization_name_snapshot, s.title,
                s.location_name, s.location_address, s.location_public_confirmed,
                s.event_url, s.description_text, s.approved_at, s.updated_at
         FROM submissions s
         WHERE s.submission_kind = :submission_kind
           AND s.status = :status
           AND s.approved_at IS NOT NULL
           AND s.title IS NOT NULL AND s.title <> ""
           AND s.location_name IS NOT NULL AND s.location_name <> ""
           AND s.location_public_confirmed = 1
           AND (
                NOT EXISTS (
                    SELECT 1
                    FROM startpartner_pilot_content_links pcl_any
                    WHERE pcl_any.submission_id = s.id
                )
                OR EXISTS (
                    SELECT 1
                    FROM startpartner_pilot_content_links pcl
                    INNER JOIN startpartner_pilots sp ON sp.id = pcl.pilot_id
                    WHERE pcl.submission_id = s.id
                      AND pcl.status = "approved"
                      AND sp.status IN ("active", "paused", "closing")
                )
           )
         ORDER BY s.approved_at DESC, s.id DESC
         LIMIT 250'
    );
    $stmt->execute(['submission_kind' => 'activity', 'status' => 'approved']);
    $activities = [];
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $activities[] = public_activities_normalize_row($row);
    }
    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'activities' => $activities,
            'total' => count($activities),
            'source' => 'submission_db_approved',
        ],
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Public activities could not be loaded.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/activities/public.php === */