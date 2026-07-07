#!/usr/bin/env python3
# === BEGIN FILE: scripts/audit-content-ops-visual-feedback.py | Zweck: prueft Visual-Feedback-Lernkreis, Problemtypen und Folgeaktionen ===
from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from content_ops_visual_feedback import classify_visual_issue, load_visual_feedback_contract  # noqa: E402

CONTRACT_PATH = ROOT / "data" / "content_ops_visual_feedback_contract.json"
REPORT_PATH = ROOT / "data" / "content-ops-visual-feedback-report.json"


def add(checks: list[dict[str, Any]], check_id: str, area: str, ok: bool, message: str, details: dict[str, Any] | None = None) -> None:
    checks.append({
        "id": check_id,
        "area": area,
        "status": "ok" if ok else "fail",
        "message": message,
        "details": details or {},
    })


def main() -> None:
    checks: list[dict[str, Any]] = []

    if not CONTRACT_PATH.exists():
        contract = {"problem_types": {}}
        add(checks, "visual_contract_file", "contract", False, "Visual-Feedback-Contract fehlt")
    else:
        contract = load_visual_feedback_contract(str(CONTRACT_PATH))
        add(checks, "visual_contract_file", "contract", True, "Visual-Feedback-Contract vorhanden")

    required = {
        "visual_key_wrong",
        "visual_motif_wrong",
        "asset_missing",
        "asset_low_quality",
        "source_or_rights_issue",
        "accepted",
        "remaster_needed",
        "visual_review",
    }
    problem_types = set((contract.get("problem_types") or {}).keys())
    missing = sorted(required - problem_types)
    add(checks, "visual_problem_types_complete", "contract", not missing, "alle Ziel-Problemtypen vorhanden" if not missing else "fehlende Visual-Problemtypen", {"missing": missing})

    required_fields = {"problem_type", "decision_class", "default_effect", "followup_route", "requires_task"}
    fields = set(contract.get("required_result_fields") or [])
    missing_fields = sorted(required_fields - fields)
    add(checks, "visual_result_fields_complete", "contract", not missing_fields, "alle Ergebnisfelder vorhanden" if not missing_fields else "fehlende Ergebnisfelder", {"missing": missing_fields})

    fixtures = [
        {
            "id": "asset_gap_routes_to_asset_backlog",
            "row": {"issue_code": "event_visual_asset_gap", "visual_asset_status": "asset_gap"},
            "expect": {"problem_type": "asset_missing", "decision_class": "needs_visual_fix", "followup_route": "asset_production_backlog"},
        },
        {
            "id": "wrong_key_routes_to_resolver_review",
            "row": {"issue_code": "event_visual_key_wrong", "visual_key": "market", "suggested_visual_key": "theater_play"},
            "expect": {"problem_type": "visual_key_wrong", "decision_class": "needs_patch", "followup_route": "resolver_rule_review"},
        },
        {
            "id": "motif_mismatch_routes_to_motif_rule_review",
            "row": {"process_category": "visual_motif_fit", "recommended_action": "Motiv passt nicht zum Event"},
            "expect": {"problem_type": "visual_motif_wrong", "decision_class": "needs_visual_fix", "followup_route": "motif_rule_review"},
        },
        {
            "id": "low_quality_routes_to_remaster_backlog",
            "row": {"issue_code": "activity_visual_low_quality", "recommended_action": "remaster needed"},
            "expect": {"problem_type": "asset_low_quality", "decision_class": "needs_visual_fix", "followup_route": "asset_remaster_backlog"},
        },
        {
            "id": "accepted_visual_stays_no_task",
            "row": {"visual_problem_type": "accepted"},
            "expect": {"problem_type": "accepted", "decision_class": "accepted", "requires_task": False},
        },
        {
            "id": "rights_issue_requires_source_review",
            "row": {"visual_problem_type": "source_or_rights_issue"},
            "expect": {"problem_type": "source_or_rights_issue", "decision_class": "needs_source", "followup_route": "source_rights_review"},
        },
    ]

    for fixture in fixtures:
        actual = classify_visual_issue(fixture["row"], contract)
        failures = {}
        for key, expected in fixture["expect"].items():
            if actual.get(key) != expected:
                failures[key] = {"expected": expected, "actual": actual.get(key)}
        add(
            checks,
            fixture["id"],
            "fixture",
            not failures,
            "Visual-Fixture erfuellt Lernkreis-Vertrag" if not failures else "Visual-Fixture verletzt Lernkreis-Vertrag",
            {"actual": actual, "failures": failures},
        )

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
    REPORT_PATH.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"content_ops_visual_feedback={report['status']}")
    print(json.dumps(report["summary"], ensure_ascii=False))
    for item in failures:
        print(f"FAIL {item['id']}: {item['message']}")
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
# === END FILE: scripts/audit-content-ops-visual-feedback.py ===
