#!/usr/bin/env python3
# === BEGIN FILE: scripts/audit-content-ops-e2e-fixtures.py | Zweck: echter E2E-Fixture-Guard fuer Content-Ops-Normalisierung, Metriken, Findings und RuleEffects ===
from __future__ import annotations

import importlib.util
import json
import sys
from dataclasses import asdict, is_dataclass
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"
TMP = ROOT / ".tmp" / "content-ops-e2e-fixtures"
REPORT_PATH = ROOT / "data" / "content-ops-e2e-fixtures-report.json"

sys.path.insert(0, str(SCRIPTS))


def load_control_module() -> Any:
    path = SCRIPTS / "content-ops-control.py"
    spec = importlib.util.spec_from_file_location("content_ops_control_e2e", path)
    if spec is None or spec.loader is None:
        raise RuntimeError("content-ops-control.py kann nicht geladen werden")
    module = importlib.util.module_from_spec(spec)
    sys.modules["content_ops_control_e2e"] = module
    spec.loader.exec_module(module)
    return module


control = load_control_module()


def write_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def obj_to_dict(value: Any) -> dict[str, Any]:
    if is_dataclass(value):
        return asdict(value)
    if isinstance(value, dict):
        return value
    return dict(value)


def metric_value(payload: Any, key: str, dimension_key: str | None = None) -> float | None:
    for item in payload.metrics:
        if item.metric_key != key:
            continue
        if dimension_key is not None and item.dimension_key != dimension_key:
            continue
        return float(item.metric_value)
    return None


def has_metric(payload: Any, key: str, dimension_key: str | None = None, expected: float | None = None) -> bool:
    value = metric_value(payload, key, dimension_key)
    if value is None:
        return False
    if expected is None:
        return True
    return value == float(expected)


def has_finding(payload: Any, *, finding_type: str | None = None, safe_action: str | None = None, detail_key: str | None = None, detail_value: Any | None = None) -> bool:
    for item in payload.findings:
        if finding_type is not None and item.finding_type != finding_type:
            continue
        if safe_action is not None and item.safe_action != safe_action:
            continue
        if detail_key is not None:
            if item.details.get(detail_key) != detail_value:
                continue
        return True
    return False


def has_rule_effect(payload: Any, *, rule_key: str | None = None, rule_type: str | None = None, rule_class: str | None = None, applied: int | None = None, prevented: int | None = None) -> bool:
    for item in payload.rule_effects:
        if rule_key is not None and item.rule_key != rule_key:
            continue
        if rule_type is not None and item.rule_type != rule_type:
            continue
        if rule_class is not None and item.rule_class != rule_class:
            continue
        if applied is not None and int(item.applied_count) != applied:
            continue
        if prevented is not None and int(item.prevented_count) != prevented:
            continue
        return True
    return False


def add(checks: list[dict[str, Any]], check_id: str, area: str, ok: bool, message: str, details: dict[str, Any] | None = None) -> None:
    checks.append({
        "id": check_id,
        "area": area,
        "status": "ok" if ok else "fail",
        "message": message,
        "details": details or {},
    })


def main() -> None:
    TMP.mkdir(parents=True, exist_ok=True)
    checks: list[dict[str, Any]] = []

    # 1) Content Audit + Visual: echter Report rein, echte Normalisierung raus.
    content_report = {
        "meta": {
            "scope": "e2e_fixture",
            "generated_at": "2026-07-07T00:00:00Z",
            "ai_candidates_total": 0,
        },
        "summary": {
            "total": 2,
            "counts": {"critical": 1, "review_needed": 1},
            "by_action_route": {},
            "by_process_category": {"human_review": 1, "visual_motif_fit": 1},
            "by_correction_owner": {},
        },
        "observations_summary": {"total": 0, "by_status": {}},
        "verification_summary": {},
        "search_feedback_summary": {},
        "visual_feedback_summary": {"route_counts": {"asset_gap": 1}},
        "issues": [
            {
                "decision_class": "needs_patch",
                "issue_code": "content_wrong_date",
                "content_type": "event",
                "content_id": "fixture-event-1",
                "title": "Fixture Event falsches Datum",
                "severity": "critical",
                "process_category": "human_review",
                "automation_policy": "human_decision_required",
                "evidence_status": "measured",
                "recommended_action": "Datum korrigieren",
            },
            {
                "decision_class": "needs_visual_fix",
                "issue_code": "event_visual_key_wrong",
                "content_type": "event",
                "content_id": "fixture-event-2",
                "title": "Fixture Event falsches Visual",
                "severity": "review_needed",
                "process_category": "visual_motif_fit",
                "visual_key": "market",
                "suggested_visual_key": "theater_play",
                "visual_asset_status": "ready",
                "recommended_action": "Resolver-Regel pruefen",
            },
            {
                "issue_code": "event_source_fact_evidence",
                "content_type": "event",
                "content_id": "fixture-event-3",
                "title": "Fixture Event Quellenprüfung",
                "severity": "warning",
                "process_category": "ai_verification_candidate",
                "action_route": "ai_factcheck_candidate",
                "recommended_action": "Quelle prüfen",
            },
            {
                "issue_code": "activity_runtime_guarded",
                "content_type": "activity",
                "content_id": "fixture-activity-1",
                "title": "Fixture Activity Laufzeitgeschützt",
                "severity": "warning",
                "process_category": "activity_condition_runtime_guarded",
                "action_route": "guarded_by_runtime",
                "recommended_action": "Beobachten",
            },
        ],
    }
    content_path = TMP / "content-quality-report.json"
    write_json(content_path, content_report)
    content_payload = control.normalize_content_audit(content_path)

    add(checks, "content_status_attention", "content", content_payload.status == "technical_or_content_attention", "Content-Audit setzt Aufmerksamkeitsstatus")
    add(checks, "content_action_required", "content", bool(content_payload.action_required), "kritisches Content-Issue erzeugt action_required")
    add(checks, "content_decision_metric_needs_patch", "content", has_metric(content_payload, "content.audit.decision_class.needs_patch", "needs_patch", 1), "needs_patch wird als Content-Decision-Metrik gezaehlt")
    add(checks, "content_decision_metric_needs_visual_fix", "content", has_metric(content_payload, "content.audit.decision_class.needs_visual_fix", "needs_visual_fix", 1), "needs_visual_fix wird als Content-Decision-Metrik gezaehlt")
    add(checks, "content_fallback_metric_needs_source", "content", has_metric(content_payload, "content.audit.decision_class.needs_source", "needs_source", 1), "Content-Audit-Fallback klassifiziert Quellenpruefung als needs_source")
    add(checks, "content_fallback_metric_watch", "content", has_metric(content_payload, "content.audit.decision_class.watch", "watch", 1), "Content-Audit-Fallback klassifiziert Runtime-Guard als watch")
    add(checks, "content_fallback_finding_details", "content", has_finding(content_payload, finding_type="event_source_fact_evidence", detail_key="decision_inferred", detail_value=True), "inferierte Content-decision_class wird im Finding markiert")
    add(checks, "content_decision_rule_effect", "content", has_rule_effect(content_payload, rule_key="content_audit_decision:needs_source", rule_type="content_audit_decision", rule_class="needs_source", applied=1), "Content-Audit-Decision erzeugt RuleEffect")
    add(checks, "visual_problem_metric", "visual", has_metric(content_payload, "content.audit.visual_problem.visual_key_wrong", "visual_key_wrong", 1), "Visual-Key-Problem wird gemessen")
    add(checks, "visual_followup_metric", "visual", has_metric(content_payload, "content.audit.visual_followup.resolver_rule_review", "resolver_rule_review", 1), "Visual-Folgeaktion resolver_rule_review wird gemessen")
    add(checks, "visual_rule_effect", "visual", has_rule_effect(content_payload, rule_key="visual_feedback:resolver_rule_review", rule_type="visual_feedback", rule_class="resolver_rule_review", applied=1), "Visual-Feedback erzeugt RuleEffect")
    add(checks, "visual_finding_details", "visual", has_finding(content_payload, finding_type="event_visual_key_wrong", detail_key="visual_followup_route", detail_value="resolver_rule_review"), "Visual-Finding traegt Followup-Details")

    # 2) Manual Intake: Skip-Gruende muessen semantische Metriken und Finding-Details erzeugen.
    manual_summary = {
        "input_count": 4,
        "appended_count": 0,
        "skipped_count": 3,
        "skip_reasons": {
            "duplicate_source_date": 2,
            "missing_required": 1,
        },
    }
    manual_path = TMP / "manual-ki-intake-summary.json"
    write_json(manual_path, manual_summary)
    manual_payload = control.normalize_manual_intake(manual_path)

    add(checks, "manual_status_no_rows", "manual_intake", manual_payload.status == "no_rows_appended", "Manual Intake ohne neue Zeilen bleibt no_rows_appended")
    add(checks, "manual_no_action_required", "manual_intake", not manual_payload.action_required, "nur Skip-Gruende erzeugen keine manuelle Aktion")
    add(checks, "manual_duplicate_metric", "manual_intake", has_metric(manual_payload, "intake.manual.decision_class.duplicate", "duplicate", 2), "Dublette wird als decision_class duplicate gemessen")
    add(checks, "manual_source_weak_metric", "manual_intake", has_metric(manual_payload, "intake.manual.decision_class.rejected_source_weak", "rejected_source_weak", 1), "missing_required wird als rejected_source_weak gemessen")
    add(checks, "manual_finding_semantic_details", "manual_intake", has_finding(manual_payload, finding_type="manual_intake_skip:duplicate_source_date", detail_key="decision_class", detail_value="duplicate"), "Manual-Intake-Finding traegt decision_class")

    # 3) Weekly KI: echte Diagnose prueft Search-Feedback, Filterwirkung und manuelle Kandidaten.
    weekly_diag = {
        "generated_at_utc": "2026-07-07T00:00:00Z",
        "raw_candidates_returned": 10,
        "selected_candidates": 2,
        "dropped_candidates": 8,
        "drop_reasons": {
            "existing_title_date": 3,
            "bad_time": 2,
            "weak_source": 1,
        },
        "coverage_status_counts": {
            "MISSING_FROM_RAW": 1,
            "OK": 4,
        },
        "source_candidate_reasons": {
            "city_source": 2,
        },
        "search_feedback_rules_applied": 2,
        "search_feedback_class_counts": {
            "rejected_not_public": 2,
        },
        "selected_candidate_titles": ["Fixture 1", "Fixture 2"],
    }
    weekly_path = TMP / "weekly_event_diagnostics.json"
    manual_json_path = TMP / "inbox_manual.json"
    write_json(weekly_path, weekly_diag)
    write_json(manual_json_path, [{"title": "Fixture 1"}, {"title": "Fixture 2"}])
    weekly_payload = control.normalize_weekly_ki(weekly_path, manual_json_path)

    add(checks, "weekly_status_candidates", "weekly_ki", weekly_payload.status == "manual_candidates_created", "Weekly KI erzeugt Kandidatenstatus")
    add(checks, "weekly_action_required", "weekly_ki", bool(weekly_payload.action_required), "ausgewaehlte Kandidaten erzeugen manuelle Aktion")
    add(checks, "weekly_feedback_rule_effect", "weekly_ki", has_rule_effect(weekly_payload, rule_key="search_feedback:rejected_not_public", rule_type="search_feedback", rule_class="rejected_not_public", applied=2), "Search-Feedback erzeugt RuleEffect")
    add(checks, "weekly_candidate_filter_effect", "weekly_ki", has_rule_effect(weekly_payload, rule_key="candidate_filters:all", rule_type="candidate_filter", rule_class="candidate_guard_and_dedupe", applied=8, prevented=5), "Kandidatenfilter erzeugt prevented_count")
    add(checks, "weekly_manual_finding", "weekly_ki", has_finding(weekly_payload, finding_type="weekly_selected_candidates_for_manual_review", safe_action="create_content_inbox_items_via_manual_intake"), "Weekly KI erzeugt Review-Finding")

    # 4) Growth: Summary muss Metriken und decided-backlog RuleEffect erzeugen.
    growth_summary = {
        "status": "ok",
        "items_created": 2,
        "items_suppressed": 3,
        "gsc_rows": 100,
        "ga4_rows": 50,
        "value_rows": 10,
        "growth_feedback_rules": 4,
        "growth_feedback_suppressed_keys": ["a", "b", "c"],
    }
    growth_path = TMP / "growth-intelligence-summary.json"
    write_json(growth_path, growth_summary)
    growth_payload = control.normalize_growth(growth_path)

    add(checks, "growth_status_ok", "growth", growth_payload.status == "growth_ok", "Growth-Normalisierung setzt growth_ok")
    add(checks, "growth_created_metric", "growth", has_metric(growth_payload, "growth.backlog.items_created", None, 2), "Growth erzeugt items_created-Metrik")
    add(checks, "growth_rule_effect", "growth", has_rule_effect(growth_payload, rule_key="growth_feedback:decided_backlog", rule_type="growth_feedback", rule_class="decided_backlog_suppression", applied=4, prevented=3), "Growth-Feedback erzeugt RuleEffect mit prevented_count")

    # 5) Missing source: fehlende Artefakte duerfen nicht still verschwinden.
    missing_payload = control.normalize_content_audit(TMP / "missing-content-quality-report.json")

    add(checks, "missing_source_status", "source_health", missing_payload.status == "source_artifact_missing", "fehlende Quelle erzeugt source_artifact_missing")
    add(checks, "missing_source_metric", "source_health", has_metric(missing_payload, "content_quality_audit.source_artifact_missing", None, 1), "fehlende Quelle erzeugt Workflow-Health-Metrik")
    add(checks, "missing_source_finding", "source_health", has_finding(missing_payload, finding_type="source_artifact_missing", safe_action="create_technical_followup_if_repeated"), "fehlende Quelle erzeugt technisches Finding")

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

    print(f"content_ops_e2e_fixtures={report['status']}")
    print(json.dumps(report["summary"], ensure_ascii=False))
    for item in failures:
        print(f"FAIL {item['id']}: {item['message']}")
        print(json.dumps(item.get("details") or {}, ensure_ascii=False))
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
# === END FILE: scripts/audit-content-ops-e2e-fixtures.py ===
