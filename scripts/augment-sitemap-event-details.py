#!/usr/bin/env python3
"""Append active generated event detail pages to a deploy sitemap."""

from __future__ import annotations

import json
import sys
import xml.etree.ElementTree as ET
from datetime import date
from pathlib import Path

NS = "http://www.sitemaps.org/schemas/sitemap/0.9"
ET.register_namespace("", NS)


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: augment-sitemap-event-details.py <sitemap.xml> <event_detail_pages.json>", file=sys.stderr)
        return 2

    sitemap_path = Path(sys.argv[1])
    manifest_path = Path(sys.argv[2])

    if not sitemap_path.exists():
        raise SystemExit(f"Missing sitemap: {sitemap_path}")
    if not manifest_path.exists():
        raise SystemExit(f"Missing event detail manifest: {manifest_path}")

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    pages = [p for p in manifest.get("pages", []) if p.get("active") and not p.get("noindex") and p.get("url")]

    tree = ET.parse(sitemap_path)
    root = tree.getroot()
    existing = {loc.text.strip() for loc in root.findall(f"{{{NS}}}url/{{{NS}}}loc") if loc.text}
    today = date.today().isoformat()
    added = 0

    for page in pages:
        url = str(page.get("url") or "").strip()
        if not url or url in existing:
            continue
        url_el = ET.SubElement(root, f"{{{NS}}}url")
        loc_el = ET.SubElement(url_el, f"{{{NS}}}loc")
        loc_el.text = url
        lastmod_el = ET.SubElement(url_el, f"{{{NS}}}lastmod")
        lastmod_el.text = today
        changefreq_el = ET.SubElement(url_el, f"{{{NS}}}changefreq")
        changefreq_el.text = "daily"
        priority_el = ET.SubElement(url_el, f"{{{NS}}}priority")
        priority_el.text = "0.70"
        existing.add(url)
        added += 1

    tree.write(sitemap_path, encoding="utf-8", xml_declaration=True)
    print(f"✅ Sitemap erweitert: {added} aktive Event-Detailseiten ergänzt.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
