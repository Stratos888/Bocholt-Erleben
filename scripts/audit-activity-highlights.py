#!/usr/bin/env python3
"""Audit seasonal Activity highlights in data/offers.json."""
from __future__ import annotations

import json
import re
import sys
from datetime import date, datetime
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
OFFERS_PATH = ROOT / "data" / "offers.json"
REQUIRED = {
    "id",
    "type",
    "label",
    "short_label",
    "starts",
    "ends",
    "confidence",
    "source_url",
    "checked_at",
    "valid_until",
    "public_note",
}
CONFIDENCE = {"verified_stable", "verified_current", "official_source"}
MONTH_DAY_RE = re.compile(r"^\d{2}-\d{2}$")


def load_offers() -> list[dict]:
    payload = json.loads(OFFERS_PATH.read_text(encoding="utf-8"))
    offers = payload if isinstance(payload, list) else payload.get("offers", [])
    if not isinstance(offers, list):
        raise ValueError("offers.json must contain a list or an object with offers[]")
    return offers


def parse_iso_date(value: str) -> date:
    return datetime.strptime(value, "%Y-%m-%d").date()


def validate_month_day(value: str, field: str, context: str) -> list[str]:
    if not MONTH_DAY_RE.match(value or ""):
        return [f"{context}: {field} must use MM-DD"]
    month, day = (int(part) for part in value.split("-"))
    try:
        date(2026, month, day)
    except ValueError:
        return [f"{context}: {field} is not a valid month/day"]
    return []


def validate_url(value: str, context: str) -> list[str]:
    parsed = urlparse(value or "")
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        return [f"{context}: source_url must be an absolute http(s) URL"]
    return []


def validate_highlight(offer_id: str, highlight: dict, index: int) -> list[str]:
    context = f"{offer_id}.seasonal_highlights[{index}]"
    errors: list[str] = []
    missing = sorted(REQUIRED - set(highlight))
    if missing:
        errors.append(f"{context}: missing required fields: {', '.join(missing)}")

    for field in ["id", "type", "label", "short_label", "confidence", "source_url", "checked_at", "valid_until", "public_note"]:
        if field in highlight and not str(highlight.get(field) or "").strip():
            errors.append(f"{context}: {field} must not be empty")

    if highlight.get("confidence") and highlight.get("confidence") not in CONFIDENCE:
        errors.append(f"{context}: unsupported confidence {highlight.get('confidence')!r}")

    for field in ["starts", "ends", "peak_starts", "peak_ends"]:
        if field in highlight:
            errors.extend(validate_month_day(str(highlight.get(field) or ""), field, context))

    if "source_url" in highlight:
        errors.extend(validate_url(str(highlight.get("source_url") or ""), context))

    for field in ["checked_at", "valid_until"]:
        if field in highlight:
            try:
                parse_iso_date(str(highlight.get(field) or ""))
            except ValueError:
                errors.append(f"{context}: {field} must use YYYY-MM-DD")

    if highlight.get("valid_until"):
        try:
            if parse_iso_date(str(highlight["valid_until"])) < date.today():
                errors.append(f"{context}: valid_until is in the past")
        except ValueError:
            pass

    for field in ["home_boost", "activity_sort_boost"]:
        if field in highlight:
            try:
                value = int(highlight[field])
            except (TypeError, ValueError):
                errors.append(f"{context}: {field} must be an integer")
                continue
            if value < 0 or value > 40:
                errors.append(f"{context}: {field} must be between 0 and 40")

    return errors


def main() -> int:
    offers = load_offers()
    errors: list[str] = []
    count = 0

    for offer in offers:
        offer_id = str(offer.get("id") or "").strip() or "<missing-id>"
        highlights = offer.get("seasonal_highlights", [])
        if highlights is None:
            continue
        if not isinstance(highlights, list):
            errors.append(f"{offer_id}.seasonal_highlights must be a list")
            continue
        for index, highlight in enumerate(highlights):
            count += 1
            if not isinstance(highlight, dict):
                errors.append(f"{offer_id}.seasonal_highlights[{index}] must be an object")
                continue
            errors.extend(validate_highlight(offer_id, highlight, index))

    if errors:
        print("ACTIVITY_HIGHLIGHTS_AUDIT_FAILED")
        for error in errors:
            print(f"ERROR: {error}")
        return 1

    print("ACTIVITY_HIGHLIGHTS_AUDIT_OK")
    print(f"offers={len(offers)}")
    print(f"seasonal_highlights={count}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
