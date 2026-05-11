-- === BEGIN FILE: api/sql/004_submission_organizer_edit_tracking.sql | Zweck: ergänzt Tracking für nachträgliche Änderungen durch Veranstalter vor der Freigabe; Umfang: einmalige Datenbankmigration ===

ALTER TABLE submissions
    ADD COLUMN organizer_edited_at DATETIME NULL AFTER rejected_at,
    ADD COLUMN organizer_edit_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER organizer_edited_at,
    ADD KEY idx_submissions_organizer_edited_at (organizer_edited_at);

-- === END FILE: api/sql/004_submission_organizer_edit_tracking.sql ===
