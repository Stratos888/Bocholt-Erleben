# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** E4 – isolierter synthetischer Staging-Write und Wiederaufnahmenachweis
- **Status:** Implementierung und E2-Vertragstest in Arbeit; noch keine externe Mutation
- **Risikoklasse:** R3 – ausschließlich synthetische, vollständig rückbaubare Staging-Mutation
- **Aktuelles Gate:** B – Bauen und statisch beweisen
- **Erforderliche Evidence:** E1, E2, anschließend E4
- **Ausgangs-SHA von `staging`:** `6fe7eb1a73753109dd8e85c6c07dc15306d2c27f`
- **Branch:** `agent/control-center-e4-synthetic-resume-proof`
- **Schreibender PR:** genau ein Draft-PR dieses Branches; weitere schreibende Workpacks sind gesperrt

## Ausgangslage

WP-2 hat für den real deployten Staging-Build nachgewiesen:

- `Inbox_Staging -> Events_Staging`;
- Writer `be_cc_writeback_staging_inbox_approve_verified`;
- Live-Inbox und Live-Events werden nicht verwendet;
- Build, Manifest, Host, Endpoint und Environment stimmen überein;
- der read-only Preflight mutiert nichts und meldet keine Blocker.

Der Writer ist auf Event-Ebene idempotent und bestätigt Event sowie Inboxstatus durch Rücklesen. Offen ist ausschließlich der reale Nachweis, dass ein bestätigtes Event bei noch offenem Inboxstatus ohne Duplikat sicher wiederaufgenommen und anschließend vollständig bereinigt wird.

## E4-Testmodell

Der Test verwendet zwei eindeutig benannte synthetische Kandidaten. Die Identität enthält Build-SHA und GitHub-Run-ID.

1. **Erfolgspfad:**
   - synthetische Zeile in `Inbox_Staging`;
   - read-only Runtime-Preflight mit Zielstatus `absent`;
   - echte Staging-Freigabe;
   - Eventmodus `appended`;
   - terminaler Inboxstatus und lokaler Fallstatus `done`;
   - Wiederholung derselben `operation_id` muss als idempotenter Replay zurückkehren.
2. **Wiederaufnahmepfad:**
   - synthetische Inbox-Zeile bleibt `review`;
   - exakt passende synthetische Eventzeile wird vorab in `Events_Staging` angelegt und zurückgelesen;
   - eine synthetische fehlgeschlagene Operation dokumentiert den kontrollierten Teilzustand;
   - dieselbe fehlgeschlagene `operation_id` muss fail-closed bleiben;
   - eine neue `operation_id` muss den vorhandenen Eventdatensatz mit Modus `existing` wiederverwenden, den Inboxstatus abschließen und kein Duplikat erzeugen.
3. **Feed- und Cleanup-Beweis:**
   - manueller Staging-Deploy weist beide synthetischen Events im Staging-Feed nach;
   - Live-Feed bleibt frei von den Test-IDs;
   - anschließend werden ausschließlich die beiden synthetischen Sheetzeilen sowie ihre synthetischen DB-Fälle und Operationen gelöscht;
   - ein zweiter Staging-Deploy entfernt die Events wieder aus dem Staging-Feed;
   - Abschlussprüfung bestätigt vollständige Abwesenheit und unveränderte Nicht-Test-/Live-Daten.

## Erlaubter Scope

- `scripts/control-center-e4-synthetic.py`
- `.github/workflows/control-center-e4-contract.yml`
- `.github/workflows/control-center-e4-synthetic.yml`
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- `docs/evidence/**` erst nach einem real abgeschlossenen Lauf

## Gesperrter Scope

- keine Änderung an `action.php` oder den Writerimplementierungen;
- keine Fehlerweiche und keine Fault-Injection in der produktiven Runtime;
- keine Mutation des echten CityArt-Falls;
- keine Mutation in `Inbox` oder `Events`;
- kein Live-Schreibtest;
- kein Feature-Branch-Deploy;
- kein allgemeiner WP-3- oder Transaktionsumbau;
- keine weitere Produkt-, UI- oder Contentänderung.

## Externe Ressourcen und Ressourcen-Lock

- `Inbox_Staging`: nur zwei synthetische Zeilen mit Präfix `be-e4-synthetic`; CityArt bleibt unverändert.
- `Events_Staging`: nur zwei synthetische Event-IDs desselben Laufs.
- Staging-Datenbank: nur synthetische `control_cases` und `control_operations` desselben Laufs.
- `Inbox`, `Events` und Live: read-only Unverändertheitsnachweis.
- STRATO Staging: ein Deploy für den synthetischen Feed und ein Cleanup-Deploy.
- Der Ressourcen-Lock gilt vom ersten synthetischen Write bis zur bestätigten vollständigen Bereinigung.
- Während dieses Fensters ist kein anderer schreibender Workpack zulässig.

## Sicherheits- und Stop-the-line-Vertrag

- Der E4-Ausführungsworkflow ist ausschließlich manuell per `workflow_dispatch` auf `staging` startbar.
- PR-Checks führen nur Syntax und deterministische Selbsttests aus; sie besitzen keine Secrets und keinen externen Schreibzugriff.
- Vor der ersten Mutation müssen Staging-Build, DB-Schema, Sheet-Metadaten, CityArt-Zustand und vollständige Live-/Staging-Baselines erfolgreich gelesen sein.
- Jede Abweichung stoppt den Fachtest.
- Der `finally`-Pfad versucht unabhängig vom Testergebnis Sheet-, DB- und Feed-Cleanup.
- Ein Lauf gilt nur als E4, wenn das Evidence-Artefakt `result=success`, keine Cleanup-Fehler und alle Unverändertheitschecks `true` enthält.
- Bei unvollständigem Cleanup keine Wiederholung und kein echter Fachfall; zuerst gezielte Bereinigung und Ursachenentscheidung.

## Definition of Done

- [ ] E1: Scope, Testidentitäten, Operationszustände und Cleanup-Vertrag sind im Diff eindeutig.
- [ ] E2: Python-Syntax, Selbsttest, Project Guardrails und PR Gate sind grün.
- [ ] Der aktuelle `staging`-Build ist vor der Mutation erfolgreich deployt.
- [ ] Beide synthetischen Preflights bestätigen Staging-Tabs, isolierten Writer und `mutation=false`.
- [ ] Erfolgspfad: `appended`, genau eine Eventzeile, terminaler Inbox- und lokaler Status.
- [ ] Gleiche abgeschlossene `operation_id`: idempotenter Replay.
- [ ] Teilzustand: vorhandenes Event, offene Inbox, fehlgeschlagene Operation.
- [ ] Gleiche fehlgeschlagene `operation_id`: fail-closed.
- [ ] Neue `operation_id`: Wiederaufnahme mit `existing`, keine doppelte Eventzeile.
- [ ] Staging-Feed enthält beide synthetischen Events; Live-Feed enthält keines.
- [ ] Cleanup entfernt Sheet-, DB- und Feed-Testdaten vollständig.
- [ ] CityArt bleibt `review`; kein CityArt-Eintrag in `Events_Staging`.
- [ ] `Inbox`, `Events` und Live sind unverändert.
- [ ] E4-Evidence ist geheimnisfrei dokumentiert.
- [ ] Danach wird entschieden: kein WP-3 oder ausschließlich ein durch E4 belegter Minimalfix.

## Nächster erlaubter Schritt

Draft-PR anlegen, ausschließlich E1/E2 prüfen und nach grünem Gate sequenziell nach `staging` integrieren. Erst danach darf der manuell gegatete E4-Workflow genau einmal gestartet werden. Kein CityArt-Klick und keine externe Mutation vor diesem Gate.
