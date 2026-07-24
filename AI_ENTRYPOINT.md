# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`

Diese Datei ist der verbindliche Arbeitsrouter. Ziel ist die schnellste zuverlässige Arbeitsweise mit möglichst wenigen Übergaben, Pull Requests, Deploys und Nutzeraktionen.

## 1. Start jeder Repo-Aufgabe

1. aktuellen `staging`-SHA, `main`-SHA und offene Pull Requests prüfen;
2. `docs/workpacks/active/CURRENT_WORKPACK.md` lesen;
3. das genau eine offene Issue mit `[ACTIVE WORKPACK]` vollständig lesen, falls vorhanden;
4. `docs/architecture/SYSTEM_MAP.md` und nur die betroffenen Owner-Dateien lesen;
5. `ENGINEERING.md` lesen, wenn technische Regeln, Workflows oder Runtime betroffen sind;
6. bei externen Daten zusätzlich `docs/external-resource-matrix.md` lesen;
7. erst danach analysieren oder schreiben.

Aktueller Ref, owning Dateien, aktives Workpack-Issue und dessen eingefrorener Vertrag sind die Source of Truth. Alte Chats, Memory, ZIPs und historische Dokumente sind nur Kontext.

## 2. Verbindlicher Standardprozess

```text
Chat führt und entscheidet
-> genau ein Repository-Schreiber
-> Work nur bei echten Mehrstrang- oder Mehrsystem-Workpacks
-> ein GitHub-Issue führt operativen Status und Evidence
-> Feature-Branch -> staging -> main
```

- Chat bleibt die führende Steuerungs-, Prüf- und Abnahmeebene.
- Genau ein schreibender Agent oder Werkzeugpfad ist der Normalfall.
- Der Nutzer muss weder Werkzeug noch technischen Ablauf auswählen.
- Repository-Writes beginnen erst nach geschlossenem Gate A.

## 3. Werkzeugwahl

### Chat – Standard-Orchestrator

Chat übernimmt:

- Zielklärung und Priorisierung;
- belegten Ausgangszustand;
- kleinsten vollständigen Scope;
- Workpack-Vertrag und Abnahmevertrag;
- Entscheidung, ob überhaupt ein Patch nötig ist;
- unabhängige proportionale Prüfung;
- GitHub-, PR-, Actions-, Branch-, Deploy- und Live-Prüfungen;
- Merge- und Releaseentscheidung;
- Dokumentations-Reconciliation und genau den nächsten Schritt.

Chat darf kleine deterministische Text- oder Konfigurationsänderungen selbst als Repository-Schreiber ausführen, wenn alle Bedingungen erfüllt sind:

- der vollständige betroffene Textscope wurde gelesen;
- die Änderung ist ohne große partielle Dateioperation oder komplexe Codebearbeitung kontrollierbar;
- Branch, Write, Read-back, PR und Required Check sind über die vorhandene GitHub-Verbindung sicher möglich;
- genau ein Branch und ein PR genügen;
- kein anderer Schreiber arbeitet am selben Owner.

### Codex – bevorzugter technischer Repository-Schreiber

Codex übernimmt insbesondere:

- Code-, Test-, Build- und Generatoränderungen;
- größere oder patchintensive Diffs;
- Änderungen an langen bestehenden Workflows oder zentralen technischen Ownern;
- lokale Befehle, Builds, Renderer- und Browsernachweise;
- genau einen Feature-Branch und einen reviewfähigen PR nach `staging`.

Codex erweitert Produktziel oder Workpack-Scope nicht selbstständig. Innerhalb eines Codex-Auftrags ist Codex der einzige Repository-Schreiber.

### Work – nur begründete Ausnahme

Work wird nur aktiviert, wenn vor der Umsetzung mindestens eines davon belegt ist:

- mindestens zwei unabhängige Lieferstränge mit getrennten Ownern;
- mehrere nacheinander zu steuernde Codex-Aufträge;
- mehrere externe Systeme mit echten fachlichen Gates;
- umfangreiche externe Recherche, die nicht sinnvoll im führenden Chat gehalten werden kann.

Ein großer einzelner Patch reicht nicht aus. Ist die Notwendigkeit nicht eindeutig, bleibt Chat der Orchestrator.

## 4. Ein operativer Status-Owner

Für jeden aktiven größeren Workpack gibt es genau ein offenes GitHub-Issue mit `[ACTIVE WORKPACK]`.

Das Issue enthält:

- Status;
- eingefrorenen Workpack- und Abnahmevertrag;
- Entscheidungen;
- Evidence und Links auf PRs und Actions;
- Risiken und Grenzen;
- genau den nächsten Schritt.

Repository-Dateien enthalten nur dauerhafte Regeln, Architektur, Datenverträge, Produktentscheidungen und Prooffähigkeiten. Operativer Fortschritt wird nicht parallel in Router, Roadmap, Queue, Proofindex oder Abschlussdateien fortgeschrieben.

`CURRENT_WORKPACK.md` bleibt ein statusfreier Router zum aktiven Issue.

## 5. Verbindlicher Vertrag vor jedem Write

Vor einem Repository- oder externen Write müssen feststehen:

1. fachliches Ziel;
2. Nicht-Ziele und gesperrter Scope;
3. erlaubte sichtbare Änderungen;
4. unveränderte Bereiche;
5. betroffene Owner;
6. erforderliche Tests;
7. konkrete Evidence aus dem tatsächlichen Zielpfad;
8. Definition of Done;
9. Rollback;
10. erforderliche dauerhafte Dokumentations-Owner.

Der maschinenlesbare Issue-Vertrag friert erlaubte und gesperrte Pfade, Tests, Evidence-Grenzen und Rollback ein. Ist der Vertrag nicht geschlossen, bleibt die Arbeit read-only.

### Zusätzlich bei UI, Rendering oder Progressive Enhancement

- Referenzzustand;
- relevante Viewports;
- Above-the-fold-Grenze;
- ausdrückliche Aussage zu neuen sichtbaren Elementen;
- Zustand mit und ohne JavaScript;
- echter Build-/Renderer-Output;
- Browser- oder Screenshotnachweis vor dem Staging-Merge.

## 6. Keine Doppelarbeit

- Chat klärt Ziel und Scope; der Schreiber wiederholt keine vollständige Produktanalyse.
- Work wiederholt weder Chat- noch Schreiberanalyse.
- Chat prüft das Ergebnis proportional und führt keine zweite Vollanalyse durch.
- Korrekturen vor dem Staging-Merge bleiben im selben Task, Branch und PR.
- Dieselbe Ursache erhält keinen zweiten parallelen Schreiber.

## 7. PR-Liefervertrag

Normalfall:

```text
ein Workpack
-> ein Repository-Schreiber
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

## 8. Prüfung vor dem Staging-Merge

Chat prüft:

- entspricht der Diff dem eingefrorenen Vertrag;
- wurden ausschließlich zuständige Owner geändert;
- beweisen Tests und Evidence den tatsächlichen Zielpfad;
- bleiben gesperrte Bereiche unverändert;
- ist die sichtbare Wirkung vorab belegt, falls betroffen;
- ist die Dokumentations-Reconciliation für dauerhafte Änderungen vorbereitet.

Erst danach wird nach `staging` gemergt.

## 9. PR- und Deploybudget

Normaler Runtime-Workpack:

```text
1 Implementierungs-PR nach staging
1 normaler Staging-Deploy
1 gezielter Staging-Smoke
1 Release-PR staging -> main
1 normaler Main-Deploy
1 gezielter Live-Smoke
```

- Keine Feature-Branch-Deploys.
- Keine synthetischen Folge-Deploys ohne konkret unbelegtes Risiko.
- Reine Vor-Merge-Korrekturen bleiben im bestehenden PR.
- Nach dem Staging-Merge ist höchstens eine begründete Korrektur zulässig.
- Erfordert der Zustand danach eine weitere Suchschleife, werden Ziel oder Architektur neu entschieden.

Dokumentations-only-Workpacks benötigen keinen fachlichen Runtime-Smoke. Der normale Branch- und PR-Pfad bleibt verbindlich.

## 10. Fehlerbudget

```text
unerwartetes Verhalten
-> stoppen
-> Zustand sichern
-> Ursache bestimmen
-> Vertrag korrigieren
-> höchstens eine begründete reale Korrektur
```

Keine Merge-/Deploy-Schleifen zur Designsuche und keine manuelle Datenkorrektur zum künstlichen Grünmachen.

## 11. Nutzerbeteiligung

Chat erledigt selbst:

- GitHub- und PR-Prüfungen;
- Action-Status und Merges;
- Branchvergleiche;
- technische Live-HTML-, Robots-, Sitemap-, Schema- und Releaseprüfungen;
- automatische Ermittlung von Deploy-Runs über Commitstatus;
- technische Releaseentscheidungen innerhalb des erteilten Mandats.

Der Nutzer wird nur benötigt für:

- subjektive visuelle Bewertung;
- private externe Oberflächen wie Search Console;
- echte fachliche Entscheidungen ohne eindeutige Ableitung;
- irreversible, kostenpflichtige oder extern schreibende Aktionen.

## 12. Artefaktregel

Actions-Artefakte, Screenshots und Browserreports sind maschinelle Evidence, keine automatische Nutzerlieferung.

- Chat oder Codex prüft bevorzugt Jobsummary, Logs und strukturierte Reports.
- Ein Actions-Artefakt wird nur intern gelesen, wenn die Evidence sonst nicht ausreicht.
- Keine ZIP-Datei, kein Downloadlink und keine Artefaktübergabe an den Nutzer, sofern dieser sie nicht ausdrücklich verlangt.
- Keine Screenshots auf Vorrat; nur bei sichtbarer Abnahme oder zur belegten Fehlerdiagnose.
- Im Chat wird das Ergebnis kompakt berichtet: Testumfang, Ergebnis, Fehler und relevante Evidence-Grenze.

## 13. Dokumentations-Owner und Reconciliation

| Dauerhafte Änderung | Kanonischer Owner |
|---|---|
| Arbeitsmodell, Werkzeugwahl, Nutzerartefakte | `AI_ENTRYPOINT.md` |
| Codex-spezifischer Einstieg | `AGENTS.md` |
| technische Regeln und Workflowrollen | `ENGINEERING.md` |
| Systeme, Datenflüsse und technische Owner | `docs/architecture/SYSTEM_MAP.md` |
| externe Ressourcen und Writes | `docs/external-resource-matrix.md` |
| Produktziel | `MASTER.md`, `Produktvertrag.md`, `COMMERCIAL_STRATEGY.md` |
| Produktpriorität und Kandidaten | `ROADMAP.md` |
| dauerhafte Testabdeckung und Evidence-Grenzen | `TEST_STATUS.md` |
| operativer Status und Lauf-Evidence | aktives GitHub-Issue |

Vor dem Schließen jedes Workpacks prüft Chat diese Matrix:

1. Welche dauerhafte Realität hat sich geändert?
2. Welcher Owner ist dafür zuständig?
3. Muss dieser Owner aktualisiert werden oder lautet das Ergebnis ausdrücklich `kein dauerhaftes Wissensdelta`?
4. Wurden veraltete aktuelle Aussagen ersetzt statt ergänzt?
5. Bleiben operative SHAs, Run-IDs und Zwischenstände im Issue, sofern sie keine dauerhafte Proofgrenze darstellen?

Repository-Dokumentation wird genau einmal und nur bei dauerhaftem Wissensdelta geändert. Roadmap, Proofindex und technische Architektur werden nicht als allgemeines Abschlussjournal benutzt.

## 14. Arbeitsmandat

Eine eindeutige Anweisung wie `mach das`, `umsetzen`, `patchen`, `dokumentieren` oder `zum Abschluss prüfen` erlaubt innerhalb des geschlossenen Scopes:

- Analyse und Zieldefinition;
- Aktivierung eines Workpack-Issues;
- Branch, Commits und PR;
- automatisierte Tests;
- Merge nach `staging`, wenn der PR Gate grün ist;
- Staging-Prüfung;
- Release-PR und Merge nach `main`, wenn die Abnahme grün ist;
- einmalige dauerhafte Dokumentations-Reconciliation;
- Abschluss des Workpack-Issues.

Eine neue Freigabe ist nur bei Scope-Erweiterung, Live-Schreibaktion, irreversibler Aktion, Zahlung, Nachricht, Veröffentlichung außerhalb des vereinbarten Releasepfads oder Berechtigungsänderung erforderlich.

## 15. Definition of Done

Ein Workpack ist abgeschlossen, wenn:

- der eingefrorene Vertrag erfüllt ist;
- der kleinste vollständige Patch integriert ist oder ein Patch nachvollziehbar nicht nötig war;
- `PR Gate` grün war, sofern ein Patch existiert;
- erforderliche Staging- und Live-Evidence grün ist;
- die konkrete Zielwirkung geprüft wurde;
- das GitHub-Issue finalen Status, Evidence und Grenzen enthält;
- die Dokumentations-Reconciliation durchgeführt wurde;
- keine veraltete Queue-, Roadmap- oder Proofaussage zurückbleibt;
- keine unnötige Nutzeraktion oder Nutzerartefaktdatei erzeugt wurde;
- `[ACTIVE WORKPACK]` aus dem abgeschlossenen Issue entfernt wurde;
- kein unnötiger Folge-Workpack erzeugt wurde.
