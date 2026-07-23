#!/usr/bin/env python3
"""Contract: Control Center event rows must pass the normal Event Builder."""
from __future__ import annotations

import csv
import json
import os
import subprocess
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
BUILDER = ROOT / "scripts" / "build-events-from-tsv.py"

PHP_EVENT_FIXTURE = r'''
require getcwd() . '/api/control-center/_staging_events_writeback.php';
$source = [
    'id_suggestion' => 'control-center-builder-contract-2099-08-30',
    'title' => 'Bocholter Kunstmarkt mit Mitmachangeboten',
    'date' => '2099-08-30',
    'time' => '11:00',
    'time_status' => 'fixed_time',
    'time_details' => '11:00–18:00 Uhr',
    'city' => 'Bocholt',
    'location' => 'Markt vor dem Historischen Rathaus',
    'kategorie_suggestion' => 'Kinder & Familie',
    'source_url' => 'https://www.bocholt.de/veranstaltungskalender/contract-test',
    'description' => 'Lokale Künstlerinnen und Künstler zeigen ihre Arbeiten, während Kinder und Jugendliche an offenen Mitmachstationen selbst kreativ werden können.',
    'visual_key' => 'art_exhibition_gallery',
];
$event = be_cc_staging_event_from_source($source);
echo json_encode(
    ['columns' => BE_CC_STAGING_EVENT_COLUMNS, 'event' => $event],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);
'''


def fail(message: str) -> None:
    raise AssertionError(message)


def control_center_event() -> tuple[list[str], dict[str, str]]:
    result = subprocess.run(
        ["php", "-r", PHP_EVENT_FIXTURE],
        cwd=ROOT,
        text=True,
        encoding="utf-8",
        capture_output=True,
        check=False,
    )
    if result.returncode != 0:
        fail(f"Control-Center-Writer konnte die Testzeile nicht erzeugen:\n{result.stderr}")

    payload = json.loads(result.stdout)
    columns = payload.get("columns")
    event = payload.get("event")
    if not isinstance(columns, list) or not all(isinstance(item, str) for item in columns):
        fail("Control-Center-Writer lieferte keinen kanonischen Eventheader.")
    if not isinstance(event, dict):
        fail("Control-Center-Writer lieferte keine Eventzeile.")
    return columns, {str(key): str(value) for key, value in event.items()}


def run_builder(columns: list[str], event: dict[str, str]) -> list[dict[str, object]]:
    with tempfile.TemporaryDirectory(prefix="be-event-builder-contract-") as temp_dir:
        temp = Path(temp_dir)
        tsv_path = temp / "events.tsv"
        json_path = temp / "events.json"

        with tsv_path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(handle, fieldnames=columns, delimiter="\t", lineterminator="\n")
            writer.writeheader()
            writer.writerow(event)

        env = os.environ.copy()
        env.update(
            {
                "BE_EVENT_BUILDER_TSV_PATH": str(tsv_path),
                "BE_EVENT_BUILDER_JSON_PATH": str(json_path),
                "SITE_ORIGIN": "https://staging.bocholt-erleben.de",
            }
        )
        result = subprocess.run(
            [sys.executable, str(BUILDER)],
            cwd=ROOT,
            env=env,
            text=True,
            encoding="utf-8",
            capture_output=True,
            check=False,
        )
        if result.returncode != 0:
            fail(
                "Der normale Event-Builder lehnte die vom Control Center erzeugte Zeile ab.\n"
                f"STDOUT:\n{result.stdout}\nSTDERR:\n{result.stderr}"
            )
        if not json_path.is_file():
            fail("Der Event-Builder erzeugte kein JSON-Artefakt.")
        payload = json.loads(json_path.read_text(encoding="utf-8"))
        if not isinstance(payload, list):
            fail("Das Event-Builder-Artefakt ist keine Eventliste.")
        return payload


def main() -> None:
    columns, event = control_center_event()
    if event.get("time") != "11:00–18:00 Uhr":
        fail("Der Control-Center-Writer bewahrt die vollständige offizielle Zeitspanne nicht.")

    built = run_builder(columns, event)
    if len(built) != 1:
        fail(f"Der Event-Builder muss genau ein Contract-Event erzeugen, erhalten: {len(built)}")

    item = built[0]
    expected_id = "control-center-builder-contract-2099-08-30"
    if item.get("id") != expected_id:
        fail("Die Event-ID wurde zwischen Control Center und Event-Builder verändert.")
    if item.get("time") != "11:00–18:00 Uhr":
        fail("Die vollständige offizielle Zeitspanne ging im Event-Builder verloren.")
    if item.get("kategorie") != "Kinder & Familie":
        fail("Die kanonische Kategorie ging im Event-Builder verloren.")
    if item.get("detail_url") != f"https://staging.bocholt-erleben.de/events/{expected_id}/":
        fail("Der Event-Builder erzeugte keine korrekte Staging-Detail-URL.")

    duplicate_a = dict(event)
    duplicate_a.update({
        "id": "2-bocholter-vereinsmesse-in-den-shopping-arkaden-2099-09-27",
        "title": "2. Bocholter Vereinsmesse in den Shopping Arkaden",
        "date": "2099-09-27", "time": "13:00–18:00 Uhr",
        "location": "Shopping Arkaden",
        "source_url": "https://www.bocholt.de/veranstaltungskalender/vereinsmesse",
    })
    duplicate_b = dict(duplicate_a)
    duplicate_b.update({
        "id": "2-bocholter-vereinsmesse-2099-09-27",
        "title": "2. Bocholter Vereinsmesse",
        "time": "12:00–17:00 Uhr",
        "location": "Shopping-Arkaden",
    })
    with tempfile.TemporaryDirectory(prefix="be-event-builder-duplicate-") as temp_dir:
        temp = Path(temp_dir)
        tsv_path, json_path = temp / "events.tsv", temp / "events.json"
        with tsv_path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(handle, fieldnames=columns, delimiter="\t", lineterminator="\n")
            writer.writeheader()
            writer.writerows([duplicate_a, duplicate_b])
        env = os.environ.copy()
        env.update({"BE_EVENT_BUILDER_TSV_PATH": str(tsv_path), "BE_EVENT_BUILDER_JSON_PATH": str(json_path)})
        result = subprocess.run([sys.executable, str(BUILDER)], cwd=ROOT, env=env, text=True, encoding="utf-8", capture_output=True)
        if result.returncode == 0 or "semantische Event-Dublette" not in result.stderr:
            fail(f"Semantische Vereinsmesse-Dublette wurde nicht fail-closed blockiert:\n{result.stdout}\n{result.stderr}")

    print("=== Event Builder / Control Center Contract: OK ===")


if __name__ == "__main__":
    main()
