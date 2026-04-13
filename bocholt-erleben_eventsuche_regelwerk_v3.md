# Bocholt erleben – Regelwerk v3 für manuelle und automatisierte KI-Eventsuche

## Zweck
Dieses Regelwerk dient für manuelle und automatisierte KI-Suchläufe, um neue, echte, veröffentlichungsreife Event-Kandidaten für **Bocholt erleben** zu finden.

Ziel ist es, nur solche Events auszugeben, die:
- für Nutzer aus Bocholt realistisch relevant sind,
- ausreichend belastbar belegt sind,
- noch nicht im aktuellen Bestand oder Review-Prozess enthalten sind,
- sauber in den bestehenden Inbox-Flow übernommen werden können.

Die KI-Suche soll möglichst nah an hoher manueller Chat-Qualität bleiben.  
Der Suchkern ist eine **echte KI-Suche mit Quellenbewertung**, keine parserbasierte Abschöpfungslogik.

---

## Verbindlicher Qualitätsmodus

### Nur 100% belastbare Eventdaten
Es gilt strikt der Modus:

**Nur 100% belastbare Eventdaten dürfen in FINAL übernommen werden.**

Wenn ein Feld oder ein Event nicht mit 100% Sicherheit aus einer belastbaren Quelle belegbar ist, dann gilt:

- nicht raten
- nicht ergänzen
- nicht halluzinieren
- nicht in FINAL übernehmen

### Review statt Raten
Wenn ein Datensatz grundsätzlich interessant ist, aber nicht vollständig oder nicht sicher genug belegt werden kann, dann gilt:

- im **manuellen Prüfmodus**: in **REVIEW NÖTIG**
- im **Automationsmodus**: **nicht ausgeben**

Unsichere Datensätze dürfen niemals direkt mit sicheren Datensätzen vermischt werden.

---

## Zwei Betriebsmodi

### Modus A – Manueller Prüfmodus
Dieser Modus gilt für interaktive Suchläufe im Chat.

Ausgabe immer in **zwei Blöcken**:

1. **FINAL FREIGEGEBEN**
2. **REVIEW NÖTIG**

Regel:
- Nur der Block **FINAL FREIGEGEBEN** darf in `data/inbox_manual.json` übernommen werden.
- Der Block **REVIEW NÖTIG** dient nur der menschlichen Nachprüfung und wird **nicht** direkt importiert.

### Modus B – Automationsmodus
Dieser Modus gilt für den automatisierten Wochenlauf.

Ausgabe:
- **nur FINAL**
- **nur als JSON-Array**
- **keine Einleitung**
- **keine Zusatztexte**
- **kein REVIEW-Block**

Regel:
- Alles, was nicht 100% sicher FINAL-fähig ist, wird im Automationsmodus **komplett weggelassen**.

---

## Wichtige Arbeitslogik

### Die KI-Suche deduped gegen Live-Bestand, offene Review-Basis, Entscheidungs-Archiv, Manual-Bestand und Chat-Session-Bestand
Damit ein Suchlauf sauber funktioniert, müssen immer diese Referenzen berücksichtigt werden:

1. **dieses Regelwerk**
2. **der aktuelle Bestands-Export**: bevorzugt `events.tsv`, ersatzweise `data/events.json`
3. **die aktuelle offene Review-Basis**: `inbox.tsv` bzw. `data/inbox.tsv`
4. **das aktuelle Entscheidungs-Archiv**: `inbox_archive.tsv`
5. **die aktuelle `data/inbox_manual.json`**, wenn dort bereits Kandidaten liegen

### Warum genau diese Dateien?
- `events.tsv` bzw. `data/events.json` = bereits kuratierter Live-/Bestandsfeed der App
- `inbox.tsv` bzw. `data/inbox.tsv` = aktuelle Inbox-/Review-Basis, also Events, die schon im Prüfprozess sind
- `inbox_archive.tsv` = bereits entschiedene Fälle aus `Inbox_Archive`, einschließlich **übernommen** und **verworfen**
- `data/inbox_manual.json` = bereits vorbereitete, aber noch nicht importierte Manual-Kandidaten

### Harte Archiv-Regel
`inbox_archive.tsv` ist **Pflichtbestand für sauberes Dedupe**.

Grund:
- verworfene Events dürfen bei späteren Suchläufen **nicht erneut vorgeschlagen** werden
- bereits entschiedene Alt-Fälle aus dem Archiv dürfen ebenfalls **nicht erneut vorgeschlagen** werden
- das Archiv ist die Grundlage für spätere Lern-/Negativlogik

### Zusätzliche Session-Regel
Innerhalb desselben Chats gelten **alle bereits ausgegebenen FINAL-JSON-Kandidaten** als temporärer Zusatz-Bestand.

Bei jedem weiteren Suchlauf desselben Chats muss zusätzlich gegen diesen Session-Bestand deduped werden.

Folgeläufe dürfen **nur neue Delta-Kandidaten** liefern.

---

## Bedienregel für neue Chats

Wenn ein neuer Suchlauf in einem neuen Chat gestartet wird, müssen immer diese Dateien mitgegeben werden:

- `bocholt-erleben_eventsuche_regelwerk_v3.md`
- `events.tsv` oder ersatzweise `data/events.json`
- `inbox.tsv` bzw. `data/inbox.tsv`
- `inbox_archive.tsv`
- `data/inbox_manual.json` (wenn dort bereits Kandidaten liegen)

### Standard-Arbeitsanweisung für neue Chats
> Nutze das beigefügte Regelwerk. Prüfe neue Events gegen `events.tsv` oder ersatzweise `data/events.json`, gegen `inbox.tsv`, gegen `inbox_archive.tsv`, gegen `data/inbox_manual.json` sofern vorhanden und gegen bereits im selben Chat gelieferte Kandidaten. Berücksichtige alle Ausschluss-, Quellen-, Dedupe-, Stil- und Qualitätsregeln aus dem Regelwerk. Liefere nur neue Delta-Kandidaten. Im manuellen Prüfmodus in zwei Blöcken: `FINAL FREIGEGEBEN` und `REVIEW NÖTIG`. Nur `FINAL FREIGEGEBEN` darf in `data/inbox_manual.json`.

### Empfohlener Startprompt für neue Chats
> Nutze das beigefügte Regelwerk. Prüfe neue Events gegen `events.tsv` oder ersatzweise `data/events.json`, gegen `inbox.tsv`, gegen `inbox_archive.tsv`, gegen `data/inbox_manual.json` sofern vorhanden und gegen bereits im selben Chat gelieferte Kandidaten. Suche nur neue, echte, veröffentlichungsreife Events im Suchgebiet und Zeitraum des Regelwerks. Wende alle Quellen-, Dedupe-, Stil-, Beschreibungs-, URL- und Qualitätsregeln strikt an. Gib Ergebnisse im manuellen Prüfmodus immer in zwei Blöcken aus: `FINAL FREIGEGEBEN` und `REVIEW NÖTIG`. Nur der FINAL-Block darf importiert werden.

Wenn eine der Referenzdateien fehlt, soll vor dem Suchlauf klar darauf hingewiesen werden, dass für sauberes Dedupe zusätzlich noch der aktuelle Bestands-Export (`events.tsv` oder ersatzweise `data/events.json`), `inbox.tsv`, `inbox_archive.tsv` und gegebenenfalls `data/inbox_manual.json` aus dem aktuellen Stand benötigt werden.

---

## Ziel
Finde **neue, echte, veröffentlichungsreife Event-Kandidaten** für **Bocholt erleben**, die mit hoher Wahrscheinlichkeit relevant sind und **noch nicht in unserer Event-App oder Review-Basis enthalten** sind.

Die FINAL-Ausgabe muss so erfolgen, dass sie direkt in `data/inbox_manual.json` übernommen werden kann.

Wichtig:
`data/inbox_manual.json` ist ein **Review-/Übergabepuffer**, nicht die finale Live-Events-Datei.

---

## 1. Suchgebiet
Suche nur Events aus:

- **Bocholt**
- plus **maximal ca. 20 km Umkreis**
- **einschließlich niederländischer Orte innerhalb dieses Radius**

Angrenzende Orte nur dann berücksichtigen, wenn sie für normale Nutzer aus Bocholt realistisch relevant sind.

Die niederländische Seite innerhalb des Radius ist eine **bewusste, gültige Scope-Erweiterung** für diese Suche und darf später nicht als Scope-Fehler interpretiert werden.

---

## 2. Zeitraum
Suche nur Events:

- ab **heute**
- bis **180 Tage in die Zukunft**

Keine vergangenen Events aufnehmen.

---

## 3. Was als Event zählt
Aufnehmen nur, wenn es sich mit hoher Wahrscheinlichkeit um ein **echtes, klar terminierbares Event** handelt, zum Beispiel:

- Stadtfeste
- Märkte
- Konzerte
- Theater
- Kulturveranstaltungen
- Ausstellungen
- Lesungen
- Comedy / Kabarett
- Open-Air-Veranstaltungen
- Familienveranstaltungen
- Freizeitveranstaltungen
- saisonale Veranstaltungen
- öffentlich relevante Sportveranstaltungen
- öffentlich zugängliche Workshops oder Kurse, wenn sie klar terminiert und öffentlich relevant sind

---

## 4. Harte Ausschlüsse
Nicht aufnehmen:

- Gottesdienste
- rein kirchliche Standardtermine
- reine Terminlisten / Kalenderübersichten ohne klaren Einzel-Event-Fokus
- reine Venue-Promo
- reine Gastro-Promo
- reine Werbeseiten
- Newsletter-/Abo-Seiten
- Impressum / Datenschutz / Kontakt
- Öffnungszeiten
- Stellenanzeigen
- Pressemeldungen ohne klaren Eventcharakter
- bloße Hinweise ohne belastbare Eventdaten
- dauerhaft wiederkehrende Standardangebote ohne Eventcharakter
- interne Vereins- oder Gruppentermine ohne öffentliche Relevanz
- Events mit zu schwacher oder unklarer Datenlage
- Einträge, bei denen nicht sauber zwischen Eventdatum und Veröffentlichungsdatum unterschieden werden kann

---

## 5. Monetarisierungsschutz
Nicht aufnehmen:

- Events von **potenziellen späteren zahlenden Kunden**, wenn diese nicht aus einer bewusst freigegebenen, neutralen oder offiziellen Quelle stammen
- Events, deren Hauptnutzen die Sichtbarkeit einer einzelnen potenziellen Kunden-Location erhöht
- Eventorte potenzieller Kunden dürfen im KI-Suchlauf **nicht aktiv als zu beliefernde Eventorte** behandelt werden

Besonders vorsichtig behandeln:

- Gastronomien
- Bars
- Restaurants
- Clubs
- private Eventlocations
- kommerzielle Veranstalter
- sonstige Locations, die später für Event-Veröffentlichung zahlen könnten

### Explizite Schutzregel
Mögliche spätere zahlende Kundschaft soll im KI-Suchlauf bewusst **nicht** als normale Eventquelle oder Eventort-Liste bespielt werden.

Dazu zählt ausdrücklich auch:

- **Kulturort Alte Molkerei**

Für solche Locations gilt konservativ:

- **ohne explizite spätere Freigabe keine neuen Treffer als Eventort liefern**
- **nicht aktiv nach deren Einzel-Events suchen**
- **keine Treffer liefern, deren Hauptwert die Sichtbarkeit dieser Location erhöht**
- **im Zweifel weglassen**

### Entscheidungsregel
Wenn ein Event primär nur einer potenziellen Kunden-Location Reichweite verschafft und kein klar öffentlich relevantes Stadt-/Kulturevent aus einer bewusst freigegebenen, neutralen oder offiziellen Quelle ist:

- **nicht aufnehmen**

Im Zweifel:

- **weglassen**

---

## 6. Rechtlich konservative Quellenregel
Nur Events aufnehmen, wenn die Quelle **rechtlich risikoarm**, **strategisch unkritisch** und **für den konkreten Termin belastbar** ist.

Bevorzugte Quellen:

- offizielle Veranstalterseiten
- offizielle kommunale / öffentliche Veranstaltungskalender
- offizielle Stadt-, Kultur- oder Tourismusseiten
- offizielle Eventseiten des Veranstalters
- offizielle Presse-/Infoseiten, wenn ein konkretes Event mit belastbaren Fakten erkennbar ist

Nicht oder nur sehr zurückhaltend nutzen:

- fremde kommerzielle Eventkalender als reine Abschöpfungsquelle
- Seiten mit unklaren Rechteverhältnissen
- Seiten, bei denen nur durch Kopieren längerer Texte ein brauchbarer Eintrag möglich wäre
- Seiten mit stark redaktionell aufbereiteten Inhalten, wenn keine reine Faktenübernahme möglich ist

### Übernahmeregel
Nur übernehmen:

- Titel
- Datum
- Uhrzeit
- Ort
- Stadt
- Quelle
- Quellink
- kurze sachliche Einordnung

Nicht übernehmen:

- längere Originalbeschreibungen
- Werbetexte
- redaktionelle Einleitungen
- individuell formulierte fremde Fließtexte

---

## 7. URL-Regel
Bevorzugt ist **nur eine event-spezifische Detailseite**.

Nicht erlaubt als finale `url` oder `source_url` für FINAL:

- Startseite einer Stadt / Institution
- allgemeine Veranstaltungsübersicht
- Jubiläums- oder Kampagnenseite ohne klaren Event-Detailfokus
- generische Homepage ohne direkten Eventbezug
- Terminlisten-Seiten, wenn daraus kein einzelner Termin eindeutig und vollständig belegbar ist

### Reihenfolge
Es gilt:

**Detailseite > eindeutige Event-Unterseite > sonst nicht FINAL**

### Ausnahme-Regel
Wenn **keine Detailseite existiert**, eine Übersichtsseite aber:
1. das Event eindeutig identifizierbar macht,
2. keine bessere Quelle existiert,
3. Datum, Ort, Titel und belastbare Einordnung daraus 100% sicher hervorgehen,

dann gilt:
- im **manuellen Prüfmodus**: als **REVIEW NÖTIG**
- im **Automationsmodus**: **nicht ausgeben**

Für FINAL gilt damit praktisch:
- **nur Detailseite oder gleichwertig event-spezifische Unterseite**

---

## 8. Beschreibungs-Regel
Beschreibungen müssen streng quellenbasiert und sachlich sein.

Nicht erlaubt:
- generische Stimmungsformulierungen
- frei erfundene Kontextsätze
- allgemeine Eventprosa ohne klare Quellenbasis
- Marketing- oder Atmosphärensprache
- Zitate
- Werbetexte
- Copy-Paste aus der Quelle

Erlaubt:
- kurze sachliche Zusammenfassung aus den belegbaren Fakten
- neutral formuliert
- nur Informationen, die aus Quelle oder eindeutig belastbarer Kontextinfo ableitbar sind

Wenn die Quelle zu wenig Inhalt bietet:
- lieber eine sehr kurze neutrale Beschreibung
- niemals halluzinierte Ausschmückung

### Stil
Die Beschreibung muss sein:
- kurz
- sachlich
- neutral
- faktenbasiert
- neu formuliert
- facts-only

### Länge
- maximal **1–2 kurze Sätze**
- ideal **80–180 Zeichen**
- maximal **200 Zeichen**

### Beschreibungsschablone
Bevorzugt:

`<Eventtyp> im <Ort>. <Kurze sachliche Einordnung des Formats oder Programms>.`

Nur wenn diese Struktur sachlich nicht passt, darf davon abgewichen werden.

---

## 9. Qualitätsanforderungen für FINAL
Ein Event darf nur dann in **FINAL FREIGEGEBEN** übernommen werden, wenn diese Felder belastbar belegt sind:

- `title`
- `date`
- `city`
- `location`
- `kategorie_suggestion`
- `url`
- `description`
- `source_name`
- `source_url`
- `notes`

Zusätzlich möglichst auch:
- `time`
- `endDate`

Wenn eines der FINAL-Pflichtfelder nicht 100% sicher belegt werden kann:
- im manuellen Prüfmodus: **REVIEW NÖTIG**
- im Automationsmodus: **nicht ausgeben**

Leere Zeilen sind immer verboten.

---

## 10. Verifikationsregel
**Felder nur setzen, wenn sie direkt und eindeutig aus der Quelle verifizierbar sind.**

Wenn ein Feld nicht eindeutig belegt ist:
- **Feld weglassen**
- **nicht raten**
- **nicht aus Vermutung ergänzen**

Das gilt besonders für:
- `time`
- `endDate`
- `location`
- `city`

Wenn durch das Weglassen die FINAL-Fähigkeit verloren geht:
- im manuellen Prüfmodus: **REVIEW NÖTIG**
- im Automationsmodus: **nicht ausgeben**

---

## 11. Datums-, Instanz- und Mehrtages-Regel

### Grundregel
Ein JSON-Objekt repräsentiert genau **eine konkrete, besuchbare Termin-Instanz**.

Verwende:
- das **tatsächliche Veranstaltungsdatum**
- nicht das Veröffentlichungsdatum einer Pressemeldung
- nicht das Änderungsdatum einer Seite
- nicht ein unsicher abgeleitetes Datum

### Mehrere Termine
Wenn mehrere konkrete Termine vorkommen:
- jeden klar genannten Termin als **eigenen JSON-Eintrag** ausgeben
- bei gleichem Eventtitel an mehreren Tagen **mehrere Einträge** erzeugen
- bei gleichem Datum mit mehreren Startzeiten **mehrere Einträge** erzeugen

### Mehrtages-Events
Wenn ein Event über mehrere Tage läuft:
- `date` = erster Tag
- `endDate` = letzter Tag
- `description` muss das **gesamte Event** beschreiben, nicht nur den Auftaktag

Wenn `endDate` nicht belastbar belegt ist:
- im manuellen Prüfmodus: **REVIEW NÖTIG**
- im Automationsmodus: **nicht ausgeben**

Wenn kein belastbares Eventdatum einer konkreten Instanz erkennbar ist:
- **nicht FINAL übernehmen**

---

## 12. Zeit-Regel

### Allgemein
`time` darf nur eingetragen werden, wenn die Quelle die Uhrzeit klar und eindeutig nennt.

Dabei exakt unterscheiden:
- Beginn
- Einlass
- Warm-up
- Veranstaltungszeitraum

Wenn mehrere Zeitangaben existieren, nur die **für das Event relevante Hauptzeit** eintragen oder in der Beschreibung sauber unterscheiden.

Keine Zeit raten.  
Keine Zeit aus ähnlichen Events übernehmen.

### Mehrtages-Event
Wenn die Quelle eine **einheitliche Zeitangabe für das gesamte Mehrtages-Event** nennt:
- `time` darf gesetzt werden

Wenn die Quelle **pro Tag unterschiedliche Zeiten** nennt:
- `time` leer lassen

Niemals nur die Uhrzeit des ersten Tages als allgemeine Event-Zeit setzen.

### Mehrere Slots / Läufe / Startzeiten
Wenn es gibt:
- mehrere Startzeiten
- mehrere Läufe / Slots / Blöcke
- denselben Eventtitel an mehreren Tagen

Dann gilt:
- **nicht zu einem Sammel-Eintrag komprimieren**
- stattdessen **pro Termin / Slot einen eigenen JSON-Eintrag**
- pro Eintrag die jeweils konkrete `time` setzen

Nur wenn für die konkrete Instanz keine eindeutige Startzeit belegbar ist:
- `time` weglassen

Ein identischer `source_url` darf mehrfach vorkommen, wenn `date` und/oder `time` unterschiedlich sind.

---

## 13. Scope-/Radius-Regel
Standard:
Nur Events im definierten Zielgebiet aufnehmen.

Zielgebiet dieses Regelwerks:
- Bocholt
- plus maximal ca. 20 km Radius
- inklusive niederländischer Orte innerhalb dieses Radius

Wenn der Nutzer für einzelne Fälle eine Ausnahme erlaubt:
- Event darf aufgenommen werden
- Ausnahme ist dann eine **bewusste Scope-Ausnahme**
- dieser Fall ist **kein Fehler**

Eine bewusste Scope-Ausnahme darf später nicht als normaler Datenfehler umgedeutet werden.

Wenn Scope unklar ist:
- im manuellen Prüfmodus: **REVIEW NÖTIG**
- im Automationsmodus: **nicht ausgeben**

---

## 14. Dedupe- und Session-Dedupe-Regel
Keine Events aufnehmen, die wahrscheinlich bereits vorhanden sind oder offensichtliche Dubletten sind.

Tracking-Parameter, URL-Fragmente und offensichtliche URL-Varianten derselben Quelle sollen als identische Quelle behandelt werden.

Als **identische Termin-Instanz** behandeln:
- kanonisierte `source_url`
- gleicher oder sehr ähnlicher `title`
- gleiches `date`
- gleiches `time`
- gleiche oder sehr ähnliche `location`

Fallback, wenn `time` für die konkrete Instanz nicht vorhanden ist:
- kanonisierte `source_url`
- gleicher oder sehr ähnlicher `title`
- gleiches `date`
- gleiche oder sehr ähnliche `location`

Wenn mehrere Varianten derselben Termin-Instanz gefunden werden:
- nur die **vollständigste und belastbarste** Variante behalten

Dieses Dedupe muss bereits im Suchlauf berücksichtigt werden gegen:
- `events.tsv` bzw. ersatzweise `data/events.json`
- `inbox.tsv` bzw. `data/inbox.tsv`
- `inbox_archive.tsv`
- `data/inbox_manual.json`, wenn vorhanden
- alle bereits im selben Chat gelieferten FINAL-Kandidaten

Besonders wichtig:
- `inbox_archive.tsv` enthält auch bereits **verworfene** Fälle
- verworfene Fälle dürfen **nicht erneut als neue Treffer** ausgegeben werden
- das Archiv ist Teil des kanonischen Dedupe-Bestands

---

## 15. Priorisierung
Bevorzugt aufnehmen:
- lokale und regionale Events mit klarem Bezug zu Bocholt
- Events mit hoher öffentlicher Relevanz
- Events mit vollständigen Fakten
- Events mit klarer Terminierung
- Events aus offiziellen oder neutralen Quellen
- Events, die für normale Nutzer realistisch interessant sind

Eher nicht aufnehmen:
- grenzwertige Mini-Termine
- schwach belegte Einträge
- stark werbliche Seiten
- stark kommerzielle Einzelpromo
- unklare oder halb vollständige Events

---

## 16. Kandidaten-Schema für `data/inbox_manual.json`

### JSON-Schema
```json
[
  {
    "title": "Eventtitel",
    "date": "YYYY-MM-DD",
    "endDate": "YYYY-MM-DD",
    "time": "HH:MM",
    "city": "Bocholt",
    "location": "Ort / Venue",
    "kategorie_suggestion": "Musik & Bühne",
    "url": "https://...",
    "description": "Kurze sachliche Beschreibung.",
    "source_name": "Quellenname",
    "source_url": "https://...",
    "notes": "manual chat search v3"
  }
]
Pflicht für FINAL

Diese Felder müssen für FINAL belastbar gesetzt sein:

title
date
city
location
kategorie_suggestion
url
description
source_name
source_url
notes
Feldregeln
title

Klarer Eventtitel, nicht generisch.

date

Immer YYYY-MM-DD.

endDate

Nur wenn belastbar bekannt.
Bei Mehrtages-Events ist ein belastbares endDate für FINAL erforderlich.

time

Nur wenn eindeutig belegt und regelkonform.

city

Sauber setzen, wenn eindeutig.

location

Möglichst konkret, aber nur wenn belastbar.

kategorie_suggestion

Es sollen nach Möglichkeit direkt die kanonischen Projektkategorien verwendet werden:

Märkte & Feste
Kultur & Kunst
Musik & Bühne
Kinder & Familie
Sport & Bewegung
Natur & Draußen
Innenstadt & Leben
Sonstiges

Keine freien Fantasie-Kategorien, keine Werbebegriffe und keine unnötig feingranularen Unterkategorien.

Wenn ein Event nicht eindeutig zuordenbar ist:

die sachlich nächstpassende kanonische Kategorie wählen
im Zweifel Sonstiges
url

Nur event-spezifische Detailseite oder gleichwertig event-spezifische Unterseite.

source_url

Beste belastbare Quell-URL.

description

Kurz, neutral, facts-only.
1–2 Sätze, maximal 200 Zeichen.
Keine Werbesprache, keine Zitate, keine Halluzinationen, keine Copy-Paste-Formulierungen.

notes

Immer setzen auf:

manual chat search v3
17. ID-Regel – bewusst downstream

Die folgende Regel ist fachlich gültig, gehört aber nicht in dieses Kandidaten-Schema für data/inbox_manual.json.

Die deterministische id ist eine Downstream-Regel für finale Event-Datensätze, nicht für diese Kandidatenliste.

Regeln downstream:

nur lowercase
Umlaute normieren
Sonderzeichen entfernen
Wörter mit Bindestrich trennen
genau ein Datum am Ende im Format YYYY-MM-DD
keine doppelten Jahresbestandteile
keine doppelten Bindestriche

Beispiel:

slug-2026-04-26

Diese Regel wird nicht im Suchkandidaten-Output erzwungen, damit die bestehende Inbox-/PWA-Pipeline unverändert kompatibel bleibt.

18. Mengenregel

Für einen normalen Suchlauf:

lieber weniger, aber sauber
keine Fülltreffer
keine fragwürdigen Grenzfälle nur für mehr Menge

Zielgröße:

10 bis 25 gute FINAL-Kandidaten
wenn nicht genug gute Events vorhanden sind, dann auch weniger
19. Review statt Raten

Wenn eines dieser Probleme vorliegt:

URL nicht eindeutig detailseitig
Mehrtages-Logik unklar
Zeit widersprüchlich
Beschreibung nicht belastbar formulierbar
Ort / Location nicht eindeutig
Scope unklar
Datumslogik unsicher
Quelle nicht belastbar genug
URL nur Übersicht statt Detail
Pflichtfelder für FINAL nicht vollständig sicher

Dann gilt:

im manuellen Prüfmodus: REVIEW NÖTIG
im Automationsmodus: nicht ausgeben

Nie unsichere Datensätze direkt in FINAL mischen.

20. Harte Endprüfung vor Ausgabe

Vor FINAL jede Zeile gegen diese Checkliste prüfen:

 keine Leerzeile
 Pflichtfelder für FINAL gefüllt
 URL ist event-spezifisch und nicht bloß generische Übersicht
 Mehrtages-Event korrekt in date / endDate
 time regelkonform
 Beschreibung sachlich und quellenbasiert
 Scope korrekt oder explizite Ausnahme
 keine Halluzination
 100% sicher oder sonst Review / Ausschluss
 keine Dublette gegen Bestand / Inbox / Archiv / Manual / Session

Nur Zeilen, die alle relevanten Punkte bestehen, dürfen in FINAL.

21. Ausgabeformat
Manueller Prüfmodus

Ausgabe immer in zwei Blöcken:

FINAL FREIGEGEBEN
REVIEW NÖTIG

Regeln:

FINAL FREIGEGEBEN bevorzugt als JSON-Array im Kandidaten-Schema
REVIEW NÖTIG getrennt davon
keine unsicheren Datensätze in FINAL
nur FINAL darf in data/inbox_manual.json
Automationsmodus

Ausgabe muss direkt als JSON für data/inbox_manual.json erfolgen.

Formatregeln:

Ausgabe nur als JSON-Array
keine Einleitung
keine Erklärung
keine Kommentare
keine zusätzlichen Texte außerhalb des JSON
kein REVIEW-Block
nur FINAL-Datensätze
22. Ausgabehinweis nach jedem manuellen Suchlauf

Nach jedem manuellen Suchlauf soll nach dem FINAL-Block zusätzlich kurz und eindeutig darauf hingewiesen werden:

Nur den Block FINAL FREIGEGEBEN vollständig in data/inbox_manual.json kopieren. Nicht in data/inbox.json und nicht in data/inbox.tsv einfügen. Danach den Workflow Manual KI Event Intake starten.

Wenn im selben Chat ein weiterer Suchlauf folgt, muss zusätzlich intern beachtet werden:

Alle bereits in diesem Chat gelieferten FINAL-Kandidaten gelten als temporärer Session-Bestand. Ein weiterer Suchlauf darf nur neue Delta-Kandidaten liefern.

Wenn für den Suchlauf nicht alle Referenzdateien vorhanden sind, soll zusätzlich vorher klar darauf hingewiesen werden:

Für sauberes Dedupe brauche ich zusätzlich den aktuellen Bestands-Export (events.tsv oder ersatzweise data/events.json), inbox.tsv, inbox_archive.tsv und gegebenenfalls data/inbox_manual.json aus deinem aktuellen Stand.

Im Automationsmodus entfällt dieser Ausgabehinweis vollständig.

23. Wöchentliche Arbeitsroutine
A. Manuell im Chat
Neues Chat-Fenster öffnen
Diese Dateien mitgeben:
bocholt-erleben_eventsuche_regelwerk_v3.md
events.tsv oder ersatzweise data/events.json
inbox.tsv bzw. data/inbox.tsv
inbox_archive.tsv
data/inbox_manual.json (wenn dort bereits Kandidaten liegen)
Suchlauf starten lassen
Nur den Block FINAL FREIGEGEBEN vollständig in data/inbox_manual.json kopieren
Wenn im selben Chat direkt weitergesucht wird:
bisherigen FINAL-Output als Session-Bestand behandeln
nur Delta-Kandidaten liefern lassen
neues FINAL-Delta in data/inbox_manual.json ergänzen
Workflow Manual KI Event Intake starten
Inbox Review PWA vollständig kuratieren
Vor dem nächsten Suchlauf wieder gegen den aktuellen Bestand, die offene Inbox, das Archiv und ggf. data/inbox_manual.json dedupen
B. Automationslauf
Regelwerk + aktuelle Referenzdaten werden automatisch geladen
Suchlauf startet nur, wenn Review-/Manual-Puffer dies zulassen
Ausgabe enthält ausschließlich FINAL-JSON
JSON wird nach data/inbox_manual.json geschrieben
Danach folgt der bestehende Intake-/Review-Prozess
24. Zusätzliche Prozessregel

Vor einem neuen Suchlauf sollte die Inbox möglichst vollständig kuratiert sein.

Das heißt:

offene Review-Reste möglichst zuerst abarbeiten
erst danach nächsten Suchlauf starten

Wichtig:

verworfene oder übernommene Fälle bleiben für künftige Suchläufe dedupe-relevant über inbox_archive.tsv
inbox_archive.tsv ist kein optionales Nice-to-have, sondern Pflichtbestand für saubere Folgeläufe
ohne Archiv-Datei ist das Dedupe unvollständig

Wenn ausnahmsweise mehrere Suchläufe innerhalb desselben Chats vor dem Intake gemacht werden, dann gilt zusätzlich:

data/inbox_manual.json vor dem Folgelauf wieder mitgeben
gegen alle bereits im Chat gelieferten FINAL-Kandidaten dedupen
bereits gelieferte FINAL-Kandidaten nicht erneut ausgeben
nur echte Delta-Kandidaten ergänzen

So bleibt der Prozess übersichtlich und das Risiko wiederholter Kandidaten sinkt.
