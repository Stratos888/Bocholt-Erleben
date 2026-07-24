-- === BEGIN FILE: api/sql/007_runtime_schema_reconciliation.sql | Zweck: konsolidiert belegte Runtime-Schemaabweichungen in der versionierten SQL-Kette; Umfang: idempotente Reconciliation ohne personenbezogene Datenmutation ===

CREATE TABLE IF NOT EXISTS app_schema_migrations (
    migration_key VARCHAR(96) NOT NULL,
    description VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE submissions ADD COLUMN activity_opening_json JSON NULL AFTER notes_text',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'submissions'
      AND COLUMN_NAME = 'activity_opening_json'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE submissions ADD COLUMN activity_image_json JSON NULL AFTER activity_opening_json',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'submissions'
      AND COLUMN_NAME = 'activity_image_json'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

SET @be_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE submissions ADD INDEX idx_submissions_organizer_edited_at (organizer_edited_at)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'submissions'
      AND INDEX_NAME = 'idx_submissions_organizer_edited_at'
);
PREPARE be_stmt FROM @be_sql;
EXECUTE be_stmt;
DEALLOCATE PREPARE be_stmt;

INSERT INTO app_schema_migrations (migration_key, description)
VALUES (
    '007_runtime_schema_reconciliation',
    'Version activity JSON columns and the organizer-edited index already proven in runtime.'
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

-- === END FILE: api/sql/007_runtime_schema_reconciliation.sql ===
