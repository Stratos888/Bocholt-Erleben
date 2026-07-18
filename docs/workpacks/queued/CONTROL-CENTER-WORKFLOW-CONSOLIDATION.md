# Gequeue-ter Workpack: Control-Center-Workflow-Konsolidierung

Stand: 2026-07-18

Status: geplant, noch nicht gestartet

Risikoklasse: R2 – GitHub-Actions-, Deploy- und Verification-Verhalten; keine externe Fachmutation

## Ziel

Die Control-Center-Prüf- und Evidence-Kette wird auf wenige autoritative Workflows reduziert. Redundante Tests, Observer-Schichten und nicht ausführbare manuelle Trigger werden entfernt oder in klare interne Jobs überführt.

## Ausgangslage

E3 ist erfolgreich belegt. Der synthetische E4-Harness ist integriert, aber der manuelle Workflow ist operativ nicht erreichbar, weil er nur auf `staging` vorhanden ist. E4 wurde nicht gestartet; CityArt und alle externen Daten bleiben eingefroren.

## Gate A – vollständige Inventur

Vor jeder Workflow-Änderung wird für alle Dateien unter `.github/workflows/**` erfasst:

- sichtbarer Workflow-Name;
- fachlicher Owner und Zweck;
- Trigger: PR, Push, Schedule, Manual, Workflow-Run;
- Branch- und Pfadfilter;
- Jobs und tatsächlich ausgeführte Tests;
- Überschneidungen mit anderen Workflows;
- Secrets und Berechtigungen;
- erzeugte Artefakte und Commit-Statusnamen;
- Branch-Ruleset-Abhängigkeiten;
- Laufzeit, Kosten und notwendige Bedienbarkeit;
- Kandidat: behalten, intern zusammenführen, ersetzen oder entfernen.

Zusätzlich wird geprüft, welche Workflows auf dem Default-Branch `main` und welche nur auf `staging` vorhanden sind.

## Zielzustand

1. `PR Gate` bleibt stabiler Always-run-Aggregator.
2. `Project Guardrails` bleibt projektweiter Architektur- und Routing-Guard.
3. Ein `Control Center CI` ersetzt überlappende fachliche Top-Level-Workflows, ohne Tests zu verlieren.
4. `Deploy to STRATO` bleibt der einzige Deploypfad.
5. Ein `Staging Verification` bündelt E3 und ausdrücklich freigegebene E4-Modi und besitzt einen vor Integration nachgewiesenen Operatorpfad.
6. Content-, Growth-, Inbox-, KI- und Zeitplan-Workflows bleiben getrennt und werden klar benannt.

## Erlaubter Scope

- `.github/workflows/**` ausschließlich nach Gate-A-Inventur;
- zugehörige kleine Test-/Helper-Dateien;
- `docs/github-actions-trigger-policy.md`;
- Architektur-, Evidence- und Workpack-Dokumentation;
- erforderliche Branch-/Status-Vertragsanpassungen nach belegter Abhängigkeit.

## Gesperrter Scope

- keine fachliche Writeränderung;
- keine Mutation in Google Sheets oder Datenbank;
- kein CityArt-Klick;
- kein synthetischer E4-Lauf innerhalb der Konsolidierungsimplementierung;
- keine Live-Schreibaktion;
- kein Feature-Branch-Deploy;
- keine Entfernung von Qualitätsprüfungen ohne nachgewiesenen Ersatz;
- keine Vermischung mit SEO-, Visual- oder Produktworkpacks.

## Evidence

### E1

- vollständige Workflow-/Trigger-/Overlap-Matrix;
- Zielzuordnung jeder bestehenden Workflow-Datei;
- Diff entspricht exakt der beschlossenen Konsolidierung.

### E2

- YAML-/Syntaxprüfung;
- alle konsolidierten fachlichen Tests grün;
- `PR Gate` aggregiert weiterhin korrekt;
- keine doppelten Testpakete im selben relevanten PR-Scope;
- Trigger-Matrix automatisiert geprüft;
- der vorgesehene manuelle/API-Operatorpfad ist auf dem Zielbranch nachweisbar.

### E3

- normaler Staging-Deploy erfolgreich;
- Build, Host, Endpoint, Umgebung, Ressourcen und Writer weiterhin read-only bestätigt;
- keine zusätzliche Observer-Kette erforderlich oder deren Restbedarf ausdrücklich begründet.

## Akzeptanzkriterien

- Actions ist für Betreiber nach klaren Präfixen und Aufgaben verständlich.
- Control-Center-Tests laufen pro Scope nur einmal.
- Reine Dokumentationsänderungen starten keine unnötige Runtime-Verifikation.
- Der E4-Ausführungspfad ist vor seiner Freigabe tatsächlich sichtbar beziehungsweise über eine verfügbare API startbar.
- Keine Qualitäts- oder Sicherheitsprüfung geht verloren.
- Kein zusätzlicher Wrapper bleibt ohne eindeutigen Mehrwert bestehen.

## Rollback

Konsolidierungsänderungen werden als klar begrenzter PR umgesetzt. Bei fehlgeschlagener Staging-Verifikation wird der Merge per Revert zurückgenommen. Externe Ressourcen werden in diesem Workpack nicht verändert.

## Nachfolgender Schritt

Erst nach erfolgreicher Konsolidierung wird ein separater R3-Workpack für genau einen synthetischen E4-Lauf aktiviert. Danach folgt abhängig vom Ergebnis entweder kein WP-3, ein minimal belegter Fix oder genau ein fachlicher CityArt-Staging-Fall.
