# Forensik: Rückgang der Suchsichtbarkeit nach der Homepage-Neupositionierung

Datum: 2026-07-18  
Status: belegte Analyse mit ausgewiesenen Restunsicherheiten; noch keine Umsetzung

## Anlass

Das interne SEO-/Mehrwert-Dashboard zeigte für den aktuellen Vergleichszeitraum gegenüber dem vorherigen Zeitraum:

- Sichtbarkeit: `3.999`, etwa `-48 %`;
- Suchklicks: `101`, etwa `-65 %`;
- Ampelstatus: rot.

Die Suchwerte stammen aus Google Search Console und Bing Webmaster Tools. Die gleichzeitig eingebrochenen internen Nutzwertmetriken sind getrennt zu bewerten, weil deren Messung seit dem 29.06.2026 eine aktive Statistik-Einwilligung voraussetzt. Dieser Methodenwechsel erklärt **nicht** den Rückgang der Google-/Bing-Werte.

## Quellen und Beleggrenzen

Verwendet wurden:

- aktueller Repository-Stand von `main` und `staging`;
- historische und aktuelle Fassungen von `index.html`;
- aktuelle Fassung von `events/index.html`;
- aktuelle Renderinglogik in `js/today-home.js`;
- Merge- und Commit-Historie der Today-Home;
- Google-Search-Console-Leistungsbericht für Mai 2026;
- interner Growth-Intelligence-Report mit Query-Clustern;
- Screenshot des SEO-/Mehrwert-Dashboards vom 17.07.2026.

Nicht verfügbar war in dieser Analyse ein vollständiger exportierter Tagesdatensatz mit allen Dimensionen `date`, `query`, `page`, `device`, `country` und `searchAppearance`. Deshalb wird keine exakte prozentuale Ursachenverteilung behauptet.

## Belegter Ausgangszustand

### Frühere Startseite

Vor der Today-Home-Umstellung war `/` selbst die vollständige Veranstaltungsseite.

Die Seite enthielt unter anderem:

- Title: `Bocholt erleben – Events & Termine in Bocholt heute`;
- Meta-Description mit `Alle Events & Termine in Bocholt`;
- H1: `Veranstaltungen in Bocholt`;
- sichtbaren Text zu Events, Märkten und Veranstaltungen;
- Eventsuche;
- Zeitfilter für heute, Woche, Wochenende und spätere Termine;
- sichtbare Kategorien.

Die URL `/` passte damit sehr direkt zu Suchintentionen wie:

- `veranstaltungen bocholt`;
- `bocholt veranstaltungen heute`;
- `was ist heute in bocholt los`;
- `veranstaltungen bocholt dieses wochenende`.

### Leistungsbasis im Mai 2026

Der Google-Search-Console-Bericht für Mai 2026 wies aus:

- `313` Klicks;
- `8.360` Impressionen;
- `307` von `313` Klicks auf die Homepage-Varianten mit und ohne `www`.

Damit lag nahezu die gesamte organische Suchleistung auf der URL `/`.

### Today-Home-Umstellung

Die Today-Home wurde am 29.05.2026 auf `staging` aktiviert. Der große `staging -> main`-Merge erfolgte am 19.06.2026.

Seitdem ist `/` nicht mehr die vollständige Veranstaltungsübersicht, sondern eine kuratierte Einstiegsschicht mit:

- Title: `Heute rund um Bocholt – Bocholt erleben`;
- H1: `Heute rund um Bocholt`;
- Lead: `Drei Vorschläge für heute.`;
- Wetterkontext;
- einem im initialen HTML zunächst leeren Empfehlungsfeed.

Die frühere Veranstaltungsintention liegt heute überwiegend auf `/events/`. Diese Seite besitzt einen passenden Title, eine passende Description, die H1 `Veranstaltungen in Bocholt`, Suche und Zeitfilter.

## Technische Beobachtung

Im initial ausgelieferten HTML der Startseite stehen derzeit:

- keine konkreten Event- oder Aktivitätstitel;
- keine statischen Empfehlungskarten;
- keine statischen Links zu `Alle Events ansehen` und `Aktivitäten entdecken`;
- keine statischen Zeit- oder Kategorieeinstiege.

Erst `js/today-home.js` lädt mehrere JSON-Quellen, kuratiert drei Vorschläge und erzeugt anschließend die Karten und die beiden Hauptlinks.

Für Nutzer mit funktionierendem JavaScript ist das Produkt nutzbar. Für Suchmaschinen entsteht jedoch eine unnötige zweite Renderingstufe. Die statische HTML-Basis trägt deutlich weniger lokale Veranstaltungssemantik als die frühere Startseite.

## Beobachtetes Suchmuster

Der interne Growth-Report zeigte für wichtige Query-Cluster:

### Allgemeine Veranstaltungssuchen

| Kennzahl | früher | später | Veränderung |
|---|---:|---:|---:|
| Impressionen | 958 | 760 | etwa -21 % |
| Klicks | 30 | 17 | etwa -43 % |
| CTR | 3,13 % | 2,24 % | deutlich schlechter |
| Position | 6,33 | 7,29 | knapp ein Platz schlechter |

### Wochenendsuchen

| Kennzahl | früher | später | Veränderung |
|---|---:|---:|---:|
| Impressionen | 223 | 211 | etwa -5 % |
| Klicks | 9 | 5 | etwa -44 % |
| CTR | 4,04 % | 2,37 % | deutlich schlechter |
| Position | 6,14 | 7,31 | gut ein Platz schlechter |

Das Muster besteht nicht nur aus weniger Suchnachfrage. Die Seite wurde teilweise ähnlich häufig eingeblendet, stand etwas schlechter und wurde zugleich deutlich seltener angeklickt.

## Ursachenbewertung

### Hohe Wahrscheinlichkeit: Intent- und URL-Diskontinuität

Die historisch starke URL `/` wurde von einer umfassenden Veranstaltungs-Landingpage zu einer engeren Today-Einstiegsseite umpositioniert.

Folgen:

1. Der frühere direkte Match zu allgemeinen Veranstaltungsqueries wurde abgeschwächt.
2. Die neue führende Veranstaltungsseite `/events/` muss eigene Rankings und Nutzersignale aufbauen.
3. Es handelt sich nicht um eine klassische URL-Migration, weil `/` weiterhin existiert und ihr Canonical auf `/` zeigt.
4. Historische Signale der Homepage werden daher nicht automatisch vollständig auf `/events/` übertragen.

Bewertung: sehr wahrscheinlich ein wesentlicher Mitverursacher.

### Hohe Wahrscheinlichkeit: schwächeres Suchsnippet für allgemeine Veranstaltungsqueries

Ein Nutzer mit der Suchintention `Veranstaltungen in Bocholt` sieht heute ein Ergebnis mit `Heute rund um Bocholt` und einer Beschreibung über Vorschläge, Wetter, Events und Aktivitäten.

Das ist fachlich nicht falsch, aber weniger exakt als der frühere Titel `Events & Termine in Bocholt heute`. Der überproportionale Klickverlust gegenüber dem Impressionenverlust passt zu einer schwächeren Suchergebnis-Relevanz und CTR.

Bewertung: sehr wahrscheinlich.

### Mittlere bis hohe Wahrscheinlichkeit: zu wenig statische Semantik und interne Verlinkung

Die zentralen Empfehlungen und Links entstehen erst im Browser. Dadurch ist das initiale HTML für Crawler deutlich dünner als zuvor.

Bewertung: technisch klar belegt; der exakte Anteil am Rankingverlust ist nicht quantifizierbar.

### Mitwirkender Faktor: außergewöhnlich starke Vergleichsbasis

Der Mai war mit `8.360` Impressionen und `313` Klicks ungewöhnlich stark. Ein Teil des Rückgangs ist daher eine Normalisierung gegenüber einer hohen Basis.

Bewertung: sicher mitwirkend, aber allein keine ausreichende Erklärung für den gleichzeitigen CTR- und Positionsverlust.

### Weitere mögliche Faktoren

- saisonale Nachfrageverschiebungen;
- normale lokale Rankingbewegungen;
- veränderte Konkurrenz in den Suchergebnissen;
- Unterschiede zwischen Google und Bing.

Diese Faktoren sind plausibel, aber mit den vorliegenden Daten nicht ausreichend getrennt belegbar.

## Nicht als Hauptursache belegt

- kein Hinweis auf einen vollständigen Indexierungs- oder Erreichbarkeitsausfall;
- keine kritische Search-Console-Warnung als zeitlich passender Auslöser;
- nicht kritische Event-Markup-Warnungen bestanden bereits im starken Mai;
- der Statistik-Consent-Wechsel betrifft nicht die Google-/Bing-Suchdaten;
- reine CSS- oder visuelle Desktop-Polish-Änderungen erklären die Suchintention nicht.

## Schlussfolgerung

Die Homepage-Neupositionierung ist mit hoher Wahrscheinlichkeit ein wesentlicher, technisch kontrollierbarer Mitverursacher des Rückgangs.

Nicht das Today-Produktkonzept ist das Problem. Das Problem ist, dass beim Wechsel gleichzeitig:

- die historisch etablierte Veranstaltungsintention der URL `/` abgeschwächt wurde;
- die breite Eventautorität auf die neue URL `/events/` verteilt wurde;
- zentrale Inhalte und Links aus dem initialen HTML in eine reine JavaScript-Nachladung wanderten.

Eine vollständige Rückkehr zur alten Homepage wäre fachlich nicht erforderlich. Der nachhaltige Zielzustand ist eine statisch verwertbare, suchintentionstreue Ausgangsversion der Today-Home mit anschließender JavaScript-Anreicherung.

## Verknüpfte Entscheidung

Siehe:

- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`
- `docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`
