#!/usr/bin/env python3
# === BEGIN FILE: scripts/audit-self-learning-semantics.py | Zweck: prueft den semantischen Zielvertrag fuer Entscheidungsklassen, Feedback-Lifecycle und Lernkreis-Fixtures ===
from __future__ import annotations

import json
import re
import sys
from datetime import date, datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DECISION_PATH = ROOT / "data" / "content_ops_decision_classes.json"
REPORT_PATH = ROOT / "data" / "self-learning-semantic-report.json"


def load_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))


def norm(value: Any) -> str:
    raw = str(value or "").strip().lower()
    raw = raw.translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    raw = re.sub(r"[^a-z0-9]+", "_", raw).strip("_")
    return raw


def parse_day(value: Any) -> date | None:
    raw = str(value or "").strip()[:10]
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except Exception:
        return None


def decision_class(row: dict[str, Any], contract: dict[str, Any]) -> str:
    classes = contract["decision_classes"]
    aliases = contract.get("status_aliases", {})
    explicit = norm(row.get("decision_class"))
    if explicit in classes:
        return explicit
    for field in ("status", "review_status", "action_state", "decision"):
        token = norm(row.get(field))
        if token in aliases:
            return aliases[token]
    return ""


def is_future_or_today(value: Any, today: date) -> bool:
    parsed = parse_day(value)
    return bool(parsed and parsed >= today)


def target_effect(row: dict[str, Any], contract: dict[str, Any], today: date) -> dict[str, Any]:
    cls = decision_class(row, contract)
    meta = contract["decision_classes"].get(cls, {})
    effect = {
        "decision_class": cls,
        "task_state": meta.get("task_state", "open"),
        "default_effect": meta.get("default_effect", "manual_review"),
        "suppress": bool(meta.get("suppresses_reopen", False)),
        "recheck": bool(meta.get("requires_recheck", False)),
        "watch_effect": bool(meta.get("requires_effect_watch", False)),
        "needs_task": meta.get("task_state") == "open",
    }
    if cls == "snoozed":
        effect["suppress"] = is_future_or_today(row.get("suppress_until") or row.get("recheck_at"), today)
        effect["recheck"] = True
    if cls == "watch":
        effect["suppress"] = False
        effect["needs_task"] = False
    return effect


def add(results: list[dict[str, Any]], check_id: str, area: str, ok: bool, message: str, details: dict[str, Any] | None = None) -> None:
    results.append({
        "id": check_id,
        "area": area,
        "status": "ok" if ok else "fail",
        "message": message,
        "details": details or {},
    })


def main() -> None:
    checks: list[dict[str, Any]] = []
    if not DECISION_PATH.exists():
        add(checks, "decision_contract_file", "taxonomy", False, "data/content_ops_decision_classes.json fehlt")
        contract = {"decision_classes": {}, "status_aliases": {}, "required_decision_fields": []}
    else:
        contract = load_json(DECISION_PATH)
        add(checks, "decision_contract_file", "taxonomy", True, "Entscheidungsklassen-Kontrakt vorhanden")

    required_classes = {
        "accepted", "confirmed", "corrected", "done", "duplicate",
        "rejected_not_event", "rejected_not_public", "rejected_not_local", "rejected_source_weak",
        "rejected_low_value", "rejected_commercial", "needs_patch", "needs_source",
        "needs_visual_fix", "snoozed", "watch",
    }
    classes = set((contract.get("decision_classes") or {}).keys())
    missing_classes = sorted(required_classes - classes)
    add(checks, "decision_classes_complete", "taxonomy", not missing_classes, "alle Ziel-Entscheidungsklassen vorhanden" if not missing_classes else "fehlende Entscheidungsklassen", {"missing": missing_classes})

    required_fields = {
        "decision_class", "decision_note", "resolved_by", "resolved_at", "suppress_until",
        "recheck_at", "reopen_policy", "expected_effect_window_days", "source_fingerprint", "content_fingerprint",
    }
    fields = set(contract.get("required_decision_fields") or [])
    missing_fields = sorted(required_fields - fields)
    add(checks, "decision_fields_complete", "taxonomy", not missing_fields, "alle Ziel-Entscheidungsfelder vorhanden" if not missing_fields else "fehlende Entscheidungsfelder", {"missing": missing_fields})

    today = date(2026, 7, 7)
    fixtures = [
        {
            "id": "search_rejected_not_public_filters_next_run",
            "area": "weekly_search",
            "row": {"decision_class": "rejected_not_public", "decision_note": "interner Termin"},
            "expect": {"suppress": True, "default_effect": "search_intake_filter", "needs_task": False},
        },
        {
            "id": "duplicate_prevents_repeat_work",
            "area": "dedupe",
            "row": {"decision_class": "duplicate", "decision_note": "bereits im Bestand"},
            "expect": {"suppress": True, "default_effect": "dedupe_suppression", "needs_task": False},
        },
        {
            "id": "confirmed_sets_cache_recheck",
            "area": "content_quality",
            "row": {"decision_class": "confirmed", "recheck_at": "2026-09-01"},
            "expect": {"suppress": True, "recheck": True, "default_effect": "verification_cache"},
        },
        {
            "id": "growth_irrelevant_suppresses_cluster",
            "area": "growth",
            "row": {"status": "irrelevant", "decision_note": "fuer Bocholt erleben nicht sinnvoll"},
            "expect": {"decision_class": "rejected_low_value", "suppress": True, "needs_task": False},
        },
        {
            "id": "growth_snoozed_future_is_temporary_suppression",
            "area": "growth",
            "row": {"decision_class": "snoozed", "suppress_until": "2026-08-01"},
            "expect": {"suppress": True, "recheck": True, "default_effect": "temporary_suppression"},
        },
        {
            "id": "growth_snoozed_past_reopens",
            "area": "growth",
            "row": {"decision_class": "snoozed", "suppress_until": "2026-06-01"},
            "expect": {"suppress": False, "recheck": True},
        },
        {
            "id": "growth_done_observes_effect",
            "area": "growth",
            "row": {"decision_class": "done", "expected_effect_window_days": "90"},
            "expect": {"suppress": True, "watch_effect": True, "default_effect": "effect_watch"},
        },
        {
            "id": "visual_fix_creates_task",
            "area": "visual",
            "row": {"decision_class": "needs_visual_fix", "decision_note": "Motiv passt nicht"},
            "expect": {"needs_task": True, "default_effect": "visual_followup", "recheck": True},
        },
        {
            "id": "watch_is_not_manual_task",
            "area": "run_health",
            "row": {"decision_class": "watch", "recheck_at": "2026-07-14"},
            "expect": {"needs_task": False, "suppress": False, "recheck": True},
        },
    ]

    for fixture in fixtures:
        actual = target_effect(fixture["row"], contract, today)
        failures = {}
        for key, expected in fixture["expect"].items():
            if actual.get(key) != expected:
                failures[key] = {"expected": expected, "actual": actual.get(key)}
        add(
            checks,
            fixture["id"],
            fixture["area"],
            not failures,
            "Fixture erfuellt semantischen Zielvertrag" if not failures else "Fixture verletzt semantischen Zielvertrag",
            {"actual": actual, "failures": failures},
        )

    lifecycle_required = {"applied_count", "prevented_count", "recurrence_count", "false_positive_count", "last_seen_at", "expires_at"}
    target_doc = (ROOT / "docs" / "content-ops-self-learning-target.md").read_text(encoding="utf-8", errors="replace") if (ROOT / "docs" / "content-ops-self-learning-target.md").exists() else ""
    lifecycle_missing = sorted(token for token in lifecycle_required if token not in target_doc)
    add(checks, "feedback_lifecycle_documented", "rule_quality", not lifecycle_missing, "Feedback-Qualitaetsmetriken dokumentiert" if not lifecycle_missing else "Feedback-Qualitaetsmetriken fehlen in Zielvertrag", {"missing": lifecycle_missing})

    failures = [item for item in checks if item["status"] == "fail"]
    report = {
        "status": "fail" if failures else "pass",
        "summary": {
            "total": len(checks),
            "ok": sum(item["status"] == "ok" for item in checks),
            "fail": len(failures),
        },
        "failures": failures,
        "checks": checks,
    }
    REPORT_PATH.parent.mkdir(parents=True, exist_ok=True)
    REPORT_PATH.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"self_learning_semantics={report['status']}")
    print(json.dumps(report["summary"], ensure_ascii=False))
    for item in failures:
        print(f"FAIL {item['id']}: {item['message']}")
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
# === END FILE: scripts/audit-self-learning-semantics.py ===
