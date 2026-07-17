# KI-Arbeitsmodell und Runtime-Verlaesslichkeit

Stand: 2026-07-17  
Status: analysierter Zielzustand; technische Umsetzung gesperrt, bis diese Workpacks bewusst gestartet werden  
Basis: `staging` auf `1eb2b796db78f88abbbeca1e7a9118c278a9b119`

## 1. Anlass und belastbare Schlussfolgerung

Die zuletzt eingefuehrten Guardrails waren nicht nutzlos. Sie haben reale Risiken reduziert:

- geschuetzte Integration ueber PRs;
- stabiler `PR Gate`;
- keine Feature-Branch-Deploys;
- explizite Branch-/Deploy-Aufloesung;
- Code- und Ressourcen-Locks;
- sequenzielle Staging-Integration.

Der CityArt-Vorfall zeigt jedoch, dass diese Guardrails nur die Git-, PR- und Deploy-Ebene absichern. Sie beweisen nicht, welcher Backendpfad auf dem realen Server ausgefuehrt wird und ob eine verteilte Schreibaktion vollstaendig oder nur teilweise abgeschlossen wurde.

Belegt ist:

1. Ein Staging-Klick erzeugte unerwartet eine unvollstaendige CityArt-Zeile im gemeinsamen Tab `Events`, waehrend `Inbox_Staging` auf `review` blieb.
2. Die unbeabsichtigte Zeile wurde eindeutig identifiziert und vollstaendig zurueckgerollt.
3. Nach Einfuehrung von `Events_Staging` blieb ein weiterer Staging-Klick ohne Sheet-Mutation, lieferte aber erneut die alte Writeback-Fehlermeldung.
4. Statische Tests, PR-Checks und ein gruener Deploy konnten den tatsaechlich ausgefuehrten Runtimepfad nicht sichtbar machen.
5. Der Nutzer wurde dadurch erneut als technisches Testinstrument eingesetzt, obwohl der kritische Pfad vorher automatisiert oder mindestens read-only beobachtbar haette sein muessen.

Die Root Cause der ineffizienten Arbeitsweise ist daher eine Kombination aus:

- einem zu komplexen Standard-Chatmodell;
- fehlender Trennung von statischem Nachweis und realer Runtime-Evidence;
- unzureichender Runtime-Observability;
- einem nicht atomaren, ueber mehrere Systeme verteilten Writeback;
- einer zu stark verteilten Dokumentations- und Systemuebersicht.

## 2. Grundentscheidung zum Chatmodell

### 2.1 Neuer Standard

Der Standard fuer dieses Projekt ist kuenftig:

```text
Ein primaerer Ausfuehrungs-Chat
+ genau ein aktiver Schreibbranch / Draft-PR
+ optionale read-only Zweitpruefung bei hohem Risiko
```

Der bisherige Standard aus Integrations-Chat plus ein oder zwei schreibenden Workpack-Chats wird nicht als Normalmodell fortgefuehrt.

Begruendung:

- Das Projekt wird im Wesentlichen von einem Nutzer und einer KI bearbeitet.
- Der Nutzer soll nicht mehrere Chats, Branches, Locks und Zwischenstaende manuell orchestrieren muessen.
- Die meisten relevanten Systeme sind technisch oder fachlich gekoppelt.
- Der Koordinationsaufwand paralleler Schreibarbeit uebersteigt in diesem Projekt meist den Zeitgewinn.
- Ein Chat kann Analyse, Implementierung und Integration uebernehmen, wenn die Phasen und Beweisgates technisch klar getrennt bleiben.

### 2.2 Parallelitaet bleibt Ausnahme

Parallele Chats sind nur sinnvoll, wenn mindestens eine der folgenden Bedingungen erfuellt ist:

- ein zweiter Chat arbeitet ausschliesslich read-only als unabhaengiger Reviewer;
- zwei Workpacks besitzen nachweisbar keine gemeinsamen Owner-Dateien, Runtimepfade, Deploys oder externen Ressourcen;
- die Zeitersparnis ist konkret groesser als der Koordinationsaufwand.

Regeln:

- standardmaessig nur ein schreibender Chat;
- maximal ein optionaler read-only Review-Chat bei Daten-, Writeback-, Deploy- oder Architekturarbeit;
- der Nutzer muss keine Chatkoordination entwerfen;
- der primaere Chat entscheidet, ob eine Zweitpruefung fachlich notwendig ist, und liefert dafuer genau einen kopierbaren Reviewauftrag;
- keine zwei schreibenden Chats am selben Produkt- oder Runtimepfad.

### 2.3 Kein separater Integrations-Chat als Pflicht

Integration ist kuenftig eine Phase im primaeren Chat, keine zwingend separate Unterhaltung.

Der primaere Chat darf einen eigenen PR erst integrieren, wenn alle dokumentierten Beweisgates erfuellt sind. Ein unabhaengiger Review kann bei hohem Risiko vorgeschaltet werden, aber der Nutzer muss nicht dauerhaft zwischen drei Unterhaltungen wechseln.

## 3. Verbindlicher Arbeitsablauf

Jeder Workpack durchlaeuft dieselben Phasen. Eine Phase darf erst verlassen werden, wenn ihr Exit-Gate belegt ist.

### Phase 0 – Orientierung

Pflicht:

- aktueller `staging`-SHA;
- offene PRs;
- betroffene Owner-Dateien;
- externe Ressourcen;
- vorhandene Runtime- und Testmoeglichkeiten;
- klarer Nutzerauftrag.

Exit-Gate: Der Scope und alle bekannten Abhaengigkeiten sind sichtbar.

### Phase 1 – Evidence und Root Cause

Pflicht:

- belegte Fakten von Hypothesen trennen;
- Datenfluss und Runtimepfad rekonstruieren;
- fehlende Beobachtbarkeit ausdruecklich benennen;
- keine funktionale Aenderung bei ungeklärter Mutation.

Exit-Gate: Root Cause ist belegt oder die fehlende Evidence wird zuerst als eigener Observability-Workpack geplant.

### Phase 2 – Zielentwurf und Workpack-Vertrag

Pflicht:

- kleinster nachhaltiger Zielzustand;
- erlaubte und gesperrte Pfade;
- Definition of Done;
- automatisierbare Beweise;
- benoetigte Nutzeraktionen;
- Rollback.

Exit-Gate: Der Patch kann ohne weitere Architekturannahmen umgesetzt werden.

### Phase 3 – Implementierung

Pflicht:

- eigener Branch und Draft-PR;
- nur deklarierter Scope;
- keine externe Mutation;
- keine parallele Implementierung am selben Ownerpfad.

Exit-Gate: Diff ist vollstaendig und lokal beziehungsweise in CI pruefbar.

### Phase 4 – Statische und simulierte Validierung

Moegliche Beweise:

- Syntax;
- Unit-/Contract-Tests;
- Replay/Fixtures;
- Build;
- PR-Checks.

Exit-Gate: Der Code ist intern konsistent. Diese Phase beweist noch keine reale Runtimeausfuehrung.

### Phase 5 – Deployed Runtime-Preflight

Pflicht bei Backend, Writeback, Environment, Deploy, Cache oder externen Ressourcen:

- deployter Build-SHA;
- tatsaechlicher Host;
- aufgeloeste Runtimeumgebung;
- ausgefuehrte Endpointversion;
- ausgewaehlter Writer;
- Quell- und Zielressourcen;
- Dry-Run beziehungsweise read-only Operationsplan.

Exit-Gate: Die reale Staging-Runtime zeigt vor einer Mutation exakt den erwarteten Pfad.

### Phase 6 – Isolierter automatisierter E2E-Beweis

Pflicht fuer Schreibprozesse:

- synthetische, eindeutig identifizierte Testdaten;
- isolierte Staging-Ressourcen;
- Schreiben, Ruecklesen und Cleanup automatisiert;
- Nachweis, dass Live-Ressourcen unveraendert blieben;
- sicherer Retry nach Teilfehler.

Exit-Gate: Der Gesamtprozess ist ohne echten Fachdatensatz bewiesen.

### Phase 7 – Reale Staging-Abnahme

Erst jetzt:

- genau eine kontrollierte Aktion am echten Staging-Fall;
- unmittelbare Ruecklesepruefung aller beteiligten Systeme;
- Nutzer prueft nur noch visuelle oder fachliche Qualitaet, nicht technische Infrastruktur.

Exit-Gate: Fachlicher Zielzustand und technische Postconditions sind vollstaendig belegt.

### Phase 8 – Abschluss

Pflicht:

- Dokumentation aktualisiert;
- PR/Branchstatus geklaert;
- offene Hypothesen entfernt oder geparkt;
- kein unerklaerter technischer Restzustand;
- naechster Workpack eindeutig benannt.

## 4. Evidence-Stufen

Jede technische Aussage muss kuenftig erkennen lassen, welche Beweisstufe erreicht ist.

| Stufe | Beweis | Erlaubte Aussage |
|---|---|---|
| E0 | Vermutung oder Modell | nur Hypothese |
| E1 | aktueller Repo-Code / statischer Diff | Code enthaelt die Logik |
| E2 | Unit-, Contract-, Replay- oder CI-Test | Logik funktioniert in der Testumgebung |
| E3 | deployte read-only Runtime-Evidence | Server fuehrt erwarteten Build und Pfad aus |
| E4 | isolierter automatisierter Staging-Write | realer technischer Schreibprozess funktioniert |
| E5 | echter fachlicher Staging-Fall | fachlicher Gesamtprozess ist abgenommen |
| E6 | read-only Live-Smoke | Produktivstand ist erreichbar und unveraendert |

Verbindliche Regel:

- Ein gruener PR beweist maximal E2.
- Ein gruener Deploy beweist ohne Runtime-Diagnose nur Auslieferung/Erreichbarkeit, nicht den gewaehlten Schreibpfad.
- Ein echter Nutzerklick darf bei Hochrisikopfaden erst nach E4 verlangt werden.

## 5. Nutzerrolle

Die KI uebernimmt standardmaessig:

- Repo- und PR-Analyse;
- Branch- und Draft-PR-Arbeit;
- Code- und Dokumentationsaenderungen;
- CI- und Logauswertung;
- read-only Sheet-/Datenpruefungen;
- Vorbereitung und Auswertung von Runtime-Diagnosen;
- automatisierte Testdaten und Cleanup, soweit technisch zulaessig;
- konsolidierte Dokumentation.

Der Nutzer wird nur benoetigt fuer:

- Berechtigungen oder UI-Aktionen, die der KI technisch nicht zur Verfuegung stehen;
- Secrets oder organisatorische Freigaben;
- echte visuelle/fachliche Produktentscheidung;
- einen kontrollierten Realtest, nachdem der technische Pfad automatisiert bewiesen wurde.

Interaktionsregeln:

- Nutzeraufgaben gebuendelt statt schrittweise nachgereicht;
- immer genau begruenden, warum die KI den Schritt nicht selbst ausfuehren kann;
- keine Wiederholung einer fehlgeschlagenen Schreibaktion ohne neue Runtime-Evidence;
- Screenshots nur fuer visuelle Abnahme oder fehlende technische Schnittstelle;
- der Nutzer ist nicht das primaere technische Testinstrument.

## 6. Dokumentations-Zielbild

Die aktuelle Dokumentation enthaelt viele richtige Regeln, ist aber zu lang, teilweise doppelt und fuer einen neuen KI-Chat schwer schnell zu operationalisieren.

Zielhierarchie:

1. `AI_ENTRYPOINT.md` – kurzer Router und verbindlicher Phasenprozess.
2. `docs/architecture/SYSTEM_MAP.md` – Systeme, Datenfluesse, Owner, Umgebungen und externe Ressourcen.
3. `docs/workpacks/active/CURRENT_WORKPACK.md` – genau ein aktiver technischer Workpack mit Phase, Evidence-Stufe, Blockern und naechstem Schritt.
4. `ENGINEERING.md` – dauerhafte technische Guardrails ohne Workflow-Duplikate.
5. `docs/external-resource-matrix.md` – Ressourcen und Schreibgrenzen.
6. `ROADMAP.md` – Produktprioritaeten, nicht technische Incidentsteuerung.
7. `TEST_STATUS.md` – aktueller Proofindex, nicht historischer Erzaehltext.
8. `docs/decisions/` – wenige dauerhafte Architekturentscheidungen.
9. `docs/archive/` – historische Forensik und abgeloeste Arbeitsmodelle.

Es soll nur eine aktive technische Steuerdatei geben. PR-Beschreibung und `CURRENT_WORKPACK.md` duerfen sich ergaenzen, aber nicht widersprechen.

## 7. Sequenzielle Workpackages

Die folgenden Workpackages werden strikt nacheinander umgesetzt. Keine parallele schreibende Arbeit in den betroffenen Pfaden.

### WP-1 – Arbeitsmodell vereinfachen und Projektsicht konsolidieren

Ziel:

Ein primaerer Chat kann das Projekt ohne manuelle Mehrchat-Orchestrierung sicher und schnell fuehren.

Scope:

- `AI_ENTRYPOINT.md` auf das Ein-Chat-Standardmodell und die acht Phasen umstellen;
- `ENGINEERING.md` von Prozessduplikaten bereinigen;
- `.github/pull_request_template.md` um Evidence-Stufe und Runtime-Preflight ergaenzen;
- `docs/architecture/SYSTEM_MAP.md` anlegen;
- `docs/workpacks/active/CURRENT_WORKPACK.md` als einzige aktive technische Steuerdatei einfuehren;
- historische oder doppelte Prozessdokumentation kennzeichnen beziehungsweise archivieren;
- PR #86 als superseded schliessen, sobald seine Evidence in den Folgeworkpack uebernommen wurde.

Nicht enthalten:

- keine Runtimeaenderung;
- keine externe Mutation;
- keine CityArt-Uebernahme.

Definition of Done:

- Ein neuer Chat benoetigt maximal die kanonischen Einstiegsdateien und den aktuellen PR-Stand.
- Standard ist ein primaerer Chat und ein aktiver Schreib-PR.
- Optionaler Review ist read-only und klar begrenzt.
- Keine widerspruechlichen Arbeitsregeln zwischen den kanonischen Dateien.

### WP-2 – Runtime-Wahrheit und read-only Preflight

Ziel:

Vor jeder kritischen Aktion ist auf der real deployten Umgebung sichtbar, welcher Code und welche Ressourcen verwendet wuerden.

Scope:

- unveraenderlicher Runtime-Buildmarker;
- authentifizierte read-only Diagnose fuer:
  - Build-SHA;
  - Host und Requestpfad;
  - konfigurierte und aufgeloeste Umgebung;
  - Endpointversion;
  - Quelltab und Zieltab;
  - ausgewaehlter Writer;
  - Fallherkunft;
- Dry-Run/Preflight fuer Inbox-Entscheidungen ohne Mutation;
- Response-Metadaten fuer spaetere Writeback-Operationen;
- automatischer Staging-Smoke gegen diese Diagnose.

Nicht enthalten:

- keine fachliche Freigabe;
- keine neue Writerlogik;
- keine reale Sheetmutation.

Definition of Done:

- Die KI kann E3 ohne Nutzerklick erreichen.
- Ein falscher Host-, Build-, Environment- oder Ressourcenzustand blockiert vor einer Mutation.
- Ein Preflight zeigt eindeutig `Inbox_Staging -> Events_Staging` oder stoppt.

### WP-3 – Einen einheitlichen transaktionalen Writeback bauen

Ziel:

Staging und Live verwenden denselben nachvollziehbaren Writeback-Kern mit unterschiedlichen gebundenen Ressourcen.

Scope:

- Apps-Script- und direkter Writer werden nicht als zwei unterschiedliche fachliche Prozesse weitergefuehrt;
- ein serverseitiger Writeback-Service mit Umgebungskonfiguration;
- persistierte Operationsstufen, zum Beispiel:
  - `requested`;
  - `event_written`;
  - `event_verified`;
  - `inbox_written`;
  - `inbox_verified`;
  - `local_case_closed`;
  - `completed`;
- idempotenter Retry ab der letzten bestaetigten Stufe;
- Quell- und Zielressourcen werden beim Start unveraenderlich an die Operation gebunden;
- Teilfehler sind technisch sichtbar und sicher fortsetzbar;
- Dry-Run und echte Ausfuehrung nutzen denselben Planer.

Nicht enthalten:

- kein echter CityArt-Test;
- kein Live-Schreibtest.

Definition of Done:

- Kein `ok` bei Teilmutation.
- Kein Duplikat bei Retry.
- Kein Stagingzugriff auf Live-Schreibressourcen.
- Vollstaendige Unit-, Contract- und Replay-Nachweise auf E2.

### WP-4 – Automatisierter isolierter E2E-Beweis und CityArt-Abschluss

Ziel:

Der reale technische Gesamtprozess wird bewiesen, bevor ein Nutzerfachfall erneut verwendet wird.

Scope:

- synthetischer, eindeutig benannter Testkandidat;
- isolierter Staging-Testpfad;
- automatisierter Ablauf:
  - Testdaten anlegen;
  - Preflight pruefen;
  - Writeback ausfuehren;
  - Events-Ziel ruecklesen;
  - Inbox-Status ruecklesen;
  - lokalen Fallstatus pruefen;
  - generierten Staging-Feed pruefen;
  - Testdaten vollstaendig entfernen;
  - Live-Ressourcen auf Unveraendertheit pruefen;
- danach genau ein kontrollierter CityArt-Versuch;
- Abschlusspruefung und Incident-Schliessung.

Definition of Done:

- automatisierter E4-Nachweis ohne Nutzer;
- genau ein erfolgreicher fachlicher E5-Nachweis;
- Live bleibt unveraendert;
- keine manuelle Sheetkorrektur fuer einen gruenen Test;
- CityArt verschwindet korrekt aus der offenen Pruefung und erscheint vollstaendig im Staging-Feed.

## 8. Reihenfolge und Parallelitaet

Verbindliche Reihenfolge:

```text
WP-1
-> stabiler Dokumentations- und Arbeitsstand
-> WP-2
-> E3-Nachweis
-> WP-3
-> E2-Nachweis des transaktionalen Writers
-> WP-4
-> E4 und E5
```

Keine parallele schreibende Arbeit an:

- Control-Center-Writeback;
- Environment-/Hostaufloesung;
- Deploy-Workflow;
- Inbox-/Events-Ressourcen;
- kanonischen Prozessdokumenten.

Ein optionaler read-only Review-Chat ist bei WP-3 sinnvoll. Er darf keine Branches, Daten oder PRs veraendern.

## 9. Aktueller Freeze

Bis zum bewussten Start von WP-1 gilt:

- PR #86 bleibt Draft und wird nicht gemergt;
- kein weiterer CityArt-Uebernahmeversuch;
- keine manuelle Statuskorrektur;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine weiteren Hypothesenpatches im Writeback-/Environment-Scope;
- nur read-only Analyse und Dokumentation.

Aktueller sicherer Datenzustand:

- CityArt steht in `Inbox_Staging` weiterhin auf `review`;
- `Events_Staging` enthaelt keine CityArt-Zeile;
- `Events` enthaelt keine CityArt-Zeile aus dem Staging-Versuch;
- Live wurde nach dem Rollback nicht weiter veraendert.

## 10. Gesamt-Definition-of-Done

Das Programm ist erst abgeschlossen, wenn:

- der Nutzer standardmaessig nur einen primaeren Chat fuehren muss;
- die KI den aktuellen Projekt- und Runtimezustand ohne wiederholte Uploads rekonstruieren kann;
- statische, deployte und schreibende Evidence klar getrennt sind;
- kritische Aktionen vorab als Dry-Run sichtbar sind;
- reale Schreibprozesse automatisiert mit synthetischen Daten getestet werden;
- Teilfehler sicher fortsetzbar statt widerspruechlich sind;
- der Nutzer nur noch fuer echte visuelle oder fachliche Abnahme benoetigt wird;
- CityArt ohne Live-Nebenwirkung erfolgreich auf Staging uebernommen wurde.
