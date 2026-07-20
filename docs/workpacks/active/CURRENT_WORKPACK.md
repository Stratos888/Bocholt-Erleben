# Current Workpack

Stand: 2026-07-20

## Aktiver Workpack

**Event-Builder-Kompatibilität als kleiner lokaler Contract-Test.**

## Aktuelle Locks

- `scripts/build-events-from-tsv.py`
- `tests/test_event_builder_control_center_contract.py`
- `scripts/validate-repo.sh`
- `TEST_STATUS.md`
- `docs/workpacks/active/CURRENT_WORKPACK.md`

## Empfohlener nächster Workpack

**Keiner während der aktiven Umsetzung.**

## Aktivierungszustand

**Aktiv auf `agent/event-builder-control-center-contract`.**

Ziel ist ausschließlich der lokale Nachweis, dass eine vom bestehenden Control-Center-Writer erzeugte Eventzeile vom normalen Event-Builder verarbeitet wird. Kein externer Write, keine neue Workflowdatei und keine Control-Center-Runtimeänderung.