#!/usr/bin/env python3
# === BEGIN FILE: scripts/content-ops-control.py | Zweck: normalisiert Content-/Search-/Growth-Findings, leitet sichere Folgeaktionen ab und schreibt Impact-Metriken fuer das interne Verwaltungs-Dashboard | Umfang: Patch-1 Decision-&-Impact-Engine ohne fachliche Live-Aenderungen ===
from __future__ import annotations

import argparse
import hashlib
import json
import os
import sys
from dataclasses import asdict, dataclass, field
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

from content_ops_decisions import resolve_decision_class, target_effect

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT_DIR = ROOT / "data" / "content-ops"


def utc_now() -> datetime:
    return datetime.now(timezone.utc).replace(microsecond=0)


def iso_utc() -> str:
    return utc_now().isoformat().replace("+00:00", "Z")


def norm(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, (dict, list)):
        return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return str(value).strip()


def safe_int(value: Any, default: int = 0) -> int:
    try:
        if value is None or value == "":
            return default
        return int(float(str(value).replace(",", ".")))
    except Exception:
        return default


def safe_float(value: Any, default: float = 0.0) -> float:
    try:
        if value is None or value == "":
            return default
        return float(str(value).replace(",", "."))
    except Exception:
        return default


def load_json(path: Path, default: Any = None) -> Any:
    try:
        if not path.exists():
            return default
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        print(f"⚠️ JSON nicht lesbar: {path}: {exc}", file=sys.stderr)
        return default


def write_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def compact_json(payload: Any) -> str:
    return json.dumps(payload, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def sha256_text(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def today_iso() -> str:
    return date.today().isoformat()


def env_name() -> str:
    explicit = norm(os.environ.get("BE_ENVIRONMENT"))
    if explicit:
        return explicit
    branch = norm(os.environ.get("GITHUB_REF_NAME")) or norm(os.environ.get("GITHUB_REF", "")).split("/")[-1]
    if branch == "staging":
        return "staging"
    if branch == "main":
        return "live"
    return branch or "unknown"


def branch_name() -> str:
    return norm(os.environ.get("GITHUB_REF_NAME")) or norm(os.environ.get("GITHUB_REF", "")).split("/")[-1] or "unknown"


def github_run_url() -> str:
    explicit = norm(os.environ.get("GITHUB_RUN_URL"))
    if explicit:
        return explicit
    repo = norm(os.environ.get("GITHUB_REPOSITORY"))
    run_id = norm(os.environ.get("GITHUB_RUN_ID"))
    if repo and run_id:
        return f"https://github.com/{repo}/actions/runs/{run_id}"
    return ""


@dataclass
class Metric:
    metric_key: str
    metric_value: float
    metric_scope: str = "run"
    dimension_key: str = ""
    dimensions: Dict[str, Any] = field(default_factory=dict)


@dataclass
class Finding:
    finding_type: str
    entity_type: str = "system"
    entity_id: str = ""
    title: str = ""
    severity: str = "info"
    confidence: str = "observed"
    safe_action: str = "observe"
    user_action_required: bool = False
    source_mode: str = ""
    source_workflow: str = ""
    details: Dict[str, Any] = field(default_factory=dict)

    def fingerprint(self, environment: str) -> str:
        basis = {
            "environment": environment,
            "finding_type": self.finding_type,
            "entity_type": self.entity_type,
            "entity_id": self.entity_id,
            "title": self.title,
            "severity": self.severity,
            "source_mode": self.source_mode,
            "safe_action": self.safe_action,
        }
        return sha256_text(compact_json(basis))


@dataclass
class RuleEffect:
    rule_key: str
    rule_type: str
    rule_class: str
    applied_count: int = 0
    prevented_count: int = 0
    recurrence_count: int = 0
    false_positive_count: int = 0
    details: Dict[str, Any] = field(default_factory=dict)


@dataclass
class RunPayload:
    source_mode: str
    status: str = "ok"
    action_required: bool = False
    generated_at_utc: str = field(default_factory=iso_utc)
    metrics: List[Metric] = field(default_factory=list)
    findings: List[Finding] = field(default_factory=list)
    rule_effects: List[RuleEffect] = field(default_factory=list)
    summary: Dict[str, Any] = field(default_factory=dict)

    def run_fingerprint(self) -> str:
        basis = {
            "source_mode": self.source_mode,
            "environment": env_name(),
            "branch": branch_name(),
            "run_id": norm(os.environ.get("GITHUB_RUN_ID")),
            "run_attempt": norm(os.environ.get("GITHUB_RUN_ATTEMPT")),
            "workflow": norm(os.environ.get("GITHUB_WORKFLOW")),
            "generated_at_utc": self.generated_at_utc if not norm(os.environ.get("GITHUB_RUN_ID")) else "github-run-scoped",
        }
        return sha256_text(compact_json(basis))


def metric(key: str, value: Any, scope: str = "run", dimension_key: str = "", **dimensions: Any) -> Metric:
    return Metric(
        metric_key=key,
        metric_value=safe_float(value),
        metric_scope=scope,
        dimension_key=dimension_key,
        dimensions={k: v for k, v in dimensions.items() if v not in (None, "")},
    )


def add_counter_metrics(metrics: List[Metric], prefix: str, values: Dict[str, Any], scope: str = "run") -> None:
    for key, value in sorted((values or {}).items()):
        clean_key = norm(key) or "empty"
        metrics.append(metric(f"{prefix}.{clean_key}", safe_int(value), scope=scope, dimension_key=clean_key))


def safe_target_effect(row: Dict[str, Any]) -> Dict[str, Any]:
    try:
        return target_effect(row)
    except Exception as exc:
        return {
            "decision_class": "",
            "default_effect": "decision_effect_error",
            "task_state": "open",
            "suppress": False,
            "recheck": False,
            "watch_effect": False,
            "needs_task": False,
            "error": str(exc),
        }


def decision_row_from_manual_skip_reason(reason: str) -> Dict[str, Any]:
    base = norm(reason).split(":", 1)[0]
    if base in {"duplicate_source_date", "duplicate_title_date_location"}:
        return {"decision_class": "duplicate", "decision_note": reason}
    if base.startswith("description_quality"):
        return {"decision_class": "rejected_low_value", "decision_note": reason}
    if base in {"missing_required", "invalid_date_format", "invalid_endDate_format"}:
        return {"decision_class": "rejected_source_weak", "decision_note": reason}
    return {"decision_class": "rejected_low_value", "decision_note": reason}


def route_content_issue(item: Dict[str, Any]) -> Tuple[str, bool]:
    severity = norm(item.get("severity"))
    category = norm(item.get("process_category"))
    policy = norm(item.get("automation_policy"))
    issue_code = norm(item.get("issue_code"))

    if severity in {"critical", "review_needed"}:
        return "create_content_review_task", True
    if severity == "auto_fixed" or norm(item.get("auto_fix_done")) == "true":
        return "suppress_auto_resolved", False
    if category == "activity_condition_runtime_guarded" or policy.startswith("guarded_by_runtime"):
        return "suppress_runtime_guarded", False
    if category == "ai_verification_candidate":
        return "queue_ai_factcheck_candidate", False
    if category.startswith("visual_") or issue_code.startswith("event_visual") or issue_code.startswith("activity_visual"):
        return "write_visual_feedback_or_backlog_signal", False
    if category == "neutral_source_observation":
        return "suppress_neutral_observation", False
    if "human_review" in policy or "human_decision" in policy:
        return "create_content_review_task", True
    return "observe_non_manual", False


def missing_source_payload(source_mode: str, missing_path: Path, workflow_label: str) -> RunPayload:
    payload = RunPayload(source_mode=source_mode, status="source_artifact_missing", action_required=False)
    payload.summary = {"missing_path": str(missing_path), "message": "Source artifact missing; upstream workflow step may have failed before producing diagnostics."}
    payload.metrics.append(metric(f"{source_mode}.source_artifact_missing", 1, scope="workflow_health", path=str(missing_path)))
    payload.findings.append(Finding(
        finding_type="source_artifact_missing",
        entity_type="workflow_artifact",
        entity_id=str(missing_path),
        title=f"Content-Ops-Quelle fehlt: {missing_path}",
        severity="warning",
        confidence="measured",
        safe_action="create_technical_followup_if_repeated",
        user_action_required=False,
        source_mode=source_mode,
        source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or workflow_label,
        details={"missing_path": str(missing_path)},
    ))
    return payload


def normalize_content_audit(report_path: Path) -> RunPayload:
    if not report_path.exists():
        return missing_source_payload("content_quality_audit", report_path, "Content Quality Audit")
    report = load_json(report_path, {}) or {}
    meta = report.get("meta") or {}
    summary = report.get("summary") or {}
    counts = summary.get("counts") or {}
    by_action_route = summary.get("by_action_route") or {}
    issues = report.get("issues") or []
    observations_summary = report.get("observations_summary") or {}
    verification = report.get("verification_summary") or {}
    search_feedback_summary = report.get("search_feedback_summary") or {}
    visual_feedback_summary = report.get("visual_feedback_summary") or {}
    decision_class_counts: Dict[str, int] = {}
    decision_effect_counts: Dict[str, int] = {}

    payload = RunPayload(source_mode="content_quality_audit")
    payload.summary = {
        "scope": meta.get("scope", norm(os.environ.get("AUDIT_SCOPE"))),
        "generated_at": meta.get("generated_at", ""),
        "issue_total": summary.get("total", len(issues)),
        "counts": counts,
        "by_action_route": by_action_route,
        "observations": observations_summary,
        "verification": verification,
        "search_feedback": search_feedback_summary,
        "visual_feedback": visual_feedback_summary,
    }

    payload.metrics.extend([
        metric("content.audit.issues_total", summary.get("total", len(issues)), scope="audit", audit_scope=payload.summary.get("scope")),
        metric("content.audit.observations_total", observations_summary.get("total", 0), scope="audit"),
        metric("content.audit.ai_candidates_total", meta.get("ai_candidates_total", verification.get("ai_candidates_total", 0)), scope="audit"),
        metric("content.audit.ai_candidates_selected", meta.get("ai_candidates_selected", verification.get("ai_candidates_selected", 0)), scope="audit"),
        metric("content.audit.ai_candidates_deferred_by_budget", meta.get("ai_candidates_deferred_by_budget", verification.get("ai_candidates_deferred_by_budget", 0)), scope="audit"),
        metric("content.audit.verification_cache_hits", meta.get("verification_cache_hits", verification.get("cache_hits", 0)), scope="audit"),
        metric("content.audit.search_feedback_signals", meta.get("search_feedback_signals", 0), scope="audit"),
        metric("content.audit.search_feedback_rules_active", meta.get("search_feedback_rules_active", 0), scope="audit"),
        metric("content.audit.visual_feedback_signals", meta.get("visual_feedback_signals", 0), scope="audit"),
        metric("content.audit.visual_feedback_asset_gaps", meta.get("visual_feedback_asset_gaps", 0), scope="audit"),
        metric("content.audit.visual_feedback_search_relevant", meta.get("visual_feedback_search_relevant", 0), scope="audit"),
    ])
    add_counter_metrics(payload.metrics, "content.audit.severity", counts, scope="audit")
    add_counter_metrics(payload.metrics, "content.audit.action_route", by_action_route, scope="audit")
    add_counter_metrics(payload.metrics, "content.audit.process_category", summary.get("by_process_category") or {}, scope="audit")
    add_counter_metrics(payload.metrics, "content.audit.correction_owner", summary.get("by_correction_owner") or {}, scope="audit")
    add_counter_metrics(payload.metrics, "content.audit.observation_status", observations_summary.get("by_status") or {}, scope="audit")
    add_counter_metrics(payload.metrics, "content.audit.visual_route", visual_feedback_summary.get("route_counts") or {}, scope="audit")

    action_required = False
    for item in issues:
        if not isinstance(item, dict):
            continue
        safe_action, user_action_required = route_content_issue(item)
        decision_effect = safe_target_effect(item)
        decision_class = norm(decision_effect.get("decision_class"))
        decision_default_effect = norm(decision_effect.get("default_effect"))
        if decision_class:
            decision_class_counts[decision_class] = decision_class_counts.get(decision_class, 0) + 1
        if decision_default_effect:
            decision_effect_counts[decision_default_effect] = decision_effect_counts.get(decision_default_effect, 0) + 1
        if user_action_required:
            action_required = True
        payload.findings.append(Finding(
            finding_type=norm(item.get("issue_code")) or "content_issue",
            entity_type=norm(item.get("content_type")) or "content",
            entity_id=norm(item.get("content_id")),
            title=norm(item.get("title")),
            severity=norm(item.get("severity")) or "warning",
            confidence=norm(item.get("evidence_status")) or norm(item.get("verification_status")) or "observed",
            safe_action=safe_action,
            user_action_required=user_action_required,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Content Quality Audit",
            details={
                "date": item.get("date", ""),
                "source_system": item.get("source_system", ""),
                "process_category": item.get("process_category", ""),
                "correction_owner": item.get("correction_owner", ""),
                "workbench_group": item.get("workbench_group", ""),
                "recommended_action": item.get("recommended_action", ""),
                "source_url": item.get("source_url", ""),
                "suggested_url": item.get("suggested_url", ""),
                "suggested_visual_key": item.get("suggested_visual_key", ""),
                "visual_asset_status": item.get("visual_asset_status", ""),
                "decision_class": decision_class,
                "decision_default_effect": decision_default_effect,
                "decision_task_state": decision_effect.get("task_state", ""),
                "decision_suppress": decision_effect.get("suppress", False),
                "decision_recheck": decision_effect.get("recheck", False),
            },
        ))

    payload.summary["decision_class_counts"] = decision_class_counts
    payload.summary["decision_effect_counts"] = decision_effect_counts
    add_counter_metrics(payload.metrics, "content.audit.decision_class", decision_class_counts, scope="audit_decisions")
    add_counter_metrics(payload.metrics, "content.audit.decision_effect", decision_effect_counts, scope="audit_decisions")

    payload.action_required = action_required
    if safe_int(counts.get("critical")) > 0:
        payload.status = "technical_or_content_attention"
    elif action_required:
        payload.status = "manual_content_action_required"
    else:
        payload.status = "no_manual_action_from_audit"
    return payload


def normalize_weekly_ki(diagnostics_path: Path, manual_json_path: Path) -> RunPayload:
    if not diagnostics_path.exists():
        return missing_source_payload("weekly_ki_websearch", diagnostics_path, "Weekly KI Websearch")
    diagnostics = load_json(diagnostics_path, {}) or {}
    manual_items = load_json(manual_json_path, []) or []
    if not isinstance(manual_items, list):
        manual_items = []
    drop_reasons = diagnostics.get("drop_reasons") or {}
    coverage_counts = diagnostics.get("coverage_status_counts") or {}
    source_candidate_reasons = diagnostics.get("source_candidate_reasons") or {}
    feedback_class_counts = diagnostics.get("search_feedback_class_counts") or {}

    selected = safe_int(diagnostics.get("selected_candidates"))
    raw = safe_int(diagnostics.get("raw_candidates_returned"))
    dropped = safe_int(diagnostics.get("dropped_candidates"))
    feedback_rules = safe_int(diagnostics.get("search_feedback_rules_applied"))

    payload = RunPayload(source_mode="weekly_ki_websearch")
    payload.summary = {
        "generated_at_utc": diagnostics.get("generated_at_utc", ""),
        "raw_candidates_returned": raw,
        "selected_candidates": selected,
        "dropped_candidates": dropped,
        "drop_reasons": drop_reasons,
        "manual_json_items": len(manual_items),
        "source_candidates_added": diagnostics.get("source_candidates_added", 0),
        "coverage_status_counts": coverage_counts,
        "search_feedback_rules_applied": feedback_rules,
        "search_feedback_class_counts": feedback_class_counts,
    }

    prevented_reasons = {
        "past_event",
        "too_short_notice",
        "out_of_window",
        "invalid_date",
        "invalid_endDate",
        "endDate_before_date",
        "bad_time",
        "source_url_download_document",
        "url_download_document",
        "existing_title_date",
        "batch_title_date",
        "batch_source_date",
        "existing_source_occurrence",
        "batch_source_occurrence",
        "existing_exact_occurrence",
        "batch_exact_occurrence",
        "existing_no_time_missing_occurrence",
        "batch_no_time_missing_occurrence",
        "existing_no_time_occurrence",
        "batch_no_time_occurrence",
    }
    prevented_total = sum(safe_int(value) for key, value in drop_reasons.items() if norm(key).split(":", 1)[0] in prevented_reasons)
    missing_coverage = safe_int(coverage_counts.get("MISSING_FROM_RAW")) + safe_int(coverage_counts.get("MISSING_FROM_SELECTED"))

    payload.metrics.extend([
        metric("search.weekly.raw_candidates", raw, scope="weekly_search"),
        metric("search.weekly.selected_candidates", selected, scope="weekly_search"),
        metric("search.weekly.dropped_candidates", dropped, scope="weekly_search"),
        metric("search.weekly.prevented_candidates", prevented_total, scope="weekly_search"),
        metric("search.weekly.manual_json_items", len(manual_items), scope="weekly_search"),
        metric("search.weekly.source_candidates_added", diagnostics.get("source_candidates_added", 0), scope="weekly_search"),
        metric("search.weekly.raw_source_candidates", diagnostics.get("raw_source_candidates_returned", 0), scope="weekly_search"),
        metric("search.weekly.search_feedback_rules_applied", feedback_rules, scope="weekly_search"),
        metric("search.weekly.coverage_targets_total", diagnostics.get("coverage_targets_total", 0), scope="weekly_search"),
        metric("search.weekly.coverage_missing_signals", missing_coverage, scope="weekly_search"),
    ])
    if raw:
        payload.metrics.append(metric("search.weekly.selected_rate_percent", selected * 100.0 / raw, scope="weekly_search"))
        payload.metrics.append(metric("search.weekly.dropped_rate_percent", dropped * 100.0 / raw, scope="weekly_search"))
    add_counter_metrics(payload.metrics, "search.weekly.drop_reason", drop_reasons, scope="weekly_search")
    add_counter_metrics(payload.metrics, "search.weekly.coverage_status", coverage_counts, scope="weekly_search")
    add_counter_metrics(payload.metrics, "search.weekly.source_candidate_reason", source_candidate_reasons, scope="weekly_search")
    add_counter_metrics(payload.metrics, "search.weekly.feedback_class", feedback_class_counts, scope="weekly_search")

    for reason, value in sorted(drop_reasons.items()):
        count = safe_int(value)
        if count <= 0:
            continue
        base_reason = norm(reason).split(":", 1)[0]
        safe_action = "suppress_correctly_filtered_candidate" if base_reason in prevented_reasons else "observe_filter_reason"
        payload.findings.append(Finding(
            finding_type=f"weekly_drop_reason:{base_reason}",
            entity_type="search_candidate_batch",
            entity_id=base_reason,
            title=f"{base_reason}: {count}",
            severity="info",
            confidence="measured",
            safe_action=safe_action,
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Weekly KI Websearch",
            details={"count": count, "raw_reason": reason},
        ))

    for status, value in sorted(coverage_counts.items()):
        count = safe_int(value)
        if count <= 0:
            continue
        needs_action = norm(status) in {"MISSING_FROM_RAW", "MISSING_FROM_SELECTED"}
        payload.findings.append(Finding(
            finding_type=f"coverage_status:{status}",
            entity_type="coverage_target_batch",
            entity_id=norm(status),
            title=f"Coverage {status}: {count}",
            severity="warning" if needs_action else "info",
            confidence="measured",
            safe_action="add_backlog_signal" if needs_action else "observe_coverage_status",
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Weekly KI Websearch",
            details={"count": count},
        ))

    for rule_class, value in sorted(feedback_class_counts.items()):
        count = safe_int(value)
        if count <= 0:
            continue
        payload.rule_effects.append(RuleEffect(
            rule_key=f"search_feedback:{rule_class}",
            rule_type="search_feedback",
            rule_class=norm(rule_class),
            applied_count=count,
            details={"source": "weekly_event_diagnostics.search_feedback_class_counts"},
        ))

    payload.rule_effects.append(RuleEffect(
        rule_key="candidate_filters:all",
        rule_type="candidate_filter",
        rule_class="candidate_guard_and_dedupe",
        applied_count=dropped,
        prevented_count=prevented_total,
        details={"drop_reasons": drop_reasons},
    ))

    if selected > 0:
        payload.action_required = True
        payload.findings.append(Finding(
            finding_type="weekly_selected_candidates_for_manual_review",
            entity_type="manual_inbox_buffer",
            entity_id="data/inbox_manual.json",
            title=f"{selected} KI-Kandidaten fuer Review-Puffer",
            severity="review_needed",
            confidence="measured",
            safe_action="create_content_inbox_items_via_manual_intake",
            user_action_required=True,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Weekly KI Websearch",
            details={"manual_json_items": len(manual_items), "selected_candidate_titles": diagnostics.get("selected_candidate_titles", [])},
        ))

    if feedback_rules == 0:
        payload.findings.append(Finding(
            finding_type="weekly_search_feedback_rules_not_applied",
            entity_type="workflow",
            entity_id="weekly-ki-websearch-to-manual-inbox",
            title="0 Content-Search-Feedback-Regeln im Weekly-Lauf angewendet",
            severity="warning",
            confidence="measured",
            safe_action="create_technical_followup_if_feedback_expected",
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Weekly KI Websearch",
            details={"search_feedback_rules_applied": feedback_rules},
        ))

    payload.status = "manual_candidates_created" if selected > 0 else "no_manual_candidates_created"
    return payload


def normalize_manual_intake(summary_path: Path) -> RunPayload:
    if not summary_path.exists():
        return missing_source_payload("manual_ki_intake", summary_path, "Manual KI Event Intake")
    summary = load_json(summary_path, {}) or {}
    payload = RunPayload(source_mode="manual_ki_intake")
    appended = safe_int(summary.get("appended_count", os.environ.get("APPENDED_COUNT")))
    input_count = safe_int(summary.get("input_count", 0))
    skipped = safe_int(summary.get("skipped_count", 0))
    skip_reasons = summary.get("skip_reasons") or {}
    reset_commit = norm(os.environ.get("RESET_DID_COMMIT"))
    payload.summary = {
        "input_count": input_count,
        "appended_count": appended,
        "skipped_count": skipped,
        "skip_reasons": skip_reasons,
        "reset_did_commit": reset_commit,
    }
    payload.metrics.extend([
        metric("intake.manual.input_items", input_count, scope="manual_intake"),
        metric("intake.manual.appended_items", appended, scope="manual_intake"),
        metric("intake.manual.skipped_items", skipped, scope="manual_intake"),
        metric("intake.manual.reset_commit", 1 if reset_commit == "true" else 0, scope="manual_intake"),
    ])
    add_counter_metrics(payload.metrics, "intake.manual.skip_reason", skip_reasons, scope="manual_intake")
    intake_decision_class_counts: Dict[str, int] = {}
    intake_decision_effect_counts: Dict[str, int] = {}
    for reason, count in sorted(skip_reasons.items()):
        decision_effect = safe_target_effect(decision_row_from_manual_skip_reason(reason))
        decision_class = norm(decision_effect.get("decision_class"))
        decision_default_effect = norm(decision_effect.get("default_effect"))
        if decision_class:
            intake_decision_class_counts[decision_class] = intake_decision_class_counts.get(decision_class, 0) + safe_int(count)
        if decision_default_effect:
            intake_decision_effect_counts[decision_default_effect] = intake_decision_effect_counts.get(decision_default_effect, 0) + safe_int(count)
        payload.findings.append(Finding(
            finding_type=f"manual_intake_skip:{norm(reason).split(':', 1)[0]}",
            entity_type="manual_inbox_candidate_batch",
            entity_id=norm(reason),
            title=f"{reason}: {safe_int(count)}",
            severity="info",
            confidence="measured",
            safe_action="suppress_skipped_candidate_batch",
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Manual KI Event Intake",
            details={"count": safe_int(count), "raw_reason": reason},
        ))
    payload.summary["decision_class_counts"] = intake_decision_class_counts
    payload.summary["decision_effect_counts"] = intake_decision_effect_counts
    add_counter_metrics(payload.metrics, "intake.manual.decision_class", intake_decision_class_counts, scope="manual_intake_decisions")
    add_counter_metrics(payload.metrics, "intake.manual.decision_effect", intake_decision_effect_counts, scope="manual_intake_decisions")

    payload.action_required = appended > 0
    payload.status = "inbox_rows_appended" if appended > 0 else "no_rows_appended"
    return payload


def normalize_inbox_cleanup(summary_path: Path) -> RunPayload:
    if not summary_path.exists():
        return missing_source_payload("inbox_cleanup", summary_path, "Inbox Cleanup")
    summary = load_json(summary_path, {}) or {}
    payload = RunPayload(source_mode="inbox_cleanup")
    archived = safe_int(summary.get("archived_count", 0))
    remaining_open = safe_int(summary.get("remaining_open_count", 0))
    final_remaining = safe_int(summary.get("remaining_finalized_count", 0))
    payload.summary = summary
    payload.metrics.extend([
        metric("inbox.cleanup.archived_items", archived, scope="inbox_cleanup"),
        metric("inbox.cleanup.remaining_open_items", remaining_open, scope="inbox_cleanup"),
        metric("inbox.cleanup.remaining_finalized_items", final_remaining, scope="inbox_cleanup"),
    ])
    if archived:
        payload.findings.append(Finding(
            finding_type="inbox_finalized_rows_archived",
            entity_type="inbox",
            entity_id=norm(summary.get("tab_inbox", "Inbox")),
            title=f"{archived} abgeschlossene Inbox-Zeilen archiviert",
            severity="info",
            confidence="measured",
            safe_action="auto_archive_completed_inbox_rows",
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Inbox Cleanup",
            details=summary,
        ))
    payload.status = "archived_rows" if archived else "nothing_to_archive"
    return payload


def normalize_growth(summary_path: Path) -> RunPayload:
    if not summary_path.exists():
        return missing_source_payload("growth_intelligence", summary_path, "Growth Intelligence Backlog")
    summary = load_json(summary_path, {}) or {}
    payload = RunPayload(source_mode="growth_intelligence")
    created = safe_int(summary.get("items_created", 0))
    suppressed = safe_int(summary.get("items_suppressed", 0))
    feedback_rules = safe_int(summary.get("growth_feedback_rules", 0))
    feedback_suppressed_keys = summary.get("growth_feedback_suppressed_keys") or []
    if not isinstance(feedback_suppressed_keys, list):
        feedback_suppressed_keys = []
    status = norm(summary.get("status")) or "unknown"
    payload.summary = summary
    payload.metrics.extend([
        metric("growth.backlog.items_created", created, scope="growth_intelligence"),
        metric("growth.backlog.items_suppressed", suppressed, scope="growth_intelligence"),
        metric("growth.backlog.gsc_rows", summary.get("gsc_rows", 0), scope="growth_intelligence"),
        metric("growth.backlog.ga4_rows", summary.get("ga4_rows", 0), scope="growth_intelligence"),
        metric("growth.backlog.value_metric_rows", summary.get("value_rows", 0), scope="growth_intelligence"),
        metric("growth.backlog.feedback_rules", feedback_rules, scope="growth_intelligence"),
        metric("growth.backlog.feedback_suppressed_keys", len(feedback_suppressed_keys), scope="growth_intelligence"),
    ])
    if created:
        payload.findings.append(Finding(
            finding_type="growth_backlog_items_created",
            entity_type="growth_backlog",
            entity_id="Growth_Backlog",
            title=f"{created} Growth-Backlog-Signale erzeugt",
            severity="review_needed",
            confidence="measured",
            safe_action="add_backlog_signal",
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Growth Intelligence Backlog",
            details=summary,
        ))
    if feedback_rules:
        payload.rule_effects.append(RuleEffect(
            rule_key="growth_feedback:decided_backlog",
            rule_type="growth_feedback",
            rule_class="decided_backlog_suppression",
            applied_count=feedback_rules,
            prevented_count=len(feedback_suppressed_keys),
            details={"suppressed_keys": feedback_suppressed_keys[:80]},
        ))

    if status not in {"ok", ""}:
        payload.findings.append(Finding(
            finding_type="growth_intelligence_partial_run",
            entity_type="workflow",
            entity_id="growth-intelligence-backlog",
            title=f"Growth Intelligence Status: {status}",
            severity="warning",
            confidence="measured",
            safe_action="create_technical_followup_if_repeated",
            user_action_required=False,
            source_mode=payload.source_mode,
            source_workflow=norm(os.environ.get("GITHUB_WORKFLOW")) or "Growth Intelligence Backlog",
            details=summary,
        ))
    payload.status = f"growth_{status}"
    return payload


def db_config() -> Optional[Dict[str, Any]]:
    environment = env_name()
    prefixes = []
    if environment == "staging" or branch_name() == "staging":
        prefixes.append("STAGING")
    else:
        prefixes.append("LIVE")
    prefixes.extend(["BE", "DB", ""])

    def first(names: Iterable[str]) -> str:
        for name in names:
            value = norm(os.environ.get(name))
            if value:
                return value
        return ""

    host = first([f"{p}_DB_HOST" for p in prefixes if p] + ["DB_HOST"])
    name = first([f"{p}_DB_NAME" for p in prefixes if p] + ["DB_NAME"])
    user = first([f"{p}_DB_USER" for p in prefixes if p] + ["DB_USER"])
    password = first([f"{p}_DB_PASSWORD" for p in prefixes if p] + ["DB_PASSWORD"])
    port = first([f"{p}_DB_PORT" for p in prefixes if p] + ["DB_PORT"])
    if not host or not name or not user:
        return None
    return {
        "host": host,
        "database": name,
        "user": user,
        "password": password,
        "port": safe_int(port, 3306),
        "charset": "utf8mb4",
        "autocommit": True,
    }


SCHEMA_SQL = [
    """
    CREATE TABLE IF NOT EXISTS content_ops_run (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      run_fingerprint CHAR(64) NOT NULL,
      generated_at_utc DATETIME NOT NULL,
      environment VARCHAR(32) NOT NULL DEFAULT '',
      branch_name VARCHAR(64) NOT NULL DEFAULT '',
      workflow_name VARCHAR(191) NOT NULL DEFAULT '',
      github_run_id VARCHAR(64) NOT NULL DEFAULT '',
      github_run_url VARCHAR(512) NOT NULL DEFAULT '',
      source_mode VARCHAR(80) NOT NULL DEFAULT '',
      status VARCHAR(80) NOT NULL DEFAULT '',
      action_required TINYINT(1) NOT NULL DEFAULT 0,
      summary_json MEDIUMTEXT NULL,
      metrics_json MEDIUMTEXT NULL,
      findings_json MEDIUMTEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_content_ops_run_fingerprint (run_fingerprint),
      KEY idx_content_ops_run_lookup (environment, source_mode, generated_at_utc)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    """,
    """
    CREATE TABLE IF NOT EXISTS content_ops_metric_daily (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      metric_date DATE NOT NULL,
      environment VARCHAR(32) NOT NULL DEFAULT '',
      metric_key VARCHAR(160) NOT NULL DEFAULT '',
      metric_scope VARCHAR(80) NOT NULL DEFAULT '',
      dimension_key VARCHAR(191) NOT NULL DEFAULT '',
      metric_value DECIMAL(18,4) NOT NULL DEFAULT 0,
      source_mode VARCHAR(80) NOT NULL DEFAULT '',
      run_fingerprint CHAR(64) NOT NULL DEFAULT '',
      dimensions_json MEDIUMTEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_content_ops_metric_run (metric_date, environment, metric_key, metric_scope, dimension_key, source_mode, run_fingerprint),
      KEY idx_content_ops_metric_lookup (environment, metric_key, metric_date),
      KEY idx_content_ops_metric_scope (environment, metric_scope, metric_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    """,
    """
    CREATE TABLE IF NOT EXISTS content_ops_action_log (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      action_fingerprint CHAR(64) NOT NULL,
      generated_at_utc DATETIME NOT NULL,
      environment VARCHAR(32) NOT NULL DEFAULT '',
      source_mode VARCHAR(80) NOT NULL DEFAULT '',
      source_workflow VARCHAR(191) NOT NULL DEFAULT '',
      action_type VARCHAR(120) NOT NULL DEFAULT '',
      finding_type VARCHAR(191) NOT NULL DEFAULT '',
      entity_type VARCHAR(80) NOT NULL DEFAULT '',
      entity_id VARCHAR(191) NOT NULL DEFAULT '',
      title VARCHAR(255) NOT NULL DEFAULT '',
      severity VARCHAR(40) NOT NULL DEFAULT '',
      confidence VARCHAR(80) NOT NULL DEFAULT '',
      user_action_required TINYINT(1) NOT NULL DEFAULT 0,
      status VARCHAR(80) NOT NULL DEFAULT 'open',
      run_fingerprint CHAR(64) NOT NULL DEFAULT '',
      details_json MEDIUMTEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_content_ops_action (action_fingerprint, run_fingerprint),
      KEY idx_content_ops_action_lookup (environment, action_type, generated_at_utc),
      KEY idx_content_ops_action_manual (environment, user_action_required, generated_at_utc)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    """,
    """
    CREATE TABLE IF NOT EXISTS feedback_rule_effectiveness_daily (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      metric_date DATE NOT NULL,
      environment VARCHAR(32) NOT NULL DEFAULT '',
      rule_key VARCHAR(191) NOT NULL DEFAULT '',
      rule_type VARCHAR(80) NOT NULL DEFAULT '',
      rule_class VARCHAR(120) NOT NULL DEFAULT '',
      applied_count INT NOT NULL DEFAULT 0,
      prevented_count INT NOT NULL DEFAULT 0,
      recurrence_count INT NOT NULL DEFAULT 0,
      false_positive_count INT NOT NULL DEFAULT 0,
      source_mode VARCHAR(80) NOT NULL DEFAULT '',
      run_fingerprint CHAR(64) NOT NULL DEFAULT '',
      details_json MEDIUMTEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_feedback_rule_effectiveness_run (metric_date, environment, rule_key, source_mode, run_fingerprint),
      KEY idx_feedback_rule_lookup (environment, rule_type, rule_class, metric_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    """,
]


def persist_sql(payload: RunPayload) -> str:
    cfg = db_config()
    if cfg is None:
        return "skipped_db_env_missing"
    try:
        import pymysql  # type: ignore
    except Exception as exc:
        return f"skipped_pymysql_missing:{type(exc).__name__}"

    run_fp = payload.run_fingerprint()
    environment = env_name()
    generated = payload.generated_at_utc.replace("Z", "").replace("T", " ")[:19]
    metric_date = generated[:10]
    workflow = norm(os.environ.get("GITHUB_WORKFLOW"))
    run_id = norm(os.environ.get("GITHUB_RUN_ID"))
    conn = None
    try:
        conn = pymysql.connect(**cfg)
        with conn.cursor() as cur:
            for statement in SCHEMA_SQL:
                cur.execute(statement)
            cur.execute(
                """
                INSERT INTO content_ops_run
                  (run_fingerprint, generated_at_utc, environment, branch_name, workflow_name, github_run_id, github_run_url, source_mode, status, action_required, summary_json, metrics_json, findings_json)
                VALUES
                  (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                  generated_at_utc=VALUES(generated_at_utc), status=VALUES(status), action_required=VALUES(action_required), summary_json=VALUES(summary_json), metrics_json=VALUES(metrics_json), findings_json=VALUES(findings_json)
                """,
                (
                    run_fp,
                    generated,
                    environment,
                    branch_name(),
                    workflow,
                    run_id,
                    github_run_url(),
                    payload.source_mode,
                    payload.status,
                    1 if payload.action_required else 0,
                    compact_json(payload.summary),
                    compact_json([asdict(item) for item in payload.metrics]),
                    compact_json([asdict(item) for item in payload.findings[:300]]),
                ),
            )
            for item in payload.metrics:
                cur.execute(
                    """
                    INSERT INTO content_ops_metric_daily
                      (metric_date, environment, metric_key, metric_scope, dimension_key, metric_value, source_mode, run_fingerprint, dimensions_json)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON DUPLICATE KEY UPDATE metric_value=VALUES(metric_value), dimensions_json=VALUES(dimensions_json)
                    """,
                    (
                        metric_date,
                        environment,
                        item.metric_key[:160],
                        item.metric_scope[:80],
                        item.dimension_key[:191],
                        item.metric_value,
                        payload.source_mode,
                        run_fp,
                        compact_json(item.dimensions),
                    ),
                )
            for finding in payload.findings:
                action_fp = finding.fingerprint(environment)
                cur.execute(
                    """
                    INSERT INTO content_ops_action_log
                      (action_fingerprint, generated_at_utc, environment, source_mode, source_workflow, action_type, finding_type, entity_type, entity_id, title, severity, confidence, user_action_required, status, run_fingerprint, details_json)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON DUPLICATE KEY UPDATE severity=VALUES(severity), confidence=VALUES(confidence), user_action_required=VALUES(user_action_required), details_json=VALUES(details_json)
                    """,
                    (
                        action_fp,
                        generated,
                        environment,
                        payload.source_mode,
                        finding.source_workflow[:191],
                        finding.safe_action[:120],
                        finding.finding_type[:191],
                        finding.entity_type[:80],
                        finding.entity_id[:191],
                        finding.title[:255],
                        finding.severity[:40],
                        finding.confidence[:80],
                        1 if finding.user_action_required else 0,
                        "open" if finding.user_action_required else "auto_routed",
                        run_fp,
                        compact_json(finding.details),
                    ),
                )
            for effect in payload.rule_effects:
                cur.execute(
                    """
                    INSERT INTO feedback_rule_effectiveness_daily
                      (metric_date, environment, rule_key, rule_type, rule_class, applied_count, prevented_count, recurrence_count, false_positive_count, source_mode, run_fingerprint, details_json)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON DUPLICATE KEY UPDATE applied_count=VALUES(applied_count), prevented_count=VALUES(prevented_count), recurrence_count=VALUES(recurrence_count), false_positive_count=VALUES(false_positive_count), details_json=VALUES(details_json)
                    """,
                    (
                        metric_date,
                        environment,
                        effect.rule_key[:191],
                        effect.rule_type[:80],
                        effect.rule_class[:120],
                        int(effect.applied_count),
                        int(effect.prevented_count),
                        int(effect.recurrence_count),
                        int(effect.false_positive_count),
                        payload.source_mode,
                        run_fp,
                        compact_json(effect.details),
                    ),
                )
        return "persisted_sql"
    except Exception as exc:
        return f"skipped_sql_error:{type(exc).__name__}:{exc}"
    finally:
        if conn is not None:
            try:
                conn.close()
            except Exception:
                pass


def write_outputs(payload: RunPayload, output_dir: Path) -> None:
    run_fp = payload.run_fingerprint()
    result = {
        "run_fingerprint": run_fp,
        "generated_at_utc": payload.generated_at_utc,
        "environment": env_name(),
        "branch": branch_name(),
        "workflow": norm(os.environ.get("GITHUB_WORKFLOW")),
        "github_run_id": norm(os.environ.get("GITHUB_RUN_ID")),
        "github_run_url": github_run_url(),
        "source_mode": payload.source_mode,
        "status": payload.status,
        "action_required": payload.action_required,
        "summary": payload.summary,
        "metrics": [asdict(item) for item in payload.metrics],
        "findings": [asdict(item) for item in payload.findings],
        "rule_effects": [asdict(item) for item in payload.rule_effects],
    }
    output_dir.mkdir(parents=True, exist_ok=True)
    write_json(output_dir / f"{payload.source_mode}-latest.json", result)
    write_json(output_dir / "latest.json", result)


def write_step_summary(payload: RunPayload, sql_status: str) -> None:
    lines = [
        "## Content Ops Decision & Impact",
        "",
        f"- source_mode: `{payload.source_mode}`",
        f"- status: `{payload.status}`",
        f"- action_required: `{str(payload.action_required).lower()}`",
        f"- metrics: `{len(payload.metrics)}`",
        f"- findings: `{len(payload.findings)}`",
        f"- rule_effects: `{len(payload.rule_effects)}`",
        f"- sql: `{sql_status}`",
        "",
        "### Key summary",
        "",
        "```json",
        json.dumps(payload.summary, ensure_ascii=False, indent=2)[:8000],
        "```",
    ]
    text = "\n".join(lines) + "\n"
    path = norm(os.environ.get("GITHUB_STEP_SUMMARY"))
    if path:
        with open(path, "a", encoding="utf-8") as handle:
            handle.write(text)
    print(text)


def resolve_payload(args: argparse.Namespace) -> RunPayload:
    if args.mode == "record-audit":
        return normalize_content_audit(ROOT / args.report_json)
    if args.mode == "record-weekly-ki":
        return normalize_weekly_ki(ROOT / args.weekly_diagnostics_json, ROOT / args.manual_json)
    if args.mode == "record-manual-intake":
        return normalize_manual_intake(ROOT / args.manual_intake_summary_json)
    if args.mode == "record-inbox-cleanup":
        return normalize_inbox_cleanup(ROOT / args.inbox_cleanup_summary_json)
    if args.mode == "record-growth":
        return normalize_growth(ROOT / args.growth_summary_json)
    raise SystemExit(f"Unsupported mode: {args.mode}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Bocholt erleben Content Ops Decision & Impact Engine")
    parser.add_argument("mode", choices=["record-audit", "record-weekly-ki", "record-manual-intake", "record-inbox-cleanup", "record-growth"])
    parser.add_argument("--report-json", default="data/content-quality-report.json")
    parser.add_argument("--weekly-diagnostics-json", default=".tmp/weekly-ki-eventsuche/weekly_event_diagnostics.json")
    parser.add_argument("--manual-json", default="data/inbox_manual.json")
    parser.add_argument("--manual-intake-summary-json", default="data/manual-ki-intake-summary.json")
    parser.add_argument("--inbox-cleanup-summary-json", default="data/inbox-cleanup-summary.json")
    parser.add_argument("--growth-summary-json", default="data/growth-intelligence-summary.json")
    parser.add_argument("--output-dir", default=str(DEFAULT_OUTPUT_DIR.relative_to(ROOT)))
    parser.add_argument("--fail-on-sql-error", action="store_true")
    args = parser.parse_args()

    payload = resolve_payload(args)
    output_dir = ROOT / args.output_dir
    write_outputs(payload, output_dir)
    sql_status = persist_sql(payload)
    write_step_summary(payload, sql_status)
    if args.fail_on_sql_error and sql_status.startswith("skipped_sql_error"):
        raise SystemExit(sql_status)


if __name__ == "__main__":
    main()
# === END FILE: scripts/content-ops-control.py ===
