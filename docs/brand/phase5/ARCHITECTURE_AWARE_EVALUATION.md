# Phase 5 – Architekturabhängige Markenbewertung

Stand: 2026-08-01
Workpack: #259
Status: verbindlicher Phase-5-Override vor weiterer Gestaltung

## 1. Anlass

Das allgemeine Phase-3-Framework entstand als notwendige Gegenreaktion auf eine zu leichtfertig qualifizierte, generische Wortmarke. In Phase 4 und den ersten Phase-5-Studien wurde sichtbar, dass einzelne Regeln übersteuern:

- `Genericity-Ablation = Knock-out`, sobald nach Entfernung eines Details eine Standardschrift verbleibt;
- `Austauschbarkeit = Knock-out`, wenn eine Form auch für andere digitale Angebote funktionieren könnte;
- eigenständige Silhouette und fertiges App-Icon bereits vor einer belastbaren Wortmarke;
- spezifische Produktbedeutung muss aus dem monochromen Zeichen allein entstehen.

Diese Regeln sind sinnvoll gegen Gimmicks, UI-Icons und schwache Monogramme. Unverändert auf eine Wortmarkenarchitektur angewandt, erzeugen sie jedoch den falschen Anreiz:

- überzeichnete Buchstabeneingriffe;
- künstliche Schnitte und Defekte;
- erzwungene Symbole;
- zu frühe App-Icon-Ableitungen;
- buchstäbliche Produktmetaphern statt einer souveränen Marke.

Viele hochwertige digitale Marken sind nicht deshalb stark, weil ihr monochromes Zeichen die Produktkategorie wörtlich erklärt. Sie sind stark durch präzise Wortmarkenzeichnung, charakteristischen Rhythmus, konsistente Typografie, Farbe, Sprache, Bildverhalten, Bewegung und reale Produktanwendung.

## 2. Unveränderte Premium-Barriere

Die Korrektur senkt den Qualitätsanspruch nicht.

Weiterhin verbindlich:

- mindestens `90/100`;
- jeder Dimensionsmindestwert;
- keine dominante falsche Kategorie;
- keine Stock-, Template-, UI- oder Artefaktlesart;
- keine unveränderte Standardschrift als Marke;
- keine Wirkung nur durch Mock-up, Farbe, Animation oder Erklärung;
- nachvollziehbare Konstruktion, Lizenz und Herkunft;
- höchstens zwei Richtungen im Product-Owner-Gate;
- keine Nutzerpräsentation unreifer Studien.

Geändert wird, **wie** diese Anforderungen je Markenarchitektur geprüft werden.

## 3. Architekturklassen

### W – Wortmarkenbasierte Identität

Primärwert ist die vollständige Wortmarke. Eine kompakte Fassung oder App-Marke wird erst nach bestandenem Wortmarken-Gate entwickelt.

### Z – Zeichen plus Wortmarke

Wortmarke und Zeichen müssen einzeln und gemeinsam überzeugen. Ein generisches Zeichen kann nicht durch eine gute Wortmarke kompensiert werden.

### I – Integriertes Zeichen-Wort-System

Das Identitätsprinzip liegt in der Verbindung von Zeichen und Buchstaben. Die Verbindung darf weder Artefakt noch dekorative Anfügung sein.

### R – Responsive Identität

Unterschiedliche Fassungen sind zulässig, wenn Formprinzip, Ton und Wiedererkennung eindeutig verwandt bleiben.

Phase 5 arbeitet zunächst ausschließlich in Klasse `W`. Ein Wechsel zu `Z`, `I` oder `R` wird erst eröffnet, wenn die Wortmarke eine tragfähige primäre Identität besitzt.

## 4. Gate W0 – vollständige Wortmarke

Vor App-Icon, Kleinmarke oder Brandboard wird ausschließlich die vollständige Wortmarke geprüft.

Pflicht:

- schnell und fehlerfrei als `Bocholt erleben` lesbar;
- professioneller optischer Rhythmus, Kerning und Proportion;
- mindestens zwei zusammenhängende formale Eigenheiten über mehrere Buchstaben hinweg;
- nicht nur Gewicht, Tracking, Kursivstellung, Farbe oder ein einzelner Eingriff;
- eigenständige Gesamtwortform im mobilen Header;
- glaubwürdige Balance aus ruhig, modern, nahbar und hochwertig;
- deutlich stärker als die aktuelle Kombination aus generischem Icon und Systemtext;
- keine dominante Lesart als Magazin, Café, Kulturinstitution, Verwaltung, Tourismus, Sport, Beauty oder generische Tech-Marke.

Scheitert W0, endet die Studie. Es wird kein App-Icon entwickelt.

## 5. Architekturabhängige Knock-outs

### 5.1 Genericity-Ablation

Bei Klasse `W` wird nicht ein einzelnes Detail entfernt. Geprüft wird das **gesamte Buchstabensystem**:

- Bleiben nach Neutralisierung aller systemischen Eigenheiten nur unveränderte Font-Outlines, ist die Studie nicht eigenständig genug.
- Beruht die Identität auf mehreren kohärenten Modifikationen, eigenem Rhythmus und eigener Gesamtzeichnung, ist die Nutzung einer lizenzierten Schriftbasis kein automatischer Knock-out.
- Ein einzelner Schnitt, Punkt, Ligaturtrick oder Farbakzent bleibt Knock-out.

### 5.2 Austauschbarkeit

Bei Klasse `W` ist Branchenneutralität nicht automatisch ein Knock-out.

Harter Knock-out nur, wenn:

- die Wortmarke wie ein Stock- oder Marketplace-Template wirkt;
- sie praktisch unverändert als beliebige Firmenmarke austauschbar ist **und** keinerlei eigenständige Tonalität oder Systemfähigkeit besitzt;
- eine falsche Kategorie dominant wird.

Mittlere Austauschbarkeit wird im Score abgezogen, nicht automatisch beendet.

### 5.3 Erklärungstest

Die Wortmarke muss ohne Herleitung sichtbar eine passende Haltung tragen. Sie muss nicht aus sich allein `Event- und Aktivitätsplattform` erklären.

Harter Knock-out, wenn die behauptete Qualität nur im Konzepttext existiert oder die sichtbare Wirkung dem Konzept widerspricht.

### 5.4 Silhouettenprüfung

Bei Klasse `W` wird die charakteristische **Wortform** geprüft:

- Rhythmus aus Ober- und Unterlängen;
- Dichte und Öffnung;
- Verhältnis der beiden Wörter;
- wiederkehrende Buchstabenformen;
- Erkennbarkeit im Header.

Ein freistehendes Symbol ist keine Voraussetzung.

### 5.5 Realgrößentest

Vor bestandener Wortmarke umfasst der Test:

- mobiler Header in tatsächlicher Größe;
- kompakte horizontale Darstellung;
- Wortmarke bei kleiner lesbarer Breite.

Favicon und App-Icon werden erst in Gate W2 geprüft.

### 5.6 Produkt- und Kategorieprüfung

Die Wortmarke wird zunächst monochrom und anschließend im identischen realen Produktkontext geprüft. Produktspezifität darf aus dem Zusammenspiel entstehen von:

- Wortmarke;
- Name;
- Typografierollen;
- Farbrollen;
- Sprache;
- Bildverhalten;
- Bewegung;
- realer mobiler Anwendung.

Ein wörtliches Piktogramm ist keine Voraussetzung.

## 6. Gate W1 – Marken-Minisystem

Nur eine bestandene Wortmarke erhält ein neutrales Mini-System:

- Primär- und Sekundärtypografie;
- Farbrollen innerhalb des bestehenden Produkts;
- ein Bild- und Zuschnittsprinzip;
- ein zurückhaltendes Bewegungsprinzip;
- reale Today-, Detail- und Veranstalterkontexte;
- Vergleich mit der aktuellen öffentlichen Identität.

Die Systemanwendung darf keine schwache Wortmarke retten. Sie prüft, ob die bereits starke Wortmarke eine eigenständige Marke tragen kann.

## 7. Gate W2 – responsive Kleinform und App-Marke

Erst nach W0 und W1 wird geprüft, welche Architektur passend ist:

1. typografischer Ausschnitt aus der Wortmarke;
2. speziell gezeichnete kompakte Buchstabenform;
3. eigenständige, aber formverwandte App-Marke;
4. reine Wortmarke im Header und separate funktionale App-Fassung.

Die App-Marke muss:

- bei 16, 24, 32, 48 und 64 Pixeln funktionieren;
- unter Kreis-, Squircle- und Maskable-Masken stabil bleiben;
- keine UI-, Scanner-, Karten-, Dashboard-, Analytics- oder Monogramm-Standardform sein;
- erkennbar zur Primäridentität gehören.

## 8. Phase-5-Scorecard für Klasse W

Nur Studien nach bestandenem W0, W1 und W2 werden bewertet.

| Dimension | Gewicht | Mindestwert |
|---|---:|---:|
| Qualität und Eigenständigkeit der Wortmarke | 25 | 22 |
| Kohärenz und Wiedererkennung des Markensystems | 20 | 17 |
| Passung zu Produkt, Haltung und Zielgruppen | 20 | 17 |
| Wirkung ohne Herleitung und Kategorieabgrenzung | 15 | 13 |
| Mobile und responsive Markenfunktion | 10 | 8 |
| Konstruktion, Provenienz und technische Robustheit | 10 | 8 |

Freigabe nur bei:

- mindestens `90/100`;
- jedem Mindestwert;
- keinem architekturabhängig belegten Knock-out;
- zwei getrennten positiven Bewertungspässen;
- keinem ungelösten Red-Team-Befund.

## 9. Konsequenz für bestehende Studien

`territory-a-study-01` wird nicht wegen fehlender wörtlicher Produktsymbolik beendet. Es wird gegen Gate W0 geprüft.

Die bereits erzeugten standardschriftbasierten Schnittvarianten und monolinearen Systeme bleiben beendet, weil sie unabhängig von der Framework-Korrektur an sichtbarer Qualität, Kohärenz oder Kategorieabgrenzung scheitern.

Neue Studien beginnen nicht mit einem App-Icon. Der nächste Fortschritt ist ausschließlich eine belastbare vollständige Wortmarke.