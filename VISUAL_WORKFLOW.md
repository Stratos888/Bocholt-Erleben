# Premium Visual Workflow

Stand: 2026-06-01
Branch: staging
Referenz-Commit: 4874e70 Aktualisiere Event Visuals auf 16-zu-9 Card Assets

## 1. Aktueller Stand

Event-Visual-Remaster V1 ist abgeschlossen.

Ergebnis:
- 10 Event-Visuals liegen als WebP-Card-Assets vor.
- Zielmaß: 1200x675.
- Format: WebP.
- Audit: Errors none.
- Event ready/fallback not 16:9: 0.
- Alte nicht-16:9 Eventbilder wurden nicht überschrieben, sondern im Pool auf usable zurückgestuft.

Fertige Event-Assets:
- market-food-01-16x9.webp
- city-festival-01-16x9.webp
- music-stage-01-16x9.webp
- culture-exhibition-01-16x9.webp
- kids-family-01-16x9.webp
- creative-workshop-01-16x9.webp
- sport-active-01-16x9.webp
- outdoor-nature-01-16x9.webp
- city-walk-01-16x9.webp
- default-city-02-16x9.webp

## 2. Verbindliche Grundsätze

Accepted Style Asset ist nicht automatisch Ready Card Asset.

Ready bedeutet künftig:
- visuell akzeptiert
- rechtlich bzw. produktionslogisch sauber
- WebP
- echtes 16:9
- card-tauglich
- audit-konform

Bilder werden nicht per CSS-Cropping gerettet.
Schwache Bilder werden ersetzt, zurückgestuft oder blockiert.

Bildproduktion und Repo-Integration bleiben getrennt:
- Bilderzeugung im separaten Bildchat
- Bewertung und Integration im Repo-Chat

## 3. Quellenhierarchie

Bevorzugte Bildquellen:
1. eigene oder exklusive Premium-Echtfotos
2. vom Veranstalter oder Rechteinhaber freigegebene Premium-Echtfotos
3. sonstige rechtlich einwandfreie, qualitativ starke Fotos
4. selbst erzeugte symbolische KI-Premium-Visuals
5. externe Legacy-Bilder nur als Übergang

Wenn kein rechtlich einwandfreies Premium-Echtfoto verfügbar ist, ist ein selbst erzeugtes symbolisches KI-Premium-Visual der bevorzugte Standard-Fallback.

KI-Visuals dürfen keine dokumentarische Behauptung eines echten Ortes oder echten Events erzeugen.

## 4. Wiederverwendbarer Prompt-Aufbau

Bitte erzeuge jetzt nur das Einzelbild [ASSET_ID] für den Visual-Key [VISUAL_KEY].

Wichtig:
- genau ein einzelnes finales Bild
- echtes 16:9-Querformat
- keine Collage
- kein Bildraster
- keine Mehrfachvarianten
- nur ein finales Bild für diese eine Asset-ID

Ziel:
Ein hochwertiges symbolisches Premium-Visual für [VISUAL_KEY] im Stil der bereits entwickelten Bildwelt für Bocholt erleben.

Bildidee:
[Konkrete symbolische Szene beschreiben: lokal plausibel, kleinstädtisch/westmünsterländisch, hochwertig, ruhig, nicht dokumentarisch.]

Abgrenzung:
- nicht wie [ANDERE_VISUAL_KEYS]
- kein Motiv, das in eine andere Kategorie kippt
- keine dokumentarische Behauptung eines echten Ortes oder Events

Desktop-Qualität:
Das Bild muss auch auf einer normalen Desktop-Card hochwertig, authentisch und nicht offensichtlich KI-generiert wirken.
Keine sterile Renderoptik.
Keine perfekte Modellkulisse.
Keine glatte Stockfoto- oder Werbekampagnen-Ästhetik.
Leichte reale Gebrauchsspuren, natürliche Unregelmäßigkeiten, glaubwürdige Materialien und asymmetrische Details sind erwünscht.

Komposition:
- Hauptmotiv in der sicheren mittleren Zone
- genug ruhige Randbereiche für Mobile- und Desktop-Cards
- keine extreme Nahaufnahme, bei der der Kontext verloren geht
- warme natürliche oder passend atmosphärische Lichtstimmung
- ruhige, klare Premium-Editorial-Fotooptik

Verbindliche Negativregeln:
- keine Logos
- keine lesbaren Marken
- keine lesbaren Schilder
- keine lesbaren Plakate
- keine lesbare Schrift im Bild
- keine erkennbaren Kinder
- keine klar erkennbaren Gesichter
- keine bekannte Kunst, Band oder Vereinsidentität als Fokus
- kein Stockfoto-Look
- kein KI-Fantasy-Look
- keine konkrete Ortsbehauptung zu Bocholt

Bildwirkung:
[3 bis 8 Zielwörter, z. B. ruhig, hochwertig, glaubwürdig, editorial, lokal sensibel, authentisch, premium.]

## 5. Bewertungslogik

ready:
Bild erfüllt Visual-Key, Abgrenzung, 16:9-Wirkung, Premium-Qualität und Negativregeln.

usable:
Stilistisch brauchbar, aber nicht final prominent. Beispiel: altes akzeptiertes Nicht-16:9-Asset.

needs_review:
Bild kann später ersetzt oder remastered werden, darf aber nicht prominent in Premium-Flächen laufen.

blocked:
Bild verletzt harte Regeln, z. B. erkennbare Kinder oder Gesichter, Logos, Schrift, falsche Ortsbehauptung, fremde Kunstwerke, schlechter KI- oder Stock-Look.

## 6. Batch-Import-Contract

Import:
- Downloads temporär nach tmp/<scope>-16x9-import/
- Zielnamen vorher sauber vergeben, z. B. market-food-01-16x9.png
- tmp/ nie committen

Konvertierung:
- WebP
- 1200x675
- cwebp -q 82
- maximal 450 KB
- alte Assets nicht überschreiben
- neue Asset-IDs versionieren, z. B. market-food-01-16x9

Pool-Update:
- neues 16:9-Asset als ready eintragen
- altes akzeptiertes Nicht-16:9-Asset auf usable zurückstufen
- altes Asset mit legacy_reason und superseded_by versehen
- neues Asset mit card_asset_format und supersedes versehen

Audit:
- python3 tools/audit-visual-contract.py
- ready/fallback not 16:9 muss für finalisierte Bereiche 0 sein
- Bilddimensionen headerbasiert lesen
- keine Regex-Auswertung von file-Output verwenden, weil Dateinamen wie 16x9 falsche Treffer erzeugen können

## 7. Nächste Workstreams

1. Event Visuals in den normalen Events-Feed integrieren.
2. Activity AI Visuals Phase 1 produzieren.
3. Activity Visual Pool in Offers integrieren.
4. Detailpanel- und Hero-Bildlogik separat entscheiden.
5. Today Home final visuell prüfen.

<!-- === BEGIN BLOCK: EVENT_VISUAL_KEYS_V31_CONTRACT_2026_06_02 | Zweck: dokumentiert den verbindlichen V3.1-Zielzustand fuer Event-Visual-Keys, Bildproduktion und kuenftige Visual-Arbeit === -->
## Event Visual Keys V3.1 – verbindlicher Zielzustand

Status: umgesetzt auf `staging` und fachlich gegen den aktuellen Staging-Feed nachgeschärft.

Bestätigter Repo-/Deploy-Stand:
- `1b7b73f` – `Integriere Event Visuals in Feed Cards`
- `ad81c00` – `Konsolidiere Event Visual Keys V3.1`
- `aa68d8c` – `Schaerfe Event Visual Key Inferenz nach`
- `8b41f06` – `Schaerfe Event Visual Key Restzuordnung nach`
- bestätigter Staging-Build nach letzter Nachschärfung: `8b41f067a3ca`

### Produktentscheidung

Langfristiges Ziel ist ein konsistent voll bebilderter Event-Feed auf Premium-Niveau.

Für Event-Cards gilt:
- Eventkarten sollen perspektivisch grundsätzlich ein Premium-Visual tragen.
- Keine dauerhafte Mischlogik „manche Events mit Bild, manche ohne Bild“, weil das wie redaktionelle Gewichtung, fehlende Pflege oder Zufall wirken kann.
- Mobile-Platzbedarf wird später über ein kompakteres Card-Layout gelöst, nicht über selektives Entfernen von Bildern.
- Schwache oder generische Bilder dürfen nicht als `ready` markiert werden.
- `ready` bedeutet: visuell akzeptiert, rechtlich/produktionslogisch sauber, WebP, echtes 16:9, card-tauglich, audit-konform.
- Planned-Slots werden nie live genutzt.
- Dopplungslogik im Feed ist ein späteres Quality-Gate vor Production, nicht der erste Staging-Fix.

### Visual-Key-System V3.1

Die frühere 12er-Taxonomie war zu grob. Eine reine 20er-Taxonomie blieb für Kultur, Bühne/Sprache, Sport, Feste sowie Familien-/Workshopformate noch zu unscharf.

V3.1 verwendet 34 kontrollierte Event-Visual-Keys. Diese gelten aktuell als bester Zielzustand:
- detailliert genug für passendere Premium-Bilder
- noch regelbasiert und automatisch inferierbar
- nicht auf einzelne Eventnamen zugeschnitten
- pflegbar über Bildfamilien und Slot-Briefs

Zentrale Datei:
- `scripts/event_visual_keys.py`

Vertragsdateien:
- `data/event_visual_pool.json`
- `data/event_visual_asset_brief.json`
- `data/event_visual_ai_style_guide.json`
- `data/event_visual_phase1_plan.tsv`
- `data/event_visual_generation_batches_phase1.json`

Audit-Dateien:
- `scripts/audit-event-visual-pool.py`
- `scripts/audit-event-visual-asset-brief.py`
- `scripts/audit-event-visual-ai-style-guide.py`
- `scripts/audit-event-visual-generation-batches.py`

### V3.1-Key-Familien

Kultur, Museum, Geschichte:
- `textile_machines_industry`
- `textile_exhibition_design`
- `art_exhibition_gallery`
- `local_history_heritage`
- `city_tour_history`

Musik, Bühne, Sprache:
- `live_music_stage`
- `classical_music`
- `theater_stage`
- `comedy_cabaret`
- `film_screening`
- `literature_reading_talk`
- `kids_stage_story`

Feste, Märkte, Stadtleben:
- `city_festival_street`
- `open_air_festival`
- `kirmes_funfair`
- `parade_festzug`
- `shooting_festival_tradition`
- `market_stalls`
- `book_market`
- `food_drink_festival`
- `country_fair_rural`

Kinder, Familie, Lernen, Workshops:
- `family_play_outdoor`
- `creative_making_workshop`
- `learning_science_workshop`
- `dance_music_workshop`

Sport, Bewegung, Natur:
- `active_route_tour`
- `running_event`
- `cycling_event`
- `indoor_sport_competition`
- `nature_learning_wildlife`

Sonstige:
- `evening_social_party`
- `business_messe_info`
- `vehicle_classic`
- `default_city`

### Inferenzregeln

Für `scripts/event_visual_keys.py` gilt:
- Starker Eventtyp vor Kategorie.
- Eventtyp vor Location.
- Location darf eine klare Eventtyp-Zuordnung nicht überschreiben.
- TextilWerk als Location darf nicht automatisch `textile_machines_industry` auslösen.
- Alte Legacy-Keys dürfen nur historisch normalisiert werden; Zielzustand bleibt V3.1.
- Unsichere Restzuordnungen nicht raten, sondern nur mit Titel/Beschreibung/Quelle patchen.

Nachgeschärfte geprüfte Fälle:
- `Issel unplugged - Stadtturm Open Air...` → `live_music_stage`
- `K-Pop Power! Sing & Dance Workshop` → `dance_music_workshop`
- `Textile Revolution – Stoffe für die Zukunft` → `textile_exhibition_design`
- `20. Sparkassen MünsterlandGiro - Profistart` → `cycling_event`
- `Führung Lebenselixier Wasser... Pröbstingsee` → `nature_learning_wildlife`
- `AaltenDagen` → `city_festival_street`
- `Bokeltsen Treff 2026` → `city_festival_street`
- `Bewegte Geschichte - Kostümierte Stadtführungen 2/2` → `city_tour_history`
- `Aasee-Festival` → `open_air_festival`
- `Internationale Herfstboekenmarkt` → `book_market`

### Datenprozess

Die gepflegte redaktionelle Quelle ist nicht `data/events.tsv` im Repo.

Aktueller Prozess:
1. KI-Suche schreibt Kandidaten nach `data/inbox_manual.json`.
2. Manual Intake schreibt Kandidaten in den Google-Sheet-Tab `Inbox`.
3. Kuratierte Events gehen in den Google-Sheet-Tab `Events`.
4. Deploy exportiert den Google-Sheet-Tab `Events` temporär nach `data/events.tsv`.
5. Daraus wird während des Deploys `data/events.json` gebaut.
6. Die Website nutzt das deployte `data/events.json`.

Konsequenz:
- `data/events.tsv` ist technische Zwischenquelle im Deploy, nicht primäre redaktionelle Repo-Quelle.
- Bei Visual-Key-Problemen zuerst `scripts/build-events-from-tsv.py` und `scripts/event_visual_keys.py` prüfen.
- Prüfen, ob `kategorie`, `description`, `title` und `location` korrekt in die Inferenz laufen.

### Bildproduktion ab V3.1

Aktueller Vertrag:
- `data/event_visual_pool.json`: 34 Keys, 156 Zielslots
- vorhandene Ready-/Usable-Assets wurden migriert
- Planned-Slots wurden auf V3.1-Dateinamen normalisiert
- `data/event_visual_phase1_plan.tsv`: 24 fehlende Basis-Visuals
- `data/event_visual_generation_batches_phase1.json`: 24 Requests in 4 Batches

Nächster fachlicher Schritt:
- Die 24 fehlenden V3.1-Basis-Visuals aus `data/event_visual_generation_batches_phase1.json` produzieren.
- Jedes Bild einzeln visuell prüfen.
- Erst nach Review als `ready` markieren.
- Danach Staging-Feed erneut visuell im echten Card-Kontext prüfen.

### Staging-/Production-Prüfung

Für Staging immer prüfen:
- `https://staging.bocholt-erleben.de/meta/build.txt`

Nicht die Production-Domain für Staging-Deploys verwenden.

Bekannte Separierung:
- Fehlgeschlagene Scheduled Runs wie `Weekly KI Websearch` oder `Inbox → Events` auf `staging` sind nicht automatisch Deploy-Fehler.
- Diese Workflows sind separat und teils main-gebunden zu bewerten.
<!-- === END BLOCK: EVENT_VISUAL_KEYS_V31_CONTRACT_2026_06_02 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_IMAGE_PROMPTING_REVIEW_LEARNINGS_2026_06_02 | Zweck: dokumentiert die Prompting- und Bewertungsverbesserungen aus dem Event-Visual-Produktionschat; Umfang: Event-Visual-Prompting, Bilderchat, Review-Gates === -->
## Event Visual Prompting – Produktionslernen 2026-06-02

Status: verbindliche Ergänzung für weitere Event-Visual-Erzeugung.

### Arbeitsmodus

Für Event-Visuals wird weiterhin getrennt gearbeitet:

- Dieser Projektchat liefert nur Prompts, Bewertungen, Statusentscheidungen und Repo-Dokumentation.
- Die tatsächliche Bildgenerierung findet in einem separaten Bilderchat statt.
- In diesem Projektchat darf nicht erneut versehentlich direkt ein Bild generiert werden.
- Pro Schritt wird grundsätzlich ein einzelner Visual-Key bearbeitet.
- Nach jedem erzeugten Bild wird erst bewertet, dann entschieden:
  - `ready`
  - `ready mit Prüfvorbehalt`
  - `retry`
  - `nicht ready`
- Danach folgt erst der nächste Prompt.

### Neue Prompting-Regel: weniger arrangierte Props

Die wichtigste Verbesserung aus dem Produktionslauf:

> Weniger arrangiertes Material ist meist glaubwürdiger. Lieber wenige starke Hinweise plus echter Raum/Situation als viele sauber drapierte Symbolobjekte.

Künftige Prompts sollen deshalb bevorzugen:

- 2–4 glaubwürdige Haupt-Cues statt 6–10 Symbolobjekte.
- Raum, Oberfläche, Licht, Bewegung und Situation als primäre Erzählträger.
- Weniger kuratierte Vordergrund-Stillleben.
- Etwas mehr echte Leere ist akzeptabel, wenn das Bild dadurch natürlicher wirkt.
- Keine „alle passenden Objekte einmal sauber nebeneinander“-Kompositionen.

Standardformulierung für weitere Prompts:

> Use fewer arranged props. Prefer a more natural, lightly used real-world setup with only a small number of believable objects. Let the room, route, surface, movement and situation do more of the storytelling. Avoid overly curated foreground clusters of symbolic items. A slightly sparse but believable real-world scene is better than an over-arranged symbolic still life.

### Neue Bewertungsregel: Einzelbild + Systemwirkung

Bilder werden nicht nur einzeln bewertet, sondern gegen das Gesamtset:

- Ist der Visual-Key alleine lesbar?
- Ist das Bild innerhalb des Visual-Systems klar genug von ähnlichen Keys unterscheidbar?
- Wiederholt es Lichtstimmung, Raumtyp, Kameraperspektive oder Requisitenlogik zu stark?
- Wirkt es im späteren Feed wie ein eigenständiger Typ oder wie eine Variante eines bereits vorhandenen Bildes?

Konkretes gelerntes Beispiel:

- `theater_stage` darf warm, bühnenhaft und vorhanggetrieben sein.
- `comedy_cabaret` muss stärker Kleinkunst-/Kulturraumgefühl haben und darf nicht wie Theater plus Mikrofon wirken.
- `film_screening` soll kühler, dunkler und screen-getrieben sein, nicht wieder warmes Bühnen-/Kleinkunstlicht.

### Harte Ablehnungs-Gates

Ein Bild ist nicht ready, wenn eines dieser Probleme sichtbar ist:

- Objektlogikfehler:
  - schwebende oder physisch unklare Objekte
  - falsche Perspektive
  - unmögliche Kontaktpunkte oder Schatten
  - falsche technische Funktion
- Projektions-/Technikfehler:
  - Beamer zeigt sichtbar in die falsche Richtung
  - Linse sitzt auf der falschen Seite
  - Projektionsstrahl ist geometrisch unmöglich
  - Lichtkegel ist unnatürlich breit, volumetrisch oder showartig
- Zu starke KI-/Stillleben-Anmutung:
  - zu viele perfekt arrangierte Symbolobjekte
  - künstlich kuratierte Vordergrund-Cluster
  - Produktkatalog-/Stockfoto-Wirkung
- Rechtliche/visuelle Probleme:
  - lesbare Schrift
  - Logos, Marken, Sponsorzeichen
  - erkennbare Buchcover, Plakate, Kunstwerke oder Figuren
  - identifizierbare Gesichter
  - erkennbare Kinder
- Systemische Wiederholung:
  - zu ähnliche Lichtstimmung zu bereits akzeptierten Keys
  - zu ähnliche Bühne/Raum/Komposition bei eigentlich unterschiedlichen Eventtypen

### Strategiewechsel statt Endlosschleife

Wenn ein Motiv in mehreren Iterationen denselben KI-Fehler produziert, wird nicht weiter mikro-optimiert. Stattdessen wird die Bildlogik geändert.

Beispiel `film_screening`:

- Problem: prominent sichtbarer Beamer erzeugte wiederholt falsche Linsen-/Projektionslogik.
- Lösung: Beamer nicht mehr als Hero-Objekt nutzen.
- Robuste Bildlogik: blanke Leinwand + Stuhlreihen + kühler Screen-Glow + kleiner Vorführraum; Beamer nur sekundär, angeschnitten, unscharf oder außerhalb des Bildes.

Diese Regel gilt künftig allgemein:

> Wenn ein technisches Objekt wiederholt KI-Fehler verursacht, wird es entdominantisiert oder aus dem sichtbaren Hauptfokus entfernt. Der Visual-Key wird dann über robustere Kontextmerkmale erzählt.

### Motivspezifische Erkenntnisse

#### Schützenfest / Vereinsfest

Nicht ausreichend:

- florale Zeltdeko
- Hochzeits-/Sommerfest-Anmutung
- Landpartie-/Blumenausstellungslook

Besser:

- Festzelt-Innenraum
- lange Bierzeltgarnituren
- einfache Theke
- grün-weiße, aber zurückhaltende Vereinsfest-Cues
- funktional, bodenständig, regional

#### Krammarkt / Marktstände

Nicht ausreichend:

- kuratierte Stoff-/Deko-Märkte
- Antikmarkt-/Vintage-Stillleben
- zu einheitliche Pavillonreihen
- zu perfekte Warenpräsentation

Besser:

- normale non-food Krammarktlogik
- einfache Klapptische
- Kunststoffkisten, Kartons, Taschen, Haushaltswaren
- leicht ungleichmäßige, praktische Marktstruktur
- nicht schmutzig, aber realer und weniger kuratiert

#### Book Market / Boekenmarkt

Nicht ausreichend:

- sortierte Antiquariats-/Archivoptik
- Bücher alle ähnlich groß
- nur schöne Holzkisten
- leere, kuratierte Marktästhetik

Besser:

- praktische Kunststoffkisten
- Bananenkartons/Kartons ohne lesbare Markierungen
- einfache Klapptische
- unterschiedliche Buchgrößen
- Bücher stehend, liegend, schräg, gestapelt
- anonyme Besucher als realer Markt-Kontext

#### Business Messe / Infoabend

Nicht ausreichend:

- sterile weiße Flyerhalter
- blanke Mappenwand
- Corporate-Stockfoto
- Hochglanz-Messebau

Besser:

- real genutzter Infotisch
- Laptop, Jacke, Stifte, einzelne Unterlagen, Gesprächssituation
- lokale Halle/Foyer/Kulturraum
- Menschen anonym als Kontext

#### Dance / Music Workshop

Nicht ausreichend:

- großer arrangierter Prop-Cluster mit Speaker, Flasche, Hoodie, Schuhen, Tambourin, Notizbuch und Papier nebeneinander

Besser:

- weniger Gegenstände
- mehr Raum, Holzboden, Spiegel, Bewegung
- anonyme Körperausschnitte
- echte Workshop-Situation statt Symbolsammlung

#### Learning / Science Workshop

Nicht ausreichend:

- Lupe oder andere Objekte wirken schwebend
- zu dekoratives Naturkunde-Stillleben

Besser:

- Lupe klar flach auf Tisch/Notizbuch/Tablett
- wenige plausible Lern-/Naturmaterialien
- physische Kontaktpunkte und Schatten sauber
- echte Workshop-Tischsituation

### Statusentscheidungen aus dem Produktionslauf

Accepted / ready oder ready-fähig nach Review:

- `textile-machines-industry-01.webp`
- `open-air-festival-01.webp`
- `kirmes-funfair-01.webp`
- `parade-festzug-01.webp`
- `shooting-festival-tradition-01.webp` nach zweitem Retry
- `country-fair-rural-01.webp`
- `vehicle-classic-01.webp`
- `textile-exhibition-design-01.webp`
- `art-exhibition-gallery-01.webp`
- `market-stalls-01.webp` nach Krammarkt-/Non-Food-Retry
- `book-market-01.webp` nach Referenzlogik-Retry
- `business-messe-info-01.webp` mit finaler Textprüfung
- `classical-music-01.webp` mit finaler Noten-/Textprüfung
- `theater-stage-01.webp`
- `comedy-cabaret-01.webp` nach Differenzierungs-Retry
- `film-screening-01.webp` nach Strategiewechsel ohne dominanten Beamer
- `literature-reading-talk-01.webp` mit finaler Buchrückenprüfung
- `kids-stage-story-01.webp` mit finaler Cover-Textprüfung
- `learning-science-workshop-01.webp` als zweites Vergleichsbild
- `dance-music-workshop-01.webp` nach reduzierter-Props-Regel
- `running-event-01.webp`

Noch offen / im nächsten Chat fortsetzen:

- `cycling-event-01.webp` wurde als nächster Prompt geliefert; Bildbewertung steht noch aus.
- Danach im nächsten Chat mit dem nächsten noch offenen Visual-Key aus `data/event_visual_generation_batches_phase1.json` fortfahren.

### Prüfvorbehalte für finale Asset-Abnahme

Auch bei `ready` gilt vor Pool-Update immer eine finale Asset-Prüfung nach Export auf `1200×675`:

- keine klar lesbare Schrift
- keine Logos/Marken
- keine erkennbaren Gesichter
- keine erkennbaren Kinder
- keine problematischen Pseudo-Schriften auf Buchrücken, Broschüren, Noten, Covern oder Wandbildern
- keine auffälligen KI-Objektfehler im finalen Crop
- keine zu starke Dopplung zu einem bereits akzeptierten Visual
<!-- === END BLOCK: VISUAL_WORKFLOW_IMAGE_PROMPTING_REVIEW_LEARNINGS_2026_06_02 === -->

<!-- === BEGIN BLOCK: EVENT_VISUAL_POOL_DIVERSIFICATION_PHASE2_2026_06_03 | Zweck: definiert die verbindliche Vorgehensweise nach Phase-1-Pilotbildern; Umfang: Event-Visual-Pool, Variantenproduktion, Bildchat, Review-Gates === -->
## Event Visual Pool Diversification – Phase 2 ab 2026-06-03

Status: verbindlicher Folgeprozess nach der Phase-1-Basisabdeckung.

### Grundentscheidung

Phase 1 erzeugt pro Visual Key ein akzeptiertes Pilotbild.
Phase 2 füllt die Visual-Key-Pools bis zum jeweiligen `target_count` mit sichtbar unterscheidbaren Varianten auf.

Ein Bild pro Visual Key ist nur Grundabdeckung. Für den produktionsreifen Event-Feed braucht jeder Visual Key mehrere `ready`-Bilder, damit zeitlich nahe Events mit gleichem Visual Key nicht wiederholt dasselbe Bild erhalten.

### Arbeitseinheit

Die Arbeitseinheit bleibt der `visual_key`, nicht das einzelne Event.

Nicht gewünscht:
- Event-by-Event-Bilder
- einmalige Sonderbilder für einzelne Termine
- Megaprompts für sehr viele Varianten ohne Review
- CSS-Cropping als Bildrettung
- geplante oder nicht geprüfte Assets live ausspielen

Gewünscht:
- ein kuratierter Bildpool pro Visual Key
- mehrere `ready`-Assets je Key
- stabile Bildsprache innerhalb des Keys
- klar unterscheidbare Motive innerhalb des Pools
- spätere No-Duplicate-/Near-Date-Logik im Event-Feed, sobald ausreichend Varianten vorhanden sind

### Pilotbild-Regel

Das akzeptierte `*-01.webp` ist der Stil- und Qualitätsanker des Visual Keys.

Das Pilotbild darf nicht kopiert werden. Es definiert:
- Qualitätsniveau
- lokale Plausibilität
- dokumentarisch-editoriale Bildsprache
- Anonymitätsstandard
- Card-Tauglichkeit
- rechtliche Sicherheitslogik

Neue Varianten müssen dieselbe Bildsprache halten, aber sichtbar andere konkrete Szenen zeigen.

### Varianten-Regel

Zusätzliche Varianten müssen sich sichtbar unterscheiden über mindestens mehrere dieser Achsen:

- Kameradistanz
- Kamerawinkel
- Komposition
- Vordergrund-/Mittelgrund-/Hintergrundstruktur
- Anzahl und Platzierung anonymer Personen
- Aktivitätsfokus vs. sozialer Kontext
- anderer Moment innerhalb desselben Eventtyps
- andere Raum- oder Ortsdetails
- andere Lichtstimmung innerhalb derselben Motivlogik
- ruhiger vs. dynamischer Bildmoment

Near-Duplicates sind nicht `ready`-fähig.

### Batch-Größen

Die Variantenproduktion erfolgt in kleinen kontrollierten Runden:

- `target_count = 3`: Pilot vorhanden → 2 Zusatzvarianten können in einer Runde entstehen.
- `target_count = 4–5`: Pilot vorhanden → erst 2–3 Zusatzvarianten, Review, dann Rest.
- `target_count = 6–8`: Pilot vorhanden → erst 3 Zusatzvarianten, Review, dann weitere Variantenrunden.

Ziel ist nicht maximale Geschwindigkeit, sondern ein belastbarer Pool mit echter visueller Spannbreite.

### Bildchat-Prompt-Muster

Für Phase 2 soll der Bildchat pro Visual Key dieses Muster verwenden:

    Use the accepted pilot image for visual key [VISUAL_KEY] as the style and quality anchor.
    Do not copy the exact composition.
    Create [X] additional final single images for the same visual key.
    All images must follow the Bocholt erleben event-card rules:
    - 16:9 landscape
    - premium but realistic documentary-local editorial photo style
    - anonymous people only
    - no readable text
    - no logos
    - no brands
    - no copyrighted artwork
    - no identifiable adults or children
    - suitable as mobile PWA event-card images
    The new images must be clearly distinguishable from the pilot and from each other.
    Vary camera distance, camera angle, composition, people placement, activity/social focus, background structure, light mood and moment of action.
    Create genuine pool variants, not near-duplicates.

Pro Visual Key müssen zusätzlich die spezifischen Motivachsen aus `data/event_visual_asset_brief.json` und `data/event_visual_ai_style_guide.json` berücksichtigt werden.

### Ready-Gate

Ein neues Variantenbild wird erst `ready`, wenn alle Punkte erfüllt sind:

- fachlich akzeptiert
- klar dem Visual Key zuordenbar
- sichtbar verschieden von bestehenden `ready`-Bildern desselben Keys
- als echtes `1200x675`-WebP exportiert
- in `data/event_visual_pool.json` eingetragen
- `alt` vorhanden
- keine lesbaren Texte, Marken, Logos oder Sponsorhinweise
- keine erkennbaren Kinder
- keine identifizierbaren Erwachsenen
- keine harten KI-Artefakte
- Card-Crop geeignet
- `tools/audit-visual-contract.py` ohne Fehler

### Reihenfolge nach Phase 1

1. Phase-1-Assets committen.
2. Phase-2-Bedarf aus `target_count - ready_count` je Visual Key ableiten.
3. Phase-2-Batches key-by-key erzeugen.
4. Zuerst häufige und stark belastete Keys auffüllen.
5. Danach kleinere Keys bis Mindestpool schließen.
6. Später Event-Feed-Logik ergänzen, damit zeitlich nahe Events desselben Keys nicht dasselbe Bild erhalten.

<!-- === END BLOCK: EVENT_VISUAL_POOL_DIVERSIFICATION_PHASE2_2026_06_03 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_DIVERSITY_FREEZE_2026_06_08 | Zweck: ergänzt dauerhafte Regeln zu Motiv-Dubletten, Pool-Diversität und Anti-Repeat-Grenzen; Umfang: Event-Visual-Workflow === -->
## Event Visual Pool Diversity + Duplicate Control – Freeze 2026-06-08

Status: verbindliche Ergänzung für weitere Event-Visual-Integration.

### Grundsatz

Ein brauchbares Bild ist nicht automatisch ein dauerhaftes `ready`-Bild.

Für Event-Cards zählt nicht nur die Einzelbildqualität, sondern auch die Wirkung im Feed:

- Wiederholt sich ein Motiv zu stark?
- Wirken mehrere unterschiedliche Dateien wie dieselbe Szene?
- Entsteht direkt oder kurz hintereinander der Eindruck von Bild-Dopplung?
- Hat ein häufig auftretender `visual_key` genug eigenständige Varianten?

### Status-Semantik für Motiv-Dubletten

`blocked` kann zwei Bedeutungen haben:

1. harte Ablehnung wegen Qualitäts-/Rechts-/Regelproblem,
2. Ausschluss aus dem `ready`-Pool wegen Near-Duplicate-Motiv.

Für Event-Visuals gilt deshalb:

- Ein geblocktes Bild kann grundsätzlich brauchbar gewesen sein.
- Bei `blocked_reason: near_duplicate_motif_in_same_visual_key_pool` ist der Grund nicht schlechte Qualität, sondern zu große Ähnlichkeit zu einem anderen `ready`-Bild im selben Visual-Key.
- Solche Dateien werden nicht gelöscht, sondern nachvollziehbar im Pool behalten.
- Nur `ready` wird prominent in Feed-Cards genutzt.

### Anti-Repeat-Grenze

Die Feed-Logik kann gleiche Bild-IDs bzw. gleiche Dateien im kurzen Fenster vermeiden.

Sie erkennt aber nicht zuverlässig:

- zwei verschiedene Dateien mit fast identischem Motiv,
- gleiche Kameraperspektive mit leicht anderer Datei,
- sehr ähnliche Szene/Lichtstimmung im selben Visual-Key.

Daher bleibt zusätzlich zur UI-Logik ein Pool-Audit nötig.

### Prüfpflicht vor einem Visual-Freeze

Vor einem Freeze des Event-Visual-Pools sind mindestens diese Punkte zu prüfen:

1. Visual Contract Audit:
   - `python3 tools/audit-visual-contract.py`
   - Erwartung: `Errors: none`
2. Event Visual Pool Audit:
   - `python3 scripts/audit-event-visual-pool.py`
   - Erwartung: Pool-Struktur konsistent
3. Ready-Near-Duplicate-Audit:
   - starke Near-Duplicate-Paare innerhalb desselben `visual_key` müssen `0` sein oder bewusst dokumentiert werden
4. Sequenzsimulation:
   - keine gleiche Bildauswahl innerhalb eines kurzen Feed-Fensters
   - für den aktuellen Freeze wurde ein 6-Card-Fenster geprüft
5. Asset-Prüfung:
   - neue Card-Assets müssen WebP sein
   - Zielmaß: `1200x675`
   - keine Rohbildordner committen

### Pool-Diversität

Häufige Visual-Keys brauchen mehr Ready-Varianten als seltene Keys.

Besonders kritisch sind:

- `city_festival_street`
- `live_music_stage`
- `city_tour_history`
- `family_play_outdoor`
- weitere Keys mit dichter Eventfolge im aktuellen Feed

Wenn eine Dublettenbereinigung einen Pool zu klein macht, ist die richtige Reihenfolge:

1. Motiv-Dubletten aus `ready` entfernen.
2. Poolgröße prüfen.
3. Gute, eigenständige Ersatzbilder importieren.
4. Erst danach erneut Sequence- und Near-Duplicate-Audit laufen lassen.

Nicht korrekt:

- pauschal alle ähnlichen Bilder blocken und dadurch häufige Keys unterversorgen,
- geblockte Dubletten als Qualitätsverlust missverstehen,
- Rohbilder oder temporäre Importordner ins Repo übernehmen.

### Freeze 2026-06-08

Der Freeze-Stand auf `staging` basiert auf:

- `c9cd092` – Anti-Repeat-Logik in `js/events.js`
- `3387568` – Motiv-Dubletten-Bereinigung und kuratierte Ersatzbilder

Finale Prüfwerte:

- `ready_items=125`
- `strong_near_duplicate_pairs=0`
- `same_selected_image_within_6_card_window=0`
- neue Ersatzassets: WebP `1200x675`
- Visual Contract Audit: `Errors: none`

Dieser Stand ist für den aktuellen Staging-Feed vorerst eingefroren.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_DIVERSITY_FREEZE_2026_06_08 === -->

<!-- === BEGIN BLOCK: ACTIVITY_VISUAL_PREMIUM_CONTRACT_V1_2026_06_09 | Zweck: definiert den dauerhaften Premium-Prozess fuer exklusive Activity-Bilder; Umfang: Activities, Prompting, Rechts-/Plausibilitaetsregeln === -->
## Activity Visual Premium Contract V1 – exklusiver Activity-Bildprozess

Status: Prozessvertrag vor Massenproduktion.

### Produktentscheidung

Activity-Bilder werden nicht aus dem Event-Visual-Pool übernommen und nicht zwischen mehreren Activities geteilt.

Zielzustand:

- Jede Activity erhält ein eigenes exklusives Premium-Hauptbild.
- Dieses Bild darf innerhalb derselben Activity auf Home/Heute, Aktivitätenseite und Detailpanel erscheinen.
- Dasselbe Bild darf nicht bei Events und nicht bei anderen Activities verwendet werden.
- `usable` ist kein Endzustand, sondern nur Übergang.
- Finaler Premium-Status ist `ready`.

### Rechtlich saubere Wiedererkennbarkeit

Activity-Bilder sollen zur jeweiligen Activity passen und sie wiedererkennbar machen, aber rechtlich und produktionslogisch sauber bleiben.

Daher gilt:

- Wiedererkennbarkeit entsteht bevorzugt über Activity-Charakter, Nutzung, Stimmung, Materialien, Landschafts-/Stadttyp und lokale Plausibilität.
- KI-generierte Activity-Bilder sind standardmäßig symbolisch-activity-spezifisch, nicht dokumentarisch.
- Ohne eigenes oder eindeutig freigegebenes Referenzmaterial darf kein Bild behaupten, ein exaktes Foto eines konkreten echten Ortes zu sein.
- Keine Kopie fremder Foto-Kompositionen, Logos, Markenauftritte, Plakate, lesbarer Beschilderung, geschützter Kunstwerke oder anderer Drittmaterialien.
- Wenn ein konkreter realer Ort dokumentarisch gezeigt werden soll, braucht es eigenes oder belastbar freigegebenes Bildmaterial.
- Bei Unsicherheit wird nicht `ready` vergeben, sondern `needs_review` oder `blocked`.

Leitsatz:

> Wiedererkennbar durch Activity-Charakter und lokale Plausibilität, nicht durch riskante Kopie eines fremden Fotos oder eine falsche dokumentarische Ortsbehauptung.

### Dauerhafte Arbeitsdateien

- `ACTIVITY_VISUAL_WORKFLOW.md` ist der operative Prozessvertrag für Activity-Bilder.
- `ACTIVITY_VISUAL_PROMPT_KIT.md` ist der wiederverwendbare Prompt-Baukasten.
- `VISUAL_WORKFLOW.md` bleibt der strategische Visual-Mastervertrag.

### Anchor-Test vor Massenproduktion

Vor der Produktion aller Activity-Bilder wird das Prompt-Kit an mindestens zwei Anchor-Activities geprüft:

1. `bocholter-innenstadt-erleben`
2. `stadtwald-bocholt-erleben`

Erst wenn beide Tests stabil Premium-Ergebnisse liefern, wird das Prompt-Kit als `v1.0` eingefroren.

### Nicht-Ziel dieses Blocks

Dieser Block integriert noch keine neuen Bilder und ändert keine Runtime-Logik. Er hält den dauerhaften Prozess fest, bevor die eigentliche Premium-Bildproduktion startet.

### Activity-Visual-Sourcing-Gate

<!-- === BEGIN BLOCK: ACTIVITY_VISUAL_SOURCING_GATE_V1_2026_06_10 | Zweck: ergänzt den Activity-Premium-Contract um die Vorentscheidung KI-geeignet vs. reales/lizenziertes/freigegebenes Foto erforderlich === -->

Nicht jede Activity ist für symbolische KI-Premiumbilder geeignet.

Vor jeder Activity-Bildproduktion muss eine Sourcing-Strategie festgelegt werden:

- `symbolic_ai_ok`
- `own_or_licensed_real_photo_required`
- `official_permission_candidate`
- `blocked_until_photo_available`

Harte Produktregel:

> Ein schönes, aber objektiv falsches Bild ist nicht premiumfähig.

Symbolische KI-Bilder sind nur zulässig, wenn die Activity über Atmosphäre, Nutzung, Landschafts-/Ortstyp oder allgemeine Aufenthaltsqualität erkennbar wird, ohne eine konkrete falsche Orts-/Objektbehauptung zu erzeugen.

KI-geeignet sind typischerweise atmosphärische Fälle wie Wald-/Naturwege, Seen/Ufer, Rad- und Wanderrouten oder allgemeine Naherholungsorte.

Reale, lizenzierte oder offiziell freigegebene Fotos sind erforderlich, wenn Nutzer erwarten, dass das Bild den konkreten Ort, das konkrete Objekt oder die konkrete Ausstattung wiedererkennbar zeigt. Das gilt besonders für konkrete Spielplätze, Museen, Burgen, Schlösser, markante Gebäude, Innenräume, Kunstwerke, Skulpturen oder spezielle Anlagen.

Fremde Netzbilder dürfen nicht 1:1 nachgebaut werden. Zulässig ist nur abstrakte Inspiration auf Motiv-, Qualitäts- oder Stimmungsniveau mit eigenständiger Komposition.

Wenn keine eigene Aufnahme, keine belastbare Lizenz und keine schriftliche Freigabe verfügbar ist, bleibt die Activity-Bildfreigabe `blocked_until_photo_available`.

Lernfall: `suderwicker-maerchenspielplatz` ist nicht sinnvoll per symbolischer KI als Premium-Endbild lösbar, weil generische KI-Spielplätze eine falsche dokumentarische Ortswirkung erzeugen. Für diese Activity gilt: reales/lizenziertes/freigegebenes Ortsfoto erforderlich; KI nicht als Premium-Ready-Endbild.

<!-- === END BLOCK: ACTIVITY_VISUAL_SOURCING_GATE_V1_2026_06_10 === -->

<!-- === END BLOCK: ACTIVITY_VISUAL_PREMIUM_CONTRACT_V1_2026_06_09 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_FIT_CONTRACT_2026_06_12 | Zweck: dauerhafter Vertrag fuer motivgenaue Eventbilder; Umfang: Event-Visual-Key, Visual-Motif, Gap-Backlog, Fallback-Regeln === -->
## Event Visual Motif Fit Contract – Freeze 2026-06-12

Status: verbindlicher Zielvertrag für die nächste Event-Visual-Ausbaustufe.

### Grundprinzip

Ein Eventbild gilt erst dann als premiumtauglich, wenn es nicht nur zur groben Kategorie, sondern auch zum konkreten Eventtyp passt.

Der vorhandene `visual_key` bleibt die stabile grobe Bildfamilie. Für konkrete Untertypen wird zusätzlich `visual_motif` eingeführt.

### Datenmodell

#### `visual_key`

Grobe Bildfamilie.

Beispiele:
- `indoor_sport_competition`
- `classical_music`
- `literature_reading_talk`
- `city_festival_street`

#### `visual_motif`

Konkretes Motiv innerhalb eines `visual_key`.

Beispiele:
- `fencing`
- `darts`
- `handball`
- `volleyball`
- `choir`
- `organ_concert`
- `poetry_slam`

Regeln:
- Lowercase snake_case.
- Nur setzen, wenn Titel, Beschreibung, Quelle oder redaktionelle Prüfung das Motiv klar tragen.
- Unsichere Motive nicht erraten.
- Ein fehlendes Motivbild ist ein Backlog-Fall, kein Grund für ein fachlich falsches Bild.

#### `visual_asset_status`

Empfohlene Zielwerte:
- `ok`: passendes Motivbild ist vorhanden.
- `needs_asset`: konkretes Motiv erkannt, aber kein passendes `ready`-Bild vorhanden.
- `review`: Motiv oder Bildauswahl ist unsicher und muss redaktionell geprüft werden.

### Pool-Regel

`data/event_visual_pool.json` darf weiterhin nach `visual_key` organisiert bleiben.

Bilder können zusätzlich `visual_motif` tragen.

Beispiel:
- Pool: `indoor_sport_competition`
- Bild: `indoor-sport-competition-fencing-01`
- `visual_motif`: `fencing`

Generische Bilder innerhalb eines Keys sollen als neutral erkennbar bleiben, z. B.:
- `visual_motif`: `neutral_indoor_sport`

### Auswahlregel

Bei Events mit eindeutigem `visual_motif` gilt:

1. Exaktes `ready`-Bild mit gleichem `visual_key` und gleichem `visual_motif` bevorzugen.
2. Wenn kein exaktes Motivbild existiert, nur ein neutrales generisches Bild desselben `visual_key` verwenden, wenn es fachlich nicht irreführend ist.
3. Kein anderes spezifisches Unter-Motiv verwenden.
4. Wenn kein sicherer Fallback vorhanden ist, `needs_asset` oder `review` ausgeben.

### Harte Negativregel

Diese Zuordnungen sind unzulässig:

- Fechten → Handballbild
- Fechten → Volleyballbild
- Fechten → Badmintonbild
- Darts → Hallenballsportbild
- Chor → beliebiges Instrumentalbild
- konkrete Sportart → andere konkrete Sportart
- konkreter Eventtyp → sichtbar anderes konkretes Motiv

### Gap-Backlog

Wenn ein konkretes Motiv erkannt wird, aber kein passendes Bild vorhanden ist, muss der Fall sichtbar werden.

Zieldatei:
- `data/event_visual_gap_backlog.tsv`

Ziel:
- KI-/Inbox-Suche deckt Bildlücken auf.
- Fehlende Motivbilder werden als Arbeitsaufgabe sichtbar.
- Neue Bilder werden gezielt für reale Lücken generiert.

Empfohlene Spalten:
- `status`
- `priority`
- `event_title`
- `event_date`
- `visual_key`
- `visual_motif`
- `problem`
- `recommended_action`
- `source_url`
- `notes`

### Start-Scope

Der erste Umsetzungsbereich ist `indoor_sport_competition`, weil hier die größte Gefahr fachlich falscher Bilder besteht.

Erste Motive:
- `neutral_indoor_sport`
- `badminton`
- `handball`
- `volleyball`
- `table_tennis`
- `darts`
- `fencing`

Spätere Kandidaten:
- `classical_music`
- `literature_reading_talk`
- `kids_stage_story`
- `business_messe_info`

### Redaktionsregel

Lieber ein sichtbarer Gap als ein falsches Premiumbild.

Ein fehlendes Bild ist kurzfristig akzeptabel, wenn es als Aufgabe sichtbar wird. Ein sichtbar falsches Bild beschädigt dagegen die wahrgenommene Qualität der Plattform.

### Produktions-Tracking vor Integration

Akzeptierte oder ausgewählte KI-Bildkandidaten, die noch nicht als WebP-Dateien im Repo integriert sind, werden in `data/event_visual_phase2_acceptance_notes.json` festgehalten. Diese Datei ist der Zwischenstand zwischen Bildproduktion und finaler Pool-Integration.

Regeln:
- `data/event_visual_pool.json` wird erst aktualisiert, wenn die WebP-Dateien wirklich unter `assets/event-visuals/` liegen.
- `data/event_visual_gap_backlog.tsv` bleibt ein generierter Bedarf-/Gap-Abgleich und wird nicht als manueller Produktionsnotizzettel verwendet.
- Für neue Kandidaten müssen `visual_key` und kanonisches `visual_motif` aus `scripts/event_visual_motifs.py` gesetzt werden.
- `production_status=downloaded_confirmed` bedeutet: Der Download wurde im Produktionschat explizit bestätigt.
- `production_status=selected_pending_confirmation` bedeutet: Das Bild wurde empfohlen, aber der Download ist noch nicht eindeutig bestätigt.
- Vor jedem neuen größeren Produktionsblock wird der Stand gegen die kanonische Motivliste und die bereits vorhandenen `ready`-Assets abgeglichen.

### Eventdatenquelle für Gap-Backlog

Für Event-Visual-Gaps gilt die Sheet-first-Regel:

- Kanonische Quelle für redaktionelle Events ist das Google Sheet, Tab `Events`.
- `data/events.json` ist nur ein generiertes Feed-Artefakt und lokal nur belastbar, wenn es frisch aus dem Sheet exportiert oder im Deploy erzeugt wurde.
- `/api/events/public.php` ergänzt freigegebene DB-/Veranstalter-Events, ersetzt aber nicht den Sheet-Eventbestand.
- `scripts/build-event-visual-gap-backlog.py` ist nur dann als aktueller Gap-Nachweis belastbar, wenn seine Eingabedaten dem aktuellen Sheet-/Deploy-Stand entsprechen.

Vor jeder Aussage wie „es gibt keine offenen Event-Visual-Gaps“ muss daher klar sein, welcher Eventdatenstand geprüft wurde.

### Event-Visual-Matrix-Arbeitszyklus

Die Event-Visual-Matrix ist das zentrale generierte Steuerartefakt für Motivbedarf, Kandidatenstand, Produktionspriorität und Integrationsstatus.

Ziel-Datei:

- `data/event_visual_motif_matrix.tsv`

Ziel-Script:

- `scripts/build-event-visual-motif-matrix.py`

Die Matrix wird nicht manuell gepflegt. Sie wird aus diesen Quellen generiert:

1. aktueller Sheet-Export `data/events.tsv`,
2. kanonische Taxonomie aus `scripts/event_visual_motifs.py`,
3. integrierte Ready-Bilder aus `data/event_visual_pool.json`,
4. akzeptierte oder ausgewählte Kandidaten aus `data/event_visual_phase2_acceptance_notes.json`.

Die Matrix muss pro `visual_key` / `visual_motif` mindestens ableiten:

- ob das Motiv im aktuellen Sheet gebraucht wird,
- konkrete Beispiel-Events,
- ob ein `ready`-Bild vorhanden ist,
- ob ein akzeptierter Kandidat vorhanden ist,
- ob ein echter Gap besteht,
- welche nächste Aktion gilt.

Zulässige abgeleitete Aktionsstände:

- `ready`: passendes integriertes Bild vorhanden, keine Aktion.
- `candidate_to_integrate`: aktueller Bedarf und akzeptierter Kandidat vorhanden, Integration vorbereiten.
- `gap_to_produce`: aktueller Bedarf, aber kein Ready-Bild und kein Kandidat vorhanden.
- `review_rules`: Eventbedarf vorhanden, aber Zuordnung oder Inferenz ist unsicher bzw. wahrscheinlich falsch.
- `parked_candidate`: Kandidat existiert, aber aktuell kein echter Bedarf.
- `not_needed`: Taxonomie-Motiv existiert, aber aktueller Sheet-Stand benötigt es nicht.

Arbeitsregeln:

1. Vor jeder neuen Bildproduktion wird die Matrix aus dem aktuellen Sheet-Stand neu gebaut.
2. Ein Bildprompt darf nur aus `gap_to_produce` entstehen oder aus bewusst dokumentiertem strategischem Poolausbau.
3. Nach jedem bewerteten Batch mit akzeptierten Bildern wird `data/event_visual_phase2_acceptance_notes.json` aktualisiert und die Matrix neu gebaut.
4. Nach jeder Asset-Integration werden `data/event_visual_pool.json`, Matrix und Gap-Backlog neu gebaut.
5. Maximal ein akzeptierter Bildbatch darf ohne Repo-Checkpoint im Chat verbleiben. Mehrere akzeptierte Batches ohne Repo-Stand sind nicht zulässig.
6. Wenn Matrix, Kandidatenlog und Pool nicht aktualisiert und committed sind, gilt der Stand nicht als belastbar erledigt.

Die Matrix ersetzt nicht die Taxonomie. Sie ist der aktuelle Arbeitsstand aus Taxonomie, Sheet-Bedarf, Pool und Kandidatenlog.

Die Matrix ersetzt nicht den Gap-Backlog. Der Gap-Backlog bleibt die fokussierte Liste offener Motivlücken; die Matrix ist die umfassendere Steuerliste inklusive `ready`, Kandidaten, geparkten Motiven und Nicht-Bedarf.

### Bedarfsquelle vor neuer Bildproduktion

Ein kanonisches `visual_motif` ohne `ready`-Bild ist allein noch kein Produktionsauftrag.

Vor jedem neuen Bild-Batch muss die Bedarfsquelle explizit benannt werden:

1. offener Eintrag in `data/event_visual_gap_backlog.tsv`,
2. konkret belegter Eventbedarf aus aktuellen Eventdaten,
3. bewusst entschiedener strategischer Poolausbau.

Wenn keine dieser drei Quellen vorliegt, wird kein neuer Batch-Prompt erstellt. In diesem Fall werden vorhandene Kandidaten höchstens als Reserve bzw. strategischer Pool-Kandidat dokumentiert, aber nicht als aktuelle Pflichtproduktion behandelt.

Wichtig:
- `scripts/event_visual_motifs.py` definiert das erlaubte Vokabular, aber keine Produktionspriorität.
- `data/event_visual_pool.json` zeigt den aktuellen Asset-Stand, aber keine automatische To-do-Liste.
- `data/event_visual_gap_backlog.tsv` ist die primäre Quelle für echte aktuelle Motivlücken.
- Vor jedem Prompt muss klar gesagt werden, ob es sich um Pflichtbedarf oder strategischen Vorrat handelt.

### Implementierungsreihenfolge

1. Dokumentierter Vertrag.
2. Motiv-Taxonomie für `indoor_sport_competition`.
3. Pool-Metadaten für vorhandene Bilder ergänzen.
4. Inferenzlogik um `visual_motif` erweitern.
5. Gap-Backlog erzeugen.
6. Resolver/Fallback-Regeln härten.
7. Fehlende Bilder gezielt generieren und integrieren.
8. Audit um Motiv-Fit-Prüfungen ergänzen.

### Akzeptanzkriterien

- Konkrete Eventtypen bekommen kein falsches spezifisches Unter-Motiv.
- Fehlende Motivbilder landen im Backlog.
- Generische Fallbacks sind nur erlaubt, wenn sie fachlich neutral bleiben.
- `visual_key` bleibt stabil.
- `visual_motif` wird schrittweise ergänzt, ohne das bestehende System unnötig aufzublähen.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_FIT_CONTRACT_2026_06_12 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_CLOSURE_2026_06_18 | Zweck: dokumentiert Abschluss der aktuellen Event-Visual-Motiv-Gaps nach finalem Restbatch; Umfang: Matrix-Zielstand, Produktionsgrenze, Regelentscheidung === -->
## Event Visual Motif-Fit – Abschluss aktueller Sheet-Gaps (2026-06-18)

Status nach finalem Restbatch: commit-/deployfertig.

Abgeschlossen wurde nicht jede theoretische Unterkategorie der Taxonomie, sondern der aktuelle aus dem Sheet belegte Bedarf.

Umgesetzter Restscope:
- `business_messe_info / info_evening` mit `motif-gap-info-evening-01`.
- `open_air_festival / market_square_open_air` mit `motif-gap-market-square-open-air-01`.
- `city_festival_street / open_house_city_services` mit `motif-gap-open-house-city-services-01`.

Regelentscheidung:
- `open_house_city_services` ist kein dauerhafter Review-Fall mehr.
- Das Motiv ist als spezifisches Motiv unter `city_festival_street` zulässig, wenn es um Tag-der-offenen-Tür-/Stadtservice-/Info-Stand-Kontexte geht.

Zielstand nach Anwendung dieses Pakets:
- `gap_to_produce: 0`
- `candidate_to_integrate: 0`
- `review_rules: 0`
- `ready: 69`
- `parked_candidate: 4`
- `not_needed: 27`
- Gap-Backlog aus aktuellem Sheet-Export: `0` offene Zeilen

Arbeitsgrenze:
- Keine weitere Event-Visual-Produktion aus reiner Vollständigkeitslogik.
- Neue Bildproduktion nur bei neuem Sheet-Bedarf, offenem Gap-Backlog oder bewusst entschiedenem strategischem Poolausbau.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_CLOSURE_2026_06_18 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_FIT_QA_RULEPATCH_2026_06_18 | Zweck: ergaenzt den Abschlussprozess um fachliche Motiv-Fit-QA nach Asset-Abdeckung; Umfang: Regelhärtung ohne neue Bildproduktion === -->
## Event Visual Motif-Fit-QA – Regelhärtung nach Asset-Abdeckung (2026-06-18)

Nach der technischen Abdeckung (`gap_to_produce: 0`, keine fehlenden Bilder) ist zusätzlich eine fachliche Motiv-Fit-QA erforderlich.

Prüfziel:
- Nicht nur belegen, dass ein Bild vorhanden ist.
- Belegen, dass der aus Titel/Beschreibung/Kategorie/Ort abgeleitete `visual_key` und `visual_motif` zum Eventtyp passen.

Regelpaket 2026-06-18:
- Stark eindeutige zusammengesetzte Begriffe ohne harte Wortgrenzen behandeln, z. B. `Rosenmontagszug`, `Familienschützenfest`, `Oldtimerfestival`, `Puppenspieltage`, `Filmvorführung`, `Segwaytouren`.
- Tour-/Bewegungsformate vor generischer Natur-Kategorie priorisieren, wenn der Eventtyp klar `Fahrradtour`, `Radtour`, `Segwaytour`, `Spaziergang` oder `Wanderung` ist.
- Business-/Info-Kontexte wie `Markterschließung Niederlande` vor generischer Workshop-Logik behandeln.
- Travel-/Vortragsformate wie `KANAREN - Sieben auf einen Streich` als Talk-/Lesungsnahes Motiv statt allgemeiner Lokalgeschichte behandeln.
- `Living History` im Textilwerk als lokales Geschichts-/Museumsformat statt Maschinenmotiv behandeln.

Wichtig:
- Diese QA erzeugt keine neuen Bilder, solange ein fachlich passender Ready-Fallback vorhanden ist.
- Spezifische theoretische Motive ohne Ready-Bild werden nicht automatisch aktiviert, wenn ein neutraler Ready-Fallback fachlich ausreichend ist.
- Nach jeder Regelhärtung müssen Matrix und Gap-Backlog neu gebaut werden.

Zielstand dieses Regelpakets:
- `gap_to_produce: 0`
- `candidate_to_integrate: 0`
- `review_rules: 0`
- Gap-Backlog: `0` offene Zeilen
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_FIT_QA_RULEPATCH_2026_06_18 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_FINAL_RULE_HIERARCHY_2026_06_18 | Zweck: fixiert finale Regelhierarchie nach sichtbarer Motiv-Fit-QA; Umfang: Format-vor-Ort, Fuehrung-vs-Tour, Kategorie-Fallback === -->
### Event-Visual-Regelhierarchie nach sichtbarer Motiv-Fit-QA

Verbindliche Reihenfolge:
1. Harte Eventtypen zuerst: Kirmes, Schützenfest, Parade/CSD, Darts/Fechten, Oldtimer, Markt-/Buch-/Food-Formate.
2. Konkretes Format schlägt Ort: Comedy im TextilWerk bleibt Comedy; Film im Museum bleibt Film; Open-Air am Marktplatz bleibt Open-Air.
3. Führungen werden differenziert:
   - Historisch/thematisch/sagenbezogen → `city_tour_history`.
   - Echte Aktivformate wie Fahrradtour, Segway, Wanderung, Spaziergang → `active_route_tour`.
   - Natur-/Wasser-/Wildlife-Kontext → `nature_learning_wildlife`.
   - Generische Begriffe wie `Tour` oder `Rundgang` sind keine ausreichenden Trigger.
4. Kinder/Familie ist kein pauschales Outdoor-Bild:
   - Puppenspiel/Kindertheater/Vorlesen → `kids_stage_story`.
   - Spiel-/Escape-/Bastel-/Workshopformate → `creative_making_workshop`.
   - Wissenschaft/Ökosystem/Junge Uni → `learning_science_workshop`.
5. Kategorie und Ort sind Fallbacks und dürfen klare Titel-/Beschreibungssignale nicht überschreiben.

Belegfall:
- `Auf dem Holzweg – Klumpenführung durch Rhede` darf kein Aktiv-/Sportbild erhalten.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_FINAL_RULE_HIERARCHY_2026_06_18 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_RESOLVER_FINAL_2026_06_18 | Zweck: fixiert motivgenauen Runtime-Resolver als verbindliche Event-Visual-Invariante; Umfang: visual_key + visual_motif + Fallback-Reihenfolge === -->
### Event-Visual-Invariante: Runtime nutzt `visual_key` und `visual_motif`

Verbindlich:
1. Eventdaten aus dem Sheet erhalten im Build `visual_key` und `visual_motif`.
2. Frontend-Resolver suchen zuerst exakt passende Bilder mit gleichem `visual_motif`.
3. Fehlt ein exaktes Motivbild, wird ein neutrales/fallbackfähiges Bild aus demselben `visual_key` bevorzugt.
4. Ein anderes spezifisches Motiv derselben Pool-Familie ist kein bevorzugter Erstfallback.
5. Neue Bildproduktion ist erst nötig, wenn Matrix/Backlog trotz dieser Resolverlogik einen echten Gap zeigt.

Beispiele:
- `seasonal_martinsmarkt` darf nicht als Flohmarktbild erscheinen.
- `darts` darf nicht als Volleyball-/Handballbild erscheinen.
- `puppet_theater` darf nicht als allgemeines Familienbild erscheinen.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_RESOLVER_FINAL_2026_06_18 === -->

<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_FIT_CLOSURE_RULE_2026_06_18 | Zweck: fixiert Abschlussregel fuer Event-Visual-Motif-Fit nach motivgenauem Resolver; Umfang: Wiederaufnahme nur bei neuem Bedarf oder konkretem sichtbarem Fehler === -->
### Abschlussregel: Event Visual Motif-Fit

Der Event-Visual-Motif-Fit gilt für den aktuellen Sheet-/Staging-Stand als abgeschlossen.

Arbeitsblock nicht erneut öffnen für:
- theoretische Vorratsproduktion aller möglichen Unterkategorien,
- rein ästhetische Varianten ohne konkreten Fehlfall,
- generische Bilddiversifizierung ohne belegten Bedarf.

Arbeitsblock nur erneut öffnen bei:
- neuem Sheet-Event mit offenem Matrix-/Backlog-Gap,
- fehlendem oder kaputtem Bild,
- konkret sichtbarer fachlicher Fehlzuordnung,
- bewusster strategischer Poolausbau-Entscheidung.

Verbindlicher Abschlussstand:
- Frontend wählt Event-Visuals motivgenau über `visual_motif`.
- Fallbacks dienen nur der technischen Robustheit, nicht als primäre Bildlogik.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_VISUAL_MOTIF_FIT_CLOSURE_RULE_2026_06_18 === -->


<!-- === BEGIN BLOCK: VISUAL_WORKFLOW_EVENT_FEED_VISUAL_DIVERSITY_2026_07_02 | Zweck: dokumentiert die Feed-Kontext-Erweiterung fuer Event-Visuals; Umfang: motivgenaue Auswahl plus Diversity-Fallback bei sichtbarem Wiederholungsrisiko === -->
### Event-Feed Visual Diversity – sichtbarer Feed statt reine Asset-Existenz

Premium-Invariante:
- Die Runtime bleibt motivgenau: exakte `visual_motif`-Bilder werden bevorzugt.
- Ein einzelnes exaktes Motivbild reicht aber nicht fuer mehrere sichtbare Karten im gleichen Feed-Umfeld.
- Wenn ein exakter Motivpool weniger als drei Ready-Bilder hat, darf der Resolver neutrale/fallbackfaehige Bilder desselben `visual_key` ergaenzen.
- Bei `live_music_stage` duerfen zusaetzlich definierte nahe Buehnen-/Konzertmotive als letzte Diversity-Stufe genutzt werden.

Begruendung:
- Der sichtbare Feed muss redaktionell kuratiert wirken, auch wenn mehrere Events desselben Motivs oder einer Dachveranstaltung direkt hintereinander erscheinen.
- Motiv-Fit und Feed-Diversity sind keine Gegensaetze: Die Erweiterung bleibt gewichtet, regelbasiert und innerhalb fachlich passender Bildfamilien.

Audit:
- `scripts/audit-event-feed-visual-diversity.py` simuliert die Feed-Bildauswahl und meldet sichtbare Bildwiederholungen, niedrige Motivdiversitaet und moegliche Serien-/Dachveranstaltungscluster.
- Der Workflow laeuft bewusst nicht-blockierend, damit echte redaktionelle Serien zuerst sichtbar und dann gezielt modelliert werden koennen.
<!-- === END BLOCK: VISUAL_WORKFLOW_EVENT_FEED_VISUAL_DIVERSITY_2026_07_02 === -->
