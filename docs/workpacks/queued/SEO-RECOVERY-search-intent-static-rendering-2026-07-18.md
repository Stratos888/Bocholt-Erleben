# Queued Workpack: SEO Recovery – Search Intent und statische Renderingbasis

Stand: 2026-07-21  
Status: eingeplant, nicht aktiv  
Risikoklasse: R2

## Grundlage

- Forensik: `docs/forensics/seo-visibility-rueckgang-homepage-intent-2026-07-18.md`
- Entscheidung: `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`
- Google-Search-Console-Befund vom 2026-07-21: nicht kritische Warnung für strukturierte Veranstaltungsdaten, weil das Feld `offers` fehlt.

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

Für veröffentlichte Event-Markups gilt zusätzlich ein wahrheitsgetreuer Google-Event-Vertrag: `offers` wird aus belegten Eintritts- beziehungsweise Ticketdaten erzeugt. Kostenlose Veranstaltungen erhalten `price: 0` und `priceCurrency: EUR`; kostenpflichtige Veranstaltungen nur belegte Preis-, Verfügbarkeits- und Angebots-URLs. Es werden keine Preise, Ticketverfügbarkeiten oder Verkaufslinks erfunden.

## Gate A

Vor dem Patch dokumentieren:

- aktuellen `staging`-SHA und offene PRs;
- frische Google-/Bing-Baseline je URL und Query-Cluster;
- aktuellen Build-, Daten- und Renderingfluss;
- Owner von HTML, Auswahl, Build, Sitemap und Tests;
- gemeinsame Grenze zwischen Grundauswahl und Browser-Anreicherung;
- Robots-, Canonical-, Sitemap- und strukturierte-Daten-Iststand;
- betroffene Search-Console-URLs und tatsächliches Event-JSON-LD für den Befund `Feld "offers" fehlt`;
- vorhandene Eintritts-, Preis-, Ticket- und Quellenfelder sowie deren belastbare Abdeckung;
- Rollback;
- erforderliche E1, E2, E3 und spätere E6.

## Erlaubter Scope

Nach aktueller Verifikation voraussichtlich:

- `index.html`;
- `events/index.html`;
- bei notwendiger Intent-Abgrenzung `aktivitaeten/index.html`;
- `js/today-home.js` oder ein gemeinsam extrahiertes Auswahlmodul;
- `js/seo-schema.js` beziehungsweise der nach Gate A belegte Owner des Event-JSON-LD;
- minimaler Event-Build-/Datenvertrag, falls für wahrheitsgetreue `offers`-Daten erforderlich;
- kleiner Build-/Prerender-Schritt;
- Sitemap-/Robots-/Canonical-Vertrag;
- SEO-, Build-, Schema-, Contract- und Browsertests;
- Evidence und zuständige Dokumentation.

## Gesperrter Scope

- kein Rollback der Today-Home;
- keine allgemeine UI-Neugestaltung;
- keine fachliche Event-/Activity-Datenänderung außerhalb des minimal erforderlichen, belegten Offer-Vertrags;
- keine erfundenen Preise, Ticketverfügbarkeiten, Ticket-URLs oder pauschalen `offers`-Objekte;
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
7. Den Google-Event-Vertrag für `offers` vollständig und quellentreu umsetzen:
   - kostenlose Veranstaltung: `Offer` mit `price: 0` und `priceCurrency: EUR`;
   - kostenpflichtige Veranstaltung: niedrigster belegter Preis, Währung, zulässige Angebots-URL und nur belegte Verfügbarkeit;
   - mehrere Ticketarten nur als getrennte Offers, wenn die Daten tatsächlich vorliegen;
   - unklare Eintrittslage bleibt ein sichtbarer Datenqualitätsbefund und wird nicht synthetisch aufgefüllt.
8. Event-Markup auf den jeweiligen eindeutigen Event-Detailseiten ausgeben und Listen-/Sammelseiten nicht als Ersatz für ein einzelnes Event auszeichnen.

## Automatisierte Akzeptanz

- initiales HTML von `/` enthält H1, Copy, Links und nicht leeren Inhaltskern;
- jede Hauptseite hat genau eine H1 und eindeutige Metadaten;
- keine Hauptseite versehentlich `noindex`;
- statischer und dynamischer Zustand nutzen dieselben kanonischen Daten;
- keine kopierte Rankingmatrix;
- keine vergangenen oder ungültigen Inhalte;
- kein doppelter Feed oder relevante Layoutverschiebung;
- ohne JavaScript bleibt die Seite nutzbar;
- HTTP-, Browser-, Sitemap-, Robots- und Canonical-Checks sind grün;
- jedes erzeugte `Event`-Markup hat eine eindeutige Detail-URL und entspricht dem sichtbaren Seiteninhalt;
- `offers` wird ausschließlich aus belegten Daten erzeugt;
- kostenlose Events werden mit numerischem Preis `0` und `EUR` modelliert;
- kostenpflichtige Offers enthalten keinen unbelegten Preis und keine ungeeignete allgemeine Quell-URL;
- Schema-Contract-Tests decken kostenlos, kostenpflichtig, mehrere Ticketarten und fehlende/unklare Angebotsdaten ab;
- Google Rich Results Test beziehungsweise ein gleichwertiger automatisierter Validator zeigt für repräsentative vollständige Eventfälle keine Warnung `Feld "offers" fehlt`.

## Evidence

### E1

- dokumentierter Daten-/Renderingfluss;
- exakter Diff;
- Intent-Vertrag;
- Event-/Offer-Datenvertrag einschließlich Quellen- und Wahrheitsregeln;
- dokumentierte betroffene Search-Console-URLs;
- Rollback.

### E2

- Syntax-, Unit-, Contract-, Build- und Browsertests;
- initiales HTML ohne JavaScript;
- Progressive Enhancement ohne Duplikate;
- Sitemap-/Robots-/Canonical-Verträge;
- validiertes Event-JSON-LD für kostenlose und kostenpflichtige repräsentative Fälle;
- Negativtest gegen erfundene oder unvollständige Offer-Werte.

### E3

Auf STRATO Staging:

- erwarteter Build-SHA;
- tatsächliches initiales HTML;
- tatsächliche statische Links/Inhalte;
- Browserzustand nach JavaScript;
- tatsächliches JSON-LD auf repräsentativen Event-Detailseiten;
- Rich-Results-Prüfung ohne fehlendes `offers` bei vollständig belegten Fällen;
- keine Buildabweichung.

### E6

Nach späterem `staging -> main`:

- read-only Live-Smoke;
- korrekte Metadaten und Hauptlinks;
- korrekte Sitemap/Robots;
- korrektes Event-/Offer-Markup auf repräsentativen Live-Detailseiten;
- in Search Console die Fehlerbehebung für `Feld "offers" fehlt` starten und den zeitversetzten Validierungsstatus dokumentieren;
- keine funktionale Regression.

Rankingverbesserung und die Search-Console-Neubewertung werden zeitversetzt gemessen und sind keine sofortige E6-Postcondition.

## Messplan

- Baseline vor Deploy;
- Anzahl gültiger Event-Elemente und betroffener `offers`-Warnungen vor Deploy sichern;
- keine weitere SEO-Strukturänderung ohne Befund;
- erste Tendenz nach mindestens 14 Tagen;
- führende Auswertung nach 28 Tagen;
- Impressionen, Klicks, CTR und Position getrennt;
- Nachfrage, Ranking und Snippet-CTR getrennt bewerten;
- Search-Console-Validierung des `offers`-Befunds getrennt vom Ranking bewerten.

## Definition of Done

- Gate A gegen aktuellen Stand vollständig;
- Zielentscheidung umgesetzt;
- initiales HTML sinnvoll und crawlbar;
- Today-Home mit JavaScript vollständig funktionsfähig;
- `/events/` eindeutig gestärkt;
- Event-Markup liegt auf eindeutigen Detailseiten und enthält bei belegter Eintrittslage wahrheitsgetreue `offers`;
- der konkrete Search-Console-Befund `Feld "offers" fehlt` ist technisch behoben und nach dem Live-Release zur Validierung eingereicht;
- E1, E2 und Staging-E3 grün;
- genau eine fachliche/visuelle Abnahme, falls noch nötig;
- spätere Releaseentscheidung und Live-E6 getrennt;
- Baseline und Messpunkte dokumentiert.
