<?php
declare(strict_types=1);

/**
 * Pure, side-effect free contracts used by runtime code and CI simulations.
 */
function be_cc_event_field_contract(): array
{
    return [
        'title' => ['title'],
        'date' => ['date', 'start_date'],
        'end_date' => ['enddate', 'end_date'],
        'time' => ['time'],
        'city' => ['city'],
        'location' => ['location', 'venue'],
        'address' => ['address'],
        'category' => ['kategorie', 'category'],
        'tags' => ['tags'],
        'source_url' => ['source_url', 'url', 'event_url'],
        'ticket_url' => ['ticket_url'],
        'description' => ['description'],
        'visual_key' => ['visual_key'],
        'visual_motif' => ['visual_motif', 'image_visual_motif'],
    ];
}

function be_cc_normalize_event_updates(array $updates): array
{
    $aliases = [
        'endDate' => 'end_date', 'end_date' => 'end_date',
        'kategorie' => 'category', 'category' => 'category',
        'url' => 'source_url', 'event_url' => 'source_url', 'source_url' => 'source_url',
        'image_visual_motif' => 'visual_motif', 'visual_motif' => 'visual_motif',
    ];
    $supported = be_cc_event_field_contract();
    $normalized = [];
    foreach ($updates as $field => $value) {
        $canonical = $aliases[$field] ?? $field;
        if (isset($supported[$canonical])) $normalized[$canonical] = trim((string)$value);
    }
    if (!$normalized) throw new InvalidArgumentException('Keine unterstützten Eventfelder übergeben.');
    return $normalized;
}

function be_cc_resolve_event_column(array $index, string $canonical): ?string
{
    $contract = be_cc_event_field_contract();
    foreach ($contract[$canonical] ?? [] as $alias) {
        $key = strtolower($alias);
        if (isset($index[$key])) return $key;
    }
    return null;
}

function be_cc_validate_event_update(array $index, array $currentRow, array $updates): array
{
    $normalized = be_cc_normalize_event_updates($updates);
    $titleColumn = be_cc_resolve_event_column($index, 'title');
    $dateColumn = be_cc_resolve_event_column($index, 'date');
    $currentTitle = $titleColumn !== null ? trim((string)($currentRow[$index[$titleColumn]] ?? '')) : '';
    $currentDate = $dateColumn !== null ? trim((string)($currentRow[$index[$dateColumn]] ?? '')) : '';
    $finalTitle = array_key_exists('title', $normalized) ? $normalized['title'] : $currentTitle;
    $finalDate = array_key_exists('date', $normalized) ? $normalized['date'] : $currentDate;

    if ($finalTitle === '') throw new InvalidArgumentException('Titel darf nicht leer sein.');
    if ($finalDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $finalDate)) {
        throw new InvalidArgumentException('Ein gültiges Startdatum ist erforderlich.');
    }

    foreach ($normalized as $canonical => $value) {
        if (in_array($canonical, ['source_url', 'ticket_url'], true) && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $value)) {
                throw new InvalidArgumentException('Ungültige URL für ' . $canonical . '.');
            }
        }
        if (in_array($canonical, ['date', 'end_date'], true) && $value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Ungültiges Datum für ' . $canonical . '.');
        }
    }

    $resolved = [];
    foreach ($normalized as $canonical => $value) {
        $columnName = be_cc_resolve_event_column($index, $canonical);
        if ($columnName !== null) $resolved[$canonical] = ['column_name' => $columnName, 'value' => $value];
    }
    if (!$resolved) throw new RuntimeException('Keine der übergebenen Eventänderungen konnte einer Sheet-Spalte zugeordnet werden.');
    return $resolved;
}

function be_cc_public_event_value(array $event, string $canonical): string
{
    $aliases = [
        'title' => ['title', 'eventName'],
        'date' => ['date', 'datum', 'start_date'],
        'end_date' => ['endDate', 'end_date', 'enddate'],
        'time' => ['time', 'uhrzeit', 'startzeit'],
        'city' => ['city', 'stadt'],
        'location' => ['location', 'ort', 'venue'],
        'address' => ['address', 'adresse'],
        'category' => ['kategorie', 'category'],
        'source_url' => ['source_url', 'url', 'event_url'],
        'ticket_url' => ['ticket_url'],
        'description' => ['description', 'beschreibung'],
        'visual_key' => ['visual_key'],
        'visual_motif' => ['visual_motif', 'image_visual_motif'],
        'tags' => ['tags'],
    ];
    foreach ($aliases[$canonical] ?? [$canonical] as $alias) {
        if (!array_key_exists($alias, $event)) continue;
        $value = $event[$alias];
        if (is_array($value)) return implode(',', array_map('strval', $value));
        return trim((string)$value);
    }
    return '';
}

function be_cc_compare_public_event_change(array $event, array $updates, array $writtenFields = []): array
{
    $written = array_flip($writtenFields);
    foreach (be_cc_normalize_event_updates($updates) as $canonical => $expected) {
        if ($written && !isset($written[$canonical])) continue;
        $actual = be_cc_public_event_value($event, $canonical);
        if ($actual !== trim((string)$expected)) {
            return [
                'verified' => false,
                'field' => $canonical,
                'expected' => trim((string)$expected),
                'actual' => $actual,
                'reason' => 'Öffentlicher Wert noch nicht aktuell: ' . $canonical . '.',
            ];
        }
    }
    return ['verified' => true, 'reason' => 'Öffentliche Eventdaten entsprechen der gespeicherten Änderung.'];
}

function be_cc_development_assessment(array $quality, array $automation, bool $trendAvailable): array
{
    $issues = (int)($quality['missing_description'] ?? 0)
        + (int)($quality['missing_source'] ?? 0)
        + (int)($quality['missing_date'] ?? 0)
        + (int)($quality['open_quality_reviews'] ?? 0)
        + (int)($automation['blocked_tasks'] ?? 0);

    if (!$trendAvailable) {
        return [
            'status' => $issues > 0 ? 'attention' : 'baseline',
            'label' => $issues > 0 ? 'Aktueller Stand mit Handlungsbedarf' : 'Aktueller Basisstand',
            'trend_available' => false,
            'message' => 'Noch keine belastbare Zeitreihe vorhanden. Es wird nur der aktuelle Zustand bewertet.',
        ];
    }

    return [
        'status' => $issues > 0 ? 'attention' : 'stable',
        'label' => $issues > 0 ? 'Entwicklung benötigt Aufmerksamkeit' : 'Entwicklung stabil',
        'trend_available' => true,
        'message' => 'Bewertung basiert auf aktuellem Zustand und gespeicherten Vergleichswerten.',
    ];
}

function be_cc_publication_result(bool $sourceSaved, bool $deployStarted, bool $publicVerified): array
{
    if (!$sourceSaved) return ['state' => 'failed', 'message' => 'Die führende Quelle wurde nicht aktualisiert.'];
    if (!$deployStarted) return ['state' => 'blocked', 'message' => 'Gespeichert, aber die Aktualisierung konnte nicht gestartet werden.'];
    if (!$publicVerified) return ['state' => 'waiting', 'message' => 'Gespeichert und Aktualisierung gestartet. Die öffentliche Wirkung ist noch nicht bestätigt.'];
    return ['state' => 'confirmed', 'message' => 'Gespeichert, veröffentlicht und öffentlich bestätigt.'];
}
