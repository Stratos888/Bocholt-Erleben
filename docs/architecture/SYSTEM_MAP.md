# System Map – Bocholt erleben

Stand: 2026-07-18

Diese Datei beschreibt stabile Systeme, Datenhoheit, Umgebungen, kritische Datenflüsse und Owner. Operative Blocker, PRs und der aktuelle Workpack stehen ausschließlich in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Repository und Umgebungen

| Ebene | Staging | Live | Owner |
|---|---|---|---|
| Git-Branch | `staging` | `main` | GitHub / PR-Prozess |
| Webziel | STRATO-Verzeichnis `staging` | STRATO-Webroot `.` | `.github/workflows/deploy-strato.yml` |
| öffentliche URL | `https://staging.bocholt-erleben.de/` | `https://bocholt-erleben.de/` | Hosting/DNS |
| Steuerzentrale | `/steuerzentrale/` | `/steuerzentrale/` | `steuerzentrale/**`, `js/control-center/**`, `api/control-center/**` |

Nur `staging` und `main` dürfen deployen. Feature- und Agent-Branches liefern keine externe Umgebung aus.

## 2. Hauptkomponenten

### Public Frontend

- statisches HTML, CSS und JavaScript im Repository;
- öffentlicher CSS-Entrypoint `css/style.css`;
- Today-, Event- und Activity-Oberflächen;
- generierte Runtime-Daten und freigegebene DB-Submissions.

### Steuerzentrale

- UI: `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS;
- API: `api/control-center/**`;
- lokaler Zustand: Control-Center-Datenbank für Fälle, Events und Operationsprotokolle;
- Zweck: Quellen synchronisieren, Ausnahmen prüfen und kontrollierte Entscheidungen ausführen.

### Anbieterbereich und Submissions

- DB-/API-owned;
- öffentliche Einreichungen, Anbieterstatus, Produkt-/Paymentzustand und Wirkungsmessung;
- Mail, Zahlung und Veröffentlichung sind externe Nebenwirkungen und grundsätzlich R3.

### Visual-System

- Vertragsdaten: `data/event_visual_pool.json`;
- Prozessvertrag: `VISUAL_WORKFLOW.md`;
- Generatoren und Audits unter `scripts/**`;
- CSS steuert Darstellung, nicht fachliche Bildqualität.

## 3. Datenhoheit

| Domäne | Kanonische Quelle | Projektion/Artefakt |
|---|---|---|
| redaktionelle Live-Events | Google Sheet `Events` | `data/events.tsv`, `data/events.json`, Event-Detailseiten |
| Staging-Eventfreigaben | `Events_Staging` als isoliertes Overlay | Staging-Feed |
| offene Inbox | `Inbox_Staging` / `Inbox` | Control-Center-Fälle und Ansichten |
| Inbox-Archiv | `Inbox_Archive_Staging` / `Inbox_Archive` | Audit-/Entscheidungsbelege |
| DB-Submissions | Veranstalter-/Submission-Datenbank | Public-API und Feed-Ergänzung |
| Activities | Repo-/JSON-Owner | öffentliche Activity-Ausgabe |
| Visuals | Visual-Pool und freigegebene Assets | Karten-/Detaildarstellung |
| Strategie/Produkt | `MASTER.md`, `Produktvertrag.md`, `COMMERCIAL_STRATEGY.md` | UI-/Backend-Implementierung |

`data/events.tsv` und `data/events.json` sind generierte Artefakte und werden nicht manuell als Source of Truth gepflegt.

## 4. Event-Feed

```text
Google Sheet Events
+ auf Staging Events_Staging-Overlay
+ freigegebene DB-Submissions
-> Deploy-/Buildgeneratoren
-> data/events.tsv und data/events.json
-> Event-API, Detailseiten, Sitemap und UI
```

Staging darf `Events` lesen, aber nur `Events_Staging` beschreiben. Live ignoriert das Staging-Overlay.

## 5. Kritischer Inbox-Übernahmepfad

```text
Steuerzentrale
-> Control-Center Action API
-> Fall- und Environment-Auflösung
-> autoritativer Writeback-Plan
-> Eventziel schreiben und zurücklesen
-> Inboxstatus schreiben und zurücklesen
-> lokalen Fall terminal schließen
-> Feed und öffentliche Darstellung prüfen
```

Umgebungsbindung:

```text
staging: Inbox_Staging -> Events_Staging
live:    Inbox         -> Events
```

Dry-Run, Runtime-Preflight und Ausführung müssen denselben Environment-Resolver und Operationsplan verwenden.

## 6. Deploy- und Runtimepfad

```text
Feature-Branch
-> PR nach staging
-> PR Gate und path-spezifische Checks
-> Merge nach staging
-> Deploy to STRATO staging
-> HTTP-/Browser-/Runtime-Evidence
-> später staging -> main
-> Live-Deploy und E6
```

Direkte Main-Hotfixes sind eine eng begrenzte Ausnahme nach `AI_ENTRYPOINT.md` und verändern diese Standardarchitektur nicht.

## 7. Evidence-Grenzen

| Ebene | Beweist | Beweist nicht |
|---|---|---|
| Repo-Diff | implementierte Logik | reale Serverausführung |
| Unit/Contract/CI | Testverhalten | tatsächliche Host-/Ressourcenauflösung |
| grüner Deploy | Auslieferung und Smokes | gewählter Writebackpfad |
| Runtime-Preflight | aktiver Build, Endpoint, Umgebung und Plan | erfolgreiche Mutation |
| isolierter synthetischer Write | realer technischer Writeback | fachliche Qualität |
| echter Staging-Fall | fachlicher Gesamtprozess | Live-Freigabe |
| Live-Smoke | erreichbarer konsistenter Produktivstand | Langzeitwirkung |

## 8. Owner-Übersicht

| Domäne | Primäre Owner |
|---|---|
| Arbeitsprozess | `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md`, `docs/README.md` |
| Architektur | `docs/architecture/SYSTEM_MAP.md` |
| technische Guardrails | `ENGINEERING.md` |
| externe Ressourcen | `docs/external-resource-matrix.md` |
| Deploy/Branchrouting | `.github/workflows/deploy-strato.yml`, `scripts/resolve-deploy-target.sh` |
| Control-Center UI | `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS |
| Control-Center Runtime | `api/control-center/**` |
| Eventfeed | Deployworkflow, Eventgeneratoren, `api/events/**` |
| Eventdaten | Sheets `Events`/`Events_Staging`, DB-Submissions |
| Activities | Repo-/JSON-Daten und Generatoren |
| Visuals | `VISUAL_WORKFLOW.md`, Visual-Pool, Assets und Audits |
| Produktmechanik | `Produktvertrag.md` |
| Produktziel und Priorität | `MASTER.md`, `COMMERCIAL_STRATEGY.md`, `ROADMAP.md` |
| Proofindex | `TEST_STATUS.md`, CI und `docs/evidence/**` |

## 9. Standardprüfung vor kritischen Änderungen

1. Wer besitzt den fachlichen Ursprungswert?
2. Welche Kopie oder Projektion existiert?
3. Welcher Endpoint und welche Version laufen tatsächlich?
4. Wie wird die Umgebung aufgelöst?
5. Welche Ressource wird gelesen und welche geschrieben?
6. Welche Postconditions müssen einzeln bestätigt werden?
7. Wie wird ein Teilfehler sichtbar und fortgesetzt?
8. Wie werden synthetische Daten bereinigt?
9. Wie wird Live-Unverändertheit oder gezielte Live-Änderung bewiesen?
10. Welcher Owner und Ressourcen-Lock gilt?

Ist eine Antwort nicht belegbar, ist der nächste Schritt Observability oder read-only Forensik statt Fachlogik.
