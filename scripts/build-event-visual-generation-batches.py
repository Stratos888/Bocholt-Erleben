# === BEGIN FILE: scripts/build-event-visual-generation-batches.py | Zweck: erzeugt Generierungsbatches fuer Phase-1-Event-Visuals aus Phase-1-Plan und AI Style Guide; Umfang: keine UI, keine Bilddateien, nur maschinenlesbare Prompt-Batches ===
from __future__ import annotations

import csv
import json
import sys
from pathlib import Path
from typing import Any, Dict, List

ROOT = Path(__file__).resolve().parents[1]
PHASE1_PATH = ROOT / "data" / "event_visual_phase1_plan.tsv"
STYLE_GUIDE_PATH = ROOT / "data" / "event_visual_ai_style_guide.json"
OUT_PATH = ROOT / "data" / "event_visual_generation_batches_phase1.json"

BATCH_SPECS = [
    {
        "batch_id": "phase1_batch_01_local_detail",
        "theme": "Local-sensitive urban and outdoor detail visuals",
        "rationale": "First batch for locality-sensitive prompts with strong no-fake-place constraints.",
        "visual_keys": ["city_festival", "city_walk", "default_city", "outdoor_nature"],
    },
    {
        "batch_id": "phase1_batch_02_symbolic_editorial",
        "theme": "Symbolic editorial making, family and culture visuals",
        "rationale": "Second batch for symbolic editorial prompts with strong materiality and safety rules.",
        "visual_keys": ["creative_workshop", "culture_exhibition", "kids_family", "theater_show"],
    },
    {
        "batch_id": "phase1_batch_03_contextual_activity",
        "theme": "Contextual market, music and sport visuals",
        "rationale": "Third batch for contextual activity visuals with foreground-detail focus.",
        "visual_keys": ["market_food", "music_stage", "sport_active"],
    },
]


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


def read_tsv(path: Path) -> List[Dict[str, str]]:
    if not path.exists():
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} fehlt.")
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle, delimiter="\t"))


def as_dict(value: Any) -> Dict[str, Any]:
    return value if isinstance(value, dict) else {}


def as_list(value: Any) -> List[Any]:
    return value if isinstance(value, list) else []


def clean_text(value: Any) -> str:
    return str(value or "").strip()


def uniq(values: List[str]) -> List[str]:
    seen = set()
    out: List[str] = []
    for value in values:
        item = clean_text(value)
        if item and item not in seen:
            seen.add(item)
            out.append(item)
    return out


def join_items(values: List[Any], sep: str = "; ") -> str:
    return sep.join(clean_text(value) for value in values if clean_text(value))


def summarize_anchor_profiles(anchor_ids: List[str], anchors: Dict[str, Any]) -> Dict[str, List[str]]:
    strengths: List[str] = []
    avoid_transferring: List[str] = []

    for anchor_id in anchor_ids:
        anchor = as_dict(anchors.get(anchor_id))
        strengths.extend(clean_text(item) for item in as_list(anchor.get("strengths")))
        avoid_transferring.extend(clean_text(item) for item in as_list(anchor.get("avoid_transferring")))

    return {
        "strengths": uniq(strengths),
        "avoid_transferring": uniq(avoid_transferring),
    }


def build_prompt(
    row: Dict[str, str],
    style_guide: Dict[str, Any],
    visual_key_rule: Dict[str, Any],
) -> str:
    anchors = as_dict(style_guide.get("approved_pilot_anchors"))
    global_style = as_dict(style_guide.get("global_style"))
    image_classes = as_dict(style_guide.get("image_classes"))

    image_class_id = clean_text(visual_key_rule.get("image_class"))
    image_class = as_dict(image_classes.get(image_class_id))
    anchor_ids = [clean_text(item) for item in as_list(visual_key_rule.get("anchor_profiles")) if clean_text(item)]
    anchor_summary = summarize_anchor_profiles(anchor_ids, anchors)

    prompt_parts = [
        'Create one premium AI-generated symbolic event visual for the "Bocholt erleben" PWA.',
        'This is a landscape event-card image and must be optimized for a 4:3 crop-safe composition.',
        f'Visual key: {clean_text(row.get("visual_key"))}. Planned asset path: {clean_text(row.get("planned_src"))}.',
        f'Image class: {image_class_id}. {clean_text(image_class.get("description"))}',
        f'Subject focus: {clean_text(visual_key_rule.get("subject_focus"))}.',
        f'Specific slot brief: {clean_text(row.get("brief"))}.',
        f'Locality requirement: {clean_text(visual_key_rule.get("locality_requirement"))}.',
        f'Anchor style profiles to follow: {join_items(anchor_ids, ", ")}.',
        f'Emphasize these anchor strengths: {join_items(anchor_summary.get("strengths", []))}.',
        f'Do not transfer these anchor weaknesses: {join_items(anchor_summary.get("avoid_transferring", []))}.',
        f'Desired visual language: {join_items(as_list(global_style.get("desired_visual_language")))}.',
        f'Lighting rules: {join_items(as_list(global_style.get("lighting_rules")))}.',
        f'Composition rules: {join_items(as_list(global_style.get("composition_rules")))}.',
        f'Realism rules: {join_items(as_list(global_style.get("realism_rules")))}.',
        f'Class-specific prompt bias: {join_items(as_list(image_class.get("prompt_bias")))}.',
        f'Must include: {join_items(as_list(visual_key_rule.get("prompt_must_include")))}.',
        f'People policy: {clean_text(visual_key_rule.get("people_policy"))}.',
        f'Prompt-specific avoid rules: {join_items(as_list(visual_key_rule.get("prompt_avoid")))}.',
        f'Class-specific avoid bias: {join_items(as_list(image_class.get("avoid_bias")))}.',
        f'Global negative rules: {join_items(as_list(style_guide.get("global_negative_rules")))}.',
        f'Legal safety rules: {join_items(as_list(global_style.get("legal_safety_rules")))}.',
        'Do not include any readable text, logos, posters, signs or copyrighted artwork.',
        'Do not create a false concrete Bocholt place claim.',
        'The image must feel premium, believable, low in AI-artifice, and suitable for a modern discovery PWA event card.',
    ]

    return " ".join(part for part in prompt_parts if clean_text(part))


def main() -> int:
    phase1_rows = read_tsv(PHASE1_PATH)
    style_guide = read_json(STYLE_GUIDE_PATH)

    phase1_rows = [row for row in phase1_rows if clean_text(row.get("visual_key"))]
    if len(phase1_rows) != 22:
        raise SystemExit(f"FEHLER: Erwartet 22 Phase-1-Zeilen, gefunden: {len(phase1_rows)}")

    visual_key_rules = as_dict(style_guide.get("visual_key_rules"))
    generation_contract = as_dict(style_guide.get("generation_contract"))
    quality_gate_policy = as_dict(style_guide.get("quality_gate_policy"))
    preferred_aspect_ratio = clean_text(generation_contract.get("preferred_generation_aspect_ratio")) or "4:3"

    batches: List[Dict[str, Any]] = []
    assigned_request_ids = set()
    assigned_visual_keys = set()
    total_requests = 0

    for batch_index, spec in enumerate(BATCH_SPECS, start=1):
        spec_visual_keys = list(spec["visual_keys"])
        batch_rows = [row for row in phase1_rows if clean_text(row.get("visual_key")) in spec_visual_keys]
        batch_rows.sort(key=lambda row: (spec_visual_keys.index(clean_text(row.get("visual_key"))), clean_text(row.get("slot"))))

        requests: List[Dict[str, Any]] = []
        for row in batch_rows:
            visual_key = clean_text(row.get("visual_key"))
            image_id = clean_text(row.get("image_id"))
            slot = clean_text(row.get("slot"))
            visual_key_rule = as_dict(visual_key_rules.get(visual_key))

            if not visual_key_rule:
                raise SystemExit(f"FEHLER: Keine visual_key-Regel im Style Guide für {visual_key!r}")

            request_id = f"phase1_{visual_key}_{slot}".replace("-", "_")
            if request_id in assigned_request_ids:
                raise SystemExit(f"FEHLER: Doppelter request_id {request_id}")
            assigned_request_ids.add(request_id)
            assigned_visual_keys.add(visual_key)

            prompt = build_prompt(row, style_guide, visual_key_rule)
            if len(prompt) < 200:
                raise SystemExit(f"FEHLER: Prompt für {request_id} ist zu kurz.")

            requests.append({
                "request_id": request_id,
                "visual_key": visual_key,
                "slot": slot,
                "image_id": image_id,
                "planned_src": clean_text(row.get("planned_src")),
                "aspect_ratio": preferred_aspect_ratio,
                "prompt": prompt,
                "metadata": {
                    "phase": 1,
                    "priority": clean_text(row.get("priority")),
                    "image_class": clean_text(visual_key_rule.get("image_class")),
                    "anchor_profiles": as_list(visual_key_rule.get("anchor_profiles")),
                    "recommended_source_type": clean_text(row.get("recommended_source_type")),
                    "must_be_local": clean_text(row.get("must_be_local")),
                    "can_be_symbolic": clean_text(row.get("can_be_symbolic")),
                    "capture_priority": clean_text(row.get("capture_priority")),
                    "rights_mode": clean_text(row.get("rights_mode")),
                    "first_action": clean_text(row.get("first_action")),
                    "notes": clean_text(row.get("notes")),
                    "review_gates": {
                        "hard_fail": as_list(quality_gate_policy.get("hard_fail_gates")),
                        "soft_review": as_list(quality_gate_policy.get("soft_review_gates")),
                        "review_emphasis": as_list(visual_key_rule.get("review_emphasis")),
                    },
                },
            })

        if len(requests) > 10:
            raise SystemExit(f"FEHLER: Batch {spec['batch_id']} hat {len(requests)} Requests und überschreitet das Limit 10.")

        total_requests += len(requests)
        batches.append({
            "batch_id": spec["batch_id"],
            "batch_order": batch_index,
            "theme": spec["theme"],
            "rationale": spec["rationale"],
            "visual_keys": spec_visual_keys,
            "requests": requests,
        })

    phase1_visual_keys = {clean_text(row.get("visual_key")) for row in phase1_rows}
    if assigned_visual_keys != phase1_visual_keys:
        missing = sorted(phase1_visual_keys - assigned_visual_keys)
        unknown = sorted(assigned_visual_keys - phase1_visual_keys)
        raise SystemExit(f"FEHLER: Batch-Abdeckung unvollständig. missing={missing}, unknown={unknown}")

    out = {
        "schema_version": 1,
        "owner": "event_visual_generation_batches_phase1_v1",
        "source_contract": {
            "phase1_plan": PHASE1_PATH.relative_to(ROOT).as_posix(),
            "ai_style_guide": STYLE_GUIDE_PATH.relative_to(ROOT).as_posix(),
        },
        "generation_defaults": {
            "aspect_ratio": preferred_aspect_ratio,
            "source_type": clean_text(generation_contract.get("source_type")),
            "is_symbolic": generation_contract.get("is_symbolic"),
            "is_documentary": generation_contract.get("is_documentary"),
            "final_asset_format": clean_text(generation_contract.get("final_asset_format")),
            "max_requests_per_batch": 10,
            "batch_count": len(batches),
            "total_requests": total_requests,
            "review_required": True,
        },
        "batches": batches,
    }

    OUT_PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print("Event Visual Generation Batches gebaut")
    print("=====================================")
    print(f"Output: {OUT_PATH.relative_to(ROOT)}")
    print(f"Batches: {len(batches)}")
    print(f"Requests: {total_requests}")
    for batch in batches:
        print(f"- {batch['batch_id']}: {len(batch['requests'])}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/build-event-visual-generation-batches.py ===
