#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="${1:-}"
PARALLEL="${2:-1}"
DELETE_FILE="${3:-}"
ATTEMPTS="${STRATO_SFTP_PHASE_ATTEMPTS:-4}"
RETRY_BASE_SECONDS="${STRATO_SFTP_RETRY_BASE_SECONDS:-15}"
LFTP_BIN="${LFTP_BIN:-lftp}"

fail() {
  echo "SFTP phase error: $*" >&2
  exit 1
}

[ -n "$SOURCE_DIR" ] || fail "source directory argument is required"
[ -d "$SOURCE_DIR" ] || fail "source directory missing: $SOURCE_DIR"
[[ "$PARALLEL" =~ ^[1-9][0-9]*$ ]] || fail "parallel must be a positive integer"
[[ "$ATTEMPTS" =~ ^[1-9][0-9]*$ ]] || fail "STRATO_SFTP_PHASE_ATTEMPTS must be a positive integer"
[[ "$RETRY_BASE_SECONDS" =~ ^[0-9]+$ ]] || fail "STRATO_SFTP_RETRY_BASE_SECONDS must be a non-negative integer"
[ -n "${STRATO_FTP_SERVER:-}" ] || fail "STRATO_FTP_SERVER is required"
[ -n "${STRATO_FTP_USERNAME:-}" ] || fail "STRATO_FTP_USERNAME is required"
[ -n "${STRATO_FTP_PASSWORD:-}" ] || fail "STRATO_FTP_PASSWORD is required"
[ -n "${DEPLOY_TARGET_DIR:-}" ] || fail "DEPLOY_TARGET_DIR is required"
command -v "$LFTP_BIN" >/dev/null 2>&1 || fail "lftp executable not found: $LFTP_BIN"

DELETE_COMMANDS=""
if [ -n "$DELETE_FILE" ]; then
  [ -f "$DELETE_FILE" ] || fail "delete command file missing: $DELETE_FILE"
  DELETE_COMMANDS="$(cat "$DELETE_FILE")"
fi

for attempt in $(seq 1 "$ATTEMPTS"); do
  echo "SFTP phase ${SOURCE_DIR}: attempt ${attempt}/${ATTEMPTS}"

  set +e
  "$LFTP_BIN" \
    -u "$STRATO_FTP_USERNAME","$STRATO_FTP_PASSWORD" \
    "sftp://$STRATO_FTP_SERVER" <<LFTP
set sftp:auto-confirm yes
set sftp:connect-program "ssh -a -x -4"
set cmd:fail-exit yes
set net:timeout 20
set net:max-retries 2
set net:reconnect-interval-base 2
set net:reconnect-interval-max 10
cd $DEPLOY_TARGET_DIR
mirror -R --parallel=$PARALLEL --verbose $SOURCE_DIR/ .
$DELETE_COMMANDS
bye
LFTP
  status=$?
  set -e

  if [ "$status" -eq 0 ]; then
    echo "SFTP phase ${SOURCE_DIR}: completed on attempt ${attempt}/${ATTEMPTS}"
    exit 0
  fi

  if [ "$attempt" -ge "$ATTEMPTS" ]; then
    echo "SFTP phase ${SOURCE_DIR}: failed after ${ATTEMPTS} attempts (last exit ${status})" >&2
    exit "$status"
  fi

  wait_seconds=$((RETRY_BASE_SECONDS * attempt))
  echo "SFTP phase ${SOURCE_DIR}: attempt ${attempt} failed (exit ${status}); retrying in ${wait_seconds}s" >&2
  sleep "$wait_seconds"
done

fail "unreachable retry state"
