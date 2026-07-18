#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OFFERS_JS = ROOT / "js" / "offers.js"
TODAY_HOME_JS = ROOT / "js" / "today-home.js"
OFFERS_JSON = ROOT / "data" / "offers.json"
TARGET_ID = "suderwicker-maerchenspielplatz"


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected anchor exactly once, found {count}")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")


def patch_offers_js() -> None:
    old = '''  function resolveImageData(offer) {\n    const poolImage = resolvePoolImageData(offer);\n'''
    new = '''  const SUPPRESSED_ACTIVITY_IMAGE_IDS = new Set([\n    "suderwicker-maerchenspielplatz"\n  ]);\n\n  function resolveImageData(offer) {\n    if (SUPPRESSED_ACTIVITY_IMAGE_IDS.has(normalizeLookupKey(offer?.id))) {\n      return {\n        url: "",\n        positionX: "50%",\n        positionY: "50%",\n        fit: "cover",\n        sourcePage: "",\n        author: "",\n        license: "",\n        credit: "",\n        sourceType: "",\n        isSymbolic: false,\n        isDocumentary: false,\n        isAiGenerated: false,\n        status: "suppressed",\n        visualKey: "",\n        alt: "",\n        note: ""\n      };\n    }\n\n    const poolImage = resolvePoolImageData(offer);\n'''
    replace_once(OFFERS_JS, old, new)


def patch_today_home_js() -> None:
    old = '''  function resolveItemVisual(item, usedImages) {\n    if (item?.type === "activity") {\n'''
    new = '''  const SUPPRESSED_TODAY_ACTIVITY_IMAGE_IDS = new Set([\n    "suderwicker-maerchenspielplatz"\n  ]);\n\n  function resolveItemVisual(item, usedImages) {\n    const activityId = asString(item?.id || item?.raw?.id);\n    if (item?.type === "activity" && SUPPRESSED_TODAY_ACTIVITY_IMAGE_IDS.has(activityId)) {\n      return null;\n    }\n\n    if (item?.type === "activity") {\n'''
    replace_once(TODAY_HOME_JS, old, new)


def clean_offer_source() -> None:
    payload = json.loads(OFFERS_JSON.read_text(encoding="utf-8"))
    offers = payload.get("offers")
    if not isinstance(offers, list):
        raise SystemExit("data/offers.json: offers array missing")

    matches = [offer for offer in offers if isinstance(offer, dict) and offer.get("id") == TARGET_ID]
    if len(matches) != 1:
        raise SystemExit(f"data/offers.json: expected one {TARGET_ID!r}, found {len(matches)}")

    offer = matches[0]
    for key in (
        "image",
        "image_quality",
        "image_position_x",
        "image_position_y",
        "image_fit",
        "image_source_page",
        "image_author",
        "image_license",
        "image_credit",
        "image_is_symbolic",
        "image_note",
        "image_source_type",
        "image_is_ai_generated",
    ):
        offer.pop(key, None)

    offer["image_suppressed"] = True
    offer["image_suppressed_reason"] = "Kein fachlich passendes und freigegebenes Bild vorhanden."

    OFFERS_JSON.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def main() -> None:
    patch_offers_js()
    patch_today_home_js()
    clean_offer_source()
    print("Suderwicker Märchenspielplatz image suppressed across activity cards, detail panel and Today Home.")


if __name__ == "__main__":
    main()
