# Current Workpack

Stand: 2026-07-20

Diese Datei ist der einzige operative technische Projektstatus. Offene PRs, aktuelle SHAs und CI-Zustände werden bei jeder Aufgabe direkt aus GitHub gelesen und nicht dauerhaft hier gespiegelt.

## Aktiver Implementierungs-Workpack

**Control Center: genau ein isolierter synthetischer E4-Staging-Beweis**

- Detailvertrag: `docs/workpacks/active/CONTROL-CENTER-E4-SYNTHETIC-2026-07-20.md`
- Domain-Router: `docs/domains/control-center.md`
- Risikoklasse: `R3`
- Aktivierungsbaseline: `80655c5e730565e43faaa51af9d96b2d02fb8057`
- erste integrierte E4-Implementierung: `fe3124347eab7a59f497940ec20dbc7abe0d9b98`
- E2 und fachfallfreies read-only E3 für diesen SHA: grün
- externe Mutation und E4-Lauf bisher: keine
- aktueller Zustand: genau eine begrenzte Korrekturrunde vor dem Main-Operator-Bootstrap

## Anlass der Korrekturrunde

Der Ein-Datei-Operatoranker-PR nach `main` wurde korrekt durch das Required Check `PR Gate` blockiert. Das bestehende Gate erlaubt im Standard ausschließlich `staging -> main`; ein manueller Status oder Ruleset-Bypass ist ausgeschlossen.

Die Korrektur stellt den Required Check real bereit und begrenzt die einmalige Ausnahme auf:

- Headbranch `agent/e4-default-branch-anchor`;
- Main-Base-SHA `3d9e3cd6707eb20b0b9bece0a2601df2d92a888f`;
- exakt `.github/workflows/pr-gate.yml` und `.github/workflows/control-center-e4-synthetic.yml`.

Nach dem Merge ist die Ausnahme wegen des veränderten Main-SHAs automatisch abgelaufen.

## Verbindlicher Evidence-first-Modus

1. Der E4-Beweis bleibt vollständig synthetisch und fachfallfrei.
2. Vor Integration sind PR-Gate-, Operator-, SHA-, Confirmation-, Cleanup- und Negativverträge maschinenprüfbar.
3. Nach der Korrektur-Integration werden Deploy und read-only E3 für den neuen finalen Staging-SHA erneut geprüft.
4. Der Main-PR muss einen real grünen `PR Gate` besitzen.
5. Der Main-Squash-Commit muss `[skip ci]` enthalten, damit der vorhandene Main-Push-Deploy für genau diesen Bootstrap nicht startet.
6. Nach einem fehlgeschlagenen E4 gibt es keinen zweiten Lauf.

## Autoritative technische Kette

1. `PR Gate` – Always-run-Integration und Branchpolicy einschließlich ablaufendem Bootstrapvertrag.
2. `Project Guardrails` – Architektur-, Dokumentations-, Workflow- und Bootstrap-Governance.
3. `Control Center CI` – vollständige E1/E2-Prüfung.
4. `Deploy to STRATO` – einziger Deploypfad.
5. `Staging Verification` – fachfallfreie read-only E3-Evidence.
6. `Control Center E4 Synthetic Proof` – manuelle, bestätigte und SHA-gebundene R3-Capability.

`Staging Verification` darf E4 weder aufrufen noch dispatchen.

## Aktiver Ressourcen-Lock

Bis zum Abschluss gilt:

- keine parallele Control-Center-Workflow- oder Writeränderung;
- keine parallele Mutation in `Inbox_Staging`, `Events_Staging` oder der Staging-Control-Center-DB;
- keine E4-Ausführung außerhalb des einen dokumentierten Runs;
- kein CityArt-Fachklick und kein anderer echter Fachfall;
- keine Mutation in `Inbox` oder `Events`;
- kein Live-Deploy und keine Live-Schreibaktion;
- kein Ruleset- oder Required-Check-Bypass.

Im späteren E4-Lauf sind nur synthetische Staging-Writes und read-only Live-Unverändert-Nachweise erlaubt.

## E4-Erfolgskriterien

- aktueller Staging-SHA, Host, Build und Ressourcenpfad bestätigt;
- Success-Write und idempotenter Replay;
- fail-closed Teilfehler und Resume ohne Duplikat;
- beide synthetischen IDs genau einmal im Staging-Feed und niemals im Live-Feed;
- vollständiger Sheet-, DB- und Feed-Cleanup;
- Nicht-Testdaten in Staging und Live-Ressourcen unverändert;
- Evidence `result=success`, keine Cleanup-Fehler.

## Stop-the-line

```text
unerwartetes Verhalten
-> weitere Writes stoppen
-> Evidence sichern
-> nur Cleanup versuchen
-> keinen zweiten E4-Lauf starten
-> Revert- oder Architekturentscheidung
```

## Nächster erlaubter Schritt

Die begrenzte PR-Gate-/Dokumentationskorrektur vollständig durch E2 validieren, nach `staging` integrieren und Deploy sowie fachfallfreies E3 für den neuen finalen Staging-SHA bestätigen.

Danach werden die bytegleichen Workflowdateien in den bestehenden Main-Operator-PR übernommen. Nur bei real grünem `PR Gate` wird dieser als Squash mit `[skip ci]` integriert. Anschließend darf genau ein manueller E4-Lauf gestartet werden.

## Nicht Teil dieses Workpacks

- echter CityArt- oder anderer Fachfall;
- Produkt-, UI-, SEO-, Content- oder Visualänderung;
- breiter `staging -> main`-Release;
- Live-Release oder Live-Write.

## Folgezustand

Nach grünem und bereinigtem E4 wird gesondert entschieden, ob ein echter CityArt-Staging-Fall noch E5-Mehrwert besitzt. Er wird nicht automatisch ausgeführt. Danach folgt wieder ein produktwirksamer Workpack.
