-- === BEGIN FILE: api/sql/001_publish_funnel_core.sql | Zweck: initiales Kernschema fuer Veranstalter, Event-Einreichungen und Stripe-Webhook-Events; Umfang: komplette Datei ===

CREATE TABLE IF NOT EXISTS organizers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_name VARCHAR(190) NOT NULL,
    contact_name VARCHAR(190) NULL,
    email VARCHAR(190) NOT NULL,
    email_normalized VARCHAR(190) NOT NULL,
    stripe_customer_id VARCHAR(191) NULL,
    default_plan_key VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_organizers_email_normalized (email_normalized),
    KEY idx_organizers_stripe_customer_id (stripe_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    submission_kind VARCHAR(32) NOT NULL DEFAULT 'event',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    requested_model_key VARCHAR(32) NOT NULL,
    payment_kind VARCHAR(32) NOT NULL DEFAULT 'single',
    payment_reference_key CHAR(36) NULL,
    organization_name_snapshot VARCHAR(190) NOT NULL,
    contact_name_snapshot VARCHAR(190) NULL,
    email_snapshot VARCHAR(190) NOT NULL,
    event_url VARCHAR(2048) NULL,
    title VARCHAR(255) NULL,
    start_date DATE NULL,
    time_text VARCHAR(64) NULL,
    location_name VARCHAR(255) NULL,
    ticket_url VARCHAR(2048) NULL,
    description_text TEXT NULL,
    notes_text TEXT NULL,
    stripe_checkout_session_id VARCHAR(191) NULL,
    stripe_customer_id VARCHAR(191) NULL,
    stripe_subscription_id VARCHAR(191) NULL,
    stripe_price_id VARCHAR(191) NULL,
    paid_at DATETIME NULL,
    review_started_at DATETIME NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submissions_payment_reference_key (payment_reference_key),
    KEY idx_submissions_organizer_id (organizer_id),
    KEY idx_submissions_status (status),
    KEY idx_submissions_requested_model_key (requested_model_key),
    KEY idx_submissions_stripe_checkout_session_id (stripe_checkout_session_id),
    KEY idx_submissions_stripe_subscription_id (stripe_subscription_id),
    CONSTRAINT fk_submissions_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
    provider_event_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(191) NOT NULL,
    is_processed TINYINT(1) NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    payload_text LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_events_provider_provider_event_id (provider, provider_event_id),
    KEY idx_webhook_events_event_type (event_type),
    KEY idx_webhook_events_is_processed (is_processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === END FILE: api/sql/001_publish_funnel_core.sql ===
