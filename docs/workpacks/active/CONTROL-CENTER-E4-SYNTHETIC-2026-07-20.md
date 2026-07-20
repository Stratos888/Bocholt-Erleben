# Aktiver Workpack – Control Center: einmaliger synthetischer E4-Staging-Beweis

Stand: 2026-07-20  
Status: begrenzte Korrekturrunde vor Main-Operator-Bootstrap  
Risikoklasse: R3

## 1. Zielzustand

Genau ein isolierter, vollständig synthetischer E4-Lauf beweist:

```text
synthetische Inbox_Staging-Identität
-> read-only Preflight
-> verifizierter Write nach Events_Staging
-> unmittelbares Rücklesen
-> idempotenter Replay
-> kontrollierter Teilfehler
-> Wiederaufnahme ohne Duplikat
-> Staging-Feed-Beweis
-> vollständiger Cleanup
-> unveränderte Live-Ressourcen
```

Der Lauf verwendet keinen echten Fachfall. CityArt und alle anderen realen Einträge sind weder Prüfobjekt noch Sentinel.

## 2. Belegter Zwischenstand

- Aktivierungsbaseline von `staging`: `80655c5e730565e43faaa51af9d96b2d02fb8057`
- erste integrierte E4-Implementierung auf `staging`: `fe3124347eab7a59f497940ec20dbc7abe0d9b98`
- E2 für diese Implementierung: `Control Center CI`, `Project Guardrails` und `PR Gate` grün
- E3 für diesen SHA: `deploy/staging-observed=success` und `control-center/runtime-preflight-e3=success`
- externe Mutation bisher: keine
- E4 bisher gestartet: nein

Der erste enge Main-PR mit nur der E4-Workflowdatei wurde korrekt blockiert, weil das Main-Ruleset den Required Check `PR Gate` verlangt. Der dauerhafte PR-Gate-Vertrag erlaubte nach `main` nur `staging -> main`; ein manueller Status oder Ruleset-Bypass ist ausgeschlossen.

Damit ist genau eine begrenzte Korrekturrunde innerhalb dieses Workpacks aktiviert. Sie ändert nicht den E4-Harness oder den fachlichen Writepfad, sondern stellt den realen Required Check für einen einmaligen, automatisch ablaufenden Operator-Bootstrap her.

## 3. Scope und Owner

Erlaubt:

- `.github/workflows/control-center-e4-synthetic.yml`
- `.github/workflows/pr-gate.yml`
- `.github/workflows/project-guardrails.yml`
- `scripts/control-center-e4-synthetic.py`
- `scripts/audit-documentation-governance.py`
- `docs/github-actions-trigger-policy.md`
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- dieser Workpack
- nach erfolgreichem Lauf genau eine Evidence-Datei, `TEST_STATUS.md` und ein abgeschlossener Workpack

Gesperrt:

- alle fachlichen CityArt-Aktionen;
- öffentliche Produkt-, UI-, SEO-, Content- oder Visualänderungen;
- andere Control-Center-Writer, Resolver oder Observer;
- ein breiter `staging -> main`-Release;
- Ruleset- oder Required-Check-Bypass;
- manueller Fake-Status;
- Live-Schreibaktionen;
- parallele Änderungen an `Inbox_Staging`, `Events_Staging` oder der Staging-Control-Center-DB.

## 4. Ressourcen-Lock

```text
Control Center E4 Synthetic Proof
+ Inbox_Staging
+ Events_Staging
+ synthetische E4-Datensätze der Staging-Control-Center-DB
```

Der Lock gilt bis zum bestätigten Cleanup und Abschluss dieses Workpacks.

## 5. Default-Branch-Operatorvertrag

GitHub benötigt die manuell startbare Workflowdatei auf dem Default-Branch. Gleichzeitig deployt der bestehende Main-Workflow bei jedem Main-Push. Der sichere Bootstrap benötigt deshalb zwei getrennte Schutzschichten.

### 5.1 Realer Required Check

Der Main-PR enthält exakt:

- `.github/workflows/pr-gate.yml`;
- `.github/workflows/control-center-e4-synthetic.yml`.

Die einmalige PR-Gate-Ausnahme gilt nur bei:

- Ziel `main`;
- Head `agent/e4-default-branch-anchor`;
- Base-SHA `3d9e3cd6707eb20b0b9bece0a2601df2d92a888f`;
- exakt den beiden genannten Dateien.

Der `PR Gate`-Check wird real ausgeführt. Nach dem Merge ändert sich der Main-SHA, wodurch die Ausnahme automatisch und dauerhaft abläuft.

### 5.2 Kein Live-Deploy beim Bootstrap

Der Squash-Commit des Bootstrap-PRs enthält `[skip ci]`. Dadurch werden die durch genau diesen Main-Push ausgelösten Push-Workflows übersprungen. Der bereits vorher real grüne Required Check bleibt die Mergevoraussetzung.

Es wird kein Workflow, Ruleset oder Check deaktiviert. Ein Main-Merge ohne real grünen `PR Gate` oder ohne `[skip ci]` ist unzulässig.

### 5.3 Bedienvertrag des E4-Workflows

- Start ausschließlich manuell auf `main`;
- Pflichtinput `expected_staging_sha`: exakter aktueller 40-stelliger `staging`-SHA;
- Pflichtinput `confirmation`: exakt `RUN_ONE_SYNTHETIC_E4`;
- Checkout genau dieses Staging-Commits;
- erneute Remote-Head-Prüfung vor dem Write;
- keine Main-Runtime und kein Live-Release;
- kein Push-, Schedule-, `workflow_run`- oder automatischer Trigger.

## 6. Synthetische Identität

Ein Lauf erzeugt zwei technisch zusammengehörige Identitäten unter einem Run-Key:

```text
be-e4-synthetic-<target-build>-<github-run-id>-success
be-e4-synthetic-<target-build>-<github-run-id>-resume
```

- `success`: normaler Append-Pfad plus idempotenter Replay;
- `resume`: vorbestätigter Eventzustand plus fehlgeschlagene Operation, fail-closed Retry und erfolgreiche Wiederaufnahme ohne zweites Event.

Vor dem ersten Write müssen Sheets, Staging-DB und beide Feeds frei von jedem Präfix `be-e4-synthetic` beziehungsweise `e4:` sein.

## 7. Ressourcen und Zugriff

| Ressource | Zugriff | Erlaubte Operation |
|---|---|---|
| `Inbox_Staging` | controlled-staging-write | zwei synthetische Zeilen anlegen, rücklesen und löschen |
| `Events_Staging` | controlled-staging-write | Resume-Zeile vorseeded, Success-Zeile über Writer, beide löschen |
| Staging-Control-Center-DB | controlled-staging-write | zwei Cases und drei Operationszustände, danach löschen |
| Staging-Feed | read-only nach Staging-Deploy | beide IDs einmal sichtbar, nach Cleanup abwesend |
| `Inbox` | read-only | Vorher-/Nachher-Hash unverändert |
| `Events` | read-only | Vorher-/Nachher-Hash unverändert |
| Live-Feed | read-only | kein synthetischer Marker |
| STRATO Staging | kontrollierter Deploy | Initial-, synthetischer und bereinigter Feedzustand |
| STRATO Live | keine Mutation | kein Deploy, nur öffentlicher Feedvergleich |

## 8. Positive Assertions

Vor dem Write:

- Target-SHA ist vollständiger aktueller `staging`-Head;
- passender Staging-Deploy und Build sind grün;
- Staging-DB, Tabs und Header sind gültig;
- keine synthetischen Reste in Sheets, DB oder Feeds;
- Live wird nur gelesen.

Write und Resume:

- beide Inbox-Zeilen und Cases sind eindeutig;
- beide Preflights sind `mutation=false`, `allowed=true`, ohne Blocker und auf `Inbox_Staging -> Events_Staging` geroutet;
- Success appendet genau ein Event und setzt Inbox auf `übernommen`;
- gleicher Operation-Key liefert idempotenten Replay;
- fehlgeschlagener Operation-Key bleibt HTTP 409 fail-closed;
- neuer Resume-Key bestätigt das vorhandene Event ohne Duplikat;
- Cases enden `done`; Operations `completed`, `failed`, `completed`;
- Staging-Feed enthält beide IDs;
- Live-Feed enthält keinen synthetischen Marker.

Cleanup:

- beide synthetischen Inbox- und Eventzeilen fehlen;
- alle synthetischen Cases und Operations fehlen;
- bereinigter Staging-Feed enthält keinen synthetischen Marker;
- Nicht-Testdaten in Staging sind hash-identisch;
- `Inbox`, `Events` und Live-Feed sind unverändert;
- Evidence endet `result=success` ohne Cleanup-Fehler.

## 9. Negative Assertions und Stop-the-line

Abbruch vor dem ersten Write bei:

- falschem oder bewegtem Target-SHA;
- falschem Host, Build oder Environment;
- fehlenden Secrets, Tabs, Tabellen oder Headern;
- synthetischem Restzustand;
- unerwartetem Live-Marker;
- nicht eindeutigem Seed.

Nach begonnenem Write führt jede unerwartete API-, Sheet-, DB-, Feed- oder Postcondition ausschließlich in Cleanup und Evidence. Es gibt keinen zweiten E4-Lauf und keine manuelle Fachdatenkorrektur.

## 10. E2- und Runtime-Design-Gate

Vor erneuter Integration nach `staging` müssen grün sein:

- PR-Gate-Logik für Standardpfad und exakt begrenzten Bootstrap;
- Project-Guardrails für Branch, Base-SHA und exakte Pfade;
- E4-Harness-Syntax und Self-Test;
- vollständige Control-Center-Contracts;
- `Control Center CI`, `Project Guardrails`, `PR Gate`;
- PR mergebar und nicht hinter `staging`.

Nach der Korrektur-Integration müssen Deploy und fachfallfreies E3 für den neuen finalen Staging-SHA erneut grün sein.

## 11. Ausführungsfolge

1. Begrenzte PR-Gate-/Dokumentationskorrektur nach `staging` integrieren.
2. Deploy und read-only E3 für den neuen finalen Staging-SHA bestätigen.
3. Bytegleiche korrigierte `pr-gate.yml` und E4-Workflowdatei in den bestehenden Main-PR übernehmen.
4. Realen `PR Gate` auf diesem Main-PR grün bestätigen.
5. Main-PR als Squash mit `[skip ci]` integrieren und belegen, dass kein Main-Push-Workflow beziehungsweise Live-Deploy gestartet wurde.
6. Genau einen manuellen E4-Lauf mit dem finalen aktuellen Staging-SHA starten.
7. Artifact und Cleanup vollständig auswerten.
8. Bei Erfolg abschließen; bei Fehler keine Wiederholung, sondern Revert-/Architekturentscheidung.

## 12. Rollback

- Staging-Korrektur und Main-Operatoranker sind normal revertierbar.
- Der E4-Harness löscht synthetische Sheet- und DB-Zustände in `finally`.
- Ein Cleanup-Deploy entfernt synthetische Feedprojektionen.
- Cleanup-Fehler blockieren jede Folgeaktion.
- Kein realer Fachdatensatz ist Rollbackziel.

## 13. Abschlussgrenze

Der Workpack endet nur als:

- `E4 erfolgreich und vollständig bereinigt`; oder
- `E4 fehlgeschlagen, Evidence gesichert, kein zweiter Lauf, Architektur-/Revertentscheidung offen`.

Ein echter CityArt-Staging-Fall ist nicht Teil dieses Workpacks und wird auch bei grünem E4 nicht automatisch ausgeführt.
