# System Map – Bocholt erleben

Stand: 2026-07-17

Diese Datei ist die kompakte technische Orientierung für neue KI-Chats. Sie beschreibt aktuelle Systeme, Datenhoheit, Umgebungen und kritische Pfade. Detailregeln stehen in `ENGINEERING.md` und `docs/external-resource-matrix.md`.

## 1. Repository und Umgebungen

| Ebene | Staging | Live | Owner |
|---|---|---|---|
| Git-Branch | `staging` | `main` | GitHub / PR-Prozess |
| Webziel | STRATO-Verzeichnis `staging` | STRATO-Webroot `.` | `.github/workflows/deploy-strato.yml` |
| öffentliche URL | `https://staging.bocholt-erleben.de/` | `https://bocholt-erleben.de/` | Hosting/DNS |
| Steuerzentrale | `/steuerzentrale/` | `/steuerzentrale/` | `steuerzentrale/**`, `js/control-center/**`, `api/control-center/**` |

Nur `staging` und `main` dürfen deployen. Feature-/Agent-Branches dürfen keine externe Umgebung ausliefern.

## 2. Hauptkomponenten

### Public Frontend

- HTML/CSS/JavaScript im Repository.
- Öffentliche CSS-Kette startet über `css/style.css`.
- Event- und Activity-Darstellung nutzt generierte Runtime-Daten und freigegebene DB-Submissions.

### Steuerzentrale

- Frontend: `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS.
- API: `api/control-center/**`.
- Lokaler Zustand: Control-Center-Datenbank, insbesondere Fälle, Events und Operationsprotokolle.
- Aufgabe: Quellen synchronisieren, Reviewfälle darstellen und kontrollierte Entscheidungen ausführen.

### Google Sheets

| Zweck | Staging | Live |
|---|---|---|
| offene Inbox | `Inbox_Staging` | `Inbox` |
| Inbox-Archiv | `Inbox_Archive_Staging` | `Inbox_Archive` |
| Eventziel | `Events_Staging` | `Events` |

`Events` ist die redaktionelle Live-Basis. `Events_Staging` ist ein isoliertes Staging-Overlay und darf Live nicht verändern.

### Event-Feed

```text
Google Sheet Events
+ auf Staging optional Events_Staging-Overlay
+ freigegebene DB-Submissions
-> Deploy/Generator
-> data/events.tsv / data/events.json
-> öffentliche Event-API und UI
```

`data/events.tsv` und `data/events.json` sind generierte Artefakte, keine manuell gepflegte Quelle.

### Submissions und Anbieterprozesse

- DB-/API-owned.
- Öffentliche Freigabe wird über die vorgesehenen Submission- und Public-API-Pfade ergänzt.
- Payment, Mail und Veröffentlichung sind externe Nebenwirkungen und grundsätzlich `R3`.

### Visual-System

- Datenvertrag: `data/event_visual_pool.json` und zugehörige Generatoren/Audits.
- Arbeitsvertrag: `VISUAL_WORKFLOW.md`.
- CSS definiert Darstellung, nicht fachliche Bildqualität.

## 3. Kritischer Inbox-Übernahmepfad

Fachlicher Zielpfad:

```text
Steuerzentrale
-> Control-Center Action API
-> Fall- und Environment-Auflösung
-> Writeback-Plan
-> Eventziel schreiben und zurücklesen
-> Inboxstatus schreiben und zurücklesen
-> lokalen Fall schließen
-> Staging-Feed aktualisieren/prüfen
```

Umgebungsbindung:

```text
staging: Inbox_Staging -> Events_Staging
live:    Inbox         -> Events
```

Aktueller Status:

- Der CityArt-Fall bleibt bis zum Runtime-Truth- und E2E-Programm eingefroren.
- PR #86 ist nur technische Evidence und darf nicht gemergt werden.
- Vor einem weiteren echten Übernahmeversuch sind E3 und E4 erforderlich.

## 4. Evidence-Grenzen

| Ebene | Was sie beweist | Was sie nicht beweist |
|---|---|---|
| Repo-Diff | implementierte Logik | reale Serverausführung |
| Unit/Contract/CI | Testverhalten | tatsächlicher Host/Writer/Ressourcenzugriff |
| grüner Deploy | Auslieferung und Smoke | gewählter Writebackpfad |
| Runtime-Preflight | aktiver Build, Endpoint und Ressourcenplan | erfolgreiche Mutation |
| isolierter synthetischer Write | realer technischer Writeback | fachliche Qualität eines echten Falls |
| echter Staging-Fall | fachlicher Gesamtprozess | Live-Freigabe |

## 5. Owner-Übersicht

| Domäne | Primäre Owner |
|---|---|
| Arbeitsprozess | `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md` |
| technische Guardrails | `ENGINEERING.md` |
| externe Ressourcen | `docs/external-resource-matrix.md` |
| Deploy/Branchrouting | `.github/workflows/deploy-strato.yml`, `scripts/resolve-deploy-target.sh` |
| Control-Center UI | `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS |
| Control-Center Runtime | `api/control-center/**` |
| Eventfeed | Deployworkflow, Eventgeneratoren, `api/events/**` |
| Eventdaten | Google Sheet `Events` bzw. `Events_Staging`; DB-Submissions |
| Activities | Repo-/JSON-Daten und zugehörige Generatoren |
| Visuals | `VISUAL_WORKFLOW.md`, Visual-Pool, Asset-/Audit-Skripte |
| Produktprioritäten | `MASTER.md`, `ROADMAP.md` |
| Testnachweise | `TEST_STATUS.md`, CI und Evidence-Dokumente |

## 6. Standard-Datenflussprüfung für KI

Vor einer Änderung an einem kritischen Prozess beantwortet die KI:

1. Wer besitzt den fachlichen Ursprungswert?
2. Welche lokale Kopie oder Projektion existiert?
3. Welcher Endpoint wird tatsächlich aufgerufen?
4. Wie wird die Umgebung aufgelöst?
5. Welche Ressource wird gelesen und welche geschrieben?
6. Welche einzelnen Postconditions müssen bestätigt werden?
7. Wie wird ein Teilfehler sichtbar und fortgesetzt?
8. Wie werden synthetische Testdaten bereinigt?
9. Wie wird Live-Unverändertheit bewiesen?

Kann eine Frage nicht belegt werden, ist die nächste Änderung Observability und nicht Fachlogik.

## 7. Dokumentationshierarchie

```text
AI_ENTRYPOINT.md
-> CURRENT_WORKPACK.md
-> SYSTEM_MAP.md
-> relevante Owner-Dateien
-> ENGINEERING.md / external-resource-matrix.md
-> ROADMAP.md / TEST_STATUS.md
-> Entscheidungen und Forensik
```

Historische Chats oder alte Dokumente dürfen diese aktuelle Systemkarte nicht übersteuern.