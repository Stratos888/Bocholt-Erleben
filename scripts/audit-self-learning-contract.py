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
    "scripts/audit-self-learning-semantics.py",
    "data/content_ops_decision_classes.json",
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
semantics_script = "scripts/audit-self-learning-semantics.py"
decision_contract = "data/content_ops_decision_classes.json"
target_doc = "docs/content-ops-self-learning-target.md"

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

add(checks, "search_feedback_write", "feedback_suche", content_wf, ["Write Content_Search_Feedback sheet tab", "Content_Search_Feedback", "content-search-feedback.json"], ok_msg="Content Quality schreibt Search-Feedback", bad_msg="Search-Feedback-Writeback fehlt")
add(checks, "search_feedback_read", "feedback_suche", weekly, ["read_search_feedback_rules", "build_search_feedback_context", "CONTENT_SEARCH_FEEDBACK", "read_inbox_rejection_feedback_rules", "classify_inbox_rejection"], ok_msg="Weekly-KI liest Content- und Inbox-Feedback", bad_msg="Weekly-KI-Lernkontext fehlt")
add(checks, "search_feedback_prompt", "feedback_suche", weekly, ["Berücksichtige CONTENT_SEARCH_FEEDBACK aktiv", "Deduped strikt gegen BESTAND_EVENTS, OFFENE_INBOX, ARCHIV und MANUAL_JSON", "Feedback ist kein Befehl zur Datenänderung"], ok_msg="Prompt-Vertrag fuer Feedback/Dedupe vorhanden", bad_msg="Prompt-Vertrag unvollstaendig")
add(checks, "search_feedback_effect", "wirkung", control, ["search.weekly.search_feedback_rules_applied", "search_feedback_class_counts", "candidate_filters:all"], ok_msg="Suchfeedback-Wirkung wird gemessen", bad_msg="Suchfeedback-Wirkungsmessung fehlt")

add(checks, "content_verification_cache", "feedback_content", content_wf, ["CONTENT_QUALITY_VERIFICATION_CACHE_WRITEBACK", "Content_Verification_Acceptance", "verification_status", "confirmed", "verified_until"], ok_msg="bestaetigte Faktenchecks fliessen in Cache zurueck", bad_msg="Faktencheck-Cache-Kreis fehlt")
add(checks, "manual_intake_guards", "feedback_inbox", manual_wf, ["evaluate_event_description", "infer_event_visual_fit", "duplicate_source_date", "duplicate_title_date_location", "write_intake_summary", "skip_reasons"], ok_msg="Manual Intake verhindert bekannte Fehler und schreibt Skip-Signale", bad_msg="Manual-Intake-Guard fehlt")
add(checks, "intake_cleanup_metrics", "wirkung", control, ["intake.manual.skip_reason", "manual_intake_skip:", "inbox.cleanup.remaining_open_items"], ok_msg="Intake-/Cleanup-Wirkung wird gemessen", bad_msg="Intake-/Cleanup-Wirkung fehlt")

add(checks, "visual_feedback_write", "feedback_visual", content_wf, ["Write Content_Visual_Feedback sheet tab", "Content_Visual_Feedback", "asset_gap"], ok_msg="Visual-Signale werden gesammelt", bad_msg="Visual-Feedback-Writeback fehlt")
add(checks, "visual_feedback_metrics", "feedback_visual", control, ["write_visual_feedback_or_backlog_signal", "content.audit.visual_feedback_signals", "content.audit.visual_feedback_asset_gaps"], ok_msg="Visual-Signale werden geroutet und gemessen", bad_msg="Visual-Feedback-Messung fehlt")

add(checks, "growth_backlog_basis", "growth", growth_script, ["Growth_Backlog", "decision_note", "items_suppressed", "cluster_key"], ok_msg="Growth-Backlog mit Dedupe-/Entscheidungsfeldern vorhanden", bad_msg="Growth-Backlog-Basis fehlt")
add(checks, "growth_metrics", "growth", control, ["growth.backlog.items_created", "growth.backlog.items_suppressed", "growth_backlog_items_created"], ok_msg="Growth-Signale werden gemessen", bad_msg="Growth-Messung fehlt")
add(checks, "growth_feedback_reader", "growth", growth_script, ["read_growth_feedback"], warn_only=True, ok_msg="Growth liest Betreiberfeedback explizit als Lernsignal", bad_msg="Growth hat noch keinen expliziten Feedback-Leser fuer kuenftige Suppression/Regelbildung")

add(checks, "task_lifecycle_minimum", "aufgabenmodell", control, ["user_action_required", "auto_routed", "open"], ok_msg="Mindest-Lifecycle fuer Aufgabe vs. Beobachtung vorhanden", bad_msg="Aufgaben-/Beobachtungs-Lifecycle fehlt")
add(checks, "dashboard_gate", "dashboard_gate", "docs/internal-dashboard-target.md", ["Dashboard ist nicht der Hauptprozess", "Betreiberentscheidung", "Feedback-Regel", "Erst danach `/intern/dashboard/` final"], ok_msg="Dashboard-Gate dokumentiert", bad_msg="Dashboard-Gate fehlt")

add(checks, "decision_taxonomy_contract", "semantik", decision_contract, ["decision_classes", "required_decision_fields", "rejected_not_public", "needs_visual_fix", "false_positive_count"], ok_msg="zentrale Entscheidungstaxonomie vorhanden", bad_msg="Entscheidungstaxonomie unvollstaendig")
add(checks, "semantic_fixture_guard", "semantik", semantics_script, ["self_learning_semantics", "search_rejected_not_public_filters_next_run", "growth_snoozed_past_reopens", "false_positive_count"], ok_msg="semantischer Fixture-Guard vorhanden", bad_msg="semantischer Fixture-Guard unvollstaendig")
add(checks, "self_learning_target_doc", "semantik", target_doc, ["Zentrale Entscheidungstaxonomie", "Pflicht-Fixtures", "recurrence_count", "false_positive_count", "Run Health"], ok_msg="optimaler Selbstlernprozess-Zielzustand dokumentiert", bad_msg="Selbstlernprozess-Zielzustand unvollstaendig")

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
