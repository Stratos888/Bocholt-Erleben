Bocholt erleben – verbindliches Arbeits- & Architekturregelwerk (v3, KI-optimiert)
🎯 Ziel

Mobile-first Event-PWA mit echtem App-Gefühl.
Stabil, wartbar, keine Layout- oder Cache-Bugs, schnelle iterative Entwicklung.

Priorität:

Stabilität

Konsistenz

UX

Features

1. Arbeitsmodus (verbindlich)
1.1 Konsolidierungs-Modus (hart)

Letzter geposteter Dateistand = Wahrheit

Keine Änderungen ohne aktuellen Code

Keine Annahmen

Keine Teil-Snippets
1.1.1 Single Source of Truth (Handshake)

Wenn mehrere Uploads/Versionen existieren:
- Es wird explizit ein kanonischer Dateistand benannt („Diese Datei ist Wahrheit“).
- Es wird ausschließlich auf diesem Stand gearbeitet.
- Jede neue Datei-Version ersetzt die alte vollständig als neue Wahrheit.

1.2 Diff-Regel (Pflicht)

Nur:

„Ersetze Block von … bis …“

„Ersetze exakt diese Zeile“

Nie:

komplette Dateien neu generieren

vage Anweisungen

„füge irgendwo ein“
1.2.1 Block-Existenz-Garantie (Pflicht)

Bei „Ersetze Block von … bis …“ gilt zusätzlich:
- Der Assistant MUSS sicherstellen, dass BEGIN/END exakt im aktuellen Dateistand vorkommen.
- Wenn BEGIN/END nicht gefunden werden: STOP → aktuellen Datei-Stand anfordern.
- Kein Ersetzen „auf Verdacht“, keine erfundenen Marker/Blocknamen.

1.3 Datei-Isolation

Pro Schritt:
→ genau eine Datei

Ausnahme nur bei zwingender Abhängigkeit.

1.4 Root-Cause-Pflicht

Vor jedem Fix:

Ursache identifizieren

minimalen Patch liefern

Nie:

raten

Workarounds

„100% sicher“ ohne Proof
1.4.1 Proof-Gate (Pflicht bei UI/Layout/Positioning)

Bevor irgendein Patch geliefert wird, MUSS zuerst ein „Proof Bundle“ vorliegen (DevTools oder reproduzierbare Messwerte):

A) DOM-Proof:
- Exists: #event-detail-panel, .detail-panel-content, .detail-panel-body, #detail-actionbar-slot

B) Geometry-Proof:
- getBoundingClientRect() für Sheet + Actionbar-Slot + window.innerHeight

C) Computed-Style-Proof:
- position / bottom / transform / overflow / z-index (für Sheet, Body, Slot)

D) Active-Stylesheet-Proof:
- Welche CSS-Datei ist wirklich geladen (document.styleSheets href)

E) Visual-Proof:
- 1 Screenshot „Problemzustand“ (gleicher Screen, gleiche Zoom-Stufe)

Ohne Proof Bundle: KEIN Patch. Erst Proof anfordern.

1.4.2 Fix-Ansage nur mit Abnahme-Proof

Ein Fix darf erst als „gelöst“ gelten, wenn nach dem Patch die gleiche Proof-Messung wiederholt wurde
und die erwarteten Werte erfüllt sind (z. B. SlotRect.bottom innerhalb Viewport; dpBaseY=0px; etc.).

1.5 CSS-first

UI/Spacing/Layout:
→ nur CSS

JS nur für:

State

Events

Datenlogik
1.6 Definition of Done (UI/Layout Tickets)

Ein UI/Layout Ticket ist erst DONE, wenn:
- Primärsymptom behoben (z. B. Actionbar sichtbar & korrekt positioniert)
- Keine Regression der Basis-UI (Typography/Spacing/Icons nicht kaputt)
- Proof Bundle erneut gemessen und erfüllt (1.4.2)
- 1 Screenshot „nachher“ mit gleicher Perspektive

Wenn ein Fix das Symptom löst, aber Design regressiert → Ticket gilt als NICHT bestanden.

2. Architektur (hart, nicht verhandelbar)
2.1 Overlay-Root

Alle Overlays:

Detailpanel

Modals

Bottom Sheets

→ direkt unter <body>

Nie innerhalb von:

transform

sticky

overflow

backdrop-filter
2.1.1 Keine Koordinatensystem-Mischung (Pflicht)

Bei Bottom-Sheets/Transforms gilt:
- Positionierungs-Strategie pro Element fixieren (absolute vs fixed vs sticky).
- Keine Wechsel „on the fly“, ohne vorher/nachher Proof Bundle.
- Wenn transform im Spiel ist: erst beweisen, welches Element der Positionierungs-Anchor ist.

2.2 Kein vh

Nie:

100vh

40vh

Immer:

dvh + Fallback

Grund: Mobile Viewport Bug

2.3 Safe-Area Pflicht

Unten:

padding-bottom = safe-area + tabbar + spacing


Nie:

Positions-Hacks

JS Scroll Tricks

2.4 Scroll nur im Content

Sheet = fixed
Content = overflow:auto

3. Designsystem (Top-App Standard)
3.1 Action Bars

nur Icons

44×44 Touch

SVG line icons

aria-label

keine Emojis

keine Markenlogos

3.2 Chips statt Meta-Text

Meta immer als Chips:

Ort

Datum

Zeit

Regeln:

kurz

niemals abgeschnitten

Zeit volle Breite

3.3 Location Logik (wichtig)

Homepage vorhanden → klickbar
Homepage fehlt → nur Info

Nie doppelte Navigation (Maps/Website)

3.4 Listen statt Buttons

Keine Web-Buttons
Nur:

Pills

List Items

ruhige Flächen

3.5 Text robust

line-height ≥ 1.6

overflow-wrap:anywhere

lange Inhalte müssen überleben

4. UX Prinzipien
4.1 Keine Redundanz

1 Aktion = 1 Weg

4.2 Progressive Disclosure

Nur das Wesentliche anzeigen

4.3 Mobile first

Desktop nur größere Variante, kein eigenes Layout

5. Qualität & Deployment
5.1 Fail Fast

Deploy schlägt fehl bei:

404 Assets

Cache-Mismatch

kaputten Links

5.2 Keine Layout-JS

Layout niemals mit JS berechnen

6. KI-Arbeitsauftrag (wichtig für mich)

Ich soll:

minimal ändern

niemals bestehendes Verhalten brechen

konsistent bleiben

nur notwendige Dateien anfassen

Root Cause liefern

mobile Probleme zuerst lösen

UI wie native App gestalten

Ich soll nicht:

neu erfinden

große Refactors ohne Grund

unnötige Features einbauen

visuelle Stilwechsel vornehmen
