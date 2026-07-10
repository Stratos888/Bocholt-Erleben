#!/usr/bin/env python3
# === BEGIN FILE: scripts/content_ops_decisions.py | Zweck: zentraler Reader fuer Content-Ops decision_class, Status-Aliase und Zielwirkungen ===
from __future__ import annotations

import json
import re
from datetime import date, datetime
from functools import lru_cache
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CONTRACT_PATH = ROOT / "data" / "content_ops_decision_classes.json"


def norm_token(value: Any) -> str:
    raw = str(value or "").strip().lower()
    raw = raw.translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    raw = re.sub(r"[^a-z0-9]+", "_", raw).strip("_")
    return raw


def parse_day(value: Any) -> date | None:
    raw = str(value or "").strip()[:10]
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except Exception:
        return None


@lru_cache(maxsize=4)
def load_decision_contract(path: str = str(DEFAULT_CONTRACT_PATH)) -> dict[str, Any]:
    p = Path(path)
    return json.loads(p.read_text(encoding="utf-8"))


def resolve_decision_class(row: dict[str, Any], contract: dict[str, Any] | None = None) -> str:
    contract = contract or load_decision_contract()
    classes = contract.get("decision_classes") or {}
    aliases = contract.get("status_aliases") or {}

    explicit = norm_token(row.get("decision_class"))
    if explicit in classes:
        return explicit

    for field in ("status", "review_status", "action_state", "decision", "resolution_type"):
        token = norm_token(row.get(field))
        if token in aliases:
            return str(aliases[token])
    return ""


def decision_meta(decision_class: str, contract: dict[str, Any] | None = None) -> dict[str, Any]:
    contract = contract or load_decision_contract()
    return dict((contract.get("decision_classes") or {}).get(decision_class, {}))


def is_suppression_active(row: dict[str, Any], today: date | None = None, contract: dict[str, Any] | None = None) -> bool:
    today = today or date.today()
    contract = contract or load_decision_contract()
    cls = resolve_decision_class(row, contract)
    meta = decision_meta(cls, contract)

    if cls == "snoozed":
        until = parse_day(row.get("suppress_until") or row.get("recheck_at") or row.get("next_review_at"))
        return bool(until and until >= today)

    return bool(meta.get("suppresses_reopen", False))


def target_effect(row: dict[str, Any], today: date | None = None, contract: dict[str, Any] | None = None) -> dict[str, Any]:
    today = today or date.today()
    contract = contract or load_decision_contract()
    cls = resolve_decision_class(row, contract)
    meta = decision_meta(cls, contract)
    return {
        "decision_class": cls,
        "task_state": meta.get("task_state", "open"),
        "default_effect": meta.get("default_effect", "manual_review"),
        "suppress": is_suppression_active(row, today, contract),
        "recheck": bool(meta.get("requires_recheck", False)),
        "watch_effect": bool(meta.get("requires_effect_watch", False)),
        "needs_task": meta.get("task_state") == "open",
    }


def rule_quality_metric_keys(contract: dict[str, Any] | None = None) -> list[str]:
    contract = contract or load_decision_contract()
    return [str(item) for item in contract.get("rule_quality_metrics") or []]


__all__ = [
    "DEFAULT_CONTRACT_PATH",
    "load_decision_contract",
    "norm_token",
    "parse_day",
    "resolve_decision_class",
    "decision_meta",
    "is_suppression_active",
    "target_effect",
    "rule_quality_metric_keys",
]
# === END FILE: scripts/content_ops_decisions.py ===
