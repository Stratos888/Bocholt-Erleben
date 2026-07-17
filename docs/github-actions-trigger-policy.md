# GitHub Actions Trigger Policy

Stand: 2026-07-17
Branch: `staging`

## Ziel

Staging soll als schneller technischer Testpfad funktionieren. Kleine Commits auf `staging` sollen primaer den notwendigen Staging-Deploy ausloesen, aber keine schweren Content-, Growth-, KI- oder Audit-Laeufe.

Diese Entkopplung ist gewollt und kein Fehler. Sie senkt nicht die Produktqualitaet, sondern trennt schnelle technische Iteration von periodischer und produktiver Qualitaetssicherung.

Pull Requests erhalten zusaetzlich einen stabilen, immer vorhandenen Merge-Check. Dieser Check darf bestehende fachliche Tests nicht duplizieren und darf den schnellen `staging`-Push-Pfad nicht belasten.

## Grundsatz

| Workflow-Klasse | `staging` Push | `main` Push | Schedule | Manual |
|---|---:|---:|---:|---:|
| Deploy / Staging-Auslieferung | ja | ja | ja, wenn fachlich benoetigt | ja |
| Schnelle Smoke-/Syntaxchecks | optional | optional | nein | optional |
| Content Quality Audit | nein, ausser gezielter Workflow-Haertung | ja bzw. relevante Produkt-/Content-Pfade | ja | ja |
| Growth Intelligence | nein | nein | ja | ja |
| KI-Websearch / Event Intake | nein | nur produktiv kontrolliert | ja | ja |
| Inbox Cleanup / Content Ops Reporting | nein | nein | ja | ja |
| Content Ops HTTP Ingest | nein | nein | via `workflow_run` nach relevanten Quelllaeufen | ja |

## Always-run PR Gate

`.github/workflows/pr-gate.yml` erzeugt bei jedem Pull Request nach `staging` oder `main` genau den stabilen Check:

```text
PR Gate
```

Der Gate-Workflow hat bewusst keinen `paths`-Filter. Dadurch ist der Check bei jedem PR vorhanden und kann spaeter als verpflichtender Statuscheck verwendet werden.

Der Gate-Workflow fuehrt keine zweite Kopie aller Testpakete aus. Er arbeitet als schlanker Aggregator:

1. Er prueft sofort die Repository- und Branch-Regel.
2. PRs nach `staging` muessen aus einem Branch desselben Repositories kommen.
3. PRs nach `main` sind ausschliesslich als `staging -> main` erlaubt.
4. Danach wartet er nur auf GitHub-Actions-Checks, die fuer den konkreten Dateiscope ohnehin gestartet wurden.
5. Ein gestarteter fehlgeschlagener, abgebrochener oder zeitlich ausgelaufener Check macht auch `PR Gate` rot.
6. Nicht relevante schwere Workflows werden nicht gestartet und nicht simuliert.

Effizienzziel:

- Bei einem kleinen PR ohne weitere pfadspezifische Checks entsteht nur eine kurze Registrierungs- und Stabilitaetswartezeit von rund 20 bis 30 Sekunden plus Runner-Start.
- Bei einem fachlichen PR endet `PR Gate` ungefaehr mit dem langsamsten ohnehin erforderlichen Check; es entsteht keine doppelte PHP-, JavaScript-, Python-, Browser- oder Produktpruefung.
- Der Gate-Workflow laeuft nur auf `pull_request`, nicht auf `push`, `schedule` oder Content-Roboterlaeufen.
- Ein neuer Commit storniert den veralteten Gate-Lauf desselben PRs automatisch.

Damit verbessert der Gate die Sicherheit und Parallelisierbarkeit, ohne die bestehende schnelle Staging-Auslieferung in einen monolithischen CI-Lauf umzubauen.

## Gewollter schneller Staging-Pfad

Ein kleiner technischer Commit auf `staging` soll im Regelfall nur ausloesen:

1. `Deploy to STRATO`
2. ggf. sehr schnelle Guards, falls sie Deploybruch verhindern

Nicht gewollt bei normalen Staging-Testcommits:

- `Content Quality Audit`
- `Growth Intelligence Backlog`
- `Weekly KI Websearch → Manual Inbox`
- `Manual KI Event Intake`
- `Inbox Cleanup (Archive)`
- `Content Ops HTTP Ingest` allein wegen Push
- `PR Gate`, weil dieser ausschliesslich PR-basiert ist

## Aktueller Stand nach Trigger-Haertung

### Bereits absichtlich entkoppelt

- `.github/workflows/growth-intelligence-backlog.yml`
  - `push`-Trigger wurde entfernt.
  - Workflow laeuft nur noch per `workflow_dispatch` oder `schedule`.
  - Grund: Growth-/Acquisition-Analyse ist schwer und fuer technische Staging-Tests nicht erforderlich.

- `.github/workflows/content-ops-http-ingest.yml`
  - `push`-Trigger wurde entfernt.
  - Workflow laeuft nur noch per `workflow_dispatch` oder `workflow_run` nach relevanten Content-Ops-Quellworkflows.
  - Grund: HTTP-Ingest soll Artefakte abgeschlossener Roboterlaeufe uebertragen, nicht jeden Staging-Push begleiten.

### Bereits passend

- `.github/workflows/deploy-strato.yml`
  - laeuft auf `push` nach `main` und `staging`.
  - bleibt zentraler schneller Staging-Testpfad.

- `.github/workflows/inbox-cleanup.yml`
  - laeuft per `workflow_dispatch` und `schedule`.
  - kein `staging`-Push-Trigger.

- `.github/workflows/weekly-ki-websearch-to-manual-inbox.yml`
  - laeuft per `workflow_dispatch` und `schedule`.
  - zusaetzlich produktiver Branch-Guard: echter KI-Lauf nur auf `main`.

- `.github/workflows/manual-ki-intake.yml`
  - laeuft per `workflow_dispatch`.
  - zusaetzlich produktiver Branch-Guard: echter Intake nur auf `main`.

### Final gehaertet

- `.github/workflows/content-quality-audit.yml`
  - `push` auf `staging` wurde entfernt.
  - Workflow laeuft weiter per `workflow_dispatch` und `schedule`.
  - Zusaetzlich laeuft er bei `push` auf `main`, aber nur fuer gezielte Audit-/Content-relevante Pfade.
  - Breite `data/**`-Ausloesung wurde bewusst entfernt, damit automatische oder kleine Daten-/Testcommits keine schweren Audits mitziehen.

## Main-Schutz

Die Entkopplung betrifft nur schnelle Staging-Iteration. Vor oder nach produktiven Aenderungen duerfen schwere Qualitaetslaeufe weiterhin laufen:

- per `schedule`,
- per `workflow_dispatch`,
- auf `main`,
- oder bei bewusst relevanten Pfadaenderungen.

Wichtig: Keine Qualitaetspruefung entfernen, sondern Ausloesezeitpunkt sauberer steuern.

Der stabile `PR Gate` ist die technische Eingangsschranke. Er ersetzt nicht die realen Staging- und Live-Abnahmen des Integrations-Chats.

## Arbeitsregel fuer zukuenftige KI-Chats

Wenn ein Workflow schwer ist und nicht unmittelbar Deploybruch verhindert, darf er nicht wieder pauschal an jeden `staging`-Push gekoppelt werden.

Bei Workflow-Aenderungen immer konkret pruefen:

- `on.pull_request.branches`,
- `on.pull_request.paths`,
- `on.push.branches`,
- `on.push.paths`,
- `schedule`,
- `workflow_dispatch`,
- `workflow_run`,
- job-level `if`,
- Downstream-Dispatches und Artefakt-Abhaengigkeiten.

Neue oder geaenderte Workflows muessen in diese Policy passen oder die Abweichung explizit begruenden.

Der Checkname `PR Gate` darf nicht umbenannt werden, solange er in einem Branch-Ruleset als verpflichtender Check eingetragen ist.

## Workflow-Dateien: wichtige Aenderungsgrenzen

Nicht erneut versuchen, Workflow-Dateien durch einen selbstmodifizierenden One-off-Workflow auf `staging` zu aendern.

Validierte Fehlerursachen vom 2026-07-07:

- `workflow_dispatch` ist fuer manuelle Ausloesung nur verlaesslich nutzbar, wenn die Workflow-Datei auf dem Default-Branch vorhanden ist. Ein nur auf `staging` angelegter One-off-Workflow kann zwar Push-Runs zeigen, aber keinen nutzbaren `Run workflow`-Button anbieten.
- Ein Actions-Run mit `GITHUB_TOKEN` und `contents: write` darf in diesem Repo keine Dateien unter `.github/workflows/` veraendern. Der Push wurde mit fehlender `workflows`-Permission abgelehnt.
- `permissions: contents: write` reicht fuer normale Repo-Dateien, aber nicht fuer das selbststaendige Erzeugen oder Aendern anderer Workflow-Dateien.

Zulaessige Wege fuer Workflow-Datei-Aenderungen:

1. GitHub-Connector direkt, wenn die Datei klein genug ist oder sicher vollstaendig ersetzt werden kann.
2. Lokale Arbeitskopie/Codespace mit normalem Git-Push durch den Nutzer.
3. Bewusstes Patch-Paket, wenn der Connector eine grosse Workflow-Datei nicht risikoarm ersetzen kann.
4. GitHub UI-Edit durch den Nutzer bei kleinen, klar beschriebenen Zeilenaenderungen.

Nicht zulaessig:

- One-off-Workflow, der `.github/workflows/*.yml` veraendert.
- Annahme, dass `workflow_dispatch` auf einem nur in `staging` existierenden Workflow manuell per Button startbar ist.
- Annahme, dass `GITHUB_TOKEN` mit `contents: write` Workflow-Dateien editieren darf.

## Validierung nach Aenderungen

Sinnvoller Test fuer den stabilen PR-Gate:

1. Kleinen PR nach `staging` oeffnen, der die Gate-/Governance-Dateien beruehrt.
2. Erwartung: `Project Guardrails` und `PR Gate` laufen.
3. Erwartung: `PR Gate` wartet auf `Project Guardrails` und wird erst danach gruen.
4. Einen kontrollierten PR nach `main` aus einem anderen Branch als `staging` vermeiden; die statische Source-Policy ist bereits im Gate und im Review zu pruefen.

Sinnvoller Test fuer den schnellen Staging-Pfad:

1. Kleiner Commit auf `staging`, der keine Content-/Audit-Datenpfade beruehrt.
2. Erwartung: `Deploy to STRATO` laeuft.
3. Nicht erwartet: Growth-, KI-, Inbox-Cleanup-, Content-Ops-Ingest- oder `PR Gate`-Run nur wegen Push.

Sinnvoller separater Test fuer Qualitaetssicherung:

1. `Content Quality Audit` manuell per `workflow_dispatch` ausloesen.
2. Danach pruefen, ob Content-Ops-Artefakte und ggf. HTTP-Ingest wie erwartet laufen.
