#!/usr/bin/env python3
# === BEGIN BLOCK: CONTENT_QUALITY_GUARD_V1 | Zweck: prueft Sheet-Events, DB-Events und Activities auf harte Inhalts-/Runtime-Risiken; Umfang: lokaler Audit ohne Google-Sheet-Schreibzugriff ===
from __future__ import annotations

import argparse
import csv
import hashlib
import html
import json
import re
import socket
import ssl
import sys
from dataclasses import asdict, dataclass
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple
from urllib.error import HTTPError, URLError
from urllib.parse import urlparse
from urllib.request import Request, urlopen

from event_visual_keys import infer_event_visual_key, normalize_event_visual_key
from event_visual_motifs import (
    event_visual_motif_role,
    infer_event_visual_fit,
    normalize_event_visual_motif,
)

ROOT = Path(__file__).resolve().parents[1]
RE_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
RE_TIME = re.compile(r"^\s*(\d{1,2})[:.](\d{2})(?:\s*[-–]\s*(\d{1,2})[:.](\d{2}))?\s*$")
HTTP_TIMEOUT_SECONDS = 12
SOURCE_TEXT_TIMEOUT_SECONDS = 10
SOURCE_TEXT_MAX_BYTES = 350_000

GERMAN_MONTHS = {
    1: ("januar", "jan"),
    2: ("februar", "feb"),
    3: ("märz", "maerz", "mrz"),
    4: ("april", "apr"),
    5: ("mai",),
    6: ("juni", "jun"),
    7: ("juli", "jul"),
    8: ("august", "aug"),
    9: ("september", "sep"),
    10: ("oktober", "okt"),
    11: ("november", "nov"),
    12: ("dezember", "dez"),
}

CONTENT_STOPWORDS = {
    "und", "oder", "der", "die", "das", "den", "dem", "des", "ein", "eine", "einer", "eines",
    "mit", "für", "fuer", "von", "vom", "zur", "zum", "auf", "bei", "am", "im", "in", "an",
    "open", "air", "show", "concert", "the", "gold", "bocholt", "borken", "rhede", "ahaus",
    "veranstaltung", "event", "live", "2026", "2025",
}

GENERIC_LOCATION_WORDS = {
    "innenstadt", "stadt", "zentrum", "city", "marktplatz", "markt", "anlage", "halle",
    "bocholt", "borken", "rhede", "ahaus", "isselburg", "wesel", "reken", "hamminkeln",
}


SOURCE_TEXT_CACHE: Dict[str, Tuple[str, str, str]] = {}

SEVERITY_RANK = {
    "critical": 0,
    "review_needed": 1,
    "warning": 2,
    "auto_fixed": 3,
    "ok": 4,
}

EVENT_REQUIRED_FIELDS = ["id", "title", "date", "city", "location", "kategorie"]
ACTIVITY_REQUIRED_FIELDS = ["id", "title", "location", "description", "url", "visual_key", "opening_status"]
SAFE_IMAGE_STATUSES = {"ready", "usable", "fallback"}
TICKET_PORTAL_HOSTS = (
    "reservix.de",
    "eventim.de",
    "ticket.io",
    "adticket.de",
    "pretix.eu",
    "rausgegangen.de",
)

SOURCE_SUGGESTIONS_PATH = ROOT / "data/content_source_suggestions.json"
AI_VERIFICATION_CACHE_DAYS = 21
AI_VERIFICATION_ACTIVITY_CACHE_DAYS = 60
AI_VERIFICATION_DEFAULT_MAX_CANDIDATES = 12


VERIFICATION_CACHE: Dict[str, Dict[str, str]] = {}
VERIFICATION_STATS: Dict[str, int] = {
    "cache_entries_loaded": 0,
    "cache_hits": 0,
    "cache_stale": 0,
    "ai_candidates_total": 0,
    "ai_candidates_selected": 0,
    "ai_candidates_deferred_by_budget": 0,
}
AI_VERIFICATION_CANDIDATES: List[Dict[str, Any]] = []


def log_checkpoint(message: str) -> None:
    print(f"[content-quality] {datetime.now().isoformat(timespec='seconds')} {message}", flush=True)


def url_host(value: str) -> str:
    return (urlparse(norm(value)).netloc or "").lower().removeprefix("www.")


def is_ticket_portal_url(value: str) -> bool:
    host = url_host(value)
    return any(host == portal or host.endswith("." + portal) for portal in TICKET_PORTAL_HOSTS)


def normalized_url_parts(value: str) -> tuple[str, str, str]:
    parsed = urlparse(norm(value))
    host = (parsed.netloc or "").lower().removeprefix("www.")
    path = re.sub(r"/{2,}", "/", parsed.path or "/")
    if path != "/":
        path = path.rstrip("/")
    query = parsed.query or ""
    return host, path, query


def strip_benign_path_prefix(path: str) -> str:
    value = path or "/"
    for prefix in ("/de", "/de-de", "/deutsch"):
        if value == prefix:
            return "/"
        if value.startswith(prefix + "/"):
            return value[len(prefix):] or "/"
    return value


def parse_redirect_target(detail: str) -> str:
    match = re.search(r"->\s*(\S+)", norm(detail))
    return match.group(1) if match else ""


def is_benign_redirect(source_url: str, detail: str) -> bool:
    target_url = parse_redirect_target(detail)
    if not target_url:
        return False
    source_host, source_path, source_query = normalized_url_parts(source_url)
    target_host, target_path, target_query = normalized_url_parts(target_url)
    if not source_host or source_host != target_host:
        return False

    source_clean = strip_benign_path_prefix(source_path)
    target_clean = strip_benign_path_prefix(target_path)
    if source_clean != target_clean:
        return False

    # Tracking- und Sortierparameter gelten nicht als redaktioneller Arbeitsfall.
    ignored_params = ("utm_", "mtm_", "fbclid", "gclid")
    def relevant_query(raw: str) -> list[str]:
        if not raw:
            return []
        parts = []
        for part in raw.split("&"):
            key = part.split("=", 1)[0].lower()
            if key.startswith(ignored_params) or key in ignored_params:
                continue
            parts.append(part)
        return sorted(parts)

    return relevant_query(source_query) == relevant_query(target_query)


def is_transient_network_warning(detail: str) -> bool:
    value = norm(detail).lower()
    transient_markers = (
        "timeouterror",
        "timed out",
        "temporarily unavailable",
        "temporary failure",
        "connection reset",
        "connection aborted",
        "remote end closed",
    )
    hard_markers = (
        "certificate_verify_failed",
        "hostname mismatch",
        "ssl:",
    )
    if any(marker in value for marker in hard_markers):
        return False
    return any(marker in value for marker in transient_markers)


@dataclass
class Issue:
    severity: str
    status: str
    content_type: str
    source_system: str
    process_category: str
    correction_owner: str
    workbench_group: str
    automation_policy: str
    content_id: str
    title: str
    date: str
    issue_code: str
    issue_text: str
    recommended_action: str
    source_url: str
    suggested_url: str
    suggested_url_label: str
    suggestion_reason: str
    suggested_visual_key: str
    suggested_visual_motif: str
    suggested_visual_motif_role: str
    visual_asset_status: str
    evidence_status: str
    evidence_summary: str
    evidence_checked_fields: str
    evidence_missing_fields: str
    evidence_field_statuses: str
    verification_key: str
    verification_status: str
    verified_by: str
    last_verified_at: str
    verified_until: str
    source_fingerprint: str
    content_fingerprint: str
    next_check_at: str
    ai_candidate_priority: str
    ai_candidate_reason: str
    public_url: str
    auto_fix_allowed: str
    auto_fix_done: str


@dataclass
class Observation:
    content_type: str
    source_system: str
    content_id: str
    title: str
    date: str
    check_code: str
    check_status: str
    workbench_group: str
    checked_url: str
    checked_fields: str
    missing_fields: str
    summary: str


OBSERVATIONS: List[Observation] = []


def infer_process_metadata(
    *,
    severity: str,
    content_type: str,
    source_system: str,
    issue_code: str,
    auto_fix_allowed: bool,
    auto_fix_done: bool,
) -> Dict[str, str]:
    """Classify issues by work route, not only by severity.

    Zielbild:
    - sichere technische Faelle verschwinden als auto_resolved aus der Arbeit,
    - echte Repo-Datenluecken werden als bewusstes Patch-Paket gebuendelt,
    - Quellen-/Retry-Faelle werden erst geprueft und nicht vorschnell gepatcht,
    - Visual-Fit bleibt ein eigener Workstream.
    """
    code = norm(issue_code)
    ctype = norm(content_type)
    source = norm(source_system)

    if severity == "auto_fixed" or auto_fix_done:
        return {
            "process_category": "auto_resolved",
            "correction_owner": "audit_script",
            "workbench_group": "Automatisch erledigt",
            "automation_policy": "safe_auto_resolved",
        }

    ai_verification_codes = {
        "event_ai_verification_candidate",
        "activity_ai_verification_candidate",
    }
    if code in ai_verification_codes:
        return {
            "process_category": "ai_verification_candidate",
            "correction_owner": "ai_fact_check_fallback",
            "workbench_group": "KI-Faktencheck",
            "automation_policy": "ai_fallback_budgeted_no_auto_write",
        }

    fact_check_codes = {
        "event_source_facts_not_confirmed",
        "event_source_has_time_but_dataset_missing_time",
        "activity_source_facts_not_confirmed",
    }
    if code in fact_check_codes:
        return {
            "process_category": "fact_check_candidate",
            "correction_owner": "content_inbox_fact_check",
            "workbench_group": "Faktencheck",
            "automation_policy": "human_review_before_write",
        }

    visual_markers = (
        "visual",
        "image",
        "bild",
        "motif",
    )
    if any(marker in code for marker in visual_markers):
        return {
            "process_category": "visual_fit_candidate",
            "correction_owner": "visual_workflow",
            "workbench_group": "Visual-Fit",
            "automation_policy": "human_decision_required",
        }

    retry_codes = {
        "activity_source_url_unstable",
        "event_source_url_unstable",
    }
    if code in retry_codes:
        return {
            "process_category": "source_retry_observation",
            "correction_owner": "audit_retry",
            "workbench_group": "Beobachten / Retry",
            "automation_policy": "retry_before_human_decision",
        }

    source_review_codes = {
        "activity_source_url_redirect",
        "activity_source_url_broken",
        "activity_source_missing",
        "activity_source_url_invalid",
        "event_source_url_redirect",
        "event_source_url_broken",
        "event_source_url_missing",
        "event_source_url_invalid",
    }
    if code in source_review_codes:
        return {
            "process_category": "source_review_candidate",
            "correction_owner": "content_inbox_source_check",
            "workbench_group": "Quellenprüfung",
            "automation_policy": "human_review_before_write",
        }

    activity_review_codes = {
        "activity_checked_at_missing_or_invalid",
        "activity_check_too_old",
        "activity_check_aging",
    }
    if code in activity_review_codes:
        return {
            "process_category": "activity_review_candidate",
            "correction_owner": "content_inbox_activity_check",
            "workbench_group": "Activity-Prüfung",
            "automation_policy": "human_review_before_repo_patch",
        }

    repo_data_patch_codes = {
        "activity_required_field_missing",
        "activity_duplicate_id",
        "activities_json_invalid",
    }
    if source == "offers_json" and code in repo_data_patch_codes:
        return {
            "process_category": "repo_data_patch_candidate",
            "correction_owner": "repo_patch",
            "workbench_group": "Repo-Datenpatch",
            "automation_policy": "proposal_only",
        }

    if source == "offers_json":
        return {
            "process_category": "activity_review_candidate",
            "correction_owner": "content_inbox_activity_check",
            "workbench_group": "Activity-Prüfung",
            "automation_policy": "human_review_before_repo_patch",
        }

    if source == "public_db_events_api":
        return {
            "process_category": "db_review_candidate",
            "correction_owner": "db_review",
            "workbench_group": "DB-/Anbieter-Review",
            "automation_policy": "proposal_only",
        }

    if ctype == "event" and source in {"sheet_events", "runtime_events_json"}:
        if "ticket" in code or "source_url" in code or code in {"event_source_url_missing", "event_source_url_invalid"}:
            return {
                "process_category": "sheet_update_candidate",
                "correction_owner": "content_inbox_to_sheet",
                "workbench_group": "Sheet-/Quellenkorrektur",
                "automation_policy": "human_review_before_write",
            }
        return {
            "process_category": "sheet_update_candidate",
            "correction_owner": "content_inbox_to_sheet",
            "workbench_group": "Sheet-Event-Korrektur",
            "automation_policy": "human_review_before_write",
        }

    return {
        "process_category": "needs_human_decision",
        "correction_owner": "content_inbox",
        "workbench_group": "Entscheidung nötig",
        "automation_policy": "human_decision_required",
    }

def norm(value: Any) -> str:
    if value is None:
        return ""
    return str(value).replace("\u00a0", " ").strip()


def norm_key(value: Any) -> str:
    return re.sub(r"\s+", " ", norm(value).lower())


def parse_iso_date(value: str) -> Optional[date]:
    value = norm(value)
    if not value or not RE_DATE.match(value):
        return None
    try:
        return datetime.strptime(value, "%Y-%m-%d").date()
    except ValueError:
        return None


def is_http_url(value: str) -> bool:
    parsed = urlparse(norm(value))
    return parsed.scheme in {"http", "https"} and bool(parsed.netloc)


def is_local_path(value: str) -> bool:
    value = norm(value)
    return value.startswith("/") and not value.startswith("//")


def local_path_exists(path_value: str) -> bool:
    value = norm(path_value).split("?", 1)[0]
    if not is_local_path(value):
        return False
    return (ROOT / value.lstrip("/")).exists()


def issue(
    severity: str,
    content_type: str,
    source_system: str,
    content_id: str,
    title: str,
    issue_code: str,
    issue_text: str,
    recommended_action: str,
    *,
    date_value: str = "",
    source_url: str = "",
    suggested_url: str = "",
    suggested_url_label: str = "",
    suggestion_reason: str = "",
    suggested_visual_key: str = "",
    suggested_visual_motif: str = "",
    suggested_visual_motif_role: str = "",
    visual_asset_status: str = "",
    evidence_status: str = "",
    evidence_summary: str = "",
    evidence_checked_fields: str = "",
    evidence_missing_fields: str = "",
    evidence_field_statuses: str = "",
    verification_key: str = "",
    verification_status: str = "",
    verified_by: str = "",
    last_verified_at: str = "",
    verified_until: str = "",
    source_fingerprint: str = "",
    content_fingerprint: str = "",
    next_check_at: str = "",
    ai_candidate_priority: str = "",
    ai_candidate_reason: str = "",
    public_url: str = "",
    auto_fix_allowed: bool = False,
    auto_fix_done: bool = False,
) -> Issue:
    process = infer_process_metadata(
        severity=severity,
        content_type=content_type,
        source_system=source_system,
        issue_code=issue_code,
        auto_fix_allowed=auto_fix_allowed,
        auto_fix_done=auto_fix_done,
    )
    return Issue(
        severity=severity,
        status="open" if severity != "auto_fixed" else "done",
        content_type=content_type,
        source_system=source_system,
        process_category=process["process_category"],
        correction_owner=process["correction_owner"],
        workbench_group=process["workbench_group"],
        automation_policy=process["automation_policy"],
        content_id=norm(content_id),
        title=norm(title),
        date=norm(date_value),
        issue_code=issue_code,
        issue_text=issue_text,
        recommended_action=recommended_action,
        source_url=norm(source_url),
        suggested_url=norm(suggested_url),
        suggested_url_label=norm(suggested_url_label),
        suggestion_reason=norm(suggestion_reason),
        suggested_visual_key=norm(suggested_visual_key),
        suggested_visual_motif=norm(suggested_visual_motif),
        suggested_visual_motif_role=norm(suggested_visual_motif_role),
        visual_asset_status=norm(visual_asset_status),
        evidence_status=norm(evidence_status),
        evidence_summary=norm(evidence_summary),
        evidence_checked_fields=norm(evidence_checked_fields),
        evidence_missing_fields=norm(evidence_missing_fields),
        evidence_field_statuses=norm(evidence_field_statuses),
        verification_key=norm(verification_key),
        verification_status=norm(verification_status),
        verified_by=norm(verified_by),
        last_verified_at=norm(last_verified_at),
        verified_until=norm(verified_until),
        source_fingerprint=norm(source_fingerprint),
        content_fingerprint=norm(content_fingerprint),
        next_check_at=norm(next_check_at),
        ai_candidate_priority=norm(ai_candidate_priority),
        ai_candidate_reason=norm(ai_candidate_reason),
        public_url=norm(public_url),
        auto_fix_allowed="yes" if auto_fix_allowed else "no",
        auto_fix_done="yes" if auto_fix_done else "no",
    )


def load_json(path: Path, *, required: bool = True) -> Any:
    if not path.exists():
        if required:
            raise FileNotFoundError(f"JSON fehlt: {path}")
        return None
    return json.loads(path.read_text(encoding="utf-8"))




def load_source_suggestions(path: Path = SOURCE_SUGGESTIONS_PATH) -> Dict[str, Dict[str, str]]:
    """Load curated source suggestions for cases the audit cannot search safely.

    The file is not a data-fix source. It only lets the workbench propose
    a reviewed official source so the user does not have to research obvious
    replacements manually. Actual event data is changed only after inbox review.
    """
    data = load_json(path, required=False) or {}
    raw_events = data.get("events") if isinstance(data, dict) else {}
    if not isinstance(raw_events, dict):
        return {}

    out: Dict[str, Dict[str, str]] = {}
    for key, item in raw_events.items():
        if not isinstance(item, dict):
            continue
        url = norm(item.get("suggested_url"))
        if not is_http_url(url):
            continue
        out[norm(key)] = {
            "suggested_url": url,
            "suggested_url_label": norm(item.get("label") or item.get("suggested_url_label")),
            "suggestion_reason": norm(item.get("reason") or item.get("suggestion_reason")),
        }
    return out


def source_suggestion_for_event(row: Dict[str, str], suggestions: Dict[str, Dict[str, str]]) -> Dict[str, str]:
    content_id = norm(row.get("id") or row.get("event_id") or row.get("submission_id"))
    title_key = norm_key(row.get("title"))

    for key in (content_id, title_key):
        if key and key in suggestions:
            return suggestions[key]

    return {"suggested_url": "", "suggested_url_label": "", "suggestion_reason": ""}


def stable_hash(parts: Iterable[Any]) -> str:
    payload = "\n".join(norm(part) for part in parts)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:24]


def verification_key(content_type: str, source_system: str, content_id: str, source_url: str) -> str:
    return stable_hash([content_type, source_system, content_id, normalized_url_parts(source_url) if source_url else ""])


def source_fingerprint_for_url(source_url: str) -> str:
    host, path, query = normalized_url_parts(source_url)
    return stable_hash([host, path, query]) if host else ""


def event_content_fingerprint(row: Dict[str, str], source_url: str) -> str:
    return stable_hash([
        row.get("id") or row.get("event_id") or row.get("submission_id"),
        row.get("title"),
        row.get("date") or row.get("start_date"),
        row.get("endDate") or row.get("end_date"),
        row.get("time"),
        row.get("city"),
        row.get("location"),
        row.get("address"),
        row.get("kategorie"),
        row.get("source_url") or row.get("url") or row.get("event_url") or source_url,
    ])


def activity_content_fingerprint(offer: Dict[str, Any], source_url: str) -> str:
    opening_status = offer.get("opening_status") if isinstance(offer.get("opening_status"), dict) else {}
    return stable_hash([
        offer.get("id"),
        offer.get("title"),
        offer.get("location"),
        offer.get("description"),
        source_url,
        opening_status.get("type"),
        opening_status.get("public_label"),
        opening_status.get("detail_note"),
        opening_status.get("source_url"),
    ])


def load_verification_cache(path: Path) -> Dict[str, Dict[str, str]]:
    data = load_json(path, required=False)
    items: list[Any]
    if isinstance(data, dict):
        raw_items = data.get("items") or data.get("cache") or data.get("rows") or []
        items = raw_items if isinstance(raw_items, list) else []
    elif isinstance(data, list):
        items = data
    else:
        items = []

    out: Dict[str, Dict[str, str]] = {}
    for item in items:
        if not isinstance(item, dict):
            continue
        key = norm(item.get("verification_key"))
        if not key:
            key = verification_key(
                norm(item.get("content_type")),
                norm(item.get("source_system")),
                norm(item.get("content_id")),
                norm(item.get("source_url")),
            )
        if not key:
            continue
        out[key] = {str(k): norm(v) for k, v in item.items()}
        out[key]["verification_key"] = key
    return out


def parse_cache_date(value: str) -> Optional[date]:
    return parse_iso_date(norm(value)[:10])


def verification_cache_match(key: str, source_fingerprint: str, content_fingerprint: str, today: date) -> Dict[str, str]:
    entry = VERIFICATION_CACHE.get(key) or {}
    if not entry:
        return {}

    status = norm(entry.get("verification_status"))
    verified_until = parse_cache_date(entry.get("verified_until", ""))
    source_ok = norm(entry.get("source_fingerprint")) == norm(source_fingerprint)
    content_ok = norm(entry.get("content_fingerprint")) == norm(content_fingerprint)
    if status == "confirmed" and verified_until and verified_until >= today and source_ok and content_ok:
        VERIFICATION_STATS["cache_hits"] += 1
        return entry

    VERIFICATION_STATS["cache_stale"] += 1
    return {}


def is_ai_fallback_url_detail(detail: str) -> bool:
    value = norm(detail).lower()
    markers = (
        "429",
        "403",
        "401",
        "bot",
        "captcha",
        "cloudflare",
        "forbidden",
        "too many requests",
        "access denied",
        "source_text_unavailable",
        "no_text",
        "timed out",
        "timeouterror",
        "url_check_unknown",
    )
    return any(marker in value for marker in markers)


def ai_priority_for_event(date_value: str, reason: str, today: date) -> int:
    day = parse_iso_date(date_value)
    if day is None:
        return 70
    days_until = (day - today).days
    reason_norm = norm(reason).lower()
    if "ticket" in reason_norm or "conflict" in reason_norm:
        bonus = 15
    elif "429" in reason_norm or "bot" in reason_norm or "403" in reason_norm:
        bonus = 10
    else:
        bonus = 0
    if days_until < 0:
        return 0
    if days_until <= 7:
        return 100 + bonus
    if days_until <= 14:
        return 90 + bonus
    if days_until <= 30:
        return 75 + bonus
    return 45 + bonus


def ai_priority_label(priority: int) -> str:
    if priority >= 100:
        return "p0_near_term"
    if priority >= 85:
        return "p1_soon"
    if priority >= 65:
        return "p2_medium"
    return "p3_low"


def add_ai_verification_candidate(
    *,
    content_type: str,
    source_system: str,
    content_id: str,
    title: str,
    date_value: str = "",
    source_url: str,
    reason: str,
    current_data: Dict[str, Any],
    source_fingerprint: str,
    content_fingerprint: str,
    priority: int,
    today: date,
    evidence_status: str = "",
    evidence_summary: str = "",
    evidence_checked_fields: str = "",
    evidence_missing_fields: str = "",
    evidence_field_statuses: str = "",
) -> Dict[str, str]:
    key = verification_key(content_type, source_system, content_id, source_url)
    cache_entry = verification_cache_match(key, source_fingerprint, content_fingerprint, today)
    if cache_entry:
        add_observation(
            content_type=content_type,
            source_system=source_system,
            content_id=content_id,
            title=title,
            date_value=date_value,
            check_code="ai_verification_cache",
            check_status="cache_hit_confirmed",
            workbench_group="KI-Faktencheck",
            checked_url=source_url,
            checked_fields=current_data.keys(),
            summary=f"Frische KI-Bestätigung im Prüfcache bis {cache_entry.get('verified_until', '')}; kein erneuter KI-Faktencheck.",
        )
        return {
            "cache_hit": "yes",
            "verification_key": key,
            "verification_status": cache_entry.get("verification_status", "confirmed"),
            "verified_by": cache_entry.get("verified_by", "ai"),
            "last_verified_at": cache_entry.get("last_verified_at", ""),
            "verified_until": cache_entry.get("verified_until", ""),
            "source_fingerprint": source_fingerprint,
            "content_fingerprint": content_fingerprint,
            "next_check_at": cache_entry.get("next_check_at", cache_entry.get("verified_until", "")),
            "ai_candidate_priority": ai_priority_label(priority),
            "ai_candidate_reason": reason,
        }

    VERIFICATION_STATS["ai_candidates_total"] += 1
    candidate = {
        "verification_key": key,
        "priority": priority,
        "priority_label": ai_priority_label(priority),
        "reason": norm(reason),
        "content_type": norm(content_type),
        "source_system": norm(source_system),
        "content_id": norm(content_id),
        "title": norm(title),
        "date": norm(date_value),
        "source_url": norm(source_url),
        "source_fingerprint": norm(source_fingerprint),
        "content_fingerprint": norm(content_fingerprint),
        "evidence_status": norm(evidence_status),
        "evidence_summary": norm(evidence_summary),
        "evidence_checked_fields": norm(evidence_checked_fields),
        "evidence_missing_fields": norm(evidence_missing_fields),
        "evidence_field_statuses": norm(evidence_field_statuses),
        "current_data": current_data,
        "expected_result_schema": {
            "verification_status": ["confirmed", "conflict", "better_source_found", "not_found", "uncertain"],
            "verified_until": "YYYY-MM-DD",
            "verification_reason": "short factual explanation",
            "better_source_url": "optional official source URL",
            "field_findings": "object keyed by checked field",
        },
    }
    AI_VERIFICATION_CANDIDATES.append(candidate)
    return {
        "cache_hit": "no",
        "verification_key": key,
        "verification_status": "candidate",
        "verified_by": "",
        "last_verified_at": "",
        "verified_until": "",
        "source_fingerprint": source_fingerprint,
        "content_fingerprint": content_fingerprint,
        "next_check_at": today.isoformat(),
        "ai_candidate_priority": ai_priority_label(priority),
        "ai_candidate_reason": reason,
    }


def selected_ai_candidates(limit: int) -> list[Dict[str, Any]]:
    limit = max(0, limit)
    unique: Dict[str, Dict[str, Any]] = {}
    for item in AI_VERIFICATION_CANDIDATES:
        key = norm(item.get("verification_key"))
        if not key:
            continue
        previous = unique.get(key)
        if previous is None or int(item.get("priority") or 0) > int(previous.get("priority") or 0):
            unique[key] = item
    ordered = sorted(unique.values(), key=lambda item: (-int(item.get("priority") or 0), norm(item.get("date")), norm(item.get("title"))))
    selected = ordered[:limit] if limit else []
    VERIFICATION_STATS["ai_candidates_selected"] = len(selected)
    VERIFICATION_STATS["ai_candidates_deferred_by_budget"] = max(0, len(ordered) - len(selected))
    return selected


def write_ai_candidates_report(path: Path, candidates: list[Dict[str, Any]], meta: Dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "meta": meta,
        "verification_summary": dict(VERIFICATION_STATS),
        "candidates": candidates,
    }
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def read_tsv(path: Path) -> List[Dict[str, str]]:
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle, delimiter="\t")
        if not reader.fieldnames:
            return []
        reader.fieldnames = [(name or "").lstrip("\ufeff").strip() for name in reader.fieldnames]
        rows: List[Dict[str, str]] = []
        for raw in reader:
            row = {(key or "").lstrip("\ufeff").strip(): norm(value) for key, value in raw.items()}
            if any(row.values()):
                rows.append(row)
        return rows


def load_events(events_tsv: Path, events_json: Path) -> Tuple[List[Dict[str, str]], str]:
    rows = read_tsv(events_tsv)
    if rows:
        return rows, "sheet_events"

    data = load_json(events_json, required=False)
    if isinstance(data, list):
        return [{str(k): norm(v) for k, v in item.items()} for item in data if isinstance(item, dict)], "runtime_events_json"

    return [], "sheet_events"


def load_db_events(path: Path) -> List[Dict[str, str]]:
    data = load_json(path, required=False)
    if data is None:
        return []
    if isinstance(data, list):
        raw_items = data
    elif isinstance(data, dict):
        raw_items = data.get("events") or data.get("items") or data.get("data") or []
    else:
        raw_items = []
    out: List[Dict[str, str]] = []
    for item in raw_items:
        if isinstance(item, dict):
            out.append({str(k): norm(v) for k, v in item.items()})
    return out


def load_visual_pools(path: Path) -> Dict[str, Dict[str, Any]]:
    data = load_json(path, required=False) or {}
    pools = data.get("pools") if isinstance(data, dict) else {}
    return pools if isinstance(pools, dict) else {}


def pool_has_safe_image(pool: Dict[str, Any]) -> bool:
    images = pool.get("images") if isinstance(pool, dict) else []
    if not isinstance(images, list):
        return False
    return any(isinstance(img, dict) and norm(img.get("status")) in SAFE_IMAGE_STATUSES for img in images)


def check_url(url: str) -> Tuple[str, str]:
    """Returns (status, detail). status: ok|redirect|warning|critical."""
    url = norm(url)
    if not url:
        return "warning", "url_empty"
    if not is_http_url(url):
        return "warning", "url_not_http"

    headers = {
        "User-Agent": "Bocholt-Erleben-ContentQualityGuard/1.0 (+https://bocholt-erleben.de)",
        "Accept": "text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8",
    }

    for method in ("HEAD", "GET"):
        try:
            request = Request(url, method=method, headers=headers)
            with urlopen(request, timeout=HTTP_TIMEOUT_SECONDS, context=ssl.create_default_context()) as response:
                code = getattr(response, "status", 0) or 0
                final_url = response.geturl()
                if 200 <= code < 400:
                    if final_url and final_url.rstrip("/") != url.rstrip("/"):
                        return "redirect", f"{code} -> {final_url}"
                    return "ok", str(code)
                if 400 <= code < 500:
                    return "critical", str(code)
                if code >= 500:
                    return "warning", str(code)
        except HTTPError as exc:
            if method == "HEAD" and exc.code in {403, 405, 406}:
                continue
            if 400 <= exc.code < 500:
                return "critical", str(exc.code)
            return "warning", str(exc.code)
        except (TimeoutError, socket.timeout, URLError, OSError, ssl.SSLError) as exc:
            if method == "HEAD":
                continue
            return "warning", f"{type(exc).__name__}: {exc}"

    return "warning", "url_check_unknown"


def text_probe_url(url: str) -> Tuple[str, str, str]:
    """Fetch readable page text for evidence checks without changing data.

    Returns (status, detail, text). status: ok|warning|critical.
    This is deliberately separate from check_url: a source may be reachable but
    not text-readable by the bot. That should usually be an observation, not an
    immediate user task.
    """
    url = norm(url)
    if not is_http_url(url):
        return "warning", "url_not_http", ""
    if url in SOURCE_TEXT_CACHE:
        return SOURCE_TEXT_CACHE[url]

    headers = {
        "User-Agent": "Bocholt-Erleben-ContentEvidenceProbe/1.0 (+https://bocholt-erleben.de)",
        "Accept": "text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.7",
    }
    try:
        request = Request(url, method="GET", headers=headers)
        with urlopen(request, timeout=SOURCE_TEXT_TIMEOUT_SECONDS, context=ssl.create_default_context()) as response:
            code = getattr(response, "status", 0) or 0
            raw = response.read(SOURCE_TEXT_MAX_BYTES)
            content_type = (response.headers.get("content-type") or "").lower()
            charset_match = re.search(r"charset=([^;]+)", content_type)
            charset = charset_match.group(1).strip() if charset_match else "utf-8"
            try:
                body = raw.decode(charset, errors="ignore")
            except LookupError:
                body = raw.decode("utf-8", errors="ignore")
            text = html_to_text(body)
            status = "ok" if 200 <= code < 400 and text else "warning"
            detail = str(code) if text else f"{code}: no_text"
            SOURCE_TEXT_CACHE[url] = (status, detail, text)
            return SOURCE_TEXT_CACHE[url]
    except HTTPError as exc:
        result = ("critical" if 400 <= exc.code < 500 else "warning", str(exc.code), "")
    except (TimeoutError, socket.timeout, URLError, OSError, ssl.SSLError) as exc:
        result = ("warning", f"{type(exc).__name__}: {exc}", "")
    SOURCE_TEXT_CACHE[url] = result
    return result


def html_to_text(value: str) -> str:
    value = re.sub(r"<script\b[^>]*>.*?</script>", " ", value, flags=re.I | re.S)
    value = re.sub(r"<style\b[^>]*>.*?</style>", " ", value, flags=re.I | re.S)
    value = re.sub(r"<[^>]+>", " ", value)
    value = html.unescape(value)
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def evidence_text(value: str) -> str:
    text = norm(value).lower()
    replacements = {
        "ä": "ae",
        "ö": "oe",
        "ü": "ue",
        "ß": "ss",
        "–": "-",
        "—": "-",
    }
    for src, target in replacements.items():
        text = text.replace(src, target)
    return re.sub(r"\s+", " ", text)


def significant_tokens(value: str, *, min_len: int = 4, max_tokens: int = 8) -> list[str]:
    text = evidence_text(value)
    raw = re.findall(r"[a-z0-9][a-z0-9-]+", text)
    tokens: list[str] = []
    for token in raw:
        token = token.strip("-")
        if len(token) < min_len or token in CONTENT_STOPWORDS:
            continue
        if token not in tokens:
            tokens.append(token)
        if len(tokens) >= max_tokens:
            break
    return tokens


def text_has_tokens(text: str, tokens: list[str], *, minimum: int = 1) -> bool:
    haystack = evidence_text(text)
    hits = 0
    for token in tokens:
        if token and token in haystack:
            hits += 1
        if hits >= minimum:
            return True
    return False


def date_variants(value: str) -> list[str]:
    parsed = parse_iso_date(value)
    if parsed is None:
        return []
    day = parsed.day
    month = parsed.month
    year = parsed.year
    variants = [
        f"{year}-{month:02d}-{day:02d}",
        f"{day:02d}.{month:02d}.{year}",
        f"{day}.{month}.{year}",
        f"{day:02d}/{month:02d}/{year}",
        f"{day}/{month}/{year}",
    ]
    for month_name in GERMAN_MONTHS.get(month, ()):  # includes Dutch-compatible names for most months used here
        variants.extend([
            f"{day}. {month_name} {year}",
            f"{day} {month_name} {year}",
        ])
    return [evidence_text(v) for v in variants]


def time_variants(value: str) -> list[str]:
    match = RE_TIME.match(norm(value))
    if not match:
        return []
    parts = [(match.group(1), match.group(2))]
    if match.group(3) and match.group(4):
        parts.append((match.group(3), match.group(4)))
    variants: list[str] = []
    for hour_raw, minute_raw in parts:
        hour = int(hour_raw)
        minute = int(minute_raw)
        variants.extend([
            f"{hour:02d}:{minute:02d}",
            f"{hour}:{minute:02d}",
            f"{hour:02d}.{minute:02d}",
            f"{hour}.{minute:02d}",
            f"{hour} uhr",
            f"{hour:02d} uhr",
        ])
    return [evidence_text(v) for v in variants]


def text_has_any_variant(text: str, variants: list[str]) -> bool:
    haystack = evidence_text(text)
    return any(variant and variant in haystack for variant in variants)


def source_text_has_time_hint(text: str) -> bool:
    """Detect plausible event-time hints in source text when the dataset has no time value.

    This is intentionally only a hint. It must not auto-write event times because
    page footers, opening hours or unrelated programme blocks can contain times.
    """
    haystack = evidence_text(text)
    if not haystack:
        return False
    return bool(re.search(r"\b(?:[01]?\d|2[0-3])[:.][0-5]\d(?:\s*(?:uhr|uur))?\b|\b(?:[01]?\d|2[0-3])\s*(?:uhr|uur)\b", haystack))


def parse_time_range(value: str) -> Dict[str, list[str]]:
    match = RE_TIME.match(norm(value))
    if not match:
        return {}
    start_hour, start_minute = int(match.group(1)), int(match.group(2))
    result = {"start_time": single_time_variants(start_hour, start_minute)}
    if match.group(3) and match.group(4):
        end_hour, end_minute = int(match.group(3)), int(match.group(4))
        result["end_time"] = single_time_variants(end_hour, end_minute)
    return result


def single_time_variants(hour: int, minute: int) -> list[str]:
    variants = [
        f"{hour:02d}:{minute:02d}",
        f"{hour}:{minute:02d}",
        f"{hour:02d}.{minute:02d}",
        f"{hour}.{minute:02d}",
    ]
    # Nur volle Stunden bekommen Uhr-Varianten ohne Minuten. Sonst wäre 18:30 durch "18 Uhr" falsch bestätigt.
    if minute == 0:
        variants.extend([
            f"{hour} uhr",
            f"{hour:02d} uhr",
        ])
    else:
        variants.extend([
            f"{hour}:{minute:02d} uhr",
            f"{hour:02d}:{minute:02d} uhr",
            f"{hour}.{minute:02d} uhr",
        ])
    return [evidence_text(v) for v in variants]


def date_field_variants(name: str, value: str) -> tuple[str, list[str]]:
    return name, date_variants(value)


def field_status_text(statuses: Dict[str, str]) -> str:
    return "; ".join(f"{key}={value}" for key, value in statuses.items() if value)


def field_label_list(statuses: Dict[str, str], wanted: set[str]) -> list[str]:
    return [key for key, value in statuses.items() if value in wanted]


def location_specific_tokens(location: str, city: str) -> list[str]:
    tokens = significant_tokens(location, min_len=3, max_tokens=5)
    city_tokens = set(significant_tokens(city, min_len=3, max_tokens=5))
    return [token for token in tokens if token not in city_tokens and token not in GENERIC_LOCATION_WORDS]


def add_observation(
    *,
    content_type: str,
    source_system: str,
    content_id: str,
    title: str,
    date_value: str = "",
    check_code: str,
    check_status: str,
    workbench_group: str,
    checked_url: str = "",
    checked_fields: Iterable[str] = (),
    missing_fields: Iterable[str] = (),
    summary: str,
) -> None:
    OBSERVATIONS.append(Observation(
        content_type=norm(content_type),
        source_system=norm(source_system),
        content_id=norm(content_id),
        title=norm(title),
        date=norm(date_value),
        check_code=norm(check_code),
        check_status=norm(check_status),
        workbench_group=norm(workbench_group),
        checked_url=norm(checked_url),
        checked_fields=", ".join([norm(item) for item in checked_fields if norm(item)]),
        missing_fields=", ".join([norm(item) for item in missing_fields if norm(item)]),
        summary=norm(summary),
    ))


def evaluate_event_source_evidence(row: Dict[str, str], source_system: str, evidence_url: str, *, today: date, network: bool) -> Dict[str, str]:
    """Compare event core facts against source text with explicit field-level proof.

    This is still not a 100% semantic truth check. It is a stronger, auditable
    evidence probe: every checked field is classified separately so the report
    can show what was confirmed, what was not automatically confirmed, and what
    was not applicable.
    """
    content_id = norm(row.get("id") or row.get("event_id") or row.get("submission_id"))
    title = norm(row.get("title"))
    date_value = norm(row.get("date") or row.get("start_date"))
    end_date_value = norm(row.get("endDate") or row.get("end_date"))
    time_value = norm(row.get("time"))
    city = norm(row.get("city"))
    location = norm(row.get("location"))
    checked_fields: list[str] = []
    field_statuses: Dict[str, str] = {}

    if not network or not is_http_url(evidence_url):
        return {
            "evidence_status": "not_checked",
            "evidence_summary": "Quellen-Faktenprobe nicht ausgeführt.",
            "evidence_checked_fields": "",
            "evidence_missing_fields": "",
            "evidence_field_statuses": "",
            "source_time_hint_status": "",
            "source_time_hint_summary": "",
        }

    status, detail, text = text_probe_url(evidence_url)
    if status != "ok" or not text:
        add_observation(
            content_type="event",
            source_system=source_system,
            content_id=content_id,
            title=title,
            date_value=date_value,
            check_code="event_source_fact_evidence",
            check_status="source_text_unavailable",
            workbench_group="Quellenprüfung",
            checked_url=evidence_url,
            summary=f"Quelle erreichbar/fetchbar nicht ausreichend für Textvergleich: {detail}",
        )
        return {
            "evidence_status": "source_text_unavailable",
            "evidence_summary": f"Quelle konnte für Faktenvergleich nicht als Text gelesen werden: {detail}",
            "evidence_checked_fields": "",
            "evidence_missing_fields": "source_text",
            "evidence_field_statuses": "source_text=unavailable",
            "source_time_hint_status": "",
            "source_time_hint_summary": "",
        }

    title_tokens = significant_tokens(title)
    if title_tokens:
        checked_fields.append("title")
        title_minimum = min(3, max(1, len(title_tokens))) if len(title_tokens) >= 3 else len(title_tokens)
        title_ok = text_has_tokens(text, title_tokens, minimum=title_minimum)
        field_statuses["title"] = "confirmed" if title_ok else "not_confirmed"

    if date_value:
        checked_fields.append("date")
        field_statuses["date"] = "confirmed" if text_has_any_variant(text, date_variants(date_value)) else "not_confirmed"

    if end_date_value and end_date_value != date_value:
        checked_fields.append("end_date")
        field_statuses["end_date"] = "confirmed" if text_has_any_variant(text, date_variants(end_date_value)) else "not_confirmed"

    time_checks = parse_time_range(time_value)
    for field_name, variants in time_checks.items():
        checked_fields.append(field_name)
        field_statuses[field_name] = "confirmed" if text_has_any_variant(text, variants) else "not_confirmed"

    city_tokens = significant_tokens(city, min_len=3, max_tokens=4)
    if city_tokens:
        checked_fields.append("city")
        field_statuses["city"] = "confirmed" if text_has_tokens(text, city_tokens, minimum=1) else "not_confirmed"

    specific_location_tokens = location_specific_tokens(location, city)
    generic_location_tokens = significant_tokens(location, min_len=3, max_tokens=4)
    if specific_location_tokens:
        checked_fields.append("location")
        # Bei spezifischem Ort reichen nicht nur Stadt/Gattungswörter. Mindestens ein spezifisches Token muss vorkommen.
        field_statuses["location"] = "confirmed" if text_has_tokens(text, specific_location_tokens, minimum=1) else "not_confirmed"
    elif generic_location_tokens:
        checked_fields.append("location")
        # Generische Orte wie Innenstadt/Marktplatz werden geprüft, aber nur als schwacher Nachweis gewertet.
        field_statuses["location"] = "weak_confirmed" if text_has_tokens(text, generic_location_tokens, minimum=1) else "not_confirmed"

    source_time_hint_status = ""
    source_time_hint_summary = ""
    if not time_value and source_text_has_time_hint(text):
        context_ok = any(field_statuses.get(name) == "confirmed" for name in ("title", "date", "end_date"))
        if context_ok:
            checked_fields.append("source_time_hint")
            source_time_hint_status = "source_has_time_but_dataset_missing_time"
            source_time_hint_summary = "Quelle enthält mindestens eine konkrete Uhrzeit, der Datensatz hat aber kein time-Feld."
            field_statuses["source_time_hint"] = source_time_hint_status

    missing_fields = field_label_list(field_statuses, {"not_confirmed"})
    weak_fields = field_label_list(field_statuses, {"weak_confirmed"})
    confirmed_fields = field_label_list(field_statuses, {"confirmed", "weak_confirmed"})

    hard_missing = set(missing_fields)
    near_event = bool(parse_iso_date(date_value) and parse_iso_date(date_value) <= today + timedelta(days=14))
    date_or_time_missing = bool(hard_missing.intersection({"date", "end_date", "start_time", "end_time"}))
    title_missing_with_date = "title" in hard_missing and "date" in hard_missing
    location_missing_near = "location" in hard_missing and near_event

    if not missing_fields:
        if weak_fields:
            evidence_status = "source_confirms_core_facts_with_weak_location"
            summary = "Quelle bestätigt Datum/Uhrzeit/Titel; Ortsnachweis ist nur generisch automatisch bestätigt."
        else:
            evidence_status = "source_confirms_key_facts"
            summary = "Quelle enthält die automatisch prüfbaren Kernfakten feldgenau."
    elif date_or_time_missing or title_missing_with_date or location_missing_near:
        evidence_status = "source_evidence_weak"
        summary = "Quelle bestätigt mindestens einen fachlich wichtigen Kernwert nicht automatisch; Faktencheck sinnvoll."
    else:
        evidence_status = "source_evidence_partial"
        summary = "Quelle bestätigt einen Teil der Kernfakten; keine sichere automatische Vollbestätigung."

    add_observation(
        content_type="event",
        source_system=source_system,
        content_id=content_id,
        title=title,
        date_value=date_value,
        check_code="event_source_fact_evidence",
        check_status=evidence_status,
        workbench_group="Faktenprüfung",
        checked_url=evidence_url,
        checked_fields=checked_fields,
        missing_fields=missing_fields,
        summary=f"{summary} Feldstatus: {field_status_text(field_statuses)}",
    )

    return {
        "evidence_status": evidence_status,
        "evidence_summary": summary,
        "evidence_checked_fields": ", ".join(checked_fields),
        "evidence_missing_fields": ", ".join(missing_fields),
        "evidence_field_statuses": field_status_text(field_statuses),
        "source_time_hint_status": source_time_hint_status,
        "source_time_hint_summary": source_time_hint_summary,
    }


def evaluate_activity_source_evidence(offer: Dict[str, Any], source_url: str, *, network: bool) -> Dict[str, str]:
    """Probe Activity source text for title/opening hints without writing data."""
    content_id = norm(offer.get("id"))
    title = norm(offer.get("title"))
    opening_status = offer.get("opening_status") if isinstance(offer.get("opening_status"), dict) else {}
    public_label = norm(opening_status.get("public_label"))
    detail_note = norm(opening_status.get("detail_note"))
    status_type = norm(opening_status.get("type"))

    if not network or not is_http_url(source_url):
        return {
            "evidence_status": "not_checked",
            "evidence_summary": "Activity-Faktenprobe nicht ausgeführt.",
            "evidence_checked_fields": "",
            "evidence_missing_fields": "",
            "evidence_field_statuses": "",
        }

    status, detail, text = text_probe_url(source_url)
    if status != "ok" or not text:
        add_observation(
            content_type="activity",
            source_system="offers_json",
            content_id=content_id,
            title=title,
            check_code="activity_source_fact_evidence",
            check_status="source_text_unavailable",
            workbench_group="Activity-Prüfung",
            checked_url=source_url,
            summary=f"Quelle konnte für Activity-Faktenvergleich nicht als Text gelesen werden: {detail}",
        )
        return {
            "evidence_status": "source_text_unavailable",
            "evidence_summary": f"Quelle konnte für Activity-Faktenvergleich nicht als Text gelesen werden: {detail}",
            "evidence_checked_fields": "",
            "evidence_missing_fields": "source_text",
            "evidence_field_statuses": "source_text=unavailable",
        }

    checked_fields: list[str] = []
    field_statuses: Dict[str, str] = {}

    title_tokens = significant_tokens(title, max_tokens=5)
    if title_tokens:
        checked_fields.append("title")
        field_statuses["title"] = "confirmed" if text_has_tokens(text, title_tokens, minimum=1) else "not_confirmed"

    opening_probe = " ".join([public_label, detail_note])
    opening_variants = [
        evidence_text(v)
        for v in re.findall(
            r"\d{1,2}[:.]\d{2}|\d{1,2}\s*uhr|sonnenaufgang|sonnenuntergang|täglich|taeglich|di\b|do\b|so\b|mo\b|fr\b|sa\b|montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag|brutzeit|saison|ferien|feiertag",
            opening_probe,
            flags=re.I,
        )
    ]
    if opening_variants or status_type in {"regular_hours", "seasonal_hours", "seasonal_recommended", "free_access", "check_required"}:
        checked_fields.append("opening_status")
        if not opening_variants:
            field_statuses["opening_status"] = "not_checkable"
        elif text_has_any_variant(text, opening_variants):
            field_statuses["opening_status"] = "confirmed"
        else:
            field_statuses["opening_status"] = "not_confirmed"

    missing_fields = field_label_list(field_statuses, {"not_confirmed"})
    if not missing_fields:
        if "not_checkable" in field_statuses.values():
            evidence_status = "source_evidence_partial"
            summary = "Activity-Quelle ist lesbar, aber Öffnungs-/Saisonlogik ist nicht vollständig automatisch beweisbar."
        else:
            evidence_status = "source_confirms_key_facts"
            summary = "Activity-Quelle enthält die automatisch prüfbaren Kernhinweise feldgenau."
    else:
        evidence_status = "source_evidence_partial"
        summary = "Activity-Quelle enthält nicht alle automatisch prüfbaren Hinweise; Activity-Faktencheck sinnvoll."

    add_observation(
        content_type="activity",
        source_system="offers_json",
        content_id=content_id,
        title=title,
        check_code="activity_source_fact_evidence",
        check_status=evidence_status,
        workbench_group="Activity-Prüfung",
        checked_url=source_url,
        checked_fields=checked_fields,
        missing_fields=missing_fields,
        summary=f"{summary} Feldstatus: {field_status_text(field_statuses)}",
    )
    return {
        "evidence_status": evidence_status,
        "evidence_summary": summary,
        "evidence_checked_fields": ", ".join(checked_fields),
        "evidence_missing_fields": ", ".join(missing_fields),
        "evidence_field_statuses": field_status_text(field_statuses),
    }



def event_visual_pool_payload(event_visual_pools: Dict[str, Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
    return {"pools": event_visual_pools}


def event_visual_fit_from_row(row: Dict[str, str], event_visual_pools: Dict[str, Dict[str, Any]]) -> Dict[str, str]:
    visual_key = norm(row.get("visual_key"))
    visual_motif = norm(row.get("visual_motif") or row.get("image_visual_motif") or row.get("visualMotif"))
    fit = infer_event_visual_fit(
        title=row.get("title", ""),
        description=row.get("description", ""),
        category=row.get("kategorie", ""),
        location=row.get("location", ""),
        visual_key=visual_key,
        visual_motif=visual_motif,
        pool_payload=event_visual_pool_payload(event_visual_pools),
    )
    fit["visual_motif_role"] = event_visual_motif_role(fit.get("visual_key", ""), fit.get("visual_motif", ""))
    fit["source_visual_key"] = visual_key
    fit["source_visual_motif"] = visual_motif
    return fit


def visual_fit_summary(fit: Dict[str, str]) -> str:
    key = norm(fit.get("visual_key")) or "<kein visual_key>"
    motif = norm(fit.get("visual_motif")) or "<kein visual_motif>"
    status = norm(fit.get("visual_asset_status")) or "review"
    role = norm(fit.get("visual_motif_role")) or "unknown"
    return f"Vorschlag: visual_key={key}, visual_motif={motif}, role={role}, asset_status={status}."


def event_visual_missing_issue_code(fit: Dict[str, str]) -> str:
    status = norm(fit.get("visual_asset_status"))
    if status == "needs_asset":
        return "event_visual_key_missing_asset_gap"
    if status == "review":
        return "event_visual_key_missing_needs_decision"
    return "event_visual_key_missing_ready_candidate"


def visual_specific_motif_gap(title: str, visual_key: str, visual_motif: str = "") -> str:
    title_norm = evidence_text(title)
    fit_norm = " ".join([evidence_text(visual_key), evidence_text(visual_motif)]).strip()
    if re.search(r"\b(dart|darts)\b", title_norm) and "dart" not in fit_norm:
        return "darts"
    if re.search(r"\b(fecht|fencing|fencer)\b", title_norm) and not any(marker in fit_norm for marker in ("fecht", "fenc")):
        return "fencing"
    return ""


def audit_event_visual_fit_candidates(occurrences: list[Dict[str, str]], source_system: str, base_url: str) -> List[Issue]:
    issues: List[Issue] = []
    public_url = f"{base_url.rstrip('/')}/events/" if base_url else ""

    for item in occurrences:
        motif_gap = visual_specific_motif_gap(item.get("title", ""), item.get("visual_key", ""), item.get("visual_motif", ""))
        if motif_gap:
            issues.append(issue(
                "review_needed",
                "event",
                source_system,
                item.get("content_id", ""),
                item.get("title", ""),
                "event_visual_specific_motif_gap",
                f"Eventtitel nennt ein spezifisches Motiv ({motif_gap}), aber der visual_key ist nicht spezifisch genug: {item.get('visual_key', '')}",
                "Im separaten Visual-Fit-Workflow prüfen: passenden visual_key wählen, Key verfeinern oder neues Bildmotiv produzieren. Nicht blind ein generisches Bild verwenden.",
                date_value=item.get("date", ""),
                source_url=item.get("source_url", ""),
                public_url=public_url,
                evidence_status="visual_fit_candidate",
                evidence_summary="Spezifischer Eventtitel benötigt Motiv-Fit-Prüfung.",
                evidence_checked_fields="title, visual_key",
                evidence_missing_fields="specific_visual_key",
            ))

    by_key: Dict[str, list[Dict[str, str]]] = {}
    for item in occurrences:
        key = norm(item.get("visual_key"))
        if key:
            by_key.setdefault(key, []).append(item)

    for visual_key, items in by_key.items():
        dated = [(parse_iso_date(item.get("date", "")), item) for item in items]
        dated = [(day, item) for day, item in dated if day is not None]
        if len(dated) < 3:
            continue
        dated.sort(key=lambda pair: pair[0])
        for start_index, (start_day, _) in enumerate(dated):
            cluster = [item for day, item in dated[start_index:] if day <= start_day + timedelta(days=7)]
            title_count = len({norm_key(item.get("title", "")) for item in cluster})
            if len(cluster) >= 3 and title_count >= 3:
                first = cluster[0]
                issues.append(issue(
                    "warning",
                    "event",
                    source_system,
                    f"visual-reuse-{visual_key}-{start_day.isoformat()}",
                    f"Visual-Fit prüfen: {visual_key}",
                    "event_visual_reuse_cluster",
                    f"visual_key wird in kurzer Zeit für {len(cluster)} unterschiedliche Events genutzt: {visual_key}",
                    "Im separaten Visual-Fit-Workflow prüfen, ob Bild-/Key-Wiederholung im Feed noch hochwertig wirkt oder zusätzliche Motive nötig sind.",
                    date_value=start_day.isoformat(),
                    source_url=first.get("source_url", ""),
                    public_url=public_url,
                    evidence_status="visual_fit_candidate",
                    evidence_summary="Mehrere unterschiedliche Events teilen in kurzer Zeit denselben visual_key.",
                    evidence_checked_fields="date, title, visual_key",
                    evidence_missing_fields="visual_diversity_review",
                ))
                break

    return issues


def audit_event_rows(
    rows: List[Dict[str, str]],
    source_system: str,
    event_visual_pools: Dict[str, Dict[str, Any]],
    source_suggestions: Dict[str, Dict[str, str]],
    today: date,
    horizon_days: int,
    scope: str,
    network: bool,
    base_url: str,
) -> List[Issue]:
    issues: List[Issue] = []
    seen_ids: Dict[str, str] = {}
    seen_fp: Dict[str, str] = {}
    visual_occurrences: list[Dict[str, str]] = []

    horizon_end = today + timedelta(days=horizon_days)
    total_rows = len(rows)
    log_checkpoint(f"event audit start: source_system={source_system}, rows={total_rows}, scope={scope}, network={network}")

    for index, row in enumerate(rows, start=1):
        if index == 1 or index % 10 == 0 or index == total_rows:
            log_checkpoint(f"event audit progress: source_system={source_system}, row={index}/{total_rows}, issues={len(issues)}")
        content_id = norm(row.get("id") or row.get("event_id") or row.get("submission_id"))
        title = norm(row.get("title"))
        date_value = norm(row.get("date") or row.get("start_date"))
        end_date_value = norm(row.get("endDate") or row.get("end_date"))
        time_value = norm(row.get("time"))
        url = norm(row.get("source_url") or row.get("url") or row.get("event_url"))
        public_url = f"{base_url.rstrip('/')}/events/" if base_url else ""
        source_suggestion = source_suggestion_for_event(row, source_suggestions)

        for field in EVENT_REQUIRED_FIELDS:
            if not norm(row.get(field)):
                issues.append(issue(
                    "critical",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_required_field_missing",
                    f"Pflichtfeld fehlt: {field}",
                    "Quelle im Google Sheet bzw. Review-System korrigieren; Deploy darf diesen Datensatz nicht ungeprüft veröffentlichen.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                ))

        start_date = parse_iso_date(date_value)
        if not start_date:
            issues.append(issue(
                "critical",
                "event",
                source_system,
                content_id,
                title,
                "event_invalid_date",
                f"Ungültiges Eventdatum: {date_value!r}",
                "Datum in der fachlichen Quelle auf YYYY-MM-DD korrigieren.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
            ))
            continue

        end_date = parse_iso_date(end_date_value) if end_date_value else start_date
        if end_date is None:
            issues.append(issue(
                "critical",
                "event",
                source_system,
                content_id,
                title,
                "event_invalid_end_date",
                f"Ungültiges Enddatum: {end_date_value!r}",
                "Enddatum in der fachlichen Quelle auf YYYY-MM-DD korrigieren oder entfernen.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
            ))
        elif end_date < start_date:
            issues.append(issue(
                "critical",
                "event",
                source_system,
                content_id,
                title,
                "event_end_before_start",
                "Enddatum liegt vor Startdatum.",
                "Enddatum oder Startdatum in der fachlichen Quelle korrigieren.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
            ))

        if end_date and end_date < today:
            issues.append(issue(
                "auto_fixed",
                "event",
                source_system,
                content_id,
                title,
                "event_expired_hidden_by_build",
                "Event liegt sicher in der Vergangenheit und wird vom Build/Feed nicht mehr öffentlich ausgespielt.",
                "Keine Sofortaktion nötig; alte Sheet-Zeilen können später redaktionell archiviert werden.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
                auto_fix_allowed=True,
                auto_fix_done=True,
            ))
            continue

        in_daily_window = today <= start_date <= horizon_end
        if scope == "daily" and not in_daily_window:
            continue

        visual_key_for_fit = norm(row.get("visual_key"))
        if visual_key_for_fit:
            visual_occurrences.append({
                "content_id": content_id,
                "title": title,
                "date": date_value,
                "visual_key": visual_key_for_fit,
                "source_url": url,
            })

        if time_value and not RE_TIME.match(time_value):
            issues.append(issue(
                "critical" if in_daily_window else "review_needed",
                "event",
                source_system,
                content_id,
                title,
                "event_time_format_unusual",
                f"Uhrzeit hat ein nicht erwartetes Format: {time_value!r}",
                "Uhrzeit im Sheet prüfen; für kommende Events ist das kritisch.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
            ))

        if content_id:
            duplicate_id = seen_ids.get(content_id)
            if duplicate_id:
                issues.append(issue(
                    "critical",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_duplicate_id",
                    f"Doppelte Event-ID: {content_id}",
                    "Eine ID in der fachlichen Quelle ändern; Deploy darf doppelte IDs nicht akzeptieren.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                ))
            seen_ids[content_id] = title

        fp = "|".join([
            norm_key(title),
            date_value,
            norm_key(time_value),
            norm_key(row.get("city")),
            norm_key(row.get("location")),
        ])
        if fp.strip("|"):
            if fp in seen_fp:
                issues.append(issue(
                    "review_needed",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_possible_duplicate",
                    "Mögliches Event-Duplikat nach Titel, Datum, Uhrzeit, Stadt und Ort.",
                    "Im Sheet bzw. Review-System prüfen, ob beide Zeilen wirklich unterschiedliche Termine sind.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                ))
            seen_fp[fp] = content_id or title

        if not url:
            issues.append(issue(
                "critical" if in_daily_window else "review_needed",
                "event",
                source_system,
                content_id,
                title,
                "event_source_url_missing",
                "Keine Event-/Quellen-URL vorhanden.",
                "Quelle ergänzen; bei zeitnahen Events ist fehlende Prüfbarkeit kritisch.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
            ))
        elif not is_http_url(url):
            issues.append(issue(
                "review_needed",
                "event",
                source_system,
                content_id,
                title,
                "event_source_url_invalid",
                f"Quellen-/Event-URL ist keine http(s)-URL: {url}",
                "URL in der fachlichen Quelle korrigieren.",
                date_value=date_value,
                source_url=url,
                public_url=public_url,
            ))
        elif is_ticket_portal_url(url):
            suggested_url = source_suggestion.get("suggested_url", "")
            evidence = {}
            if suggested_url:
                evidence = evaluate_event_source_evidence(row, source_system, suggested_url, today=today, network=network)
                action_text = "Empfohlene offizielle Event-/Veranstalterquelle in der Content-Prüfung vergleichen und erst nach Prüfung speichern. Ticketportal nur als Ticket-/Buchungslink führen, sobald ein separates Ticketfeld verfügbar ist."
            else:
                action_text = "Offizielle Event-/Veranstalterquelle bevorzugen. Ticketportal nur als Ticket-/Buchungslink führen, sobald ein separates Ticketfeld verfügbar ist."
            issues.append(issue(
                "review_needed",
                "event",
                source_system,
                content_id,
                title,
                "event_ticket_portal_as_primary_source",
                "Als primaere Eventquelle ist ein Ticketportal hinterlegt.",
                action_text,
                date_value=date_value,
                source_url=url,
                suggested_url=suggested_url,
                suggested_url_label=source_suggestion.get("suggested_url_label", ""),
                suggestion_reason=source_suggestion.get("suggestion_reason", ""),
                evidence_status=evidence.get("evidence_status", ""),
                evidence_summary=evidence.get("evidence_summary", ""),
                evidence_checked_fields=evidence.get("evidence_checked_fields", ""),
                evidence_missing_fields=evidence.get("evidence_missing_fields", ""),
                evidence_field_statuses=evidence.get("evidence_field_statuses", ""),
                public_url=public_url,
            ))
        elif network:
            source_fp = source_fingerprint_for_url(url)
            content_fp = event_content_fingerprint(row, url)
            current_event_data = {
                "title": title,
                "date": date_value,
                "end_date": end_date_value,
                "time": time_value,
                "city": norm(row.get("city")),
                "location": norm(row.get("location")),
                "address": norm(row.get("address")),
                "category": norm(row.get("kategorie")),
                "source_url": url,
            }
            status, detail = check_url(url)
            if status == "critical":
                if is_ai_fallback_url_detail(detail):
                    reason = f"Quelle ist technisch blockiert/unsicher und wird gezielt per KI-Fallback geprüft: {detail}"
                    verification = add_ai_verification_candidate(
                        content_type="event",
                        source_system=source_system,
                        content_id=content_id,
                        title=title,
                        date_value=date_value,
                        source_url=url,
                        reason=reason,
                        current_data=current_event_data,
                        source_fingerprint=source_fp,
                        content_fingerprint=content_fp,
                        priority=ai_priority_for_event(date_value, reason, today),
                        today=today,
                    )
                    if verification.get("cache_hit") != "yes":
                        issues.append(issue(
                            "warning",
                            "event",
                            source_system,
                            content_id,
                            title,
                            "event_ai_verification_candidate",
                            "Quelle kann durch den technischen Audit nicht belastbar geprüft werden.",
                            "Strukturierten KI-Faktencheck innerhalb des Budgetlimits ausführen. Nur Konflikt, bessere Quelle, not_found oder uncertain werden danach als Nutzeraufgabe eskaliert.",
                            date_value=date_value,
                            source_url=url,
                            evidence_status="source_blocked_or_unreadable",
                            evidence_summary=detail,
                            verification_key=verification.get("verification_key", ""),
                            verification_status=verification.get("verification_status", ""),
                            verified_by=verification.get("verified_by", ""),
                            last_verified_at=verification.get("last_verified_at", ""),
                            verified_until=verification.get("verified_until", ""),
                            source_fingerprint=source_fp,
                            content_fingerprint=content_fp,
                            next_check_at=verification.get("next_check_at", ""),
                            ai_candidate_priority=verification.get("ai_candidate_priority", ""),
                            ai_candidate_reason=verification.get("ai_candidate_reason", ""),
                            public_url=public_url,
                        ))
                else:
                    issues.append(issue(
                        "critical" if in_daily_window else "review_needed",
                        "event",
                        source_system,
                        content_id,
                        title,
                        "event_source_url_broken",
                        f"Quelle/Event-Link antwortet kritisch: {detail}",
                        "Quelle manuell prüfen; nicht automatisch löschen oder umschreiben.",
                        date_value=date_value,
                        source_url=url,
                        public_url=public_url,
                    ))
            elif status == "warning" and not is_transient_network_warning(detail):
                reason = f"Quelle ist fuer den technischen Audit nicht sicher lesbar: {detail}"
                verification = add_ai_verification_candidate(
                    content_type="event",
                    source_system=source_system,
                    content_id=content_id,
                    title=title,
                    date_value=date_value,
                    source_url=url,
                    reason=reason,
                    current_data=current_event_data,
                    source_fingerprint=source_fp,
                    content_fingerprint=content_fp,
                    priority=ai_priority_for_event(date_value, reason, today),
                    today=today,
                )
                if verification.get("cache_hit") != "yes":
                    issues.append(issue(
                        "warning",
                        "event",
                        source_system,
                        content_id,
                        title,
                        "event_ai_verification_candidate",
                        "Quelle/Event-Link konnte technisch nicht sicher geprüft werden.",
                        "Strukturierten KI-Faktencheck innerhalb des Budgetlimits ausführen oder bei Budgetüberschreitung priorisiert zurückstellen.",
                        date_value=date_value,
                        source_url=url,
                        evidence_status="source_unstable_or_unreadable",
                        evidence_summary=detail,
                        verification_key=verification.get("verification_key", ""),
                        verification_status=verification.get("verification_status", ""),
                        verified_by=verification.get("verified_by", ""),
                        last_verified_at=verification.get("last_verified_at", ""),
                        verified_until=verification.get("verified_until", ""),
                        source_fingerprint=source_fp,
                        content_fingerprint=content_fp,
                        next_check_at=verification.get("next_check_at", ""),
                        ai_candidate_priority=verification.get("ai_candidate_priority", ""),
                        ai_candidate_reason=verification.get("ai_candidate_reason", ""),
                        public_url=public_url,
                    ))
            elif status == "redirect" and not is_benign_redirect(url, detail):
                issues.append(issue(
                    "warning",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_source_url_redirect",
                    f"Quelle leitet weiter: {detail}",
                    "Redirect nur prüfen, wenn Zielseite fachlich abweicht, auf eine fremde Domain wechselt oder nicht mehr die konkrete Event-/Veranstalterquelle ist.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                ))

            # Faktenprobe: beobachtet, ob die Quelle die Kernangaben wirklich trägt.
            # Starke Unsicherheit wird jetzt zuerst als KI-Fallback-Kandidat statt als direkte Nutzeraufgabe geführt.
            if in_daily_window and is_http_url(url):
                evidence = evaluate_event_source_evidence(row, source_system, url, today=today, network=network)
                if evidence.get("evidence_status") == "source_evidence_weak":
                    reason = "Automatischer Quellenvergleich bestaetigt fachlich wichtige Kernfelder nicht ausreichend."
                    verification = add_ai_verification_candidate(
                        content_type="event",
                        source_system=source_system,
                        content_id=content_id,
                        title=title,
                        date_value=date_value,
                        source_url=url,
                        reason=reason,
                        current_data=current_event_data,
                        source_fingerprint=source_fp,
                        content_fingerprint=content_fp,
                        priority=ai_priority_for_event(date_value, reason, today),
                        today=today,
                        evidence_status=evidence.get("evidence_status", ""),
                        evidence_summary=evidence.get("evidence_summary", ""),
                        evidence_checked_fields=evidence.get("evidence_checked_fields", ""),
                        evidence_missing_fields=evidence.get("evidence_missing_fields", ""),
                        evidence_field_statuses=evidence.get("evidence_field_statuses", ""),
                    )
                    if verification.get("cache_hit") != "yes":
                        issues.append(issue(
                            "warning",
                            "event",
                            source_system,
                            content_id,
                            title,
                            "event_ai_verification_candidate",
                            "Die Quelle bestätigt die automatisch prüfbaren Kernfakten nicht ausreichend.",
                            "Strukturierten KI-Faktencheck ausführen. Nur bei Konflikt, besserer Quelle, not_found oder uncertain wird eine Content-Inbox-Aufgabe daraus.",
                            date_value=date_value,
                            source_url=url,
                            evidence_status=evidence.get("evidence_status", ""),
                            evidence_summary=evidence.get("evidence_summary", ""),
                            evidence_checked_fields=evidence.get("evidence_checked_fields", ""),
                            evidence_missing_fields=evidence.get("evidence_missing_fields", ""),
                            evidence_field_statuses=evidence.get("evidence_field_statuses", ""),
                            verification_key=verification.get("verification_key", ""),
                            verification_status=verification.get("verification_status", ""),
                            verified_by=verification.get("verified_by", ""),
                            last_verified_at=verification.get("last_verified_at", ""),
                            verified_until=verification.get("verified_until", ""),
                            source_fingerprint=source_fp,
                            content_fingerprint=content_fp,
                            next_check_at=verification.get("next_check_at", ""),
                            ai_candidate_priority=verification.get("ai_candidate_priority", ""),
                            ai_candidate_reason=verification.get("ai_candidate_reason", ""),
                            public_url=public_url,
                        ))

                if evidence.get("source_time_hint_status") == "source_has_time_but_dataset_missing_time":
                    issues.append(issue(
                        "warning",
                        "event",
                        source_system,
                        content_id,
                        title,
                        "event_source_has_time_but_dataset_missing_time",
                        "Quelle nennt mindestens eine konkrete Uhrzeit, der Datensatz enthält aber keine Uhrzeit.",
                        "Zeitangabe fachlich prüfen und nur nach bestätigter Quelle im Sheet ergänzen; keine automatische Korrektur.",
                        date_value=date_value,
                        source_url=url,
                        evidence_status=evidence.get("source_time_hint_status", ""),
                        evidence_summary=evidence.get("source_time_hint_summary", ""),
                        evidence_checked_fields=evidence.get("evidence_checked_fields", ""),
                        evidence_missing_fields="dataset_time",
                        evidence_field_statuses=evidence.get("evidence_field_statuses", ""),
                        public_url=public_url,
                    ))

        visual_key = norm(row.get("visual_key"))
        visual_motif = norm(row.get("visual_motif") or row.get("image_visual_motif") or row.get("visualMotif"))
        visual_fit = event_visual_fit_from_row(row, event_visual_pools)
        suggested_visual_key = norm(visual_fit.get("visual_key"))
        suggested_visual_motif = norm(visual_fit.get("visual_motif"))
        suggested_visual_motif_role = norm(visual_fit.get("visual_motif_role"))
        visual_asset_status = norm(visual_fit.get("visual_asset_status"))
        visual_summary = visual_fit_summary(visual_fit)

        if suggested_visual_key:
            visual_occurrences.append({
                "content_id": content_id,
                "title": title,
                "date": date_value,
                "source_url": url,
                "visual_key": suggested_visual_key if not visual_key else (normalize_event_visual_key(visual_key) or visual_key),
                "visual_motif": suggested_visual_motif,
                "visual_asset_status": visual_asset_status,
            })

        if not visual_key:
            add_observation(
                content_type="event",
                source_system=source_system,
                content_id=content_id,
                title=title,
                date_value=date_value,
                check_code="event_visual_fit_inference",
                check_status="visual_key_missing",
                workbench_group="Visual-Fit",
                checked_fields=("title", "description", "kategorie", "location"),
                missing_fields=("visual_key",),
                summary=f"Kein visual_key gesetzt. {visual_summary}",
            )
            if in_daily_window:
                code = event_visual_missing_issue_code(visual_fit)
                issues.append(issue(
                    "warning",
                    "event",
                    source_system,
                    content_id,
                    title,
                    code,
                    "Event hat keinen visual_key für den Event-Visual-Pool.",
                    "Im separaten Visual-Fit-Workflow prüfen: vorgeschlagenen visual_key/visual_motif übernehmen, Visual-Key-Regel verfeinern oder bei fehlendem Asset ein neues Motiv produzieren. Keine Bildzuordnung blind setzen.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                    suggested_visual_key=suggested_visual_key,
                    suggested_visual_motif=suggested_visual_motif,
                    suggested_visual_motif_role=suggested_visual_motif_role,
                    visual_asset_status=visual_asset_status,
                    evidence_status="visual_key_missing",
                    evidence_summary=visual_summary,
                    evidence_checked_fields="title, description, kategorie, location, event_visual_pool",
                    evidence_missing_fields="visual_key",
                ))
        else:
            normalized_visual_key = normalize_event_visual_key(visual_key) or visual_key
            inferred_visual_key = infer_event_visual_key(
                title=row.get("title"),
                description=row.get("description"),
                category=row.get("kategorie"),
                location=row.get("location"),
            )
            if inferred_visual_key and normalized_visual_key != inferred_visual_key:
                add_observation(
                    content_type="event",
                    source_system=source_system,
                    content_id=content_id,
                    title=title,
                    date_value=date_value,
                    check_code="event_visual_fit_inference",
                    check_status="visual_key_differs_from_rule",
                    workbench_group="Visual-Fit",
                    checked_fields=("title", "description", "kategorie", "location", "visual_key"),
                    missing_fields=(),
                    summary=f"Gesetzter visual_key {visual_key}; Regelvorschlag {inferred_visual_key}. {visual_summary}",
                )

            if not visual_motif and suggested_visual_motif and suggested_visual_motif_role == "specific" and in_daily_window:
                issues.append(issue(
                    "warning",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_visual_motif_missing_specific",
                    "Event hat einen visual_key, aber kein spezifisches visual_motif; dadurch kann im Feed ein zu generisches Bild erscheinen.",
                    "Im separaten Visual-Fit-Workflow prüfen: vorgeschlagenes visual_motif übernehmen oder bewusst beim neutralen Fallback bleiben. Keine Bildzuordnung blind setzen.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                    suggested_visual_key=suggested_visual_key,
                    suggested_visual_motif=suggested_visual_motif,
                    suggested_visual_motif_role=suggested_visual_motif_role,
                    visual_asset_status=visual_asset_status,
                    evidence_status="visual_motif_missing_specific",
                    evidence_summary=visual_summary,
                    evidence_checked_fields="title, description, kategorie, location, visual_key, event_visual_pool",
                    evidence_missing_fields="visual_motif",
                ))

            if visual_motif:
                normalized_visual_motif = normalize_event_visual_motif(visual_motif, normalized_visual_key)
                if not normalized_visual_motif:
                    issues.append(issue(
                        "warning",
                        "event",
                        source_system,
                        content_id,
                        title,
                        "event_visual_motif_unknown",
                        f"visual_motif ist für den visual_key nicht im Motiv-Regelwerk vorhanden: {visual_motif}",
                        "visual_motif im Sheet korrigieren oder Motiv-Regelwerk bewusst erweitern.",
                        date_value=date_value,
                        source_url=url,
                        public_url=public_url,
                        suggested_visual_key=suggested_visual_key,
                        suggested_visual_motif=suggested_visual_motif,
                        suggested_visual_motif_role=suggested_visual_motif_role,
                        visual_asset_status=visual_asset_status,
                        evidence_status="visual_motif_unknown",
                        evidence_summary=visual_summary,
                        evidence_checked_fields="visual_key, visual_motif, motif_rules",
                        evidence_missing_fields="valid_visual_motif",
                    ))
                elif visual_asset_status == "needs_asset":
                    issues.append(issue(
                        "warning",
                        "event",
                        source_system,
                        content_id,
                        title,
                        "event_visual_motif_without_ready_asset",
                        f"visual_motif hat kein ready-Bild im Event-Visual-Pool: {visual_motif}",
                        "Visual-Gap in separatem Visual-Fit-Workflow behandeln: passendes Asset produzieren oder bewusst anderes Motiv wählen.",
                        date_value=date_value,
                        source_url=url,
                        public_url=public_url,
                        suggested_visual_key=suggested_visual_key,
                        suggested_visual_motif=suggested_visual_motif,
                        suggested_visual_motif_role=suggested_visual_motif_role,
                        visual_asset_status=visual_asset_status,
                        evidence_status="visual_motif_needs_asset",
                        evidence_summary=visual_summary,
                        evidence_checked_fields="visual_key, visual_motif, event_visual_pool",
                        evidence_missing_fields="ready_visual_asset",
                    ))

            pool = event_visual_pools.get(normalized_visual_key)
            if pool is None:
                issues.append(issue(
                    "critical",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_visual_key_unknown",
                    f"visual_key ist nicht im Event-Visual-Pool vorhanden: {visual_key}",
                    "visual_key im Sheet korrigieren oder Pool bewusst erweitern.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                    suggested_visual_key=suggested_visual_key,
                    suggested_visual_motif=suggested_visual_motif,
                    suggested_visual_motif_role=suggested_visual_motif_role,
                    visual_asset_status=visual_asset_status,
                ))
            elif not pool_has_safe_image(pool):
                issues.append(issue(
                    "critical",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_visual_key_without_safe_image",
                    f"visual_key hat kein ready/usable/fallback-Bild: {visual_key}",
                    "Pool-Bild bereitstellen oder sicheren Ersatz-Key setzen.",
                    date_value=date_value,
                    source_url=url,
                    public_url=public_url,
                    suggested_visual_key=suggested_visual_key,
                    suggested_visual_motif=suggested_visual_motif,
                    suggested_visual_motif_role=suggested_visual_motif_role,
                    visual_asset_status=visual_asset_status,
                ))

    issues.extend(audit_event_visual_fit_candidates(visual_occurrences, source_system, base_url))
    log_checkpoint(f"event audit done: source_system={source_system}, rows={total_rows}, issues={len(issues)}")
    return issues


def audit_activities(
    offers_json: Path,
    activity_visual_pools: Dict[str, Dict[str, Any]],
    today: date,
    network: bool,
    base_url: str,
) -> List[Issue]:
    data = load_json(offers_json, required=False) or {}
    offers = data.get("offers") if isinstance(data, dict) else []
    if not isinstance(offers, list):
        return [issue(
            "critical",
            "activity",
            "offers_json",
            "offers",
            "Activities",
            "activities_json_invalid",
            "data/offers.json enthält keine gültige offers-Liste.",
            "JSON-Struktur reparieren; Activities dürfen ohne valide Quelle nicht zuverlässig ausgespielt werden.",
            public_url=f"{base_url.rstrip('/')}/aktivitaeten/" if base_url else "",
        )]

    issues: List[Issue] = []
    seen_ids: set[str] = set()
    public_url = f"{base_url.rstrip('/')}/aktivitaeten/" if base_url else ""
    total_offers = len(offers)
    log_checkpoint(f"activity audit start: rows={total_offers}, network={network}")

    for index, offer in enumerate(offers, start=1):
        if index == 1 or index % 10 == 0 or index == total_offers:
            log_checkpoint(f"activity audit progress: row={index}/{total_offers}, issues={len(issues)}")
        if not isinstance(offer, dict):
            continue

        content_id = norm(offer.get("id"))
        title = norm(offer.get("title"))
        url = norm(offer.get("url"))
        image = norm(offer.get("image"))
        visual_key = norm(offer.get("visual_key"))
        opening_status = offer.get("opening_status") if isinstance(offer.get("opening_status"), dict) else {}
        checked_at = norm(opening_status.get("checked_at")) if opening_status else ""
        source_url = norm(opening_status.get("source_url")) or url

        for field in ACTIVITY_REQUIRED_FIELDS:
            value = offer.get(field)
            if isinstance(value, dict):
                missing = not value
            else:
                missing = not norm(value)
            if missing:
                issues.append(issue(
                    "critical" if field in {"id", "title", "opening_status"} else "review_needed",
                    "activity",
                    "offers_json",
                    content_id,
                    title,
                    "activity_required_field_missing",
                    f"Pflicht-/Kernfeld fehlt: {field}",
                    "Activity-Datensatz per bewusstem Repo-Patch korrigieren.",
                    source_url=source_url,
                    public_url=public_url,
                ))

        if content_id in seen_ids:
            issues.append(issue(
                "critical",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_duplicate_id",
                f"Doppelte Activity-ID: {content_id}",
                "Eine ID in data/offers.json korrigieren; doppelte IDs sind nicht zulässig.",
                source_url=source_url,
                public_url=public_url,
            ))
        if content_id:
            seen_ids.add(content_id)

        pool = activity_visual_pools.get(visual_key) if visual_key else None
        if visual_key and pool is None:
            issues.append(issue(
                "critical",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_visual_key_unknown",
                f"visual_key ist nicht im Activity-Visual-Pool vorhanden: {visual_key}",
                "visual_key oder Activity-Visual-Pool per Repo-Patch korrigieren; vorhandenes Bild nicht blind ersetzen.",
                source_url=source_url,
                public_url=public_url,
            ))
        elif pool is not None and not pool_has_safe_image(pool):
            issues.append(issue(
                "review_needed",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_visual_key_without_safe_image",
                f"visual_key hat kein ready/usable/fallback-Bild: {visual_key}",
                "Pool-Bild prüfen oder sicheren Ersatz bereitstellen.",
                source_url=source_url,
                public_url=public_url,
            ))

        image_quality = norm(offer.get("image_quality"))
        safe_pool_image = pool is not None and pool_has_safe_image(pool)
        if image_quality == "blocked" or (image_quality == "needs_review" and not safe_pool_image):
            issues.append(issue(
                "review_needed",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_image_needs_review",
                f"Activity-Bildstatus ist {image_quality}.",
                "Bild bewusst prüfen, ersetzen oder Status dokumentiert freigeben. Wenn bereits ein sicheres Poolbild vorhanden ist, image_quality per Repo-Patch bereinigen.",
                source_url=source_url,
                public_url=public_url,
            ))

        if image:
            if is_local_path(image) and not local_path_exists(image):
                issues.append(issue(
                    "critical",
                    "activity",
                    "offers_json",
                    content_id,
                    title,
                    "activity_local_image_missing",
                    f"Lokales Activity-Bild fehlt: {image}",
                    "Bilddatei ergänzen oder Datensatz auf sicheres Pool-/Fallbackbild umstellen.",
                    source_url=source_url,
                    public_url=public_url,
                ))
            elif is_http_url(image) and network:
                status, detail = check_url(image)
                if status == "critical":
                    issues.append(issue(
                        "review_needed",
                        "activity",
                        "offers_json",
                        content_id,
                        title,
                        "activity_external_image_broken",
                        f"Externes Bild antwortet kritisch: {detail}",
                        "Bildquelle prüfen; keine automatische Übernahme fremder Ersatzbilder.",
                        source_url=source_url,
                        public_url=public_url,
                    ))
        else:
            issues.append(issue(
                "review_needed",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_image_missing",
                "Activity hat kein Bildfeld.",
                "Bild/Visual-Key prüfen und per Repo-Patch korrigieren.",
                source_url=source_url,
                public_url=public_url,
            ))

        checked_date = parse_iso_date(checked_at)
        if not checked_date:
            issues.append(issue(
                "review_needed",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_checked_at_missing_or_invalid",
                f"opening_status.checked_at fehlt oder ist ungültig: {checked_at!r}",
                "Activity-Quelle prüfen und checked_at aktualisieren.",
                source_url=source_url,
                public_url=public_url,
            ))
        else:
            age_days = (today - checked_date).days
            if age_days > 90:
                issues.append(issue(
                    "review_needed",
                    "activity",
                    "offers_json",
                    content_id,
                    title,
                    "activity_check_too_old",
                    f"Activity-Quellenprüfung ist {age_days} Tage alt.",
                    "Quelle fachlich prüfen und checked_at per Repo-Patch aktualisieren.",
                    source_url=source_url,
                    public_url=public_url,
                ))
            elif age_days > 45:
                issues.append(issue(
                    "warning",
                    "activity",
                    "offers_json",
                    content_id,
                    title,
                    "activity_check_aging",
                    f"Activity-Quellenprüfung ist {age_days} Tage alt.",
                    "Für monatliche/regelmäßige Pflege vormerken.",
                    source_url=source_url,
                    public_url=public_url,
                ))

        if not source_url:
            issues.append(issue(
                "review_needed",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_source_missing",
                "Keine prüfbare Activity-Quelle vorhanden.",
                "Quelle ergänzen; ohne Quelle keine belastbare langfristige Content-Sicherung.",
                source_url=source_url,
                public_url=public_url,
            ))
        elif not is_http_url(source_url):
            issues.append(issue(
                "review_needed",
                "activity",
                "offers_json",
                content_id,
                title,
                "activity_source_url_invalid",
                f"Activity-Quelle ist keine http(s)-URL: {source_url}",
                "Quellen-URL per Repo-Patch korrigieren.",
                source_url=source_url,
                public_url=public_url,
            ))
        elif network:
            source_fp = source_fingerprint_for_url(source_url)
            content_fp = activity_content_fingerprint(offer, source_url)
            current_activity_data = {
                "title": title,
                "location": norm(offer.get("location")),
                "source_url": source_url,
                "opening_type": norm(opening_status.get("type")) if opening_status else "",
                "opening_label": norm(opening_status.get("public_label")) if opening_status else "",
                "opening_detail": norm(opening_status.get("detail_note")) if opening_status else "",
                "checked_at": checked_at,
            }
            status, detail = check_url(source_url)
            if status == "critical":
                if is_ai_fallback_url_detail(detail):
                    reason = f"Activity-Quelle ist technisch blockiert/unsicher und wird gezielt per KI-Fallback geprüft: {detail}"
                    verification = add_ai_verification_candidate(
                        content_type="activity",
                        source_system="offers_json",
                        content_id=content_id,
                        title=title,
                        source_url=source_url,
                        reason=reason,
                        current_data=current_activity_data,
                        source_fingerprint=source_fp,
                        content_fingerprint=content_fp,
                        priority=60,
                        today=today,
                    )
                    if verification.get("cache_hit") != "yes":
                        issues.append(issue(
                            "warning",
                            "activity",
                            "offers_json",
                            content_id,
                            title,
                            "activity_ai_verification_candidate",
                            "Activity-Quelle kann durch den technischen Audit nicht belastbar geprüft werden.",
                            "Strukturierten KI-Faktencheck innerhalb des Budgetlimits ausführen. Keine Activity-Daten automatisch überschreiben.",
                            source_url=source_url,
                            evidence_status="source_blocked_or_unreadable",
                            evidence_summary=detail,
                            verification_key=verification.get("verification_key", ""),
                            verification_status=verification.get("verification_status", ""),
                            verified_by=verification.get("verified_by", ""),
                            last_verified_at=verification.get("last_verified_at", ""),
                            verified_until=verification.get("verified_until", ""),
                            source_fingerprint=source_fp,
                            content_fingerprint=content_fp,
                            next_check_at=verification.get("next_check_at", ""),
                            ai_candidate_priority=verification.get("ai_candidate_priority", ""),
                            ai_candidate_reason=verification.get("ai_candidate_reason", ""),
                            public_url=public_url,
                        ))
                else:
                    issues.append(issue(
                        "review_needed",
                        "activity",
                        "offers_json",
                        content_id,
                        title,
                        "activity_source_url_broken",
                        f"Activity-Quelle antwortet kritisch: {detail}",
                        "Quelle manuell prüfen; Activity nicht automatisch löschen.",
                        source_url=source_url,
                        public_url=public_url,
                    ))
            elif status == "warning" and not is_transient_network_warning(detail):
                reason = f"Activity-Quelle ist fuer den technischen Audit nicht sicher lesbar: {detail}"
                verification = add_ai_verification_candidate(
                    content_type="activity",
                    source_system="offers_json",
                    content_id=content_id,
                    title=title,
                    source_url=source_url,
                    reason=reason,
                    current_data=current_activity_data,
                    source_fingerprint=source_fp,
                    content_fingerprint=content_fp,
                    priority=55,
                    today=today,
                )
                if verification.get("cache_hit") != "yes":
                    issues.append(issue(
                        "warning",
                        "activity",
                        "offers_json",
                        content_id,
                        title,
                        "activity_ai_verification_candidate",
                        "Activity-Quelle konnte technisch nicht sicher geprüft werden.",
                        "Strukturierten KI-Faktencheck innerhalb des Budgetlimits ausführen oder priorisiert zurückstellen.",
                        source_url=source_url,
                        evidence_status="source_unstable_or_unreadable",
                        evidence_summary=detail,
                        verification_key=verification.get("verification_key", ""),
                        verification_status=verification.get("verification_status", ""),
                        verified_by=verification.get("verified_by", ""),
                        last_verified_at=verification.get("last_verified_at", ""),
                        verified_until=verification.get("verified_until", ""),
                        source_fingerprint=source_fp,
                        content_fingerprint=content_fp,
                        next_check_at=verification.get("next_check_at", ""),
                        ai_candidate_priority=verification.get("ai_candidate_priority", ""),
                        ai_candidate_reason=verification.get("ai_candidate_reason", ""),
                        public_url=public_url,
                    ))
            elif status == "redirect" and not is_benign_redirect(source_url, detail):
                issues.append(issue(
                    "warning",
                    "activity",
                    "offers_json",
                    content_id,
                    title,
                    "activity_source_url_redirect",
                    f"Activity-Quelle leitet weiter: {detail}",
                    "Redirect nur prüfen, wenn Zielseite fachlich abweicht, auf eine fremde Domain wechselt oder nicht mehr die konkrete Activity-/Anbieterquelle ist.",
                    source_url=source_url,
                    public_url=public_url,
                ))

            # Activity-Faktenproben bleiben bewusst ein separater Ausbau.
            # Der naechste sichere Sprung liegt zuerst bei feldgenauer Event-Faktenpruefung;
            # Activity-Oeffnungszeiten/Kosten/Saison duerfen nicht durch eine zu grobe Textprobe verrauscht werden.


    log_checkpoint(f"activity audit done: rows={total_offers}, issues={len(issues)}")
    return issues


def summarize(issues: List[Issue]) -> Dict[str, Any]:
    counts: Dict[str, int] = {"critical": 0, "review_needed": 0, "warning": 0, "auto_fixed": 0}
    by_type: Dict[str, int] = {}
    by_process_category: Dict[str, int] = {}
    by_correction_owner: Dict[str, int] = {}
    by_workbench_group: Dict[str, int] = {}
    by_verification_status: Dict[str, int] = {}
    for item in issues:
        counts[item.severity] = counts.get(item.severity, 0) + 1
        by_type[item.content_type] = by_type.get(item.content_type, 0) + 1
        by_process_category[item.process_category] = by_process_category.get(item.process_category, 0) + 1
        by_correction_owner[item.correction_owner] = by_correction_owner.get(item.correction_owner, 0) + 1
        by_workbench_group[item.workbench_group] = by_workbench_group.get(item.workbench_group, 0) + 1
        if item.verification_status:
            by_verification_status[item.verification_status] = by_verification_status.get(item.verification_status, 0) + 1
    return {
        "counts": counts,
        "by_type": by_type,
        "by_process_category": by_process_category,
        "by_correction_owner": by_correction_owner,
        "by_workbench_group": by_workbench_group,
        "by_verification_status": by_verification_status,
        "total": len(issues),
    }


def summarize_observations(observations: List[Observation]) -> Dict[str, Any]:
    by_status: Dict[str, int] = {}
    by_code: Dict[str, int] = {}
    by_type: Dict[str, int] = {}
    for item in observations:
        by_status[item.check_status] = by_status.get(item.check_status, 0) + 1
        by_code[item.check_code] = by_code.get(item.check_code, 0) + 1
        by_type[item.content_type] = by_type.get(item.content_type, 0) + 1
    return {
        "total": len(observations),
        "by_status": by_status,
        "by_code": by_code,
        "by_type": by_type,
    }


def write_json_report(path: Path, issues: List[Issue], meta: Dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "meta": meta,
        "summary": summarize(issues),
        "verification_summary": dict(VERIFICATION_STATS),
        "observations_summary": summarize_observations(OBSERVATIONS),
        "issues": [asdict(item) for item in issues],
        "observations": [asdict(item) for item in OBSERVATIONS],
    }
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_markdown_report(path: Path, issues: List[Issue], meta: Dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    summary = summarize(issues)
    observation_summary = summarize_observations(OBSERVATIONS)
    lines = [
        "# Content Quality Report",
        "",
        f"Generated: `{meta['generated_at']}`",
        f"Scope: `{meta['scope']}`",
        f"Network checks: `{meta['network_checks']}`",
        "",
        "## Summary",
        "",
        f"- Critical: {summary['counts'].get('critical', 0)}",
        f"- Review needed: {summary['counts'].get('review_needed', 0)}",
        f"- Warning: {summary['counts'].get('warning', 0)}",
        f"- Auto fixed: {summary['counts'].get('auto_fixed', 0)}",
        "",
        "## Process categories",
        "",
        *[f"- {key}: {value}" for key, value in sorted(summary.get("by_process_category", {}).items())],
        "",
        "## Correction owners",
        "",
        *[f"- {key}: {value}" for key, value in sorted(summary.get("by_correction_owner", {}).items())],
        "",
        "## Verification fallback / cache",
        "",
        f"- Cache entries loaded: {VERIFICATION_STATS.get('cache_entries_loaded', 0)}",
        f"- Cache hits: {VERIFICATION_STATS.get('cache_hits', 0)}",
        f"- AI candidates total: {VERIFICATION_STATS.get('ai_candidates_total', 0)}",
        f"- AI candidates selected: {VERIFICATION_STATS.get('ai_candidates_selected', 0)}",
        f"- Deferred by budget: {VERIFICATION_STATS.get('ai_candidates_deferred_by_budget', 0)}",
        "",
        "## Evidence observations",
        "",
        f"- Total observations: {observation_summary.get('total', 0)}",
        *[f"- {key}: {value}" for key, value in sorted(observation_summary.get("by_status", {}).items())],
        "",
        "## Issues",
        "",
        "| Severity | Workbench | Owner | Type | Source | ID | Title | Issue | Action | Suggested URL | Visual Key | Visual Motif | Visual Asset | Evidence | Verification | Priority | Field status |",
        "|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|",
    ]
    for item in sorted(issues, key=lambda x: (SEVERITY_RANK.get(x.severity, 9), x.workbench_group, x.content_type, x.date, x.title)):
        def cell(value: str) -> str:
            return norm(value).replace("|", "\\|").replace("\n", " ")[:240]
        lines.append(
            "| " + " | ".join([
                cell(item.severity),
                cell(item.workbench_group),
                cell(item.correction_owner),
                cell(item.content_type),
                cell(item.source_system),
                cell(item.content_id),
                cell(item.title),
                cell(item.issue_text),
                cell(item.recommended_action),
                cell(item.suggested_url),
                cell(item.suggested_visual_key),
                cell(item.suggested_visual_motif),
                cell(item.visual_asset_status),
                cell(item.evidence_status),
                cell(item.verification_status),
                cell(item.ai_candidate_priority),
                cell(item.evidence_field_statuses),
            ]) + " |"
        )
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Bocholt erleben Content Quality Guard")
    parser.add_argument("--scope", choices=["deploy-gate", "daily", "full"], default="deploy-gate")
    parser.add_argument("--events-tsv", default="data/events.tsv")
    parser.add_argument("--events-json", default="data/events.json")
    parser.add_argument("--db-events-json", default="data/public-db-events.json")
    parser.add_argument("--offers-json", default="data/offers.json")
    parser.add_argument("--event-visual-pool", default="data/event_visual_pool.json")
    parser.add_argument("--activity-visual-pool", default="data/activity_visual_pool.json")
    parser.add_argument("--output-json", default="data/content-quality-report.json")
    parser.add_argument("--output-md", default="data/content-quality-report.md")
    parser.add_argument("--verification-cache-json", default="data/content-verification-cache.json")
    parser.add_argument("--ai-candidates-json", default="data/content-ai-verification-candidates.json")
    parser.add_argument("--ai-max-candidates", type=int, default=AI_VERIFICATION_DEFAULT_MAX_CANDIDATES)
    parser.add_argument("--base-url", default="https://bocholt-erleben.de")
    parser.add_argument("--horizon-days", type=int, default=14)
    parser.add_argument("--network", action="store_true")
    parser.add_argument("--fail-on-critical", action="store_true")
    args = parser.parse_args()

    today = date.today()
    log_checkpoint(f"audit start: scope={args.scope}, network={args.network}, base_url={args.base_url}")

    global VERIFICATION_CACHE
    log_checkpoint(f"load verification cache: {args.verification_cache_json}")
    VERIFICATION_CACHE = load_verification_cache(ROOT / args.verification_cache_json)
    VERIFICATION_STATS["cache_entries_loaded"] = len(VERIFICATION_CACHE)
    log_checkpoint(f"verification cache loaded: entries={len(VERIFICATION_CACHE)}")

    event_pools = load_visual_pools(ROOT / args.event_visual_pool)
    activity_pools = load_visual_pools(ROOT / args.activity_visual_pool)
    source_suggestions = load_source_suggestions()
    log_checkpoint(f"supporting data loaded: event_pools={len(event_pools)}, activity_pools={len(activity_pools)}, source_suggestions={len(source_suggestions)}")

    event_rows, event_source = load_events(ROOT / args.events_tsv, ROOT / args.events_json)
    db_event_rows = load_db_events(ROOT / args.db_events_json)
    log_checkpoint(f"content rows loaded: sheet_events={len(event_rows)} ({event_source}), db_events={len(db_event_rows)}")

    issues: List[Issue] = []
    if not event_rows:
        issues.append(issue(
            "critical",
            "event",
            "sheet_events",
            "events",
            "Events",
            "events_source_missing",
            "Keine Sheet-/Runtime-Events für den Audit gefunden.",
            "Workflow-Export aus dem Google Sheet prüfen.",
            public_url=f"{args.base_url.rstrip('/')}/events/",
        ))
    else:
        issues.extend(audit_event_rows(
            event_rows,
            event_source,
            event_pools,
            source_suggestions,
            today,
            args.horizon_days,
            args.scope,
            args.network,
            args.base_url,
        ))

    if db_event_rows:
        issues.extend(audit_event_rows(
            db_event_rows,
            "public_db_events_api",
            event_pools,
            source_suggestions,
            today,
            args.horizon_days,
            args.scope,
            args.network,
            args.base_url,
        ))

    if args.scope in {"deploy-gate", "full"}:
        issues.extend(audit_activities(
            ROOT / args.offers_json,
            activity_pools,
            today,
            args.network,
            args.base_url,
        ))

    issues.sort(key=lambda x: (SEVERITY_RANK.get(x.severity, 9), x.workbench_group, x.content_type, x.date, x.title, x.issue_code))

    meta = {
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "scope": args.scope,
        "network_checks": bool(args.network),
        "horizon_days": args.horizon_days,
        "event_source": event_source,
        "db_events_loaded": len(db_event_rows),
        "base_url": args.base_url,
        "source_suggestions_loaded": len(source_suggestions),
        "verification_cache_entries_loaded": len(VERIFICATION_CACHE),
        "ai_max_candidates": max(0, args.ai_max_candidates),
    }

    log_checkpoint(f"audit issue collection done: issues={len(issues)}")
    candidates = selected_ai_candidates(args.ai_max_candidates)
    log_checkpoint(f"ai candidates selected: total={VERIFICATION_STATS.get('ai_candidates_total', 0)}, selected={len(candidates)}, limit={max(0, args.ai_max_candidates)}")
    meta["ai_candidates_total"] = VERIFICATION_STATS.get("ai_candidates_total", 0)
    meta["ai_candidates_selected"] = VERIFICATION_STATS.get("ai_candidates_selected", 0)
    meta["ai_candidates_deferred_by_budget"] = VERIFICATION_STATS.get("ai_candidates_deferred_by_budget", 0)
    meta["verification_cache_hits"] = VERIFICATION_STATS.get("cache_hits", 0)

    log_checkpoint("write reports start")
    write_json_report(ROOT / args.output_json, issues, meta)
    write_markdown_report(ROOT / args.output_md, issues, meta)
    write_ai_candidates_report(ROOT / args.ai_candidates_json, candidates, meta)
    log_checkpoint(f"write reports done: json={args.output_json}, md={args.output_md}, ai_candidates={args.ai_candidates_json}")

    summary = summarize(issues)
    print("✅ Content Quality Audit written")
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    print("=== Verification fallback / cache ===")
    print(json.dumps(dict(VERIFICATION_STATS), ensure_ascii=False, indent=2))

    if args.fail_on_critical and summary["counts"].get("critical", 0):
        print("❌ Content Quality Guard found critical issues.", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
# === END BLOCK: CONTENT_QUALITY_GUARD_V1 ===
