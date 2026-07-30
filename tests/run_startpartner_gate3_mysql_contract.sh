#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

command -v docker >/dev/null 2>&1 || { printf '%s\n' 'Docker is required for the Startpartner Gate-3 database contract.' >&2; exit 2; }
command -v php >/dev/null 2>&1 || { printf '%s\n' 'PHP is required for the Startpartner Gate-3 database contract.' >&2; exit 2; }

if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'; then
  if command -v sudo >/dev/null 2>&1; then
    sudo apt-get update -qq
    sudo apt-get install -y -qq php-mysql
  else
    printf '%s\n' 'pdo_mysql is required and could not be installed.' >&2
    exit 2
  fi
fi

python3 -m json.tool api/sql/000_manifest.json >/dev/null
python3 - <<'PY'
import json
from pathlib import Path
manifest = json.loads(Path('api/sql/000_manifest.json').read_text(encoding='utf-8'))
expected = [f'{number:03d}' for number in range(1, 12)]
actual = [entry['key'][:3] for entry in manifest['migrations']]
if actual[:len(expected)] != expected:
    raise SystemExit(f'Unexpected Gate-3 migration prefix: {actual}')
for entry in manifest['migrations']:
    path = Path('api/sql') / entry['file']
    if not path.is_file():
        raise SystemExit(f'Manifest migration missing: {path}')
PY

CONTAINERS=()
cleanup_all() {
  local container
  for container in "${CONTAINERS[@]:-}"; do
    docker rm -f "$container" >/dev/null 2>&1 || true
  done
}
trap cleanup_all EXIT

run_engine() {
  local engine="$1"
  local image="$2"
  local client="$3"
  local container="be-startpartner-gate3-${engine}-$RANDOM-$RANDOM"
  CONTAINERS+=("$container")

  if [ "$engine" = "mysql8" ]; then
    docker run -d --name "$container" \
      -e MYSQL_ROOT_PASSWORD=contract-root \
      -e MYSQL_DATABASE=be_contract \
      -p 127.0.0.1::3306 \
      "$image" >/dev/null
  else
    docker run -d --name "$container" \
      -e MARIADB_ROOT_PASSWORD=contract-root \
      -e MARIADB_DATABASE=be_contract \
      -p 127.0.0.1::3306 \
      "$image" >/dev/null
  fi

  ready_check() {
    docker exec "$container" "$client" --protocol=TCP -h127.0.0.1 \
      -uroot -pcontract-root -Nse 'SELECT 1' be_contract >/dev/null 2>&1
  }

  for attempt in {1..90}; do
    if ready_check; then
      sleep 2
      if ready_check; then
        break
      fi
    fi
    if [ "$attempt" -eq 90 ]; then
      printf '%s\n' "${engine} contract container did not become stably ready." >&2
      docker logs "$container" >&2 || true
      return 1
    fi
    sleep 1
  done

  apply_sql() {
    local file="$1"
    printf '[%s] Applying %s\n' "$engine" "$file"
    docker exec -i "$container" "$client" --protocol=TCP -h127.0.0.1 \
      -uroot -pcontract-root be_contract < "$file"
  }

  for file in \
    api/sql/001_publish_funnel_core.sql \
    api/sql/002_organizer_portal_core.sql \
    api/sql/003_submission_intake_origin_location_review.sql \
    api/sql/004_submission_organizer_edit_tracking.sql \
    api/sql/005_inbox_push_notifications.sql \
    api/sql/006_single_event_review_before_payment.sql \
    api/sql/007_runtime_schema_reconciliation.sql \
    api/sql/008_startpartner_candidates.sql \
    api/sql/009_control_center_runtime_schema.sql \
    api/sql/010_startpartner_gate2_qualification_capacity.sql \
    api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql; do
    apply_sql "$file"
  done

  for file in \
    api/sql/007_runtime_schema_reconciliation.sql \
    api/sql/008_startpartner_candidates.sql \
    api/sql/009_control_center_runtime_schema.sql \
    api/sql/010_startpartner_gate2_qualification_capacity.sql \
    api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql; do
    apply_sql "$file"
  done

  local port
  port="$(docker inspect -f '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}' "$container")"
  for contract in \
    tests/startpartner_gate3_schema_contract_test.php \
    tests/startpartner_gate3_mysql_contract_test.php; do
    printf '[%s] Running %s\n' "$engine" "$contract"
    STARTPARTNER_TEST_DSN="mysql:host=127.0.0.1;port=${port};dbname=be_contract;charset=utf8mb4" \
    STARTPARTNER_TEST_USER='root' \
    STARTPARTNER_TEST_PASSWORD='contract-root' \
      php "$contract"
  done

  docker rm -f "$container" >/dev/null
}

run_engine mysql8 mysql:8.0 mysql
run_engine mariadb114 mariadb:11.4 mariadb

printf '%s\n' '=== Startpartner Gate-3 MySQL 8 and MariaDB 11.4 Contracts: OK ==='
