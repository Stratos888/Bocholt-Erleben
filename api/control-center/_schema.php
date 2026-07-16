<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

function be_cc_ensure_schema(): void
{
    $pdo = be_db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS control_cases (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS control_case_events (
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
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS control_content_changes (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS control_development_snapshots (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          metrics_json JSON NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_development_snapshots_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS control_operations (
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
          KEY idx_control_operations_status (status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS control_editorial_feedback (
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
          KEY idx_control_editorial_feedback_issue (issue_code, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
