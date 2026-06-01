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
