<?php
declare(strict_types=1);

function be_replace_submission_publication_snapshot(PDO $pdo, int $submissionId): void
{
    $statement = $pdo->prepare(
        'INSERT INTO submission_publication_snapshots (
            submission_id, submission_kind, organizer_id, organization_name_snapshot,
            title, start_date, time_text, location_name, location_address,
            location_public_confirmed, event_url, ticket_url, description_text, approved_at
         )
         SELECT id, submission_kind, organizer_id, organization_name_snapshot,
                title, start_date, time_text, location_name, location_address,
                location_public_confirmed, event_url, ticket_url, description_text, approved_at
         FROM submissions
         WHERE id = :submission_id AND status = "approved" AND approved_at IS NOT NULL
         ON DUPLICATE KEY UPDATE
            submission_kind = VALUES(submission_kind), organizer_id = VALUES(organizer_id),
            organization_name_snapshot = VALUES(organization_name_snapshot), title = VALUES(title),
            start_date = VALUES(start_date), time_text = VALUES(time_text),
            location_name = VALUES(location_name), location_address = VALUES(location_address),
            location_public_confirmed = VALUES(location_public_confirmed), event_url = VALUES(event_url),
            ticket_url = VALUES(ticket_url), description_text = VALUES(description_text),
            approved_at = VALUES(approved_at), published_at = CURRENT_TIMESTAMP'
    );
    $statement->execute(['submission_id' => $submissionId]);
}

function be_remove_submission_publication_snapshot(PDO $pdo, int $submissionId): void
{
    $statement = $pdo->prepare('DELETE FROM submission_publication_snapshots WHERE submission_id = :submission_id');
    $statement->execute(['submission_id' => $submissionId]);
}
