# GitHub Actions Trigger Policy

## Ziel

GitHub Actions unterstützt die Produktarbeit, ohne eine zweite Prozessarchitektur zu bilden. Es gibt genau einen Entwicklungscheck, einen Deployowner und nur fachlich notwendige Betriebsworkflows.

## Verbindlicher Workflowbestand

| Workflowdatei | Anzeigename | Aufgabe | Trigger | Klassifikation |
|---|---|---|---|---|
| `pr-gate.yml` | `PR Gate` | Branchpolicy sowie alle Syntax-, Unit- und Contracttests | jeder PR nach `staging` oder `main` | technischer Kernowner |
| `deploy-strato.yml` | `Deploy to STRATO` | Feed-Build, HTTP-/Browser-Smoke und einziger Staging-/Live-Deploy | Push nach `staging`/`main`, täglich, manuell | technischer Kernowner |
| `content-quality-audit.yml` | `Content Quality Audit` | fachliche Qualitätsprüfung der Inhalte | eigener Schedule, gezielter Main-Push oder manuell | fachlicher Betriebsowner |
| `growth-intelligence-backlog.yml` | `Growth Intelligence Backlog` | Growth-/SEO-Signale erzeugen | wöchentlich oder manuell | fachlicher Betriebsowner |
| `inbox-cleanup.yml` | `Inbox Operations` | freigegebene Live-Inboxzeilen importieren, finalisierte Zeilen archivieren und bei Import den Deploy anstoßen | täglich oder manuell | zusammengeführter fachlicher Betriebsowner |
| `weekly-ki-websearch-to-manual-inbox.yml` | `Weekly KI Websearch → Live Inbox` | Eventkandidaten suchen, validieren und direkt in die Live-Inbox übernehmen | wöchentlich oder manuell | zusammengeführter fachlicher Betriebsowner |
| `bathing-water-guard.yml` | `Bathing Water Guard V2` | saisonalen Badegewässerstatus prüfen und ausschließlich die generierte Statusdatei aktualisieren | Mai bis September täglich oder manuell | saisonaler fachlicher Betriebsowner |

Diese sieben Dateien bilden eine fail-closed Allowlist. `scripts/audit_github_workflows.py` wird über `scripts/validate-repo.sh` im bestehenden `PR Gate` ausgeführt. Jede zusätzliche oder fehlende Workflowdatei blockiert den PR.

## PR Gate

- stabiler Required-Check-Name: `PR Gate`;
- genau ein PR-Workflow;
- Feature-Branches zielen auf `staging`;
- nach `main` darf nur `staging` gemergt werden;
- `scripts/validate-repo.sh` ist der einzige Testeinstieg;
- keine Aggregation anderer Workflows und kein Polling auf weitere Checks;
- keine Pfadfilter: der Check startet zuverlässig bei jedem PR.

## Deploy

- `Deploy to STRATO` ist der einzige Deployowner.
- Nur `staging` und `main` werden akzeptiert.
- Ein Merge nach `staging` erzeugt genau einen Deploy.
- Der tägliche Main-Lauf bleibt nötig, weil die öffentlichen Eventdaten aus dem Google Sheet kommen.
- HTTP- und Browser-Smoke sind Bestandteile desselben Deploys; ein eigenständiger Smoke-Workflow ist ausgeschlossen.
- Technische Staging-Commits starten keine Content-, Inbox-, Growth-, KI- oder Badegewässerläufe.
- Zusätzliche Deployobserver, Statuspublisher und synthetische Folge-Deploys sind ausgeschlossen.

## Fachliche Betriebsworkflows

Diese Workflows bleiben getrennt, wenn sie reale fachliche Arbeit mit eigenem Trigger, eigenem Datenowner und eigenem Fehlerbild verrichten. Technische Zwischenhops werden dagegen in den fachlichen Owner integriert.

- `Weekly KI Websearch → Live Inbox` besitzt Suche und Intake gemeinsam; es gibt keinen zweiten Manual-Intake-Workflow.
- `Inbox Operations` besitzt Import und Archivierung gemeinsam; es gibt keinen separaten Inbox→Events-Workflow.
- `Bathing Water Guard V2` ist der einzige aktuelle Badegewässer-Workflow; frühere Proof-/Discovery-Workflows bleiben nur in Git-Historie und Dokumentation nachvollziehbar.

## Nicht dauerhafte Muster

Nicht im Repository behalten werden:

- ein eigener Governance-Workflow;
- ein zweiter statischer CI-Workflow;
- ein Workflow, der nur einen anderen Deploy beobachtet;
- `workflow_run`-Observer für reine Metrikweiterleitung;
- einmalige Operator-, Proof-, Discovery- oder Testworkflows;
- eigenständige Browser-Smokes neben dem Deployowner;
- technische Handoff-Workflows, deren Schritte im fachlichen Owner ausgeführt werden können;
- abgelaufene Bootstrap-Ausnahmen.

Ein temporärer Workflow muss im selben Workpack wieder entfernt werden. Eine neue dauerhafte Workflowdatei benötigt einen klaren fachlichen Owner, einen dauerhaft notwendigen Trigger und einen Nutzen, den keine bestehende Rolle abdecken kann. Zusätzlich muss die Allowlist bewusst angepasst werden.

## Permissions

- PR- und reine Prüfworkflows: `contents: read`.
- Schreibrechte nur für den konkreten fachlichen Workflow, der sie benötigt.
- Keine ungenutzten Datenbank-, Actions- oder Statusrechte.
- Direkte Repository-Commits sind nur zulässig, wenn die versionierte Datei selbst das notwendige fachliche Laufzeitartefakt ist und kein sicherer bestehender Owner sie übernehmen kann.

## Änderungsvorgehen

Workflowänderungen werden seriell in einem eigenen Scope durchgeführt. Vor dem Merge muss `PR Gate` grün sein. Nach dem Merge wird nur der normale Deploy geprüft; eine weitere Verification-Pipeline wird nicht gestartet.
