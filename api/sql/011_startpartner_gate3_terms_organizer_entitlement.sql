-- === BEGIN FILE: api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql | Zweck: versioniert Gate-3-Pilotbedingungen, Organizer-Verknuepfung, Pilot, Scopes, ausstehende kostenlose Pilotberechtigung und Pilotaudit; Umfang: idempotente Neuanlage ohne Mail-, Session-, Stripe-, Submission-, reguläre Entitlement- oder Veröffentlichungsmutation ===

CREATE TABLE IF NOT EXISTS startpartner_pilot_terms_acceptances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id CHAR(36) NOT NULL,
    decision_id BIGINT UNSIGNED NOT NULL,
    terms_version VARCHAR(64) NOT NULL,
    terms_reference VARCHAR(2048) NOT NULL,
    terms_digest CHAR(64) NOT NULL,
    accepting_person VARCHAR(190) NOT NULL,
    accepting_organization VARCHAR(190) NOT NULL,
    accepted_at DATETIME NOT NULL,
    confirmation_channel ENUM('operator_recorded','signed_document','email_reply','portal') NOT NULL,
    service_scope_json JSON NOT NULL,
    source_care_json JSON NOT NULL,
    reach_contribution_json JSON NOT NULL,
    planned_activation_start DATE NULL,
    planned_activation_end DATE NULL,
    privacy_notice_version VARCHAR(64) NULL,
    communication_notice_version VARCHAR(64) NULL,
    no_automatic_paid_renewal TINYINT(1) NOT NULL,
    operator_reference VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_terms_candidate_version_digest (
        candidate_id, terms_version, terms_digest
    ),
    KEY idx_startpartner_terms_decision (decision_id),
    KEY idx_startpartner_terms_accepted (accepted_at),
    CONSTRAINT chk_startpartner_terms_digest
        CHECK (CHAR_LENGTH(terms_digest) = 64),
    CONSTRAINT chk_startpartner_terms_no_auto_renewal
        CHECK (no_automatic_paid_renewal = 1),
    CONSTRAINT chk_startpartner_terms_activation_window
        CHECK (
            planned_activation_start IS NULL
            OR planned_activation_end IS NULL
            OR planned_activation_end >= planned_activation_start
        ),
    CONSTRAINT fk_startpartner_terms_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_terms_decision
        FOREIGN KEY (decision_id) REFERENCES startpartner_candidate_decisions(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilots (
    id CHAR(36) NOT NULL,
    candidate_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    terms_acceptance_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NOT NULL,
    cohort_key VARCHAR(64) NOT NULL,
    status ENUM(
        'onboarding','activation_ready','active','paused','closing',
        'converted','ended_without_conversion','terminated'
    ) NOT NULL DEFAULT 'onboarding',
    health ENUM('green','yellow','red','unknown') NOT NULL DEFAULT 'unknown',
    target_plan_keys_json JSON NOT NULL,
    internal_owner VARCHAR(191) NOT NULL,
    partner_contact_name_snapshot VARCHAR(190) NULL,
    partner_contact_email_snapshot VARCHAR(190) NOT NULL,
    onboarding_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activation_ready_at DATETIME NULL,
    activated_at DATETIME NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    closed_at DATETIME NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilots_candidate (candidate_id),
    UNIQUE KEY uq_startpartner_pilots_terms (terms_acceptance_id),
    UNIQUE KEY uq_startpartner_pilots_reservation (reservation_id),
    KEY idx_startpartner_pilots_organizer (organizer_id),
    KEY idx_startpartner_pilots_status (status, updated_at),
    CONSTRAINT chk_startpartner_pilots_revision CHECK (revision >= 1),
    CONSTRAINT chk_startpartner_pilots_period
        CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at),
    CONSTRAINT fk_startpartner_pilots_candidate
        FOREIGN KEY (candidate_id) REFERENCES startpartner_candidates(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilots_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilots_terms
        FOREIGN KEY (terms_acceptance_id) REFERENCES startpartner_pilot_terms_acceptances(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_startpartner_pilots_reservation
        FOREIGN KEY (reservation_id) REFERENCES startpartner_candidate_reservations(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_scopes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    scope_key VARCHAR(64) NOT NULL,
    scope_type ENUM(
        'events','activities','automatic_source','maintenance_service',
        'provider_portal','measurement','reach_contribution'
    ) NOT NULL,
    status ENUM('planned','active','paused','ended') NOT NULL DEFAULT 'planned',
    target_plan_key VARCHAR(64) NULL,
    limit_value INT UNSIGNED NULL,
    is_unlimited TINYINT(1) NOT NULL DEFAULT 0,
    period_unit ENUM('pilot_month','concurrent','pilot_total','not_applicable')
        NOT NULL DEFAULT 'not_applicable',
    details_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_scope (pilot_id, scope_key),
    KEY idx_startpartner_pilot_scopes_type (scope_type, status),
    CONSTRAINT chk_startpartner_pilot_scope_limit
        CHECK (
            (is_unlimited = 1 AND limit_value IS NULL)
            OR (is_unlimited = 0)
        ),
    CONSTRAINT fk_startpartner_pilot_scopes_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_entitlements (
    id CHAR(36) NOT NULL,
    pilot_id CHAR(36) NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(32) NOT NULL DEFAULT 'startpartner_pilot',
    source_reference VARCHAR(191) NOT NULL,
    status ENUM('pending_activation','active','paused','ended','revoked')
        NOT NULL DEFAULT 'pending_activation',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    target_plan_keys_json JSON NOT NULL,
    event_limit_per_pilot_month INT UNSIGNED NULL,
    activity_concurrent_limit INT UNSIGNED NULL,
    is_event_unlimited TINYINT(1) NOT NULL DEFAULT 0,
    source_scope_json JSON NOT NULL,
    audit_json JSON NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_startpartner_pilot_entitlement_pilot (pilot_id),
    UNIQUE KEY uq_startpartner_pilot_entitlement_source (source_type, source_reference),
    KEY idx_startpartner_pilot_entitlement_organizer (organizer_id, status),
    KEY idx_startpartner_pilot_entitlement_period (starts_at, ends_at),
    CONSTRAINT chk_startpartner_pilot_entitlement_source
        CHECK (source_type = 'startpartner_pilot'),
    CONSTRAINT chk_startpartner_pilot_entitlement_revision CHECK (revision >= 1),
    CONSTRAINT chk_startpartner_pilot_entitlement_pending
        CHECK (
            status <> 'pending_activation'
            OR (starts_at IS NULL AND ends_at IS NULL)
        ),
    CONSTRAINT chk_startpartner_pilot_entitlement_period
        CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at),
    CONSTRAINT chk_startpartner_pilot_entitlement_unlimited
        CHECK (
            (is_event_unlimited = 1 AND event_limit_per_pilot_month IS NULL)
            OR is_event_unlimited = 0
        ),
    CONSTRAINT fk_startpartner_pilot_entitlement_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_startpartner_pilot_entitlement_organizer
        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS startpartner_pilot_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pilot_id CHAR(36) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    actor_reference VARCHAR(191) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_startpartner_pilot_events_pilot (pilot_id, created_at, id),
    KEY idx_startpartner_pilot_events_type (event_type, created_at),
    CONSTRAINT fk_startpartner_pilot_events_pilot
        FOREIGN KEY (pilot_id) REFERENCES startpartner_pilots(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '011_startpartner_gate3_terms_organizer_entitlement',
    'Add Gate-3 immutable pilot terms, onboarding pilot, normalized scopes, pending pilot entitlement and pilot audit owners.'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === END FILE: api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql ===
