# Current Workpack

Stand: 2026-07-20

Diese Datei ist der einzige operative technische Projektstatus. Offene PRs, aktuelle SHAs und CI-Zustände werden bei jeder Aufgabe direkt aus GitHub gelesen und nicht dauerhaft hier gespiegelt.

## Aktiver Implementierungs-Workpack

**Keiner.**

Die Dokumentations-Governance, die semantische Vollklassifikation, die Control-Center-Workflow-Konsolidierung und die Evidence-first-Härtung des Ausführungsmodells sind abgeschlossen. Ergebnisse und Grenzen stehen in:

- `docs/workpacks/completed/DOCUMENTATION-GOVERNANCE-HARDENING-2026-07-19.md`
- `docs/workpacks/completed/DOCUMENTATION-SEMANTIC-CLASSIFICATION-2026-07-19.md`
- `docs/workpacks/completed/CONTROL-CENTER-WORKFLOW-CONSOLIDATION-2026-07-19.md`
- `docs/workpacks/completed/EXECUTION-MODEL-EVIDENCE-DESIGN-2026-07-20.md`
- `docs/evidence/control-center-workflow-inventory-2026-07-19.md`

Es wird kein weiterer allgemeiner Governance-, Dokumentations- oder Prozessoptimierungs-Workpack eröffnet, solange keine neue konkrete und belegte Lücke vorliegt.

## Verbindlicher Ausführungsmodus

Für folgende Workpacks gilt ab jetzt:

1. Evidence-Design ist bei `R2` und `R3` vor dem ersten Patch vollständig Teil von Gate A.
2. Technische Runtime-Evidence ist fachfallfrei, solange nicht der echte Fachfall selbst Prüfgegenstand ist.
3. Der Prüfaufwand bleibt proportional; Vollinventuren gibt es nur bei tatsächlich konkurrierenden Pfaden.
4. Vor Integration eines `R2`-/`R3`-Workpacks muss das Runtime-Design-Gate erfüllt sein.
5. Nach einer fehlgeschlagenen Integration ist höchstens eine eng begrenzte Korrekturrunde zulässig; danach folgt eine Revert-, Architektur- oder Workpack-Neuentscheidung.
6. Nach erfüllter Evidence wird der Workpack abgeschlossen und die Arbeit kehrt zum nächsten produkt- oder risikowirksamen Ziel zurück.

Der vollständige Vertrag steht in `AI_ENTRYPOINT.md`; das PR-Template macht ihn für jeden neuen Workpack explizit.

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

Einen separaten R3-Workpack für genau einen isolierten synthetischen E4-Staging-Lauf erstellen und vor dem ersten Patch gegen den aktuellen `staging`-Stand mit dem neuen Evidence-first-Vertrag validieren.

Der Workpack muss insbesondere vor der Implementierung klären:

- wie der Workflow auf dem Default-Branch sicher bedienbar wird, ohne E4 vorzeitig freizuschalten;
- exakte synthetische Identität;
- `Inbox_Staging`- und `Events_Staging`-Vorherzustand;
- Write-, Resume-, Rücklese- und Cleanup-Postconditions;
- positiven und negativen E4-Assertions sowie ihren E2-Contract-/Replay-Nachweis;
- exklusiven Ressourcen-Lock;
- sofortigen Stop bei jeder Abweichung;
- Revert- oder Architekturentscheidung, falls eine begrenzte Korrekturrunde nicht ausreicht.

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
