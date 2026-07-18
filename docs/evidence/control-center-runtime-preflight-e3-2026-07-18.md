# Control Center Runtime Preflight – E3-Nachweis

Stand: 2026-07-18  
Workpack: WP-2 – Runtime-Truth und read-only Preflight  
Repository: `Stratos888/Bocholt-Erleben`  
Staging-Commit: `42d555bd76a34af1bba146b9fb740c28e1d53365`

## Ergebnis

WP-2 erreicht E3. Der reale STRATO-Staging-Build, der geschützte Control-Center-Endpoint, die Laufzeitumgebung, die Quell- und Zielressourcen sowie der ausgewählte Writer wurden am eingefrorenen CityArt-Fall read-only geprüft.

Es fand keine fachliche Mutation statt.

## Maschinenlesbare Nachweise

### Staging-Deploy

- Commit-Status: `deploy/staging-observed`
- Ergebnis: `success`
- beobachteter Deploy-Run: `29640399347`
- Build: `42d555bd76a3`

### Runtime-Preflight

- Commit-Status: `control-center/runtime-preflight-e3`
- Ergebnis: `success`
- Workflow-Run: `29640399348`
- Job: `88069849550`
- Artifact: `control-center-runtime-preflight-4`
- Artifact-ID: `8428427815`
- Artifact-Digest: `sha256:78a9b1bae524e5d76580af9d6d77abe5b35dac73ab393e533b442099f3555ba4`

Alle Workflow-Schritte waren erfolgreich:

1. lokaler Preflight-Vertrag;
2. maschinenlesbarer Pending-Status;
3. passender deployter Build;
4. CityArt-Operationsplan ohne Mutation;
5. Evidence-Zusammenfassung;
6. Artifact-Upload;
7. finaler E3-Commit-Status.

## Bestätigte Runtime-Werte

| Vertrag | Bestätigter Wert |
|---|---|
| erwarteter Build | `42d555bd76a3` |
| deployter Buildmarker | `42d555bd76a3` |
| Deploymanifest-Build | `42d555bd76a3` |
| Manifest-Umgebung | `staging` |
| konfigurierte Umgebung | `staging` |
| aufgelöste Umgebung | `staging` |
| Host | `staging.bocholt-erleben.de` |
| Endpoint | `/api/control-center/preflight.php` |
| Methode | `POST` |
| Quelltab | `Inbox_Staging` |
| Zieltab | `Events_Staging` |
| Writer | `be_cc_writeback_staging_inbox_approve_verified` |
| Live-Inbox | `not_used` |
| Live-Events | `not_used` |
| Mutation | `false` |
| Blocker | keine |

Die führende CityArt-Zeile wurde read-only in `Inbox_Staging` eindeutig aufgelöst. Das Eventziel wurde read-only als in `Events_Staging` noch nicht vorhanden bestätigt. Der Preflight gab die Aktion technisch frei, führte sie aber nicht aus.

## Datenschutz und Geheimnisfreiheit

Das Artifact enthält:

- den gekürzten Buildmarker;
- den öffentlichen CityArt-Quellverweis;
- technische Fall- und Ressourcen-Fingerprints;
- Tabs, Writer und Operationsplan;
- boolesche Assertions.

Es enthält nicht:

- Review-Passwort oder andere Credentials;
- Service-Account-Daten;
- vollständige Google-Sheet-ID;
- Tokens;
- Mail-, Payment- oder Live-Secrets.

Die Google-Sheet-Ressource erscheint ausschließlich als 16-stelliger SHA-256-Fingerprint.

## Zusätzlich behobener Deploy-Blocker

Der zunächst fehlende E3-Nachweis lag nicht am CityArt-Writer. Der beobachtete Deploy-Run `29639923944` scheiterte zuvor im Schritt `Export environment-safe Events feed to data/events.tsv`.

Read-only Prüfung des führenden Google Sheets ergab:

- `Events` und `Events_Staging` besitzen denselben kanonischen 11-spaltigen Header;
- `Events_Staging` ist korrekt header-only;
- `Events` enthält 199 nichtleere Eventzeilen;
- keine doppelte Event-ID;
- 15 legitime Gruppen mit gemeinsam verwendeter Quellen-URL, etwa Serientermine oder gemeinsame Programmseiten.

Root Cause war die pauschale Ablehnung mehrfach verwendeter Basis-URLs im Overlay-Merger. PR #99 behob dies minimal:

- IDs bleiben kanonisch und eindeutig;
- gemeinsam verwendete Basis-URLs sind erlaubt;
- eine passende bestehende ID löst genau den zugehörigen Termin auf;
- eine mehrdeutige URL ohne passende ID bleibt fail-closed blockiert.

Der anschließende Deploy wurde real erfolgreich bestätigt.

## Entscheidung nach E3

E3 belegt:

- die richtige aktuelle Runtime läuft;
- Staging verwendet nicht den Live-Writer;
- der erwartete isolierte Staging-Writer ist ausgewählt;
- Source und Target sind korrekt umgebungsgebunden;
- der frühere erneute Altfehler wurde durch die nicht deployte Runtime erklärt.

Der aktuelle Staging-Writer führt bereits folgende Schutzschritte aus:

1. führende Inbox-Zeile eindeutig auflösen;
2. optional kanonische Korrekturen schreiben und zurücklesen;
3. Event in `Events_Staging` idempotent anlegen oder aktualisieren;
4. Event vollständig zurücklesen und per Fingerprint bestätigen;
5. Inboxstatus schreiben;
6. Inboxstatus zurücklesen und bestätigen;
7. lokalen Fall anschließend abschließen und prüfen.

Damit ist vor E4 kein allgemeiner WP-3-Architekturumbau belegt. Das verbleibende Risiko ist ein kontrollierter Teilfehler zwischen Event- und Inbox-Schritt sowie dessen Wiederaufnahme.

## Nächster Workpack

Nächster fachlich zulässiger Schritt ist ein isolierter synthetischer E4-Test:

- eindeutiger Testkandidat ausschließlich in Staging;
- erfolgreicher Gesamtpfad;
- kontrollierter Teilfehler nach bestätigtem Event-Write;
- sichere Wiederaufnahme ohne Duplikat;
- Rücklesen aller Postconditions;
- vollständiges Cleanup;
- Nachweis, dass `Inbox`, `Events` und Live unverändert blieben.

Nur wenn E4 eine konkrete Wiederaufnahme- oder Operationslücke belegt, wird anschließend ein minimaler WP-3-Fix geschnitten. Ein vorsorglicher großer Writeback-Umbau ist nicht freigegeben.

## Freeze

Bis E4 geplant, implementiert und grün ist:

- kein echter CityArt-Klick;
- keine manuelle Statuskorrektur;
- keine Mutation des eingefrorenen CityArt-Falls;
- kein Live-Schreibtest;
- kein allgemeiner Writeback-Umbau.
