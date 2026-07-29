-- === BEGIN FILE: api/sql/012_startpartner_gate4_onboarding_content_activation.sql | Zweck: versioniert Gate-4-Onboarding, Portalnachweis, Inhalts-/Mess-/Distributionszuordnung, Pilotnutzung und atomare Aktivierung ohne Stripe- oder Live-Wirkung ===

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_pilots ADD COLUMN activation_date_local DATE NULL AFTER activated_at',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_pilots' AND COLUMN_NAME = 'activation_date_local'
);
PREPARE be_stmt FROM @be_sql; EXECUTE be_stmt; DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_pilots ADD COLUMN planned_end_date DATE NULL AFTER activation_date_local',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_pilots' AND COLUMN_NAME = 'planned_end_date'
);
PREPARE be_stmt FROM @be_sql; EXECUTE be_stmt; DEALLOCATE PREPARE be_stmt;

CREATE TABLE IF NOT EXISTS startpartner_pilot_onboarding_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    item_key VARCHAR(64) NOT NULL,
    status ENUM('pending','complete','blocked') NOT NULL DEFAULT 'pending',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    evidence_text TEXT NULL,
    blocker_text TEXT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    completed_at DATETIME NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_onboarding_item (pilot_id, item_key),
    KEY idx_startpartner_onboarding_status (pilot_id, status, is_required),
    CONSTRAINT chk_startpartner_onboarding_revision CHECK (revision >= 1),
    CONSTRAINT chk_startpartner_onboarding_completion CHECK (
        (status = 'complete' AND completed_at IS NOT NULL AND blocker_text IS NULL)
        OR (status = 'blocked' AND blocker_text IS NOT NULL)
        OR status = 'pending'
    ),
    CONSTRAINT fk_startpartner_onboarding_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_portal_proofs (
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    proof_kind ENUM('session_readback','no_send_fixture') NOT NULL,
    status ENUM('ready','blocked') NOT NULL,
    session_reference VARCHAR(191) NULL,
    evidence_json JSON NOT NULL,
    checked_at DATETIME NOT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (pilot_id),
    KEY idx_startpartner_portal_proof_organizer (organizer_id, status),
    CONSTRAINT chk_startpartner_portal_proof_revision CHECK (revision >= 1),
    CONSTRAINT fk_startpartner_portal_proof_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_portal_proof_organizer FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_content_links (
    id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    content_type ENUM('event','activity') NOT NULL,
    status ENUM('prepared','editorial_ready','approved','rejected') NOT NULL DEFAULT 'prepared',
    source_reference VARCHAR(191) NOT NULL,
    reporting_target_id VARCHAR(191) NOT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    editorial_ready_at DATETIME NULL,
    approved_at DATETIME NULL,
    operator_reference VARCHAR(191) NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_content_submission (submission_id),
    KEY idx_startpartner_content_pilot (pilot_id, status),
    KEY idx_startpartner_content_reporting (reporting_target_id),
    CONSTRAINT chk_startpartner_content_revision CHECK (revision >= 1),
    CONSTRAINT fk_startpartner_content_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_content_organizer FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_content_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_measurement_preflights (
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    content_link_id CHAR(36) NOT NULL,
    reporting_target_type VARCHAR(32) NOT NULL DEFAULT 'organizer',
    reporting_target_id VARCHAR(191) NOT NULL,
    status ENUM('pending','ready','blocked') NOT NULL DEFAULT 'pending',
    evidence_json JSON NOT NULL,
    checked_at DATETIME NULL,
    operator_reference VARCHAR(191) NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (pilot_id),
    UNIQUE KEY uq_startpartner_measurement_target (reporting_target_type, reporting_target_id, pilot_id),
    KEY idx_startpartner_measurement_status (status, checked_at),
    CONSTRAINT chk_startpartner_measurement_revision CHECK (revision >= 1),
    CONSTRAINT fk_startpartner_measurement_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_measurement_organizer FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_measurement_content FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_distribution_commitments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    channel VARCHAR(64) NOT NULL,
    planned_date DATE NOT NULL,
    target_reference VARCHAR(2048) NOT NULL,
    status ENUM('planned','ready','completed','blocked') NOT NULL DEFAULT 'planned',
    evidence_text TEXT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_startpartner_distribution_pilot (pilot_id, status, planned_date),
    CONSTRAINT chk_startpartner_distribution_revision CHECK (revision >= 1),
    CONSTRAINT fk_startpartner_distribution_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_usages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    pilot_entitlement_id CHAR(36) NOT NULL,
    content_link_id CHAR(36) NOT NULL,
    usage_kind ENUM('event_publication','activity_presence') NOT NULL,
    units INT UNSIGNED NOT NULL DEFAULT 1,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    operator_reference VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_usage_content (content_link_id),
    KEY idx_startpartner_usage_period (pilot_id, period_start, period_end),
    CONSTRAINT chk_startpartner_usage_units CHECK (units >= 1),
    CONSTRAINT chk_startpartner_usage_period CHECK (period_end >= period_start),
    CONSTRAINT fk_startpartner_usage_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_usage_entitlement FOREIGN KEY (pilot_entitlement_id) REFERENCES startpartner_pilot_entitlements(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_usage_content FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_operations (
    operation_id VARCHAR(128) NOT NULL,
    candidate_id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    action VARCHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status ENUM('started','completed','failed') NOT NULL DEFAULT 'started',
    result_json JSON NULL,
    error_text TEXT NULL,
    candidate_revision_before BIGINT UNSIGNED NOT NULL,
    candidate_revision_after BIGINT UNSIGNED NULL,
    pilot_revision_before BIGINT UNSIGNED NOT NULL,
    pilot_revision_after BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (operation_id),
    KEY idx_startpartner_pilot_operations_pilot (pilot_id, created_at),
    KEY idx_startpartner_pilot_operations_status (status, updated_at),
    CONSTRAINT chk_startpartner_pilot_operation_candidate_revision CHECK (candidate_revision_after IS NULL OR candidate_revision_after >= candidate_revision_before),
    CONSTRAINT chk_startpartner_pilot_operation_pilot_revision CHECK (pilot_revision_after IS NULL OR pilot_revision_after >= pilot_revision_before),
    CONSTRAINT fk_startpartner_pilot_operation_candidate FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_operation_pilot FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES ('012_startpartner_gate4_onboarding_content_activation','Add Gate-4 onboarding, portal proof, pilot content and measurement attribution, distribution readiness, dedicated pilot usage and atomic activation operation owners.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === END FILE: api/sql/012_startpartner_gate4_onboarding_content_activation.sql ===
