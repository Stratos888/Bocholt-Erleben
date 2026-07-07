#!/usr/bin/env python3
# === BEGIN FILE: scripts/content_ops_visual_feedback.py | Zweck: zentraler Reader fuer Visual-Feedback-Problemtypen, decision_class und Folgeaktionen ===
from __future__ import annotations

import json
import re
from functools import lru_cache
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CONTRACT_PATH = ROOT / "data" / "content_ops_visual_feedback_contract.json"


def norm_token(value: Any) -> str:
    raw = str(value or "").strip().lower()
    raw = raw.translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    raw = re.sub(r"[^a-z0-9]+", "_", raw).strip("_")
    return raw


@lru_cache(maxsize=4)
def load_visual_feedback_contract(path: str = str(DEFAULT_CONTRACT_PATH)) -> dict[str, Any]:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def _contract_result(problem_type: str, contract: dict[str, Any]) -> dict[str, Any]:
    problem_types = contract.get("problem_types") or {}
    meta = dict(problem_types.get(problem_type) or problem_types.get("visual_review") or {})
    return {
        "problem_type": problem_type if problem_type in problem_types else "visual_review",
        "decision_class": str(meta.get("decision_class") or "needs_visual_fix"),
        "default_effect": str(meta.get("default_effect") or "visual_review_task"),
        "followup_route": str(meta.get("followup_route") or "visual_review"),
        "requires_task": bool(meta.get("requires_task", True)),
        "label": str(meta.get("label") or problem_type),
    }


def classify_visual_issue(row: dict[str, Any], contract: dict[str, Any] | None = None) -> dict[str, Any]:
    contract = contract or load_visual_feedback_contract()
    problem_types = contract.get("problem_types") or {}

    explicit = norm_token(row.get("visual_problem_type") or row.get("problem_type") or row.get("visual_issue_type"))
    if explicit in problem_types:
        return _contract_result(explicit, contract)

    issue_code = norm_token(row.get("issue_code"))
    category = norm_token(row.get("process_category"))
    route = norm_token(row.get("action_route") or row.get("safe_action") or row.get("route"))
    asset_status = norm_token(row.get("visual_asset_status") or row.get("asset_status"))
    visual_key = norm_token(row.get("visual_key") or row.get("current_visual_key"))
    suggested_key = norm_token(row.get("suggested_visual_key"))
    text = " ".join([
        issue_code,
        category,
        route,
        asset_status,
        visual_key,
        suggested_key,
        norm_token(row.get("title")),
        norm_token(row.get("recommended_action")),
        norm_token(row.get("decision_note")),
        norm_token(row.get("visual_motif_status")),
        norm_token(row.get("motif_fit")),
    ])

    if any(token in text for token in ("accepted", "valid_visual", "visual_ok")):
        return _contract_result("accepted", contract)
    if any(token in text for token in ("rights", "copyright", "license", "lizenz", "quelle", "source_rights")):
        return _contract_result("source_or_rights_issue", contract)
    if any(token in text for token in ("asset_gap", "asset_missing", "missing_asset", "no_asset", "fallback_missing", "image_missing")) or asset_status in {"missing", "gap", "asset_gap", "missing_asset"}:
        return _contract_result("asset_missing", contract)
    if any(token in text for token in ("low_quality", "bad_quality", "blurry", "blurred", "remaster", "crop_bad", "upscale")):
        return _contract_result("asset_low_quality", contract)
    if any(token in text for token in ("visual_key_wrong", "key_wrong", "wrong_key", "resolver", "mapping_wrong")):
        return _contract_result("visual_key_wrong", contract)
    if suggested_key and visual_key and suggested_key != visual_key:
        return _contract_result("visual_key_wrong", contract)
    if any(token in text for token in ("motif_wrong", "motiv_wrong", "motif_fit", "motiv_passt_nicht", "motif_mismatch", "visual_motif")):
        return _contract_result("visual_motif_wrong", contract)
    if "visual" in text:
        return _contract_result("visual_review", contract)

    return {
        "problem_type": "",
        "decision_class": "",
        "default_effect": "",
        "followup_route": "",
        "requires_task": False,
        "label": "",
    }


__all__ = [
    "DEFAULT_CONTRACT_PATH",
    "load_visual_feedback_contract",
    "classify_visual_issue",
    "norm_token",
]
# === END FILE: scripts/content_ops_visual_feedback.py ===
