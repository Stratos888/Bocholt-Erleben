# ENGINEERING – Bocholt erleben

Diese Datei enthält nur dauerhafte technische Regeln. Arbeitsablauf und Werkzeugwahl stehen in `AI_ENTRYPOINT.md`. Operativer Workpack-Status und Zwischen-Evidence stehen im zuständigen GitHub-Issue.

## 1. Quellen der Wahrheit

- `staging` ist die Entwicklungsbaseline.
- `main` ist der Live-Releasebranch.
- Google Sheet `Events` ist die redaktionelle Live-Quelle; `Events_Staging` ist das Staging-Overlay.
- Live-Inbox: `Inbox`; Staging-Inbox: `Inbox_Staging`.
- Generierte Dateien wie `data/events.tsv`, `data/events.json` und `data/inbox.json` sind Buildartefakte, keine manuell gepflegten Quellen.
- Kein Patch ohne aktuellen Inhalt der owning Datei.
- Bei einem aktiven Workpack ist das zugehörige GitHub-Issue der einzige operative Status- und Evidence-Owner.

## 2. Owner statt Schichten

1. bestehenden Owner bestimmen;
2. Ursache dort beheben;
3. konkurrierende oder ersetzte Pfade entfernen;
4. nur notwendige Guards ergänzen.

Nicht akzeptiert werden:

- Wrapper auf Wrapper;
- mehrere gleichwertige Writer oder Resolver;
- zusätzliche Observer ohne eigene dauerhafte Aufgabe;
- One-off-Workflows, die nach ihrem Nachweis im Repository bleiben;
- statische Markerprüfungen ohne fachlichen oder technischen Nutzen;
- parallele Statusführung in mehreren Dokumenten.

Zentrale Owner werden seriell geändert: `.github/workflows/**`, Deploy, Environment, Schema, globale CSS-/JS-Entrypoints und kanonische Steuerdokumente.

## 3. Environment- und Datenisolation

```text
staging: Inbox_Staging -> Events_Staging
live:    Inbox         -> Events
```

- Branch, Host, Konfiguration, Quell- und Zielressource müssen ein konsistentes Tupel bilden.
- Staging darf keine Live-Ressource beschreiben.
- Live-Schreibtests sind ausgeschlossen.
- Externe Writes benötigen stabile Identität, Vorherzustand, begrenzte Mutation, Rücklesen und Rollback/Cleanup.
- Beim ersten unerwarteten realen Verhalten wird nicht erneut geschrieben.

## 4. Workflows

Dauerhafte Workflowrollen:

1. `PR Gate` – einziger Entwicklungs- und Integrationscheck;
2. `Deploy to STRATO` – einziger Deploy- und Feed-Build-Pfad;
3. `Content Quality Audit` – fachlicher Inhaltsaudit;
4. `Growth Intelligence Backlog` – fachliche Growth-/SEO-Signale;
5. `Inbox Cleanup (Archive)` – fachliche Inbox-Pflege;
6. `Weekly KI Websearch → Manual Inbox` – fachliche Kandidatensuche;
7. `Manual KI Event Intake` – fachlicher Intake-Handoff.

Neue Workflows sind nur zulässig, wenn keine bestehende Rolle die Aufgabe übernehmen kann und der Workflow dauerhaft produktiv gebraucht wird. Reine Aggregatoren, Observer, Governance-Workflows und einmalige Testharnesses gehören nicht in die dauerhafte Topologie.

## 5. Tests und Evidence

- Tests beweisen Verhalten, nicht Dateinamen oder Kommentare.
- Der PR besitzt genau einen Required Check: `PR Gate`.
- `scripts/validate-repo.sh` ist der zentrale lokale und CI-Einstieg für Syntax-, Unit- und Contracttests.
- Fachlich notwendige Tests bleiben erhalten, auch wenn ihre früheren Top-Level-Workflows entfernt werden.
- Ein temporärer Runtime-Test wird nach dem Nachweis auf einen kleinen lokalen Contract-Test reduziert oder vollständig entfernt.
- Ein roter Test wird vor dem Merge behoben; reale Try-and-Error-Schleifen nach dem Merge sind ausgeschlossen.
- Build-, Render- und Generatoränderungen werden gegen den tatsächlich erzeugten Output geprüft, nicht nur gegen Eingabevorlagen oder statische Marker.
- UI-Änderungen benötigen vor dem Staging-Merge einen Browser- oder Screenshotnachweis für die vereinbarten Viewports.
- Progressive Enhancement wird bei relevantem Scope mit und ohne JavaScript geprüft.
- Evidence gehört in PR, Action oder Workpack-Issue; sie wird nicht in mehreren Statusdateien dupliziert.

## 6. Deploy- und Releasebudget

- Nur `staging` und `main` dürfen deployen.
- Normalfall: ein Implementierungs-PR nach `staging`, ein normaler Staging-Deploy, ein gezielter Smoke, ein Release-PR nach `main`, ein normaler Main-Deploy und ein Live-Smoke.
- Ein Merge nach `staging` erzeugt genau einen normalen Deploy.
- Keine zusätzlichen Deploy-Observer oder synthetischen Folge-Deploys.
- Der Deploy bleibt fail-fast, wenn Sheet-Export, Feed-Build oder Upload scheitert.
- Nach einem erfolgreichen Deploy genügt der enthaltene Build-/HTTP-Smoke; zusätzliche Runtimeverification wird nur für ein konkret nicht abgedecktes Risiko temporär und aufgabenbezogen ausgeführt.
- Korrekturen vor dem Staging-Merge bleiben im selben Feature-Branch und PR.
- Nach dem Staging-Merge ist höchstens eine klar begründete Korrektur zulässig. Danach wird gestoppt und Ziel oder Architektur neu entschieden.
- Scheduled Deploy bleibt notwendig, damit Sheet-basierte Eventdaten ohne Codeänderung veröffentlicht werden.

## 7. UI, CSS und Inhalte

- Komponenten stylen sich selbst; Layoutdateien platzieren sie.
- Token-first, keine dauerhaften Override-Ketten.
- Bild-, Content- oder Datenprobleme werden upstream gelöst, nicht per CSS kaschiert.
- Eventbeschreibungen folgen `EVENT_DESCRIPTION_STANDARD.md`.
- KI-Ausgaben überschreiben fachliche Quellen nie ungeprüft.
- Vor UI-Umsetzung muss ausdrücklich feststehen, welche sichtbaren Änderungen zulässig sind und welche Bereiche unverändert bleiben.
- Ist keine sichtbare Produktänderung Teil des Ziels, darf ein technischer Patch keine neue sichtbare Oberfläche einführen.

## 8. Git- und Schreibdisziplin

- Standard: genau ein Codex-Task -> ein Feature-Branch -> ein PR nach `staging` -> später `staging -> main`.
- Kein Force-Push und kein Force-Reset.
- Keine Feature-Branch-Deploys.
- Keine Secrets oder Zugangsdaten im Repository oder Chat.
- Datei- und Workflowlöschungen sind erwünscht, wenn der Pfad nicht mehr gebraucht wird und Git die Historie bewahrt.
- Keine neue große Änderung auf einen ungeklärten Zwischenstand stapeln.
- Dieselbe Ursache erhält vor dem Staging-Merge keinen zweiten Branch, PR oder Codex-Task.

## 9. Dokumentation

Kanonische Rollen:

- `AI_ENTRYPOINT.md` – Arbeitsmodell und Werkzeugwahl;
- `AGENTS.md` – kurzer Codex-Router;
- `CURRENT_WORKPACK.md` – stabiler Router zum operativen GitHub-Issue;
- GitHub-Issue – operativer Status, Entscheidungen, Evidence und nächster Schritt;
- `SYSTEM_MAP.md` – Systeme und Datenflüsse;
- `ENGINEERING.md` – technische Regeln;
- `external-resource-matrix.md` – externe Ressourcen;
- `MASTER.md` und `ROADMAP.md` – Produktziel und Prioritäten;
- Workpack-Datei – dauerhafter Scope, Wahrheitsvertrag und Definition of Done.

Regeln:

- keine Abschlussdokumentation während der Umsetzung;
- Repository-Dokumentation einmal am Ende und nur bei dauerhaftem Wissensdelta;
- Roadmap nur bei echter Prioritäts- oder Zieländerung;
- `TEST_STATUS.md` nur bei dauerhafter Änderung von Testabdeckung oder Evidence-Grenze;
- reine Statusdokumentation erzeugt keinen eigenen Releasezyklus;
- veraltete aktuelle Aussagen werden ersetzt; Git enthält die Historie;
- kein zusätzliches Dokumentregister und kein Dokumentations-Governance-Workflow.