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

Ziel:

- Neue Einreichungen erzeugen zuverlässig Review-Arbeit und interne Hinweise.

Warum:

- Still liegenbleibende Einreichungen wären für zahlende Veranstalter besonders schädlich.

Akzeptanzkriterien:

- Single-Event-, Membership- und Activity-Presence-Einreichungen erscheinen zuverlässig in der Inbox.
- Push ist best-effort, aber zentrale Auslöser sind nachvollziehbar.
- Wenn Push nicht ausgelöst wird, muss die Review-Inbox trotzdem korrekt sein.

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

Ziel:

- `/events-veroeffentlichen/`, `/fuer-veranstalter/` und `/angebote/sichtbar-werden/` erklären klarer, welchen konkreten Nutzen Anbieter bekommen.

Warum:

- Der aktuelle Funnel ist logisch und fair, aber noch nicht maximal verkaufsstark.

Akzeptanzkriterien:

- Kommunikation bleibt seriös und lokal.
- Keine Zahlen verwenden, die nicht belegbar sind.
- Mehrwert wird als Dienstleistung erklärt: Prüfung, Aufbereitung, Sichtbarkeit, messbare Interaktionen.

### 8. Monatlichen Wertbericht vorbereiten

Ziel:

- regelmäßiger Bericht pro Anbieter/Location.

Warum:

- Retention entsteht nicht nur durch Veröffentlichung, sondern durch wiederholten Nutzennachweis.

Akzeptanzkriterien:

- Bericht kann zunächst manuell oder halbautomatisch erzeugt werden.
- Automatisierung erst nach stabilem Datenmodell.

---

## P2 — Nutzerprodukt weiter Richtung Discovery-Portal ausbauen

### 9. `Heute in Bocholt` als eigener Discovery-Workpack

Ziel:

- Nutzer finden schneller passende Dinge für konkrete Situationen.

Mögliche Einstiege:

- Heute
- Morgen
- Wochenende
- Mit Kindern
- Draußen
- Bei Regen
- kostenlos / günstig
- Aktivitäten ohne festen Termin

Warum:
