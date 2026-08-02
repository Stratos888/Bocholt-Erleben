# Phase 5 – Candidate 201: 7/7 Responsive Word Block

Stand: 2026-08-02
Workpack: #259
Status: **interner Premiumkandidat – Product-Owner-Gate noch nicht eröffnet**

## 1. Konstruktive Leitidee

`Bocholt` und `erleben` besitzen jeweils genau sieben Buchstaben. Candidate 201 nutzt diese reale Eigenschaft des Namens als Primärarchitektur:

- zwei Zeilen mit identischer optischer Breite;
- `bocholt` oben, `erleben` unten;
- ein gemeinsames, ruhig-humanistisches Buchstabensystem;
- verkürzte Oberlängen für einen kompakten mobilen Markenblock;
- optisch normalisierte Rundungen, schmale Zeichen und Laufweiten;
- keine Kursivpaarung, kein Symbolgimmick und keine lokale Illustration.

Die kompakte Fassung ist kein unabhängig erfundenes Monogramm. Sie ist die responsive erste Spalte des vollständigen Systems: `b` über `e`.

## 2. Vergleich der zwei konstruierten Richtungen

### Richtung A – neutraler 7/7-Block

- streng ausgerichtete Zweizeilenarchitektur;
- neutrale groteske Grundform;
- sehr gute mobile Lesbarkeit;
- aber zu geringer Abstand zu einer hochwertigen Standardschrift.

Ergebnis: **beendet vor Kandidatenbildung**.

### Richtung B – humanistischer responsiver 7/7-Block

- gleiche 7/7-Architektur;
- wärmere und weniger technische Buchstabenproportionen;
- systemische Oberlängen-, Breiten- und Laufweitenkorrektur;
- farbliche Rollen: `bocholt` in Text-Primary, `erleben` in dunklem Markengrün;
- responsive `b/e`-Spalte für App- und Kleinstanwendungen.

Ergebnis: **als Candidate 201 weitergeführt**.

## 3. Provenienz und Konstruktion

Ausgangsmaterial:

- Cabin Medium für die Primärwortmarke;
- Cabin SemiBold als optische Korrektur für die kompakte Kleinform;
- Lizenz: SIL Open Font License 1.1;
- alle Markenfassungen werden in Pfade umgewandelt und besitzen keine externe Laufzeitabhängigkeit.

Systemische Änderungen:

- Oberlängen oberhalb der x-Höhe um rund 16 Prozent komprimiert;
- Rundbuchstaben moderat verbreitert;
- `l`, `t` und `r` optisch verschmälert;
- beide Wörter auf identische optische Breite gebracht;
- Laufweiten einzeln korrigiert;
- kompakte Fassung mit höherem optischem Gewicht für 16 bis 64 Pixel.

Die Bezeichnung `Cabin` wird nicht als Markenname oder neuer Fontname verwendet. Die finalen Assets sind reine Pfadgrafiken.

## 4. Architektur-Gates

### W0 – vollständige Wortmarke

Vorläufig bestanden:

- sofort als `Bocholt erleben` lesbar;
- eigenständige Gesamtwortform durch 7/7-Doppelzeile;
- klar kompakter und ruhiger als die bisherige einzeilige Sans-/Kursivkombination;
- keine dominante Verwaltungs-, Tourismus-, Magazin-, Gastronomie-, Sport- oder SaaS-Lesart;
- monochrom tragfähig.

### W1 – Marken-Minisystem

Vorläufig bestanden:

- dunkles `bocholt` trägt Vertrauen und lokale Verankerung;
- dunkles Markengrün für `erleben` trägt Aktivierung, ohne die helle Action-Farbe zu duplizieren;
- mobile Headerbreite bleibt innerhalb von ungefähr 175 bis 180 Pixeln;
- die Zweizeilenstruktur kann in Partner-, Social- und redaktionellen Anwendungen wiederholt werden.

### W2 – responsive Kleinform

Vorläufig bestanden:

- `b/e` ist ein direkter Ausschnitt aus der ersten Spalte;
- keine zusätzliche Bildmetapher;
- stabile Fassungen bei 64, 48, 32 und 24 Pixeln;
- bei 16 Pixeln erkennbarer zweistufiger Buchstabenrhythmus;
- Squircle- und maskable-tauglich.

## 5. Getrennte interne Bewertungspässe

Die Pässe wurden zeitlich getrennt und ohne Änderung der Konstruktion durchgeführt. Sie sind keine externe unabhängige Begutachtung.

### Pass A – Form und Originalgröße

| Dimension | Wert |
|---|---:|
| Qualität und Eigenständigkeit der Wortmarke | 23/25 |
| Kohärenz und Wiedererkennung des Markensystems | 18/20 |
| Passung zu Produkt, Haltung und Zielgruppen | 18/20 |
| Wirkung ohne Herleitung und Kategorieabgrenzung | 13/15 |
| Mobile und responsive Markenfunktion | 9/10 |
| Konstruktion, Provenienz und technische Robustheit | 9/10 |
| **Gesamt** | **90/100** |

Urteil: **Freigabe für den nächsten internen Gate-Schritt**.

### Pass B – Produkt und Kategorie

| Dimension | Wert |
|---|---:|
| Qualität und Eigenständigkeit der Wortmarke | 22/25 |
| Kohärenz und Wiedererkennung des Markensystems | 18/20 |
| Passung zu Produkt, Haltung und Zielgruppen | 19/20 |
| Wirkung ohne Herleitung und Kategorieabgrenzung | 13/15 |
| Mobile und responsive Markenfunktion | 9/10 |
| Konstruktion, Provenienz und technische Robustheit | 9/10 |
| **Gesamt** | **90/100** |

Urteil: **Freigabe für den nächsten internen Gate-Schritt**.

## 6. Red-Team-Pass

### Kein harter Knock-out

- keine UI-, Karten-, Ticket-, Pin-, Scanner- oder Dashboardform;
- kein Kleingrößenkollaps der Primärwortmarke;
- zentrale Wirkung hängt nicht an Erklärung, Verlauf, Schatten oder 3D;
- Grundidee bleibt nach Entfernung der Farbe sichtbar;
- kompakte Fassung ist nachvollziehbar aus der Primärarchitektur abgeleitet.

### Restliche Risiken

1. `b/e` bleibt als Initialausschnitt grundsätzlich einfacher als die vollständige Primäridentität.
2. Eine formale Marken- und Ähnlichkeitsrecherche ist vor öffentlicher Einführung weiterhin Pflicht.
3. Finale Pfade benötigen noch optische Bereinigung und Pixel-Hinting für 16 und 24 Pixel.

Diese Punkte sind Release-Aufgaben und aktuell keine Design-Knock-outs.

## 7. Aktueller Status

Candidate 201 ist der erste Phase-5-Ansatz, der die interne 90-Punkte-Barriere in beiden getrennten Pässen erreicht und keinen harten Red-Team-Knock-out besitzt.

Er ist damit:

- **intern designqualifiziert**;
- **für eine einzelne Product-Owner-Präsentation zulässig**;
- noch **nicht markenrechtlich oder produktiv freigegeben**;
- nicht in `staging`, `main` oder Live integriert.

Vor einer Repository-Integration folgen ausschließlich:

1. saubere Pfadmaster und Größenexporte;
2. formale Ähnlichkeits- und Markenprüfung;
3. Product-Owner-Entscheidung;
4. separates Integrationsworkpack.
