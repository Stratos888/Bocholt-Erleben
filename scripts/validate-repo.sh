#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== Branch and deploy routing =="
bash tests/test_deploy_branch_routing.sh

echo "== JSON contracts =="
for file in \
  data/control_center_repo_workpacks.json \
  data/control_center_editorial_contract.json \
  data/content_ops_decision_classes.json \
  data/event_visual_pool.json \
  tests/fixtures/control_center_editorial_cases.json; do
  python3 -m json.tool "$file" >/dev/null
done

echo "== PHP syntax and contracts =="
find api -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
for file in tests/control_center*.php; do
  php -l "$file" >/dev/null
  php "$file"
done

echo "== JavaScript syntax and frontend contract =="
node --check js/control-center-environment.js
node --check js/control-center.js
node --check js/control-center-seo-embed.js
for file in js/control-center/*.js; do
  node --input-type=module --check < "$file"
done
node tests/control_center_frontend_contract_test.mjs

echo "== Python and repository contracts =="
python3 -m compileall -q scripts tools
python3 scripts/audit_control_center_product_contract.py
python3 scripts/audit_control_center_editorial_contracts.py
python3 tools/audit-css-governance.py
python3 tests/test_event_visual_gap_backlog.py
python3 tests/test_events_overlay_merge.py

echo "Repository validation: OK"
