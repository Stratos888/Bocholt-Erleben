# PROJECT.md – Bocholt erleben
Single Source of Truth (Architektur + Regeln + Arbeitsweise)

Ziel:
Eine mobile-first Event- und Freizeit-Ideenplattform für Bocholt.
Fokus: schnell inspirieren („Was kann ich heute machen?“), nicht Datenbank oder Spezial-App.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
GRUNDPRINZIP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Die Seite ist KEINE:
- Tourenplaner-App (Komoot etc.)
- Sporttracking-App
- Spezialistenplattform

Die Seite IST:
→ ein schneller Ideenfinder für Freizeit & Ausflüge

Designziel:
- ruhig
- hochwertig
- wenig visuelle Unruhe
- scannbar in Sekunden
- möglichst wenig Text auf Cards
- Details erst im Panel

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VERBINDLICHE ARBEITSREGELN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Diese Regeln gelten IMMER.

1. Konsolidierungs-Modus
   Der zuletzt gepostete Stand einer Datei ist vollständig und korrekt.
   Niemals raten oder Teile rekonstruieren.

2. Diff statt Snippet
   Nur Replace-Blöcke oder klare Änderungen.
   Keine kompletten Dateien neu erfinden.

3. Datei-fokussiert
   Immer nur 1 Datei pro Schritt ändern.

4. BEGIN/END Marker
   Jeder Patch enthält klar markierte Blöcke.

5. Niemals spekulieren
   Erst Root Cause beweisen, dann fixen.

6. Datenpipeline vor UI debuggen

7. events.json / offers.json = Runtime Truth
   Keine TSV/CSV im Client rekonstruieren.

8. UI-Polish nur CSS

9. Overlay-Root unter <body>
   Alle Modals/Sheets/Details außerhalb sticky/transform Container.

10. Fail-Fast Deploy
   Build darf bei kaputten Assets hart fehlschlagen.

11. 100%-Regel für Fixes
   Änderungen vollständig und korrekt liefern.

12. ❗ NEU – Systemstabilität (verbindlich)
   Neue Features oder Änderungen dürfen:
   - nichts anderes kaputt machen
   - keine Seiteneffekte erzeugen
   - bestehende Patterns wiederverwenden
   - keine Sonderlogik einführen
   - immer ganzheitlich das System berücksichtigen

   → Evolution statt Workarounds

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ARCHITEKTUR – EVENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Datenfluss:
data/events.json
→ js/main.js (load)
→ js/events.js (render)
→ js/details.js (detail panel)

Regeln:
- Cards minimal
- keine Beschreibung auf Card
- Details im Panel
- URL im Panel
- Kategorie-Icon oben rechts
- dynamische Zeitsektionen (Heute/Demnächst/Später)
- Multi-Day Events gelten während Laufzeit als "Heute"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ARCHITEKTUR – ANGEBOTE (NEU)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Zweck:
Nicht-kommerzielle Freizeit-Ideen (Seen, Natur, Spielplätze, etc.)

KEINE:
- kommerziellen Anbieter (z.B. Erlebnisbäder)
- bezahlpflichtige Locations

Datenfluss (identisch zu Events):
data/offers.json
→ js/offers-main.js (load + filter)
→ js/offers.js (render)
→ js/offers-details.js (detail panel)

WICHTIG:
Keine Sonderlösung gegenüber Events.
Gleiche Patterns wiederverwenden.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OFFERS – DATENMODELL (final)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Root:
{
  meta: {},
  offers: []
}

Offer:
{
  id: string,
  title: string,
  kategorie: string,   // Pflicht – Hauptkategorie
  tags: string[],      // optional – Aktivitäten/Intents
  location: string,
  description: string, // Pflicht – nur im Detailpanel
  hint: string,
  url: string          // Pflicht – offizielle Info
}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OFFERS – UI REGELN (final)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Card zeigt NUR:
- Titel
- Hauptkategorie
- Location
- Kategorie-Icon

Card zeigt NICHT:
- description
- Links
- mehrere Tags

Card-Klick:
→ Detailpanel öffnen

Detailpanel zeigt:
- vollständige Beschreibung
- Zur Location (url)
- Maps-Fallback

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KATEGORIEN & TAGS (festgelegt)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Hauptkategorien (ein Wort, ruhig):
- Baden
- Natur
- Familie
- Freizeit
- Kultur

Tags (nur Aktivitäten/Intents, keine Details):
- Wandern
- Radfahren
- Spazieren
- Baden
- Spielen
- Picknick
- Familie
- Hundegeeignet
- Entspannen

KEINE Tags:
- Uferweg
- Kurzweg
- Moor
- Vogelbeobachtung
- technische Eigenschaften

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILTERLOGIK (OFFERS)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

2 Filter (State of the Art):

1. Kategorie (Hauptkategorie)
2. Aktivität (Tags)

Logik:
AND-Verknüpfung

UX:
Tag-Filter ist facettiert (zeigt nur passende Tags der gewählten Kategorie)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CARD DESIGN REGELN (global)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- immer gleiche Höhe
- Titel 1 Zeile + Ellipsis
- fester Abstand zum Kategorie-Icon
- keine Label-Flut
- minimalistische Meta-Zeile

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ARBEITSWEISE FÜR NEUE FEATURES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Bevor ein Feature gebaut wird:

1. Passt es zur Produktvision (Ideenfinder)?
2. Ist es minimal?
3. Wiederverwendet es bestehende Patterns?
4. Bricht es nichts Bestehendes?
5. Kann es ohne Sonderlogik integriert werden?

Wenn NEIN → nicht bauen oder vereinfachen.
