# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10  
Status: Härtungs- und Simulationsworkpack umgesetzt; externe Gesamtfreigabe ausstehend

## 1. Verbindliche Dokumente

- `STEUERZENTRALE_ZIELBILD.md`
- `STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md`
- `STEUERZENTRALE_VORGANGSKATALOG.md`
- `STEUERZENTRALE_INFORMATIONARCHITEKTUR.md`
- `STEUERZENTRALE_SCREENVERTRAG.md`
- `STEUERZENTRALE_ABNAHMEMATRIX.md`
- `STEUERZENTRALE_BACKEND_GAP_ANALYSE.md`
- `STEUERZENTRALE_SIMULATIONSBERICHT.md`

## 2. Verbindliche Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

Seltene Funktionen liegen im Header-Menü.

## 3. Bereits umgesetzt

### Zentrale Grundlage

- kanonisches Vorgangsmodell und Verlauf,
- eindeutige Quellreferenzen und Deduplizierung,
- Anbieter-, Content-Audit-, KI-/Sheet-, Growth-Backlog- und Repo-Workpack-Anbindung,
- dauerhaft sichtbare Navigation,
- automatische PHP-, JavaScript-, JSON-, Simulations- und Produktvertragsprüfung.

### Prüfen

- neue Inhalte, Qualitätsfälle, Anbieterentscheidungen und Freigaben,
- mobil fokussierter Einzelfall,
- Desktop Queue plus Detail,
- fallgerechte Hauptaktionen,
- Content-Korrekturformular,
- deutsche Statussprache.

### Arbeit

- Jetzt, Als Nächstes, Wartet, Blockiert, Backlog, Ideen und Archiv,
- gemeinsame Neuerfassung über `+ Neu`,
- kompakte aufklappbare Arbeitszeilen,
- Aufgaben-, Ideen- und Backlog-Lebenszyklus,
- Warte- und Blockadegründe,
- Growth-Backlog-Writeback,
- Umwandlung ohne Doppelkarte,
- deduplizierte Publikationsprobleme aus Eventänderungen.

### Eventverwaltung

Umgesetzt:

- Suche ohne Fokusverlust,
- vollständiger Datensatz,
- Bearbeitung von Titel, Beschreibung, Datum, Uhrzeit, Ort, Stadt, Kategorie, Quelle und Ticketlink,
- vollständige Vorabvalidierung,
- Aliasauflösung zur tatsächlichen Sheet-Spalte,
- gemeinsamer Google-Sheets-Batch statt einzelner Teilupdates,
- dauerhafter Änderungsverlauf in `control_content_changes`,
- automatischer Deploy-Start,
- Polling des öffentlichen `/data/events.json`-Feeds,
- Vergleich der tatsächlich geschriebenen Felder,
- bestätigter Abschluss erst nach öffentlicher Datenwirkung,
- blockierte Arbeit bei Deploy- oder Verifikationsfehlern.

Die UI verwendet bewusst `Speichern und Aktualisierung starten` und behauptet keine Veröffentlichung vor der öffentlichen Bestätigung.

### Aktivitätsverwaltung

Umgesetzt beziehungsweise vorbereitet:

- Bestand und Vorschau,
- GitHub-Contents-API-Writeback für `data/offers.json`,
- SHA-Konfliktschutz,
- erlaubte Feldmenge und URL-Validierung,
- Commit auf konfigurierten Branch,
- serverseitiger Secret-Vertrag `BE_GITHUB_REPO_TOKEN`.

Der Editor bleibt bewusst gesperrt, bis Secret, Commit, Deploy und öffentliche Wirkung auf Staging vollständig E2E bestätigt wurden.

### Entwicklung

Umgesetzt:

- realer Content-Basisstand aus dem Events-Sheet,
- offene Qualitätsprüfungen,
- aktive, wartende und blockierte Arbeit,
- Publikationsprobleme,
- stündlich begrenzte Entwicklungssnapshots,
- Trends nur mit zeitlich getrenntem Snapshot,
- Deltas für Content-Vollständigkeit, fehlende Angaben, Prüfungen, Blockaden, technische SEO und Publikationsprobleme,
- technische SEO-Basis für Startseite, Veranstaltungen und Aktivitäten,
- Prüfung von Title, Meta-Description, Canonical, Sitemap, robots.txt und Eventfeed,
- kuratierte Repo-Workpacks,
- Search-Console-Lücke ausdrücklich gekennzeichnet.

### Header-Menü und Systemstatus

- Anbieterbereich,
- verständlicher Systemstatus,
- manueller Deploy-Start,
- Abmelden,
- technische Synchronisationswerte nur eingeklappt.

### Dialoge

- Schließen-Button `type="button"`,
- keine Pflichtfeldvalidierung beim Schließen,
- Escape und Hintergrundklick.

## 4. Interne Simulation

Ausgeführt und erfolgreich:

- Eventaliasse,
- Pflichtfeld-, Datums- und URL-Validierung,
- nicht zuordenbare Sheet-Spalten,
- öffentliche Eventfeed-Aliasse,
- bestätigte und abweichende öffentliche Werte,
- nur tatsächlich geschriebene Felder,
- Entwicklungsbewertung ohne Zeitreihe,
- aktueller Handlungsbedarf,
- vier Veröffentlichungszustände.

Ergebnis:

```text
=== Control Center Contract Simulation: OK ===
```

Details: `STEUERZENTRALE_SIMULATIONSBERICHT.md`.

## 5. Noch erforderlich

### Externer Event-E2E-Nachweis

- realer Google-Sheets-Writeback,
- Apps-Script-Deploy,
- öffentlicher Feed nach Deployment,
- bestätigter Änderungsverlauf und automatisch abgeschlossene Publikationsaufgabe.

### Aktivitätsverwaltung E2E

- serverseitiges Repository-Secret konfigurieren,
- Testcommit mit SHA-Konfliktschutz,
- Deploy aus Repo-Änderung,
- öffentliche Aktivitätswirkung verifizieren,
- erst danach Editor freischalten.

### Entwicklung und SEO

- mindestens einen zeitlich getrennten Folgesnapshot erzeugen,
- Automatisierungsqualität um echte Treffer-/Fehlerquoten erweitern,
- negative Signale dedupliziert in Arbeit überführen,
- Search Console anbinden oder ausdrücklich als späteren nicht blockierenden Ausbau entscheiden.

### Verwaltung vervollständigen

- Absage- und Änderungsstatus,
- Objektverlauf sichtbar im Editor darstellen,
- Aktivitätseditor nach E2E-Freigabe.

### Altprozess ablösen

`/inbox/` bleibt vorläufig für noch nicht vollständig migrierte Push- und Diagnosefunktionen.

## 6. Automatische Absicherung

`.github/workflows/control-center-validation.yml` prüft:

- PHP-Syntax aller Control-Center-Dateien,
- JavaScript-Syntax aller vier UI-Skripte,
- ausführbare Domänensimulation,
- Produktvertragsaudit,
- JSON-Gültigkeit,
- Pflichtdateien,
- Zugriffsschutz,
- Quell-, Verlaufs-, Snapshot- und Publikationsverträge,
- Aktivitäts-Repo-Writeback und bewusstes Editor-Gate.

Ein erfolgreicher GitHub-Actions-Lauf ist über den aktuell verfügbaren Connector noch nicht belegt.

## 7. Freigabestatus

| Bereich | Status |
|---|---|
| Informationsarchitektur | umgesetzt |
| Prüfen | funktional; externe Quellaktionen noch real zu belegen |
| Arbeit | konsolidiert und intern simuliert |
| Eventverwaltung | technisch E2E modelliert; realer Staging-Nachweis offen |
| Event-Veröffentlichungsbestätigung | implementiert; externer Nachweis offen |
| Aktivitätsverwaltung | Writeback vorbereitet, Editor sicher gesperrt |
| Entwicklung | Snapshots und technische SEO umgesetzt; erster Folgevergleich offen |
| Search Console | nicht angebunden |
| Systemstatus | fachliche Grundsicht umgesetzt |
| Alte Inbox-Ablösung | teilweise |
| CI-Nachweis | noch nicht verfügbar |
| Main-Merge | nicht zulässig |

## 8. Nächste Entscheidung

Es werden noch keine Benutzer-Prüfschritte als Freigabe ausgegeben. Zuerst müssen CI-Status und die externen Staging-Systeme soweit erreichbar sein, dass die implementierten Abläufe real gegen Google Sheets, Apps Script, MySQL, GitHub und den öffentlichen Feed nachgewiesen werden können.
