# Phase 5 – Interner Studienlog

Stand: 2026-08-01
Workpack: #259

Dieser Log dokumentiert Entscheidungen und Fehlerklassen. Verworfene visuelle Rohstudien werden nicht als Kandidatenpaket oder Nutzerpräsentation gespeichert.

## Gesamtstand

| Runde | Gegenstand | Umfang | Ergebnis |
|---|---|---:|---|
| 1 | standardschriftbasierte systemische Eingriffe | 5 | vollständig verworfen |
| 2 | eigene Monoline-/Grotesk-Systeme | 5 | vollständig verworfen |
| 3 | typografische Dualität und Paarungen | 7 | 6 verworfen, 1 vorläufig weitergeführt |
| 4 | W0-Prüfung `territory-a-study-01` | 1 | beendet |
| 5 | korrigierende Einzelprototypen | 7 | vollständig verworfen |
| 6 | Type-Material Study 01, zwei Konstruktionspässe | 2 | vollständig verworfen |
| 7 | Guided Opening Study 01, Primär- und Ablationspfad | 2 | vollständig verworfen |
| 8 | Integrated Study 01, Primär- und Ablationspfad | 2 | an I0 beendet |

- intern geprüfte Studien und Gegenproben: `30`;
- verworfen: `30`;
- Kandidaten: `0`;
- vorläufig weitergeführt: `0`;
- Nutzerpräsentationen: `0`;
- öffentliche Produktänderungen: `0`.

## Runde 1 – standardschriftbasierte Eingriffe

Verworfen, weil Eingriffe wie beschädigte Glyphen wirkten, die Grundidentität Standardschrift blieb und die Eigenständigkeit an einzelnen Effekten hing.

## Runde 2 – eigene Monoline-/Grotesk-Systeme

Verworfen wegen kindlicher, lernangebotsnaher oder generisch-digitaler Wirkung und fehlender typografischer Premiumspannung.

## Runde 3 und 4 – typografische Dualität

Sans-/Serif- und Sans-/Italic-Paarungen wurden wegen Magazin-, Buchhandel-, Gastronomie-, Kulturinstitutions-, Sport- oder Template-Nähe verworfen.

`territory-a-study-01` bestand Lesbarkeit und reale Produktkontexte, scheiterte aber an fehlender gemeinsamer Eigenzeichnung und falschen Nachbarkategorien.

Dokumentation:

- `internal/studies/territory-a-study-01-w0.md`

## Runde 5 – korrigierende Einzelprototypen

Schnitte, Aperturen, Quer- und Endstrichverbindungen, humanistische Rundformen, dynamische Monoline und typografische Brücken wurden verworfen.

Fehlerklassen:

- Artefakt oder Ligaturgimmick;
- UI-, Euro-, Finanz- oder generische Digitalassoziation;
- familien- oder lernangebotsnahe Wirkung;
- Dynamik nur durch Schräglage oder Rhythmustrick.

## Prozesskorrektur – architekturabhängige Bewertung

Das allgemeine Phase-3-Framework war für Wortmarken teilweise architekturblind. Es förderte überzeichnete Eingriffe und zu frühe App-Zeichen.

Verbindliche Korrektur:

- `docs/brand/phase5/ARCHITECTURE_AWARE_EVALUATION.md`

Die Premiumgrenze blieb mindestens `90/100` mit allen Mindestwerten.

## Runde 6 – Type-Material Study 01

### Pass 1 – eigenes kontrastiertes Buchstabensystem

Verworfen wegen geometrischer Product-Sans-Nähe, fehlender Premiumspannung und vorhersehbarer Rundformen.

### Pass 2 – humanistische Gegenfassung

Verworfen wegen zu großer Nähe zu Humanist-Sans- und Gill-artigen Formeln und fehlendem Abstand zu einer sorgfältig gesetzten hochwertigen Schriftbasis.

Dokumentation:

- `internal/studies/type-material-study-01-w0.md`

## Runde 7 – Guided Opening Study 01

Das produktspezifische Prinzip `Geführte Öffnung` wurde gegen eine Ablationsfassung geprüft.

- subtil umgesetzt: Primär- und Ablationsfassung praktisch gleich stark;
- sichtbar umgesetzt: schwere, geometrische, technische und artefaktanfällige Buchstaben.

Dokumentation:

- `PRODUCT_FORM_PRINCIPLE.md`
- `internal/studies/guided-opening-study-01-w0.md`

## Architekturwechsel zur integrierten Identität

Nach drei gescheiterten W0-Pfaden wurde Architekturklasse `I` geöffnet.

Vorfilter:

- I-A gemeinsamer Negativraum nicht eigenständig weitergeführt;
- I-B kuratierter Rhythmuskern wegen Trennzeichen-, Cursor- und Ligaturgimmick-Risiko beendet;
- I-C responsive Buchstabenfamilie bedingt zur vollständigen Studie zugelassen.

Dokumentation:

- `INTEGRATED_IDENTITY_BRIEF.md`
- `INTEGRATED_HYPOTHESIS_PREFILTER.md`

## Runde 8 – Integrated Study 01

Geprüft wurde eine integrierte Buchstabenfamilie mit asymmetrischem Negativraummodul über `b`, `o`, `c`, `e`, `h` und `n` sowie verwandten Endungen in `l`, `t` und `r`.

Primärfassung:

- Modul über mehrere Buchstaben sichtbar;
- formale Systemkohärenz vorhanden;
- jedoch Pfeil-, Status-, Interface- und technische Überlagerungslesart;
- mobile Verdichtung und visuelle Unruhe;
- modulare Digitalwirkung statt ruhiger Premiumhaltung.

Ablationsfassung:

- ruhiger und besser lesbar;
- dafür generische geometrische Sans ohne ausreichende Eigenständigkeit.

Unlösbarer Zielkonflikt:

- Modul sichtbar: falsche Kategorie und Artefakt;
- Modul neutralisiert: generisch und austauschbar.

I0-Ergebnis: **HARD KNOCK-OUT – Artefakt-/UI-Lesart.**

Dokumentation:

- `internal/studies/integrated-study-01-i0.md`

## Aktueller Befund

Der Phase-5-Zyklus besitzt weiterhin:

- qualifizierte Richtungen: `0`;
- Kandidaten: `0`;
- Nutzerpräsentationen: `0`.

Keine schwache Richtung wird künstlich weitergeführt.

## Nächste Prozessentscheidung

Eine weitere Variation aus Sans, Apertur, Negativraum oder Modul wäre Wiederholung ohne Erkenntnisgewinn.

Vor einem nächsten visuellen Zyklus muss deshalb auf Strategieebene entschieden werden, welcher Suchraum geöffnet wird:

1. eigenständige, nicht Sans-dominierte Letteringarchitektur;
2. bewusste Weiterentwicklung des aktuellen B mit vollständiger Systemprüfung;
3. Prüfung, ob der stark beschreibende Name zusätzliche visuelle Eigenständigkeit strukturell begrenzt;
4. neuer generativer Suchraum mit späterer deterministischer Rekonstruktion, ohne Rohbilder für den Nutzer.

Bis diese Entscheidung dokumentiert ist, bleibt die öffentliche Marke unverändert.