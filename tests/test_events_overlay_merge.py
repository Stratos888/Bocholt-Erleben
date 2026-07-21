#!/usr/bin/env python3
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from merge_events_overlay import merge_event_rows  # noqa: E402


HEADER = [
    "id", "title", "date", "endDate", "time", "city", "location",
    "kategorie", "url", "description", "visual_key",
]


def row(event_id: str, title: str, url: str, time: str = "18:00") -> list[str]:
    return [
        event_id, title, "2026-08-15", "", time, "Bocholt", "Marktplatz",
        "Kultur & Kunst", url, "Sachliche Beschreibung.", "art_exhibition_gallery",
    ]


def expect_error(label: str, callback, marker: str) -> None:
    try:
        callback()
    except ValueError as error:
        assert marker in str(error), f"{label}: unerwartete Meldung: {error}"
    else:
        raise AssertionError(f"{label}: Fehler wurde nicht ausgelöst")


def main() -> None:
    base = [row("base-1", "Basis", "https://example.org/base")]

    header, merged, stats = merge_event_rows(HEADER, base)
    assert header == HEADER
    assert merged == base
    assert stats == {"base": 1, "overlay": 0, "replaced": 0, "appended": 0}

    # Reale Eventserien und Programmseiten verwenden absichtlich dieselbe
    # Quellen-URL für mehrere Termine. Eindeutige IDs bleiben kanonisch.
    shared_url_base = [
        row("series-1", "Serientermin 1", "https://example.org/series"),
        row("series-2", "Serientermin 2", "https://example.org/series"),
    ]
    _, merged, stats = merge_event_rows(HEADER, shared_url_base, HEADER, [])
    assert merged == shared_url_base
    assert stats == {"base": 2, "overlay": 0, "replaced": 0, "appended": 0}

    shared_url_update = row("series-2", "Serientermin 2 aktualisiert", "https://example.org/series", "20:00")
    _, merged, stats = merge_event_rows(HEADER, shared_url_base, HEADER, [shared_url_update])
    assert merged == [shared_url_base[0], shared_url_update]
    assert stats == {"base": 2, "overlay": 1, "replaced": 1, "appended": 0}

    expect_error(
        "mehrdeutige Basis-URL ohne passende ID",
        lambda: merge_event_rows(
            HEADER,
            shared_url_base,
            HEADER,
            [row("series-new", "Nicht eindeutig", "https://example.org/series")],
        ),
        "URL ist im Basisbestand mehrdeutig",
    )

    expect_error(
        "doppelte Basis-ID",
        lambda: merge_event_rows(
            HEADER,
            [
                row("duplicate-id", "A", "https://example.org/a"),
                row("duplicate-id", "B", "https://example.org/b"),
            ],
            HEADER,
            [],
        ),
        "doppelte ID im Basisbestand",
    )

    appended = row("staging-1", "Staging", "https://example.org/staging", "11:00–18:00 Uhr")
    _, merged, stats = merge_event_rows(HEADER, base, HEADER, [appended])
    assert merged == [base[0], appended]
    assert stats == {"base": 1, "overlay": 1, "replaced": 0, "appended": 1}

    override = row("base-1", "Basis aktualisiert", "https://example.org/base", "20:00")
    _, merged, stats = merge_event_rows(HEADER, base, HEADER, [override])
    assert merged == [override]
    assert stats == {"base": 1, "overlay": 1, "replaced": 1, "appended": 0}

    url_override = row("replacement-id", "URL-Override", "https://example.org/base", "19:00")
    _, merged, stats = merge_event_rows(HEADER, base, HEADER, [url_override])
    assert merged == [url_override]
    assert stats["replaced"] == 1

    expect_error(
        "abweichender Header",
        lambda: merge_event_rows(HEADER, base, HEADER[:-1], []),
        "Header muss exakt",
    )
    expect_error(
        "doppelte Overlay-ID",
        lambda: merge_event_rows(HEADER, base, HEADER, [appended, appended]),
        "doppelte Overlay-ID",
    )
    expect_error(
        "doppelte Overlay-URL",
        lambda: merge_event_rows(
            HEADER,
            base,
            HEADER,
            [
                row("staging-1", "A", "https://example.org/same"),
                row("staging-2", "B", "https://example.org/same"),
            ],
        ),
        "doppelte Overlay-URL",
    )
    expect_error(
        "widersprüchliche ID-/URL-Auflösung",
        lambda: merge_event_rows(
            HEADER,
            [
                row("base-1", "A", "https://example.org/a"),
                row("base-2", "B", "https://example.org/b"),
            ],
            HEADER,
            [row("base-1", "Konflikt", "https://example.org/b")],
        ),
        "unterschiedliche Basiszeilen",
    )

    print("Events overlay merge contract: OK")


if __name__ == "__main__":
    main()
