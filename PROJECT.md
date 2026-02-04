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
CRITICAL ARCHITECTURE SUMMARY (UNVERÄNDERLICH)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Diese Punkte sind feste Systemgesetze. Sie dürfen NICHT umgangen oder „vereinfacht“ werden:

1. Google Sheet ist die EINZIGE redaktionelle Quelle für Events.
   → Niemals manuell events.tsv oder events.json bearbeiten.

2. events.tsv ist nur ein temporäres Build-Artefakt (CI-intern).
   → Kein Mensch pflegt diese Datei.

3. events.json ist die einzige Runtime-Datenquelle im Frontend.
   → UI liest ausschließlich JSON, niemals TSV/CSV.

4. Das Datenschema wird vom Builder (scripts/build-events-from-tsv.py) definiert.
   → Wenn Feldnamen geändert werden:
      IMMER zuerst Builder → dann Sheet → dann Frontend.
      Niemals umgekehrt.

5. Deploy ist Fail-Fast.
   → Ungültige Daten, Duplikate oder fehlende Pflichtfelder müssen den Build stoppen,
      niemals „still durchrutschen“.

Diese Architektur sorgt für:
- einfache Redaktion (Sheet)
- stabile PWA/Cache (statische JSON)
- deterministische Builds
- keine versteckten Seiteneffekte

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VERBINDLICHE ARBEITSREGELN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Diese Regeln gelten IMMER.

1. Konsolidierungs-Modus
   Der zuletzt gepostete Stand einer Datei ist vollständig und korrekt.
   Niemals raten oder Teile rekonstruieren.

2. Diff statt Snippet (präzisiert)
Nur Replace-Blöcke oder klar benannte, lokale Änderungen.
Keine kompletten Dateien neu erfinden (außer ausdrücklich „neu anlegen“).
Replace immer nur über verifizierbare Anker (siehe Regel 13).

2a. Minimal-Diff-Regel (NEU, verbindlich)
Änderungen dürfen nur die minimal notwendigen Zeilen betreffen.
Keine Block-Replacements für UI-/Schönheitsänderungen.
Wenn eine Änderung mehr als ca. 10–20 Zeilen oder mehrere Bereiche betrifft, ist es kein UI-Polish mehr → als eigener Task behandeln (mit Proof/Scope).
Keine „Nebenbei-Fixes“ im gleichen Schritt.

3. Datei-fokussiert
   Immer nur 1 Datei pro Schritt ändern.
   Ziel: 1 Commit pro Schritt.

4. BEGIN/END Marker
   Jeder Patch enthält klar markierte Blöcke.

5. Niemals spekulieren
   Erst Root Cause beweisen, dann fixen.

6. Datenpipeline vor UI debuggen

7. events.json / offers.json = Runtime Truth
   Keine TSV/CSV im Client rekonstruieren.

8. UI-Polish nur CSS
   (JS nur bei Funktionsbedarf, niemals für “nur schöner”.)

9. Overlay-Root unter <body>
   Alle Modals/Sheets/Details außerhalb sticky/transform Container.

10. Fail-Fast Deploy
   Build darf bei kaputten Assets hart fehlschlagen.
   Zusätzlich: wenn Datenquelle (Events) nicht erreichbar/ungültig ist → Deploy bricht ab.

11. 100%-Regel für Fixes (Klarstellung)
   Änderungen vollständig und korrekt liefern (keine halben Patches).
   Klarstellung: „vollständig“ bedeutet: alle für das konkret angegebene Problem notwendigen Änderungen im definierten    Scope (1 Datei / Minimal-Diff). Keine zusätzlichen Optimierungen.

12. Systemstabilität (Klarstellung, ohne Abschwächung)
   Neue Features oder Änderungen dürfen keine unbeabsichtigten Seiteneffekte erzeugen.
   Klarstellung: Bei CSS gilt „Seiteneffekt“ = Änderung außerhalb der betroffenen Komponente/Selektoren, außer sie ist    explizit Teil des Fix-Scopes und begründet/proved.

13. Replace-Anker-Regel (verbindlich)
   Replace-Blöcke dürfen nur über Textbereiche/Selector/Marker erfolgen,
   die nachweislich exakt so im aktuellen Stand existieren.
   Wenn der Block nicht 1:1 verifizierbar ist → erst Proof liefern, dann patchen.

14. Proof-First Patch Protocol (P3) für Layout/CSS (verbindlich)
   Für Layout-/CSS-Fixes (Overflow, Grid, Container, Cards, Tabbar) gilt:
   A) Pre-Proof:
      - scrollWidth - clientWidth
      - Top-Offender (Element + Breite)
      - Computed Styles des Offenders (width/min/max/boxSizing/whiteSpace/transform/grid)
   B) Token-Check:
      - jede genutzte var(--...) ist in :root definiert ODER bewusst ergänzt
   C) Post-Proof:
      - scrollWidth - clientWidth == 0
      - visuelle Kontrolle: Cards + Tabbar sichtbar, keine Miniatur-Zoom-Effekte

15. CSS Token Discipline (verbindlich)
   - Keine neuen Token-Namen “einfach verwenden”.
   - Card-Background/Shadow müssen auf bestehende Tokens mappen
     (z.B. --surface, --shadow-sm, --shadow-md).
   - Neue Tokens nur als eigener, bewusster Schritt in :root.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ARCHITEKTUR – EVENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Runtime-Datenfluss:
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
- Multi-Day Events gelten während Laufzeit als "Heute" (UI/Filter/Sortierung müssen range-aware sein)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EVENTS – DATENQUELLE & PUBLISHING (final)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Single Source of Truth (Redaktion):
Google Sheet (öffentlich per Link, read-only genügt)

CI/Build:
Google Sheet (TSV Export)
→ (CI lädt) data/events.tsv (nur Build-Artefakt; nicht redaktionell pflegen)
→ scripts/build-events-from-tsv.py
→ data/events.json
→ Deploy

Wichtige Konsequenzen:
- events.tsv ist kein Redaktionsmedium mehr.
- Der Deploy ist Fail-Fast, wenn das Sheet nicht erreichbar ist oder Schema/Validierung failt.
- Das Frontend bleibt statisch und lädt ausschließlich events.json (PWA/Cache bleibt stabil).

Copy/Paste Workflow:
- Neue Events werden in das Sheet eingetragen.
- Bulk-Import ist Tab-getrennt (TSV): mehrere Zeilen können direkt eingefügt werden.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EVENTS – SCHEMA (muss konsistent bleiben)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Das Schema ist durch scripts/build-events-from-tsv.py vorgegeben.

Minimal required Header (Tab-getrennt):
id	title	date	city	location	kategorie

Erlaubte/erwartete zusätzliche Felder (projektabhängig):
endDate	time	url	description	image (falls im Projekt unterstützt)

WICHTIG:
- Spaltennamen müssen exakt matchen (Groß/Klein, Umlaute).
- Wenn Schema geändert wird: Proof (alle Verwendungen in Code + CI) und dann konsolidierter Patch.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KATEGORIEN (EVENTS) – FINAL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Prinzip:
- Jedes Event MUSS mindestens 1 gültige Kategorie haben.
- “Sonstiges” soll NICHT verwendet werden.
- Validierung: Google Sheet Dropdown + Builder Fail-Fast.

Kategorien (aktuell gültig im Projekt / Builder):
- Märkte & Feste
- Kultur & Kunst
- Musik & Bühne
- Kinder & Familie
- Sport & Bewegung
- Natur & Draußen
- Innenstadt & Leben

Regel:
- Google Sheet Datenvalidierung: Dropdown exakt mit obiger Liste
- Ungültige Werte: ABLEHNEN (nicht nur Warnung)
- Leere Kategorie: nicht zulassen

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DUPLIKATE & DATENHYGIENE (EVENTS)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Ziel:
- Keine doppelten Veröffentlichungen
- Copy/Paste soll sicher sein

Regeln (Build/CI):
- Duplikate müssen deterministisch verhindert werden (Fail-Fast) – bevorzugt per (url + date).
- Alte Ein-Tages-Events (date < heute und kein endDate) sollen nicht mehr veröffentlicht werden
  (entweder Skip oder Fail-Fast – Entscheidung als eigener Schritt dokumentieren).

Hinweis:
- Automatisches „Löschen im Google Sheet“ erfolgt nicht durch CI (keine Schreibrechte).
- “Nicht veröffentlichen” = nicht in events.json output.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ARCHITEKTUR – ANGEBOTE
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
KATEGORIEN & TAGS (OFFERS) (festgelegt)
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

2 Filter:
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

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
BACKLOG – GOOGLE SHEETS EVENTS (verbindlich dokumentiert)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Ziel:
Events effizient einpflegen (Copy/Paste), keine Duplikate, keine falschen Kategorien,
saubere Automatisierung, und später TSV aus dem Prozess entfernen.

A) Google Sheet Setup (Datenqualität)
- [x] Sheet existiert, öffentlich “Jeder mit Link → Betrachter”
- [ ] Header-Zeile exakt nach Builder-Schema final prüfen und fixieren
- [ ] Datenvalidierung:
      - kategorie Dropdown nur mit finalen Kategorien (ohne “Sonstiges”)
      - ungültige Werte ablehnen
      - Pflichtfelder: id, title, date, city, location, kategorie
- [ ] ID-Workflow:
      - Redaktionsregel definieren (wie IDs erstellt werden)
      - optional: Sheet-Formel/Helper zur ID-Generierung dokumentieren
- [ ] Copy/Paste Standard:
      - ChatGPT liefert TSV-Zeilen (tab-getrennt) passend zum Sheet-Header

B) CI/Deploy (Automatisierung)
- [x] Deploy lädt Events aus Google Sheet (TSV Export) und baut events.json Fail-Fast
- [ ] Optional: Deploy-Zeitplan (schedule) definieren (z.B. täglich) + workflow_dispatch beibehalten

C) Dedupe & Cleanup (Build-Logik)
- [ ] Duplikat-Regel in Builder final festlegen und dokumentieren:
      - Fail-Fast: (url + date) eindeutig (empfohlen)
- [ ] Abgelaufene Ein-Tages-Events:
      - Regel final: Skip oder Fail-Fast
      - Dokumentation: “nicht veröffentlichen” bedeutet “nicht in events.json”
- [ ] Range-Events (endDate):
      - Sicherstellen: UI/Filter/Sortierung sind vollständig range-aware

D) Prozess: tägliche Event-Recherche
- [x] Täglicher ChatGPT-Check ist eingerichtet (notify only if new events)
- [ ] Quellenliste final definieren (Stadt, Locations, Kulturkalender)
- [ ] Standardausgabe definieren: kurze Liste + TSV-Block + Duplikatwarnungen

E) TSV-Entfernung (späterer finaler Schritt)
- [ ] Wenn Pipeline stabil ist: events.tsv nicht mehr als Repo-Quelle behandeln
- [ ] Abschließend: TSV als Prozess-/Repo-Abhängigkeit entfernen (nur wenn alle Schritte oben stabil sind)

