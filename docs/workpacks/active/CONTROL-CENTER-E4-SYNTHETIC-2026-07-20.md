# Aktiver Workpack – Control Center: einmaliger synthetischer E4-Staging-Beweis

Stand: 2026-07-20  
Status: Gate B – Implementierung und E2 vor Integration  
Risikoklasse: R3

## 1. Zielzustand

Genau ein isolierter, vollständig synthetischer E4-Lauf beweist den wiederverwendbaren Control-Center-Pfad:

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

## 2. Ausgangsstand

- Aktivierungsbaseline von `staging`: `80655c5e730565e43faaa51af9d96b2d02fb8057`
- offene PRs bei Aktivierung: keine
- erreichte Evidence: E1, E2 und fachfallfreies read-only E3
- fehlende Evidence: E4
- E4-Harness und Workflow sind auf `staging` vorhanden, aber der Workflow fehlt auf dem Default-Branch `main` und ist dadurch noch nicht zuverlässig per `workflow_dispatch` bedienbar.
- `main` und `staging` sind deutlich auseinanderentwickelt; ein breiter Release nach `main` ist nicht Teil dieses Workpacks.

## 3. Scope und Owner

Erlaubt:

- `.github/workflows/control-center-e4-synthetic.yml`
- `.github/workflows/project-guardrails.yml`
- `scripts/control-center-e4-synthetic.py`
- `docs/github-actions-trigger-policy.md`
- `docs/domains/control-center.md`
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- dieser Workpack
- nach erfolgreichem Lauf genau eine Evidence-Datei, `TEST_STATUS.md` und ein abgeschlossener Workpack

Gesperrt:

- alle fachlichen CityArt-Aktionen;
- öffentliche Produkt-, UI-, SEO-, Content- oder Visualänderungen;
- andere Control-Center-Writer, Resolver oder Observer;
- ein breiter `staging -> main`-Release;
- Live-Schreibaktionen;
- parallele Änderungen an `Inbox_Staging`, `Events_Staging` oder der Staging-Control-Center-DB.

Owner- und Ressourcen-Lock:

```text
Control Center E4 Synthetic Proof
+ Inbox_Staging
+ Events_Staging
+ synthetische E4-Datensätze der Staging-Control-Center-DB
```

Der Lock beginnt mit Aktivierung dieses Workpacks und endet erst nach bestätigtem Cleanup und Abschlussdokumentation.

## 4. Default-Branch-Operatorvertrag

GitHub verarbeitet einen manuellen `workflow_dispatch` zuverlässig nur, wenn die Workflowdatei auf dem Default-Branch vorhanden ist. Deshalb wird nach grüner Staging-Integration ein enger Operatoranker nach `main` gebracht:

- ausschließlich `.github/workflows/control-center-e4-synthetic.yml`;
- keine Produkt-, Runtime-, Daten- oder Deploylogik aus `staging`;
- kein automatischer Trigger, kein Schedule und kein Push-Trigger;
- Start ausschließlich manuell auf `main`;
- Pflichtinput `expected_staging_sha`: exakter aktueller 40-stelliger `staging`-SHA;
- Pflichtinput `confirmation`: exakt `RUN_ONE_SYNTHETIC_E4`;
- der Job checkt den angegebenen Staging-Commit aus und prüft vor dem Write, dass er weiterhin aktueller `staging`-Head ist;
- der Operatoranker ist kein Live-Release und führt keine Main-Runtime aus.

Der Operatoranker bleibt als wiederverwendbarer, weiterhin manuell und SHA-gebundener R3-Einstieg bestehen. Jeder weitere E4-Lauf benötigt trotzdem einen neuen ausdrücklich aktivierten R3-Workpack.

## 5. Synthetische Identität

Ein Lauf erzeugt genau zwei technisch zusammengehörige Identitäten unter einem gemeinsamen Run-Key:

```text
be-e4-synthetic-<target-build>-<github-run-id>-success
be-e4-synthetic-<target-build>-<github-run-id>-resume
```

Sie gehören zu genau einem E4-Lauf:

- `success`: normaler Append-Pfad plus idempotenter Replay;
- `resume`: vorbestätigter Eventzustand plus fehlgeschlagene Operation, fail-closed Retry und erfolgreiche Wiederaufnahme ohne zweites Event.

Vor dem ersten Write müssen alle vier Tabellen/Tabs, beide öffentlichen Feeds und die Staging-DB frei von jedem Präfix `be-e4-synthetic` beziehungsweise `e4:` sein. Vorhandene Reste blockieren den Lauf.

## 6. Ressourcen und Zugriff

| Ressource | Zugriff | Erlaubte Operation |
|---|---|---|
| `Inbox_Staging` | controlled-staging-write | zwei synthetische Zeilen anlegen, Status rücklesen, beide löschen |
| `Events_Staging` | controlled-staging-write | Resume-Zeile vorseeded, Success-Zeile über Writer, beide rücklesen und löschen |
| Staging-Control-Center-DB | controlled-staging-write | zwei synthetische Cases und drei synthetische Operationszustände, danach löschen |
| Staging-Feed | read-only nach kontrolliertem Deploy | beide synthetischen IDs einmal sichtbar, nach Cleanup beide abwesend |
| `Inbox` | read-only | vollständiger Vorher-/Nachher-Hash unverändert |
| `Events` | read-only | vollständiger Vorher-/Nachher-Hash unverändert |
| Live-Feed | read-only | kein synthetischer Marker vor, während oder nach dem Lauf |
| STRATO Staging | kontrollierter Deploy | Initialzustand, synthetischer Feed, Cleanup-Feed |
| STRATO Live | none für Deploy/Write | nur öffentlicher read-only Feedvergleich |

## 7. Evidence-Design vor dem Patch

### Host, Endpoints und Workflow

- Operator: `Control Center E4 Synthetic Proof` auf Default-Branch `main`
- ausgecheckter Code und Zielbuild: exakter aktueller `staging`-SHA
- Host: `https://staging.bocholt-erleben.de`
- read-only Livevergleich: `https://bocholt-erleben.de`
- Endpoints:
  - `/api/control-center/cases.php`
  - `/api/control-center/preflight.php`
  - `/api/control-center/action.php`
  - `/data/events.json`
  - `/meta/build.txt`

### Positive Assertions

Vor dem Write:

- Target-SHA ist vollständiger SHA und aktueller `staging`-Head;
- passender Push-Deploy ist grün;
- deployter Build entspricht Target-SHA;
- Staging-DB und beide benötigten Tabellen existieren;
- `Inbox_Staging`- und `Events_Staging`-Header sind gültig;
- keine synthetischen E4-Reste in Sheets, DB oder Feeds;
- Live wird nur gelesen.

Write- und Resume-Pfad:

- beide synthetischen Inbox-Zeilen sind eindeutig;
- Resume-Event ist vorab genau einmal vorhanden;
- beide synthetischen Cases werden eindeutig angelegt;
- beide Preflights sind `mutation=false`, `allowed=true`, ohne Blocker und auf `Inbox_Staging -> Events_Staging` geroutet;
- Success-Write appendet genau ein Event und setzt Inbox auf `übernommen`;
- derselbe abgeschlossene Operation-Key liefert idempotenten Replay;
- der vorab fehlgeschlagene Operation-Key bleibt fail-closed mit HTTP 409;
- ein neuer Resume-Key bestätigt das vorhandene Event, ohne Duplikat, und setzt Inbox auf `übernommen`;
- Cases enden `done`; Operations enden `completed`, `failed`, `completed`;
- der Staging-Feed enthält danach beide synthetischen IDs;
- der Live-Feed enthält keinen synthetischen Marker.

Cleanup:

- beide synthetischen Inbox-Zeilen fehlen;
- beide synthetischen Event-Zeilen fehlen;
- alle synthetischen Cases und Operations fehlen;
- der bereinigte Staging-Feed enthält keinen synthetischen Marker;
- alle nicht zum Run gehörenden Staging-Zeilen sind hash-identisch;
- `Inbox`, `Events` und Live-Feed sind unverändert beziehungsweise frei von synthetischen Markern;
- Evidence-Artefakt endet mit `result=success` und leeren Cleanup-Fehlern.

### Negative Assertions und Abbruchbedingungen

Sofortiger Stop vor dem ersten Write bei:

- Target-SHA ungleich aktuellem `staging`-Head;
- falschem Host, Build oder Environment;
- fehlenden Secrets, Tabs, Tabellen oder Headern;
- vorhandenem synthetischem Restzustand;
- unerwartetem Live-Marker;
- nicht eindeutigem synthetischem Seed.

Sofortiger Stop nach begonnenem Write bei jeder unerwarteten API-, Sheet-, DB-, Feed- oder Postcondition. Danach wird ausschließlich Cleanup versucht; es gibt keinen zweiten E4-Lauf und keinen manuellen Fachdateneingriff.

## 8. E2- und Runtime-Design-Gate

Vor Integration nach `staging` müssen grün sein:

- Python-Syntax und Harness-Self-Test;
- vollständige Control-Center-Contracts;
- Workflow-Guardrails für Default-Branch, Pflichtinputs, Bestätigung, SHA-Bindung und exakten Checkout;
- Guard, dass Workflow und Harness keinen CityArt-Bezug enthalten;
- Guard, dass `Staging Verification` E4 nicht dispatcht;
- `Control Center CI`;
- `Project Guardrails`;
- `PR Gate`;
- PR mergebar und nicht hinter `staging`.

Der Harness-Self-Test beweist lokal Identitätsbildung, Payload-Stabilität, Marker-Erkennung, Filterung, SHA-Validierung und Event-Erkennung. Die bestehenden PHP-/Contracttests beweisen Writer-, Preflight-, Isolation- und Idempotenzverträge bis E2.

## 9. Integrations- und Ausführungsfolge

1. Hardened Workflow, Harness, Guards und Workpack per PR nach `staging` integrieren.
2. Staging-Deploy und fachfallfreies read-only E3 für den Merge-SHA grün bestätigen.
3. Identische Workflowdatei als einzigen Operatoranker per separatem engen PR nach `main` integrieren.
4. Genau einen manuellen Lauf auf `main` mit finalem aktuellem `staging`-SHA und Bestätigung starten.
5. Lauf, Artifact und Cleanup vollständig auswerten.
6. Bei grünem E4 den Workpack abschließen und den nächsten zulässigen Zustand dokumentieren.
7. Bei rotem E4 keine Wiederholung: Evidence sichern und Revert-, Architektur- oder Workpack-Neuentscheidung treffen.

## 10. Rollback

Vor E4:

- normaler Revert der Staging-Implementierung;
- normaler Revert des Main-Operatorankers.

Während/nach E4:

- der Harness löscht synthetische Sheet- und DB-Zustände in `finally`;
- ein Cleanup-Deploy entfernt synthetische Feedprojektionen;
- fehlgeschlagener Cleanup ist ein harter Fehler und blockiert jede Folgeaktion;
- keine reale fachliche Zeile wird als Rollbackziel verwendet.

## 11. Abschlussgrenze

Dieser Workpack endet ausschließlich in einem der Zustände:

- `E4 erfolgreich und vollständig bereinigt`; oder
- `E4 fehlgeschlagen, Evidence gesichert, kein zweiter Lauf, Architektur-/Revertentscheidung offen`.

Ein echter CityArt-Staging-Fall ist nicht Teil dieses Workpacks und wird auch bei grünem E4 nicht automatisch ausgeführt.
