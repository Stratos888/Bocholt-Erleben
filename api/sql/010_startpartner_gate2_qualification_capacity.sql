-- === BEGIN FILE: api/sql/010_startpartner_gate2_qualification_capacity.sql | Zweck: versioniert Gate-2-Qualifizierung, Entscheidungen, Kapazitätsreservierungen, Warteliste, Kandidatenrevision und payloadgebundene Operations-Idempotenz; Umfang: idempotente Schemaerweiterung ohne Organizer-, Submission-, Subscription-, Entitlement- oder Publication-Mutation ===

ALTER TABLE startpartner_candidates
    MODIFY COLUMN status ENUM(
        'new','prequalifying','contact_pending','awaiting_response','qualifying',
        'needs_information','decision_ready','accepted_pending_terms','waitlisted',
        'routed_to_regular_product','rejected','withdrawn','expired','qualified'
    ) NOT NULL DEFAULT 'new';

UPDATE startpartner_candidates
SET status = 'decision_ready'
WHERE status = 'qualified';

ALTER TABLE startpartner_candidates
    MODIFY COLUMN status ENUM(
        'new','prequalifying','contact_pending','awaiting_response','qualifying',
        'needs_information','decision_ready','accepted_pending_terms','waitlisted',
        'routed_to_regular_product','rejected','withdrawn','expired'
    ) NOT NULL DEFAULT 'new';

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_candidates ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 1 CHECK (revision >= 1) AFTER status_reason',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_candidates' AND COLUMN_NAME = 'revision'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_candidates ADD COLUMN assigned_to VARCHAR(191) NULL AFTER revision',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_candidates' AND COLUMN_NAME = 'assigned_to'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_candidates ADD COLUMN next_review_at DATETIME NULL AFTER assigned_to',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_candidates' AND COLUMN_NAME = 'next_review_at'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_candidates ADD COLUMN status_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER next_review_at',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_candidates' AND COLUMN_NAME = 'status_changed_at'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_candidates ADD INDEX idx_startpartner_candidates_assignment (assigned_to, status, next_review_at)',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_candidates' AND INDEX_NAME = 'idx_startpartner_candidates_assignment'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE startpartner_candidates ADD INDEX idx_startpartner_candidates_review (next_review_at, status, updated_at)',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'startpartner_candidates' AND INDEX_NAME = 'idx_startpartner_candidates_review'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

CREATE TABLE IF NOT EXISTS startpartner_candidate_qualifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id CHAR(36) NOT NULL,
    dimension ENUM(
        'local_relevance','organization_contact','content_sources','editorial_fit',
        'content_leverage','reach_leverage','user_need','maintenance_capability',
        'cooperation_readiness','setup_effort','support_effort','regular_path',
        'legal_technical','required_information'
    ) NOT NULL,
    assessment ENUM('unknown','weak','adequate','strong') NOT NULL DEFAULT 'unknown',
    reason TEXT NULL,
    evidence_text TEXT NULL,
    evidence_url VARCHAR(2048) NULL,
    operator_reference VARCHAR(191) NOT NULL,
    evaluated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1 CHECK (revision >= 1),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_qualification_dimension (candidate_id, dimension),
    KEY idx_startpartner_qualifications_assessment (dimension, assessment, updated_at),
    CONSTRAINT fk_startpartner_qualifications_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_candidate_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id CHAR(36) NOT NULL,
    result ENUM('accepted_pending_terms','waitlisted','routed_to_regular_product','rejected','withdrawn','expired') NOT NULL,
    reason TEXT NOT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    candidate_revision BIGINT UNSIGNED NOT NULL CHECK (candidate_revision >= 1),
    qualification_snapshot_json JSON NOT NULL,
    capacity_snapshot_json JSON NOT NULL,
    regular_alternative VARCHAR(500) NULL,
    waitlist_or_rejection_reason TEXT NULL,
    reservation_reference BIGINT UNSIGNED NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1 CHECK (is_current IN (0,1)),
    current_guard TINYINT(1) GENERATED ALWAYS AS (CASE WHEN is_current = 1 THEN 1 ELSE NULL END) STORED,
    superseded_at DATETIME NULL,
    superseded_by_decision_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_decisions_current (candidate_id, current_guard),
    KEY idx_startpartner_decisions_history (candidate_id, decided_at, id),
    KEY idx_startpartner_decisions_result (result, decided_at),
    CONSTRAINT fk_startpartner_decisions_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_decisions_superseded_by
        FOREIGN KEY (superseded_by_decision_id) REFERENCES startpartner_candidate_decisions(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_candidate_reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id CHAR(36) NOT NULL,
    decision_id BIGINT UNSIGNED NULL,
    status ENUM('active','released','expired') NOT NULL DEFAULT 'active',
    active_guard TINYINT(1) GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) STORED,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    capacity_snapshot_json JSON NOT NULL,
    soft_stop_exception_reason TEXT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    supersedes_reservation_id BIGINT UNSIGNED NULL,
    released_at DATETIME NULL,
    release_reference VARCHAR(191) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_reservations_active (candidate_id, active_guard),
    KEY idx_startpartner_reservations_capacity (status, ends_at, starts_at),
    KEY idx_startpartner_reservations_decision (decision_id),
    CONSTRAINT chk_startpartner_reservation_window CHECK (ends_at > starts_at AND DATEDIFF(ends_at, starts_at) <= 30),
    CONSTRAINT fk_startpartner_reservations_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_reservations_decision
        FOREIGN KEY (decision_id) REFERENCES startpartner_candidate_decisions(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_startpartner_reservations_supersedes
        FOREIGN KEY (supersedes_reservation_id) REFERENCES startpartner_candidate_reservations(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_candidate_waitlist (
    candidate_id CHAR(36) NOT NULL,
    eligibility_reason TEXT NOT NULL,
    priority_reason TEXT NOT NULL,
    next_review_at DATETIME NOT NULL,
    contact_status ENUM('not_contacted','contact_pending','contacted','paused') NOT NULL DEFAULT 'not_contacted',
    regular_alternative VARCHAR(500) NULL,
    operator_reference VARCHAR(191) NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1 CHECK (revision >= 1),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (candidate_id),
    KEY idx_startpartner_waitlist_review (next_review_at, contact_status),
    CONSTRAINT fk_startpartner_waitlist_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_candidate_operations (
    operation_id VARCHAR(128) NOT NULL,
    candidate_id CHAR(36) NOT NULL,
    action VARCHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status ENUM('started','completed','failed') NOT NULL DEFAULT 'started',
    result_json JSON NULL,
    error_text TEXT NULL,
    candidate_revision_before BIGINT UNSIGNED NOT NULL CHECK (candidate_revision_before >= 1),
    candidate_revision_after BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (operation_id),
    KEY idx_startpartner_operations_candidate (candidate_id, created_at),
    KEY idx_startpartner_operations_status (status, updated_at),
    CONSTRAINT chk_startpartner_operations_revision_order CHECK (
        candidate_revision_after IS NULL OR candidate_revision_after >= candidate_revision_before
    ),
    CONSTRAINT fk_startpartner_operations_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '010_startpartner_gate2_qualification_capacity',
    'Add Gate-2 candidate revision, normalized qualifications, append-only decisions, historized reservations, waitlist and payload-bound operations.'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === END FILE: api/sql/010_startpartner_gate2_qualification_capacity.sql ===
