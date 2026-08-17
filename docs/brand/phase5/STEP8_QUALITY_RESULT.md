# Phase 5 – Schritt 8: Responsive Kleinform und formale Qualitätsprüfung

Stand: 2026-08-17
Workpack: #259
Status: **BESTANDEN – VORBEHALT SCHRITT 9 ÄHNLICHKEITSRECHERCHE**

## 1. Untersuchte Kleinformen

Es wurden intern genau drei zulässige Ableitungen aus der bestehenden Primäridentität geprüft.

### A – einzelnes `b`

Befund: **beendet**.

Das `b` ist trotz eigener Rund-/Terminaldetails als einzelner Markenbaustein zu nah an einem üblichen Initial-App-Icon. Die Verwandtschaft zur Wortmarke ist vorhanden, die eigenständige Wiedererkennung aber zu schwach.

### B – horizontal `be`

Befund: **beendet**.

Die Kombination ist semantisch nachvollziehbar, wirkt aber als Kürzel austauschbar und wird bei sehr kleinen Größen unnötig dicht. Zudem würde sie zu nah an die frühere Pflichtidee eines `b/e`-Monogramms heranrücken.

### C – charakteristisches `e`

Befund: **weitergeführt**.

Das `e` ist der stärkste wiederkehrende charakteristische Buchstabe der Primäridentität:

- dreimal in `bocholt erleben` vorhanden;
- identische Soft-Square-/Aperturlogik wie die vollständige Wortmarke;
- leicht ansteigender, weich gezeichneter Querstrich;
- sofort als Buchstabe lesbar;
- keine zusätzliche Symbolmetapher erforderlich;
- kein Initialmonogramm und keine neue Formfamilie.

Kanonische Quellen:

- `docs/brand/phase5/step8/app-mark-mono.svg`
- `docs/brand/phase5/step8/app-icon-source.svg`

## 2. Größenprüfung

Das weitergeführte `e` wurde gerendert bei:

- 64px;
- 48px;
- 32px;
- 24px;
- 16px.

Befund:

- 64/48/32px: klar und stabil;
- 24px: charakteristischer Querstrich und offene Form bleiben sichtbar;
- 16px: weiterhin als `e` lesbar, ohne Zerfall oder Verschmelzung.

Es ist keine separate 16px-Neuzeichnung erforderlich.

## 3. Masken- und Safe-Area-Prüfung

Geprüft:

- Kreis;
- Squircle;
- Full-bleed Maskable-Quelle.

Der Glyph wird bewusst innerhalb einer großzügigen zentralen Safe Area geführt. Der kanonische App-Source enthält **keine eingebrannte Plattformmaske**; Betriebssystem beziehungsweise PWA-Maske darf die Außenform bestimmen.

Befund: **bestanden**.

## 4. Farb- und Kontrastprüfung

App-Source:

- Hintergrund `#0D3014`;
- Glyph Weiß.

Der rechnerische WCAG-Kontrast Weiß zu `#0D3014` liegt bei ungefähr `14.5:1`.

Zusätzlich bleibt `app-mark-mono.svg` unabhängig vom Tile als einfarbige Kleinmarke nutzbar.

Befund: **bestanden**.

## 5. Technische Konstruktion und Provenienz

### Primäridentität

Ausgangsgeometrie:

- Inter Display Medium;
- The Inter Project Authors;
- Paketprovenienz: `OFL-1.1 and Apache-2.0`.

Die Primäridentität besteht als SVG-Pfadsystem ohne Runtime-Fontabhängigkeit.

### Kleinform

Das `e` wird **direkt aus demselben kanonischen Glyphenpfad** der Primäridentität abgeleitet. Es ist keine nachträgliche Fremdzeichnung.

Damit sind Wortmarke und Kleinform geometrisch verwandt und reproduzierbar.

Eine abschließende markenrechtliche beziehungsweise Freedom-to-use-Prüfung ist ausdrücklich nicht Teil dieses technischen Befunds und bleibt Schritt 9 vorbehalten.

## 6. Getrennter interner Bewertungspass A – Form und Mobile

Der Pass bewertet sichtbare Form, mobile Größen, Kleinform und technische Robustheit. Er ist ein getrennter interner Pass, keine externe unabhängige Begutachtung.

| Dimension | Wert |
|---|---:|
| Qualität und Eigenständigkeit der Wortmarke | 22/25 |
| Kohärenz und Wiedererkennung des Markensystems | 18/20 |
| Passung zu Produkt, Haltung und Zielgruppen | 19/20 |
| Wirkung ohne Herleitung und Kategorieabgrenzung | 13/15 |
| Mobile und responsive Markenfunktion | 10/10 |
| Konstruktion, Provenienz und technische Robustheit | 9/10 |
| **Gesamt** | **91/100** |

Urteil: **positiv**.

Bewusst kein höherer Wortmarkenwert: Die Identität ist eigenständig und systemisch, aber visuell zurückhaltend. Sie wird nicht künstlich in einen außergewöhnlichen 9er-/10er-Bereich hochgestuft.

## 7. Getrennter interner Bewertungspass B – Produkt und Kategorie

Der Pass fokussiert reale Produktwirkung, Kategorieabgrenzung und Systemkohärenz. Er ist ebenfalls kein externer unabhängiger Gutachter.

| Dimension | Wert |
|---|---:|
| Qualität und Eigenständigkeit der Wortmarke | 22/25 |
| Kohärenz und Wiedererkennung des Markensystems | 19/20 |
| Passung zu Produkt, Haltung und Zielgruppen | 18/20 |
| Wirkung ohne Herleitung und Kategorieabgrenzung | 13/15 |
| Mobile und responsive Markenfunktion | 9/10 |
| Konstruktion, Provenienz und technische Robustheit | 9/10 |
| **Gesamt** | **90/100** |

Urteil: **positiv**.

## 8. Red-Team-Pass

Auftrag: aktive Ablehnungssuche, nicht Verbesserung.

### Genericity

**Kein harter Knock-out der Primäridentität.**

Die Wortmarke basiert nicht mehr auf einer unveränderten Fontsetzung. Wiederkehrende Rund-, Apertur-, Querstrich-, Schulter- und Breitenregeln wirken über mehrere Buchstaben hinweg.

### Kleinmarke `e`

**Mittleres offenes Ähnlichkeitsrisiko, kein Design-Knock-out.**

Ein einzelnes `e` ist als Grundzeichen naturgemäß häufig. Die konkrete Zeichnung besitzt zwar eigene Merkmale, muss aber vor öffentlicher Einführung ausdrücklich einer Marken-/Bildähnlichkeitsrecherche unterzogen werden. Dieser Punkt wird nicht weggewertet und geht als Pflicht in Schritt 9.

### Artefakt-/Defektlesart

**Kein harter Knock-out.**

Der ansteigende Querstrich ist innerhalb aller `e`-Formen konsistent und bleibt als normaler Buchstabenbestandteil lesbar. Er wird nicht als herausgeschnittener oder beschädigter Bereich benötigt.

### Kategorie

**Kein harter Knock-out.**

Keine dominante Wirkung als Verwaltung, Tourismus, SaaS, Kulturinstitution, Gastro, Mode, Ticketing oder Navigation.

### Erklärungspflicht

**Kein harter Knock-out.**

Die Primärmarke ist direkt lesbar. Die Kleinform muss nicht die Produktkategorie erklären; ihre Beziehung entsteht aus dem identischen charakteristischen Glyphen der Wortmarke.

### Kleingröße

**Bestanden.**

16–64px stabil.

### Farbe

**Bestanden.**

Primäridentität und Kleinform funktionieren monochrom; Farbe ist keine Voraussetzung für die Formwirkung.

### Provenienz

**Technisch nachvollziehbar.**

Offen bleibt ausschließlich die spätere formale Marken-/Ähnlichkeitsprüfung.

## 9. Formales Gate

Pflichtwerte:

- mindestens 90/100;
- alle Dimensionsmindestwerte;
- zwei positive getrennte interne Bewertungspässe;
- kein ungelöster harter Red-Team-Befund;
- Größen-/Masken-/Technikprüfung bestanden.

Ergebnis:

- Pass A: `91/100`;
- Pass B: `90/100`;
- alle Dimensionsmindestwerte erreicht;
- kein harter Design-Knock-out;
- technische Größen-/Maskenprüfung bestanden;
- ein mittleres Ähnlichkeitsrisiko der Einzelglyphe ist für Schritt 9 ausdrücklich offen.

## 10. Fachliches Urteil

**SCHRITT 8 – DESIGN- UND TECHNIKSEITIG BESTANDEN.**

Das bedeutet noch nicht öffentliche Freigabe.

Vor dem finalen Product-Owner-Endgate sind zwingend erforderlich:

1. aktuelle Namens- und Markenrecherche;
2. visuelle Ähnlichkeitsrecherche insbesondere für das einzelne `e`;
3. abschließende Provenienz-/Lizenzdokumentation;
4. finale Integrationsvorbereitung ohne Änderung von `staging`, `main` oder Live;
5. Product-Owner-Endentscheidung.

## 11. Nächster zulässiger Schritt

**SCHRITT 9 VON 9 – FINALE MARKEN-/ÄHNLICHKEITSRECHERCHE, PRODUCT-OWNER-ENDGATE UND INTEGRATIONSVORBEREITUNG**
