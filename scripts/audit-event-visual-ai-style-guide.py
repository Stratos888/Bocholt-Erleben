# === BEGIN FILE: scripts/audit-event-visual-ai-style-guide.py | Zweck: prueft den kanonischen KI-Regelvertrag fuer Event-Visuals; Umfang: Konsistenzcheck fuer Style Guide und Visual-Key-Abdeckung ===
from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any, Dict, List

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_keys import ALLOWED_EVENT_VISUAL_KEYS

GUIDE_PATH = ROOT / "data" / "event_visual_ai_style_guide.json"

REQUIRED_TOP_LEVEL_KEYS = {
    "schema_version",
    "owner",
    "purpose",
    "generation_contract",
    "approved_pilot_anchors",
    "global_style",
    "global_negative_rules",
    "quality_gate_policy",
    "image_classes",
    "prompt_building_rules",
    "visual_key_rules",
}

REQUIRED_ANCHORS = {
    "city_walk_v2",
    "market_food_v3",
    "creative_workshop_v2",
}

ALLOWED_IMAGE_CLASSES = {
    "local_sensitive_detail",
    "hybrid_contextual",
    "symbolic_editorial",
}

REQUIRED_RULE_FIELDS = {
    "image_class",
    "priority",
    "anchor_profiles",
    "locality_requirement",
    "subject_focus",
    "prompt_must_include",
    "prompt_avoid",
    "people_policy",
    "review_emphasis",
}

PRIORITIES = {"high", "medium", "low"}


def read_json(path: Path) -> Dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} fehlt.")
    except json.JSONDecodeError as exc:
        raise SystemExit(
            f"FEHLER: {path.relative_to(ROOT)} ist kein gültiges JSON "
            f"({exc.msg}, Zeile {exc.lineno}, Spalte {exc.colno})."
        )

    if not isinstance(value, dict):
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} muss ein JSON-Objekt sein.")
    return value


def is_non_empty_str(value: Any) -> bool:
    return isinstance(value, str) and bool(value.strip())


def is_non_empty_list(value: Any) -> bool:
    return isinstance(value, list) and any(bool(str(item).strip()) for item in value)


def main() -> int:
    guide = read_json(GUIDE_PATH)
    errors: List[str] = []

    top_level_missing = sorted(REQUIRED_TOP_LEVEL_KEYS - set(guide.keys()))
    if top_level_missing:
        errors.append(f"Fehlende Top-Level-Keys: {', '.join(top_level_missing)}")

    generation_contract = guide.get("generation_contract", {})
    if not isinstance(generation_contract, dict):
        errors.append("generation_contract muss ein Objekt sein.")
    else:
        if generation_contract.get("source_type") != "ai_generated":
            errors.append("generation_contract.source_type muss ai_generated sein.")
        if generation_contract.get("is_symbolic") is not True:
            errors.append("generation_contract.is_symbolic muss true sein.")
        if generation_contract.get("allow_false_place_claim") is not False:
            errors.append("generation_contract.allow_false_place_claim muss false sein.")

    anchors = guide.get("approved_pilot_anchors", {})
    if not isinstance(anchors, dict):
        errors.append("approved_pilot_anchors muss ein Objekt sein.")
        anchors = {}
    else:
        missing_anchors = sorted(REQUIRED_ANCHORS - set(anchors.keys()))
        if missing_anchors:
            errors.append(f"Fehlende Pilotanker: {', '.join(missing_anchors)}")

        for anchor_id, anchor in anchors.items():
            if not isinstance(anchor, dict):
                errors.append(f"{anchor_id}: Anchor-Eintrag muss ein Objekt sein.")
                continue
            if anchor.get("accepted_as_style_anchor") is not True:
                errors.append(f"{anchor_id}: accepted_as_style_anchor muss true sein.")
            for field in ("role",):
                if not is_non_empty_str(anchor.get(field)):
                    errors.append(f"{anchor_id}: {field} fehlt oder ist leer.")
            for field in ("applicable_image_classes", "strengths", "avoid_transferring"):
                if not is_non_empty_list(anchor.get(field)):
                    errors.append(f"{anchor_id}: {field} fehlt oder ist leer.")

    global_style = guide.get("global_style", {})
    if not isinstance(global_style, dict):
        errors.append("global_style muss ein Objekt sein.")
    else:
        for field in (
            "desired_visual_language",
            "lighting_rules",
            "composition_rules",
            "realism_rules",
            "legal_safety_rules",
        ):
            if not is_non_empty_list(global_style.get(field)):
                errors.append(f"global_style.{field} fehlt oder ist leer.")

    global_negative_rules = guide.get("global_negative_rules")
    if not is_non_empty_list(global_negative_rules):
        errors.append("global_negative_rules fehlt oder ist leer.")

    quality_gate_policy = guide.get("quality_gate_policy", {})
    if not isinstance(quality_gate_policy, dict):
        errors.append("quality_gate_policy muss ein Objekt sein.")
        quality_gate_policy = {}
    else:
        if not is_non_empty_list(quality_gate_policy.get("hard_fail_gates")):
            errors.append("quality_gate_policy.hard_fail_gates fehlt oder ist leer.")
        if not is_non_empty_list(quality_gate_policy.get("soft_review_gates")):
            errors.append("quality_gate_policy.soft_review_gates fehlt oder ist leer.")
        if not is_non_empty_str(quality_gate_policy.get("pass_rule")):
            errors.append("quality_gate_policy.pass_rule fehlt oder ist leer.")

        gates = quality_gate_policy.get("gates", {})
        if not isinstance(gates, dict) or not gates:
            errors.append("quality_gate_policy.gates fehlt oder ist leer.")
            gates = {}

        for gate_id in list(quality_gate_policy.get("hard_fail_gates", [])) + list(quality_gate_policy.get("soft_review_gates", [])):
            if gate_id not in gates:
                errors.append(f"Qualitäts-Gate {gate_id} ist referenziert, aber nicht definiert.")

        for gate_id, gate in gates.items():
            if not isinstance(gate, dict):
                errors.append(f"Gate {gate_id} muss ein Objekt sein.")
                continue
            for field in ("description", "pass_requirement", "failure_signals"):
                if field == "failure_signals":
                    if not is_non_empty_list(gate.get(field)):
                        errors.append(f"Gate {gate_id}.{field} fehlt oder ist leer.")
                else:
                    if not is_non_empty_str(gate.get(field)):
                        errors.append(f"Gate {gate_id}.{field} fehlt oder ist leer.")

    image_classes = guide.get("image_classes", {})
    if not isinstance(image_classes, dict):
        errors.append("image_classes muss ein Objekt sein.")
        image_classes = {}
    else:
        missing_classes = sorted(ALLOWED_IMAGE_CLASSES - set(image_classes.keys()))
        if missing_classes:
            errors.append(f"Fehlende image_classes: {', '.join(missing_classes)}")

        for class_id, class_payload in image_classes.items():
            if not isinstance(class_payload, dict):
                errors.append(f"image_class {class_id} muss ein Objekt sein.")
                continue
            for field in ("description", "core_rule"):
                if not is_non_empty_str(class_payload.get(field)):
                    errors.append(f"image_class {class_id}.{field} fehlt oder ist leer.")
            for field in ("preferred_anchor_profiles", "prompt_bias", "avoid_bias"):
                if not is_non_empty_list(class_payload.get(field)):
                    errors.append(f"image_class {class_id}.{field} fehlt oder ist leer.")
                else:
                    if field == "preferred_anchor_profiles":
                        for anchor_id in class_payload.get(field, []):
                            if anchor_id not in anchors:
                                errors.append(f"image_class {class_id} referenziert unbekannten Anchor {anchor_id}.")

    prompt_building_rules = guide.get("prompt_building_rules", {})
    if not isinstance(prompt_building_rules, dict):
        errors.append("prompt_building_rules muss ein Objekt sein.")
    else:
        for field in ("global_positive_clauses", "global_negative_clauses"):
            if not is_non_empty_list(prompt_building_rules.get(field)):
                errors.append(f"prompt_building_rules.{field} fehlt oder ist leer.")
        class_specific_clauses = prompt_building_rules.get("class_specific_clauses", {})
        if not isinstance(class_specific_clauses, dict):
            errors.append("prompt_building_rules.class_specific_clauses muss ein Objekt sein.")
        else:
            for class_id in ALLOWED_IMAGE_CLASSES:
                if not is_non_empty_list(class_specific_clauses.get(class_id)):
                    errors.append(f"prompt_building_rules.class_specific_clauses.{class_id} fehlt oder ist leer.")

    visual_key_rules = guide.get("visual_key_rules", {})
    if not isinstance(visual_key_rules, dict):
        errors.append("visual_key_rules muss ein Objekt sein.")
        visual_key_rules = {}
    else:
        allowed_keys = set(ALLOWED_EVENT_VISUAL_KEYS)
        rule_keys = set(visual_key_rules.keys())
        missing_keys = sorted(allowed_keys - rule_keys)
        unknown_keys = sorted(rule_keys - allowed_keys)
        if missing_keys:
            errors.append(f"Fehlende visual_key_rules: {', '.join(missing_keys)}")
        if unknown_keys:
            errors.append(f"Unbekannte visual_key_rules: {', '.join(unknown_keys)}")

        for key, rule in visual_key_rules.items():
            if not isinstance(rule, dict):
                errors.append(f"{key}: Regel muss ein Objekt sein.")
                continue

            missing_fields = sorted(REQUIRED_RULE_FIELDS - set(rule.keys()))
            if missing_fields:
                errors.append(f"{key}: Fehlende Felder: {', '.join(missing_fields)}")
                continue

            if rule.get("image_class") not in ALLOWED_IMAGE_CLASSES:
                errors.append(f"{key}: image_class {rule.get('image_class')!r} ist ungültig.")
            if rule.get("priority") not in PRIORITIES:
                errors.append(f"{key}: priority {rule.get('priority')!r} ist ungültig.")
            if not is_non_empty_str(rule.get("locality_requirement")):
                errors.append(f"{key}: locality_requirement fehlt oder ist leer.")
            if not is_non_empty_str(rule.get("subject_focus")):
                errors.append(f"{key}: subject_focus fehlt oder ist leer.")
            if not is_non_empty_str(rule.get("people_policy")):
                errors.append(f"{key}: people_policy fehlt oder ist leer.")

            for field in ("anchor_profiles", "prompt_must_include", "prompt_avoid", "review_emphasis"):
                if not is_non_empty_list(rule.get(field)):
                    errors.append(f"{key}: {field} fehlt oder ist leer.")
                else:
                    if field == "anchor_profiles":
                        for anchor_id in rule.get(field, []):
                            if anchor_id not in anchors:
                                errors.append(f"{key}: unbekannter Anchor {anchor_id}.")

    print("Event Visual AI Style Guide Audit")
    print("=================================")
    print(f"Datei: {GUIDE_PATH.relative_to(ROOT)}")
    print(f"Pilotanker: {len(anchors)}")
    print(f"Image classes: {len(image_classes)}")
    print(f"Visual-key-Regeln: {len(visual_key_rules)}")

    if errors:
        print("\nFEHLER:")
        for error in errors:
            print(f"- {error}")
        return 1

    print("\nOK: AI Style Guide ist konsistent und deckt alle Visual Keys ab.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/audit-event-visual-ai-style-guide.py ===
