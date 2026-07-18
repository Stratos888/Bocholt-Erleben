# Aktueller Proofindex – Bocholt erleben

Stand: 2026-07-18

Diese Datei ist ein kompakter Index der aktuell relevanten Evidence. Sie ist kein historisches Testtagebuch. Ausführliche Nachweise liegen in CI, `docs/evidence/`, `docs/forensics/`, Entscheidungen und Git-Historie.

## 1. Evidence-Legende

| Stufe | Bedeutung |
|---|---|
| E0 | Hypothese |
| E1 | aktueller Code oder Diff |
| E2 | automatisierter Test, CI oder Replay |
| E3 | deployte read-only Runtime-Evidence |
| E4 | isolierter realer Staging-Write oder kontrollierte deterministische Admin-Mutation mit Rücklesen |
| E5 | echter fachlicher Staging-Fall |
| E6 | read-only Live-Smoke |

## 2. Aktueller projektweiter Proofstand

| Bereich | Höchste aktuell relevante Evidence | Status | Führende Quelle |
|---|---:|---|---|
| Branch- und Deployrouting | E2 | nur `staging` und `main` dürfen deployen; Standardrelease `staging -> main` | CI und `SYSTEM_MAP.md` |
| PR-Integration | E2 | Always-run `PR Gate` aggregiert gestartete Actions-Checks | `.github/workflows/pr-gate.yml` |
| Dokumentations-Governance | E2 nach grünem PR | Struktur-, Größen- und Widerspruchsaudit | `scripts/audit-documentation-governance.py` |
| Staging-Deploy Control Center | E3 | deployter Staging-Build und read-only Runtime-Preflight belegt | `CURRENT_WORKPACK.md` und CI |
| Control-Center-Environment | E3 | `Inbox_Staging -> Events_Staging`; Live-Ressourcen nicht verwendet | `CURRENT_WORKPACK.md` |
| Control-Center externer Write | E3, E4 offen | Harness statisch vorhanden; kein synthetischer E4-Lauf ausgeführt | `CURRENT_WORKPACK.md` |
| CityArt-Fachfall | unter E5 | eingefroren bis Workflow-Konsolidierung und E4 | `CURRENT_WORKPACK.md` |
| direkte einzelne Live-Eventpflege | pro Operation neu | Policy vorhanden; jeder Fall benötigt eigenen Vorher-/Write-/Rücklesebeleg | `AI_ENTRYPOINT.md`, Ressourcenmatrix |
| direkter Main-Hotfix | nicht operativ freigeschaltet | dedizierter Ruleset-Bypass-Akteur fehlt; kein allgemeiner Erfolgsbeweis | zugehörige Entscheidung und `AI_ENTRYPOINT.md` |
| Search-Intent-/SEO-Recovery | E1 Ziel/Forensik | Zielarchitektur und Workpack dokumentiert; noch keine Implementierung | `docs/forensics/` und queued Workpack |
| Startpartner-Wachstumspilot | E1 Zielzustand | fachlich validiert; operativer E2E-Lebenszyklus noch nicht umgesetzt | Zielzustandsdatei |
| Weekly-KI-/Inbox-Betrieb | historische E2/E5-Teilbelege | reguläre Folgeläufe nur anhand neuer Artefakte bewerten | CI/Artefakte; kein aktueller Umbau ohne Befund |

## 3. Aktuelle harte Evidence-Lücke

Der wichtigste offene Runtimebeweis ist:

```text
Workflow-Konsolidierung
-> erneutes E3
-> genau ein synthetischer E4-Staging-Write
-> vollständiges Rücklesen und Cleanup
```

Bis dahin darf kein echter Control-Center-Fachfall als technischer Erfolgsbeweis verwendet werden.

## 4. Evidence-Eintrag

Neue ausführliche Evidence dokumentiert mindestens:

- Datum und exakten Commit-/Build-SHA;
- Umgebung, Host und Endpoint;
- relevante Quell- und Zielressourcen;
- Test- oder Objektidentität;
- ausgeführte Schritte;
- erwartete und tatsächliche Postconditions;
- Cleanup/Rollback;
- erreichte Evidence-Stufe;
- Restunsicherheiten;
- Link auf Workflow, Artefakt, Decision oder Forensik.

## 5. Pflege

`TEST_STATUS.md` wird nur geändert, wenn sich der höchste relevante Proofstand oder eine harte Evidence-Lücke ändert.

Nicht hier ablegen:

- komplette Run-Logs;
- wiederholte Patchbeschreibungen;
- „nach Deployment prüfen“-Listen ohne neuen Beweis;
- alte Screenshots oder historische UI-Abnahmen;
- Produktroadmap und Workpackstatus.

Frühere umfangreiche Einträge bleiben vollständig in der Git-Historie nachvollziehbar.
