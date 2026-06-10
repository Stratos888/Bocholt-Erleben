# Activity Visual Sourcing Matrix V1

<!-- === BEGIN BLOCK: ACTIVITY_VISUAL_SOURCING_MATRIX_V1_2026_06_10 | Zweck: dokumentiert die Vorentscheidung pro Activity, ob symbolische KI-Bilder zulässig sind oder reale/lizenzierte/freigegebene Fotos benötigt werden === -->

## Zweck

Diese Matrix ist eine interne Arbeitsgrundlage für den Premium-Activity-Visual-Workstream.
Sie entscheidet noch nicht über finale Assets, sondern nur über die passende Bildbeschaffungsstrategie pro Activity.

Harte Regel aus dem Activity-Visual-Sourcing-Gate:

> Ein schönes, aber objektiv falsches Bild ist nicht premiumfähig.

## Strategien

| Strategie | Bedeutung | Premium-Endbild möglich? |
|---|---|---|
| `symbolic_ai_ok` | Die Activity kann atmosphärisch, über Nutzung oder allgemeinen Ortstyp ehrlich dargestellt werden, ohne eine falsche konkrete Orts-/Objektbehauptung zu erzeugen. | Ja, nach Review. |
| `own_or_licensed_real_photo_required` | Der konkrete Ort, das konkrete Gebäude, die konkrete Ausstattung oder eine wiedererkennbare Anlage muss real/lizenziert gezeigt werden. | Nur mit eigener Aufnahme oder belastbarer Lizenz. |
| `official_permission_candidate` | Geeignetes Bildmaterial existiert wahrscheinlich bei Stadt, Betreiber, Verein, Presse, Fotograf oder Rechteinhaber. | Nur mit schriftlicher Freigabe. |
| `blocked_until_photo_available` | Aktuell kein rechtlich und fachlich geeignetes Premiumbild verfügbar. | Nein. Nicht prominent verwenden. |

## Status-Hinweise aus dem bisherigen Anchor-Test

- `stadtwald-bocholt-erleben`: symbolische KI grundsätzlich geeignet; akzeptierter `ready`-Kandidat aus Anchor-Test vorhanden.
- `hilgelo-erleben`: symbolische KI grundsätzlich geeignet; akzeptierter `ready`-Kandidat aus Anchor-Test vorhanden. Das eigene Sonnenuntergangsfoto ist nicht Premium-Endstand.
- `hohenhorster-berge-entdecken`: symbolische KI grundsätzlich geeignet; akzeptierter `ready`-Kandidat aus Anchor-Test vorhanden.
- `suderwicker-maerchenspielplatz`: symbolische KI als Premium-Endbild verworfen; konkreter Spielplatz mit spezifischer Ausstattung, daher reales/lizenziertes/freigegebenes Ortsfoto erforderlich.
- `bocholter-innenstadt-erleben`: echtes eigenes Foto bevorzugt; KI ist wegen Fake-Ortswirkung und generischer Innenstadtoptik kritisch.

## Matrix

| # | Activity ID | Titel | Sourcing-Strategie | Begründung | Nächster Bildschritt |
|---:|---|---|---|---|---|
| 1 | `aasee-erleben` | Aasee erleben | `symbolic_ai_ok` | See, Ufer, Rundweg und Naherholung sind atmosphärisch darstellbar, solange keine konkrete falsche Aasee-Perspektive behauptet wird. | KI-Premiumbild möglich; reales/lizenziertes Foto bevorzugen, wenn stark verfügbar. |
| 2 | `burloer-venn-entdecken` | Burloer Venn entdecken | `symbolic_ai_ok` | Venn, Moor, Wege und Naturbeobachtung sind typologisch ehrlich darstellbar. | KI-Moor-/Venn-/Naturweg-Motiv planen. |
| 3 | `hilgelo-erleben` | Hilgelo erleben | `symbolic_ai_ok` | Freizeitsee/Naherholung ist atmosphärisch lösbar; akzeptierter KI-Kandidat existiert. | Aktuelles KI-Bild als `ready`-Kandidat vormerken; finale Assetintegration später. |
| 4 | `textilwerk-bocholt-erleben` | Textilwerk Bocholt | `own_or_licensed_real_photo_required` | Konkreter Kultur-/Museumsort; Nutzer erwarten reale Anlage, nicht symbolische Fabrik. | Commons/Lizenz prüfen oder offizielle Freigabe einholen. |
| 5 | `handwerksmuseum-bocholt-erleben` | Handwerksmuseum Bocholt | `own_or_licensed_real_photo_required` | Konkretes Museum und konkrete Werkstatt-/Gebäudewirkung. | Reales/lizenziertes Foto erforderlich. |
| 6 | `heelweg-dinxperlo-suderwick-entdecken` | Grenzort Dinxperlo–Suderwick entdecken | `symbolic_ai_ok` | Grenzort-/Straßen-/Spaziergangsatmosphäre ist darstellbar, wenn keine konkrete falsche Straßenansicht behauptet wird. | KI möglich; Motiv allgemein als Grenz-/Dorfspaziergang halten. |
| 7 | `vestingpark-st-bernardus-erleben` | Vestingpark St. Bernardus entdecken | `own_or_licensed_real_photo_required` | Konkreter historischer Park/Ort in Bredevoort; generischer Park wäre fachlich schwach. | Lizenzfoto oder offizielle Freigabe suchen. |
| 8 | `bredevoort-buecherstadt-erleben` | Bredevoort Bücherstadt erleben | `own_or_licensed_real_photo_required` | Konkrete Bücherstadt/Altstadt; KI erzeugt leicht falsche Ortskulisse. | Reales/lizenziertes Ortsfoto oder Freigabe. |
| 9 | `unterduikmuseum-aalten-entdecken` | Unterduikmuseum Aalten | `own_or_licensed_real_photo_required` | Konkretes Museum mit sensibler historischer Thematik; symbolisches Bild wäre riskant. | Echtes/lizenziertes Foto erforderlich. |
| 10 | `schloss-ringenberg-erleben` | Schloss Ringenberg | `own_or_licensed_real_photo_required` | Konkretes Schloss; falsches KI-Schloss wäre nicht premiumfähig. | Commons/Lizenz oder offizielle Freigabe. |
| 11 | `wasserburg-anholt-erleben` | Wasserburg Anholt | `own_or_licensed_real_photo_required` | Konkrete Wasserburg/Schlossanlage; Nutzer erwarten reale Anlage. | Reales/lizenziertes Foto nötig. |
| 12 | `anholter-schweiz-erleben` | Anholter Schweiz erleben | `official_permission_candidate` | Konkreter Wildpark mit Betreiber-/Tier-/Anlagenbezug; offizielles Bildmaterial wahrscheinlich. | Betreiberfreigabe oder lizenzierte Alternative suchen. |
| 13 | `villa-mondriaan-winterswijk-erleben` | Villa Mondriaan Winterswijk | `own_or_licensed_real_photo_required` | Konkretes Museum; Kunst-/Gebäude-/Innenraumrisiken. | Reales/lizenziertes Foto, keine symbolische KI als Endbild. |
| 14 | `museumfabriek-winterswijk-erleben` | MuseumFabriek Winterswijk | `own_or_licensed_real_photo_required` | Konkretes Museum/Fabrikgebäude. | Lizenzfoto prüfen oder Freigabe einholen. |
| 15 | `zwillbrocker-venn-flamingos-entdecken` | Zwillbrocker Venn & Flamingos entdecken | `symbolic_ai_ok` | Venn/Vogelbeobachtung/Flamingos sind atmosphärisch darstellbar; keine exakte Ortsbehauptung nötig. | KI möglich, Tierdarstellung streng auf Plausibilität prüfen. |
| 16 | `korenburgerveen-entdecken` | Korenburgerveen entdecken | `symbolic_ai_ok` | Moor/Bohlenweg/Wandergebiet typologisch lösbar. | KI-Moor-/Bohlenweg-Motiv möglich. |
| 17 | `wooldse-veen-entdecken` | Wooldse Veen entdecken | `symbolic_ai_ok` | Hochmoor/Bohlenweg/Grenzlandschaft atmosphärisch darstellbar. | KI möglich; keine exakte Ortsbehauptung. |
| 18 | `grenzenlos-wandern-dinxperlo-suderwick` | GrenzenLos wandern Dinxperlo–Suderwick | `symbolic_ai_ok` | Wanderroute/Grenzlandschaft kann typologisch dargestellt werden. | KI-Grenz-/Wanderweg-Motiv möglich. |
| 19 | `bocholter-innenstadt-erleben` | Bocholter Innenstadt erleben | `own_or_licensed_real_photo_required` | Innenstadt ist konkret und KI wirkt schnell fake/generisch; echtes Foto geplant. | Eigenes echtes Foto oder rechtlich sauberes Ortsfoto bevorzugen. |
| 20 | `bocholter-aa-radweg-erleben` | Bocholter-Aa-Radweg | `symbolic_ai_ok` | Radweg/Fluss/Uferweg ist atmosphärisch ehrlich darstellbar. | KI-Radweg entlang kleiner Aa/Grünzug möglich. |
| 21 | `kubaai-aa-promenade-aktiv-erleben` | Kubaai / Aa-Promenade erleben | `own_or_licensed_real_photo_required` | Konkreter Stadtentwicklungs-/Promenadenort mit markanten Elementen. | Reales/lizenziertes Ortsfoto nötig. |
| 22 | `hohenhorster-berge-entdecken` | Hohenhorster Berge entdecken | `symbolic_ai_ok` | Regionale Sand-/Wald-/Hügelnatur ist atmosphärisch lösbar; akzeptierter KI-Kandidat existiert. | Aktuelles KI-Bild als `ready`-Kandidat vormerken. |
| 23 | `noaberpad-ab-bocholt` | Noaberpad ab Bocholt wandern | `symbolic_ai_ok` | Grenz-/Wanderroute kann typologisch dargestellt werden. | KI-Wanderweg/Grenzlandschaft möglich. |
| 24 | `stadtwald-bocholt-erleben` | Stadtwald Bocholt erleben | `symbolic_ai_ok` | Stadtwald/Naherholung ist atmosphärisch lösbar; akzeptierter KI-Kandidat existiert. | Aktuelles KI-Bild als `ready`-Kandidat vormerken. |
| 25 | `waldlehrpfad-am-vossenpand` | Waldlehrpfad am Vossenpand | `official_permission_candidate` | Konkreter Lehrpfad; Stationen/Schilder/Ausstattung können relevant sein. | Offizielle/freigegebene Fotos prüfen; KI nur für nicht-prominente Übergangsstimmung. |
| 26 | `erlebnisweg-olle-kerkpatt` | Erlebnisweg Olle Kerkpatt | `official_permission_candidate` | Erlebnisweg kann konkrete Stationen/Wegeführung haben; generischer Waldweg wäre schwach. | Offizielle/lizenzierte Route- oder Stationenfotos suchen. |
| 27 | `suderwicker-maerchenspielplatz` | Suderwicker Märchenspielplatz | `official_permission_candidate` | Konkreter Spielplatz mit spezifischer Ausstattung; KI erzeugte falsche dokumentarische Ortswirkung. | Reales/lizenziertes/freigegebenes Ortsfoto nötig; bis dahin nicht premium-ready. |
| 28 | `buergerpark-rhede` | Bürgerpark Rhede | `own_or_licensed_real_photo_required` | Konkreter Bürgerpark; generischer Park wäre wenig glaubwürdig. | Reales/lizenziertes Foto suchen. |
| 29 | `das-mysterium-von-winterswijk` | Das Mysterium von Winterswijk | `official_permission_candidate` | Konkrete Rätsel-/Erlebnisroute; falsches KI-Motiv würde Erwartung verzerren. | Betreiber-/Tourismus-/Stadtfreigabe suchen. |
| 30 | `handwerk-in-winterswijk` | Handwerk in Winterswijk | `official_permission_candidate` | Konkrete handwerkliche Route/Einrichtung; generische Werkbank reicht nicht sicher. | Offizielle/freigegebene Fotos oder konkrete Lizenzquelle suchen. |
| 31 | `mtb-route-winterswijk` | MTB-Route Winterswijk | `symbolic_ai_ok` | MTB-/Waldroute kann typologisch dargestellt werden, wenn kein konkreter Trail behauptet wird. | KI-MTB-Waldweg ohne Action-/Markenfokus möglich. |
| 32 | `proebstingsee-borken-erleben` | Pröbstingsee Borken erleben | `symbolic_ai_ok` | Freizeitsee/Naherholung ist atmosphärisch lösbar. | KI-See-/Ufer-/Freizeitsee-Motiv möglich. |
| 33 | `borkener-tuerme-tour` | Borkener Türme-Tour | `own_or_licensed_real_photo_required` | Tour hängt an konkreten Türmen/Bauwerken; falsche Türme wären nicht premiumfähig. | Reale/lizenzierte Turm-/Stadtfotos erforderlich. |
| 34 | `naturkulturspaziergang-bocholt-rhede` | Naturkulturspaziergang Bocholt–Rhede | `symbolic_ai_ok` | Route/Natur/Kulturstimmung kann atmosphärisch dargestellt werden, solange keine konkrete Schloss-/Bauwerksbehauptung entsteht. | KI-Route/Naturkultur-Motiv möglich; konkrete Bauwerke vermeiden. |
| 35 | `zeitreise-dingdener-heide` | Zeitreise durch die Dingdener Heide | `symbolic_ai_ok` | Heide-/Natur-/Wegstimmung ist typologisch darstellbar. | KI-Heide-/Feldweg-/Zeitreise-Naturmotiv möglich. |
| 36 | `sternbusch-gemen-erleben` | Sternbusch & Gemen erleben | `own_or_licensed_real_photo_required` | Titel/aktuelles Bild verweisen auf konkreten Ort/Burg-/Gemen-Bezug. | Reales/lizenziertes Ortsfoto nötig, wenn Gemen/Burg sichtbar sein soll. |
| 37 | `quellengrundpark-weseke-entdecken` | Quellengrundpark Weseke entdecken | `own_or_licensed_real_photo_required` | Konkreter Park/Ort; generischer Park würde Activity nicht sicher treffen. | Reales/lizenziertes Foto oder Freigabe suchen. |
| 38 | `die-berge-hombornquelle-entdecken` | Die Berge & Hombornquelle entdecken | `symbolic_ai_ok` | Wald-/Quell-/Hügelweg kann atmosphärisch dargestellt werden. | KI-Natur-/Quellweg-Motiv möglich; keine falschen konkreten Quellbauten. |
| 39 | `tiergarten-schloss-raesfeld-erleben` | Tiergarten Schloss Raesfeld erleben | `own_or_licensed_real_photo_required` | Historischer Tiergarten/Schlossumfeld ist konkreter Ort. | Reales/lizenziertes Foto nötig. |
| 40 | `erlebnispfad-klostersee-burlo` | Erlebnispfad Klostersee Burlo | `official_permission_candidate` | Konkreter Erlebnispfad/Klostersee; aktuelles Bild war motivisch unscharf. | Offizielle/lizenzierte Fotos des tatsächlichen Pfads/Sees suchen. |
| 41 | `gaengeskes-aalten-entdecken` | Gängeskes Aalten entdecken | `own_or_licensed_real_photo_required` | Konkrete historische Gassen; generische Altstadtgasse wäre falsche Ortswirkung. | Reales/lizenziertes Gängeskes-Foto nötig. |
| 42 | `auesee-wesel-erleben` | Auesee Wesel erleben | `symbolic_ai_ok` | See/Naherholung ist atmosphärisch lösbar. | KI-See-/Ufer-Motiv möglich; keine konkrete falsche Wesel-Perspektive. |
| 43 | `schwarzes-wasser-wesel-entdecken` | Schwarzes Wasser Wesel entdecken | `symbolic_ai_ok` | Natur-/Wasser-/Schutzgebietsstimmung typologisch darstellbar. | KI-Natursee-/Moorwasser-Motiv möglich. |
| 44 | `witte-venn-ahaus-alstaette-entdecken` | Witte Venn Ahaus-Alstätte entdecken | `symbolic_ai_ok` | Venn-/Natur-/Grenzlandschaft ist atmosphärisch darstellbar. | KI-Venn-/Heide-/Naturweg-Motiv möglich. |

## Produktionslogik für spätere Chats

1. Zuerst diese Matrix prüfen.
2. Nur bei `symbolic_ai_ok` einen finalen KI-Premium-Prompt erstellen.
3. Bei `own_or_licensed_real_photo_required` oder `official_permission_candidate` keinen KI-Endbild-Prompt erstellen, sondern einen Sourcing-Brief.
4. Bei `blocked_until_photo_available` Activity nicht prominent mit falschem Bild verwenden.
5. Nach realer Lizenz-/Freigabeprüfung oder akzeptiertem KI-Bild muss die konkrete Asset-Entscheidung separat dokumentiert werden.

## Offene Folgeschritte

- Matrix später in eine maschinenlesbare Datenstruktur überführen, falls sie für Audits benötigt wird.
- Pro Activity finalen `asset_status`, `source_type`, `rights_status` und `review_notes` ergänzen.
- Akzeptierte KI-Kandidaten aus dem Anchor-Test als Asset-Dateien, WebP 1200x675 und finale Manifest-/Pool-Einträge integrieren.
- Für konkrete Orts-/Objekt-Activities gezielt Lizenz-/Freigabequellen recherchieren.

<!-- === END BLOCK: ACTIVITY_VISUAL_SOURCING_MATRIX_V1_2026_06_10 === -->
