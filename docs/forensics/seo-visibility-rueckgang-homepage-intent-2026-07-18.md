# Forensik: Rückgang der Suchsichtbarkeit nach der Homepage-Neupositionierung

Datum: 2026-07-18  
Status: belegte Analyse mit ausgewiesenen Restunsicherheiten; keine Umsetzung

## Anlass

Das interne SEO-/Mehrwert-Dashboard zeigte gegenüber dem vorherigen Vergleichszeitraum:

- Sichtbarkeit `3.999`, etwa `-48 %`;
- Suchklicks `101`, etwa `-65 %`;
- Ampelstatus rot.

Die Suchwerte stammen aus Google Search Console und Bing Webmaster Tools. Der separate Consent-bedingte Rückgang interner Nutzwertmetriken erklärt diese Suchdaten nicht.

## Belegbasis und Grenze

Verwendet wurden:

- aktuelle und historische Repo-Fassungen von `/`, `/events/` und `js/today-home.js`;
- Merge-/Commit-Historie der Today-Home;
- Google-Search-Console-Bericht für Mai 2026;
- interner Growth-Report mit Query-Clustern;
- Dashboardbefund vom 17.07.2026.

Ein vollständiger Tagesexport über Datum, Query, Seite, Gerät, Land und Search Appearance lag nicht vor. Deshalb wird kein exakter prozentualer Ursachenanteil behauptet.

## Vorher

Die frühere Homepage war selbst die vollständige Veranstaltungsseite mit:

- Title `Bocholt erleben – Events & Termine in Bocholt heute`;
- H1 `Veranstaltungen in Bocholt`;
- sichtbarer Eventcopy;
- Suche, Zeitfiltern und Kategorien.

Im Mai 2026 wurden `313` Google-Klicks und `8.360` Impressionen gemessen; `307` der `313` Klicks entfielen auf Homepagevarianten.

## Nachher

Die Today-Home wurde am 29.05.2026 auf `staging` aktiviert und am 19.06.2026 nach `main` gebracht.

Seitdem führt `/` mit:

- Title `Heute rund um Bocholt – Bocholt erleben`;
- H1 `Heute rund um Bocholt`;
- kurzer Today-Copy und Wetterkontext;
- initial leerem Empfehlungsfeed.

Die vollständige Veranstaltungsintention liegt nun überwiegend auf `/events/`.

## Technischer Befund

Im initialen HTML der Homepage fehlen derzeit konkrete Empfehlungen und die zentralen Einstiegslinks. `js/today-home.js` lädt Daten, wählt Inhalte und erzeugt Karten und Links erst im Browser.

Für Nutzer mit JavaScript funktioniert die Seite. Die statische Suchbasis ist aber deutlich dünner und weniger veranstaltungsorientiert als früher.

## Suchmuster

Wichtige Query-Cluster zeigten nicht nur weniger Nachfrage, sondern auch geringere Klickrate und etwas schlechtere Position.

Allgemeine Veranstaltungssuchen:

- Impressionen etwa `-21 %`;
- Klicks etwa `-43 %`;
- CTR von `3,13 %` auf `2,24 %`;
- Position von `6,33` auf `7,29`.

Wochenendsuchen:

- Impressionen etwa `-5 %`;
- Klicks etwa `-44 %`;
- CTR von `4,04 %` auf `2,37 %`;
- Position von `6,14` auf `7,31`.

## Ursachenbewertung

### Sehr wahrscheinlich wesentlich

**Intent- und URL-Diskontinuität:** Die historisch starke URL `/` wurde von einer breiten Veranstaltungsseite zu einem engeren Today-Einstieg. `/events/` muss eigene Rankings und Nutzersignale aufbauen.

**Schwächeres Snippet für allgemeine Veranstaltungssuchen:** Today-Titel und -Beschreibung treffen breite Eventqueries weniger exakt. Der Klickverlust ist stärker als der Impressionenverlust.

### Technisch belegt, Anteil nicht quantifizierbar

**Zu wenig statische Semantik und interne Verlinkung:** Kerninhalte entstehen erst nach JavaScript.

### Sicher mitwirkend

**Starke Vergleichsbasis:** Mai 2026 war ungewöhnlich stark, erklärt aber den gleichzeitigen CTR- und Positionsverlust nicht allein.

### Plausibel, nicht getrennt belegt

- Saisonalität;
- normale lokale Rankingbewegungen;
- Konkurrenz;
- Unterschiede zwischen Google und Bing.

## Nicht als Hauptursache belegt

- kein vollständiger Indexierungs- oder Erreichbarkeitsausfall;
- keine passende kritische Search-Console-Warnung;
- Consent-Wechsel erklärt nicht Google-/Bing-Werte;
- reine CSS-/Polish-Änderungen erklären die Intentverschiebung nicht.

## Schlussfolgerung

Die Homepage-Neupositionierung ist mit hoher Wahrscheinlichkeit ein wesentlicher kontrollierbarer Mitverursacher. Nicht das Today-Konzept ist falsch, sondern die fehlende statische, suchintentionstreue Ausgangsbasis.

Der nachhaltige Zielzustand steht in:

`docs/decisions/2026-07-18-search-intent-und-static-rendering.md`
