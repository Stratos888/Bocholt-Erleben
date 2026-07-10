<?php
declare(strict_types=1);

require_once __DIR__ . '/_sources.php';

function be_cc_sync_submissions(): array
{
    $pdo = be_db();
    $stmt = $pdo->query(
        'SELECT id, submission_kind, status, title, start_date, time_text, location_name, location_address,
                description_text, event_url, ticket_url, organization_name_snapshot, intake_origin,
                payment_released_at, payment_started_at, paid_at, created_at
         FROM submissions
         WHERE submission_kind IN ("event","activity")
           AND status IN ("pending_review","payment_released","checkout_started","paid","in_review")
         ORDER BY id ASC
         LIMIT 250'
    );
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach ($items as $row) {
        $status = trim((string)$row['status']);
        $isDecision = in_array($status, ['pending_review','paid','in_review'], true);
        $state = $isDecision ? 'decision_required' : 'waiting';
        $next = match ($status) {
            'pending_review' => 'Angaben prüfen und Zahlungslink freigeben.',
            'paid', 'in_review' => 'Final prüfen und veröffentlichen.',
            'payment_released', 'checkout_started' => 'Zahlungsbestätigung abwarten.',
            default => 'Einreichung prüfen.',
        };
        $caseId = be_cc_upsert_source_case([
            'type' => $isDecision ? 'intake' : 'task',
            'state' => $state,
            'priority' => $status === 'paid' ? 'high' : 'normal',
            'title' => trim((string)$row['title']) ?: 'Einreichung prüfen',
            'reason' => trim((string)$row['description_text']) ?: 'Neue Anbieter-Einreichung.',
            'next_action' => $next,
            'object_type' => (string)$row['submission_kind'],
            'object_id' => (string)$row['id'],
            'object_title' => trim((string)$row['title']),
            'source_system' => 'submission_db',
            'source_reference' => 'submission:' . (string)$row['id'],
            'source_payload' => $row,
            'decision_ready' => $isDecision,
        ]);

        // Eine zuvor wartende Einreichung muss nach bestätigter Zahlung wieder
        // als echte Entscheidung sichtbar werden. Bewusste Snoozes/Blockaden bleiben erhalten.
        if (in_array($status, ['paid', 'in_review'], true)) {
            $advance = $pdo->prepare(
                "UPDATE control_cases
                 SET case_type='intake', state='decision_required', decision_ready=1, priority=:priority, updated_at=NOW()
                 WHERE id=:id AND state='waiting'"
            );
            $advance->execute(['id' => $caseId, 'priority' => $status === 'paid' ? 'high' : 'normal']);
            if ($advance->rowCount() === 1) {
                be_cc_record_event($pdo, $caseId, 'source_payment_confirmed', 'waiting', 'decision_required', ['submission_status' => $status], 'system');
            }
        }
        $count++;
    }
    return ['seen' => count($items), 'upserted' => $count];
}
