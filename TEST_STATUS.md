<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_FAVORITES_CARD_PARITY_2026_06_30 | Zweck: dokumentiert Activity-Favoriten, mobile Card-Parity und Browser-Smoke-Main-Abnahme; Umfang: aktueller Produktreife-Status nach P1-Smoke === -->
## Activity-Favoriten und Mobile Card-Parity – umgesetzt

Status: umgesetzt und nach Staging-Deploy fachlich/visuell abgenommen.

Abgenommen:

- Activity-Favoriten funktionieren lokal und bleiben nach Reload erhalten.
- Favoriten bleiben stille Priorisierung ohne Favoriten-Pill, ohne eigene Section und ohne Erklaerzeile.
- Mobile Activity-Cards wirken ruhiger und sind in der Bildgeometrie an Event-Cards angenaehert.
- Mobile Schnellfilter-Rail ist final abgenommen: einzeilig, links startend mit `Jetzt besonders`, horizontal wischbar.

Umgesetzt:

- Activity-Favoriten als Herz-Aktion auf Aktivitaetskarten.
- Activity-Favoriten als Herz-Aktion im mobilen Activity-Detailpanel.
- Favoriten werden lokal im Browser bzw. in der PWA gespeichert, ohne Cookies, Login, Backend oder Serveruebertragung.
- Favoriten werden auf `/aktivitaeten/` bevorzugt oben einsortiert, ohne eigene Favoriten-Section, ohne Erklaerzeile und ohne Filterstatus.
- Kein `Favoriten`-Schnellfilter-Pill: Favoriten sind persoenliche Priorisierung, keine Inhaltskategorie.
- Mobile Activity-Bilder wurden an die kompaktere Event-Thumbnail-Geometrie angeglichen, damit der Wechsel Events/Aktivitaeten ruhiger wirkt.
- Mobile Schnellfilter wurden als horizontale Chip-Rail umgesetzt, damit der Filterblock eine Zeile spart und die Ergebnisliste frueher beginnt.
- Events erhalten bewusst keine Favoritenlogik; dort bleibt Kalender/Terminaktion der passende Nutzerpfad.
- Browser-Smoke prueft die lokale Favoritenfunktion zusaetzlich als echten Browserpfad.

Datenschutz-/Rechtsbewertung:

- Keine neuen Cookies.
- Keine optionale Statistik-/Trackingverarbeitung.
- Lokale Speicherung ist fuer die vom Nutzer ausdruecklich gewaehlte Favoritenfunktion erforderlich und wird in `/datenschutz/` beschrieben.
- Statistik-Consent bleibt davon getrennt.

P1 Browser-Smoke:

- P1 Browser-Smoke auf Main abgenommen: 21/21 OK, 0 Fehler, 0 Warnungen.
- Neuer Favoriten-Smoke prueft Herz, lokalen Speicher, Reload, Priorisierung, Wegfall des Favoriten-Schnellfilters und Wegfall einer eigenen Favoriten-Section.
- Browser-Smoke prueft mobile Schnellfilter-Rail: flex/nowrap, eine sichtbare Chip-Zeile, linker Startzustand mit `Jetzt besonders`, Karten bleiben sichtbar.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_FAVORITES_CARD_PARITY_2026_06_30 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_BROWSER_SMOKE_REPORTING_POLISH_2026_06_29 | Zweck: dokumentiert Reporting-Haertung nach erstem Staging-Lauf; Umfang: Warnungslabel, bekannte geschuetzte 401-/Fetch-Hinweise === -->
## P1 Browser-Smoke Reporting-Polish – umgesetzt

Status: Patch vorbereitet.

Ausgangspunkt:

- Erster Staging-Lauf nach P1-Einfuehrung: `21/23 OK`, `0 Fehler`, `2 Warnungen`.
- Die Warnungen waren erwartete Konsolenhinweise beim geschuetzten Veranstalter-Dashboard ohne Login (`401`/Fetch), waehrend der sichtbare Zugangszustand erfolgreich geprueft wurde.
- `summary.md` betitelte `warn`-Eintraege in der Tabelle irrefuehrend als `FEHLER`.

Umgesetzt:

- `scripts/browser-smoke.mjs`: `summary.md` trennt `OK`, `WARNUNG` und `FEHLER` sauber.
- `scripts/browser-smoke.mjs`: erwartete geschuetzte `401`-/Fetch-Konsolenhinweise beim Veranstalter-Dashboard-Zugangszustand werden als bekannte Zugangshinweise ignoriert, sofern der Seitenzustand selbst OK ist.
- `BROWSER_SMOKE_SYSTEM.md`: Fehlerklassen und Reporting-Verhalten entsprechend dokumentiert.

Validierung im ZIP-Worktree:

- `node --check scripts/browser-smoke.mjs`: OK.

Erwartung nach Upload:

- Staging-Browser-Smoke soll bei gleichem App-Zustand ohne diese beiden irrefuehrenden Warnungen laufen.
- Echte nicht bekannte Konsolenprobleme bleiben weiterhin als `WARNUNG` sichtbar.
- Harte Kernwegfehler bleiben `FEHLER` und machen den Workflow rot.
<!-- === END BLOCK: TEST_STATUS_BROWSER_SMOKE_REPORTING_POLISH_2026_06_29 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_BROWSER_SMOKE_SYSTEM_V1_2026_06_29 | Zweck: dokumentiert Implementierung von P1 Browser-Smoke V1; Umfang: Trigger, Testmatrix, Validierung, Abnahmehinweis === -->
## P1 Browser-Smoke-System V1 – umgesetzt

Status: Patch vorbereitet.

Umgesetzt:

- `scripts/browser-smoke.mjs`: Playwright/Chromium-Smoke fuer zentrale Browserwege.
- `.github/workflows/browser-smoke.yml`: manueller Smoke fuer Staging/Live/Custom ohne Redeploy.
- `.github/workflows/deploy-strato.yml`: automatischer Browser-Smoke nach STRATO-Deploy und HTTP-Smoke.
- `BROWSER_SMOKE_SYSTEM.md`: Zielzustand, Trigger, Fehlerverhalten und Nicht-Ziele dokumentiert.

Testmatrix V1:

- Home / Today.
- Events.
- Aktivitaeten.
- Bottom-Tabbar-Navigation.
- Consent-Systemlayer bleibt nach Tabwechsel weg.
- Event-Einreichung.
- Aktivitaetspraesenz-Funnel.
- Zahlung-starten-Zugangszustand.
- Veranstalterlogin.
- Veranstalter-Dashboard-Zugangszustand.

Validierung im ZIP-Worktree:

- `node --check scripts/browser-smoke.mjs`: OK.
- `.github/workflows/browser-smoke.yml`: YAML parse OK.
- `.github/workflows/deploy-strato.yml`: YAML parse OK.
- `bash tools/check-js-syntax.sh`: OK.
- `python3 tools/audit-css-governance.py`: OK.

Abnahme nach Upload:

- Staging-Deploy laeuft durch und fuehrt Browser-Smoke aus.
- Alternativ/manuell: GitHub Actions -> `Browser Smoke` -> `target=staging`, `profile=all`.
- Bei Erfolg ist P1 V1 als Sicherheitsnetz abgenommen.
<!-- === END BLOCK: TEST_STATUS_BROWSER_SMOKE_SYSTEM_V1_2026_06_29 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_PRIVACY_TRACKING_P0_IMPLEMENTED_2026_06_29 | Zweck: dokumentiert Umsetzung des ersten Product-Maturity-Workpacks Datenschutz/Tracking; Umfang: Runtime-Gating, Datenschutzseite, serverseitiger Consent-Guard, Validierung === -->
## Product-Maturity P0 Datenschutz/Tracking – umgesetzt im Patch 2026-06-29

Status: Patch vorbereitet; nach Upload/Deploy kurzer Live-Smoke erforderlich.

Umgesetzt:

- `config.js`: GA4 und `BEAnalytics`/Nutzwerttracking starten nur noch auf Live-Hosts und nur nach aktiver Statistik-Zustimmung.
- `config.js`: Consent-Banner und `window.BEPrivacy`-API fuer Datenschutz-Einstellungen ergaenzt.
- `api/value-track.php`: serverseitiger Consent-Guard; Metriken ohne `be_statistics_consent=granted` werden ignoriert.
- `datenschutz/index.html`: Datenschutztext fuer lokale Speicherung, Statistik, GA4, First-Party-Nutzwerttracking, Anbieterbereich und Zahlungen aktualisiert.
- `css/components.css`: UI-Stile fuer Consent-Banner und Datenschutz-Einstellungen ergaenzt.

Lokale Validierung im ZIP-Worktree:

- `node --check config.js`: OK.
- `php -l api/value-track.php`: OK.
- `python3 tools/audit-css-governance.py`: OK.

Live-Smoke nach Deploy:

1. Auf `bocholt-erleben.de` ohne Auswahl pruefen: kein `googletagmanager.com`-Request, kein `/api/value-track.php`-POST.
2. `Nur notwendige` klicken: Banner verschwindet, weiterhin keine Statistikrequests.
3. Auf `/datenschutz/#datenschutz-einstellungen` `Statistik erlauben` klicken: GA4-Script darf laden, interne Nutzwertmetriken duerfen bei Detail-/Outbound-Aktionen gesendet werden.
4. `Auswahl zuruecksetzen` pruefen: Banner erscheint wieder.

Naechster Product-Maturity-Workpack nach Live-Smoke: P1 Browser-Smoke-Tests fuer Kernwege.
<!-- === END BLOCK: TEST_STATUS_PRIVACY_TRACKING_P0_IMPLEMENTED_2026_06_29 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_PRODUCT_MATURITY_ROADMAP_VALIDATION_2026_06_29 | Zweck: dokumentiert Validierung der nicht-contentbezogenen Produktreife-Roadmap gegen den aktuellen Repo-Stand; Umfang: Befunde, Reihenfolge, keine Implementierung === -->
## Produktreife-Roadmap ohne Content-Operation – Validierung 2026-06-29

Status: Roadmap validiert und in `ROADMAP.md` / `MASTER.md` als eigener Nicht-Content-Strang eingetragen.

Bewusst ausgeklammert:

- Dienstag-/Mittwoch-Liveprozess.
- KI-Suche.
- Content-Audit.
- Inbox-Content-Routing.

Validierte Befunde:

- Datenschutz/Tracking: `config.js` startete bisher GA4 (`G-Y6QLCQ4HXT`) und internes Nutzwerttracking ueber `/api/value-track.php`, waehrend `/datenschutz/` noch `kein Tracking und keine Analyse-Tools` sagte. Mit dem P0-Patch gilt: Statistik erst nach Zustimmung; Datenschutztext und Runtime sind konsistent. Nach Deploy bleibt nur der Live-Smoke.
- Nutzerbindung: `js/user-preferences.js` und `js/recommendations.js` enthalten lokale Interessen-, Merkliste-, Ausblendungs- und Scoring-Grundlagen; sichtbar produktisiert ist `Merken / Fuer dich / Erinnern` noch nicht. Daraus folgt P1 nach Datenschutz-/Smoke-Test-Grundlage.
- Standort/Naehe: `data/offers.json` enthaelt Ortsnamen, `maps_query` und `maps_label`, aber keine robusten Koordinatenfelder fuer Activities. Daraus folgt P1/P2 Standort-/Karten-/Naehe-Schicht.
- Anbieter/Monetarisierung: Anbieterbereich, Stripe-/Abo-Flows, Magic-Link, Mail-System und Nutzwertmetriken sind technisch vorhanden. Daraus folgt kein Neubau, sondern Verkaufsverstaendlichkeit und oeffentliche Rechts-/Leistungsseiten haerten.
- Technik: Mehrere zentrale CSS-/JS-Dateien sind gross und historisch gewachsen; Syntax-/Auditchecks reichen nicht als Nutzerflussbeweis. Daraus folgt zuerst kleiner Browser-Smoke-Test-Grundstock, danach gezielte Konsolidierung.

Validierte Reihenfolge:

1. P0 Datenschutz-/Tracking-Konsistenz.
2. P1 Browser-Smoke-Tests fuer Kernwege.
3. P1 Nutzerbindung: Merken / Fuer dich / Erinnern.
4. P1/P2 Standort-/Karten-/Naehe-Schicht.
5. P2 Anbieterbereich und Verkaufsstrecke verkaufsfertig machen.
6. P2 Recht-/Verkaufsseiten fuer bezahlte Produkte haerten.
7. P2/P3 UI-/CSS-/JS-Konsolidierung gezielt fortsetzen.

Diese Validierung ist ein Planungs-/Doku-Stand. Sie implementiert keine Datenschutz-, Tracking-, Consent-, Karten- oder Browser-Test-Aenderung.
<!-- === END BLOCK: TEST_STATUS_PRODUCT_MATURITY_ROADMAP_VALIDATION_2026_06_29 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_CURRENT_DOKU_ABGLEICH_2026_06_27 | Zweck: korrigiert den aktuellen Test-/Statusindex nach Roadmap-/Doku-Abgleich; Umfang: erledigte Punkte, echte offene Beweise, kleine Rest-To-dos === -->
## Aktueller Test- und Statusabgleich – 2026-06-27

Dieser Block ist die aktuelle Einstiegsschicht. Aeltere Testbloecke darunter bleiben Beweisarchiv und koennen veraltete damalige "offen"-Hinweise enthalten.

Aktuell als bestanden oder abgeschlossen zu behandeln:

- Main-Merge und Live-Smoke bestanden.
- KI-Suchlauf -> Manual Inbox -> Visual-Key-Handoff -> Events-/Live-Bildausspielung ist auf `main` bestanden.
- Event-Visual-Motif-Fit ist fuer den aktuellen Sheet-/Matrix-Stand abgeschlossen; keine offenen Event-Produktions-/Review-Gaps.
- Mail-System V1 ist zentral umgesetzt und mit 9 Topics getestet; `MAIL_SYSTEM.md` ist Contract/Referenz, kein offener Implementierungsauftrag.
- Anbieterbereich/Nutzwertdaten sind technisch vorhanden; naechster Punkt ist Datenlauf/Bewertung, nicht Neubau.
- Aktivitaetspraesenz-/Abo-Livebeweis wird als erledigt gefuehrt; Activity-Presence-Funnel, Zahlungslink-/Checkout-Kette, Anbieterbereich-Kontext und Review-/Inbox-Sichtbarkeit sind belegt.
- Badegewaesserstatus-Proof / Guard V2 ist abgehakt: Statusdatei, Commit, Deploy, Frontend-Override und keine positive Badeempfehlung ohne `ok` sind validiert.
- Seasonal Activity Highlights V1 ist abgeschlossen; Bade-/Wasser-Highlights bleiben ohne frische positive Quelle unsichtbar.

Aktuell noch zu beobachten oder gezielt zu beweisen:

- Dienstag-/Mittwoch-Automation mit echten Live-Daten: Weekly-KI, Import, Cleanup/Archiv, Content-Audit.
- Content Search Feedback Loop: lokal/statisch umgesetzt; echte Livewirkung und Logs nach dem naechsten Lauf pruefen.
- Inbox-Owner-UX fuer `visual_key`: Handoff bestanden, komfortable Korrektur vor Uebernahme noch kleiner UI-/Handoff-Rest.
- Activity-Visual-Rest `buergerpark-rhede`, `suderwicker-maerchenspielplatz`, `waldlehrpfad-am-vossenpand`: weiterhin kleiner Visual-Pruefpunkt / To-do, aber kein Content-Blocker und keine normale Inbox-Sofortaktion.

<!-- === END BLOCK: TEST_STATUS_CURRENT_DOKU_ABGLEICH_2026_06_27 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_SEASONAL_ACTIVITY_HIGHLIGHTS_V1_DONE_2026_06_26 | Zweck: dokumentiert Abschluss des Workpacks Seasonal Activity Highlights V1 inklusive Content Batch 01, Bade-/Status-Gate und Home-Rotationshaertung; Umfang: Staging-Deploy, fachlicher Smoke, Datenstand, bewusste Grenzen und Folge-Proof statt unbewiesener Automatisierung === -->
## Seasonal Activity Highlights V1 + Content Batch 01 – abgeschlossen (2026-06-26)

Status: auf Staging eingebracht, deployed und fachlich per Smoke-Test bestaetigt.

### Umgesetzter Zielzustand

- Saisonale Activity-Highlights sind als gepruefte Zusatzschicht in `data/offers.json` modelliert.
- Home nutzt aktive Highlights kuratierend, aber nicht mehr dauerhaft dominierend.
- Die Aktivitaetenseite zeigt aktive Highlights ueber:
  - Card-Pill `Jetzt: ...`,
  - Filter `Jetzt besonders`,
  - Detailpanel-Block `Jetzt besonders`.
- Zustandsabhaengige Bade-/Wasser-Highlights werden nur mit frischer positiver Statusquelle ausgespielt.
- Negative oder unbekannte Statuslage blockiert Bade-Highlights auch dann, wenn das saisonale Grundfenster passt.
- Konkrete Aktionen, Fuehrungen und Termine bleiben Events und werden nicht als Activity-Highlight gepflegt.
- Weiche Annahmen wie `Park = Bluete`, `Sommer = Baden` oder `Naturgebiet = Tiere sichtbar` werden nicht ausgespielt.

### Aktueller Datenstand

- Activities gesamt: 44.
- Saisonale Highlight-Datensaetze: 10.
- Stabile saisonale Highlights: 6.
- Zustandsabhaengige Bade-/Status-Highlights: 4.
- Aktive Bade-Highlights ohne frische positive Statusquelle: 0.

Aktiv stabile Highlights je Saisonfenster:

- `zwillbrocker-venn-flamingos-entdecken` – Flamingo-Zeit.
- `korenburgerveen-entdecken` – Moor-/Wollgraszeit.
- `quellengrundpark-weseke-entdecken` – Apothekergarten-Saison.
- `witte-venn-ahaus-alstaette-entdecken` – Heidebluete-Zeit.
- `wasserburg-anholt-erleben` – Landschaftspark-Saison.
- `anholter-schweiz-erleben` – Wildpark-Saison.

Bewusst nicht aktiv als Bade-Highlight ausgespielt:

- `aasee-erleben` – aktueller Status `blocked`.
- `hilgelo-erleben` – aktueller Status `unknown`.
- `proebstingsee-borken-erleben` – aktueller Status `unknown`.
- `auesee-wesel-erleben` – aktueller Status `unknown`.

### Gepruefter Staging-Smoke

Bestanden:

- GitHub Actions / Deploy nach den Seasonal-Highlight-Patches gruen.
- Kurzzeitiger STRATO-/Smoke-Timeout wurde per Rerun ohne Codeaenderung geloest.
- Staging laedt wieder auf Mobile und Desktop.
- Home laedt ohne kaputte Cards.
- Aktivitaetenseite laedt und zeigt `Jetzt besonders (5)` im aktuellen Saisonkontext.
- Cards zeigen `Jetzt: ...` nur bei aktivem, erlaubtem Highlight-Zeitfenster.
- Aasee, Hilgelo, Proebstingsee und Auesee zeigen keine Bade-Highlight-Pill.
- Zwillbrocker Venn, Quellengrundpark Weseke, Wasserburg Anholt, Anholter Schweiz und Korenburgerveen zeigen im aktuellen Zeitraum passende `Jetzt`-Pills.
- Detailpanel zeigt bei stabilen Highlights einen kompakten Block `Jetzt besonders` mit vorsichtiger Sprache.
- Home-Rotationshaertung verhindert, dass saisonale Highlights dauerhaft die komplette Empfehlungsliste dominieren.

### Validierung

Bestanden im Patch-/Deploy-Kontext:

- JS-Syntaxchecks fuer die betroffenen Frontend-Dateien.
- Python-Compile-Checks fuer Content-/Highlight-Audit-Skripte.
- `data/offers.json` JSON-valid.
- `python3 scripts/audit-activity-highlights.py --scope full` bestanden.
- Audit-Datenstand nach Content Batch 01:
  - `seasonal_highlights=10`
  - `stable=6`
  - `condition_sensitive=4`
  - `condition_currently_playable=0`
  - `blocked=1`
  - `unknown=3`

### Bewusste Grenzen

- Die eingebaute Logik ist ein Sicherheits-/Ausspiel-Gate, kein bereits bewiesener automatischer Badegewaesser-Statusabruf.
- Die Content-Pruefung erkennt fehlende, alte oder unklare Statusdaten und verhindert dadurch falsche Ausspielung.
- Die Content-Pruefung recherchiert derzeit noch nicht belastbar automatisch externe Badegewaesserquellen und setzt daraus nicht selbststaendig `ok`, `watch`, `blocked` oder `unknown`.
- Bade-/Wasser-Highlights bleiben ohne frische positive Statusquelle unsichtbar.
- Frontend fragt keine Live-News ab; ausgespielt werden nur gepruefte Repo-/Contentdaten.

### Abschlussbewertung

Das Workpack `Seasonal Activity Highlights V1` ist fuer den aktuellen Staging-Stand abgeschlossen.

Abgeschlossen sind:

- Systemlogik fuer saisonale Activity-Highlights.
- Status-/Blockerlogik fuer zustandsabhaengige Highlights.
- Aktivitaetenseiten-Filter und Card-/Detailpanel-Ausweisung.
- Content Batch 01 mit konservativ geprueften Zusatzhighlights.
- Home-Rotationshaertung gegen monotone saisonale Toplisten.

### Keine aktiven Folgepflichten aus diesem Workpack

- Kein geplanter Massen-`Content Batch 02` als naechster Pflichtschritt.
- Weitere saisonale Highlights nur bei neuem konkretem Quellenfund oder bewusstem spaeterem Rechercheauftrag.
- Zusaetzliche Highlights nur mit belastbarer Quelle und klarer Aktivierungslogik.

### Badegewaesser-Status: Proof/Guard inzwischen weitergefuehrt

Der urspruenglich optionale Proof wurde durch Guard-/Source-Discovery- und Guard-V2-Arbeiten weitergefuehrt. Der aktuelle Stand ist nicht mehr "Proof noch starten", sondern: Safe-Writeback ueber `data/bathing_water_status.json` ist auf Staging belegt. Gepruefte Badestellen:

- `aasee-erleben`
- `hilgelo-erleben`
- `proebstingsee-borken-erleben`
- `auesee-wesel-erleben`

Aktueller Guard-V2-Stand:

- Statusquelle/Statusfile-Kette ist technisch umgesetzt.
- `ok` wird nur aktiv, wenn Wasserstatus und lokale Badeeignung positiv sind.
- Unsichere oder negative Zustaende bleiben unterdrueckt bzw. als knapper Hinweis sichtbar.
- Badegewaesserstatus-Proof / Guard V2 ist damit abgehakt; kein neuer Proof-Start.

Weiterhin verbindliche sichere Regel:

- Keine Badeempfehlung ohne frische positive Statusquelle.
- `unknown`, `watch` und `blocked` erzeugen keinen Chip, keinen Boost und keinen `Jetzt besonders`-Treffer.
<!-- === END BLOCK: TEST_STATUS_SEASONAL_ACTIVITY_HIGHLIGHTS_V1_DONE_2026_06_26 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_INBOX_CONTENT_AUTOMATION_ROUTING_FREEZE_2026_06_26 | Zweck: dokumentiert den eingefrorenen Staging-Stand fuer Content-Automation-Routing und mobile Inbox-Nutzung; Umfang: Prozessentscheid, Validierung, offene Live-Nutzungsbeobachtung === -->
## Inbox / Content-Automation-Routing – Mobile-Arbeitsstand eingefroren (2026-06-26)

Status: Staging-Deployment gruen; UI-/Prozessstand wird vorerst eingefroren und im Alltag mobil genutzt.

### Eingefrorener Zielzustand

- Die private `/inbox/` bleibt die zentrale Arbeitsansicht fuer:
  1. neue Event-Kandidaten,
  2. echte manuelle Content-Prueffaelle.
- Die Inbox ist mobile-first zu bewerten, weil sie primaer am Smartphone genutzt wird.
- Die normale Content-Pruefung soll keine technische Vollfehlerliste sein, sondern eine Ausnahme-Queue:
  - nur Faelle anzeigen, bei denen der Nutzer wirklich entscheiden, bestaetigen oder korrigieren muss,
  - keine reinen Visual-/Premium-Backlog-Hinweise als manuelle Sofortaktion anzeigen.
- Activity-Faelle wie `visual_key fehlt`, bei denen die Activity sonst nutzbar ist und nur ein Premium-Visual-Pool bzw. neues Bild fehlt, werden als Visual-Backlog aggregiert statt in der normalen Content-Inbox angezeigt.
- Beispielentscheid: `Buergerpark Rhede` soll nicht als normale Content-Aktion erscheinen, sondern in `Content_Visual_Feedback(_Staging)` bzw. im Visual-Backlog gesammelt werden.
- Ticketportal-als-Primärquelle-Faelle bleiben echte Content-Prueffaelle, wenn eine offizielle Quelle vorgeschlagen wurde:
  - Ticketportal oeffnen nur zur Identifikation,
  - offizielle Quelle pruefen,
  - bei Uebereinstimmung offizielle Quelle uebernehmen,
  - Ticketportal nur bewusst behalten, wenn keine bessere Quelle nutzbar ist.

### UI-Stand

- Tabs zeigen Counts direkt: `Neue Events (x)` und `Content-Pruefung (x)`.
- Erklaerende Zwischenbloecke wurden entfernt.
- Content-Karten wurden fuer Mobile verdichtet:
  - weniger sichtbarer Fliesstext,
  - technische Issue-Codes nicht prominent,
  - Quellencheck gekuerzt,
  - Eventdaten-Korrekturfelder bei Quellenfaellen eingeklappt,
  - Hauptaktion steht vor Navigation.
- Technische Pruefdaten/Arbeitspakete bleiben nicht Teil der normalen Arbeitsansicht; Debug-/Diagnosezugriff bleibt separat.

### Geprueft

- Deployment nach Content-Automation-Routing und UI-Polish war gruen.
- Nach Routing-Patch sank die sichtbare Content-Pruefung von vorherigen Visual-Key-Pflichtfeldfaellen auf echte manuelle Faelle.
- Ticketportal-Fall `Borken Open Air - ABBA Gold The Concert Show` wurde in der Inbox als echter manueller Quellenfall sichtbar und UI-seitig auf `Offizielle Quelle uebernehmen` gefuehrt.
- Mobile-Screenshots zeigten noch zu viel vertikale Hoehe; daraufhin wurde der mobile Kompaktstand als aktueller Arbeitsstand erstellt.

### Noch beobachten, nicht sofort neu umbauen

- Der Nutzer arbeitet jetzt zunaechst mit diesem Stand.
- Weitere UI-Aenderungen erst nach realer mobiler Nutzung und konkreten Screenshots/Faellen.
- Besonders beobachten:
  - ob `Offizielle Quelle uebernehmen` Faelle sauber verschwinden,
  - ob Counts nach Aktionen stabil sinken,
  - ob reine Visual-Gaps dauerhaft aus der normalen Content-Pruefung herausbleiben,
  - ob `Content_Visual_Feedback(_Staging)` die Visual-/Bildbedarfe ausreichend aggregiert.
<!-- === END BLOCK: TEST_STATUS_INBOX_CONTENT_AUTOMATION_ROUTING_FREEZE_2026_06_26 === -->

<!-- === BEGIN FILE: TEST_STATUS.md | Zweck: kanonisches Test- und Freigabeprotokoll für geprüfte Funktionen im Projekt „Bocholt erleben“; Umfang: Staging-/Live-Teststände, bestandene Smoke-Tests, offene Regressionen === -->

# TEST STATUS — BOCHOLT ERLEBEN

<!-- === BEGIN BLOCK: TEST_STATUS_CURRENT_INDEX_2026_06_27 | Zweck: macht das lange Testprotokoll current-first lesbar; Umfang: aktuelle Hauptbeweise, offene Beobachtungen und kleine Restbeweise, Umgang mit historischen Alt-Routen und Alt-Offenpunkten === -->
## Aktueller Test-Index für Folgechats

Dieser Index ist die aktuelle Einstiegsschicht. Ältere Testblöcke darunter bleiben als Beweisarchiv erhalten, sind aber nicht automatisch aktuelle Aufgaben.

### Aktuell bestandene Hauptbeweise

- Main-Merge und Live-Smoke bestanden; öffentliche Kernbereiche laden ohne bekannten Blocker.
- KI-Suchlauf → Manual Inbox → Visual-Key-Handoff → Events-/Live-Bildausspielung ist auf `main` mit dem aktuellen Prüflauf bestanden.
- Event-Visual-Motif-Fit ist für den aktuellen Sheet-/Matrix-Stand abgeschlossen; keine offenen Event-Produktions-/Review-Gaps.
- Badegewässerstatus-Proof / Guard V2 ist abgehakt: Guard erzeugt `data/bathing_water_status.json`, Workflow committet bei Diff, Deploy läuft, Frontend nutzt den Override und spielt keine falsche Badeempfehlung aus.
- Seasonal Activity Highlights V1 ist abgeschlossen; kein geplanter Massen-Content-Batch-02 als Pflichtfolge.
- Mail-System V1 ist zentral umgesetzt und mit 9 Topics getestet.
- Anbieterbereich/Nutzwert-Metrikpfad ist technisch vorhanden; organischer Datenlauf bleibt abzuwarten.
- Aktivitaetspraesenz-/Abo-Livebeweis wird als erledigt gefuehrt; Activity-Presence-Funnel, Zahlungslink-/Checkout-Kette, Anbieterbereich-Kontext und Review-/Inbox-Sichtbarkeit sind belegt.
- `/angebote/` ist Legacy-Redirect; kanonische Aktivitätenroute ist `/aktivitaeten/`.
- Aktivitätspräsenz-Funnel ist unter `/aktivitaeten/sichtbar-werden/...` kanonisch; alte `/angebote/sichtbar-werden/...`-Routen sind nur Redirects.
- Public-Shell/Footer-Konsistenz, Reporting-/Tracking-Hardening, CSS-Governance und Stripe-Rücksprunglogik sind geprüft.

### Offene Beobachtungen oder kleine Restbeweise

- Dienstag-/Mittwoch-Automationslauf mit echten Live-Daten beobachten und nach erfolgreichem Lauf als Betriebsbeweis dokumentieren.
- Content Search Feedback Loop ist lokal/statisch bestanden; echte Livewirkung und Logbeleg nach dem nächsten Weekly-Lauf prüfen.
- Inbox-Review-UI soll den `visual_key` künftig sichtbarer und vor dem Übernehmen komfortabel änderbar machen; der technische Handoff ist bereits bestanden.
- 28-/30-Tage-Reporting-Datenlauf abwarten, bevor Akquise-/Feedbackberichte als belastbar gelten.
- Activity-Visual-Restschuld `buergerpark-rhede`, `suderwicker-maerchenspielplatz`, `waldlehrpfad-am-vossenpand` bleibt als kleiner Visual-Pruefpunkt / To-do offen, ist aber kein Content-Blocker.
- Echte Zahlungs-/Webhook-/Stripe-Livefälle, inklusive Aktivitaetspraesenz-/Abo, bleiben nur dann erneut zu testen, wenn ein konkreter Zahlungsflow geändert wurde oder ein neues Symptom auftritt.

### Hinweis zu historischen Blöcken

- Alte Erwähnungen von `/angebote/...` in früheren Testblöcken sind historische Nachweise, nicht aktuelle Informationsarchitektur.
- Alte `Noch offen`-Abschnitte in Spezialdokumenten gelten nur, wenn sie nicht durch diesen Index, `MASTER.md` oder einen späteren Abschlussblock überholt wurden.
- Bei Widerspruch gilt: `MASTER.md` für strategische Steuerung, `ROADMAP.md` für aktuelle Taktik, `ENGINEERING.md` für Arbeitsregeln.
<!-- === END BLOCK: TEST_STATUS_CURRENT_INDEX_2026_06_27 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_CONTENT_SEARCH_FEEDBACK_LOOP_FINAL_STATIC_2026_06_25 | Zweck: dokumentiert lokale/statische Validierung des finalen KI-Suchlauf-Feedback-Loop-Prozesspatches; Umfang: kein Live-Proof, keine Eventdatenfixes === -->
## Content Search Feedback Loop – finaler Prozesspatch vorbereitet, Live-Beweis offen (2026-06-25)

Status: lokal/statisch bestanden; Live-Beweis nach Upload/Deployment noch offen.

### Lokal geprüfter Umfang

- `scripts/content-quality-audit.py` erzeugt zusätzlich ein strukturiertes, gedeckeltes `content-search-feedback.json` mit TTL-/Bloat-Guard-Metadaten.
- Der Content-Audit-Workflow schreibt daraus einen Sheet-Handoff-Tab `Content_Search_Feedback` bzw. `Content_Search_Feedback_Staging`.
- `scripts/weekly-ki-websearch-to-manual-inbox.py` liest diesen Feedback-Tab optional, fällt bei fehlendem Tab auf das JSON-Artefakt zurück und ergänzt echte Inbox-/Archiv-Ablehnungen als typisierte Feedbackklassen.
- Der Suchprompt erhält keinen Roh-Anhang aller Einzelfälle, sondern nur einen begrenzten `CONTENT_SEARCH_FEEDBACK`-Block mit priorisierten Klassenregeln.
- Die Weekly-Diagnose protokolliert Anzahl, Klassen, Quellen und Kurzfassung der angewendeten Feedbackregeln.

### Lokal bestandene Checks

- Python-Syntaxcheck für `scripts/content-quality-audit.py` bestanden.
- Python-Syntaxcheck für `scripts/weekly-ki-websearch-to-manual-inbox.py` bestanden.
- YAML-Parsing für `content-quality-audit.yml`, `weekly-ki-websearch-to-manual-inbox.yml` und `manual-ki-intake.yml` bestanden.
- Content-Audit-Testlauf ohne Live-Sheet-/Secret-Zugriff erzeugte ein gültiges Feedback-JSON mit Default-Kontextregeln und Bloat-Guard-Metadaten.
- Synthetischer Ablehnungs-/Ticketportal-Test erzeugte verdichtete Feedbackregeln aus `Content_Search_Feedback`, `Inbox_Archive` und noch nicht archivierten `verworfen`-Inbox-Zeilen.

### Noch nicht als Live-Proof behauptet

- GitHub-Auditlauf muss nach Deployment den Sheet-Tab `Content_Search_Feedback(_Staging)` tatsächlich schreiben.
- Der nächste Weekly-KI-Suchlauf muss im Log `search_feedback_rules_applied` und die Feedback-Klassen ausweisen.
- Ob wiederkehrende Fehlerklassen praktisch abnehmen, ist erst nach mehreren realen Läufen bewertbar.
<!-- === END BLOCK: TEST_STATUS_CONTENT_SEARCH_FEEDBACK_LOOP_FINAL_STATIC_2026_06_25 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_MAIN_KI_SEARCH_INBOX_VISUAL_KEY_PROOF_2026_06_25 | Zweck: dokumentiert den bestandenen Main-Beweis fuer Weekly-KI-Suchlauf, Manual-Inbox-Intake, Visual-Key-Handoff, Apps-Script-Approve-Fix und Live-Bildausspielung; Umfang: GitHub-Workflowlogs, Sheet-/PWA-Proof, Grenzen und Folgeworkpack === -->
## Main-KI-Suchlauf / Manual Inbox / Visual-Key-Handoff – bestanden (2026-06-25)

Status: bestanden für den aktuellen Main-/Live-Prozess mit menschlicher Inbox-Prüfung.

### Geprüfter Umfang

Geprüft wurde der nach dem Main-Merge offene Kontrollpunkt:

`Weekly KI Websearch -> Manual Inbox -> Manual KI Event Intake -> Inbox-Review -> Events -> Deploy -> Live-Bildausspielung`.

### Belegte Workflow-Ergebnisse

`Weekly KI Websearch -> Manual Inbox #17` auf `main`:

- Workflow grün.
- Events-Snapshot: 176 Zeilen.
- Inbox-Snapshot vor Lauf: 0 Zeilen.
- Roh-Kandidaten: 3.
- Produktiv ausgewählt: 2.
- Verworfen: 1 mit Grund `out_of_window` (`600 Jahre Werth - Festwoche`).
- In `data/inbox_manual.json` geschrieben: 2.
- Neue Source-Candidates: 0.
- Coverage-Ziele: 27.
- Coverage-Status: `IN_EVENTS` 21, `IN_INBOX_ARCHIVE` 2, `MISSING_FROM_RAW` 3, `PAST_TARGET` 1.

`Manual KI Event Intake` als Folgelauf:

- Workflow grün.
- `visual_key`-Dropdown im Google-Sheet-Tab `Inbox` gesetzt: 34 Optionen.
- `Append OK: appended=2 skipped=0`.
- `data/inbox_manual.json` wurde danach zurückgesetzt.
- Deploy wurde durch den Intake-Workflow angestoßen.

Import-/Review-Pfad:

- Die zwei Kandidaten waren in der Inbox sichtbar und konnten redaktionell übernommen werden:
  - `Bocholter Gesundheitstage`.
  - `Nacht der Ausbildung 2026 mit Isselburg`.
- Der GitHub-Workflow `Inbox -> Events (Import) #147` meldete `Import-Kandidaten (status=übernehmen): 0`, weil die PWA-/Apps-Script-Review bereits direkt nach `Events` importiert hatte. Das ist kein Fehler dieses Beweises, sondern beschreibt den tatsächlich genutzten Live-Review-Pfad.

### Gefundener und behobener Prozessfehler

Beim Test wurde ein echter Handoff-Fehler im externen Google Apps Script gefunden:

- `approve_()` importierte die Inbox-Zeile direkt nach `Events`, übernahm aber `visual_key` nicht in `eventMap`.
- Folge: `Events.visual_key` blieb leer und der Build musste den Bildtyp wieder automatisch ableiten.
- Sichtbares Symptom: `Bocholter Gesundheitstage` fiel wegen Kategorie `Sport & Bewegung` auf `indoor_sport_competition` zurück und zeigte ein unpassendes Darts-/Sporthallenbild.

Der externe Apps-Script-Code wurde korrigiert:

- `approve_()` setzt jetzt `visual_key: valueOrEmpty_(inboxMap.visual_key)`.
- Zusätzlich werden gemeinsame Zusatzspalten aus Inbox nach Events übernommen, ohne bewusst gemappte Felder zu überschreiben.
- Die bestehende Web-App-Bereitstellung wurde auf eine neue Version gehoben; die API-URL blieb unverändert.

Beweis nach Fix:

- Testfall `TEST Visual Key Import Proof` wurde in der Inbox-PWA angezeigt.
- Nach Klick auf `Übernehmen` erschien der Fall im Google-Sheet-Tab `Events`.
- `Events.visual_key` war dort korrekt mit `business_messe_info` befüllt.

### Live-Bildausspielung

- `Bocholter Gesundheitstage` wurde in `Events.visual_key` auf `business_messe_info` korrigiert und erneut deployt.
- Das vorherige Darts-/Sporthallenbild wurde live durch ein passenderes Beratungs-/Info-Motiv ersetzt.
- `Nacht der Ausbildung 2026 mit Isselburg` nutzt ebenfalls `business_messe_info` und zeigt ein passendes Vortrag-/Infoveranstaltungsbild.

### Bewertung gegen den ursprünglichen Kontrollpunkt

Bestanden:

1. Weekly-KI-Suchlauf läuft auf `main` und erzeugt Kandidaten.
2. Manual-KI-Intake schreibt Kandidaten in den Google-Sheet-Tab `Inbox`.
3. `Inbox.visual_key` wird mit KI-Vorschlag befüllt und das Dropdown mit 34 erlaubten Keys gesetzt.
4. Der tatsächlich genutzte PWA-/Apps-Script-Übernehmen-Pfad schreibt `visual_key` nach dem Fix nach `Events.visual_key`.
5. `Events.visual_key` steuert nach Deploy die Live-Bildausspielung.
6. Der konkrete Fehlfall `Gesundheitstage -> Dartsbild` ist live korrigiert.

Damit gilt der Main-Kontrollpunkt `KI-Suchlauf / Inbox / visual_key / Events-Build / Live-Bildausspielung prüfen` als abgeschlossen.

### Grenzen und Folgeworkpack

Nicht Teil dieses Beweises:

- Die Inbox-PWA zeigt den `visual_key` noch nicht redaktionell sichtbar/änderbar an. Das ist der nächste sinnvolle UI-Workpack.
- Der externe Google-Apps-Script-Code liegt nicht im Repo und muss bei künftigen Änderungen separat beachtet werden.
- Vollautomatische Bildtyp-Perfektion ist nicht bewiesen; der aktuelle Zielprozess bleibt KI-Vorschlag plus menschliche Inbox-Prüfung.
- Die automatische Ableitung sollte zusätzlich gehärtet werden, z. B. `Gesundheitstage` / `Gesundheitsprogramm` nicht auf Sport-Wettkampf-Motive fallen lassen.

Nächster empfohlener Workpack:

`Inbox-Review-UI: visual_key anzeigen, per Dropdown änderbar machen und den ursprünglichen KI-Vorschlag als Lernsignal erhalten.`
<!-- === END BLOCK: TEST_STATUS_MAIN_KI_SEARCH_INBOX_VISUAL_KEY_PROOF_2026_06_25 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_CONTENT_QUALITY_PROCESS_V2_INDEX_2026_06_24 | Zweck: macht den aktuellen Content-Pruefprozess-Stand im Test-Index sichtbar; Umfang: Audit-Report, Inbox-Pakete, Visual-Fit-V2, offene Folgebeweise === -->
## Aktueller Test-Index Zusatz – Content-Prüfung V2 (2026-06-24)

Status: Prozess-V2 plus KI-Faktencheck-/Prüfcache-V1 auf Staging geprüft; Grundprozess bestanden, offene Arbeitspakete bleiben fachlich bzw. als Feedback-Loop-Ausbau zu bearbeiten.

Bestandene Beweise:

- Content Quality Audit läuft nach Deploy und erzeugt `content-quality-report-full`.
- Der Report enthält Prozesskategorien, Correction Owner, Workbench-Gruppen und Automation Policy.
- Die Workbench `/inbox/` bzw. der Audit-Report zeigt Arbeitspakete getrennt an:
  - Repo-Datenpatch.
  - Quellencheck.
  - Faktencheck.
  - KI-Faktencheck.
  - Beobachten / Retry.
  - Visual-Fit.
- Repo-Datenpatch-Paket enthält die drei echten Activity-Datenlücken und nicht mehr Redirect-/Retry-Fälle.
- Sheet-/Quellenkorrektur ist über die Content-Inbox vorbereitet; Borken Open Air zeigt eine empfohlene offizielle Quelle und befüllt das Quellenfeld mit dieser URL, ohne automatisch zu speichern.
- Quellencheck und Retry werden nicht mehr mit Repo-Datenpatches vermischt.
- Faktencheck V1 und Visual-Fit V2 sind im Report aktiv.
- Visual-Fit V2 liefert konkrete Vorschläge für `visual_key`, `visual_motif`, Motivrolle und Asset-Status.

Letzter belegter Audit-Stand nach KI-Faktencheck-/Prüfcache-V1:

- Critical: 0.
- Review needed: 4.
- Warnings: 19.
- Automatisch erledigt: 118.
- Arbeitspakete: Repo-Datenpatch 3, Sheet-/Quellenkorrektur 1, Quellenprüfung 1, Faktencheck 4, KI-Faktencheck 3, Visual-Fit 11, Automatisch erledigt 118.
- Verification-Fallback: Cache entries loaded 1, Cache hits 1, AI candidates selected 3, Deferred by budget 0.

Offene Folgebeweise:

- KI-Suchlauf Feedback Loop / Self-Improving Search entwerfen und testen, damit Audit-/Inbox-Fehler automatisch als Suchlauf-Lernsignale nutzbar werden.
- Visual-Fit-Paket fachlich bewerten und daraus konkrete Folgeaktionen ableiten.
- Repo-Datenpatch-Paket als bewussten Sammelpatch vorbereiten und testen.
- Quellencheck-/Faktencheck-Fälle nach Cache-/KI-Fallbacklogik fachlich prüfen.
- UI/UX der Content-Inbox erst am Ende polieren.
<!-- === END BLOCK: TEST_STATUS_CONTENT_QUALITY_PROCESS_V2_INDEX_2026_06_24 === -->



<!-- === BEGIN BLOCK: TEST_STATUS_CONTENT_QUALITY_AI_CACHE_TARGET_2026_06_24 | Zweck: dokumentiert den bestandenen Beweis fuer KI-Faktencheck-Fallback, Pruefcache, Acceptance-Schicht und Runtime-Logging; Umfang: Staging-Artefakte, Farm-Country-Fair-Proof, Grenzen, Folgeworkpack === -->
## Content Quality – KI-Faktencheck-Fallback / Prüfcache bestanden (2026-06-25)

Status: bestanden als V1-Prozess auf Staging.

### Geprüfter Umfang

- Content Quality Audit mit Scope `full`.
- `ai_verification_candidate` für unsichere/blockierte Quellen.
- Budgetlimit für KI-Kandidaten.
- `content-ai-verification-candidates.json` als strukturiertes KI-Fallback-Artefakt.
- `Content_Verification_Cache_Staging` als Prüfcache.
- `Content_Verification_Acceptance_Staging` als robuste Acceptance-Schicht.
- Cache-Writeback aus Acceptance-Zeile.
- Cache-Hit im Folgelauf.
- Zeitlücken-Hinweis `source_has_time_but_dataset_missing_time`.
- Checkpoint-Logs im Workflow-/Python-Lauf.

### Letzter belegter Reportstand

Artifact: `content-quality-report-full` vom Staging-Lauf 2026-06-25.

- `Critical`: 0.
- `Review needed`: 4.
- `Warning`: 19.
- `Auto fixed`: 118.
- `Cache entries loaded`: 1.
- `Cache hits`: 1.
- `AI candidates total`: 3.
- `AI candidates selected`: 3.
- `Deferred by budget`: 0.

Aktuelle KI-Faktencheck-Kandidaten im belegten Lauf:

- `Pokémon-Tag`.
- `Das schönste Ei der Welt`.
- `Witte Venn Ahaus-Alstätte entdecken`.

### Farm-&-Country-Fair-Proof

- Manuell geprüfter Testfall: `Farm & Country Fair`, `verification_key=0c54bf13073014fd79f9b8d7`.
- Offizielle Quelle bestätigte Titel, Datum 26.–28.06.2026, Uhrzeiten und Adresse.
- Acceptance-Zeile wurde im Writeback-Lauf gelesen und in den Prüfcache übernommen.
- Folgelauf meldete `Cache entries loaded: 1` und `Cache hits: 1`.
- `Farm & Country Fair` erschien danach nicht mehr in `content-ai-verification-candidates.json`.
- Der Fall blieb nur noch für unabhängige Hinweise sichtbar, z. B. Visual-Fit oder Zeitlücke.

### Runtime-Logging-Proof

Der Audit-Schritt ist jetzt beobachtbar. Im GitHub-Log erscheinen Checkpoints wie:

- `=== Content Quality Audit: start ===`.
- `verification cache loaded: entries=1`.
- `supporting data loaded`.
- `event audit start`.
- `event audit progress: row=...`.

Kein enger Timeout wurde ergänzt, weil ein vorher scheinbar hängender Lauf später erfolgreich weiterlief.

### Grenzen

- Der Beweis gilt für die definierte Event-/Activity-Content-Prüfmatrix, nicht für jeden Text der gesamten Website.
- Es wird keine 100%-Fehlerfreiheit behauptet.
- Der Prozess garantiert nicht, dass jeder theoretisch mögliche externe Datenfehler erkannt wird.
- Belegt ist: erkannte oder nicht sicher bestätigbare Fälle werden typisiert, gecacht oder als Arbeitspaket sichtbar gemacht.
- KI-/Audit-Ergebnisse überschreiben keine fachlichen Daten automatisch.

### Folgeworkpack

Nächster sinnvoller Workpack: KI-Suchlauf Feedback Loop / Self-Improving Search.

Ziel: Audit-Findings, Inbox-Ablehnungsgründe, Korrekturgründe und KI-Faktencheck-Ergebnisse werden nicht manuell alle paar Wochen in Suchregeln übertragen, sondern strukturiert als Feedback für den nächsten KI-Suchlauf genutzt.
<!-- === END BLOCK: TEST_STATUS_CONTENT_QUALITY_AI_CACHE_TARGET_2026_06_24 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_CONTENT_QUALITY_PROCESS_V2_PROOF_2026_06_24 | Zweck: dokumentiert den bestandenen Staging-Prozessbeweis fuer Content Quality Guard V2; Umfang: Reportzahlen, Paketlogik, Inbox-Screenshots, Grenzen === -->
## Content Quality Guard V2 – Prozessbeweis bestanden (2026-06-24)

Status: bestanden als Prozessgrundlage; fachliche Paketbearbeitung offen.

### Geprüfter Umfang

- Automatischer Content-Audit nach Deploy.
- Audit-Report `content-quality-report-full`.
- `/inbox/` Content-Prüfung.
- Sheet-/Quellenkorrektur-Fall `Borken Open Air - ABBA Gold The Concert Show`.
- Repo-Datenpatch-Paket am Beispiel `Bürgerpark Rhede`.
- Pakettrennung für Quellencheck, Faktencheck und Visual-Fit.

### Report-Beweis

Letzter geprüfter Staging-Report:

- `critical`: 0.
- `review_needed`: 5.
- `warning`: 16.
- `auto_fixed` / automatisch erledigt: 118.
- `by_workbench_group`:
  - Automatisch erledigt: 118.
  - Visual-Fit: 12.
  - Repo-Datenpatch: 3.
  - Faktencheck: 3.
  - Quellenprüfung: 2.
  - Sheet-/Quellenkorrektur: 1.

### Bestandene Detailbeweise

- Borken Open Air:
  - Ticketportal als Primärquelle wird erkannt.
  - Empfohlene offizielle Quelle wird angezeigt.
  - Feld `Offizielle Quelle` wird mit der empfohlenen Quelle vorbefüllt.
  - Es erfolgt keine automatische Speicherung.
- Repo-Datenpatch-Paket:
  - Enthält `Bürgerpark Rhede`, `Suderwicker Märchenspielplatz`, `Waldlehrpfad am Vossenpand`.
  - Enthält nicht mehr `Unterduikmuseum Aalten` oder `Witte Venn`.
- Quellencheck:
  - `Unterduikmuseum Aalten` wird als Quellencheck geführt, nicht als Repo-Datenpatch.
- Retry/Beobachten:
  - technisch wackelige Quellen werden nicht als direkter Patchkandidat behandelt.
- Faktencheck:
  - `Farm & Country Fair`, `Pokémon-Tag`, `Playfountain - Bocholter Wasserspaß` werden als Prüfstichprobe geführt, nicht als bestätigte Korrektur.
- Visual-Fit:
  - Visual-Fit-Fälle werden separat vom normalen Content-/Datenpatch-Prozess geführt.
  - Der Report enthält vorgeschlagene `visual_key`-/`visual_motif`-Werte und Asset-Status.

### Ergebnisbewertung

- Der Content-Prüfprozess ist als automatische Vorprüfung und Paketierungslogik belastbar.
- Zielerreichung: ca. 90 %.
- Der Prozess ist noch kein Ersatz für fachliche Entscheidungen bei Quellenwiderspruch, Öffnungszeiten-/Kostenunsicherheit oder Bild-Motiv-Bewertung.

### Nicht Bestandteil dieses Abschlusses

- Kein Daten-Cleanup.
- Kein automatisches Setzen von `visual_key` oder `visual_motif`.
- Kein automatisches Überschreiben von Sheet-, DB- oder Repo-Inhalten.
- Kein UI/UX-Polish.

### Nächster Testfokus

- Visual-Fit-Paket fachlich bewerten.
- Danach erst konkrete Sammelpatches oder Sheet-/Quellenkorrekturen aus den geprüften Paketen ableiten.
<!-- === END BLOCK: TEST_STATUS_CONTENT_QUALITY_PROCESS_V2_PROOF_2026_06_24 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_MAIN_MERGE_LIVE_SMOKE_2026_06_19 | Zweck: dokumentiert erfolgreichen Main-Merge, Live-Smoke und nachgezogenen Today-Card-Alignment-Fix; Umfang: Abschlussanker vor KI-Suchlauf-Handoff-Test === -->
## Main-Merge / Live-Smoke – bestanden (2026-06-19)

Status: abgeschlossen.

Belegt:

- `staging` wurde erfolgreich nach `main` gemerged.
- Der Live-Bereich wurde manuell geprüft und wirkt stabil.
- Die öffentlichen Kernbereiche laden ohne gemeldeten Blocker:
  - `/`
  - `/events/`
  - `/aktivitaeten/`
  - `/bildnachweise/`
- Der nach dem Merge sichtbare Desktop-Alignment-Fehler in den drei Today-Cards wurde behoben.
- Die Textbereiche der Today-Cards beginnen nach dem Bild wieder konsistent auf gleicher Höhe.
- Der Event-Visual-Motif-Fit-Stand ist auf `main` ausgerollt; aus dem aktuellen Matrix-/Backlog-Stand ergeben sich keine offenen Bildproduktionsgaps.

Bewertung:

- Main/Live gilt für den aktuellen Stand als stabil.
- Es gibt keinen bekannten UI- oder Visual-Blocker, der vor dem nächsten Roadmap-Beweis erneut geöffnet werden muss.
- Nächster operativer Test bleibt der automatische KI-Suchlauf bzw. Visual-Key-Handoff auf `main`.

Nächster Kontrollpunkt:

- Dienstag, 2026-06-23, 11:00 Uhr: KI-Suchlauf / Inbox / `visual_key` / Events-Build / Live-Bildausspielung prüfen.
<!-- === END BLOCK: TEST_STATUS_MAIN_MERGE_LIVE_SMOKE_2026_06_19 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_FIT_QA_RULEPATCH_2026_06_18 | Zweck: dokumentiert fachliche Motiv-Fit-QA nach technischer Bildabdeckung; Umfang: Regelkorrekturen, Matrix-/Backlog-Zielstand, keine neue Bildproduktion === -->
## Event Visual Motif-Fit-QA – Regelpaket lokal geprüft (2026-06-18)

Status: auf `main` ausgerollt; Main-/Live-Smoke am 2026-06-19 ohne sichtbaren Blocker bestätigt.

Ausgangslage:
- Finaler Restbatch war deployed.
- Sicht-Smoke zeigte keine weißen/leeren Bildflächen.
- Technische Asset-Abdeckung war damit erfüllt, aber der fachliche Motiv-Fit war noch nicht abschließend belegt.

Korrekturtyp:
- Keine neuen Bilder.
- Keine Pool-Erweiterung.
- Nur deterministische Regelhärtung in `scripts/event_visual_keys.py` und `scripts/event_visual_motifs.py`.
- Matrix und Gap-Backlog wurden danach gegen den aktuellen Sheet-Export neu gebaut.

Repräsentative korrigierte Fälle:

| Event/Typ | Vorher | Nachher |
|---|---|---|
| Rosenmontagszug | `market_stalls / neutral_market_stalls` | `parade_festzug / neutral_parade` |
| Fahrradtour mit Guide | `nature_learning_wildlife / neutral_nature_learning` | `active_route_tour / guided_bike_tour` |
| Segwaytouren | `nature_learning_wildlife / neutral_nature_learning` | `active_route_tour / neutral_active_tour` |
| Bocholter Puppenspieltage | `family_play_outdoor / neutral_family_play_outdoor` | `kids_stage_story / puppet_theater` |
| Das schönste Ei der Welt | `family_play_outdoor / neutral_family_play_outdoor` | `kids_stage_story / puppet_theater` |
| Für Kinder: Ein Abend voller Bären-Geschichten | `family_play_outdoor / neutral_family_play_outdoor` | `kids_stage_story / neutral_kids_stage` |
| Quartierfest im Klostergarten | `market_stalls / neutral_market_stalls` | `city_festival_street / district_festival` |
| KANAREN - Sieben auf einen Streich | `local_history_heritage / neutral_local_history` | `literature_reading_talk / neutral_reading_talk` |
| In Szene gesetzt - Living History im LWL-Museum Textilwerk | `textile_machines_industry / weaving_mill` | `local_history_heritage / museum_history_exhibition` |
| Filmvorführung … Un secret | `local_history_heritage / neutral_local_history` | `film_screening / film_screening` |
| Familienschützenfest | `city_festival_street / neutral_city_festival` | `shooting_festival_tradition / shooting_festival_tradition` |
| Markterschließung Niederlande | `creative_making_workshop / neutral_creative_workshop` | `business_messe_info / business_fair` |
| Bocholt Blüht mit großem Oldtimerfestival | `market_stalls / neutral_market_stalls` | `vehicle_classic / classic_car_meet` |
| Führung Lebenselixier Wasser / Pröbstingsee | `city_tour_history / neutral_guided_city_tour` | `nature_learning_wildlife / walking_nature_tour` |

Lokaler Prüfbefund nach Regelpaket:
- `ready: 70`
- `gap_to_produce: 0`
- `candidate_to_integrate: 0`
- `review_rules: 0`
- `parked_candidate: 4`
- `not_needed: 26`
- Gap-Backlog: `0` offene Zeilen
- Premium Visual Contract Audit: bestanden, keine Errors.

Bewertung:
- Es bleibt keine technische Bildlücke.
- Die bekannten fachlichen Fehlzuordnungen aus der Motiv-Fit-QA wurden auf vorhandene passendere Ready-Pools umgebogen.
- Die Änderung produziert keine neuen Gaps und keinen neuen Bildbedarf.
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_FIT_QA_RULEPATCH_2026_06_18 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_FINAL_RESTBATCH_2026_06_18 | Zweck: dokumentiert den lokal geprueften Abschluss der aktuellen Event-Visual-Motiv-Gaps; Umfang: finaler Restbatch, Matrix, Gap-Backlog, Audit === -->
## Event Visual Motif-Fit – finaler Restbatch lokal geprüft (2026-06-18)

Status: auf `main` ausgerollt; Main-/Live-Smoke am 2026-06-19 ohne sichtbaren Blocker bestätigt.

Basis:
- Aktueller `staging`-ZIP-Stand nach Event Visual Gap Batch 02.
- Batch 02 war bereits deployed und per Sichtprüfung ohne fehlende Bildflächen geprüft.
- Finaler Restbatch wurde lokal auf diesen Stand angewendet.

Neu ergänzt:
- `motif-gap-info-evening-01.webp`
- `motif-gap-market-square-open-air-01.webp`
- `motif-gap-open-house-city-services-01.webp`

Regel-/Datenänderungen:
- `open_house_city_services` wurde von `review` auf `specific` gesetzt.
- `data/event_visual_pool.json` wurde um die 3 Ready-Assets ergänzt.
- `data/event_visual_phase2_acceptance_notes.json` dokumentiert die Abnahme.
- `data/event_visual_motif_matrix.tsv` wurde aus dem aktuellen Sheet-Export neu gebaut.
- `data/event_visual_gap_backlog.tsv` wurde aus dem aktuellen Sheet-Export neu gebaut.

Lokaler Prüfbefund nach Anwendung:
- `ready: 69`
- `gap_to_produce: 0`
- `candidate_to_integrate: 0`
- `review_rules: 0`
- `parked_candidate: 4`
- `not_needed: 27`
- Gap-Backlog: `0` offene Zeilen
- Premium Visual Contract Audit: bestanden, keine Errors.

Abschlussbewertung:
- Für den aktuellen Sheet-Stand sind alle notwendigen Event-Visual-Motive mit Ready-Bildern abgedeckt.
- Es wurden nicht alle theoretischen Visual-Subcategories auf Vorrat produziert; das bleibt bewusst Nicht-Ziel.
- Neue Event-Visual-Produktion nur bei neuem Sheet-Bedarf, neuem Gap-Backlog oder bewusster strategischer Poolentscheidung.
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_FINAL_RESTBATCH_2026_06_18 === -->



<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_IMAGE_MATERIAL_FORM_V1_2026_06_12 | Zweck: dokumentiert Abschluss der Bildmaterial-Abfrage fuer Kunden-Aktivitaeten inklusive Live-DB-Vorbereitung === -->
## Kunden-Aktivitäten: Bildmaterial-Abfrage V1 abgeschlossen (2026-06-12)

Status: umgesetzt, auf `staging` deployed und visuell geprüft.

Relevante Commits:
- `cd37051` – `Ergaenze Bildmaterial-Abfrage fuer Aktivitaeten`
- `220c6ac` – `Praezisiere Bildmaterial-Option im Aktivitaetsformular`
- `51b0d0b` – `Verbessere Bildmaterial Hinweise im Aktivitaetsformular`

Umgesetzter Zielzustand:
- Das Activity-Einreichungsformular fragt Bildmaterial optional ab.
- Es gibt weiterhin keinen Datei-Upload, keinen Storage und keine Medienverwaltung im Formular.
- Kunden können angeben, ob Bildmaterial vorhanden ist.
- Bei vorhandenem Bildmaterial wählen Kunden die Bereitstellungsart:
  - Downloadlink / Cloud-Ordner / Pressemappe
  - Website-Galerie mit passenden Bildern
  - Bilder später per E-Mail
  - Sonstiges
- Die Hinweise im Textfeld wechseln dynamisch je Bereitstellungsart.
- Die Bildrechte-Bestätigung ist nur erforderlich, wenn eigenes/verlinktes/später zugesendetes Bildmaterial angegeben wird.
- Die Angaben werden strukturiert in `submissions.activity_image_json` gespeichert.
- Die Inbox zeigt die Bildmaterial-Angaben in den Prüfdaten an.
- Die redaktionelle Bildhoheit bleibt bei Bocholt erleben: Kunden können Material anbieten, aber Bocholt erleben entscheidet, welches Bild veröffentlicht wird.

Datenbankstand:
- Staging-DB: `submissions.activity_image_json` vorhanden.
- Live-DB: `submissions.activity_opening_json` wurde geprüft; `submissions.activity_image_json` wurde als additive nullable JSON-Spalte vorbereitet.
- Migration im Repo: `api/sql/008_activity_image_json.sql`.

Smoke-Status:
- Formular lässt sich mit der Option „Nein, Bocholt erleben darf ein passendes Bild zur Verfügung stellen“ absenden.
- Einreichung landet in der Inbox.
- Inbox erkennt den redaktionellen Bildstatus korrekt.
- Dropdown-Wording und dynamische Platzhalter wurden auf Staging geprüft.

Abschluss:
- Kein weiterer Code-Patch für diesen Workstream offen.
- Kein Upload-Workpack in V1; ein echter Datei-Upload bleibt bewusst späterer separater Scope.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_IMAGE_MATERIAL_FORM_V1_2026_06_12 === -->

## Rolle dieses Dokuments

Dieses Dokument hält fest, welche Funktionen im Projekt „Bocholt erleben“ auf welchem Stand praktisch geprüft wurden.

Es ist keine Produktdefinition und keine Engineering-Regeldatei.

Kanonische Rollen bleiben:

- `Produktvertrag.md` = Produktlogik, Preise, Modelle, Kontingente
- `MASTER.md` = strategische Projektsteuerung
- `ENGINEERING.md` = harte Umsetzungsregeln
- `DEBUG.md` = wiederverwendbare Debug-/Proof-Snippets
- `TEST_STATUS.md` = geprüfte Funktionsstände und offene Regressionstests

---

## Arbeitsregel für dieses Dokument

Neue Einträge nur ergänzen oder konsolidiert ersetzen, wenn ein Teststand tatsächlich geprüft wurde.

Jeder Teststand muss enthalten:

- Datum
- Umgebung
- geprüfter ZIP-/Commit-Stand, sofern bekannt
- betroffene Funktion
- Ergebnis
- offene Punkte
- Live-Rollout-Hinweise

---

<!-- === BEGIN BLOCK: TEST_STATUS_MAIL_SYSTEM_PILOT_2026_06_05 | Zweck: dokumentiert den bestandenen Staging-Test des Mail-System-Piloten; Umfang: Einzeltermin-Eingangsbestätigung, HTML-Mail, Anrede und Referenzdarstellung === -->

# Teststand: Mail-System Pilot – Einzeltermin-Eingangsbestätigung

- Datum: 2026-06-05
- Umgebung: Staging
- relevante Commits:
  - `fbd7b6d` — Mail-System-Contract dokumentiert
  - `d05dbf8` — Mail-System-Pilot implementiert
  - `ec1db43` — Ansprechpartner-Feld ergänzt
  - `ceafedf` — Mail-Referenzen lesbar formatiert
- Ergebnis: bestanden / Pilot freigegeben

Geprüft und bestanden:
- HTML-Mail wird korrekt dargestellt.
- Betreff: `Dein Einzeltermin wird geprüft`.
- Persönliche Anrede funktioniert über das neue Pflichtfeld `Ansprechperson`.
- Datenbox zeigt Veranstaltung und lesbare Referenz, z. B. `BE-E50125C5-32A2`.
- Die vollständige UUID wird nicht mehr in der Kundenmail angezeigt.
- Die interne UUID bleibt für Stripe, Webhooks, Review und Datenbankzuordnung unverändert.
- Hinweisbox stellt klar: Veröffentlichung erst nach finaler redaktioneller Freigabe.
- Keine Formulierung mit `grundsätzlich`.
- Layout bleibt auch in schmaler Outlook-/Webmail-Ansicht nutzbar.

Offen:
- Weitere vorgefertigte Mails einzeln nach `MAIL_SYSTEM.md` migrieren.
- Nächste Mail: Zahlungslink-Mail Einzeltermin.
- Vor Production-Rollout: kontrollierte Test-Einreichung auf Production prüfen.

<!-- === END BLOCK: TEST_STATUS_MAIL_SYSTEM_PILOT_2026_06_05 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_PAYMENT_LINK_MAIL_EVENT_2026_06_05 | Zweck: dokumentiert den bestandenen Staging-Test der Zahlungslink-Mail fuer Einzeltermine; Umfang: Topic-Mail, CTA, Fristlabel, Referenz und Prozesskommunikation === -->

# Teststand: Mail-System – Zahlungslink-Mail Einzeltermin

- Datum: 2026-06-05
- Umgebung: Staging
- relevante Commits:
  - `4e13863` — Mail-Topics zentralisiert
  - `5cbcb84` — Zahlungslink-Mails ins Topic-System migriert
  - `0c055e1` — Zahlungslink-Frist in Mails präzisiert
- Ergebnis: bestanden / freigegeben

Geprüft und bestanden:
- Betreff: `Nächster Schritt: Zahlung für deinen Einzeltermin`.
- Persönliche Anrede funktioniert bei neuen Einreichungen mit `contact_name_snapshot`.
- Datenbox zeigt Veranstaltung, lesbare Referenz und `Zahlungslink gültig bis`.
- CTA-Button `Zahlung starten` wird korrekt angezeigt.
- Die vollständige UUID wird nicht in der Kundenmail angezeigt.
- Die Mail enthält keine Formulierung mit `grundsätzlich`.
- Die Mail unterscheidet sich klar von der Eingangsbestätigung und vermittelt Prozessfortschritt.
- Hinweisbox erklärt: Nach Zahlung wird der Termin final für die Veröffentlichung vorbereitet; sichtbar wird er erst nach redaktioneller Freigabe.
- Die Mail kündigt an, dass der Nutzer eine weitere E-Mail erhält, sobald der Termin sichtbar ist.
- Layout bleibt auch in schmaler Outlook-/Webmail-Ansicht nutzbar.

Bewertung:
- Die Mailkette Eingang → Zahlungslink → Veröffentlichung bleibt aus Nutzersicht nachvollziehbar.
- Keine zusätzliche Bocholt-erleben-Mail nur für `Zahlung erhalten` vorgesehen, um unnötige Mailmenge zu vermeiden.

Offen:
- Zahlungslink-Mail Aktivitätspräsenz noch bewusst prüfen.
- Veröffentlichung-/Live-Mail als nächste relevante Nutzer-Mail migrieren.

<!-- === END BLOCK: TEST_STATUS_PAYMENT_LINK_MAIL_EVENT_2026_06_05 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_MAIL_SYSTEM_TOPICS_FREEZE_2026_06_05 | Zweck: dokumentiert den konsolidierten Staging-Abschluss des zentralen Mail-Topic-Systems; Umfang: Eingangs-, Zahlungslink-, Freigabe-, Ablehnungs- und Magic-Link-Mails sowie interner Testendpunkt === -->

# Teststand: Mail-System Topic-Migration – Staging-Freeze

- Datum: 2026-06-05
- Umgebung: Staging
- geprüfter Stand: `57563b8`
- Ergebnis: bestanden / Mail-System-Topic-Migration abgeschlossen

## Umgesetzter Scope

Alle produktiven Nutzer-Mails laufen über das zentrale Mail-Topic-System in `api/_bootstrap.php`:

- `submission_received_event`
- `submission_received_activity`
- `payment_released_event`
- `payment_released_activity`
- `publication_approved_event`
- `publication_approved_activity`
- `rejection_event`
- `rejection_activity`
- `magic_link_portal`

Die produktiven Versandstellen bleiben fachlich getrennt, nutzen aber zentral gerenderte Maildaten:

- `api/submissions/init.php` → Eingangsbestätigung
- `api/submissions/release-payment.php` → Zahlungslink
- `api/submissions/approve.php` → Freigabe
- `api/submissions/reject.php` → Ablehnung
- `api/organizer-portal/request-magic-link.php` → Anbieterbereich-Zugangslink

## Geprüft und bestanden

- HTML-Mail-Layout wird zentral gerendert.
- Plain-Text-Fallback bleibt vorhanden.
- Persönliche Anrede funktioniert über `contact_name` bzw. Snapshot-Felder, sofern vorhanden.
- Lesbare öffentliche Referenz wird statt vollständiger UUID angezeigt.
- Zahlungslink-Mails zeigen `Zahlungslink gültig bis`.
- Magic-Link-Mail zeigt `Zugangslink gültig bis`.
- Freigabe-Mails formulieren bewusst `wurde freigegeben` statt `ist sichtbar`, weil tatsächliche Sichtbarkeit von Aktualisierung/Deploy abhängen kann.
- Aktivitäts-Freigabe-Mail erklärt die Tarifzuordnung über die Freigabe.
- Ablehnungs-Mails nutzen verpflichtende, mail-taugliche Ablehnungsgründe aus der Inbox.
- Alte technische Ablehnungs-Codes wie `DUBLETTE`, `DATUM_FALSCH`, `BOT_FEHLER_SONSTIGES` werden nicht mehr als Mailgrund versendet.
- Interner Testendpunkt `api/mail-topic-test.php` ermöglicht geschützten Topic-Testversand ohne Stripe-, DB- oder Inbox-Statusänderung.

## Sammeltest

Über den internen Mail-Topic-Testendpunkt wurden alle 9 Topics gegen Staging ausgelöst.

Alle Topics lieferten:

- `status: ok`
- `sent: true`
- erwarteten Betreff

Getestete Betreffzeilen:

- `Dein Einzeltermin wird geprüft`
- `Deine Aktivität wird geprüft`
- `Nächster Schritt: Zahlung für deinen Einzeltermin`
- `Nächster Schritt: Zahlung für deine Aktivitätspräsenz`
- `Dein Einzeltermin wurde freigegeben`
- `Deine Aktivität wurde freigegeben`
- `Dein Einzeltermin wurde nicht freigegeben`
- `Deine Aktivität wurde nicht freigegeben`
- `Dein Zugangslink für Bocholt erleben`

## Qualitäts- und Sicherheitsbewertung

- Keine produktive Mailstelle mit alter Plain-Text-Sonderlogik gefunden.
- Alte Ablehnungsgrund-Codes wurden aus Inbox/API-Scan entfernt.
- Alle relevanten PHP-Dateien ohne Syntaxfehler:
  - `api/_bootstrap.php`
  - `api/submissions/init.php`
  - `api/submissions/release-payment.php`
  - `api/submissions/approve.php`
  - `api/submissions/reject.php`
  - `api/organizer-portal/request-magic-link.php`
  - `api/mail-topic-test.php`
- Der Testendpunkt ist per Review-Zugang geschützt und verändert keine Datenbank-, Stripe- oder Inbox-Zustände.

## Offene Hinweise

- Review-Passwort rotieren, weil es im Chat sichtbar verwendet wurde.
- `api/mail-topic-test.php` bleibt vorerst als internes Testwerkzeug bestehen.
- Später entscheiden, ob der Testendpunkt dauerhaft bleibt oder vor Production-Härtung wieder entfernt wird.
- Untracked Event-Visual-PNGs unter `assets/event-visuals/` gehören zu einem separaten Visual-Workstream und wurden in diesem Mail-Workstream nicht berührt.

<!-- === END BLOCK: TEST_STATUS_MAIL_SYSTEM_TOPICS_FREEZE_2026_06_05 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_PUBLISH_EXPLAINER_FREEZE_2026_05_29 | Zweck: dokumentiert den Staging-Proof der zentralen Veröffentlichungserklärung und der kontextuellen Hilfelinks; Umfang: Informationsarchitektur, Mobile-/Desktop-UX, Ankerverhalten, Link-Hierarchie, Abgrenzung zu Funnel-/Checkout-Logik === -->

# Teststand: Veröffentlichungserklärung und kontextuelle Hilfelinks

## Stand

- Datum: 2026-05-29
- Umgebung: Staging
- Funktion: zentrale Erklärseite `/veroeffentlichung-erklaert/` plus kontextuelle Hilfelinks aus den Veranstalter-/Anbieter-Funnels
- relevante Staging-Commits:
  - `faebbe4` — zentrale Erklärseite angelegt
  - `1584e8f` — Ankerziele der Veröffentlichungserklärung korrigiert
  - `626584d` — Scroll-Offset für Erklärseiten-Anker korrigiert
  - `9afede6` — Link-Hierarchie der Veröffentlichungserklärung poliert
  - `563c130` — Unterstreichung bei Erklärlinks entfernt
- Ergebnis: bestanden / für den aktuellen Spitzenstand gefreezt

## Ziel der geprüften Funktion

Die bestehenden Funnel-Seiten sollen kurz, mobil-tauglich und handlungsorientiert bleiben.

Tiefe Erklärungen zu Prüfung, Freigabe, Zahlung, Fairness, Aktivitätspräsenz und messbarem Mehrwert werden nicht redundant auf mehrere Funnel-Seiten verteilt, sondern zentral auf `/veroeffentlichung-erklaert/` gebündelt.

## Geprüfte Seiten und Verbindungen

Bestanden:

- `/events-veroeffentlichen/`
  - Kontextlink `Unsicher, welcher Weg passt? Veröffentlichung einfach erklärt`
  - Ziel: `/veroeffentlichung-erklaert/#welcher-weg-passt`
- `/fuer-veranstalter/`
  - Kontextlink `Fragen zu Prüfung, Freigabe und veröffentlichten Terminen? Ablauf kurz erklärt`
  - Ziel: `/veroeffentlichung-erklaert/#zahlung-und-freigabe`
- `/angebote/sichtbar-werden/`
  - Kontextlink `Nicht sicher, ob dein Angebot eine Aktivität oder Veranstaltung ist? Unterschied kurz erklärt`
  - Ziel: `/veroeffentlichung-erklaert/#aktivitaet-oder-veranstaltung`
- `/ueber/`
  - dezenter Querverweis zur neuen Veröffentlichungserklärung
- `/veroeffentlichung-erklaert/`
  - zentrale Erklärung lädt auf Staging
  - Rückwege zu Veranstaltung, Mitgliedschaft und Aktivitätspräsenz funktionieren
  - FAQ-/Details-Elemente sind scanbar
  - Hash-Ziele öffnen passende FAQ-Punkte automatisch
  - Scroll-Offset hält Zielüberschrift unter dem sticky Header sichtbar
  - Link-Hierarchie unterscheidet Hilfelinks klar von Haupt-CTAs

## UX-Bewertung

Bestanden:

- Mobile:
  - Funnel-Seiten bleiben kurz.
  - Hilfelinks sind sichtbar, aber nicht dominant.
  - Keine altbackene Unterstreichung bei appartigen Navigationslinks.
  - Ankerziele landen inhaltlich richtig und mit sichtbarer Zielüberschrift.
- Desktop:
  - Erklärseite wirkt ruhig und strukturiert.
  - obere Rückwege wirken nicht mehr wie dominante CTA-Buttons.
  - FAQ und Abschlussnavigation sind geordnet.
- Informationsarchitektur:
  - Funnel-Seiten erklären nur kurz und verlinken bei Bedarf.
  - Die neue Seite übernimmt Tiefe und Vertrauen.
  - Keine Pop-up-/Modal-Lösung nötig.
  - Keine redundanten FAQ-Blöcke auf mehreren Funnel-Seiten.

## Bewusste Nicht-Prüfung

Nicht betroffen und nicht neu getestet:

- Formularvalidierung
- Stripe Checkout
- Webhooks
- Review-Inbox-Funktionen
- Magic-Link-Login
- Anbieter-Dashboard
- Datenbankstatus
- Veröffentlichung aus der Inbox

Begründung: Der Workpack hat nur statische Seitenstruktur, Links, CSS-Hierarchie, Sitemap und Erklärinhalt verändert. Funnel-, Backend- und Zahlungslogik wurden nicht angefasst.

## Bewertung

Der Roadmap-Punkt `Veranstalter-Funnel stärker auf belegbaren Mehrwert ausrichten` ist für den aktuellen Stand erfüllt.

Erfüllt sind:

- seriöse lokale Kommunikation
- keine unbelegten Reichweitenversprechen
- Mehrwert als Dienstleistung erklärt:
  - Prüfung
  - Aufbereitung
  - Sichtbarkeit
  - faire Darstellung
  - vorsichtig eingeordnete messbare Interaktionen
- bestehende Funnel-Seiten bleiben minimal-invasiv verändert
- neue zentrale Erklärseite vermeidet Redundanz und mobile Überlänge

## Offene Punkte

Keine offenen Entwicklungsblocker für diesen Workpack.

Später separat möglich, aber nicht Teil dieses Freezes:

- echte Anbieter-/Location-Berichte aus Nutzwertdaten weiter ausbauen
- monatlichen Wertbericht vorbereiten
- weitere konkrete Reporting-Ziele ergänzen
- Live nach Main-Rollout kurz smoke-testen

## Live-Rollout-Hinweise

Nach Merge/Main-Deploy nur Smoke prüfen:

- `/veroeffentlichung-erklaert/` lädt.
- Kontextlinks aus `/events-veroeffentlichen/`, `/fuer-veranstalter/` und `/angebote/sichtbar-werden/` führen zu den passenden Abschnitten.
- FAQ-Anker öffnen den passenden Punkt.
- Zielüberschrift bleibt unter dem Header sichtbar.
- Sitemap enthält `/veroeffentlichung-erklaert/`.

<!-- === END BLOCK: TEST_STATUS_PUBLISH_EXPLAINER_FREEZE_2026_05_29 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_REVIEW_PUSH_SMOKE_PROOF_2026_05_27 | Zweck: dokumentiert Review-/Push-Zugriffsschutz und fachliche Inbox-Flow-Einordnung; Umfang: Staging-Proof, Live-Proof, relevante Review-Fälle, bewusste Abgrenzung neuer Mitgliedschafts-Checkouts und offene Push-Versand-Grenzen === -->

# Teststand: Review-/Push-Zugriffsschutz und Inbox-Flow-Einordnung

## Stand

- Datum: 2026-05-27
- Umgebung: Staging und Live
- Funktion: Review-/Push-Zugriffsschutz, Inbox-Sichtbarkeit relevanter Review-Fälle, stille-Ausfall-Abgrenzung
- relevante Stände:
  - Staging: Commit `b858d2d` (`Harden push endpoint smoke checks`)
  - Live/Main: Build `fb6efe5443b`
- Ergebnis: bestanden für Zugriffsschutz und fachliche Inbox-Sichtbarkeit der relevanten Review-Fälle

## Ziel der geprüften Funktion

Nach einem STRATO-Deploy soll automatisch sichtbar sein, ob Review- und Push-Endpunkte versehentlich öffentlich erreichbar sind.

Zusätzlich muss für die relevanten Review-Fälle belegt sein, dass Einreichungen nicht still verschwinden, sondern in der Review-Inbox sichtbar werden. Push ist dabei nur ein interner Hinweis, nicht die führende Datenquelle.

## Automatisch geprüfte Endpunkte

Bestanden:

- `/api/submissions/review-list.php` ist ohne Review-Passwort nicht öffentlich erreichbar.
- `/api/push/config.php` ist ohne Berechtigung nicht öffentlich erreichbar.
- `/api/push/subscribe.php` ist ohne Berechtigung nicht öffentlich erreichbar.
- `/api/push/test.php` ist ohne Berechtigung nicht öffentlich erreichbar.
- `/api/push/notify-inbox.php` ist ohne Berechtigung bzw. Secret nicht öffentlich erreichbar.

## Belegte Staging-Prüfung

Bestanden mit Commit `b858d2d`:

- GitHub-Actions-Schritt `Smoke-check deployed site` läuft grün durch.
- Alle Review-/Push-Zugriffsschutzchecks sind bestanden.

## Belegte Live-/Main-Prüfung

Bestanden mit Build `fb6efe5443b`:

- GitHub-Actions-Schritt `Smoke-check deployed site` läuft grün durch.
- Alle Review-/Push-Zugriffsschutzchecks sind bestanden.

## Fachliche Inbox-Flow-Belege

Bestanden / bereits dokumentiert:

- Einzeltermin: Der Block `TEST_STATUS_LIVE_SINGLE_EVENT_PAYMENT_2026_05_27` belegt Live-Einreichung, Review-Inbox-Sichtbarkeit, Zahlungsfreigabe, echte Stripe-Zahlung, Veröffentlichung und Cleanup.
- Aktivitätspräsenz: Der Block `TEST_STATUS_ACTIVITY_PRESENCE_LIVE_READINESS_2026_05_26` belegt Live-Einreichung, Erfolgsseite, Eingangs-Mail und Live-Inbox-Sichtbarkeit als `Aktivitätspräsenz`.
- Event-Submission aus aktiver Mitgliedschaft: Die dokumentierten Membership-Reuse-Tests belegen, dass eine Formular-Einreichung mit aktiver Mitgliedschaft keinen neuen Stripe Checkout auslöst, in `submissions` landet, von `review-list.php` geliefert und in `/inbox/` mit Badge `Mitgliedschaft` angezeigt wird.
- Neue Mitgliedschafts-Checkouts sind bewusst kein Review-Inbox-Fall. Sie starten den Abo-/Zahlungsflow; der spätere Review-Fall entsteht erst bei einer Event-Submission aus aktiver Mitgliedschaft.

## Bewertung

Der Roadmap-Punkt `Review-/Push-Flows gegen stille Ausfälle prüfen` ist für P0 erfüllt.

Belegt ist: Die relevanten Review-Fälle erscheinen in der Review-Inbox. Die Review-Inbox ist die führende Arbeitsquelle; Push ist nur ein zusätzlicher interner Hinweis. Ein nicht empfangener Push ist deshalb kein stiller Verlust, solange die Review-Inbox korrekt ist.

## Grenzen des Tests

Nicht durch diesen Stand bewiesen:

- tatsächlicher Push-Versand in Live
- erzwungener technischer Push-Fehler während einer neuen Submission
- echte Live-Zahlung für Aktivitätspräsenz nach Zahlungslink (historische Grenze; Korrektur 2026-06-27: Aktivitaetspraesenz-/Abo-Livebeweis wird inzwischen als erledigt gefuehrt)

Diese Punkte sind keine Blocker für den stille-Ausfall-Proof. Der tatsächliche Push-Versand bleibt optional, solange die Review-Inbox zuverlässig ist.

## Nächste Prüfschritte

Für Roadmap-Punkt 5 ist kein weiterer P0-Test nötig.

Später optional:

- tatsächlichen Live-Push-Versand prüfen, falls Live-Push aktiv genutzt werden soll
- Aktivitaetspraesenz-/Abo-Zahlungsflow nur erneut testen, wenn ein konkreter Zahlungsflow geaendert wurde oder ein Stripe-/Billing-Symptom auftritt

<!-- === END BLOCK: TEST_STATUS_REVIEW_PUSH_SMOKE_PROOF_2026_05_27 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_DEPLOY_SMOKE_CHECKS_2026_05_27 | Zweck: dokumentiert automatisierte Deploy-Smoke-Checks nach STRATO-Upload für Staging und Live; Umfang: Workflow-Schritt, geprüfte Endpunkte, Proof-Commits und offene Grenzen === -->

# Teststand: Automatisierte Deploy-Smoke-Checks nach STRATO-Upload

## Stand

- Datum: 2026-05-27
- Umgebung: Staging und Live
- Funktion: Deploy-Smoke-Checks / GitHub Actions / STRATO-Deploy-Absicherung
- relevante Stände:
  - Staging: Commit `9f5b8a6` (`Add deploy smoke checks`)
  - Live/Main: Build `2b7f6daecf4c`
- Ergebnis: bestanden

## Ziel der geprüften Funktion

Nach einem STRATO-Deploy sollen zentrale öffentliche und geschützte Kernpfade automatisch geprüft werden, damit offensichtliche Laufzeit-, Upload-, Konfigurations- oder Zugriffsschutzfehler direkt im GitHub-Actions-Lauf sichtbar werden.

Der Smoke-Check ist bewusst kein vollständiger End-to-End-Test mit echter Zahlung, sondern ein technischer Fail-Fast-Rauchmelder nach dem Upload.

---

## Umgesetzte technische Basis

Bestanden:

- Neues Skript vorhanden:
  - `tools/smoke-check-deploy.py`
- Deploy-Workflow enthält nach dem STRATO-SFTP-Upload den Schritt:
  - `Smoke-check deployed site`
- Der Workflow prüft abhängig von der Umgebung automatisch:
  - Staging: `https://staging.bocholt-erleben.de`
  - Live/Main: `https://bocholt-erleben.de`
- Der Workflow prüft zusätzlich die deployte Build-Datei gegen den aktuellen Build:
  - `/meta/build.txt`

---

## Belegte Staging-Prüfung

Bestanden mit Commit `9f5b8a6`:

- Build-Datei entspricht dem deployten Commit.
- Startseite lädt mit HTTP `200` und HTML.
- `/angebote/` lädt mit HTTP `200` und HTML.
- `/events-veroeffentlichen/einreichen/` lädt mit HTTP `200` und HTML.
- `/api/status.php` liefert:
  - `status: ok`
  - `config: ok`
  - `database: ok`
- `/api/events/public.php` liefert gültiges JSON mit kontrollierter Struktur.
- `/api/stripe/create-checkout-session.php` liefert bei leerem JSON-Body kontrolliert HTTP `422` statt HTTP `500`.
- `/api/submissions/review-list.php` ist ohne Review-Passwort nicht öffentlich erreichbar.
- GitHub-Actions-Schritt `Smoke-check deployed site` läuft grün durch.

---

## Belegte Live-/Main-Prüfung

Bestanden mit Build `2b7f6daecf4c`:

- Build-Datei entspricht dem deployten Live-Build.
- Startseite lädt mit HTTP `200` und HTML.
- `/angebote/` lädt mit HTTP `200` und HTML.
- `/events-veroeffentlichen/einreichen/` lädt mit HTTP `200` und HTML.
- `/api/status.php` liefert:
  - `status: ok`
  - `config: ok`
  - `database: ok`
- `/api/events/public.php` liefert gültiges JSON mit kontrollierter Struktur.
- `/api/stripe/create-checkout-session.php` liefert bei leerem JSON-Body kontrolliert HTTP `422` statt HTTP `500`.
- `/api/submissions/review-list.php` ist ohne Review-Passwort nicht öffentlich erreichbar.
- GitHub-Actions-Schritt `Smoke-check deployed site` läuft grün durch.

---

## Bewertung

Der Roadmap-Punkt `Kritische Deploy-Smoke-Tests automatisieren` ist für Staging und Live/Main umgesetzt und praktisch bewiesen.

Damit ist nach Deploys automatisch sichtbar, ob zentrale Seiten, Status-/DB-Prüfung, Public-Events-API, Checkout-Validierung und Review-Zugriffsschutz grundsätzlich funktionieren.

`Public-Events-API: 0 DB-Events` ist in diesem Test kein Fehler. Der Smoke-Check bewertet hier Erreichbarkeit, JSON-Gültigkeit und Struktur, nicht die fachliche Datenmenge in der Datenbank.

---

## Grenzen des Smoke-Checks

Nicht durch diesen Smoke-Check bewiesen:

- echte Live-Zahlung
- erfolgreicher Live-Stripe-Webhook nach Zahlung
- Live-Erfolgsseite nach echter Zahlung
- Anbieterbereich/Dashboard-Status nach echter Zahlung
- finale Veröffentlichung nach echter Zahlung
- fachliche Vollständigkeit der Event- oder Activity-Daten
- tatsächlicher Push-Versand

Diese Punkte bleiben separate Tests in P0.

## Nächste technische Prüfschritte

Weiterhin offen in diesem Chat:

- Review-/Push-Flows gegen stille Ausfälle prüfen.
- echten Live-Zahlungsfall bewusst vollständig testen.

<!-- === END BLOCK: TEST_STATUS_DEPLOY_SMOKE_CHECKS_2026_05_27 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_LIVE_VALUE_REPORTING_TARGET_2026_05_27 | Zweck: dokumentiert Live-Beweis der Nutzwertmessung mit expliziter Reporting-Ziel-Zuordnung; Umfang: Activity-Tracking, value-track Payload, Dashboard-Zuordnung, Eigenes-Tracking-Status === -->

# Teststand: Live-Nutzwert-Tracking und Reporting-Ziel-Zuordnung

## Stand

- Datum: 2026-05-27
- Umgebung: Live
- Funktion: SEO-/Mehrwert-Dashboard, Nutzwert-Tracking, Activity-Reporting-Ziele
- geprüfte Inhalte: `Anholter Schweiz erleben` / `Biotopwildpark Anholter Schweiz`
- relevante Deploy-/Commit-Stände aus dem Testverlauf:
  - `96fd109` = Reporting-Ziel-Felder und Dashboard-Zuordnung eingeführt
  - `e8e2487` = `reporting_target` bleibt beim Normalisieren der Activities erhalten
- Ergebnis: technisch live bewiesen

## Ziel der geprüften Funktion

Nutzwertsignale sollen nicht nur allgemein gezählt werden, sondern für explizit konfigurierte Ziele einem Anbieter oder einer Location zuordenbar sein.

Für die Akquise ist entscheidend, dass künftig nicht nur Gesamtzahlen wie Website- oder Maps-Klicks sichtbar sind, sondern konkrete Ziele separat ausgewertet werden können.

---

## Belegte Live-Kette

Bestanden:

- Live-Seite `/angebote/` lädt nach Deploy.
- Activity `Anholter Schweiz erleben` öffnet die Detailansicht.
- `value-track.php` wird für `activity_detail_view` aufgerufen.
- `value-track.php` wird für `website_click` aufgerufen.
- Network-Payload enthält bei beiden Requests:
  - `reporting_target_type: "location"`
  - `reporting_target_id: "anholter-schweiz"`
  - `reporting_target_title: "Biotopwildpark Anholter Schweiz"`
- Das interne SEO-/Mehrwert-Dashboard zeigt den Bereich `Zuordnung / Reporting-Ziele`.
- Das Dashboard trennt explizite Ziele von `nicht zugeordnet`.
- Das Dashboard zeigt `Biotopwildpark Anholter Schweiz` als eigenes Reporting-Ziel.
- Belegter Live-Wert nach Test:
  - `2` Interaktionen gesamt
  - `1` Detail-Aufruf
  - `1` Website-Klick
  - `0` Maps-Klicks
- Eigenes Tracking wurde nach dem Test wieder ausgeschlossen.

---

## Bewertung

Der Roadmap-Punkt `Item- und Anbieter-Zuordnung für Nutzwertdaten prüfen und härten` ist für das erste konkrete Activity-Ziel technisch bewiesen.

Die Messung gilt allgemein weiter für Nutzwertsignale wie Detail-Aufrufe, Website-Klicks und Maps-/Routen-Klicks.

Explizite Anbieter-/Location-Auswertung erscheint aber nur für Inhalte, bei denen bewusst ein Reporting-Ziel gepflegt ist. Aktuell ist als erstes Ziel belegt:

- `Biotopwildpark Anholter Schweiz`

Alle nicht explizit zugeordneten Nutzwerte bleiben korrekt unter:

- `nicht zugeordnet`

Das ist Absicht. Unklare Anbieter-/Location-Zuordnungen werden nicht geraten.

---

## Offene Folgepunkte

Noch offen:

- Weitere Activities nur dann mit `reporting_target` ergänzen, wenn die Zuordnung fachlich sauber und belegbar ist.
- Historische Nutzwertdaten vor Einführung der Reporting-Ziele bleiben nicht rückwirkend zugeordnet.
- Der erste mail- oder screenshotfähige Feedbackbericht für eine Location ist noch nicht gebaut.
- Der echte Live-Zahlungsfall bleibt weiterhin ein separater P0-Test.

Nächster sinnvoller Schritt:

- ersten Feedbackbericht für `Biotopwildpark Anholter Schweiz` vorbereiten oder weitere sauber belegbare Reporting-Ziele auswählen.

<!-- === END BLOCK: TEST_STATUS_LIVE_VALUE_REPORTING_TARGET_2026_05_27 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_REPORTING_TARGET_EXPANSION_2026_05_27 | Zweck: dokumentiert Live-Kontrolltest für weitere Activity-Reporting-Ziele; Umfang: Aasee-Payload, Erweiterbarkeit der Reporting-Ziel-Logik, Datenbasis-Wartepunkt === -->

# Teststand: Erweiterte Activity-Reporting-Ziele

## Stand

- Datum: 2026-05-27
- Umgebung: Live
- Funktion: Activity-Tracking mit expliziten Reporting-Zielen
- geprüfter Inhalt: `Aasee erleben`
- erwartetes Reporting-Ziel: `Aasee Bocholt`
- Ergebnis: bestanden

## Ziel des Tests

Nach dem ersten Live-Beweis mit `Biotopwildpark Anholter Schweiz` wurde geprüft, ob die Reporting-Ziel-Logik auch für neu ergänzte Activities aus `data/offers.json` funktioniert.

Damit sollte ausgeschlossen werden, dass nur ein einzelner Sonderfall korrekt läuft.

---

## Bestandene Prüfung

Bestanden:

- Live-Seite `/angebote/` lädt.
- Activity `Aasee erleben` öffnet die Detailansicht.
- `value-track.php` wird für `activity_detail_view` aufgerufen.
- `value-track.php` wird für `website_click` aufgerufen.
- Network-Payload enthält korrekt:
  - `reporting_target_type: "location"`
  - `reporting_target_id: "aasee-bocholt"`
  - `reporting_target_title: "Aasee Bocholt"`

Damit ist belegt:

- `reporting_target` aus `data/offers.json` wird beim Normalisieren der Activities erhalten.
- Das Frontend sendet Reporting-Ziele auch für neu ergänzte Activities.
- Die Reporting-Ziel-Logik ist nicht auf `Anholter Schweiz` beschränkt.

---

## Bewertung

Der technische Teil der Reporting-Ziel-Zuordnung ist live bewiesen.

Die aktuellen Testklicks sind nur Funktionsbeweise. Sie werden nicht als Akquise-Erfolgsdaten bewertet.

Für Akquise-/Feedbackberichte wird jetzt organische Datenbasis gesammelt.

Nächste Bewertungslogik:

- nach ca. 7 Tagen: Plausibilitätscheck der ersten echten Zielsignale
- nach ca. 30 Tagen bzw. einem vollständigen 28-Tage-Zeitraum: prüfen, ob ein erster Feedbackbericht für eine Location belastbar ist

Automatische Erinnerung:

- 26.06.2026

Offen:

- erster screenshot- oder mailfähiger Feedbackbericht
- echter Live-Zahlungsfall als separater P0-Test

<!-- === END BLOCK: TEST_STATUS_ACTIVITY_REPORTING_TARGET_EXPANSION_2026_05_27 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_LIVE_SINGLE_EVENT_PAYMENT_2026_05_27 | Zweck: dokumentiert vollständigen Live-E2E-Test für Einzeltermin-Zahlung; Umfang: Einreichung, Review, Zahlungsfreigabe, Stripe-Zahlung, Veröffentlichung, Rücknahme/Cleanup === -->

# Teststand: Live-Einzeltermin-Zahlung und Veröffentlichung

## Stand

- Datum: 2026-05-27
- Umgebung: Live
- Funktion: Einzeltermin-Funnel / Review-first-Zahlung / Stripe-Live-Zahlung / Veröffentlichung / Cleanup
- getesteter Titel: `Bocholt erleben – Info-Nachmittag für Veranstalter`
- getestetes Datum: `14.11.2026`
- getesteter Betrag: `9,90 €`
- Referenz aus Mail: `f006f9f1-672b-4a15-a4a3-783501649a69`
- Ergebnis: bestanden

## Ziel des Tests

Geprüft wurde, ob ein echter Einzeltermin live vollständig durch den bezahlten Funnel laufen kann:

Einreichung → redaktionelle Vorprüfung → Zahlungsfreigabe → Zahlungslink-Mail → Stripe-Zahlung → Erfolgsseite → veröffentlichungsbereit → Veröffentlichung → öffentliche Sichtbarkeit.

Zusätzlich wurde geprüft, ob ein bereits veröffentlichter Zukunftstermin ohne direkte DB-Korrektur wieder aus der öffentlichen Sichtbarkeit genommen und sauber abgeschlossen werden kann.

---

## Bestandene Prüfung

Bestanden:

- Live-Einreichung über den Einzeltermin-Funnel funktioniert.
- Erfolgsseite nach Einreichung zeigt korrekt:
  - Veranstaltung wurde zur Prüfung eingereicht.
  - Zahlung folgt erst nach Prüfung per Zahlungslink.
- Bestätigungsmail nach Einreichung kommt an.
- Einreichung erscheint in der Review-Inbox mit Status sinngemäß `Neu eingereicht / Vorprüfung offen`.
- Button `Zur Zahlung freigeben` funktioniert.
- Inbox meldet:
  - Zahlungslink wurde versendet.
  - Veröffentlichung ist erst nach bestätigter Zahlung möglich.
- Zahlungslink-Mail kommt an.
- Stripe-Checkout öffnet live mit:
  - Produkt/Leistung: Einzeltermin veröffentlichen
  - Betrag: `9,90 €`
- Stripe-Zahlung wurde erfolgreich abgeschlossen.
- Erfolgsseite nach Zahlung zeigt korrekt:
  - Zahlung wurde abgeschlossen.
  - Veröffentlichung erfolgt erst nach finaler redaktioneller Freigabe.
- Inbox zeigt danach Status sinngemäß:
  - `Bezahlt / veröffentlichungsbereit`
- Button `Veröffentlichen` funktioniert.
- Inbox meldet erfolgreiche Veröffentlichung.
- Veröffentlichungsmail kommt an.
- Event ist danach im öffentlichen Eventbereich sichtbar.

---

## Bestandener Cleanup-/Rücknahme-Test

Bestanden:

- Der veröffentlichte Testtermin wurde über den Veranstalterbereich nachträglich geändert.
- Die Änderung führte zur erneuten redaktionellen Prüfung.
- Der Termin verschwand danach aus der öffentlichen Eventsuche.
- In der Review-Inbox erschien der Eintrag wieder mit Review-Risiko:
  - vom Veranstalter nachträglich geändert
- Ablehnung aus der Review-Inbox funktioniert.
- Ablehnungsgrund `SONSTIGES` wurde gesetzt.
- Ablehnungsmail kommt an.
- Der Testtermin ist damit nicht mehr öffentlich sichtbar und abgeschlossen.

---

## Bewertung

Der P0-Live-Zahlungsfall für Einzeltermine ist praktisch bewiesen.

Belegt ist insbesondere:

- Review-first statt Sofortzahlung funktioniert live.
- Zahlungsfreigabe per Review funktioniert live.
- Zahlungslink-Mail funktioniert live.
- Stripe-Live-Zahlung funktioniert mit korrektem Betrag.
- Erfolgsseite nach Zahlung funktioniert.
- Veröffentlichungsbereitschaft nach Zahlung wird korrekt erreicht.
- Finale Veröffentlichung funktioniert.
- Öffentliche Sichtbarkeit nach Veröffentlichung funktioniert.
- Rücknahme aus öffentlicher Sichtbarkeit durch Veranstalteränderung funktioniert ohne manuelle DB-Korrektur.
- Abschluss/Ablehnung nach Rücknahme funktioniert.

Damit ist der bezahlte Einzeltermin-Kernfluss für spätere Akquise belastbar.

---

## Offene Folgepunkte

Noch separat zu prüfen:

- Mitgliedschafts-/Abo-Live-Test: Korrektur 2026-06-27, als erledigt fuehren; neue Tests nur bei Flow-Aenderung oder Stripe-/Billing-Symptom.
- `past_due` und echtes Periodenende bleiben gesonderte Stripe-/Billing-Fälle.
- Test-/Proofstand in Roadmap berücksichtigen, aber keine weitere Codeänderung aus diesem Test ableiten.

<!-- === END BLOCK: TEST_STATUS_LIVE_SINGLE_EVENT_PAYMENT_2026_05_27 === -->

# Teststand: Veranstalter-Funnel + Kuratier-PWA-Review-Bridge

## Stand

- Datum: 2026-05-07
- Umgebung: Staging
- Staging-Domain: `staging.bocholt-erleben.de`
- geprüfter ZIP-Stand: `Bocholt-Erleben-staging - 2026-05-07T150253.537.zip`
- ZIP/Commit-Hinweis aus Archiv: `d31466ab64ba1b8215d212bca6dc8b10cfbe314c`
- Status: funktional auf Staging geprüft

## Ziel der geprüften Funktion

Eingereichte Events aus dem Veranstalter-Funnel sollen nach Zahlung oder aktiver Mitgliedschaft nicht direkt live gehen, sondern als Review-Fälle in der Kuratier-PWA erscheinen.

Die Herkunft darf nicht über die Event-Kategorie abgebildet werden, sondern über ein eigenes Herkunftsfeld:

- `single_event` = Einzelevent / einmalige Veröffentlichung
- `membership` = Einreichung aus aktiver Mitgliedschaft
- `ai_search` = KI-Suche / automatisierter Suchlauf

Anzeige in der Kuratier-PWA:

- Einzelevent
- Mitgliedschaft
- KI-Suche

---

## Geprüfte technische Basis

### Datenbank

Die Staging-Datenbankmigration wurde ausgeführt:

- `api/sql/003_submission_intake_origin_location_review.sql`

Bestätigte neue Felder in `submissions`:

- `intake_origin`
- `location_address`
- `location_public_confirmed`

Bestätigte Migration bestehender Einreichungen:

- bestehende Submissions wurden auf `single_event` oder `membership` einsortiert

### Backend / API

Geprüfte Endpunkte:

- `api/submissions/init.php`
- `api/stripe/create-checkout-session.php`
- `api/submissions/review-list.php`
- `api/submissions/reject.php`
- `api/submissions/approve.php`

Geprüfte Schutzlogik:

- direkter Aufruf von `/api/submissions/review-list.php` ohne Review-Passwort liefert `Review access denied`
- `/inbox/` fragt beim Öffnen nach Passwort
- nach korrektem Passwort lädt die Inbox ohne 503-Fehler
- DB-Review-Endpunkte werden mit Passwort-Header aus der Kuratier-PWA aufgerufen

### GitHub Actions / Deploy

Der Deploy-Workflow erzeugt die private `api/_config.php` serverseitig aus GitHub Secrets.

Für die Review-Endpunkte ist erforderlich:

- `STAGING_REVIEW_PASSWORD`
- `LIVE_REVIEW_PASSWORD`

Der geprüfte Staging-Stand nutzt das konfigurierte Review-Passwort erfolgreich.

---

## Bestandene Tests

### 1. Einzeltermin-Formular / Sichtprüfung

Route:

- `/events-veroeffentlichen/einreichen/`

Bestanden:

- nur Einzeltermin-Formular sichtbar
- kein Modell-Dropdown
- keine Starter-/Aktiv-/Dauerhaft-Auswahl
- Kostenhinweis: `9,90 € einmalig`
- kein Feld „Ansprechperson“
- Pflichtfelder vorhanden:
  - Veranstalter / Organisation
  - E-Mail-Adresse
  - Titel der Veranstaltung
  - Datum
  - Veranstaltungsort / Location
  - Adresse / offizieller Treffpunkt
  - PLZ
  - Stadt / Ort
  - Bestätigung zur Berechtigung und öffentlichen Ortsnennung
- optionale Felder vorhanden:
  - Uhrzeit
  - Link zur Veranstaltungsseite
  - Kurze Beschreibung oder Hinweise
- PLZ und Stadt / Ort stehen in einer Zeile
- CTA: `Einreichung abschließen`
- Hinweis: Danach folgt die Zahlungsmethode; Einreichung wird anschließend geprüft

### 2. Pflichtfeldtests

Bestanden:

- leer absenden wird vom Browser blockiert
- fehlende Ortsfelder werden blockiert
- fehlende Bestätigungs-Checkbox wird blockiert
- kein Stripe Checkout startet bei fehlenden Pflichtfeldern

### 3. Einzeltermin-Zahlungsflow

Bestanden:

- Einzeltermin-Formular absenden
- Stripe Checkout öffnet
- Stripe zeigt `Einzeltermin veröffentlichen`
- Stripe zeigt `9,90 €`
- kein Monatsbetrag
- keine Mitgliedschaft
- Sandbox-Zahlung erfolgreich
- Erfolgsseite erscheint
- Erfolgsseite sagt nicht `Deine Mitgliedschaft wurde verwendet`
- Erfolgsseite sagt sinngemäß: Einreichung bestätigt / wird geprüft

### 4. Einzeltermin → Kuratier-PWA

Bestanden:

- bezahlter Einzeltermin landet in `submissions`
- `payment_kind = single`
- `intake_origin = single_event`
- `location_address` gefüllt
- `location_public_confirmed = true`
- `review-list.php` liefert den Fall
- `/inbox/` zeigt den Fall
- Badge: `Einzelevent`
- Adresse/Treffpunkt sichtbar
- Ortsfreigabe sichtbar
- kein Review-Risiko, wenn Ortsdaten vollständig sind

### 5. Einzeltermin verwerfen

Bestanden:

- Einzeltermin-Review-Fall in `/inbox/` verworfen
- Fall verschwindet aus `review-list.php`
- DB-Status wird `rejected`
- `rejected_at` wird gesetzt
- `approved_at` bleibt `NULL`
- kein Kontingentverbrauch

### 6. Stripe-Abbruch

Bestanden:

- Stripe Checkout geöffnet
- Stripe-intern zurück/abbrechen geklickt
- Weiterleitung auf `/events-veroeffentlichen/abgebrochen/`
- Text: `Keine Zahlung abgeschlossen.`
- keine falsche Erfolgsmeldung

Bekannte UX-Notiz:

- Browser-Zurück aus Stripe kann die Formularseite mit Button-Ladezustand wiederherstellen.
- Der reguläre Stripe-Abbruchpfad ist trotzdem bestanden.

### 7. Magic-Link / Einzeltermin-Dashboard

Bestanden:

- bezahlter Einzeltermin mit empfangbarer Mail angelegt
- `/fuer-veranstalter/login/` geöffnet
- Zugangslink angefordert
- auf Staging erscheint Direktlink zum Öffnen
- Dashboard öffnet Einzeltermin-Ansicht
- Anzeige: `Meine Einreichung`
- keine aktive Mitgliedschaft
- keine Abo-/Tariffunktionen
- keine Mitgliedschaftsverwaltung für Einzeltermin-only-Nutzer

### 8. Mitgliedschafts-Reuse aus Formular

Bestanden:

- aktive Mitgliedschaft wird erkannt
- Formular-Einreichung mit aktiver Mitgliedschaft löst keinen neuen Stripe Checkout aus
- Erfolgsseite: `Deine Mitgliedschaft wurde verwendet`
- Submission landet in `submissions`
- `payment_kind = subscription`
- `intake_origin = membership`
- `requested_model_key` entspricht aktivem Modell, z. B. `unlimited`
- `location_public_confirmed = true`
- `review-list.php` liefert den Fall
- `/inbox/` zeigt den Fall
- Badge: `Mitgliedschaft`

### 9. Mitgliedschaft → Übernehmen → Kontingentverbrauch

Bestanden:

- Mitgliedschafts-Review-Fall in `/inbox/` übernommen
- DB-Status wird `approved`
- `approved_at` wird gesetzt
- `rejected_at` bleibt `NULL`
- `publication_consumptions` erhält eine Zeile
- `units = 1`
- `consumed_reason = approved_publication`
- `publication_entitlements.consumed_publications` wird erhöht
- Veröffentlichung geht dadurch nicht automatisch live

### 10. Veranstalter-Prefill aus eingeloggtem Dashboard

Bestanden:

- eingeloggter Veranstalterbereich zeigt aktiven Veranstalter
- Klick auf `Neue Veranstaltung einreichen`
- Formular öffnet
- Veranstalter / Organisation wird vorausgefüllt
- E-Mail-Adresse wird vorausgefüllt
- beide Felder sind gesperrt / nicht frei änderbar
- andere Eventfelder bleiben leer und editierbar

### 11. Mitgliedschafts-Reuse nach Prefill-Patch

Bestanden:

- vorausgefülltes Formular aus eingeloggtem Veranstalterbereich abgesendet
- kein Stripe Checkout
- Erfolgsseite: `Deine Mitgliedschaft wurde verwendet`
- neuer Review-Fall erscheint in `review-list.php`
- `intake_origin = membership`
- `payment_kind = subscription`
- `requested_model_key = unlimited`
- `review_risk_flags = []`

### 12. Review-Endpunkte geschützt

Bestanden:

- direkter Browseraufruf von `/api/submissions/review-list.php` liefert:
  - `status = error`
  - `message = Review access denied.`
- `/inbox/` fragt beim Öffnen nach Passwort
- nach korrektem Passwort lädt die Inbox
- keine 503-Fehlermeldung mehr
- `review-list.php`, `reject.php`, `approve.php` nutzen Review-Passwortschutz

### 13. Gesicherter Reject-Flow

Bestanden:

- DB-Review-Fall erzeugt
- `/inbox/` geöffnet
- Passwort eingegeben
- Fall sichtbar
- Verwerfen ausgeführt
- keine zweite Passwortabfrage erkennbar
- Fall verschwindet aus Review-Liste
- DB-Status wird `rejected`
- `rejected_at` gesetzt
- `approved_at = NULL`

### 14. Gesicherter Approve-Flow

Bestanden:

- DB-Review-Fall erzeugt
- `/inbox/` geöffnet
- Passwort eingegeben
- Fall sichtbar
- Übernehmen ausgeführt
- keine zweite Passwortabfrage erkennbar
- DB-Status wird `approved`
- `approved_at` gesetzt
- `rejected_at = NULL`
- `publication_consumptions` enthält Verbrauch
- Kontingentverbrauch funktioniert weiterhin

### 15. Mobile Header

Geprüfte Seiten:

- `/events-veroeffentlichen/`
- `/events-veroeffentlichen/einreichen/`
- `/fuer-veranstalter/`

Bestanden:

- Header einzeilig
- Logo + `Bocholt erleben` sichtbar
- Zugang-Icon rechts sichtbar
- kein Umbruch
- kein App-/Info-/Zugang-Chaos

### 16. Kuratier-PWA Anzeigeformat

Bestanden für normale Einzeltermine:

- Datum deutsch, z. B. `17.05.2026`
- Uhrzeit deutsch, z. B. `19:00 Uhr`
- Eingereicht am deutsch, z. B. `07.05.2026, 15:23 Uhr`
- technische Felder reduziert
- keine `Submission-ID` in der Hauptanzeige
- keine rohen doppelten Adress-Notes bei DB-Submissions
- relevante Kuratierfelder sichtbar:
  - Herkunft
  - Datum / Zeitraum
  - Uhrzeit / Zeiten
  - Ort
  - Adresse/Treffpunkt
  - Ortsfreigabe
  - Quelle / Veranstalter
  - Eingereicht am
  - Beschreibung / Hinweise
  - Links, falls vorhanden

---
<!-- === BEGIN BLOCK: TEST_STATUS_REVIEW_INBOX_LOCKED_STATE_2026_05_08 | Zweck: dokumentiert geprüften Sperrzustand der Kuratier-PWA nach Passwortabbruch; Umfang: Ergänzung zum Staging-Teststand Veranstalter-Funnel + Review-Bridge === -->

### 17. Kuratier-PWA Sperrzustand nach Passwortabbruch

Bestanden:

- `/inbox/` fragt beim Öffnen nach Passwort.
- Wird die Passwortabfrage abgebrochen oder durch Rausklicken geschlossen, lädt die Kuratier-PWA keine Review-Daten.
- Die Kuratier-PWA bleibt im Status `gesperrt`.
- Es erscheint ein Sperrhinweis mit Möglichkeit zum erneuten Laden.
- Nach korrekter Passwort-Eingabe lädt die Inbox normal.
- Direkter API-Aufruf ohne Passwort-Header bleibt blockiert:
  - `/api/submissions/review-list.php`
  - Ergebnis: `Review access denied.`

Geprüfter Stand:

- Datum: 2026-05-08
- Umgebung: Staging
- geprüfter ZIP-Stand: `Bocholt-Erleben-staging - 2026-05-08T081914.106.zip`
- Ergebnis: bestanden

Bewertung:

- Die Kuratier-PWA zeigt nach abgebrochener Passwortabfrage keinen falschen Zustand `Fertig`.
- Review-Daten werden nicht mehr ohne erfolgreiche Entsperrung geladen.
- Der Sperrzustand ist damit für Staging funktional korrekt.

<!-- === END BLOCK: TEST_STATUS_REVIEW_INBOX_LOCKED_STATE_2026_05_08 === -->

## Ergänzender Teststand: KI-/Sheet-Inbox-Regression

- Datum: 2026-05-11
- Umgebung: Staging
- Funktion: KI-/Sheet-Suchlauf → Google-Sheet-Inbox → Kuratier-PWA
- Ergebnis: bestanden

Bestanden:

- letzter Suchlauf landete korrekt in der Inbox
- Inbox-Fälle wurden in der Kuratier-PWA korrekt dargestellt
- KI-/Sheet-Pfad ist dadurch nicht mehr offene Regression
- KI-/Sheet-Darstellung ist kein Live-Blocker mehr

<!-- === BEGIN BLOCK: TEST_STATUS_INBOX_PUSH_V2_2026_05_12 | Zweck: dokumentiert den final geprüften internen Push-Hinweis für neue Inbox-Elemente; Umfang: Staging-only-Nutzungsmodus, technische Basis, Prüfergebnis, Grenzen und spätere Erweiterbarkeit === -->

## Ergänzender Teststand: Interne Push-Hinweise für neue Inbox-Elemente

- Datum: 2026-05-12
- Umgebung: Staging
- Funktion: einfache interne Web-Push-Benachrichtigung bei neuen Inbox-/Review-Elementen
- Ziel: reine Erinnerung, dass neue Elemente in der Inbox liegen
- Push-Text: `Neue Elemente in der Inbox.`
- Status: Staging funktionsfähig geprüft
- Aktueller Nutzungsmodus: dauerhaft nur Staging-Inbox für interne Kuratierung

### Umgesetzte technische Basis

Neu bzw. ergänzt:

- `api/push/_lib.php`
- `api/push/config.php`
- `api/push/subscribe.php`
- `api/push/notify-inbox.php`
- `api/push/test.php`
- `service-worker.js` mit zusätzlichem Push-Handler
- `/inbox/` mit internem Button `Push aktivieren`
- `/inbox/` mit Diagnose-Button `Lokale Testmeldung`
- Deploy-Konfiguration für optionale Push-Secrets
- best-effort Push-Auslöser für:
  - KI-/Sheet-Intake nach tatsächlich neu angehängten Inbox-Zeilen
  - bezahlte Event-Submissions nach Stripe-Webhook
  - Event-Submissions über aktive Mitgliedschaft ohne neuen Checkout

### Datenbank

In der Staging-Datenbank wurden die Push-Tabellen angelegt:

- `push_subscriptions`
- `review_notification_log`

Die SQL-Migration liegt im Repo als Push-Inbox-Migration unter `api/sql/`.

Hinweis: Die Datei wurde lokal wegen bestehender Nummerierung als `006_...` angelegt. Maßgeblich ist der SQL-Inhalt.

### Staging-Konfiguration

Für Staging wurden GitHub-Secrets angelegt:

- `STAGING_PUSH_VAPID_PUBLIC_KEY`
- `STAGING_PUSH_VAPID_PRIVATE_KEY_PEM`
- `STAGING_PUSH_SECRET`

Der Deploy-Workflow übernimmt diese Werte in die private `api/_config.php`.

### Geprüftes Verhalten

Bestanden:

- Push-Subscription für Desktop-Browser wurde gespeichert.
- Push-Subscription für Android/Chrome wurde gespeichert.
- Server-Push an beide Geräte wurde erfolgreich geloggt.
- `review_notification_log` zeigte für manuelle Tests `status = sent`.
- `attempted_count = 2`, `success_count = 2`, `failure_count = 0` wurde für Desktop + Android erreicht.
- Desktop erhielt sichtbare Pushmeldungen.
- Android erhielt nach lokaler Benachrichtigungskonfiguration sichtbare Pushmeldungen.
- Der KI-/Sheet-Intake erzeugte nach neu angehängten Inbox-Zeilen einen Push-Logeintrag mit `source_type = ki_inbox` und `status = sent`.
- Der Buttonstatus `Push aktiv` erkennt bestehende Browser-Subscriptions nach erneutem Öffnen der Inbox.
- Die lokale Testmeldung kann zur Geräte-/Browser-Diagnose genutzt werden.

### Bewusste Produktgrenze

Die Pushmeldung ist absichtlich minimal:

- kein Eventtitel
- keine Quellenangabe
- keine Detaildaten
- kein verpflichtendes Öffnen der Inbox beim Klick
- keine Benachrichtigungszentrale
- keine User-/Rollenverwaltung

Die Funktion dient nur als interner Hinweis, damit neue Inbox-Elemente nicht übersehen werden.

### Verbindlicher aktueller Nutzungsmodus

Es wird weiterhin ausschließlich mit der Staging-Inbox gearbeitet.

Das ist bewusst ausreichend, solange neue Review-Arbeit praktisch über Staging geprüft und kuratiert wird.

Live-Push ist aktuell kein notwendiger nächster Schritt und bleibt nur eine spätere Option, falls die Live-Inbox aktiv überwacht werden soll.

### Offene Folgepunkte

Aktuell nicht erforderlich für den laufenden Staging-Betrieb:

1. Live-Datenbank ebenfalls mit den Push-Tabellen migrieren, falls Live-Push später genutzt werden soll.
2. Live-Secrets setzen, falls Live-Push später genutzt werden soll:
   - `LIVE_PUSH_VAPID_PUBLIC_KEY`
   - `LIVE_PUSH_VAPID_PRIVATE_KEY_PEM`
   - `LIVE_PUSH_SECRET`
3. Live-Deploy ausführen und Push in der Live-Inbox separat aktivieren, falls Live-Push später genutzt werden soll.
4. Beim nächsten echten externen Event-Submission-Fall prüfen, ob nach Review-Relevanz automatisch eine Pushmeldung kommt.
5. Spätere Aktivitätenanfragen müssen denselben zentralen Endpoint `/api/push/notify-inbox.php` bzw. die zentrale Push-Funktion anschließen.

### Bewertung

Der Push-Mechanismus ist als kleiner, zentraler Add-on-Mechanismus umgesetzt.

Er ist nicht an einen bestimmten Inhaltstyp gebunden und kann später für Aktivitätenanfragen, Location-/Anbieteranfragen oder weitere Review-Quellen wiederverwendet werden.

Für den aktuellen Arbeitsmodus ist Staging-Push ausreichend und abgeschlossen.

<!-- === END BLOCK: TEST_STATUS_INBOX_PUSH_V2_2026_05_12 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_SINGLE_EVENT_REVIEW_BEFORE_PAYMENT_E2E_2026_05_12 | Zweck: dokumentiert den final geprüften Einzelevent-Funnel mit Vorprüfung vor Zahlung, Zahlungslink und direkter öffentlicher DB-Veröffentlichung; Umfang: Staging-E2E, Live-Smoke, Architekturentscheidung und verbleibende Beobachtungspunkte === -->

## Ergänzender Teststand: Einzelevent-Funnel mit Vorprüfung vor Zahlung

- Datum: 2026-05-12
- Umgebung: Staging E2E, Live Smoke
- geprüfter ZIP-/Arbeitsstand: `Bocholt-Erleben-staging - 2026-05-12T201538.920.zip` plus anschließende Patches für Review-before-payment, Public-DB-Feed und Inbox-Ergonomie
- Funktion: Einzeltermin / Einzelevent einreichen, vorprüfen, zur Zahlung freigeben, bezahlen, final veröffentlichen
- Ergebnis: E2E auf Staging bestanden; Live-Smoke nach Main-Rollout bestanden
- Status: V1 abgeschlossen

### Zielzustand dieses Teststands

Einzelevents laufen nicht mehr über Sofortzahlung.

Geprüfter Zielablauf:

1. Einzelevent-Formular absenden.
2. Submission wird ohne Stripe-Redirect gespeichert.
3. Status wird `pending_review`.
4. Eingangsbestätigung per Mail wird versendet.
5. Fall erscheint in der Kuratier-Inbox.
6. Kurator-Aktion `Zur Zahlung freigeben`.
7. Status wird `payment_released`.
8. Zahlungslink-Mail mit internem Link `/zahlung-starten/?token=...` wird versendet.
9. Interne Zahlungsstart-Seite prüft Token und erzeugt serverseitig eine frische Stripe Checkout Session.
10. Stripe-Zahlung wird abgeschlossen.
11. Stripe-Webhook setzt Submission auf `paid`.
12. Fall erscheint in der Kuratier-Inbox als `Bezahlt / veröffentlichungsbereit`.
13. Kurator-Aktion `Veröffentlichen`.
14. Status wird `approved`.
15. Verbrauch wird erst bei finaler Freigabe gebucht.
16. Veröffentlichungs-Mail wird versendet.
17. Event erscheint über `/api/events/public.php` im öffentlichen Eventbereich.

### Verbindliche Produktlogik

Der neue Zahlungslink-Flow gilt nur für Einzelevent-/Einzeltermin-Einreichungen:

- `requested_model_key = single`
- `payment_kind = single`
- `intake_origin = single_event`

Mitgliedschaften bleiben unverändert:

1. Einreichung
2. Prüfung
3. finale Freigabe
4. Verbrauch erst bei finaler Freigabe

Zahlung bedeutet weiterhin keine automatische Veröffentlichung.

### Verbindliche technische Architekturentscheidung

Der öffentliche Eventfeed ist ab diesem Stand hybrid:

1. Google Sheet / `data/events.json` für redaktionelle, KI-/Sheet- und manuell gepflegte Events.
2. Approved DB-Submissions aus `/api/events/public.php` für Veranstalter-Einreichungen.

Veranstalter-Einreichungen werden nach finaler Freigabe nicht ins Google Sheet geschrieben.

Der V1-Publishing-Handoff für Veranstalter-Einreichungen ist:

`submissions.status = approved`
→ `/api/events/public.php`
→ `js/main.js` mischt approved DB-Events zusätzlich in den öffentlichen Feed
→ Event-Card erscheint ohne Sheet-Deploy im öffentlichen Eventbereich.

### Geprüfte technische Basis

Neu bzw. relevant ergänzt:

- `api/sql/006_single_event_review_before_payment.sql`
- `api/submissions/init.php`
- `api/submissions/release-payment.php`
- `api/submissions/review-list.php`
- `api/submissions/reject.php`
- `api/submissions/approve.php`
- `api/stripe/create-checkout-session.php`
- `api/events/public.php`
- `zahlung-starten/index.html`
- `events-veroeffentlichen/einreichen/index.html`
- `events-veroeffentlichen/erfolg/index.html`
- `events-veroeffentlichen/abgebrochen/index.html`
- `inbox/index.html`
- `js/publish-funnel.js`
- `js/main.js`
- `js/seo-schema.js`
- `service-worker.js`

### Bestandene Staging-E2E-Tests

Bestanden:

- Einzelevent wurde über `/events-veroeffentlichen/einreichen/` eingereicht.
- Erfolgsseite zeigte `Deine Veranstaltung wurde zur Prüfung eingereicht`.
- Eingangs-Mail wurde versendet.
- Fall erschien in `/inbox/` mit Status `Neu eingereicht / Vorprüfung offen`.
- Button `Zur Zahlung freigeben` erzeugte Zahlungsfreigabe.
- Zahlungslink-Mail wurde versendet.
- `/zahlung-starten/?token=...` leitete korrekt zu Stripe Checkout weiter.
- Stripe-Zahlung wurde erfolgreich abgeschlossen.
- Erfolgsseite zeigte `Danke, die Zahlung wurde abgeschlossen`.
- Fall erschien in `/inbox/` mit Status `Bezahlt / veröffentlichungsbereit`.
- Button `Veröffentlichen` setzte den Fall final frei.
- Veröffentlichungs-Mail wurde versendet.
- `/api/events/public.php` enthielt die freigegebene Submission als `submission_db_approved`.
- Staging-Startseite zeigte die veröffentlichte Event-Card im öffentlichen Feed.
- `publication_consumptions` enthielt genau einen Verbrauch für die veröffentlichte Submission.
- Verbrauch wurde erst bei finalem `approved` gebucht.

### Bestandene Kurator-UX-Tests

Bestanden:

- `pending_review` bleibt aktiv vorne, weil Kurator-Aktion nötig ist.
- `payment_released` und `checkout_started` blockieren die aktive Kuratierung nicht mehr.
- Wartende Zahlungsfälle werden ans Ende sortiert.
- Button `Später prüfen` verschiebt den aktuellen Fall lokal ans Ende der aktuellen Ansicht.
- `paid` bleibt aktiv sichtbar, weil final `Veröffentlichen` oder `Ablehnen` nötig ist.
- `approved` und `rejected` verschwinden aus der Inbox.
- KI-/Sheet-Inbox-Pfad bleibt unverändert nutzbar.

### Bestandene Live-Smoke-Tests

Bestanden nach Main-/Live-Rollout:

- Live-DB-Migration `006_single_event_review_before_payment.sql` wurde ausgeführt.
- `https://bocholt-erleben.de/api/events/public.php` liefert `status = ok`.
- Live-DB-Feed lieferte erwartbar `total = 0`, solange keine approved Live-DB-Submissions existieren.
- `https://bocholt-erleben.de/zahlung-starten/` lädt und zeigt ohne Token korrekt `Zahlungslink ungültig`.
- `https://bocholt-erleben.de/events-veroeffentlichen/einreichen/` zeigt den neuen Vorprüfungsflow:
  - `Einzeltermin zur Prüfung einreichen`
  - `Zahlung erst nach redaktioneller Vorprüfung`
  - Button `Zur Prüfung einreichen`
- Live-Secrets für DB, Stripe, Mail und Review sind hinterlegt.
- Live und Staging nutzen getrennte Datenbanken; Staging-Testevents erscheinen dadurch nicht automatisch live.

### Live-Go-Live-Hinweise

Für Live ist vor dem echten Produktivbetrieb erforderlich bzw. bereits berücksichtigt:

- Live-DB-Migration `006_single_event_review_before_payment.sql` muss auf der Live-Datenbank ausgeführt sein.
- Live-Stripe-Secrets müssen gesetzt sein:
  - `LIVE_STRIPE_SECRET_KEY`
  - `LIVE_STRIPE_WEBHOOK_SECRET`
  - `LIVE_STRIPE_PRICE_SINGLE`
  - `LIVE_STRIPE_PRICE_STARTER`
  - `LIVE_STRIPE_PRICE_ACTIVE`
  - `LIVE_STRIPE_PRICE_UNLIMITED`
- Live-Mail-Secrets müssen gesetzt sein.
- Live-Review-Passwort muss gesetzt sein.
- Stripe-Live-Webhook muss auf `/api/stripe/webhook.php` zeigen.
- Ein echter Live-Zahlungsfall ist erst bei realer Live-Zahlung vollständig bewiesen.

### Verbleibende Beobachtungspunkte

Keine offenen Entwicklungsblocker für den Einzelevent-Funnel.

Bewusst nicht mit Testzahlung belegt:

- echter Live-Zahlungslink
- echte Live-Stripe-Zahlung
- Live-Webhook auf `paid` bei realem Zahlungsvorgang

Das ist kein Code-Blocker, da Staging-E2E bestanden ist und die Live-Stripe-Infrastruktur bereits produktiv genutzt wurde.

---

## Offene Tests / bekannte Lücken

### Live-Rollout

Der Einzelevent-Funnel ist live smoke-getestet.

Noch nicht durch echten externen Produktivfall belegt:

1. echter Live-Einzeltermin
2. echte Live-Zahlung
3. Live-Webhook setzt `paid`
4. finale Live-Veröffentlichung erscheint über `/api/events/public.php` im öffentlichen Feed

Keinen echten Live-Zahlungstest ohne bewusste Entscheidung.

---

## Bekannte UX-Verbesserungen nach diesem Teststand

Diese Punkte sind keine Blocker für den geprüften Kernflow:

1. Login/Zugang könnte komfortabler werden, insbesondere direkter Weiter nach Magic-Link-Anforderung auf Staging.
2. Hinweistext in der Kuratier-PWA zur alten Passwortlogik ist intern/missverständlich, aber nicht kritisch, da nur für Admin-Nutzung.
3. Label `Adresse / offizieller Treffpunkt` könnte später klarer werden, z. B. `Straße, Hausnummer oder offizieller Treffpunkt`.
4. Browser-Zurück aus Stripe kann den Formularbutton im Ladezustand zeigen.

---

## Aktueller Funktionsstatus

Der Kernzielzustand ist erfüllt:

- Einzelevents werden zuerst ohne Zahlung zur Vorprüfung eingereicht.
- Zahlungslink wird nur nach Kurator-Freigabe erzeugt.
- Zahlung erfolgt über internen Zahlungsstart-Link und serverseitig erzeugte Stripe Checkout Session.
- Stripe-Webhook setzt bezahlte Einzelevents auf `paid`.
- Finale Veröffentlichung erfolgt in der Kuratier-Inbox.
- Verbrauch wird erst bei finaler Freigabe gebucht.
- Approved DB-Submissions erscheinen über `/api/events/public.php` im öffentlichen Eventfeed.
- Öffentlicher Feed ist hybrid: `data/events.json` plus approved DB-Submissions.
- Mitgliedschaftslogik bleibt unverändert.
- KI-/Sheet-Fälle bleiben unverändert im bisherigen Sheet-/Inbox-Pfad.
- Review-Endpunkte sind gegen direkten öffentlichen Zugriff geschützt.
- Google-Sheet-basierter Inbox-Pfad ist praktisch geprüft.
- Einzelevent-Funnel ist als V1 abgeschlossen.

Nicht vollständig belegt ist nur:

- erster echter Live-Zahlungsfall mit realem externem Nutzer

<!-- === END BLOCK: TEST_STATUS_SINGLE_EVENT_REVIEW_BEFORE_PAYMENT_E2E_2026_05_12 === -->
<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_FUNNEL_E2E_2026_05_18 | Zweck: dokumentiert den geprüften Aktivitäten-Funnel mit Aktivitätspräsenz, Review-before-payment, bestehender Subscription, Veröffentlichung, Änderung und finalem Wording-/UI-Zielzustand; Umfang: Staging-E2E, UI-Konsolidierung, bekannte Grenzen und finaler Smoke-Testbedarf === -->

## Ergänzender Teststand: Aktivitäten-Funnel / Aktivitätspräsenz

- Datum: 2026-05-18
- Umgebung: Staging
- geprüfter ZIP-/Arbeitsstand: `Bocholt-Erleben-staging (16).zip` plus anschließende Wording-/UI-Patches im Aktivitäten-Funnel
- Funktion: Aktivitätspräsenz einreichen, prüfen, Zahlung freigeben, veröffentlichen, im Anbieterbereich verwalten und ändern
- Ergebnis: Kernflow E2E im Chat-Stand geprüft; Wording/UI anschließend auf schlanken mobilen Enterprise-Funnel konsolidiert
- Status: fachlich und funktional weitgehend abgenommen; finaler Smoke-Test nach letzter Patch-Anwendung erforderlich

### Zielzustand dieses Teststands

Der Aktivitäten-Funnel ist ein schlanker, mobiler Enterprise-Funnel.

Kanonische Routen:

1. `/angebote/sichtbar-werden/`
2. `/angebote/sichtbar-werden/einreichen/`
3. `/angebote/sichtbar-werden/erfolg/`

Zielwirkung:
Verstehen → Eignung prüfen → Tarif wählen → Formular ausfüllen → Prüfung abwarten

Nicht gewünscht:

lange Produkt-Erklärung → viele Hinweise → Formular
Verbindliche Produktlogik

Aktivitätspräsenzen sind eine eigene Produktlinie und getrennt von Event-Veröffentlichungen.

Tarife:

Tarif	Interner Schlüssel	Preis	Enthalten
Aktivitätspräsenz Basis	activity_basic	9,99 € / Monat	1 veröffentlichte Aktivität
Aktivitätspräsenz Plus	activity_plus	19,99 € / Monat	bis zu 3 veröffentlichte Aktivitäten

Verbindliche Regeln:

Zahlung erst nach Freigabe.
Zahlungslink erst nach Eignungsprüfung.
Veröffentlichung erst nach redaktioneller Aufbereitung und finaler Freigabe.
Erst veröffentlichte Aktivitäten zählen in deinem Tarif.
Abgelehnte Aktivitäts-Einreichungen zählen nicht.
Noch nicht veröffentlichte Aktivitäts-Einreichungen zählen nicht.
Bestehende aktive Aktivitätspräsenz kann für neue Einreichungen genutzt werden.
Erneute Veröffentlichung nach Änderung darf keinen Doppelverbrauch auslösen.
Final konsolidierter UI-/Wording-Zielzustand
Entscheidungsseite /angebote/sichtbar-werden/

Reihenfolge:

Hero
→ Eignungskarte
→ Tarifwahl
→ kurzer Ablaufblock

Hero:

Als Aktivität bei Bocholt erleben sichtbar werden
Für dauerhaft verfügbare oder regelmäßig buchbare Angebote. Zahlung erst nach Freigabe.

Eignungskarte:

Für welche Angebote ist die Aktivitätspräsenz gedacht?

Geeignet:

Eigenständige Angebote, die dauerhaft, saisonal oder regelmäßig buchbar sind – z. B. Bouldern, Kindergeburtstage oder Kurse.

Nicht geeignet:

Einzeltermine, andere Preise, andere Öffnungszeiten oder kleine Textänderungen derselben Aktivität. Einzeltermine gehören in den Veranstaltungsbereich.

Tarifwahl:

Wähle den passenden Tarif

Buttons:

Basis auswählen
Plus auswählen

Ablauf:

Einreichen → Prüfung → Zahlungslink → Aufbereitung → Veröffentlichung.
Formularseite /angebote/sichtbar-werden/einreichen/

Reihenfolge:

Hero
→ Einreichung vorbereiten
→ Tarif für die Prüfung
→ Formularabschnitte
→ Bestätigung
→ Button
→ kurzer Zahlungs-/Prüfungshinweis

Hero:

Aktivität zur Prüfung einreichen
Trage die Angaben ein. Zahlung erst nach Freigabe.

Formularblock:

Einreichung vorbereiten
* Pflichtfelder
Zahlung erst nach Freigabe.

Tarifbereich:

Tarif für die Prüfung

Die Tarifauswahl bleibt sichtbar und änderbar.

Verbindliche Feldreihenfolge:

Kontaktdaten
Anbieter / Organisation
Ansprechpartner
E-Mail-Adresse
Aktivität
Name der Aktivität
Name des Standorts
Website / Buchungslink
Adresse / offizieller Treffpunkt
PLZ
Stadt / Ort
Beschreibung und Verfügbarkeit
Kurzbeschreibung der Aktivität
Verfügbarkeit
Weitere Hinweise
Bestätigung

Verfügbarkeit ist ein Dropdown mit:

Bitte auswählen
Dauerhaft verfügbar
Regelmäßig buchbar
Saisonal verfügbar
Nach Vereinbarung buchbar

Button:

Zur Prüfung einreichen

Hinweis unter Button:

Nach der Einreichung prüfen wir die Aktivität zuerst. Wenn sie zu Bocholt erleben passt, erhältst du per E-Mail einen Zahlungslink.
Erfolgsseite /angebote/sichtbar-werden/erfolg/

Zu unterscheidende Zustände:

flow=submitted
Aktivität eingereicht
keine Zahlung gestartet
Prüfung vor Zahlungslink
Prüfung → Zahlungslink → Aufbereitung/Veröffentlichung
primärer CTA: Zur Aktivitätenseite
sekundärer CTA: Weitere Aktivität einreichen
flow=existing-subscription
aktive Aktivitätspräsenz vorhanden
keine neue Zahlung nötig
redaktionelle Prüfung und Aufbereitung
Veröffentlichung erst nach Freigabe
primärer CTA: Anbieterbereich öffnen
sekundärer CTA: Zur Aktivitätenseite
session_id vorhanden
Zahlung erhalten
redaktionelle Aufbereitung und finale Prüfung
Veröffentlichung erst nach Freigabe
Aktivität zählt erst nach Veröffentlichung im Tarif
primärer CTA: Anbieterbereich öffnen
Bereits bestandene Staging-E2E-Funktionstests

Im Chat-Stand wurden diese Kernfunktionen praktisch geprüft bzw. als erreicht dokumentiert:

Einstieg über Aktivitätenseite:
Feed-Card Als Aktivität sichtbar werden
Weiterleitung auf /angebote/sichtbar-werden/
Entscheidungsseite:
Basis-Tarif führt zu /angebote/sichtbar-werden/einreichen/?plan=activity_basic
Plus-Tarif führt zu /angebote/sichtbar-werden/einreichen/?plan=activity_plus
Formularseite:
?plan=activity_basic wählt Basis voraus
?plan=activity_plus wählt Plus voraus
Tarif kann im Formular geändert werden
Pflichtfeldvalidierung greift
Verfügbarkeit ist als Dropdown vorgesehen
Einreichung führt auf die Erfolgsseite
Mail:
Eingangs-Mail nach Einreichung wurde geprüft
Zahlungslink-Mail nach Zahlungsfreigabe wurde geprüft
Review-Inbox:
Aktivitäts-Einreichung erscheint in der Review-Inbox
fachliche Fehler/Erfolge werden direkt an der Karte angezeigt
Inbox bleibt nach Aktionen auf dem aktuellen Fall
erst Später verschiebt weiter
Zahlung kann freigegeben werden
Veröffentlichung kann fachlich blockiert werden, wenn kein Platz im Aktivitätstarif frei ist
Zahlungsflow:
Zahlungslink führt auf /zahlung-starten/
Stripe Checkout oder bestehende Aktivitätspräsenz wird korrekt genutzt
Stripe-Webhook setzt Zahlung auf paid
Veröffentlichung erfolgt erst nach finaler Inbox-Aktion
Verbrauchs-/Zählungslogik:
Verbrauch/Zählung wird erst bei Veröffentlichung gebucht
volle Aktivitätspräsenz blockiert weitere Veröffentlichung fachlich
erneute Veröffentlichung nach Anbieteränderung löst keinen Doppelverbrauch aus
Anbieterbereich:
Anbieterbereich zeigt Status der Aktivitäts-Einreichung
Anbieter kann Aktivität ändern
Änderung geht zurück in Prüfung
erneute Veröffentlichung ohne Doppelverbrauch wurde geprüft
mehrere Tarife und Monatssumme werden als kompakte Tarifkarte dargestellt
Success-States:
Standardfall flow=submitted unterscheidet Einreichung von Zahlung
vorhandene aktive Aktivitätspräsenz kann ohne neue Zahlung genutzt werden
Zahlungserfolg führt nicht zu automatischer Sofortveröffentlichung
Bewusst getroffene Produkt- und UX-Entscheidungen
Kein Foto-Upload im V1-Formular.
Grund: Storage, Dateiprüfung, Bildrechte, Moderation, Datenschutz und Missbrauchsschutz wären ein eigener größerer Workpack.
Fotos bleiben redaktionell bzw. werden später separat angefragt.
Inhaltliche Verantwortlichkeit wird über die Bestätigung abgedeckt:
berechtigt zur Einreichung
Angaben korrekt
Standort darf öffentlich genannt werden
H1-Ausrichtung:
linksbündiger Inhalt in zentrierter Card bleibt Standard
keine mittige H1 für Service-/Formular-Funnels
Tarifauswahl bleibt auf der Formularseite sichtbar und änderbar:
nötig für Direktaufrufe
nötig für Planwechsel vor Absenden
robustere Funktionalität als versteckter Tarifwert
Push für Aktivitäten bleibt best-effort:
kein Blocker für den Aktivitäten-Funnel
bestehender Push-Mechanismus kann später weiterverwendet werden
<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_FUNNEL_FINAL_SMOKE_2026_05_19 | Zweck: dokumentiert den finalen Smoke-Test nach Account-Kontext-, Checkout-Endpoint- und Inbox-Terminalstatus-Fixes; Umfang: ersetzt den bisherigen offenen Smoke-Testbedarf durch den abgeschlossenen Prüfstand === -->

## Finaler Smoke-Test nach letzter Patch-Anwendung

- Datum: 2026-05-19
- Umgebung: Staging
- Funktion: Aktivitäten-Funnel / Aktivitätspräsenz
- Ergebnis: bestanden
- Status: V1 abgeschlossen und eingefroren

### Geprüfte finale Fixes

Bestanden:

- `api/stripe/create-checkout-session.php` ist wieder lauffähig.
- Empty-Body-Proof liefert kein leeres `500` mehr.
- Empty-Body-Proof liefert kontrolliert JSON:
  - HTTP `422`
  - `submission_id is required.`
- Zahlungsfreigabe erzeugt wieder einen Zahlungslink.
- Zahlungslink führt zu Stripe Checkout.
- Stripe Checkout zeigt:
  - `Aktivitätspräsenz Basis`
  - `9,99 € / Monat`
- Zahlung führt auf die Aktivitäts-Erfolgsseite.
- Erfolgsseite zeigt `Zahlung erhalten`.
- CTA `Anbieterbereich öffnen` enthält den korrekten E-Mail-Kontext:
  - `/fuer-veranstalter/login/?email=...`
- Anbieterbereich öffnet den passenden Organizer-Account.
- Dashboard zeigt die neue Aktivitäts-Einreichung im richtigen Account.
- Aktivitäts-Einreichung wird als `Aktivität bezahlt – in Prüfung` angezeigt.
- Terminalstatus-Fix in der Inbox funktioniert:
  - abgelehnte Fälle verschwinden aus der aktuellen Inbox-Ansicht
  - nach Reload bleiben abgelehnte Fälle aus der Inbox heraus
  - kein erneuter Reject auf bereits abgelehnte Fälle
  - kein Frontend-Abbruch durch undefinierte Statusfunktion

### Belegte Root-Cause-Korrekturen

Korrigiert:

- Alte Portal-Sessions überlagern den Magic-Link-Kontext nicht mehr bei fehlgeschlagener Magic-Link-Einlösung.
- Aktivitäts-Einreichungen übernehmen keine abweichende alte Portal-Session mehr, wenn die Formular-E-Mail nicht zur Session-E-Mail passt.
- Aktivitäts-Zahlungsrücksprünge führen den E-Mail-Kontext bis zur Erfolgsseite mit.
- Der Anbieterbereich-CTA führt mit E-Mail-Prefill zum richtigen Login-Kontext.
- Der Checkout-Endpoint antwortet wieder stabil mit JSON statt leerem `500`.
- Terminale Review-Status werden im Inbox-Frontend nicht mehr als weiter bearbeitbare Karte stehen gelassen.

### Geprüfte E2E-Kette

Der finale Staging-Test belegt diese Kette:

1. Aktivität einreichen.
2. Eingangs-Mail wird versendet.
3. Aktivität erscheint in der Review-Inbox.
4. Zahlung wird freigegeben.
5. Zahlungslink-Mail wird versendet.
6. Zahlungslink öffnet Stripe Checkout.
7. Zahlung wird abgeschlossen.
8. Erfolgsseite zeigt Zahlungserhalt.
9. Anbieterbereich-CTA führt in den richtigen Account-Kontext.
10. Dashboard zeigt die Aktivität beim korrekten Organizer.
11. Terminalstatus-Aktionen in der Inbox entfernen Fälle aus der aktiven Review-Ansicht.

### Nicht erneut künstlich getestet

Nicht erneut künstlich erzeugt wurde ein zusätzlicher bezahlter Testfall nur für eine weitere Veröffentlichungsaktion.

Begründung:

- Der Veröffentlichungspfad wurde zuvor backendseitig per SQL belegt.
- Die betroffene Frontend-Terminalstatus-Logik wurde anschließend über den Ablehnen-Test erfolgreich verifiziert.
- Ein erneuter Veröffentlichungs-Test hätte einen weiteren vollständigen Zahlungsfall erzeugt und keinen zusätzlichen Erkenntnisgewinn für den V1-Freeze geliefert.

### Bekannte Nicht-Blocker

Diese Punkte sind keine Blocker für den V1-Freeze:

- Staging enthält mehrere Test-Abos im selben Organizer-Account.
- Stripe-Sandbox kann testweise `webhook-test@example.com` anzeigen.
- Alte Testdaten mit gleichen Organisationsnamen können verwirren, sind aber kein Funktionsfehler.
- Produktlogik für spätere Abo-Bereinigung oder Upgrade-/Reuse-Komfort bleibt ein separater späterer Workpack.

### Freeze-Entscheidung

Der Aktivitäten-Funnel gilt damit als V1-funktional abgeschlossen.

Eingefroren sind:

- Entscheidungsseite `/angebote/sichtbar-werden/`
- Formularseite `/angebote/sichtbar-werden/einreichen/`
- Erfolgsseite `/angebote/sichtbar-werden/erfolg/`
- Review-before-payment-Flow
- Aktivitäts-Zahlungslink-Flow
- Anbieterbereich-Kontext nach Zahlung
- Dashboard-Anzeige der Aktivitäts-Einreichungen
- Inbox-Terminalstatus-Verhalten für abgelehnte/veröffentlichte Fälle

Weitere Änderungen an diesem Funnel nur noch bei konkretem Symptom oder neuer Produktanforderung.

<!-- === END BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_FUNNEL_FINAL_SMOKE_2026_05_19 === -->
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_FUNNEL_FINAL_SMOKE_2026_05_19 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_LIVE_READINESS_2026_05_26 | Zweck: dokumentiert Live-Go-live-Prüfung, Live/Staging-Inbox-Tab-Trennung und offenen echten Zahlungsbeweis; Umfang: Aktivitäten-Funnel, Sheet-Inbox-Trennung, Live-Basischecks === -->

## Live-Readiness-Prüfung: Aktivitäten-Funnel und Inbox-Tab-Trennung

- Datum: 2026-05-26
- Umgebung: Staging und Live
- Funktion: Aktivitäten-Funnel / Aktivitätspräsenz / Review-Inbox / Google-Sheet-Inbox-Trennung
- Ergebnis: Live bis Stripe Checkout bestanden
- Status: Go-live-fähig ohne echten Live-Zahlungsbeweis

### Belegte Live-Basischecks

Bestanden:

- Live-DB-Schema vollständig.
- Missing-only-Schema-Check liefert keine fehlenden Tabellen oder Spalten.
- Nachmigration auf Live abgeschlossen:
  - `submissions.organizer_edited_at`
  - `submissions.organizer_edit_count`
- GitHub Actions Main-Deploy erfolgreich.
- Live-Status-Endpunkt liefert:
  - `status: ok`
  - `app_env: live`
  - `checks.config: true`
  - `checks.database: true`
- Live-Checkout-Endpoint Empty-Body-Proof liefert kontrolliert:
  - HTTP `422`
  - `submission_id is required.`
- Kein leerer `500` im Live-Checkout-Endpoint.

### Belegte Google-Sheet-Inbox-Trennung

Zielzustand:

| Umgebung | Google-Sheet-Inbox-Tab | Archiv-Tab |
|---|---|---|
| `main` / Live | `Inbox` | `Inbox_Archive` |
| `staging` | `Inbox_Staging` | `Inbox_Archive_Staging` |

Bestanden:

- Staging-Deploy-Log zeigt:
  - `Google Sheet target resolved for staging.`
  - `Inbox tab: Inbox_Staging`
- Main-/Live-Deploy-Log zeigt:
  - `Google Sheet target resolved for main.`
  - `Inbox tab: Inbox`
- Deploy-Workflow verwendet den resolved Tab tatsächlich für den Inbox-Export:
  - `rng = f"{inbox_tab_name}!A:ZZ"`
- `Inbox_Staging` war leer und die Staging-Inbox zeigte keine Live-/KI-Kandidaten mehr.
- Live-Inbox zeigte weiterhin die Live-Einreichung aus dem produktiven Kontext.
- Damit ist belegt: Staging liest nicht mehr den produktiven `Inbox`-Tab.

### Belegte Live-Funnel-Kette bis Stripe Checkout

Bestanden:

1. Live-Formular `/angebote/sichtbar-werden/einreichen/?plan=activity_basic` geöffnet.
2. Live-Aktivität eingereicht.
3. Live-Erfolgsseite `Aktivität eingereicht` angezeigt.
4. Eingangs-Mail `Deine Aktivität wurde zur Prüfung eingereicht` empfangen.
5. Live-Inbox zeigt die Einreichung als `Aktivitätspräsenz`.
6. Zahlungsfreigabe in der Live-Inbox funktioniert.
7. Zahlungslink-Mail `Zahlung für deine Aktivitätspräsenz starten` empfangen.
8. Live-Zahlungslink öffnet Stripe Checkout.
9. Stripe Checkout zeigt:
   - `Aktivitätspräsenz Basis`
   - `9,99 € / Monat`
   - monatliches Abo
   - kein Sandbox-Hinweis
10. Live-Inbox nutzt `Inbox`, Staging-Inbox nutzt `Inbox_Staging`.

### Nicht live vollständig bewiesen

Noch nicht mit echter Live-Zahlung geprüft:

- echte Live-Zahlung
- Live-Stripe-Webhook nach erfolgreicher Zahlung
- Live-Erfolgsseite nach echter Zahlung
- Live-Anbieterbereich nach echter Zahlung
- Dashboard-Status nach echter Zahlung
- Live-Veröffentlichung nach echter Zahlung

Bewertung:

- Bis Stripe Checkout ist der Live-Funnel belegt.
- Vollständiger Live-E2E-Beweis benötigt eine echte Zahlung.
- Der echte Zahlungstest ist ein separater, bewusster Smoke-Test und kein Blocker für den aktuellen Deploy-Stand.

### Bekannte Nicht-Blocker

- Staging- und Live-Inbox sind bewusst getrennt.
- Live-Einreichungen sollen nicht in der Staging-Inbox erscheinen.
- Staging-Inbox darf leer sein, wenn `Inbox_Staging` leer ist.
- Mails sind Plain-Text-Mails; HTML-Mail-Design ist kein V1-Blocker.
- Mail-Wording kann später poliert werden, ist aktuell aber funktional korrekt.
- Stripe-Sandbox-/Testdaten aus früheren Staging-Tests sind kein Live-Blocker.
- GitHub Actions hatte temporär Checkout-/Auth-Probleme; der spätere Main-Deploy war wieder erfolgreich.

### Entscheidung

Der Aktivitäten-Funnel und die Live/Staging-Inbox-Tab-Trennung gelten für den aktuellen Stand als ausreichend vorbereitet.

Weitere Änderungen nur noch bei:

- konkretem Live-Symptom,
- bewusstem echten Live-Zahlungstest,
- Mail-Template-Polish,
- separater Testdaten-/Abo-Bereinigung,
- neuer Produktanforderung.

<!-- === END BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_LIVE_READINESS_2026_05_26 === -->

<!-- === END BLOCK: TEST_STATUS_ACTIVITY_PRESENCE_FUNNEL_E2E_2026_05_18 === -->
<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_VISUAL_PREMIUM_ASSET_INTEGRATION_2026_06_11 | Zweck: dokumentiert den geprüften Stand der Activity-Premiumbild-Assetintegration; Umfang: Activity Visual Pool, finale WebP-Assets, B2-Tonwerke-Fallback, offene Frontend-Verdrahtung === -->
## Activity Visual Premium Asset Integration – Staging-Stand 2026-06-11

Status: Asset-Integration abgeschlossen, Frontend-/Offer-Verdrahtung noch offen.

### Gesicherte Commits

- `ed2fcc6` – Ergaenze B2 Tonwerke Fallback Visual
- `1603c84` – Ordne falsch abgelegte Activity Visuals den Event Visuals zu
- `91125bd` – Integriere akzeptierte Activity Visuals
- `1b7f2ee` – Dokumentiere Codespaces Quota Fallback

### Ergebnis

Der Activity-Visual-Pool enthält jetzt 25 Pools mit 25 Bildern:

- 24 akzeptierte KI-generierte Activity-Premiumbilder mit Status `ready`
- 1 rechtlich nutzbarer echter Foto-Fallback mit Status `fallback` für `b2-tonwerke-route`
- keine fehlenden Ready-/Fallback-Dateien
- keine Ready-/Fallback-Non-WebP-Dateien
- keine übergroßen Ready-/Fallback-Dateien
- keine fehlenden Alt-Texte
- keine 16:9-Verletzungen
- Visual-Audit: `Errors: none`

### Integrierte ready-Activity-Bilder

- `100-schloesser-route-ab-bocholt`
- `aasee-erleben`
- `auesee-wesel-erleben`
- `b1-mosse-route`
- `bocholter-aa-radweg-erleben`
- `burloer-venn-entdecken`
- `die-berge-hombornquelle-entdecken`
- `flamingoroute-ab-bocholt`
- `hilgelo-erleben`
- `hohe-mark-radroute-ab-bocholt`
- `hohenhorster-berge-entdecken`
- `korenburgerveen-entdecken`
- `mtb-route-winterswijk`
- `naturkulturspaziergang-bocholt-rhede`
- `niederrhein-route-ab-bocholt`
- `noaberpad-ab-bocholt`
- `proebstingsee-borken-erleben`
- `schmuggelroute`
- `schwarzes-wasser-wesel-entdecken`
- `stadtwald-bocholt-erleben`
- `witte-venn-ahaus-alstaette-entdecken`
- `wooldse-veen-entdecken`
- `zeitreise-dingdener-heide`
- `zwillbrocker-venn-flamingos-entdecken`

### Fallback-Bild

`b2-tonwerke-route` nutzt vorerst einen rechtlich nutzbaren Wikimedia-Commons-Foto-Fallback von Dietmar Rabich unter CC BY-SA 4.0.

Wichtige Folgepflicht:
Bei öffentlicher Nutzung muss der Bildnachweis bzw. die Lizenzangabe sauber berücksichtigt werden. Der aktuelle Stand ist technisch als Fallback integriert, aber kein Premium-Endbild. Ein besseres Betreiber-/Stadt-/Vereinsfoto bleibt später bevorzugt.

### Bewusste Nicht-Übernahmen

Der zusätzliche Schlossrouten-Versuch aus der Inbox wurde nicht importiert, weil der spätere Schloss-/Gräften-Ausschnitt für `100-schloesser-route-ab-bocholt` stärker und eindeutiger war.

Temporäre Inbox-Dateien und Contactsheets wurden nicht committed.

### Offener nächster Workstream

Die Assets sind im `data/activity_visual_pool.json` vorhanden. Die öffentlichen Activities nutzen laut Audit weiterhin `explicit images` und noch keine `visual_keys`.

Nächster sinnvoller Schritt:
Activity-Frontend-/Datenmapping so umstellen, dass die Activities ihre neuen Pool-Bilder tatsächlich nutzen. Dabei B2-Tonwerke als `fallback` behandeln und nicht als vollwertiges Premium-Ready-Bild verkaufen.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_VISUAL_PREMIUM_ASSET_INTEGRATION_2026_06_11 === -->

<!-- === END FILE: TEST_STATUS.md === -->

<!-- === BEGIN BLOCK: PUBLIC_UX_DASHBOARD_VALUE_CENTER_STAGING_PROOF_2026_05_28 | Zweck: dokumentiert den geprüften Staging-Stand für Public-UX-Copy und Anbieterbereich-Wertzentrum; Umfang: reiner Test-/Statusnachweis ohne technische Änderung === -->
## Public UX & Anbieterbereich-Wertzentrum – Staging-Proof 2026-05-28

Status: bestanden.

Geprüfter Scope:
- Public-Copy für `/events-veroeffentlichen/`
- Public-Copy für `/fuer-veranstalter/`
- Anbieterbereich-Copy für `/fuer-veranstalter/dashboard/`

Geprüfte Commits auf `staging`:
- `b0b23b0` – Hero-Lead der Veranstalterseite geglättet
- `036bac0` – Anbieterbereich als Wertzentrum gestärkt
- `5c0f0a3` – Anbieterbereich-Copy im Dashboard-JS aktualisiert

Staging-Prüfung:
- Dashboard-HTML lädt aktualisierten Script-Cache-Buster `organizer-portal.js?v=5c0f0a3a6489`.
- Browserprüfung ohne Cache-Buster erfolgreich.
- Sichtbar bestätigt:
  - `Kontakt & Organisation`
  - `Tarife & Veröffentlichungen`
  - `Aktive Tarife`
  - `Einreichungen & Status`
  - Hero-Lead mit `Einreichungen, Veröffentlichungsstatus und Mitgliedschaft`

Bewertung:
- Public-UX-Copy-Schritt ist freigabefähig.
- Anbieterbereich-Wertzentrum v1 ist freigabefähig.
- Keine CSS-, Backend-, Tracking-, Stripe- oder Datenmodelländerung erforderlich.
<!-- === END BLOCK: PUBLIC_UX_DASHBOARD_VALUE_CENTER_STAGING_PROOF_2026_05_28 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_INTERNAL_LOCATION_FEEDBACK_REPORT_2026_05_28 | Zweck: dokumentiert den geprüften Staging-Stand des internen Location-Feedbackberichts; Umfang: SEO-/Mehrwert-Dashboard, Akquise-Snapshot-Kompaktisierung, Reporting-Ziel-Bericht === -->
## Interner Location-Feedbackbericht – Staging-Proof 2026-05-28

Status: bestanden / vorbereitet.

Geprüfter Scope:
- internes SEO-/Mehrwert-Dashboard `/intern/seo-dashboard/`
- kompakter oberer Statusbereich
- einklappbarer `Akquise-Gesamtstatus`
- neuer Block `Location-Feedbackbericht`
- Reporting-Ziel-Auswahl
- Hauptzahlen und eingeklappte Technikdetails

Geprüfter Stand:
- Dashboard lädt auf Staging.
- Status-Chips bleiben oben direkt sichtbar:
  - `Gelb – Akquise prüfen`
  - `Technik ok`
  - `Eigenes Tracking ausgeschlossen`
- Bisherige große Bereiche `Akquise-Snapshot` und `Für Screenshot / Akquise` sind nicht mehr dauerhaft oben sichtbar, sondern unter `Akquise-Gesamtstatus` einklappbar erreichbar.
- Neuer Block `Location-Feedbackbericht` steht oberhalb der Hauptzahlen.
- Reporting-Ziel-Auswahl lädt konfigurierte Ziele; geprüft wurde `Biotopwildpark Anholter Schweiz`.
- Einzelbericht zeigt für `Biotopwildpark Anholter Schweiz`:
  - Interaktionen gesamt: 2
  - Detail-Aufrufe: 1
  - Website-Klicks: 1
  - Route/Maps: 0
  - Zeitraum
  - zugeordneten Inhalt `Anholter Schweiz erleben`
  - Status `Nutzsignal gemessen`
- Hauptzahlen bleiben sichtbar und unverändert erreichbar.
- `Technik, Quellen und Detailwerte` bleibt eingeklappt.

Bewertung:
- Der interne Feedbackbericht ist als Akquise- und Gesprächsvorbereitung technisch vorbereitet.
- Der Bericht ist screenshotfähig und verständlicher als die reine KPI-/Technikansicht.
- Der Bericht liefert jetzt einen ersten kleinen, aber echten Nutzsignal-Proof; für belastbare Akquise-Aussagen bleibt ein längerer Zeitraum mit stabileren Nutzwert-Klicks nötig.
- Es wurden keine neuen Tracking-Endpunkte, keine neue Datenbanktabelle und kein Anbieterbereich-Umbau eingeführt.
- Self-Service-Auswertung im Anbieterbereich bleibt ein späterer Schritt nach belastbarer Datenbasis und sauberer Rechte-/Ziel-Zuordnung.
<!-- === END BLOCK: TEST_STATUS_INTERNAL_LOCATION_FEEDBACK_REPORT_2026_05_28 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_IMPACT_CARD_HIDE_2026_05_28 | Zweck: dokumentiert den Staging-Proof zur Ausblendung der Wirkungskarte bei Aktivitätstarifen; Umfang: Anbieter-Dashboard, Aktivitätspräsenzen, Wirkungskarte === -->
## Anbieter-Dashboard – Wirkungskarte bei Aktivitätstarifen ausgeblendet – Staging-Proof 2026-05-28

Status: bestanden.

Geprüfter Scope:
- Anbieter-Dashboard `/fuer-veranstalter/dashboard/`
- Account mit `Dauerhaft` plus mehreren `Aktivitätspräsenz Basis`-Tarifen
- Karte `Meine Wirkung auf Bocholt erleben`

Geprüfter Stand:
- Dashboard lädt auf Staging.
- `Kontakt & Organisation` bleibt sichtbar.
- `Tarife & Veröffentlichungen` bleibt sichtbar.
- `Einreichungen & Status` bleibt sichtbar.
- Die Karte `Meine Wirkung auf Bocholt erleben` ist bei diesem Activity-/Location-Testaccount nicht mehr sichtbar.

Bewertung:
- Der Sicherheitsfix verhindert eine irreführende Null-Auswertung für Aktivitätspräsenzen.
- Die Wirkungskarte bleibt erst dann für Aktivitätsanbieter sinnvoll, wenn Anbieteraccount und Activity-/Location-Reporting-Ziele sauber verknüpft sind.
- Restbefund: Das Anbieter-Dashboard ist weiterhin strukturell zu unaufgeräumt und sollte als eigener Dashboard-V2-Workpack überarbeitet werden.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_IMPACT_CARD_HIDE_2026_05_28 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ORGANIZER_DASHBOARD_V2_FREEZE_2026_05_28 | Zweck: dokumentiert den Freeze-Stand des Anbieter-Dashboards nach Struktur-, Owner- und Handlungsorientierungs-Polish; Umfang: User-/Veranstalter-Dashboard auf Staging === -->
## Anbieter-Dashboard V2 – Übersichtlichkeit und Handlungsorientierung – Staging-Freeze 2026-05-28

Status: bestanden / vorerst gefreezt.

Geprüfter Scope:
- Anbieter-Dashboard `/fuer-veranstalter/dashboard/`
- Activity-/Location-Testaccount mit Veranstaltungen und Aktivitätspräsenzen
- Mobile-Ansicht
- Desktop-/breite Ansicht
- Einreichungen, Tarife, Organisation und Rücknavigation

Geprüfter Stand:
- Dashboard lädt auf Staging.
- Die frühere Trennung aus `Aktueller Stand` und `Einreichungen & Status` wurde zu einer gemeinsamen Karte `Einreichungen` konsolidiert.
- Statuswerte sind direkt in der Einreichungskarte integriert:
  - sichtbar
  - in Prüfung
  - Zahlung offen
  - abgelehnt
- Ein Handlungsbedarf-/Statushinweis ist sichtbar.
- Die Einreichungsliste nutzt Status-Badges, unter anderem `Veröffentlicht` und `Abgelehnt`.
- `Tarife & Veröffentlichungen` steht vor `Kontakt & Organisation`.
- Die Activity-/Location-Wirkungskarte bleibt ausgeblendet, solange Activity-/Location-Reporting-Ziele nicht sauber mit Anbieteraccounts verknüpft sind.
- Der CSS-Stand wurde als Owner-Cleanup konsolidiert:
  - kein zusätzlicher später Override-Block für Dashboard-Hierarchie
  - Tarifdarstellung bleibt im Tarif-Owner
  - Einreichungsdarstellung bleibt im Einreichungs-Owner
  - Summary-/Statusdarstellung liegt beim Einreichungsbereich

Bewertung:
- Der aktuelle Stand ist aus Nutzersicht deutlich klarer und handlungsorientierter als der Ausgangsstand.
- Mobile bleibt nutzbar und übersichtlicher, ohne essenzielle Informationen zu entfernen.
- Desktop ist ruhiger und logischer strukturiert.
- Weitere Optimierungen am Anbieter-Dashboard werden vorerst zurückgestellt und nur bei konkretem neuem Befund wieder geöffnet.
<!-- === END BLOCK: TEST_STATUS_ORGANIZER_DASHBOARD_V2_FREEZE_2026_05_28 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_TODAY_HOME_EVENT_VISUALS_AND_TODAY_ONLY_EVENTS_2026_06_01 | Zweck: belegter Staging-Teststand fuer Event Visual Coverage und Today-Only-Eventlogik; Umfang: technische und visuelle Smoke-Ergebnisse === -->
## Staging-Teststand – Today Home Event Visuals und Today-Only-Eventlogik – 2026-06-01

Geprüfter Branch/Stand:
- Branch: `staging`
- Event-Visual-Commit: `fffaf3d` – `Fuege weitere Event Visual Assets hinzu`
- Today-Only-Eventlogik: `9115d43` – `Begrenze Today Events auf heutige Termine`
- `origin/staging` synchron
- Arbeitsbaum zuletzt sauber

### Event Visual Assets

Neue/ersetzte WebP-Assets:
- `assets/event-visuals/music-stage-01.webp`
- `assets/event-visuals/default-city-02.webp`
- `assets/event-visuals/culture-exhibition-01.webp`
- `assets/event-visuals/market-food-01.webp`
- `assets/event-visuals/sport-active-01.webp`
- `assets/event-visuals/creative-workshop-01.webp`
- `assets/event-visuals/kids-family-01.webp`

Geprüft:
- Dateien sind echte WebP-Dateien.
- Größen nach Komprimierung ca. 67–254 KB.
- `data/event_visual_pool.json` referenziert alle 7 neuen Assets als `status: ready`.
- `rights_status`: `ai_generated_symbolic_reviewed`.
- `review_status`: `accepted_phase1`.
- `default-city-01` wurde durch `default-city-02` als aktives Default-Bild ersetzt.

Audit-Ergebnisse:
- `python scripts/audit-event-visual-pool.py`: OK.
- `python scripts/build-event-visual-asset-backlog.py`: OK, Backlog neu gebaut.
- `python scripts/audit-event-visual-asset-brief.py`: OK.
- `python scripts/audit-event-visual-generation-batches.py`: OK.
- `python scripts/audit-event-visual-ai-style-guide.py`: OK.
- `node --check js/recommendations.js`: OK.
- `node --check js/today-home.js`: OK.
- `git diff --check`: OK.

Staging-Proof:
- `/data/event_visual_pool.json` liefert HTTP 200.
- Alle 7 neuen Asset-URLs liefern HTTP 200.
- Aktuelle Staging-Events: 62.
- Coverage nach `visual_key`: 62/62 Events haben ein ready Event-Visual.

### Today-Only-Eventlogik

Problem:
- Auf Today Home erschien ein Event vom 14.06. als Heute-Tipp.
- Ursache: frühere Event-Spur ließ nahe Zukunftsevents bis 14 Tage zu.

Fix:
- `TODAY_RECOMMENDATION_EVENT_MIX_V4`
- Events sind Today-Kandidaten nur noch, wenn:
  - Startdatum heute ist, oder
  - das Event als Mehrtagesevent heute läuft.
- Zukünftige Events werden nicht mehr auf Today Home beigemischt.

Staging-Proof:
- `https://staging.bocholt-erleben.de/js/today-home.js` liefert:
  - `TODAY_RECOMMENDATION_EVENT_MIX_V4`
  - `function isTodayEventCandidate`
  - keinen alten `TODAY_RECOMMENDATION_EVENT_MIX_V3`
  - keine alte `isNearEventCandidate`
  - keine alte 14-Tage-Zukunftslogik
- Aktuelle Eventdaten ergaben 0 heute-fähige Events.
- Today Home zeigte danach korrekt 3 Aktivitäten.
- Das Event vom 14.06. war nicht mehr sichtbar.

Bewertung:
- Technisch bestanden.
- Visueller Smoke bestanden.
- Kein Freeze: Today Home bleibt offen für Premium Completion.
- Nächster Hauptpunkt nach Today Home Premium Completion: Eventbilder konsistent in den normalen Events Feed übernehmen.
<!-- === END BLOCK: TEST_STATUS_TODAY_HOME_EVENT_VISUALS_AND_TODAY_ONLY_EVENTS_2026_06_01 === -->

<!-- === BEGIN BLOCK: EVENT_VISUAL_KEYS_V31_STAGING_PROOF_2026_06_02 | Zweck: dokumentiert den getesteten Staging-Proof fuer Event-Visual-Key-System V3.1 und Restzuordnung === -->
## Event Visual Keys V3.1 – Staging-Proof 2026-06-02

Status: bestanden.

Geprüfter Stand:
- Branch: `staging`
- letzter bestätigter Commit: `8b41f06`
- Commit: `Schaerfe Event Visual Key Restzuordnung nach`
- bestätigter Staging-Build: `8b41f067a3ca`

Relevante Commit-Kette:
- `1b7b73f` – Event Visuals in Feed Cards integriert
- `ad81c00` – Event Visual Keys V3.1 konsolidiert
- `aa68d8c` – Event Visual Key Inferenz nachgeschärft
- `8b41f06` – sichere Restzuordnung nachgeschärft

Geprüfte Audits vor V3.1-Commit:
- `python scripts/audit-event-visual-pool.py` → OK
- `python scripts/audit-event-visual-asset-brief.py` → OK
- `python scripts/audit-event-visual-ai-style-guide.py` → OK
- `python scripts/audit-event-visual-generation-batches.py` → OK mit 24/24 Requests
- Python-Compile der relevanten Build-/Audit-Skripte → OK
- `git diff --check` → OK

Geprüfte Vertragsstände:
- `data/event_visual_pool.json`: 34 / 34 Keys, 156 Zielslots
- `data/event_visual_asset_brief.json`: 34 / 34 Keys, 156 Slot-Briefs
- `data/event_visual_ai_style_guide.json`: 34 Visual-Key-Regeln
- `data/event_visual_phase1_plan.tsv`: 24 fehlende Basis-Visuals
- `data/event_visual_generation_batches_phase1.json`: 24 Requests in 4 Batches
- `scripts/audit-event-visual-generation-batches.py`: OK-Text dynamisch auf tatsächliche Request-Anzahl korrigiert

Staging-Deploy-Prüfung:
- Deploy nach `ad81c00` erfolgreich
- Deploy nach `aa68d8c` erfolgreich
- Deploy nach `8b41f06` erfolgreich
- `https://staging.bocholt-erleben.de/meta/build.txt` lieferte `8b41f067a3ca`

Geprüfte deployte Eventdaten nach finalem Fix:
- `Bewegte Geschichte - Kostümierte Stadtführungen 2/2` → `city_tour_history`
- `Aasee-Festival` → `open_air_festival`
- `Internationale Herfstboekenmarkt` → `book_market`

Weitere geprüfte Inferenzfälle:
- `Issel unplugged - Stadtturm Open Air...` → `live_music_stage`
- `K-Pop Power! Sing & Dance Workshop` → `dance_music_workshop`
- `Textile Revolution – Stoffe für die Zukunft` → `textile_exhibition_design`
- `20. Sparkassen MünsterlandGiro - Profistart` → `cycling_event`
- `Führung Lebenselixier Wasser... Pröbstingsee` → `nature_learning_wildlife`
- `AaltenDagen` → `city_festival_street`
- `Innenstadtsommer` → `city_festival_street`
- `Bokeltsen Treff 2026` → `city_festival_street`

Bewertung:
- V3.1 ist technisch konsistent.
- Die deployten Staging-Daten verwenden keine alten 12er-Visual-Keys mehr.
- Die geprüften Restfehler sind korrigiert.
- Der Stand ist für die nächste Bildproduktionsrunde geeignet.

Offen / nicht Teil dieses Proofs:
- Noch keine Produktion der 24 fehlenden V3.1-Basis-Visuals.
- Noch kein finales Mobile-Card-Layout.
- Noch keine Duplicate-Vermeidungslogik im Feed.
- Noch kein Production-Deploy dieses Visual-Systems.
- Scheduled-Workflow-Fehler auf Staging wurden nicht als Teil dieses Proofs gelöst, da sie nicht den erfolgreichen Staging-Deploy betreffen.

Nächster Schritt:
- 24 fehlende Basis-Visuals aus `data/event_visual_generation_batches_phase1.json` erzeugen und einzeln im Card-Kontext prüfen.
<!-- === END BLOCK: EVENT_VISUAL_KEYS_V31_STAGING_PROOF_2026_06_02 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_PROMPTING_RUN_2026_06_02 | Zweck: dokumentiert die Prompting- und Bewertungsverbesserungen aus dem Event-Visual-Produktionschat; Umfang: Event-Visual-Prompting, Bilderchat, Review-Gates === -->
## Event Visual Prompting Run – Bildchat-Vorbereitung – 2026-06-02

Status: Prompting-/Review-Stand dokumentiert; Asset-Integration noch nicht durchgeführt.

Geprüfter Arbeitsstand:

- Branch: `staging`
- HEAD vor Dokumentationspatch: `491c435`
- Relevanter Workflow: Event Visual Keys V3.1 / Phase-1-Bildproduktion
- Bildgenerierung erfolgte separat im Bilderchat; dieser Projektchat bewertete Ergebnisse und lieferte Folgeprompts.

Ergebnis:

- Mehrere Phase-1-Visuals wurden fachlich als `ready` bzw. `ready mit Prüfvorbehalt` bewertet.
- Mehrere Prompts wurden aufgrund sichtbarer KI-/Realismusprobleme gezielt verschärft.
- Neue Prompting-/Bewertungsregeln wurden im `VISUAL_WORKFLOW.md` dokumentiert.

Wichtigste gelernte Quality-Gates:

- Einzelbildqualität reicht nicht; Systemwirkung und Abgrenzung zu benachbarten Visual-Keys werden mitgeprüft.
- Weniger arrangierte Props ist künftig Standardregel.
- Technische Objektlogik ist hartes Gate, z. B. Beamer-/Projektionsrichtung.
- Sichtbare KI-Physikfehler wie schwebende Lupen sind harte Retry-Gründe.
- Wiederholte Fehler werden durch Strategiewechsel gelöst, nicht durch Endlos-Mikro-Prompting.
- Finale Asset-Abnahme erfolgt erst nach `1200×675`-Exportprüfung auf Text, Logos, Gesichter, Kinder, KI-Artefakte und Crop-Tauglichkeit.

Akzeptierte Visuals aus dem Chatlauf:

- `textile-machines-industry-01.webp`
- `open-air-festival-01.webp`
- `kirmes-funfair-01.webp`
- `parade-festzug-01.webp`
- `shooting-festival-tradition-01.webp`
- `country-fair-rural-01.webp`
- `vehicle-classic-01.webp`
- `textile-exhibition-design-01.webp`
- `art-exhibition-gallery-01.webp`
- `market-stalls-01.webp`
- `book-market-01.webp`
- `business-messe-info-01.webp`
- `classical-music-01.webp`
- `theater-stage-01.webp`
- `comedy-cabaret-01.webp`
- `film-screening-01.webp`
- `literature-reading-talk-01.webp`
- `kids-stage-story-01.webp`
- `learning-science-workshop-01.webp`
- `dance-music-workshop-01.webp`
- `running-event-01.webp`

Noch offen:

- `cycling-event-01.webp` wurde als nächster Prompt vorbereitet; die Bildbewertung erfolgt im nächsten Chat.
- Nach `cycling-event` mit dem nächsten offenen Visual-Key aus `data/event_visual_generation_batches_phase1.json` fortfahren.

Nicht erledigt:

- Keine Assets ins Repo kopiert.
- Keine WebP-Optimierung durchgeführt.
- `data/event_visual_pool.json` noch nicht mit neuen `ready`-Assets aktualisiert.
- Keine finale `1200×675`-Assetprüfung im Repo durchgeführt.
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_PROMPTING_RUN_2026_06_02 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_PHASE1_ASSET_INTEGRATION_2026_06_03 | Zweck: dokumentiert die Integration der 24 Phase-1-Event-Visuals als finale WebP-Card-Assets; Umfang: assets/event-visuals, event_visual_pool.json, Audits === -->
## Event Visual Phase 1 – Assetintegration – 2026-06-03

Status: lokal bestanden / bereit für Commit.

Geprüfter Stand:
- Branch: `staging`
- HEAD vor Assetintegration: `b8ac2a3`
- Scope: Event Visual Keys V3.1 / Phase-1-Basisvisuals

Durchgeführt:
- 24 generierte PNG-Rohbilder wurden inventarisiert.
- 24 Phase-1-Requests aus `data/event_visual_generation_batches_phase1.json` wurden dagegen geprüft.
- PNGs wurden 1:1 auf die geplanten `planned_src`-Zielnamen gemappt.
- Alle 24 Zielassets wurden mit `cwebp` als `1200x675`-WebP erzeugt.
- Die PNG-Rohdateien wurden nach erfolgreicher WebP-Erzeugung entfernt.
- `data/event_visual_pool.json` wurde für diese 24 Slots von `planned` auf `ready` aktualisiert.
- Pflichtmetadaten wurden ergänzt:
  - `src`
  - `status`
  - `source`
  - `source_type`
  - `rights_status`
  - `alt`
  - `is_symbolic`
  - `is_documentary`
  - `review_status`
  - `card_asset_format`
  - `phase1_request_id`

Erzeugte Phase-1-Assets:
- `textile-machines-industry-01.webp`
- `open-air-festival-01.webp`
- `kirmes-funfair-01.webp`
- `parade-festzug-01.webp`
- `shooting-festival-tradition-01.webp`
- `country-fair-rural-01.webp`
- `vehicle-classic-01.webp`
- `textile-exhibition-design-01.webp`
- `art-exhibition-gallery-01.webp`
- `market-stalls-01.webp`
- `book-market-01.webp`
- `business-messe-info-01.webp`
- `classical-music-01.webp`
- `theater-stage-01.webp`
- `comedy-cabaret-01.webp`
- `film-screening-01.webp`
- `literature-reading-talk-01.webp`
- `kids-stage-story-01.webp`
- `learning-science-workshop-01.webp`
- `dance-music-workshop-01.webp`
- `running-event-01.webp`
- `cycling-event-01.webp`
- `indoor-sport-competition-01.webp`
- `evening-social-party-01.webp`

Audit-Ergebnis:
- `python scripts/audit-event-visual-pool.py` → OK
- `python tools/audit-visual-contract.py` → Errors: none
- `git diff --check` → OK

Pool-Status nach Integration:
- `ready`: 34
- `planned`: 122
- `usable`: 10
- Phase-1-Ready-Assets: 24
- fehlende Phase-1-Assets: 0

Bekannte nicht blockierende Warnungen:
- `event_visual_pool.json owner is not event_visual_pool_v1` ist für den aktuellen V3.1-Owner nicht blockierend.
- Activity-Visual-Warnungen sind nicht Teil dieses Event-Visual-Workstreams.

Bewertung:
- Phase-1-Basisabdeckung ist technisch integriert.
- Jeder der 34 Event-Visual-Keys hat jetzt mindestens ein `ready`-Asset.
- Der nächste fachliche Schritt ist nicht weitere Basisabdeckung, sondern Phase-2-Pool-Diversifizierung bis `target_count`.

Nächster Schritt:
- Commit der 24 WebP-Assets, `data/event_visual_pool.json` und dieser Dokumentation.
- Danach Phase-2-Bedarfsplan aus `target_count - ready_count` erzeugen.

<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_PHASE1_ASSET_INTEGRATION_2026_06_03 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_OPENING_HOURS_V1_PREP_2026_06_05 | Zweck: dokumentiert vorbereiteten Livegang fuer strukturierte Oeffnungszeiten bei Aktivitaetspraesenzen; Umfang: Formular, Payload, Backend, Review-API, DB-Migration 007 === -->
## Activity Opening Hours V1 – Livegang-Vorbereitung – 2026-06-05

Status: vorbereitet, noch nicht deployt.

Scope:
- Aktivitätspräsenz-Formular unter `/angebote/sichtbar-werden/einreichen/`
- `js/activity-presence-funnel.js`
- `api/submissions/init.php`
- `api/submissions/review-list.php`
- neue DB-Migration `api/sql/007_activity_opening_json.sql`

Ziel:
- Anbieter können bei Aktivitätseinreichungen eine einfache Zugänglichkeitslogik angeben.
- Optionen: frei zugänglich, regelmäßige Öffnungszeiten, saisonal/unregelmäßig, nach Vereinbarung/bitte prüfen.
- Bei regelmäßigen Öffnungszeiten werden Wochenzeiten und Feiertagslogik strukturiert im Payload übergeben.
- Das Backend speichert diese Daten in `submissions.activity_opening_json`.
- Die Review-API gibt die Daten als `activity_opening` wieder aus.

Wichtige Deploy-Abhängigkeit:
- Vor einem Staging-Deploy dieses Codes muss die **Staging-Datenbank** die Spalte `activity_opening_json` besitzen.
- Vor einem späteren Live-Deploy muss die **Live-Datenbank** dieselbe Spalte besitzen.
- Grund: `api/submissions/init.php` schreibt nach dem Deploy in diese Spalte und `api/submissions/review-list.php` liest sie aus.
- Ohne vorherige DB-Migration können neue Activity-Einreichungen bzw. die Review-Liste fehlschlagen.

Vorbereitete Migration:
- Datei: `api/sql/007_activity_opening_json.sql`
- SQL: `ALTER TABLE submissions ADD COLUMN activity_opening_json JSON NULL AFTER notes_text;`

Preflight-SQL auf der jeweiligen Ziel-Datenbank:
- Für Staging-Test: auf der **Staging-DB** ausführen.
- Für Livegang: auf der **Live-DB** ausführen.
- SQL: `SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'submissions' AND COLUMN_NAME = 'activity_opening_json';`

Erwartung Preflight:
- Kein Ergebnis: Migration 007 muss auf dieser Datenbank ausgeführt werden.
- Ergebnis mit `activity_opening_json`: Migration auf dieser Datenbank nicht erneut ausführen.

Postflight-SQL nach Migration:
- SQL: `SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'submissions' AND COLUMN_NAME = 'activity_opening_json';`
- Erwartung: `COLUMN_NAME = activity_opening_json`, `DATA_TYPE = json`, `IS_NULLABLE = YES`.

Sichere Reihenfolge für Staging:
1. Staging-DB per Preflight prüfen.
2. Falls Spalte fehlt: `api/sql/007_activity_opening_json.sql` auf der Staging-DB ausführen.
3. Postflight auf Staging-DB prüfen.
4. Danach Code auf Staging deployen.
5. Danach Staging-Smoke-Test durchführen.

Sichere Reihenfolge für Live:
1. Live-DB per Preflight prüfen.
2. Falls Spalte fehlt: `api/sql/007_activity_opening_json.sql` auf der Live-DB ausführen.
3. Postflight auf Live-DB prüfen.
4. Danach erst Merge/Deploy auf Live.
5. Danach Live-Smoke-Test durchführen.

Minimaler Smoke-Test nach Deploy:
1. `/angebote/sichtbar-werden/einreichen/?plan=activity_basic` öffnen.
2. Formular mit `Frei zugänglich / ohne feste Öffnungszeiten` prüfen.
3. Formular mit `Regelmäßige Öffnungszeiten` prüfen.
4. Prüfen: Öffnungszeitenblock erscheint nur bei dieser Auswahl.
5. Prüfen: geschlossene Tage deaktivieren Start-/Endzeit.
6. Prüfen: mindestens ein geöffneter Tag mit gültigem Zeitfenster wird akzeptiert.
7. Testeinreichung absenden.
8. Review-API/Inhouse-Review prüfen: Einreichung erscheint und `activity_opening` ist vorhanden.
9. Kurz prüfen, dass Event-Einreichungen unverändert erreichbar bleiben.

Bewusste Nicht-Ziele dieses Schritts:
- Noch keine Auswertung auf Today Home.
- Noch keine automatische Daily-/Weekly-Prüfung von Öffnungszeiten.
- Noch kein öffentliches `Heute geöffnet bis ...`.
- Noch keine Migration vorhandener Activities aus `data/offers.json`.

Bewertung:
- Der Schritt bereitet den strukturierten Datenkanal für Öffnungszeiten vor.
- Die bestehende Funnel-Grundlogik bleibt erhalten.
- Der fachliche Nutzen für Today Home folgt später über `activity_opening_hours.json`, `opening-status.js` und kontrollierte Recommendation-Anbindung.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_OPENING_HOURS_V1_PREP_2026_06_05 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_OPENING_HOURS_V1_DONE_2026_06_05 | Zweck: dokumentiert Abschluss des Activity-Opening-Hours-V1-Submission-Workpacks; Umfang: Formular, Backend, DB, Review-API, Review-Inbox === -->
## Activity Opening Hours V1 – Submission-/Review-Kanal abgeschlossen – 2026-06-05

Status: auf `staging` umgesetzt und per Testeinreichung verifiziert.

Abgeschlossener Umfang:
- Aktivitätsformular erfasst Zugänglichkeit / Verfügbarkeit.
- Regelmäßige Öffnungszeiten können strukturiert pro Wochentag erfasst werden.
- Geschlossene Tage blenden Von/Bis-Felder aus.
- Kein Wochentag ist standardmäßig geschlossen.
- Payload sendet `activity_opening` an `api/submissions/init.php`.
- Backend speichert die Daten in `submissions.activity_opening_json`.
- Review-API gibt `activity_opening` aus.
- Review-Inbox zeigt Anbieterangaben unter „Weitere Prüfdaten“ an.

Verifizierter Test:
- Neue Testeinreichung `Test Öffnungszeiten Payload 2` wurde erstellt.
- Staging-DB `dbs15596763.submissions.activity_opening_json` war gefüllt.
- Review-Inbox zeigte:
  - Zugänglichkeit laut Anbieter
  - Vom Anbieter angegebene Öffnungszeiten
  - Feiertage laut Anbieter
  - Prüfhinweis Öffnungszeiten

Wichtige Einschränkung:
- Alte Testeinträge vor dem Payload-Fix haben erwartbar `activity_opening_json = NULL`.
- Für Live gilt weiterhin: Vor Live-Deploy muss die Live-DB die Spalte `activity_opening_json` besitzen.

Aktueller relevanter Commit:
- `19f040b` – Sende Öffnungszeiten im Aktivitätsformular-Payload

Nicht Teil dieses Workpacks:
- Öffnungszeiten öffentlicher bestehender Activities auswerten.
- Today Home anhand echter Öffnungsstatus steuern.
- Öffnungsstatus öffentlich anzeigen.
- Daily-/Weekly-Check gegen Anbieterquellen.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_OPENING_HOURS_V1_DONE_2026_06_05 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_OPENING_PUBLIC_STATUS_DONE_2026_06_08 | Zweck: dokumentiert Abschluss der zentralen öffentlichen Öffnungsstatus-Logik; Umfang: Today Home, Recommendations, Top-Tipp-Sperre für ungeprüfte Öffnungszeiten === -->
## Activity Opening Public Status – abgeschlossen am 2026-06-08

### Ziel

Öffnungsstatus und Schließrisiko für bestehende Activities werden zentral bewertet, damit Today Home keine starken Top-Tipps vergibt, wenn Öffnungszeiten ungeprüft oder der Tageskontext unsicher ist.

### Umgesetzt

- Neuer zentraler Owner `js/opening-status.js`.
- Öffnungsstatus-/Schließrisiko-Logik aus `js/recommendations.js` und `js/today-home.js` herausgelöst.
- Zentrale API für:
  - `hasClosureRisk`
  - `isTopTipEligible`
  - Recommendation-Day-Labels
  - Recommendation-Availability-Labels
  - Activity-Meta-Labels
  - Non-Business-Day-Score-Abzug
- `js/recommendations.js` delegiert Labels und Score-Abzug an `OpeningStatus`.
- `js/today-home.js` delegiert Top-Tipp-Eignung und Activity-Meta-Labels an `OpeningStatus`.
- Activities mit `availability = opening_hours_check` können weiterhin empfohlen werden, bekommen aber kein Top-Tipp-Badge mehr.
- Sonntag-/Feiertag-Schließrisiko wird zentral behandelt.
- Regenlogik bleibt erhalten: Indoor-/wetterunabhängige Orte können empfohlen werden; ungeprüfte Öffnungszeiten bleiben aber kein Top-Tipp.

### Verifizierte Tests

- `node --check` für:
  - `js/opening-status.js`
  - `js/recommendations.js`
  - `js/today-home.js`
- Smoke-Test der `OpeningStatus`-API:
  - `opening_hours_check` ist nicht Top-Tipp-fähig.
  - frei planbare Indoor-Activities bleiben Top-Tipp-fähig.
  - Sonntag-/Feiertag-Schließrisiko liefert passende Labels und Score-Abzüge.
- Realer Recommendation-Test mit `data/offers.json`:
  - Werktag trocken
  - Werktag Regen
  - Sonntag trocken
  - Feiertag trocken
- Ergebnis bei Regen:
  - Indoor-Orte mit `opening_hours_check` bleiben oben, aber `topTip=no`.
  - Today Home kann dadurch bewusst ohne Top-Tipp-Badge bleiben, statt ungeprüfte Öffnungszeiten als starken Tipp auszuzeichnen.

### Commits

- `0e03a7a` – Zentralisiere Activity-Oeffnungsstatus
- `4c9a52e` – Zentralisiere Activity-Oeffnungsstatus-Labels
- `1934307` – Sperre Oeffnungszeiten-Pruefen als Top-Tipp

### Bewusste Grenzen

- Noch keine echten Öffnungszeiten in `data/offers.json` eingetragen.
- `holiday_policy` fehlt aktuell bei allen 44 Activities.
- Die aktuelle Bewertung ist vorsichtig und heuristisch.
- Keine automatische Prüfung konkreter Wochenzeiten.
- Vor Live-Merge/Live-Deploy weiterhin nötig: Live-DB-Spalte `submissions.activity_opening_json`.

### Nächster sinnvoller Schritt

Gezielte Datenanreicherung statt weiterer Logik-Patches:

1. Zuerst die 9 Activities mit `availability = opening_hours_check` prüfen.
2. Danach `holiday_policy` bzw. geprüfte Statuswerte in `data/offers.json` ergänzen.
3. Erst danach echte Wochenöffnungszeiten bzw. präzisere öffentliche Statusanzeige modellieren.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_OPENING_PUBLIC_STATUS_DONE_2026_06_08 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_QUALITY_AUDIT_V1_DONE_2026_06_08 | Zweck: dokumentiert Abschluss des Activity-Quality-Audit-V1-Datenstands; Umfang: 44 Activities, opening_status-Abdeckung, Needs-Review-Grenzen, finale Integrationstests === -->
## Activity Quality Audit V1 – Öffnungsstatus-Datenstand abgeschlossen – 2026-06-08

### Ergebnis

Der fachliche Audit der bestehenden Activities wurde vollständig durchgeführt und in `data/offers.json` umgesetzt.

### Umfang

- 44 Activities geprüft.
- 44 Activities mit `opening_status` versehen.
- Die zwei vorherigen Needs-Review-Fälle wurden nach Quellenprüfung gezielt abgeschlossen:
  - `quellengrundpark-weseke-entdecken` → `check_required`
  - `erlebnispfad-klostersee-burlo` → `free_access`

### Finale Verteilung

- `free_access`: 25
- `regular_hours`: 5
- `seasonal_recommended`: 5
- `seasonal_hours`: 5
- `check_required`: 4

### Umgesetzte Commits

- `c720627` – Stelle mobile Eventbilder auf Thumbnails um
  - Enthält zusätzlich den Batch-1-Opening-Status-Datenstand.
- `0ec6e18` – Ergaenze Oeffnungsstatus fuer Activity Audit Batch 2
- `023996a` – Ergaenze Oeffnungsstatus fuer Activity Audit Batch 3
- `1b69e55` – Ergaenze Oeffnungsstatus fuer Activity Audit Batch 4
- `c46c9ae` – Korrigiere Feiertagslogik fuer freie Activities
- `11215b2` – Schliesse Activity Opening Needs-Review ab
- `52370ed` – Entschaerfe Feiertagsrisiko freier Teilangebote

### Technische Validierung

Finale Integration geprüft für:

- Werktag trocken
- Werktag Regen
- Sonntag trocken
- Feiertag trocken

Ergebnis:

- 44 Recommendations werden erzeugt.
- `check_required` und `opening_hours_check` erhalten keinen Top-Tipp.
- `holiday_policy = not_applicable` erzeugt keine falsche Öffnungszeiten-Warnung.
- `holiday_policy = open` erzeugt keine falsche Öffnungszeiten-Warnung.
- `holiday_policy = check` bleibt für kernangebot-kritische Öffnungs-/Terminrisiken erhalten.
- Freie Kernangebote mit prüfpflichtigen Teilangeboten laufen über `holiday_policy = not_applicable`; Hinweise bleiben in `public_label` und `detail_note` sichtbar.
- `data/offers.json` ist valides JSON.
- Syntaxchecks bestanden:
  - `js/opening-status.js`
  - `js/recommendations.js`
  - `js/today-home.js`

### Bewusste Grenzen

- Keine Massenpflege echter Wochenöffnungszeiten über alle Activities.
- Keine erfundenen Öffnungszeiten.
- Saisonale Regeln wie Brutzeit, Badesaison oder Monitoring-Saison sind noch nicht als eigene strukturierte Saisonlogik final modelliert.
- Detailpanel-/UI-Anzeige der neuen `opening_status.detail_note` ist noch nicht final poliert.
- Die vorherigen Needs-Review-Activities sind fachlich geklärt; es bleibt keine Activity ohne `opening_status`.

### Nächster sinnvoller Schritt

Kein weiterer Daten-Massenpatch. Als nächstes nur gezielte Folgearbeit:

1. Saisonregel-Modell für echte Schutz-/Bade-/Saisonfälle sauber definieren.
2. UI-/Detailpanel-Anzeige für `opening_status.public_label` und `detail_note` prüfen.
3. Today-Home-/Recommendation-Verhalten visuell prüfen.
4. Erst danach kleine, owner-klare Logik- oder UI-Patches.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_QUALITY_AUDIT_V1_DONE_2026_06_08 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_OPENING_DETAILPANEL_DONE_2026_06_08 | Zweck: dokumentiert Abschluss der Activity-Öffnungsstatus-Anzeige im Detailpanel; Umfang: Datenweitergabe, sichtbarer Callout, einklappbare Hinweise === -->
## Activity Opening Detailpanel – abgeschlossen am 2026-06-08

### Ergebnis

Der geprüfte `opening_status` aus `data/offers.json` wird jetzt im Activity-Detailpanel sichtbar genutzt.

### Umgesetzt

- `js/offers-main.js` reicht `opening_status` und `opening_hours` aus `data/offers.json` in normalisierte Activity-Objekte durch.
- `js/offers-details.js` zeigt `opening_status.public_label` im Activity-Detailpanel an.
- `opening_status.detail_note` wird als nativer, ausklappbarer Hinweis angezeigt.
- Der kurze Öffnungsstatus bleibt sichtbar; lange Hinweise blockieren das Detailpanel nicht mehr dauerhaft.
- `css/overlays.css` stylt den ausklappbaren Hinweis ruhig und sekundär.

### Validierung

Geprüft wurden exemplarisch:

- `Aasee erleben`
- `Textilwerk Bocholt`
- `Bocholter Innenstadt erleben`
- `Quellengrundpark Weseke entdecken`

Ergebnis:

- Öffnungsstatus erscheint an sinnvoller Stelle nach der Beschreibung.
- Eingeklappt bleibt der Block kompakt.
- Ausgeklappt ist der Hinweis lesbar, aber weniger dominant.
- Merkmale und Aktionen bleiben schneller erreichbar.

### Commits

- `bec9c66` – Reiche Activity Oeffnungsstatus ins Detailpanel durch
- `8867eb8` – Zeige Activity Oeffnungsstatus im Detailpanel
- `9d18823` – Mache Activity Oeffnungshinweise ausklappbar

### Bewusste Grenze

Keine weitere Öffnungszeiten-Datenpflege und keine neue Saisonlogik in diesem Workpack.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_OPENING_DETAILPANEL_DONE_2026_06_08 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_DUPLICATE_CLEANUP_FREEZE_2026_06_08 | Zweck: dokumentiert Freeze der Eventbild-Motivdubletten-Bereinigung; Umfang: Event-Visual-Pool, Anti-Repeat, Ersatzbilder, Audits === -->
## Event Visual Duplicate Cleanup + Pool-Diversity Freeze – 2026-06-08

Status: auf `staging` umgesetzt, geprüft und vorerst gefreezt.

### Geprüfter Stand

Branch:

- `staging`

Relevante Commits:

- `78a7b4b` – `Integriere kuratierte Visual Assets`
- `c9cd092` – `Vermeide kurze Eventbild-Wiederholungen`
- `3387568` – `Bereinige Eventbild-Motivdubletten`

Finaler bestätigter Stand:

- `HEAD`: `3387568`
- `origin/staging`: `3387568`

### Umgesetzter Scope

Abgeschlossen wurden zwei zusammengehörige Qualitätsprobleme im Event-Visual-System:

1. Wiederholung gleicher Bilddateien im kurzen Feed-Kontext.
2. Near-Duplicate-Motive im `ready`-Pool trotz unterschiedlicher Dateien/IDs.

Umgesetzt:

- `js/events.js` wählt Eventbilder nicht mehr rein hashbasiert aus, sondern berücksichtigt:
  - bereits innerhalb eines `visual_key` verwendete Bilder,
  - ein kurzes Recent-Fenster,
  - Fallback-Auswahl bei kleinen Pools.
- `data/event_visual_pool.json` wurde bereinigt:
  - starke Near-Duplicate-Motive wurden aus `ready` auf `blocked` gesetzt,
  - Dateien wurden nicht gelöscht,
  - `blocked` bedeutet in diesem Kontext: nicht im Feed verwenden, weil Motiv-Dublette.
- Ersatz-/Ergänzungsbilder wurden kuratiert importiert:
  - `city-festival-street-04.webp`
  - `city-festival-street-05.webp`
  - `city-festival-street-06.webp`
  - `city-festival-street-07.webp`
  - `learning-science-workshop-03.webp`
  - `learning-science-workshop-04.webp`
  - `learning-science-workshop-05.webp`
- Alle neuen Assets wurden als WebP-Card-Assets im Format `1200x675` erzeugt und in den Pool als `ready` eingetragen.

### Finaler Pool-Stand

Nach Bereinigung und Ersatzimport:

- Event-Visual-Pools: `34`
- Event-Visual-Images: `204`
- `ready`: `125`
- `blocked`: `39`
- `planned`: `30`
- `usable`: `10`
- `fallback`: `0`

Wichtig:

- `blocked` ist hier kein Qualitätsurteil über grundsätzlich schlechte Bilder.
- `blocked` wurde für brauchbare, aber zu ähnliche Motivvarianten verwendet.
- Die geblockten Dateien bleiben nachvollziehbar im Pool erhalten, werden aber nicht im Feed als `ready` genutzt.

### Bestandene Prüfungen

Bestanden:

- `python3 tools/audit-visual-contract.py`
  - `Errors: none`
  - missing ready/fallback files: `0`
  - ready/fallback non-WebP: `0`
  - ready/fallback oversized: `0`
  - ready/fallback not 16:9: `0`
- `python3 scripts/audit-event-visual-pool.py`
  - Pool-Keys: `34 / 34`
  - Pool-Struktur konsistent
- `node --check js/events.js`
- Dimensionstest der neuen Assets:
  - alle neuen WebP-Dateien: `1200x675`
- Ready-Near-Duplicate-Audit:
  - `ready_items=125`
  - `strong_near_duplicate_pairs=0`
- Future-Event-Sequenzsimulation:
  - `future_events=61`
  - `same_selected_image_within_6_card_window=0`

### Geprüfte Problemfälle

Die zuvor sichtbaren bzw. erwartbaren Problemgruppen wurden fachlich adressiert:

- Konzert-/Bühnenbilder:
  - `Marienthaler Abende`
  - `As Time Goes By`
  - `live_music_stage`
- Stadtfest-/Innenstadtbilder:
  - `AaltenDagen`
  - `Innenstadtsommer`
  - `city_festival_street`
- Learning-/Workshop-Bilder:
  - `SummerSchool der JUNGE UNI`
  - `Ökosystem auf Pfosten`
  - `learning_science_workshop`
- konkret erkannte Near-Duplicate-Paare wie:
  - `comedy-cabaret-01` / `comedy-cabaret-02`
  - `classical-music-01` / `classical-music-02`
  - `music-stage-01-16x9` / `live-music-stage-02`
  - `city-festival-01-16x9` / `city-festival-street-02`

### Bekannte nicht blockierende Warnungen

Weiterhin bekannt und nicht Teil dieses Freeze-Scopes:

- `event_visual_pool.json owner is not event_visual_pool_v1`
- 18 ältere ready Event-Visuals ohne Alt-Text
- lokale Activity-Bildwarnung für `hilgelo-erleben`
- Activity-Visual- und Activity-Opening-Hours-Themen sind separate Workstreams.

### Bewertung

Der Event-Feed-Visual-Workpack ist für den aktuellen `staging`-Stand fachlich ausreichend bereinigt und wird vorerst gefreezt.

Der aktuelle Zielzustand ist nicht „maximal viele Bilder“, sondern:

- keine offensichtlichen Motiv-Dubletten im `ready`-Pool,
- keine gleiche Bildauswahl im kurzen Feed-Fenster,
- ausreichend Pool-Diversität bei häufig auftretenden Visual-Keys,
- keine ungeprüften Rohbilder im Repo,
- keine Änderung am Eventdatenprozess.

### Offene Folgepunkte

Nicht Teil dieses Freezes:

- weitere echte Veranstalter-/Location-Fotos,
- Detailpanel-/Hero-Bildlogik,
- vollständiges visuelles Audit aller Cards nach jedem neuen Eventdatenstand,
- Alt-Text-Schuld älterer Phase-2-Assets,
- Activity-Visual-System.

Nach einem Staging-Deploy genügt ein visueller Smoke-Test mit hartem Reload für:

- `Marienthaler Abende` / `As Time Goes By`
- `AaltenDagen` / `Innenstadtsommer`
- `SummerSchool` / `Ökosystem auf Pfosten`
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_DUPLICATE_CLEANUP_FREEZE_2026_06_08 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_MANUAL_KI_INTAKE_STAGING_LIMIT_2026_06_09 | Zweck: dokumentiert bewusste Testgrenze des Manual-KI-Intake-Workflows auf staging; Umfang: Visual-Key-Handoff, Branch-Guard, offener Main-Beweis === -->
## Manual-KI-Intake / Visual-Key-Handoff – Staging-Testgrenze

Stand: `staging` Commit `ba10f20` (`Finalisiere Visual-Key-Handoff fuer KI-Intake`)

### Umgesetzt und auf staging validiert

Der Visual-Key-Handoff für KI-gefundene Events wurde technisch umgesetzt:

- `manual-ki-intake.yml` schreibt `visual_key` in den Google-Sheet-Tab `Inbox`.
- `Inbox.visual_key` wird als Google-Sheets-Dropdown aus `data/event_visual_pool.json -> pools` gesetzt.
- Alte bzw. manuelle KI-JSON-Einträge ohne `visual_key` bekommen deterministisch einen Key über `scripts/event_visual_keys.py`.
- `scripts/inbox-to-events.py` erzwingt `visual_key` in `Inbox` und `Events`.
- Redaktionell geänderte `Inbox.visual_key`-Werte werden nach `Events.visual_key` übernommen.
- Ungültige manuelle Keys brechen beim Übernehmen fail-fast ab, statt still neu inferiert zu werden.

### Bestandene Prüfungen auf staging

Bestanden:

- `git diff --check`
- `python3 -m py_compile scripts/event_visual_keys.py scripts/inbox-to-events.py scripts/build-events-from-tsv.py scripts/build-inbox-from-tsv.py`
- `python3 scripts/audit-event-visual-pool.py`
  - Pool-Keys: `34 / 34`
  - Pool-Struktur konsistent
- Lokaler Inferenzbeweis für `data/inbox_manual.json`
  - `manual_items=9`
  - `pool_keys=34`
  - `errors=0`
- Staging-Deploy:
  - Workflow: `Deploy to STRATO`
  - Run: `27205948911`
  - Ergebnis: `success`
  - HEAD: `ba10f202fa13b1301408aad0ff7e821fea05a697`

### Bewusste Testgrenze

Der eigentliche Workflow `Manual KI Event Intake` kann auf `staging` bewusst nicht vollständig ausgeführt werden.

Grund:

- `.github/workflows/manual-ki-intake.yml` enthält den Branch-Guard `MANUAL_KI_INTAKE_PRODUCTION_BRANCH_GUARD_V1`.
- Der Workflow darf nur auf `main` laufen.
- `staging` soll keine echten KI-Kandidaten in die Live-Google-Sheet-Review-Kette schreiben.

Konsequenz:

- Auf `staging` können Code-, Syntax-, Pool-, Dropdown-Definition- und lokale Inferenzprüfungen durchgeführt werden.
- Der vollständige Live-Beweis gegen Google Sheets ist erst nach Merge bzw. Run auf `main` möglich.
- Ein fehlgeschlagener Versuch, den Workflow per `gh workflow run manual-ki-intake.yml --ref staging` zu starten, ist kein fachlicher Fehler des Visual-Key-Handoffs; `staging` ist hierfür bewusst nicht der Zielpfad.

### Main-Beweis nachträglich erledigt

Der ursprünglich offene Main-Beweis wurde am 2026-06-25 im Block `TEST_STATUS_MAIN_KI_SEARCH_INBOX_VISUAL_KEY_PROOF_2026_06_25` bestanden.

Geprüft und bestanden:

1. `Inbox.visual_key` wird im Google Sheet mit dem KI-Vorschlag befüllt.
2. Das Dropdown für `Inbox.visual_key` erscheint mit den erlaubten Keys aus `event_visual_pool.json`.
3. Der PWA-/Apps-Script-Übernehmen-Pfad übernimmt `visual_key` nach einem externen Apps-Script-Fix nach `Events.visual_key`.
4. Der spätere Deploy übernimmt `Events.visual_key` in die Live-Bildausspielung.
5. Deployte Event-Cards erhalten dadurch ein Bild aus dem passenden Event-Visual-Pool.

Folge: Der alte Staging-Grenzblock bleibt historischer Implementierungsnachweis; der aktuelle Status ist nicht mehr offen.
<!-- === END BLOCK: TEST_STATUS_MANUAL_KI_INTAKE_STAGING_LIMIT_2026_06_09 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_VISUAL_AUDIT_WARNINGS_CLEANUP_2026_06_09 | Zweck: dokumentiert Bereinigung der Visual-Audit-Warnungen; Umfang: Event-Alt-Texte, Audit-Owner-Check, Activity-JPG-Einordnung === -->
## Visual-Audit-Warnungen bereinigt – 2026-06-09

Scope:
- keine neuen Bilder
- keine UI-/App-Logik-Änderung
- kein Event-Visual-Duplicate-Cleanup
- kein Activity-Premium-Visual-Produktionslauf

Umgesetzt:
- 18 `ready`-Event-Visuals ohne Alt-Text in `data/event_visual_pool.json` wurden mit generischen, symbolischen Alt-Texten ergänzt.
- `tools/audit-visual-contract.py` akzeptiert den dokumentierten Event-Pool-Owner `event_visual_pool_v31` zusätzlich zum historischen `event_visual_pool_v1`.
- Lokale Activity-Bilder werden nur dann als Non-WebP-Warnung gemeldet, wenn sie produktiv verwendbar markiert sind (`ready`, `usable`, `fallback`).

Bewusst geparkt:
- `hilgelo-erleben` referenziert weiterhin das lokale JPG `/assets/activities/20250704_203925.jpg`, bleibt aber in `data/offers.json` als `image_quality: needs_review` eingeordnet.
- Die finale Activity-Bildablösung erfolgt im separaten Activity-Premium-Visual-Workstream.
- Die drei Activity-Einträge mit `needs_review` bleiben fachlich sichtbar im Audit-Summary, sind aber keine Strict-Warnung, solange sie nicht als produktive Premium-Assets freigegeben sind.

Prüfziel:
- `python3 tools/audit-visual-contract.py --strict` läuft ohne Fehler und ohne Warnungen durch.

Ergebnis nach erfolgreicher Prüfung:
- `Errors: none`
- `Warnings / known visual debt: none`
<!-- === END BLOCK: TEST_STATUS_VISUAL_AUDIT_WARNINGS_CLEANUP_2026_06_09 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_TODAY_HOME_RELEASE_PROOF_2026_06_10 | Zweck: dokumentiert Staging-Release-Proof der neuen Today-Home; Umfang: P0/P1-Abschluss, Deploy, HTTP-Smoke, Browser-Runtime, Detailpanel === -->
## Today Home – Staging Release Proof 2026-06-10

Status: auf `staging` umgesetzt, deployed und per HTTP-/Browser-Runtime-Probe geprüft.

Bestätigter Stand:

- Branch: `staging`
- Commit: `4457fc7` (`Korrigiere Today Home JSON-LD`)
- Deploy: `Deploy to STRATO`, Run `27260197568`, Ergebnis `success`
- Build-Marker: `4457fc73a403`
- Runtime-Ziel: `https://staging.bocholt-erleben.de/`

Bestandene lokale Prüfungen:

- `node --check` für:
  - `js/today-home.js`
  - `js/recommendations.js`
  - `js/opening-status.js`
  - `js/weather-context.js`
  - `js/details.js`
  - `js/offers-details.js`
- `python3 tools/audit-visual-contract.py --strict`
  - `Errors: none`
  - `Warnings / known visual debt: none`
- Cleanup-Grep ohne Treffer für:
  - alte Today-Controls
  - alte Today-Mode-/Interest-Controls
  - alte Eventfilter-Popover
  - alte `FILTER_SHEET_WIRING`-/`DESKTOP FILTER POPOVERS`-Reste

Bestandene HTTP-Runtime-Prüfungen auf Staging:

- `/` liefert HTTP 200.
- Ausgeliefertes HTML enthält `#today-root`, `#today-weather-note`, `#today-feed` und Today-SEO.
- Ausgeliefertes HTML enthält keine alten Today-Controls, keine alten Popover und keinen alten JSON-LD-Namen `Veranstaltungen in Bocholt`.
- `/data/events.json` liefert HTTP 200.
- `/data/offers.json` liefert HTTP 200.
- `/data/event_visual_pool.json` liefert HTTP 200.
- Kritische Today-/Detailpanel-JS-Assets liefern HTTP 200.

Bestandene Browser-Runtime-Prüfung:

- `todayRoot: true`
- `cardCount: 3`
- `oldControls: 0`
- `oldPopovers: 0`
- alle drei gerenderten Karten haben ein Bild
- alle drei Kartenbilder waren geladen
- WeatherNote rendert
- Status rendert `3 Tipps`
- keine sichtbaren alten Controls/Popover

Bestandene Detailpanel-Prüfung:

- `panelExists: true`
- `panelVisible: true`
- `hasContent: true`
- `hasImage: true`
- `actionbarExists: true`
- Event-Visual wird im Detailpanel übernommen.
- Actionbar enthält Kalender- und Teilen-Aktion.

Abgeschlossene Workpacks:

- P0.1 Eventdaten optional laden.
- P0.2 Today-Event-Visual ins Detailpanel übernehmen.
- P0.3 Runtime-Test mit echter deployter Eventdatei.
- P0.4 Event-Visual-Audit ohne Warnungen/Fehler.
- P0.5 Activity-Needs-Review-Ausschluss.
- P1.1 lokale/diverse Activity-Auswahl.
- P1.2 maximal drei Skeleton-Cards.
- P1.5 versteckte Today-Controls entfernt.
- P1.6 alte Eventfilter-/Popover-Reste entfernt.
- P1.7 Footer-/HTML-Struktur bereinigt.
- P1.8 JSON-LD auf Today-Home korrigiert.

Bewertung:

Die neue Today-Home ist für den aktuellen `staging`-Stand funktional releasefähig. Weitere Today-Arbeit soll ab jetzt nur noch als klar abgegrenzter visueller Feinschliff, Accessibility-Prüfung oder belegter Bugfix erfolgen.
<!-- === END BLOCK: TEST_STATUS_TODAY_HOME_RELEASE_PROOF_2026_06_10 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_TODAY_HOME_PREMIUM_FREEZE_2026_06_11 | Zweck: dokumentiert die finale visuelle Premium-Abnahme der Today/Home nach Desktop-/Mobile-Preview; Umfang: / Today Home, Desktop-/Mobile-Layout, Hero-Typografie, CTA-Hierarchie, Footer; keine Activity-Bildproduktion === -->
## Today/Home Premium Freeze – Desktop-/Mobile-Abnahme – 2026-06-11

### Baseline

- Branch: `staging`
- Abschluss-HEAD: `5c849da` – `Gleiche Today Hero Titelgewicht an`
- Relevante Vorarbeiten:
  - `b2cb148` – `Gleiche Today Desktop Shell an Events an`
  - `b24952f` – `Zentralisiere globalen Footer`
  - `8ad02da` – `Nutze Activity Visual Pool auf Today Home`
  - `3c62d7a` – `Strecke Today Card Media auf volle Breite`

### Geprüfte Preview-Zustände

Geprüft per Codespaces Preview, nicht per blindem Staging-Zwischen-Deploy:

- Desktop `/` bei `1440 × 1220`, 100 % Zoom
- Mobile `/` bei `390 × 844`, 100 % Zoom
- Ausschnitte:
  - Header
  - Hero
  - Wetterhinweis
  - Empfehlungsfläche
  - drei Today-Cards
  - CTA-Karten
  - Footer
  - Bottom-Navigation

### Ergebnis

Today/Home ist visuell und layoutseitig für V1 eingefroren.

Bestanden:

- Header-Konsistenz zu Events/Aktivitäten.
- Desktop-Hero wirkt als Premium-Top-Shell und nicht mehr wie ein isolierter Sonderfall.
- Hero-Titelgewicht wurde an Events/Aktivitäten angeglichen.
- Desktop-Lesefläche ist klar, aber keine harte weiße Box.
- Übergang Hero → grüne Empfehlungsfläche ist ruhig.
- Empfehlungs-Cards sind nicht zu schwer.
- CTA-Karten `Alle Events ansehen` und `Aktivitäten entdecken` sind sichtbar, aber nicht dominanter als die Empfehlungen.
- Mobile wurde durch Desktop-Polish nicht beschädigt.
- Zentraler Footer ist auf `/`, `/events/` und `/aktivitaeten/` konsistent.

### Freeze-Entscheidung

- Today/Home V1 gilt als visuell abgeschlossen.
- Weitere CSS-/Layout-Arbeit an `/` nur noch bei konkretem belegtem Symptom.
- Keine offenen generischen Polish-Runden mehr für Today/Home.

### Bewusste Nicht-Blocker

- Das sichtbare `Textilwerk Bocholt`-Bild wirkt dunkel/schwer.
- Das ist kein CSS-/Layout-Blocker.
- Bildqualität einzelner Activity-Cards gehört in den separaten Activity-Premium-Visual-Workstream.
- Keine dauerhafte Bildrettung per CSS.
<!-- === END BLOCK: TEST_STATUS_TODAY_HOME_PREMIUM_FREEZE_2026_06_11 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_CARD_IMAGES_POLISH_DONE_2026_06_10 | Zweck: dokumentiert Abschluss des Activity-Card-Bild-Polish-Workpacks; Umfang: mobile Activity-Cards, Kategorie-Kicker, Codespaces-Preview-Nutzung === -->
## Activity Card Images / Mobile Card Polish abgeschlossen

Stand: 2026-06-10

Ergebnis:
- Activity-Cards zeigen im mobilen Feed jetzt Bilder rechts als Thumbnail.
- Normale Activity-Cards zeigen keinen Kategorie-Kicker mehr über dem Titel.
- Die Card-Hierarchie ist dadurch näher an den Eventcards.
- Die vorhandene Activity-Bildquelle bleibt unverändert; Premiumbilder werden separat nachgezogen.
- Die Anbieter-CTA-Karte bleibt bewusst als Sonderkarte mit eigenem Kicker bestehen.
- Codespaces Preview wurde erfolgreich für schnelle UI-Prüfung genutzt und in `ENGINEERING.md` dokumentiert.

Validierung:
- Lokale Codespaces Preview geprüft.
- Staging `/aktivitaeten/` mobil geprüft.
- Darstellung auf Samsung S24 als passend bestätigt.
- Keine weitere Card-Struktur-Korrektur notwendig.

Relevante Commits:
- `88d1e12` — Zeige Activitybilder in mobilen Cards
- `0cd9ab7` — Entferne Kategorie-Kicker aus Activitycards
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_CARD_IMAGES_POLISH_DONE_2026_06_10 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_VISUAL_PHASE_A_POOL_INTEGRATION_2026_06_11 === -->
## Activity Visual Phase A – Pool-Integration abgeschlossen (2026-06-11)

### Status

Activity Visual Phase A ist abgeschlossen.

Die geprüften und vorbereiteten Activity-Bilder wurden als lokale 16:9-WebP-Assets integriert und im Activity Visual Pool registriert.

Bestätigter Commit:

- `7a78240` – `Integriere Activity Visual Phase A Assets`

Aktueller bestätigter Staging-Stand nach nachgelagertem Today-Commit:

- `b2cb148` – `Gleiche Today Desktop Shell an Events an`

### Umfang

Integriert wurden:

- 16 neue WebP-Assets unter `assets/activity-visuals/`
- 16 neue Pool-Einträge in `data/activity_visual_pool.json`
- keine Änderungen an `data/offers.json`
- keine Änderungen an `js/offers.js`
- keine Änderungen an `js/offers-details.js`
- keine Änderungen an `css/today.css` durch diesen Workstream

### Finaler Audit-Stand

Letzter bestätigter Audit:

- `python3 tools/audit-visual-contract.py --strict`

Ergebnis:

- Activity visual pool:
  - `exists: True`
  - `pools: 41`
  - `images: 41`
  - `ready: 38`
  - `fallback: 3`
  - `missing ready/fallback files: 0`
  - `ready/fallback non-WebP: 0`
  - `ready/fallback oversized: 0`
  - `ready/fallback missing alt: 0`
  - `ready/fallback not 16:9: 0`
- Activity visuals:
  - `offers: 44`
  - `explicit images: 44`
  - `visual keys: 0`
- `Warnings / known visual debt: none`
- `Errors: none`

### Einordnung

Die Asset- und Pool-Basis ist damit technisch sauber.

Die öffentlichen Activity-Flächen nutzen die neuen Poolbilder noch nicht vollständig, weil die Offer-Daten weiterhin explizite Bilder verwenden und noch keine `visual_key`-Zuordnung gesetzt ist.

Das ist bewusst offen und gehört in Phase B.

### Offener nächster Schritt

Activity Visual Phase B:

- `data/offers.json` mit passenden `visual_key`-Werten ausstatten
- `js/offers.js` so umstellen, dass der Activity Visual Pool Vorrang vor `offer.image` hat
- `js/offers-details.js` auf dieselbe Bildauflösung bringen
- `offer.image` nur noch als Fallback verwenden
- danach Audit, Syntaxchecks und UI-Smoke-Test auf `/aktivitaeten/`

### Weiterhin nicht final premium

| Activity-ID | Status | Maßnahme |
|---|---|---|
| `suderwicker-maerchenspielplatz` | `own_photo_required` | echtes Foto ohne erkennbare Kinder/Personen beschaffen |
| `waldlehrpfad-am-vossenpand` | `request_permission` | Stadt Bocholt/ESB um Freigabe für besseres personenfreies Galeriebild bitten |
| `handwerksmuseum-bocholt-erleben` | `request_permission` | Handwerksmuseum/Stadt Bocholt um hochauflösendes Innen-/Werkstattbild bitten |
| `das-mysterium-von-winterswijk` | `request_permission` | 100% Winterswijk/Tourismus Winterswijk um offizielles Routenmotiv bitten |
| `grenzenlos-wandern-dinxperlo-suderwick` | `fallback_needs_better_real_photo` | später besseres echtes Grenzroute-Foto ohne dominante Marke/Schilder beschaffen |
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_VISUAL_PHASE_A_POOL_INTEGRATION_2026_06_11 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_VISUAL_PHASE_B_PUBLIC_USAGE_2026_06_12 | Zweck: dokumentiert Abschluss der oeffentlichen Activity-Visual-Pool-Nutzung; Umfang: data/offers.json visual_key-Mapping, zentraler Resolver, Pool-Load, Card-/Detailpanel-Smoke, Restbilder === -->
## Activity Visual Phase B – Public Usage / Pool Mapping abgeschlossen (2026-06-12)

### Status

Activity Visual Phase B ist auf `staging` implementiert, getestet und gepusht.

Bestätigter Commit:

- `988b437` – `Nutze Activity Visual Pool auf Activity-Seite`

### Umgesetzter Scope

Geändert wurden ausschließlich:

- `data/offers.json`
- `js/offers.js`
- `js/offers-main.js`

Nicht geändert wurden:

- `js/offers-details.js`
- `css/today.css`
- Today/Home-Layout
- Event-Visual-System

### Technischer Zielzustand

Die öffentlichen Activity-Flächen nutzen jetzt den Activity Visual Pool vorrangig über `visual_key`.

Die zentrale Bildauflösung läuft über:

- `window.OfferVisuals.resolveImageData(offer)`

Priorität der Bildauflösung:

1. Activity Visual Pool über `visual_key`
2. `offer.image` als Fallback
3. generisches Fallbackbild als letzte Stufe

`js/offers-details.js` musste nicht geändert werden, weil das Activity-Detailpanel bereits dieselbe zentrale Resolver-Funktion nutzt.

### Mapping-Ergebnis

`data/offers.json` enthält jetzt 34 `visual_key`-Zuordnungen.

Bewusst ohne Pool-Key bleiben 10 Activities, weil aktuell kein passender Pool-Eintrag existiert:

- `handwerksmuseum-bocholt-erleben`
- `schloss-ringenberg-erleben`
- `anholter-schweiz-erleben`
- `villa-mondriaan-winterswijk-erleben`
- `waldlehrpfad-am-vossenpand`
- `suderwicker-maerchenspielplatz`
- `buergerpark-rhede`
- `das-mysterium-von-winterswijk`
- `borkener-tuerme-tour`
- `tiergarten-schloss-raesfeld-erleben`

### Validierung

Bestandene technische Checks:

- `git diff --check -- data/offers.json js/offers.js js/offers-main.js`
- `node --check js/offers.js`
- `node --check js/offers-main.js`
- `node --check js/offers-details.js`
- `python3 tools/audit-visual-contract.py --strict`

Audit-Ergebnis:

- Activity visual pool:
  - `pools: 41`
  - `images: 41`
  - `ready: 38`
  - `fallback: 3`
  - `missing ready/fallback files: 0`
  - `ready/fallback non-WebP: 0`
  - `ready/fallback oversized: 0`
  - `ready/fallback missing alt: 0`
  - `ready/fallback not 16:9: 0`
- Activity visuals:
  - `offers: 44`
  - `explicit images: 44`
  - `visual keys: 34`
  - `missing visual status: 0`
  - `needs_review/blocked with image: 3`
- `Warnings / known visual debt: none`
- `Errors: none`

Zusätzliche Datenkonsistenzprüfung:

- `resolved_pool_images: 34`
- `fallback_offers: 10`
- `duplicate_pool_src: 0`
- `errors: 0`

Runtime-Resolver-Smoke:

- Pool-Activities lösen auf `/assets/activity-visuals/...` auf.
- `ready` und `fallback` werden korrekt erkannt.
- Fallback-Activities ohne `visual_key` fallen stabil auf `offer.image` zurück.
- `resolver_errors: 0`

UI-Smoke auf `/aktivitaeten/`:

- Cards geprüft für:
  - `textilwerk-bocholt-erleben`
  - `bocholter-innenstadt-erleben`
  - `wasserburg-anholt-erleben`
  - `grenzenlos-wandern-dinxperlo-suderwick`
  - `erlebnisweg-olle-kerkpatt`
  - `suderwicker-maerchenspielplatz`
- Ergebnis: alle Test-Cards gefunden, alle mit Bild.
- Detailpanel-Smoke: alle 6 Detailpanel-Bilder gefunden.
- Card und Detailpanel nutzen dieselbe Resolver-Logik.

### Weiterhin nicht final premium

Diese Restbilder bleiben bewusst offen und werden nicht per CSS kaschiert:

| Activity-ID | Status | Maßnahme |
|---|---|---|
| `suderwicker-maerchenspielplatz` | `own_photo_required` | echtes Foto ohne erkennbare Kinder/Personen beschaffen |
| `waldlehrpfad-am-vossenpand` | `request_permission` | Stadt Bocholt/ESB um Freigabe für besseres personenfreies Galeriebild bitten |
| `handwerksmuseum-bocholt-erleben` | `request_permission` | Handwerksmuseum/Stadt Bocholt um hochauflösendes Innen-/Werkstattbild bitten |
| `das-mysterium-von-winterswijk` | `request_permission` | 100% Winterswijk/Tourismus Winterswijk um offizielles Routenmotiv bitten |
| `grenzenlos-wandern-dinxperlo-suderwick` | `fallback_needs_better_real_photo` | später besseres echtes Grenzroute-Foto ohne dominante Marke/Schilder beschaffen |

### Abschlussbewertung

Activity Visual Phase B ist abgeschlossen.

Die Activity-Bilder sind öffentlich sauber über den Activity Visual Pool verdrahtet. Restbilder bleiben als transparenter Visual-Debt bestehen.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_VISUAL_PHASE_B_PUBLIC_USAGE_2026_06_12 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_DETAILPANEL_PREMIUM_SYSTEM_DONE_2026_06_12 | Zweck: dokumentiert Abschluss des Detailpanel-Premium-System-Blocks nach Mobile-Shell-Haertung und Desktop-Direct-Policy; Umfang: mobile Event-/Activity-Detailpanels, Today/Home Desktop Direct-Outbound, keine Parallelchat-Dateien === -->
## Detailpanel Premium System – abgeschlossen am 2026-06-12

Der Detailpanel-Live-Blocker wurde auf `staging` funktional abgeschlossen.

### Zielentscheidung

- Mobile nutzt Detailpanels als Entscheidungs- und Aktionsflaeche.
- Desktop bleibt Card-first: Cards enthalten die wichtigsten Informationen, Klicks fuehren direkt outbound bzw. zum primaeren Ziel.
- Today/Home nutzt mobil die bestehenden Event-/Activity-Detailpanels und fuehrt auf Desktop nicht mehr in das geteilte Detailpanel.

### Umgesetzter Scope

- `89bb32b` – `Haerte mobiles Activity Detailpanel`
  - Activity-Detailpanel mobil technisch gehaertet.
  - Fokus-/ARIA-/Back-/Scroll-/Drag-Shell an das robuste Event-Detailpanel angenaehert.
  - Detailpanel-Systemvertrag um Mobile-/Desktop-Policy ergaenzt.

- `8239d92` – `Oeffne Today Desktop Cards direkt`
  - Today/Home Desktop-Cards oeffnen direkt outbound bzw. das primaere Ziel.
  - Mobile Today/Home bleibt bei Event-/Activity-Detailpanels.

### Gepruefter Stand

Vom Nutzer bestaetigt:

- Mobile `/aktivitaeten/`: Activity-Detailpanel funktioniert.
- Mobile `/`: Event- und Activity-Detailpanel funktionieren.
- Mobile `/events/`: Event-Detailpanel funktioniert weiter.
- Desktop `/`: Home-Cards oeffnen wie gewuenscht direkt outbound bzw. zum Ziel, kein Detailpanel.

### Nicht Teil dieses Scopes

- Keine Activity-Bildproduktion.
- Keine Event-/Activity-Feed-Card-Polishrunde.
- Keine Today/Home-Layoutaenderung nach dem Premium-Freeze.
- Keine Bearbeitung der Parallelchat-Dateien `data/offers.json`, `js/offers-main.js`, `js/offers.js`.
<!-- === END BLOCK: TEST_STATUS_DETAILPANEL_PREMIUM_SYSTEM_DONE_2026_06_12 === -->

<!-- BEGIN TEST_STATUS_ACTIVITY_REALFOTO_VISUALS_DONE_2026_06_12 -->

## Activity Realfoto Visuals – Abschluss 2026-06-12

Status: abgeschlossen und auf `staging` gepusht.

Commits:
- `c7b3699` – Dokumentiere Activity Visual Bildentscheidungen
- `52b1bc1` – Integriere Activity Realfoto Visuals

Umgesetzt:
- 16 geprüfte Activity-Realfotos als lokale WebP-Assets integriert bzw. bestehende Activity-Visuals ersetzt.
- 8 neue lokale Activity-WebPs ergänzt.
- 8 bestehende Activity-WebPs durch bessere Realfoto-Versionen ersetzt.
- `data/activity_visual_pool.json` erweitert/aktualisiert.
- `data/offers.json` um fehlende `visual_key`-Mappings ergänzt.
- Lizenz-/Quellenentscheidungen kanonisch in `data/activity_visual_source_decisions.tsv` festgehalten.
- Rohdateien/Arbeitsdateien wurden nicht committed.

Audit nach Umsetzung:
- Event visuals: OK
- Activity visual pool: 48 Pools, 48 Images, 44 `ready`, 4 `fallback`
- Activity offers: 44 Offers, 41 Visual Keys
- Missing files: 0
- Non-WebP ready/fallback: 0
- Missing alt: 0
- 16:9 violations: 0
- Errors: none

Bewusste Restfälle:
- `buergerpark-rhede`: gutes offizielles Stadt-Rhede-Bild vorhanden, aber Freigabe nötig.
- `waldlehrpfad-am-vossenpand`: offizielles Bocholt-/Pressebildmaterial vorhanden, aber Nutzungsbasis/Freigabe noch zu klären.
- `suderwicker-maerchenspielplatz`: vorerst parken bzw. später ggf. in Sonderkategorie Spielplätze aufnehmen.
- `handwerksmuseum-bocholt-erleben` und `grenzenlos-wandern-dinxperlo-suderwick`: aktuell als `fallback`/Übergang bewusst akzeptiert.

<!-- END TEST_STATUS_ACTIVITY_REALFOTO_VISUALS_DONE_2026_06_12 -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_FINAL_VISIBLE_RULE_SWEEP_2026_06_18 | Zweck: dokumentiert konsolidierten Regelabschluss nach Frontend-Sichtfund; Umfang: Fuehrung/Tour, sichtbare Zukunftsevents, keine neuen Bilder === -->
## Event Visual Motif-Fit – finaler sichtbarer Regel-Sweep (2026-06-18)

Auslöser:
- Frontend-Sichtprüfung fand bei `Auf dem Holzweg – Klumpenführung durch Rhede` ein fachlich falsches Aktiv-/Sportbild.
- Ursache war keine fehlende Datei, sondern eine zu breite Tour-/Führungslogik.

Korrekturprinzip:
- Harte Eventtypen und konkrete Formate bleiben vorrangig.
- Historische/thematische Führungen werden vor Aktiv-/Naturtouren bewertet.
- Generische Begriffe wie `Tour` oder `Rundgang` entscheiden allein nichts mehr.
- Echte Aktivformate wie Fahrradtour und Segway bleiben `active_route_tour`.
- Natur-/Wasser-/Wildlife-Kontexte bleiben `nature_learning_wildlife`.

Lokal geprüfte Leitfälle:
- `Auf dem Holzweg – Klumpenführung durch Rhede` → `city_tour_history / costumed_history_tour`
- `Sagensafari` → `city_tour_history / neutral_guided_city_tour`
- `Fahrradtour mit Guide` → `active_route_tour / guided_bike_tour`
- `Segwaytouren` → `active_route_tour / neutral_active_tour`
- `Führung Lebenselixier Wasser - der Pröbstingsee...` → `nature_learning_wildlife / walking_nature_tour`
- `Das schönste Ei der Welt` → `kids_stage_story / puppet_theater`
- `Quartierfest im Klostergarten` → `city_festival_street / district_festival`
- `Living History im Textilwerk` → `local_history_heritage / museum_history_exhibition`

Prüfstand:
- Keine neuen Bilder.
- Matrix neu gebaut.
- Gap-Backlog neu gebaut.
- Premium Visual Contract Audit bestanden (`Errors: none`).
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_FINAL_VISIBLE_RULE_SWEEP_2026_06_18 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_RESOLVER_FINAL_2026_06_18 | Zweck: dokumentiert finale motivgenaue Runtime-Bildauswahl; Umfang: events.json visual_motif, Frontend-Resolver, Matrix/Backlog/Audit === -->
## Event Visual Motif-Fit – finaler motivgenauer Resolver (2026-06-18)

Auslöser:
- Nach technischer Bildabdeckung blieben fachlich schwache Treffer möglich, weil Runtime/Frontend nur `visual_key`, aber nicht `visual_motif` nutzten.
- Dadurch konnte innerhalb eines groben Pools ein anderes spezifisches Motiv gewählt werden, z. B. Markt allgemein statt Martinsmarkt oder Indoor-Sport allgemein statt Darts/Fechten.

Korrektur:
- `scripts/build-events-from-tsv.py` schreibt zusätzlich `visual_motif` in das generierte `data/events.json`.
- `js/events.js` und `js/today-home.js` wählen zuerst Bilder mit exakt gleichem `visual_motif`.
- Wenn kein exaktes Motivbild existiert, wird ein neutrales/fallbackfähiges Bild aus demselben `visual_key` bevorzugt.
- Andere spezifische Motive desselben Pools werden nicht mehr als erster Fallback bevorzugt.

Zusätzliche Regelhärtung:
- `Weltkindertagsfest`/Kinder-/Familienfest-Kontexte fallen auf `family_play_outdoor`, statt als leeres Stadtfestbild zu erscheinen.
- Historische/thematische Führungen bleiben von Aktivtouren getrennt.

Lokaler Prüfbefund:
- Matrix: {'not_needed': 26, 'parked_candidate': 4, 'ready': 70}
- Gap-Backlog: 0 offene Zeilen
- Resolver-Abdeckung generierter Events: {'exact': 57}
- JS Syntaxcheck: `node --check js/events.js` und `node --check js/today-home.js` bestanden.
- Premium Visual Contract Audit: bestanden (`Errors: none`).
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_RESOLVER_FINAL_2026_06_18 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_FIT_FINAL_CLOSURE_2026_06_18 | Zweck: finaler Abschlussnachweis fuer Event-Visual-Motif-Fit nach Deploy und Frontend-QA; Umfang: technischer Smoke, Motiv-Fit-Stichprobe, datenbasierte Vollpruefung, Main-Freigabe === -->
## Event Visual Motif-Fit – final abgeschlossen auf Staging (2026-06-18)

Status: abgeschlossen für den aktuellen Sheet-/Staging-Stand.

Durchgeführter Abschlussnachweis:
- Finaler motivgenauer Resolver wurde auf `staging` deployed.
- Deploy war grün.
- Technischer Frontend-Smoke auf `/events/` bestanden:
  - keine weißen Bildflächen,
  - keine leeren Bilder,
  - keine kaputten Event-Visuals.
- Sichtbare Motiv-Fit-Stichprobe bestanden:
  - `Auf dem Holzweg – Klumpenführung durch Rhede` → historisch/thematische Führung,
  - `Sagensafari` → geführte Orts-/Geschichtstour,
  - `Das schönste Ei der Welt` → Puppentheater/Kinderbühne,
  - `Quartierfest im Klostergarten` → Quartiers-/Nachbarschaftsfest,
  - `Living History im Textilwerk` → Museum/Geschichte/Textilwerk-Kontext,
  - `Weltkindertagsfest` → Familien-/Kinderfest,
  - `Rosenbergfestival` → Stadtteil-/Quartiersfest,
  - `CityArt` → Kunstmarkt/Kunststände,
  - `Martinsmarkt` → saisonaler Markt,
  - `Lichtersonntag` → Innenstadt/Shopping/verkaufsoffener Sonntag,
  - `Gesundheitsberufemesse` → Gesundheits-/Berufsmesse,
  - `Vereinsmesse` → Vereins-/Infomesse.
- Datenbasierte Vollprüfung für veröffentlichte/zukünftige Events bestanden:
  - exakte `visual_motif`-Treffer,
  - keine fehlenden Bilder,
  - keine offenen Produktions-/Review-Gaps.

Abschlussbewertung:
- Event-Visual-Produktion, Motiv-Fit, Regelhärtung und motivgenauer Resolver sind für den aktuellen Stand abgeschlossen.
- Keine weitere Bildproduktion und kein weiterer Patch aus diesem Arbeitsblock nötig.
- Weitere Arbeit nur bei neuem Sheet-Bedarf oder konkret sichtbarem falschem Einzelfall.

Main-Freigabe:
- Dieser Arbeitsblock kann Richtung `main`, sofern `staging` keine anderen unfertigen Änderungen enthält.
<!-- === END BLOCK: TEST_STATUS_EVENT_VISUAL_MOTIF_FIT_FINAL_CLOSURE_2026_06_18 === -->
<!-- === BEGIN BLOCK: TEST_STATUS_REPORTING_HARDENING_LIVE_PROOF_2026_06_19 | Zweck: dokumentiert den Live-Beweis nach Reporting-Hardening, Aktivitaeten-Funnel-Migration und Main-Merge; Umfang: Anbieter-CTA, Nutzwert-Klicks, Dashboard-Nachweis, Abgrenzung Testklicks === -->
## Reporting-/Tracking-Hardening – Live-Beweis nach Main-Merge (2026-06-19)

Status: bestanden.

Kontext:
- Der Staging-Stand mit Aktivitäts-Funnel-Migration, Footer-Konsistenz und Reporting-Hardening wurde nach `main` gemerged und ist live.
- Geprüft wurde die echte Live-Kette über UI-Klick und internes SEO-/Mehrwert-Dashboard.

Belegter Live-Test:
- Vor dem Test im Dashboard:
  - `Nutzwert-Klicks`: 75
  - `Veranstalter-CTA`: 3
  - Eigenes Tracking war ausgeschlossen.
- Live geöffnet:
  - `/aktivitaeten/sichtbar-werden/`
- Nach dem Test im Dashboard:
  - `Nutzwert-Klicks`: 76
  - `Veranstalter-CTA`: 4
  - Eigenes Tracking war während des Nachweises aktiv.

Bewertung:
- Die neue Route `/aktivitaeten/sichtbar-werden/` wird live als Anbieter-/Veranstalter-CTA erfasst.
- Der Klick landet messbar im internen Nutzwert-/Mehrwert-Dashboard.
- Der Reporting-Hardening-Workpack ist technisch abgeschlossen.
- Der Test ist ein Funktionsbeweis, kein Akquise-Erfolgsbeleg.

Wichtige Abgrenzung:
- Eigene Testklicks dürfen nicht als organische Nutzersignale oder Anbietermehrwert verkauft werden.
- Nach dem Beweistest sollte eigenes Tracking wieder ausgeschlossen werden.
- Für echte Akquise-Aussagen bleibt ein längerer organischer Datenlauf nötig.

Offen nach diesem Nachweis:
- 28-Tage-/30-Tage-Auswertung organischer Nutzwertsignale.
- Spätere Bewertung, ob ein screenshot- oder mailfähiger Feedbackbericht belastbar ist.
- Keine weitere technische Reporting-Härtung aus diesem Befund nötig.
<!-- === END BLOCK: TEST_STATUS_REPORTING_HARDENING_LIVE_PROOF_2026_06_19 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_BATHING_WATER_GUARD_V2_SAFE_WRITEBACK_2026_06_26 | Zweck: dokumentiert den Wechsel vom Report-only-Proof zum sicheren Live-Statusdatei-Writeback fuer Badegewaesser; Umfang: Architektur, UI-Wirkung, Guard-Ergebnis, offene Grenzen === -->
## Badegewässer Guard V2 – Safe Writeback validiert (2026-06-26)

Status: Staging-Guard, Statusdatei, Deploy und Frontend-Override validiert.

Kontext:
- Guard V1/V1.1/V1.2 haben Quellenzugriff, Zukunftszeilen-Handling, Zwemwater-Konservativlogik und lokale Badeeignung geprüft.
- Der reine Report-only-Modus schützt zwar fachlich, ändert aber die Live-Seite nicht automatisch.
- V2 führt deshalb eine getrennte generierte Statusdatei ein.

Technische Zielarchitektur:
- `data/offers.json` bleibt redaktioneller Activity-/Highlight-Stammdatenbestand.
- `data/bathing_water_status.json` ist die automatisch generierte Badegewässer-Statusdatei.
- `scripts/check-bathing-water-status.py --write-data data/bathing_water_status.json` schreibt nur diese Statusdatei.
- Der Workflow `Bathing Water Guard V2` committet die Statusdatei nur bei echtem Diff.
- Das Frontend lädt `offers.json` plus `bathing_water_status.json`; die Statusdatei überschreibt nur `current_status` für bekannte Badegewässer-Highlights.
- Wenn die Statusdatei fehlt oder fehlerhaft ist, bleibt der konservative Fallback aus `offers.json` aktiv.

Sicherheitsregeln:
- Kein automatischer Writeback nach `data/offers.json`.
- `ok` darf nur aktivierend wirken, wenn der Guard `water_state=ok` und `local_suitability_state=ok` liefert.
- `watch`, `blocked` und `unknown` verhindern weiterhin Bade-Highlight, Bade-Boost und `Jetzt besonders`-Treffer.
- Negative lokale Hinweise werden als knapper Statuschip auf der Aktivitätenseite und als kompakter Hinweis im Detailpanel sichtbar, nicht als prominente Home-Warnung.

Initialer Status aus dem geprüften Guard-Lauf vom 2026-06-26:
- Aasee Bocholt: `watch` wegen lokaler Schlamm-/Geruch-/Ablagerungs-Hinweise trotz unauffälliger Wasserwerte.
- Hilgelo: `unknown` wegen gemischter, nicht sicher gescopter Zwemwater-Statussignale.
- Pröbstingsee Borken: `watch` wegen Messwertalter oberhalb Watch-Schwelle.
- Auesee Wesel: `watch`, weil Wasserwerte ok sind, aber positive lokale Badeeignung nicht belegt ist.

Erwartete Live-Wirkung:
- Home zeigt keine Badeempfehlung.
- Aasee zeigt auf der Aktivitätenkarte `Badehinweis prüfen`.
- Aasee-Detailpanel erklärt die lokale Eignungswarnung.
- `Jetzt besonders` enthält keine Badegewässer als positive Highlights, solange kein finaler Guard-Status `ok` vorliegt.

Offene Grenze:
- Neue Badegewässer-Aktivitäten müssen künftig bewusst im Guard ergänzt werden. Der Activity-Highlight-Audit warnt, wenn ein Badegewässer-Highlight keinen Eintrag in `data/bathing_water_status.json` hat.
<!-- === END BLOCK: TEST_STATUS_BATHING_WATER_GUARD_V2_SAFE_WRITEBACK_2026_06_26 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_BROWSER_SMOKE_REPORTING_POLISH_V2_2026_06_29 | Zweck: dokumentiert die zweite Reporting-Haertung nach Staging-Artefakt 3303; Umfang: erwartete 401 auf Einreichungsseite, strengere Bottom-Tabbar-Dynamik, keine App-Funktionsaenderung === -->
## P1 Browser-Smoke – Reporting-Polish V2 nach Staging-Artefakt 3303 (2026-06-29)

Status: Reporting-Haertung vorbereitet; keine App-Funktionsaenderung.

Befund aus `browser-smoke-staging-3303`:
- Ergebnis: `21/23 OK, 0 Fehler, 2 Warnungen`.
- Die Summary klassifiziert Warnungen korrekt als `WARNUNG`, nicht mehr als `FEHLER`.
- Verbliebene Warnungen:
  - erwarteter `401` auf `/events-veroeffentlichen/einreichen/` durch optionale Portal-Session-Pruefung ohne Login,
  - mobiler `App initialization failed: TypeError: Failed to fetch` waehrend Navigation/Browserkontext.

Bewertung:
- Kein Deploy-Blocker.
- Event-Einreichung, Bottom-Tabbar, Consent-Reappearing-Fix und alle Kernwege waren sichtbar OK.
- Der `401` auf der Einreichungsseite ist erwarteter Zugangszustand, weil ohne Login keine Portal-Session existiert.

Haertung:
- Erwartete `401`-Konsolenhinweise der Einreichungsseite werden als bekannte Zugangshinweise ignoriert, sofern der Seitencheck erfolgreich war.
- Die Bottom-Tabbar-Navigation prueft nun nach Tabwechsel nicht nur Container-Sichtbarkeit, sondern echte Event-/Activity-Karten.
- Ein bekannter Hintergrund-Fetch-Hinweis waehrend dieser Navigation wird nur dann nicht gewarnt, wenn der strengere Navigationscheck bestanden hat.

Zielzustand:
- Stable Baseline soll ohne fachlich erwartete Warnungen laufen.
- Echte neue Browser-Konsolenprobleme bleiben sichtbar.
- Harte Kernwegfehler bleiben weiterhin `FEHLER` und machen den Workflow rot.
<!-- === END BLOCK: TEST_STATUS_BROWSER_SMOKE_REPORTING_POLISH_V2_2026_06_29 === -->

<!-- === BEGIN BLOCK: TEST_STATUS_HOME_TODAY_WEATHER_CONTEXT_FINAL_2026_07_01 | Zweck: dokumentiert Abschluss des Home-Today-Wetter-/Copy-/Icon-Workpacks; Umfang: Produktentscheidung, Wetter-Cue, zentrale Iconlogik, Temperaturbanding, Smoke-Ergebnis === -->
## Home Today – Wetterkontext, Copy und Iconlogik finalisiert (2026-07-01)

Status: Staging-Workpack fachlich und visuell abgenommen; Dokumentation nach Abschluss ergänzt.

Produktentscheidung:
- Die Home-Seite wird als `Heute`-Seite geführt, nicht als `Jetzt`-Seite.
- Ziel der Seite sind wenige passende Vorschläge für den heutigen Tag.
- Die Wetteranzeige ist kein Wettermodul und kein Current-Weather-Widget, sondern ein kompakter Tageskontext für die Auswahl.
- Der sichtbare Wetter-Cue beschreibt die relevante Wetterlage knapp, z. B. `Warm und trocken`, `Warm, später Schauer`, `Regnerisch und kühl`, `Kühl und windig`.
- Keine erklärenden Formulierungen wie `Wetter mitgedacht`, `draußen passt`, `Heute gut für draußen` oder Temperatur-first-Texte wie `19 °C · ideal für draußen`.

Sichtbare Home-Copy:
- Hero bleibt `Heute rund um Bocholt`.
- Die Auswahlsektion nutzt `Unsere Auswahl` und den Status `3 Vorschläge`.
- Card-Auszeichnung wurde von `Top-Tipp` auf `Empfohlen` beruhigt.
- Die frühere Mischsprache aus `Ideen` und `Tipps` wurde zugunsten von `Vorschläge` vereinheitlicht.
- Der Aufbau auf Desktop und Mobile bleibt unverändert; es wurden keine Layout- oder CSS-Umbauten vorgenommen.

Wetter- und Temperaturmodell:
- Die Wetterlage kombiniert Niederschlag/Trockenheit, Temperaturband, Wind und Tagesverlauf.
- Temperatur-Banding wurde alltagstauglich entschärft:
  - `< 5 °C`: kalt
  - `5–11 °C`: kühl
  - `12–18 °C`: mild
  - `19–27 °C`: warm
  - `28–29 °C`: sehr warm
  - `ab 30 °C`: heiß
- Bei etwa 25 °C Tagesmaximum wird `Warm und trocken` angezeigt, nicht `Sehr warm und trocken`.
- `Sehr warm` soll erst greifen, wenn Schatten-/Wasser-/kurze-Wege-Signale stärker relevant werden; `Heiß` ab 30 °C.

Zentrale Iconlogik:
- Wettericons werden nicht lokal in `today-home.js` als SVG-Sonderfall gepflegt.
- `weather-context.js` liefert Label und semantischen Icon-Token.
- `js/icons.js` bleibt die zentrale Quelle für Icon-SVGs und semantische Aliase.
- Home rendert nur noch das Wetterprofil, z. B. `summaryLabel` plus `summaryIcon`.
- Die Wetter-Pill nutzt ein wetterspezifisches Icon statt `sparkles`; `sparkles` bleibt damit frei für Highlight-/Empfehlungssemantik und wird nicht für Wetter missbraucht.

Auswahllogik und Rotation:
- Die sichtbare Wetterlage muss dieselben Signale repräsentieren, die auch in die Auswahl-/Scoringlogik einfließen.
- Bei warm/trocken sind Outdoor- und saisonale Vorschläge plausibel; bei sehr warm/heiß sollen Wasser, Schatten, kurze Wege oder wetterunabhängige Ziele stärker berücksichtigt werden.
- Lokale Today-Impression-/Preference-Daten dürfen normale Browserzustände beeinflussen, damit Aktivitäten über Tage nicht immer identisch erscheinen.
- Die frische Basis-Auswahl ist deterministisch genug: Mobile und Desktop-Inkognito zeigten dieselbe Auswahl; abweichende Desktop-Normalansicht wurde auf lokalen Browserzustand zurückgeführt.

Validierter Smoke:
- Mobile nach Patch: `Warm und trocken` mit Sonne/Wolken-Wettericon sichtbar.
- Desktop-Inkognito: gleiche Basis-Auswahl wie Mobile sichtbar.
- Desktop normal: mögliche Abweichung durch lokale Impression-/Preference-Historie verstanden und nicht als Layout-/Deploy-Fehler bewertet.
- Keine alten Texte wie `Top-Tipp`, `3 Tipps`, `Wetter mitgedacht`, `draußen passt` oder `Sehr warm und trocken` bei ca. 25 °C sichtbar.
- JS-Syntaxchecks der funktionalen Patchpakete waren grün.

Abschlussbewertung:
- Workpack ist abgeschlossen.
- Keine weitere Änderung an Position, Aufbau, Mobile/Desktop-Layout oder Home-Hero nötig.
- Falls künftig vollständige geräteübergreifende Identität aller drei Vorschläge gewünscht ist, wäre das ein separates Produktziel und nicht Teil dieses abgeschlossenen Workpacks.
<!-- === END BLOCK: TEST_STATUS_HOME_TODAY_WEATHER_CONTEXT_FINAL_2026_07_01 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_ACTIVITY_MOBILE_FILTER_RAIL_CONTROLLED_2026_07_01 | Zweck: dokumentiert den finalen Zielzustand fuer die kontrollierte mobile Schnellfilter-Rail auf Aktivitaeten; Umfang: Status und Abnahmekriterien === -->
## Aktivitaeten Mobile Schnellfilter-Rail – kontrollierter Premium-Zielzustand (2026-07-01)

Status:
- Umsetzungspatch eingebracht und per mobiler Staging-Sichtpruefung abgenommen.

Zielzustand:
- Mobile Schnellfilter bleiben eine horizontale Chip-Rail.
- Desktop bleibt unveraendert.
- Im ungefilterten Startzustand beginnt die Rail immer links.
- Wenn `Jetzt besonders` verfuegbar ist, ist dieser Chip initial der erste sichtbare Chip.
- Alte Scrollpositionen, bfcache oder automatisches Zentrieren duerfen den Startzustand nicht verschieben.
- Aktive Schnellfilter werden nur bei echter Auswahl sichtbar gehalten.
- Fades zeigen reale Scrollkanten, nicht einen statischen harten Anschnitt.

Abnahme:
- `/aktivitaeten/` mobil zeigt initial links startend: `Jetzt besonders`, `Mit Kindern`, `Bei Regen`. Weitere Chips sind per horizontalem Wischen erreichbar.
- Seitliches Wischen zeigt die weiteren Schnellfilter.
- Browser-Smoke Mobile Schnellfilter Rail bleibt massgeblicher automatischer Guard fuer Regressionen.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_MOBILE_FILTER_RAIL_CONTROLLED_2026_07_01 === -->


<!-- === BEGIN BLOCK: TEST_STATUS_EVENT_FEED_VISUAL_DIVERSITY_2026_07_02 | Zweck: dokumentiert den aktuellen Patch gegen sichtbare Event-Bildwiederholungen im Feed; Umfang: Ursache, technische Loesung, Grenzen === -->
## Event-Feed Visual Diversity – Duplikatpraevention im sichtbaren Feed (2026-07-02)

Status:
- Patch gegen den aktuellen Staging-ZIP-Stand `Bocholt-Erleben-staging (83)` neu aufgebaut.
- Der vorherige Patch darf nicht unveraendert eingespielt werden, weil er aeltere Vollversionen von `today-home.js`, `VISUAL_WORKFLOW.md` und `TEST_STATUS.md` enthielt und aktuelle Home-/Activity-Dokumentation ueberschrieben haette.

Ursache:
- Der Event-Visual-Resolver war zu hart motivexakt.
- Sobald ein einziges exaktes `visual_motif`-Bild existierte, wurden neutrale/fallbackfaehige Bilder derselben Bildfamilie ausgeschlossen.
- Bei mehreren benachbarten Kulturtage-/Open-Air- oder Live-Musik-Karten konnte die Anti-Duplikat-Logik deshalb nicht wirksam ausweichen.

Technische Loesung:
- `/events/` und Today/Home nutzen weiterhin exakte Motivbilder bevorzugt.
- Exakte Motivbilder bleiben erst ab drei Ready-Varianten exklusiv.
- Bei nur ein bis zwei exakten Motivbildern werden neutrale/fallbackfaehige Bilder desselben `visual_key` ergaenzt.
- Fuer `live_music_stage` sind definierte nahe Buehnen-/Konzertmotive als letzte Diversity-Stufe zugelassen.
- Der neue Audit `scripts/audit-event-feed-visual-diversity.py` simuliert die Feed-Auswahl und meldet sichtbare Bildwiederholungen, niedrige Motivdiversitaet und moegliche Seriencluster.

Grenze:
- Dieser Patch behebt den sichtbaren Bild-Duplikat-Effekt technisch.
- Der spaetere Premium-Fix fuer echte Mehrtages-/Dachveranstaltungen bleibt ein eigenes Datenmodellthema: `event_group_id`, `group_title`, `group_role`, `occurrence_title`, `display_mode`.
<!-- === END BLOCK: TEST_STATUS_EVENT_FEED_VISUAL_DIVERSITY_2026_07_02 === -->
