# Steuerzentrale – verbindlicher Screen- und Interaktionsvertrag

Stand: 2026-07-10
Status: verbindlicher Produktvertrag vor weiterer UI-Umsetzung

## 1. Zweck

Dieses Dokument übersetzt Vorgangskatalog und Informationsarchitektur in konkrete Bildschirm- und Interaktionszustände. Kein sichtbarer Bereich darf umgesetzt werden, ohne diesen Vertrag vollständig zu erfüllen.

## 2. Globale Shell

### Header

- kompakte Produktkennung `Bocholt erleben`
- aktueller Bereichstitel
- Aktualisieren nur als nachrangige Aktion
- keine dekorative Höhe

### Hauptnavigation

Mobil und Tablet:

- dauerhaft fixierte Bottom-Bar
- fünf Ziele: Übersicht, Bearbeiten, Aufgaben, Verwaltung, Mehr
- bei jeder Scrollposition sichtbar
- Safe-Area berücksichtigt
- mindestens 44 px Touchziel

Desktop:

- feste linke Navigation oder dauerhaft fixierte Bottom-Bar
- niemals erst am Seitenende

### Rückmeldungen

Nach jeder Aktion erscheint eine kurze Rückmeldung:

- was geändert wurde
- ob weitere Arbeit nötig ist
- wohin der Vorgang gewechselt ist

Leicht reversible Aktionen verwenden bevorzugt Undo.

## 3. Screen: Übersicht mobil

### Ziel

Innerhalb weniger Sekunden erfassen, ob und wo Handlung nötig ist.

### Aufbau

```text
Bocholt erleben
Übersicht

Jetzt erforderlich
2 Vorgänge
1 bezahlte Einreichung · 1 kritische Quellenkorrektur
[Jetzt bearbeiten]

Neuer Eingang
12 Vorgänge
8 Qualitätsprüfungen · 3 neue Inhalte · 1 Anbieterfall
[Eingang öffnen]

Als Nächstes
1 wartender Vorgang
Zahlungsbestätigung für Pilotmail Test
[Aufgaben öffnen]

Zur Kenntnis
3 automatische Prüfungen erfolgreich

System
Alles läuft ohne bekannte Auswirkung
```

### Regeln

- keine vollständige Arbeitskarte
- höchstens zwei priorisierte Beispiele
- pro Block genau ein Einstieg
- leere Blöcke werden kompakt dargestellt oder ausgeblendet
- Systemnormalzustand ruhig und kurz

## 4. Screen: Übersicht Desktop

- dieselben verdichteten Blöcke
- maximal zweispaltige Anordnung von Zusammenfassungen
- keine vollständigen Vorgangskarten
- oberster sichtbarer Bereich beantwortet die Handlungsfrage ohne Scrollen

## 5. Screen: Bearbeiten mobil

### Ziel

Einen Vorgang sicher, schnell und ohne Ablenkung bearbeiten.

### Aufbau

```text
Bearbeiten
[Alle] [Neue Inhalte] [Änderungen] [Qualität] [Anbieter] [Freigaben]

Qualitätsprüfung · 3 von 8

SummerSchool der JUNGE UNI

Problem
Die Beschreibung enthält eine Quellenherleitung.

Aktueller Text
...

Erforderliche Korrektur
Beschreibung lokal-redaktionell formulieren.

Quelle öffnen

[Korrigieren und übernehmen]

[Zurückstellen] [Ablehnen]

Weitere Details

‹ Vorheriger                      Nächster ›
```

### Regeln

- genau ein fokussierter Vorgang
- Hauptaktion entspricht dem konkreten Fall
- maximal zwei sichtbare Nebenaktionen
- technische Details eingeklappt
- Fortschritt sichtbar
- nach Aktion automatisch nächster Fall
- Filterzustand bleibt erhalten
- kein vertikales Kartenarchiv

## 6. Screen: Bearbeiten Desktop

### Aufbau

```text
Filterleiste

Kompakte Queue                 Geöffneter Vorgang
-----------------              ------------------
Titel · Typ · Kurzstatus       Titel
Titel · Typ · Kurzstatus       Anlass / Problem
Titel · Typ · Kurzstatus       Quelle / Vorher-Nachher
                               Bearbeitbare Felder
                               Hauptaktion
                               Nebenaktionen
```

### Queue-Regeln

- kompakte Zeilen statt vollständiger Karten
- ausgewählter Vorgang markiert
- Titel, Typ, Status, Kurzgrund
- kein langer Beschreibungstext

### Detail-Regeln

- vollständiger Kontext nur rechts
- primäre Aktion visuell dominant
- destruktive Aktion räumlich getrennt
- Auswahl und Scrollposition bleiben nach Aktionen erhalten

## 7. Fallspezifische Hauptaktionen

| Fall | Hauptaktion |
|---|---|
| neuer Eventkandidat | Übernehmen |
| Anbieter vor Zahlung | Zahlung freigeben |
| bezahlte Anbieter-Einreichung | Veröffentlichen |
| Beschreibungsfehler | Korrigieren und übernehmen |
| Quellenfehler | Quelle korrigieren/übernehmen |
| Faktenprüfung ohne Fehlerbeleg | Als korrekt bestätigen |
| Eventänderung | Änderung übernehmen |
| Absage | Absage übernehmen |
| Bildentscheidung | Bildzuordnung übernehmen |

Allgemeines `Freigeben` ist nur zulässig, wenn der Vorgang tatsächlich entscheidungsreif ist und keine Korrektur mehr verlangt.

## 8. Screen: Aufgaben mobil

### Übersicht

Tabs oder Filter:

- Jetzt
- Als Nächstes
- Wartet
- Blockiert

Kompakte Aufgabenzeile:

- Titel
- nächster Schritt
- Fälligkeit oder Wartegrund
- Objektbezug

### Detail

- Beschreibung
- Herkunft
- Status
- Fälligkeit
- Warte-/Blockadegrund
- Verlauf
- Aktionen

### Aktionen

- Starten
- Erledigen
- Warten
- Blockieren
- Zurückstellen
- Bearbeiten

Ein Button `Status prüfen` ist nur zulässig, wenn er eine reale Quellprüfung ausführt und das Ergebnis sichtbar zurückmeldet.

## 9. Screen: Aufgabe anlegen

Pflichtfelder:

- Titel
- nächster Schritt

Optional:

- Beschreibung
- Fälligkeit
- Priorität
- Objektbezug

Regeln:

- mobil in weniger als einer Minute erfassbar
- keine unnötigen Pflichtfelder
- nach Speichern klare Rückmeldung

## 10. Screen: Verwaltung – Liste

### Aufbau

- Suche prominent
- Segment Events/Aktivitäten
- Filter: publiziert, geplant, geändert, mit offenem Vorgang
- kompakte Ergebnisliste

Jede Zeile zeigt:

- Titel
- Typ
- Publikationsstatus
- Datum/Ort beziehungsweise Aktivitätsart
- offenen Handlungsbedarf, falls vorhanden

## 11. Screen: Verwaltung – Objektdetail

Abschnitte:

1. öffentliche Vorschau
2. Stammdaten
3. Quelle und letzte Aktualisierung
4. Qualitätsstatus
5. offene zentrale Vorgänge
6. relevanter Verlauf

Aktionen:

- Bearbeiten
- öffentliche Vorschau öffnen
- Änderung/Absage durchführen
- Aufgabe anlegen

Technische Rohdaten bleiben eingeklappt.

## 12. Screen: Ideen

### Liste

- Titel
- Kategorie
- kurzer Nutzen/Anlass
- Status gesammelt/geparkt

### Erfassung

Pflicht:

- Titel

Optional:

- Notiz
- Kategorie
- erwarteter Nutzen

Aktionen:

- Bearbeiten
- Parken
- Verwerfen
- In Aufgabe umwandeln

Keine Fälligkeitspflicht, keine roten Warnungen.

## 13. Screen: Systemstatus

Normalzustand:

```text
Automatisierungen laufen
Keine bekannte Störung mit Auswirkung.
Letzte relevante Aktualisierung: heute 14:20
```

Störung:

- betroffene Funktion
- fachliche Auswirkung
- genau eine nächste Aktion
- technische Details nachrangig

Keine Rohlogs in der Standardansicht.

## 14. Screen: Archiv

- Suche
- Filter erledigt/abgelehnt/archiviert
- Zeitraum
- Vorgangsdetail und Verlauf
- keine aktiven Badges

## 15. Leer-, Lade- und Fehlerzustände

### Leer

- konkret benennen, was leer ist
- keine falsche Erfolgsmeldung
- optional passende Erfassungsaktion

### Laden

- Inhalt bleibt strukturell stabil
- keine springende Navigation

### Fehler

- verständliche Aussage
- betroffener Bereich
- erneuter Versuch
- technische Details nur bei Bedarf

## 16. Sprachvertrag

Sichtbare Statusbezeichnungen:

- Neu
- Entscheidung erforderlich
- Offen
- In Arbeit
- Wartet
- Blockiert
- Zurückgestellt
- Erledigt
- Abgelehnt

Nicht sichtbar in Standardansichten:

- `decision_required`
- `waiting`
- `source_system`
- Auditcodes
- Guard-/Runtime-Begriffe
