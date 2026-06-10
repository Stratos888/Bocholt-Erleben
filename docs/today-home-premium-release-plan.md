<!-- === BEGIN BLOCK: TODAY_HOME_PREMIUM_RELEASE_PLAN_2026_06_10 | Zweck: hält Audit-Befunde, Release-Kriterien und priorisierte To-dos für die neue Home-Seite „Bocholt Heute“ fest; Umfang: Dokumentation, keine Code-Änderung === -->

# Bocholt Heute – Premium Release Plan

Stand: 2026-06-10
Branch-Basis bei Anlage: `staging` / `c0629ee`

## Ziel

Die neue Home-Seite `/` soll als tägliche Einstiegsseite für „Bocholt erleben“ funktionieren.

Nicht Ziel:

- keine zweite Eventliste,
- kein langer Feed,
- keine halbfertige Personalisierungsoberfläche,
- kein Release-Block durch noch nicht vollständig finale Activity-Premium-Bilder.

Zielbild:

- schnelle mobile Entscheidungshilfe,
- maximal drei starke Tagesvorschläge,
- Mischung aus heutigen Events und passenden Aktivitäten,
- robust gegen fehlende oder leere Eventdaten,
- visuell konsistent mit dem Premium-Anspruch der Event- und Activity-Cards,
- klare Weiterführung zu Events und Aktivitäten.

## Aktueller Audit-Befund

Die Home-Seite ist konzeptionell richtig aufgestellt und technisch bereits weit genug, um gezielt gehärtet zu werden.

Stark:

- klare Rolle als Tages-Discovery-Seite,
- kurze Hero- und Empfehlungshierarchie,
- Today-spezifische Empfehlungslogik,
- Wetter-/Tageskontext vorhanden,
- Activity-Bilder mit `image_quality: needs_review` werden aktuell nicht prominent für Today verwendet,
- Bottom-Tabbar setzt Today als zentralen Einstieg.

Noch nicht release-sicher:

- `data/events.json` wird aktuell als Pflichtquelle geladen,
- Today-Eventbilder können beim Öffnen ins Detailpanel verloren gehen, wenn das aufgelöste Visual nicht mitgegeben wird,
- Runtime-Verhalten mit echter deployter `data/events.json` muss noch belegt werden,
- CSS-/DOM-Hygiene ist noch nicht final konsolidiert,
- SEO/Structured-Data passt noch nicht vollständig zur neuen Seitenrolle,
- Event-Visual-Metadaten enthalten noch Datenhygiene-Lücken, insbesondere Alt-Texte bei `ready`-Assets.

## Verbindliche Release-Logik

Die Home-Seite darf nicht vollständig ausfallen, nur weil Eventdaten fehlen.

Pflichtquelle:

- `data/offers.json`

Optionale Quellen:

- `data/events.json`
- `/api/events/public.php`

Sollverhalten:

- Wenn Events verfügbar sind, dürfen sie in Today einfließen.
- Wenn keine Events verfügbar sind, muss Today mit Activities weiter funktionieren.
- Wenn Eventquellen fehlschlagen, darf die Seite keinen globalen Fehlerzustand zeigen.
- Nur wenn auch Activities nicht geladen werden können, ist ein harter Fehlerzustand plausibel.

## Umgang mit Activity-Bildern

Die finalen Activity-Premium-Bilder sind ein separater Workstream und blockieren diesen Release nicht vollständig.

Für diesen Release gilt:

- vorhandene `usable`-Activity-Bilder dürfen weiter genutzt werden,
- `needs_review`-Bilder dürfen nicht prominent auf Today erscheinen,
- keine Bilder aus dem Event-Visual-Pool für Activities verwenden,
- Activity-Premium-Bilder werden später nachgezogen,
- Today muss mit dem aktuellen Activity-Bildstand sauber funktionieren.

Spätere Zielrichtung:

- jede Activity erhält ein eigenes Premium-Bild,
- keine Dopplung zwischen Event- und Activity-Bildern,
- Activity-Visual-Pool wird sauber mit den Activity-Daten verdrahtet,
- externe Activity-Bilder werden schrittweise durch lokale WebP-Assets ersetzt.

## P0 – Release-Blocker

### P0.1 Eventdaten optional laden

`js/today-home.js` muss so gehärtet werden, dass fehlende oder fehlerhafte Eventquellen die Home-Seite nicht komplett abbrechen.

Release-Kriterium:

- Home lädt mit Events und Activities.
- Home lädt nur mit Activities.
- Home lädt bei fehlender `data/events.json`.
- Home lädt bei leerer Eventliste.
- Home lädt bei API-Fehler.

### P0.2 Event-Visual beim Öffnen ins Detailpanel erhalten

Wenn eine Today-Eventkarte ein aufgelöstes Premium-Visual zeigt, muss dasselbe Visual beim Öffnen im Detailpanel verfügbar bleiben.

Release-Kriterium:

- Eventbild in Today-Card und Event-Detailpanel ist konsistent.

### P0.3 Runtime-Test mit echter deployter Eventdatei

Da `data/events.json` im Repo/ZIP nicht dauerhaft liegt, muss das Verhalten nach Deploy mit echter generierter Datei geprüft werden.

Release-Kriterium:

- deployte `/data/events.json` lädt erfolgreich,
- heutige Events werden korrekt erkannt,
- abgelaufene Events werden nicht als Heute-Vorschläge gezeigt,
- mehrtägige Events werden korrekt behandelt,
- bei null heutigen Events erscheint ein sauberer Activity-Fallback.

### P0.4 Event-Visual-Alt-Texte ergänzen

`ready`-Event-Visuals sollen vollständige Metadaten haben.

Release-Kriterium:

- keine `ready`-Assets ohne sinnvollen Alt-Text.

### P0.5 Activity-Needs-Review-Ausschluss absichern

Activities mit `image_quality: needs_review` dürfen nicht prominent in Today erscheinen.

Release-Kriterium:

- Today zeigt keine Activity mit `needs_review`-Bild.

## P1 – Premium-Polish vor Release
