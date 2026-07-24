-- === BEGIN FILE: api/sql/008_startpartner_candidates.sql | Zweck: kanonische Source of Truth fuer Gate-1-Startpartner-Kandidaten, Kontakte und unveraenderliche Auditereignisse; Umfang: idempotente Neuanlage ohne Organizer-, Zahlungs- oder Pilotobjekte ===

CREATE TABLE IF NOT EXISTS startpartner_candidates (
    id CHAR(36) NOT NULL,
    source ENUM('self_service','targeted_outreach') NOT NULL,
    source_reference VARCHAR(191) NULL,
    organization_name VARCHAR(190) NOT NULL,
    organization_name_normalized VARCHAR(190) NOT NULL,
    website_url VARCHAR(2048) NULL,
    description_text TEXT NULL,
    desired_content_scope ENUM('events','activities','both','unknown') NOT NULL DEFAULT 'unknown',
    status ENUM('new','needs_information','qualified','waitlisted','routed_to_regular_product','rejected','withdrawn','expired') NOT NULL DEFAULT 'new',
    status_reason VARCHAR(500) NULL,
    identity_key CHAR(64) NOT NULL,
    idempotency_key_hash CHAR(64) NOT NULL,
    privacy_policy_version VARCHAR(64) NULL,
    form_version VARCHAR(64) NOT NULL,
    retention_review_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_candidates_identity (identity_key),
    UNIQUE KEY uq_startpartner_candidates_idempotency (idempotency_key_hash),
    KEY idx_startpartner_candidates_status (status, updated_at),
    KEY idx_startpartner_candidates_source (source, created_at),
    KEY idx_startpartner_candidates_retention (retention_review_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_candidate_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id CHAR(36) NOT NULL,
    contact_name VARCHAR(190) NULL,
    contact_role VARCHAR(190) NULL,
    email VARCHAR(190) NOT NULL,
    email_normalized VARCHAR(190) NOT NULL,
    phone VARCHAR(64) NULL,
    is_primary TINYINT(1) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_contacts_candidate_email (candidate_id, email_normalized),
    UNIQUE KEY uq_startpartner_contacts_single_primary (candidate_id, is_primary),
    KEY idx_startpartner_contacts_email (email_normalized),
    CONSTRAINT fk_startpartner_contacts_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_candidate_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id CHAR(36) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NULL,
    actor_type ENUM('system','self_service','operator') NOT NULL DEFAULT 'system',
    actor_reference VARCHAR(191) NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_startpartner_events_candidate (candidate_id, created_at, id),
    KEY idx_startpartner_events_type (event_type, created_at),
    CONSTRAINT fk_startpartner_events_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '008_startpartner_candidates',
    'Create Gate-1 Startpartner candidate, contact and immutable event owners.'
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

-- === END FILE: api/sql/008_startpartner_candidates.sql ===
