# ENGINEERING – Bocholt erleben

Diese Datei enthält dauerhafte technische Regeln. Der Arbeitsablauf steht in `AI_ENTRYPOINT.md`; operativer Status ausschließlich im aktiven GitHub-Issue.

## 1. Quellen der Wahrheit

- `staging` ist Entwicklungsbaseline.
- `main` ist Live-Releasebranch.
- Kein Patch ohne aktuellen Inhalt der owning Datei.
- Genau ein aktives Issue ist der operative Scope- und Evidence-Owner.
- Generierte Eventdateien sind Buildartefakte, keine manuell gepflegten Quellen.

## 2. Owner und Schreiber

1. bestehenden Owner bestimmen;
2. Ursache dort beheben;
3. konkurrierende Pfade entfernen;
4. nur notwendige Guards ergänzen.

Nicht zulässig:

- mehrere Writer für denselben Workpack;
- zweiter Feature-Branch oder zweiter PR für dieselbe Ursache;
- Wrapper auf Wrapper;
- parallele Statusführung;
- große Multi-Datei-Änderungen über die Contents API ohne Checkout und lokale Tests.

Zentrale Owner, Schema, Deploy und globale Entrypoints werden seriell geändert.

## 3. Git-Topologie

```text
Feature-Branch -> staging -> main
```

- Pro aktivem Workpack genau ein im Issue deklarierter Feature-Branch.
- Repositoryweit höchstens ein offener Feature-PR nach `staging`.
- Korrekturen bleiben im selben Branch und PR.
- Kein direkter Commit nach `staging` oder `main`.
- Kein Force-Push, Force-Reset oder Feature-Branch-Deploy.
- Merge nur bei grünem Check auf exakt aktuellem Head-SHA.

## 4. Entwicklungsumgebung

Größere Codearbeit benötigt einen vollständigen Checkout mit lokaler Suche, Patchbearbeitung und Tests. Der GitHub-Connector dient für Repositorylesen, Issues, PRs, Checks, Logs und Merge.

Ohne Checkout sind nur kleine, vollständig gelesene und deterministische Text- oder Konfigurationsänderungen zulässig. Ein fehlendes Werkzeug wird nicht durch Dutzende einzelne Remote-Datei-Commits kompensiert.

## 5. Tests und CI

Der PR besitzt genau einen Required Check `PR Gate`.

### Draft

`bash scripts/validate-repo.sh quick`

Enthält nur schnelle, containerfreie Prüfungen: Routing, Syntax, Whitespace, Validator-Tests und Compile-Checks.

### Reviewbereit

`bash scripts/validate-repo.sh`

Enthält vollständige Backend-, Datenbank-, Frontend- und Repositoryverträge. Browser-Fixtures laufen zusätzlich im PR Gate.

- PR-Textänderungen lösen keinen Lauf aus.
- Nach Reviewbereitschaft löst jede Codeänderung erneut den vollständigen Lauf aus.
- Ein roter Test wird vor dem Merge im selben PR behoben.
- Tests beweisen Verhalten, nicht Dateinamen oder Kommentare.

## 6. Environment und externe Writes

```text
staging: Staging-Quellen und Staging-Ziele
live:    Live-Quellen und Live-Ziele
```

Externe Writes benötigen stabile Identität, Vorherzustand, begrenzte Mutation, Readback und Cleanup. Beim ersten unerwarteten Verhalten wird gestoppt.

Live-Schreibtests bleiben ausgeschlossen. Secrets gehören weder in Repository noch Chat.

## 7. Evidence

Normalfall:

- bestehender permanenter Owner;
- automatisierter Contracttest;
- normaler Staging-Deploy;
- vorhandener Deploy-Smoke.

Synthetische Mutation, Readback und Cleanup erfolgen in einem Lauf. Temporäre Runtime-Endpunkte, Marker- und mehrstufige Cleanup-PR-Ketten sind kein Standardweg.

Actions-Artefakte bleiben interne Evidence. Nutzerseitig werden Ergebnisse und Grenzen berichtet, nicht automatisch ZIP-Dateien.

## 8. Deploy und Release

- Nur `staging` und `main` deployen.
- Ein Implementierungs-PR erzeugt einen normalen Staging-Deploy.
- Der enthaltene Build-/HTTP-/Browser-Smoke genügt, sofern kein konkret unbelegtes Risiko besteht.
- Zusätzliche Deploys benötigen ein benanntes offenes Risiko.
- Release ausschließlich per `staging -> main`, anschließend normaler Live-Smoke.

## 9. UI und Inhalte

- Komponenten stylen sich selbst; Layoutdateien platzieren sie.
- Token-first, keine Override-Ketten.
- Daten- und Bildprobleme upstream lösen.
- Sichtbare Änderungen benötigen vorher festgelegte Viewports und Browser- oder Screenshotnachweis.
- Progressive Enhancement bei relevantem Scope mit und ohne JavaScript prüfen.

## 10. Dokumentation

- `AI_ENTRYPOINT.md`: Arbeitsmodell;
- `AGENTS.md`: Codex-Kurzrouter;
- aktives Issue: operativer Status;
- `SYSTEM_MAP.md`: Owner und Datenflüsse;
- `TEST_STATUS.md`: dauerhafte Evidence-Grenzen;
- Roadmap und Produktverträge: nur echte Zieländerungen.

Repository-Dokumentation wird am Workpack-Ende genau einmal und nur bei dauerhaftem Wissensdelta aktualisiert.
