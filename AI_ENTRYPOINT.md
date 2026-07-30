# Bocholt erleben – KI-Arbeitsmodell

Arbeitsbaseline ist `staging`. Ziel ist eine schnelle, fail-closed Arbeitsweise mit möglichst wenig Übergaben, Branches, PRs und Deploys.

## 1. Unverrückbarer Normalfall

```text
ein aktives Workpack
-> ein Repository-Schreiber
-> ein deklarierter Feature-Branch
-> ein offener PR nach staging
-> ein vollständiger Review-Check
-> ein Staging-Deploy
```

`main` bleibt der Live-Releasebranch. Ein Release-PR ist ausschließlich `staging -> main`.

## 2. Start jeder Repository-Aufgabe

1. aktuellen `staging`- und `main`-SHA lesen;
2. offene PRs lesen;
3. genau ein offenes Issue mit `[ACTIVE WORKPACK]` lesen;
4. den im Vertrag deklarierten `branch` lesen;
5. einen vorhandenen PR dieses Branches fortsetzen;
6. `SYSTEM_MAP.md`, betroffene Owner und bei Technik `ENGINEERING.md` lesen.

Fail-closed stoppen, wenn:

- kein oder mehr als ein aktiver Workpack existiert;
- mehr als ein Feature-PR nach `staging` offen ist;
- der offene PR nicht vom deklarierten Branch stammt;
- ein anderer Schreiber denselben Workpack bearbeitet.

Ein neuer Chat eröffnet niemals vorsorglich einen neuen Branch oder PR. Er setzt den kanonischen Stand fort.

## 3. Werkzeugwahl

### Chat

Chat steuert Ziel, Scope, GitHub, Checks, Merge, Deployprüfung und Abschluss. Kleine vollständig gelesene Text- oder Konfigurationsänderungen darf Chat selbst schreiben.

### Checkout-fähiger Code-Agent

Größere Code-, Test-, Build- und UI-Arbeit benötigt:

- vollständigen Repository-Checkout;
- Suche und patchfähige Bearbeitung;
- lokale Syntax- und Contracttests;
- Build- oder Browsernachweis;
- gebündelte Commits auf dem deklarierten Branch.

### GitHub-Connector

Der Connector ist primär für Lesen, Issues, PRs, Checks, Logs und Merge. Er ist keine Ersatz-IDE für große Multi-Datei-Implementierungen. Fehlt eine Checkout- und Testumgebung, wird kein großer Patch Datei für Datei begonnen.

## 4. Kompakter Workpack-Vertrag

Neue Workpacks verwenden genau einen TOML-Block im aktiven Issue:

```toml
schema_version = 2
workpack_issue = 123
branch = "agent/workpack-123"
objective = "Kleinster vollständiger Zielzustand."
allowed_paths = ["api/bereich/**", "tests/bereich_*"]
locked_paths = ["api/stripe/**", ".github/workflows/**"]
external_access = "none"
required_tests = ["bash scripts/validate-repo.sh"]
done = ["Zielwirkung ist belegt."]
forbidden_effects = ["Keine Mail, kein Stripe, kein Live-Write."]
staging_smoke = "Normaler Deploy-Smoke genügt."
```

Bei einem kontrollierten externen Write kommt nur dies hinzu:

```toml
[external_write]
resource = "Staging-Ressource"
identity = "stabile synthetische ID"
before = "belegter Vorherzustand"
mutation = "eine begrenzte Mutation"
readback = "exakter Readback"
cleanup = "Cleanup und Zero Residue"
```

Der PR enthält nur:

```text
Workpack: #123
```

Revision, Hash, Tests, Rollback und Evidence werden nicht mehr manuell in den PR kopiert. Das Issue ist alleiniger operativer Vertrag.

## 5. Serialisierung

Der Required Check erzwingt:

- genau ein aktives Workpack;
- genau einen offenen Feature-PR nach `staging`;
- PR-Head entspricht exakt dem im Issue deklarierten Branch;
- vollständiger Diff liegt innerhalb `allowed_paths`;
- `locked_paths` bleiben unverändert;
- Release nur `staging -> main`.

Ein zweiter Chat liest dadurch den bestehenden Branch und PR und setzt dort fort. Er kann keinen konkurrierenden PR grün bekommen.

## 6. Zwei CI-Stufen

### Draft

Bei jedem Draft-Push laufen nur:

- Workpack-, Branch- und PR-Serialisierung;
- `git diff --check`;
- Syntax und Routing;
- PR-Validator-Tests;
- weitere schnelle, containerfreie Checks.

Befehl:

```bash
bash scripts/validate-repo.sh quick
```

### Reviewbereit

Erst beim Wechsel auf reviewbereit und nach späteren Codeänderungen laufen:

- vollständige Repositoryvalidierung;
- erforderliche MySQL-/MariaDB-Verträge;
- vollständige Frontendverträge;
- Browser-Fixtures und vereinbarte Viewports.

Befehl:

```bash
bash scripts/validate-repo.sh
```

Ein bloßes Editieren des PR-Texts startet keinen neuen Lauf. Der grüne Required Check muss zum aktuellen Head-SHA gehören.

## 7. Evidence und Staging

Der Normalfall ist:

```text
ein Implementierungs-PR
-> ein normaler Staging-Deploy
-> der vorhandene Deploy-Smoke
-> höchstens ein gezielter zusätzlicher Smoke bei konkret unbelegtem Risiko
```

Nicht mehr zulässig als Standard:

- temporäre Runtime-Endpunkte;
- Completion-Marker;
- Diagnose-PRs;
- Marker-Cleanup-PRs;
- Endpunktentfernungs-PRs;
- separate 404-Finalisierungs-PRs.

Synthetische Staging-Evidence nutzt permanente geschützte APIs oder einen wiederverwendbaren Test-Runner. Mutation, Readback und Cleanup geschehen im selben Lauf. Bei unerwartetem Verhalten wird gestoppt, nicht erneut geschrieben.

## 8. Merge und Release

Vor dem Merge nach `staging`:

- PR vollständig;
- aktueller Head-SHA grün;
- relevante Evidence vorhanden;
- keine offenen Reviewthreads;
- Merge mit exakter Head-SHA-Bindung.

Nach dem Staging-Deploy genügt der enthaltene Smoke, solange kein konkretes Risiko offen bleibt.

Release nach Live:

```text
staging -> main
-> normaler Main-Deploy
-> gezielter Live-Smoke
```

Live-Schreibtests bleiben ausgeschlossen.

## 9. Dokumentation

- aktives Issue: operativer Status, Entscheidungen, Evidence, nächster Schritt;
- `SYSTEM_MAP.md`: dauerhafte Owner und Datenflüsse;
- `ENGINEERING.md`: dauerhafte technische Regeln;
- `TEST_STATUS.md`: dauerhafte Prooffähigkeiten;
- Roadmap und Produktvertrag nur bei echter Zieländerung.

Keine parallelen Statusjournale. Dauerhafte Dokumentation wird einmal am Workpack-Ende reconciliiert.

## 10. Arbeitsmandat

Eine klare Anweisung wie `mach das`, `umsetzen` oder `zum Abschluss bringen` erlaubt innerhalb des geschlossenen Vertrags:

- Arbeit auf dem deklarierten Branch;
- Aktualisierung desselben PRs;
- Tests;
- Merge nach `staging` bei grünem Required Check;
- vereinbarte Staging-Evidence;
- Release nach `main`, sofern im Mandat enthalten und ohne neue externe Freigabegrenze;
- Abschlussdokumentation und Issue-Abschluss.

Neue Zustimmung ist nur bei Scope-Erweiterung, Zahlung, Nachricht, Berechtigungsänderung, irreversiblem externen Write oder einer ausdrücklich gesperrten Live-Aktion erforderlich.

## 11. Definition of Done

Ein Workpack ist fertig, wenn:

- der Issue-Vertrag erfüllt ist;
- genau der deklarierte Branch und PR integriert wurden;
- der aktuelle Required Check grün ist;
- erforderliche Staging-/Live-Evidence grün ist;
- synthetische Daten vollständig bereinigt sind;
- dauerhaftes Wissensdelta einmal dokumentiert ist;
- das Issue den finalen Stand und genau den nächsten Schritt enthält.
