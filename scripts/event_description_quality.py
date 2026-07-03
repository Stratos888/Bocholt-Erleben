#!/usr/bin/env python3
"""Bocholt erleben event description quality contract.

Purpose:
- Keep public event descriptions locally editorial, warm, concise and fact-based.
- Detect internal source notes, generic AI prose, marketing language, title repetition
  and length problems before they reach public event pages.
- Provide one shared rule set for Weekly KI, manual intake, Inbox->Events import,
  content audit and public build.

This module does not call external services and does not invent text.
"""
from __future__ import annotations

import json
import re
import unicodedata
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, Optional, Tuple

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OVERRIDES_PATH = ROOT / "data" / "event_description_overrides.json"

SOFT_MIN_CHARS = 70
HARD_MIN_CHARS = 38
IDEAL_MAX_CHARS = 180
SOFT_MAX_CHARS = 220
HARD_MAX_CHARS = 280

BLOCKING_CODES = {
    "event_description_missing",
    "event_description_too_short_hard",
    "event_description_too_long_hard",
    "event_description_internal_source_leak",
    "event_description_generic_ai_prose",
    "event_description_marketing_language",
    "event_description_title_repetition",
    "event_description_linebreak",
}

WARNING_CODES = {
    "event_description_too_short",
    "event_description_too_long",
    "event_description_uncertain_wording",
    "event_description_date_place_redundancy",
}

_INTERNAL_SOURCE_PATTERNS: tuple[tuple[str, re.Pattern[str]], ...] = (
    ("PDF-/Dateihinweis", re.compile(r"\b(pdf|newsletter[-\s]?pdf|download|office[-\s]?datei|datei[-\s]?download)\b", re.I)),
    ("Quellenherleitung", re.compile(r"\b(laut\s+(?:quelle|programm|dokument|angabe)|(?:quelle|programm|dokument)\s+nennt|offiziell(?:e|es|er)?\s+.*\bnennt)\b", re.I)),
    ("Recherche-Notiz", re.compile(r"\b(das\s+programm\s+nennt|die\s+quelle\s+nennt|aus\s+der\s+quelle\s+geht\s+hervor|laut\s+website)\b", re.I)),
)

_GENERIC_AI_PATTERNS: tuple[tuple[str, re.Pattern[str]], ...] = (
    ("alltägliches Leben", re.compile(r"\bteil\s+des\s+alltäglichen\s+lebens\b", re.I)),
    ("bringt Bewegung", re.compile(r"\bbringt\s+bewegung\s+in\s+die\s+stadt\b", re.I)),
    ("bewusst wahrnehmen", re.compile(r"\bbewusst\s+wahrzunehmen\b", re.I)),
    ("Stände Wege Begegnungen", re.compile(r"\bstände,?\s+wege\s+und\s+begegnungen\b", re.I)),
    ("natürlicher Teil des Tages", re.compile(r"\bnatürlichen?\s+teil\s+des\s+tages\b", re.I)),
    ("Atmosphäre spürbar", re.compile(r"\batmosphäre\s+spürbar\b", re.I)),
    ("Musik übernimmt Raum", re.compile(r"\bwenn\s+musik\s+den\s+raum\s+übernimmt\b", re.I)),
    ("kulturelles Leben", re.compile(r"\bteil\s+des\s+kulturellen\s+lebens\b", re.I)),
    ("eigene Geschichten", re.compile(r"\berzählen\s+ihre\s+eigenen\s+geschichten\b", re.I)),
    ("andere Perspektive", re.compile(r"\baus\s+einer\s+anderen\s+perspektive\b", re.I)),
    ("Treffpunkt-Floskel", re.compile(r"\bfür\s+einen\s+abend\s+zum\s+treffpunkt\b", re.I)),
)

_MARKETING_PATTERNS: tuple[tuple[str, re.Pattern[str]], ...] = (
    ("Highlight", re.compile(r"\bhighlight\b", re.I)),
    ("unvergesslich", re.compile(r"\bunvergesslich\b", re.I)),
    ("für Jung und Alt", re.compile(r"\bfür\s+jung\s+und\s+alt\b", re.I)),
    ("lässt keine Wünsche offen", re.compile(r"\blässt\s+keine\s+wünsche\s+offen\b", re.I)),
    ("ein Muss", re.compile(r"\bein\s+muss\b", re.I)),
    ("einzigartig", re.compile(r"\beinzigartig\b", re.I)),
    ("toll", re.compile(r"\btolle?s?\b", re.I)),
    ("perfekt", re.compile(r"\bperfekt(?:e|er|es|en)?\b", re.I)),
)

_UNCERTAIN_PATTERNS: tuple[tuple[str, re.Pattern[str]], ...] = (
    ("geplant", re.compile(r"\bgeplant\s+(?:ist|sind|soll|wird|werden)\b", re.I)),
    ("soll veröffentlicht werden", re.compile(r"\bsoll(?:en|te|ten)?\s+(?:auf|über|unter|im|in|veröffentlicht|bekanntgegeben)", re.I)),
    ("voraussichtlich", re.compile(r"\bvoraussichtlich\b", re.I)),
    ("angekündigt", re.compile(r"\bangekündigt\b", re.I)),
)

_DATE_RE = re.compile(r"\b\d{1,2}\.(?:\d{1,2}\.|\s*(?:januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember))", re.I)
_TIME_RE = re.compile(r"\b\d{1,2}[:.]\d{2}\b")


@dataclass(frozen=True)
class DescriptionFinding:
    code: str
    severity: str
    detail: str
    blocking: bool = False

    def asdict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class DescriptionQualityResult:
    description: str
    findings: tuple[DescriptionFinding, ...]
    override_applied: bool = False
    override_reason: str = ""

    @property
    def blocking(self) -> bool:
        return any(item.blocking for item in self.findings)

    @property
    def warning(self) -> bool:
        return bool(self.findings) and not self.blocking

    @property
    def issue_codes(self) -> list[str]:
        return [item.code for item in self.findings]

    def summary(self, limit: int = 3) -> str:
        if not self.findings:
            return "ok"
        items = [f"{f.code}: {f.detail}" for f in self.findings[:limit]]
        if len(self.findings) > limit:
            items.append(f"+{len(self.findings) - limit} weitere")
        return "; ".join(items)


def normalize_text(value: Any) -> str:
    text = str(value or "").replace("\u00A0", " ")
    text = unicodedata.normalize("NFC", text)
    text = re.sub(r"[ \t]+", " ", text)
    return text.strip()


def text_key(value: Any) -> str:
    text = normalize_text(value).lower()
    replacements = {
        "ä": "ae",
        "ö": "oe",
        "ü": "ue",
        "ß": "ss",
        "é": "e",
        "è": "e",
        "á": "a",
        "à": "a",
    }
    for source, target in replacements.items():
        text = text.replace(source, target)
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def compact_title_for_repetition(title: str) -> str:
    key = text_key(title)
    key = re.sub(r"\b20\d{2}\b", "", key)
    key = re.sub(r"\s+", " ", key).strip()
    return key


def description_starts_with_title(title: str, description: str) -> bool:
    title_key = compact_title_for_repetition(title)
    desc_key = text_key(description)
    if not title_key or not desc_key:
        return False
    if len(title_key) < 8:
        return False
    if desc_key.startswith(title_key + " ") or desc_key == title_key:
        return True
    # Typische Sheet-/KI-Form: "Titel: ..."
    raw_title = normalize_text(title).rstrip(":")
    raw_desc = normalize_text(description)
    return bool(raw_title and raw_desc.lower().startswith(raw_title.lower() + ":"))


def pattern_findings(patterns: Iterable[tuple[str, re.Pattern[str]]], description: str, code: str, severity: str, blocking: bool) -> list[DescriptionFinding]:
    out: list[DescriptionFinding] = []
    for label, pattern in patterns:
        match = pattern.search(description)
        if match:
            out.append(DescriptionFinding(code, severity, f"{label}: {match.group(0)}", blocking))
            break
    return out


def evaluate_event_description(event: Mapping[str, Any]) -> DescriptionQualityResult:
    """Evaluate a final/public event description without changing it."""
    title = normalize_text(event.get("title", ""))
    description = normalize_text(event.get("description", ""))
    location = normalize_text(event.get("location", ""))
    city = normalize_text(event.get("city", ""))
    findings: list[DescriptionFinding] = []

    if not description:
        findings.append(DescriptionFinding("event_description_missing", "review_needed", "description ist leer", True))
        return DescriptionQualityResult(description, tuple(findings))

    if "\n" in str(event.get("description", "")) or "\r" in str(event.get("description", "")):
        findings.append(DescriptionFinding("event_description_linebreak", "review_needed", "sichtbarer Zeilenumbruch in description", True))

    length = len(description)
    if length < HARD_MIN_CHARS:
        findings.append(DescriptionFinding("event_description_too_short_hard", "review_needed", f"{length} Zeichen", True))
    elif length < SOFT_MIN_CHARS:
        findings.append(DescriptionFinding("event_description_too_short", "warning", f"{length} Zeichen; Ziel sind ca. 80–180 Zeichen", False))
    if length > HARD_MAX_CHARS:
        findings.append(DescriptionFinding("event_description_too_long_hard", "review_needed", f"{length} Zeichen", True))
    elif length > SOFT_MAX_CHARS:
        findings.append(DescriptionFinding("event_description_too_long", "warning", f"{length} Zeichen; Ziel sind ca. 80–180 Zeichen", False))

    if description_starts_with_title(title, description):
        findings.append(DescriptionFinding("event_description_title_repetition", "review_needed", "description beginnt mit dem Titel", True))

    findings.extend(pattern_findings(_INTERNAL_SOURCE_PATTERNS, description, "event_description_internal_source_leak", "review_needed", True))
    findings.extend(pattern_findings(_GENERIC_AI_PATTERNS, description, "event_description_generic_ai_prose", "review_needed", True))
    findings.extend(pattern_findings(_MARKETING_PATTERNS, description, "event_description_marketing_language", "review_needed", True))
    findings.extend(pattern_findings(_UNCERTAIN_PATTERNS, description, "event_description_uncertain_wording", "warning", False))

    # Datum/Uhrzeit stehen bereits in eigenen Feldern. Lokale Ortsbegriffe sind dagegen
    # fuer den Bocholt-erleben-Ton oft sinnvoll und werden nicht pauschal beanstandet.
    if _DATE_RE.search(description) or _TIME_RE.search(description):
        findings.append(DescriptionFinding("event_description_date_time_redundancy", "warning", "Datum/Uhrzeit im Beschreibungstext", False))

    # Deduplicate by code, keep first detail.
    seen: set[str] = set()
    unique: list[DescriptionFinding] = []
    for item in findings:
        if item.code in seen:
            continue
        seen.add(item.code)
        unique.append(item)

    return DescriptionQualityResult(description, tuple(unique))


def has_blocking_description_findings(result: DescriptionQualityResult) -> bool:
    return result.blocking


def load_description_overrides(path: Path = DEFAULT_OVERRIDES_PATH) -> dict[str, dict[str, str]]:
    if not path.exists():
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return {}
    raw_overrides = data.get("overrides") if isinstance(data, dict) else data
    if not isinstance(raw_overrides, dict):
        return {}
    out: dict[str, dict[str, str]] = {}
    for key, value in raw_overrides.items():
        event_id = normalize_text(key)
        if not event_id:
            continue
        if isinstance(value, str):
            desc = normalize_text(value)
            if desc:
                out[event_id] = {"description": desc, "reason": "curated_override"}
        elif isinstance(value, dict):
            desc = normalize_text(value.get("description", ""))
            if desc:
                out[event_id] = {
                    "description": desc,
                    "reason": normalize_text(value.get("reason", "curated_override")),
                    "updated_at": normalize_text(value.get("updated_at", "")),
                }
    return out


def apply_description_override(event: Mapping[str, Any], overrides: Optional[Mapping[str, Mapping[str, str]]] = None) -> DescriptionQualityResult:
    overrides = overrides if overrides is not None else load_description_overrides()
    event_id = normalize_text(event.get("id", ""))
    if event_id and event_id in overrides:
        override = overrides[event_id]
        patched = dict(event)
        patched["description"] = normalize_text(override.get("description", ""))
        result = evaluate_event_description(patched)
        return DescriptionQualityResult(
            result.description,
            result.findings,
            override_applied=True,
            override_reason=normalize_text(override.get("reason", "curated_override")),
        )
    return evaluate_event_description(event)


def issue_summary(result: DescriptionQualityResult) -> str:
    return result.summary()


def blocking_issue_codes(result: DescriptionQualityResult) -> list[str]:
    return [item.code for item in result.findings if item.blocking]


def warning_issue_codes(result: DescriptionQualityResult) -> list[str]:
    return [item.code for item in result.findings if not item.blocking]
