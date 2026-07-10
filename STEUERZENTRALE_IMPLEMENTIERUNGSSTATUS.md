# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10  
Status: Gesamtumbau `Prüfen / Arbeit / Verwaltung / Entwicklung` umgesetzt; Gesamtfreigabe ausstehend

## 1. Verbindliche Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

Seltene Funktionen liegen im Header-Menü.

## 2. Bereits umgesetzt

### Zentrale Grundlage

- kanonisches Vorgangsmodell und Verlauf,
- eindeutige Quellreferenzen und Deduplizierung,
- Quell-Writeback vor zentralem Abschluss,
- Anbieter-, Content-Audit-, KI-/Sheet-, Growth-Backlog- und Repo-Workpack-Anbindung,
- dauerhaft sichtbare Navigation,
- automatische PHP-, JavaScript-, JSON- und Produktvertragsprüfung.

### Prüfen

- neue Inhalte, Qualitätsfälle, Anbieterentscheidungen und Freigaben,
- mobil fokussierter Einzelfall,
- Desktop Queue plus Detail,
- fallgerechte Hauptaktionen,
- Content-Korrekturformular bleibt erhalten,
- deutsche Statussprache.

### Arbeit

- Jetzt, Als Nächstes, Wartet, Blockiert, Backlog, Ideen und Archiv,
- gemeinsame Neuerfassung über `+ Neu`,
- kompakte aufklappbare Arbeitszeilen,
- Aufgaben-, Ideen- und Backlog-Lebenszyklus,
- Warte- und Blockadegründe,
- Growth-Backlog-Writeback,
- Umwandlung ohne Doppelkarte.

### Verwaltung

#### Veranstaltungen

- Suche ohne Fokusverlust,
- vollständigen Datensatz öffnen,
- Titel, Beschreibung, Datum, Uhrzeit, Ort, Stadt, Kategorie, Quelle und Ticketlink bearbeiten,
- Speichern in das führende Events-Sheet,
- anschließend etablierten Deployprozess automatisch starten,
- öffentliche Vorschau öffnen,
- Teilfehler zwischen Speichern und Deploy verständlich melden.

#### Aktivitäten

- Bestand und Vorschau vorhanden,
- dauerhafte Bearbeitung bewusst noch gesperrt,
- Grund: führende Quelle ist `data/offers.json`; ein lokaler Webserver-Writeback wäre nicht versionsfest und würde beim nächsten Deploy verloren gehen.

### Entwicklung

- eigener Haupttab,
- Content-Vollständigkeit aus realen Eventdaten,
- offene Qualitätsprüfungen,
- aktive, wartende und blockierte Arbeit,
- erledigte Vorgänge,
- SEO-Onpage-Signale,
- kuratierte offene Repo-Workpacks,
- Search-Console-Lücke ausdrücklich gekennzeichnet statt Kennzahlen zu erfinden.

### Header-Menü und Systemstatus

- Anbieterbereich,
- verständlicher Systemstatus,
- manueller Deploy-Start,
- Abmelden,
- technische Synchronisationswerte nur eingeklappt.

### Dialoge

- Schließen-Button ist `type="button"`,
- keine Pflichtfeldvalidierung beim Schließen,
- Escape und Klick auf den Hintergrund funktionieren zusätzlich.

## 3. Noch erforderlich

### Aktivitätsverwaltung E2E

- abgesicherten Repo-Writeback für `data/offers.json` bereitstellen,
- Commit/Deploy atomar beziehungsweise nachvollziehbar orchestrieren,
- Aktivitätseditor danach freischalten,
- Fehlerzustände und Versionskonflikte behandeln.

### Entwicklung und SEO vertiefen

- Search Console anbinden,
- echte Zeitreihen und Vergleichszeiträume speichern,
- Automatisierungsqualität statt nur Mengen messen,
- Regressionen gegenüber dem vorherigen Lauf erkennen,
- negative Signale dedupliziert in Arbeit überführen.

### Verwaltung vervollständigen

- Absage- und Änderungsstatus,
- Änderungsverlauf,
- öffentliche Wirkung nach Deploy automatisiert bestätigen,
- fachliche Feldvalidierung weiter härten.

### Altprozess ablösen

`/inbox/` bleibt vorläufig für noch nicht vollständig migrierte Push- und Diagnosefunktionen. Reguläre Prüfung, Arbeit und Backlog liegen in der Steuerzentrale.

## 4. Freigabestatus

| Bereich | Status |
|---|---|
| Übersicht | strukturell umgesetzt, Laufzeitprüfung offen |
| Prüfen | funktional, reale Writebacks weiter prüfen |
| Arbeit | konsolidiert, kompakte Darstellung umgesetzt |
| Eventverwaltung | führender Writeback plus Deploy umgesetzt, Staging-Test offen |
| Aktivitätsverwaltung | nur lesend; Repo-Writeback fehlt |
| Entwicklung | belastbare Grundsicht umgesetzt |
| Search Console | noch nicht angebunden |
| Systemstatus | fachliche Grundsicht umgesetzt |
| Alte Inbox-Ablösung | teilweise |
| Main-Merge | nicht zulässig |

## 5. Nächster Prüfschritt

Nach erfolgreichem Deployment sind auf Staging insbesondere zu prüfen:

1. Navigation `Übersicht / Prüfen / Arbeit / Verwaltung / Entwicklung`.
2. Dialoge lassen sich jederzeit über `X` schließen.
3. Backlog erscheint als kompakte Zeilen und klappt kontrolliert auf.
4. Ein echter Testevent kann bearbeitet, im Sheet gespeichert und mit Deploy gestartet werden.
5. Aktivitätsbearbeitung erklärt die noch bestehende Repo-Grenze korrekt.
6. Entwicklung zeigt nur reale Werte und kennzeichnet fehlende Search-Console-Daten.
7. Systemstatus zeigt zuerst fachliche Informationen und technische Details nur nach Aufklappen.
