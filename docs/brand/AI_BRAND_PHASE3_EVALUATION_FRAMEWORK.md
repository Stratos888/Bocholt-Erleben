# AI-Markenentwicklung Phase 3 – Evaluations- und Konzeptframework

Stand: 2026-08-01
Workpack: #225
Status: verbindlich vor jeder neuen Markenexploration

## 1. Grundsatz

Ein technischer Validator kann keine Premiumgestaltung erkennen.

Er darf nur prüfen, ob der vereinbarte Bewertungsprozess vollständig, getrennt, nachvollziehbar und rechnerisch korrekt durchgeführt wurde.

Die gestalterische Entscheidung bleibt ein dokumentiertes menschliches beziehungsweise kritisch-visuelles Urteil. Grüne CI ist kein Qualitätsprädikat.

## 2. Offene Markenarchitektur

Die nächste Exploration darf gleichberechtigt untersuchen:

1. eigenständige Wortmarke;
2. Zeichen plus Wortmarke;
3. integriertes Zeichen-Wort-System;
4. abstrakte, nicht buchstabenbasierte Kleinmarke;
5. responsive Identität mit unterschiedlichen, aber eindeutig verwandten Fassungen.

Keine Architektur erhält vor der Exploration einen Bonus.

### Verbotene Abkürzungen

- generische lokale Klischees;
- beliebige Initialmonogramme;
- Standardschrift plus einzelner Schnitt, Punkt, Akzent oder Farbe als behauptete Individualität;
- 3D, Glanz, Schatten, Verlauf oder Mock-up-Inszenierung als Qualitätsersatz;
- erzwungene Ableitung eines App-Icons aus einem ungeeigneten Wortmarkendetail.

## 3. Rollenmodell

### Produzent

- erstellt formale Prinzipien und Assets;
- dokumentiert Konstruktion und Herkunft;
- darf Risiken benennen;
- darf keinen finalen gestalterischen Score vergeben;
- sieht Einzelkritiken erst nach deren Festschreibung.

### Kritiker A – Form und Originalgröße

Sieht ausschließlich:

- monochrome Primäridentität;
- 16-, 24-, 32-, 48- und 64-Pixel-Evidenz;
- App-Masken;
- mobilen Header in Originalgröße.

Sieht nicht:

- Konzepttitel;
- beabsichtigte Bedeutung;
- Farbe;
- Produktionsbegründung;
- frühere Scores;
- Kritik B oder Red-Team-Befund.

### Kritiker B – Produkt und Kategorie

Sieht ausschließlich:

- anonymisierte Produktkontexte;
- Header, Homescreen, Favicon und Partneranwendung;
- neutrale Vergleichskontrollen.

Sieht nicht:

- Konzepttitel;
- beabsichtigte Bedeutung;
- Produktionsbegründung;
- frühere Scores;
- Kritik A oder Red-Team-Befund.

### Red Team

Auftrag ist nicht Verbesserung, sondern Ablehnungssuche.

Prüft:

- Genericity;
- Austauschbarkeit;
- Artefaktlesarten;
- Kategoriefehler;
- Erklärungspflicht;
- Kleingrößenkollaps;
- Ähnlichkeits- und Herkunftsrisiken.

Ein belegter harter Knock-out beendet die Richtung vor Punkten.

### Konsolidator

- erhält erst nach Abschluss alle Einzelurteile;
- dokumentiert Konflikte;
- darf einen Knock-out nicht wegmitteln;
- berechnet Scores nur aus freigegebenen Einzelwerten;
- darf CI-Erfolg nicht als Qualitätsargument verwenden.

## 4. Gate-Reihenfolge

### Gate P0 – anonymes Originalgrößen-Preflight

Reihenfolge der ersten Sichtung:

1. mobiler Header in tatsächlicher CSS-Größe;
2. App-Icon auf normalem Homescreen;
3. 24 und 32 Pixel;
4. Browser-Tab/Favicon;
5. monochrome Primäridentität;
6. erst danach größere Anwendungen.

Kandidat wird ausschließlich über anonyme ID bezeichnet.

### Gate P1 – harte Knock-outs

Vor jeder Punktbewertung werden alle folgenden Tests beantwortet:

#### Genericity-Ablation

Wird das besondere Detail entfernt oder neutralisiert, bleibt dann eine gewöhnliche Standardschrift, ein übliches Monogramm oder ein bekanntes Basisschema?

`Ja` bedeutet Knock-out.

#### Erklärungstest

Ist die beabsichtigte Hauptwirkung ohne Titel und Herleitung erkennbar?

`Nein` bedeutet Knock-out, wenn die zentrale Eigenidee nur verbal existiert.

#### Austauschbarkeitstest

Kann die Gestaltung nahezu unverändert für andere lokale Portale oder digitale Dienste verwendet werden?

`Ja` bedeutet Knock-out bei fehlender spezifischer Bindung.

#### Artefakttest

Wird das Merkmal plausibel als Fehler, Beschädigung, Pflaster, Etikett, Pixel, Cursor, Scanrahmen oder Softwareelement gelesen?

`Ja` bedeutet Knock-out.

#### Realgrößentest

Bleibt das zentrale Identitätsmerkmal in Header, App-Icon, 24 und 32 Pixeln klar?

`Nein` bedeutet Knock-out.

#### Kategorieprüfung

Wirkt die Richtung primär wie Verwaltung, Tourismusbroschüre, Karten-App, Bank, Kanzlei, Beauty, Immobilien, generisches SaaS oder eine andere falsche Kategorie?

`Ja` bedeutet Knock-out.

#### Silhouettenprüfung

Besitzt die Primäridentität ohne Farbe eine eigenständige, merkfähige Silhouette?

`Nein` bedeutet Knock-out.

#### Herkunftsprüfung

Sind Assetbasis, Lizenzen und Konstruktion nachvollziehbar?

`Nein` bedeutet Produktionsstopp.

### Gate P2 – Blindkritiken festschreiben

- Kritiker A, Kritiker B und Red Team speichern ihre Urteile unabhängig.
- Jede Datei enthält Evaluator-ID und Zeitstempel.
- Evaluator-IDs müssen verschieden sein.
- Kein Urteil darf nach Kenntnis der anderen still verändert werden.
- Änderungen benötigen neue Revision und Begründung.

### Gate P3 – gestalterischer Score

Nur Kandidaten ohne Knock-out werden bewertet.

| Dimension | Gewicht | Mindestwert |
|---|---:|---:|
| Eigenständigkeit und Wiedererkennung | 25 | 22 |
| Spezifische Passung zu Bocholt erleben | 20 | 17 |
| Qualität der primären Identität | 20 | 17 |
| Wirkung ohne Erklärung | 15 | 13 |
| Kleinformat- und App-Funktion | 10 | 8 |
| Produkt- und Systemintegration | 10 | 8 |

Freigabe nur bei:

- mindestens 90/100;
- jedem Mindestwert;
- keinem Knock-out;
- übereinstimmender Freigabe beider Blindkritiker;
- keinem ungelösten Red-Team-Befund.

### Gate P4 – technische Pass/Fail-Prüfung

Technik erhöht den Designscore nicht.

Pflicht:

- deterministische Vektormaster;
- monochrome und inverse Fassungen;
- echte Rastergrößen;
- Masken und Safe Areas;
- Kontrast und Barrierearmut;
- Lizenz und Provenienz;
- keine externen Laufzeitabhängigkeiten;
- reproduzierbare Asseterzeugung.

### Gate P5 – Product-Owner-Präsentation

Höchstens zwei Richtungen.

Präsentationsreihenfolge:

1. mobile Originalgröße;
2. Homescreen;
3. Favicon und Kleingrößen;
4. monochrome Primäridentität;
5. Produktkontexte;
6. große Markenansicht;
7. erst danach Titel und Herleitung;
8. getrennte Stärken, Risiken und Kritikerabweichungen.

## 5. Bewertungsanker

Die Skala wird je Dimension proportional angewandt:

- 0–3: unbrauchbar;
- 4–5: generisch oder fachlich schwach;
- 6: ordentlich, aber austauschbar;
- 7: professionell und erkennbar;
- 8: deutlich eigenständig und hochwertig;
- 9: außergewöhnlich und klar über dem Markt;
- 10: kategorial herausragend.

Eine Bewertung im 9er- oder 10er-Bereich benötigt:

- konkrete visuelle Belege;
- übereinstimmende Blindurteile;
- bestandene Kontrollvergleiche;
- keine Erklärung als Ersatz für sichtbare Wirkung.

## 6. Vergleichskontrollen

Jede Richtung wird neutral verglichen mit:

- aktuellem öffentlichen Icon;
- früheren zurückgewiesenen Richtungen;
- hochwertigen digitalen Marken;
- typischen generischen Lokal-/Eventportal-Marken;
- neutralen Kontrollvarianten ohne behauptetes Identitätsdetail.

Die Vergleichstafel verwendet identische Größen, Farben und Hintergründe.

## 7. Evidenzpflicht

Der maschinenlesbare Vertrag steht in `docs/brand/evaluation/EVIDENCE_CONTRACT.json`.

Pflichtdateien je späterem Kandidaten:

- `producer.json`;
- `critic-a.json`;
- `critic-b.json`;
- `red-team.json`;
- `consolidation.json`;
- Originalgrößen-Screenshots;
- Genericity-Ablation;
- Austauschbarkeitskontrollen;
- technische Pass/Fail-Nachweise.

Vorlagen liegen unter `docs/brand/evaluation/templates/`.

## 8. CI-Grenze

CI prüft:

- Vollständigkeit;
- Dateiformate;
- Rollenverschiedenheit;
- Blindheitsfelder;
- Gate-Reihenfolge;
- Knock-out-Logik;
- Gewichtssumme;
- Mindestwerte;
- technische Nachweise;
- verbotene Selbstzertifizierung.

CI prüft nicht:

- Schönheit;
- Premiumwirkung;
- Originalität als ästhetisches Urteil;
- Markenpassung als Wahrnehmungsurteil;
- Schutzfähigkeit;
- Freedom-to-use.

Aus einem grünen Validator folgt ausschließlich:

> Der Bewertungsprozess ist formal vollständig.

## 9. Stop-Regel

Vor Abschluss dieses Phase-3-Workpacks werden keine neuen Markenentwürfe erzeugt oder präsentiert.

Ein späteres Gestaltungs-Gate beginnt wieder bei null qualifizierten Richtungen.
