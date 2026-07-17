#!/usr/bin/env bash
set -euo pipefail

branch="${1:-${GITHUB_REF_NAME:-}}"
env_file="${2:-${GITHUB_ENV:-}}"

if [[ -z "$branch" ]]; then
  echo "Deploy branch is missing; refusing to select an environment." >&2
  exit 64
fi

case "$branch" in
  main)
    target_dir="."
    environment="live"
    inbox_tab="Inbox"
    ;;
  staging)
    target_dir="staging"
    environment="staging"
    inbox_tab="Inbox_Staging"
    ;;
  *)
    echo "Deploy from branch '$branch' is not allowed. Only 'main' and 'staging' may deploy." >&2
    exit 78
    ;;
esac

emit() {
  printf 'DEPLOY_TARGET_DIR=%s\n' "$target_dir"
  printf 'DEPLOY_ENV_NAME=%s\n' "$environment"
  printf 'INBOX_TAB_NAME=%s\n' "$inbox_tab"
}

if [[ -n "$env_file" ]]; then
  emit >> "$env_file"
else
  emit
fi

printf 'Authorized deploy branch: %s\n' "$branch"
printf 'Deploy environment: %s\n' "$environment"
printf 'Deploy target directory: %s\n' "$target_dir"
printf 'Inbox tab: %s\n' "$inbox_tab"
