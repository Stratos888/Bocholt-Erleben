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

## Offene Tests / bekannte Lücken

### Live-Rollout

Noch nicht in diesem Teststand erfolgt.

Vor Main/Live erforderlich:

1. `LIVE_REVIEW_PASSWORD` in GitHub Secrets vorhanden
2. Live-Datenbankmigration für `003_submission_intake_origin_location_review.sql` ausführen
3. Main/Live-Deploy ausführen und im Deploy-Log prüfen:
   - Google Sheet `Events` → `data/events.tsv` → `data/events.json`
   - Google Sheet `Inbox` → `data/inbox.tsv` → `data/inbox.json`
   - `robots.txt` live aus `deploy-templates/robots.live.txt`
   - `sitemap.xml` live aus `deploy-templates/sitemap.live.xml`
4. Live-Smoke-Test:
   - `/api/submissions/review-list.php` → `Review access denied`
   - `/inbox/` → Passwortabfrage, danach Laden ohne 503
   - `/events-veroeffentlichen/` lädt
   - `/events-veroeffentlichen/einreichen/` lädt
   - `/events-veroeffentlichen/anbindung/` lädt
   - `/fuer-veranstalter/` lädt
5. SEO-/Analytics-Smoke-Test:
   - `https://bocholt-erleben.de/robots.txt` erreichbar
   - `https://bocholt-erleben.de/sitemap.xml` erreichbar
   - GA4 lädt nur auf Live-Domain
   - mindestens ein `outbound_click` wird bei externem Event-/Activity-Klick erfasst

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

Der Kernzielzustand ist auf Staging erfüllt:

- eingereichte Einzelevents erscheinen in der Kuratier-PWA
- eingereichte Mitgliedschafts-Events erscheinen in der Kuratier-PWA
- KI-/Sheet-Fälle erscheinen korrekt in der Kuratier-PWA
- Herkunft wird getrennt von Event-Kategorie angezeigt
- Einzelevent / Mitgliedschaft / KI-Suche werden korrekt unterschieden
- Verwerfen funktioniert
- Übernehmen funktioniert
- Kontingentverbrauch erfolgt erst bei Übernehmen
- Review-Endpunkte sind gegen direkten öffentlichen Zugriff geschützt
- Google-Sheet-basierter Inbox-Pfad ist praktisch geprüft

Nicht vollständig belegt ist nur:

- Live-Rollout

<!-- === END FILE: TEST_STATUS.md === -->
