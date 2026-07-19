# GitHub Actions Trigger Policy

Stand: 2026-07-19  
Branch: `staging`

## Ziel

GitHub Actions trennt Integrationsprüfung, Deploy, read-only Runtime-Evidence und schreibende Betriebsautomation. Jede Prüfung besitzt genau einen autoritativen Owner; kleine Staging-Commits lösen keine schweren Content-, Growth-, KI- oder Audit-Läufe aus.

## Autoritative Topologie

| Workflow | Owner | PR | `staging` Push | `main` Push | Schedule | Manual |
|---|---|---:|---:|---:|---:|---:|
| `PR Gate` | Always-run-Integration und Branchpolicy | ja, `staging`/`main` | nein | nein | nein | nein |
| `Project Guardrails` | Architektur-, Dokumentations- und Workflowtopologie | pfadspezifisch | pfadspezifisch | pfadspezifisch | nein | ja |
| `Control Center CI` | vollständige Control-Center-E1/E2-Prüfung | pfadspezifisch | nein | nein | nein | ja, sobald Datei auf Default-Branch vorhanden |
| `Deploy to STRATO` | einziger Deploypfad | nein | ja | ja | ja | ja |
| `Staging Verification` | kombinierte Deploy-/Build-/read-only-E3-Evidence | nein | pfadspezifisch | nein | nein | nein |
| `Control Center E4 Synthetic Proof` | isolierter R3-Write-/Resume-/Cleanup-Beweis | nein | nein | nein | nein | gesperrt; erst nach eigenem R3-Workpack |
| Content-/Growth-/Inbox-/KI-/Content-Ops-Workflows | fachlicher Betrieb | nein | nein | nur wo ausdrücklich definiert | ja | ja |

## Always-run PR Gate

`.github/workflows/pr-gate.yml` erzeugt bei jedem Pull Request nach `staging` oder `main` den stabilen Check:

```text
PR Gate
```

Der Gate-Workflow:

1. erlaubt PRs nach `staging` nur aus demselben Repository;
2. erlaubt PRs nach `main` ausschließlich als `staging -> main`;
3. wartet nur auf GitHub-Actions-Checks, die für den konkreten PR tatsächlich gestartet wurden;
4. dupliziert keine PHP-, JavaScript-, Python-, Browser- oder Produktprüfung;
5. wird rot, sobald ein gestarteter Check fehlschlägt, abbricht oder zeitlich ausläuft;
6. besitzt keinen `paths`-Filter und darf nicht umbenannt werden, solange ein Ruleset davon abhängt.

## Control Center CI

`.github/workflows/control-center-ci.yml` ist der einzige autoritative statische Control-Center-Testworkflow.

Er enthält einmalig:

- JSON- und PHP-Syntax;
- vollständige Control-Center-Contracttests;
- Frontend-/JavaScript-Prüfungen;
- Produkt- und Editorial-Audits;
- CSS-Governance;
- konsolidierte Dateiarchitektur;
- Security-/Integrationsguards;
- den rein lokalen E4-Harness-Self-Test.

Er läuft auf relevanten Pull Requests, nicht erneut bei jedem Merge-Push. Alte Teilworkflows dürfen nicht wieder eingeführt werden.

Stabiler Job-/Checkname:

```text
Control Center CI
```

## Staging Verification

`.github/workflows/staging-verification.yml` ist der einzige zusätzliche read-only Runtime-Verifikationspfad für relevante `staging`-Pushes.

Er:

1. beobachtet den passenden `Deploy to STRATO`-Run;
2. veröffentlicht weiterhin `deploy/staging-observed`;
3. wartet auf den passenden Build;
4. führt den read-only Runtime-Preflight mit `mutation=false` aus;
5. veröffentlicht weiterhin `control-center/runtime-preflight-e3`;
6. lädt ein gemeinsames Evidence-Artefakt hoch;
7. besitzt keine Sheet- oder DB-Credentials.

Die beiden Statuskontexte bleiben unverändert, bis eine nachweislich koordinierte Ruleset-Anpassung erfolgt.

Ein manueller `workflow_dispatch` wird nicht vorgetäuscht: Ein Dispatch-Workflow ist nur zuverlässig bedienbar, wenn seine Datei auf dem Default-Branch vorhanden ist. Für Staging-E3 genügt der normale Integrations-Push; ein bestehender Run kann über GitHub erneut ausgeführt werden.

## E4-Grenze

`Control Center E4 Synthetic Proof` bleibt getrennt, weil der Workflow:

- reale Staging-Write-Secrets benötigt;
- eine andere Risikoklasse besitzt;
- Write, Resume, Rücklesen und Cleanup ausführt;
- ausschließlich in einem separaten R3-Workpack freigeschaltet werden darf.

`Staging Verification` darf E4 weder aufrufen noch dispatchen. Ein E4-Self-Test im `Control Center CI` besitzt keinen externen Zugriff.

## Gewollter schneller Staging-Pfad

Ein normaler relevanter Merge nach `staging` löst aus:

1. `Deploy to STRATO`;
2. bei Control-Center-/Verification-Scope genau einmal `Staging Verification`;
3. gegebenenfalls `Project Guardrails` bei Governance-Scope.

Nicht gewollt allein wegen eines technischen Staging-Pushes:

- `Control Center CI`;
- `Content Quality Audit`;
- `Growth Intelligence Backlog`;
- `Weekly KI Websearch → Manual Inbox`;
- `Manual KI Event Intake`;
- `Inbox Cleanup (Archive)`;
- `Content Ops HTTP Ingest`;
- `PR Gate`.

## Fachliche Betriebsworkflows

### Content Quality Audit

- manual, schedule und gezielter `main`-Push;
- kein normaler `staging`-Push;
- Audit-/Feedback-/Cache-Schreiblogik bleibt fachlich getrennt.

### Growth Intelligence Backlog

- nur manual oder schedule;
- kein Push.

### Weekly KI und Manual Intake

- echter Lauf nur auf `main`;
- Staging-Starts brechen fail-closed ab;
- produktive Repo-/Sheet-/Dispatch-Schreibrechte bleiben ausschließlich in diesen fachlichen Workflows.

### Inbox Cleanup

- manual oder schedule;
- Staging-/Live-Tabs werden anhand des Refs getrennt.

### Content Ops HTTP Ingest

- manual oder `workflow_run` nach definierten Quellworkflows;
- kein Push.

## Permissions

Jeder read-only Workflow setzt minimale Rechte explizit:

- `PR Gate`: `contents/checks/pull-requests: read`;
- `Project Guardrails`: `contents: read`;
- `Control Center CI`: `contents: read`;
- `Staging Verification`: `actions: read`, `contents: read`, `statuses: write`.

Breite Repository-Default-Permissions dürfen nicht stillschweigend für Testworkflows verwendet werden.

## Workflowänderungen

Bei jeder Änderung prüfen:

- `pull_request.branches` und `paths`;
- `push.branches` und `paths`;
- `schedule`;
- `workflow_dispatch`;
- `workflow_run`;
- job-level `if`;
- Permissions und Secrets;
- Artefakte;
- Job-, Check- und Commitstatusnamen;
- Downstream-Dispatches;
- Vorkommen der Datei auf `main` und `staging`;
- Ruleset-Abhängigkeiten;
- tatsächlichen Operatorpfad.

`Project Guardrails` muss bei jeder Änderung unter `.github/workflows/**` starten und die autoritative Topologie prüfen.

## Verbotene Muster

- selbstmodifizierender One-off-Workflow für `.github/workflows/**`;
- zusätzliche Observer-/Wrapper-Schicht ohne belegten Mehrwert;
- staging-only `workflow_dispatch` als angeblich sicher bedienbarer Operatorpfad;
- Umbenennung von `PR Gate`, `deploy/staging-observed` oder `control-center/runtime-preflight-e3` ohne koordinierte Ruleset-Prüfung;
- Entfernen einer Qualitätsprüfung ohne expliziten Ersatz;
- E4-Aufruf aus der read-only Staging Verification.

## Validierung nach Änderungen

### E2

1. Draft-PR nach `staging`.
2. `Project Guardrails`, `Control Center CI` und `PR Gate` grün.
3. Keine alten `diagnostics`-, `contracts`- oder duplizierten Control-Center-`validate`-Top-Level-Checks.
4. PR mergebar.

### E3

1. Merge nach `staging`.
2. `Deploy to STRATO` grün.
3. `deploy/staging-observed=success`.
4. `control-center/runtime-preflight-e3=success`.
5. Build, Host, Umgebung und `Inbox_Staging -> Events_Staging` bestätigt.
6. `mutation=false`; Live-Ressourcen unbenutzt.
7. Kein E4-Run.
