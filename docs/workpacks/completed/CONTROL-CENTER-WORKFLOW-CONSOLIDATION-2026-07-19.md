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
- keine Daten-, SEO-, Visual-, Content- oder Produktänderung.

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
- kein CityArt- oder anderer Fachfall als E3-Abnahmeobjekt;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine Live-Schreibaktion;
- `Staging Verification` besitzt keine Sheet- oder DB-Secrets;
- die bestehenden Statuskontexte `deploy/staging-observed` und `control-center/runtime-preflight-e3` bleiben stabil.

## Abnahmekorrektur

Die erste E3-Fassung wählte read-only den eingefrorenen CityArt-Fall. Die zweite Fassung schloss CityArt aus, war aber noch an fachliche Entscheidungsreife gekoppelt. Die dritte Fassung belegte, dass der einzige andere aktive Fall keine eindeutig auflösbare aktuelle `Inbox_Staging`-Quelle besitzt.

Alle drei Abweichungen blieben fail-closed und mutationsfrei. Sie zeigen zugleich, dass ein technischer E3 nicht von einem zufälligen echten Fachdatensatz abhängen darf.

Die Endkorrektur erweitert den bestehenden authentifizierten `preflight.php` um einen dauerhaften `runtime`-Modus:

- kein neuer Endpoint;
- keine Case-Liste;
- keine `case_id`;
- keine `action`;
- read-only Validierung von Build, Host, Environment, `Inbox_Staging`-Schema, `Events_Staging`-Schema und Writerauflösung;
- keine fachlichen Zeilendaten in der Antwort;
- `mutation=false`;
- Live-Ressourcen `not_used`.

Die vollständige Evidenzkette und Abnahmebedingungen stehen in:

- `docs/evidence/control-center-workflow-e3-correction-2026-07-19.md`.

`Project Guardrails` schützt diesen Vertrag dauerhaft.

## Evidence

### E1

- vollständige Workflow-/Trigger-/Test-/Secret-/Artefakt-/Ruleset-Matrix;
- explizite Test-Union;
- begrenzter Diff;
- begründete Scope-Erweiterung nur um den fachfallfreien read-only Runtime-Ressourcenvertrag im bestehenden Endpoint.

### E2

- `Project Guardrails`, `Control Center CI` und `PR Gate` grün;
- keine duplizierten alten Control-Center-Top-Level-Checks;
- E4-Harness nur statisch kompiliert und selbstgetestet;
- Runtime-Modus akzeptiert weder Case noch Action;
- lokaler Contracttest beweist `mutation=false`, Staging-Ressourcenbindung und fail-closed Hostschutz.

### E3

Der Abschluss setzt nach Integration voraus:

- erfolgreichen normalen Staging-Deploy;
- Status `deploy/staging-observed=success`;
- Status `control-center/runtime-preflight-e3=success`;
- Scope `runtime_and_resource_contract_only`;
- Assertion `no_fachfall=true`;
- Staging-Host, passender Build und `mutation=false`;
- gültiges `Inbox_Staging`-Schema;
- gültiges `Events_Staging`-Schema;
- Writer korrekt aufgelöst, aber nicht aufgerufen;
- Live-Ressourcen unbenutzt.

Die konkreten Run- und Commit-IDs werden direkt aus GitHub gelesen und nicht als veraltender operativer Status in diesem Dokument gespiegelt.

## Rollback

Bei fehlendem E2 oder E3 wird der jeweilige Mergecommit revertiert. Da dieses Workpack keine externe Ressource verändert, besteht kein Daten-Cleanup; E4 bleibt weiterhin gesperrt.

## Nächster Schritt

Ein separater R3-Workpack darf den Operatorpfad für genau einen isolierten synthetischen E4-Staging-Lauf aktivieren und nachweisen. Vorher bleibt E4 blockiert.
