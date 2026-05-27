<!-- === BEGIN FILE: TEST_STATUS.md | Zweck: kanonisches Test- und Freigabeprotokoll für geprüfte Funktionen im Projekt „Bocholt erleben“; Umfang: Staging-/Live-Teststände, bestandene Smoke-Tests, offene Regressionen === -->

# TEST STATUS — BOCHOLT ERLEBEN

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
- echte Live-Zahlung für Aktivitätspräsenz nach Zahlungslink

Diese Punkte sind keine Blocker für den stille-Ausfall-Proof. Der tatsächliche Push-Versand bleibt optional, solange die Review-Inbox zuverlässig ist.

## Nächste Prüfschritte

Für Roadmap-Punkt 5 ist kein weiterer P0-Test nötig.

Später optional:

- tatsächlichen Live-Push-Versand prüfen, falls Live-Push aktiv genutzt werden soll
- echte Live-Zahlung für Aktivitätspräsenz separat testen, wenn der Activity-Abo-Zahlungsbeweis priorisiert wird

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

- Mitgliedschafts-/Abo-Live-Test, falls aktive Vermarktung der Mitgliedschaft breiter gestartet wird.
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
<!-- === END FILE: TEST_STATUS.md === -->
