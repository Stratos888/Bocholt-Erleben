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

1.2 Diff-Regel (Pflicht)

Nur:

„Ersetze Block von … bis …“

„Ersetze exakt diese Zeile“

Nie:

komplette Dateien neu generieren

vage Anweisungen

„füge irgendwo ein“

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

1.5 CSS-first

UI/Spacing/Layout:
→ nur CSS

JS nur für:

State

Events

Datenlogik

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
