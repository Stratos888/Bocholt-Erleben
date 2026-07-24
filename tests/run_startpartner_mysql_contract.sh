#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

command -v docker >/dev/null 2>&1 || { echo "Docker is required for the Startpartner MySQL contract." >&2; exit 2; }
command -v php >/dev/null 2>&1 || { echo "PHP is required for the Startpartner MySQL contract." >&2; exit 2; }

if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'; then
  if command -v sudo >/dev/null 2>&1; then
    sudo apt-get update -qq
    sudo apt-get install -y -qq php-mysql
  else
    echo "pdo_mysql is required and could not be installed." >&2
    exit 2
  fi
fi

CONTAINER="be-startpartner-mysql-$RANDOM-$RANDOM"
cleanup() {
  docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker run -d --name "$CONTAINER" \
  -e MARIADB_ROOT_PASSWORD=contract-root \
  -e MARIADB_DATABASE=be_contract \
  -p 127.0.0.1::3306 \
  mariadb:11.4 >/dev/null

for attempt in $(seq 1 60); do
  if docker exec "$CONTAINER" mariadb-admin ping -uroot -pcontract-root --silent >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 60 ]; then
    echo "MariaDB contract container did not become ready." >&2
    exit 1
  fi
  sleep 1
done

apply_sql() {
  local file="$1"
  echo "Applying $file"
  docker exec -i "$CONTAINER" mariadb -uroot -pcontract-root be_contract < "$file"
}

python3 -m json.tool api/sql/000_manifest.json >/dev/null
for file in \
  api/sql/001_publish_funnel_core.sql \
  api/sql/002_organizer_portal_core.sql \
  api/sql/003_submission_intake_origin_location_review.sql \
  api/sql/004_submission_organizer_edit_tracking.sql \
  api/sql/005_inbox_push_notifications.sql \
  api/sql/006_single_event_review_before_payment.sql \
  api/sql/007_runtime_schema_reconciliation.sql \
  api/sql/008_startpartner_candidates.sql \
  sql/009_control_center_cases.sql; do
  apply_sql "$file"
done

# Gate-1 migrations must be safe to re-run against the same installed schema.
apply_sql api/sql/007_runtime_schema_reconciliation.sql
apply_sql api/sql/008_startpartner_candidates.sql

PORT="$(docker inspect -f '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}' "$CONTAINER")"
export STARTPARTNER_TEST_DSN="mysql:host=127.0.0.1;port=${PORT};dbname=be_contract;charset=utf8mb4"
export STARTPARTNER_TEST_USER="root"
export STARTPARTNER_TEST_PASSWORD="contract-root"
php tests/startpartner_mysql_contract_test.php

echo "=== Startpartner MySQL Migration and Runtime Contract: OK ==="
