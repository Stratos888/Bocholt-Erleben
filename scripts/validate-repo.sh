#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

section="${1:-all}"

validate_backend() {
  echo "== Branch, data and backend contracts =="
  bash tests/test_deploy_branch_routing.sh

  for file in \
    data/control_center_repo_workpacks.json \
    data/control_center_editorial_contract.json \
    data/content_ops_decision_classes.json \
    data/event_visual_pool.json \
    tests/fixtures/control_center_editorial_cases.json; do
    python3 -m json.tool "$file" >/dev/null
  done

  find api -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
  for file in tests/control_center*.php; do
    echo "Running $file"
    php -l "$file" >/dev/null
    php "$file"
  done
}

validate_frontend() {
  echo "== Frontend contracts =="
  node --check js/control-center-environment.js
  node --check js/control-center.js
  node --check js/control-center-seo-embed.js
  for file in js/control-center/*.js; do
    node --input-type=module --check < "$file"
  done
  node tests/control_center_frontend_contract_test.mjs
}

validate_repository() {
  echo "== Repository tools and generators =="
  python3 -m compileall -q scripts tools
  python3 scripts/audit_control_center_product_contract.py
  python3 scripts/audit_control_center_editorial_contracts.py
  python3 tools/audit-css-governance.py
  python3 tests/test_event_visual_gap_backlog.py
  python3 tests/test_events_overlay_merge.py
}

case "$section" in
  backend) validate_backend ;;
  frontend) validate_frontend ;;
  repository) validate_repository ;;
  all)
    validate_backend
    validate_frontend
    validate_repository
    ;;
  *)
    echo "Unknown validation section: $section" >&2
    exit 2
    ;;
esac

echo "Repository validation ($section): OK"
