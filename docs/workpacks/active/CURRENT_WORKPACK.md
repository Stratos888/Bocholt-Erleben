# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Offene PRs, aktuelle SHAs und CI-Zustände werden bei jeder Aufgabe direkt aus GitHub gelesen und nicht dauerhaft hier gespiegelt.

## Aktiver Implementierungs-Workpack

**Keiner.**

Die Dokumentations-Governance ist konsolidiert. Vor dem nächsten fachlichen oder technischen Workpack gilt ausschließlich die unten festgelegte Reihenfolge.

## Pausierter Runtime-Workpack

- **Programm:** Control Center Runtime-Verlässlichkeit
- **Workpack:** isolierter synthetischer E4-Staging-Write und Wiederaufnahmenachweis
- **Status:** pausiert
- **Risikoklasse:** R3
- **Erreichte Evidence:** E1, E2 und read-only E3
- **Fehlende Evidence:** E4
- **Blocker:** Die Actions-/Operatorstruktur muss vor dem externen Schreibbeweis konsolidiert und real bedienbar nachgewiesen werden.

Belegt:

- der E4-Harness ist statisch integriert;
- der Staging-Build und der read-only Runtime-Preflight waren grün;
- `staging` löst `Inbox_Staging -> Events_Staging` auf;
- der externe synthetische E4-Lauf wurde nicht gestartet;
- keine synthetischen Restdaten und keine Live-Mutation wurden erzeugt.

## Ressourcen-Lock

Bis zur erfolgreichen Workflow-Konsolidierung und einem erneut grünen E3 gilt:

- keine E4-Ausführung;
- kein CityArt-Fachklick;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events` im Control-Center-Testpfad;
- kein zusätzlicher Trigger-, Observer- oder One-off-Workflow;
- keine parallele Control-Center-Workflowänderung.

Eine ausdrücklich beauftragte, deterministische einzelne Live-Eventpflege nach `AI_ENTRYPOINT.md` ist kein Control-Center-Test und wird separat exklusiv gelockt.

## Nächster erlaubter Schritt

Den R2-Workpack `docs/workpacks/queued/CONTROL-CENTER-WORKFLOW-CONSOLIDATION.md` aktivieren.

Er beginnt zwingend mit einer vollständigen Inventur aller GitHub-Actions-Workflows, Trigger, Jobs, Testüberschneidungen, Secrets, Artefakte, Statusnamen, Laufzeiten, Bedienpfade und Ruleset-Abhängigkeiten. Erst danach ist ein Konsolidierungspatch zulässig.

## Verbindliche Folgefolge

1. Control-Center-Workflow-Konsolidierung abschließen und E3 erneut bestätigen.
2. Separaten R3-Workpack für genau einen synthetischen E4-Lauf aktivieren.
3. Nur bei grünem E4 über genau einen echten CityArt-Staging-Fall entscheiden.
4. Danach einen Produktworkpack aus `docs/workpacks/queued/INDEX.md` aktivieren.

## Dokumentationsstatus

- Dokumentrollen und Rangfolge: `docs/README.md`
- Technische Queue: `docs/workpacks/queued/INDEX.md`
- Aktueller Proofstand: `TEST_STATUS.md`
- Architektur- und Produktzielentscheidungen bleiben getrennt von diesem operativen Status.
