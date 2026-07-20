# System Map – Bocholt erleben

Diese Datei beschreibt stabile Systeme, Datenhoheit, Umgebungen und kritische Datenflüsse. Operative Blocker und der aktuelle Workpack stehen ausschließlich in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Repository und Umgebungen

| Ebene | Staging | Live | Owner |
|---|---|---|---|
| Git-Branch | `staging` | `main` | GitHub / PR-Prozess |
| Webziel | STRATO-Verzeichnis `staging` | STRATO-Webroot `.` | `.github/workflows/deploy-strato.yml` |
| öffentliche URL | `https://staging.bocholt-erleben.de/` | `https://bocholt-erleben.de/` | Hosting/DNS |
| Steuerzentrale | `/steuerzentrale/` | `/steuerzentrale/` | `steuerzentrale/**`, `js/control-center/**`, `api/control-center/**` |

Nur `staging` und `main` dürfen deployen. Feature-Branches besitzen keine externe Umgebung.

## 2. Hauptkomponenten

### Public Frontend

- statisches HTML, CSS und JavaScript;
- Today-, Event- und Activity-Oberflächen;
- generierte Event-/Inbox-Daten und freigegebene DB-Submissions.

### Steuerzentrale

- UI: `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS;
- API: `api/control-center/**`;
- lokaler Zustand: Control-Center-Datenbank für Fälle und Operationszustände;
- Zweck: Quellen synchronisieren, Ausnahmen prüfen und kontrollierte Entscheidungen ausführen.

### Anbieterbereich und Submissions

- DB-/API-owned;
- Einreichungen, Anbieterstatus, Produkte, Zahlung und Wirkungsmessung;
- Mail, Zahlung und Veröffentlichung sind externe Nebenwirkungen.

### Visual-System

- Vertragsdaten: `data/event_visual_pool.json`;
- Prozessvertrag: `VISUAL_WORKFLOW.md`;
- Generatoren und Audits unter `scripts/**`.

## 3. Datenhoheit

| Domäne | Kanonische Quelle | Projektion/Artefakt |
|---|---|---|
| redaktionelle Live-Events | Google Sheet `Events` | Eventfeed und Detailseiten |
| Staging-Eventfreigaben | `Events_Staging` als Overlay | Staging-Feed |
| offene Inbox | `Inbox_Staging` / `Inbox` | Control-Center-Fälle und Ansichten |
| Inbox-Archiv | `Inbox_Archive_Staging` / `Inbox_Archive` | Archiv |
| DB-Submissions | Submission-Datenbank | Public-API und Feed-Ergänzung |
| Activities | Repo-/JSON-Owner | öffentliche Activity-Ausgabe |
| Visuals | Visual-Pool und freigegebene Assets | Karten-/Detaildarstellung |

`data/events.tsv`, `data/events.json` und `data/inbox.json` sind generierte Buildartefakte.

## 4. Event-Feed

```text
Google Sheet Events
+ auf Staging Events_Staging-Overlay
+ freigegebene DB-Submissions
-> Deploy-/Buildgeneratoren
-> Event-API, Detailseiten, Sitemap und UI
```

Staging darf `Events` lesen, aber nur `Events_Staging` beschreiben. Live ignoriert das Overlay.

## 5. Inbox-Übernahmepfad

```text
Steuerzentrale
-> Action API
-> Fall- und Environment-Auflösung
-> fallbezogener read-only Preflight
-> Eventziel schreiben und zurücklesen
-> Inboxstatus schreiben und zurücklesen
-> lokalen Fall schließen
```

Umgebungsbindung:

```text
staging: Inbox_Staging -> Events_Staging
live:    Inbox         -> Events
```

Preflight und Ausführung verwenden denselben Environment- und Writer-Resolver.

## 6. Entwicklungs- und Deploypfad

```text
Feature-Branch
-> PR nach staging
-> ein Required Check: PR Gate
-> Merge nach staging
-> genau ein Deploy to STRATO
-> Build-/HTTP-Smoke
-> später staging -> main
```

Zusätzliche Deployobserver, Runtimeverification-Workflows und synthetische Folge-Deploys gehören nicht zur Standardarchitektur.

## 7. Dauerhafte Workflowrollen

| Workflow | Rolle |
|---|---|
| `PR Gate` | Branchpolicy und Repositorytests |
| `Deploy to STRATO` | Feed-Build und Deploy |
| `Content Quality Audit` | Inhaltsqualität |
| `Inbox Cleanup (Archive)` | Inbox-Archivierung |
| `Weekly KI Websearch → Manual Inbox` | Eventkandidatensuche |
| `Manual KI Event Intake` | Kandidaten-Handoff |

## 8. Owner-Übersicht

| Domäne | Primäre Owner |
|---|---|
| Arbeitsprozess | `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md` |
| Architektur | `docs/architecture/SYSTEM_MAP.md` |
| technische Regeln | `ENGINEERING.md` |
| externe Ressourcen | `docs/external-resource-matrix.md` |
| PR-Prüfung | `.github/workflows/pr-gate.yml`, `scripts/validate-repo.sh` |
| Deploy/Branchrouting | `.github/workflows/deploy-strato.yml`, `scripts/resolve-deploy-target.sh` |
| Control-Center UI | `steuerzentrale/**`, `js/control-center/**` |
| Control-Center Runtime | `api/control-center/**` |
| Eventfeed | Deployworkflow, Eventgeneratoren, `api/events/**` |
| Produktziel und Priorität | `MASTER.md`, `COMMERCIAL_STRATEGY.md`, `ROADMAP.md` |
| Proofstand | `TEST_STATUS.md` |

## 9. Prüfung vor kritischen Änderungen

1. Wer besitzt den Ursprungswert?
2. Welche Projektionen existieren?
3. Welche Umgebung und Ressource wird verwendet?
4. Was wird konkret gelesen oder geschrieben?
5. Welche Postconditions müssen bestätigt werden?
6. Wie wird ein Teilfehler sichtbar?
7. Wie erfolgt Rollback oder Cleanup?
8. Ist ein anderer Chat oder Workpack am selben Owner aktiv?

Ist eine Antwort nicht belegbar, folgt read-only Analyse statt Mutation.
