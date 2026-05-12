-- === BEGIN FILE: api/sql/005_inbox_push_notifications.sql | Zweck: speichert interne Browser-Push-Abos und dedupliziert einfache Inbox-Benachrichtigungen; Umfang: komplette Datei ===

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint_hash CHAR(64) NOT NULL,
    endpoint_url TEXT NOT NULL,
    p256dh_key TEXT NULL,
    auth_key TEXT NULL,
    user_agent_text VARCHAR(512) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_success_at DATETIME NULL,
    last_failure_at DATETIME NULL,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_subscriptions_endpoint_hash (endpoint_hash),
    KEY idx_push_subscriptions_active (is_active),
    KEY idx_push_subscriptions_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_notification_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel VARCHAR(32) NOT NULL DEFAULT 'web_push',
    source_type VARCHAR(64) NOT NULL,
    source_key VARCHAR(191) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempted_count INT UNSIGNED NOT NULL DEFAULT 0,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_notification_source (channel, source_type, source_key),
    KEY idx_review_notification_status (status),
    KEY idx_review_notification_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === END FILE: api/sql/005_inbox_push_notifications.sql ===
