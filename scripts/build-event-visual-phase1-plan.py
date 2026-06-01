# === BEGIN FILE: scripts/build-event-visual-phase1-plan.py | Zweck: baut eine kompakte Beschaffungsliste fuer Phase-1-Event-Visuals; Umfang: keine UI, keine Websuche, keine OpenAI-Nutzung ===
from __future__ import annotations

import csv
from pathlib import Path
from typing import Dict, List

ROOT = Path(__file__).resolve().parents[1]
BACKLOG_PATH = ROOT / "data" / "event_visual_asset_backlog.tsv"
OUT_PATH = ROOT / "data" / "event_visual_phase1_plan.tsv"

FIELDNAMES = [
    "visual_key",
    "slot",
    "image_id",
    "planned_src",
    "priority",
    "brief",
    "recommended_source_type",
    "must_be_local",
    "can_be_symbolic",
    "capture_priority",
    "rights_mode",
    "first_action",
    "notes",
]

STRATEGIES: Dict[str, Dict[str, str]] = {
    "city_festival": {
        "recommended_source_type": "own_or_cleared_local_photo",
        "must_be_local": "preferred",
        "can_be_symbolic": "limited",
        "capture_priority": "very_high",
        "rights_mode": "own_photo_or_explicitly_cleared",
        "first_action": "Eigene Bocholt-/Innenstadt-Fotos oder gezielt lokale, rechteklare Bilder beschaffen.",
        "notes": "Für Premium-Wirkung möglichst lokal. Generische Stadtfestbilder nur als Notlösung.",
    },
    "city_walk": {
        "recommended_source_type": "own_local_photo_or_commons_local_landmark",
        "must_be_local": "yes",
        "can_be_symbolic": "limited",
        "capture_priority": "very_high",
        "rights_mode": "own_photo_or_commons_with_license_attribution",
        "first_action": "Bocholt-/Regionsmotive priorisieren: Straße, Turm, Kirche, Promenade, Route.",
        "notes": "Dieser Pool entscheidet stark über lokale Wiedererkennbarkeit.",
    },
    "creative_workshop": {
        "recommended_source_type": "self_created_symbolic_detail",
        "must_be_local": "no",
        "can_be_symbolic": "yes",
        "capture_priority": "high",
        "rights_mode": "own_photo_or_self_created_material_scene",
        "first_action": "Material-/Hände-/Werkzeug-Szenen selbst bauen oder rechteklare Detailbilder nutzen.",
        "notes": "Keine erkennbaren Kinder, keine geschützten Figuren wie Pokémon im Bildfokus.",
    },
    "kids_family": {
        "recommended_source_type": "self_created_family_safe_detail",
        "must_be_local": "no",
        "can_be_symbolic": "yes",
        "capture_priority": "high",
        "rights_mode": "own_photo_no_recognizable_children",
        "first_action": "Freundliche Detailmotive mit Spiel-/Mitmachmaterial erstellen.",
        "notes": "Keine erkennbaren Kindergesichter. Familienklischee-Stockfotos vermeiden.",
    },
    "market_food": {
        "recommended_source_type": "own_local_or_high_quality_symbolic_food_market",
        "must_be_local": "preferred",
        "can_be_symbolic": "yes",
        "capture_priority": "high",
        "rights_mode": "own_photo_or_cleared_stock_without_brands",
        "first_action": "Markt-/Food-/Stadtfest-Stimmung mit warmem Licht beschaffen.",
        "notes": "Keine Marken, keine Alkoholzentrierung, keine beliebigen Tellerbilder.",
    },
    "music_stage": {
        "recommended_source_type": "cleared_symbolic_stage_or_own_detail",
        "must_be_local": "no",
        "can_be_symbolic": "yes",
        "capture_priority": "medium",
        "rights_mode": "own_photo_or_cleared_symbolic_stage_image",
        "first_action": "Bühnenlicht, Mikrofon, Instrument oder Tanzdetail ohne erkennbare Performer nutzen.",
        "notes": "Keine erkennbaren Künstler, keine Logos, keine Poster.",
    },
    "outdoor_nature": {
        "recommended_source_type": "own_local_nature_or_commons_region",
        "must_be_local": "preferred",
        "can_be_symbolic": "yes",
        "capture_priority": "high",
        "rights_mode": "own_photo_or_commons_with_license_attribution",
        "first_action": "Lokale Natur-/Park-/Wald-/Wassermotive priorisieren.",
        "notes": "Nicht nach Alpen/Strand/Reise aussehen lassen. Ruhig, grün, regional.",
    },
    "culture_exhibition": {
        "recommended_source_type": "own_culture_detail_or_commons_architecture",
        "must_be_local": "preferred",
        "can_be_symbolic": "yes",
        "capture_priority": "medium",
        "rights_mode": "own_photo_or_commons_with_license_attribution",
        "first_action": "Kultur-, Ausstellungs-, Architektur- oder Textil-/Materialdetails beschaffen.",
        "notes": "Keine geschützten Kunstwerke prominent abbilden.",
    },
    "theater_show": {
        "recommended_source_type": "cleared_symbolic_theater_stage",
        "must_be_local": "no",
        "can_be_symbolic": "yes",
        "capture_priority": "medium",
        "rights_mode": "own_photo_or_cleared_symbolic_stage_image",
        "first_action": "Vorhang, leere Bühne, Spotlicht oder Lesungsmotiv ohne Performer nutzen.",
        "notes": "Keine Bühnenbilder oder Personen ohne Rechte.",
    },
    "sport_active": {
        "recommended_source_type": "own_route_or_symbolic_sport_detail",
        "must_be_local": "preferred",
        "can_be_symbolic": "yes",
        "capture_priority": "medium",
        "rights_mode": "own_photo_or_cleared_symbolic_sport_image",
        "first_action": "Radweg, Schuhe, Strecke, Parkweg oder Sportdetail beschaffen.",
        "notes": "Keine erkennbaren Sportler oder Markenkleidung im Fokus.",
    },
    "default_city": {
        "recommended_source_type": "own_local_brand_visual",
        "must_be_local": "yes",
        "can_be_symbolic": "limited",
        "capture_priority": "very_high",
        "rights_mode": "own_photo_or_self_created_brand_visual",
        "first_action": "Hochwertige neutrale Bocholt-/Brand-Visuals erstellen oder fotografieren.",
        "notes": "Default darf nicht billig wirken, weil er bei unklaren Events sichtbar wird.",
    },
}


def read_backlog() -> List[Dict[str, str]]:
    if not BACKLOG_PATH.exists():
        raise SystemExit(f"FEHLER: {BACKLOG_PATH.relative_to(ROOT)} fehlt.")

    with BACKLOG_PATH.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle, delimiter="\t"))


def main() -> int:
    backlog_rows = read_backlog()
    phase1_rows = [row for row in backlog_rows if row.get("phase") == "1"]

    if len(phase1_rows) != 22:
        raise SystemExit(f"FEHLER: Erwartet 22 Phase-1-Zeilen, gefunden: {len(phase1_rows)}")

    output_rows: List[Dict[str, str]] = []

    for row in phase1_rows:
        key = row.get("visual_key", "").strip()
        strategy = STRATEGIES.get(key)

        if strategy is None:
            raise SystemExit(f"FEHLER: Keine Strategie fuer visual_key {key!r}")

        output_rows.append({
            "visual_key": key,
            "slot": row.get("slot", "").strip(),
            "image_id": row.get("image_id", "").strip(),
            "planned_src": row.get("planned_src", "").strip(),
            "priority": row.get("priority", "").strip(),
            "brief": row.get("brief", "").strip(),
            "recommended_source_type": strategy["recommended_source_type"],
            "must_be_local": strategy["must_be_local"],
            "can_be_symbolic": strategy["can_be_symbolic"],
            "capture_priority": strategy["capture_priority"],
            "rights_mode": strategy["rights_mode"],
            "first_action": strategy["first_action"],
            "notes": strategy["notes"],
        })

    OUT_PATH.parent.mkdir(parents=True, exist_ok=True)

    with OUT_PATH.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDNAMES, delimiter="\t", lineterminator="\n")
        writer.writeheader()
        writer.writerows(output_rows)

    print("Event Visual Phase-1 Plan gebaut")
    print("================================")
    print(f"Output: {OUT_PATH.relative_to(ROOT)}")
    print(f"Zeilen: {len(output_rows)}")

    counts: Dict[str, int] = {}
    for row in output_rows:
        counts[row["visual_key"]] = counts.get(row["visual_key"], 0) + 1

    for key in sorted(counts):
        print(f"- {key}: {counts[key]}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/build-event-visual-phase1-plan.py ===
