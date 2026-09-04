-- Last editorially approved version; submissions remains the current edit/review owner.
CREATE TABLE IF NOT EXISTS submission_publication_snapshots (
    submission_id BIGINT UNSIGNED NOT NULL,
    submission_kind VARCHAR(32) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    organization_name_snapshot VARCHAR(190) NOT NULL,
    title VARCHAR(255) NULL,
    start_date DATE NULL,
    time_text VARCHAR(64) NULL,
    location_name VARCHAR(255) NULL,
    location_address VARCHAR(255) NULL,
    location_public_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    event_url VARCHAR(2048) NULL,
    ticket_url VARCHAR(2048) NULL,
    description_text TEXT NULL,
    approved_at DATETIME NOT NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (submission_id),
    KEY idx_submission_publication_kind_date (submission_kind, start_date),
    KEY idx_submission_publication_organizer (organizer_id),
    CONSTRAINT fk_submission_publication_snapshot_submission
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_submission_publication_snapshot_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO submission_publication_snapshots (
    submission_id, submission_kind, organizer_id, organization_name_snapshot,
    title, start_date, time_text, location_name, location_address,
    location_public_confirmed, event_url, ticket_url, description_text, approved_at
)
SELECT id, submission_kind, organizer_id, organization_name_snapshot,
       title, start_date, time_text, location_name, location_address,
       location_public_confirmed, event_url, ticket_url, description_text, approved_at
FROM submissions
WHERE status = 'approved' AND approved_at IS NOT NULL
ON DUPLICATE KEY UPDATE submission_id = VALUES(submission_id);
