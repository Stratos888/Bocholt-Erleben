#!/usr/bin/env python3
# === BEGIN FILE: tools/audit-visual-contract.py | Zweck: prueft Event- und Activity-Visuals gegen den Premium Visual Contract; Umfang: nur Audit/Reporting, keine Daten- oder UI-Aenderungen ===

from __future__ import annotations

import argparse
import json
from collections import Counter
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]

EVENT_POOL_PATH = ROOT / "data" / "event_visual_pool.json"
OFFERS_PATH = ROOT / "data" / "offers.json"

VISUAL_STATUSES = {"ready", "usable", "fallback", "needs_review", "blocked"}
EVENT_BACKLOG_STATUSES = {"planned"}
ALL_ALLOWED_STATUSES = VISUAL_STATUSES | EVENT_BACKLOG_STATUSES

CARD_ASSET_MAX_BYTES = 450 * 1024


def load_json(path: Path) -> Any:
    if not path.exists():
        raise SystemExit(f"ERROR missing file: {path.relative_to(ROOT)}")

    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise SystemExit(f"ERROR invalid JSON in {path.relative_to(ROOT)}: {exc}") from exc


def as_list(value: Any) -> list[Any]:
    return value if isinstance(value, list) else []


def as_dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def local_asset_path(src: str) -> Path | None:
    value = str(src or "").strip()
    if not value.startswith("/"):
        return None
    return ROOT / value.lstrip("/")


def relative(path: Path) -> str:
    try:
        return str(path.relative_to(ROOT))
    except ValueError:
        return str(path)


def audit_event_visual_pool(errors: list[str], warnings: list[str]) -> dict[str, Any]:
    payload = as_dict(load_json(EVENT_POOL_PATH))
    pools = as_dict(payload.get("pools"))

    summary: dict[str, Any] = {
        "pool_count": len(pools),
        "image_count": 0,
        "status_counts": Counter(),
        "ready_count": 0,
        "fallback_count": 0,
        "missing_ready_files": 0,
        "non_webp_ready": 0,
        "oversized_ready": 0,
        "missing_ready_alt": 0,
    }

    if payload.get("owner") != "event_visual_pool_v1":
        warnings.append("event_visual_pool.json owner is not event_visual_pool_v1.")

    if not pools:
        errors.append("event_visual_pool.json has no pools.")
        return summary

    for visual_key, pool in pools.items():
        images = as_list(as_dict(pool).get("images"))

        if not images:
            warnings.append(f"event pool {visual_key!r} has no images.")

        for image in images:
            item = as_dict(image)
            image_id = str(item.get("id") or "").strip()
            src = str(item.get("src") or "").strip()
            status = str(item.get("status") or "").strip()

            summary["image_count"] += 1
            summary["status_counts"][status or "<missing>"] += 1

            if status not in ALL_ALLOWED_STATUSES:
                errors.append(f"event visual {visual_key}/{image_id or '<no-id>'} has invalid status {status!r}.")

            if status in {"ready", "fallback"}:
                if status == "ready":
                    summary["ready_count"] += 1
                if status == "fallback":
                    summary["fallback_count"] += 1

                if not image_id:
                    errors.append(f"event visual {visual_key} has {status} image without id.")

                if not src:
                    errors.append(f"event visual {visual_key}/{image_id or '<no-id>'} is {status} but has no src.")
                    continue

                if not src.endswith(".webp"):
                    summary["non_webp_ready"] += 1
                    warnings.append(f"event visual {visual_key}/{image_id} is {status} but not WebP: {src}")

                if not str(item.get("alt") or "").strip():
                    summary["missing_ready_alt"] += 1
                    warnings.append(f"event visual {visual_key}/{image_id} is {status} but has no alt text.")

                asset_path = local_asset_path(src)
                if asset_path is not None:
                    if not asset_path.exists():
                        summary["missing_ready_files"] += 1
                        errors.append(f"event visual {visual_key}/{image_id} references missing asset: {src}")
                    else:
                        size = asset_path.stat().st_size
                        if size > CARD_ASSET_MAX_BYTES:
                            summary["oversized_ready"] += 1
                            warnings.append(
                                f"event visual {visual_key}/{image_id} is large for card use: "
                                f"{relative(asset_path)} ({round(size / 1024)} KB)"
                            )

    return summary


def audit_activity_visuals(errors: list[str], warnings: list[str]) -> dict[str, Any]:
    payload = load_json(OFFERS_PATH)
    offers = as_list(as_dict(payload).get("offers"))

    summary: dict[str, Any] = {
        "offer_count": len(offers),
        "explicit_image_count": 0,
        "visual_key_count": 0,
        "status_counts": Counter(),
        "missing_status_count": 0,
        "legacy_position_count": 0,
        "local_image_count": 0,
        "local_non_webp_count": 0,
        "missing_local_files": 0,
        "blocked_or_review_with_image": 0,
    }

    if not offers:
        errors.append("offers.json has no offers.")
        return summary

    missing_status_examples: list[str] = []
    local_non_webp_examples: list[str] = []

    for offer in offers:
        item = as_dict(offer)
        offer_id = str(item.get("id") or "<no-id>").strip()
        image = str(item.get("image") or "").strip()
        visual_key = str(item.get("visual_key") or item.get("image_visual_key") or "").strip()
        status = str(
            item.get("image_quality")
            or item.get("image_status")
            or item.get("visual_status")
            or ""
        ).strip()

        if image:
            summary["explicit_image_count"] += 1

        if visual_key:
            summary["visual_key_count"] += 1

        if status:
            summary["status_counts"][status] += 1
            if status not in VISUAL_STATUSES:
                errors.append(f"activity {offer_id} has invalid visual status {status!r}.")
        else:
            summary["missing_status_count"] += 1
            if len(missing_status_examples) < 12:
                missing_status_examples.append(offer_id)

        if item.get("image_position_x") or item.get("image_position_y"):
            summary["legacy_position_count"] += 1

        if status in {"needs_review", "blocked"} and image:
            summary["blocked_or_review_with_image"] += 1

        asset_path = local_asset_path(image)
        if asset_path is not None:
            summary["local_image_count"] += 1

            if not asset_path.exists():
                summary["missing_local_files"] += 1
                errors.append(f"activity {offer_id} references missing local image: {image}")
            else:
                if asset_path.suffix.lower() != ".webp":
                    summary["local_non_webp_count"] += 1
                    if len(local_non_webp_examples) < 12:
                        local_non_webp_examples.append(f"{offer_id}: {relative(asset_path)}")

    if missing_status_examples:
        warnings.append(
            "activity visuals have no image_quality/image_status/visual_status yet; "
            "first examples: " + ", ".join(missing_status_examples)
        )

    if local_non_webp_examples:
        warnings.append(
            "local activity images are not WebP card assets yet; "
            "examples: " + ", ".join(local_non_webp_examples)
        )

    return summary


def print_counter(label: str, counter: Counter[str]) -> None:
    if not counter:
        print(f"  {label}: none")
        return

    print(f"  {label}:")
    for key, count in sorted(counter.items(), key=lambda item: (str(item[0]), item[1])):
        print(f"    {key}: {count}")


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Audit event/activity visuals against the Bocholt erleben Premium Visual Contract."
    )
    parser.add_argument(
        "--strict",
        action="store_true",
        help="Fail on warnings as well as errors. Default is baseline mode: errors fail, warnings report debt.",
    )
    args = parser.parse_args()

    errors: list[str] = []
    warnings: list[str] = []

    event_summary = audit_event_visual_pool(errors, warnings)
    activity_summary = audit_activity_visuals(errors, warnings)

    print("=== Premium Visual Contract Audit ===")
    print()
    print("Event visuals")
    print(f"  pools: {event_summary['pool_count']}")
    print(f"  images: {event_summary['image_count']}")
    print(f"  ready: {event_summary['ready_count']}")
    print(f"  fallback: {event_summary['fallback_count']}")
    print(f"  missing ready/fallback files: {event_summary['missing_ready_files']}")
    print(f"  ready/fallback non-WebP: {event_summary['non_webp_ready']}")
    print(f"  ready/fallback oversized: {event_summary['oversized_ready']}")
    print(f"  ready/fallback missing alt: {event_summary['missing_ready_alt']}")
    print_counter("status counts", event_summary["status_counts"])

    print()
    print("Activity visuals")
    print(f"  offers: {activity_summary['offer_count']}")
    print(f"  explicit images: {activity_summary['explicit_image_count']}")
    print(f"  visual keys: {activity_summary['visual_key_count']}")
    print(f"  missing visual status: {activity_summary['missing_status_count']}")
    print(f"  legacy crop position fields: {activity_summary['legacy_position_count']}")
    print(f"  local images: {activity_summary['local_image_count']}")
    print(f"  local non-WebP images: {activity_summary['local_non_webp_count']}")
    print(f"  missing local files: {activity_summary['missing_local_files']}")
    print(f"  needs_review/blocked with image: {activity_summary['blocked_or_review_with_image']}")
    print_counter("status counts", activity_summary["status_counts"])

    print()
    if warnings:
        print("Warnings / known visual debt")
        for warning in warnings:
            print(f"  WARN: {warning}")
    else:
        print("Warnings / known visual debt: none")

    print()
    if errors:
        print("Errors")
        for error in errors:
            print(f"  ERROR: {error}")
    else:
        print("Errors: none")

    if errors:
        return 1

    if args.strict and warnings:
        return 2

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

# === END FILE: tools/audit-visual-contract.py ===
