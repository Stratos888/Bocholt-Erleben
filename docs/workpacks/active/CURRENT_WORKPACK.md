# Current Workpack

Stand: 2026-07-19

Diese Datei ist der einzige operative technische Projektstatus. Offene PRs, aktuelle SHAs und CI-Zustände werden bei jeder Aufgabe direkt aus GitHub gelesen und nicht dauerhaft hier gespiegelt.

## Aktiver Implementierungs-Workpack

**Keiner.**

Die Dokumentations-Governance, die semantische Vollklassifikation und die Control-Center-Workflow-Konsolidierung sind abgeschlossen. Ergebnisse und Grenzen stehen in:

- `docs/workpacks/completed/DOCUMENTATION-GOVERNANCE-HARDENING-2026-07-19.md`
- `docs/workpacks/completed/DOCUMENTATION-SEMANTIC-CLASSIFICATION-2026-07-19.md`
- `docs/workpacks/completed/CONTROL-CENTER-WORKFLOW-CONSOLIDATION-2026-07-19.md`
- `docs/evidence/control-center-workflow-inventory-2026-07-19.md`

## Integrierter Workflowzustand

Autoritative technische Kette:

1. `PR Gate` – Always-run-Integration und Branchpolicy.
2. `Project Guardrails` – Architektur-, Dokumentations- und Workflowtopologie-Governance.
3. `Control Center CI` – vollständige E1/E2-Prüfung je relevantem PR.
4. `Deploy to STRATO` – einziger Deploypfad.
5. `Staging Verification` – gemeinsame read-only Deploy-/Build-/E3-Evidence.
6. `Control Center E4 Synthetic Proof` – getrennte, weiterhin gesperrte R3-Capability.

Die Statuskontexte `deploy/staging-observed` und `control-center/runtime-preflight-e3` bleiben verbindlich. Content-, Growth-, Inbox-, KI- und Content-Ops-Betriebsworkflows bleiben fachlich getrennt.

## Pausierter Runtime-Workpack

- **Programm:** Control Center Runtime-Verlässlichkeit
- **Nächster Workpack:** genau ein isolierter synthetischer E4-Staging-Write mit Wiederaufnahme, Rücklesen und Cleanup
- **Status:** noch nicht aktiviert
- **Risikoklasse:** R3
- **Erreichte Evidence:** E1, E2 und read-only E3
- **Fehlende Evidence:** E4

Belegt beziehungsweise vorgeschrieben:

- der E4-Harness ist statisch integriert und selbstgetestet;
- die normale Staging-Verifikation ist read-only;
- `staging` löst `Inbox_Staging -> Events_Staging` auf;
- kein synthetischer E4-Lauf wurde gestartet;
- keine synthetischen Restdaten und keine Live-Mutation wurden erzeugt.

## Ressourcen-Lock

Bis zur ausdrücklichen Aktivierung eines separaten R3-Workpacks gilt:

- keine E4-Ausführung;
- kein CityArt-Fachklick;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events` im Control-Center-Testpfad;
- kein zusätzlicher Trigger-, Observer- oder One-off-Workflow;
- keine parallele Control-Center-Workflowänderung.

Eine ausdrücklich beauftragte deterministische einzelne Live-Eventpflege nach `AI_ENTRYPOINT.md` ist kein Control-Center-Test und wird separat exklusiv gelockt.

## Nächster erlaubter Schritt

Einen separaten R3-Workpack für genau einen isolierten synthetischen E4-Staging-Lauf erstellen und vor Aktivierung gegen den aktuellen `staging`-Stand validieren.

Der Workpack muss insbesondere klären:

- wie der Workflow auf dem Default-Branch sicher bedienbar wird, ohne E4 vorzeitig freizuschalten;
- exakte synthetische Identität;
- `Inbox_Staging`- und `Events_Staging`-Vorherzustand;
- Write-, Resume-, Rücklese- und Cleanup-Postconditions;
- exklusiven Ressourcen-Lock;
- sofortigen Stop bei jeder Abweichung.

## Verbindliche Folgefolge

1. Separaten R3-Workpack für genau einen synthetischen E4-Lauf aktivieren.
2. Nur bei grünem E4 über genau einen echten CityArt-Staging-Fall entscheiden.
3. Danach einen Produktworkpack aus `docs/workpacks/queued/INDEX.md` aktivieren.

## Dokumentationsstatus

- Dokumentrollen und Rangfolge: `docs/README.md`
- vollständiger Rollenvertrag: `docs/DOCUMENT_REGISTRY.md`
- Control-Center-Einstieg: `docs/domains/control-center.md`
- technische Queue: `docs/workpacks/queued/INDEX.md`
- aktueller Proofstand: `TEST_STATUS.md`
- Architektur- und Produktzielentscheidungen bleiben getrennt von diesem operativen Status.
