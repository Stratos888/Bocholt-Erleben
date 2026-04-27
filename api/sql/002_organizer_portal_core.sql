-- === BEGIN FILE: api/sql/002_organizer_portal_core.sql | Zweck: Grundschema fuer Veranstalter-Zugang per Magic Link, Sessions, Abos, Kontingente und Verbrauchsbuchungen; Umfang: komplette Datei ===

CREATE TABLE IF NOT EXISTS organizer_magic_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    intended_action VARCHAR(32) NOT NULL DEFAULT 'portal_login',
    email_snapshot VARCHAR(190) NOT NULL,
    requested_ip VARCHAR(64) NULL,
    user_agent_text VARCHAR(512) NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    revoked_at DATETIME NULL,
    last_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_organizer_magic_links_token_hash (token_hash),
    KEY idx_organizer_magic_links_organizer_id (organizer_id),
    KEY idx_organizer_magic_links_expires_at (expires_at),
    CONSTRAINT fk_organizer_magic_links_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizer_portal_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    issued_from_magic_link_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_organizer_portal_sessions_session_token_hash (session_token_hash),
    KEY idx_organizer_portal_sessions_organizer_id (organizer_id),
    KEY idx_organizer_portal_sessions_expires_at (expires_at),
    CONSTRAINT fk_organizer_portal_sessions_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_organizer_portal_sessions_magic_link
        FOREIGN KEY (issued_from_magic_link_id) REFERENCES organizer_magic_links(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    source_provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
    stripe_subscription_id VARCHAR(191) NOT NULL,
    stripe_customer_id VARCHAR(191) NULL,
    plan_key VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    cancel_at_period_end TINYINT NOT NULL DEFAULT 0,
    canceled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscriptions_stripe_subscription_id (stripe_subscription_id),
    KEY idx_subscriptions_organizer_id (organizer_id),
    KEY idx_subscriptions_status (status),
    KEY idx_subscriptions_plan_key (plan_key),
    CONSTRAINT fk_subscriptions_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publication_entitlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    source_reference VARCHAR(191) NULL,
    source_submission_id BIGINT UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    plan_key VARCHAR(32) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    period_start DATETIME NULL,
    period_end DATETIME NULL,
    included_publications INT UNSIGNED NOT NULL DEFAULT 0,
    consumed_publications INT UNSIGNED NOT NULL DEFAULT 0,
    is_unlimited TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_publication_entitlements_source (source_type, source_reference),
    KEY idx_publication_entitlements_organizer_id (organizer_id),
    KEY idx_publication_entitlements_status (status),
    KEY idx_publication_entitlements_period (period_start, period_end),
    CONSTRAINT fk_publication_entitlements_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_publication_entitlements_submission
        FOREIGN KEY (source_submission_id) REFERENCES submissions(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_publication_entitlements_subscription
        FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publication_consumptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    entitlement_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    units INT UNSIGNED NOT NULL DEFAULT 1,
    consumed_reason VARCHAR(32) NOT NULL DEFAULT 'approved_publication',
    consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_publication_consumptions_submission_id (submission_id),
    KEY idx_publication_consumptions_organizer_id (organizer_id),
    KEY idx_publication_consumptions_entitlement_id (entitlement_id),
    CONSTRAINT fk_publication_consumptions_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_publication_consumptions_entitlement
        FOREIGN KEY (entitlement_id) REFERENCES publication_entitlements(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_publication_consumptions_submission
        FOREIGN KEY (submission_id) REFERENCES submissions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === END FILE: api/sql/002_organizer_portal_core.sql ===
