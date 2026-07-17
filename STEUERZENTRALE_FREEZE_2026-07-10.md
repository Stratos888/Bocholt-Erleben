# Steuerzentrale – kanonischer Einstieg

Stand: 2026-07-17

Vor jeder Analyse, Änderung, Abnahme oder Veröffentlichung der Steuerzentrale vollständig und in dieser Reihenfolge lesen:

1. `STEUERZENTRALE_WORKMODE_FREEZE_2026-07-17.md`
2. `docs/steuerzentrale-zielzustand-2026-07-14.md`
3. den aktiven fachlichen Workpack und seine Evidence-Dateien

Der Arbeits- und Abnahmemodus vom 17.07.2026 hat bei Prozess-, Test-, Daten- und Mergefragen Vorrang vor älteren Formulierungen.

## Aktueller Stop-the-line-Status

- Keine weiteren funktionalen Patches an der Eventprüfung.
- Keine manuellen Korrekturen in `Inbox` oder `Inbox_Staging` zu Testzwecken.
- Keine weiteren Staging-Schreibaktionen zur Fehlersuche.
- `main` bleibt unverändert.
- Nächster fachlicher Schritt erst nach aktivierter Governance: forensische Inventur der vollständigen CityArt-Daten- und Zustandskette sowie Prüfung möglicher unbeabsichtigter Live-Mutationen.

## Verbindliche Umgebungsgrenzen

- CI/lokal: Fixtures oder temporärer Testbestand
- Staging: `Inbox_Staging`
- Live: `Inbox`
- unbekannte Umgebung: fail-closed

## Mergegrenzen

- Arbeitsbranch → `staging`: nur nach belegter Ursache, isolierter Integration und grünem Governance-Manifest
- Merge nach `staging`: Deploy in die Entwicklungsumgebung, keine fachliche Freigabe
- reale Staging-Abnahme: erst nach Staging-Deploy, kontrolliert und evidence-basiert
- `staging` → `main`: erst nach vollständig belegter Staging-Abnahme
- keine Live-Schreibtests
