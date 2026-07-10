# Steuerzentrale – Simulations- und Validierungsbericht

Stand: 2026-07-10  
Status: finaler Härtungspatch intern simuliert; externe Laufzeitnachweise separat offen

## 1. Prüfprinzip

Der Bericht trennt verbindlich:

1. statische Syntax- und Vertragsprüfung,
2. tatsächlich ausgeführte interne Domänensimulation,
3. externe Laufzeitnachweise gegen Google Sheets, Apps Script, MySQL, GitHub und öffentliche Feeds.

Keine dieser Ebenen wird als Ersatz für eine andere ausgegeben.

## 2. Tatsächlich ausgeführte Simulation

Ausgeführt wurden die reinen Laufzeitverträge für:

### Eventverwaltung

- Aliasauflösung `date/start_date`, `location/venue`, `kategorie/category`, `source_url/url/event_url`,
- vollständige Updateplanung vor dem ersten Schreibzugriff,
- leerer Titel,
- ungültiges Datum,
- unsichere URL,
- fehlende Zielspalte ohne falschen Erfolg,
- öffentliche Werte mit abweichenden Feldnamen,
- bestätigte öffentliche Änderung,
- erkannte öffentliche Abweichung,
- bewusst nicht geschriebene optionale Felder.

### Aktivitätsverwaltung

- erlaubte Feldmenge,
- Verwerfen unbekannter Felder,
- leerer Titel,
- unsichere URL,
- Standardsperre ohne explizite Serverfreigabe,
- unterscheidbare Zustände `nicht konfiguriert`, `nicht freigeschaltet`, `bereit`.

### Arbeitslebenszyklus

Geprüft wurden unter anderem:

```text
open → in_progress → waiting → open
open → blocked → open
open → snoozed → open
open → done → open
new → task/open
decision_required → done
new → rejected
```

Zusätzlich:

- unzulässige Aktion für aktuellen Status,
- unbekannte Aktion.

Laufzeit und Test verwenden denselben Vertrag aus `api/control-center/_workflow_contracts.php`.

### Entwicklung und Veröffentlichung

- Basisstand ohne erfundenen Trend,
- aktueller Handlungsbedarf,
- Quelle fehlgeschlagen,
- Deploy blockiert,
- wartet auf öffentliche Bestätigung,
- öffentlich bestätigt.

## 3. Ausführungsergebnis

Lokal ausgeführt:

```text
No syntax errors detected in api/control-center/_contracts.php
No syntax errors detected in api/control-center/_github_repo.php
No syntax errors detected in api/control-center/_workflow_contracts.php
No syntax errors detected in tests/control_center_contracts_test.php
=== Control Center Contract Simulation: OK ===
```

Zusätzlich wurden die beiden im finalen Patch geänderten Browsercontroller mit `node --check` geprüft:

- `js/control-center-source-editors.js`,
- `js/control-center-publication.js`.

Beide Syntaxprüfungen waren erfolgreich.

## 4. Gehärteter Veröffentlichungsprozess

### Veranstaltung

```text
Editor
→ vollständige Vorabvalidierung
→ gemeinsamer Sheet-Batch
→ dauerhafter Änderungsverlauf
→ Apps-Script-Deploy
→ wartender Arbeitsvorgang
→ /data/events.json prüfen
→ geänderte Felder vergleichen
→ erst danach bestätigen
```

### Aktivität

```text
Editor-Gate
→ explizite Serverfreigabe prüfen
→ aktuelle offers.json samt SHA laden
→ Felder validieren
→ versionierten GitHub-Commit schreiben
→ wartender Arbeitsvorgang
→ /data/offers.json prüfen
→ geänderte Felder vergleichen
→ erst danach bestätigen
```

Fehler bleiben als deduplizierte Arbeit sichtbar.

## 5. Aktivitäts-Sicherheitsgate

Für eine Aktivierung sind gleichzeitig erforderlich:

- gültiges `BE_GITHUB_REPO_TOKEN`,
- gültige Repository- und Branch-Konfiguration,
- `BE_ACTIVITY_WRITEBACK_ENABLED=true`.

Ein Token allein reicht nicht. GitHub-Konflikte erzeugen eine verständliche Meldung; ein API-Erfolg ohne bestätigte Commit-SHA gilt als Fehler.

## 6. Automatische CI-Prüfung

`.github/workflows/control-center-validation.yml` prüft:

- PHP-Syntax aller Control-Center-Endpunkte,
- JavaScript-Syntax aller vier UI-Skripte,
- ausführbare Domänensimulation,
- Produktvertragsaudit,
- JSON-Gültigkeit,
- Pflichtdateien,
- Zugriffsschutz,
- gemeinsamen Workflow-Vertrag,
- Event- und Aktivitätswriteback,
- explizites Aktivitäts-Gate,
- Änderungsverlauf, Snapshots und öffentliche Verifikation.

Über den aktuell verfügbaren Connector wird für den jüngsten Commit noch kein GitHub-Statuscheck geliefert. Ein grüner Actions-Lauf wird daher nicht behauptet.

## 7. Gegen den Premium-Zielzustand

| Zielkriterium | Intern geprüft |
|---|---|
| klare Informationsarchitektur | ja |
| Prüfen und Arbeit getrennt | ja |
| kompakte Arbeitsansicht | ja |
| einheitlicher Arbeitslebenszyklus | ja |
| Eventvalidierung und gebündelter Writeback | ja |
| dauerhafter Änderungsverlauf | ja |
| öffentliche Eventverifikation | ja, als Domänen- und Datenvergleich |
| Aktivitäts-Repo-Writeback | ja, einschließlich Sicherheitsgate |
| öffentliche Aktivitätsverifikation | ja, als Datenvergleich |
| Teilfehler bleiben als Arbeit sichtbar | ja |
| Entwicklung ohne erfundene Kennzahlen | ja |
| technische SEO-Basis | ja |
| Search Console | nicht angebunden |
| alte Inbox vollständig abgelöst | nein, Diagnose-/Push-Restfunktionen |
| reale externe Gesamtkette | noch auf Staging nachzuweisen |

## 8. Externe Nachweise

Nicht intern simulierbar sind:

- reale Google-Sheets-Berechtigung,
- Apps-Script-Unlock und Deployment,
- Staging-MySQL-Schema,
- Dauer des realen Deployments,
- tatsächliche öffentliche Feed-Aktualisierung,
- GitHub-Secret und dessen konkrete Rechte,
- Browserdarstellung auf Zielgeräten,
- Search-Console-Daten.

## 9. Ergebnis

Der finale Härtungspatch ist intern konsistent modelliert und die kritischen Domänenprozesse sind erfolgreich simuliert. Weitere Codehärtung ist vor dem nächsten Staging-Nachweis nicht erforderlich. Eine Main-Freigabe folgt daraus noch nicht; sie setzt den realen externen E2E-Nachweis voraus.
