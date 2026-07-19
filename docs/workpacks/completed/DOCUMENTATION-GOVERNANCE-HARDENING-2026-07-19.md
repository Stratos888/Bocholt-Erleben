# Dokumentations-Governance-Härtung

Datum: 2026-07-19  
Status: abgeschlossen und in `staging` integriert  
Risikoklasse: R2, weil der read-only CI-Vertrag erweitert wurde; keine Produkt- oder Runtimeänderung

## Anlass

Die zentrale Dokumentationssteuerung war bereits konsolidiert, aber der vollständige Markdown-Bestand wurde noch nicht dauerhaft inventarisiert. Ein erster Draft vermischte die Diagnose mit Massenverschiebungen, einem selbstschreibenden Workflow und einer fachfremden Datenänderung. Dieser Versuch wurde ohne Merge beendet.

## Zielzustand

Eine neue KI kann mit minimaler Lesemenge deterministisch feststellen:

- welche Dateien kanonisch, unterstützend, historisch oder archiviert sind;
- welche Datei den operativen Status besitzt;
- welche Owner für eine Aufgabe gelesen und geändert werden dürfen;
- welche Dokumentationsänderung zu einer Code-, Produkt-, Architektur- oder Evidence-Änderung gehört;
- ob der gesamte getrackte Markdown-Bestand strukturell konsistent ist.

## Umgesetzter Scope

- `AI_ENTRYPOINT.md`: verbindlicher Dokumentations- und Implementierungsvertrag;
- `docs/README.md`: aufgabenbezogene Lesepfade und exakter Pflegeablauf;
- `docs/DOCUMENT_REGISTRY.md`: Rollen, Pfadklassen und Änderungsmatrix;
- `docs/domains/visual-system.md` und `docs/domains/event-search-system.md`: kompakte Router vor großen Legacy-Referenzen;
- `scripts/report-documentation-inventory.py`: vollständige read-only Inventur;
- `scripts/audit-documentation-governance.py`: Schutz der kanonischen Dateien und des CI-Vertrags;
- `.github/workflows/project-guardrails.yml`: read-only Ausführung und Inventurartefakt;
- `.github/pull_request_template.md`: verpflichtende Dokumentationsauswirkung;
- `README.md` und `CURRENT_WORKPACK.md`: eindeutiges Routing und Folgezustand.

## Bewusste Abgrenzung

- keine automatische Verschiebung bestehender Dokumente;
- keine Mutation an Produkt-, Event-, Activity-, Runtime- oder externen Daten;
- kein Workflow mit `contents: write`;
- kein Commit, Push oder Force-Push aus einem Guardrail-Workflow;
- keine Umdeutung historischer Detailreferenzen ohne fachliche Prüfung.

Dateimigrationen sind nur als eigener atomarer Workpack zulässig, wenn der Inventurbericht einen konkreten Nutzen und vollständige Referenzierbarkeit belegt.

## Evidence

### E1

- klar begrenzter Dokumentations-/CI-Diff;
- Rollen- und Pfadvertrag explizit;
- keine fachfremden Dateien im Scope.

### E2

Erfolgreich ausgeführt wurden:

- `python3 scripts/report-documentation-inventory.py --check`;
- `python3 scripts/audit-documentation-governance.py`;
- bestehende `Project Guardrails`-Prüfungen;
- stabiler `PR Gate`.

Der Workflow veröffentlichte `build/documentation-inventory.json` als read-only Artefakt. Der Integrationsstand wies 76 getrackte Markdown-Dateien, 0 Fehler und 0 Warnungen aus; exakt benannte Legacy-Marker wurden als kontrollierte Notes geführt.

## Ergebnis und Folge

Die strukturelle Governance wurde integriert. Eine anschließende semantische Vollklassifikation beseitigte den verbliebenen generischen `docs/**`-Catch-all und ergänzte den Control-Center-Router. Nach beiden Paketen ist kein Dokumentations-Workpack aktiv. Der nächste zulässige technische Schritt ist die Control-Center-Workflow-Konsolidierung; externe Ressourcen-Locks bleiben bestehen.
