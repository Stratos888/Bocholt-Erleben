# Projekt „Bocholt erleben“ – Verbindlicher Stand (Single Source of Truth)

> **Wichtig:** Dieses Dokument ist die maßgebliche Referenz für Folgechats.
> Alle hier festgehaltenen Punkte gelten als **verbindlich entschieden**.

---

## 1. Projektziel & Grundverständnis

**Bocholt erleben** ist eine **PWA-first Eventplattform**, keine klassische Stadt-Website.

* Home-Screen = **Events-Übersicht**
* Fokus: *Was ist wann los?*
* Ruhige, sachliche UI
* Keine Werbung, keine Hervorhebung einzelner Anbieter
* Vertrauen durch Klarheit, Ordnung und Neutralität

---

## 2. Arbeitsprinzipien (oberste Priorität)

1. **Niemals raten**
2. „100 % sicher“ nur mit reproduzierbarem Proof
3. Wenn etwas unklar ist → **erst klären, dann handeln**
4. Lieber keine Änderung als eine falsche

---

## 3. Technischer Arbeitsmodus (streng)

* **Konsolidierungs‑Modus**
  → Der zuletzt gepostete Stand einer Datei gilt vollständig

* **Diff statt Snippet**
  → Nur gezielte Ersetzungen bestehender Blöcke

* **Eine Datei pro Schritt**

* **Codeblock‑Markierungen verpflichtend**
  (`BEGIN / END`, Zweck & Umfang)

* **Kein ungefragtes Refactoring**

* **Kein neuer Code, wenn Analyse gefragt ist**

---

## 4. Architektur‑Entscheidungen (fest)

### 4.1 Overlays

Alle Overlays (DetailPanel, Filter‑Sheet, Modals):

* liegen **im Overlay‑Root direkt unter `<body>`**
* **nie** in sticky / transform / backdrop‑Containern

### 4.2 Deploy / Cache

* **Fail‑Fast‑Deploy**
* Asset‑Links müssen korrekt sein
* Cache‑Chaos aktiv verhindern
* `build.json` / `build.txt` ist die gültige Build‑Quelle

---

## 5. Events – Datenmodell (verbindlich)

### 5.1 Single Source of Truth

* **`data/events.tsv`** ist die **einzige** Quelle
* **`data/events.json`** wird **automatisch generiert**
* JSON **niemals manuell bearbeiten**

### 5.2 Build‑Workflow (lokal)

```bash
python scripts/build-events-from-tsv.py
```

Eigenschaften:

* Fail‑Fast (bricht bei Fehlern sofort ab)
* Validiert:

  * eindeutige IDs (slug‑like)
  * Pflichtfelder
  * Kategorien
  * Sonderzeichen
  * Dubletten

### 5.3 Pflichtfelder je Event

* `id` (slug‑like, lowercase, `a–z0–9-`)
* `title`
* `date` (YYYY‑MM‑DD)
* `time`
* `location` (Ort/Location)
* **`city` (z. B. Bocholt, Rhede, Isselburg)**
* `kategorie`
* `description`
* `url`

> **Wichtig:** Stadt ist **verpflichtend** und wird UI‑seitig genutzt.

---

## 6. Event‑Radius (festgelegt)

* Standard‑Suchradius: **20 km um Bocholt**
* Keine harte Stadtgrenze
* Vorbereitung für spätere Radius‑/Ort‑Filter

---

## 7. Kategorien – Filter‑Logik (Stufe B)

### 7.1 Prinzip

* Kategorien sind **normalisiert** (Canonical Mapping)
* Events dürfen granular sein, Filter bleibt stabil
* Kein Event darf „unsichtbar“ werden

### 7.2 Aktive Filterkategorien (UI)

* Alle
* Kinder & Familie
* Musik & Bühne
* Kultur & Kunst
* Märkte & Feste
* Sport & Bewegung
* Innenstadt & Leben
* Sonstiges

### 7.3 Deaktivierte Optionen

* Filteroptionen ohne Treffer werden **disabled & ausgegraut angezeigt**
* Nutzer sehen bewusst: *„Hier ist aktuell nichts“*

---

## 8. Zeit‑Filter – UX‑Regeln

* Optionen:

  * Alle
  * Heute
  * Wochenende
  * Demnächst (14 Tage)

* Optionen ohne Treffer:

  * bleiben sichtbar
  * sind disabled

---

## 9. Preis‑Filter (vorbereitet, noch nicht sichtbar)

* Events können künftig sein:

  * kostenlos
  * kostenpflichtig

* Filterlogik ist vorbereitet

* UI wird **erst später aktiviert**

---

## 10. Event‑Liste – Darstellung

* Sortierung:

  * Datum ↑
  * Uhrzeit ↑

* Dynamische Trenner:

  * nur anzeigen, wenn Events vorhanden sind

---

## 11. Event Card – aktueller Stand

**Meta‑Zeile:**

```
Stadt · Datum · Uhrzeit
```

* Stadt ist **immer sichtbar**
* Location bleibt Bestandteil der Card
* Kategorie wird als Icon dargestellt

---

## 12. DetailPanel – offener Punkt

❗ **Fehlt aktuell:** Anzeige der **Stadt** im DetailPanel

→ Muss ergänzt werden (gleiches Datenfeld wie Card)

---

## 13. Infoseiten – Blueprint (verbindlich)

Struktur (für alle Infoseiten):

* `content-hero`
* 2–4 `content-card`
* **GENAU ein Navigationselement am Ende**

```html
<nav class="content-links">
  <a class="content-link" href="/">Zurück zu den Events</a>
</nav>
```

❌ keine CTA‑Buttons
❌ keine Querverlinkung zwischen Infoseiten

---

## 14. Aktueller Projektstand

* Events bis **April** gepflegt
* Wochenmärkte als Serienevents enthalten
* Script läuft grün (68 Events validiert)
* Filter & UI konsistent

---

## 15. Nächste sinnvolle Aufgaben

1. Stadt im **DetailPanel** ergänzen
2. Preisfeld im TSV finalisieren
3. Serien‑Events eleganter modellieren
4. GitHub Action für automatischen TSV‑Build
5. SEO / Sichtbarkeit (Google, KI‑Suche)

---

**Ende – dieses Dokument ist die verbindliche Übergabe für Folgechats.**
