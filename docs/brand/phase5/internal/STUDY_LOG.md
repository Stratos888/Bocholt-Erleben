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

Hinweis: Die Runden enthalten teils Studien und teils eng begrenzte Gegenproben. Entscheidend ist nicht die Anzahl, sondern dass keine verworfene Form als Kandidat weitergeführt wurde.

- intern geprüfte Studien und Gegenproben: `28`;
- verworfen: `28`;
- Kandidaten: `0`;
- vorläufig weitergeführt: `0`;
- Nutzerpräsentationen: `0`;
- öffentliche Produktänderungen: `0`.

## Runde 1 – standardschriftbasierte Eingriffe

Geprüft wurden Apertur-, Terminal-, Gewichts-, Breiten- und Laufweitenprinzipien auf neutralen Sans-Grundformen.

Verworfen, weil:

- Eingriffe wie beschädigte oder angeschnittene Glyphen wirkten;
- die Grundidentität Standardschrift blieb;
- Eigenständigkeit an einem einzelnen Effekt hing;
- keine belastbare Premiumwirkung entstand.

## Runde 2 – eigene Monoline-/Grotesk-Systeme

Geprüft wurden vollständig gezeichnete gerundete Alphabete in präziser, warmer und bewegter Ausprägung.

Verworfen, weil:

- zu kindlich, freundlich oder spielzeughaft;
- Nähe zu Familienzentrum, Lernangebot oder generischer Digitalmarke;
- unzureichender typografischer Kontrast;
- Dynamik erzeugte keinen eigenständigen Markenwert.

## Runde 3 und 4 – typografische Dualität

Sans-/Serif- und Sans-/Italic-Paarungen wurden wegen Magazin-, Buchhandel-, Gastronomie- oder Kulturinstitutionsnähe verworfen.

`territory-a-study-01` wurde als einzige stärkere Hybridstudie real im mobilen Header sowie in Today-, Detail- und Veranstalterkontexten geprüft.

W0-Ergebnis: **nicht bestanden**.

- Serifenkursive: zu redaktionell-kulturell und gastronomisch;
- Sanskursive Korrektur: zu Sport, Active, Apparel und Template;
- das Grundrezept `stabiler fetter Wortteil + lebendig kursiver Wortteil` blieb zu verbreitet;
- keine ausreichend eigene gemeinsame Zeichnung.

Dokumentation:

- `internal/studies/territory-a-study-01-w0.md`

## Runde 5 – korrigierende Einzelprototypen

Geprüft wurden Schnitte, Aperturen, Quer- und Endstrichverbindungen, humanistische Rundformen, dynamische Monoline, typografische Brücken und aktive Rhythmen.

Verworfen, weil:

- Schnittvarianten Standardschrift plus Artefakt blieben;
- Brücken wie Ligaturgimmicks oder fehlerhafte Verbindungen wirkten;
- Rundformen in UI-, Euro-, Finanz- oder generische Digitalassoziationen kippten;
- monolineare Systeme familien- oder lernangebotsnah wirkten;
- aktivierende Fassungen Ruhe und Vertrauen verloren.

## Prozesskorrektur – architekturabhängige Bewertung

Das allgemeine Phase-3-Framework war für Wortmarken teilweise architekturblind. Es förderte überzeichnete Eingriffe, wörtliche Metaphern und zu frühe App-Zeichen.

Verbindliche Korrektur:

- `docs/brand/phase5/ARCHITECTURE_AWARE_EVALUATION.md`

Architekturklasse `W` wurde in drei Gates getrennt:

1. W0 – vollständige Wortmarke;
2. W1 – neutrales Marken-Minisystem;
3. W2 – erst danach responsive Kleinform und App-Marke.

Die Premiumgrenze blieb mindestens `90/100` mit allen Mindestwerten.

## Runde 6 – Type-Material Study 01

### Pass 1 – eigenes kontrastiertes Buchstabensystem

Verworfen, weil:

- zu geometrisch und generisch-digital;
- Nähe zu Product-Sans, Lernangebot oder freundlicher Plattformmarke;
- keine souveräne Premiumspannung;
- Rundformen und Öffnungen zu vorhersehbar.

### Pass 2 – humanistische Gegenfassung

Verworfen, weil:

- lesbar und erwachsener, aber zu nah an Humanist-Sans- und Gill-artigen Formeln;
- keine vollständig eigene Gesamtzeichnung;
- kein ausreichender Abstand zu einer sorgfältig gesetzten hochwertigen Schriftbasis.

Dokumentation:

- `internal/studies/type-material-study-01-w0.md`

## Runde 7 – Guided Opening Study 01

Das produktspezifische Prinzip `Geführte Öffnung` wurde gegen eine neutrale Ablationsfassung geprüft.

### Pass 1 – humanistische Skelettfassung

Verworfen, weil:

- Primär- und Ablationsfassung praktisch gleich stark waren;
- die Öffnung zu subtil blieb;
- der Markenwert weiter hauptsächlich aus einer bekannten Humanist-Sans-Formel stammte.

### Pass 2 – vollständig eigene parametrisierte Fassung

Verworfen, weil:

- stärkere Sichtbarkeit nur durch schwere, geometrische und technische Formen entstand;
- `c` und `e` sich Interface-, Euro- oder Tech-Zeichen näherten;
- die Wortmarke ihre ruhige Premiumhaltung verlor.

Dokumentation:

- `PRODUCT_FORM_PRINCIPLE.md`
- `internal/studies/guided-opening-study-01-w0.md`

## Architekturentscheidung

Drei belastbare W0-Pfade scheiterten am gleichen Kernproblem:

1. professionelle, aber bekannte Stilformel;
2. ordentliches, aber generisches eigenes Sans-System;
3. produktspezifisches Prinzip entweder unsichtbar oder typografisch überzeichnet.

Eine weitere reine Wortmarkenvariation wäre kein Erkenntnisgewinn.

Deshalb wird Architekturklasse `I` geöffnet:

- Wortmarke und Identitätsmodul werden gemeinsam entwickelt;
- kein isoliertes Icon vor der Primäridentität;
- kein Monogramm oder Branchenpiktogramm;
- Modul und Wortmarke müssen dieselben Kurven-, Endungs- und Negativraumprinzipien teilen;
- nur eine integrierte Studie darf I0 erreichen.

Verbindlicher Brief:

- `docs/brand/phase5/INTEGRATED_IDENTITY_BRIEF.md`

## Nächster zulässiger Schritt

1. Die drei integrierten Architekturhypothesen I-A, I-B und I-C nur als Schwarz-Weiß-Struktur vorfiltern.
2. Hypothesen mit UI-, Artefakt-, Monogramm- oder falscher Kategorie sofort beenden.
3. Höchstens eine Hypothese vollständig als Primäridentität konstruieren.
4. Gegen eine neutrale Ablationskontrolle und im mobilen Header prüfen.
5. Scheitert I0, entsteht weder Kleinmarke noch App-Icon noch Nutzerpräsentation.
6. Nur bei bestandenem I0 wird ein Marken-Minisystem geöffnet.