#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_NAME="bocholt_release_preflight"
DB_PASSWORD="release-preflight-only"

BASELINE_MIGRATIONS=(
  "api/sql/001_publish_funnel_core.sql"
  "api/sql/002_organizer_portal_core.sql"
  "api/sql/003_submission_intake_origin_location_review.sql"
  "api/sql/004_submission_organizer_edit_tracking.sql"
  "api/sql/005_inbox_push_notifications.sql"
  "api/sql/006_single_event_review_before_payment.sql"
  "api/sql/007_activity_opening_json.sql"
  "api/sql/008_activity_image_json.sql"
  "api/sql/009_content_ops_metrics.sql"
)

OBSERVED_LIVE_TRACKED_MIGRATIONS=(
  "api/sql/001_publish_funnel_core.sql"
  "api/sql/002_organizer_portal_core.sql"
  "api/sql/003_submission_intake_origin_location_review.sql"
  "api/sql/004_submission_organizer_edit_tracking.sql"
  "api/sql/005_inbox_push_notifications.sql"
  "api/sql/006_single_event_review_before_payment.sql"
  "api/sql/007_activity_opening_json.sql"
  "api/sql/008_activity_image_json.sql"
)

UPGRADE_MIGRATIONS=(
  "api/sql/007_runtime_schema_reconciliation.sql"
  "api/sql/008_startpartner_candidates.sql"
  "api/sql/009_control_center_runtime_schema.sql"
  "api/sql/010_startpartner_gate2_qualification_capacity.sql"
  "api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql"
  "api/sql/012_startpartner_gate4_onboarding_content_activation.sql"
)

cleanup_containers=()
cleanup() {
  for container in "${cleanup_containers[@]:-}"; do
    docker rm -f "$container" >/dev/null 2>&1 || true
  done
}
trap cleanup EXIT

apply_file() {
  local container="$1"
  local client="$2"
  local file="$3"
  echo "[$container] apply $file"
  docker exec -i "$container" "$client" -uroot "-p$DB_PASSWORD" "$DB_NAME" < "$ROOT/$file"
}

query_scalar() {
  local container="$1"
  local client="$2"
  local sql="$3"
  docker exec "$container" "$client" -N -B -uroot "-p$DB_PASSWORD" "$DB_NAME" -e "$sql" 2>/dev/null | tr -d '\r'
}

assert_scalar() {
  local container="$1"
  local client="$2"
  local sql="$3"
  local expected="$4"
  local label="$5"
  local actual
  actual="$(query_scalar "$container" "$client" "$sql")"
  if [ "$actual" != "$expected" ]; then
    echo "[$container] $label: expected '$expected', got '$actual'" >&2
    exit 1
  fi
}

start_engine() {
  local label="$1"
  local image="$2"
  local client="$3"
  local password_var="$4"
  local database_var="$5"
  local container="be-release-preflight-${label}-${GITHUB_RUN_ID:-local}-$$"

  cleanup_containers+=("$container")
  echo "=== $label: start temporary database ===" >&2
  docker run -d --name "$container" \
    -e "$password_var=$DB_PASSWORD" \
    -e "$database_var=$DB_NAME" \
    "$image" >/dev/null

  local ready=false
  for _ in $(seq 1 60); do
    if docker exec "$container" "$client" -uroot "-p$DB_PASSWORD" -e 'SELECT 1' >/dev/null 2>&1; then
      ready=true
      break
    fi
    sleep 1
  done
  if [ "$ready" != true ]; then
    echo "$label database did not become ready" >&2
    docker logs "$container" >&2 || true
    exit 1
  fi
  printf '%s\n' "$container"
}

finish_engine() {
  local container="$1"
  docker rm -f "$container" >/dev/null
  local remaining=()
  local item
  for item in "${cleanup_containers[@]:-}"; do
    if [ "$item" != "$container" ]; then
      remaining+=("$item")
    fi
  done
  cleanup_containers=("${remaining[@]:-}")
}

install_observed_live_runtime_schema() {
  local container="$1"
  local client="$2"
  docker exec -i "$container" "$client" -uroot "-p$DB_PASSWORD" "$DB_NAME" <<'SQL'
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
  CONSTRAINT fk_control_case_events_case FOREIGN KEY (case_id) REFERENCES control_cases(id) ON DELETE CASCADE
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
  KEY idx_control_operations_status (status, updated_at)
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
  KEY idx_control_editorial_feedback_issue (issue_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS value_metric_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  metric_key VARCHAR(64) NOT NULL,
  entity_type VARCHAR(40) NOT NULL DEFAULT '',
  entity_id VARCHAR(191) NOT NULL DEFAULT '',
  entity_title VARCHAR(255) NULL,
  destination_url VARCHAR(1024) NULL,
  reporting_target_type VARCHAR(40) NOT NULL DEFAULT '',
  reporting_target_id VARCHAR(191) NOT NULL DEFAULT '',
  reporting_target_title VARCHAR(255) NULL,
  page_path VARCHAR(255) NULL,
  source_context VARCHAR(64) NOT NULL DEFAULT '',
  bucket_hash CHAR(64) NOT NULL,
  count_value INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_value_metric_daily_bucket (bucket_hash),
  KEY idx_value_metric_daily_date_key (metric_date, metric_key),
  KEY idx_value_metric_daily_entity (metric_date, entity_type, entity_id),
  KEY idx_value_metric_daily_reporting_target (metric_date, reporting_target_type, reporting_target_id),
  KEY idx_value_metric_daily_source_context (metric_date, metric_key, source_context)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO control_cases (id, case_type, state, title, source_system, source_reference)
VALUES ('00000000-0000-0000-0000-000000000001', 'task', 'open', 'release preflight marker', 'release_preflight', 'live-shape');
INSERT INTO control_case_events (case_id, action, to_state)
VALUES ('00000000-0000-0000-0000-000000000001', 'created', 'open');
INSERT INTO control_operations (operation_id, case_id, action, payload_hash, status)
VALUES ('release-preflight-op', '00000000-0000-0000-0000-000000000001', 'noop', REPEAT('a',64), 'completed');
INSERT INTO control_editorial_feedback (id, case_id, final_text, decision_class)
VALUES ('00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000001', 'marker', 'observation');
SQL
}

run_expected_baseline_engine() {
  local label="$1"
  local image="$2"
  local client="$3"
  local password_var="$4"
  local database_var="$5"
  local container
  container="$(start_engine "$label" "$image" "$client" "$password_var" "$database_var")"

  echo "=== $label: reproduce tracked main schema lineage ==="
  for file in "${BASELINE_MIGRATIONS[@]}"; do
    apply_file "$container" "$client" "$file"
  done

  docker exec "$container" "$client" -uroot "-p$DB_PASSWORD" "$DB_NAME" -e \
    "INSERT INTO content_ops_run (run_fingerprint, generated_at_utc, environment, status) VALUES (REPEAT('a',64), '2026-08-18 00:00:00', 'live-preflight', 'baseline');" >/dev/null

  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='submissions' AND COLUMN_NAME IN ('activity_opening_json','activity_image_json');" \
    "2" "legacy activity columns before upgrade"
  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM content_ops_run WHERE run_fingerprint=REPEAT('a',64);" \
    "1" "baseline Content-Ops row before upgrade"

  for file in "${UPGRADE_MIGRATIONS[@]}"; do
    apply_file "$container" "$client" "$file"
  done
  for file in "${UPGRADE_MIGRATIONS[@]}"; do
    apply_file "$container" "$client" "$file"
  done

  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM content_ops_run WHERE run_fingerprint=REPEAT('a',64) AND environment='live-preflight' AND status='baseline';" \
    "1" "pre-existing Content-Ops row preserved"
  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key IN ('007_runtime_schema_reconciliation','008_startpartner_candidates','009_control_center_runtime_schema','010_startpartner_gate2_qualification_capacity','011_startpartner_gate3_terms_organizer_entitlement','012_startpartner_gate4_onboarding_content_activation');" \
    "6" "all staging migration keys recorded once"

  echo "=== $label: tracked main-to-staging schema upgrade OK ==="
  finish_engine "$container"
}

run_observed_live_engine() {
  local container
  container="$(start_engine "mysql8036-observed-live" "mysql:8.0.36" "mysql" "MYSQL_ROOT_PASSWORD" "MYSQL_DATABASE")"

  echo "=== mysql8036-observed-live: reproduce exact read-only observed Live shape ==="
  for file in "${OBSERVED_LIVE_TRACKED_MIGRATIONS[@]}"; do
    apply_file "$container" "mysql" "$file"
  done
  install_observed_live_runtime_schema "$container" "mysql"

  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('content_ops_run','content_ops_metric_daily','content_ops_action_log','feedback_rule_effectiveness_daily');" \
    "0" "observed Live has no legacy Content-Ops tables before cutover"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'startpartner\\_%';" \
    "0" "observed Live has no Startpartner tables before cutover"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='submissions' AND COLUMN_NAME IN ('activity_opening_json','activity_image_json','organizer_edited_at');" \
    "3" "observed Live reconciliation columns present"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='submissions' AND INDEX_NAME='idx_submissions_organizer_edited_at';" \
    "0" "observed Live organizer edit index absent before cutover"

  echo "=== mysql8036-observed-live: first apply missing tracked legacy Content-Ops migration ==="
  apply_file "$container" "mysql" "api/sql/009_content_ops_metrics.sql"
  docker exec "$container" mysql -uroot "-p$DB_PASSWORD" "$DB_NAME" -e \
    "INSERT INTO content_ops_run (run_fingerprint, generated_at_utc, environment, status) VALUES (REPEAT('b',64), '2026-08-18 00:00:00', 'observed-live', 'baseline');" >/dev/null

  echo "=== mysql8036-observed-live: apply canonical 007-012 chain ==="
  for file in "${UPGRADE_MIGRATIONS[@]}"; do
    apply_file "$container" "mysql" "$file"
  done
  echo "=== mysql8036-observed-live: repeat canonical 007-012 chain ==="
  for file in "${UPGRADE_MIGRATIONS[@]}"; do
    apply_file "$container" "mysql" "$file"
  done

  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('content_ops_run','content_ops_metric_daily','content_ops_action_log','feedback_rule_effectiveness_daily');" \
    "4" "missing legacy Content-Ops tables created"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM content_ops_run WHERE run_fingerprint=REPEAT('b',64) AND environment='observed-live' AND status='baseline';" \
    "1" "observed-live Content-Ops marker preserved"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='submissions' AND INDEX_NAME='idx_submissions_organizer_edited_at';" \
    "1" "missing organizer edit index reconciled"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key IN ('007_runtime_schema_reconciliation','008_startpartner_candidates','009_control_center_runtime_schema','010_startpartner_gate2_qualification_capacity','011_startpartner_gate3_terms_organizer_entitlement','012_startpartner_gate4_onboarding_content_activation');" \
    "6" "observed-live canonical migration keys recorded once"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('startpartner_candidates','startpartner_pilots','startpartner_pilot_onboarding_items','startpartner_pilot_content_links','startpartner_pilot_measurement_preflights','startpartner_pilot_distribution_commitments','startpartner_pilot_usages');" \
    "7" "observed-live required Startpartner owner tables present"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM control_cases WHERE id='00000000-0000-0000-0000-000000000001';" \
    "1" "pre-existing control case preserved"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM control_case_events WHERE case_id='00000000-0000-0000-0000-000000000001';" \
    "1" "pre-existing control event preserved"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM control_operations WHERE operation_id='release-preflight-op';" \
    "1" "pre-existing control operation preserved"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM control_editorial_feedback WHERE id='00000000-0000-0000-0000-000000000002';" \
    "1" "pre-existing editorial feedback preserved"
  assert_scalar "$container" "mysql" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('fk_control_case_events_case','fk_control_operations_case','fk_control_editorial_feedback_case');" \
    "3" "all Control Center relationships present after reconciliation"
  assert_scalar "$container" "mysql" \
    "SELECT (SELECT COUNT(*) FROM control_case_events e LEFT JOIN control_cases c ON c.id=e.case_id WHERE c.id IS NULL) + (SELECT COUNT(*) FROM control_operations o LEFT JOIN control_cases c ON c.id=o.case_id WHERE c.id IS NULL) + (SELECT COUNT(*) FROM control_editorial_feedback f LEFT JOIN control_cases c ON c.id=f.case_id WHERE c.id IS NULL);" \
    "0" "Control Center relationships remain orphan-free"

  echo "=== mysql8036-observed-live: exact observed Live cutover path OK ==="
  finish_engine "$container"
}

run_expected_baseline_engine "mysql8" "mysql:8.0" "mysql" "MYSQL_ROOT_PASSWORD" "MYSQL_DATABASE"
run_expected_baseline_engine "mariadb114" "mariadb:11.4" "mariadb" "MARIADB_ROOT_PASSWORD" "MARIADB_DATABASE"
run_observed_live_engine

echo "=== Release Preflight Main-to-Staging + Observed-Live Schema Contract: OK ==="
