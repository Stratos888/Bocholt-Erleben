# Domain-Router: Control Center und interne Steuerzentrale

Diese Datei ist der aktuelle Einstieg für Aufgaben an Control Center, Inbox-Review, Source-Reconciliation und Writeback. Operativer Status und Locks stehen ausschließlich in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## Ziel

Anzeige, Entscheidung, führende Quelle, Persistenz und Rücklesen müssen zu einem eindeutigen Endzustand führen. Eine sichtbare Erfolgsmeldung ohne bestätigten Quell- und Zielzustand ist unvollständig.

## Autoritätsreihenfolge

1. `CURRENT_WORKPACK.md`
2. `docs/architecture/SYSTEM_MAP.md`
3. `docs/external-resource-matrix.md`
4. aktuelle Backend-, UI- und Datenowner im aktuellen Ref
5. Evidence oder Forensik nur für den konkreten Fall

## Aktuelle Owner

| Ebene | Owner |
|---|---|
| Arbeitsmodell | `AI_ENTRYPOINT.md` |
| Architektur und Datenhoheit | `docs/architecture/SYSTEM_MAP.md` |
| externe Ressourcen | `docs/external-resource-matrix.md` |
| Workflowpolicy | `docs/github-actions-trigger-policy.md` |
| PR-Prüfung | `.github/workflows/pr-gate.yml`, `scripts/validate-repo.sh` |
| Deploy | `.github/workflows/deploy-strato.yml` |
| Backendverträge | `api/control-center/_editorial_contracts.php` |
| Darstellung | `api/control-center/_presentation.php`, `js/control-center/review-render.js` |
| Aktionen | `js/control-center/review-actions.js`, `api/control-center/action.php` |
| Source-Reconciliation | `api/control-center/_source_reconciliation.php` |
| verifizierter Writeback | `api/control-center/_verified_source_writeback.php`, `api/control-center/_staging_events_writeback.php` |
| fallbezogener Preflight | `api/control-center/preflight.php`, `api/control-center/_runtime_preflight.php` |

## Aufgabenbezogener Lesepfad

| Aufgabe | Zusätzlich lesen |
|---|---|
| Review-UI oder Aktion | Informationsarchitektur, Screenvertrag, Render-/Action-Owner |
| falscher oder verschwundener Zustand | führende Quelle, Reconciliation, lokale Projektion und API-Antwort |
| externer Write | Ressourcenmatrix, Writer, stabile Identität, Vorherzustand und Rücklesen |
| neuer Falltyp | Vorgangskatalog, Backendvertrag und vollständiger Folgeprozess |
| Workflow oder Deploy | Triggerpolicy und genau die betroffene Workflowdatei |

## Harte Regeln

- Genau ein schreibender Control-Center-Workpack.
- Staging und Live verwenden ausschließlich ihre dokumentierten Ressourcen.
- UI-Erfolg beweist keinen Writeback; ein Write beweist keinen korrekten Endzustand.
- Vor einem externen Write wird der konkrete fallbezogene Preflight gelesen.
- Jeder Write wird unmittelbar aus der führenden Quelle zurückgelesen.
- Beim ersten unerwarteten Verhalten wird nicht erneut geschrieben.
- Keine zweite Resolver-, Writer-, Observer- oder Workflow-Schicht ohne belegte dauerhafte Notwendigkeit.
- Temporäre synthetische Testinfrastruktur wird nach dem Nachweis entfernt.

## Fachliche Referenzen

Nur bei Bedarf lesen:

- `STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md`
- `STEUERZENTRALE_INFORMATIONARCHITEKTUR.md`
- `STEUERZENTRALE_SCREENVERTRAG.md`
- `STEUERZENTRALE_VORGANGSKATALOG.md`
- `STEUERZENTRALE_ABNAHMEMATRIX.md`
- `docs/steuerzentrale-backlog-roadmap-vertrag.md`

Freeze-, frühere Implementierungs-, E3-/E4- und Einzelfalldokumente sind keine aktuellen Arbeitsrouter. Git bewahrt ihre Historie.
