#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

command -v docker >/dev/null 2>&1 || { printf '%s\n' 'Docker is required for the Startpartner Gate-2 MariaDB contract.' >&2; exit 2; }
command -v php >/dev/null 2>&1 || { printf '%s\n' 'PHP is required for the Startpartner Gate-2 MariaDB contract.' >&2; exit 2; }

if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'; then
  if command -v sudo >/dev/null 2>&1; then
    sudo apt-get update -qq
    sudo apt-get install -y -qq php-mysql
  else
    printf '%s\n' 'pdo_mysql is required and could not be installed.' >&2
    exit 2
  fi
fi

CONTAINER="be-startpartner-gate2-mariadb-$RANDOM-$RANDOM"
cleanup() {
  docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker run -d --name "$CONTAINER" \
  -e MARIADB_ROOT_PASSWORD=contract-root \
  -e MARIADB_DATABASE=be_contract \
  -p 127.0.0.1::3306 \
  mariadb:11.4 >/dev/null

ready_check() {
  docker exec "$CONTAINER" mariadb --protocol=TCP -h127.0.0.1 \
    -uroot -pcontract-root -Nse 'SELECT 1' be_contract >/dev/null 2>&1
}

for attempt in {1..60}; do
  if ready_check; then
    sleep 2
    if ready_check; then
      break
    fi
  fi
  if [ "$attempt" -eq 60 ]; then
    printf '%s\n' 'MariaDB contract container did not become stably ready with authenticated TCP access.' >&2
    docker logs "$CONTAINER" >&2 || true
    exit 1
  fi
  sleep 1
done

apply_sql() {
  local file="$1"
  printf 'Applying %s\n' "$file"
  docker exec -i "$CONTAINER" mariadb --protocol=TCP -h127.0.0.1 \
    -uroot -pcontract-root be_contract < "$file"
}

python3 -m json.tool api/sql/000_manifest.json >/dev/null
python3 - <<'PY'
import json
from pathlib import Path
manifest = json.loads(Path('api/sql/000_manifest.json').read_text(encoding='utf-8'))
expected_prefix = [f'{number:03d}' for number in range(1, 11)]
actual = [entry['key'][:3] for entry in manifest['migrations']]
if actual[:len(expected_prefix)] != expected_prefix:
    raise SystemExit(f'Unexpected Gate-2 migration prefix: {actual}')
if len(actual) != len(set(actual)):
    raise SystemExit(f'Duplicate migration numbers: {actual}')
for entry in manifest['migrations']:
    path = Path('api/sql') / entry['file']
    if not path.is_file():
        raise SystemExit(f'Manifest migration missing: {path}')
PY

for file in \
  api/sql/001_publish_funnel_core.sql \
  api/sql/002_organizer_portal_core.sql \
  api/sql/003_submission_intake_origin_location_review.sql \
  api/sql/004_submission_organizer_edit_tracking.sql \
  api/sql/005_inbox_push_notifications.sql \
  api/sql/006_single_event_review_before_payment.sql; do
  apply_sql "$file"
done

docker exec -i "$CONTAINER" mariadb --protocol=TCP -h127.0.0.1 \
  -uroot -pcontract-root be_contract <<'SQL'
INSERT INTO organizers (
  id, organization_name, contact_name, email, email_normalized,
  stripe_customer_id, default_plan_key
) VALUES (
  900001, 'GATE2_SCHEMA_SENTINEL', 'Schema Sentinel',
  'gate2-schema-sentinel@example.org', 'gate2-schema-sentinel@example.org',
  'cus_gate2_schema_sentinel', 'active'
);

INSERT INTO submissions (
  id, organizer_id, submission_kind, status, requested_model_key,
  payment_kind, payment_reference_key, organization_name_snapshot,
  contact_name_snapshot, email_snapshot, intake_origin,
  location_public_confirmed, title, notes_text
) VALUES (
  900001, 900001, 'event', 'draft', 'single',
  'single', '00000000-0000-0000-0000-000000900001',
  'GATE2_SCHEMA_SENTINEL', 'Schema Sentinel',
  'gate2-schema-sentinel@example.org', 'single_event',
  1, 'Sentinel event', 'Must remain unchanged'
);

INSERT INTO subscriptions (
  id, organizer_id, source_provider, stripe_subscription_id,
  stripe_customer_id, plan_key, status
) VALUES (
  900001, 900001, 'stripe', 'sub_gate2_schema_sentinel',
  'cus_gate2_schema_sentinel', 'active', 'active'
);

INSERT INTO publication_entitlements (
  id, organizer_id, source_type, source_reference, source_submission_id,
  subscription_id, plan_key, status, included_publications,
  consumed_publications, is_unlimited
) VALUES (
  900001, 900001, 'contract', 'gate2-schema-sentinel', 900001,
  900001, 'active', 'active', 3, 1, 0
);

INSERT INTO publication_consumptions (
  id, organizer_id, entitlement_id, submission_id, units, consumed_reason
) VALUES (
  900001, 900001, 900001, 900001, 1, 'approved_publication'
);
SQL

for file in \
  api/sql/007_runtime_schema_reconciliation.sql \
  api/sql/008_startpartner_candidates.sql \
  api/sql/009_control_center_runtime_schema.sql \
  api/sql/010_startpartner_gate2_qualification_capacity.sql; do
  apply_sql "$file"
done

# The complete Gate-2 idempotent owner chain must be safely repeatable.
for file in \
  api/sql/007_runtime_schema_reconciliation.sql \
  api/sql/008_startpartner_candidates.sql \
  api/sql/009_control_center_runtime_schema.sql \
  api/sql/010_startpartner_gate2_qualification_capacity.sql; do
  apply_sql "$file"
done

PORT="$(docker inspect -f '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}' "$CONTAINER")"
export STARTPARTNER_TEST_DSN="mysql:host=127.0.0.1;port=${PORT};dbname=be_contract;charset=utf8mb4"
export STARTPARTNER_TEST_USER='root'
export STARTPARTNER_TEST_PASSWORD='contract-root'
php tests/startpartner_gate2_mysql_contract_test.php
php tests/startpartner_gate2_runtime_mysql_contract_test.php

printf '%s\n' '=== Startpartner Gate-2 Fresh MariaDB and Runtime Contract: OK ==='
