#!/usr/bin/env python3
# === BEGIN FILE: scripts/audit-self-learning-contract.py | Zweck: statischer Guard fuer den geschlossenen Content-/KI-/Feedback-Selbstlernprozess ===
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPORT_PATH = ROOT / "data" / "self-learning-contract-report.json"


def text(rel: str) -> str:
    path = ROOT / rel
    return path.read_text(encoding="utf-8", errors="replace") if path.exists() else ""


def has(rel: str, *needles: str) -> tuple[bool, list[str]]:
    raw = text(rel)
    missing = [needle for needle in needles if needle not in raw]
    return not missing, missing


def add(items: list[dict], check_id: str, area: str, rel: str, needles: list[str], *, warn_only: bool = False, ok_msg: str = "ok", bad_msg: str = "missing") -> None:
    ok, missing = has(rel, *needles)
    if ok:
        status = "ok"
        msg = ok_msg
    else:
        status = "warn" if warn_only else "fail"
        msg = f"{bad_msg}: {', '.join(missing)}"
    items.append({"id": check_id, "area": area, "status": status, "message": msg, "file": rel})


checks: list[dict] = []

required_files = [
    "scripts/content-ops-control.py",
    "scripts/weekly-ki-websearch-to-manual-inbox.py",
    "scripts/content-quality-audit.py",
    "scripts/growth-intelligence-backlog.py",
    "scripts/content_ops_decisions.py",
    "scripts/audit-self-learning-semantics.py",
    "scripts/audit-content-ops-run-health.py",
    "scripts/content_ops_visual_feedback.py",
    "scripts/audit-content-ops-visual-feedback.py",
    "scripts/audit-content-ops-e2e-fixtures.py",
    "data/content_ops_decision_classes.json",
    "data/content_ops_visual_feedback_contract.json",
    "data/content_ops_run_health_targets.json",
    "api/content-ops-health.php",
    ".github/workflows/content-quality-audit.yml",
    ".github/workflows/weekly-ki-websearch-to-manual-inbox.yml",
    ".github/workflows/manual-ki-intake.yml",
    ".github/workflows/inbox-cleanup.yml",
    ".github/workflows/growth-intelligence-backlog.yml",
    ".github/workflows/content-ops-http-ingest.yml",
    "docs/internal-dashboard-target.md",
    "docs/content-ops-self-learning-target.md",
]
for rel in required_files:
    checks.append({"id": f"file:{rel}", "area": "basis", "status": "ok" if (ROOT / rel).exists() else "fail", "message": "vorhanden" if (ROOT / rel).exists() else "fehlt", "file": rel})

control = "scripts/content-ops-control.py"
weekly = "scripts/weekly-ki-websearch-to-manual-inbox.py"
content_wf = ".github/workflows/content-quality-audit.yml"
weekly_wf = ".github/workflows/weekly-ki-websearch-to-manual-inbox.yml"
manual_wf = ".github/workflows/manual-ki-intake.yml"
cleanup_wf = ".github/workflows/inbox-cleanup.yml"
growth_wf = ".github/workflows/growth-intelligence-backlog.yml"
ingest_wf = ".github/workflows/content-ops-http-ingest.yml"
growth_script = "scripts/growth-intelligence-backlog.py"
helper_script = "scripts/content_ops_decisions.py"
semantics_script = "scripts/audit-self-learning-semantics.py"
decision_contract = "data/content_ops_decision_classes.json"
target_doc = "docs/content-ops-self-learning-target.md"
run_health_script = "scripts/audit-content-ops-run-health.py"
run_health_targets = "data/content_ops_run_health_targets.json"
run_health_api = "api/content-ops-health.php"
visual_helper_script = "scripts/content_ops_visual_feedback.py"
visual_audit_script = "scripts/audit-content-ops-visual-feedback.py"
visual_contract = "data/content_ops_visual_feedback_contract.json"
e2e_fixture_script = "scripts/audit-content-ops-e2e-fixtures.py"

action_modes = {
    "audit": (content_wf, "python scripts/content-ops-control.py record-audit"),
    "weekly_ki": (weekly_wf, "python scripts/content-ops-control.py record-weekly-ki"),
    "manual_intake": (manual_wf, "python scripts/content-ops-control.py record-manual-intake"),
    "inbox_cleanup": (cleanup_wf, "python scripts/content-ops-control.py record-inbox-cleanup"),
    "growth": (growth_wf, "python scripts/content-ops-control.py record-growth"),
}
for mode in ["record-audit", "record-weekly-ki", "record-manual-intake", "record-inbox-cleanup", "record-growth"]:
    add(checks, f"mode:{mode}", "normalisierung", control, [mode], ok_msg="Mode vorhanden", bad_msg="Mode fehlt")

add(checks, "content_ops_schema", "normalisierung", control, ["content_ops_run", "content_ops_metric_daily", "content_ops_action_log", "feedback_rule_effectiveness_daily", "Finding", "RuleEffect", "RunPayload"], ok_msg="gemeinsame Run-/Metrik-/Finding-/Wirkungsschicht vorhanden", bad_msg="Content-Ops-Struktur unvollstaendig")

for key, (rel, cmd) in action_modes.items():
    add(checks, f"workflow:{key}", "roboteranschluss", rel, [cmd, "data/content-ops/*.json"], ok_msg="Roboter schreibt Content-Ops-Artefakte", bad_msg="Roboter nicht vollstaendig angeschlossen")

add(checks, "http_ingest", "persistenz", ingest_wf, ["Content Quality Audit", "Inbox Cleanup (Archive)", "Growth Intelligence Backlog", "Weekly KI Websearch", "Manual KI Event Intake", "gh run download", "scripts/content-ops-http-ingest.py"], ok_msg="Folge-Ingest deckt die Content-Ops-Roboter ab", bad_msg="HTTP-Ingest unvollstaendig")
add(checks, "content_quality_http_ingest", "persistenz", content_wf, ["Send Content Ops audit impact to HTTP ingest", "CONTENT_OPS_INGEST_URL", "CONTENT_OPS_INGEST_TOKEN", "scripts/content-ops-http-ingest.py --input data/content-ops"], ok_msg="Content Quality sendet Content-Ops-Artefakte per HTTP-Ingest", bad_msg="Content Quality HTTP-Ingest fehlt")
add(checks, "inbox_cleanup_http_ingest", "persistenz", cleanup_wf, ["Send Content Ops inbox cleanup impact to HTTP ingest", "CONTENT_OPS_INGEST_URL", "CONTENT_OPS_INGEST_TOKEN", "scripts/content-ops-http-ingest.py --input data/content-ops"], ok_msg="Inbox Cleanup sendet Content-Ops-Artefakte per HTTP-Ingest", bad_msg="Inbox Cleanup HTTP-Ingest fehlt")

add(checks, "search_feedback_write", "feedback_suche", content_wf, ["Write Content_Search_Feedback sheet tab", "Content_Search_Feedback", "content-search-feedback.json"], ok_msg="Content Quality schreibt Search-Feedback", bad_msg="Search-Feedback-Writeback fehlt")
add(checks, "search_feedback_read", "feedback_suche", weekly, ["read_search_feedback_rules", "build_search_feedback_context", "CONTENT_SEARCH_FEEDBACK", "read_inbox_rejection_feedback_rules", "classify_inbox_rejection"], ok_msg="Weekly-KI liest Content- und Inbox-Feedback", bad_msg="Weekly-KI-Lernkontext fehlt")
add(checks, "search_feedback_prompt", "feedback_suche", weekly, ["Berücksichtige CONTENT_SEARCH_FEEDBACK aktiv", "Deduped strikt gegen BESTAND_EVENTS, OFFENE_INBOX, ARCHIV und MANUAL_JSON", "Feedback ist kein Befehl zur Datenänderung"], ok_msg="Prompt-Vertrag fuer Feedback/Dedupe vorhanden", bad_msg="Prompt-Vertrag unvollstaendig")
add(checks, "search_feedback_effect", "wirkung", control, ["search.weekly.search_feedback_rules_applied", "search_feedback_class_counts", "candidate_filters:all"], ok_msg="Suchfeedback-Wirkung wird gemessen", bad_msg="Suchfeedback-Wirkungsmessung fehlt")

add(checks, "content_verification_cache", "feedback_content", content_wf, ["CONTENT_QUALITY_VERIFICATION_CACHE_WRITEBACK", "Content_Verification_Acceptance", "verification_status", "confirmed", "verified_until"], ok_msg="bestaetigte Faktenchecks fliessen in Cache zurueck", bad_msg="Faktencheck-Cache-Kreis fehlt")
add(checks, "manual_intake_guards", "feedback_inbox", manual_wf, ["evaluate_event_description", "infer_event_visual_fit", "duplicate_source_date", "duplicate_title_date_location", "write_intake_summary", "skip_reasons"], ok_msg="Manual Intake verhindert bekannte Fehler und schreibt Skip-Signale", bad_msg="Manual-Intake-Guard fehlt")
add(checks, "intake_cleanup_metrics", "wirkung", control, ["intake.manual.skip_reason", "manual_intake_skip:", "inbox.cleanup.remaining_open_items"], ok_msg="Intake-/Cleanup-Wirkung wird gemessen", bad_msg="Intake-/Cleanup-Wirkung fehlt")

add(checks, "visual_feedback_write", "feedback_visual", content_wf, ["Write Content_Visual_Feedback sheet tab", "Content_Visual_Feedback", "asset_gap"], ok_msg="Visual-Signale werden gesammelt", bad_msg="Visual-Feedback-Writeback fehlt")
add(checks, "visual_feedback_metrics", "feedback_visual", control, ["write_visual_feedback_or_backlog_signal", "content.audit.visual_feedback_signals", "content.audit.visual_feedback_asset_gaps"], ok_msg="Visual-Signale werden geroutet und gemessen", bad_msg="Visual-Feedback-Messung fehlt")
add(checks, "visual_feedback_contract_check", "feedback_visual", visual_contract, ["visual_key_wrong", "visual_motif_wrong", "asset_missing", "asset_low_quality", "source_or_rights_issue"], ok_msg="Visual-Feedback-Contract vorhanden", bad_msg="Visual-Feedback-Contract unvollstaendig")
add(checks, "visual_feedback_reader_check", "feedback_visual", visual_helper_script, ["classify_visual_issue", "load_visual_feedback_contract", "followup_route", "decision_class"], ok_msg="Visual-Feedback-Reader vorhanden", bad_msg="Visual-Feedback-Reader unvollstaendig")
add(checks, "visual_feedback_audit_check", "feedback_visual", visual_audit_script, ["content_ops_visual_feedback", "asset_gap_routes_to_asset_backlog", "wrong_key_routes_to_resolver_review", "motif_mismatch_routes_to_motif_rule_review"], ok_msg="Visual-Feedback-Audit vorhanden", bad_msg="Visual-Feedback-Audit unvollstaendig")
add(checks, "visual_feedback_learning_metrics", "feedback_visual", control, ["content.audit.visual_problem", "content.audit.visual_followup", "visual_feedback:"], ok_msg="Visual-Feedback-Lernwirkung wird gemessen", bad_msg="Visual-Feedback-Lernwirkung fehlt")
add(checks, "content_ops_e2e_fixture_guard", "e2e", e2e_fixture_script, ["content_ops_e2e_fixtures", "normalize_content_audit", "normalize_manual_intake", "normalize_weekly_ki", "normalize_growth", "visual_rule_effect", "missing_source_finding"], ok_msg="funktionaler Content-Ops-E2E-Fixture-Guard vorhanden", bad_msg="Content-Ops-E2E-Fixture-Guard unvollstaendig")

add(checks, "growth_backlog_basis", "growth", growth_script, ["Growth_Backlog", "decision_note", "items_suppressed", "cluster_key"], ok_msg="Growth-Backlog mit Dedupe-/Entscheidungsfeldern vorhanden", bad_msg="Growth-Backlog-Basis fehlt")
add(checks, "growth_metrics", "growth", control, ["growth.backlog.items_created", "growth.backlog.items_suppressed", "growth_backlog_items_created"], ok_msg="Growth-Signale werden gemessen", bad_msg="Growth-Messung fehlt")
add(checks, "growth_feedback_reader", "growth", growth_script, ["read_growth_feedback"], warn_only=True, ok_msg="Growth liest Betreiberfeedback explizit als Lernsignal", bad_msg="Growth hat noch keinen expliziten Feedback-Leser fuer kuenftige Suppression/Regelbildung")
add(checks, "growth_runtime_helper_import", "growth", growth_script, ["SCRIPT_DIR", "sys.path.insert", "content_ops_decisions", "target_effect"], ok_msg="Growth kann zentrale Helper auch bei runpy-Ausfuehrung importieren", bad_msg="Growth-Helper-Import fuer Workflow-runtime nicht robust")

add(checks, "task_lifecycle_minimum", "aufgabenmodell", control, ["user_action_required", "auto_routed", "open"], ok_msg="Mindest-Lifecycle fuer Aufgabe vs. Beobachtung vorhanden", bad_msg="Aufgaben-/Beobachtungs-Lifecycle fehlt")
add(checks, "dashboard_gate", "dashboard_gate", "docs/internal-dashboard-target.md", ["Dashboard ist nicht der Hauptprozess", "Betreiberentscheidung", "Feedback-Regel", "Erst danach `/intern/dashboard/` final"], ok_msg="Dashboard-Gate dokumentiert", bad_msg="Dashboard-Gate fehlt")

add(checks, "decision_taxonomy_contract", "semantik", decision_contract, ["decision_classes", "required_decision_fields", "rejected_not_public", "needs_visual_fix", "false_positive_count"], ok_msg="zentrale Entscheidungstaxonomie vorhanden", bad_msg="Entscheidungstaxonomie unvollstaendig")
add(checks, "decision_taxonomy_reader", "semantik", helper_script, ["resolve_decision_class", "is_suppression_active", "target_effect", "rule_quality_metric_keys"], ok_msg="operativer Entscheidungsklassen-Reader vorhanden", bad_msg="operativer Entscheidungsklassen-Reader unvollstaendig")
add(checks, "content_inbox_decision_semantics", "semantik", control, ["content.audit.decision_class", "intake.manual.decision_class", "decision_default_effect"], ok_msg="Content-/Inbox-Entscheidungen werden semantisch gemessen", bad_msg="Content-/Inbox-Decision-Semantik fehlt")
add(checks, "semantic_fixture_guard", "semantik", semantics_script, ["self_learning_semantics", "search_rejected_not_public_filters_next_run", "growth_snoozed_past_reopens", "false_positive_count"], ok_msg="semantischer Fixture-Guard vorhanden", bad_msg="semantischer Fixture-Guard unvollstaendig")
add(checks, "self_learning_target_doc", "semantik", target_doc, ["Zentrale Entscheidungstaxonomie", "Pflicht-Fixtures", "recurrence_count", "false_positive_count", "Run Health"], ok_msg="optimaler Selbstlernprozess-Zielzustand dokumentiert", bad_msg="Selbstlernprozess-Zielzustand unvollstaendig")
add(checks, "run_health_targets_check", "run_health", run_health_targets, ["content_quality_audit", "weekly_ki_websearch", "growth_intelligence", "warn_after_hours", "stale_after_hours"], ok_msg="Run-Health-Ziele vorhanden", bad_msg="Run-Health-Ziele unvollstaendig")
add(checks, "run_health_environment_required", "run_health", run_health_targets, ["required_environments", "staging", "live"], ok_msg="Run-Health-Pflichtlaeufe sind je Umgebung steuerbar", bad_msg="Run-Health-Umgebungslogik fehlt")
add(checks, "run_health_audit_check", "run_health", run_health_script, ["content_ops_run_health", "missing_required", "stale", "warn_after_hours", "content_ops_run_health_targets.json"], ok_msg="Run-Health-Audit vorhanden", bad_msg="Run-Health-Audit unvollstaendig")
add(checks, "run_health_api_check", "run_health", run_health_api, ["be_require_review_access", "content_ops_run", "warn_after_hours", "stale_after_hours", "action_required"], ok_msg="Run-Health-API vorhanden", bad_msg="Run-Health-API unvollstaendig")
add(checks, "run_health_api_environment_required", "run_health", run_health_api, ["coh_target_required", "required_environments"], ok_msg="Run-Health-API wertet Pflichtlaeufe je Umgebung aus", bad_msg="Run-Health-API-Umgebungslogik fehlt")

fails = [c for c in checks if c["status"] == "fail"]
warns = [c for c in checks if c["status"] == "warn"]
report = {
    "status": "fail" if fails else "pass",
    "ready_for_final_dashboard": not fails and not warns,
    "summary": {"total": len(checks), "ok": sum(c["status"] == "ok" for c in checks), "warn": len(warns), "fail": len(fails)},
    "warnings": warns,
    "failures": fails,
    "checks": checks,
}
REPORT_PATH.parent.mkdir(parents=True, exist_ok=True)
REPORT_PATH.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

print(f"self_learning_contract={report['status']}")
print(f"ready_for_final_dashboard={str(report['ready_for_final_dashboard']).lower()}")
print(json.dumps(report["summary"], ensure_ascii=False))
for c in warns:
    print(f"WARN {c['id']}: {c['message']}")
for c in fails:
    print(f"FAIL {c['id']}: {c['message']}")
if fails:
    sys.exit(1)
# === END FILE: scripts/audit-self-learning-contract.py ===
