# Projekt „Bocholt erleben“ – Verbindlicher Stand (Single Source of Truth)

> **Wichtig:** Dieses Dokument ist die maßgebliche Referenz für Folgechats.
> Alles hier gilt als **verbindlich entschieden**. Änderungen nur nach Proof.
> Dieses Dokument ist **KI-optimiert**: Regeln, Architektur, Prozess – keine Diskussionen.

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
2. „100% sicher“ nur mit **reproduzierbarem Proof** (DevTools/Logs/Code)
3. Wenn etwas unklar ist → **erst klären, dann patchen**
4. Lieber kein Patch als ein falscher
5. **Datenpipeline vor UI debuggen (NEU, verbindlich)**

---

## 3. Technischer Arbeitsmodus (streng / verbindlich)

- **Konsolidierungs-Modus**  
  Der zuletzt gepostete Stand einer Datei gilt als vollständig. Keine Änderungen ohne sichtbaren Code.

- **Diff statt Snippet**  
  Änderungen nur als gezieltes **Ersetzen/Löschen/Verschieben** konkreter Blöcke.

- **Datei-fokussiert**  
  Immer nur **eine Datei pro Schritt** bearbeiten.

- **Codeblock-Markierungen verpflichtend**  
  Bei Einfügen/Ersetzen: `BEGIN/END`-Markierungen mit Zweck & Umfang.

- **UI-Polish-Patches: CSS-only**

- **Bugfix-Oberregel**  
  Kein „Fix ist safe“ ohne Root-Cause-Nachweis.

- **Spekulative Fixes verboten (NEU)**  
  Kein „probier mal“, kein mehrfaches Herumdoktern.

---

# 🆕 4. Debug- & Diagnose-Regeln (aus Lessons Learned – verbindlich)

## 4.1 Feste Debug-Reihenfolge bei Event-Problemen

IMMER:

1️⃣ `data/events.tsv` prüfen  
2️⃣ `data/events.json` prüfen  
3️⃣ `scripts/build-events-from-tsv.py` prüfen  
4️⃣ erst dann Frontend (`events.js`, `details.js`)

❌ Niemals direkt UI patchen, wenn Daten evtl. fehlen

---

## 4.2 Runtime-Truth (wichtig)

Zur Laufzeit gilt ausschließlich:

👉 **events.json ist die Wahrheit**

Nicht:
- TSV
- Editor
- Annahmen

Wenn ein Event in `events.json` fehlt → Frontend ist automatisch unschuldig.

---

## 4.3 Build-Status-Regel (NEU, hart)

Wenn GitHub Actions **rot**:
- kein Frontend-Debugging erlaubt
- erst Builder/Script reparieren

---

## 4.4 TSV/CSV Transportregel (NEU)

Strukturierte Tab-Dateien dürfen **niemals im Chat kopiert werden**.

Grund:
- Tabs werden zu Spaces
- Parser bricht
- Spalten verschieben sich

Erlaubt:
- Datei hochladen
- Builder fixen
- Diff-Patches

Verboten:
- komplette TSV hier posten
- „copy/paste Rekonstruktionen“

---

## 4.5 Root-Cause Pflichtprozess (NEU)

Vor jedem Patch:

Beweis liefern:
- console.log(...)
- events.json prüfen
- konkrete Codezeile

Ohne Proof → kein Patch.

---

## 5. Architektur-Entscheidungen (fest)

### 5.1 Overlays / Fixed/Sticky
- Alle Overlays in Overlay-Root unter `<body>`
- Nie innerhalb sticky/transform/backdrop-filter

### 5.2 Deploy / Cache / Fail-Fast
- Deploy schlägt hart fehl bei Asset-Inkonsistenzen
- Cache-Busting via `?v=BUILD_ID`
- Versionfile `/meta/build.txt`

---

## 6. Repo-Struktur (relevant)

- `data/events.tsv` = Single Source of Truth (Editor)
- `data/events.json` = **Runtime Source of Truth**
- `scripts/build-events-from-tsv.py` = einzig erlaubter Konverter
- JSON wird niemals manuell editiert

Frontend:
- `events.js` = Cards
- `details.js` = DetailPanel
- `filter.js` = Filter
- `style.css` = UI-only

---

## 7. Events – Datenmodell (verbindlich)

Pflicht:
- id
- title
- date
- time
- city
- location
- kategorie
- url
- description

Optional:
- **endDate** (Mehrtage/Laufzeit)

---

## 8. Range-Events (finale Produktregel)

- EIN Event mit `date + endDate`
- keine Tagesduplikate
- Anzeige:
  - Card: 20.11 – 10.01
  - Detail: gleicher Zeitraum
- während Laufzeit sichtbar

---

## 9. Darstellung (Eventliste, Cards, Detail)

(unverändert – bestehende Regeln bleiben)

---

## 10. Content-Erweiterung: „Angebote“
(unverändert)

---

## 11. Deploy/Build Prozess
(unverändert + Builder ist kritischster Punkt)

---

# 🆕 12. Lessons Learned (dauerhafte Regeln)

Diese Fehler dürfen nie wieder passieren:

❌ UI debuggen obwohl JSON falsch  
❌ TSV im Chat posten  
❌ mehrere Hypothese-Fixes nacheinander  
❌ Builder ignorieren  
❌ „wahrscheinlich“-Patches  

Immer:

✅ JSON prüfen  
✅ Builder prüfen  
✅ 1 minimaler Fix  
✅ eine Datei pro Schritt  

---

## 13. Offene ToDos (Reihenfolge bleibt)

1) Range-Events final polish  
2) Angebote-Struktur  
3) Content-Aufbau  

---

## 14. Ablauf im nächsten Chat

- ZIP hochladen
- aktuelle Datei posten
- diff-basiert arbeiten
- nie raten
