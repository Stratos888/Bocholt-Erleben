# GitHub Actions Trigger Policy

Stand: 2026-07-07
Branch: `staging`

## Ziel

Staging soll wieder als schneller technischer Testpfad funktionieren. Kleine Commits auf `staging` sollen primaer den notwendigen Staging-Deploy ausloesen, aber keine schweren Content-, Growth-, KI- oder Audit-Laeufe.

Diese Entkopplung ist gewollt und kein Fehler. Sie senkt nicht die Produktqualitaet, sondern trennt schnelle technische Iteration von periodischer und produktiver Qualitaetssicherung.

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

### Bekannter verbleibender Haertungspunkt

- `.github/workflows/content-quality-audit.yml`
  - hat aktuell noch einen `push`-Trigger auf `staging` mit breiten Pfaden, insbesondere `data/**`.
  - Das ist fuer den finalen schnellen Staging-Zielzustand noch nicht optimal.
  - Gewollter Zielzustand: kein pauschaler schwerer Audit bei normalen `staging`-Testcommits.
  - Kuenftige Härtung: `push` dort auf `main` und/oder sehr gezielte Audit-Owner-Dateien begrenzen; schedule und manual beibehalten.

## Main-Schutz

Die Entkopplung betrifft nur schnelle Staging-Iteration. Vor oder nach produktiven Aenderungen duerfen schwere Qualitaetslaeufe weiterhin laufen:

- per `schedule`,
- per `workflow_dispatch`,
- auf `main`,
- oder bei bewusst relevanten Pfadaenderungen.

Wichtig: Keine Qualitaetspruefung entfernen, sondern Ausloesezeitpunkt sauberer steuern.

## Arbeitsregel fuer zukuenftige KI-Chats

Wenn ein Workflow schwer ist und nicht unmittelbar Deploybruch verhindert, darf er nicht wieder pauschal an jeden `staging`-Push gekoppelt werden.

Bei Workflow-Aenderungen immer konkret pruefen:

- `on.push.branches`,
- `on.push.paths`,
- `schedule`,
- `workflow_dispatch`,
- `workflow_run`,
- job-level `if`,
- Downstream-Dispatches und Artefakt-Abhaengigkeiten.

Neue oder geaenderte Workflows muessen in diese Policy passen oder die Abweichung explizit begruenden.

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

Sinnvoller Test fuer den schnellen Staging-Pfad:

1. Kleiner Commit auf `staging`, der keine Content-/Audit-Datenpfade beruehrt.
2. Erwartung: `Deploy to STRATO` laeuft.
3. Nicht erwartet: Growth-, KI-, Inbox-Cleanup- oder Content-Ops-Ingest-Run nur wegen Push.

Sinnvoller separater Test fuer Qualitaetssicherung:

1. `Content Quality Audit` manuell per `workflow_dispatch` ausloesen.
2. Danach pruefen, ob Content-Ops-Artefakte und ggf. HTTP-Ingest wie erwartet laufen.
