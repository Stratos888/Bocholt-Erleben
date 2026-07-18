# Event-Beschreibungen – Premium-Standard

Stand: 2026-07-03

## Ziel

Event-Beschreibungen auf Bocholt erleben sollen wie kurze lokale Redaktion klingen: seriös, freundlich, konkret und mobil schnell erfassbar. Sie sind keine Quellen-Notizen, keine Veranstalterwerbung und keine generische KI-Prosa.

## Tonalität

Verbindlicher Zielton:

> lokal-redaktionell, seriös, freundlich, kurz, faktenbasiert

Eine Beschreibung soll in 1–2 Sätzen beantworten:

- Was ist das für ein Event?
- Was passiert dort bzw. welches Format ist es?
- Für wen oder welchen Anlass ist es relevant?

## Länge

- ideal: 80–180 Zeichen
- maximal ca. 220 Zeichen
- nur bei erklärungsbedürftigen Events etwas länger, aber ohne Fülltext

## Erlaubt

- kurze neu formulierte Zusammenfassung aus belegbaren Fakten
- lokale Einordnung, wenn sie aus Eventtyp, Ort oder Quelle sicher ableitbar ist
- ruhiger, leicht persönlicher Ton
- Eventtyp + Programmform + lokaler Anlass

## Nicht erlaubt

- Quellenherleitung: `laut Quelle`, `PDF`, `Newsletter-PDF`, `das Programm nennt`, `offiziell nennt`, `Quelle nennt`
- generische KI-Prosa: `Atmosphäre spürbar`, `Teil des kulturellen Lebens`, `bringt Bewegung in die Stadt`, `bekannte Orte bewusst wahrnehmen`
- Werbesprache: `Highlight`, `unvergesslich`, `für Jung und Alt`, `lässt keine Wünsche offen`, `ein Muss`
- längere Originaltexte oder Copy-Paste aus Quellen
- erfundene Details
- Titel, Datum, Uhrzeit oder Ort als Fülltext wiederholen

## Beispiele

Gut:

> Stadtteilfest am Rosenberg zum Abschluss der Interkulturellen Woche. Im Mittelpunkt stehen Begegnung, lokale Aktionen und ein gemeinsamer Tag im Quartier.

Schlecht:

> Das offizielle Integrations-Newsletter-PDF nennt das Rosenbergfestival ganztägig am 26. September 2026.

Gut:

> Open-Air-Konzert am Bahia im Rahmen der WattExtra-Reihe. Der Abend richtet sich an Besucherinnen und Besucher mit Interesse an Livemusik in Bocholt.

Schlecht:

> Wenn Musik den Raum übernimmt, verändert sich die Atmosphäre spürbar.

## Prozess-Contract

Der Standard wird an mehreren Stellen abgesichert:

1. Weekly-KI-Prompt erzeugt Beschreibungen im Zielton.
2. Weekly-KI-Kandidaten werden lokal validiert; harte Fehler werden verworfen.
3. Manual-Intake hängt Kandidaten mit harten Description-Fehlern nicht in die Inbox.
4. Inbox-to-Events blockiert Übernahmen mit nicht publikationsfähiger Beschreibung.
5. Der Public-Build prüft den finalen Runtime-Feed und nutzt kuratierte Overrides für bekannte Bestandsfälle.
6. Content-Quality-Audit erzeugt Issue-Codes und Search-Feedback, damit der nächste KI-Lauf aus Fehlerklassen lernt.

## Rechtlicher Rahmen

Beschreibungen werden kurz paraphrasiert. Sie übernehmen keine längeren Originaltexte aus Quellen und erfinden keine Fakten. Unsicherheiten gehören in interne Notizen oder Review-Hinweise, nicht in die öffentliche Beschreibung.
