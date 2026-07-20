# GitHub Actions Trigger Policy

Stand: 2026-07-20  
Branch: `staging`

## Ziel

GitHub Actions trennt Integrationsprüfung, Deploy, read-only Runtime-Evidence und schreibende Betriebsautomation. Jede Prüfung besitzt genau einen autoritativen Owner. Technische Staging-Commits lösen keine unnötigen Content-, Growth-, KI- oder Auditläufe aus.

## Autoritative Topologie

| Workflow | Zweck | Normaler Trigger |
|---|---|---|
| `PR Gate` | Always-run-Integration und Branchpolicy | Pull Requests nach `staging` oder `main` |
| `Project Guardrails` | Architektur-, Dokumentations- und Workflow-Governance | pfadspezifische PRs/Pushes |
| `Control Center CI` | vollständige Control-Center-E1/E2-Prüfung | relevante PRs |
| `Deploy to STRATO` | einziger Deploypfad | Push nach `staging`/`main`, Schedule, manuell |
| `Staging Verification` | kombinierte Deploy-/Build-/read-only-E3-Evidence | relevante `staging`-Pushes |
| `Control Center E4 Synthetic Proof` | isolierter R3-Write-/Resume-/Cleanup-Beweis | ausschließlich manuell auf `main` |
| Content-/Growth-/Inbox-/KI-/Content-Ops-Workflows | fachlicher Betrieb | nur ihre ausdrücklich definierten Trigger |

## PR Gate

`.github/workflows/pr-gate.yml` erzeugt den stabilen Check:

```text
PR Gate
```

Dauerhafter Standard:

1. PRs nach `staging` stammen aus demselben Repository.
2. Releases nach `main` erfolgen ausschließlich als `staging -> main`.
3. Das Gate wartet nur auf GitHub-Actions-Checks, die für den konkreten PR tatsächlich gestartet wurden.
4. Es dupliziert keine fachlichen Tests.
5. Es besitzt keinen `paths`-Filter und wird nicht umbenannt, solange ein Ruleset davon abhängt.

### Einmaliger E4-Operator-Bootstrap

Für den aktuell aktiven R3-Workpack existiert genau eine automatisch ablaufende Ausnahme, weil `main` den Required Check `PR Gate` verlangt, die Workflowdatei dort aber noch nicht vorhanden ist.

Die Ausnahme ist nur gültig, wenn gleichzeitig gilt:

- Zielbranch: `main`;
- Headbranch: exakt `agent/e4-default-branch-anchor`;
- Base-SHA: exakt `3d9e3cd6707eb20b0b9bece0a2601df2d92a888f`;
- geänderte Dateien: exakt
  - `.github/workflows/pr-gate.yml`;
  - `.github/workflows/control-center-e4-synthetic.yml`.

Nach dem Merge ändert sich der Main-SHA. Die Ausnahme kann danach nicht erneut greifen und bleibt als historisch nachvollziehbarer, technisch abgelaufener Bootstrapvertrag im Gate sichtbar.

Dieser Bootstrap ist kein Ruleset-Bypass: Der Required Check wird real ausgeführt und prüft Branch, Base-SHA und Diff. Jede Abweichung bleibt rot.

## Control Center CI

`.github/workflows/control-center-ci.yml` ist der einzige autoritative statische Control-Center-Testworkflow. Er enthält einmalig:

- JSON- und PHP-Syntax;
- vollständige Control-Center-Contracttests;
- Frontend-, Produkt-, Editorial- und CSS-Prüfungen;
- Architektur-, Security- und Integrationsguards;
- den rein lokalen E4-Harness-Self-Test.

Stabiler Job-/Checkname:

```text
Control Center CI
```

## Staging Verification

`.github/workflows/staging-verification.yml` ist der einzige zusätzliche read-only Runtime-Verifikationspfad für relevante `staging`-Pushes.

Er:

1. beobachtet den passenden `Deploy to STRATO`-Run;
2. veröffentlicht `deploy/staging-observed`;
3. bestätigt den passenden Build;
4. ruft den Preflight ausschließlich mit `{ "mode": "runtime" }` auf;
5. liest keine Case-Liste und sendet weder `case_id` noch `action`;
6. prüft `runtime_and_resource_contract_only`, `no_fachfall=true` und `mutation=false`;
7. validiert Host, Build, Manifest, Environment und beide Staging-Schemas;
8. löst den Writer auf, ruft ihn aber nicht auf;
9. weist Live-Ressourcen als `not_used` aus;
10. veröffentlicht `control-center/runtime-preflight-e3` und ein Evidence-Artefakt;
11. besitzt keine Sheet- oder DB-Credentials.

`Staging Verification` darf E4 weder aufrufen noch dispatchen.

## E4-Operatorvertrag

`Control Center E4 Synthetic Proof` bleibt getrennt, weil der Workflow reale Staging-Write-Secrets benötigt und eine andere Risikoklasse besitzt.

Der Operatorpfad:

1. Die Workflowdatei liegt auf dem Default-Branch `main`.
2. Der Run wird ausschließlich manuell auf `main` gestartet.
3. Pflichtinput `expected_staging_sha` ist der exakte aktuelle 40-stellige `staging`-SHA.
4. Pflichtinput `confirmation` ist exakt `RUN_ONE_SYNTHETIC_E4`.
5. Der Job checkt genau den angegebenen Staging-Commit aus.
6. Vor jedem Write wird der Remote-Head von `staging` erneut gegen den Input geprüft.
7. Bei falschem Ref, falscher Bestätigung oder bewegtem SHA bleibt der Job fail-closed.
8. Es gibt keinen Push-, Schedule-, `workflow_run`- oder automatischen E4-Trigger.
9. Jeder weitere E4-Lauf benötigt einen neuen ausdrücklich aktiven R3-Workpack.

Der Main-Operatoranker ist kein Live-Release. Harness und ausführbarer Code kommen aus dem SHA-gebundenen Staging-Commit.

### Merge ohne Live-Deploy

Der auf `main` vorhandene Workflow `Deploy to STRATO` reagiert auf jeden Main-Push. Deshalb wird der einmalige Operator-Bootstrap-Squash-Commit mit der offiziellen GitHub-Skip-Anweisung `[skip ci]` erstellt.

Damit gilt für genau diesen Commit:

- `push`- und `pull_request`-Workflows werden nicht ausgelöst;
- insbesondere findet kein Live-Deploy statt;
- kein Workflow, Ruleset oder Required Check wird deaktiviert;
- der `PR Gate`-Required-Check muss bereits vor dem Merge real grün sein;
- der neu installierte E4-Workflow bleibt inaktiv, weil er nur `workflow_dispatch` besitzt.

Ein Merge ohne die Skip-Anweisung ist für diesen Bootstrap unzulässig.

## Fachfallfreier E4-Beweis

Der E4-Harness:

- verwendet ausschließlich synthetische Identitäten;
- verwendet weder CityArt noch einen anderen realen Fachfall als Prüfobjekt oder Sentinel;
- blockiert bei vorbestehenden synthetischen Resten in Sheets, DB oder Feeds;
- mutiert nur `Inbox_Staging`, `Events_Staging` und synthetische Staging-DB-Datensätze;
- liest `Inbox`, `Events` und Live-Feed nur für Unverändert-Nachweise;
- beweist Success-Write, Replay, kontrollierten Teilfehler, Resume und Staging-Feed;
- versucht nach jeder begonnenen Mutation Cleanup;
- macht Cleanup-Fehler hart rot;
- wird nach einem roten E4 nicht erneut gestartet.

## Gewollter Staging-Pfad

Ein relevanter Merge nach `staging` löst aus:

1. `Deploy to STRATO`;
2. bei Control-Center-/Verification-Scope `Staging Verification`;
3. bei Governance-Scope `Project Guardrails`.

Nicht allein wegen eines technischen Staging-Pushes auslösen:

- `Control Center CI`;
- `Control Center E4 Synthetic Proof`;
- schwere Content-, Growth-, KI-, Inbox- oder Auditläufe;
- `PR Gate`.

## Permissions

Read-only Workflows setzen minimale Rechte explizit:

- `PR Gate`: `contents/checks/pull-requests: read`;
- `Project Guardrails`: `contents: read`;
- `Control Center CI`: `contents: read`;
- `Staging Verification`: `actions: read`, `contents: read`, `statuses: write`.

E4 erhält ausschließlich:

- `contents: read` für den SHA-gebundenen Checkout;
- `actions: write` für kontrollierte Staging-Feed-Deploys.

## Verbotene Muster

- Ruleset- oder Status-Bypass;
- manueller Fake-Status für `PR Gate`;
- selbstmodifizierender One-off-Workflow;
- zusätzlicher Observer oder Wrapper ohne belegten Mehrwert;
- staging-only `workflow_dispatch` als angeblich bedienbarer Operatorpfad;
- E4-Push-, Schedule-, `workflow_run`- oder automatischer Trigger;
- E4 ohne vollständigen Staging-SHA und exakte Bestätigung;
- E4-Checkout von `main`, einem beweglichen Branch oder ungeprüften Ref;
- E4 nach zwischenzeitlicher Bewegung von `staging`;
- CityArt oder ein anderer realer Fachfall als technische E4-Abhängigkeit;
- Main-Operator-Bootstrap ohne real grünen `PR Gate`;
- Main-Operator-Bootstrap ohne `[skip ci]`;
- Umbenennung von `PR Gate`, `deploy/staging-observed` oder `control-center/runtime-preflight-e3` ohne koordinierte Ruleset-Prüfung;
- Entfernen einer Qualitätsprüfung ohne Ersatz;
- E4-Aufruf aus `Staging Verification`.

## Validierung

### E2

- `Project Guardrails`, `Control Center CI` und `PR Gate` grün;
- E4-Harness-Syntax und Self-Test grün;
- Operator-, SHA-, Confirmation-, Fachfall- und Cleanup-Guards grün;
- PR mergebar und nicht hinter `staging`.

### E3

- `Deploy to STRATO` grün;
- `deploy/staging-observed=success`;
- `control-center/runtime-preflight-e3=success`;
- fachfallfreier Runtimevertrag, `mutation=false`, Staging-Schemas gültig, Live unbenutzt.

### Main-Bootstrap

- PR-Diff exakt die zwei erlaubten Workflowdateien;
- Base-SHA und Headbranch exakt;
- `PR Gate` real grün;
- Squash-Commit enthält `[skip ci]`;
- kein Main-Push-Workflow und kein Live-Deploy gestartet;
- Workflowdatei auf `main` bytegleich mit `staging`.

### E4

- genau ein manueller Run mit finalem aktuellen `staging`-SHA und exakter Bestätigung;
- Success-Write, Replay, Teilfehler und Resume bestätigt;
- synthetische IDs nur im Staging-Feed;
- vollständiger Sheet-, DB- und Feed-Cleanup;
- Nicht-Testdaten und Live-Ressourcen unverändert;
- Evidence `result=success`, keine Cleanup-Fehler;
- kein zweiter E4-Lauf.
