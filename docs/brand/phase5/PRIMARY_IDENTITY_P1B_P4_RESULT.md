# Phase 5 – P1b bis P4: Primäridentität, zweite Suchachse

Stand: 2026-08-18
Workpack: #259
Status: **BESTANDEN – EIN SYSTEM WIRD ZU P5 BEFÖRDERT**

## 1. Ausgangspunkt

Der erste Lauf nach dem Primary Identity Reset ist dokumentiert in:

- `docs/brand/phase5/PRIMARY_IDENTITY_P1_P4_INTERNAL_RESULT.md`

Er eliminierte geometrische Zeichen-, Öffnungs-, Loop-, Spark-, B/e- und Mikro-Glyphenfamilien vollständig. Kein Kandidat wurde dem Product Owner gezeigt.

Die zweite Suchachse korrigiert deshalb nicht erneut einzelne Glyphen, sondern prüft eine andere Primärarchitektur:

> Die Markenstimme wird zuerst über eine eigenständige, deutlich von der funktionalen UI getrennte typografische Gesamtform aufgebaut. Die Wortmarke muss nicht allein sämtliche spätere Differenzierung tragen; Farbe, Sekundärasset und App-Icon folgen erst nach bewiesener Primärwirkung.

Das folgt der R1-Erkenntnis, dass starke Consumer-Marken ihre Eigenständigkeit über ein Bündel von Codes erzeugen und die Wortmarke deshalb nicht durch ein Einzelgimmick überladen werden darf.

## 2. P1b – neue typografische Suchachse

Breit gegengeprüft wurden:

- moderne Grotesk- und Humanist-Sans-Fassungen;
- robuste Serif-Fassungen;
- hochkontrastige Display-Serif-Fassungen;
- condensed versus offene Proportion;
- Titel-/Großschreibung versus vollständige Kleinschreibung;
- einheitliches Gewicht versus kontrollierte Rollenverteilung zwischen `bocholt` und `erleben`.

Verworfen wurden:

- neutrale Sans-Fassungen wegen zu geringer Distanz zum Produktfont;
- Rounded-/Soft-Grotesk-Fassungen wegen generischer App-/Familiennähe;
- traditionelle Serif-Fassungen mit zu starker Zeitung-, Institution- oder Tourismusnähe;
- Italic-/Roman-Kontrast wegen Magazin-/Editorial-Gimmick-Risiko;
- Großschreibung, wenn sie die Wortform institutioneller und weniger consumer-nah machte.

## 3. P3 – deterministisch konstruierter Kandidat A

Kanonisches Prüfasset:

- `docs/brand/phase5/primary-identity/p1b-candidate-a-mono.svg`

Konstruktion:

- vollständige Kleinschreibung `bocholt erleben` ausschließlich in der Markenwortmarke;
- `bocholt` als kompakter, gewichtiger lokaler Anker;
- `erleben` etwas offener und leichter, aber innerhalb derselben typografischen Familie;
- keine manipulierte Einzelglyphe als Identitätsbehauptung;
- keine Wortbildmarke mit zusätzlichem Symbol;
- Pfade statt Runtime-Font;
- Konstruktionsbasis `Noto Serif Display`, SIL Open Font License 1.1;
- kein App-Icon und kein Sekundärasset vor P5.

Wichtig: Die Richtung beansprucht nicht, dass ein einzelner Spezialbuchstabe die Marke unverwechselbar macht. Die Eigenwirkung entsteht aus Gesamtwortform, Kontrast zur funktionalen UI und der kontrollierten Rollenverteilung der beiden Namensbestandteile.

## 4. P4 – reale Größen- und Produktprüfung

Geprüft wurden:

- monochrome Großansicht;
- 600px, 300px, 180px, 142px, 100px und 80px Wortmarkenbreite;
- realer `48px`-Headerkontext;
- identischer Produktinhalt;
- identische Produkt-UI;
- neutrale Inter-/System-UI-Kontrolle;
- bestehendes dunkles Brand Green nur als Sekundärgegenprobe, nicht als Rettung.

### Ergebnis

- Lesbarkeit bei realer Headergröße: **PASS**;
- optische Ruhe bei realer Headergröße: **PASS**;
- sichtbare Trennung von Brand und funktionaler UI: **PASS**;
- keine Abhängigkeit von Foto, Effekt oder App-Icon: **PASS**;
- keine SaaS-/Startup-Standardwirkung: **PASS**;
- keine Glyphenartefakte oder Mikro-Gimmicks: **PASS**;
- technische Reproduzierbarkeit als monochromes Pfadasset: **PASS**.

Offenes menschliches Risiko:

- Die Serif-Richtung kann als hochwertig-kuratiert wahrgenommen werden, kann aber bei falscher Gesamtwahrnehmung auch zu stark in Richtung Magazin/Editorial kippen.

Dieses Risiko ist sinnvoll nur durch die reale Product-Owner-Wahrnehmung zu entscheiden und nicht durch weitere interne Mikrovariation.

## 5. Entscheidung

**Kandidat A wird als einziges System zu P5 befördert.**

Es gibt keinen zweiten schwächeren Kandidaten nur für künstliche Auswahlbreite.

Neutrale Kontrolle:

- aktuelle hochwertige System-/Inter-artige Wortsetzung ohne individuelle Markenarchitektur.

## 6. P5-Frage

Der Product Owner bewertet keine Typografiedetails.

Nur:

> Wirkt Kandidat A im direkten Realgrößenvergleich wie ein hochwertiger, eigenständiger Absender für `Bocholt erleben` – oder weiterhin nur wie eine andere Schrift beziehungsweise zu stark wie Magazin/Editorial?

Zulässige Antwort:

- `weiter`;
- `zurück`;
- optional ein spontaner klarer Störpunkt.

## 7. Bei PASS

Erst danach:

1. Farbrolle auf Basis des bewiesenen Primärsystems festlegen;
2. prüfen, ob überhaupt ein zusätzliches Sekundärasset benötigt wird;
3. App-Icon zuletzt aus dem dann konsistenten System ableiten;
4. technische, rechtliche und reale Produkt-Härtung;
5. separate Integrationsfreigabe.

Bis dahin:

- keine Änderung an `staging`, `main` oder Live;
- keine Produktintegration;
- keine öffentliche Markenänderung.
