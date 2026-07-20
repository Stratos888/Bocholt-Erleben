# GitHub Actions Trigger Policy

## Ziel

GitHub Actions unterstützt die Produktarbeit, ohne eine zweite Prozessarchitektur zu bilden. Es gibt einen Entwicklungscheck, einen Deploypfad und nur fachlich arbeitende Betriebsworkflows.

## Dauerhafte Workflows

| Workflow | Aufgabe | Trigger |
|---|---|---|
| `PR Gate` | Branchpolicy sowie alle lokalen Syntax-, Unit- und Contracttests | jeder PR nach `staging` oder `main` |
| `Deploy to STRATO` | Feed-Build und einziger Staging-/Live-Deploy | Push nach `staging`/`main`, täglich, manuell |
| `Content Quality Audit` | fachliche Qualitätsprüfung der Inhalte | eigener Schedule oder manuell |
| `Inbox Cleanup (Archive)` | abgeschlossene Inboxzeilen archivieren | eigener Schedule oder manuell |
| `Weekly KI Websearch → Manual Inbox` | neue Eventkandidaten suchen | wöchentlich oder manuell |
| `Manual KI Event Intake` | Kandidaten in die Live-Inbox übernehmen | manuell bzw. durch den Weekly-Handoff |

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
- Technische Staging-Commits starten keine Content-, Inbox-, Growth- oder KI-Läufe.
- Zusätzliche Deployobserver, Statuspublisher und synthetische Folge-Deploys sind ausgeschlossen.

## Fachliche Betriebsworkflows

Diese Workflows bleiben getrennt, weil sie reale fachliche Arbeit verrichten und unabhängig vom Entwicklungsprozess laufen. Sie dürfen nicht als allgemeine CI-, Monitoring- oder Governance-Schicht verwendet werden.

## Nicht dauerhafte Muster

Nicht im Repository behalten werden:

- ein eigener Governance-Workflow;
- ein zweiter statischer CI-Workflow;
- ein Workflow, der nur einen anderen Deploy beobachtet;
- `workflow_run`-Observer für reine Metrikweiterleitung;
- einmalige E3-/E4-Operator- oder Testworkflows;
- abgelaufene Bootstrap-Ausnahmen;
- Workflows für derzeit nicht aktive Produktprogramme.

Ein temporärer Workflow muss im selben Workpack wieder entfernt werden. Eine neue dauerhafte Workflowdatei benötigt einen klaren fachlichen Owner, einen dauerhaft notwendigen Trigger und einen Nutzen, den keine bestehende Rolle abdecken kann.

## Permissions

- PR- und reine Prüfworkflows: `contents: read`.
- Schreibrechte nur für den konkreten fachlichen Workflow, der sie benötigt.
- Keine ungenutzten Datenbank-, Actions- oder Statusrechte.

## Änderungsvorgehen

Workflowänderungen werden seriell in einem eigenen Scope durchgeführt. Vor dem Merge muss `PR Gate` grün sein. Nach dem Merge wird nur der normale Deploy geprüft; eine weitere Verification-Pipeline wird nicht gestartet.
