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
- 42 Activities mit `opening_status` ergänzt.
- 2 Activities bewusst ohne `opening_status`, weil sie fachlich `needs_review` bleiben:
  - `quellengrundpark-weseke-entdecken`
  - `erlebnispfad-klostersee-burlo`

### Finale Verteilung

- `free_access`: 24
- `regular_hours`: 5
- `seasonal_recommended`: 5
- `seasonal_hours`: 5
- `check_required`: 3

### Umgesetzte Commits

- `c720627` – Stelle mobile Eventbilder auf Thumbnails um
  - Enthält zusätzlich den Batch-1-Opening-Status-Datenstand.
- `0ec6e18` – Ergaenze Oeffnungsstatus fuer Activity Audit Batch 2
- `023996a` – Ergaenze Oeffnungsstatus fuer Activity Audit Batch 3
- `1b69e55` – Ergaenze Oeffnungsstatus fuer Activity Audit Batch 4
- `c46c9ae` – Korrigiere Feiertagslogik fuer freie Activities

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
- `holiday_policy = check` bleibt für Teilangebote/Innenräume/Führungen prüfhinweisfähig.
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
- Die zwei Needs-Review-Activities bleiben absichtlich offen, bis Quelle/Titel/Activity-Zuschnitt geklärt ist.

### Nächster sinnvoller Schritt

Kein weiterer Daten-Massenpatch. Als nächstes nur gezielte Folgearbeit:

1. Needs-Review-Fälle klären.
2. Saisonregel-Modell für echte Schutz-/Bade-/Saisonfälle sauber definieren.
3. UI-/Detailpanel-Anzeige für `opening_status.public_label` und `detail_note` prüfen.
4. Erst danach kleine, owner-klare Logik- oder UI-Patches.
<!-- === END BLOCK: TEST_STATUS_ACTIVITY_QUALITY_AUDIT_V1_DONE_2026_06_08 === -->
