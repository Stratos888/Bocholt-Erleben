# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`

Diese Datei ist der verbindliche Arbeitsrouter. Ziel ist die schnellste zuverlässige KI-Arbeitsweise – nicht maximale Prozess- oder Testinfrastruktur.

## 1. Start jeder Repo-Aufgabe

1. aktuellen `staging`-SHA und offene PRs in GitHub prüfen;
2. `docs/workpacks/active/CURRENT_WORKPACK.md` lesen;
3. `docs/architecture/SYSTEM_MAP.md` und nur die betroffenen Owner-Dateien lesen;
4. bei externen Daten zusätzlich `docs/external-resource-matrix.md` lesen;
5. erst danach analysieren oder patchen.

Alte Chats, Memory, ZIPs und historische Dokumente sind Kontext. Der aktuelle Ref und die aktuellen Owner-Dateien sind die Source of Truth.

## 2. Verbindlicher Arbeitsmodus

```text
erste KI-Instanz
-> Ziel und Aufgabengröße automatisch ermitteln
-> klein: Chat führt
-> großer Workpack: Work orchestriert
-> Codex ist der technische Repository-Agent
-> Chat prüft und entscheidet
-> genau ein schreibender Agent
-> Feature-Branch -> staging -> main
```

- Standardmäßig gibt es nur einen schreibenden Agenten.
- Eine unabhängige read-only Analyse darf parallel laufen, verändert aber nichts.
- Zwei schreibende Agenten sind nur bei vollständig getrennten Dateien, Workflows und externen Ressourcen zulässig; sie sind nicht der Normalfall.
- Workflow-, Deploy-, zentrale Doku-, Schema-, Environment- und globale UI-Owner werden immer seriell geändert.

## 3. Automatische Zielermittlung

Der Nutzer muss kein technisches Ziel, keinen Workpack und kein Werkzeug auswählen.

### Konkreter Nutzerwunsch

Nennt der Nutzer ein fachliches Ergebnis, Problem oder eine gewünschte Wirkung, übernimmt die führende KI-Instanz dieses Anliegen und ermittelt selbst:

1. den belegten Ausgangszustand;
2. das konkrete operative Ziel;
3. den kleinsten vollständigen Scope;
4. das passende Werkzeug;
5. die erforderlichen Prüfungen;
6. den genau nächsten Schritt.

### Offene Fortsetzungsfrage

Fragt der Nutzer beispielsweise `Wie machen wir weiter?`, `Was ist jetzt sinnvoll?` oder bittet um die beste nächste Maßnahme, ermittelt die führende KI-Instanz das Ziel selbst aus:

- Projektzweck und Produktziel;
- aktuellem `staging`-Stand;
- aktivem Workpack;
- Roadmap und belegten offenen Lücken;
- laufenden Pull Requests und Locks;
- vorhandener Evidence und realem Nutzwert.

Die KI schlägt nicht mehrere gleichwertige Optionen zur Auswahl vor, wenn eine beste Reihenfolge ableitbar ist. Sie nennt genau das empfohlene Ziel und begründet nur echte Unsicherheiten oder notwendige fachliche Entscheidungen.

Ein ausdrücklich genanntes Nutzerziel hat Vorrang vor einer automatisch abgeleiteten Priorität, solange es nicht gegen Sicherheits-, Release- oder Scopegrenzen verstößt.

## 4. Automatische Werkzeugwahl

**Die KI entscheidet, ob Chat, Work oder Codex das geeignete Werkzeug ist. Der Nutzer muss diese Auswahl nicht selbst treffen.**

### Chat

Chat ist die erste Anlaufstelle und führt kleine oder klar begrenzte Aufgaben direkt. Chat übernimmt außerdem:

- die initiale Größen- und Werkzeugentscheidung;
- kurze Analysen und direkte Antworten;
- notwendige fachliche Entscheidungen;
- Screenshot-, Staging- und Live-Abnahmen;
- unabhängige Prüfung von Work- und Codex-Ergebnissen;
- Scope-, Merge- und Releaseentscheidungen;
- den genau nächsten Schritt.

Bei einem großen, mehrstufigen Workpack bleibt Chat nicht die dauerhafte Orchestrierungsebene, sondern übergibt mit einem vollständigen Prompt an Work.

### Work

Work ist die führende Steuerungs- und Orchestrierungsebene für große, klar abgegrenzte Workpacks. Work übernimmt insbesondere:

- den Gesamtauftrag und den Premium-Zielzustand;
- die mehrstufige Planung und Gate-Steuerung;
- fachliche und externe Recherche;
- die Trennung zwischen externem, fachlichem und technischem Scope;
- die Erstellung vollständiger Codex-Aufträge;
- die Zusammenführung der Teilbefunde;
- die Statusführung bis zur Chat-Abnahme.

Work bleibt repositoryseitig standardmäßig read-only und delegiert technische Repository-Arbeit an Codex. Ist Work in der Oberfläche nicht verfügbar, übernimmt Chat diese Orchestrierung transparent als Fallback.

### Codex

Codex ist das Standardwerkzeug für technische Repository-Arbeit:

- Repository- und Ursachenanalyse;
- Analyse von Code, Build, Tests und Datenflüssen;
- Code-, Test- und substanzielle Repository-Dokumentationsänderungen;
- Ausführung von Befehlen und Tests;
- Vorbereitung eines reviewfähigen Pull Requests.

Codex liest zuerst `AGENTS.md` und die dort gerouteten kanonischen Dateien. Codex bestimmt nicht eigenständig einen konkurrierenden Produkt- oder Workpack-Scope, sondern arbeitet innerhalb des von Chat beziehungsweise Work festgelegten Auftrags.

### Verbindliches Routing

Die zuerst angesprochene KI entscheidet ohne Rückfrage, ob sie selbst weiterarbeitet oder an Work beziehungsweise Codex übergibt.

Bei einem Wechsel liefert sie:

1. die nächste Oberfläche: `Chat`, `Work` oder `Codex`;
2. einen kurzen Grund;
3. einen vollständig kopierbaren Startprompt;
4. das erwartete Ergebnis;
5. genau die Informationen oder Dateien, die der Nutzer noch bereitstellen muss.

Sobald Work einen großen Workpack übernommen hat, steuert Work die weiteren Übergaben an Codex und Chat. Der Nutzer wird nicht aufgefordert, selbst zwischen Werkzeugen abzuwägen oder einen technischen Auftrag zu formulieren.

### Keine Doppelarbeit

- Dieselbe Analyse wird nicht parallel in Work und Codex wiederholt.
- Work bearbeitet Gesamtziel, externe Evidenz und Orchestrierung; Codex bearbeitet den eindeutig zugewiesenen Repository-Scope.
- Chat führt eine unabhängige, proportionale Abnahme durch und wiederholt keine vollständige Voranalyse.
- Vor einem schreibenden Codex-Auftrag müssen Ziel, Scope und Akzeptanz durch die führende Instanz eindeutig sein.
- Work-, Codex- und Chatverläufe werden nicht als gegenseitig bekannt vorausgesetzt; jeder Übergabeprompt ist selbstständig ausführbar.

## 5. Arbeitsmandat

Eine eindeutige Anweisung wie `mach das`, `umsetzen`, `patchen` oder `dokumentieren` erlaubt innerhalb des vereinbarten Scopes:

- Analyse und Zieldefinition;
- Branch, Commits und PR;
- automatisierte Tests;
- Merge nach `staging`, wenn `PR Gate` grün ist;
- Prüfung des normalen Staging-Deploys;
- Abschluss der Dokumentation.

Eine neue Freigabe ist nur bei echter Scope-Erweiterung, Live-Schreibaktion, irreversibler Aktion, Zahlung, Nachricht, Veröffentlichung oder Berechtigungsänderung erforderlich.

## 6. Kleinster nachhaltiger Patch

Vor jeder Änderung werden genau vier Fragen beantwortet:

1. Was ist die belegte Ursache oder das konkrete Ziel?
2. Welche Datei oder Komponente besitzt das Verhalten?
3. Was ist der kleinste vollständige Zielzustand?
4. Welche Prüfung beweist genau diesen Zielzustand?

Keine Vollinventur, kein Meta-Workpack und keine neue Workflow-Schicht nur aus Vorsicht. Neue Wrapper, Observer, Guardrail-Dateien oder dauerhafte Testharnesses sind nur zulässig, wenn sie einen bestehenden Pfad ersetzen und dauerhaft gebraucht werden.

## 7. Proportionale Prüfung

### Dokumentation und kleine statische Änderungen

- Diff und Links prüfen;
- `PR Gate` ausführen;
- nach dem Merge keine zusätzliche Sonderverification.

### Code oder Runtime ohne externen Write

- relevante Unit-, Contract- und Syntaxtests im `PR Gate`;
- genau ein normaler Staging-Deploy;
- ein gezielter read-only Smoke für das geänderte Verhalten, falls der Deploy-Smoke es nicht bereits abdeckt.

### Externer Write

- Zielressource und stabile Identität vorher lesen;
- genau einen begrenzten Write ausführen;
- sofort zurücklesen;
- Rollback oder Cleanup sicherstellen;
- beim ersten unerwarteten Verhalten stoppen.

Es gibt kein allgemeines E3-/E4-Programm und keinen eigenen Workflow nur für einen einmaligen Nachweis. Ein einmaliger Testharness wird nach Abschluss entfernt; dauerhafte Sicherung erfolgt als kleiner lokaler Contract-Test.

## 8. Fehlerbudget

```text
unerwartetes Verhalten
-> stoppen
-> Zustand sichern
-> Ursache bestimmen
-> vor dem nächsten Write korrigieren
```

- Keine wiederholten Merge-/Deploy-Schleifen zur Suche nach dem richtigen Design.
- Vor dem Merge darf der Feature-Branch anhand roter Tests korrigiert werden.
- Nach einem roten realen Lauf gibt es höchstens eine klar begründete Korrektur; sonst Revert oder neue Architekturentscheidung.
- Keine manuelle Datenkorrektur zum künstlichen Grünmachen.

## 9. Git- und Releasepfad

- Feature-Branch -> PR nach `staging`.
- Release ausschließlich `staging -> main`.
- Keine Feature-Branch-Deploys.
- Kein Force-Push.
- Keine Secrets im Repo oder Chat.
- Ein normaler Merge nach `staging` erzeugt genau einen Deploy. Zusätzliche Observer- oder Verification-Deploys sind ausgeschlossen.

## 10. Dokumentation

Kanonische Lesereihenfolge:

1. `AGENTS.md`, wenn Codex arbeitet;
2. `AI_ENTRYPOINT.md`;
3. `docs/workpacks/active/CURRENT_WORKPACK.md`;
4. `docs/architecture/SYSTEM_MAP.md`;
5. fachlicher Domain-Router und Owner-Dateien;
6. `ENGINEERING.md`;
7. `docs/external-resource-matrix.md`, falls externe Ressourcen betroffen sind.

Git bewahrt Historie. Veraltete aktuelle Aussagen und einmalige Prozessdokumente werden aus dem Arbeitsbaum entfernt statt durch weitere Register und Audits verwaltet.

## 11. Definition of Done

Ein Workpack ist abgeschlossen, wenn:

- der kleinste vollständige Patch integriert ist;
- `PR Gate` grün war;
- der normale Staging-Deploy grün ist, sofern Runtime betroffen ist;
- die konkrete Zielwirkung geprüft wurde;
- temporäre Test- oder Workflowinfrastruktur entfernt ist;
- `CURRENT_WORKPACK.md` den echten Folgezustand zeigt;
- kein unnötiger Folge-Workpack erzeugt wurde.
