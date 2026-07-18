# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** WP-2 – Runtime-Truth und read-only Preflight
- **Status:** Runtime-Preflight und Deploy-Observability integriert; belegter Deploy-Blocker behoben; finaler E3-Lauf steht aus
- **Risikoklasse:** R2
- **Aktuelles Gate:** C – reale Runtime beweisen
- **Erforderliche Evidence:** E1 und E2 erreicht; E3 jetzt ausführen
- **Aktueller `staging`-Stand vor diesem Doku-Commit:** `3eb0b99c8e4e0b7cbd32b0cf8a6fccab1d85e5d7`
- **Führende PRs:** #91 Runtime-Preflight, #92/#94 Evidence-Status, #98 Deploy-Observer, #99 Deploy-Fix; alle gemergt

## Belegter Runtime- und Deploy-Befund

- `api/control-center/action.php` ist der reale schreibende Aktionsendpoint.
- Bei `inbox_feed + approve` wird über einen gemeinsamen reinen Resolver zwischen dem isolierten Staging-Writer und dem bestehenden Live-Writer gewählt.
- Staging ist auf `Inbox_Staging -> Events_Staging` gebunden; Live auf `Inbox -> Events`.
- Der geschützte POST-Endpunkt `api/control-center/preflight.php` erzeugt ausschließlich einen read-only Operationsplan mit `mutation: false`.
- Der Deploy veröffentlicht `meta/build.txt` und `meta/deploy-manifest.json`.
- Der erste exakt beobachtete Deploy-Run `29639923944` scheiterte im Schritt `Export environment-safe Events feed to data/events.tsv`.
- STRATO-Staging blieb dadurch auf Build `3b5795f07771`, dem Stand vor Einführung des Staging-Overlays.
- Read-only Prüfung des führenden Sheets ergab:
  - `Events` und `Events_Staging` besitzen denselben 11-spaltigen Header;
  - `Events_Staging` ist korrekt header-only;
  - `Events` enthält 199 nichtleere Eventzeilen;
  - keine doppelte Event-ID;
  - 15 legitime Gruppen mit gemeinsam verwendeter Quellen-URL, insbesondere Serientermine und gemeinsame Programmseiten.
- Root Cause: `merge_event_rows` behandelte jede doppelte Basis-URL pauschal als Fehler, obwohl Event-IDs eindeutig waren.
- PR #99 korrigiert ausschließlich diese Mehrdeutigkeitslogik:
  - IDs bleiben kanonisch und eindeutig;
  - gemeinsame Basis-URLs sind zulässig;
  - ein Overlay mit passender bestehender ID kann genau den zugehörigen Termin aktualisieren;
  - eine mehrdeutige URL ohne passende ID bleibt fail-closed blockiert.
- Der anschließende Staging-Deploy für Commit `3eb0b99c8e4e` wurde durch `deploy/staging-observed` erfolgreich bestätigt.

## Zielzustand von WP-2

Der deployte Runtime-Preflight muss für den eingefrorenen CityArt-Fall ohne Mutation ausweisen und bestätigen:

- Build-SHA und Manifest-Konsistenz;
- Host `staging.bocholt-erleben.de`;
- Endpoint `/api/control-center/preflight.php`;
- konfigurierte und aufgelöste Umgebung `staging`;
- Quelltab `Inbox_Staging`;
- Zieltab `Events_Staging`;
- Writer `be_cc_writeback_staging_inbox_approve_verified`;
- eindeutige Fall- und Quellidentität;
- read-only Zielauflösung;
- Live-Ressourcen `not_used`;
- `mutation: false`;
- keine Blocker.

## Erlaubter Scope

Der Implementierungs- und Diagnose-Scope von WP-2 umfasst ausschließlich:

- `api/control-center/_runtime_preflight.php`
- `api/control-center/preflight.php`
- `api/control-center/action.php` nur für den gemeinsamen verhaltensgleichen Writer-Resolver
- zugehörige Runtime-, Isolations- und Merge-Vertragstests
- `.github/workflows/control-center-validation.yml`
- `.github/workflows/control-center-runtime-preflight.yml`
- `.github/workflows/staging-deploy-observer.yml`
- `scripts/merge_events_overlay.py` ausschließlich für den belegten Deploy-Blocker
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- `docs/evidence/**` für den Abschlussnachweis

## Gesperrter Scope und Ressourcen-Locks

- keine Änderung der Writerimplementierungen;
- keine Änderung des fachlichen Writeback-Ablaufs;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- kein CityArt-Klick;
- kein synthetischer Schreibtest;
- kein Retry-/Transaktionsumbau;
- kein WP-3-Code;
- keine Apps-Script-Umstellung;
- keine Live-Schreibaktion;
- kein Feature-Branch-Deploy;
- kein Release nach `main` in WP-2;
- Ressourcen-Lock auf Inbox-/Events-Writeback bleibt bis WP-4 bestehen.

## Erreichte Evidence

- **E1:** Datenfluss, Environment-Tupel, Writer-Auflösung, Deploypfad und Scope sind im aktuellen Code und in den gemergten PRs nachvollziehbar.
- **E2:** PHP-Syntax, Runtime-Preflight-Vertrag, Staging-Isolationsvertrag, Overlay-Merge-Vertrag, Control-Center-Validation, Contract Diagnostics, Editorial Contracts, Project Guardrails und PR Gates sind grün.
- **Deploy-Evidence:** Der konkrete fehlgeschlagene Deploy-Run und seine Root Cause wurden ermittelt; der minimal korrigierte Deploy für `3eb0b99c8e4e` ist erfolgreich.
- **E3:** noch offen; dieser notwendige Status-Commit startet Deploy und Runtime-Preflight für exakt dieselbe neue `staging`-SHA.

## Definition of Done

- [x] Writer-Auflösung ist zwischen Action und Preflight identisch und zentral belegt.
- [x] Preflight ist review-authentifiziert, POST-only und mutiert weder DB noch Sheets.
- [x] Source- und Target-Identität werden read-only geprüft.
- [x] Build, Manifest, Host und Environment werden fail-closed validiert.
- [x] Contract-, Syntax- und Merge-Tests sind grün.
- [x] Deploy-Fehler ist mit exaktem Run und belegter Root Cause erklärt.
- [x] Minimaler Deploy-Fix ist integriert und real deployt.
- [ ] Finaler Deploy für diesen Status-Commit ist erfolgreich.
- [ ] `control-center/runtime-preflight-e3` ist für dieselbe SHA grün.
- [ ] Das E3-Artefakt enthält keine Secrets und keine vollständige Sheet-ID.
- [ ] WP-2 wird mit dokumentierter Root-Cause-Entscheidung abgeschlossen.

## Nächster erlaubter Schritt

Diesen reinen Status-Commit nach `staging` integrieren und ausschließlich die beiden read-only Nachweise auswerten:

1. `deploy/staging-observed` muss für dieselbe SHA erfolgreich sein.
2. `control-center/runtime-preflight-e3` muss CityArt mit `Inbox_Staging -> Events_Staging`, dem isolierten Writer und `mutation=false` bestätigen.

Bei jeder Abweichung sofort stoppen. Keine externe Mutation und kein CityArt-Klick.
