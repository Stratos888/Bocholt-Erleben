# Semantische Dokumentationsklassifikation und Control-Center-Routing

Datum: 2026-07-19  
Status: abgeschlossen nach grünem Integrations-Gate  
Risikoklasse: R2, weil die read-only Governance-Prüfung verschärft wird; keine Produkt-, Runtime- oder externe Datenänderung

## Anlass

Die erste Vollinventur war strukturell grün, behandelte jedoch unbekannte Dateien unter `docs/**` pauschal als `supporting_documentation`. Damit war nicht für jede Datei bewiesen, ob sie aktueller Vertrag, Zielreferenz, Evidence oder Historie ist. Zudem fehlte vor dem nächsten technischen Workpack ein kompakter Control-Center-Domain-Router.

## Ziel

- jede getrackte Markdown-Datei besitzt eine exakte oder klar pfadbasierte Rolle;
- unbekannte Dateien unter `docs/**` blockieren künftig die Integration;
- historische Handoffs, Deploy-Notizen und alte Workpacks bilden keinen aktuellen Arbeitsweg;
- Control-Center-Aufgaben beginnen über einen kompakten aktuellen Router;
- Inventur-`--check` verlangt 0 Fehler und 0 Warnungen.

## Umgesetzter Scope

- `docs/domains/control-center.md` als aktueller Domain-Router;
- explizite semantische Rollen für die zuvor 21 generisch klassifizierten Dokumente;
- unbekannte Root- und `docs/**`-Dateien als Gate-Fehler;
- Schutz vor historischen Markdown-Routen aus aktuellen Routern und Verträgen;
- strenger `--check` auf Fehler und Warnungen;
- aktualisierte Dokumentationslandkarte und Rollenregister;
- korrigierter integrierter Status in `CURRENT_WORKPACK.md`, `TEST_STATUS.md` und dem vorherigen completed Workpack;
- `docs/ingest-bridge.md` als kurzer Alias statt doppeltem Vertragsinhalt.

## Bewusste Abgrenzung

- keine Massenverschiebung historischer Dateien;
- keine inhaltliche Umschreibung historischer Belege;
- keine Control-Center-Workflowänderung;
- kein E4, kein CityArt-Fachfall und keine externe Mutation;
- keine Änderung von Produktmechanik oder öffentlicher UI.

## Evidence

### E1

- begrenzter Dokumentations-/Governance-Diff;
- alle vorher generischen Pfade sind explizit klassifiziert;
- Control-Center-Router trennt aktuelle Owner, Zielreferenzen und Historie.

### E2

Vor Integration müssen grün sein:

- Python-Syntax der Governance-Skripte;
- `python3 scripts/report-documentation-inventory.py --check` mit 0 Fehlern und 0 Warnungen;
- `python3 scripts/audit-documentation-governance.py`;
- `Project Guardrails`;
- stabiler `PR Gate`.

## Folgezustand

Nach Integration ist kein Dokumentations-Workpack aktiv. Als nächstes wird ausschließlich der queued R2-Workpack zur Control-Center-Workflow-Konsolidierung gegen den aktuellen `staging`-Stand neu validiert und aktiviert.
