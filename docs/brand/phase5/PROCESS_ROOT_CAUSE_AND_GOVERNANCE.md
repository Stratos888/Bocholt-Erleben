# Phase 5 – Prozessursache und Governance-Auflösung

Stand: 2026-08-01
Workpack: #259
Status: verbindlich

## 1. Warum Phase 4 trotz vorhandener Dokumentation scheiterte

Die Repository-Dokumentation enthielt bereits viele richtige Qualitätsprinzipien. Das Scheitern entstand aus einer Kombination aus Dokumentwidersprüchen, unvollständiger Operationalisierung und Ausführungsfehlern.

### 1.1 Widersprüchliche Freigaberegeln

Der ältere `BRAND_IDENTITY_TARGET_CONTRACT.md` nennt:

- Mindestwert `78/100`;
- höchstens drei Richtungen für das Product-Owner-Gate.

Das spätere `AI_BRAND_PHASE3_EVALUATION_FRAMEWORK.md` nennt:

- Mindestwert `90/100`;
- verpflichtende Dimensionsmindestwerte;
- übereinstimmende Freigabe beider Blindkritiker;
- keinen ungelösten Red-Team-Befund;
- höchstens zwei Richtungen.

Diese Regeln waren nicht ausdrücklich hierarchisiert. Dadurch bestand Interpretationsspielraum, obwohl der spätere Rahmen erkennbar die strengere Prozessreparatur darstellt.

### 1.2 Phase 4 war nur strukturelle Exploration

`docs/brand/phase4/README.md` begrenzt Phase 4 ausdrücklich auf:

- keine Scores;
- keine Rangfolge;
- keine Finalisten;
- keine Freigabe;
- strukturelle SVG-Exploration statt Produktionsmaster.

Die erzeugten Systeme waren daher Prüfrohstoff, keine Premiumkandidaten. Sie wurden im weiteren Ablauf dennoch wie eine nahezu vollständige Markenrunde behandelt.

### 1.3 Die kreative Erzeugung war zu schwach

Die Phase-4-Systeme bestanden überwiegend aus einfachen geometrischen Primitiven, bekannten Symbolschemata und Standardtypografie. Der Prüfprozess erkannte viele dieser Schwächen korrekt, setzte aber erst ein, nachdem bereits zu viel Aufwand in schwache Grundideen geflossen war.

Eine strengere Bewertung kann schlechte Gestaltung beenden, aber nicht nachträglich in Premiumgestaltung verwandeln.

### 1.4 Die Scorecard war nicht Teil der Blindaufträge

Der Konsolidierungsauftrag verlangte eine Phase-3-Scorecard. Die tatsächlichen Kritikaufträge lieferten jedoch qualitative Knock-out-Urteile ohne vollständige Dimensionswerte.

Später rekonstruierte Punktwerte waren keine unabhängige Blind-Evidenz und dürfen nicht als solche gespeichert oder zur formalen Freigabe verwendet werden.

### 1.5 Rollenunabhängigkeit wurde überschätzt

Getrennte Prompts und getrennte Sichtflächen verbessern die Disziplin. Sie erzeugen aber keine vollständig unabhängigen menschlichen Gutachter, wenn derselbe ChatGPT-Orchestrator Produktion, Kritik und Konsolidierung ausführt oder bereits alle Ergebnisse kennt.

Der Prozess darf künftig nur von getrennten Bewertungspässen sprechen, nicht von unabhängiger externer Begutachtung.

### 1.6 Nutzerinteraktion wurde verletzt

Der Product Owner sollte keine Rohideen, Mikrovarianten oder internen Berichtsgrafiken erhalten. Die Ausgabe einer unbestellten Prüfberichtgrafik verletzte diese Regel und hatte keinen gestalterischen Nutzen.

### 1.7 Das Gegenframework übersteuerte bei Wortmarken

Die frühe Prozessreparatur behandelte Genericity, Austauschbarkeit, Silhouette, Produktspezifität und App-Icon architekturunabhängig.

Das war für generische Symbole und einzelne Schriftgimmicks sinnvoll, erzeugte bei einer Wortmarkenarchitektur aber neue Fehlanreize:

- hochwertige Wortmarken wurden wegen fehlender wörtlicher Produktsymbolik benachteiligt;
- ein freistehendes Zeichen und App-Icon wurden zu früh erzwungen;
- systemische Typografie wurde wie Standardschrift plus Detail behandelt;
- harte Austauschbarkeitsregeln förderten überzeichnete Buchstabenmetaphern statt souveräner Markenführung.

Die Korrektur steht in `ARCHITECTURE_AWARE_EVALUATION.md` und senkt den Premiumanspruch ausdrücklich nicht.

### 1.8 Interne Erzeugungsgrenze

Phase 5 hat mehrere Architekturklassen real konstruiert und anschließend einen begrenzten generativen Suchzyklus über vier offene Formfelder durchgeführt.

Der wiederholte Befund ist stabil:

- prozedural erzeugte Letteringvarianten bleiben bekannte Stilformeln oder wirken dekorativ verändert;
- abstrakte Formen kippen in UI, Mode, Handwerk, Publishing oder andere falsche Kategorien;
- lokale Strukturideen benötigen zu viel Erklärung;
- technisch saubere Initialableitungen bleiben austauschbar;
- keine der zwölf ernsthaften Rohhypothesen aus dem generativen Zyklus rechtfertigte eine deterministische Rekonstruktion.

Die Grenze ist nicht fehlende Prüftiefe, sondern fehlende originäre Creative-Director-/Lettering-Qualität mit den aktuell verfügbaren internen Erzeugungswegen.

## 2. Verbindliche Dokumenthierarchie

Für Workpack #259 gilt folgende Reihenfolge:

1. `docs/brand/phase5/PROCESS_ROOT_CAUSE_AND_GOVERNANCE.md`
2. `docs/brand/phase5/ARCHITECTURE_AWARE_EVALUATION.md`
3. `docs/brand/AI_BRAND_PHASE3_EVALUATION_FRAMEWORK.md`
4. `docs/brand/phase5/README.md`
5. `docs/brand/phase5/GENERATIVE_SEARCH_CYCLE_01_RESULT.md`
6. `docs/brand/phase5/EXTERNAL_CREATIVE_EXECUTION_BRIEF.md`
7. weitere Phase-5-Such- und Benchmarkdateien
8. `docs/brand/BRAND_IDENTITY_TARGET_CONTRACT.md`
9. Phase-4-Dokumente ausschließlich als Negativwissen

Bei Konflikten gilt die höher stehende Datei.

Damit sind verbindlich:

- Freigabeschwelle: mindestens `90/100`;
- alle Dimensionsmindestwerte müssen erreicht sein;
- kein architekturabhängig belegter Knock-out;
- zwei getrennte positive Bewertungspässe;
- kein ungelöster Red-Team-Befund;
- höchstens zwei Richtungen im Product-Owner-Gate;
- App-Icon und Kleinmarke erst nach bestandener vollständiger Primäridentität.

Die ältere 78-Punkte-Schwelle und die Grenze von drei Richtungen sind für Phase 5 außer Kraft.

## 3. Verbindliche Prozesssperren

1. Keine Quantitätsziele als Qualitätsersatz.
2. Keine Standardschrift plus dekoratives Einzelmerkmal.
3. Kein App-Icon vor überzeugender vollständiger Primäridentität.
4. Keine Speicherung rekonstruierter Scores als formale Blind-Evidenz.
5. Keine Behauptung unabhängiger Prüfer bei identischer Orchestrierung.
6. Keine Nutzerpräsentation von Rohideen, Prüfboards oder internen Berichtsgrafiken.
7. Keine öffentliche oder produktive Markenänderung vor vollständiger Qualifikation und separatem Integrationsworkpack.
8. Keine architekturblinde Anwendung von Knock-outs.
9. Keine Wiederholung der im Studienlog geschlossenen Formfamilien.
10. Keine weitere rein prozedurale automatische Variantenrunde nach dem abgeschlossenen generativen Suchzyklus 01.
11. Kein externer Entwurf wird wegen Präsentationsqualität bevorzugt; bewertet wird das sichtbare Markensystem in identischen realen Anwendungen.

## 4. Korrigierter Qualitätsweg

### Geschlossene interne Strecke

1. Produktkern und emotionale Markenspannung präzisieren.
2. Premium- und Kategoriebenchmarks dokumentieren.
3. mehrere Architekturklassen untersuchen.
4. Wortmarken-, integrierte und responsive Studien real konstruieren.
5. Ablations-, Originalgrößen- und Kategorieprüfungen durchführen.
6. generativen Suchraum über vier begrenzte Felder prüfen.
7. alle nicht tragfähigen Richtungen ohne Nutzerpräsentation beenden.

Diese Strecke ist abgeschlossen mit:

- `44` ernsthaften internen Studien beziehungsweise Rohhypothesen über beide Phase-5-Zyklen;
- `0` Kandidaten;
- `0` qualifizierten Richtungen;
- `0` öffentlichen Änderungen.

### Nächste Strecke

1. originäre externe Creative-Director-/Lettering-Leistung gemäß `EXTERNAL_CREATIVE_EXECUTION_BRIEF.md`;
2. Eingangskontrolle und Provenienzprüfung;
3. Anonymisierung und identische Originalgrößen-Prüfsets;
4. architekturabhängige Knock-outs;
5. neutrales Marken-Minisystem;
6. responsive Kleinform und App-Marke;
7. zwei getrennte Bewertungspässe;
8. Red-Team-Pass;
9. Scorecard und technische Prüfung;
10. höchstens zwei vollständig qualifizierte Richtungen für den Product Owner;
11. separate Finalisierung und Repository-Integration.

## 5. Definition von Fortschritt

Fortschritt ist nicht:

- Anzahl erzeugter Varianten;
- Anzahl SVG-Dateien;
- grüne CI;
- formal vollständige Prüftabellen;
- ein Kandidat ohne harten Knock-out;
- ein wörtlich erklärbares App-Symbol;
- eine professionelle, aber austauschbare Fontsetzung;
- ein technisch stabiles Initialmonogramm.

Fortschritt ist erst belegt, wenn eine vollständige Primäridentität:

- sichtbar eigenständig ist;
- klar zum Produkt und zur gewünschten Haltung passt;
- ohne Erklärung hochwertig wirkt;
- die aktuelle Identität deutlich übertrifft;
- als Wortmarke und im realen mobilen Header überzeugt;
- ein kohärentes Typografie-, Farb-, Bild- und Bewegungsverhalten tragen kann;
- anschließend eine verwandte belastbare Kleinform ermöglicht;
- vollständig nachvollziehbar konstruiert und lizenziert ist.
