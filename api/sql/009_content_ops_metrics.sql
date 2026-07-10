-- === BEGIN FILE: api/sql/009_content_ops_metrics.sql | Zweck: SQL-Zeitreihen fuer Content Ops Decision & Impact Engine und spaetere zentrale Verwaltungsoberflaeche | Umfang: additive Tabellen, keine Aenderung bestehender Daten ===

CREATE TABLE IF NOT EXISTS content_ops_run (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_fingerprint CHAR(64) NOT NULL,
  generated_at_utc DATETIME NOT NULL,
  environment VARCHAR(32) NOT NULL DEFAULT '',
  branch_name VARCHAR(64) NOT NULL DEFAULT '',
  workflow_name VARCHAR(191) NOT NULL DEFAULT '',
  github_run_id VARCHAR(64) NOT NULL DEFAULT '',
  github_run_url VARCHAR(512) NOT NULL DEFAULT '',
  source_mode VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(80) NOT NULL DEFAULT '',
  action_required TINYINT(1) NOT NULL DEFAULT 0,
  summary_json MEDIUMTEXT NULL,
  metrics_json MEDIUMTEXT NULL,
  findings_json MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_ops_run_fingerprint (run_fingerprint),
  KEY idx_content_ops_run_lookup (environment, source_mode, generated_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_ops_metric_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  environment VARCHAR(32) NOT NULL DEFAULT '',
  metric_key VARCHAR(160) NOT NULL DEFAULT '',
  metric_scope VARCHAR(80) NOT NULL DEFAULT '',
  dimension_key VARCHAR(191) NOT NULL DEFAULT '',
  metric_value DECIMAL(18,4) NOT NULL DEFAULT 0,
  source_mode VARCHAR(80) NOT NULL DEFAULT '',
  run_fingerprint CHAR(64) NOT NULL DEFAULT '',
  dimensions_json MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_ops_metric_run (metric_date, environment, metric_key, metric_scope, dimension_key, source_mode, run_fingerprint),
  KEY idx_content_ops_metric_lookup (environment, metric_key, metric_date),
  KEY idx_content_ops_metric_scope (environment, metric_scope, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_ops_action_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  action_fingerprint CHAR(64) NOT NULL,
  generated_at_utc DATETIME NOT NULL,
  environment VARCHAR(32) NOT NULL DEFAULT '',
  source_mode VARCHAR(80) NOT NULL DEFAULT '',
  source_workflow VARCHAR(191) NOT NULL DEFAULT '',
  action_type VARCHAR(120) NOT NULL DEFAULT '',
  finding_type VARCHAR(191) NOT NULL DEFAULT '',
  entity_type VARCHAR(80) NOT NULL DEFAULT '',
  entity_id VARCHAR(191) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL DEFAULT '',
  severity VARCHAR(40) NOT NULL DEFAULT '',
  confidence VARCHAR(80) NOT NULL DEFAULT '',
  user_action_required TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(80) NOT NULL DEFAULT 'open',
  run_fingerprint CHAR(64) NOT NULL DEFAULT '',
  details_json MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_ops_action (action_fingerprint, run_fingerprint),
  KEY idx_content_ops_action_lookup (environment, action_type, generated_at_utc),
  KEY idx_content_ops_action_manual (environment, user_action_required, generated_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback_rule_effectiveness_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  environment VARCHAR(32) NOT NULL DEFAULT '',
  rule_key VARCHAR(191) NOT NULL DEFAULT '',
  rule_type VARCHAR(80) NOT NULL DEFAULT '',
  rule_class VARCHAR(120) NOT NULL DEFAULT '',
  applied_count INT NOT NULL DEFAULT 0,
  prevented_count INT NOT NULL DEFAULT 0,
  recurrence_count INT NOT NULL DEFAULT 0,
  false_positive_count INT NOT NULL DEFAULT 0,
  source_mode VARCHAR(80) NOT NULL DEFAULT '',
  run_fingerprint CHAR(64) NOT NULL DEFAULT '',
  details_json MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feedback_rule_effectiveness_run (metric_date, environment, rule_key, source_mode, run_fingerprint),
  KEY idx_feedback_rule_lookup (environment, rule_type, rule_class, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === END FILE: api/sql/009_content_ops_metrics.sql ===
