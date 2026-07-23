#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
mode="${1:-}"
case "$mode" in real|fixture) ;; *) echo "Usage: $0 real|fixture" >&2; exit 2 ;; esac

tmp="$(mktemp -d)"
server_pid=""
cleanup() {
  [ -z "$server_pid" ] || kill "$server_pid" 2>/dev/null || true
  rm -rf "$tmp"
}
trap cleanup EXIT

tar -C "$ROOT" --exclude=.git --exclude=node_modules --exclude=artifacts -cf - . | tar -C "$tmp" -xf -

if [ "$mode" = real ] && [ ! -s "$tmp/data/events.json" ]; then
  echo "Evidence kind: REAL_REPOSITORY_FEED"
  echo "Evidence result: NOT_PROVEN (the PR checkout contains no generated data/events.json; no fixture was substituted)"
  if python3 - "$ROOT/docs/workpacks/active/acceptance-contract.json" <<'PY'
import json
import sys
scope = json.load(open(sys.argv[1], encoding="utf-8"))["scope_classes"]
raise SystemExit(0 if scope.get("ui") or scope.get("rendering") else 1)
PY
  then
    echo "UI/rendering scope requires real feed evidence." >&2
    exit 1
  fi
  exit 0
fi

if [ "$mode" = fixture ]; then
  python3 - "$tmp/data/events.json" <<'PY'
import json
import sys
from pathlib import Path
event = {
    "id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2099-09-27",
    "title": "Du wunderst mich – Die Kinderzaubershow mit Endrik Thier",
    "date": "2099-09-27", "time": "14:00", "location": "Bocholt",
    "city": "Bocholt", "category": "Familie",
    "description": "Deterministischer begrenzter Zusatztest.",
    "url": "https://yuki-magazin.de/veranstaltungen/du-wunderst-mich-die-kinderzaubershow-mit-endrik-thier/"
}
Path(sys.argv[1]).write_text(json.dumps([event], ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
PY
fi

(cd "$tmp" && SITE_ORIGIN=http://127.0.0.1:8765 python3 scripts/build-event-detail-pages.py)
python3 -m http.server 8765 --directory "$tmp" >"/tmp/bocholt-event-${mode}.log" 2>&1 &
server_pid=$!
for _ in {1..20}; do
  curl --fail --silent http://127.0.0.1:8765/events/ >/dev/null && break
  sleep 1
done
curl --fail --silent http://127.0.0.1:8765/events/ >/dev/null
echo "Evidence kind: $([ "$mode" = real ] && echo REAL_REPOSITORY_FEED || echo SYNTHETIC_BOUNDED_FIXTURE)"
node "$ROOT/scripts/browser-smoke.mjs" --base-url http://127.0.0.1:8765 --profile all --check event-navigation --out-dir "/tmp/browser-smoke-${mode}"
