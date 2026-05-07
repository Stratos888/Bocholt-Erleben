-- === BEGIN FILE: api/sql/003_submission_intake_origin_location_review.sql | Zweck: erweitert Event-Submissions um Herkunft und prüfpflichtige Ortsangaben für die Review-Inbox; Umfang: einmalige Datenbankmigration ===

ALTER TABLE submissions
    ADD COLUMN intake_origin VARCHAR(32) NULL AFTER payment_kind,
    ADD COLUMN location_address VARCHAR(255) NULL AFTER location_name,
    ADD COLUMN location_public_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER location_address,
    ADD KEY idx_submissions_intake_origin (intake_origin),
    ADD KEY idx_submissions_review_queue (submission_kind, status, intake_origin, created_at);

UPDATE submissions
SET intake_origin = CASE
    WHEN payment_kind = 'subscription' OR requested_model_key IN ('starter', 'active', 'unlimited') THEN 'membership'
    ELSE 'single_event'
END
WHERE intake_origin IS NULL OR intake_origin = '';

ALTER TABLE submissions
    MODIFY COLUMN intake_origin VARCHAR(32) NOT NULL DEFAULT 'single_event';

-- === END FILE: api/sql/003_submission_intake_origin_location_review.sql ===
