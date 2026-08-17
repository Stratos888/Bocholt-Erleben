# Phase 5 – Active Execution Lock

Stand: 2026-08-17
Workpack: #259
Branch: `brand/phase5-qualified-search-2026-08-01`
Status: **PRODUCT-OWNER-ENDGATE – VISUELLE IDENTITÄT EINFÜHRUNGSFÄHIG**

## 1. Unveränderliches Ziel

Eine eigenständige Premium-Markenidentität für die private kuratierte Discovery-/Freizeitplattform **Bocholt erleben** entwickeln, ohne externe Kreativbeauftragung und ohne schwache AI-Ergebnisse durch Prozess, Präsentation oder Scores künstlich aufzuwerten.

Öffentliche Marke, `staging`, `main` und Live bleiben bis zur ausdrücklichen Integrationsfreigabe unverändert.

## 2. Abgeschlossene Schritte

- Schritt 2: Premium-Referenzanalyse – abgeschlossen.
- Schritt 3: Creative Direction – `Klar ausgewählt`.
- Schritt 4: Formlogik – `Präzise Humanität`.
- Schritt 5: Primäridentität konstruiert und einmal gezielt korrigiert.
- Schritt 6: neutraler Vergleich – bestanden in Lauf 2.
- Schritt 7: Marken-Minisystem und reale Produktwirkung – bestanden.
- Schritt 8: responsive Kleinform und formale Design-/Technikprüfung – bestanden.
- Schritt 9: Namens-/Rechtepreflight, visuelle Ähnlichkeitsprüfung und Endgate – abgeschlossen mit dokumentierten Restunsicherheiten.

## 3. Kanonische Identität

### Primärwortmarke

- `docs/brand/phase5/step5/construction-a-mono.svg`
- Farbe auf hell: `#0D3014`
- inverse Fassung: Weiß auf ausreichend dunkler Markenfläche

### Responsive Kleinform

- `docs/brand/phase5/step8/app-mark-mono.svg`
- charakteristisches `e` direkt aus derselben Wortmarkengeometrie

### App-/Maskable-Source

- `docs/brand/phase5/step8/app-icon-source.svg`
- Hintergrund `#F3F2E6`
- Glyph `#0D3014`

Die App-Kachel wurde nach externer Step-9-Ähnlichkeitssuche von der dicht belegten Architektur `weißes e auf grün` weggeführt, ohne die kanonische `e`-Geometrie oder die bestehende Markenpalette neu zu erfinden.

## 4. Namens-/Rechtestand

Der Name bleibt **`Bocholt erleben`**.

Belegt:

- der frühere behauptete harte Stadtmarketing-Logo-Blocker war falsch und wurde zurückgezogen;
- Stadtmarketing nutzt `bocholterleben` historisch und aktuell als Social-/Kommunikationsbezeichnung;
- der Product Owner führte am 2026-08-17 eine DPMAregister-Basisrecherche mit nationalen, Unions- und internationalen Markenbeständen und dem Suchbegriff `Bocholt erleben` durch;
- Ergebnis laut Screenshot: `Die Datenbankabfrage lieferte keine Treffer.`

Nicht behauptet:

- anwaltliche Freedom-to-use-Freigabe;
- vollständiger Ausschluss nicht eingetragener älterer Kennzeichenrechte;
- abschließende rechtliche Verwechslungsbewertung.

Diese Restunsicherheit betrifft den bereits bestehenden Namen und wird nicht fälschlich als neuer Effekt des visuellen Rebrands behandelt.

Nachweise:

- `docs/brand/phase5/STEP9_NAME_RIGHTS_VERIFICATION.md`
- `docs/brand/phase5/STEP9_FINAL_ENDGATE.md`

## 5. Fachliches Urteil

**Die neue visuelle Identität ist zur kontrollierten Integration auf `staging` freigabefähig.**

Es besteht kein offener harter Design- oder Technik-Knock-out.

## 6. Aktueller Gate

**PRODUCT-OWNER-INTEGRATIONSFREIGABE**

Erforderliche Entscheidung:

- `freigeben` → separates Integrationsworkpack auf aktueller `staging`-Basis;
- `nicht freigeben` → keine Produktänderung, Designbranch bleibt dokumentierter Endstand.

## 7. Bei Freigabe zulässiger nächster Schritt

Nicht in diesem Workpack direkt integrieren.

Stattdessen:

1. neues Integrationsworkpack von aktuellem `staging`;
2. finale SVG-/Rasterassets erzeugen;
3. Header-Wortmarke ersetzen;
4. PWA/Favicon/Apple-Touch/Maskable-Assets ersetzen;
5. Manifest- und Cacheversionen kontrolliert aktualisieren;
6. mobile und Desktop-Staging-Screenshots prüfen;
7. Safe Area, Kontrast, Accessibility und Installation prüfen;
8. erst nach Staging-Sichtprüfung separater Main-/Live-Entscheid.

## 8. Verboten bis zur Product-Owner-Freigabe

- kein Merge nach `staging` als Markenintegration;
- keine Änderung an `main` oder Live;
- kein Deploy;
- kein weiterer Namenswechsel;
- keine neue Logoexploration;
- keine Behauptung rechtlicher Vollfreigabe.

Kein stiller Prozesswechsel.
