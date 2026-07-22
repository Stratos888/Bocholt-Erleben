# Current Workpack

Stand: 2026-07-22

## Aktiver Workpack

**Keiner.**

Der Produkt-Workpack **SEO Recovery – Search Intent und statische Renderingbasis** ist technisch, fachlich und im Livebetrieb abgeschlossen.

Kanonischer Abschlussnachweis:

- finaler Main-SHA: `5490b0fe7416d39e675796a9759b1ef5fef20b5f`;
- Main-PR-Gate #221: grün;
- normaler Main-Deploy: grün;
- `main` und `staging` besitzen denselben Dateiinhalt;
- Live-Seitenquelltext der Startseite enthält `data-static-event-context` genau einmal;
- Live-Seitenquelltext enthält `Alle Events ansehen` genau einmal;
- Live-Seitenquelltext enthält `Aktivitäten entdecken` genau einmal;
- keine zusätzlichen Hero-Zeilen oder visuelle CTA-Regression.

Der ausführliche Abschluss steht in:

- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

## Erreichtes Produktziel

Umgesetzt und belegt sind:

- eindeutige Intentrollen für `/`, `/events/` und `/aktivitaeten/`;
- nicht leerer statischer Inhaltskern aus den kanonischen Deploydaten;
- gemeinsame deterministische Grundauswahl für Build und Browser;
- Progressive Enhancement ohne zweite Rankingmatrix und ohne doppelten Feed;
- Event-JSON-LD ausschließlich auf geeigneten eindeutigen Detailseiten;
- wahrheitsgetreuer Event-/Offer-Vertrag ohne erfundene Preise, Verfügbarkeiten oder Ticket-URLs;
- kostenlose Offers mit numerischem `0` und `EUR`;
- kostenpflichtige Offers nur bei belegtem Preis, Währung und Ticket-URL;
- unbekannte Eintrittslage als indexierbare HTML-Detailseite ohne synthetisches Event-Markup;
- Robots-, Sitemap-, Canonical-, No-JS-, Fixture- und Schema-Contracts im zentralen `PR Gate`.

## Bewertung des Werkzeugsteuerungs-Piloten

Der Pilot war **fachlich brauchbar, aber nicht vollständig erfolgreich**.

Automatische Zielableitung, Repositoryzugriff, Werkzeugaufteilung und PR-Gates funktionierten. Nicht erfüllt wurden die Effizienzkriterien „keine Grundsatzkorrektur nach Umsetzungsbeginn“ und „keine reale Try-and-Error-Schleife nach dem Staging-Merge“. Die zusätzlichen Hero-Links mussten nach mehreren visuellen Korrekturrunden vollständig zurückgebaut werden; die letzte No-JS-Linklücke wurde erst in der Abschlussprüfung entdeckt.

Daraus folgt keine neue allgemeine Prozessoptimierungsrunde. Die konkreten Lücken sind durch Renderer-Fixturetests, Offer-/Schema-Contracts und die strengere visuelle Abnahme abgesichert.

## Zeitversetzte Nacharbeiten

Diese Punkte sind kein offener Implementierungs-Workpack:

- in Google Search Console die Validierung des Befunds `Feld "offers" fehlt` starten und beobachten;
- Suchwirkung nach mindestens 14 und 28 Tagen anhand Impressionen, Klicks, CTR und Position bewerten;
- ohne konkreten neuen Befund keine weitere SEO-Strukturänderung durchführen.

## Genau nächster Schritt

In Google Search Console die Validierung des Befunds `Feld "offers" fehlt` starten.