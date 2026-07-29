-- === BEGIN FILE: api/sql/012_startpartner_gate4_onboarding_content_activation.sql | Zweck: versioniert Gate-4-Onboarding, Content-, Mess-, Distributions-, Nutzungs- und Aktivierungsowner ohne Stripe- oder reguläre Entitlement-Mutation ===

CREATE TABLE IF NOT EXISTS startpartner_pilot_onboarding_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    item_key VARCHAR(64) NOT NULL,
    item_type ENUM('terms','organizer','contact','portal_access','entitlement','service_scope','source','maintenance','content_rights','first_content','editorial_review','measurement','distribution','activation_target') NOT NULL,
    status ENUM('open','complete','blocked','not_applicable') NOT NULL DEFAULT 'open',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_hard_blocker TINYINT(1) NOT NULL DEFAULT 1,
    evidence_json JSON NULL,
    blocker_reason VARCHAR(1000) NULL,
    operator_name VARCHAR(191) NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_onboarding_item (pilot_id, item_key),
    KEY idx_startpartner_pilot_onboarding_status (pilot_id, status, is_hard_blocker),
    CONSTRAINT fk_startpartner_pilot_onboarding_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_startpartner_pilot_onboarding_complete
        CHECK (status <> 'complete' OR completed_at IS NOT NULL),
    CONSTRAINT chk_startpartner_pilot_onboarding_blocked
        CHECK (status <> 'blocked' OR blocker_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_content_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    content_type ENUM('event','activity') NOT NULL,
    content_reference VARCHAR(191) NULL,
    publication_status ENUM('prepared','editorial_ready','approved','rejected','withdrawn') NOT NULL DEFAULT 'prepared',
    reporting_target_type VARCHAR(32) NOT NULL DEFAULT 'organizer',
    reporting_target_id VARCHAR(191) NOT NULL,
    source_reference VARCHAR(191) NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_content_submission (submission_id),
    KEY idx_startpartner_pilot_content_pilot (pilot_id, publication_status),
    KEY idx_startpartner_pilot_content_reporting (reporting_target_type, reporting_target_id),
    CONSTRAINT fk_startpartner_pilot_content_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_content_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_content_submission
        FOREIGN KEY (submission_id) REFERENCES submissions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_startpartner_pilot_content_approved
        CHECK (publication_status <> 'approved' OR approved_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_measurement_preflights (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    content_link_id BIGINT UNSIGNED NOT NULL,
    reporting_target_type VARCHAR(32) NOT NULL,
    reporting_target_id VARCHAR(191) NOT NULL,
    status ENUM('pending','ready','blocked') NOT NULL DEFAULT 'pending',
    checked_at DATETIME NULL,
    checked_by VARCHAR(191) NOT NULL,
    evidence_json JSON NULL,
    blocker_reason VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_measurement_content (content_link_id),
    KEY idx_startpartner_pilot_measurement_status (pilot_id, status),
    CONSTRAINT fk_startpartner_pilot_measurement_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_measurement_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_measurement_content
        FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_startpartner_pilot_measurement_ready
        CHECK (status <> 'ready' OR checked_at IS NOT NULL),
    CONSTRAINT chk_startpartner_pilot_measurement_blocked
        CHECK (status <> 'blocked' OR blocker_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_distribution_commitments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    channel ENUM('website','social_media','newsletter','member_communication','qr_code','on_site','specific_page_share','other') NOT NULL,
    planned_at DATETIME NOT NULL,
    target_reference VARCHAR(2048) NOT NULL,
    status ENUM('planned','ready','completed','blocked','cancelled') NOT NULL DEFAULT 'planned',
    evidence_json JSON NULL,
    blocker_reason VARCHAR(1000) NULL,
    operator_name VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_startpartner_pilot_distribution_status (pilot_id, status, planned_at),
    CONSTRAINT fk_startpartner_pilot_distribution_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_startpartner_pilot_distribution_blocked
        CHECK (status <> 'blocked' OR blocker_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    entitlement_id CHAR(36) NOT NULL,
    content_link_id BIGINT UNSIGNED NOT NULL,
    usage_kind ENUM('event_publication','activity_publication') NOT NULL,
    pilot_month_index TINYINT UNSIGNED NOT NULL,
    units INT UNSIGNED NOT NULL DEFAULT 1,
    consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_usage_content (content_link_id),
    KEY idx_startpartner_pilot_usage_period (pilot_id, pilot_month_index, usage_kind),
    CONSTRAINT fk_startpartner_pilot_usage_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_usage_entitlement
        FOREIGN KEY (entitlement_id) REFERENCES startpartner_pilot_entitlements(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_usage_content
        FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_startpartner_pilot_usage_month CHECK (pilot_month_index BETWEEN 1 AND 6),
    CONSTRAINT chk_startpartner_pilot_usage_units CHECK (units >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_activation_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    content_link_id BIGINT UNSIGNED NOT NULL,
    operation_id CHAR(36) NOT NULL,
    activation_date_local DATE NOT NULL,
    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
    activated_at_utc DATETIME NOT NULL,
    planned_end_date DATE NOT NULL,
    before_candidate_revision BIGINT UNSIGNED NOT NULL,
    after_candidate_revision BIGINT UNSIGNED NOT NULL,
    before_pilot_revision BIGINT UNSIGNED NOT NULL,
    after_pilot_revision BIGINT UNSIGNED NOT NULL,
    actor_reference VARCHAR(191) NOT NULL,
    evidence_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_activation_pilot (pilot_id),
    UNIQUE KEY uq_startpartner_pilot_activation_operation (operation_id),
    CONSTRAINT fk_startpartner_pilot_activation_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_activation_content
        FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_startpartner_pilot_activation_timezone CHECK (timezone_name = 'Europe/Berlin'),
    CONSTRAINT chk_startpartner_pilot_activation_period CHECK (planned_end_date > activation_date_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    operation_type VARCHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    expected_candidate_revision BIGINT UNSIGNED NOT NULL,
    expected_pilot_revision BIGINT UNSIGNED NOT NULL,
    status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
    result_json JSON NULL,
    error_text TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_operations_id (operation_id),
    KEY idx_startpartner_pilot_operations_pilot (pilot_id, created_at),
    CONSTRAINT fk_startpartner_pilot_operations_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_startpartner_pilot_operations_hash CHECK (CHAR_LENGTH(payload_hash) = 64)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '012_startpartner_gate4_onboarding_content_activation',
    'Add Gate-4 onboarding, pilot-content attribution, measurement preflight, distribution readiness, dedicated pilot usage, idempotent operations and activation evidence owners.'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === END FILE: api/sql/012_startpartner_gate4_onboarding_content_activation.sql ===
