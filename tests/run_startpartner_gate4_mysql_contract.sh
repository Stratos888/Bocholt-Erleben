#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
command -v docker >/dev/null 2>&1 || { echo 'Docker is required for Gate-4 database contract.' >&2; exit 2; }
command -v php >/dev/null 2>&1 || { echo 'PHP is required for Gate-4 database contract.' >&2; exit 2; }
if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'; then
  if command -v sudo >/dev/null 2>&1; then sudo apt-get update -qq && sudo apt-get install -y -qq php-mysql; else echo 'pdo_mysql is required.' >&2; exit 2; fi
fi
python3 -m json.tool api/sql/000_manifest.json >/dev/null
python3 - <<'PY'
import json
from pathlib import Path
manifest=json.loads(Path('api/sql/000_manifest.json').read_text())
actual=[entry['key'][:3] for entry in manifest['migrations']]
expected=[f'{number:03d}' for number in range(1,13)]
if actual!=expected: raise SystemExit(f'Unexpected migration order: {actual}')
for entry in manifest['migrations']:
    path=Path('api/sql')/entry['file']
    if not path.is_file(): raise SystemExit(f'Manifest migration missing: {path}')
PY
CONTAINERS=()
cleanup(){ for c in "${CONTAINERS[@]:-}"; do docker rm -f "$c" >/dev/null 2>&1 || true; done; }
trap cleanup EXIT
run_engine(){
  local engine="$1" image="$2" client="$3" container="be-startpartner-gate4-${engine}-$RANDOM-$RANDOM"
  CONTAINERS+=("$container")
  if [ "$engine" = mysql8 ]; then
    docker run -d --name "$container" -e MYSQL_ROOT_PASSWORD=contract-root -e MYSQL_DATABASE=be_contract -p 127.0.0.1::3306 "$image" >/dev/null
  else
    docker run -d --name "$container" -e MARIADB_ROOT_PASSWORD=contract-root -e MARIADB_DATABASE=be_contract -p 127.0.0.1::3306 "$image" >/dev/null
  fi
  for attempt in {1..90}; do
    if docker exec "$container" "$client" --protocol=TCP -h127.0.0.1 -uroot -pcontract-root -Nse 'SELECT 1' be_contract >/dev/null 2>&1; then sleep 2; break; fi
    if [ "$attempt" -eq 90 ]; then docker logs "$container" >&2 || true; return 1; fi
    sleep 1
  done
  apply(){ docker exec -i "$container" "$client" --protocol=TCP -h127.0.0.1 -uroot -pcontract-root be_contract < "$1"; }
  for file in api/sql/001_publish_funnel_core.sql api/sql/002_organizer_portal_core.sql api/sql/003_submission_intake_origin_location_review.sql api/sql/004_submission_organizer_edit_tracking.sql api/sql/005_inbox_push_notifications.sql api/sql/006_single_event_review_before_payment.sql api/sql/007_runtime_schema_reconciliation.sql api/sql/008_startpartner_candidates.sql api/sql/009_control_center_runtime_schema.sql api/sql/010_startpartner_gate2_qualification_capacity.sql api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql api/sql/012_startpartner_gate4_onboarding_content_activation.sql; do apply "$file"; done
  for file in api/sql/007_runtime_schema_reconciliation.sql api/sql/008_startpartner_candidates.sql api/sql/009_control_center_runtime_schema.sql api/sql/010_startpartner_gate2_qualification_capacity.sql api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql api/sql/012_startpartner_gate4_onboarding_content_activation.sql; do apply "$file"; done
  local port
  port="$(docker inspect -f '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}' "$container")"
  STARTPARTNER_TEST_DSN="mysql:host=127.0.0.1;port=${port};dbname=be_contract;charset=utf8mb4" STARTPARTNER_TEST_USER=root STARTPARTNER_TEST_PASSWORD=contract-root php tests/startpartner_gate4_schema_contract_test.php
  docker rm -f "$container" >/dev/null
}
run_engine mysql8 mysql:8.0 mysql
run_engine mariadb114 mariadb:11.4 mariadb
echo '=== Startpartner Gate-4 MySQL 8 and MariaDB 11.4 Contracts: OK ==='
