#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESOLVER="$ROOT/scripts/resolve-deploy-target.sh"
WORKFLOW="$ROOT/.github/workflows/deploy-strato.yml"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

assert_line() {
  local file="$1"
  local expected="$2"
  grep -Fxq "$expected" "$file" || fail "Missing line '$expected' in $file"
}

run_allowed_case() {
  local branch="$1"
  local expected_dir="$2"
  local expected_env="$3"
  local expected_inbox="$4"
  local expected_events="$5"
  local output
  output="$(mktemp)"
  trap 'rm -f "$output"' RETURN

  bash "$RESOLVER" "$branch" "$output" >/dev/null
  assert_line "$output" "DEPLOY_TARGET_DIR=$expected_dir"
  assert_line "$output" "DEPLOY_ENV_NAME=$expected_env"
  assert_line "$output" "INBOX_TAB_NAME=$expected_inbox"
  assert_line "$output" "EVENTS_TAB_NAME=$expected_events"

  rm -f "$output"
  trap - RETURN
}

run_blocked_case() {
  local branch="$1"
  local output
  output="$(mktemp)"
  trap 'rm -f "$output"' RETURN

  if bash "$RESOLVER" "$branch" "$output" >/dev/null 2>&1; then
    fail "Branch '$branch' was unexpectedly authorized"
  fi
  [[ ! -s "$output" ]] || fail "Blocked branch '$branch' wrote deploy environment values"

  rm -f "$output"
  trap - RETURN
}

run_allowed_case main . live Inbox Events
run_allowed_case staging staging staging Inbox_Staging Events_Staging
run_blocked_case agent/example-workpack
run_blocked_case feature/test
run_blocked_case ""

[[ -f "$WORKFLOW" ]] || fail "Deploy workflow is missing"

grep -q 'name: Authorize deploy branch before external access' "$WORKFLOW" \
  || fail "Deploy workflow does not contain the early authorization step"
grep -q 'bash scripts/resolve-deploy-target.sh "${GITHUB_REF_NAME}" "${GITHUB_ENV}"' "$WORKFLOW" \
  || fail "Deploy workflow does not call the canonical resolver"
grep -q 'EVENTS_TAB_NAME' "$WORKFLOW" \
  || fail "Deploy workflow does not consume the authorized Events target"
grep -q 'Events_Staging' "$WORKFLOW" \
  || fail "Deploy workflow does not recognize the isolated staging overlay"
grep -q 'merge_events_overlay' "$WORKFLOW" \
  || fail "Deploy workflow does not merge the staging Events overlay"

checkout_line="$(grep -n 'name: Checkout' "$WORKFLOW" | head -1 | cut -d: -f1)"
authorize_line="$(grep -n 'name: Authorize deploy branch before external access' "$WORKFLOW" | head -1 | cut -d: -f1)"
setup_python_line="$(grep -n 'name: Setup Python' "$WORKFLOW" | head -1 | cut -d: -f1)"
sheet_line="$(grep -n 'name: Resolve Google Sheet and Inbox tab target' "$WORKFLOW" | head -1 | cut -d: -f1)"

[[ -n "$checkout_line" && -n "$authorize_line" && -n "$setup_python_line" && -n "$sheet_line" ]] \
  || fail "Required deploy workflow steps could not be located"
(( checkout_line < authorize_line )) || fail "Authorization must run after checkout"
(( authorize_line < setup_python_line )) || fail "Authorization must run before Python setup"
(( authorize_line < sheet_line )) || fail "Authorization must run before Google Sheet access"

if grep -q 'if \[ "${GITHUB_REF_NAME}" = "staging" \].*' "$WORKFLOW"; then
  fail "Deploy workflow still contains the legacy staging/else-live branch decision"
fi

python3 "$ROOT/tests/test_events_overlay_merge.py"

echo "Deploy branch routing guardrails: OK"
