# Current Workpack

Stand: 2026-07-17

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** WP-1 – Arbeitsmodell vereinfachen und Projektsicht konsolidieren
- **Status:** in PR #87 vollständig umgesetzt und E2-validiert; Integration nach `staging` ausstehend
- **Risikoklasse:** R1
- **Aktuelles Gate:** D – abschließen
- **Aktuelle Evidence:** E2
- **Ausgangs-SHA:** `1eb2b796db78f88abbbeca1e7a9118c278a9b119`
- **Branch:** `agent/working-model-redesign-analysis`
- **PR:** #87

## Erreichter Zielzustand

- ein primärer Ausführungs-Chat als Standard;
- ein aktiver Schreibbranch/Draft-PR;
- R1–R3 und Gates A–D;
- einmaliges Arbeitsmandat statt wiederholter Zwischenfreigaben;
- Runtime-Evidence vor kritischen Nutzeraktionen;
- optionaler read-only Review statt paralleler Schreibarbeit;
- verbindliches Fehlerbudget und Stop-the-line;
- kompakte Systemkarte und eindeutige Dokumentationshierarchie;
- maschinelle Project-Guardrail-Prüfung des Arbeitsmodells.

## Aktueller Freeze

Bis WP-2 bewusst gestartet und E3 hergestellt ist:

- keine weitere CityArt-Übernahme;
- keine manuelle Statuskorrektur;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine weiteren Hypothesenpatches im Writeback-/Environment-Scope;
- PR #86 nicht mergen oder weiterentwickeln.

Sicherer Datenzustand:

- `Inbox_Staging`: CityArt bleibt `review`;
- `Events_Staging`: kein CityArt-Eintrag;
- `Events`: kein CityArt-Eintrag aus dem Staging-Versuch;
- Live wurde nach dem Rollback nicht weiter verändert.

## Offene PRs und Locks

- **PR #86:** `SUSPENDED`, technische Evidence; wird nach Integration von PR #87 als superseded geschlossen.
- **PR #87:** alleiniger aktiver Schreib-PR für WP-1.
- Owner-Lock: kanonische Prozessdokumentation und Project-Guardrail-Marker bis zum Merge von PR #87.
- Ressourcen-Lock: Inbox-/Events-Writeback bleibt gesperrt.

## Definition of Done für WP-1

- [x] operative Dokumente enthalten keinen Mehrchat-Standard;
- [x] R1–R3, Gates A–D, Evidence und Fehlerbudget sind KI-ausführbar beschrieben;
- [x] `SYSTEM_MAP.md` ist vorhanden;
- [x] `CURRENT_WORKPACK.md` ist der einzige aktuelle technische Status;
- [x] PR-Vorlage erzwingt Risiko- und Evidence-Angaben;
- [x] externe Ressourcenmatrix ist an das Arbeitsmandat und R3 angepasst;
- [x] Project Guardrails validieren das neue Modell;
- [x] ausführliche Begründung liegt als dauerhafte Entscheidung unter `docs/decisions/`;
- [ ] PR #87 nach `staging` integrieren;
- [ ] PR #86 als superseded schließen;
- [x] WP-2 ist nur geplant, nicht begonnen.

## Nächster erlaubter Schritt

1. PR #87 nach finalem Ready-for-review-Gate in `staging` integrieren.
2. PR #86 schließen.
3. Danach kein technischer Folgepatch, bis der Nutzer WP-2 ausdrücklich startet.

## Nächste geplante Workpacks

1. **WP-2 – Runtime-Truth und read-only Preflight**  
   E3 herstellen; keine Writeränderung und keine externe Mutation.
2. **Entscheidungsgate nach WP-2**  
   Reale Root Cause und kleinsten notwendigen Writeback-Umbau bestimmen.
3. **WP-3 – Minimaler robuster Writeback**  
   Umfang erst nach E3 festlegen.
4. **WP-4 – Isolierter E4-Test und CityArt-Abschluss**  
   Synthetischer Test vor genau einem echten CityArt-Versuch.