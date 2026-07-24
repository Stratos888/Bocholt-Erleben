#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 0 ]; then
  echo "Usage: $0" >&2
  exit 2
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_PARENT="${RUNNER_TEMP:-/tmp}"
TMP="$(mktemp -d "$TMP_PARENT/bocholt-event-fixture.XXXXXX")"
SMOKE_OUT_DIR="${SMOKE_OUT_DIR:-$TMP_PARENT/browser-smoke-synthetic}"

snapshot_checkout() {
  tar -C "$ROOT" \
    --sort=name \
    --mtime='UTC 1970-01-01' \
    --owner=0 \
    --group=0 \
    --numeric-owner \
    --exclude=.git \
    --exclude=node_modules \
    --exclude=artifacts \
    -cf - . | sha256sum | awk '{print $1}'
}

cleanup() {
  if [ -f "$TMP/server.pid" ]; then
    server_pid="$(cat "$TMP/server.pid" 2>/dev/null || true)"
    if [ -n "$server_pid" ]; then
      kill "$server_pid" 2>/dev/null || true
      wait "$server_pid" 2>/dev/null || true
    fi
  fi
  rm -rf "$TMP"
}

before_snapshot="$(snapshot_checkout)"
trap cleanup EXIT

echo "Evidence kind: SYNTHETIC_BOUNDED_FIXTURE"
echo "Checkout snapshot before: $before_snapshot"

set +e
(
  set -euo pipefail

  tar -C "$ROOT" \
    --exclude=.git \
    --exclude=node_modules \
    --exclude=artifacts \
    -cf - . | tar -C "$TMP" -xf -

  mkdir -p "$TMP/data"
  python3 - "$TMP/data/events.json" <<'PY'
import json
import sys
from pathlib import Path

event = {
    "id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2099-09-27",
    "title": "Du wunderst mich – Die Kinderzaubershow mit Endrik Thier",
    "date": "2099-09-27",
    "time": "14:00",
    "location": "Bocholt",
    "city": "Bocholt",
    "category": "Familie",
    "description": "Deterministischer begrenzter PR-Gate-Zusatztest.",
    "url": "https://yuki-magazin.de/veranstaltungen/du-wunderst-mich-die-kinderzaubershow-mit-endrik-thier/",
}
Path(sys.argv[1]).write_text(
    json.dumps([event], ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)
PY

  port="$(python3 - <<'PY'
import socket
with socket.socket() as sock:
    sock.bind(("127.0.0.1", 0))
    print(sock.getsockname()[1])
PY
)"

  (
    cd "$TMP"
    SITE_ORIGIN="http://127.0.0.1:$port" python3 scripts/build-event-detail-pages.py
  )

  python3 -m http.server "$port" --bind 127.0.0.1 --directory "$TMP" \
    >"$TMP/http-server.log" 2>&1 &
  echo "$!" > "$TMP/server.pid"

  ready="false"
  for _ in {1..30}; do
    if curl --fail --silent "http://127.0.0.1:$port/events/" >/dev/null; then
      ready="true"
      break
    fi
    sleep 1
  done
  if [ "$ready" != "true" ]; then
    cat "$TMP/http-server.log" >&2 || true
    exit 1
  fi

  rm -rf "$SMOKE_OUT_DIR"
  mkdir -p "$SMOKE_OUT_DIR"
  node "$ROOT/scripts/browser-smoke.mjs" \
    --base-url "http://127.0.0.1:$port" \
    --profile all \
    --check event-navigation \
    --out-dir "$SMOKE_OUT_DIR"
)
smoke_status=$?
set -e

cleanup
trap - EXIT

after_snapshot="$(snapshot_checkout)"
echo "Checkout snapshot after:  $after_snapshot"

if [ "$before_snapshot" != "$after_snapshot" ]; then
  echo "SYNTHETIC_BOUNDED_FIXTURE: checkout changed during smoke" >&2
  exit 1
fi

if [ "$smoke_status" -ne 0 ]; then
  echo "SYNTHETIC_BOUNDED_FIXTURE: browser contract failed" >&2
  exit "$smoke_status"
fi

echo "SYNTHETIC_BOUNDED_FIXTURE: OK"
