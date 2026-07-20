#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

section="${1:-all}"
PREFLIGHT_TEST="tests/control_center_runtime_preflight_contract_test.php"

validate_routing() {
  bash tests/test_deploy_branch_routing.sh
  for file in \
    data/control_center_repo_workpacks.json \
    data/control_center_editorial_contract.json \
    data/content_ops_decision_classes.json \
    data/event_visual_pool.json \
    tests/fixtures/control_center_editorial_cases.json; do
    python3 -m json.tool "$file" >/dev/null
  done
}

validate_php_syntax() {
  find api -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
  for file in tests/control_center*.php; do
    php -l "$file" >/dev/null
  done
}

validate_preflight() {
  php "$PREFLIGHT_TEST"
}

validate_php_tests() {
  for file in tests/control_center*.php; do
    if [ "$file" = "$PREFLIGHT_TEST" ]; then
      continue
    fi
    php "$file"
  done
}

validate_backend() {
  echo "== Backend and data contracts =="
  validate_routing
  validate_php_syntax
  validate_preflight
  validate_php_tests
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
  routing) validate_routing ;;
  php-syntax) validate_php_syntax ;;
  preflight) validate_preflight ;;
  php-tests) validate_php_tests ;;
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
