## Ziel und belegte Ursache

- Problem/Ist-Zustand:
- Erwarteter Zustand:
- Belegte Root Cause:
- Ausdrücklich noch offene Hypothesen:

## Scope

- [ ] Governance/Dokumentation
- [ ] Runtime/UI/API
- [ ] Quelle/Mapping/Writeback
- [ ] Deployment/Environment
- [ ] Externe Datenänderung

Führende Quelle und stabile Identität:

- Umgebung:
- Quellsystem/Tab:
- Objekt-ID:
- Zeilenidentität:

## Isolierte Reproduktion und Integration

- Testbestand/Fixtures:
- vollständige E2E-Kette geprüft:
- Evidence-Dateien:
- [ ] Keine realen Staging- oder Live-Daten als Entwicklungs-Testbestand verwendet

## Environment- und Datenvertrag

- [ ] Staging zeigt ausschließlich auf Staging-Ziele
- [ ] Live zeigt ausschließlich auf Live-Ziele
- [ ] unbekannte Umgebung blockiert fail-closed
- [ ] Quellzeile, lokaler Fall, API-Payload und UI sind über stabile Identität verbunden

## Reale Staging-Abnahme

Für PR nach `staging`:

- Status: `pending_after_merge` oder `not_required`
- kontrollierte Schreibprobe erforderlich: ja/nein
- Vorher-Snapshot, erwartete Mutation und Rollback für die spätere Abnahme beschrieben:

Für PR nach `main`:

- Staging-Deploy:
- read-only Smoke:
- kontrollierte Schreibprobe einschließlich Vorher-/Nachher-Evidence:
- Abnahme-Evidence:

## Sicherheits- und Mergegrenzen

- [ ] Change-Manifest für den aktuellen Branch aktualisiert
- [ ] Rollback beschrieben
- [ ] Keine Live-Schreibtests
- [ ] Merge nach `staging` wird nicht als fachliche Freigabe dargestellt
- [ ] `main` bleibt gesperrt, solange reale Staging-Abnahme fehlt
