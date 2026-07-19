# Completed Workpack – Control-Center-Workflow-Konsolidierung

Stand: 2026-07-19  
Risikoklasse: R2  
Ausgangs-SHA: `a129c83e6642041280a4a836894bd8da37513964`

## Ziel

Die Control-Center-Prüf- und Evidence-Kette wurde auf wenige autoritative Workflows reduziert, ohne eine Qualitäts-, Sicherheits- oder Runtimeprüfung zu verlieren.

## Ergebnis

- vollständige Gate-A-Inventur unter `docs/evidence/control-center-workflow-inventory-2026-07-19.md`;
- ein autoritativer PR-Workflow `Control Center CI`;
- ein autoritativer read-only Push-Workflow `Staging Verification`;
- `PR Gate`, `Project Guardrails` und `Deploy to STRATO` bleiben getrennte Owner;
- `Control Center E4 Synthetic Proof` bleibt als gesperrte R3-Capability unverändert getrennt;
- Content-, Growth-, Inbox-, KI- und Content-Ops-Betriebsworkflows bleiben unverändert;
- keine neue Observer-, Wrapper- oder One-off-Schicht;
- keine Fach-, Daten-, SEO-, Visual-, Content- oder Produktänderung.

## Ersetzte Top-Level-Workflows

Die vollständige Test- und Evidence-Abdeckung wurde vor Entfernung in den Zielworkflows nachgewiesen:

- `control-center-validation.yml`;
- `control-center-editorial-contracts.yml`;
- `control-center-contract-diagnostics.yml`;
- `control-center-e4-contract.yml`;
- `control-center-runtime-preflight.yml`;
- `staging-deploy-observer.yml`.

## Dauerhafte Zieltopologie

1. `PR Gate` – Always-run-Integration und Branchpolicy.
2. `Project Guardrails` – Architektur-, Dokumentations- und Workflowtopologie-Governance.
3. `Control Center CI` – vollständige E1/E2-Prüfung genau einmal je relevantem PR.
4. `Deploy to STRATO` – einziger Deploypfad.
5. `Staging Verification` – gemeinsame read-only Deploy-/Build-/E3-Evidence.
6. `Control Center E4 Synthetic Proof` – getrennt, pausiert und nicht ausgeführt.
7. Fachliche Betriebsworkflows – getrennt nach Content, Growth, Inbox, KI und Content Ops.

## Sicherheitsgrenzen

- kein E4-Lauf;
- kein CityArt-Fachfall;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine Live-Schreibaktion;
- neue Staging Verification besitzt keine Sheet- oder DB-Secrets;
- die bestehenden Statuskontexte `deploy/staging-observed` und `control-center/runtime-preflight-e3` bleiben stabil.

## Abnahmekorrektur

Der erste integrierte Lauf der konsolidierten `Staging Verification` war read-only und belegte `mutation=false`, wählte als technisches Preflight-Objekt aber erneut den eingefrorenen CityArt-Fall. Es erfolgte kein Fachklick, kein Writeback und keine externe Mutation. Wegen der harten Fachfallgrenze zählt dieser Lauf nicht als finale E3-Abnahme.

Die Korrektur und ihre finale Abnahmebedingung stehen in:

- `docs/evidence/control-center-workflow-e3-correction-2026-07-19.md`.

Der Selektor schließt CityArt nun vor jedem Preflight-Aufruf aus, dokumentiert übersprungene eingefrorene Fälle und bricht ohne geeigneten Nicht-CityArt-Fall fail-closed ab. `Project Guardrails` schützt diesen Vertrag dauerhaft.

## Evidence

### E1

- vollständige Workflow-/Trigger-/Test-/Secret-/Artefakt-/Ruleset-Matrix;
- explizite Test-Union;
- begrenzter Diff.

### E2

- `Project Guardrails`, `Control Center CI` und `PR Gate` im Konsolidierungs-PR grün;
- keine duplizierten alten Control-Center-Top-Level-Checks;
- E4-Harness nur statisch kompiliert und selbstgetestet;
- CityArt-Ausschluss und fail-closed-Verhalten durch `Project Guardrails` abgesichert.

### E3

Der Abschluss setzt nach Integration voraus:

- erfolgreichen normalen Staging-Deploy;
- Status `deploy/staging-observed=success`;
- Status `control-center/runtime-preflight-e3=success`;
- Staging-Host, passender Build und `mutation=false`;
- ausgewählter Preflight-Fall ohne CityArt-Bezug;
- Assertion `frozen_case_excluded=true`;
- `Inbox_Staging -> Events_Staging`;
- Live-Ressourcen unbenutzt.

Die konkreten Run- und Commit-IDs werden direkt aus GitHub gelesen und nicht als veraltender operativer Status in diesem Dokument gespiegelt.

## Rollback

Bei fehlendem E2 oder E3 wird der jeweilige Konsolidierungs- beziehungsweise Korrektur-Mergecommit revertiert. Da dieses Workpack keine externe Ressource verändert, besteht kein Daten-Cleanup; E4 bleibt weiterhin gesperrt.

## Nächster Schritt

Ein separater R3-Workpack darf den Operatorpfad für genau einen isolierten synthetischen E4-Staging-Lauf aktivieren und nachweisen. Vorher bleibt E4 blockiert.
