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

Im aktuellen Zustand existiert nur eine öffentliche Anfrage über Formspree und ein dokumentierter Zielzustand.

Im Zielzustand umfasst die Domäne:

- Kandidat und Deduplizierung;
- Qualifizierung und Aufnahmeentscheidung;
- Kapazität, Reservierung und Warteliste;
- Pilotbedingungen und Bestätigung;
- Pilot, Serviceumfang und kostenlose Berechtigung;
- Organizer- und Portalverknüpfung;
- Onboarding, Aktivierung und Laufzeit;
- Inhalts- und Quellenzuordnung;
- Wirkungsmessung;
- Kommunikation und Kontrollpunkte;
- Abschluss, Konversion oder geordnetes Ende.

Die fachliche Startpartner-Domäne ist Source of Truth. `control_cases` ist nur operative Aufgaben- und Entscheidungsprojektion.

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
| Startpartner-Anfrage aktuell | Formspree-Übermittlung | E-Mail/Formspree-Ansicht; kein kanonischer eigener Kandidat |
| Startpartner-Kandidat im Ziel | eigene fachliche Kandidatentabelle | Control-Center-Aufgabe, Kommunikation, Entscheidung |
| Startpartner-Pilot im Ziel | eigene fachliche Pilottabelle | Portalstatus, Berechtigung, Kontrollpunkte, Abschluss |
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

Belegte Grenze:

- keine automatische Organizer-Anlage;
- keine Pilotvereinbarung;
- keine kostenlose Berechtigung;
- keine Stripe-Subscription;
- keine Veröffentlichung;
- keine eigene strukturierte Kandidaten-Source-of-Truth.

Formspree ist ein Übergangswriter und muss vor dem Ziel-Cutover als externe Ressource behandelt werden.

## 7. Startpartner-Pfad – Zielzustand

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
  -> CandidateDecision
  -> CandidateCommunication
  -> control_case projection

accepted candidate
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
| PR-Prüfung | `.github/workflows/pr-gate.yml`, `scripts/validate_pr_contract.py`, `scripts/validate-repo.sh` |
| Deploy/Branchrouting | `.github/workflows/deploy-strato.yml`, `scripts/resolve-deploy-target.sh` |
| Deploy-Run-Auffindbarkeit | `.github/workflows/deploy-run-status.yml`, `scripts/publish_deploy_run_status.py` |
| Control-Center UI | `steuerzentrale/**`, `js/control-center/**` |
| Control-Center Runtime | `api/control-center/**` |
| Anbieterportal | `fuer-veranstalter/**`, `js/organizer-portal.js`, `api/organizer-portal/**` |
| Organizer/Submission/Subscription | `api/**`, `api/sql/**`, Submission-/Anbieter-DB |
| Startpartner öffentliche Anfrage aktuell | `startpartner/**`, `js/startpartner-funnel.js`, Formspree |
| Startpartner fachlicher Zielvertrag | `docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md` |
| Startpartner Kandidat/Pilot künftig | neue eindeutig benannte fachliche API-/SQL-Owner innerhalb der bestehenden Anbieter-/Submission-Domäne |
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
