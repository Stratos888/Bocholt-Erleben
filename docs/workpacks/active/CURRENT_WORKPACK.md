# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** WP-2 – Runtime-Truth und read-only Preflight
- **Status:** Gate A abgeschlossen; Implementierung auf eigenem Branch gestartet
- **Risikoklasse:** R2
- **Aktuelles Gate:** B – Bauen und statisch beweisen
- **Erforderliche Evidence:** E1, E2 und anschließend E3
- **Ausgangs-SHA von `staging`:** `a2e89c1a1a10e0a9cdf9c8e9da4d01212f369df6`
- **Branch:** `agent/wp2-runtime-truth-preflight`
- **Draft-PR:** wird nach dem ersten Commit angelegt

## Belegter Ist-Zustand

- `api/control-center/action.php` ist der reale schreibende Aktionsendpoint.
- Bei `inbox_feed + approve` wird anhand von `be_cc_events_tab_name()` zwischen `be_cc_writeback_staging_inbox_approve_verified` und `be_cc_writeback_inbox_approve_verified` gewählt.
- Staging ist fachlich auf `Inbox_Staging -> Events_Staging` gebunden; Live auf `Inbox -> Events`.
- Ablehnen und Zurückstellen verwenden `be_cc_writeback_inbox_decision_direct`.
- Der Deploy veröffentlicht bereits `meta/build.txt` und `meta/deploy-manifest.json`.
- Vor einer Aktion ist über die deployte Runtime derzeit nicht sichtbar, welcher Build, Host, Endpoint, Environment-Vertrag, Sheet-Fingerprint, Tab-Vertrag und Writer tatsächlich verwendet würden.
- Ein grüner PR oder Deploy erreicht daher höchstens E2 und belegt den realen Writebackpfad nicht.

## Zielzustand

Ein geschützter read-only Preflight weist vor jeder Inbox-Entscheidung aus:

- Build-SHA und Manifest-Konsistenz;
- Host, Requestpfad und Endpointversion;
- konfigurierte und aufgelöste Umgebung;
- Sheet-Fingerprint, Quelltab und Zieltab;
- stabile Fall- und Quellidentität;
- tatsächlich ausgewählten Writer;
- geplante Operationsschritte und Postconditions;
- eindeutige Blocker bei jeder Abweichung;
- `mutation: false` als harter Vertragsbestandteil.

Ein automatischer Staging-Smoke muss den eingefrorenen CityArt-Fall read-only finden und den Vertrag `Inbox_Staging -> Events_Staging` gegen den gerade deployten Build beweisen.

## Erlaubter Scope

- `api/control-center/_runtime_preflight.php`
- `api/control-center/preflight.php`
- `api/control-center/action.php` ausschließlich für einen gemeinsam genutzten, verhaltensgleichen Writer-Resolver
- `tests/control_center_runtime_preflight_contract_test.php`
- `.github/workflows/control-center-validation.yml`
- `.github/workflows/deploy-strato.yml` ausschließlich für den read-only E3-Smoke und dessen Artefakt
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- `docs/evidence/**` für den abschließenden E3-Nachweis

## Gesperrter Scope

- keine Änderung der Writerimplementierungen;
- keine Änderung des fachlichen Writeback-Ablaufs;
- keine Sheetmutation;
- kein CityArt-Klick;
- kein synthetischer Schreibtest;
- kein Retry-/Transaktionsumbau;
- kein WP-3-Code;
- keine Apps-Script-Umstellung;
- keine Live-Schreibaktion;
- kein Feature-Branch-Deploy;
- keine Vermischung mit dem Main-/Staging-Historienabgleich.

## Externe Ressourcen und Locks

- `Inbox_Staging`: read-only Preflight; keine Mutation.
- `Events_Staging`: read-only Zielprüfung; keine Mutation.
- `Inbox` und `Events`: nicht verwenden; read-only Unverändertheitsvertrag.
- STRATO Staging: erst nach sequenzieller Integration nach `staging` deployen.
- Ressourcen-Lock auf Inbox-/Events-Writeback bleibt bis WP-4 bestehen.
- Kein anderer schreibender Workpack ist zulässig.

## Definition of Done

- [ ] Writer-Auflösung ist zwischen Action und Preflight identisch und zentral belegt.
- [ ] Preflight ist review-authentifiziert, POST-only und mutiert weder DB noch Sheets.
- [ ] Source- und Target-Identität werden read-only gegen Staging geprüft.
- [ ] Build, Manifest, Host und Environment werden fail-closed validiert.
- [ ] Contract- und Syntax-Tests sind grün.
- [ ] PR-Gates und Control-Center-Validation sind grün.
- [ ] Nach Staging-Integration belegt ein automatisierter Smoke E3 am CityArt-Fall.
- [ ] Das E3-Artefakt enthält keine Secrets oder vollständige Sheet-ID.
- [ ] Danach ist die Root Cause beziehungsweise die kleinste verbleibende Evidence-Lücke eindeutig dokumentiert.

## Nächster erlaubter Schritt

Den kleinsten read-only Runtime-Preflight implementieren, statisch validieren und als Draft-PR gegen `staging` bereitstellen. Noch keine externe Mutation und kein CityArt-Klick.
