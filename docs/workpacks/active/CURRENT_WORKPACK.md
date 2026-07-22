# Current Workpack

Stand: 2026-07-22

## Aktiver Workpack

**Keiner.**

Der Produkt-Workpack **SEO Recovery – Search Intent und statische Renderingbasis** ist technisch und fachlich auf `staging` abgeschlossen.

Kanonischer Abschlussnachweis:

- Staging-SHA: `2ee2990bb06ee03ac8248e47150bb12de8a1c74e`;
- PR Gate #219: grün;
- normaler Staging-Deploy: grün;
- mobile Sichtprüfung bei 327 × 779 Pixeln: grün;
- keine zusätzlichen Hero-Zeilen oder visuelle CTA-Regression;
- tatsächlicher statischer Renderer-Output enthält direkte crawlbare Hauptlinks zu `/events/` und `/aktivitaeten/`.

Der ausführliche Abschluss steht in:

- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

## Ergebnis

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

Erfüllt wurden insbesondere automatische Zielableitung, selbstständige Repository- und Sheet-Nutzung, klare Trennung von Orchestrierung, technischer Umsetzung und unabhängiger Prüfung sowie grüne PR Gates vor jedem Merge.

Nicht erfüllt wurden die Effizienzkriterien „keine Grundsatzkorrektur nach Umsetzungsbeginn“ und „keine reale Try-and-Error-Schleife nach dem Staging-Merge“. Die zusätzlichen Hero-Links mussten nach mehreren visuellen Korrekturrunden vollständig zurückgebaut werden; die letzte No-JS-Linklücke wurde erst in der Abschlussprüfung entdeckt.

Daraus folgt keine neue allgemeine Prozessoptimierungsrunde. Die konkreten Lücken sind stattdessen in Renderer-Fixturetests, Offer-/Schema-Contracts und der strengeren visuellen Abnahme abgesichert.

## Getrennte Nacharbeiten

Diese Punkte sind kein offener Implementierungs-Workpack:

- den finalen Staging-Stand regulär nach `main` veröffentlichen;
- danach read-only Live-E6 für HTML, Hauptlinks, Sitemap, Robots und repräsentative Eventdetailseiten durchführen;
- in Google Search Console die Validierung des Befunds `Feld "offers" fehlt` nach dem finalen Live-Release starten;
- Suchwirkung getrennt nach 14 und 28 Tagen anhand Impressionen, Klicks, CTR und Position bewerten.

Zeitversetzte Search-Console- und Rankingdaten sind keine rückwirkende technische Abnahme und begründen ohne konkreten neuen Befund keine weitere SEO-Strukturänderung.

## Genau nächster Schritt

Den Dokumentationsabschluss über den normalen PR-Pfad nach `staging` integrieren und anschließend den vollständig geprüften Staging-Stand nach `main` veröffentlichen.
