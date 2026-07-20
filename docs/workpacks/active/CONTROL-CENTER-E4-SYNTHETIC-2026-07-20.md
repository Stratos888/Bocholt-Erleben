# Aktiver Workpack – Control Center: einmaliger synthetischer E4-Staging-Beweis

Stand: 2026-07-20  
Status: Architekturkorrektur nach E4-Abbruch vor Mutation  
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
- erste integrierte E4-Implementierung: `fe3124347eab7a59f497940ec20dbc7abe0d9b98`
- Bootstrap-/Gate-Korrektur und finaler geprüfter Staging-Stand vor dem Lauf: `311db94a12d67efc1921af1d4981cfaacb016d82`
- Main-Operatoranker: integriert, manueller `workflow_dispatch`, exakte Bestätigung und SHA-Bindung
- E2 und fachfallfreies read-only E3 für den finalen Stand: grün
- erster E4-Start: rot nach rund 26 Sekunden
- Fehler: Timeout beim direkten `pymysql.connect(...)` vom GitHub-hosted Runner zur Staging-Datenbank
- Mutationsgrenze: nicht erreicht
- synthetische oder reale externe Mutation im fehlgeschlagenen Lauf: keine

Die vollständige Evidence steht in:

`docs/evidence/control-center-e4-direct-db-timeout-2026-07-20.md`

## 3. Bedeutung des Fehlers

Der Timeout belegt einen nicht tragfähigen Infrastrukturpfad:

```text
GitHub-hosted Runner
-> direkter externer TCP/MySQL-Zugriff
-> geschützte STRATO-Staging-Datenbank
```

Er belegt weder falsche Zugangsdaten noch einen Fehler im fachlichen Inbox-Writer. Der Ablauf stoppte beim Konstruktor der Datenbankklasse, bevor der Harness `mutations_started = True` setzte.

Deshalb wurden nicht ausgeführt:

- kein Append nach `Inbox_Staging`;
- kein Seed nach `Events_Staging`;
- kein Control-Center-Case;
- keine Control-Center-Operation;
- kein synthetischer Feed-Deploy;
- keine Live-Aktion.

## 4. Architekturentscheidung

Nicht zulässig sind:

- MySQL-Port öffentlich für GitHub-hosted Runner öffnen;
- wechselnde GitHub-IP-Allowlist pflegen;
- weitere DB-Secrets oder Netzwerkausnahmen ergänzen;
- den fehlgeschlagenen Workflow unverändert erneut starten.

Der nachhaltige Ersatzpfad lautet:

```text
GitHub-hosted Runner
-> HTTPS mit bestehendem Review-Zugang
-> ausschließlich Staging-Endpunkt
-> private serverseitige PDO-Verbindung
-> ausschließlich exakter synthetischer Run-Key
```

Die bereits E2-geprüfte fachliche E4-Ablauflogik bleibt als Core unverändert. Nur die Transportklasse für DB-Preflight, kontrollierte Fault-Injection, State-Readback und Cleanup wird ersetzt.

## 5. Scope und Owner

Erlaubt:

- `api/control-center/e4-synthetic.php`
- `api/control-center/_e4_synthetic_runtime.php`
- `scripts/control-center-e4-synthetic.py`
- `scripts/control_center_e4_synthetic_core.py`
- `tests/control_center_e4_synthetic_runtime_contract_test.php`
- `.github/workflows/control-center-ci.yml`
- `.github/workflows/project-guardrails.yml`
- `docs/evidence/control-center-e4-direct-db-timeout-2026-07-20.md`
- `docs/workpacks/active/CURRENT_WORKPACK.md`
- dieser Workpack
- nach erfolgreichem Ersatzlauf genau eine abschließende Evidence-Datei, `TEST_STATUS.md` und ein abgeschlossener Workpack

Gesperrt:

- CityArt und alle anderen echten Fachfälle;
- öffentliche Produkt-, UI-, SEO-, Content- oder Visualänderungen;
- fachliche Control-Center-Writer und Resolver;
- Öffnung oder externe Freigabe der Staging-Datenbank;
- breiter `staging -> main`-Release;
- Ruleset-, Required-Check- oder Status-Bypass;
- Live-Schreibaktionen;
- parallele Änderungen an den gesperrten Staging-Ressourcen.

## 6. Ressourcen-Lock

```text
Control Center E4 Synthetic Proof
+ Inbox_Staging
+ Events_Staging
+ synthetische E4-Datensätze der Staging-Control-Center-DB
+ serververmittelter E4-Staging-Endpunkt
```

Der Lock gilt bis zum bestätigten Cleanup und Abschluss dieses Workpacks.

## 7. Serververtrag

Der authentifizierte Endpunkt ist POST-only und darf ausschließlich im aufgelösten Environment `staging` arbeiten.

Jede Anfrage enthält:

- vollständigen `target_sha`;
- `run_key` im Format `<erste-12-SHA>-<github-run-id>`;
- einen festen Modus.

Zulässige Modi:

| Modus | Mutation | Zweck |
|---|---:|---|
| `preflight` | nein | Schema, Build und globale synthetische Restfreiheit bestätigen |
| `seed_failed_operation` | ja | exakt `e4:<run-key>:partial-failed` für einen synthetischen Case anlegen |
| `states` | nein | ausschließlich angegebene synthetische Cases und Run-Key-Operationen lesen |
| `counts` | nein | synthetische Restzustände des Runs und global zählen |
| `cleanup` | ja | ausschließlich Cases und Operations des exakten Run-Keys löschen |

Preflight und Fault-Injection verlangen den passenden deployten Build. `states`, `counts` und `cleanup` bleiben auch nach einer möglichen Staging-Bewegung verfügbar, damit ein begonnener Lauf sicher bereinigt werden kann. Sie bleiben trotzdem auf den exakten Run-Key begrenzt.

Der Endpunkt verweigert:

- Production oder unbekannte Umgebung;
- ungültigen oder nicht zum SHA passenden Run-Key;
- fremde Operation-IDs;
- jede andere Fault-Injection als `partial-failed`;
- nichtsynthetische Cases;
- generische SQL-, Update- oder Delete-Befehle.

## 8. Synthetische Identität

Ein Ersatzlauf erzeugt neue Identitäten unter seinem neuen Run-Key:

```text
be-e4-synthetic-<target-build>-<github-run-id>-success
be-e4-synthetic-<target-build>-<github-run-id>-resume
```

Der fehlgeschlagene alte Run erzeugte keine dieser Identitäten. Ein neuer Ersatzlauf ist keine Wiederholung desselben synthetischen Zustands, sondern ein neu SHA- und Run-ID-gebundener Nachweis des korrigierten Architekturpfads.

## 9. Ressourcen und Zugriff

| Ressource | Zugriff | Erlaubte Operation |
|---|---|---|
| `Inbox_Staging` | controlled-staging-write | zwei synthetische Zeilen anlegen, rücklesen und löschen |
| `Events_Staging` | controlled-staging-write | Resume-Zeile vorseeded, Success-Zeile über Writer, beide löschen |
| Staging-Control-Center-DB | serverseitiger controlled-staging-write | zwei Cases, drei Operationszustände und Run-Key-Cleanup |
| E4-Staging-Endpunkt | authentifiziert | nur die fünf festen synthetischen Modi |
| Staging-Feed | read-only nach Staging-Deploy | beide IDs einmal sichtbar, nach Cleanup abwesend |
| `Inbox` | read-only | Vorher-/Nachher-Hash unverändert |
| `Events` | read-only | Vorher-/Nachher-Hash unverändert |
| Live-Feed | read-only | kein synthetischer Marker |
| STRATO Staging | kontrollierter Deploy | Initial-, synthetischer und bereinigter Feedzustand |
| STRATO Live | keine Mutation | kein Deploy, nur öffentlicher Feedvergleich |

## 10. Positive Assertions

Vor Integration:

- Endpoint und Helper sind PHP-syntaktisch gültig;
- reiner Contracttest deckt Staging-, Build-, Run-Key-, Fachfall-, Fault- und Cleanup-Grenzen ab;
- aktiver Python-Einstieg enthält keine direkte MySQL-Verbindung und keine DB-Credentials;
- der unveränderte Core-Self-Test und der Adapter-Self-Test sind grün;
- `Control Center CI`, `Project Guardrails` und `PR Gate` sind grün.

Vor dem Ersatz-Write:

- Target-SHA ist vollständiger aktueller `staging`-Head;
- passender Staging-Deploy und Build sind grün;
- Staging-Endpunkt bestätigt DB-Schema und synthetische Restfreiheit;
- Tabs und Header sind gültig;
- Live wird nur gelesen.

Write und Resume:

- beide Inbox-Zeilen und Cases sind eindeutig;
- beide Preflights sind `mutation=false`, erlaubt, blockierungsfrei und auf `Inbox_Staging -> Events_Staging` geroutet;
- Success appendet genau ein Event und setzt Inbox auf `übernommen`;
- gleicher Operation-Key liefert idempotenten Replay;
- nur der exakte kontrollierte Fehlerzustand wird serverseitig angelegt;
- fehlgeschlagener Operation-Key bleibt HTTP 409 fail-closed;
- neuer Resume-Key bestätigt das vorhandene Event ohne Duplikat;
- Cases enden `done`; Operations `completed`, `failed`, `completed`;
- Staging-Feed enthält beide IDs;
- Live-Feed enthält keinen synthetischen Marker.

Cleanup:

- beide synthetischen Inbox- und Eventzeilen fehlen;
- alle Cases und Operations des Run-Keys fehlen;
- global existieren keine synthetischen E4-DB-Reste;
- bereinigter Staging-Feed enthält keinen synthetischen Marker;
- Nicht-Testdaten in Staging sind hash-identisch;
- `Inbox`, `Events` und Live-Feed sind unverändert;
- Evidence endet `result=success` ohne Cleanup-Fehler.

## 11. Negative Assertions und Stop-the-line

Abbruch vor dem ersten Write bei:

- falschem oder bewegtem Target-SHA;
- falschem Host, Build oder Environment;
- fehlendem Endpoint, Review-Zugang, Tabs, Tabellen oder Headern;
- synthetischem Restzustand;
- unerwartetem Live-Marker;
- nicht eindeutigem Seed.

Der Serververtrag wird fail-closed bei fremdem Case, fremdem Run-Key, falscher Operation oder nicht erlaubtem Modus.

Nach begonnenem Write führt jede unerwartete API-, Sheet-, DB-, Feed- oder Postcondition ausschließlich in Run-Key-begrenzten Cleanup und Evidence. Der alte direkte-DB-Lauf wird nicht erneut ausgeführt.

## 12. E2- und Runtime-Design-Gate

Vor Integration nach `staging` müssen grün sein:

- PHP-Syntax aller Control-Center-Dateien;
- `tests/control_center_e4_synthetic_runtime_contract_test.php`;
- alter Core-Self-Test und neuer Adapter-Self-Test;
- statischer Nachweis, dass der aktive Einstieg keine direkte DB-Verbindung enthält;
- vollständige Control-Center-Contracts;
- Dokumentationsinventur und Governance-Audit;
- `Control Center CI`, `Project Guardrails`, `PR Gate`;
- PR mergebar und nicht hinter `staging`.

Nach Integration müssen Deploy und fachfallfreies E3 für den neuen finalen Staging-SHA erneut grün sein.

## 13. Ausführungsfolge

1. Serververtrag, Adapter, Tests, Guards und Evidence auf Feature-Branch abschließen.
2. E2 vollständig grün bestätigen.
3. Nach `staging` integrieren.
4. Deploy und read-only E3 für den neuen finalen Staging-SHA bestätigen.
5. Genau einen neuen manuellen Ersatz-E4-Lauf mit diesem SHA starten.
6. Artifact und Cleanup vollständig auswerten.
7. Bei Erfolg abschließen; bei Fehler keinen unveränderten Lauf wiederholen, sondern Architecture-/Revertentscheidung treffen.

Der Main-Operatoranker muss nicht erneut geändert werden: Er checkt bei jedem Start exakt den angegebenen aktuellen Staging-Commit aus. Die dort noch gesetzten, nun ungenutzten DB-Secrets werden vom aktiven Einstieg nicht gelesen; ihre Entfernung ist kein Grund für einen weiteren Main-Bootstrap innerhalb dieses Workpacks.

## 14. Rollback

- Die Staging-Architekturkorrektur ist normal revertierbar.
- Der Main-Operatoranker bleibt ohne automatischen Trigger wirkungslos.
- Der E4-Harness löscht synthetische Sheet- und DB-Zustände in `finally`.
- Der serverseitige Cleanup bleibt bei bewegtem Staging erreichbar und löscht ausschließlich den exakten Run-Key.
- Ein Cleanup-Deploy entfernt synthetische Feedprojektionen.
- Cleanup-Fehler blockieren jede Folgeaktion.
- Kein realer Fachdatensatz ist Rollbackziel.

## 15. Abschlussgrenze

Der Workpack endet nur als:

- `E4 erfolgreich und vollständig bereinigt`; oder
- `E4 fehlgeschlagen, Evidence gesichert, kein unveränderter Lauf wiederholt, Architektur-/Revertentscheidung dokumentiert`.

Ein echter CityArt-Staging-Fall ist nicht Teil dieses Workpacks und wird auch bei grünem E4 nicht automatisch ausgeführt.
