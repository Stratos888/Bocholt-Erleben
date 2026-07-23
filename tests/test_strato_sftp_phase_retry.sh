#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNNER="$ROOT/scripts/run_strato_sftp_phase.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/payload" "$TMP/control"
printf 'payload\n' > "$TMP/payload/file.txt"
printf 'rm -f "obsolete.txt"\n' > "$TMP/delete.lftp"

FAKE_LFTP="$TMP/fake-lftp"
cat > "$FAKE_LFTP" <<'FAKE'
#!/usr/bin/env bash
set -euo pipefail
count=0
if [ -f "$FAKE_LFTP_COUNT_FILE" ]; then
  count="$(cat "$FAKE_LFTP_COUNT_FILE")"
fi
count=$((count + 1))
printf '%s\n' "$count" > "$FAKE_LFTP_COUNT_FILE"
printf '%s\n' "$*" > "$FAKE_LFTP_ARGS_DIR/args-$count.txt"
cat > "$FAKE_LFTP_ARGS_DIR/stdin-$count.txt"
if [ "$count" -le "${FAKE_LFTP_FAIL_UNTIL:-0}" ]; then
  exit 7
fi
FAKE
chmod +x "$FAKE_LFTP"

run_env=(
  "STRATO_FTP_SERVER=sftp.example.invalid"
  "STRATO_FTP_USERNAME=test-user"
  "STRATO_FTP_PASSWORD=test-password"
  "DEPLOY_TARGET_DIR=target"
  "STRATO_SFTP_RETRY_BASE_SECONDS=0"
  "STRATO_SFTP_MAX_PARALLEL=2"
  "RUNNER_TEMP=$TMP/control"
  "LFTP_BIN=$FAKE_LFTP"
  "FAKE_LFTP_ARGS_DIR=$TMP"
)

# A transient connection failure must be retried in a new lftp process.
printf '0\n' > "$TMP/count-success"
env \
  "${run_env[@]}" \
  "STRATO_SFTP_PHASE_ATTEMPTS=4" \
  "FAKE_LFTP_COUNT_FILE=$TMP/count-success" \
  "FAKE_LFTP_FAIL_UNTIL=2" \
  bash "$RUNNER" "$TMP/payload" 8 "$TMP/delete.lftp"

[ "$(cat "$TMP/count-success")" = "3" ]
grep -Fq 'sftp://sftp.example.invalid' "$TMP/args-3.txt"
grep -Fq "set sftp:connect-program \"ssh -a -x -4 -o ControlMaster=auto -o ControlPersist=600 -o ControlPath=$TMP/control/be-strato-sftp-%C\"" "$TMP/stdin-3.txt"
grep -Fq "mirror -R --parallel=2 --verbose $TMP/payload/ ." "$TMP/stdin-3.txt"
grep -Fq 'rm -f "obsolete.txt"' "$TMP/stdin-3.txt"

# Persistent failures must stop exactly at the configured attempt budget.
rm -f "$TMP"/args-*.txt "$TMP"/stdin-*.txt
printf '0\n' > "$TMP/count-failure"
if env \
  "${run_env[@]}" \
  "STRATO_SFTP_PHASE_ATTEMPTS=3" \
  "FAKE_LFTP_COUNT_FILE=$TMP/count-failure" \
  "FAKE_LFTP_FAIL_UNTIL=9" \
  bash "$RUNNER" "$TMP/payload" 1; then
  echo "Expected persistent SFTP failure" >&2
  exit 1
fi
[ "$(cat "$TMP/count-failure")" = "3" ]

# Missing credentials must fail before lftp is started.
printf '0\n' > "$TMP/count-preflight"
if env \
  "STRATO_FTP_SERVER=sftp.example.invalid" \
  "STRATO_FTP_USERNAME=test-user" \
  "DEPLOY_TARGET_DIR=target" \
  "RUNNER_TEMP=$TMP/control" \
  "LFTP_BIN=$FAKE_LFTP" \
  "FAKE_LFTP_ARGS_DIR=$TMP" \
  "FAKE_LFTP_COUNT_FILE=$TMP/count-preflight" \
  bash "$RUNNER" "$TMP/payload" 1; then
  echo "Expected missing-password preflight failure" >&2
  exit 1
fi
[ "$(cat "$TMP/count-preflight")" = "0" ]

echo "STRATO SFTP phase retry and reuse contract: OK"
