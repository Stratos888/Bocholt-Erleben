# Current Workpack

Stand: 2026-07-20

## Aktiver Workpack

**Gemeinsamer Event-Identitätsvertrag für KI-Intake und Freigabe-Preflight.**

- Ausgangs-SHA `staging`: `9f1552983ebecd373d6449e96d1ec958ef70c0b9`
- Branch: `agent/event-identity-intake-preflight`
- Risikoklasse: `R2`
- Ziel: Exakte Dubletten werden vor dem Inbox-Append verworfen; starke semantische Treffer erhalten Match-Evidence und müssen in der Steuerzentrale entschieden werden. Unmittelbar vor jeder Übernahme wird gegen den aktuellen effektiven Eventbestand erneut geprüft.
- Staging-Bestand: `Events` plus `Events_Staging` als ID-basiertes Overlay.
- Live-Bestand: ausschließlich `Events`.
- Schutz: gleiche stabile ID kann bei einem idempotenten Resume weiterverarbeitet werden; ID-Konflikte und neue Dubletten blockieren fail-closed.

## Aktuelle Locks

- `.github/workflows/manual-ki-intake.yml`
- `scripts/manual_ki_event_intake.py`
- `scripts/event_identity.py`
- `data/event_identity_contract.json`
- `api/control-center/_event_identity.php`
- Event-Inbox-Synchronisation, Review-Writeback und Approval-Preflight
- keine externe Datenmutation und kein Main-/Live-Release in diesem Workpack

## Evidence-Plan

- E1: gemeinsamer JSON-Vertrag und Python-/PHP-Implementierung;
- E2: gemeinsame Fixture-Matrix einschließlich CityArt, ID-Konflikt, Shared-URL, Same-ID-Resume und Overlay-Sicherheit; vollständiger `PR Gate`;
- E3 nach Integration: normaler Staging-Deploy und read-only Bestätigung, dass der vorhandene CityArt-Kandidat als mögliche Dublette erscheint, sofern der reale Staging-Datensatz noch vorhanden ist.

## Empfohlener nächster Workpack nach Abschluss

**Search-Intent und statische Renderingbasis – SEO-Recovery nach aktueller Suchbaseline.**
