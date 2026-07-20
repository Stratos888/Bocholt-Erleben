# Current Workpack

Stand: 2026-07-20

Diese Datei ist der einzige operative technische Projektstatus. Offene PRs, aktuelle SHAs und CI-Zustände werden bei jeder Aufgabe direkt aus GitHub gelesen und nicht dauerhaft hier gespiegelt.

## Aktiver Implementierungs-Workpack

**Control Center: genau ein isolierter synthetischer E4-Staging-Beweis**

- Detailvertrag: `docs/workpacks/active/CONTROL-CENTER-E4-SYNTHETIC-2026-07-20.md`
- Domain-Router: `docs/domains/control-center.md`
- Risikoklasse: `R3`
- Aktivierungsbaseline von `staging`: `80655c5e730565e43faaa51af9d96b2d02fb8057`
- aktuelles Gate: `B` – Implementierung und E2 vor Integration
- erreichte Evidence vor Aktivierung: E1, E2 und fachfallfreies read-only E3
- Ziel dieses Workpacks: genau ein synthetischer E4-Lauf mit Write, Rücklesen, idempotentem Replay, kontrolliertem Teilfehler, Wiederaufnahme, Staging-Feed-Beweis und vollständigem Cleanup

Die allgemeine Dokumentations-Governance, die semantische Vollklassifikation, die Control-Center-Workflow-Konsolidierung und die Evidence-first-Härtung des Ausführungsmodells bleiben abgeschlossen. Es wird kein weiterer allgemeiner Governance- oder Prozessoptimierungs-Workpack eröffnet.

## Verbindlicher Evidence-first-Modus

1. Evidence-Design ist vollständig vor dem ersten Patch festgelegt.
2. Der E4-Beweis ist vollständig synthetisch und fachfallfrei.
3. Technische Assertions, Negativfälle, Cleanup und Revert stehen im aktiven Detailvertrag.
4. Vor Integration muss das Runtime-Design-Gate über `Control Center CI`, `Project Guardrails` und `PR Gate` grün sein.
5. Nach einer fehlgeschlagenen Integration ist höchstens eine eng begrenzte Korrekturrunde zulässig.
6. Nach einem fehlgeschlagenen E4-Lauf gibt es keine Wiederholung; es folgt eine Revert-, Architektur- oder Workpack-Neuentscheidung.

## Autoritative technische Kette

1. `PR Gate` – Always-run-Integration und Branchpolicy.
2. `Project Guardrails` – Architektur-, Dokumentations- und Workflowtopologie-Governance.
3. `Control Center CI` – vollständige E1/E2-Prüfung je relevantem PR.
4. `Deploy to STRATO` – einziger Deploypfad.
5. `Staging Verification` – gemeinsame read-only Deploy-/Build-/E3-Evidence.
6. `Control Center E4 Synthetic Proof` – getrennte, manuelle und SHA-gebundene R3-Capability.

Die Statuskontexte `deploy/staging-observed` und `control-center/runtime-preflight-e3` bleiben verbindlich. `Staging Verification` ist read-only und darf E4 weder aufrufen noch dispatchen.

## Aktiver Ressourcen-Lock

Bis zum dokumentierten Abschluss dieses Workpacks gilt exklusiv:

- keine parallele Control-Center-Workflow- oder Writeränderung;
- keine parallele Mutation in `Inbox_Staging`, `Events_Staging` oder der Staging-Control-Center-DB;
- keine E4-Ausführung außerhalb des einen dokumentierten Runs;
- kein CityArt-Fachklick und kein anderer echter Fachfall;
- keine Mutation in `Inbox` oder `Events`;
- kein Live-Deploy und keine Live-Schreibaktion;
- kein zusätzlicher Trigger-, Observer- oder One-off-Workflow.

Erlaubter externer Zugriff im E4-Lauf:

- `Inbox_Staging`, `Events_Staging` und synthetische Staging-DB-Datensätze: `controlled-staging-write`;
- `Inbox`, `Events` und Live-Feed: ausschließlich read-only für Unverändert-Nachweise;
- STRATO Staging: sequenzielle Deploys für Initial-, synthetischen und bereinigten Feedzustand.

## Geplanter Operatorpfad

Der manuelle E4-Workflow benötigt einen Workflowanker auf dem Default-Branch `main`. Der zulässige Weg ist:

1. hardened E4-Workflow und Harness nach grüner E2-Prüfung nach `staging` integrieren;
2. Staging-Deploy und read-only E3 für den finalen Merge-SHA bestätigen;
3. ausschließlich die identische Workflowdatei als engen Operatoranker nach `main` bringen;
4. den Workflow auf `main` mit dem exakten aktuellen 40-stelligen `staging`-SHA und der Bestätigung `RUN_ONE_SYNTHETIC_E4` starten;
5. der Job checkt genau diesen Staging-Commit aus und stoppt, falls `staging` inzwischen weitergelaufen ist.

Der Main-Operatoranker ist kein Live-Release. Ein breiter `staging -> main`-Merge ist nicht Teil dieses Workpacks.

## E4-Erfolgskriterien

Der Workpack ist nur grün, wenn das Evidence-Artefakt mindestens bestätigt:

- fachfallfreie synthetische Identitäten;
- korrekter aktueller Staging-SHA, Host, Build und Ressourcenpfad;
- Success-Write und idempotenter Replay;
- fail-closed Teilfehler und erfolgreiche Wiederaufnahme ohne Duplikat;
- beide synthetischen Events genau einmal im Staging-Feed;
- kein synthetischer Eintrag im Live-Feed;
- vollständiger Sheet-, DB- und Feed-Cleanup;
- unveränderte Nicht-Testdaten in Staging;
- unveränderte Live-Sheets;
- keine synthetischen Restdaten;
- `result=success` und keine Cleanup-Fehler.

## Stop-the-line

Beim ersten unerwarteten realen Verhalten:

```text
weitere Writes stoppen
-> Evidence sichern
-> nur Cleanup versuchen
-> keinen zweiten E4-Lauf starten
-> Revert-, Architektur- oder Workpack-Neuentscheidung
```

Manuelle Datenkorrekturen zum künstlichen Grünmachen sind ausgeschlossen.

## Nächster erlaubter Schritt

Den aktiven Workpack auf dem Feature-Branch vollständig durch E2 validieren, nach `staging` integrieren und die read-only E3-Postconditions für den finalen Merge-SHA bestätigen.

Erst danach darf der enge Default-Branch-Operatoranker eingerichtet und genau ein synthetischer E4-Lauf gestartet werden.

## Nicht Teil dieses Workpacks

- echter CityArt-Staging-Fall;
- sonstiger echter Fachfall;
- Produkt-, UI-, SEO-, Content- oder Visualänderung;
- allgemeine Workflowkonsolidierung;
- Live-Release oder Live-Write.

## Folgezustand

Nach einem vollständig grünen und bereinigten E4 wird gesondert entschieden, ob ein echter CityArt-Staging-Fall noch zusätzliche E5-Evidence benötigt. Er wird nicht automatisch ausgeführt. Danach wird wieder ein produktwirksamer Workpack aus `docs/workpacks/queued/INDEX.md` aktiviert.
