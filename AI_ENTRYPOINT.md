# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`

Diese Datei ist der verbindliche Arbeitsrouter. Ziel ist die schnellste zuverlässige Arbeitsweise für einen primären KI-Chat – nicht maximale Prozess- oder Testinfrastruktur.

## 1. Start jeder Repo-Aufgabe

1. aktuellen `staging`-SHA und offene PRs in GitHub prüfen;
2. `docs/workpacks/active/CURRENT_WORKPACK.md` lesen;
3. `docs/architecture/SYSTEM_MAP.md` und nur die betroffenen Owner-Dateien lesen;
4. bei externen Daten zusätzlich `docs/external-resource-matrix.md` lesen;
5. erst danach patchen.

Alte Chats, Memory, ZIPs und historische Dokumente sind Kontext. Der aktuelle Ref und die aktuellen Owner-Dateien sind die Source of Truth.

## 2. Verbindlicher Arbeitsmodus

```text
ein primärer Entwicklungs-Chat
-> ein aktiver Workpack
-> ein Feature-Branch
-> ein PR nach staging
-> ein normaler Staging-Deploy
-> Abschluss
```

- Standardmäßig gibt es nur einen schreibenden Chat.
- Eine unabhängige read-only Analyse darf parallel laufen, verändert aber nichts.
- Zwei schreibende Chats sind nur bei vollständig getrennten Dateien, Workflows und externen Ressourcen zulässig; sie sind nicht der Normalfall.
- Workflow-, Deploy-, zentrale Doku-, Schema-, Environment- und globale UI-Owner werden immer seriell geändert.

## 3. Arbeitsmandat

Eine eindeutige Anweisung wie `mach das`, `umsetzen`, `patchen` oder `dokumentieren` erlaubt innerhalb des vereinbarten Scopes:

- Analyse und Zieldefinition;
- Branch, Commits und PR;
- automatisierte Tests;
- Merge nach `staging`, wenn `PR Gate` grün ist;
- Prüfung des normalen Staging-Deploys;
- Abschluss der Dokumentation.

Eine neue Freigabe ist nur bei echter Scope-Erweiterung, Live-Schreibaktion, irreversibler Aktion, Zahlung, Nachricht, Veröffentlichung oder Berechtigungsänderung erforderlich.

## 4. Kleinster nachhaltiger Patch

Vor jeder Änderung werden genau vier Fragen beantwortet:

1. Was ist die belegte Ursache oder das konkrete Ziel?
2. Welche Datei oder Komponente besitzt das Verhalten?
3. Was ist der kleinste vollständige Zielzustand?
4. Welche Prüfung beweist genau diesen Zielzustand?

Keine Vollinventur, kein Meta-Workpack und keine neue Workflow-Schicht nur aus Vorsicht. Neue Wrapper, Observer, Guardrail-Dateien oder dauerhafte Testharnesses sind nur zulässig, wenn sie einen bestehenden Pfad ersetzen und dauerhaft gebraucht werden.

## 5. Proportionale Prüfung

### Dokumentation und kleine statische Änderungen

- Diff und Links prüfen;
- `PR Gate` ausführen;
- nach dem Merge keine zusätzliche Sonderverification.

### Code oder Runtime ohne externen Write

- relevante Unit-/Contract-/Syntaxtests im `PR Gate`;
- genau ein normaler Staging-Deploy;
- ein gezielter read-only Smoke für das geänderte Verhalten, falls der Deploy-Smoke es nicht bereits abdeckt.

### Externer Write

- Zielressource und stabile Identität vorher lesen;
- genau einen begrenzten Write ausführen;
- sofort zurücklesen;
- Rollback oder Cleanup sicherstellen;
- beim ersten unerwarteten Verhalten stoppen.

Es gibt kein allgemeines E3-/E4-Programm und keinen eigenen Workflow nur für einen einmaligen Nachweis. Ein einmaliger Testharness wird nach Abschluss entfernt; dauerhafte Sicherung erfolgt als kleiner lokaler Contract-Test.

## 6. Fehlerbudget

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

## 7. Git- und Releasepfad

- Feature-Branch -> PR nach `staging`.
- Release ausschließlich `staging -> main`.
- Keine Feature-Branch-Deploys.
- Kein Force-Push.
- Keine Secrets im Repo oder Chat.
- Ein normaler Merge nach `staging` erzeugt genau einen Deploy. Zusätzliche Observer- oder Verification-Deploys sind ausgeschlossen.

## 8. Dokumentation

Kanonische Lesereihenfolge:

1. `AI_ENTRYPOINT.md`
2. `docs/workpacks/active/CURRENT_WORKPACK.md`
3. `docs/architecture/SYSTEM_MAP.md`
4. fachlicher Domain-Router und Owner-Dateien
5. `ENGINEERING.md`
6. `docs/external-resource-matrix.md`, falls externe Ressourcen betroffen sind

Git bewahrt Historie. Veraltete aktuelle Aussagen und einmalige Prozessdokumente werden aus dem Arbeitsbaum entfernt statt durch weitere Register und Audits verwaltet.

## 9. Definition of Done

Ein Workpack ist abgeschlossen, wenn:

- der kleinste vollständige Patch integriert ist;
- `PR Gate` grün war;
- der normale Staging-Deploy grün ist, sofern Runtime betroffen ist;
- die konkrete Zielwirkung geprüft wurde;
- temporäre Test- oder Workflowinfrastruktur entfernt ist;
- `CURRENT_WORKPACK.md` den echten Folgezustand zeigt;
- kein unnötiger Folge-Workpack erzeugt wurde.
