# Entscheidung: Search-Intent und statische Renderingbasis

Datum: 2026-07-18  
Status: angenommener Zielzustand; noch nicht umgesetzt

## Kontext

Die Today-Home verbessert den kuratierten Einstieg, hat aber drei suchrelevante Eigenschaften der früheren Homepage verloren:

1. `/` ist nicht mehr die vollständige Veranstaltungs-Landingpage.
2. Title, H1 und sichtbare Copy passen weniger direkt zu allgemeinen Veranstaltungssuchen.
3. Empfehlungen und zentrale Links entstehen erst nach JavaScript-Ausführung.

Die belegte Analyse steht in `docs/forensics/seo-visibility-rueckgang-homepage-intent-2026-07-18.md`.

## Entscheidung

Die Today-Home bleibt bestehen. Sie wird nicht auf die frühere Eventkalender-Homepage zurückgerollt.

Die drei öffentlichen Hauptseiten erhalten eindeutige, komplementäre Aufgaben:

| URL | Hauptintention |
|---|---|
| `/` | kuratierter Einstieg: heute Events und Aktivitäten in Bocholt |
| `/events/` | vollständige Veranstaltungen und Termine |
| `/aktivitaeten/` | dauerhafte Aktivitäten, Ausflugsziele und Freizeitideen |

Jede Seite behält eigene Title-, Description-, H1- und Canonical-Semantik.

## Statisch zuerst, JavaScript als Anreicherung

Das initial ausgelieferte HTML von `/` enthält mindestens:

- eine suchintentionstreue H1;
- kurze sichtbare lokale Copy;
- normale Links zu `/events/` und `/aktivitaeten/`;
- einen kleinen neutralen Inhaltskern aus kanonischen Daten;
- nach technischer Validierung direkte Einstiege für heute und Wochenende.

JavaScript darf anschließend Wetter, Rotation, Präferenzen, aktuelle Karteninhalte, Detailpanel und Tracking ergänzen oder den neutralen Zustand kontrolliert ersetzen.

Ohne JavaScript bleibt die Seite verständlich und navigierbar.

## Eine Daten- und Auswahlgrenze

Build-Time- und Browserausgabe dürfen nicht zwei unabhängig gepflegte Empfehlungssysteme werden.

```text
kanonische Daten
+ gemeinsam nutzbare deterministische Grundauswahl
+ statischer Ausgangszustand
+ optionale Browser-Anreicherung
```

Eine kopierte SEO-Rankingmatrix ist unzulässig.

## Kein Bot-Sonderpfad

Crawler und Nutzer erhalten dasselbe initiale HTML. Kein Dynamic Rendering, kein Bot-Template und kein hostabhängiger Sonderinhalt.

## Technische Verträge

Der Implementierungs-Workpack prüft automatisiert:

- sinnvolles initiales HTML ohne JavaScript;
- genau eine H1 je Hauptseite;
- eindeutige Titles, Descriptions und Canonicals;
- crawlbare `<a href>`-Links;
- kein doppelter Feed nach JavaScript-Start;
- keine sichtbare Inhaltsduplikation oder relevante Layoutverschiebung;
- gemeinsame Datenbasis von statischem und dynamischem Zustand;
- Sitemap-, Robots- und strukturierte-Daten-Vertrag;
- bestehende Today-Funktion im Browser.

## Messung

Vor dem Deploy wird eine Baseline je URL und Query-Cluster gesichert. Danach:

- erste Tendenz frühestens nach 14 Tagen;
- führende Auswertung nach 28 Tagen;
- Impressionen, Klicks, CTR und Position getrennt;
- Google und Bing getrennt, soweit verfügbar;
- keine weiteren SEO-Strukturpatches ohne neue Evidence.

## Verworfene Alternativen

- vollständiges Zurückrollen der Today-Home;
- nur Meta-Texte ändern;
- großes SSR-Framework ohne Notwendigkeit;
- Bot-spezifische Ausgabe;
- identische Positionierung von `/` und `/events/`.

## Umsetzungsgrenze

Diese Datei beschreibt den Zielzustand und keine bereits produktive Funktion.

Die Umsetzung ist R2 und steht in:

`docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`
