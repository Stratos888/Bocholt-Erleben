# Markenidentität – Zielvertrag und belastbarer Lieferweg

Stand: 2026-07-27
Status: angenommener Zielzustand; noch nicht beauftragt oder umgesetzt
Operativer Owner bei Umsetzung: jeweils genau ein aktives GitHub-Workpack

## 1. Zweck und Geltungsgrenze

Dieses Dokument ist der kanonische Zielvertrag für die zukünftige Markenidentität von **Bocholt erleben**. Es legt fest:

- welche strategischen, rechtlichen und organisatorischen Fragen vor einem Designauftrag geschlossen werden;
- welche Beschaffungs- und Produktionswege geeignet, bedingt geeignet oder ausgeschlossen sind;
- wie Wortmarke, optionales Markenzeichen, App-Icon und visuelles System entwickelt und geprüft werden;
- welche Entscheidungen beim Product Owner verbleiben und welche interne Facharbeit nicht als Nutzeriteration sichtbar wird;
- welche Dateien, Nutzungsrechte, Lizenzen und technischen Nachweise vor einer Repository-Integration vorliegen müssen;
- wie die spätere Implementierung vom externen Markendesign getrennt wird.

Dieses Dokument ist **keine fertige Markenidentität** und gibt keine öffentliche Änderung frei. Bis zum vollständigen Abschluss aller Gates bleiben aktuelles Logo, App-Icon, Wortlaut, PWA-Assets, Fonts, Farben und UI unverändert.

## 2. Belegter Ausgangspunkt

### 2.1 Produktidentität

Bocholt erleben ist eine mobile-first Plattform, die relevante Veranstaltungen, Aktivitäten und lokale Freizeitideen kuratiert, verständlich bündelt und schnell nutzbar macht.

Die Marke soll wirken:

- vertrauenswürdig;
- ruhig und modern;
- lokal und persönlich, aber nicht provinziell;
- hochwertig, aber nicht elitär;
- lebendig, aber nicht laut;
- hilfreich, direkt und mobil effizient;
- glaubwürdig für Nutzer, Veranstalter und Anbieter.

Die Marke ist nicht:

- Stadtverwaltung, Behörde oder amtlicher Veranstaltungskalender;
- Tourismusbroschüre oder Heimatverein;
- Karten-, Routen- oder reine Fahrrad-App;
- Bank, Versicherung, Immobilien- oder generische SaaS-Marke;
- Gaming-, Dating- oder laute Lifestyle-App;
- Display-Werbeportal, Kleinanzeigenmarkt oder gekaufte Empfehlungsfläche.

### 2.2 Bestehendes Produktsystem

Der aktuelle öffentliche Header verwendet das bestehende App-Icon und die einfache Textzeile `Bocholt erleben`. Das Produkt besitzt bereits:

- eine belastbare mobile-first Informationsarchitektur;
- zentrale Farb- und Komponententokens;
- ein einheitliches Lucide-Icon-System;
- Today-, Event-, Activity-, Detail-, Anbieter- und Vertrauenskontexte;
- bestehende Responsive-, Accessibility-, PWA-, Cache- und Browserverträge.

Die Markenentwicklung startet deshalb **nicht** mit einer allgemeinen Neugestaltung des Produkts. Sie entwickelt zuerst den Markenkern und integriert ihn danach kontrolliert in die vorhandenen Owner.

### 2.3 Stadtmarke Bocholt

Die Stadt Bocholt besitzt seit 2024 eine eigene Stadtmarke als Place-Branding-Leitplanke. Deren belegte Stärken – unter anderem Gemeinschaft, Nähe zu den Niederlanden sowie Historie und Natur im Stadtbild – sind Kontextwissen, aber kein Baukasten für ein Produktlogo.

Verbindliche Grenze:

- Bocholt erleben bleibt ohne ausdrückliche Vereinbarung eine eigenständige Plattform;
- Gestaltung, Sprache und Absender dürfen keine amtliche Trägerschaft oder offizielle Kooperation suggerieren;
- Wappen-, Siegel-, Rathaus-, Kirchturm-, Brücken-, Fahrrad-, Blatt- oder Landschaftsmontagen sind keine Abkürzung zu lokaler Eigenständigkeit;
- eine spätere formale Kooperation oder Co-Branding-Lösung benötigt einen separaten Produkt-, Rechts- und Markenvertrag.

## 3. Root Cause der bisherigen Schleifen

Die bisherigen Versuche haben drei Aufgaben zu früh miteinander vermischt:

1. Markenstrategie und Positionierung;
2. handwerkliche Entwicklung von Wortmarke, Zeichen und App-Icon;
3. vollständige Produkt- und UI-Integration.

Bildgenerierung und programmatisch konstruierte SVGs konnten Richtungen visualisieren, aber keine belastbare typografische und formale Markenqualität sichern. Schwache Zeichen wurden dadurch wiederholt in aufwendigen BrandLabs präsentiert und erst danach verworfen.

Daraus folgt:

- keine weitere Logoerzeugung durch Chat, Bild-KI oder Code als Produktionsweg;
- keine vollständige UI-Ausarbeitung, bevor ein handwerklich belastbarer Markenkern existiert;
- viele notwendige Entwurfs- und Verwerfungsrunden finden intern beim beauftragten Designer statt;
- der Product Owner sieht höchstens zwei fachlich kuratierte Konzepte und trifft keine Entscheidung zwischen unreifen Skizzen.

## 4. Zentrale Architekturentscheidung

### 4.1 Wortmarke zuerst, Symbol nur bei Mehrwert

Die Entwicklung ist **wordmark-first**, nicht symbol-first.

Verpflichtend ist eine individuelle, hochwertige Wortmarke. Ein separates Markenzeichen ist optional und wird nur übernommen, wenn es:

- die Wiedererkennbarkeit nachweislich stärkt;
- eigenständig und nicht klischeehaft ist;
- als App-Icon und in sehr kleinen Größen funktioniert;
- mit der Wortmarke ein gemeinsames Formprinzip bildet;
- keine falsche Produktkategorie oder amtliche Nähe vermittelt.

Eine reine Wortmarke ist ein vollwertiger Finalkandidat. Das App-Icon darf daraus als optisch eigenständiger typografischer Ausschnitt, Monogramm oder kompakte Systemform abgeleitet werden. Es muss nicht die gesamte Markenbotschaft erklären.

### 4.2 Marke ist ein System, nicht nur ein Logo

Der Zielzustand umfasst:

- individuelle Wortmarke;
- optionales Markenzeichen;
- responsive Wort-Bild-Kombinationen;
- App-Icon- und Kleingrößenprinzip;
- typografische Hierarchie;
- Farbrollen und Kontrastprinzip;
- Bildsprache und Zuschnittlogik;
- ein zurückhaltendes Bewegungsprinzip;
- Tonalität und kurze Textbeispiele;
- klare Anwendung im digitalen Produkt.

Die Marke darf Inhalte und Funktionen nicht verdrängen. Der Markenkern wird im Header, App-Icon, in Typografie, Farbe, Bildsprache und wenigen charakteristischen Details erkennbar – nicht durch wiederholtes Platzieren des Logos auf jeder Oberfläche.

## 5. Strategische Vorentscheidung zum Namen

Vor einem kostenpflichtigen Identitätsauftrag wird die Namensfrage einmalig geschlossen. Der Name `Bocholt erleben` wird nicht beiläufig im Designprozess geändert.

### 5.1 Standardannahme

Der bestehende Name bleibt erhalten, weil er:

- die lokale Leistung unmittelbar erklärt;
- bereits in Produkt, Domain, Suchsignalen und öffentlichen Inhalten verwendet wird;
- keine zusätzliche Lernhürde erzeugt.

### 5.2 Pflichtprüfung vor Beauftragung

Zu prüfen sind:

- identische und ähnliche Wortmarken mit Schutzwirkung in Deutschland beziehungsweise der EU;
- Unternehmens-, Domain-, App-, Social- und lokale Namensverwendung;
- mögliche Verwechslung mit Stadt, Stadtmarketing, Tourismus oder anderen lokalen Absendern;
- tatsächliche Schutz- und Durchsetzbarkeit der Wort-, Bild- oder kombinierten Marke;
- Auswirkung einer möglichen späteren Expansion über Bocholt hinaus.

Eine interne Registersuche ist nur ein Preflight. Die finale Beurteilung von Schutzfähigkeit und Verwechslungsgefahr erfolgt professionell.

### 5.3 Nur bei belegtem Blocker zu entscheidende Alternativen

Falls die Vorprüfung einen erheblichen rechtlichen, strategischen oder skalierungsbezogenen Blocker ergibt, werden genau diese Optionen gegeneinander bewertet:

1. Name beibehalten und eine besonders eigenständige kombinierte Wort-/Bildmarke aufbauen;
2. `Bocholt erleben` als beschreibenden Produktnamen unter einer schutzfähigeren Absendermarke führen;
3. den Produktnamen bewusst ändern und Domain-, SEO-, Kommunikations- und Migrationsfolgen separat planen;
4. bei realer institutioneller Kooperation eine vertraglich definierte Co-Branding-Architektur entwickeln.

Ohne solchen Blocker bleibt der Name erhalten. Eine Umbenennung ist kein kreativer Selbstzweck.

## 6. Vollständige Optionsbewertung

| Option | Einordnung | Entscheidung |
|---|---|---|
| Aktuelle Identität unverändert lassen | minimales Risiko, aber bestehender Qualitätsabstand bleibt | verbindlicher Zwischenzustand bis zur vollständigen Abnahme |
| Nur das aktuelle 3D-B modernisieren | geringe Umstellung, kann aber eine generische Altidee konservieren | als Designerexploration zulässig, nicht als Vorgabe |
| Reine individuelle Wortmarke | hohe Lesbarkeit, geringer Klischeedruck, guter Header-Fit | verpflichtender Konzeptweg |
| Wortmarke plus abgeleitetes kompaktes Zeichen | verbindet Markenstärke und App-Tauglichkeit | bevorzugte Zielarchitektur, sofern das Zeichen echten Mehrwert besitzt |
| Symbol-first oder erzwungene B/e-Ligatur | führt leicht zu generischen, technischen oder dekorativen Zeichen | nicht vorgeben; nur bei außergewöhnlich guter Lösung zulässig |
| Vollständige Umbenennung | höhere Schutz- oder Skalierungschance, aber hohe Migrations- und SEO-Kosten | nur bei belegtem Namensblocker |
| Beschreibender Produktname plus proprietäre Absendermarke | kann Schutz und Expansion erleichtern, erhöht aber Komplexität | bedingte Alternative bei Namens- oder Skalierungsproblem |
| Formales Co-Branding mit Stadt oder Stadtmarketing | kann Vertrauen und Reichweite stärken, erzeugt Abhängigkeit und Governancebedarf | nur nach ausdrücklicher institutioneller Vereinbarung |
| Seniorer unabhängiger Identity-Designer | direkte Zusammenarbeit, gute Kosteneffizienz, ausreichend für klar begrenzten Scope | empfohlener Standardweg |
| Kleines spezialisiertes Identity-Studio | breitere Qualitätssicherung und Vertretung, höherer Aufwand | bevorzugte Alternative bei ausreichendem Budget oder größerem Systemumfang |
| Allgemeine Web-/Marketingagentur | kann Integration mitdenken, besitzt aber nicht automatisch Markendesign-Handwerk | nur bei belegtem Identity-, Typografie- und App-Icon-Portfolio |
| Lokaler Designer allein wegen Ortsnähe | lokales Verständnis ist nützlich, ersetzt aber keine Fachqualität | Herkunft ist Zusatzkriterium, kein Auswahlgrund |
| Marketplace-Freelancer | kann gute Einzelpersonen enthalten, Plattform garantiert aber keine Qualität | nur als Suchkanal mit vollständiger Scorecard |
| Offener Logo-Wettbewerb oder Crowdsourcing | viele schnelle Vorschläge, aber flache Recherche, unklare Herkunft und Spec-Work-Risiko | ausgeschlossen |
| Unbezahlter Pitch mehrerer Anbieter | verschiebt Risiko auf Designer und fördert oberflächliche Lösungen | ausgeschlossen |
| Bezahlter Mini-Test mehrerer Anbieter | fairer, aber teuer und koordinationsintensiv | nur als begründete Ausnahme, nicht als Standard |
| Designschule oder offene Community-Challenge | kann Perspektiven liefern, aber keine verlässliche Produktionsverantwortung | nur Forschung, nicht finaler Owner |
| Stock-, Template- oder Logo-Generator-Lösung | schnell und günstig, aber austauschbar und schwer exklusiv zu sichern | ausgeschlossen |
| AI-only-Produktion | gut für Exploration, nicht verlässlich für Originalität, Typografie, Geometrie und Rechtekette | als Finalproduktion ausgeschlossen |
| AI-gestützte Arbeit eines verantwortlichen Designers | kann Recherche und Varianten beschleunigen, wenn Ursprung und Rechte transparent bleiben | zulässig mit Offenlegung und vollständig kontrolliertem Finalmaster |
| Getrennte Spezialisten für Lettering und Produktdesign | potenziell höchste Fachqualität, aber mehr Koordination und Schnittstellenrisiko | Fallback, wenn ein Lead-Designer den Gesamtumfang nicht abdeckt |

## 7. Verbindlicher Beschaffungsweg

### 7.1 Standardroute

Der Standardweg ist ein **erfahrener unabhängiger Identity-Designer** als eindeutiger fachlicher Lead. Ein kleines spezialisiertes Studio wird gewählt, wenn:

- der gewünschte Umfang über eine kompakte digitale Identität hinausgeht;
- parallele Qualitätssicherung oder Vertretung notwendig ist;
- Naming, Strategie, Typedesign und umfangreiche Anwendungen aus einer Hand benötigt werden;
- das freigegebene Budget den zusätzlichen Umfang rechtfertigt.

### 7.2 Auswahl ohne Spec Work

Ablauf:

1. acht bis zwölf passende Portfolios recherchieren;
2. drei bis fünf Anbieter anhand der Scorecard vorprüfen;
3. mit höchstens drei Finalisten ein kurzes Gespräch zu Prozess, Scope, Rechten, Terminen und Zusammenarbeit führen;
4. schriftliche Angebote auf dieselbe Leistungsbeschreibung beziehen;
5. genau einen Anbieter beauftragen;
6. nur bei verbleibender begründeter Unsicherheit einen bezahlten Diagnose- oder Discovery-Baustein mit dem bevorzugten Anbieter vorschalten.

Es werden keine kostenlosen, eigens für Bocholt erleben angefertigten Entwürfe verlangt. Vorhandene Arbeiten, Prozessqualität, Referenzen und ein klares Angebot müssen für die Auswahl genügen.

### 7.3 Anbieter-Scorecard

| Kriterium | Gewicht |
|---|---:|
| nachweisbare Qualität eigenständiger Markenidentitäten | 25 |
| digitale Produkt- und App-Icon-Erfahrung | 20 |
| Typografie, Lettering und Wortmarkenhandwerk | 15 |
| Fähigkeit, aus dem Markenkern ein konsistentes System zu entwickeln | 15 |
| nachvollziehbarer Prozess mit interner Divergenz und klarer Kuration | 10 |
| Rechte-, Lizenz-, Provenienz- und Source-File-Kompetenz | 10 |
| verständliche Zusammenarbeit und lokaler Kontexttransfer | 5 |

Mindestwert: 75 von 100 Punkten und kein Knock-out.

Knock-out-Kriterien:

- kein einschlägiges Portfolio;
- nur dekorative Mock-ups ohne belastbare Logosysteme oder Kleingrößen;
- kostenlose Entwürfe als Auswahlbedingung;
- Stock-, Template- oder undeklarierte AI-Abhängigkeit;
- unklare Urheber-, Nutzungs- oder Exklusivrechte;
- keine editierbaren Source-Dateien;
- kein Nachweis für responsive Marken, Wortmarken oder App-Icons;
- keine Bereitschaft, reale Produktkontexte und technische Anforderungen zu prüfen;
- pauschaler Vorschlag einer vollständigen Website-Neugestaltung ohne Markenkern und Owneranalyse.

## 8. Verbindliches Designerbriefing

Das Briefing enthält mindestens:

- Produktauftrag, Nutzer- und Anbieterwert;
- reale Zielgruppen und Nutzungssituationen;
- bestehende Informationsarchitektur und aktuelle Screens;
- verbindliche Markenpersönlichkeit und ausgeschlossene Kategorien;
- klare Abgrenzung zur Stadt Bocholt und zum Stadtmarketing;
- reale deutsche Inhalte und typische lange Textfälle;
- aktuelle Farben und Komponenten als Ausgangs-DNA, nicht als unveränderliche Designvorgabe;
- Pflichtkontexte für Header, App-Icon, Today, Events, Aktivitäten, Detail und Anbieter-/Vertrauensweg;
- Accessibility-, PWA-, Kleingrößen-, Responsive- und Performanceanforderungen;
- verbindliche Liefer-, Rechte- und Provenienzanforderungen;
- genau einen Product Owner als konsolidierten Feedbackgeber.

Nicht Teil des ersten Auftrags sind ohne gesonderte Freigabe:

- komplette Neugestaltung aller Seiten;
- neues Framework oder Designsystem-Rewrite;
- Merchandising, Fahrzeugbeklebung oder umfangreiche Printkampagnen;
- Social-Media-Templatepakete;
- laufende Contentproduktion;
- Naming, falls der Name-Gate keinen Blocker ergibt.

## 9. Designprozess ohne Nutzer-Try-and-Error

### Phase 0 – Freeze

- Aktuelle öffentliche Identität bleibt unverändert.
- Bisherige AI- und Codeentwürfe werden nicht als Designvorlage übergeben.
- Verwertet werden nur strategische Erkenntnisse, Ausschlüsse und technische Anforderungen.

### Phase 1 – Name, Absender und Schutz-Preflight

- bestehende Namensnutzung und offizielle Stadtmarken-Abgrenzung prüfen;
- relevante Markenregister und Waren-/Dienstleistungsbereiche vorprüfen;
- Standardannahme `Name bleibt` bestätigen oder einen echten Blocker dokumentieren;
- nur bei Blocker eine separate Namensentscheidung auslösen.

### Phase 2 – Anbieter auswählen

- Portfolios und Angebote nach einer Scorecard bewerten;
- genau einen fachlichen Lead beauftragen;
- Scope, Meilensteine, Feedbackweg, Abbruchregel, Rechte und Lieferformat vertraglich festhalten.

### Phase 3 – Interne breite Entwicklung

Der Designer recherchiert, skizziert, konstruiert und verwirft intern. Weder Product Owner noch Repository erhalten laufend Rohentwürfe.

Verbindlich zu untersuchen sind mindestens:

- reine individuelle Wortmarke;
- Wortmarke mit abgeleitetem kompaktem Zeichen;
- typografischer App-Icon-Ausschnitt oder Monogramm;
- eine mögliche Weiterentwicklung der bestehenden B-Erkennung, ohne sie zu erzwingen;
- unterschiedliche Grade von Wärme und Präzision;
- monochrome und sehr kleine Fassungen von Beginn an.

### Phase 4 – Genau zwei kuratierte Konzepte

Der Product Owner erhält höchstens zwei Konzepte. Mindestens eines ist klar wortmarkengeführt. Jedes Konzept muss bereits zeigen:

- Markenidee in einem klaren Satz;
- Wortmarke und gegebenenfalls Zeichen;
- horizontale und kompakte Kombination;
- Schwarz-Weiß-Fassung;
- reale Darstellung bei 16, 24, 32 und 48 Pixeln;
- App-Icon in Kreis-, Squircle- und Maskable-Kontext;
- echten mobilen Header;
- eine Today-/Event-Anwendung mit realem Inhalt;
- einen Anbieter- oder Vertrauenskontext;
- begründete Abgrenzung zu Verwaltung, Tourismus, Karten-App und generischer Tech-Marke;
- erste visuelle Ähnlichkeits- und Provenienzprüfung.

Ein Konzept wird nicht präsentiert, wenn es ein Knock-out-Kriterium verletzt. Bestehen nicht zwei Konzepte die Qualitätsgrenze, arbeitet der Designer intern weiter oder der Auftrag wird nach vertraglicher Abbruchregel beendet. Es wird kein „bester schwacher Kandidat“ zur Auswahl gestellt.

### Phase 5 – Eine Richtungsentscheidung

Der Product Owner wählt genau eine Grundrichtung und nennt gebündelt höchstens die wesentlichen Störpunkte. Detailentscheidungen zu Kurven, Kerning, optischer Korrektur und Produktionsgeometrie verbleiben beim Designer.

Keine neue Grundrichtung wird nach Auswahl eröffnet, außer ein rechtlicher oder nachweisbarer Verwechslungsblocker macht sie unbrauchbar.

### Phase 6 – Gebündelte Verfeinerung

Nur die gewählte Richtung wird finalisiert:

- Wortmarkenzeichnung, Kerning und optische Größen;
- Zeichen- und App-Icon-Geometrie;
- responsive Lockups;
- monochrome, inverse und kleine Fassungen;
- Farb- und Typografiesystem;
- Bild- und Bewegungsprinzip;
- reale Produktanwendung;
- technische und rechtliche Vorbereitung.

Der Product Owner erhält eine konsolidierte Korrekturrunde und anschließend den Finalstand. Interne handwerkliche Revisionen des Designers zählen nicht als Nutzeriteration.

### Phase 7 – Rechts- und Produktionsfreigabe

Vor öffentlicher Nutzung:

- professionelle Prüfung von Wort-, Bild- und kombinierter Marke;
- bei Bildbestandteilen geeignete Bild- und Ähnlichkeitssuche;
- relevante Waren-/Dienstleistungsklassen fachlich bestimmen;
- finalen Rechte-, Font-, Asset- und AI-/Provenienznachweis prüfen;
- erst danach finale Master abnehmen oder eine Anmeldung vorbereiten.

### Phase 8 – Separater Repository-Workpack

Erst nach vollständiger Asset- und Rechteabnahme wird ein Implementierungs-Workpack aktiviert. Dieser integriert die Marke token-first in bestehende Owner und verändert nicht automatisch Informationsarchitektur oder Produktlogik.

## 10. Begrenzte Nutzerentscheidungen

Der Product Owner trifft höchstens drei Entscheidungen:

1. **Route und Budget:** Solo-Spezialist oder kleines Spezialstudio; eine Namensentscheidung nur bei belegtem Blocker.
2. **Markenrichtung:** eine Auswahl aus höchstens zwei qualifizierten Konzepten.
3. **Staging-Endfreigabe:** finale Marke im echten Produkt auf Smartphone und Desktop.

Keine öffentliche Abstimmung und kein Design-by-Committee. Eine kleine Nutzerprüfung darf objektive Fehlassoziationen erkennen, entscheidet aber nicht per Mehrheitsgeschmack über das Logo.

## 11. Objektive Konzept-Scorecard

| Kriterium | Gewicht |
|---|---:|
| Produkt- und Markenpassung | 20 |
| Eigenständigkeit und potenzielle Schutzfähigkeit | 20 |
| Wortmarkenqualität, Lesbarkeit und typografisches Handwerk | 15 |
| App-Icon, Maskierung und Kleingrößenfestigkeit | 15 |
| Erweiterbarkeit zu einem responsiven Markensystem | 15 |
| Integration in reales digitales Produkt und Accessibility | 10 |
| technische Produktions- und Rechtequalität | 5 |

Mindestwert: 75 von 100 Punkten und kein Knock-out.

Konzept-Knock-outs:

- falsche Kategorieassoziation zu Verwaltung, Tourismusbroschüre, Karten-App oder generischer Tech-Marke;
- unlesbare oder nicht unterscheidbare Form bei 24 Pixeln;
- keine belastbare einfarbige Fassung;
- entscheidende Details liegen außerhalb der PWA-Maskable-Safe-Zone;
- Zeichen und Wortmarke besitzen kein gemeinsames System;
- offenkundige visuelle Nähe zu vorhandenen Marken;
- nicht belegbare Herkunft oder Rechte;
- benötigte Fonts oder Assets sind nicht rechtssicher webfähig;
- Marke funktioniert nur im präsentierten Hochglanz-Mock-up, nicht in realen Produktkontexten.

## 12. Gezielte Validierung statt Geschmacksabstimmung

Vor der Finalisierung werden folgende Fragen geprüft:

- Wird die Plattform als eigenständig und nicht amtlich verstanden?
- Werden Ruhe, lokale Relevanz, Vertrauen und moderne Nutzbarkeit wahrgenommen?
- Entsteht eine falsche Tourismus-, Routen-, Bank-, SaaS- oder Lifestyle-Assoziation?
- Ist die Wortmarke schnell lesbar?
- Ist das App-Icon ohne App-Namen wiedererkennbar und unter unterschiedlichen Masken stabil?
- bleibt die Marke in monochromer und thematisierter Darstellung erkennbar?

Eine kleine, gezielte Fehlassoziationsprüfung mit repräsentativen Nutzern ist zulässig. Sie ist kein Popularitätsvoting. Bei eindeutigen Fehlassoziationen wird intern korrigiert; bei bloßer Geschmacksverteilung bleibt die fachlich ausgewählte Richtung bestehen.

## 13. Pflichtliefergegenstände

### 13.1 Markenmaster

- individuelle Wortmarke;
- optionales Markenzeichen, falls fachlich begründet;
- horizontale, kompakte und gegebenenfalls gestapelte Lockups;
- optisch korrigierte Kleinversion;
- monochrome, inverse und Graustufenfassung;
- App-Icon-Master und monochrome Kernform;
- definierte Schutzräume, Mindestgrößen und Fehlanwendungen;
- farbverbindliche Werte und zugängliche Anwendungsrollen;
- Typografie- und Fallback-Empfehlung;
- Bildsprache, Zuschnitt und ein zurückhaltendes Bewegungsprinzip;
- kurze Tonalitäts- und Copy-Beispiele.

### 13.2 Dateiformate

- editierbare native Source-Dateien;
- saubere SVG-Master ohne unnötige Rasterbestandteile;
- druckfähige PDF- beziehungsweise Vektorfassung;
- PNG-Exporte für definierte Vorschauzwecke;
- schwarz-weiße Artwork-Datei für Markenprüfung oder Anmeldung;
- dokumentierte Farbprofile und Exportregeln.

Plattformspezifische Rasterderivate werden im Implementierungs-Workpack deterministisch aus den freigegebenen Mastern erzeugt, nicht manuell in vielen abweichenden Varianten gepflegt.

### 13.3 Rechte- und Lizenzpaket

Schriftlich geklärt werden:

- exklusive beziehungsweise ausreichend umfassende Nutzungsrechte am finalen Markensystem;
- Bearbeitungs-, Vervielfältigungs-, Online-, App-, Social-, Print- und zukünftige Produktnutzung;
- Übergabe der Source-Dateien;
- Rechte an individuell gezeichneter Typografie;
- vollständige Liste aller Fonts, Stock- oder Drittassets und ihrer Lizenzen;
- Offenlegung verwendeter generativer Werkzeuge und Herkunft relevanter Bestandteile;
- Zusicherung, dass keine Stock- oder Template-Marke als exklusiver Finalmaster ausgegeben wird;
- Regelung zu unveröffentlichten und verworfenen Konzepten;
- keine Wiederverwendung des finalen unverwechselbaren Markenmasters für andere Kunden.

Die konkrete Vertragsformulierung wird bei Bedarf juristisch geprüft.

## 14. Technische Abnahmekriterien vor Integration

### 14.1 Zeichen und App-Icon

- klar bei 16, 24, 32 und 48 CSS-Pixeln;
- stabil als Favicon, PWA-Icon, Apple-Touch-Icon und maskable Icon;
- alle wesentlichen Inhalte innerhalb der W3C-Maskable-Safe-Zone;
- funktioniert unter Kreis-, Squircle- und abgerundeten Masken;
- brauchbare monochrome beziehungsweise thematisierbare Kernform;
- keine extrem dünnen Linien oder nur in Großansicht erkennbare Details;
- keine vorgerenderten Masken in den Masterebenen.

### 14.2 Wortmarke und Typografie

- lesbar im realen mobilen Header;
- responsive kompakte Fassung ohne Verlust der Identität;
- sauberes Kerning und optische Korrektur;
- Webfont-Lizenz und Self-Hosting geklärt;
- begrenzte notwendige Schnitte und vertretbares Ladebudget;
- robuste Fallbacks ohne kritische Layoutverschiebung;
- Textvergrößerung und deutsche Sonderzeichen funktionieren.

### 14.3 Produktanwendung

Prüfkontexte:

- Today mobil `390x844`;
- Events Desktop `1440x900`;
- Aktivitäten mobil;
- Detailansicht mobil;
- Anbieter-/Vertrauensweg;
- PWA-Installations- und Homescreen-Kontext;
- Social-/Avatar-Kontext.

Die Anwendung muss reale Komponenten und reale deutsche Inhaltslängen verwenden. Keine Freigabe allein auf Basis dekorativer Mock-ups.

### 14.4 Accessibility und Technik

- WCAG-konforme Kontraste für funktionale Farbrollen;
- sichtbare Fokuszustände bleiben erhalten;
- `prefers-reduced-motion` wird respektiert;
- keine horizontale Überbreite oder relevante Layoutverschiebung;
- Theme-Color, Manifest, Favicons, Apple-Touch- und PWA-Assets sind konsistent;
- Service-Worker- und Cache-Cutover sind geordnet;
- bestehende Lucide- und Interaktionskonventionen werden nicht ohne belegten Grund ersetzt.

## 15. Spätere Repository-Integration

Die Integration erhält einen eigenen Workpack mit eingefrorenem Scope. Voraussichtlich betroffen sind nur die tatsächlich owning Pfade für:

- Markenmaster und deterministisch erzeugte Icon-Derivate;
- Header- und Subpage-Lockups;
- globale Brand-, Farb- und Typografietokens;
- Font-Assets und Ladeverhalten;
- Manifest, Theme-Color, Favicons und Apple-Touch-Icon;
- optional ein einziges charakteristisches Bewegungsprinzip;
- Browser-, Accessibility-, PWA-, Cache- und Screenshotverträge.

Nicht automatisch betroffen:

- Produktlogik und Tarife;
- Event-/Activity-Daten;
- Informationsarchitektur;
- Lucide-Funktionsicons;
- Control Center, APIs, Datenbanken oder Workflows;
- allgemeine CSS-/JS-Sanierung.

Der Preview- und Abnahmezustand verwendet die echten Komponenten und Inhalte. Ein separater Theme- oder Query-Preview darf genutzt werden, sofern er keine zweite dauerhafte UI-Logik erzeugt. Nach Auswahl bleiben keine parallelen Markenvarianten im Produktivcode.

## 16. Abbruch-, Fallback- und Qualitätsregeln

- Erreicht kein Anbieter die Mindestscorecard, wird nicht beauftragt.
- Erreicht kein Konzept die Mindestqualität, wird keine Nutzerentscheidung erzwungen.
- Verursacht die Namensprüfung einen harten Blocker, stoppt die visuelle Ausarbeitung bis zur Namensentscheidung.
- Scheitert der beauftragte Anbieter fachlich oder prozessual, greift die vertragliche Meilenstein- und Abbruchregel; schwache Arbeit wird nicht durch weitere UI-Mock-ups kaschiert.
- Solange kein vollständiger Finalmaster vorliegt, bleibt die aktuelle Identität aktiv.
- Eine verzögerte gute Marke ist einem schnellen, erneuten Zwischenlogo vorzuziehen.
- Nach finaler Auswahl werden keine alternativen Markensysteme dauerhaft im Repository gehalten.
- Ein späterer Rebrand öffnet dieses Ziel nicht erneut, solange keine neue belegte strategische, rechtliche oder technische Ursache vorliegt.

## 17. Ergebnis dieses Zielvertrags

Der verbindliche Weg lautet:

```text
aktuellen öffentlichen Stand einfrieren
-> Name, Absender, Schutz- und Stadtmarken-Abgrenzung einmalig prüfen
-> einen qualifizierten Identity-Spezialisten auswählen
-> breite interne Entwicklung ohne Nutzer-Rohschleifen
-> höchstens zwei belastbare Konzepte
-> genau eine Richtungsentscheidung
-> gebündelte Verfeinerung und professionelle Rechtsprüfung
-> vollständige Asset-, Rechte- und Lizenzabnahme
-> separater Repository-Integrationsworkpack
-> genau eine Staging-Endabnahme
-> kontrollierter Release
```

Damit werden notwendige kreative Iterationen nicht geleugnet, sondern fachlich dorthin verlagert, wo sie hingehören: intern zum verantwortlichen Designer. Der Product Owner entscheidet nur an klaren Gates über Route, Richtung und fertige Produktintegration.

## 18. Referenzen

Interne Grundlagen:

- `MASTER.md`;
- `Produktvertrag.md`;
- `ROADMAP.md`;
- `ENGINEERING.md`;
- `docs/architecture/SYSTEM_MAP.md`;
- aktuelle öffentliche Today-, Event-, Activity-, Detail- und Anbieteroberflächen.

Externe Primär- und Fachreferenzen, abgerufen am 2026-07-27:

- Stadt Bocholt, Stadtmarke: <https://www.bocholt.de/stadtmarke>
- Design Council, Double Diamond: <https://www.designcouncil.org.uk/resources/the-double-diamond/>
- Apple Human Interface Guidelines, Branding: <https://developer.apple.com/design/human-interface-guidelines/branding>
- Apple Human Interface Guidelines, App icons: <https://developer.apple.com/design/human-interface-guidelines/app-icons/>
- Android, Adaptive Icons: <https://developer.android.com/develop/ui/compose/system/icon_design_adaptive>
- W3C, Web Application Manifest – Icon masks and safe zone: <https://www.w3.org/TR/appmanifest/#icon-masks>
- DPMA, Markenrecherche: <https://www.dpma.de/marken/markenrecherche/index.html>
- EUIPO, Wiener Klassifikation für Bildmarken: <https://www.euipo.europa.eu/de/help-centre/searches/faq-vienna-classification>
- WIPO, Nice Classification: <https://www.wipo.int/classifications/nice/>
- AIGA, Auswahl ohne unbezahlte Entwürfe: <https://losangeles.aiga.org/call-for-designers-design-the-identity-for-the-2025-aiga-national-conference/>
