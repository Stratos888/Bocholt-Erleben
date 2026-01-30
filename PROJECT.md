# Projekt „Bocholt erleben“ – Verbindlicher Stand (Single Source of Truth)

> **Wichtig:** Dieses Dokument ist die maßgebliche Referenz für Folgechats.
> Alles hier gilt als **verbindlich entschieden**. Änderungen nur nach Proof.

---

## 1. Projektziel & Grundverständnis

**Bocholt erleben** ist eine **PWA-first Eventplattform**, keine klassische Stadt-Website.

- Home/Start = **Events-Übersicht**
- Fokus: „Was ist wann los?“
- Ruhige, moderne, sachliche UI
- **Keine Werbung, keine Hervorhebung einzelner Anbieter**
- Vertrauen durch Klarheit, Ordnung, Neutralität

---

## 2. Arbeitsprinzipien (oberste Priorität)

1. **Niemals raten**
2. „100% sicher“ nur mit **reproduzierbarem Proof** (DevTools/Logs/konkrete Stellen im Code)
3. Wenn etwas unklar ist → **erst klären, dann patchen**
4. Lieber kein Patch als ein falscher

---

## 3. Technischer Arbeitsmodus (streng / verbindlich)

- **Konsolidierungs-Modus**  
  Der zuletzt gepostete Stand einer Datei gilt als vollständig. Keine Änderungen ohne sichtbaren Code.

- **Diff statt Snippet**  
  Änderungen nur als gezieltes **Ersetzen/Löschen/Verschieben** konkreter Blöcke.

- **Datei-fokussiert**  
  Immer nur **eine Datei pro Schritt** bearbeiten.

- **Codeblock-Markierungen verpflichtend**  
  Bei Einfügen/Ersetzen: am Anfang + Ende eindeutige `BEGIN/END`-Markierungen mit **Zweck & Umfang**.

- **UI-Polish-Patches: CSS-only**  
  Wenn es „nur schöner“ werden soll: ausschließlich CSS.

- **Bugfix-Oberregel**  
  Kein „Fix ist safe“ ohne Root-Cause-Nachweis.

---

## 4. Architektur-Entscheidungen (fest)

### 4.1 Overlays / Fixed/Sticky
- Alle Overlays (Bottom-Sheets, Modals, DetailPanel) gehören in einen **Overlay-Root direkt unter `<body>`**
- Nie innerhalb von sticky/transform/backdrop-filter Containern rendern

### 4.2 Deploy / Cache / Fail-Fast
- Deploy-Pipeline soll **hart fehlschlagen (Fail-Fast)**, wenn Asset-Links in HTML inkonsistent/kaputt sind
- Cache-Busting via `?v=BUILD_ID` (aus Commit-SHA)
- Versionfile liegt unter `/meta/build.txt` (TXT statt JSON, weil STRATO .json teilweise blockt)

---

## 5. Repo-Struktur (relevant)
- `data/events.tsv` = **Single Source of Truth**
- `data/events.json` = wird erzeugt (nicht manuell bearbeiten)
- `scripts/build-events-from-tsv.py` = TSV → JSON Build
- `.github/workflows/deploy-strato.yml` = Build + Guard + Deploy (SFTP/STRATO)
- `js/filter.js` = Filterlogik (Zeit/Kategorie/Suche)
- `js/events.js` = Event Cards Rendering (Anzeige)
- `js/details.js` = DetailPanel Rendering (Anzeige)
- `css/style.css` = UI/Polish (CSS-only für Designpatches)
- `index.html` = Script-Reihenfolge + Cache-Busting Links

---

## 6. Events – Datenmodell (verbindlich)

### 6.1 Single Source of Truth
- **`data/events.tsv`** ist die einzige Quelle
- **`data/events.json`** wird automatisch generiert (Actions)
- JSON wird **niemals** manuell bearbeitet

### 6.2 TSV-Spalten (aktueller Stand)
Pflicht:
- `id` (slug-like, lowercase, `a–z0–9-`)
- `title`
- `date` (YYYY-MM-DD)
- `time` (kann leer sein)
- `city` (z. B. Bocholt, Rhede, Isselburg …)
- `location`
- `kategorie`
- `url`
- `description`

Optional (neu):
- `endDate` (YYYY-MM-DD) – für **Mehrtage-/Laufzeit-Events**

Wichtig:
- Zwischen allen Feldern stehen **Tabulatoren**, keine Spaces
- `endDate` ist optional und darf leer bleiben

### 6.3 Range-Events (Mehrtage/Laufzeit) – Produktregel
- Alles mit Start+Ende bleibt ein **Event** (keine Duplikate pro Tag)
- Card/Detail sollen Zeitraum anzeigen (z. B. `20.11 – 10.01`)
- Während der Laufzeit sollen Events sichtbar bleiben (nicht „im Startmonat verschwinden“)

---

## 7. Event-Radius (festgelegt)
- Standardradius: **20 km um Bocholt**
- Keine harte Stadtgrenze
- Vorbereitung für spätere Standort/Radiussuche

---

## 8. Kategorien & Filter (verbindlich)

### 8.1 Kategorie-Filter (UI)
- Alle
- Märkte & Feste
- Kultur & Kunst
- Musik & Bühne
- Kinder & Familie
- Sport & Bewegung
- Natur & Draußen
- Innenstadt & Leben
- Sonstiges

### 8.2 Disabled-Optionen
- Optionen ohne Treffer bleiben sichtbar, aber **disabled** (ausgegraut)

### 8.3 Zeit-Filter (UX)
- Alle
- Heute
- Wochenende (nächstes Sa+So, robust)
- Demnächst (14 Tage)

---

## 9. Darstellung (Eventliste, Cards, Detail)

### 9.1 Eventliste
- Sortierung: Datum ↑, Uhrzeit ↑
- Dynamische Sections: Heute / Dieses Wochenende / Demnächst / Später
- Keine leeren Trenner

### 9.2 Event Card
- Meta-Zeile: `Stadt · Datum · Uhrzeit`
- Location-Zeile bleibt auf der Card
- Kategorie-Icon oben rechts ist rein visuell
- Ziel: Card bleibt clean, Details im Panel

### 9.3 DetailPanel
- DetailPanel zeigt Beschreibung, Link etc.
- **Offen / als Nächstes wichtig**:
  - Range-Date Anzeige (Start–Ende) + ggf. „läuft aktuell“
  - Ort/Location sauber als Action-Zeile (Homepage/Maps-Fallback)
  - Stadt/Ort-Kontext im Panel konsistent zur Card

---

## 10. Content-Erweiterung: „Angebote“ (Konzept, noch nicht implementiert)

Ziel:
- Dauerhafte Angebote/Orte (Museen, Ponyhof, Indoor/Outdoor) als zusätzlicher Content
- **Nicht** über Bezahlung visuell bevorzugen (Fairness-Versprechen bleibt)

Grundregel:
- „Angebote“ = dauerhaft/immer verfügbar
- „Events“ = zeitlich (auch Laufzeit-Events mit `endDate` bleiben Events)

Monetarisierung (später, optional):
- Keine visuelle Priorisierung durch Zahlung
- Einnahmen eher über Zusatzfunktionen (z. B. Self-Service, Statistiken, eigene Detailseite) – später entscheiden

---

## 11. Deploy/Build Prozess (aktuell)

### 11.1 GitHub Actions (Quelle der Wahrheit)
Bei Push auf `main`:
1) `scripts/build-events-from-tsv.py` erzeugt `data/events.json` aus `data/events.tsv` (Fail-Fast)
2) Build-ID = Commit-SHA (kurz)
3) Staging nach `deploy/` via rsync
4) HTML Guard:
   - setzt/normalisiert `?v=BUILD_ID` in HTML
   - bricht ab, wenn kaputte Asset-Links gefunden werden
   - Mindestanforderung: `index.html` muss `style.css?v=`, `config.js?v=`, `main.js?v=` enthalten
5) Upload zu STRATO via SFTP (lftp mirror)

Wichtig:
- Ein TSV-Commit triggert denselben Deploy wie jeder andere Commit
- Wenn Guard fehlschlägt, wird nichts deployt

---

## 12. Offene ToDos (Next Steps, verbindliche Reihenfolge)

1) **Range-Events sauber anzeigen**
   - Cards: `date` vs `date–endDate`
   - DetailPanel: Zeitraum korrekt darstellen
   - (Optional) „läuft aktuell“/Sortierung für laufende Events

2) **Angebote-Struktur vorbereiten**
   - Datenmodell (z. B. `data/offers.tsv` → `data/offers.json`)
   - UI-Navigation: Bottom Tab Bar „Events | Angebote“ (kein Filter-Chip)
   - Angebote-Seite `/angebote/` anlegen (ruhig, appig, konsistent)

3) Content schrittweise aufbauen
   - erst rechtlich safe: nur Fakten + eigene Texte, keine fremden Fotos/Texte

---

## 13. „Wie geht’s im nächsten Chat weiter?“ (verbindlicher Ablauf)

- ZIP hochladen
- Prompt aus der nächsten Sektion einfügen
- Dann arbeiten wir **Datei-fokussiert** an:
  1) `js/details.js` (Range-Date Anzeige im DetailPanel)
  2) danach erst Angebote-Struktur
