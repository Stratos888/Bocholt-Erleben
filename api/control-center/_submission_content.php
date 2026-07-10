<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

function be_cc_submission_event_items(bool $full = false): array
{
    $stmt = be_db()->prepare(
        "SELECT id, title, start_date, time_text, location_name, location_address,
                description_text, event_url, ticket_url, organization_name_snapshot,
                approved_at, updated_at
         FROM submissions
         WHERE submission_kind='event'
           AND status='approved'
           AND approved_at IS NOT NULL
           AND start_date >= CURRENT_DATE()
         ORDER BY start_date ASC, time_text ASC, id ASC
         LIMIT 500"
    );
    $stmt->execute();
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = 'submission-' . (string)$row['id'];
        $title = trim((string)$row['title']);
        if ($title === '') continue;
        $item = [
            'id' => $id,
            'source_system' => 'submission_db',
            'source_label' => 'Anbieter-Event',
            'submission_id' => (int)$row['id'],
            'title' => $title,
            'date' => trim((string)$row['start_date']),
            'end_date' => '',
            'time' => trim((string)$row['time_text']),
            'location' => trim((string)$row['location_name']),
            'city' => 'Bocholt',
            'category' => '',
            'status' => 'published',
            'source_url' => trim((string)$row['event_url']),
            'ticket_url' => trim((string)$row['ticket_url']),
            'public_url' => '/events/?event=' . rawurlencode($id),
            'updated_at' => trim((string)$row['updated_at']),
            'editable' => true,
            'writeback_status' => ['available' => true, 'code' => 'ready', 'message' => 'Anbieter-Events werden direkt in der führenden Einreichungsdatenbank bearbeitet.'],
        ];
        if ($full) {
            $item['description'] = trim((string)$row['description_text']);
            $item['address'] = trim((string)$row['location_address']);
            $item['organization_name'] = trim((string)$row['organization_name_snapshot']);
        }
        $items[] = $item;
    }
    return $items;
}

function be_cc_normalize_submission_event_updates(array $updates): array
{
    $allowed = [
        'title' => 'title',
        'date' => 'start_date',
        'time' => 'time_text',
        'location' => 'location_name',
        'address' => 'location_address',
        'description' => 'description_text',
        'source_url' => 'event_url',
        'ticket_url' => 'ticket_url',
    ];
    $normalized = [];
    foreach ($allowed as $input => $column) {
        if (array_key_exists($input, $updates)) $normalized[$column] = trim((string)$updates[$input]);
    }
    if (!$normalized) throw new InvalidArgumentException('Keine unterstützten Anbieter-Eventfelder übergeben.');
    if (array_key_exists('title', $normalized) && $normalized['title'] === '') throw new InvalidArgumentException('Titel darf nicht leer sein.');
    if (array_key_exists('start_date', $normalized) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized['start_date'])) throw new InvalidArgumentException('Ein gültiges Startdatum ist erforderlich.');
    foreach (['event_url','ticket_url'] as $field) {
        if (($normalized[$field] ?? '') !== '' && (!filter_var($normalized[$field], FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $normalized[$field]))) {
            throw new InvalidArgumentException('Ungültige URL für ' . $field . '.');
        }
    }
    return $normalized;
}

function be_cc_update_submission_event(int $submissionId, array $updates): array
{
    if ($submissionId <= 0) throw new InvalidArgumentException('Ungültige Einreichungs-ID.');
    $normalized = be_cc_normalize_submission_event_updates($updates);
    $pdo = be_db();
    $pdo->beginTransaction();
    try {
        $check = $pdo->prepare("SELECT id FROM submissions WHERE id=:id AND submission_kind='event' AND status='approved' FOR UPDATE");
        $check->execute(['id' => $submissionId]);
        if (!$check->fetchColumn()) throw new RuntimeException('Genehmigtes Anbieter-Event wurde nicht gefunden.');
        $fields = [];
        $params = ['id' => $submissionId];
        foreach ($normalized as $column => $value) {
            $fields[] = $column . '=:' . $column;
            $params[$column] = $value !== '' ? $value : null;
        }
        $fields[] = 'updated_at=NOW()';
        $stmt = $pdo->prepare('UPDATE submissions SET ' . implode(',', $fields) . ' WHERE id=:id');
        $stmt->execute($params);
        $pdo->commit();
        return array_keys($normalized);
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
