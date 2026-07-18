# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** WP-2 – Runtime-Truth und read-only Preflight
- **Status:** Implementierung vollständig; E2 validiert; Staging-Integration und E3 stehen aus
- **Risikoklasse:** R2
- **Aktuelles Gate:** B – Bauen und statisch beweisen
- **Erforderliche Evidence:** E1, E2 und anschließend E3
- **Ausgangs-SHA von `staging`:** `a2e89c1a1a10e0a9cdf9c8e9da4d01212f369df6`
- **Branch:** `agent/wp2-runtime-truth-preflight`
- **Draft-PR:** #91

## Belegter Ist-Zustand

- `api/control-center/action.php` ist der reale schreibende Aktionsendpoint.
- Bei `inbox_feed + approve` wird anhand des aufgelösten Events-Tabs zwischen dem isolierten Staging-Writer und dem bestehenden Live-Writer gewählt.
- Staging ist fachlich auf `Inbox_Staging -> Events_Staging` gebunden; Live auf `Inbox -> Events`.
- Ablehnen und Zurückstellen verwenden den direkten verifizierten Inbox-Writer.
- Der Deploy veröffentlicht bereits `meta/build.txt` und `meta/deploy-manifest.json`.
- Vor WP-2 war über die deployte Runtime nicht sichtbar, welcher Build, Host, Endpoint, Environment-Vertrag, Sheet-Fingerprint, Tab-Vertrag und Writer tatsächlich verwendet würden.
- Ein grüner PR oder Deploy erreicht höchstens E2 und belegt den realen Writebackpfad nicht.

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
- `tests/control_center_staging_events_isolation_contract_test.php` ausschließlich zur Anpassung des bestehenden Routing-Nachweises an den gemeinsamen Resolver
- `.github/workflows/control-center-validation.yml`
- `.github/workflows/control-center-runtime-preflight.yml`
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

## Erreichte Evidence

- **E1:** aktueller Datenfluss, Environment-Tupel, Writer-Auflösung und Scope sind im Code und PR nachvollziehbar.
- **E2:** PHP-Syntax, Runtime-Preflight-Vertrag, Staging-Isolationsvertrag, Control-Center-Validation, Contract Diagnostics, Editorial Contracts und Project Guardrails sind grün.
- **E3:** noch offen; wird erst nach Merge und Staging-Deploy durch den automatisierten Runtime-Preflight-Smoke hergestellt.

## Definition of Done

- [x] Writer-Auflösung ist zwischen Action und Preflight identisch und zentral belegt.
- [x] Preflight ist review-authentifiziert, POST-only und mutiert weder DB noch Sheets.
- [x] Source- und Target-Identität werden im Plan read-only geprüft.
- [x] Build, Manifest, Host und Environment werden fail-closed validiert.
- [x] Contract- und Syntax-Tests sind grün.
- [ ] PR Gate ist nach dem finalen Dokumentationscommit grün.
- [ ] Nach Staging-Integration belegt ein automatisierter Smoke E3 am CityArt-Fall.
- [ ] Das E3-Artefakt enthält keine Secrets oder vollständige Sheet-ID.
- [ ] Danach ist die Root Cause beziehungsweise die kleinste verbleibende Evidence-Lücke eindeutig dokumentiert.

## Nächster erlaubter Schritt

Finale PR-Gates prüfen, PR #91 nach `staging` integrieren und anschließend ausschließlich den automatisierten read-only E3-Smoke auswerten. Noch keine externe Mutation und kein CityArt-Klick.
