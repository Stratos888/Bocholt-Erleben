#!/usr/bin/env bash
set -euo pipefail
if [ "$#" -ne 0 ]; then echo "Usage: $0" >&2; exit 2; fi
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_PARENT="${RUNNER_TEMP:-/tmp}"
TMP="$(mktemp -d "$TMP_PARENT/bocholt-control-center-mobile.XXXXXX")"
SMOKE_OUT_DIR="${SMOKE_OUT_DIR:-$TMP_PARENT/browser-smoke-control-center-mobile}"
snapshot_checkout(){ tar -C "$ROOT" --sort=name --mtime='UTC 1970-01-01' --owner=0 --group=0 --numeric-owner --exclude=.git --exclude=node_modules --exclude=artifacts -cf - . | sha256sum | awk '{print $1}'; }
cleanup(){ if [ -f "$TMP/server.pid" ]; then kill "$(cat "$TMP/server.pid")" 2>/dev/null || true; wait "$(cat "$TMP/server.pid")" 2>/dev/null || true; fi; rm -rf "$TMP"; }
before_snapshot="$(snapshot_checkout)"; trap cleanup EXIT
port="$(python3 - <<'PY'
import socket
with socket.socket() as sock:
    sock.bind(('127.0.0.1',0))
    print(sock.getsockname()[1])
PY
)"
python3 -m http.server "$port" --bind 127.0.0.1 --directory "$ROOT" >"$TMP/http-server.log" 2>&1 & echo "$!" > "$TMP/server.pid"
for _ in {1..30}; do curl --fail --silent "http://127.0.0.1:$port/tests/fixtures/control_center_mobile_exception_review.html" >/dev/null && break; sleep 1; done
curl --fail --silent "http://127.0.0.1:$port/tests/fixtures/control_center_mobile_exception_review.html" >/dev/null || { cat "$TMP/http-server.log" >&2; exit 1; }
rm -rf "$SMOKE_OUT_DIR"; mkdir -p "$SMOKE_OUT_DIR"
node "$ROOT/tests/control_center_mobile_exception_review_browser_test.mjs" --base-url "http://127.0.0.1:$port" --out-dir "$SMOKE_OUT_DIR"
cleanup; trap - EXIT
after_snapshot="$(snapshot_checkout)"
if [ "$before_snapshot" != "$after_snapshot" ]; then echo "CONTROL_CENTER_MOBILE_EXCEPTION_REVIEW: checkout changed during smoke" >&2; exit 1; fi
echo "CONTROL_CENTER_MOBILE_EXCEPTION_REVIEW: OK"
