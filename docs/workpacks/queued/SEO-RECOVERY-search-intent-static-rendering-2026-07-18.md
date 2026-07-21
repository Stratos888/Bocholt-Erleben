# Queued Workpack: SEO Recovery – Search Intent und statische Renderingbasis

Stand: 2026-07-18  
Status: eingeplant, nicht aktiv  
Risikoklasse: R2

## Grundlage

- Forensik: `docs/forensics/seo-visibility-rueckgang-homepage-intent-2026-07-18.md`
- Entscheidung: `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`

Dieser Workpack wird erst durch `CURRENT_WORKPACK.md` aktiviert.

## Zielzustand

```text
/              -> kuratierter Heute-Einstieg
/events/        -> vollständige Veranstaltungen
/aktivitaeten/  -> dauerhafte Aktivitäten
```

```text
kanonische Daten
-> deterministische neutrale Grundauswahl
-> statisches initiales HTML
-> Browser-Anreicherung
```

Die Today-Home bleibt funktional. Ohne JavaScript liefert sie bereits eine verständliche, crawlbare und ehrliche Ausgangsversion.

## Gate A

Vor dem Patch dokumentieren:

- aktuellen `staging`-SHA und offene PRs;
- frische Google-/Bing-Baseline je URL und Query-Cluster;
- aktuellen Build-, Daten- und Renderingfluss;
- Owner von HTML, Auswahl, Build, Sitemap und Tests;
- gemeinsame Grenze zwischen Grundauswahl und Browser-Anreicherung;
- Robots-, Canonical-, Sitemap- und strukturierte-Daten-Iststand;
- Rollback;
- erforderliche E1, E2, E3 und spätere E6.

## Erlaubter Scope

Nach aktueller Verifikation voraussichtlich:

- `index.html`;
- `events/index.html`;
- bei notwendiger Intent-Abgrenzung `aktivitaeten/index.html`;
- `js/today-home.js` oder ein gemeinsam extrahiertes Auswahlmodul;
- kleiner Build-/Prerender-Schritt;
- Sitemap-/Robots-/Canonical-Vertrag;
- SEO-, Build-, Contract- und Browsertests;
- Evidence und zuständige Dokumentation.

## Gesperrter Scope

- kein Rollback der Today-Home;
- keine allgemeine UI-Neugestaltung;
- keine fachliche Event-/Activity-Datenänderung;
- keine neue Personalisierung;
- keine Consent-Änderung;
- kein Bot-Rendering;
- kein neues SSR-Framework ohne Beleg;
- keine zweite Empfehlung-/Rankingmatrix;
- keine Control-Center-, Payment-, Visual- oder Anbieter-Funnel-Arbeit;
- kein Feature-Branch-Deploy und kein direkter Main-Commit.

## Umsetzungspaket

1. Eindeutigen Intent-Vertrag für `/`, `/events/` und `/aktivitaeten/` festlegen.
2. Statische H1, kurze Copy und crawlbare Hauptlinks liefern.
3. Kleine deterministische Grundauswahl aus kanonischen Daten bauen.
4. Browserlogik als Progressive Enhancement ohne doppelten Feed anbinden.
5. `/events/` als Veranstaltungs-Landingpage absichern.
6. Robots, Sitemap, Canonicals, Redirects und strukturierte Daten zusammen prüfen.

## Automatisierte Akzeptanz

- initiales HTML von `/` enthält H1, Copy, Links und nicht leeren Inhaltskern;
- jede Hauptseite hat genau eine H1 und eindeutige Metadaten;
- keine Hauptseite versehentlich `noindex`;
- statischer und dynamischer Zustand nutzen dieselben kanonischen Daten;
- keine kopierte Rankingmatrix;
- keine vergangenen oder ungültigen Inhalte;
- kein doppelter Feed oder relevante Layoutverschiebung;
- ohne JavaScript bleibt die Seite nutzbar;
- HTTP-, Browser-, Sitemap-, Robots- und Canonical-Checks sind grün.

## Evidence

### E1

- dokumentierter Daten-/Renderingfluss;
- exakter Diff;
- Intent-Vertrag;
- Rollback.

### E2

- Syntax-, Unit-, Contract-, Build- und Browsertests;
- initiales HTML ohne JavaScript;
- Progressive Enhancement ohne Duplikate;
- Sitemap-/Robots-/Canonical-Verträge.

### E3

Auf STRATO Staging:

- erwarteter Build-SHA;
- tatsächliches initiales HTML;
- tatsächliche statische Links/Inhalte;
- Browserzustand nach JavaScript;
- keine Buildabweichung.

### E6

Nach späterem `staging -> main`:

- read-only Live-Smoke;
- korrekte Metadaten und Hauptlinks;
- korrekte Sitemap/Robots;
- keine funktionale Regression.

Rankingverbesserung wird zeitversetzt gemessen und ist keine sofortige E6-Postcondition.

## Messplan

- Baseline vor Deploy;
- keine weitere SEO-Strukturänderung ohne Befund;
- erste Tendenz nach mindestens 14 Tagen;
- führende Auswertung nach 28 Tagen;
- Impressionen, Klicks, CTR und Position getrennt;
- Nachfrage, Ranking und Snippet-CTR getrennt bewerten.

## Definition of Done

- Gate A gegen aktuellen Stand vollständig;
- Zielentscheidung umgesetzt;
- initiales HTML sinnvoll und crawlbar;
- Today-Home mit JavaScript vollständig funktionsfähig;
- `/events/` eindeutig gestärkt;
- E1, E2 und Staging-E3 grün;
- genau eine fachliche/visuelle Abnahme, falls noch nötig;
- spätere Releaseentscheidung und Live-E6 getrennt;
- Baseline und Messpunkte dokumentiert.
