# Entscheidung: Search-Intent-Architektur und statische Renderingbasis

Datum: 2026-07-18  
Status: als Zielarchitektur angenommen; Umsetzung noch nicht begonnen

## Kontext

Die Today-Home verbessert das Produkt für Nutzer, hat aber beim Wechsel von der früheren Event-Homepage drei SEO-relevante Eigenschaften verloren:

1. Die historisch starke URL `/` ist nicht mehr die vollständige Veranstaltungs-Landingpage.
2. Der Title-, H1- und Copy-Fokus wurde von allgemeinen Veranstaltungen auf eine engere Today-Auswahl verschoben.
3. Empfehlungen und zentrale interne Links entstehen erst nach JavaScript-Ausführung.

Die vertiefte Ursachenanalyse ist dokumentiert in:

`docs/forensics/seo-visibility-rueckgang-homepage-intent-2026-07-18.md`

## Entscheidung

Die Today-Home bleibt bestehen. Sie wird nicht auf die alte Eventkalender-Homepage zurückgerollt.

Stattdessen wird die öffentliche Sucharchitektur dauerhaft auf drei klare, komplementäre Intentionen ausgerichtet:

| URL | führende Such- und Produktintention |
|---|---|
| `/` | Was kann ich heute in Bocholt machen? Events und Aktivitäten als kuratierter Einstieg |
| `/events/` | Vollständige Veranstaltungen und Termine in Bocholt, insbesondere heute und am Wochenende |
| `/aktivitaeten/` | Dauerhafte Aktivitäten, Ausflugsziele und Freizeitideen rund um Bocholt |

Die Seiten dürfen Begriffe gemeinsam verwenden, müssen aber jeweils eine eindeutige Hauptaufgabe, einen eindeutigen Title, eine eindeutige H1 und ein eigenes Canonical behalten.

## Statisch zuerst, JavaScript als Anreicherung

Die Startseite erhält eine statisch verwertbare Ausgangsversion im ausgelieferten HTML.

Diese Ausgangsversion muss mindestens enthalten:

- eine suchintentionstreue H1;
- einen kurzen sichtbaren Erklärungstext zu Veranstaltungen und Aktivitäten heute in Bocholt;
- normale HTML-Links zu `/events/` und `/aktivitaeten/`;
- nach technischer Validierung zusätzliche direkte Einstiege für heute und Wochenende;
- eine kleine neutrale, nicht personalisierte Ausgangsauswahl aus denselben kanonischen Datenquellen wie die dynamische Today-Home.

JavaScript darf diese Ausgangsversion anschließend anreichern oder ersetzen mit:

- Wetterkontext;
- tagesaktueller Rotation;
- personalisierten oder lokal gewichteten Empfehlungen;
- Detailpanel- und Interaktionslogik;
- aktualisierten Karteninhalten.

Die Seite darf nicht von JavaScript abhängen, um ihre grundlegende Suchintention, Hauptnavigation und einen sinnvollen Inhaltskern auszuliefern.

## Kein Bot-Sonderpfad

Es wird keine gesonderte Bot-Version und kein Dynamic Rendering eingeführt.

Crawler und Nutzer erhalten dasselbe initiale HTML. Die dynamische Schicht ist eine Progressive Enhancement des gemeinsamen Ausgangszustands.

Damit werden Cloaking-Risiken, doppelte Renderinglogik und dauerhafte Sonderpfade vermieden.

## Eine Auswahl- und Datenlogik

Build-Time-Ausgabe und Browserausgabe dürfen nicht zwei unabhängig gepflegte fachliche Empfehlungssysteme werden.

Verbindliches Zielprinzip:

```text
kanonische Datenquellen
+ gemeinsam nutzbare deterministische Grundauswahl
+ statischer Ausgangszustand
+ optionale Browser-Anreicherung
```

Falls die aktuelle Auswahlfunktion nicht direkt wiederverwendbar ist, muss zunächst eine kleine gemeinsame, testbare Auswahlgrenze extrahiert werden. Eine zweite kopierte Rankingmatrix nur für SEO ist nicht zulässig.

## Positionierung der Startseite

Die endgültige Copy wird im Workpack validiert. Die semantische Richtung ist verbindlich:

- Title verbindet `Veranstaltungen`, `Aktivitäten`, `heute` und `Bocholt`;
- H1 beschreibt den konkreten Heute-Nutzen;
- sichtbare Copy stellt klar, dass die Seite eine kuratierte Auswahl ist;
- `/events/` bleibt der vollständige Veranstaltungskalender.

Beispielrichtung, noch keine finale Copy:

- Title: `Veranstaltungen & Aktivitäten heute in Bocholt – Bocholt erleben`;
- H1: `Heute in Bocholt: Veranstaltungen & Aktivitäten`.

## Stärkung von `/events/`

`/events/` wird dauerhaft als führende Veranstaltungs-Landingpage behandelt.

Dazu gehören:

- eindeutiger Title, Description, H1 und Canonical;
- statischer Introtext;
- normale interne Links von `/`;
- indexierbare Event- und Filtersemantik;
- Aufnahme in Sitemap und relevante Navigation;
- keine Konkurrenz durch eine nahezu identisch optimierte Homepage.

## Technische Absicherung

Der spätere Implementierungs-Workpack muss automatisiert prüfen:

- sinnvolles initiales HTML ohne JavaScript;
- genau eine H1 je Hauptseite;
- eindeutige Titles, Descriptions und Canonicals;
- normale crawlbare `<a href>`-Links;
- kein doppelter Feed nach JavaScript-Start;
- keine sichtbare Inhaltsduplikation oder starke Layoutverschiebung;
- Übereinstimmung zwischen statischem Ausgangszustand und dynamischer Datenbasis;
- Sitemap- und Robots-Vertrag;
- strukturierte Daten ohne widersprüchliche Seitenidentität;
- Browserfunktion der bestehenden Today-Home.

## Messentscheidung

Die Wirkung wird getrennt nach URL und Query-Cluster bewertet.

Mindestens zu beobachten sind:

- `/` und `/events/` getrennt;
- allgemeine Veranstaltungsqueries;
- Heute-Queries;
- Wochenend-Queries;
- Aktivitäten-Queries;
- Impressionen, Klicks, CTR und durchschnittliche Position;
- Google und Bing getrennt, sofern die Quelldaten es zulassen.

Der Deploy erhält ein festes Referenzdatum. Eine erste Tendenz darf frühestens nach zwei Wochen bewertet werden; die reguläre 28-Tage-Auswertung bleibt führend. Mehrere nachfolgende SEO-Patches ohne neue Evidence sind zu vermeiden.

## Verworfene Alternativen

### Vollständiges Zurückrollen der Today-Home

Verworfen, weil die neue Startseite einen besseren, eigenständigen Produktnutzen bietet und das Problem durch eine statische Suchbasis behoben werden kann.

### Nur Title und Meta-Description ändern

Verworfen als unvollständig, weil damit die fehlende statische Semantik und interne Verlinkung nicht behoben wird.

### Großes SSR-Framework einführen

Verworfen, solange Build-Time-Prerendering im vorhandenen statischen Deploymodell genügt. Ein Frameworkwechsel wäre für den begrenzten Bedarf unverhältnismäßig.

### Spezielle Bot-Ausgabe

Verworfen wegen zusätzlicher Komplexität, Divergenz- und Cloaking-Risiko.

### `/` und `/events/` identisch optimieren

Verworfen, weil dadurch die Seiten um dieselbe Suchintention konkurrieren und die Produkttrennung unklar bleibt.

## Konsequenzen

Positiv:

- Today-Produktlogik bleibt erhalten;
- Crawler erhalten sofort verwertbare Inhalte und Links;
- `/events/` kann klare Veranstaltungsautorität aufbauen;
- künftige Änderungen an Wetter oder Personalisierung gefährden die statische Suchbasis nicht;
- kein schwerer Frameworkwechsel erforderlich.

Kosten und Risiken:

- Build- und Browserausgabe müssen aus derselben fachlichen Logik gespeist werden;
- Hydration beziehungsweise Austausch des Ausgangsfeeds muss duplikat- und layoutstabil umgesetzt werden;
- Rankings reagieren zeitverzögert und erlauben keine sofortige Erfolgsaussage;
- der genaue Anteil der Homepage-Umstellung am bisherigen Rückgang bleibt nicht vollständig quantifizierbar.

## Umsetzungsgrenze

Diese Entscheidung dokumentiert nur den Zielzustand.

Die Umsetzung ist ein eigener `R2`-Workpack, weil ausgeliefertes HTML, Build-/Deployverhalten und öffentliche Suchdarstellung verändert werden. Sie darf erst nach Abschluss des aktuell in `docs/workpacks/active/CURRENT_WORKPACK.md` geführten Workpacks begonnen werden.

Der geplante Scope und die Gates stehen in:

`docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`
