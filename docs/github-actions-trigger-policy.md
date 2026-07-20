# GitHub Actions Trigger Policy

Stand: 2026-07-20  
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
| `Control Center E4 Synthetic Proof` | isolierter R3-Write-/Resume-/Cleanup-Beweis | nein | nein | nein | nein | ausschließlich auf `main`, manuell, bestätigt und SHA-gebunden |
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

Ein enger, ausdrücklich dokumentierter Default-Branch-Operatoranker kann nur dann separat nach `main` integriert werden, wenn der auf `main` tatsächlich geltende Ruleset- und Checkzustand diesen eng begrenzten Workflow-Pfad zulässt. Regeln werden dafür nicht deaktiviert oder umgangen. Ist der Pfad technisch blockiert, stoppt der R3-Workpack vor E4.

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
4. ruft den bestehenden authentifizierten Preflight ausschließlich mit `{ "mode": "runtime" }` auf;
5. liest keine Case-Liste und sendet weder `case_id` noch `action`;
6. prüft Scope `runtime_and_resource_contract_only` und `mutation=false`;
7. validiert Staging-Host, Build, Manifest und Umgebung;
8. validiert `Inbox_Staging`-Schema und `Events_Staging`-Schema read-only, ohne fachliche Zeilendaten zurückzugeben;
9. löst den erwarteten Staging-Writer auf, ruft ihn aber nicht auf;
10. weist Live-Inbox und Live-Events als `not_used` aus;
11. veröffentlicht weiterhin `control-center/runtime-preflight-e3`;
12. lädt ein gemeinsames Evidence-Artefakt hoch;
13. besitzt keine Sheet- oder DB-Credentials.

Die beiden Statuskontexte bleiben unverändert, bis eine nachweislich koordinierte Ruleset-Anpassung erfolgt.

Ein manueller `workflow_dispatch` wird nicht vorgetäuscht: Ein Dispatch-Workflow ist nur zuverlässig bedienbar, wenn seine Datei auf dem Default-Branch vorhanden ist. Für Staging-E3 genügt der normale Integrations-Push; ein bestehender Run kann über GitHub erneut ausgeführt werden.

## E4-Operator- und Sicherheitsvertrag

`Control Center E4 Synthetic Proof` bleibt getrennt, weil der Workflow:

- reale Staging-Write-Secrets benötigt;
- eine andere Risikoklasse besitzt;
- Write, Resume, Rücklesen und Cleanup ausführt;
- ausschließlich in einem separaten R3-Workpack freigeschaltet werden darf.

Der Workflow besitzt absichtlich genau einen manuellen Operatorpfad:

1. Die Workflowdatei liegt als enger Operatoranker auf dem Default-Branch `main`.
2. Der Run wird ausschließlich auf `main` gestartet.
3. Pflichtinput `expected_staging_sha` ist der exakte aktuelle 40-stellige `staging`-SHA.
4. Pflichtinput `confirmation` ist exakt `RUN_ONE_SYNTHETIC_E4`.
5. Der Job checkt nicht Main-Runtime aus, sondern genau den angegebenen Staging-Commit.
6. Vor jedem Write wird der Remote-Head von `staging` erneut gegen den Input geprüft.
7. Bei abweichendem SHA, falscher Bestätigung oder falschem Ref bleibt der Job fail-closed.
8. Der Operatoranker besitzt keinen Push-, Schedule-, `workflow_run`- oder automatischen Dispatch-Trigger.
9. Ein erfolgreicher Lauf berechtigt nicht zu einem weiteren Lauf. Jeder weitere E4 benötigt einen neuen ausdrücklich aktiven R3-Workpack.

Der Main-Operatoranker ist kein Live-Release. Er enthält ausschließlich die Workflowdatei; Harness und ausführbarer Code werden aus dem SHA-gebundenen Staging-Commit ausgecheckt. Ein breiter `staging -> main`-Merge ist dafür weder erforderlich noch zulässig.

Der E4-Beweis ist vollständig synthetisch und fachfallfrei:

- keine Auswahl oder Abhängigkeit von CityArt oder anderen realen Fachfällen;
- keine vorhandene reale Zeile als Sentinel;
- vor dem Write müssen Sheets, Staging-DB und Feeds frei von jedem synthetischen E4-Marker sein;
- Live-Sheets und Live-Feed werden ausschließlich read-only verglichen;
- jede begonnene synthetische Mutation führt zwingend in einen Cleanup-Versuch;
- Cleanup-Fehler machen den Lauf rot;
- nach einem roten E4 gibt es keinen zweiten Lauf.

`Staging Verification` darf E4 weder aufrufen noch dispatchen. Ein E4-Self-Test im `Control Center CI` besitzt keinen externen Zugriff.

## Gewollter schneller Staging-Pfad

Ein normaler relevanter Merge nach `staging` löst aus:

1. `Deploy to STRATO`;
2. bei Control-Center-/Verification-Scope genau einmal `Staging Verification`;
3. gegebenenfalls `Project Guardrails` bei Governance-Scope.

Nicht gewollt allein wegen eines technischen Staging-Pushes:

- `Control Center CI`;
- `Control Center E4 Synthetic Proof`;
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

Der E4-Workflow erhält ausschließlich:

- `contents: read` für den SHA-gebundenen Checkout;
- `actions: write` für die zwei kontrollierten Staging-Feed-Deploys.

Breite Repository-Default-Permissions dürfen nicht stillschweigend für Testworkflows verwendet werden.

## Workflowänderungen

Bei jeder Änderung prüfen:

- `pull_request.branches` und `paths`;
- `push.branches` und `paths`;
- `schedule`;
- `workflow_dispatch` und Inputs;
- `workflow_run`;
- job-level `if`;
- Permissions und Secrets;
- Artefakte;
- Job-, Check- und Commitstatusnamen;
- Downstream-Dispatches;
- Vorkommen der Datei auf `main` und `staging`;
- Ruleset-Abhängigkeiten;
- tatsächlichen Operatorpfad;
- SHA-Bindung und Race-Condition vor dem ersten Write;
- Cleanup-Verhalten nach Branchbewegung oder Teilfehler.

`Project Guardrails` muss bei jeder Änderung unter `.github/workflows/**` starten und die autoritative Topologie einschließlich fachfallfreiem Runtime-E3 und manuell bestätigtem, SHA-gebundenem E4 prüfen.

## Verbotene Muster

- selbstmodifizierender One-off-Workflow für `.github/workflows/**`;
- zusätzliche Observer-/Wrapper-Schicht ohne belegten Mehrwert;
- staging-only `workflow_dispatch` als angeblich sicher bedienbarer Operatorpfad;
- E4-Push-, Schedule-, `workflow_run`- oder automatischer Dispatch-Trigger;
- E4 ohne vollständigen erwarteten Staging-SHA und exakte Bestätigung;
- E4-Checkout von `main`, einem beweglichen Branch oder einem ungeprüften Ref;
- E4-Ausführung nach zwischenzeitlicher Bewegung von `staging`;
- CityArt oder ein anderer realer Fachfall als technische E4-Abhängigkeit oder Sentinel;
- Umbenennung von `PR Gate`, `deploy/staging-observed` oder `control-center/runtime-preflight-e3` ohne koordinierte Ruleset-Prüfung;
- Entfernen einer Qualitätsprüfung ohne expliziten Ersatz;
- E4-Aufruf aus der read-only Staging Verification;
- Auswahl eines echten Fachfalls als technisches E3-Abnahmeobjekt;
- Ableitung einer fachlichen Aktionsfreigabe aus dem technischen E3-Status.

## Validierung nach Änderungen

### E2

1. Draft-PR nach `staging`.
2. `Project Guardrails`, `Control Center CI` und `PR Gate` grün.
3. E4-Harness-Syntax und Self-Test grün.
4. Workflow enthält ausschließlich manuellen `workflow_dispatch`, Pflichtinputs, Bestätigung, SHA-Checkout und aktuellen Staging-Head-Guard.
5. Workflow und Harness enthalten keinen CityArt-Bezug.
6. Keine alten `diagnostics`-, `contracts`- oder duplizierten Control-Center-`validate`-Top-Level-Checks.
7. PR mergebar und nicht hinter `staging`.

### E3

1. Merge nach `staging`.
2. `Deploy to STRATO` grün.
3. `deploy/staging-observed=success`.
4. `control-center/runtime-preflight-e3=success`.
5. Scope `runtime_and_resource_contract_only` und `no_fachfall=true`.
6. Build, Host und Staging-Environment bestätigt.
7. `Inbox_Staging`- und `Events_Staging`-Schema gültig.
8. Writer aufgelöst, aber nicht aufgerufen.
9. `mutation=false`; Live-Ressourcen unbenutzt.
10. Noch kein E4-Run.

### E4

1. Identische Workflowdatei als enger Operatoranker auf `main` vorhanden.
2. Genau ein manueller Run mit finalem aktuellem `staging`-SHA und exakter Bestätigung.
3. Success-Write und idempotenter Replay bestätigt.
4. Kontrollierter Teilfehler fail-closed und Wiederaufnahme ohne Duplikat bestätigt.
5. Beide synthetischen IDs genau einmal im Staging-Feed, niemals im Live-Feed.
6. Synthetische Sheet- und DB-Datensätze vollständig entfernt.
7. Bereinigter Staging-Feed frei von synthetischen Markern.
8. Nicht-Testdaten und Live-Ressourcen unverändert.
9. Evidence-Artefakt `result=success`, keine Cleanup-Fehler.
10. Kein zweiter E4-Lauf.
