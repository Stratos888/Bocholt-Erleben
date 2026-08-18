-- === BEGIN FILE: api/sql/012_startpartner_gate4_onboarding_content_activation.sql | Zweck: versioniert Gate-4-Onboarding, Pilotinhalt, Messpreflight, Distribution, Pilotnutzung und Aktivierungsdaten; Umfang: idempotente Ergänzung ohne Stripe-, Mail- oder reguläre Entitlement-Mutation ===

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE startpartner_pilots ADD COLUMN activation_date_local DATE NULL AFTER activated_at',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'startpartner_pilots'
      AND COLUMN_NAME = 'activation_date_local'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE startpartner_pilots ADD COLUMN planned_end_date DATE NULL AFTER activation_date_local',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'startpartner_pilots'
      AND COLUMN_NAME = 'planned_end_date'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

CREATE TABLE IF NOT EXISTS startpartner_pilot_onboarding_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    item_key VARCHAR(64) NOT NULL,
    status ENUM('pending','complete','blocked','not_applicable') NOT NULL DEFAULT 'pending',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_hard_blocker TINYINT(1) NOT NULL DEFAULT 1,
    evidence_text TEXT NULL,
    evidence_reference VARCHAR(2048) NULL,
    operator_reference VARCHAR(191) NULL,
    completed_at DATETIME NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_onboarding_item (pilot_id, item_key),
    KEY idx_startpartner_pilot_onboarding_status (pilot_id, status, is_hard_blocker),
    CONSTRAINT chk_startpartner_pilot_onboarding_revision CHECK (revision >= 1),
    CONSTRAINT fk_startpartner_pilot_onboarding_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_content_links (
    id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    content_type ENUM('event','activity') NOT NULL,
    status ENUM('draft','editorial_ready','approved','rejected','withdrawn') NOT NULL DEFAULT 'draft',
    reporting_target_type VARCHAR(32) NOT NULL DEFAULT 'organizer',
    reporting_target_id VARCHAR(96) NOT NULL,
    source_reference VARCHAR(191) NOT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    editorial_ready_at DATETIME NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_content_submission (submission_id),
    UNIQUE KEY uq_startpartner_pilot_content_source (pilot_id, source_reference),
    KEY idx_startpartner_pilot_content_status (pilot_id, status, content_type),
    KEY idx_startpartner_pilot_content_reporting (reporting_target_type, reporting_target_id),
    CONSTRAINT fk_startpartner_pilot_content_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_content_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_content_submission
        FOREIGN KEY (submission_id) REFERENCES submissions(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_measurement_preflights (
    id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    content_link_id CHAR(36) NOT NULL,
    status ENUM('pending','ready','blocked') NOT NULL DEFAULT 'pending',
    metrics_owner VARCHAR(64) NOT NULL DEFAULT 'value_metric_daily',
    reporting_target_type VARCHAR(32) NOT NULL DEFAULT 'organizer',
    reporting_target_id VARCHAR(96) NOT NULL,
    evidence_json JSON NOT NULL,
    checked_by VARCHAR(191) NOT NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_measurement_content (pilot_id, content_link_id),
    KEY idx_startpartner_pilot_measurement_status (pilot_id, status),
    CONSTRAINT fk_startpartner_pilot_measurement_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_measurement_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_measurement_content
        FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_distribution_commitments (
    id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    channel VARCHAR(64) NOT NULL,
    planned_at DATETIME NOT NULL,
    target_reference VARCHAR(2048) NOT NULL,
    status ENUM('planned','ready','completed','blocked','cancelled') NOT NULL DEFAULT 'planned',
    evidence_text TEXT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_startpartner_pilot_distribution_status (pilot_id, status, planned_at),
    CONSTRAINT fk_startpartner_pilot_distribution_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_usages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    pilot_entitlement_id CHAR(36) NOT NULL,
    content_link_id CHAR(36) NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    content_type ENUM('event','activity') NOT NULL,
    pilot_month_index TINYINT UNSIGNED NOT NULL DEFAULT 1,
    units INT UNSIGNED NOT NULL DEFAULT 1,
    consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_usage_submission (submission_id),
    UNIQUE KEY uq_startpartner_pilot_usage_content (content_link_id),
    KEY idx_startpartner_pilot_usage_period (pilot_id, pilot_month_index, content_type),
    CONSTRAINT chk_startpartner_pilot_usage_month CHECK (pilot_month_index BETWEEN 1 AND 6),
    CONSTRAINT chk_startpartner_pilot_usage_units CHECK (units >= 1),
    CONSTRAINT fk_startpartner_pilot_usage_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_usage_entitlement
        FOREIGN KEY (pilot_entitlement_id) REFERENCES startpartner_pilot_entitlements(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_usage_content
        FOREIGN KEY (content_link_id) REFERENCES startpartner_pilot_content_links(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilot_usage_submission
        FOREIGN KEY (submission_id) REFERENCES submissions(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '012_startpartner_gate4_onboarding_content_activation',
    'Add Gate-4 onboarding checklist, pilot-content attribution, measurement preflight, distribution readiness, pilot usage and local activation dates.'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === END FILE: api/sql/012_startpartner_gate4_onboarding_content_activation.sql ===
