#!/usr/bin/env python3
# === BEGIN FILE: scripts/check-bathing-water-status-proof.py | Zweck: prueft, ob Badegewaesser-Statusquellen robust automatisiert auslesbar sind; Umfang: Proof ohne Daten-/Inbox-Schreibzugriff ===
from __future__ import annotations

import argparse
import html
import json
import re
import sys
import urllib.error
import urllib.request
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
USER_AGENT = "Bocholt-erleben bathing-water-status-proof/1.0 (+https://bocholt-erleben.de/)"

STATUS_OK = "ok"
STATUS_WATCH = "watch"
STATUS_BLOCKED = "blocked"
STATUS_UNKNOWN = "unknown"


@dataclass(frozen=True)
class BathingSource:
    id: str
    group_id: str
    activity_id: str
    title: str
    source_type: str
    source_url: str
    expected_title: str


SOURCES: tuple[BathingSource, ...] = (
    BathingSource(
        id="aasee-bocholt-nrw",
        group_id="aasee-bocholt",
        activity_id="aasee-erleben",
        title="Aasee Bocholt",
        source_type="official_bathing_water_status_nrw",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/48",
        expected_title="Aasee - Badestelle",
    ),
    BathingSource(
        id="hilgelo-winterswijk-zwemwater",
        group_id="hilgelo-winterswijk",
        activity_id="hilgelo-erleben",
        title="'t Hilgelo Winterswijk",
        source_type="official_bathing_water_status_nl",
        source_url="https://www.zwemwater.nl/?id=1750",
        expected_title="Hilgelo",
    ),
    BathingSource(
        id="proebstingsee-borken-nrw",
        group_id="proebstingsee-borken",
        activity_id="proebstingsee-borken-erleben",
        title="Pröbstingsee Borken",
        source_type="official_bathing_water_status_nrw",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/50",
        expected_title="Pröbstingsee - Badestelle",
    ),
    BathingSource(
        id="auesee-wesel-rettungsinsel-nrw",
        group_id="auesee-wesel",
        activity_id="auesee-wesel-erleben",
        title="Auesee Wesel - Rettungsinsel",
        source_type="official_bathing_water_status_nrw",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/22",
        expected_title="Auesee - Rettungsinsel",
    ),
    BathingSource(
        id="auesee-wesel-treibsand-nrw",
        group_id="auesee-wesel",
        activity_id="auesee-wesel-erleben",
        title="Auesee Wesel - Treibsand",
        source_type="official_bathing_water_status_nrw",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/23",
        expected_title="Auesee - Treibsand",
    ),
)


def norm_text(text: str) -> str:
    text = html.unescape(text or "")
    text = re.sub(r"<script\b.*?</script>", " ", text, flags=re.IGNORECASE | re.DOTALL)
    text = re.sub(r"<style\b.*?</style>", " ", text, flags=re.IGNORECASE | re.DOTALL)
    text = re.sub(r"<[^>]+>", " ", text)
    text = text.replace("\xa0", " ")
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def norm_key(text: str) -> str:
    value = norm_text(text).lower()
    value = value.replace("ö", "oe").replace("ü", "ue").replace("ä", "ae").replace("ß", "ss")
    return value


def fetch_url(url: str, timeout: int) -> tuple[int | None, str, str | None]:
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"})
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            status = getattr(response, "status", None)
            charset = response.headers.get_content_charset() or "utf-8"
            body = response.read().decode(charset, errors="replace")
            return status, body, None
    except urllib.error.HTTPError as exc:
        try:
            body = exc.read().decode("utf-8", errors="replace")
        except Exception:
            body = ""
        return exc.code, body, f"HTTPError: {exc.code}"
    except Exception as exc:  # noqa: BLE001 - Proof soll Fehler konservativ als unknown erfassen.
        return None, "", f"{type(exc).__name__}: {exc}"


def extract_year_quality_labels(text: str) -> list[str]:
    labels = []
    for pattern in (
        r"ausgezeichnete Wasserqualität",
        r"gute Wasserqualität",
        r"ausreichende Wasserqualität",
        r"mangelhafte Wasserqualität",
    ):
        labels.extend(re.findall(pattern, text, flags=re.IGNORECASE))
    return sorted(set(label.lower() for label in labels))


def has_current_sample_rows(text: str) -> bool:
    # Conservative proof heuristic: Datumszeilen mit mikrobiologischen Messwerten muessen im HTML sichtbar sein.
    # Die NRW-Seite rendert diese Tabelle aktuell oft clientseitig/leer; dann ist der Automationsbeweis nicht erbracht.
    if "no data available to generate a report" in text.lower():
        return False
    has_sample_date = bool(re.search(r"\b\d{1,2}\.\d{1,2}\.\d{4}\b", text)) or bool(re.search(r"\b\d{4}-\d{2}-\d{2}\b", text))
    has_indicators = "escherichia coli" in text.lower() and "enterococc" in text.lower()
    return has_sample_date and has_indicators


def infer_nrw_status(source: BathingSource, body: str) -> dict[str, Any]:
    plain = norm_text(body)
    key = norm_key(body)
    expected_present = norm_key(source.expected_title) in key
    year_labels = extract_year_quality_labels(plain)
    sample_rows_visible = has_current_sample_rows(plain)

    notes: list[str] = []
    status = STATUS_UNKNOWN
    usable_for_automation = False
    confidence = "source_reachable_but_no_machine_current_status"

    if not expected_present:
        notes.append("expected_title_not_found")
        confidence = "source_reachable_but_unexpected_content"

    if year_labels:
        notes.append("annual_quality_detected_but_not_current_clearance")

    if "an unexpected error has occurred" in key or "error happened while loading list data" in key:
        notes.append("client_side_list_error_or_empty_report_visible")

    if sample_rows_visible:
        notes.append("sample_rows_visible_manual_review_required")
        # Kein automatisches OK aus NRW-Probenzeilen im Proof: Badeverbot/Statuslogik muss erst separat sicher gemappt werden.
        confidence = "current_samples_visible_but_mapping_not_implemented"

    # Header enthaelt das Wort Badeverbot; deshalb nur konkrete Ja-/Verbot-Phrasen als Blocker werten.
    if re.search(r"badeverbot\s*[:=]?\s*(ja|true|1)\b", key) or "baden verboten" in key:
        status = STATUS_BLOCKED
        usable_for_automation = True
        confidence = "explicit_current_blocker_detected"
        notes.append("explicit_badeverbot_detected")

    return {
        "status": status,
        "usable_for_automation": usable_for_automation,
        "confidence": confidence,
        "expected_title_present": expected_present,
        "annual_quality_labels": year_labels,
        "current_sample_rows_visible": sample_rows_visible,
        "notes": notes,
    }


def infer_zwemwater_status(source: BathingSource, body: str) -> dict[str, Any]:
    plain = norm_text(body)
    key = norm_key(body)
    expected_present = norm_key(source.expected_title) in key
    notes: list[str] = []

    if not expected_present:
        notes.append("expected_title_not_found")

    # Zwemwater nutzt Statusbegriffe/Alt-Texte wie status:goed, waarschuwing, negatief zwemadvies, verboden te zwemmen.
    negative_patterns = (
        "zwemplek status:verboden",
        "verboden te zwemmen",
        "zwemverbod",
        "negatief zwemadvies",
        "zwemmen wordt afgeraden",
    )
    watch_patterns = (
        "zwemplek status:waarschuwing",
        "waarschuwing",
        "kans op gezondheidsklachten",
        "waterkwaliteit wordt onderzocht",
        "wordt onderzocht",
    )
    ok_patterns = (
        "actuele situatie: in orde",
        "zwemplek status:goed",
        "de waterkwaliteit is goed",
    )

    status = STATUS_UNKNOWN
    usable_for_automation = False
    confidence = "no_current_status_signal_detected"

    if any(pattern in key for pattern in negative_patterns):
        status = STATUS_BLOCKED
        usable_for_automation = True
        confidence = "current_negative_status_detected"
    elif any(pattern in key for pattern in watch_patterns):
        status = STATUS_WATCH
        usable_for_automation = True
        confidence = "current_warning_status_detected"
    elif any(pattern in key for pattern in ok_patterns):
        status = STATUS_OK
        usable_for_automation = True
        confidence = "current_positive_status_detected"
    else:
        notes.append("no_known_status_label_found")

    return {
        "status": status,
        "usable_for_automation": usable_for_automation,
        "confidence": confidence,
        "expected_title_present": expected_present,
        "annual_quality_labels": [],
        "current_sample_rows_visible": False,
        "notes": notes,
    }


def infer_status(source: BathingSource, body: str) -> dict[str, Any]:
    if source.source_type == "official_bathing_water_status_nl":
        return infer_zwemwater_status(source, body)
    return infer_nrw_status(source, body)


def aggregate_group(group_id: str, source_results: list[dict[str, Any]]) -> dict[str, Any]:
    statuses = [result.get("status", STATUS_UNKNOWN) for result in source_results]
    usable = [bool(result.get("usable_for_automation")) for result in source_results]
    titles = [str(result.get("title") or "") for result in source_results]
    activity_ids = sorted(set(str(result.get("activity_id") or "") for result in source_results if result.get("activity_id")))

    if any(status == STATUS_BLOCKED for status in statuses):
        status = STATUS_BLOCKED
    elif any(status == STATUS_WATCH for status in statuses):
        status = STATUS_WATCH
    elif statuses and all(status == STATUS_OK for status in statuses) and all(usable):
        status = STATUS_OK
    else:
        status = STATUS_UNKNOWN

    return {
        "group_id": group_id,
        "activity_ids": activity_ids,
        "titles": titles,
        "status": status,
        "usable_for_automation": status in {STATUS_OK, STATUS_WATCH, STATUS_BLOCKED} and all(usable),
        "source_count": len(source_results),
        "usable_source_count": sum(1 for result in source_results if result.get("usable_for_automation")),
        "source_ids": [str(result.get("source_id")) for result in source_results],
    }


def make_markdown(report: dict[str, Any]) -> str:
    verdict = report["verdict"]
    lines = [
        "# Badegewässer Status Proof",
        "",
        f"Generated: `{report['generated_at']}`",
        "",
        "## Verdict",
        "",
        f"- Ready to operationalize: `{str(verdict['ready_to_operationalize']).lower()}`",
        f"- Usable activity groups: `{verdict['usable_activity_groups']}/{verdict['total_activity_groups']}`",
        f"- Required usable groups: `{verdict['min_usable_activity_groups']}`",
        f"- Recommendation: `{verdict['recommendation']}`",
        "",
        "## Activity groups",
        "",
        "| Group | Status | Usable | Sources |",
        "|---|---:|---:|---:|",
    ]
    for group in report["groups"]:
        lines.append(f"| `{group['group_id']}` | `{group['status']}` | `{str(group['usable_for_automation']).lower()}` | `{group['usable_source_count']}/{group['source_count']}` |")
    lines.extend([
        "",
        "## Sources",
        "",
        "| Source | HTTP | Status | Usable | Confidence | Notes |",
        "|---|---:|---:|---:|---|---|",
    ])
    for source in report["sources"]:
        http_status = source.get("http_status") if source.get("http_status") is not None else "n/a"
        notes = ", ".join(source.get("notes") or [])
        lines.append(f"| `{source['source_id']}` | `{http_status}` | `{source['status']}` | `{str(source['usable_for_automation']).lower()}` | `{source['confidence']}` | {notes} |")
    lines.append("")
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description="Proof whether bathing-water status sources are usable for automated highlights.")
    parser.add_argument("--output", default="tmp/bathing-water-status-proof.json", help="JSON output path")
    parser.add_argument("--markdown", default="", help="Optional Markdown summary output path")
    parser.add_argument("--timeout", type=int, default=20, help="HTTP timeout per source in seconds")
    parser.add_argument("--min-usable-activity-groups", type=int, default=3, help="Minimum usable activity groups before operationalization is recommended")
    parser.add_argument("--fail-if-not-ready", action="store_true", help="Exit non-zero when verdict is not ready_to_operationalize")
    args = parser.parse_args()

    generated_at = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
    source_results: list[dict[str, Any]] = []

    for source in SOURCES:
        http_status, body, fetch_error = fetch_url(source.source_url, args.timeout)
        base: dict[str, Any] = {
            "source_id": source.id,
            "group_id": source.group_id,
            "activity_id": source.activity_id,
            "title": source.title,
            "source_type": source.source_type,
            "source_url": source.source_url,
            "http_status": http_status,
            "fetched_ok": fetch_error is None and http_status is not None and 200 <= int(http_status) < 300,
            "fetch_error": fetch_error,
        }
        if not base["fetched_ok"]:
            base.update({
                "status": STATUS_UNKNOWN,
                "usable_for_automation": False,
                "confidence": "fetch_failed",
                "expected_title_present": False,
                "annual_quality_labels": [],
                "current_sample_rows_visible": False,
                "notes": ["fetch_failed"],
            })
        else:
            base.update(infer_status(source, body))
        source_results.append(base)

    groups: list[dict[str, Any]] = []
    for group_id in sorted(set(source.group_id for source in SOURCES)):
        group_sources = [result for result in source_results if result["group_id"] == group_id]
        groups.append(aggregate_group(group_id, group_sources))

    usable_groups = sum(1 for group in groups if group["usable_for_automation"])
    ready = usable_groups >= args.min_usable_activity_groups
    recommendation = "operationalize_guard" if ready else "keep_manual_or_unknown_until_sources_are_proven"
    report = {
        "generated_at": generated_at,
        "proof_version": "bathing_water_status_proof_v1",
        "verdict": {
            "ready_to_operationalize": ready,
            "usable_activity_groups": usable_groups,
            "total_activity_groups": len(groups),
            "min_usable_activity_groups": args.min_usable_activity_groups,
            "recommendation": recommendation,
        },
        "groups": groups,
        "sources": source_results,
    }

    output_path = (ROOT / args.output) if not Path(args.output).is_absolute() else Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    markdown = make_markdown(report)
    if args.markdown:
        markdown_path = (ROOT / args.markdown) if not Path(args.markdown).is_absolute() else Path(args.markdown)
        markdown_path.parent.mkdir(parents=True, exist_ok=True)
        markdown_path.write_text(markdown, encoding="utf-8")

    print(markdown)
    if args.fail_if_not_ready and not ready:
        return 3
    return 0


if __name__ == "__main__":
    sys.exit(main())
# === END FILE: scripts/check-bathing-water-status-proof.py ===
