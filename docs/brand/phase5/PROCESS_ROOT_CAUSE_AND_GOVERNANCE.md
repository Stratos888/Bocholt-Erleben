# Phase 5 – Prozessursache und Governance-Auflösung

Stand: 2026-08-17
Workpack: #259
Status: verbindlich

## 1. Warum die bisherigen Schleifen trotz vorhandener Dokumentation scheiterten

Die Repository-Dokumentation enthielt viele richtige Qualitätsprinzipien. Das Scheitern entstand aus Dokumentwidersprüchen, unvollständiger Operationalisierung, zu schwacher kreativer Erzeugung und einem zu spät eingebundenen realen visuellen Product-Owner-Gate.

### 1.1 Widersprüchliche Freigaberegeln

Der ältere `BRAND_IDENTITY_TARGET_CONTRACT.md` nannte niedrigere Schwellen als das spätere `AI_BRAND_PHASE3_EVALUATION_FRAMEWORK.md`. Für Phase 5 bleibt die strengere spätere Governance maßgeblich.

### 1.2 Phase 4 war nur strukturelle Exploration

Phase 4 erzeugte Prüfrohstoff und keine Premiumkandidaten. Spätere Prozesse behandelten diese Studien teilweise zu nah an einer vollständigen Markenrunde.

### 1.3 Die kreative Erzeugung war zu schwach

Viele frühe Systeme bestanden aus einfachen geometrischen Primitiven, bekannten Symbolschemata, Standardtypografie oder `Font + Gimmick`. Strenge Bewertung kann solche Gestaltung beenden, aber nicht nachträglich in Premiumgestaltung verwandeln.

### 1.4 Rollenunabhängigkeit wurde überschätzt

Getrennte Prompts und Sichtflächen verbessern Disziplin, erzeugen aber keine wirklich unabhängigen Gutachter, wenn derselbe AI-Orchestrator Produktion, Kritik und Konsolidierung kennt.

### 1.5 Der Product Owner wurde visuell zu spät eingebunden

Die frühere Governance wollte den Product Owner vor Rohideen schützen. Das war richtig, wurde aber überzogen: Eine tatsächliche visuelle Richtungsentscheidung erfolgte erst, nachdem einzelne Identitäten bereits weit ausgearbeitet und intern bewertet waren.

Candidate 201 belegt das Problem konkret:

- intern zweimal `90/100`;
- intern als designqualifiziert geführt;
- später in tatsächlicher Product-Owner-Sichtung und erneuter visueller Bewertung klar nicht als Premium-Marke bestätigt.

Damit ist belegt, dass interne Scorecards keinen menschlichen visuellen Richtungsentscheid ersetzen dürfen.

### 1.6 Generatoren wurden mit der falschen Aufgabe betraut

Zusätzliche Tests mit Recraft und Ideogram bestätigten typische Fehlmuster:

- Recraft: `Font + Gimmick`, insbesondere Schnitte und dekorative Eingriffe;
- Ideogram: saubere, aber generische Fontsetzungen ohne ausreichende Identität.

Logo-Generatoren werden deshalb nicht weiter als Primärerzeuger eingesetzt.

### 1.7 Textliche Benchmarks waren zu wenig visuell operationalisiert

Premiumreferenzen wurden inhaltlich korrekt analysiert, aber ihre sichtbare Grammatik – Dichte, x-Höhenwirkung, Proportion, Terminalcharakter, Weißraum, Bildnähe und Consumer-Markenwirkung – wurde nicht früh genug als visuelle Richtungsentscheidung übersetzt.

## 2. Verbindliche Dokumenthierarchie

Für Workpack #259 gilt ab 2026-08-17:

1. `docs/brand/phase5/VISUAL_DIRECTION_GATE.md`
2. `docs/brand/phase5/PROCESS_ROOT_CAUSE_AND_GOVERNANCE.md`
3. `docs/brand/phase5/ARCHITECTURE_AWARE_EVALUATION.md`, soweit nicht durch Gate 1/2 übersteuert
4. `docs/brand/AI_BRAND_PHASE3_EVALUATION_FRAMEWORK.md`, nur für spätere formale Qualifikation
5. `docs/brand/phase5/README.md`
6. weitere Phase-5-Such- und Benchmarkdateien
7. `docs/brand/BRAND_IDENTITY_TARGET_CONTRACT.md`
8. ältere Studien ausschließlich als Negativwissen

Bei Konflikten gilt die höher stehende Datei.

## 3. Verbindliche Prozesssperren

1. Keine Quantitätsziele als Qualitätsersatz.
2. Keine Standardschrift plus dekoratives Einzelmerkmal.
3. Kein App-Icon vor überzeugender vollständiger Primäridentität.
4. Keine rekonstruierte oder selbstvergebene Scorecard als Ersatz für visuelle Freigabe.
5. Keine Behauptung unabhängiger Prüfer bei identischer Orchestrierung.
6. Keine öffentliche oder produktive Markenänderung vor vollständiger Qualifikation und separatem Integrationsworkpack.
7. Keine Wiederholung geschlossener Formfamilien ohne neue visuelle Richtungsgrundlage.
8. Keine weiteren Recraft-/Ideogram-Logo-Generator-Runden derselben Art.
9. Candidate 201 bleibt verworfen und besitzt keinen bevorzugten Status.
10. Keine Architektur wird vor Gate 1 als Sieger festgelegt.
11. Keine Scorecard vor bestandenem visuellen Product-Owner-Gate 2.

## 4. Korrigierter Qualitätsweg

### Gate 1 – Visual Direction

- zwei klar getrennte Markenwelten statt Logos;
- `Klar ausgewählt` und `Nah am echten Leben`;
- sichtbare Typografie-, Dichte-, Bild-, Farbtemperatur- und Consumer-Markenmechanik;
- keine Finalwortmarke, kein App-Icon, kein Claim;
- Product Owner entscheidet `A`, `B` oder `keine`.

### Formsprachenerkundung

Nur die gewählte Welt wird in formale Bausteine übersetzt. AI dient als Such- und Sparringsinstrument; die endgültige Primäridentität wird bewusst und reproduzierbar konstruiert.

### Gate 2 – Primäridentität

Höchstens zwei ernsthafte Identitäten werden ohne Konzepttitel und Scores gegen aktuellen Stand und neutrale Kontrollsetzung verglichen. Product Owner entscheidet `weiter` oder `keine`.

### Formale Qualifikation erst danach

Erst eine sichtbar bestätigte Primäridentität erhält Mini-System, App-Marke, getrennte Bewertungspässe, Red Team, Ähnlichkeits- und Lizenzprüfung, technische Härtung und Scorecard.

## 5. Definition von Fortschritt

Fortschritt ist nicht:

- Anzahl erzeugter Varianten;
- Anzahl SVG-Dateien;
- grüne CI;
- formal vollständige Prüftabellen;
- selbstvergebene 90 Punkte;
- ein technisch stabiles Initialmonogramm;
- eine professionelle, aber austauschbare Fontsetzung.

Fortschritt ist:

- eine vom Product Owner sichtbar bestätigte Markenwelt;
- daraus eine bewusst konstruierte Primäridentität, die in realer mobiler Größe klar über aktuellem Stand und neutraler Kontrollsetzung liegt;
- erst anschließend ein konsistentes Marken- und Produktsystem.

## 6. Aktueller Status

- qualifizierte Richtungen: `0`;
- Candidate 201: `verworfen / Negativwissen`;
- externe Kreativbeauftragung: weiterhin ausgeschlossen;
- öffentliche Änderungen: `0`;
- Gate 1: aktiv.
