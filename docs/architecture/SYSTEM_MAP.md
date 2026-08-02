# System Map – Bocholt erleben

Diese Datei beschreibt stabile Systeme, Datenhoheit, Umgebungen und kritische Datenflüsse. Operativer Status steht ausschließlich im aktiven GitHub-Issue; `docs/workpacks/active/CURRENT_WORKPACK.md` ist nur der Router dorthin.

## 1. Repository und Umgebungen

| Ebene | Staging | Live | Owner |
|---|---|---|---|
| Git-Branch | `staging` | `main` | GitHub / PR-Prozess |
| Webziel | STRATO-Verzeichnis `staging` | STRATO-Webroot `.` | `.github/workflows/deploy-strato.yml` |
| öffentliche URL | `https://staging.bocholt-erleben.de/` | `https://bocholt-erleben.de/` | Hosting/DNS |
| Steuerzentrale | `/steuerzentrale/` | `/steuerzentrale/` | `steuerzentrale/**`, `js/control-center/**`, `api/control-center/**` |
| Anbieterbereich | Staging-Portal und Staging-DB | Live-Portal und Live-DB | `fuer-veranstalter/**`, `js/organizer-portal.js`, `api/organizer-portal/**` |
| Startpartner-Anfrage aktuell | öffentliche Staging-Route mit Formspree-Ziel | öffentliche Live-Route mit Formspree-Ziel | `startpartner/**`, `js/startpartner-funnel.js`, Formspree |
| Startpartner-Zielprozess | synthetische Staging-Kandidaten und -Piloten | produktive Kandidaten und Piloten erst nach Freigabe | zukünftige fachliche Startpartner-Owner in Submission-/Anbieter-DB |

Nur `staging` und `main` dürfen deployen. Feature-Branches besitzen keine externe Umgebung.

## 2. Hauptkomponenten

### Public Frontend

- statisches HTML, CSS und JavaScript;
- Today-, Event- und Activity-Oberflächen;
- öffentliche Einreichungs-, Anbieter- und Startpartnerseiten;
- generierte Event-/Inbox-Daten und freigegebene DB-Submissions.

### Steuerzentrale

- UI: `steuerzentrale/**`, `js/control-center/**`, Control-Center-CSS;
- API: `api/control-center/**`;
- lokaler Zustand: Control-Center-Datenbank für Fälle und Operationszustände;
- Zweck: Quellen synchronisieren, Ausnahmen prüfen und kontrollierte Entscheidungen ausführen.

### Anbieterbereich und Submissions

- DB-/API-owned;
- Organizer, Portalzugang, Einreichungen, Anbieterstatus, Produkte, Zahlung und Wirkungsmessung;
- Mail, Zahlung und Veröffentlichung sind externe Nebenwirkungen.

### Startpartner-Wachstumspilot

Der öffentliche Anfragepfad bleibt unverändert Formspree-owned. Zusätzlich besitzt die interne Staging-/Dev-Domäne einen geschützten Kandidatenprozess bis `accepted_pending_terms`:

- Kandidat, Kontakte, Deduplizierung und unveränderlicher Auditstream;
- monoton steigende Candidate-Revision und payloadgebundene Operations-Idempotenz;
- 14 normalisierte Qualifikationsdimensionen und deterministische Entscheidungsreife;
- append-only Entscheidungen;
- Kapazität, historisierte Reservierungen und normalisierte Warteliste;
- atomare `control_cases`-Projektion;
- Startpartner-Prüfbereich innerhalb der vorhandenen Steuerzentrale.

Noch nicht Teil des aktuellen Systems sind Pilotbedingungen und Bestätigung, Pilotobjekt, Organizer- und Portalverknüpfung, kostenlose Berechtigung, Onboarding, Aktivierung, Laufzeit, Inhaltszuordnung, Wirkungsmessung, Kommunikation, Abschluss oder Konversion.

Die fachliche Startpartner-Domäne ist Source of Truth. `control_cases` ist ausschließlich operative Aufgaben- und Entscheidungsprojektion und kein paralleler Writer.

### Visual-System

- Vertragsdaten: `data/event_visual_pool.json`;
- Prozessvertrag: `VISUAL_WORKFLOW.md`;
- Generatoren und Audits unter `scripts/**`.

## 3. Datenhoheit

| Domäne | Kanonische Quelle | Projektion/Artefakt |
|---|---|---|
| redaktionelle Live-Events | Google Sheet `Events` | Eventfeed und Detailseiten |
| Staging-Eventfreigaben | `Events_Staging` als Overlay | Staging-Feed |
| offene Inbox | `Inbox_Staging` / `Inbox` | Control-Center-Fälle und Ansichten |
| Inbox-Archiv | `Inbox_Archive_Staging` / `Inbox_Archive` | Archiv |
| DB-Submissions | Submission-Datenbank | Public-API und Feed-Ergänzung |
| Organizer und Portal | Anbieter-Datenbank | Anbieterbereich und Statusansichten |
| reguläre Mitgliedschaften | Stripe plus Subscription-Datenbank | Tarifstatus und reguläre Berechtigungen |
| Veröffentlichungsberechtigungen | Entitlement-Datenbank | zulässige Einreichungs-/Veröffentlichungsumfänge |
| Wirkungsmessung | `value_metric_daily` und zugehörige Attributionsdaten | Anbieterwirkung und Auswertungen |
| Startpartner-Anfrage öffentlich | Formspree-Übermittlung | E-Mail/Formspree-Ansicht; kein automatischer First-Party-Intake |
| Startpartner-Kandidat intern | `startpartner_candidates` plus Contacts, Events, Qualifications, Decisions, Reservations, Waitlist und Operations | geschützte Startpartner-APIs und atomare `control_cases`-Projektion |
| Startpartner-Pilot im Ziel | zukünftige eigene fachliche Pilottabelle | Portalstatus, Berechtigung, Kontrollpunkte, Abschluss |
| Startpartner-Pilotberechtigung im Ziel | befristeter Pilotgrant oder eindeutig pilotfähiges Entitlement | bestehende Submission-/Publikationspfade |
| Activities | Repo-/JSON-Owner | öffentliche Activity-Ausgabe |
| Visuals | Visual-Pool und freigegebene Assets | Karten-/Detaildarstellung |

`data/events.tsv`, `data/events.json` und `data/inbox.json` sind generierte Buildartefakte.

## 4. Event-Feed

```text
Google Sheet Events
+ auf Staging Events_Staging-Overlay
+ freigegebene DB-Submissions
-> Deploy-/Buildgeneratoren
-> Event-API, Detailseiten, Sitemap und UI
```

Staging darf `Events` lesen, aber nur `Events_Staging` beschreiben. Live ignoriert das Overlay.

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

## 6. Startpartner-Pfad – aktueller Zustand

```text
/startpartner/
-> Browservalidierung
-> Formspree
-> externe Übermittlung / Nachricht
-> manuelle Bearbeitung außerhalb eines kanonischen eigenen Kandidatenmodells
```

Belegte Grenze des öffentlichen Pfads:

- keine automatische Organizer-Anlage;
- keine Pilotvereinbarung;
- keine kostenlose Berechtigung;
- keine Stripe-Subscription;
- keine Veröffentlichung;
- kein öffentlicher First-Party-Kandidatenwrite.

Der geschützte interne Staging-/Dev-Pfad lautet:

```text
interner Gate-1-Intake oder vorhandener Kandidat
-> startpartner_candidates / contacts / events
-> Profil und 14 Qualifikationsdimensionen
-> Entscheidungsreife und append-only Entscheidung
-> Kapazitätsprüfung
-> Reservierung oder Warteliste
-> atomare control_case-Projektion
-> Startpartner-Review in der Steuerzentrale
```

Jede Gate-2-Mutation benötigt Reviewzugang, `operation_id`, `expected_revision` und `operator_name`. Ein stale write endet mit HTTP `409`; ein identischer Retry liefert das gespeicherte Ergebnis. Der generische Control-Center-Writer weist Startpartner-Fälle ab.

Formspree bleibt ein externer Übergangswriter und muss vor einem späteren öffentlichen Cutover separat behandelt werden.

## 7. Startpartner-Pfad – weiterer Zielzustand ab Gate 3

```text
Selbstmeldung oder interne Identifizierung
-> First-Party-Kandidaten-API / fachliche Kandidaten-Source-of-Truth
-> Deduplizierung und Audit
-> control_case als operative Projektion
-> Qualifizierung und Kapazitätsprüfung
-> Aufnahmeentscheidung
-> Pilotbedingungen und ausdrückliche Bestätigung
-> Organizer anlegen oder verknüpfen
-> StartpartnerPilot anlegen
-> befristete kostenlose Pilotberechtigung
-> Onboarding
-> Submissions / Quellen / redaktionelle Prüfung
-> Event- oder Activity-Projektion
-> Organizer-, Pilot- und Inhaltsattribution in value_metric_daily
-> Aktivierung und sechsmonatige Laufzeit
-> Kontrollpunkte und Kommunikation
-> Abschlussbericht
-> ausdrücklicher regulärer Checkout oder geordnetes Ende
```

### Source-of-Truth-Regeln

- Kandidat und Pilot besitzen eigene fachliche Datenowner.
- `control_cases` besitzt Aufgabenstatus, nicht den vollständigen Fachvertrag.
- `organizers` besitzt die Anbieteridentität erst nach Annahme.
- `subscriptions` besitzt ausschließlich reguläre Stripe-Mitgliedschaften.
- Eine kostenlose Pilotberechtigung darf keine Stripe-Testsubscription vortäuschen.
- Ein Startpartnerinhalt verwendet die vorhandenen Submission-, Review- und Veröffentlichungsowner.
- Wirkungsmessung verwendet vorhandene Metrikowner mit zusätzlicher stabiler Pilotattribution.
- Formspree wird nach belegtem Cutover als Writer abgeschaltet; kein dauerhafter Dual-Write.

### Aktivierungsgrenze

Der Pilot beginnt erst, wenn:

- Portalzugang funktioniert;
- Pilotberechtigung aktiv und zurückgelesen ist;
- mindestens ein relevanter Inhalt veröffentlicht ist;
- Messzuordnung funktioniert;
- Partnerdistribution vorbereitet ist.

Anfrage, Aufnahmeentscheidung oder Accountanlage allein starten die sechs Monate nicht.

## 8. Startpartner-Datenbeziehungen

```text
StartpartnerCandidate
  -> CandidateContact
  -> CandidateQualification
  -> CandidateDecision
  -> CandidateReservation
  -> CandidateWaitlist
  -> CandidateOperation
  -> CandidateEvent
  -> control_case projection

accepted and explicitly confirmed candidate in a later gate
  -> Organizer
  -> StartpartnerPilot
       -> PilotScope / PilotEntitlement
       -> StartpartnerCheckpoint
       -> StartpartnerCommunication
       -> StartpartnerContentLink
            -> Submission
            -> Event / Activity
       -> StartpartnerMetricSnapshot
            -> value_metric_daily
       -> FinalDecision
            -> regular Subscription / Entitlement
            -> or ordered end
```

Pflichtidentitäten:

- Candidate-ID;
- Pilot-ID;
- Organizer-ID;
- Submission- oder Content-ID;
- Reporting-Target-ID;
- externe Anfrage-/Kommunikationsreferenz, falls vorhanden.

## 9. Startpartner-Nebenwirkungen

Mögliche echte Nebenwirkungen:

- Nachricht an Kandidat oder Partner;
- Organizer-Anlage;
- Magic-Link-Mail;
- Pilotberechtigung;
- Submission;
- Veröffentlichung;
- regulärer Stripe-Checkout;
- Deaktivierung oder Ende einer Berechtigung.

Jede Nebenwirkung benötigt:

- stabile Identität;
- Vorherzustand;
- erwartete Mutation;
- Rücklesen;
- unveränderte Nicht-Zielfelder;
- Rollback oder geordneten Cleanup;
- eindeutige Environmentbindung.

Kein späterer Implementierungs-Workpack darf mehrere dieser Nebenwirkungen ohne geschlossenen E2E- und Fehlervertrag zusammenfassen.

## 10. Entwicklungs-, Release- und Beobachtungspfad

```text
Feature-Branch
-> PR nach staging
-> ein Required Check: PR Gate
-> Merge nach staging
-> Deploy to STRATO
-> Build-/HTTP-/Browser-Smoke
-> Publish Deploy Run Status am Commit
-> später Release-PR staging -> main
-> Main-Deploy und Live-Smoke
```

`Publish Deploy Run Status` ist passiv:

- beobachtet ausschließlich `Deploy to STRATO`;
- schreibt `pending`, `success`, `failure` oder `error` auf den betroffenen Commit;
- verlinkt den exakten Actions-Run;
- erzeugt selbst keinen Deploy und verändert keine Fachressource.

Zusätzliche Deploytrigger, Runtimeverification-Workflows und synthetische Folge-Deploys gehören nicht zur Standardarchitektur.

## 11. Dauerhafte Workflowrollen

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

Für Startpartner wird kein neuer dauerhafter GitHub-Workflow angelegt, solange der bestehende Web-/API-/Control-Center-Pfad die Aufgabe übernehmen kann. Fristen und Kontrollpunkte gehören in die fachliche Daten- und Aufgabenlogik, nicht in zusätzliche Repository-Observer.

## 12. Owner-Übersicht

| Domäne | Primäre Owner |
|---|---|
| Arbeitsprozess | `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md` |
| Codex-Routing | `AGENTS.md` |
| Architektur | `docs/architecture/SYSTEM_MAP.md` |
| technische Regeln | `ENGINEERING.md` |
| externe Ressourcen | `docs/external-resource-matrix.md` |
| Deploy/Branchrouting | `.github/workflows/deploy-strato.yml`, `scripts/resolve-deploy-target.sh` |
| Deploy-Run-Auffindbarkeit | `.github/workflows/deploy-run-status.yml`, `scripts/publish_deploy_run_status.py` |
| Control-Center UI | `steuerzentrale/**`, `js/control-center/**` |
| Control-Center Runtime | `api/control-center/**` |
| Anbieterportal | `fuer-veranstalter/**`, `js/organizer-portal.js`, `api/organizer-portal/**` |
| Organizer/Submission/Subscription | `api/**`, `api/sql/**`, Submission-/Anbieter-DB |
| Startpartner öffentliche Anfrage aktuell | `startpartner/**`, `js/startpartner-funnel.js`, Formspree |
| Startpartner fachlicher Zielvertrag | `docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md` |
| Startpartner Kandidat Gate 1/2 | `api/startpartner/**`, `api/sql/008_startpartner_candidates.sql`, `api/sql/010_startpartner_gate2_qualification_capacity.sql`; `control_cases` nur als Projektion |
| Startpartner Pilot künftig | neue eindeutig benannte Pilot-, Berechtigungs-, Kommunikations- und Messowner innerhalb der bestehenden Anbieter-/Submission-Domäne |
| Eventfeed | Deployworkflow, Eventgeneratoren, `api/events/**` |
| Produktziel | `MASTER.md`, `Produktvertrag.md`, `COMMERCIAL_STRATEGY.md` |
| Produktpriorität | `ROADMAP.md` |
| Proofstand | `TEST_STATUS.md` |

## 13. Prüfung vor kritischen Änderungen

1. Wer besitzt den Ursprungswert?
2. Welche Projektionen existieren?
3. Welche Umgebung und Ressource wird verwendet?
4. Was wird konkret gelesen oder geschrieben?
5. Welche Postconditions müssen bestätigt werden?
6. Wie wird ein Teilfehler sichtbar?
7. Wie erfolgt Rollback oder Cleanup?
8. Ist ein anderer Chat oder Workpack am selben Owner aktiv?
9. Welche dauerhafte Dokumentation besitzt die geänderte Realität?
10. Wird ein fachlicher Startpartnerzustand fälschlich nur in `control_cases`, Mail oder Formspree gehalten?
11. Entsteht versehentlich eine Stripe-Subscription oder Zahlungswirkung für den kostenlosen Pilot?
12. Sind Kandidat, Pilot, Organizer, Inhalt und Messung über stabile IDs verbunden?

Ist eine Antwort nicht belegbar, folgt read-only Analyse statt Mutation.

## 14. Startpartner Gate 4 – aktueller kanonischer Staging-Zustand

Dieser Abschnitt ist für den aktuellen Staging-Stand maßgeblich und ersetzt dort die historischen Gate-2-/Zielzustandsaussagen der Abschnitte 2, 3 und 6 bis 9. Der öffentliche Startpartner-Anfragepfad und Live bleiben weiterhin unverändert.

### Dauerhafte Runtime-Owner

| Verantwortung | Kanonischer Owner |
|---|---|
| Kandidat, Qualifikation, Entscheidung, Reservierung, Warteliste und Operations | `api/startpartner/**` sowie Migrationen `008` bis `010`; `control_cases` bleibt nur operative Projektion |
| ausdrückliche Pilotbedingungen, Pilot, Scopes und kostenlose Pilotberechtigung | Gate-3-Owner in `api/startpartner/**` und Migration `011` |
| Onboarding | `startpartner_pilot_onboarding_items` |
| Pilotinhalt und Attribution | `startpartner_pilot_content_links` mit bestehenden `organizers`- und `submissions`-Ownern |
| Messpreflight | `startpartner_pilot_measurement_preflights`; Metrikowner bleibt `value_metric_daily` |
| Distributionsbereitschaft | `startpartner_pilot_distribution_commitments` |
| Pilotnutzung | `startpartner_pilot_usages` |
| lokales Aktivierungs- und Enddatum | `startpartner_pilots.activation_date_local` und `startpartner_pilots.planned_end_date` |
| Organizer-Portal-Readback | `api/organizer-portal/pilot.php` und additive Gate-4-Dashboarddarstellung |
| reguläre Zahlung, Abos und Veröffentlichungsberechtigungen | unverändert die bestehenden Stripe-, Subscription- und Publication-Owner; Gate 4 schreibt dort nicht regulär |

Migration `012_startpartner_gate4_onboarding_content_activation` ergänzt diese Owner idempotent und wurde auf Staging erfolgreich angewendet.

### Gate-4-Datenfluss

```text
qualifizierter, angenommener und bestätigter Kandidat
-> bestehender Organizer und Gate-3-Pilot
-> sieben Pilot-Scopes und fail-closed Pilotberechtigung
-> 14 Onboardingpunkte
-> bestehende Submission als Event oder Aktivität verknüpfen
-> redaktionellen Status zurücklesen
-> Messpreflight auf value_metric_daily vorbereiten
-> Distribution verbindlich vorbereiten
-> Pilotnutzung dem Inhalt zuordnen
-> Aktivierungsdatum und sechsmonatiges Enddatum atomar festlegen
-> geschützter Readback in Steuerzentrale und Organizer-Portal
```

Die Aktivierung ist operations-idempotent: ein identischer Replay liefert das gespeicherte Ergebnis; geänderter Payload oder stale Revision endet kontrolliert mit HTTP `409`. Eine Aktivierung erzeugt keine reguläre Subscription, keinen Stripe-Checkout und keine automatische Veröffentlichung.

### Belegter Staging-Lifecycle und Rückbau

- Deploy Run `30714196723`: kontrollierter authentifizierter No-Send-Lifecycle auf Build `9549ad072da0` erfolgreich;
- Migration `012` angewendet;
- vollständiger Gate-4-Datenfluss einschließlich Event- und Activity-Kompatibilität, 14 Onboardingpunkten, Messowner, Distribution, Pilotnutzung, Kalenderdaten sowie Replay-/Konfliktgrenzen belegt;
- Cleanup vollständig: `residue.total = 0`;
- gesperrte Runtime-Owner vor und nach dem Lauf unverändert;
- Startpartner-Kapazität vor und nach dem Lauf unverändert;
- keine Mail- oder Magic-Link-Zustellung, keine Partnerkommunikation, keine Stripe-, Zahlungs-, Abo-, reguläre Entitlement- oder automatische Veröffentlichungswirkung;
- Deploy Run `30714760725`: einmaliger Lifecycle-Marker kontrolliert `1 -> 0` entfernt, ohne den Lifecycle erneut auszuführen;
- PR `#265`, Merge-SHA `37c31add98f015f86086a7e0d541434f1bd1ca46`: vollständiger Rückbau aller temporären Gate-4-Evidence-Komponenten;
- Removal-Deploy `30715216900`, Build `37c31add98f0`: exakt drei temporäre Evidence-Dateien gelöscht, HTTP-Smoke erfolgreich, Browser-Smoke 26/26 OK, 0 Fehler, 0 Warnungen;
- dauerhafte Negativgrenze verbietet die drei ehemaligen Evidence-Dateien sowie Marker- und Lock-Tokens.

Die drei ehemaligen Evidence-URLs müssen vor Workpack-Schließung zusätzlich durch einen tatsächlichen read-only HTTP-Abruf jeweils mit Status `404` bestätigt werden. Der erfolgreiche SFTP-Löschplan und die Dateiabwesenheit allein werden nicht als HTTP-Status ausgegeben oder umgedeutet.

`main` und Live wurden durch Lifecycle, Marker-Cleanup und Evidence-Rückbau nicht verändert. Der dokumentierte Gate-4-Runtime-Endstand auf Staging ist Merge-SHA `37c31add98f015f86086a7e0d541434f1bd1ca46` mit Removal-Deploy `30715216900`; ein späterer reiner Dokumentationsdeploy ändert diese fachliche Runtime-Evidence nicht.
