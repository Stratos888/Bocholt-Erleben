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

run_engine() {
  local label="$1"
  local image="$2"
  local client="$3"
  local password_var="$4"
  local database_var="$5"
  local container="be-release-preflight-${label}-${GITHUB_RUN_ID:-local}-$$"

  cleanup_containers+=("$container")
  echo "=== $label: start temporary database ==="
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

  echo "=== $label: reproduce current main/live schema lineage ==="
  for file in "${BASELINE_MIGRATIONS[@]}"; do
    apply_file "$container" "$client" "$file"
  done

  # Durable marker proving a pre-existing Content-Ops row survives the upgrade.
  docker exec "$container" "$client" -uroot "-p$DB_PASSWORD" "$DB_NAME" -e \
    "INSERT INTO content_ops_run (run_fingerprint, generated_at_utc, environment, status) VALUES (REPEAT('a',64), '2026-08-18 00:00:00', 'live-preflight', 'baseline');" >/dev/null

  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='submissions' AND COLUMN_NAME IN ('activity_opening_json','activity_image_json');" \
    "2" "legacy activity columns before upgrade"
  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM content_ops_run WHERE run_fingerprint=REPEAT('a',64);" \
    "1" "baseline Content-Ops row before upgrade"

  echo "=== $label: apply staging reconciliation/startpartner chain ==="
  for file in "${UPGRADE_MIGRATIONS[@]}"; do
    apply_file "$container" "$client" "$file"
  done

  echo "=== $label: repeat staging chain to prove idempotency ==="
  for file in "${UPGRADE_MIGRATIONS[@]}"; do
    apply_file "$container" "$client" "$file"
  done

  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='submissions' AND COLUMN_NAME IN ('activity_opening_json','activity_image_json');" \
    "2" "legacy activity columns after upgrade"
  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM content_ops_run WHERE run_fingerprint=REPEAT('a',64) AND environment='live-preflight' AND status='baseline';" \
    "1" "pre-existing Content-Ops row preserved"
  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key IN ('007_runtime_schema_reconciliation','008_startpartner_candidates','009_control_center_runtime_schema','010_startpartner_gate2_qualification_capacity','011_startpartner_gate3_terms_organizer_entitlement','012_startpartner_gate4_onboarding_content_activation');" \
    "6" "all staging migration keys recorded once"
  assert_scalar "$container" "$client" \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('startpartner_candidates','startpartner_pilots','startpartner_pilot_onboarding_items','startpartner_pilot_content_links','startpartner_pilot_measurement_preflights','startpartner_pilot_distribution_commitments','startpartner_pilot_usages');" \
    "7" "required Startpartner owner tables present"

  echo "=== $label: main-to-staging schema upgrade OK ==="
  docker rm -f "$container" >/dev/null
  cleanup_containers=("${cleanup_containers[@]/$container}")
}

run_engine "mysql8" "mysql:8.0" "mysql" "MYSQL_ROOT_PASSWORD" "MYSQL_DATABASE"
run_engine "mariadb114" "mariadb:11.4" "mariadb" "MARIADB_ROOT_PASSWORD" "MARIADB_DATABASE"

echo "=== Release Preflight Main-to-Staging Schema Contract: OK ==="
