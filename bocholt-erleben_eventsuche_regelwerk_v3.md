# Bocholt erleben – Regelwerk v3 für manuelle Chat-Eventsuche

## Zweck
Dieses Regelwerk dient für manuelle oder wiederkehrende Event-Suchläufe im Chat.

Ziel ist es, neue, echte, veröffentlichungsreife Events für **Bocholt erleben** zu finden und so auszugeben, dass sie anschließend sauber in den bestehenden Inbox-Flow übernommen werden können.

Die Chat-Suche soll möglichst nah an hoher manueller Chat-Qualität bleiben, ohne eine komplexe automatische Pipeline vorauszusetzen.

---

## Wichtige Arbeitslogik

### Die Chat-Suche deduped nicht allein gegen den echten Projektbestand.
Damit ein Suchlauf sauber funktioniert, müssen dem Chat **immer drei Dinge** mitgegeben werden:

1. **dieses Regelwerk**
2. **die aktuelle `data/events.json`**
3. **die aktuelle `data/inbox.tsv`**

### Warum genau diese Dateien?
- `data/events.json` = bereits kuratierter Live-/Bestandsfeed der App
- `data/inbox.tsv` = aktuelle Inbox-/Review-Basis, also Events, die schon im Prüfprozess sind

Nur mit beiden Dateien kann der Chat vorab möglichst gut erkennen, welche Events vermutlich schon vorhanden oder bereits in Review sind.

---

## Bedienregel für neue Chats

Wenn ein neuer Suchlauf in einem neuen Chat gestartet wird, müssen immer diese Dateien mitgegeben werden:

- `bocholt-erleben_eventsuche_regelwerk_v3.md`
- `data/events.json`
- `data/inbox.tsv`

### Standard-Arbeitsanweisung für neue Chats
Für neue Suchläufe soll nach Möglichkeit diese Standard-Anweisung verwendet werden:

> Nutze das beigefügte Regelwerk. Prüfe neue Events gegen `data/events.json` und `data/inbox.tsv`. Berücksichtige alle Ausschluss-, Quellen-, Dedupe-, Stil- und Qualitätsregeln aus dem Regelwerk. Liefere nur neue, passende Kandidaten ausschließlich als JSON für `data/inbox_manual.json`.

### Empfohlener Startprompt für neue Chats
Dieser Prompt kann in neuen Chats direkt verwendet werden:

> Nutze das beigefügte Regelwerk. Prüfe neue Events gegen `data/events.json` und `data/inbox.tsv`. Suche nur neue, echte, veröffentlichungsreife Events im Suchgebiet und Zeitraum des Regelwerks. Wende alle Quellen-, Dedupe-, Stil-, Beschreibungs- und Qualitätsregeln strikt an. Liefere ausschließlich ein JSON-Array für `data/inbox_manual.json` und keine weiteren Erklärungen im JSON-Block.
Der Chat soll vor der Suche bzw. vor der Ausgabe immer berücksichtigen:
- Regelwerk anwenden
- gegen `data/events.json` dedupen
- gegen `data/inbox.tsv` dedupen
- nur JSON für `data/inbox_manual.json` ausgeben

Wenn eine der beiden Referenzdateien fehlt, soll der Chat vor dem Suchlauf darauf hinweisen, dass für sauberes Dedupe zusätzlich noch `data/events.json` und/oder `data/inbox.tsv` aus dem aktuellen Repo-Stand benötigt werden.

---

## Ziel
Finde **neue, echte, veröffentlichungsreife Events** für **Bocholt erleben**, die mit hoher Wahrscheinlichkeit relevant sind und **noch nicht in unserer Event-App enthalten** sind.

Die Ausgabe muss so erfolgen, dass sie **direkt in `data/inbox_manual.json`** eingefügt werden kann.

---

## 1. Suchgebiet
Suche nur Events aus:

- **Bocholt**
- plus **maximal ca. 20 km Umkreis**

Angrenzende Orte nur dann berücksichtigen, wenn sie für normale Nutzer aus Bocholt realistisch relevant sind.

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

Besonders vorsichtig behandeln:

- Gastronomien
- Bars
- Restaurants
- Clubs
- private Eventlocations
- kommerzielle Veranstalter
- sonstige Locations, die später für Event-Veröffentlichung zahlen könnten

### Entscheidungsregel
Wenn ein Event primär nur einer potenziellen Kunden-Location Reichweite verschafft und kein klar öffentlich relevantes Stadt-/Kulturevent ist:

- **nicht aufnehmen**

Im Zweifel:

- **weglassen**

---

## 6. Rechtlich konservative Quellenregel
Nur Events aufnehmen, wenn die Quelle **rechtlich risikoarm** und **strategisch unkritisch** ist.

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

### Formulierungsregel

Beschreibung immer:

- kurz
- sachlich
- neu formuliert
- facts-only

### Stilregeln für Eventbeschreibungen

Die Eventbeschreibung wird immer **redaktionell neu formuliert**.  
Originaltexte von Veranstaltern oder Eventseiten dürfen **nicht übernommen werden**.

Ziel ist eine kurze neutrale Einordnung im Stil eines Veranstaltungskalenders.

#### Länge

- maximal **1–2 kurze Sätze**
- ideal **80–180 Zeichen**
- maximal **200 Zeichen**

#### Stil

Die Beschreibung muss:

- sachlich
- neutral
- faktenbasiert
- nicht werblich
- nicht ausschmückend

sein.

Nicht verwenden:

- Werbesprache
- Marketingformulierungen
- Aufforderungen
- Bewertungen

Beispiele für verbotene Formulierungen:

- „Freuen Sie sich auf einen unvergesslichen Abend“
- „Ein besonderes Highlight erwartet die Besucher“
- „Der Veranstalter lädt herzlich ein“

#### Inhalt

Die Beschreibung soll möglichst nur enthalten:

- Eventart
- kurzer Kontext des Programms
- ggf. Besonderheit oder Format

Keine:

- langen Programmbeschreibungen
- vollständigen Ablauftexte
- Zitate
- Werbeformulierungen

#### Beschreibungsschablone

Die KI soll standardmäßig diese Struktur verwenden:
<Eventtyp> im <Ort>. <Kurze Einordnung des Programms oder Formats>.

Nur wenn diese Struktur sachlich nicht passt, darf davon abgewichen werden.

Die KI soll bevorzugt konkrete Begriffe aus der Quelle verwenden
(z. B. Konzert, Markt, Ausstellung, Lesung, Comedy-Abend),
aber die Beschreibung vollständig neu formulieren.

Gut:

> Live-Konzert im Kinodrom Bocholt. Mehrere regionale Bands treten an diesem Abend auf.

Gut:

> Comedy-Abend im Kinodrom Bocholt mit mehreren Stand-up-Künstlern.

Gut:

> Geführte Fahrradtour durch das Bocholter Umland mit mehreren Zwischenstopps.

Schlecht:

> Freuen Sie sich auf einen unvergesslichen Abend voller Unterhaltung.

Schlecht:

> Der Veranstalter verspricht beste Stimmung und tolle Künstler.

#### Rechtliche Sicherheitsregel

Die Beschreibung muss immer:

- vollständig **neu formuliert**
- **nicht strukturell aus der Quelle übernommen**
- **keine Zitate enthalten**

Dies dient der Vermeidung von Urheberrechts- und Leistungsschutzproblemen.

---

## 7. Qualitätsanforderungen
Ein Event nur aufnehmen, wenn die Kerndaten ausreichend belastbar sind.

Nach Möglichkeit müssen vorhanden sein:

- `title`
- `date`
- `city`
- `location`
- `source_name`
- `source_url`

Zusätzlich möglichst auch:

- `time`
- `endDate`
- `url`
- `description`
- `kategorie_suggestion`

Wenn wesentliche Kerndaten fehlen:

- Event eher **nicht aufnehmen**

---

## 8. Verifikationsregel
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

---

## 9. Datums- und Instanzregel
Wichtig:

- **ein JSON-Objekt repräsentiert genau eine konkrete, besuchbare Termin-Instanz**
- verwende das **tatsächliche Veranstaltungsdatum dieser Instanz**
- nicht das Veröffentlichungsdatum einer Pressemeldung
- nicht das Änderungsdatum einer Seite
- nicht ein unsicher abgeleitetes Datum

Wenn mehrere Daten vorkommen:

- jeden **klar genannten Termin** als **eigenen JSON-Eintrag** ausgeben
- bei gleichem Eventtitel an mehreren Tagen **mehrere Einträge** erzeugen
- bei gleichem Datum mit mehreren Startzeiten **mehrere Einträge** erzeugen

Wenn kein belastbares Eventdatum einer konkreten Instanz erkennbar ist:

- **nicht aufnehmen**

---

## 10. Uhrzeit-Regel
`time` pro Instanz setzen, wenn für **diese konkrete Instanz** direkt und eindeutig eine Startzeit aus der Quelle hervorgeht.

Wenn es gibt:

- mehrere Startzeiten
- mehrere Läufe / Slots / Blöcke
- denselben Eventtitel an mehreren Tagen

Dann gilt:

- **nicht zu einem Sammel-Eintrag komprimieren**
- stattdessen **pro Termin / Slot einen eigenen JSON-Eintrag** erzeugen
- pro Eintrag die jeweils konkrete `time` setzen

Nur wenn für die **konkrete Instanz** keine eindeutige Startzeit belegbar ist, dann:

- `time` **weglassen**

Ein identischer `source_url` darf dabei mehrfach vorkommen, wenn `date` und/oder `time` unterschiedlich sind.

---

## 11. Dedupe- und Session-Dedupe-Regel
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

Wichtig:
Dieses Dedupe soll bereits im Chat-Suchlauf berücksichtigt werden gegen:

- `data/events.json`
- `data/inbox.tsv`
- `data/inbox_manual.json`, wenn vorhanden
- alle bereits **im selben Chat** gelieferten JSON-Kandidaten

Bei mehreren Suchläufen innerhalb desselben Chats gilt:

- bereits gelieferte Kandidaten sind **temporärer Session-Bestand**
- Folgeläufe dürfen **nur Delta-Kandidaten** liefern
- ein bereits gelieferter Kandidat darf **nicht noch einmal als neuer Treffer** ausgegeben werden
- wenn später bessere Daten gefunden werden, darf der Kandidat nur als **gezielte Korrektur/Ersetzung** behandelt werden

---

## 12. Priorisierung
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

## 13. Stilregel für die Ausgabe
Die Ausgabe muss sein:

- nüchtern
- kurz
- faktenbasiert
- ohne Werbung
- ohne erfundene Details
- ohne Ausschmückung

---

## 14. Ausgabeformat
Die Ausgabe muss **direkt als JSON für `data/inbox_manual.json`** erfolgen.

### Formatregeln

- Ausgabe **nur als JSON-Array**
- keine Einleitung
- keine Erklärung
- keine Kommentare
- keine zusätzlichen Texte außerhalb des JSON

---

## 15. JSON-Schema

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
```

---

## 16. Pflichtfelder
Diese Felder sollen nach Möglichkeit immer gesetzt sein:

- `title`
- `date`
- `city`
- `location`
- `source_name`
- `source_url`

---

## 17. Feldregeln
### `title`
Klarer Eventtitel, nicht generisch.

### `date`
Immer `YYYY-MM-DD`

### `endDate`
Nur wenn belastbar bekannt, sonst weglassen.

### `time`
Nur wenn eindeutig belegt, sonst weglassen.

### `city`
Sauber setzen, wenn eindeutig.

### `location`
Möglichst konkret, aber nur wenn belastbar.

### `kategorie_suggestion`
Es sollen nach Möglichkeit direkt die kanonischen Projektkategorien verwendet werden:

- Märkte & Feste
- Kultur & Kunst
- Musik & Bühne
- Kinder & Familie
- Sport & Bewegung
- Natur & Draußen
- Innenstadt & Leben
- Sonstiges

Keine freien Fantasie-Kategorien, keine Werbebegriffe und keine unnötig feingranularen Unterkategorien verwenden.

Wenn ein Event nicht eindeutig zuordenbar ist, dann:

- die sachlich nächstpassende kanonische Kategorie wählen
im Zweifel `Sonstiges` verwenden.

### `url`
Direkte Event-URL, wenn vorhanden.

### `source_url`
Beste belastbare Quell-URL.

### `description`
Kurz, neutral, facts-only.
1–2 Sätze, maximal 200 Zeichen, gemäß den Stilregeln für Eventbeschreibungen.
Keine Werbesprache, keine Zitate, keine Copy-Paste-Formulierungen aus der Quelle.

### `notes`
Immer setzen auf:

- `manual chat search v3`

---

## 18. Mengenregel
Für einen normalen Suchlauf:

- lieber **weniger, aber sauber**
- keine Fülltreffer
- keine fragwürdigen Grenzfälle nur für mehr Menge

Zielgröße:

- **10 bis 25 gute Events**
- wenn nicht genug gute Events vorhanden sind, dann auch weniger

---

## 19. Sicherheitsregel bei Unsicherheit
Wenn unklar bei:

- Eventcharakter
- Datum
- Ort
- Quelle
- rechtlichem Risiko
- Monetarisierungsrisiko
- Dublettenlage

Dann:

- **nicht aufnehmen**

---

## 20. Ausgabehinweis nach jedem Suchlauf
Nach jedem Suchlauf soll der Chat nach dem JSON zusätzlich kurz und eindeutig darauf hinweisen:

> Dieses JSON vollständig in `data/inbox_manual.json` kopieren. Nicht in `data/inbox.json` und nicht in `data/inbox.tsv` einfügen. Danach den Workflow `Manual KI Event Intake` starten.

Wenn im selben Chat ein weiterer Suchlauf folgt, muss der Chat zusätzlich intern beachten:

> Alle bereits in diesem Chat gelieferten JSON-Kandidaten gelten als temporärer Session-Bestand. Ein weiterer Suchlauf darf nur neue Delta-Kandidaten liefern.

Wenn für den Suchlauf nicht alle Referenzdateien vorhanden sind, soll der Chat zusätzlich vorher klar darauf hinweisen:

> Für sauberes Dedupe brauche ich zusätzlich die aktuelle `data/events.json`, `data/inbox.tsv` und gegebenenfalls `data/inbox_manual.json` aus deinem Repo.

---

## 21. Wöchentliche Arbeitsroutine
1. Neues Chat-Fenster öffnen
2. Diese Dateien mitgeben:
   - `bocholt-erleben_eventsuche_regelwerk_v3.md`
   - `data/events.json`
   - `data/inbox.tsv`
   - `data/inbox_manual.json` (wenn dort bereits Kandidaten liegen)
3. Suchlauf starten lassen
4. JSON-Ausgabe vollständig in `data/inbox_manual.json` kopieren
5. Wenn im selben Chat direkt weitergesucht wird:
   - bisherigen Chat-Output als Session-Bestand behandeln
   - nur Delta-Kandidaten liefern lassen
   - neues Delta in `data/inbox_manual.json` ergänzen
6. Workflow **Manual KI Event Intake** starten
7. Inbox Review PWA vollständig kuratieren
8. Erst danach neuen Suchlauf starten

---

## 22. Zusätzliche Prozessregel
Vor einem neuen Suchlauf sollte die Inbox möglichst vollständig kuratiert sein.

Das heißt:
- offene Review-Reste möglichst zuerst abarbeiten
- erst danach nächsten Suchlauf starten

Wenn ausnahmsweise mehrere Suchläufe **innerhalb desselben Chats** vor dem Intake gemacht werden, dann gilt zusätzlich:

- `data/inbox_manual.json` vor dem Folgelauf wieder mitgeben
- gegen alle bereits im Chat gelieferten Kandidaten dedupen
- bereits gelieferte Kandidaten nicht erneut ausgeben
- nur echte Delta-Kandidaten ergänzen

So bleibt der Prozess übersichtlich und das Risiko wiederholter Kandidaten sinkt.
