#!/usr/bin/env python3
# === BEGIN FILE: scripts/audit-activity-highlights.py | Zweck: prueft Seasonal-Activity-Highlights auf harte Quellen-/Status-Gates; Umfang: lokaler Repo-Audit ohne externe Schreibzugriffe ===
from __future__ import annotations

import argparse
import json
import re
import sys
from datetime import date, datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
RE_ISO_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
RE_MONTH_DAY = re.compile(r"^\d{2}-\d{2}$")
ACTIVE_MODES = {"stable_seasonal", "condition_sensitive"}
HIDDEN_MODES = {"event_only", "candidate_only"}
STATUS_VALUES = {"ok", "watch", "blocked", "unknown"}
OFFICIAL_POSITIVE_SOURCE_TYPES = {
    "official_city",
    "official_authority",
    "official_bathing_water_status",
    "official_operator",
}


def norm(value: Any) -> str:
    return str(value or "").strip()


def parse_iso_date(value: Any) -> date | None:
    text = norm(value)[:10]
    if not RE_ISO_DATE.match(text):
        return None
    try:
        return datetime.strptime(text, "%Y-%m-%d").date()
    except ValueError:
        return None


def valid_month_day(value: Any) -> bool:
    text = norm(value)
    if not RE_MONTH_DAY.match(text):
        return False
    try:
        datetime.strptime(f"2024-{text}", "%Y-%m-%d")
        return True
    except ValueError:
        return False


def month_day_ord(value: str) -> int:
    month, day = map(int, value.split("-"))
    return month * 100 + day


def in_month_day_window(today: date, starts: str, ends: str) -> bool:
    if not valid_month_day(starts) or not valid_month_day(ends):
        return False
    current = today.month * 100 + today.day
    start = month_day_ord(starts)
    end = month_day_ord(ends)
    if start <= end:
        return start <= current <= end
    return current >= start or current <= end


def add_error(errors: list[str], offer_id: str, highlight_id: str, message: str) -> None:
    errors.append(f"{offer_id}/{highlight_id}: {message}")


def audit_highlight(offer: dict[str, Any], highlight: dict[str, Any], today: date, *, scope: str) -> tuple[list[str], list[str], dict[str, int]]:
    errors: list[str] = []
    warnings: list[str] = []
    stats = {
        "stable": 0,
        "condition_sensitive": 0,
        "condition_in_season": 0,
        "condition_currently_playable": 0,
        "blocked": 0,
        "unknown": 0,
    }

    offer_id = norm(offer.get("id")) or "<missing-offer-id>"
    highlight_id = norm(highlight.get("id")) or "<missing-highlight-id>"
    mode = norm(highlight.get("activation_mode") or "stable_seasonal").lower()
    starts = norm(highlight.get("starts"))
    ends = norm(highlight.get("ends"))

    if not highlight_id or highlight_id == "<missing-highlight-id>":
        add_error(errors, offer_id, highlight_id, "id fehlt")
    if mode not in ACTIVE_MODES and mode not in HIDDEN_MODES:
        add_error(errors, offer_id, highlight_id, f"activation_mode ungueltig: {mode!r}")
    if not norm(highlight.get("label")):
        add_error(errors, offer_id, highlight_id, "label fehlt")
    if not valid_month_day(starts):
        add_error(errors, offer_id, highlight_id, f"starts ungueltig: {starts!r}")
    if not valid_month_day(ends):
        add_error(errors, offer_id, highlight_id, f"ends ungueltig: {ends!r}")

    if mode in HIDDEN_MODES:
        if highlight.get("home_boost") or highlight.get("activity_sort_boost"):
            add_error(errors, offer_id, highlight_id, "event_only/candidate_only darf keinen Boost haben")
        return errors, warnings, stats

    if not norm(highlight.get("source_url")):
        add_error(errors, offer_id, highlight_id, "source_url fehlt")
    if not parse_iso_date(highlight.get("checked_at")):
        add_error(errors, offer_id, highlight_id, "checked_at fehlt oder ist ungueltig")
    valid_until = parse_iso_date(highlight.get("valid_until"))
    if not valid_until:
        add_error(errors, offer_id, highlight_id, "valid_until fehlt oder ist ungueltig")
    elif valid_until < today:
        add_error(errors, offer_id, highlight_id, f"valid_until ist abgelaufen: {valid_until.isoformat()}")

    if mode == "stable_seasonal":
        stats["stable"] += 1
        if norm(highlight.get("confidence")) != "verified_stable":
            add_error(errors, offer_id, highlight_id, "stable_seasonal braucht confidence=verified_stable")
        if not norm(highlight.get("public_note")):
            warnings.append(f"{offer_id}/{highlight_id}: public_note fehlt")
        return errors, warnings, stats

    if mode == "condition_sensitive":
        stats["condition_sensitive"] += 1
        in_season = in_month_day_window(today, starts, ends)
        if in_season:
            stats["condition_in_season"] += 1
        if highlight.get("requires_current_status") is not True:
            add_error(errors, offer_id, highlight_id, "condition_sensitive braucht requires_current_status=true")

        status = highlight.get("current_status") if isinstance(highlight.get("current_status"), dict) else {}
        state = norm(status.get("state") or "unknown").lower()
        if state not in STATUS_VALUES:
            add_error(errors, offer_id, highlight_id, f"current_status.state ungueltig: {state!r}")
            state = "unknown"
        if state == "blocked":
            stats["blocked"] += 1
        if state == "unknown":
            stats["unknown"] += 1

        status_valid_until = parse_iso_date(status.get("valid_until"))
        status_checked_at = parse_iso_date(status.get("checked_at"))
        if state == "ok":
            source_type = norm(status.get("source_type")).lower()
            if source_type not in OFFICIAL_POSITIVE_SOURCE_TYPES:
                add_error(errors, offer_id, highlight_id, f"positive Freigabe braucht offizielle Quelle, source_type={source_type!r}")
            if not norm(status.get("source_url")):
                add_error(errors, offer_id, highlight_id, "positive Freigabe braucht current_status.source_url")
            if not status_checked_at or not status_valid_until or status_valid_until < today:
                add_error(errors, offer_id, highlight_id, "positive Freigabe ist nicht frisch/gueltig")
            else:
                max_age = int(highlight.get("current_status_max_age_days") or 7)
                if (today - status_checked_at).days > max_age:
                    add_error(errors, offer_id, highlight_id, f"positive Freigabe ist aelter als {max_age} Tage")
                elif in_season:
                    stats["condition_currently_playable"] += 1
        elif state in {"blocked", "watch"}:
            if not status_checked_at:
                add_error(errors, offer_id, highlight_id, f"{state} braucht checked_at")
            if not status_valid_until:
                add_error(errors, offer_id, highlight_id, f"{state} braucht valid_until")
            elif status_valid_until < today:
                add_error(errors, offer_id, highlight_id, f"{state}-Status ist abgelaufen und muss neu bewertet werden")
            if not norm(status.get("reason")):
                warnings.append(f"{offer_id}/{highlight_id}: {state} ohne reason")
        elif in_season and scope in {"daily", "full"}:
            warnings.append(f"{offer_id}/{highlight_id}: in Saison, aber current_status=unknown; vor Bade-/Statushighlight pruefen")

    return errors, warnings, stats


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit seasonal activity highlights")
    parser.add_argument("--offers-json", default="data/offers.json")
    parser.add_argument("--scope", choices=["deploy-gate", "daily", "full"], default="deploy-gate")
    parser.add_argument("--today", default="")
    args = parser.parse_args()

    today = parse_iso_date(args.today) if args.today else date.today()
    if today is None:
        print(f"Invalid --today: {args.today}", file=sys.stderr)
        return 2

    path = ROOT / args.offers_json
    data = json.loads(path.read_text(encoding="utf-8"))
    offers = data.get("offers") if isinstance(data, dict) else []
    if not isinstance(offers, list):
        print("data/offers.json has no offers list", file=sys.stderr)
        return 2

    errors: list[str] = []
    warnings: list[str] = []
    totals = {
        "offers": len(offers),
        "seasonal_highlights": 0,
        "stable": 0,
        "condition_sensitive": 0,
        "condition_in_season": 0,
        "condition_currently_playable": 0,
        "blocked": 0,
        "unknown": 0,
    }

    for offer in offers:
        if not isinstance(offer, dict):
            continue
        highlights = offer.get("seasonal_highlights") or []
        if not isinstance(highlights, list):
            errors.append(f"{norm(offer.get('id'))}: seasonal_highlights ist keine Liste")
            continue
        for highlight in highlights:
            if not isinstance(highlight, dict):
                errors.append(f"{norm(offer.get('id'))}: seasonal_highlights enthaelt keinen Objekt-Eintrag")
                continue
            totals["seasonal_highlights"] += 1
            item_errors, item_warnings, stats = audit_highlight(offer, highlight, today, scope=args.scope)
            errors.extend(item_errors)
            warnings.extend(item_warnings)
            for key, value in stats.items():
                totals[key] = totals.get(key, 0) + value

    if errors:
        print("ACTIVITY_HIGHLIGHTS_AUDIT_FAILED", file=sys.stderr)
        for item in errors:
            print(f"ERROR: {item}", file=sys.stderr)
        for item in warnings:
            print(f"WARN: {item}", file=sys.stderr)
        return 1

    print("ACTIVITY_HIGHLIGHTS_AUDIT_OK")
    for key in ["offers", "seasonal_highlights", "stable", "condition_sensitive", "condition_in_season", "condition_currently_playable", "blocked", "unknown"]:
        print(f"{key}={totals.get(key, 0)}")
    for item in warnings:
        print(f"WARN: {item}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/audit-activity-highlights.py ===
