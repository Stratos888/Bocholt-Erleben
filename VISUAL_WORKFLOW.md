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
