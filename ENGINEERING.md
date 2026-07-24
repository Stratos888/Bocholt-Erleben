# ENGINEERING – Bocholt erleben

Diese Datei enthält dauerhafte technische Regeln. Arbeitsablauf, Werkzeugwahl, Nutzerartefakte und Dokumentations-Reconciliation stehen in `AI_ENTRYPOINT.md`. Operativer Workpack-Status und Zwischen-Evidence stehen im zuständigen GitHub-Issue.

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

## 4. Dauerhafte Workflowrollen

1. `PR Gate` – einziger Entwicklungs- und Integrationscheck;
2. `Deploy to STRATO` – einziger Feed-Build- und Deploypfad;
3. `Publish Deploy Run Status` – passiver Commitstatus für Run-Auffindbarkeit und Ergebnis, ohne Deploy oder Fachwrite;
4. `Content Quality Audit` – fachlicher Inhaltsaudit;
5. `Growth Intelligence Backlog` – fachliche Growth-/SEO-Signale;
6. `Inbox Cleanup (Archive)` – fachliche Inbox-Pflege;
7. `Weekly KI Websearch → Manual Inbox` – fachliche Kandidatensuche;
8. `Manual KI Event Intake` – fachlicher Intake-Handoff.

Neue Workflows sind nur zulässig, wenn keine bestehende Rolle die Aufgabe übernehmen kann und der Workflow dauerhaft produktiv gebraucht wird.

Ein passiver Observability-Workflow ist nur zulässig, wenn er einen sonst nicht zuverlässig erreichbaren technischen Zustand sichtbar macht, keine Fachressource verändert, keinen Deploy auslöst, minimale Rechte besitzt und im Workflow-Inventar registriert ist. `Publish Deploy Run Status` ist die einzige derzeit zugelassene Rolle dieser Art.

Reine Aggregatoren, zusätzliche Deploy-Trigger, temporäre Observer, Governance-Workflows und einmalige Testharnesses gehören nicht in die dauerhafte Topologie.

## 5. Tests und Evidence

- Tests beweisen Verhalten, nicht nur Dateinamen oder Kommentare.
- Der PR besitzt genau einen Required Check: `PR Gate`.
- `scripts/validate-repo.sh` ist der zentrale lokale und CI-Einstieg für Syntax-, Unit- und Contracttests.
- Fachlich notwendige Tests bleiben erhalten, auch wenn frühere Top-Level-Workflows entfernt werden.
- Ein temporärer Runtime-Test wird nach dem Nachweis auf einen lokalen Contracttest reduziert oder entfernt.
- Ein roter Test wird vor dem Merge behoben; reale Try-and-Error-Schleifen nach dem Merge sind ausgeschlossen.
- Build-, Render- und Generatoränderungen werden gegen den tatsächlich erzeugten Output geprüft.
- UI-Änderungen benötigen vor dem Staging-Merge Browser- oder Screenshot-Evidence für die vereinbarten Viewports.
- Progressive Enhancement wird bei relevantem Scope mit und ohne JavaScript geprüft.
- Evidence gehört in PR, Action oder Workpack-Issue; sie wird nicht in mehreren Statusdateien dupliziert.

### Artifact-Grenze

- Actions-Artefakte bleiben interne maschinelle Evidence.
- Chat oder Codex liest sie nur, wenn Summary und Logs nicht genügen.
- Nutzerseitig werden Ergebnisse berichtet, nicht automatisch ZIP-Dateien oder Downloadlinks geliefert.
- Screenshots werden nicht auf Vorrat produziert.

## 6. Deploy- und Releasebudget

- Nur `staging` und `main` dürfen deployen.
- Normalfall: ein Implementierungs-PR nach `staging`, ein normaler Staging-Deploy, ein gezielter Smoke, ein Release-PR nach `main`, ein normaler Main-Deploy und ein Live-Smoke.
- Ein Merge nach `staging` erzeugt genau einen normalen Deploy, sofern der Workflow für den geänderten Pfad ausgelöst wird.
- Keine zusätzlichen Deploys oder synthetischen Folge-Deploys ohne konkret unbelegtes Risiko.
- `Publish Deploy Run Status` beobachtet ausschließlich vorhandene Runs und erzeugt keinen Deploy.
- Der Deploy bleibt fail-fast, wenn Sheet-Export, Feed-Build oder Upload scheitert.
- Nach erfolgreichem Deploy genügt der enthaltene Build-/HTTP-/Browser-Smoke, sofern kein konkret nicht abgedecktes Risiko besteht.
- Korrekturen vor dem Staging-Merge bleiben im selben Feature-Branch und PR.
- Nach dem Staging-Merge ist höchstens eine klar begründete Korrektur zulässig.
- Scheduled Deploy bleibt notwendig, damit Sheet-basierte Eventdaten ohne Codeänderung veröffentlicht werden.

## 7. UI, CSS und Inhalte

- Komponenten stylen sich selbst; Layoutdateien platzieren sie.
- Token-first, keine dauerhaften Override-Ketten.
- Bild-, Content- oder Datenprobleme werden upstream gelöst, nicht per CSS kaschiert.
- Eventbeschreibungen folgen `EVENT_DESCRIPTION_STANDARD.md`.
- KI-Ausgaben überschreiben fachliche Quellen nie ungeprüft.
- Vor UI-Umsetzung muss feststehen, welche sichtbaren Änderungen zulässig sind und welche Bereiche unverändert bleiben.
- Ist keine sichtbare Produktänderung Teil des Ziels, darf ein technischer Patch keine neue sichtbare Oberfläche einführen.

## 8. Git- und Schreibdisziplin

- Standard: ein Workpack -> genau ein Repository-Schreiber -> ein Feature-Branch -> ein PR nach `staging` -> später `staging -> main`.
- Chat darf kleine vollständig kontrollierbare Text-/Konfigurationsänderungen schreiben; Codex ist bevorzugt für Code, große Diffs und patchintensive Owner.
- Kein Force-Push und kein Force-Reset.
- Keine Feature-Branch-Deploys.
- Keine Secrets oder Zugangsdaten im Repository oder Chat.
- Datei- und Workflowlöschungen sind erwünscht, wenn der Pfad nicht mehr gebraucht wird und Git die Historie bewahrt.
- Keine neue große Änderung auf einen ungeklärten Zwischenstand stapeln.
- Dieselbe Ursache erhält vor dem Staging-Merge keinen zweiten Branch, PR oder Schreiber.

## 9. Dokumentationsrollen

- `AI_ENTRYPOINT.md` – Arbeitsmodell, Werkzeugwahl, Nutzerartefakte und Reconciliation;
- `AGENTS.md` – kurzer Codex-Router;
- `CURRENT_WORKPACK.md` – stabiler Router zum operativen GitHub-Issue;
- GitHub-Issue – operativer Status, Entscheidungen, Evidence und nächster Schritt;
- `SYSTEM_MAP.md` – Systeme, Datenflüsse und technische Owner;
- `ENGINEERING.md` – technische Regeln und Workflowrollen;
- `external-resource-matrix.md` – externe Ressourcen;
- `MASTER.md`, `Produktvertrag.md`, `COMMERCIAL_STRATEGY.md` – Produktvertrag;
- `ROADMAP.md` – Produktprioritäten und Kandidaten;
- `TEST_STATUS.md` – dauerhafte Prooffähigkeiten und Evidence-Grenzen.

Regeln:

- keine Abschlusschronik während der Umsetzung;
- Repository-Dokumentation einmal und nur bei dauerhaftem Wissensdelta;
- Roadmap nur bei echter Prioritäts- oder Zieländerung;
- `TEST_STATUS.md` nur bei dauerhafter Änderung von Testabdeckung oder Evidence-Grenze;
- veraltete aktuelle Aussagen werden ersetzt; Git enthält die Historie;
- kein zusätzliches Dokumentregister und kein Dokumentations-Governance-Workflow;
- vor Issue-Abschluss wird die Owner-Matrix aus `AI_ENTRYPOINT.md` geprüft.
