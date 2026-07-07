#!/usr/bin/env python3
# === BEGIN FILE: scripts/audit-content-ops-run-health.py | Zweck: prueft Content-Ops Run-Health-Ziele, lokale Artefaktfrische und Guard-Vertrag ===
from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
TARGETS_PATH = ROOT / "data" / "content_ops_run_health_targets.json"
DEFAULT_INPUT_DIR = ROOT / "data" / "content-ops"
REPORT_PATH = ROOT / "data" / "content-ops-run-health-report.json"


def load_json(path: Path, default: Any = None) -> Any:
    try:
        if not path.exists():
            return default
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        return {"_error": f"{type(exc).__name__}: {exc}"}


def parse_dt(value: Any) -> datetime | None:
    raw = str(value or "").strip()
    if not raw:
        return None
    try:
        return datetime.fromisoformat(raw.replace("Z", "+00:00")).astimezone(timezone.utc)
    except Exception:
        return None


def age_hours(value: Any, now: datetime) -> float | None:
    parsed = parse_dt(value)
    if not parsed:
        return None
    return round(max(0.0, (now - parsed).total_seconds() / 3600.0), 2)


def classify(target: dict[str, Any], payload: dict[str, Any] | None, now: datetime) -> dict[str, Any]:
    source = str(target.get("source_mode") or "").strip()
    required = bool(target.get("required"))
    warn_after = float(target.get("warn_after_hours") or 0)
    stale_after = float(target.get("stale_after_hours") or 0)

    if not payload:
        return {
            "source_mode": source,
            "label": target.get("label") or source,
            "status": "missing_required" if required else "missing_optional",
            "severity": "error" if required else "info",
            "action_required": required,
            "age_hours": None,
            "warn_after_hours": warn_after,
            "stale_after_hours": stale_after,
            "message": "Pflichtlauf fehlt." if required else "Optionaler ereignisgetriebener Lauf wurde lokal nicht beobachtet.",
        }

    generated = payload.get("generated_at_utc")
    age = age_hours(generated, now)
    run_status = str(payload.get("status") or "").strip()
    if age is None:
        return {
            "source_mode": source,
            "label": target.get("label") or source,
            "status": "invalid_timestamp",
            "severity": "error" if required else "warning",
            "action_required": required,
            "age_hours": None,
            "generated_at_utc": generated,
            "run_status": run_status,
            "message": "Run-Artefakt hat keinen gueltigen Zeitstempel.",
        }

    if stale_after and age >= stale_after:
        health = "stale"
        severity = "error" if required else "warning"
        action = required
    elif warn_after and age >= warn_after:
        health = "late"
        severity = "warning"
        action = False
    elif run_status in {"source_artifact_missing", "missing_source", "failed"} or run_status.startswith("skipped_sql_error"):
        health = "degraded"
        severity = "warning"
        action = False
    else:
        health = "ok"
        severity = "ok"
        action = False

    return {
        "source_mode": source,
        "label": target.get("label") or source,
        "status": health,
        "severity": severity,
        "action_required": action,
        "age_hours": age,
        "generated_at_utc": generated,
        "run_status": run_status,
        "warn_after_hours": warn_after,
        "stale_after_hours": stale_after,
        "github_run_url": payload.get("github_run_url", ""),
        "run_fingerprint": payload.get("run_fingerprint", ""),
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Bocholt erleben Content-Ops Run-Health Audit")
    parser.add_argument("--input-dir", default=str(DEFAULT_INPUT_DIR.relative_to(ROOT)))
    parser.add_argument("--strict", action="store_true", help="Exit nonzero bei runtime health errors.")
    args = parser.parse_args()

    checks: list[dict[str, Any]] = []
    targets_payload = load_json(TARGETS_PATH, {})
    targets = targets_payload.get("targets") if isinstance(targets_payload, dict) else None

    if not isinstance(targets, list) or not targets:
        checks.append({"id": "targets_config", "status": "fail", "message": "Run-Health-Targets fehlen oder sind ungueltig."})
        targets = []
    else:
        checks.append({"id": "targets_config", "status": "ok", "message": "Run-Health-Targets vorhanden."})

    required_sources = {"content_quality_audit", "weekly_ki_websearch", "growth_intelligence", "inbox_cleanup"}
    configured_sources = {str(item.get("source_mode") or "") for item in targets if isinstance(item, dict)}
    missing_sources = sorted(required_sources - configured_sources)
    checks.append({
        "id": "required_sources",
        "status": "ok" if not missing_sources else "fail",
        "message": "Pflichtquellen konfiguriert." if not missing_sources else "Pflichtquellen fehlen.",
        "missing": missing_sources,
    })

    threshold_errors = []
    for item in targets:
        if not isinstance(item, dict):
            threshold_errors.append("target_not_object")
            continue
        source = str(item.get("source_mode") or "")
        warn = float(item.get("warn_after_hours") or 0)
        stale = float(item.get("stale_after_hours") or 0)
        if not source or warn <= 0 or stale <= warn:
            threshold_errors.append(source or "unknown")
    checks.append({
        "id": "thresholds",
        "status": "ok" if not threshold_errors else "fail",
        "message": "Frische-Schwellen plausibel." if not threshold_errors else "Frische-Schwellen unplausibel.",
        "invalid": threshold_errors,
    })

    input_dir = ROOT / args.input_dir
    payload_by_source: dict[str, dict[str, Any]] = {}
    if input_dir.exists():
        for path in sorted(input_dir.glob("*-latest.json")):
            payload = load_json(path, {})
            if not isinstance(payload, dict) or payload.get("_error"):
                continue
            source = str(payload.get("source_mode") or "").strip()
            if source:
                payload_by_source[source] = payload

    now = datetime.now(timezone.utc)
    health_items = [classify(item, payload_by_source.get(str(item.get("source_mode") or "")), now) for item in targets if isinstance(item, dict)]

    errors = [item for item in health_items if item.get("severity") == "error"]
    warnings = [item for item in health_items if item.get("severity") == "warning"]

    static_failures = [item for item in checks if item.get("status") == "fail"]
    status = "fail" if static_failures or (args.strict and errors) else "pass"
    report = {
        "status": status,
        "generated_at_utc": now.isoformat().replace("+00:00", "Z"),
        "observed_artifacts": len(payload_by_source),
        "summary": {
            "checks_total": len(checks),
            "checks_ok": sum(1 for item in checks if item.get("status") == "ok"),
            "checks_fail": len(static_failures),
            "health_total": len(health_items),
            "health_errors": len(errors),
            "health_warnings": len(warnings),
        },
        "checks": checks,
        "health": health_items,
    }

    REPORT_PATH.parent.mkdir(parents=True, exist_ok=True)
    REPORT_PATH.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"content_ops_run_health={status}")
    print(json.dumps(report["summary"], ensure_ascii=False))
    for item in static_failures:
        print(f"FAIL {item['id']}: {item['message']}")
    if args.strict:
        for item in errors:
            print(f"ERROR {item['source_mode']}: {item['status']}")
    if status == "fail":
        sys.exit(1)


if __name__ == "__main__":
    main()
# === END FILE: scripts/audit-content-ops-run-health.py ===
