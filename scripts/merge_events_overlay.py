#!/usr/bin/env python3
"""Merge an isolated staging Events overlay onto the read-only Events base."""

from __future__ import annotations

import argparse
import csv
from pathlib import Path
from typing import Iterable, List, Sequence, Tuple


REQUIRED_COLUMNS = ("id", "title", "date", "city", "location", "kategorie")


def _text(value: object) -> str:
    return str(value or "").strip()


def _key(value: object) -> str:
    return " ".join(_text(value).lower().split())


def _normalize_row(row: Sequence[object], width: int) -> List[str]:
    values = [_text(value) for value in row[:width]]
    return values + [""] * (width - len(values))


def _nonempty_rows(rows: Iterable[Sequence[object]], width: int) -> List[List[str]]:
    result: List[List[str]] = []
    for row in rows:
        normalized = _normalize_row(row, width)
        if any(normalized):
            result.append(normalized)
    return result


def _validate_header(header: Sequence[object], label: str) -> List[str]:
    normalized = [_text(value) for value in header]
    if not normalized:
        raise ValueError(f"{label}: Header fehlt.")
    if len(set(normalized)) != len(normalized):
        raise ValueError(f"{label}: Header enthält doppelte Spalten.")
    missing = [column for column in REQUIRED_COLUMNS if column not in normalized]
    if missing:
        raise ValueError(f"{label}: Pflichtspalten fehlen: {missing}.")
    return normalized


def merge_event_rows(
    base_header: Sequence[object],
    base_rows: Iterable[Sequence[object]],
    overlay_header: Sequence[object] | None = None,
    overlay_rows: Iterable[Sequence[object]] | None = None,
) -> Tuple[List[str], List[List[str]], dict]:
    """Return header, merged rows and deterministic merge statistics."""

    header = _validate_header(base_header, "Events")
    width = len(header)
    merged = _nonempty_rows(base_rows, width)

    if overlay_header is None:
        return header, merged, {"base": len(merged), "overlay": 0, "replaced": 0, "appended": 0}

    overlay = _validate_header(overlay_header, "Events_Staging")
    if overlay != header:
        raise ValueError("Events_Staging: Header muss exakt dem Events-Header entsprechen.")
    overlay_values = _nonempty_rows(overlay_rows or [], width)

    index = {name: position for position, name in enumerate(header)}
    id_pos = index["id"]
    url_pos = index.get("url")

    base_by_id: dict[str, int] = {}
    base_by_url: dict[str, int] = {}
    for position, row in enumerate(merged):
        event_id = _key(row[id_pos])
        event_url = _key(row[url_pos]) if url_pos is not None else ""
        if event_id:
            if event_id in base_by_id:
                raise ValueError(f"Events: doppelte ID im Basisbestand: {row[id_pos]}")
            base_by_id[event_id] = position
        if event_url:
            if event_url in base_by_url and base_by_url[event_url] != position:
                raise ValueError(f"Events: doppelte URL im Basisbestand: {row[url_pos]}")
            base_by_url[event_url] = position

    seen_overlay_ids: set[str] = set()
    seen_overlay_urls: set[str] = set()
    replaced = 0
    appended = 0

    for row in overlay_values:
        event_id = _key(row[id_pos])
        event_url = _key(row[url_pos]) if url_pos is not None else ""
        if not event_id:
            raise ValueError("Events_Staging: Overlay-Zeile ohne ID.")
        if event_id in seen_overlay_ids:
            raise ValueError(f"Events_Staging: doppelte Overlay-ID: {row[id_pos]}")
        seen_overlay_ids.add(event_id)
        if event_url:
            if event_url in seen_overlay_urls:
                raise ValueError(f"Events_Staging: doppelte Overlay-URL: {row[url_pos]}")
            seen_overlay_urls.add(event_url)

        id_match = base_by_id.get(event_id)
        url_match = base_by_url.get(event_url) if event_url else None
        if id_match is not None and url_match is not None and id_match != url_match:
            raise ValueError(
                f"Events_Staging: ID und URL verweisen auf unterschiedliche Basiszeilen: {row[id_pos]}"
            )

        target = id_match if id_match is not None else url_match
        if target is None:
            target = len(merged)
            merged.append(row)
            appended += 1
        else:
            old = merged[target]
            old_id = _key(old[id_pos])
            old_url = _key(old[url_pos]) if url_pos is not None else ""
            if old_id and old_id != event_id:
                base_by_id.pop(old_id, None)
            if old_url and old_url != event_url:
                base_by_url.pop(old_url, None)
            merged[target] = row
            replaced += 1

        base_by_id[event_id] = target
        if event_url:
            base_by_url[event_url] = target

    return header, merged, {
        "base": len(merged) - appended,
        "overlay": len(overlay_values),
        "replaced": replaced,
        "appended": appended,
    }


def read_tsv(path: Path) -> Tuple[List[str], List[List[str]]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        rows = list(csv.reader(handle, delimiter="\t"))
    if not rows:
        raise ValueError(f"{path}: Datei ist leer.")
    return rows[0], rows[1:]


def write_tsv(path: Path, header: Sequence[str], rows: Iterable[Sequence[str]]) -> None:
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle, delimiter="\t", lineterminator="\n")
        writer.writerow(header)
        writer.writerows(rows)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", required=True, type=Path)
    parser.add_argument("--overlay", type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    base_header, base_rows = read_tsv(args.base)
    overlay_header = overlay_rows = None
    if args.overlay is not None:
        overlay_header, overlay_rows = read_tsv(args.overlay)
    header, rows, stats = merge_event_rows(base_header, base_rows, overlay_header, overlay_rows)
    write_tsv(args.output, header, rows)
    print(
        "Events merge OK: "
        f"base={stats['base']} overlay={stats['overlay']} "
        f"replaced={stats['replaced']} appended={stats['appended']} total={len(rows)}"
    )


if __name__ == "__main__":
    main()
