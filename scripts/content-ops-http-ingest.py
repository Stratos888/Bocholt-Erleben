#!/usr/bin/env python3
# === BEGIN FILE: scripts/content-ops-http-ingest.py | Zweck: uebertraegt lokale Content-Ops-Artefakte per HTTPS an den Strato-PHP-Ingest; Umfang: robuster optionaler Folge-Step ohne Blockade der fachlichen Workflows ===
from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any, Dict, Iterable, List, Tuple

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_INPUTS = [ROOT / "data" / "content-ops"]


def norm(value: Any) -> str:
    return str(value or "").strip()


def load_json(path: Path) -> Dict[str, Any] | None:
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
        return raw if isinstance(raw, dict) else None
    except Exception as exc:
        print(f"⚠️ Content-Ops JSON nicht lesbar: {path}: {type(exc).__name__}: {exc}", file=sys.stderr)
        return None


def iter_json_files(inputs: Iterable[Path]) -> Iterable[Path]:
    for item in inputs:
        if item.is_dir():
            yield from sorted(item.rglob("*.json"))
        elif item.is_file() and item.suffix.lower() == ".json":
            yield item


def collect_payloads(inputs: Iterable[Path]) -> List[Tuple[Path, Dict[str, Any]]]:
    seen: set[Tuple[str, str]] = set()
    payloads: List[Tuple[Path, Dict[str, Any]]] = []
    for path in iter_json_files(inputs):
        payload = load_json(path)
        if not payload:
            continue
        run_fp = norm(payload.get("run_fingerprint"))
        source_mode = norm(payload.get("source_mode"))
        if not run_fp or not source_mode:
            continue
        key = (run_fp, source_mode)
        if key in seen:
            continue
        seen.add(key)
        payloads.append((path, payload))
    return payloads


def post_payload(url: str, token: str, payload: Dict[str, Any], timeout: int) -> Dict[str, Any]:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Accept": "application/json",
            "Content-Type": "application/json; charset=utf-8",
            "Authorization": f"Bearer {token}",
            "X-BE-Content-Ops-Token": token,
            "User-Agent": "bocholt-content-ops-http-ingest/1.0",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            text = response.read().decode("utf-8", errors="replace")
            try:
                data = json.loads(text) if text else {}
            except Exception:
                data = {"raw_response": text[:1000]}
            return {"ok": 200 <= response.status < 300, "status_code": response.status, "response": data}
    except urllib.error.HTTPError as exc:
        text = exc.read().decode("utf-8", errors="replace") if exc.fp else ""
        try:
            data = json.loads(text) if text else {}
        except Exception:
            data = {"raw_response": text[:1000]}
        return {"ok": False, "status_code": exc.code, "response": data}
    except Exception as exc:
        return {"ok": False, "status_code": 0, "response": {"error_class": type(exc).__name__, "error_message": str(exc)}}


def write_step_summary(status: str, rows: List[Dict[str, Any]]) -> None:
    lines = [
        "## Content Ops HTTP Ingest",
        "",
        f"- http_ingest: `{status}`",
        f"- payloads: `{len(rows)}`",
        "",
    ]
    if rows:
        lines.extend(["| source_mode | run_fingerprint | result |", "|---|---|---|"])
        for row in rows[:20]:
            lines.append(
                f"| `{norm(row.get('source_mode'))}` | `{norm(row.get('run_fingerprint'))[:12]}` | `{norm(row.get('result'))}` |"
            )
    text = "\n".join(lines) + "\n"
    summary_path = norm(os.environ.get("GITHUB_STEP_SUMMARY"))
    if summary_path:
        with open(summary_path, "a", encoding="utf-8") as handle:
            handle.write(text)
    print(text)


def main() -> None:
    parser = argparse.ArgumentParser(description="Send Content Ops artifacts to the Strato PHP ingest endpoint.")
    parser.add_argument("--input", action="append", default=[], help="JSON file or directory. May be supplied multiple times.")
    parser.add_argument("--url", default=norm(os.environ.get("CONTENT_OPS_INGEST_URL")))
    parser.add_argument("--token", default=norm(os.environ.get("CONTENT_OPS_INGEST_TOKEN")))
    parser.add_argument("--timeout", type=int, default=int(norm(os.environ.get("CONTENT_OPS_INGEST_TIMEOUT")) or "25"))
    parser.add_argument("--fail-on-error", action="store_true")
    args = parser.parse_args()

    input_paths = [Path(value) for value in args.input] if args.input else DEFAULT_INPUTS
    input_paths = [path if path.is_absolute() else ROOT / path for path in input_paths]

    if not args.url or not args.token:
        write_step_summary("skipped_missing_ingest_config", [])
        return

    payloads = collect_payloads(input_paths)
    if not payloads:
        write_step_summary("skipped_no_payloads", [])
        return

    rows: List[Dict[str, Any]] = []
    errors = 0
    for path, payload in payloads:
        result = post_payload(args.url, args.token, payload, args.timeout)
        ok = bool(result.get("ok"))
        if not ok:
            errors += 1
        row = {
            "path": str(path.relative_to(ROOT) if path.is_relative_to(ROOT) else path),
            "source_mode": payload.get("source_mode"),
            "run_fingerprint": payload.get("run_fingerprint"),
            "result": "persisted_http_ingest" if ok else f"failed_http_ingest:{result.get('status_code')}",
            "response": result.get("response"),
        }
        rows.append(row)
        print(json.dumps(row, ensure_ascii=False, sort_keys=True))

    status = "persisted_http_ingest" if errors == 0 else f"partial_http_ingest_errors:{errors}"
    write_step_summary(status, rows)
    if errors and args.fail_on_error:
        raise SystemExit(status)


if __name__ == "__main__":
    main()
# === END FILE: scripts/content-ops-http-ingest.py ===
