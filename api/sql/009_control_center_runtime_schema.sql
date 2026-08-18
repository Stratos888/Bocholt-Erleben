-- === BEGIN FILE: api/sql/009_control_center_runtime_schema.sql | Zweck: übernimmt alle dauerhaft genutzten Control-Center-Tabellen in die kanonische versionierte SQL-Kette und beendet Runtime-DDL als Schema-Owner; Umfang: idempotente Tabellenanlage ohne fachliche Datenmutation ===

CREATE TABLE IF NOT EXISTS control_cases (
    id CHAR(36) NOT NULL,
    case_type ENUM('intake','task','idea','information') NOT NULL,
    state ENUM('new','decision_required','open','in_progress','waiting','blocked','snoozed','done','rejected','information','parked') NOT NULL,
    priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    title VARCHAR(240) NOT NULL,
    reason TEXT NULL,
    next_action VARCHAR(500) NULL,
    object_type VARCHAR(64) NULL,
    object_id VARCHAR(191) NULL,
    object_title VARCHAR(240) NULL,
    source_system VARCHAR(96) NOT NULL,
    source_reference VARCHAR(191) NOT NULL,
    source_payload_json JSON NULL,
    due_at DATETIME NULL,
    snoozed_until DATETIME NULL,
    blocked_reason VARCHAR(500) NULL,
    decision_ready TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_control_cases_source (source_system, source_reference),
    KEY idx_control_cases_attention (state, priority, due_at),
    KEY idx_control_cases_object (object_type, object_id),
    KEY idx_control_cases_snooze (snoozed_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_case_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id CHAR(36) NOT NULL,
    action VARCHAR(64) NOT NULL,
    from_state VARCHAR(32) NULL,
    to_state VARCHAR(32) NULL,
    actor VARCHAR(96) NOT NULL DEFAULT 'system',
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_control_case_events_case (case_id, created_at),
    CONSTRAINT fk_control_case_events_case
        FOREIGN KEY (case_id) REFERENCES control_cases(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_content_changes (
    id CHAR(36) NOT NULL,
    object_type VARCHAR(64) NOT NULL,
    object_id VARCHAR(191) NOT NULL,
    object_title VARCHAR(240) NOT NULL,
    source_system VARCHAR(96) NOT NULL,
    before_json JSON NULL,
    updates_json JSON NOT NULL,
    written_fields_json JSON NULL,
    publication_state ENUM('saved','deploy_started','deploy_failed','waiting','confirmed','verification_failed') NOT NULL DEFAULT 'saved',
    publication_error TEXT NULL,
    public_url VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_content_changes_object (object_type, object_id, created_at),
    KEY idx_content_changes_state (publication_state, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_development_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metrics_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_development_snapshots_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_operations (
    operation_id VARCHAR(128) NOT NULL,
    case_id CHAR(36) NOT NULL,
    action VARCHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status ENUM('started','source_written','completed','failed') NOT NULL DEFAULT 'started',
    result_json JSON NULL,
    error_text TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (operation_id),
    KEY idx_control_operations_case (case_id, created_at),
    KEY idx_control_operations_status (status, updated_at),
    CONSTRAINT fk_control_operations_case
        FOREIGN KEY (case_id) REFERENCES control_cases(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_editorial_feedback (
    id CHAR(36) NOT NULL,
    case_id CHAR(36) NOT NULL,
    object_id VARCHAR(191) NULL,
    issue_code VARCHAR(128) NULL,
    before_text MEDIUMTEXT NULL,
    suggested_text MEDIUMTEXT NULL,
    final_text MEDIUMTEXT NOT NULL,
    diff_json JSON NULL,
    categories_json JSON NULL,
    decision_class VARCHAR(64) NOT NULL,
    source_fingerprint VARCHAR(191) NULL,
    content_fingerprint VARCHAR(191) NULL,
    rule_version VARCHAR(128) NULL,
    status ENUM('observation','candidate','active','disabled') NOT NULL DEFAULT 'observation',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_control_editorial_feedback_case (case_id, created_at),
    KEY idx_control_editorial_feedback_status (status, created_at),
    KEY idx_control_editorial_feedback_issue (issue_code, created_at),
    CONSTRAINT fk_control_editorial_feedback_case
        FOREIGN KEY (case_id) REFERENCES control_cases(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Existing Runtime-DDL installations already own the tables but not every
-- relationship. Reconcile the constraints without touching business rows.
SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE control_case_events ADD CONSTRAINT fk_control_case_events_case FOREIGN KEY (case_id) REFERENCES control_cases(id) ON UPDATE CASCADE ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'control_case_events'
      AND CONSTRAINT_NAME = 'fk_control_case_events_case'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE control_operations ADD CONSTRAINT fk_control_operations_case FOREIGN KEY (case_id) REFERENCES control_cases(id) ON UPDATE CASCADE ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'control_operations'
      AND CONSTRAINT_NAME = 'fk_control_operations_case'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE control_editorial_feedback ADD CONSTRAINT fk_control_editorial_feedback_case FOREIGN KEY (case_id) REFERENCES control_cases(id) ON UPDATE CASCADE ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'control_editorial_feedback'
      AND CONSTRAINT_NAME = 'fk_control_editorial_feedback_case'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '009_control_center_runtime_schema',
    'Move all persistent Control Center tables from runtime DDL into the canonical versioned SQL chain.'
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

-- === END FILE: api/sql/009_control_center_runtime_schema.sql ===
