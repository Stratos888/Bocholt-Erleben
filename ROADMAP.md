# ROADMAP — BOCHOLT ERLEBEN

<!-- === BEGIN CANONICAL ROADMAP FILE: Tactical prioritized backlog only === -->

## Rolle dieses Dokuments

Dieses Dokument hält die priorisierten nächsten Arbeitsblöcke fest.

Es ist keine Produktdefinition und keine Engineering-Regeldatei.

Kanonische Rollen:

- `Produktvertrag.md` = Produktlogik, Preise, Modelle, Kontingente
- `MASTER.md` = strategische Projektsteuerung
- `ROADMAP.md` = taktische To-do-Liste und Reihenfolge der nächsten Workpacks
- `ENGINEERING.md` = harte Umsetzungsregeln und Arbeitsmodi
- `TEST_STATUS.md` = geprüfte Funktionsstände und offene Regressionstests

---

## Ausgangslage: Live-Messbasis

Stand: 2026-05-26, Live-Dashboard-Screenshot.

Belegt ist:

- Live misst bereits echte SEO- und Nutzwert-Kennzahlen.
- Status steht auf `Gelb`, nicht mehr auf `nicht konfiguriert`.
- Eigenes Tracking ist im Dashboard ausgeschlossen.
- Aktueller 28-Tage-Zeitraum zeigt unter anderem:
  - Sichtbarkeit: `7.049`
  - Such-Klicks: `264`
  - Nutzwert-Klicks: `33`
  - Detail-Interesse: `87`
  - Website-Klicks: `22`
  - Route/Maps: `5`
  - Veranstalter-CTA: `6`
  - performende Events: `28`
  - performende Ziele: `16`
  - Location-Links: `0`

Bewertung:

- `Messdaten aktivieren` ist nicht mehr der richtige To-do-Titel.
- Der nächste Reifegrad ist `Messdaten nutzbar machen`.
- Gesamtzahlen reichen für die interne Ampel.
- Für Veranstalter-Akquise braucht es zusätzlich auswertbare Anbieter-, Event-, Aktivitäts- und Location-Bezüge.
- Für `Grün` fehlen laut Dashboard-Hinweis vor allem stärkere automatisch gemessene Website-, Maps- oder Location-Klicks.

Zusatzbeleg 2026-05-27:

- Reporting-Ziel-Zuordnung ist live technisch bewiesen.
- `Anholter Schweiz erleben` sendet `reporting_target_type`, `reporting_target_id` und `reporting_target_title` im `value-track.php`-Payload.
- Das interne Dashboard zeigt das explizite Ziel `Biotopwildpark Anholter Schweiz` getrennt von `nicht zugeordnet`.
- Belegter Live-Wert nach Test: `2` Interaktionen gesamt, davon `1` Detail-Aufruf und `1` Website-Klick.
- Eigenes Tracking wurde nach dem Beweistest wieder ausgeschlossen.

---

## P0 — Monetarisierungs- und Reporting-Readiness

Diese Punkte kommen vor weiterer breiter UI-Polish-Arbeit.

### 1. Live-Zahlungsfall bewusst vollständig testen

<!-- === BEGIN BLOCK: ROADMAP_LIVE_SINGLE_EVENT_PAYMENT_PROOF_2026_05_27 | Zweck: dokumentiert erledigten P0-Live-Zahlungsbeweis für Einzeltermin; Umfang: Review-first, Stripe-Zahlung, Veröffentlichung, Rücknahme/Cleanup === -->

Status 2026-05-27:

- Live-Zahlungsfall für Einzeltermin wurde vollständig praktisch bewiesen.
- Getesteter Fall: `Bocholt erleben – Info-Nachmittag für Veranstalter`.
- Preis/Betrag im Stripe-Checkout: `9,90 €`.
- Review-first-Flow bestätigt:
  - Einreichung erzeugt noch keinen direkten Checkout.
  - Bestätigungsmail nach Einreichung kommt an.
  - Einreichung erscheint in der Review-Inbox.
  - Zahlung wird erst nach redaktioneller Vorprüfung freigegeben.
  - Zahlungslink-Mail kommt an.
- Stripe-Live-Zahlung erfolgreich abgeschlossen.
- Erfolgsseite nach Zahlung korrekt angezeigt.
- Inbox-Status nach Zahlung: bezahlt / veröffentlichungsbereit.
- Finale Veröffentlichung aus der Inbox erfolgreich.
- Veröffentlichungsmail kommt an.
- Event war anschließend im öffentlichen Eventbereich sichtbar.
- Cleanup ohne manuelle DB-Korrektur bestanden:
  - Veranstalteränderung an bereits veröffentlichtem Zukunftstermin setzt den Fall zurück in redaktionelle Prüfung.
  - Öffentliche Sichtbarkeit wird dadurch entfernt.
  - Eintrag erscheint wieder in der Review-Inbox.
  - Ablehnung/Abschluss aus der Inbox funktioniert.
  - Ablehnungsmail kommt an.
- Der Testtermin wurde nach dem Test wieder aus der öffentlichen Sichtbarkeit entfernt und abgeschlossen.

Bewertung:

- P0 `Live-Zahlungsfall bewusst vollständig testen` ist für den Einzeltermin-Funnel erledigt.
- Kein Code-Patch und keine direkte DB-Korrektur waren nötig.
- Für breite Akquise ist der bezahlte Einzeltermin-Kernfluss praktisch belastbar.
- Mitgliedschafts-/Abo-Live-Test bleibt ein separater Testfall, weil dort Abo-, Billing-Portal- und Periodenlogik zusätzlich betroffen sind.

<!-- === END BLOCK: ROADMAP_LIVE_SINGLE_EVENT_PAYMENT_PROOF_2026_05_27 === -->

Ziel:

- erster echter Live-Zahlungsfall für Event- oder Aktivitätsprodukt
- Stripe-Webhook nach echter Zahlung bewiesen
- Erfolgsseite nach echter Zahlung bewiesen
- Anbieterbereich/Dashboard-Status nach echter Zahlung bewiesen
- finale Veröffentlichung nach echter Zahlung bewiesen

Warum:

- Staging-E2E und Live-Smoke ersetzen keinen echten Produktiv-Zahlungsbeweis.
- Vor aktiver Akquise darf der bezahlte Kernfluss nicht nur theoretisch plausibel sein.

Akzeptanzkriterien:

- Testfall ist in `TEST_STATUS.md` dokumentiert.
- Keine manuelle DB-Korrektur ist nötig.
- Veranstalter-Sicht, Kurator-Sicht und öffentliche Veröffentlichung sind einmal durchgehend belegt.

### 2. Item- und Anbieter-Zuordnung für Nutzwertdaten prüfen und härten

Status 2026-05-27:

- Erster Live-Beweis abgeschlossen.
- Technische Kette ist für ein explizites Activity-Reporting-Ziel belegt:
  - `data/offers.json`
  - `js/offers-main.js`
  - Frontend-Tracking-Payload
  - `api/value-track.php`
  - `value_metric_daily`
  - internes SEO-/Mehrwert-Dashboard
- Aktuell explizit zugeordnet: `Biotopwildpark Anholter Schweiz`.
- Alle anderen nicht explizit zugeordneten Nutzwerte bleiben bewusst unter `nicht zugeordnet`.
- Der nächste Schritt ist nicht erneutes Tracking-Grundgerüst, sondern gezielte Erweiterung weiterer sauber belegbarer Reporting-Ziele und danach der erste Feedbackbericht.

Ziel:

- Detail-, Website-, Maps-, Ticket-, Location- und Veranstalter-CTA-Klicks sind pro Event/Aktivität eindeutig auswertbar.
- Events und Aktivitäten können einem Anbieter, einer Location oder einem Reporting-Ziel zugeordnet werden.
- Der Sonderfall `Anholter Schweiz` kann als erster realer Feedback-Report ausgewertet werden.

Warum:

- Gesamtzahlen sind gut für die interne Ampel.
- Veranstalter zahlen eher, wenn sie ihre eigenen Zahlen sehen.

Akzeptanzkriterien:

- Für ein konkretes Ziel ist abrufbar:
  - zugeordnete Events/Aktivitäten
  - Detail-Interesse
  - Website-/Ticket-Klicks
  - Route/Maps-Klicks
  - Zeitraum
  - Vergleich zum vorherigen Zeitraum, wenn belastbar vorhanden
- Eigene Testklicks bleiben ausgeschlossen.
- Unklare Zuordnungen werden nicht geraten, sondern als `nicht zugeordnet` sichtbar gemacht.

<!-- === BEGIN BLOCK: ROADMAP_REPORTING_TARGET_DATA_WAIT_2026_05_27 | Zweck: hält den Live-Beweis und den Datenbasis-Wartepunkt für Reporting-Ziele fest; Umfang: Akquise-Readiness, 28-/30-Tage-Datenlauf, nächster Feedbackbericht === -->

### Status 2026-05-27 — Reporting-Ziele live bewiesen, Datenbasis läuft

Belegt:

- Explizite Reporting-Ziel-Zuordnung funktioniert live.
- Erster Einzelbeweis: `Anholter Schweiz erleben` wird dem Ziel `Biotopwildpark Anholter Schweiz` zugeordnet.
- Erweiterungsbeweis: zusätzlich ergänzte Ziele aus `data/offers.json` funktionieren ebenfalls live; geprüftes Beispiel: `Aasee erleben` mit Ziel `Aasee Bocholt`.
- `value-track.php` erhält bei geprüften Activity-Requests korrekt:
  - `reporting_target_type`
  - `reporting_target_id`
  - `reporting_target_title`
- Das SEO-/Mehrwert-Dashboard trennt explizit zugeordnete Ziele von `nicht zugeordnet`.

Bewertung:

- Der technische Roadmap-Punkt `Item- und Anbieter-Zuordnung für Nutzwertdaten prüfen und härten` ist für Activities grundsätzlich bewiesen.
- Testklicks dienen nur als Funktionsbeweis und sind kein Akquise-Beleg.
- Für einen belastbaren Feedbackbericht werden jetzt organische Daten gesammelt.

Nächste Datenprüfungen:

- Kurzcheck nach ca. 7 Tagen: prüfen, ob erste echte Zielsignale plausibel einlaufen.
- Hauptcheck nach ca. 30 Tagen bzw. nach einem vollständigen 28-Tage-Zeitraum: bewerten, ob ein erster Akquise-/Feedbackbericht belastbar erstellt werden kann.
- Automatische Erinnerung ist für den 26.06.2026 angelegt.

Bis dahin:

- Kein großer Feedbackbericht auf Basis von Testklicks.
- Weitere Reporting-Ziele nur gezielt ergänzen, wenn die Zuordnung fachlich eindeutig ist.
- Nächster aktiver P0-Block kann unabhängig davon der echte Live-Zahlungsfall sein.

<!-- === END BLOCK: ROADMAP_REPORTING_TARGET_DATA_WAIT_2026_05_27 === -->

### 3. Ersten Veranstalter-/Location-Feedbackbericht bauen

Status 2026-05-28:

- Interner Location-Feedbackbericht ist im SEO-/Mehrwert-Dashboard auf Staging eingebaut und geprüft.
- Einbauort: `intern/seo-dashboard/`.
- Der Bericht nutzt die vorhandenen Reporting-Ziele aus `data/offers.json` und die Messwerte aus `value_metric_daily`.
- Sichtbar sind pro Reporting-Ziel:
  - Interaktionen gesamt
  - Detail-Aufrufe
  - Website-Klicks
  - Route/Maps-Klicks
  - Zeitraum
  - vorsichtige Einordnung bei fehlenden Signalen
- Der frühere obere Akquise-Snapshot ist jetzt als `Akquise-Gesamtstatus` einklappbar; die Status-Chips bleiben direkt sichtbar.
- Bewertung: technische Grundlage für Feedbackbericht und Akquise-Screenshot ist vorbereitet.
- Erster kleiner Live-Proof liegt für `Biotopwildpark Anholter Schweiz` vor; als belastbaren Akquise-Erfolgsnachweis erst nach längerem Zeitraum mit stabileren Nutzwert-Signalen verwenden.

Ziel:

- screenshot- oder mailfähiger Bericht für einzelne Anbieter/Locations.
- Erste Zielperson: Betriebsleitung Anholter Schweiz.

Warum:

- Das ist der direkte Übergang von internem Dashboard zu verkaufbarem Mehrwert.
- Ein konkreter Bericht ist wertvoller als abstrakte KPI-Kacheln.

Akzeptanzkriterien:

- Bericht erklärt ohne Fachbegriffe:
  - Sichtbarkeit
  - Detail-Interesse
  - konkrete Website-/Ticket-/Maps-Klicks
  - Zeitraum
  - was die Zahlen bedeuten
  - welche Zahlen noch vorsichtig zu interpretieren sind
- Keine überzogenen Claims ohne Datenbasis.
- Export/Screenshot funktioniert mobil und desktop.

### 4. Kritische Deploy-Smoke-Tests automatisieren

Status 2026-05-27:

- Umgesetzt und für Staging sowie Live/Main praktisch bewiesen.
- Workflow-Schritt `Smoke-check deployed site` läuft nach dem STRATO-SFTP-Upload.
- Staging-Proof: Commit `9f5b8a6` (`Add deploy smoke checks`).
- Live-/Main-Proof: Build `2b7f6daecf4c`.
- Der Proof ist in `TEST_STATUS.md` dokumentiert.

Ziel:

- Nach Deploys werden Kernpfade automatisch geprüft.

Mindestumfang:

- Homepage lädt.
- Events laden.
- Aktivitäten laden.
- `/api/status.php` liefert `ok`.
- `/api/events/public.php` liefert kontrolliertes JSON.
- Checkout-Endpoint liefert bei leerem Body kontrolliert `422`, keinen leeren `500`.
- Inbox-/Review-Endpunkte bleiben geschützt.

Warum:

- Das Projekt ist groß genug, dass manuelle Sichtprüfung allein zu riskant wird.

Akzeptanzkriterien:

- Smoke-Test-Ergebnis ist im Deploy oder als separater Check sichtbar.
- Fehler blockieren den Rollout oder werden bewusst als Nicht-Blocker dokumentiert.

### 5. Review-/Push-Flows gegen stille Ausfälle prüfen

Status 2026-05-27:

- Technischer Zugriffsschutzteil ist in den Deploy-Smoke-Check integriert und für Staging sowie Live/Main bewiesen.
- Review-Liste und Push-Endpunkte werden nach Deploys automatisch gegen versehentliche öffentliche Öffnung geprüft.
- Einzeltermin-Flow ist durch den Live-E2E-Test `TEST_STATUS_LIVE_SINGLE_EVENT_PAYMENT_2026_05_27` inklusive Review-Inbox-Sichtbarkeit bewiesen.
- Activity-Presence-Flow ist durch die Live-Readiness-Prüfung `TEST_STATUS_ACTIVITY_PRESENCE_LIVE_READINESS_2026_05_26` inklusive Live-Inbox-Sichtbarkeit bewiesen.
- Event-Submissions aus aktiver Mitgliedschaft sind durch die dokumentierten Membership-Reuse-Tests inklusive `review-list.php`- und `/inbox/`-Sichtbarkeit bewiesen.
- Neue Mitgliedschafts-Checkouts sind bewusst kein Review-Inbox-Fall, sondern starten den Abo-/Zahlungsflow.
- Tatsächlicher Push-Versand bleibt optional und ist kein P0-Blocker, solange die Review-Inbox zuverlässig ist.

Ziel:

- Neue Einreichungen erzeugen zuverlässig Review-Arbeit und interne Hinweise.

Warum:

- Still liegenbleibende Einreichungen wären für zahlende Veranstalter besonders schädlich.

Akzeptanzkriterien:

- Einzeltermin-, Activity-Presence- und Event-Submissions aus aktiver Mitgliedschaft erscheinen zuverlässig in der Review-Inbox.
- Neue Mitgliedschafts-Checkouts sind bewusst kein Review-Inbox-Fall, sondern starten den Abo-/Zahlungsflow.
- Push ist best-effort; wenn Push nicht ausgelöst wird, muss die Review-Inbox trotzdem korrekt sein.

---

## P0.5 — Inhaltsqualität, Prüfläufe und kontrolliertes Self-Healing

<!-- === BEGIN BLOCK: ROADMAP_CONTENT_QUALITY_SELF_HEALING_V1_2026_05_31 | Zweck: nimmt regelmäßige Live-Content-Prüfung, Warnlogik und sichere automatische Korrekturen als späteren Workstream auf; Umfang: Events aus Google Sheet/DB, Activities aus offers.json, Audit-Reports, Auto-Fix-Grenzen === -->

Status 2026-05-31:

- Der generierte Event-Feed `data/events.json` wurde aus dem Repository entfernt.
- Fachliche Event-Quelle bleibt das Google Sheet; zugelassene Veranstalter-Einreichungen kommen zusätzlich aus der DB/API.
- `data/events.json` bleibt nur ein generiertes Deploy-/Runtime-Artefakt, weil Frontend, SEO-Schema und Service Worker den Feed zur Laufzeit laden.
- Dadurch ist die bisherige Repo-Verwirrung durch stale Event-JSON reduziert.
- Der nächste Qualitätsbedarf liegt nicht bei weiterer Discovery allein, sondern bei regelmäßiger Prüfung bereits live sichtbarer Inhalte.

Ziel:

- Live sichtbare Events und Aktivitäten werden regelmäßig auf technische und fachliche Auffälligkeiten geprüft.
- Der Prüflauf unterscheidet klar zwischen sicher automatisch korrigierbaren Fällen und Fällen, die eine Warnung bzw. redaktionelle Prüfung brauchen.
- Automatische Korrekturen dürfen nur erfolgen, wenn die Änderung deterministisch belegbar und regressionsarm ist.

Warum:

- Bocholt erleben lebt vom Vertrauen in korrekte lokale Informationen.
- Neue Inhalte allein reichen nicht, wenn bestehende Events, Aktivitäten, Links, Bilder oder Quellinformationen veralten.
- Manuelle Vollkontrolle aller Live-Inhalte ist langfristig nicht realistisch.
- Blinde Auto-Korrekturen wären aber riskanter als ein fehlender Prüflauf.

Scope für den späteren Workstream:

- Events aus dem live generierten Sheet-Feed.
- Genehmigte öffentliche DB-Events aus `/api/events/public.php`.
- Aktivitäten aus `data/offers.json`.
- Quellen-URLs, Event-URLs, Website-Links, Maps-Ziele und Bild-URLs.
- Prüfbericht für interne Nutzung.
- Optional später: Audit-Status im internen Dashboard.

Nicht im Scope der ersten Umsetzung:

- Kein automatisches Umschreiben freier Beschreibungstexte.
- Kein automatisches Löschen von Events nur wegen temporär nicht erreichbarer Quelle.
- Kein blindes Überschreiben des Google Sheets durch unsichere KI-Auswertung.
- Kein Anbieterbericht oder öffentliche Qualitätsanzeige.

Empfohlener Prüfrhythmus:

- Events, leichtgewichtig: täglich, Fokus auf die nächsten 14 Tage.
- Events, tiefer Quellencheck: wöchentlich für den relevanten Zukunftsbestand.
- Aktivitäten, technischer Check: wöchentlich.
- Aktivitäten, tiefer Plausibilitäts-/Quellencheck: monatlich.

Sichere Auto-Fix-Kandidaten ohne KI:

- abgelaufene Events nicht mehr ausspielen bzw. beim Feed-Build ausschließen.
- kaputte Bild-URLs durch vorhandene Fallback-Logik abfangen.
- fehlende Pflichtfelder blockieren oder in Review markieren.
- harte Duplikate nach stabiler ID oder eindeutigem Schlüssel unterdrücken.
- permanente Redirects protokollieren und später kontrolliert übernehmen.
- strukturierte Quelldaten übernehmen, wenn JSON-LD, ICS oder API-Daten eindeutig demselben Event zugeordnet werden können.

Nicht sicher ohne KI oder spezialisierten Quellenparser:

- geänderte Uhrzeiten aus freiem Website-Text erkennen.
- verschobene oder abgesagte Events sicher interpretieren.
- subtile Ortsänderungen erkennen.
- mehrere Events auf einer Sammelseite korrekt zuordnen.
- veraltete Activity-Beschreibungen semantisch bewerten.

Benachrichtigungs- und Reaktionslogik:

- `auto_fixed`: keine Sofortwarnung; Aufnahme in Tages-/Wochenprotokoll.
- `warning`: im internen Dashboard oder Audit-Report sichtbar machen.
- `review_needed`: interne Warnung erzeugen; nicht automatisch öffentlich ändern.
- `critical`: Push/E-Mail oder deutlich sichtbarer interner Hinweis.
- `source_unreachable`: protokollieren; erst nach Wiederholung oder besonderer Kritikalität eskalieren.

Mögliche technische Bausteine:

- neuer Workflow `content-quality-audit.yml`.
- neues Script `scripts/content-quality-audit.py` für harte technische Checks.
- später optional `scripts/content-source-audit.py` für Quellenvergleich.
- Ergebnisdatei oder Sheet-Tab `Content_Audit`.
- Google-Sheets-Schreibrechte nur für einen eng begrenzten späteren Auto-Fix-Workflow, nicht für den normalen Deploy.

Akzeptanzkriterien für Phase 1:

- Der Audit läuft unabhängig vom normalen Deploy.
- Der Audit prüft den echten Live-/Runtime-Bestand, nicht eine stale Repo-Kopie.
- Der Audit erzeugt einen verständlichen Bericht mit Severity, betroffener Quelle und empfohlener Aktion.
- Harte technische Fehler sind klar von fachlichen Unsicherheiten getrennt.
- Kein unsicherer Befund verändert automatisch öffentliche Inhalte.

Akzeptanzkriterien für spätere Auto-Fix-Phase:

- Jede Auto-Korrektur ist nachvollziehbar protokolliert.
- Auto-Fixes sind auf deterministische Fälle begrenzt.
- Für Google-Sheet-Änderungen gibt es Schreibschutzlogik, Audit-Log und möglichst eine Rücknahmeoption.
- KI darf nur als Assistenz für semantische Prüfung eingesetzt werden, nicht als alleiniger Grund für blindes Überschreiben.
- Bei widersprüchlichen Quellen gewinnt nicht automatisch die externe Quelle; der Fall geht in Review.

Prioritätseinordnung:

- Nicht vor den aktuell laufenden Messwert-/Akquise-Wartepunkten erzwingen.
- Sinnvoller Anschluss nach stabiler Nutzwertmessung und bevor größere Content-Mengen weiter ausgebaut werden.
- Phase 1 kann klein und technisch beginnen, ohne die Produktoberfläche zu verändern.

<!-- === END BLOCK: ROADMAP_CONTENT_QUALITY_SELF_HEALING_V1_2026_05_31 === -->

---

## P1 — Veranstalter-Nutzwert sichtbar machen

### 6. Anbieterbereich vom Verwaltungsbereich zum Wertzentrum ausbauen

Ziel:

- Anbieter sehen nicht nur Status und Tarif, sondern den Nutzen ihrer veröffentlichten Inhalte.

Mögliche erste Kennzahlen:

- veröffentlichte Veranstaltungen/Aktivitäten
- Detail-Interesse
- Website-/Ticket-Klicks
- Route/Maps-Klicks
- Zeitraum und einfacher Vergleich

Akzeptanzkriterien:

- Anbieter versteht ohne KPI-Wissen, was passiert ist.
- Zahlen werden nur gezeigt, wenn sie dem Anbieter sauber zuordenbar sind.
- Unvollständige Daten werden transparent gekennzeichnet.

### 7. Veranstalter-Funnel stärker auf belegbaren Mehrwert ausrichten

<!-- === BEGIN BLOCK: ROADMAP_PUBLISH_EXPLAINER_FREEZE_2026_05_29 | Zweck: dokumentiert die Umsetzung des P1-Punkts zur stärkeren Mehrwert-Erklärung im Veranstalter-/Anbieter-Funnel; Umfang: zentrale Erklärseite, Kontextlinks, Redundanz- und Funnel-Abgrenzung === -->

Status 2026-05-29:

- Umgesetzt und auf Staging geprüft.
- Neue zentrale Erklärseite: `/veroeffentlichung-erklaert/`.
- Bestehende Funnel-Seiten bleiben kurz und handlungsorientiert:
  - `/events-veroeffentlichen/`
  - `/fuer-veranstalter/`
  - `/angebote/sichtbar-werden/`
- Die bestehenden Seiten wurden nur minimal-invasiv um kontextuelle Hilfelinks ergänzt.
- Die neue Erklärseite bündelt:
  - Veröffentlichungswege
  - Prüfung und Freigabe
  - Zahlung und veröffentlichte Termine
  - Veranstaltung vs. Aktivitätspräsenz
  - Fairness ohne gekaufte Hervorhebung
  - vorsichtige Einordnung messbarer Interaktionen
- Ankerziele, FAQ-Öffnung, Scroll-Offset und Link-Hierarchie wurden nach Staging-Prüfung nachgeschärft.
- Ergebnis: Workpack ist für den aktuellen Spitzenstand gefreezt.
- Details und getestete Grenzen sind in `TEST_STATUS.md` dokumentiert.

Bewertung:

- P1 Punkt 7 ist für den aktuellen Stand erledigt.
- Kein weiterer Copy-/UI-Ausbau an den Funnel-Seiten nötig.
- Keine Formular-, Checkout-, Stripe-, Review- oder Dashboard-Logik wurde verändert.
- Nächster fachlicher Anschluss bleibt der messbare Nutzennachweis über Bericht/Reporting, nicht weiterer Erklärtext.

<!-- === END BLOCK: ROADMAP_PUBLISH_EXPLAINER_FREEZE_2026_05_29 === -->

Ziel:

- `/events-veroeffentlichen/`, `/fuer-veranstalter/` und `/angebote/sichtbar-werden/` erklären klarer, welchen konkreten Nutzen Anbieter bekommen.

Warum:

- Der aktuelle Funnel ist logisch und fair, aber noch nicht maximal verkaufsstark.

Akzeptanzkriterien:

- Kommunikation bleibt seriös und lokal.
- Keine Zahlen verwenden, die nicht belegbar sind.
- Mehrwert wird als Dienstleistung erklärt: Prüfung, Aufbereitung, Sichtbarkeit, messbare Interaktionen.

### 8. Monatlichen Wertbericht vorbereiten

<!-- === BEGIN BLOCK: ROADMAP_MONTHLY_VALUE_REPORT_PREPARED_2026_05_29 | Zweck: korrigiert Punkt 8 als vorbereiteten Reporting-/Retention-Baustein; Umfang: Status, Wartepunkt, Abgrenzung zur späteren Automatisierung === -->

Status 2026-05-29:

- Punkt 8 ist in der aktuell sinnvollen Form vorbereitet und kein aktiver Bau-Block mehr.
- Der interne `Location-Feedbackbericht` ist im SEO-/Mehrwert-Dashboard `/intern/seo-dashboard/` eingebaut und auf Staging geprüft.
- Der Bericht ist screenshot- und gesprächsfähig für interne Akquise-/Proof-Vorbereitung.
- Belastbare Anbieter-/Location-Aussagen brauchen weiter echte organische Daten über einen längeren Zeitraum.

Wartepunkt:

- Kurzcheck nach ca. 7 Tagen: prüfen, ob erste echte Zielsignale plausibel einlaufen.
- Hauptcheck nach ca. 30 Tagen bzw. nach einem vollständigen 28-Tage-Zeitraum: prüfen, ob ein erster Akquise-/Feedbackbericht belastbar ist.

Nicht jetzt bauen:

- keine automatische Monatsmail
- kein PDF-/Export-System
- kein neuer Anbieter-Self-Service-Bericht
- keine neue Datenbanktabelle nur für Reporting

Nächster aktiver Anschluss:

- Da Punkt 8 vorbereitet ist und die Datenbasis nun laufen muss, ist der nächste aktive Produkt-Workpack Punkt 9: `Heute in Bocholt` als Discovery-Einstieg.

<!-- === END BLOCK: ROADMAP_MONTHLY_VALUE_REPORT_PREPARED_2026_05_29 === -->

---

## P2 — Nutzerprodukt weiter Richtung Discovery-Portal ausbauen

### 9. `Für dich in Bocholt` – intelligente Premium-Home

<!-- === BEGIN BLOCK: ROADMAP_FOR_YOU_BOCHOLT_PREMIUM_HOME_2026_05_29 | Zweck: ersetzt den einfachen Heute-/Discovery-Workpack durch einen Premium-Produktvertrag für eine intelligente Startseite; Umfang: Home-Architektur, Empfehlungsmodell, lokale Personalisierung, Wetter, Datenvorbereitung, Abgrenzungen === -->

Status 2026-05-29:

- Der bisherige Ansatz `Heute in Bocholt` als reiner Discovery-/Filter-Workpack ist zu klein und teilweise redundant zum bestehenden Eventfeed.
- Die aktuelle mobile Eventansicht enthält bereits Suche, Zeitfilter, Kategorien sowie Gruppen wie `Heute` und `Dieses Wochenende`.
- Ein zusätzlicher Heute-/Wochenende-Block wäre daher kein Premium-Mehrwert.
- Der neue Zielzustand ist eine echte intelligente Home: `Für dich in Bocholt`.

Produktentscheidung:

- `/` wird perspektivisch zur kompakten Entscheidungsseite `Für dich in Bocholt`.
- Die bestehende Eventlogik bleibt als eigene Such-/Durchblätterseite erhalten.
- Die bestehende Aktivitätenseite bleibt als eigene Such-/Durchblätterseite erhalten.
- Die Home beantwortet nicht primär `Welche Termine gibt es?`, sondern `Was passt heute oder am Wochenende für mich in Bocholt?`

Zielbild:

- Die Startseite kombiniert Events und Aktivitäten in einem gemeinsamen Empfehlungsfeed.
- Nutzer erhalten schnell konkrete Vorschläge für:
  - heute
  - heute Abend
  - Wochenende
  - Familie / mit Kindern
  - draußen
  - bei Regen
  - spontan
- Die ersten sichtbaren mobilen Karten sind die wichtigste Produktfläche.
- Es wird keine große zusätzliche Modulfläche oberhalb der Inhalte aufgebaut.
- Die Home soll wie ein lokaler Entscheidungsassistent wirken, nicht wie ein weiterer Kalender.

Neue Seitenrollen:

- `/` = `Für dich in Bocholt`: kompakter Empfehlungsfeed aus Events + Aktivitäten.
- Eventseite = vollständige Termin-Suche, Filterung und chronologisches Stöbern.
- Aktivitätenseite = dauerhafte Orte, Ideen und Ausflugsziele suchen und filtern.
- Veranstalterbereiche bleiben getrennte Anbieterpfade.

Bottom-Navigation-Ziel:

- `Für dich`
- `Events`
- `Aktivitäten`

Explizit im Scope:

- neue Home-Logik als Premium-Entscheidungsfeed
- Events und Aktivitäten gemeinsam normalisieren
- lokales Interessenprofil ohne Account
- Merken-Funktion
- Ausblenden-/`Nicht interessant`-Funktion
- Wetterkontext für Bocholt
- Rankinglogik für Empfehlungen
- kompakte Mobile-UI
- Entscheidungssignale auf Karten
- Activity-Fallback, wenn Events dünn sind
- bestehende Event- und Aktivitätenseiten als Suchseiten erhalten

Explizit nicht im Scope:

- Nutzerkonto
- Sync zwischen Geräten
- Mittagstisch
- Push-Personalisierung
- komplexe KI-Empfehlungen
- Geolocation des Nutzers
- Preis-/Kostenfilter ohne belastbare strukturierte Daten
- automatische Anbieterberichte
- Wetterempfehlungen ohne passende Content-Tags

Architekturprinzip:

- Nicht die bestehende Eventseite weiter aufblasen.
- Stattdessen eine eigene Recommendation-Schicht bauen, die Events, Aktivitäten, Wetter und lokale Präferenzen zusammenführt.
- Bestehende Seiten bleiben möglichst stabil und werden nicht unnötig regressionsgefährdet.
- Premium bedeutet hier: ein konsistentes System, nicht mehrere isolierte Zusatzblöcke.

Benötigte technische Bausteine:

- `recommendations.js`
  - normalisiert Events und Aktivitäten
  - berechnet Scores
  - mischt Content
  - erzeugt Begründungslabels
  - berücksichtigt lokale Präferenzen
  - berücksichtigt Wetterkontext

- `user-preferences.js`
  - speichert Interessen lokal
  - speichert gemerkte Inhalte
  - speichert ausgeblendete Inhalte
  - speichert zuletzt gewählten Modus
  - kein Account, kein Sync

- `weather-context.js`
  - lädt oder hält Wetterkontext für Bocholt
  - bildet einfache Wetterklassen
  - liefert sicheren Fallback `unknown`

- neue Home-Renderlogik
  - rendert `Für dich in Bocholt`
  - rendert Modus-Auswahl
  - rendert gemischte Empfehlungskarten
  - verlinkt sauber zu Events und Aktivitäten

Lokales Profil ohne Account:

- Speicherung bevorzugt lokal im Browser.
- `localStorage` reicht für einfache Einstellungen.
- `IndexedDB` ist möglich, falls Merkliste/Ausblendungen größer oder strukturierter werden.
- Keine klassische Cookie-Logik als primärer Personalisierungsspeicher.
- Nutzerkonto und Geräte-Sync bleiben bewusst späterer Scope.

Lokale Profildaten:

- Interessen:
  - Familie
  - Draußen
  - Kultur
  - Musik
  - Essen & Trinken
  - Sport & Bewegung
  - Kurz & spontan
  - Wochenende
  - Bei Regen

- Gemerkt:
  - Event-IDs
  - Activity-IDs

- Ausgeblendet:
  - Event-IDs
  - Activity-IDs

- Nutzungskontext:
  - letzter Modus
  - bevorzugte Modi
  - optional zuletzt geklickte Kategorien

Gemeinsames Empfehlungsmodell:

Events und Aktivitäten müssen intern in ein gemeinsames `RecommendationItem` überführt werden.

Pflichtfelder intern:

- `type`: `event` oder `activity`
- `id`
- `title`
- `description`
- `category`
- `location`
- `url`
- `mapsTarget`
- `image`
- `dateContext`
- `timeContext`
- `situationTags`
- `audienceTags`
- `weatherProfile`
- `availability`
- `costLevel`
- `planningLevel`
- `recommendationWeight`
- `reasonLabels`
- `score`

Benötigte zusätzliche Event-Daten:

- `situation_tags`
  - `Mit Kindern`
  - `Draußen`
  - `Bei Regen`
  - `Abend`
  - `Wochenende`
  - `Spontan`

- `weather_profile`
  - `indoor`
  - `outdoor`
  - `mixed`
  - `weather_independent`
  - `rain_sensitive`
  - `unknown`

- `audience_tags`
  - `Familie`
  - `Erwachsene`
  - `Kultur`
  - `Musik`
  - `Sport`
  - `Essen & Trinken`

- `planning_level`
  - `spontan`
  - `planbar`
  - `ticket_or_registration_check`
  - `unknown`

- `cost_level`
  - `free`
  - `low`
  - `paid`
  - `unknown`

- `recommendation_weight`
  - `high`
  - `normal`
  - `fallback`

Benötigte zusätzliche Activity-Daten:

- `availability`
  - `always`
  - `opening_hours_check`
  - `seasonal`
  - `weather_dependent`

- `weather_profile`
  - `indoor`
  - `outdoor`
  - `mixed`
  - `rain_ok`
  - `rain_bad`
  - `weather_independent`
  - `unknown`

- `time_profile`
  - `morning`
  - `afternoon`
  - `evening`
  - `weekend`
  - `short_spontaneous`

- `cost_level`
  - `free`
  - `low`
  - `paid`
  - `unknown`

- `recommendation_weight`
  - `core`
  - `normal`
  - `fallback`

Wetterlogik:

- Wetter wird für Bocholt allgemein verwendet, nicht über Nutzer-Geolocation.
- Wetter ist ein Rankingfaktor, kein Show-Element.
- Wetter darf Empfehlungen nur beeinflussen, wenn Inhalte passende Wetterprofile haben.
- Bei fehlenden Wetterdaten muss der Feed sinnvoll ohne Wetter weiterlaufen.

Einfache Wetterklassen:

- `dry`
- `rain`
- `hot`
- `cold`
- `windy`
- `unknown`

Rankinglogik:

Höher bewerten:

- passt zu lokalen Interessen
- findet heute oder bald statt
- ist noch nicht vorbei
- passt zum gewählten Modus
- passt zum Wetter
- hat klare Location
- hat klare Uhrzeit oder klare Verfügbarkeit
- hat guten CTA
- ist für Familie/Draußen/Regen sauber getaggt
- ist gemerkt
- ergänzt den Feed sinnvoll, wenn Events dünn sind

Niedriger bewerten:

- unklare Daten
- fast vorbei
- wetterkritisch bei schlechtem Wetter
- nicht passend zum Modus
- zu ähnlich zu vorherigen Karten
- nur schwach belegte Empfehlungstags
- Activity mit Öffnungszeiten, wenn Verfügbarkeit ungeprüft ist

Ausschließen:

- ausgeblendete Inhalte
- vergangene Events
- Events ohne sinnvolle Mindestdaten
- Empfehlungen, deren Aussage nur geraten wäre

Content-Bewertung:

- Für eine Premium-Home nur mit Events reicht der Content nicht zuverlässig.
- Für eine Premium-Home mit Events + Aktivitäten ist die Grundlage vorhanden.
- Activities sind bereits stark genug als Fallback- und Ergänzungslogik.
- Events brauchen zusätzliche Empfehlungstags.
- Activities brauchen schärfere Wetter-, Verfügbarkeits- und Empfehlungsprofile.
- Mittagstisch wird bewusst später verschoben und gehört nicht zu diesem Workpack.

Wichtigster Daten-Gap:

- Events sind bisher eher kalendarisch beschrieben.
- Activities sind bereits stärker situations- und merkmalsbasiert beschrieben.
- Für `Für dich in Bocholt` müssen beide Content-Typen in ein gemeinsames Empfehlungsformat gebracht werden.

Akzeptanzkriterien für den späteren Premium-Workpack:

- `/` ist als `Für dich in Bocholt` erkennbar.
- Events und Aktivitäten erscheinen in einem gemeinsamen Empfehlungsfeed.
- Bestehende Event- und Aktivitätenseiten bleiben eigenständig nutzbar.
- Nutzer kann Interessen lokal setzen.
- Nutzer kann Inhalte merken.
- Nutzer kann Inhalte ausblenden.
- Wetterkontext beeinflusst Empfehlungen nur bei belastbaren Content-Tags.
- Der Feed funktioniert auch ohne Wetterdaten.
- Der Feed funktioniert auch ohne gespeicherte Interessen.
- Der Feed wirkt auf Mobile nicht überladen.
- Die ersten sichtbaren Karten liefern konkreten Entscheidungswert.
- Anbieter-CTA verdrängt nicht den ersten Nutzerentscheidungsbereich.
- Keine falschen Personalisierungsversprechen ohne lokale Signale.
- Keine Preis-/Kostenversprechen ohne strukturierte Daten.
- Keine Accountpflicht.

Umsetzungsreihenfolge innerhalb dieses Workpacks:

1. Datenvertrag finalisieren.
2. Event- und Activity-Datenfelder prüfen und ergänzen.
3. Recommendation-Normalisierung bauen.
4. Lokales Profilmodul bauen.
5. Wetterkontextmodul bauen.
6. Rankinglogik bauen.
7. Neue Home rendern.
8. Bottom-Navigation auf `Für dich | Events | Aktivitäten` ausrichten.
9. Eventseite als eigene Suchseite absichern.
10. Aktivitätenseite unverändert als Suchseite absichern.
11. Mobile- und Desktop-Proof.
12. Tracking-Proof für Recommendation-Klicks, Merken und Ausblenden.

Freeze-Bedingung:

- Der Workpack gilt erst als abgeschlossen, wenn die neue Home nicht nur anders aussieht, sondern einen klar höheren Nutzwert liefert als der bisherige Eventfeed.
- Maßstab ist: Nutzer erkennt schneller, was heute oder am Wochenende wirklich passt.
- Kein Release, wenn die Home nur wie ein umsortierter Kalender wirkt.

<!-- === END BLOCK: ROADMAP_FOR_YOU_BOCHOLT_PREMIUM_HOME_2026_05_29 === -->

<!-- === BEGIN BLOCK: ROADMAP_TODAY_HOME_PREMIUM_VISUAL_CONTRACT_AND_EVENT_FEED_2026_06_01 | Zweck: ordnet Today-Home-Abschluss, Premium-Visual-Contract und Events-Feed-Visuals in die richtige Reihenfolge; Umfang: Home, Event-/Activity-Bilder, Feed-Cards, Visual-Audit === -->
## Nächster Roadmap-Block – Today Home Premium Completion, Visual Contract und Events Feed Visual Integration

Status: aktiv / nächste priorisierte Arbeit.

### 1. Today Home Premium Completion

Ziel: Die Home-/Today-Seite soll nicht nur V1-tauglich, sondern vollständig premium-fertig werden.

Bereits erledigt:
- Today Home als zentraler Einstieg auf `/`.
- 3 kuratierte Tipps statt langer Liste.
- Activities als Standardempfehlungen.
- Events nur bei echter Heute-Relevanz.
- Keine Zukunftsevents mehr auf Today Home.
- Eventbilder über `visual_key -> event_visual_pool.json -> ready WebP`.
- Aktuelle Event-Visual-Coverage auf Staging: 62/62 Events.
- Premium-Layout-Polish wurde auf Staging umgesetzt.
- Regionalbezug wurde geklärt: `Heute rund um Bocholt`.
- Bottom-Tabbar wurde wieder als leichtes Glass-/Floating-Dock ausgerichtet, nicht als schwere opake Maske.

Nicht als Layout-Problem weiterführen:
- Bildqualität einzelner Activity-Karten.
- Roh wirkende Teasertexte durch harte Textabbrüche.
- Schwache oder zufällige Motive in prominenten Karten.

Verbleibender Premium-Hebel:
- Today Home nur mit wirklich geeigneten Visuals und redaktionell sauberen Kurztexten abschließen.
- Schwache Activity-Bilder wie Stadtwald/Rohr oder zu generische Naturdetails nicht per CSS retten, sondern ersetzen, zurückstufen oder aus Today ausschließen.

### 2. Premium Visual Contract V1

Ziel: Bildqualität künftig systematisch lösen, damit Event- und Activity-Karten nicht dauerhaft über manuelle Crop-/Focal-Point-Versuche repariert werden müssen.

Verbindliche Richtung:
- Card-Bilder werden als geprüfte 16:9-WebP-Card-Assets vorbereitet.
- Statuslogik für Visuals: `ready`, `usable`, `fallback`, `needs_review`, `blocked`.
- Prominente Flächen wie Today Home nutzen nur `ready` oder bewusst freigegebene `fallback`-Visuals.
- Schwache Bilder werden ersetzt, zurückgestuft oder ausgeschlossen.
- CSS bleibt Rendering-Rahmen, nicht Rettungssystem.
- Ein späterer interner Visual-Audit soll echte Card-Kontexte zeigen:
  - Today Mobile
  - Today Desktop
  - Events Feed
  - Activities Feed
  - später Detail-/Hero-Kontexte

Erste Umsetzungsschritte:
1. Bestehendes Event-Visual-System gegen diesen Contract prüfen.
2. Minimalmodell für Activity-Visual-Status festlegen.
3. Today Home darauf vorbereiten, keine `needs_review`/`blocked`-Visuals prominent auszuspielen.
4. Danach Bild-/Text-Polish für die sichtbaren Today-Karten durchführen.

### 3. Danach: Events Feed Visual Integration

Ziel: Das neue Event-Visual-System soll nicht nur auf Today Home, sondern auch im normalen Events Feed genutzt werden.

Geplanter Zielzustand:
- Eventkarten auf `/events/` nutzen dieselbe Visual-Contract-Logik wie Today Home:
  - `visual_key`
  - `data/event_visual_pool.json`
  - 16:9-WebP-Card-Assets
  - nur `status: ready` oder bewusst freigegebene Fallbacks
  - Fallback auf `default_city`
- Keine geplanten/non-ready Assets live ausspielen.
- Kein separater Bildstandard für Today und Events Feed.
- Mobile- und Desktop-Cards müssen mit Bildern premium wirken.
- Cropping nicht als Einzelbild-Ratespiel lösen, sondern über vorbereitete Card-Assets und Vorschauprüfung.
- Event-Detailpanel-Bilder erst danach bewerten; zunächst Fokus auf Feed-Cards.

Priorität:
1. Visual Contract V1 dokumentiert und technisch vorbereiten.
2. Today Home Content-/Visual-Polish abschließen.
3. Events Feed Visual Integration als nächster Hauptworkstream.
4. Danach ggf. Detailpanel-/Hero-Bildlogik separat entscheiden.
<!-- === END BLOCK: ROADMAP_TODAY_HOME_PREMIUM_VISUAL_CONTRACT_AND_EVENT_FEED_2026_06_01 === -->
