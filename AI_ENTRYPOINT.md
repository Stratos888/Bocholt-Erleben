# Bocholt erleben – KI-Arbeitsweise

Arbeitsbaseline ist `staging`. Ziel ist eine autonome Lieferung: Der Nutzer beschreibt die gewünschte Wirkung; die KI setzt sie zuverlässig um und meldet sich, sobald sie auf Staging konkret geprüft werden kann.

## 1. Start

Vor jeder Repository-Änderung:

1. aktuellen `staging`-Stand lesen;
2. bestehende Branches und Pull Requests zur Aufgabe prüfen;
3. den fachlichen Owner der betroffenen Dateien bestimmen;
4. vorhandene passende Arbeit fortsetzen, statt eine zweite Lösung zu beginnen;
5. nur den für die Änderung notwendigen Kontext lesen.

Reine Analyse bleibt read-only und benötigt weder Branch noch Issue noch Pull Request.

## 2. Normaler Änderungsweg

Kleine und mittlere Änderungen benötigen kein Workpack-Issue.

```text
Aufgabe verstehen
-> bestehenden Stand prüfen
-> ein Feature-Branch
-> ein Pull Request nach staging
-> passende Tests
-> Merge nach staging
-> normalen Staging-Deploy prüfen
-> fertig zur Prüfung melden
```

Der Pull Request darf eine normale Beschreibung ohne Prozessmetadaten enthalten.

## 3. Workpack nur bei echtem Bedarf

Ein Workpack wird verwendet, wenn mindestens einer dieser Punkte zutrifft:

- die Arbeit muss über mehrere Chats zuverlässig fortgesetzt werden;
- mehrere Systeme oder fachliche Owner werden gemeinsam verändert;
- Datenbankschema, Berechtigungen, Zahlungen, Nachrichten oder externe Writes sind betroffen;
- mehrere voneinander abhängige Umsetzungsschritte müssen gemeinsam abgenommen werden;
- zentrale Governance- oder Deploymentregeln werden verändert.

Ein Workpack besitzt genau:

- ein offenes Issue mit `[ACTIVE WORKPACK]`;
- einen dort deklarierten Branch;
- einen Pull Request mit `Workpack: #<Issue>`;
- einen kompakten TOML-Vertrag mit Ziel, Pfaden, Tests und Sicherheitsgrenzen.

Für normale Änderungen wird kein künstliches Workpack erzeugt.

## 4. Parallelität

Unabhängige Pull Requests dürfen parallel offen sein.

Blockiert werden:

- dieselbe geänderte Datei in mehreren offenen Pull Requests;
- mehrere Pull Requests für dasselbe Workpack;
- parallele Änderungen am selben zentralen Owner, sofern sie fachlich voneinander abhängen.

Gehört eine neue Aufgabe zu einem vorhandenen Pull Request, wird dort weitergearbeitet.

Zentrale Schema-, Authentifizierungs-, Zahlungs-, Deployment- und Governancebereiche bleiben seriell und benötigen ein Workpack.

## 5. Werkzeugwahl

### Chat und GitHub-Connector

Geeignet für:

- Repository- und PR-Prüfung;
- Issues, Checks, Logs und Merge;
- kleine, vollständig gelesene und deterministische Text- oder Konfigurationsänderungen.

### Checkout-fähiger Code-Agent

Erforderlich für größere Code-, Build-, Test- oder UI-Arbeit:

- vollständiger Checkout;
- lokale Suche und Patchbearbeitung;
- lokale Syntax- und Contracttests;
- gebündelte Commits.

Ein fehlender Checkout wird nicht durch viele einzelne Remote-Datei-Commits ersetzt.

## 6. Tests nach tatsächlichem Risiko

Der PR-Gate-Validator plant automatisch:

- `docs`: Dokumentation und Text;
- `quick`: Werkzeuge, Tests und kleine technische Konfiguration;
- `backend`: PHP-, API- und zugehörige Backendverträge;
- `frontend`: HTML, CSS, JavaScript und relevante Browsernachweise;
- `full`: Workpacks, Datenbank-, Governance-, gemischte oder nicht eindeutig begrenzte Änderungen.

Draft-Pushes erhalten nur den schnellen Check. Reviewbereite Pull Requests erhalten den zum Diff passenden Abschlusscheck.

Vor dem Merge werden aktueller Head-SHA, aktueller Diff, offene Konflikte und aktuelle Checks erneut geprüft. Bei Workpacks wird unmittelbar vor dem Merge einmal das PR-Label `final-validation` gesetzt. Dieses Ereignis startet den Required Check neu; er lädt den aktuellen Issue-Vertrag live und bindet das Ergebnis an den exakten PR-Head.

## 7. Staging und Live

Normale Umsetzung endet auf `staging`.

- kein direkter Commit nach `staging` oder `main`;
- Merge nur bei grünem Check auf dem aktuellen Head-SHA;
- normaler Staging-Deploy und vorhandener Smoke genügen, sofern kein konkretes Risiko offen bleibt;
- zusätzliche synthetische Evidence nur bei einem benannten unbelegten Risiko;
- Mutation, Readback und Cleanup erfolgen in einem Lauf.

`main` bleibt der Live-Releasebranch. Release ausschließlich über `staging -> main`.

Echte Nachrichten, Zahlungen, Berechtigungsänderungen, irreversible externe Writes und gesperrte Live-Aktionen benötigen die entsprechende Freigabe.

## 8. Kommunikation mit dem Nutzer

Normale interne Schritte werden nicht einzeln berichtet.

Nach erfolgreichem Merge und Staging-Prüfung lautet die Rückmeldung:

```text
Fertig zur Prüfung auf Staging.

Geändert:
- konkrete Wirkung

Automatisch geprüft:
- relevante Tests

Bitte prüfen:
- konkrete Seite, Funktion und gegebenenfalls Viewport

Unverändert:
- wichtige Sicherheitsgrenzen
```

Früher wird nur gefragt, wenn eine notwendige fachliche Entscheidung weder aus Auftrag, Repository noch bestehender Dokumentation eindeutig ableitbar ist.

## 9. Definition of Done

Eine Änderung ist fertig, wenn:

- die beauftragte Wirkung vollständig umgesetzt ist;
- vorhandene passende Arbeit fortgeführt und keine parallele Alternative erzeugt wurde;
- der aktuelle Diff konfliktfrei und passend geprüft ist;
- der aktuelle Head-SHA grün ist;
- die Änderung nach `staging` gemergt wurde;
- der normale Staging-Deploy und relevante Smoke grün sind;
- dauerhaftes Wissensdelta genau einmal dokumentiert wurde;
- der Nutzer eine konkrete Prüfaufforderung erhalten hat.
