# Current Workpack

Stand: 2026-07-17

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** WP-1 – Arbeitsmodell vereinfachen und Projektsicht konsolidieren
- **Status:** Dokumentations- und Guardrail-PR #87 in Arbeit
- **Risikoklasse:** R1
- **Aktuelles Gate:** B – Bauen und statisch beweisen
- **Aktuelle Evidence:** E1
- **Ausgangs-SHA:** `1eb2b796db78f88abbbeca1e7a9118c278a9b119`
- **Branch:** `agent/working-model-redesign-analysis`
- **PR:** #87

## Zielzustand

- ein primärer Ausführungs-Chat als Standard;
- ein aktiver Schreibbranch/Draft-PR;
- R1–R3 und Gates A–D;
- einmaliges Arbeitsmandat statt wiederholter Zwischenfreigaben;
- Runtime-Evidence vor kritischen Nutzeraktionen;
- optionaler read-only Review statt paralleler Schreibarbeit;
- klare Fehlerbudget- und Stop-the-line-Regeln;
- kompakte Systemkarte und eindeutige Dokumentationshierarchie.

## Erlaubter Scope

- `AI_ENTRYPOINT.md`
- `ENGINEERING.md`
- `.github/pull_request_template.md`
- `.github/workflows/project-guardrails.yml`
- `docs/architecture/SYSTEM_MAP.md`
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- `docs/workpacks/active/AI_WORKING_MODEL_AND_RUNTIME_RELIABILITY_2026-07-17.md`
- `docs/decisions/**` für die dauerhafte Arbeitsmodellentscheidung
- `docs/external-resource-matrix.md`, falls nur Querverweise an das neue Modell ergänzt werden

## Gesperrter Scope

- `api/**`
- `js/**`
- `css/**`
- Runtime-, Writeback- und Environment-Logik
- Deploylogik und externe Zielsysteme
- Google-Sheet-Daten
- CityArt-Übernahme

## Externe Ressourcen

- Zugriff: ausschließlich read-only
- `Inbox_Staging`: CityArt bleibt `review`
- `Events_Staging`: kein CityArt-Eintrag
- `Events`: kein CityArt-Eintrag aus dem Staging-Versuch
- keine weitere Schreibprobe erlaubt

## Offene PRs und Locks

- **PR #86:** `SUSPENDED`, nur technische Evidence; nicht mergen und nicht weiterentwickeln.
- **PR #87:** alleiniger aktiver Schreib-PR für WP-1.
- Owner-Lock: kanonische Prozessdokumentation und Project-Guardrail-Marker.

## Definition of Done für WP-1

- [ ] operative Dokumente enthalten keine widersprüchlichen Mehrchat- oder Integrationsregeln;
- [ ] R1–R3, Gates A–D, Evidence und Fehlerbudget sind KI-ausführbar beschrieben;
- [ ] `SYSTEM_MAP.md` ist vorhanden;
- [ ] `CURRENT_WORKPACK.md` ist der einzige aktuelle technische Status;
- [ ] PR-Vorlage erzwingt Risiko- und Evidence-Angaben;
- [ ] Project Guardrails validieren das neue Modell;
- [ ] PR #87 ist geprüft und nach `staging` integriert;
- [ ] PR #86 ist anschließend als superseded geschlossen;
- [ ] WP-2 ist nur geplant, nicht begonnen.

## Nächster erlaubter Schritt

WP-1 vollständig dokumentieren, den reinen Doku-/Guardrail-Diff validieren und PR #87 integrieren. Danach `CURRENT_WORKPACK.md` auf `WP-1 abgeschlossen / WP-2 nicht gestartet` setzen.

## Nächste geplante Workpacks

1. **WP-2 – Runtime-Truth und read-only Preflight**  
   E3 herstellen; keine Writeränderung und keine externe Mutation.
2. **Entscheidungsgate nach WP-2**  
   Reale Root Cause und kleinsten notwendigen Writeback-Umbau bestimmen.
3. **WP-3 – Minimaler robuster Writeback**  
   Umfang erst nach E3 festlegen.
4. **WP-4 – Isolierter E4-Test und CityArt-Abschluss**  
   Synthetischer Test vor genau einem echten CityArt-Versuch.