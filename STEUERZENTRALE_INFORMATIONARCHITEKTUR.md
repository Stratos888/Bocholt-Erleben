# Steuerzentrale – verbindliche Informationsarchitektur

Stand: 2026-07-10
Status: verbindlicher Navigations- und Bereichsvertrag

## 1. Leitentscheidung

Die Steuerzentrale ist eine Betreiberanwendung für Aufmerksamkeit, Arbeit und fachliche Verwaltung. Die Hauptnavigation enthält nur regelmäßig benötigte, vollständig nutzbare Bereiche.

## 2. Hauptnavigation

1. Übersicht
2. Bearbeiten
3. Arbeit
4. Verwaltung
5. Menü

Die Navigation bleibt auf Mobilgerät, Tablet und Desktop dauerhaft erreichbar.

## 3. Übersicht

Zweck: in wenigen Sekunden erkennen, ob und wo Handlung nötig ist.

Blöcke:

- Jetzt erforderlich
- Zu bearbeiten
- Arbeit
- Wartet/Blockiert
- Zur Kenntnis
- Systemzustand

Regeln:

- keine vollständigen Vorgangskarten,
- keine doppelte Arbeitsliste,
- maximal zwei Beispiele pro verdichtetem Block,
- CTA öffnet den passenden Bereich mit gesetztem Filter,
- Normalzustand ruhig und fachlich formuliert.

## 4. Bearbeiten

Zweck: alle ungeklärten und entscheidungsbedürftigen Quellfälle sicher bearbeiten.

Filter:

- Alle
- Neue Inhalte
- Änderungen
- Qualität
- Anbieter
- Freigaben
- Sonstige

Mobil:

- ein fokussierter Vorgang,
- Fortschritt,
- Problem/Anlass,
- erforderlicher Schritt,
- relevante Daten und Quelle,
- genau eine dominante Hauptaktion,
- maximal zwei sichtbare Nebenaktionen,
- Vorher/Weiter.

Desktop:

- kompakte Queue links,
- Detail und Aktionen rechts,
- Auswahl und Scrollzustand bleiben erhalten.

## 5. Arbeit

Zweck: den vollständigen Lebenszyklus geplanter menschlicher Arbeit abbilden.

Unteransichten:

- Aktiv/Jetzt
- Als Nächstes
- Wartet
- Blockiert
- Backlog
- Ideen
- Archiv

### Aufgabe

Entschiedene konkrete Arbeit mit nächstem Schritt, optionaler Fälligkeit und Objektbezug.

### Backlog

Bewusst vorgemerkte Arbeit ohne aktive Einplanung. Quellen:

- Growth-/Acquisition-Backlog,
- kuratierte Repo-Workpacks,
- Produkt-, Content- und Technikverbesserungen,
- aus Ideen übernommene Punkte.

### Idee

Ungeprüfter Gedanke ohne Fälligkeit. Kann verworfen, geparkt, als Backlog übernommen oder direkt gestartet werden.

Übergänge ändern den führenden Zustand und erzeugen keine Doppelkarte.

## 6. Verwaltung

Zweck: Events und Aktivitäten intern suchen, prüfen und tatsächlich verändern.

### Objektliste

- stabiles Suchfeld ohne Fokusverlust,
- Filter nach Typ, Status und Handlungsbedarf,
- Publikationsstatus,
- Quelle/letzte Aktualisierung,
- offene Vorgänge,
- kompakte Ergebnisse.

### Objektdetail

- öffentliche Vorschau,
- bearbeitbare fachliche Felder,
- Quelle,
- Qualitätsstatus,
- offene Vorgänge,
- Verlauf,
- Änderung/Absage,
- Speichern mit führendem Writeback und Veröffentlichungswirkung.

Das Suchfeld bleibt beim Tippen bestehen; nur die Ergebnisliste wird aktualisiert.

## 7. Menü

Enthält nur selten benötigte Funktionen:

- Anbieterbereich,
- Systemstatus,
- Einstellungen,
- Abmelden,
- technische Diagnose nachrangig.

Nicht im Menü:

- Ideen und Backlog als versteckte Nebenfunktion,
- technische Rohwerte in der Standardansicht,
- leere Funktionsversprechen,
- reguläre Altansicht.

## 8. Systemstatus

Standardansicht:

- Synchronisation erfolgreich/gestört,
- betroffene fachliche Funktion,
- tatsächliche Auswirkung,
- genau eine nächste Aktion bei Handlungsbedarf.

Technische Details können `seen`, `upserted`, Fehlerklasse und Logs enthalten, sind aber eingeklappt.

## 9. Badges

- Bearbeiten: aktive entscheidungsbedürftige Fälle.
- Arbeit: aktive Aufgaben plus tatsächlich fällige/blockierte Arbeit.
- Keine Badges für Ideen, normalen Backlog, Informationen oder erfolgreiche Läufe.

## 10. Rolle der Altansicht

`/inbox/` ist bis zur vollständigen Migration ausschließlich Fallback und Diagnose. Sie ist kein Bestandteil der finalen Hauptnavigation.
