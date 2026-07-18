# Steuerzentrale – verbindlicher Screen- und Interaktionsvertrag

Stand: 2026-07-10
Status: verbindlicher UI-Vertrag

## 1. Globale Shell

### Header

- kompakte Produktkennung,
- aktueller Bereichstitel,
- Aktualisieren nachrangig,
- keine dekorative Höhe.

### Hauptnavigation

Dauerhaft erreichbar mit fünf Zielen:

- Übersicht
- Bearbeiten
- Arbeit
- Verwaltung
- Menü

Mobil/Tablet als fixierte Bottom-Bar, Desktop als fixierte Bottom-Bar oder feste Seitenleiste. Safe Area und ausreichender Inhaltsabstand sind Pflicht.

### Rückmeldung

Nach jeder Aktion:

- was geändert wurde,
- ob Quell-Writeback erfolgreich war,
- ob Veröffentlichung noch läuft oder fehlschlug,
- wohin der Vorgang gewechselt ist.

## 2. Übersicht

Darstellung ausschließlich als verdichtete Zusammenfassung:

```text
Jetzt erforderlich
2
1 kritische Korrektur · 1 überfällige Aufgabe
[Jetzt bearbeiten]

Zu bearbeiten
12
8 Qualität · 4 Anbieter
[Bearbeiten öffnen]

Arbeit
3 aktiv · 1 wartet
[Arbeit öffnen]

System
Keine bekannte Störung mit Auswirkung
```

Keine vollständigen Arbeitskarten.

## 3. Bearbeiten

### Mobil

- Filterleiste,
- genau ein Vorgang,
- Position in Queue,
- fachlicher Typ und deutscher Status,
- Problem/Anlass,
- erforderlicher Schritt,
- aktuelle Daten beziehungsweise Vorher/Nachher,
- Quelle,
- eine dominante Hauptaktion,
- maximal zwei sichtbare Nebenaktionen,
- weitere Details eingeklappt,
- Vorher/Weiter.

### Desktop

- kompakte Queue links,
- vollständiger Vorgang rechts,
- kein Kartenraster,
- Queue-Scrollposition und Auswahl bleiben erhalten.

### Fallspezifische Hauptaktionen

| Fall | Hauptaktion |
|---|---|
| neuer Kandidat | Übernehmen |
| Anbieter vor Zahlung | Zahlung freigeben |
| bezahlt | Veröffentlichen |
| Beschreibung fehlerhaft | Korrigieren und übernehmen |
| Quelle fehlerhaft | Quelle korrigieren |
| Änderung | Änderung übernehmen |
| Absage | Absage übernehmen |
| Faktenprüfung | Als korrekt bestätigen |

## 4. Arbeit

### Hauptscreen

Segmentierte Unteransichten:

- Aktiv
- Als Nächstes
- Wartet
- Blockiert
- Backlog
- Ideen
- Archiv

### Aufgabenzeile

- Titel,
- nächster Schritt,
- Status,
- Fälligkeit oder Warte-/Blockadegrund,
- Objektbezug,
- passende Hauptaktion.

### Backlogzeile

- Titel,
- Kategorie,
- Priorität,
- Nutzen/Risiko,
- Herkunft,
- letzte Aktualisierung,
- Aktionen: bearbeiten, starten, verwerfen.

### Idee

- schnelle Erfassung,
- Anlass/Nutzen,
- optional Kategorie,
- Aktionen: parken, verwerfen, in Backlog, direkt starten.

Übergänge erzeugen keine Kopien.

## 5. Verwaltung

### Liste

- Umschaltung Events/Aktivitäten,
- Suchfeld mit stabilem Fokus,
- Debounce oder lokale Filterung,
- nur Ergebnisliste wird beim Tippen aktualisiert,
- Filter nach Publikationsstatus und offenem Handlungsbedarf,
- Quelle/letzte Aktualisierung,
- offene Vorgänge.

### Detail/Editor

- öffentliche Vorschau,
- bearbeitbare fachliche Felder,
- klare Gruppierung,
- Speichern und Verwerfen,
- Validierungsfehler direkt am Feld,
- Quellen- und Qualitätskontext,
- Verlauf.

### Speichern

```text
Validieren
-> führende Quelle aktualisieren
-> Verlauf schreiben
-> Daten erzeugen/Deploy anstoßen
-> öffentliche Wirkung bestätigen
```

Bei Fehler bleibt der Editor geöffnet. Der konkrete fehlgeschlagene Schritt wird angezeigt.

## 6. Menü

Nur selten benötigte Funktionen:

- Anbieterbereich,
- Systemstatus,
- Einstellungen,
- Abmelden,
- technische Diagnose eingeklappt.

Ideen und Backlog gehören nicht in das Menü.

## 7. Systemstatus

Normalansicht:

```text
Alle relevanten Quellen sind synchronisiert.
Keine bekannte Störung mit Auswirkung.
```

Bei Störung:

```text
Content-Prüfung konnte nicht aktualisiert werden.
Neue Prüffälle können fehlen.
[Erneut versuchen]
[Technische Details]
```

Rohwerte wie JSON, `seen` und `upserted` erscheinen nur in technischen Details.

## 8. Suche und Fokus

- Eingabefeld wird beim Filtern nicht neu erzeugt,
- Fokus und Cursorposition bleiben erhalten,
- Suchbegriff bleibt beim Öffnen und Zurückkehren erhalten,
- keine vollständige Seiten-Neurenderung pro Zeichen.

## 9. Dialoge

- mobil vollständig nutzbar,
- sichtbarer Fokus,
- Schließen eindeutig,
- Fehler zerstören keine Eingaben,
- destruktive Aktionen verlangen Grund oder Undo.
