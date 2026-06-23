#!/usr/bin/env python3
# === BEGIN BLOCK: CONTENT_QUALITY_GUARD_V1 | Zweck: prueft Sheet-Events, DB-Events und Activities auf harte Inhalts-/Runtime-Risiken; Umfang: lokaler Audit ohne Google-Sheet-Schreibzugriff ===
from __future__ import annotations

import argparse
import csv
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

ROOT = Path(__file__).resolve().parents[1]
RE_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
RE_TIME = re.compile(r"^\s*(\d{1,2})[:.](\d{2})(?:\s*[-–]\s*(\d{1,2})[:.](\d{2}))?\s*$")
HTTP_TIMEOUT_SECONDS = 12

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
    public_url: str
    auto_fix_allowed: str
    auto_fix_done: str



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

    horizon_end = today + timedelta(days=horizon_days)

    for row in rows:
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
            if suggested_url:
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
                public_url=public_url,
            ))
        elif network:
            status, detail = check_url(url)
            if status == "critical":
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
                issues.append(issue(
                    "review_needed" if in_daily_window else "warning",
                    "event",
                    source_system,
                    content_id,
                    title,
                    "event_source_url_unstable",
                    f"Quelle/Event-Link konnte nicht sicher geprüft werden: {detail}",
                    "Bei Wiederholung oder zeitnahen Events manuell prüfen.",
                    date_value=date_value,
                    source_url=url,
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

        visual_key = norm(row.get("visual_key"))
        if visual_key:
            pool = event_visual_pools.get(visual_key)
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
                ))

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

    for offer in offers:
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
            status, detail = check_url(source_url)
            if status == "critical":
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
                issues.append(issue(
                    "warning",
                    "activity",
                    "offers_json",
                    content_id,
                    title,
                    "activity_source_url_unstable",
                    f"Activity-Quelle konnte nicht sicher geprüft werden: {detail}",
                    "Bei Wiederholung manuell prüfen.",
                    source_url=source_url,
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

    return issues


def summarize(issues: List[Issue]) -> Dict[str, Any]:
    counts: Dict[str, int] = {"critical": 0, "review_needed": 0, "warning": 0, "auto_fixed": 0}
    by_type: Dict[str, int] = {}
    by_process_category: Dict[str, int] = {}
    by_correction_owner: Dict[str, int] = {}
    by_workbench_group: Dict[str, int] = {}
    for item in issues:
        counts[item.severity] = counts.get(item.severity, 0) + 1
        by_type[item.content_type] = by_type.get(item.content_type, 0) + 1
        by_process_category[item.process_category] = by_process_category.get(item.process_category, 0) + 1
        by_correction_owner[item.correction_owner] = by_correction_owner.get(item.correction_owner, 0) + 1
        by_workbench_group[item.workbench_group] = by_workbench_group.get(item.workbench_group, 0) + 1
    return {
        "counts": counts,
        "by_type": by_type,
        "by_process_category": by_process_category,
        "by_correction_owner": by_correction_owner,
        "by_workbench_group": by_workbench_group,
        "total": len(issues),
    }


def write_json_report(path: Path, issues: List[Issue], meta: Dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "meta": meta,
        "summary": summarize(issues),
        "issues": [asdict(item) for item in issues],
    }
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_markdown_report(path: Path, issues: List[Issue], meta: Dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    summary = summarize(issues)
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
        "## Issues",
        "",
        "| Severity | Workbench | Owner | Type | Source | ID | Title | Issue | Action | Suggested URL |",
        "|---|---|---|---|---|---|---|---|---|---|",
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
    parser.add_argument("--base-url", default="https://bocholt-erleben.de")
    parser.add_argument("--horizon-days", type=int, default=14)
    parser.add_argument("--network", action="store_true")
    parser.add_argument("--fail-on-critical", action="store_true")
    args = parser.parse_args()

    today = date.today()
    event_pools = load_visual_pools(ROOT / args.event_visual_pool)
    activity_pools = load_visual_pools(ROOT / args.activity_visual_pool)
    source_suggestions = load_source_suggestions()

    event_rows, event_source = load_events(ROOT / args.events_tsv, ROOT / args.events_json)
    db_event_rows = load_db_events(ROOT / args.db_events_json)

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
    }

    write_json_report(ROOT / args.output_json, issues, meta)
    write_markdown_report(ROOT / args.output_md, issues, meta)

    summary = summarize(issues)
    print("✅ Content Quality Audit written")
    print(json.dumps(summary, ensure_ascii=False, indent=2))

    if args.fail_on_critical and summary["counts"].get("critical", 0):
        print("❌ Content Quality Guard found critical issues.", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
# === END BLOCK: CONTENT_QUALITY_GUARD_V1 ===
