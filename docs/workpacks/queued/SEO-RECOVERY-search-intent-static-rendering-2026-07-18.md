# Queued Workpack: SEO Recovery – Search Intent und statische Renderingbasis

Stand: 2026-07-18  
Status: eingeplant, noch nicht aktiv  
Risikoklasse der späteren Umsetzung: `R2`

## Einordnung

Dieser Workpack ist die geplante Umsetzung aus:

- `docs/forensics/seo-visibility-rueckgang-homepage-intent-2026-07-18.md`;
- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`.

Er überschreibt **nicht** `docs/workpacks/active/CURRENT_WORKPACK.md` und darf erst begonnen werden, wenn der dort geführte aktive Workpack abgeschlossen ist.

## Problem

Die historisch starke URL `/` war früher selbst die vollständige Veranstaltungsseite. Nach der Umstellung auf die Today-Home ist die Produktoberfläche besser fokussiert, aber die statische Suchbasis schwächer:

- allgemeine Veranstaltungsintention auf `/` wurde reduziert;
- `/events/` muss als neue Veranstaltungsautorität aufgebaut werden;
- Empfehlungen und wichtige interne Links entstehen erst per JavaScript;
- Title, H1 und Snippet passen weniger exakt zu allgemeinen Veranstaltungsqueries;
- Google-/Bing-Kennzahlen sind gegenüber dem vorherigen Zeitraum deutlich zurückgegangen.

## Zielzustand

Die Today-Home bleibt als Produkt erhalten und liefert zugleich schon ohne JavaScript eine vollständige, ehrliche und suchintentionstreue Ausgangsversion.

Zielarchitektur:

```text
/               -> kuratierter Heute-Einstieg für Events und Aktivitäten
/events/         -> vollständige Veranstaltungen und Termine
/aktivitaeten/   -> dauerhafte Aktivitäten und Ausflugsziele
```

Renderingprinzip:

```text
kanonische Daten
-> deterministische neutrale Grundauswahl
-> statisches initiales HTML
-> Browser-Anreicherung durch Wetter, Rotation und Präferenzen
```

## Gate A – vor dem ersten Patch

Vor der Implementierung müssen dokumentiert sein:

- aktueller `staging`-SHA und offene PRs;
- frische Google-/Bing-Baseline nach URL und Query-Cluster;
- aktueller Deploy- und Datenfluss für `index.html`, Eventdaten und Today-Auswahl;
- Owner-Dateien der statischen Seite, Auswahlfunktion, Build-/Deploykette und Tests;
- genaue Grenze zwischen gemeinsamer Grundauswahl und Browser-Anreicherung;
- Sitemap-, Robots-, Canonical- und strukturierte-Daten-Istzustand;
- Rollbackpfad;
- erwartete Evidence E1, E2, E3 und später E6.

Fakten und Hypothesen müssen getrennt bleiben. Es darf kein Patch nur auf Basis allgemeiner SEO-Annahmen entstehen.

## Geplanter erlaubter Scope

Der finale Scope wird bei Gate A gegen den aktuellen Stand verifiziert. Voraussichtlich betroffen:

- `index.html`;
- `events/index.html`;
- gegebenenfalls `aktivitaeten/index.html` nur zur eindeutigen Intent-Abgrenzung;
- `js/today-home.js` oder ein gemeinsam extrahiertes Auswahlmodul;
- ein kleiner Build-/Prerender-Schritt im vorhandenen Deploymodell;
- Sitemap-/Robots-Erzeugung beziehungsweise statische Dateien;
- SEO-, Build-, Contract- und Browsertests;
- passende Dokumentation und Evidence.

## Gesperrter Scope

- kein vollständiges Zurückrollen der Today-Home;
- keine allgemeine UI-Neugestaltung;
- keine Änderung der fachlichen Event- oder Aktivitätsdaten;
- keine neue Personalisierungslogik;
- keine Änderung der Statistik-Consent-Regeln;
- kein Bot-spezifisches Rendering;
- kein neues SSR-Framework ohne belegte Notwendigkeit;
- keine identische SEO-Positionierung von `/` und `/events/`;
- keine gleichzeitige Bild-, Steuerzentralen-, Payment- oder Anbieter-Funnel-Arbeit;
- kein direkter Commit auf `main`;
- kein Feature-Branch-Deploy.

## Umsetzungspaket

### 1. Search-Intent-Vertrag

Für `/`, `/events/` und `/aktivitaeten/` werden jeweils eindeutig festgelegt und getestet:

- Hauptintention;
- Title;
- Meta-Description;
- H1;
- sichtbarer Introtext;
- Canonical;
- interne Ankertexte;
- strukturierte Seitenidentität.

Die Homepage verbindet `heute`, `Veranstaltungen`, `Aktivitäten` und `Bocholt`, bleibt aber als kuratierter Einstieg erkennbar. `/events/` bleibt die vollständige Veranstaltungsseite.

### 2. Statische Homepage-Brücke

Das initiale HTML von `/` enthält mindestens:

- eine eindeutige H1;
- einen kurzen lokalen Erklärungstext;
- normale Links zu `/events/` und `/aktivitaeten/`;
- nach Validierung direkte Einstiege für heute und Wochenende;
- einen sinnvollen, nicht leeren Inhaltskern.

Die sichtbare Gestaltung bleibt kompakt und darf keine zweite CTA-Wand erzeugen.

### 3. Build-Time-Ausgangsauswahl

Der Build beziehungsweise Deploy erzeugt aus den kanonischen Daten eine kleine neutrale Ausgangsauswahl.

Verbindliche Regeln:

- gleiche Datenquellen wie die Today-Home;
- keine eigene kopierte SEO-Rankingmatrix;
- deterministische und testbare Auswahl;
- keine vergangenen Events;
- keine ungültigen oder visuell nicht darstellbaren Inhalte;
- klare Fallbacks bei fehlenden Daten;
- keine Personalisierung im statischen Zustand.

### 4. Progressive Enhancement

Nach JavaScript-Start darf die vorhandene Today-Logik:

- Wetter anwenden;
- Tagesrotation anwenden;
- Präferenzen berücksichtigen;
- Karten aktualisieren;
- Detailansichten und Tracking aktivieren.

Dabei gelten:

- kein doppelter Feed;
- keine doppelte H1 oder doppelte Navigation;
- keine starken Layoutsprünge;
- keine widersprüchlichen Inhalte zwischen initialem und dynamischem Zustand;
- bei JavaScript-Fehler bleibt die statische Seite vollständig nutzbar.

### 5. Event-Landingpage-Härtung

Für `/events/` werden geprüft und gegebenenfalls korrigiert:

- Title, Description, H1 und Canonical;
- statischer Introtext;
- crawlbare interne Links;
- Filter- und Eventsemantik im initialen HTML;
- Sitemap-Aufnahme;
- strukturierte Daten;
- Abgrenzung zur Homepage.

### 6. Technische Suchbasis

Zusammenhängend prüfen:

- `robots.txt`;
- Sitemap beziehungsweise Generator;
- Canonicals der drei Hauptseiten;
- `www`-/Nicht-`www`-Konsistenz;
- HTTP-Status und Redirectketten;
- strukturierte Daten;
- initiale HTML-Ausgabe ohne Browser-JavaScript;
- Renderausgabe mit Browser-JavaScript.

## Automatisierte Akzeptanzkriterien

### Initiales HTML

- [ ] `/` enthält ohne JavaScript eine sinnvolle H1, sichtbare Copy und crawlbare Hauptlinks.
- [ ] `/` enthält einen nicht leeren statischen Inhaltskern.
- [ ] `/events/` und `/aktivitaeten/` sind direkt über normale Links erreichbar.
- [ ] Jede Hauptseite besitzt genau eine H1.
- [ ] Titles, Descriptions und Canonicals sind eindeutig und zur Seitenintention konsistent.
- [ ] Keine Hauptseite ist versehentlich `noindex`.

### Gemeinsame Logik

- [ ] Build-Auswahl und Browserauswahl verwenden dieselben kanonischen Daten und eine dokumentierte gemeinsame Grundlogik.
- [ ] Keine zweite unabhängig gepflegte Empfehlungsmatrix.
- [ ] Vergangene oder ungültige Inhalte werden im statischen Zustand ausgeschlossen.
- [ ] Fallbacks sind deterministisch getestet.

### Browser

- [ ] Today-Home bleibt funktional und visuell im vereinbarten Produktzustand.
- [ ] Nach JavaScript-Start entsteht kein doppelter Feed.
- [ ] Detailpanel, Wetter, Rotation und Präferenzen funktionieren weiter.
- [ ] Kein relevanter Layoutshift durch den Austausch der Ausgangsauswahl.
- [ ] Ohne JavaScript bleibt die Seite navigierbar und inhaltlich verständlich.

### Infrastruktur

- [ ] Sitemap, Robots und Canonicals bestehen den Contract-Test.
- [ ] HTTP-Smoke ist grün.
- [ ] Browser-Smoke ist grün.
- [ ] Kein Bot-Sonderpfad und keine hostabhängige Inhaltsdivergenz.

## Evidence-Plan

### E1

- dokumentierter Daten- und Renderingfluss;
- exakter Diff gegen aktuellen `staging`-Stand;
- eindeutiger Intent-Vertrag je URL;
- dokumentierter Rollback.

### E2

- Syntax-, Unit-, Contract-, Build- und Browsertests;
- Test des initialen HTML ohne JavaScript;
- Test der Progressive Enhancement ohne Duplikate;
- Sitemap-/Robots-/Canonical-Verträge.

### E3

Nach Integration nach `staging`:

- deployter Build-SHA;
- tatsächliches initiales HTML von STRATO Staging;
- tatsächliche statische Links und Inhalte;
- Browserzustand nach JavaScript-Ausführung;
- keine Abweichung zwischen vorgesehenem und deploytem Build.

### E6

Nach späterer Freigabe und `staging -> main`:

- read-only Live-Smoke;
- korrekte Titles, H1, Canonicals und Hauptlinks;
- korrekte Sitemap-/Robots-Ausgabe;
- keine funktionale Regression.

Rankingverbesserungen selbst sind keine sofortige E6-Postcondition, sondern werden zeitversetzt gemessen.

## Mess- und Lernplan

Vor Deploy wird eine Baseline gespeichert für:

- `/`;
- `/events/`;
- `/aktivitaeten/`;
- allgemeine Veranstaltungsqueries;
- Heute-Queries;
- Wochenend-Queries;
- Aktivitäten-Queries;
- Google und Bing getrennt, soweit verfügbar.

Nach Deploy:

- keine weitere SEO-Strukturänderung ohne neuen technischen Befund;
- erste Trendprüfung nach mindestens 14 Tagen;
- führende Bewertung nach einem vollständigen 28-Tage-Zeitraum;
- Vergleich von Impressionen, Klicks, CTR und Position;
- getrennte Bewertung von Nachfrage, Ranking und Snippet-CTR;
- keine Erfolgsbehauptung allein aufgrund einzelner Tage.

## Rollback

Der Workpack muss so geschnitten sein, dass zurückgenommen werden können:

- statische Copy und Links;
- Build-Time-Ausgangsauswahl;
- Progressive-Enhancement-Integration;
- Sitemap-/Canonical-Anpassungen.

Ein Rollback darf die bestehende Today-Home nicht dauerhaft funktionsunfähig machen. Die vorherige dynamische Ausgabe bleibt bis zum vollständigen E3-Nachweis als klarer Revertpunkt erhalten.

## Definition of Done

- [ ] Gate A vollständig und mit aktuellem Repository- und Datenstand belegt.
- [ ] Entscheidung aus `docs/decisions/2026-07-18-search-intent-und-static-rendering.md` umgesetzt.
- [ ] Initiales HTML ist ohne JavaScript inhaltlich sinnvoll und crawlbar.
- [ ] Today-Home bleibt mit JavaScript produktseitig vollständig funktionsfähig.
- [ ] `/events/` ist eindeutig als Veranstaltungs-Landingpage gestärkt.
- [ ] E1 und E2 vollständig.
- [ ] Staging-E3 vollständig.
- [ ] Genau eine fachliche/visuelle Staging-Abnahme, falls nach automatisierter Prüfung noch erforderlich.
- [ ] Erst danach Entscheidung über `staging -> main`.
- [ ] Live-E6 nach Release.
- [ ] Baseline und 14-/28-Tage-Messpunkte dokumentiert.

## Nächster erlaubter Schritt

Noch keine Implementierung.

Zuerst den aktuell aktiven Workpack in `docs/workpacks/active/CURRENT_WORKPACK.md` abschließen. Danach diesen Workpack aktivieren, Gate A gegen den dann aktuellen `staging`-Stand durchführen und erst anschließend den kleinsten zusammenhängenden Patch entwerfen.
