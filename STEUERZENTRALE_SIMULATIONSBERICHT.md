# Steuerzentrale – Simulations- und Validierungsbericht

Stand: 2026-07-10  
Status: interne Verträge simuliert; externe Laufzeitnachweise teilweise offen

## 1. Zweck

Dieser Bericht trennt verbindlich zwischen:

1. statisch geprüftem Code,
2. tatsächlich ausgeführten internen Simulationen,
3. noch nicht beweisbaren externen Laufzeitpfaden.

Ein erfolgreicher Syntax- oder Vertragstest wird nicht als Beweis für Google Sheets, Apps Script, GitHub, Deployment oder öffentliche Erreichbarkeit ausgegeben.

## 2. Tatsächlich ausgeführte interne Simulation

Ausgeführt wurde `tests/control_center_contracts_test.php` gegen die reinen Domänenverträge aus `api/control-center/_contracts.php`.

Simulierte Szenarien:

- Eventfeld-Aliasse `date/start_date`, `location/venue`, `kategorie/category` und `source_url/url/event_url`,
- vollständige Event-Updateplanung vor dem ersten Schreibzugriff,
- leerer Pflicht-Titel,
- ungültiges Startdatum,
- unsichere URL,
- nicht vorhandene Zielspalte ohne falschen Erfolgsstatus,
- öffentlicher Eventfeed mit abweichenden Feldnamen,
- erfolgreich bestätigte öffentliche Änderung,
- erkannte Abweichung zwischen erwartetem und öffentlichem Wert,
- bewusst nicht geschriebene optionale Felder,
- Entwicklungsbewertung ohne Zeitreihe,
- Entwicklungsbewertung mit aktuellem Handlungsbedarf,
- Veröffentlichungskette `Quelle fehlgeschlagen`, `Deploy blockiert`, `wartet auf öffentliche Bestätigung`, `öffentlich bestätigt`.

Ergebnis nach Korrektur des Testvertrags:

```text
=== Control Center Contract Simulation: OK ===
```

Der erste Durchlauf fand einen falsch formulierten Erwartungstext im Test. Dieser wurde korrigiert und die Simulation erneut erfolgreich ausgeführt.

## 3. Durch die Gesamtprüfung gefundene und behobene Fehler

### 3.1 Doppelte PHP-Funktion

`be_cc_public_event_value()` war gleichzeitig in Vertrags- und Veröffentlichungsmodul definiert. Das hätte zur Laufzeit eine fatale Funktions-Neudeklaration verursacht.

Behoben durch eine einzige führende Definition in `_contracts.php`.

### 3.2 Partieller Event-Writeback

Eventfelder wurden zuvor einzeln geschrieben. Ein später Fehler konnte einen teilweise geänderten Datensatz hinterlassen.

Behoben durch:

- vollständige Vorabvalidierung,
- Aliasauflösung,
- gemeinsamen Google-Sheets-Batch,
- Fehler bei nicht zuordenbaren Feldern statt stillem Überspringen.

### 3.3 Falsches Veröffentlichungsversprechen

Der UI-Text behauptete `Speichern und veröffentlichen`, obwohl nur ein Deploy gestartet wurde.

Behoben durch:

- `Speichern und Aktualisierung starten`,
- dauerhaften Änderungsdatensatz,
- Polling des öffentlichen Eventfeeds,
- Abschluss erst nach bestätigten öffentlichen Werten.

### 3.4 Verlorene Teilfehler

Ein fehlgeschlagener Deploy oder eine nach fünf Minuten nicht bestätigte öffentliche Wirkung konnte bislang nur als kurzlebige Meldung erscheinen.

Behoben durch deduplizierte Aufgaben aus `publication_pipeline` unter `Arbeit`.

### 3.5 Unbelegte Entwicklungsbewertung

Ohne Zeitreihe wurde ein stabiler Projektzustand behauptet.

Behoben durch:

- stündlich begrenzte Snapshots,
- Trend erst mit zeitlich getrenntem Vergleich,
- ausdrücklichen Basisstand beim ersten Snapshot,
- Deltas für Content, Prüfungen, Blockaden, technische SEO und Veröffentlichungsprobleme.

### 3.6 Zu schmale SEO-Sicht

SEO bestand nur aus vorhandener Beschreibung und Quelle.

Ergänzt wurden statische technische Basissignale für zentrale Seiten:

- Title,
- Meta-Description,
- Canonical,
- Sitemap,
- robots.txt,
- öffentlicher Eventfeed.

Search Console bleibt transparent als nicht angebunden gekennzeichnet.

## 4. Veröffentlichungsprozess nach Härtung

```text
Editor
→ vollständige Vorabvalidierung
→ gemeinsamer Writeback in das Events-Sheet
→ dauerhafter Änderungsverlauf
→ Deploy starten
→ öffentlichen /data/events.json-Feed pollen
→ geänderte Felder vergleichen
→ erst danach bestätigt
```

Fehlerzustände:

- Quelle nicht geschrieben: Fehler, keine Veröffentlichung,
- Deploy nicht gestartet: blockierte Aufgabe,
- öffentliche Wirkung noch ausstehend: wartender Zustand,
- nach fünf Minuten nicht bestätigt: blockierte Aufgabe,
- bestätigt: eventuelle Publikationsaufgabe wird abgeschlossen.

## 5. Automatische CI-Prüfung

Der Workflow `.github/workflows/control-center-validation.yml` führt nun aus:

- PHP-Syntax aller Control-Center-Endpunkte,
- JavaScript-Syntax aller vier UI-Skripte,
- ausführbare Domänensimulation,
- Produktvertragsaudit,
- JSON-Validierung,
- Sicherheits- und Quellverträge,
- Pflichtprüfung für Verlauf, Snapshots, öffentliche Verifikation und Aktivitäts-Gate.

Ein grüner GitHub-Actions-Lauf ist über den aktuell verfügbaren Connector noch nicht belegt. Dieser Bericht behauptet ihn daher nicht.

## 6. Aktivitätsverwaltung

Ein GitHub-Contents-API-Writeback mit SHA-Konfliktschutz ist vorbereitet.

Voraussetzungen:

- serverseitiges Secret `BE_GITHUB_REPO_TOKEN`,
- Schreibrecht nur auf das benötigte Repository,
- konfigurierter Branch,
- reale Commit-, Deploy- und öffentliche Verifikationsprüfung.

Der Aktivitätseditor bleibt bis zu diesem E2E-Nachweis bewusst gesperrt. Ein vorhandener Token allein aktiviert ihn nicht automatisch.

## 7. Noch nicht intern vollständig beweisbar

Die folgenden realen Integrationen benötigen die Staging-Laufzeit und externe Systeme:

- Google-Sheets-Schreibzugriff,
- Apps-Script-Unlock und Deploy,
- tatsächliche Dauer des Deployments,
- öffentlicher Feed nach Deployment,
- MySQL-Schemaerweiterung auf Staging,
- GitHub-Repo-Secret und Activity-Commit,
- Search-Console-Daten,
- Browserdarstellung auf den Zielgeräten.

Die internen Simulationen stellen sicher, dass die Entscheidungen und Fehlerzustände korrekt modelliert sind. Sie ersetzen diese externen Nachweise nicht.

## 8. Premium-Abgleich

| Zielkriterium | Stand |
|---|---|
| klare Informationsarchitektur | umgesetzt |
| Prüfen und Arbeit getrennt | umgesetzt |
| kompakter Arbeitsbereich | umgesetzt |
| Event-Writeback vorvalidiert und gebündelt | umgesetzt |
| dauerhafter Änderungsverlauf | umgesetzt |
| öffentliche Eventwirkung technisch verifizierbar | umgesetzt, externer Laufzeitnachweis offen |
| Teilfehler bleiben als Arbeit sichtbar | umgesetzt |
| Entwicklung mit echter Vergleichsbasis | umgesetzt; erster zeitlich getrennter Folgesnapshot erforderlich |
| technische SEO-Basis | umgesetzt |
| Search Console | offen |
| Aktivitätsverwaltung E2E | vorbereitet, bewusst gesperrt |
| alte Inbox vollständig abgelöst | offen |
| kompletter externer E2E-Nachweis | offen |
| Main-Merge | nicht freigegeben |

## 9. Freigaberegel

Die Steuerzentrale darf erst als Premium-Zielzustand und Main-Merge-Kandidat gelten, wenn zusätzlich:

- CI nachweislich grün ist,
- Eventänderung auf Staging bis in den öffentlichen Feed bestätigt wurde,
- Aktivitäts-Repo-Writeback sicher konfiguriert und E2E bestätigt ist,
- mindestens ein zeitlich getrennter Entwicklungssnapshot vorliegt,
- Search Console entweder angebunden oder bewusst als späterer, nicht blockierender Datenbaustein entschieden ist,
- verbleibende Funktionen der alten Inbox migriert oder bewusst dauerhaft abgegrenzt sind.
