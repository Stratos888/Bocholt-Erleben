# Architekturentscheidung: Workflow-Konsolidierung vor E4

Stand: 2026-07-18

Status: verbindliche Kurskorrektur

## Entscheidung

Das Grundmodell bleibt richtig: ein primärer Ausführungs-Chat, ein aktiver Workpack, ein Branch/PR, isolierte Staging-Ressourcen, E3 vor E4 und ein synthetischer Test vor einem echten Fachfall.

Die GitHub-Actions-Umsetzung ist jedoch zu stark geschichtet. Vor E4 wird sie konsolidiert. Bis dahin gelten:

- kein E4-Lauf;
- kein CityArt-Klick;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- kein zusätzlicher Trigger-, Observer- oder One-off-Workflow;
- kein vorsorglicher WP-3-Umbau.

## Belegter Befund

Im Control-Center-Umfeld existieren überlappende Schichten: `PR Gate`, `Project Guardrails`, `Control Center Validation`, `Control Center Editorial Contracts`, `Control Center Contract Diagnostics`, `Control Center Runtime Preflight`, `Staging Deploy Observer`, `Control Center E4 Contract`, `Control Center E4 Synthetic Proof` und `Deploy to STRATO`.

Mehrere fachliche Tests werden mehrfach ausgeführt. Zusätzliche Workflows beobachten den Deploy oder warten auf denselben Buildmarker. Das erzeugt redundante CI, unübersichtliche Actions, längere Nachweisketten und reine Status-PRs zum erneuten Auslösen von Deploy/E3.

Der konkrete Stop-the-line-Befund: `Control Center E4 Synthetic Proof` besitzt nur `workflow_dispatch` und existiert nur auf `staging`. Die bestehende Trigger-Policy dokumentiert bereits, dass ein manueller Button nur zuverlässig ist, wenn die Workflow-Datei auf dem Default-Branch vorhanden ist. Der fehlende Button war daher kein Bedienfehler. E1, E2, Deploy und E3 bleiben gültig; E4 wurde nicht gestartet.

## Zielarchitektur

Die dauerhafte Struktur soll auf fünf klare Ebenen reduziert werden:

1. **PR Gate** – Branchregeln und Aggregation der tatsächlich gestarteten Checks.
2. **Project Guardrails** – Architektur-, Governance-, Routing- und Ressourcenverträge.
3. **Control Center CI** – ein konsolidierter fachlicher Prüfpfad ohne doppelte Testpakete.
4. **Deploy to STRATO** – einziger Deploypfad für `staging` und `main`.
5. **Staging Verification** – ein autoritativer Post-Deploy-Pfad für read-only E3 und ausdrücklich freigegebene synthetische E4-Tests.

Content-, Growth-, Inbox-, KI- und Zeitplan-Workflows bleiben fachlich getrennt.

## Verbindliche Regeln

- Kein neuer Control-Center-Workflow vor vollständiger Trigger- und Überschneidungsmatrix.
- Bestehende Workflows erst nach Inventur von Triggern, Checks, Secrets, Artefakten, Statusnamen und Ruleset-Abhängigkeiten ersetzen oder entfernen.
- Ein zentraler Workflow muss Parallelpfade ablösen, nicht ergänzen.
- `workflow_dispatch` gilt nur als Operatorpfad, wenn seine tatsächliche Bedienbarkeit vor Integration belegt ist.
- Ein Workflow nur auf einem Nicht-Default-Branch darf nicht auf einen erwarteten `Run workflow`-Button angewiesen sein.
- Reine Dokumentationsänderungen lösen keinen Runtime-Preflight aus, sofern sie keinen deployrelevanten Vertrag ändern.
- Deploy-Evidence stammt möglichst direkt aus dem Deploy oder genau einem nachgelagerten Verifikationspfad.
- Nutzeraktionen ersetzen keine technische Integration oder fehlende Toolfähigkeit.

## Status von E4 und CityArt

- E3 ist belegt.
- E4 ist nicht gestartet.
- Keine synthetischen `be-e4-synthetic`-Zeilen sind vorhanden.
- CityArt steht unverändert auf `review`.
- Kein CityArt-Event wurde in `Events_Staging` erzeugt.
- Live-Ressourcen blieben unverändert.

## Offene PR-Grenzen

- PR #93 bleibt ein unabhängiger Dokumentationsentwurf und muss vor späterer Integration gegen den aktuellen `staging`-Stand synchronisiert werden.
- PR #102 zielt aus einem Feature-Branch direkt auf `main` und entspricht nicht dem verbindlichen Releasepfad `staging -> main`. Er darf in dieser Form nicht gemergt werden.

## Nächste Entscheidung

Nach der Konsolidierung wird genau ein synthetischer E4-Lauf ausgeführt. Nur eine dort konkret belegte Wiederaufnahmelücke rechtfertigt einen minimalen WP-3-Fix. Welche Workflow-Dateien zusammengeführt oder entfernt werden, entscheidet erst die vollständige Inventur des gequeue-ten Konsolidierungs-Workpacks.
