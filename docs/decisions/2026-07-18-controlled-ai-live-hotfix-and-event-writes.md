# Entscheidung: Kontrollierte KI-Schreibrechte für Live-Events und kleine Main-Hotfixes

Stand: 2026-07-18  
Status: fachlich beschlossen, technisch für Repo-Hotfixes noch nicht vollständig freigeschaltet  
Repository: `Stratos888/Bocholt-Erleben`

## 1. Anlass

Beim Live-Event **„Du wunderst mich! – Die Kinderzaubershow mit Endrik Thier“** wurde bestätigt, dass einzelne redaktionelle Events direkt über die Live-Quelle `Events` veröffentlicht werden können, ohne den aktuellen Staging-Code nach `main` zu übernehmen.

Parallel sollte das fachlich unpassende Symbolbild des **Suderwicker Märchenspielplatzes** als kleiner isolierter Live-Hotfix entfernt werden. Der vorbereitete Patch umfasste ausschließlich:

- `data/offers.json`;
- `js/offers.js`;
- `js/today-home.js`.

Der direkte Write nach `main` wurde durch das aktuelle GitHub-Ruleset blockiert:

- Änderungen an `main` müssen über einen Pull Request erfolgen;
- der verpflichtende Check `PR Gate` wird erwartet;
- der aktuelle `PR Gate` erlaubt PRs nach `main` ausschließlich als `staging -> main`;
- ein direkter Ref-Write wurde ebenfalls durch das Ruleset abgelehnt.

Ein vollständiger Merge von `staging` nach `main` wäre für diesen isolierten Bildfehler unverhältnismäßig und würde fachfremde Änderungen mit veröffentlichen.

## 2. Fachliche Entscheidung

Die KI soll künftig zwei klar begrenzte Live-Schreibwege besitzen:

1. **Direkte Pflege einzelner Live-Events** in der redaktionellen Source of Truth `Events`.
2. **Direkte kleine, reversible Repo-Hotfixes auf `main`**, ohne vollständigen Staging-Release.

Der Standardpfad für normale Entwicklung bleibt unverändert:

```text
Feature / größere Korrektur
-> eigener Branch
-> PR nach staging
-> Staging-Deploy und Abnahme
-> Release staging -> main
```

Der Live-Sofortpfad ist eine Ausnahme und darf nicht zu einem zweiten allgemeinen Entwicklungszweig werden.

## 3. Zulässiger Scope

### 3.1 Live-Eventdaten

Zulässig sind:

- ein neues, konkret vom Nutzer beauftragtes Event;
- Korrektur einer eindeutig identifizierten Eventzeile;
- Titel, Datum, Zeit, Ort, Kategorie, Beschreibung, URL und Visual-Key innerhalb des bestehenden Schemas;
- unmittelbares Rücklesen und anschließender read-only Live-Smoke.

Pflichtbedingungen:

- stabile Event-ID;
- eindeutige Zielzeile;
- Vorherzustand gelesen;
- nur deklarierte Felder verändert;
- Qualitäts- und Schema-Guards eingehalten;
- Rücklesen erfolgreich;
- kein konkurrierender redaktioneller Write.

Nicht zulässig sind blinde Massenänderungen, unklare Dublettenbereinigung oder das manuelle Bearbeiten generierter Dateien wie `data/events.json` und `data/events.tsv`.

### 3.2 Direkter Main-Hotfix

Ein direkter KI-Commit auf `main` ist nur zulässig für einen konkreten Produktionsfehler oder eine klar begrenzte Content-/Visual-Korrektur.

Grenzen:

- ausdrückliches Nutzer-Mandat für den direkten Live-Hotfix;
- aktueller `main`-SHA als Basis;
- maximal drei fachlich zusammengehörige Owner-Dateien;
- im Regelfall maximal 100 geänderte Zeilen;
- isoliert und reversibel;
- kein Feature;
- kein Refactoring;
- keine verdeckte Übernahme von Staging-Inhalten;
- statische Prüfung vor dem Write;
- eindeutige Hotfix-Commit-Message;
- Main-Deploy und read-only Live-Smoke danach;
- Revert statt weiterem Reparaturpatch bei Abweichung.

Explizit ausgeschlossen:

- `.github/workflows/**`;
- Secrets und Credentials;
- Branch-/Ruleset-/Berechtigungsänderungen;
- Deploy- und Environment-Verträge;
- zentrale Governance-Dateien;
- Datenbankmigrationen;
- externe Mehrfach-Writes;
- destruktive oder nicht sicher rücknehmbare Änderungen.

## 4. Technischer Premium-Zielzustand

Der Main-Schutz soll nicht pauschal abgeschaltet werden. Stattdessen wird ein **dedizierter GitHub-Akteur für KI-Live-Hotfixes** eingerichtet.

Zielkonfiguration:

1. Das bestehende Main-Ruleset bleibt für normale Nutzer, Bots und Entwicklungsarbeit aktiv.
2. Ein dedizierter GitHub-App- oder Service-Account-Akteur wird gezielt in die Ruleset-Bypass-Liste aufgenommen.
3. Dieser Akteur wird ausschließlich für den dokumentierten Live-Sofortpfad verwendet.
4. Jeder direkte Main-Write wird durch die KI vorab gegen Scope, Dateigrenze, Zeilengrenze, Ausschlussliste und Rollback geprüft.
5. Der Write erzeugt einen normalen nachvollziehbaren Commit; Force-Push und History-Rewrite bleiben verboten.
6. Der nachfolgende Main-Deploy und die Live-Postconditions bleiben verpflichtend.

Eine pauschale Administrator-Ausnahme für das normale Benutzerkonto ist nicht der bevorzugte Zielzustand, weil sie KI-, Nutzer- und sonstige Admin-Aktionen technisch nicht sauber trennt.

## 5. Aktueller technischer Status

### Bereits umgesetzt

- Die Live-Eventquelle `Events` kann kontrolliert direkt gepflegt werden.
- Die Kinderzaubershow ist live.
- Für die Kinderzaubershow existiert kein passendes magie-spezifisches `ready`-Visual.
- Der Bildbedarf wurde mit `visual_key=kids_stage_story` und Zielmotiv `children_magic_show` als priorisierter Visual-Gap dokumentiert und über PR #103 nach `staging` integriert.
- `AI_ENTRYPOINT.md` und `ENGINEERING.md` enthalten den neuen kontrollierten Live-Sofortpfad.

### Noch offen

- Der dedizierte GitHub-Akteur beziehungsweise dessen Ruleset-Bypass ist noch nicht eingerichtet.
- Direkte Repo-Hotfixes auf `main` bleiben bis dahin technisch blockiert.
- Das falsche Bild des Suderwicker Märchenspielplatzes ist deshalb zum Zeitpunkt dieser Entscheidung noch live.
- PR #102 enthält den isolierten Drei-Dateien-Patch als nachvollziehbaren technischen Beleg, soll aber nicht als breiter oder regulärer Main-Merge missverstanden werden.

## 6. Verbindliches Verhalten bis zur technischen Freischaltung

Solange kein dedizierter Bypass-Akteur eingerichtet ist:

- Live-Events dürfen nach ausdrücklichem Auftrag kontrolliert in `Events` gepflegt werden.
- Ein Repo-Hotfix wird vollständig vorbereitet und statisch geprüft.
- Die KI führt **keinen** vollständigen `staging -> main`-Merge als Ersatz für einen kleinen Hotfix aus.
- Die KI behauptet nicht, der Hotfix sei live, solange der Main-Write und Live-Smoke fehlen.
- Der blockierende Ruleset-Status und der exakte Restschritt werden dokumentiert.

## 7. Einrichtungsaufgabe

Einmalige Repository-Administration:

1. dedizierten GitHub-App- oder Service-Account-Akteur für KI-Live-Hotfixes bestimmen;
2. diesen Akteur im Main-Ruleset als gezielten Bypass-Akteur freigeben;
3. mit einem harmlosen, isolierten Testcommit prüfen, dass nur dieser Akteur direkt nach `main` schreiben kann;
4. Main-Deploy und Revertpfad validieren;
5. Evidence in `TEST_STATUS.md` oder einer eigenen Evidence-Datei festhalten.

Erst nach diesem Nachweis gilt der direkte Repo-Live-Hotfixpfad als technisch betriebsbereit.

## 8. Abschluss dieses Vorgangs

Der Chat kann fachlich geschlossen werden mit folgendem Ergebnis:

- Live-Eventpflege ist als kontrollierter KI-Pfad bestätigt.
- Der Bedarf für ein eigenes Kinderzaubershow-Visual ist kanonisch erfasst.
- Der gewünschte direkte Main-Hotfixpfad ist verbindlich dokumentiert.
- Die aktuelle GitHub-Regel verhindert dessen Nutzung noch.
- Das falsche Märchenspielplatz-Bild bleibt ein offener Live-Hotfix, bis der dedizierte Ruleset-Bypass eingerichtet und der isolierte Patch direkt auf `main` geschrieben wurde.
