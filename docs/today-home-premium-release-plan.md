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

## Umsetzungsstatus

Stand: 2026-06-10

- P0.1 erledigt mit Commit `61ebdad`: `data/events.json` wird optional geladen; `data/offers.json` bleibt Pflichtquelle.
- P0.2 erledigt mit Commit `d004489`: Today-Event-Visuals werden beim Öffnen an das Event-Detailpanel übergeben.
- P0.5 per Proof bestätigt: `hasAllowedActivityVisual()` schließt `needs_review` und `blocked` aus; aktueller Datenstand `usable: 41`, `needs_review: 3`, `blocked: 0`.
- Aktuell ausgeschlossene `needs_review`-Activities: `hilgelo-erleben`, `hohenhorster-berge-entdecken`, `stadtwald-bocholt-erleben`.

## P1 – Premium-Polish vor Release

1. Today-Ranking stärker lokal und divers machen.
2. Maximal drei Skeleton-Cards im Loading-State zeigen.
3. `css/today.css` konsolidieren und konkurrierende Blöcke reduzieren.
4. Today-Farben sauber an den zentralen Token-Vertrag anbinden.
5. Versteckte Today-Controls entweder entfernen oder finalisieren.
6. Tote Eventfilter-DOM-Reste aus `index.html` entfernen.
7. Footer-/HTML-Struktur in `index.html` bereinigen.
8. SEO/JSON-LD auf die neue Rolle „Bocholt Heute“ aktualisieren.
9. Social-Preview-Asset prüfen und ggf. optimieren.
10. Tastaturbedienung und Fokuszustände der Today-Cards prüfen.

## P2 – Nach Release oder späterer Qualitätsausbau

1. Activity-Premium-Bilder final produzieren.
2. Activity-Visual-Pool wirklich mit den Activity-Daten verdrahten.
3. Externe Activity-Bilder durch lokale WebP-Assets ersetzen.
4. Activity-Bildstatus-System konsequent anwenden: `ready`, `usable`, `needs_review`, `blocked`.
5. Optional echte Personalisierung erst nach UI-/Datenschutz-/Mehrwertklärung aktivieren.

## Abarbeitungsreihenfolge

Empfohlene Reihenfolge:

1. Dokumentation dieses Plans committen.
2. P0.1 Eventdaten optional laden.
3. P0.2 Detailpanel-Visual konsistent übergeben.
4. P0.5 Activity-Needs-Review-Ausschluss per Test/Proof absichern.
5. P0.4 Event-Visual-Alt-Texte ergänzen.
6. P0.3 Runtime-Test nach Deploy.
7. Danach P1-Polish in kleinen, klar getrennten Workpacks.

## Freeze-Regel

Nach Erledigung der P0-Punkte wird die Today-Home funktional eingefroren.

Danach nur noch:

- klar abgegrenzter Premium-Polish,
- belegte Bugfixes,
- Activity-Bildnachzug aus dem separaten Premium-Visual-Prozess.

Keine breite Neudiskussion der Seitenrolle ohne neuen Zielvertrag.

<!-- === END BLOCK: TODAY_HOME_PREMIUM_RELEASE_PLAN_2026_06_10 === -->
