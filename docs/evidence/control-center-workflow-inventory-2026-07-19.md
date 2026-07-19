# Gate A – vollständige GitHub-Actions-Inventur

Stand: 2026-07-19  
Repository: `Stratos888/Bocholt-Erleben`  
Prüfref: `staging`  
Ausgangs-SHA: `a129c83e6642041280a4a836894bd8da37513964`  
Evidence-Stufe: E1/E2 für Inventur und CI-Verträge; keine externe Mutation

## 1. Prüfgrenzen

- Alle vorhandenen Dateien unter `.github/workflows/**` wurden auf `staging` einzeln gelesen.
- Das Vorkommen auf `main` wurde je Datei gegen den Default-Branch geprüft.
- Offene Pull Requests zum Prüfzeitpunkt: `0`.
- `staging` entsprach exakt dem verbindlichen Ausgangs-SHA.
- GitHub-Settings für Rulesets sind über den verfügbaren Connector nicht vollständig lesbar. Abhängigkeiten werden deshalb aus den kanonischen Verträgen, real veröffentlichten Check-/Statusnamen und beobachteten PR-/Push-Läufen abgeleitet.
- Kein Workflow wurde ausgeführt oder geändert, bevor diese Inventur und die Zielentscheidung abgeschlossen waren.
- Kein E4-Lauf, kein CityArt-Fachfall und keine externe Schreibaktion waren Teil von Gate A.

## 2. Vollständige Workflow-Matrix

| Datei | sichtbarer Name | Owner / Zweck | Trigger und Filter | Jobs / tatsächlich ausgeführte Prüfungen | Überschneidung | Secrets / Berechtigungen | Artefakte / Check- oder Statusname | Bedienpfad | Vorkommen | Laufzeit | Gate-A-Kandidat |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `deploy-strato.yml` | `Deploy to STRATO` | Deploy-/Eventfeed-Owner; Build, Umgebungskonfiguration, STRATO-Auslieferung und Smoke | `push` auf `main`,`staging`; täglich `05:15 UTC`; `workflow_dispatch` mit `full_repair`; keine Pfadfilter | Job `deploy`: Branchrouting, read-only Sheets-Export, Event-/Inbox-/Detail-Build, CSS-Audit, private Config, Delta-/Full-Deploy, HTTP- und Playwright-Smoke | Runtime-Verifikation beobachtet denselben Deploy; CSS-Audit überschneidet sich fachlich mit CI, ist hier aber ein Deploybruch-Guard | Kein expliziter Workflow-Permissions-Block; dadurch Repository-Default. Secrets: Sheet/Service Account, Staging-/Live-DB, Stripe, Mail, Review, Push, Search-Metriken, Content-Ops, SFTP | Browser-Smoke-Artefakte; Actions-Check `deploy`; beobachteter Status `deploy/staging-observed` stammt bisher aus separatem Observer | Push; manueller UI/API-Dispatch auf Default-Branch | `main` + `staging`, auf `staging` erweitert | letzter belegter Staging-Lauf ca. 2–3 Minuten; kein explizites Job-Timeout | **behalten**; einziger Deploypfad |
| `pr-gate.yml` | `PR Gate` | Integrations-Gate; Repository-/Branchpolicy und Aggregation gestarteter Actions-Checks | jeder PR nach `staging` oder `main`; keine Pfadfilter; opened/synchronize/reopened/ready_for_review | Job/Check `PR Gate`: gleicher Repo-Ursprung, nur `staging -> main`, Polling aller gestarteten GitHub-Actions-Checks | keine Testduplikation; aggregiert alle gestarteten Checks | `contents: read`, `checks: read`, `pull-requests: read`; keine Secrets | stabiler Required-Check-Kandidat `PR Gate`; keine Artefakte | automatisch bei PR | nur `staging` | Timeout 20 min; beobachtet ca. 24 s nach Runnerstart bei bereits fertigen Checks | **behalten** |
| `project-guardrails.yml` | `Project Guardrails` | Governance-/Architektur-Owner; Branchrouting, Dokumentinventur und dauerhafte Engineering-Verträge | PR/Push `staging`,`main` mit Markdown- und ausgewählten Workflow-/Helper-Pfaden; manual | Job/Check-ID `validate`: Branchroutingtest, PR-Gate-Vertrag, Dokumentinventur/-audit, Engineering-/Freeze-Guards | keine fachliche Control-Center-Duplikation; Workflow-Pfadabdeckung bislang unvollständig | `contents: read`; keine Secrets | Artefakt `documentation-inventory`; Check `validate` | automatisch; manuell | nur `staging` | kurzer E2-Lauf, typischerweise unter 1 Minute | **behalten und intern härten** |
| `control-center-validation.yml` | `Control Center Validation` | Control-Center-Gesamt-CI; Syntax, Contracts, Architektur, Security, JS, CSS | PR/Push `staging`,`main` bei breitem Control-Center-Scope; manual | Job `validate`: PHP-Syntax; 11 Contracttests; Visual-Python-Test; JS-Syntax und Frontendtest; Produkt-/Editorial-Audits; CSS; Dateistruktur; Security-Greps | vollständige Obermenge großer Teile von Editorial Contracts und Contract Diagnostics; Runtime-Preflight-Contract ebenfalls enthalten | implizite Default-Permissions; keine Secrets; Testmodus deaktiviert Writer | keine Artefakte; generischer Check `validate` | automatisch; manuell | `main` + `staging`, Staging deutlich erweitert | typischer kurzer bis mittlerer E2-Lauf; kein Timeout | **ersetzen** durch autoritativen `Control Center CI` |
| `control-center-editorial-contracts.yml` | `Control Center Editorial Contracts` | Editorial-/State-Contract-Teilprüfung | PR/Push nur `staging` mit detailliertem Pfadfilter; manual | Job `contracts`: JSON- und PHP-Syntax, acht PHP-Contracttests, Visual-Python-Test, Frontendtest, Editorial-Audit | fast vollständig in `Control Center Validation`; große Teilmenge zusätzlich in Diagnostics | implizite Default-Permissions; keine Secrets | keine Artefakte; Check `contracts` | automatisch; manual nur operativ zuverlässig, weil ältere Datei auch auf `main` existiert | `main` + `staging`, Versionen unterschiedlich | kurzer E2-Lauf; kein Timeout | **intern zusammenführen / entfernen** |
| `control-center-contract-diagnostics.yml` | `Control Center Contract Diagnostics` | historisch ergänzte Diagnose-Teilprüfung | PR nur nach `staging`; sehr breiter Filter einschließlich `scripts/**` | Job `diagnostics`: sieben PHP-Contracttests, Visual-Python-Test, Frontendtest | nahezu vollständig in Validation/Editorial; `scripts/**` erzeugt fachfremde Starts, z. B. bei Governance-Skripten | implizite Default-Permissions; beobachtet unerwartet breite Tokenrechte; keine Secrets genutzt | keine Artefakte; Check `diagnostics` | automatisch im PR | nur `staging` | beobachtet ca. 1 Minute inklusive Runnerstart | **entfernen**, Ersatz im `Control Center CI` |
| `control-center-e4-contract.yml` | `Control Center E4 Contract` | statischer, externer-zugriffsfreier Harness-Vertrag | PR nach `staging` nur bei E4-Dateien; manual | Job `validate`: Python-Kompilierung und `--self-test` | kleiner Teiltest, sinnvoll, aber eigener Top-Level-Workflow unnötig | `contents: read`; keine Secrets | keine Artefakte; generischer Check `validate` | PR; Manual auf `staging` nicht verlässlich sichtbar, solange Datei nicht auf Default-Branch liegt | nur `staging` | sehr kurz | **intern in Control Center CI zusammenführen** |
| `control-center-e4-synthetic.yml` | `Control Center E4 Synthetic Proof` | isolierter R3-Harness für späteren synthetischen Write/Resume/Cleanup | ausschließlich `workflow_dispatch`; Job nur bei Ref `staging` | Job `isolated-synthetic-write-resume-cleanup`: Self-Test, Dependencies, realer synthetischer E4, Evidence-Upload | keine legitime E2/E3-Duplikation; darf wegen R3 nicht mit normaler Verification gekoppelt werden | `contents: read`, `actions: write`; Secrets: Review, Sheet, Service Account, Staging-DB; GitHub-Token | Artefakt `control-center-e4-*`; Jobname `isolated-synthetic-write-resume-cleanup` | aktueller UI/API-Dispatch operativ blockiert, weil Workflow nur auf `staging` liegt; **nicht ausführen** | nur `staging` | Timeout 75 min | **behalten, getrennt und gesperrt** |
| `control-center-runtime-preflight.yml` | `Control Center Runtime Preflight` | read-only E3 für Build, Host, Ressourcen und Operationsplan | Push `staging` bei Control-Center-/Preflight-/Deploy-/Current-Workpack-Pfaden; manual | Job `preflight`: lokaler Contract, Status pending, Build-Wait, CityArt-spezifischer read-only Preflight, Evidence, finaler Status | beobachtet denselben Build wie Staging Deploy Observer; Contracttest auch in Validation | `contents: read`, `statuses: write`; Secret `STAGING_REVIEW_PASSWORD` | Artefakt `control-center-runtime-preflight-*`; Status `control-center/runtime-preflight-e3` | automatisch nach Push; manual auf staging-only Datei nicht zuverlässig bedienbar | nur `staging` | Timeout 25 min; Build-Wait maximal 10 min | **ersetzen** durch gemeinsame read-only Staging Verification |
| `staging-deploy-observer.yml` | `Staging Deploy Observer` | Observer für passenden Deploy-Run und Statusprojektion | jeder Push auf `staging`; manual | Job `observe`: Deploy-Run-Polling, Evidence, finaler Commitstatus | parallel zum Runtime-Preflight; beide warten auf denselben Deploy/Build | `actions: read`, `contents: read`, `statuses: write`; keine Secrets | Artefakt `staging-deploy-observer-*`; Status `deploy/staging-observed` | automatisch; manual auf staging-only Datei nicht zuverlässig | nur `staging` | Timeout 20 min; Polling maximal 15 min | **ersetzen** durch gemeinsame read-only Staging Verification |
| `content-quality-audit.yml` | `Content Quality Audit` | Content-/Activity-/Feed-Audit, Reports und Lernsignale | manual mit `daily/full`; Push nur `main` auf gezielten Content-/Auditpfaden; täglich und mittwochs Schedule | ein umfangreicher Auditjob: Sheet-/DB-/Runtime-Lesen, Content-/Visual-/Link-Audits, Audit-/Feedback-/Cache-Writeback, Content-Ops | keine Control-Center-CI-Duplikation; fachlich getrennte Betriebsautomation | `contents: read`; Sheets-, DB-, Content-Ops-Secrets | `content-quality-report-*` und Content-Ops-Artefakte; Workflow-Check nur bei bewusstem Trigger | manual/schedule/main-push | `main` + `staging` | schwerer Lauf, mehrere externe Timeoutgrenzen; kein Gesamt-Timeout | **behalten** |
| `growth-intelligence-backlog.yml` | `Growth Intelligence Backlog` | Growth-/Acquisition-Auswertung und Backlog | manual; Montag-Schedule; kein Push | ein Job: GSC/GA4/Sheet/DB-Auswertung, Backlog/Report, Content-Ops/HTTP | keine Control-Center-Überschneidung | `contents: read`; Sheet, GSC, GA4, Staging-/Live-DB, Content-Ops | `growth-content-ops-impact` | manual/schedule | `main` + `staging` | Update-Step Timeout 10 min | **behalten** |
| `content-ops-http-ingest.yml` | `Content Ops HTTP Ingest` | Folgeworkflow für Content-Ops-Artefakte | manual mit `source_run_id`; `workflow_run` nach fünf Betriebsworkflows | Artefakte herunterladen und per HTTPS ingestieren | Quellworkflows senden teilweise zusätzlich direkt; das ist eine fachliche Content-Ops-Frage außerhalb dieses Workpacks, kein Control-Center-CI-Scope | `actions: read`, `contents: read`; HTTP-Ingest URL/Token | keine eigenen Uploads; Folgeworkflow-Check | workflow_run/manual | `main` + `staging`; zuverlässiger workflow_run effektiv vom Default-Branch | kurz; kein Timeout | **behalten** |
| `inbox-cleanup.yml` | `Inbox Cleanup (Archive)` | Inbox-Archivierung/-Bereinigung plus Impact | manual; täglich `05:55 UTC` | Sheet-Archivierung/Entfernung, Content-Ops DB/HTTP, Artefakt | keine Control-Center-CI-Duplikation; schreibender Betriebsjob | `contents: read`; Sheet/Service Account, Staging-/Live-DB, Content-Ops | `inbox-cleanup-content-ops-impact` | manual/schedule | `main` + `staging` | kein Timeout; externe Laufzeit | **behalten** |
| `weekly-ki-websearch-to-manual-inbox.yml` | `Weekly KI Websearch → Manual Inbox` | produktive KI-Eventsuche, Repo-Handoff und Intake-Dispatch | manual; Dienstag-Schedule; harter `main`-Guard | Suche, Diagnostics, Repo-Commit, Content-Ops, Dispatch Manual Intake | keine Control-Center-CI-Duplikation | `contents: write`; Sheet/Service Account, OpenAI, Live-DB, Dispatch-Token | `weekly-event-diagnostics`, `weekly-content-ops-impact`; Repo-Commit | manual/schedule nur sinnvoll auf `main` | `main` + `staging` | externer KI-Lauf; kein Gesamt-Timeout | **behalten** |
| `manual-ki-intake.yml` | `Manual KI Event Intake` | produktiver KI-Kandidaten-Import in Live-Inbox, Buffer-Reset und Deploy-Handoff | ausschließlich manual; harter `main`-Guard | Sheet-Append mit Qualitäts-/Dedupe-Guards, Push-Hinweis, Repo-Reset, Content-Ops und Deploy-Dispatch | keine Control-Center-CI-Duplikation; schreibender Produktionspfad | `contents: write`, `actions: write`; Sheet/Service Account, Push, DB, Content-Ops | Intake-/Content-Ops-Artefakte; Repo-Commit; Deploy-Dispatch | manual/API auf `main` | `main` + `staging` | kein Gesamt-Timeout | **behalten** |

## 3. Belegte Probleme und Überschneidungen

1. **Dreifache statische Control-Center-Prüfung.**  
   `Control Center Validation` enthält bereits die vollständige Obermenge. `Editorial Contracts` und `Contract Diagnostics` führen dieselben PHP-, Python- und Frontendtests erneut aus.

2. **Fachfremder Trigger-Scope.**  
   `Control Center Contract Diagnostics` reagiert auf jedes `scripts/**`. Dadurch startete der Workflow nachweislich bei einer reinen Dokumentations-Governance-Änderung.

3. **Doppelte Post-Merge-Runtime-Beobachtung.**  
   `Staging Deploy Observer` und `Control Center Runtime Preflight` starten parallel, pollten denselben Deploy beziehungsweise denselben Build und erzeugten zwei getrennte Evidence-Ketten.

4. **Doppelte statische Push-CI nach bereits grünem PR.**  
   `Control Center Validation` und `Editorial Contracts` laufen zusätzlich nach dem Merge auf `staging`, obwohl dieselben Tests bereits auf dem PR-Head grün waren.

5. **Irreführende Manual-Trigger auf staging-only Workflows.**  
   Ein `workflow_dispatch` ist nur zuverlässig bedienbar, wenn die Workflowdatei auf dem Default-Branch vorhanden ist. Das betrifft aktuell E4 Synthetic, E4 Contract, Runtime Preflight und Deploy Observer.

6. **Uneindeutige Checknamen.**  
   Mehrere Jobs heißen generisch `validate`. Der stabile Integrationsvertrag ist dagegen nur für `PR Gate` ausdrücklich benannt.

7. **Unvollständige Workflow-Governance.**  
   `Project Guardrails` beobachtet bisher nur drei konkrete Workflowdateien. Änderungen an anderen Actions-Dateien können die Topologie verändern, ohne den Architekturguard auszulösen.

8. **Überbreite Default-Tokenrechte.**  
   Mehrere read-only Testworkflows besitzen keinen expliziten `permissions`-Block. Ein beobachteter Diagnostics-Run erhielt dadurch deutlich mehr Tokenrechte als benötigt.

9. **CityArt-Kopplung im E3-Beweis.**  
   Der Runtime-Preflight ist technisch read-only, sucht aber fest nach einem konkreten historischen CityArt-Fall. E3 soll den aktiven Build und Operationsplan beweisen, nicht einen eingefrorenen Fachfall als dauerhafte Infrastrukturabhängigkeit verwenden.

## 4. Validierte optimale Zieltopologie

### A. Unverändert autoritativ

- `PR Gate`: Always-run-Aggregator und Branchpolicy.
- `Project Guardrails`: projektweite Architektur-, Dokumentations- und Workflowtopologie-Guards.
- `Deploy to STRATO`: einziger Deploypfad.
- alle Content-, Growth-, Inbox-, KI- und Content-Ops-Betriebsworkflows: fachlich getrennt.
- `Control Center E4 Synthetic Proof`: getrennte, gesperrte R3-Capability; kein E4 in diesem Workpack.

### B. Ein autoritativer E1/E2-Workflow

`Control Center CI` ersetzt:

- `control-center-validation.yml`;
- `control-center-editorial-contracts.yml`;
- `control-center-contract-diagnostics.yml`;
- `control-center-e4-contract.yml`.

Eigenschaften:

- PR-basiert auf `staging` und `main`;
- vollständige Vereinigungsmenge aller bisherigen Tests, jede Prüfung genau einmal;
- explizit `contents: read`;
- eigener stabiler Job-/Checkname `Control Center CI`;
- kein Push-Trigger und damit keine doppelte statische Prüfung nach dem Merge;
- statischer E4-Harness-Self-Test bleibt erhalten, aber ohne externe Zugriffe.

### C. Ein autoritativer read-only E3-Workflow

`Staging Verification` ersetzt:

- `control-center-runtime-preflight.yml`;
- `staging-deploy-observer.yml`.

Eigenschaften:

- gezielter `push` auf `staging`;
- genau eine Runner-/Polling-Kette für Deploy, Build und Runtime-Preflight;
- beibehaltener Commitstatus `deploy/staging-observed`;
- beibehaltener Commitstatus `control-center/runtime-preflight-e3`;
- read-only Laufzeitprüfung mit `mutation=false`;
- generische Auswahl eines stabilen `inbox_feed`-Falles statt CityArt-Hardcoding;
- nur `actions: read`, `contents: read`, `statuses: write` und `STAGING_REVIEW_PASSWORD`;
- kombiniertes Evidence-Artefakt;
- kein `workflow_dispatch`, solange der Workflow nur auf `staging` liegt und der Operatorpfad dadurch nicht zuverlässig wäre.

### D. Warum E4 nicht integriert wird

E4 hat eine andere Risikoklasse, reale Staging-Secrets, Write-/Cleanup-Semantik und einen ausdrücklich gesperrten Operatorpfad. Eine Bündelung mit E3 würde die normale read-only Verification unnötig privilegieren und könnte versehentliche Schreibausführung ermöglichen. E4 bleibt deshalb getrennt und wird erst in einem eigenen R3-Workpack operativ freigeschaltet.

## 5. Exakt begrenzter Umsetzungsscope

### Ersetzen / hinzufügen

- neu: `.github/workflows/control-center-ci.yml`;
- neu: `.github/workflows/staging-verification.yml`;
- härten: `.github/workflows/project-guardrails.yml`.

### Entfernen nach nachgewiesenem Ersatz

- `.github/workflows/control-center-validation.yml`;
- `.github/workflows/control-center-editorial-contracts.yml`;
- `.github/workflows/control-center-contract-diagnostics.yml`;
- `.github/workflows/control-center-e4-contract.yml`;
- `.github/workflows/control-center-runtime-preflight.yml`;
- `.github/workflows/staging-deploy-observer.yml`.

### Unverändert

- `deploy-strato.yml`;
- `pr-gate.yml`;
- `control-center-e4-synthetic.yml`;
- alle sieben Content-/Growth-/Inbox-/KI-/Content-Ops-Workflows;
- alle Fachlogik-, Daten-, SEO-, Visual-, Content- und Produktdateien.

## 6. Ruleset- und Statuscheck-Abhängigkeiten

| Name | Beleg | Zielentscheidung |
|---|---|---|
| `PR Gate` | kanonische Triggerpolicy und erfolgreicher PR-Lauf | unverändert |
| `validate` aus Project Guardrails | erfolgreicher PR-Lauf; möglicher Required-Check | Job-ID/Checkname unverändert |
| `Control Center CI` | neuer eindeutiger fachlicher Check | darf alte `contracts`/`diagnostics`/Control-Center-`validate` ersetzen; PR Gate aggregiert ihn |
| `deploy/staging-observed` | realer grüner Commitstatus auf Ausgangs-SHA | exakt beibehalten |
| `control-center/runtime-preflight-e3` | realer grüner Commitstatus auf Ausgangs-SHA | exakt beibehalten |
| alte `diagnostics`, `contracts`, generisches Control-Center-`validate` | keine kanonische Required-Check-Benennung nachgewiesen | erst nach grünem PR und Mergeability entfernen |

Die GitHub-Ruleset-Konfiguration selbst ist über den Connector nicht vollständig abrufbar. Deshalb gilt fail-closed: Der Draft-PR muss mergebar sein, `PR Gate`, `Project Guardrails` und `Control Center CI` müssen grün sein; nach Integration müssen die beiden bestehenden Commitstatus-Kontexte erneut grün erscheinen.

## 7. Risiken und Rollback

| Risiko | Guard | Rollback |
|---|---|---|
| Testverlust bei Konsolidierung | explizite Test-Union und Topologie-Greps in Project Guardrails | Revert des Konsolidierungs-Mergecommits |
| PR Gate wartet auf falsche/fehlende Checks | Always-run-Gate unverändert; realer Draft-PR als E2 | Revert; alte Workflows wiederherstellen |
| Ruleset erwartet alten Check | Mergeability und Required-Checks vor Merge prüfen | nicht mergen beziehungsweise Ruleset separat anpassen |
| Staging Verification findet keinen geeigneten read-only Fall | fail-closed, `mutation=false`, keine Retry-Mutation | Revert; alten Preflight/Observer wiederherstellen |
| Deploystatus geht verloren | alte Statuskontexte bytegenau beibehalten | Revert |
| E4 wird versehentlich gestartet | E4-Workflow unverändert und nicht referenziert; kein Dispatch | sofort stoppen; keine Wiederholung; Ressourcen prüfen |
| externe Datenmutation | neue Verification besitzt keine Sheet-/DB-Credentials und ruft nur Preflight auf | Stop-the-line; Revert |

## 8. Erforderliche Evidence

### E1

- diese vollständige Inventur;
- Zielzuordnung jeder bisherigen Workflowdatei;
- Diff ausschließlich im erklärten Workflow-/Governance-Scope;
- statisch nachgewiesene Test-Union ohne fehlende Prüfung.

### E2

- YAML-/Syntaxprüfung;
- `Project Guardrails` grün;
- `Control Center CI` grün;
- `PR Gate` grün und korrekt aggregierend;
- keine alten duplizierten Control-Center-Checks auf demselben PR-Scope;
- Draft-PR mergebar;
- E4-Harness nur per Self-Test, kein externer Lauf.

### E3

- Merge nach `staging`;
- normaler `Deploy to STRATO` erfolgreich;
- `deploy/staging-observed=success`;
- `control-center/runtime-preflight-e3=success`;
- Build-ID stimmt;
- Host und Umgebung sind Staging;
- Quelle/Ziel sind `Inbox_Staging -> Events_Staging`;
- `mutation=false`;
- Live-Inbox und Live-Events unbenutzt;
- kein E4-Run und keine externe Mutation.
