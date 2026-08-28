#!/usr/bin/env python3
from __future__ import annotations

import json
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts" / "augment-sitemap-event-details.py"
NS = "http://www.sitemaps.org/schemas/sitemap/0.9"


def tag(name: str) -> str:
    return f"{{{NS}}}{name}"


with tempfile.TemporaryDirectory() as tmpdir:
    tmp = Path(tmpdir)
    sitemap = tmp / "sitemap.xml"
    manifest = tmp / "event_detail_pages.json"

    sitemap.write_text(
        """<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://bocholt-erleben.de/</loc>
    <lastmod>2026-08-20</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url><loc>https://bocholt-erleben.de/events/existing/</loc></url>
</urlset>
""",
        encoding="utf-8",
    )
    manifest.write_text(
        json.dumps(
            {
                "pages": [
                    {"active": True, "noindex": False, "url": "https://bocholt-erleben.de/events/new/"},
                    {"active": True, "noindex": False, "url": "https://bocholt-erleben.de/events/new/"},
                    {"active": True, "noindex": False, "url": "https://bocholt-erleben.de/events/existing/"},
                    {"active": False, "noindex": False, "url": "https://bocholt-erleben.de/events/inactive/"},
                    {"active": True, "noindex": True, "url": "https://bocholt-erleben.de/events/noindex/"},
                    {"active": True, "noindex": False, "url": ""},
                ]
            }
        ),
        encoding="utf-8",
    )

    result = subprocess.run(
        [sys.executable, str(SCRIPT), str(sitemap), str(manifest)],
        check=True,
        capture_output=True,
        text=True,
    )
    assert "1 aktive Event-Detailseiten" in result.stdout, result.stdout

    root = ET.parse(sitemap).getroot()
    entries: dict[str, list[ET.Element]] = {}
    for url_element in root.findall(tag("url")):
        loc = url_element.find(tag("loc"))
        assert loc is not None and loc.text
        entries.setdefault(loc.text.strip(), []).append(url_element)

    expected = {
        "https://bocholt-erleben.de/",
        "https://bocholt-erleben.de/events/existing/",
        "https://bocholt-erleben.de/events/new/",
    }
    assert set(entries) == expected, entries.keys()
    assert all(len(matches) == 1 for matches in entries.values()), entries

    generated = entries["https://bocholt-erleben.de/events/new/"][0]
    assert [child.tag.rsplit("}", 1)[-1] for child in generated] == ["loc"]

    home = entries["https://bocholt-erleben.de/"][0]
    assert home.find(tag("lastmod")).text == "2026-08-20"
    assert home.find(tag("changefreq")).text == "weekly"
    assert home.find(tag("priority")).text == "1.0"

print("Sitemap event-detail contract: OK")
