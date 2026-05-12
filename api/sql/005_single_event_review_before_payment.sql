-- === BEGIN FILE: api/sql/005_single_event_review_before_payment.sql | Zweck: ergänzt den Einzelevent-Flow um interne Zahlungsfreigabe mit Token vor Stripe-Checkout; Umfang: einmalige Datenbankmigration ===

ALTER TABLE submissions
    ADD COLUMN payment_start_token_hash CHAR(64) NULL AFTER payment_reference_key,
    ADD COLUMN payment_start_token_expires_at DATETIME NULL AFTER payment_start_token_hash,
    ADD COLUMN payment_released_at DATETIME NULL AFTER payment_start_token_expires_at,
    ADD COLUMN payment_released_mail_sent_at DATETIME NULL AFTER payment_released_at,
    ADD COLUMN payment_started_at DATETIME NULL AFTER payment_released_mail_sent_at,
    ADD UNIQUE KEY uq_submissions_payment_start_token_hash (payment_start_token_hash),
    ADD KEY idx_submissions_single_payment_flow (submission_kind, intake_origin, status, payment_released_at, paid_at);

-- === END FILE: api/sql/005_single_event_review_before_payment.sql ===
