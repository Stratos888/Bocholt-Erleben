# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`

Diese Datei ist der verbindliche Arbeitsrouter. Ziel ist die schnellste zuverlässige Arbeitsweise mit möglichst wenigen Übergaben, Pull Requests, Deploys und Nutzeraktionen.

## 1. Start jeder Repo-Aufgabe

1. aktuellen `staging`-SHA und offene Pull Requests prüfen;
2. `docs/workpacks/active/CURRENT_WORKPACK.md` lesen;
3. ein dort genanntes GitHub-Issue lesen; dieses ist der operative Status- und Evidence-Owner;
4. `docs/architecture/SYSTEM_MAP.md` und nur die betroffenen Owner-Dateien lesen;
5. bei externen Daten zusätzlich `docs/external-resource-matrix.md` lesen;
6. erst danach analysieren oder patchen.

Aktueller Ref, owning Dateien und das aktive Workpack-Issue sind die Source of Truth. Alte Chats, Memory, ZIPs und historische Dokumente sind nur Kontext.

## 2. Verbindlicher Standardprozess

```text
Chat führt und entscheidet
-> Codex ist der einzige Repository-Schreiber
-> Work nur bei echten Mehrstrang- oder Mehrsystem-Workpacks
-> ein GitHub-Issue führt operativen Status und Evidence
-> Feature-Branch -> staging -> main
```

- Genau ein schreibender Agent ist der Normalfall.
- Chat bleibt die führende Steuerungs- und Abnahmeebene.
- Codex übernimmt technische Repository-Analyse und -Umsetzung innerhalb eines geschlossenen Auftrags.
- Work ist eine Ausnahme, keine Pflichtstufe.
- Der Nutzer muss weder Werkzeug noch technischen Ablauf auswählen.

## 3. Werkzeugwahl

### Chat – Standard-Orchestrator

Chat übernimmt:

- Zielklärung und Priorisierung;
- belegten Ausgangszustand;
- kleinsten vollständigen Scope;
- Abnahmevertrag;
- Entscheidung, ob überhaupt ein Patch nötig ist;
- unabhängige proportionale Prüfung;
- Merge-, Staging- und Releaseentscheidung;
- GitHub-, Actions-, Branch- und Live-Prüfungen;
- genau den nächsten Schritt.

Kleine Analysen und klar begrenzte Nicht-Repo-Aufgaben erledigt Chat direkt.

### Codex – einziger Repository-Schreiber

Codex übernimmt:

- Ursachen- und Codeanalyse im festgelegten Scope;
- Code-, Test- und notwendige dauerhafte Dokumentationsänderungen;
- lokale Builds und Befehle;
- Browser-, Screenshot- oder Renderer-Nachweise, soweit erforderlich;
- genau einen Feature-Branch und einen reviewfähigen PR nach `staging`.

Codex erweitert nicht selbstständig Produktziel oder Workpack-Scope.

### Work – nur begründete Ausnahme

Work wird nur aktiviert, wenn vor der Umsetzung mindestens eines davon belegt ist:

- mindestens zwei unabhängige Lieferstränge mit getrennten Ownern;
- mehrere nacheinander zu steuernde Codex-Aufträge;
- mehrere externe Systeme mit echten fachlichen Gates zwischen den Schritten;
- umfangreiche externe Recherche, die nicht sinnvoll im führenden Chat gehalten werden kann.

Ein großer einzelner Repository-Patch reicht nicht aus. Ist die Notwendigkeit nicht eindeutig, bleibt Chat der Orchestrator.

## 4. Ein operativer Status-Owner

Für jeden aktiven oder vorbereiteten größeren Workpack gibt es genau ein GitHub-Issue als operativen Owner.

Das Issue enthält:

- Status;
- offenen Abnahmevertrag;
- Entscheidungen;
- Evidence und Links auf PRs/Actions;
- Risiken und Grenzen;
- genau den nächsten Schritt.

Repository-Dateien enthalten nur dauerhafte Regeln, Architektur, Datenverträge, Produktentscheidungen und einen stabilen Workpack-Scope. Kurzfristiger Fortschritt wird nicht parallel in `CURRENT_WORKPACK.md`, Roadmap, Queue, Proofindex und mehreren Abschlussdateien fortgeschrieben.

`CURRENT_WORKPACK.md` bleibt ein kurzer Router zum zuständigen Issue und ist kein Statusjournal.

## 5. Verbindlicher Abnahmevertrag vor Codex

Vor jedem schreibenden Codex-Auftrag müssen feststehen:

1. fachliches Ziel;
2. Nicht-Ziele und gesperrter Scope;
3. erlaubte sichtbare Änderungen;
4. Bereiche, die unverändert bleiben müssen;
5. betroffene Owner;
6. erforderliche Tests;
7. konkrete Evidence aus dem tatsächlichen Zielpfad;
8. Definition of Done;
9. Rollback.

### Zusätzlich bei UI, Rendering oder Progressive Enhancement

- Referenzzustand oder Screenshot;
- relevante Viewports;
- Above-the-fold-Grenze;
- ausdrückliche Aussage, ob neue sichtbare Elemente zulässig sind;
- Zustand mit und ohne JavaScript;
- echter Build-/Renderer-Output statt nur Repositoryvorlage;
- Browser- oder Screenshotnachweis vor dem Staging-Merge.

Ist der Vertrag nicht geschlossen, bleibt die Arbeit read-only.

## 6. Keine Doppelarbeit

- Chat klärt Ziel und Scope; Codex wiederholt keine vollständige Produktanalyse.
- Work wiederholt weder Chat- noch Codex-Analyse.
- Chat prüft das Ergebnis proportional und führt keine zweite Vollanalyse durch.
- Übergabeprompts sind selbstständig ausführbar, aber auf den notwendigen Kontext begrenzt.
- Dieselbe Ursache bleibt in demselben Codex-Task, Branch und PR, solange der Staging-Merge noch nicht erfolgt ist.

## 7. Codex-Liefervertrag

Der Normalfall ist:

```text
ein Codex-Task
-> ein Feature-Branch
-> ein vollständiger PR nach staging
```

Vor dem PR müssen vorliegen:

- relevanter Diff vollständig;
- Syntax-, Unit- und Contracttests grün;
- `bash scripts/validate-repo.sh` grün;
- tatsächlicher Build-/Renderer-Output geprüft, falls betroffen;
- UI-/Browsernachweis geprüft, falls betroffen;
- keine bekannte Grundsatzfrage offen.

Korrekturen vor dem Staging-Merge bleiben im selben Branch und PR. Für dieselbe Ursache wird kein neuer Codex-Task eröffnet.

## 8. Prüfung vor dem Staging-Merge

Chat prüft nur:

- entspricht der Diff dem Abnahmevertrag;
- wurden ausschließlich zuständige Owner geändert;
- beweisen Tests und Artefakte den tatsächlichen Zielpfad;
- bleiben gesperrte Bereiche unverändert;
- ist die sichtbare Wirkung vorab belegt, falls betroffen.

Erst danach wird nach `staging` gemergt.

## 9. PR- und Deploybudget

Normaler Workpack:

```text
1 Implementierungs-PR nach staging
1 normaler Staging-Deploy
1 gezielter Staging-Smoke
1 Release-PR staging -> main
1 normaler Main-Deploy
1 gezielter Live-Smoke
```

- Keine Feature-Branch-Deploys.
- Keine zusätzlichen Observer- oder Verification-Deploys.
- Reine Vor-Merge-Korrekturen bleiben im bestehenden PR.
- Nach dem Staging-Merge ist höchstens eine klar begründete Korrektur zulässig.
- Erfordert der Zustand danach eine weitere Suchschleife, wird gestoppt und Ziel oder Architektur neu entschieden.

## 10. Fehlerbudget

```text
unerwartetes Verhalten
-> stoppen
-> Zustand sichern
-> Ursache bestimmen
-> Abnahmevertrag korrigieren
-> höchstens eine begründete reale Korrektur
```

Keine Merge-/Deploy-Schleifen zur Designsuche und keine manuelle Datenkorrektur zum künstlichen Grünmachen.

## 11. Nutzerbeteiligung

Chat erledigt selbst:

- GitHub- und PR-Prüfungen;
- Action-Status und Merges;
- Branchvergleiche;
- technische Live-HTML-, Robots-, Sitemap- und Schema-Prüfungen;
- technische Releaseentscheidungen innerhalb des erteilten Mandats.

Der Nutzer wird nur benötigt für:

- subjektive visuelle Bewertung;
- private externe Oberflächen wie Search Console;
- echte fachliche Entscheidungen ohne eindeutige Ableitung;
- irreversible, kostenpflichtige oder extern schreibende Aktionen.

## 12. Dokumentation

- Keine Abschlussdokumentation während der Umsetzung.
- Operativer Status und Zwischen-Evidence stehen ausschließlich im Workpack-Issue.
- Repository-Dokumentation wird einmal am Ende und nur bei dauerhaftem Wissensdelta geändert.
- Roadmap nur ändern, wenn sich Priorität oder Produktziel wirklich ändern.
- `TEST_STATUS.md` nur ändern, wenn sich dauerhafte Testabdeckung oder Evidence-Grenze ändert.
- Reine Statusdokumentation erzeugt keinen separaten Releasezyklus.
- Dauerhafte Arbeitsregeln dürfen vor dem nächsten Test-Workpack regulär veröffentlicht werden.

## 13. Arbeitsmandat

Eine eindeutige Anweisung wie `mach das`, `umsetzen`, `patchen` oder `dokumentieren` erlaubt innerhalb des geschlossenen Scopes:

- Analyse und Zieldefinition;
- Branch, Commits und PR;
- automatisierte Tests;
- Merge nach `staging`, wenn der PR Gate grün ist;
- Staging-Prüfung;
- Release-PR und Merge nach `main`, wenn die Abnahme grün ist;
- einmalige dauerhafte Abschlussdokumentation.

Eine neue Freigabe ist nur bei Scope-Erweiterung, Live-Schreibaktion, irreversibler Aktion, Zahlung, Nachricht, Veröffentlichung außerhalb des vereinbarten Releasepfads oder Berechtigungsänderung erforderlich.

## 14. Definition of Done

Ein Workpack ist abgeschlossen, wenn:

- der Abnahmevertrag erfüllt ist;
- der kleinste vollständige Patch integriert ist oder ein Patch nachvollziehbar nicht nötig war;
- `PR Gate` grün war, sofern ein Patch existiert;
- der normale Staging-Deploy grün ist, sofern Runtime betroffen ist;
- die konkrete Zielwirkung geprüft wurde;
- der reguläre Release und Live-Smoke abgeschlossen sind, sofern veröffentlicht wurde;
- das GitHub-Issue den finalen Status und Evidence enthält;
- nur dauerhaftes Wissensdelta im Repository dokumentiert wurde;
- kein unnötiger Folge-Workpack erzeugt wurde.