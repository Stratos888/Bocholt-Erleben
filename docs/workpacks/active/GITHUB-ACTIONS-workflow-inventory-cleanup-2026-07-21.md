# GitHub Actions – Vollinventur und Workflow-Cleanup

Stand: 2026-07-21

## Ausgangslage

Die vollständige Git-Bauminventur ergab zwölf aktive Workflowdateien unter `.github/workflows`. Die kanonische Triggerpolicy führte nur sieben davon auf. Mehrere Dateien waren abgeschlossene Proofs, doppelte technische Pfade oder eigenständige Zwischenhops ohne dauerhaften Owner.

## Zielzustand

- genau ein PR-Check;
- genau ein Deployowner einschließlich HTTP- und Browser-Smoke;
- fachliche Betriebsworkflows nur bei eigenem Datenowner und dauerhaftem Trigger;
- technische Zwischenhops in den fachlichen Owner integrieren;
- exakte fail-closed Workflow-Allowlist im bestehenden `PR Gate`;
- keine produktiven externen Schreibtests während des Cleanup-Workpacks.

## Vollständige Klassifikation des Ausgangsbestands

| Ausgangsdatei | Ausgangsrolle | Urteil | Zielzustand |
|---|---|---|---|
| `pr-gate.yml` | zentraler PR-Check | behalten | unveränderter einziger PR-Workflow; prüft zusätzlich die Workflow-Allowlist über `scripts/validate-repo.sh` |
| `deploy-strato.yml` | Build, Deploy, HTTP- und Browser-Smoke | behalten | einziger Deployowner; Standalone-Browser-Smoke entfällt |
| `content-quality-audit.yml` | Contentprüfung und Lernsignale | behalten | eigenständiger fachlicher Betriebsowner |
| `growth-intelligence-backlog.yml` | Growth-/SEO-Signale | behalten | eigenständiger fachlicher Betriebsowner |
| `inbox-cleanup.yml` | Archivierung finalisierter Inboxzeilen | zusammenführen | wird zu `Inbox Operations` und übernimmt zusätzlich den freigegebenen Live-Import |
| `inbox-to-events.yml` | freigegebene Inboxzeilen nach Events importieren | löschen nach Integration | Schritte liegen vollständig im Workflow `Inbox Operations` |
| `weekly-ki-websearch-to-manual-inbox.yml` | KI-Suche, Repo-Puffer, Dispatch zum Intake | zusammenführen und härten | Suche und Intake laufen in einem Workflow; kein Repo-Commit und kein Dispatch-Token-Handoff mehr |
| `manual-ki-intake.yml` | Repo-Puffer in Live-Inbox übernehmen | löschen nach Integration | Intake liegt vollständig im Weekly-KI-Owner |
| `browser-smoke.yml` | manueller Browser-Smoke | löschen | Browser-Smoke ist bereits Bestandteil des einzigen Deployowners |
| `bathing-water-guard-v1.yml` | produktiver Guard V2 mit altem Dateinamen | behalten und umbenennen | `bathing-water-guard.yml` als einziger saisonaler Fachowner; nur Schedule/Manual auf `main` |
| `bathing-water-source-discovery-v2.yml` | abgeschlossener Discovery-Proof | löschen | Erkenntnisse sind im produktiven Guard und in Git-Historie erhalten |
| `bathing-water-status-proof.yml` | überholter Machbarkeits-Proof | löschen | Erkenntnisse sind im produktiven Guard und in Git-Historie erhalten |

## Endgültiger Workflowbestand

1. `.github/workflows/pr-gate.yml`
2. `.github/workflows/deploy-strato.yml`
3. `.github/workflows/content-quality-audit.yml`
4. `.github/workflows/growth-intelligence-backlog.yml`
5. `.github/workflows/inbox-cleanup.yml` – Anzeigename `Inbox Operations`
6. `.github/workflows/weekly-ki-websearch-to-manual-inbox.yml` – Anzeigename `Weekly KI Websearch → Live Inbox`
7. `.github/workflows/bathing-water-guard.yml`

## Sicherheitswirkung

- Workflowbestand von zwölf auf sieben reduziert.
- Zwei abgeschlossene Proof-Workflows entfernt.
- Standalone-Browser-Smoke entfernt.
- Zwei technische Handoff-Workflows in ihre fachlichen Owner integriert.
- Weekly-KI benötigt kein `contents: write` und keinen Workflow-Dispatch-Token mehr.
- Der saisonale Badegewässer-Writer wird nicht mehr durch Code-Pushes ausgelöst und ist auf `main` begrenzt.
- Jede künftige zusätzliche oder fehlende Workflowdatei blockiert den bestehenden `PR Gate`.

## Bewusste Abgrenzung

Der Badegewässer-Guard wurde nicht in den Deploy integriert. Ein allgemeiner Release darf nicht von externen Badegewässerquellen abhängig werden. Der Guard besitzt ein eigenes saisonales Fehlerbild und bleibt deshalb ein separater fachlicher Betriebsowner.

## Validierung

- vollständige Inventur über den Git-Baum des PR-Head-SHA;
- `scripts/audit_github_workflows.py` mit exakter Siebener-Allowlist;
- Audit in `scripts/validate-repo.sh` und damit im bestehenden `PR Gate`;
- keine temporäre Inventuraktion bleibt im finalen `PR Gate`;
- keine produktiven Workflow-Dispatches oder Sheet-Schreibtests im Feature-Branch.

## Abschlusskriterium

Der Workpack ist abgeschlossen, wenn der finale `PR Gate` grün ist, der Merge nach `staging` genau den normalen Staging-Deploy auslöst und `CURRENT_WORKPACK.md` wieder den nächsten fachlichen Workpack ausweist.
