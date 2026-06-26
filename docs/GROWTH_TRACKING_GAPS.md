# Growth Tracking Gaps

## Bereits technisch nutzbar

Der Growth-Backlog kann aktuell folgende Signale direkt verwenden:

- Google Search Console: Suchanfragen, Seiten, Impressionen, Klicks, CTR, Position
- GA4 Data API: organische Landingpages, Sessions, Engagement-Rate, durchschnittliche Sitzungsdauer
- interne Nutzwert-Metriken aus `value_metric_daily`:
  - `event_detail_view`
  - `activity_detail_view`
  - `website_click`
  - `maps_click`
  - `location_click`
  - `organizer_cta_click`
- Google-Sheet-Bestand:
  - Events
  - Activities / Aktivitaeten
  - Locations
  - Growth_Backlog-Historie
  - Inbox_Archive, sofern vorhanden
- Repo-Daten für Visual-Backlog:
  - `data/event_visual_asset_backlog.tsv`
  - `data/event_visual_remaster_backlog.tsv`

## Noch nicht ausreichend maschinenlesbar getrackt

Diese Punkte wären sinnvoll, sind aber im aktuellen Stand nicht als robuste Growth-Signalquelle erkennbar:

- interne Suchbegriffe auf Events/Activities
- Filter-Nutzung mit Ergebnisanzahl
- Filter-Kombinationen ohne Treffer
- Card-Impressions, also welche Karten überhaupt sichtbar waren
- Card-Klickrate relativ zur Sichtbarkeit
- Favoriten / Merkliste als aggregiertes Signal
- Feedback-Formular-Eingaben als strukturierte Datenquelle, nicht nur Mail/Formspree
- Content-Audit-Ergebnisse als dauerhaftes Sheet-/JSON-Archiv pro Lauf
- Visual-Korrekturen aus der Inbox als strukturierte Historie

## Zielregel

Es sollen keine Rohdaten blind in das Backlog geschrieben werden. Neue Trackingquellen müssen zuerst zu deduplizierten Arbeitspaketen verdichtet werden:

```text
Signal -> Intent/Problem -> Cluster -> priorisiertes Backlog-Arbeitspaket
```

