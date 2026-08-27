# System Map – Bocholt erleben

Diese Datei beschreibt **stabile Systeme, Datenhoheit, Umgebungen und kritische Datenflüsse**. Operativer Status, konkrete SHAs, Deploy-Runs und Workpack-Evidence gehören ausschließlich in das jeweils aktive GitHub-Issue und werden hier nicht als zweite Statusquelle dupliziert.

## 1. Repository und Umgebungen

| Ebene | Staging | Live | Owner |
|---|---|---|---|
| Git-Branch | `staging` | `main` | GitHub / PR-Prozess |
| Webziel | STRATO-Verzeichnis `staging` | STRATO-Webroot `.` | `.github/workflows/deploy-strato.yml` |
| öffentliche URL | `https://staging.bocholt-erleben.de/` | `https://bocholt-erleben.de/` | Hosting/DNS |
| Steuerzentrale | `/steuerzentrale/` | `/steuerzentrale/` | `steuerzentrale/**`, `js/control-center/**`, `api/control-center/**` |
| Anbieterbereich | Staging-Portal und Staging-DB | Live-Portal und Live-DB | `fuer-veranstalter/**`, Organizer-Portal-JS, `api/organizer-portal/**` |
| öffentliche Startpartner-Anfrage | First-Party-Intake `/api/startpartner/intake.php` | bis zum nächsten Main-Release weiterhin der auf `main` vorhandene öffentliche Pfad | `startpartner/**`, `js/startpartner-funnel.js`, `api/startpartner/**` |
| Startpartner-Pilotbetrieb | Kandidat bis aktiver/pausierter/abschließender Pilot implementiert | keine operative Freigabe allein aus dem Staging-Stand ableiten | `api/startpartner/**`, `api/sql/008`–`012`, bestehende Organizer-/Submission-/Metric-Owner |

Nur `staging` und `main` dürfen deployen. Feature-Branches besitzen keine externe Umgebung. Ein auf `staging` implementierter Zustand ist nicht automatisch Live-Vertrag.

## 2. Hauptkomponenten

### Public Frontend

- statisches HTML, CSS und JavaScript;
- Today-, Event- und Activity-Oberflächen;
- öffentliche Einreichungs-, Anbieter- und Startpartnerseiten;
- generierte Event-/Inbox-Daten und freigegebene DB-Submissions;
- öffentliche DB-Projektionen unter `api/events/public.php` und `api/activities/public.php`.

### Steuerzentrale

- UI: `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS;
- API: `api/control-center/**`;
- Control-Center-Datenbank für operative Fälle und Operationszustände;
- Zweck: Quellen synchronisieren, Ausnahmen prüfen und kontrollierte Entscheidungen ausführen;
- `control_cases` ist bei Startpartner ausschließlich operative Projektion und niemals fachliche Source of Truth.

### Anbieterbereich und Submissions

- DB-/API-owned;
- `organizers` besitzt die Anbieteridentität;
- `submissions` besitzt eingereichte Inhalte und den redaktionellen Approval-Status;
- reguläre Subscriptions/Stripe und Publication-Entitlements bleiben eigene Produktowner;
- Mail, Zahlung und öffentliche Sichtbarkeit sind getrennte Nebenwirkungen und dürfen nicht aus einem Statusnamen allein angenommen werden.

### Startpartner-Wachstumspilot

Die Startpartner-Domäne ist auf `staging` als zusammenhängender fachlicher Pfad implementiert:

- First-Party-Self-Service-Intake und geschützter Targeted-Outreach-Intake;
- Kandidat, Kontakte, Deduplizierung und unveränderlicher Auditstream;
- normalisierte Qualifikation, Entscheidungsreife, append-only Entscheidungen;
- Kapazität, Reservierung und Warteliste;
- ausdrückliche Pilotbedingungen und Bestätigung;
- deterministische Organizer-Verknüpfung;
- Pilot, Scopes und separate kostenlose Pilotberechtigung;
- Onboarding, Content-Link, Messpreflight, Distribution und Pilotnutzung;
- Aktivierung und sechsmonatige Kalenderlaufzeit;
- aktiver Lifecycle mit `active`, `paused`, `closing`, `ended_without_conversion`, `terminated`;
- Kontrollpunkte und geordnetes Ende ohne automatische kostenpflichtige Konversion;
- Partnerdarstellung im bestehenden Anbieterportal und Operatorführung in der bestehenden Steuerzentrale.

Die fachliche Startpartner-Domäne bleibt Source of Truth. Das Control Center projiziert Aufgaben und die vom Backend bestimmte nächste Operatoraktion. Es erzeugt keinen zweiten Lifecycle-Entscheider.

## 3. Datenhoheit

| Domäne | Kanonische Quelle | Projektion/Artefakt |
|---|---|---|
| redaktionelle Live-Events | Google Sheet `Events` | Eventfeed und Detailseiten |
| Staging-Eventfreigaben | `Events_Staging` als Overlay | Staging-Feed |
| offene Inbox | `Inbox_Staging` / `Inbox` | Control-Center-Fälle und Ansichten |
| Inbox-Archiv | `Inbox_Archive_Staging` / `Inbox_Archive` | Archiv |
| DB-Submissions | `submissions` | Public-APIs und Feed-Ergänzungen |
| Organizer und Portal | Anbieter-Datenbank | Anbieterbereich und Statusansichten |
| reguläre Mitgliedschaften | Stripe plus `subscriptions` | Tarifstatus und reguläre Berechtigungen |
| reguläre Veröffentlichungsberechtigungen | Publication-Entitlement-Owner | zulässige reguläre Einreichungs-/Veröffentlichungsumfänge |
| Wirkungsmessung | `value_metric_daily` und Attributionsdaten | Anbieterwirkung und Startpartner-Messreadback |
| Startpartner-Kandidat | `startpartner_candidates` plus Contacts, Events, Qualifications, Decisions, Reservations, Waitlist und Operations | geschützte Startpartner-APIs und `control_cases`-Projektion |
| Startpartner-Pilot | `startpartner_pilots` | Portal-/Control-Center-Readback |
| Startpartner-Scopes | `startpartner_pilot_scopes` | erlaubter Event-/Activity-Umfang und Limits |
| kostenlose Pilotberechtigung | `startpartner_pilot_entitlements` | wirksamer Pilotzeitraum, Status und Limits |
| Pilotinhalt | `startpartner_pilot_content_links` + kanonische `submissions` | öffentliche Event-/Activity-Projektion nach Approval- und Lifecycle-Regeln |
| Pilotnutzung | `startpartner_pilot_usages` | historische, genau-einmalige Verbrauchsevidence |
| Pilotkontrollpunkte | `startpartner_pilot_events` | Checkpoint-/Lifecycle-Audit |
| Distribution | `startpartner_pilot_distribution_commitments` | Operator-/Partnerstatus |
| Activities kuratiert | `data/offers.json` und Activity-Visual-Owner | öffentliche Activity-Ausgabe |
| Activities aus DB-Submissions | `submissions` | `api/activities/public.php` + additive UI-Projektion |
| Visuals | Visual-Pool und freigegebene Assets | Karten-/Detaildarstellung |

`data/events.tsv`, `data/events.json` und `data/inbox.json` sind generierte Buildartefakte und keine fachlichen Writer.

## 4. Öffentliche Event- und Activity-Projektion

### Events

```text
Google Sheet Events
+ auf Staging Events_Staging-Overlay
+ freigegebene DB-Submissions über api/events/public.php
-> Eventfeed / Detaildarstellung / UI
```

Staging darf `Events` lesen, aber nur `Events_Staging` beschreiben. Live ignoriert das Overlay.

### Activities

```text
data/offers.json
+ freigegebene Activity-Submissions über api/activities/public.php
-> additive Activity-Ausgabe
```

Der kuratierte JSON-Bestand bleibt bestehen. Der DB-Pfad ist eine additive öffentliche Projektion, keine zweite Submission- oder Approval-Source-of-Truth. Fällt die DB-Projektion aus, bleibt der kuratierte Activity-Feed nutzbar.

### Publication Contract für DB-Submissions

`submissions.status = approved` ist die kanonische redaktionelle Freigabe, aber öffentliche Sichtbarkeit wird durch den jeweiligen Public-Reader bestimmt.

Für normale freigegebene DB-Submissions gilt:

- Approval-Evidence und die jeweiligen öffentlichen Mindestfelder müssen vorliegen;
- der Public-Reader erzeugt daraus die öffentliche Event- oder Activity-Projektion.

Für Startpartner-verknüpfte Inhalte gilt zusätzlich:

- `startpartner_pilot_content_links.status = approved`;
- Pilotstatus `active`, `paused` oder `closing`;
- `withdrawn` entfernt den Inhalt aus der öffentlichen Projektion, ohne historische Approval-/Usage-Evidence umzuschreiben;
- `ended_without_conversion` und `terminated` beenden die aktive Startpartner-Public-Projection;
- Pause und Closing behalten bereits freigegebene Inhalte sichtbar, blockieren aber neue Partner-Contentaktionen gemäß Lifecycle-Vertrag.

Damit ist öffentliche Sichtbarkeit **abgeleitete Projektion**, nicht ein separater Startpartner-Publisher und nicht allein eine Eigenschaft des Submission-Status.

## 5. Inbox-Übernahmepfad

```text
Steuerzentrale
-> Action API
-> Fall- und Environment-Auflösung
-> fallbezogener read-only Preflight
-> Eventziel schreiben und zurücklesen
-> Inboxstatus schreiben und zurücklesen
-> lokalen Fall schließen
```

Umgebungsbindung:

```text
staging: Inbox_Staging -> Events_Staging
live:    Inbox         -> Events
```

Preflight und Ausführung verwenden denselben Environment- und Writer-Resolver.

## 6. Startpartner-Pfad – aktueller Staging-Vertrag

### Öffentlicher Intake

```text
/startpartner/
-> clientseitige Validierung + Idempotency-Key
-> POST /api/startpartner/intake.php
-> kanonischer Candidate-/Contact-/Audit-Write
-> bestehende control_case-Projektion
-> einmalige Eingangsbestätigung nur bei neu angelegter Self-Service-Anfrage
-> eindeutiger Erfolgszustand
```

Der First-Party-Intake erzeugt **keinen** Organizer, Pilot, Pilotentitlement, Submission, Stripe-/Payment- oder Publication-Write. Ein Mailfehler rollt den bereits gespeicherten Kandidaten nicht zurück. Ein identischer Replay erzeugt keinen zweiten Kandidaten und keine zweite Eingangsbestätigung.

Der Live-Branch `main` kann bis zu einem späteren Release noch den historischen Formspree-Funnel enthalten. Deshalb muss vor jedem Main-Cutover der tatsächliche `main`-/Live-Stand erneut geprüft werden; die System Map ist keine Releasefreigabe.

### Qualifikation bis Pilotbetrieb

```text
First-Party-Kandidat
-> Deduplizierung / Audit
-> Qualifikation und Kapazitätsprüfung
-> Aufnahmeentscheidung
-> Pilotbedingungen und ausdrückliche Bestätigung
-> Organizer verknüpfen oder deterministisch anlegen
-> Pilot + Scopes + pending Pilotentitlement
-> Portal-/Onboarding-Readback
-> Partner reicht Pilotinhalt als bestehende Submission ein
-> redaktionelle Vorbereitung
-> Mess- und Distribution-Readiness
-> ausdrückliche Pilotaktivierung
-> sechsmonatige Laufzeit
-> aktive Inhalte / Limits / Checkpoints / Distribution / Messreadback
-> Pause oder Closing bei Bedarf
-> geordnetes Ende oder Abbruch
```

Jede geschützte fachliche Mutation nutzt die vorhandenen revisions- und idempotenzgesicherten Operationsrahmen. Stale Writes enden fail-closed; identische Retries liefern das gespeicherte Ergebnis.

## 7. Startpartner Source-of-Truth- und Lifecycle-Regeln

- Kandidat und Pilot besitzen eigene fachliche Datenowner.
- `control_cases` besitzt Aufgabenstatus und Projektion, nicht den vollständigen Fachvertrag.
- `organizers` besitzt die Anbieteridentität.
- `submissions` besitzt eingereichten Inhalt und redaktionellen Approval-Status.
- Startpartner-Content-Link und Pilotstatus bestimmen zusätzlich, ob eine Startpartner-Submission öffentlich projiziert werden darf.
- `subscriptions` besitzt ausschließlich reguläre Stripe-Mitgliedschaften.
- Die kostenlose Pilotberechtigung ist ein eigener Startpartner-Owner und keine Stripe-Testsubscription.
- Wirkungsmessung verwendet `value_metric_daily`; fehlende Messzeilen sind kein Beweis für Nullnutzung.
- Partnerdistribution besitzt einen eigenen Commitment-/Fulfillment-Status; `ready` ist nicht gleich `completed`.
- Pilotnutzung wird bei erfolgreicher redaktioneller Approval genau einmal geschrieben.
- Historische Nutzung bleibt bei Withdrawal und Pilotende erhalten.
- Eventlimit wird pro Pilotmonat gezählt; Activity-Limit ist gleichzeitig belegte freigegebene Aktivitätspräsenz.
- `withdrawn` gibt Activity-Occupancy frei, ohne historische Usage zu löschen.
- `paused` belegt weiterhin Kohortenkapazität und behält bereits freigegebene öffentliche Inhalte.
- `closing` ist in diesem Lifecycle nicht reversibel und behält die Kohortenkapazität bis zum terminalen Ende.
- `ended_without_conversion` und `terminated` geben Kohortenkapazität frei und beenden die aktive öffentliche Startpartner-Projektion.
- Keine automatische bezahlte Konversion. Eine spätere reguläre Fortführung benötigt einen neuen ausdrücklichen regulären Vertrags-/Checkoutweg.

## 8. Startpartner-Datenbeziehungen

```text
StartpartnerCandidate
  -> CandidateContact
  -> CandidateQualification
  -> CandidateDecision
  -> CandidateReservation / CandidateWaitlist
  -> CandidateOperation / CandidateEvent
  -> control_case projection
  -> Organizer
  -> StartpartnerPilot
       -> PilotScope
       -> PilotEntitlement
       -> PilotOnboardingItem
       -> PilotContentLink -> Submission -> public Event / Activity projection
       -> PilotMeasurementPreflight -> value_metric_daily readback
       -> PilotDistributionCommitment
       -> PilotUsage
       -> PilotEvent / Checkpoint audit
```

Pflichtidentitäten:

- Candidate-ID;
- Pilot-ID;
- Organizer-ID;
- Content-Link-/Submission-ID;
- Reporting-Target-ID;
- Operation-ID und erwartete Revision bei Mutation;
- externe Kommunikationsreferenz nur dort, wo Kommunikation tatsächlich stattfindet.

## 9. Operator- und Partnerprojektion

### Operator

`api/startpartner/_gate4_state.php` ist für den laufenden Pilot der kanonische Owner der **nächsten Operatoraktion**. Prioritäten wie Pilotende, Closing, fällige Checkpoints, fällige/blockierte Distribution und Content-Review werden dort bestimmt.

`api/startpartner/_gate4_projection.php` projiziert diese Entscheidung in `control_cases`. Das Control Center rendert sie; es soll für aktive Lifecycle-Phasen keine zweite Prioritätslogik aufbauen.

### Partner

Die Partnerprojektion ist bewusst rollenbezogen und kleiner:

- genau eine primäre nächste Partneraktion oder ein klarer Status;
- keine internen Evidence-, Operator- oder technischen Fehlerdetails;
- keine Content-CTA in `paused`, `closing`, terminalen Zuständen oder außerhalb der wirksamen Laufzeit;
- Limitstatus verhindert nicht erlaubte zusätzliche Einreichungen;
- kein Payment-/Upgrade-CTA als implizite Pilotkonversion.

## 10. Startpartner-Nebenwirkungen

Mögliche echte Nebenwirkungen im Gesamtsystem sind voneinander zu trennen:

- Nachricht an Kandidat oder Partner;
- Organizer-Anlage;
- Magic-Link-Mail und Portal-Session;
- Pilotberechtigung;
- Submission;
- redaktionelle Freigabe und daraus abgeleitete Public-Projection;
- regulärer Stripe-Checkout;
- Deaktivierung oder Ende einer Berechtigung.

Für den laufenden Pilot-Lifecycle gelten insbesondere:

- Content-Approval darf nach allen Guards genau eine Pilotnutzung erzeugen und den Inhalt öffentlich projektionsberechtigt machen;
- Reject eines noch nicht freigegebenen Inhalts besitzt keine Publikationswirkung;
- Withdrawal eines freigegebenen Pilotinhalts beendet seine Public-Projection, historische Approval-/Usage-Evidence bleibt erhalten;
- Pause/Resume/Closing verändern keine historische Usage und entfernen bereits freigegebene Inhalte nicht automatisch;
- terminales Ende beendet die aktive Startpartner-Public-Projection;
- Lifecycle-Aktionen erzeugen keine Stripe-, Subscription- oder automatische Paid-Conversion-Wirkung.

Jede echte Nebenwirkung benötigt stabile Identität, Vorherzustand, erwartete Mutation, Rücklesen, unveränderte Nicht-Zielfelder sowie Rollback oder geordneten Cleanup.

## 11. Aktivierungs- und Endgrenze

Der Pilot beginnt erst nach belegter Aktivierung. Vor dem Start müssen insbesondere vorhanden sein:

- bestätigte Pilotbedingungen;
- gebundener Organizer und funktionierender Portalzugang;
- konsistente Scopes und pending Pilotentitlement;
- erster redaktionell vorbereiteter Inhalt;
- Messzuordnung;
- vorbereiteter Reichweitenbeitrag;
- keine harten Blocker.

Die Aktivierungsaktion setzt den wirksamen Start und das sechsmonatige lokale Enddatum. Anfrage, Aufnahmeentscheidung oder Accountanlage allein starten die Laufzeit nicht.

Am geplanten Endtag wird die Abschlussentscheidung zur führenden Operatoraktion. Eine Laufzeit wird nicht stillschweigend verlängert. Nach der wirksamen Laufzeit sind neue Partner-Submits, neue Approvals und Resume gesperrt; der Abschluss bleibt eine bewusste Operatoraktion.

## 12. Entwicklungs-, Release- und Beobachtungspfad

```text
Feature-Branch
-> PR nach staging
-> PR Gate
-> Merge nach staging
-> Deploy to STRATO
-> Build-/HTTP-/Browser-Smoke
-> Publish Deploy Run Status am Commit
-> separater Release-Preflight staging -> main
-> erst nach Freigabe Main-Merge / Live-Deploy / Live-Smoke
```

`Publish Deploy Run Status` ist passiv: Er beobachtet `Deploy to STRATO`, veröffentlicht Commitstatus und Run-Link, erzeugt aber selbst keinen Deploy und verändert keine Fachressource.

Für Startpartner wird kein eigener dauerhafter GitHub-Workflow angelegt, solange der bestehende Web-/API-/Control-Center-Pfad die Aufgabe übernimmt. Fristen und Checkpoints gehören in die fachliche Daten- und Aufgabenlogik.

## 13. Dauerhafte Workflowrollen

| Workflow | Rolle |
|---|---|
| `PR Gate` | Branchpolicy, Workpack-Vertrag und Repositorytests |
| `Deploy to STRATO` | Feed-Build, geordneter Release und Runtime-Smokes |
| `Publish Deploy Run Status` | passiver Commitstatus und automatische Run-Auffindbarkeit |
| `Content Quality Audit` | Inhaltsqualität |
| `Growth Intelligence Backlog` | Growth-/SEO-Signale |
| `Inbox Cleanup (Archive)` | Inbox-Archivierung |
| `Weekly KI Websearch → Manual Inbox` | Eventkandidatensuche |
| `Manual KI Event Intake` | Kandidaten-Handoff |

## 14. Owner-Übersicht

| Domäne | Primäre Owner |
|---|---|
| Agent-Arbeitsprozess | `AGENTS.md` |
| Architektur | `docs/architecture/SYSTEM_MAP.md` |
| technische Regeln | `ENGINEERING.md` |
| externe Ressourcen | `docs/external-resource-matrix.md` |
| Deploy/Branchrouting | `.github/workflows/deploy-strato.yml`, `scripts/resolve-deploy-target.sh` |
| Deploy-Run-Auffindbarkeit | `.github/workflows/deploy-run-status.yml`, `scripts/publish_deploy_run_status.py` |
| Control-Center UI | `steuerzentrale/**`, `js/control-center/**` |
| Control-Center Runtime | `api/control-center/**` |
| Anbieterportal | `fuer-veranstalter/**`, Organizer-Portal-JS, `api/organizer-portal/**` |
| Organizer/Submission/Subscription | `api/**`, `api/sql/**`, Submission-/Anbieter-DB |
| Startpartner öffentlicher Intake | `startpartner/**`, `js/startpartner-funnel.js`, `api/startpartner/intake.php`, `api/startpartner/_public_intake.php` |
| Startpartner Candidate + Gate 2 | `api/startpartner/**`, Migrationen `008` und `010`; `control_cases` nur Projektion |
| Startpartner Gate 3 | `api/startpartner/**`, Migration `011` |
| Startpartner Gate 4 + aktiver Lifecycle | `api/startpartner/**`, Migration `012`, `js/organizer-pilot.js`, `js/control-center/startpartner-gate4.js` |
| öffentliche DB-Events | `api/events/public.php` |
| öffentliche DB-Activities | `api/activities/public.php`, `js/activity-submission-feed.js` als UI-Adapter |
| Eventfeed insgesamt | Deployworkflow, Eventgeneratoren, `api/events/**` |
| kuratierte Activities | `data/offers.json`, Activity-Visual-/UI-Owner |
| Produktziel | `MASTER.md`, `Produktvertrag.md`, `COMMERCIAL_STRATEGY.md` |
| Produktpriorität | `ROADMAP.md` |
| Proofstand | `TEST_STATUS.md` |

## 15. Prüfung vor kritischen Änderungen

1. Wer besitzt den Ursprungswert?
2. Welche Projektionen existieren?
3. Welche Umgebung und Ressource wird verwendet?
4. Was wird konkret gelesen oder geschrieben?
5. Welche Postconditions müssen bestätigt werden?
6. Wie wird ein Teilfehler sichtbar?
7. Wie erfolgt Rollback oder Cleanup?
8. Ist ein anderer Schreiber am selben Owner aktiv?
9. Welche dauerhafte Dokumentation besitzt die geänderte Realität?
10. Wird ein fachlicher Startpartnerzustand fälschlich nur in `control_cases`, Mail oder einem externen Formular gehalten?
11. Entsteht versehentlich eine Stripe-/Subscription-/Payment-Wirkung für den kostenlosen Pilot?
12. Sind Kandidat, Pilot, Organizer, Inhalt, öffentliche Projektion und Messung über stabile IDs verbunden?
13. Wird öffentliche Sichtbarkeit fälschlich nur aus `submissions.status` abgeleitet, obwohl ein Startpartner-Link/Lifecycle beteiligt ist?
14. Wird eine fachliche Next Action parallel in Backend, Projection und Frontend neu entschieden?

Ist eine Antwort nicht belegbar, folgt read-only Analyse statt Mutation.
