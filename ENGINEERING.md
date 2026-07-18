# ENGINEERING – Bocholt erleben

Diese Datei enthält ausschließlich dauerhafte technische Guardrails. Arbeitsablauf, Risikoklassen, Evidence und Chatmodell stehen in `AI_ENTRYPOINT.md`. Der aktuelle operative Status steht in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Quellen der Wahrheit

### Repo

- Aktueller GitHub-Stand auf `staging` ist die primäre Entwicklungsbaseline.
- Für einen ausdrücklich beauftragten Live-Sofortpfad ist der aktuelle `main`-SHA die verbindliche Patch-Baseline.
- Ein aktiver lokaler Clone/Codespace kann die Baseline ergänzen, wenn sein Ref und Diff sichtbar sind.
- ZIPs sind Fallback oder bewusster Snapshotvergleich.
- Kein Patch ohne aktuellen Inhalt der betroffenen Owner-Dateien.
- Der Normalfall ist Release ausschließlich `staging -> main`. Direkte KI-Schreibaktionen auf `main` sind nur im kontrollierten Live-Sofortpfad aus `AI_ENTRYPOINT.md` zulässig.

### Eventdaten

- Google Sheet `Events` ist die redaktionelle Live-Quelle für Sheet-/KI-Events.
- `Events_Staging` ist das isolierte Staging-Overlay.
- DB-Submissions ergänzen den öffentlichen Feed über den freigegebenen API-Pfad.
- `data/events.tsv` und `data/events.json` sind generierte Deploy-/Runtime-Artefakte, keine manuell gepflegte Source of Truth.
- Staging verwendet `Inbox_Staging` und `Inbox_Archive_Staging`; Live verwendet `Inbox` und `Inbox_Archive`.
- Ein ausdrücklich beauftragtes einzelnes Live-Event darf als deterministische Admin-Mutation direkt in `Events` angelegt oder korrigiert werden, wenn stabile Identität, Vorherzustand, Rücklesen und Rollback feststehen.

### Sonstige Daten

- Activities bleiben repo-/JSON-owned, solange kein eigener Owner-Vertrag sie verschiebt.
- Reports, Search-Metrics und Audit-Artefakte sind Evidence oder Vorschläge, keine fachliche Quelle.
- KI- oder Audit-Ausgaben dürfen fachliche Source-Daten nicht blind überschreiben.

## 2. Owner- und Scope-Regeln

- Komponenten stylen sich selbst; Layoutdateien platzieren sie.
- Änderungen gehören in die fachlich owning Datei, nicht in nachträgliche Override-Ketten.
- Cross-file-Fixes nur, wenn die Root Cause mehrere Owner nachweislich betrifft.
- Neue zentrale Abstraktionen benötigen einen klaren Owner und müssen alte Parallelpfade ersetzen, nicht nur ergänzen.
- Ein aktiver Code-/Owner-Lock sperrt den Bereich für weitere schreibende Workpacks.
- Ein Live-Sofortpfad erhält einen exklusiven Lock auf seine Owner-Dateien oder die externe Zielressource.

Besonders zentral und standardmäßig seriell:

- `.github/workflows/**`;
- `AI_ENTRYPOINT.md`, `ENGINEERING.md`, `MASTER.md`, `ROADMAP.md`, `TEST_STATUS.md`;
- `docs/architecture/SYSTEM_MAP.md` und `docs/workpacks/active/CURRENT_WORKPACK.md`;
- globale CSS-/JS-/Bootstrap-/Environment-Entrypoints;
- Control-Center-Schema-, Source-, Writeback- und Environment-Dateien;
- Service Worker, Cache, Deploy und gemeinsame Datenverträge.

Diese zentralen Bereiche sind vom direkten Main-Hotfixpfad ausgeschlossen.

## 3. Architekturprinzip: vereinfachen statt schichten

Bei einer Root-Cause-Korrektur gilt die Zielreihenfolge:

1. konkurrierende Pfade identifizieren;
2. einen autoritativen Resolver, Planer oder Owner bestimmen;
3. Parallelpfade entfernen oder klar deaktivieren;
4. erst danach Guards ergänzen.

Nicht als nachhaltiger Endzustand akzeptieren:

- mehrere fachlich gleichwertige Writer;
- Host-, Environment- und Ressourcenziele an verschiedenen Stellen unabhängig auflösen;
- Wrapper auf Wrapper setzen, ohne den alten Pfad zu entfernen;
- Erfolg melden, obwohl nur ein Teilschritt bestätigt ist;
- Nutzeraktionen als Ersatz für fehlende Observability verwenden.

## 4. Runtime- und Environment-Vertrag

Kritische Runtimepfade benötigen genau eine autoritative Environment-Auflösung. Branch, Host, Konfiguration, Quellressource und Zielressource dürfen sich nicht unabhängig widersprechen.

Für `R2` und `R3` muss ein read-only Preflight vor realen Nebenwirkungen mindestens ausweisen:

- Build-SHA;
- Host und Requestpfad;
- Endpointversion;
- konfigurierte und aufgelöste Umgebung;
- Quell- und Zielressource;
- ausgewählten Writer beziehungsweise Operationsplan.

Ein grüner Deploy beweist Erreichbarkeit, aber nicht automatisch den tatsächlich gewählten Backend- oder Writebackpfad.

## 5. Writeback- und Transaktionsregeln

Für wiederverwendbare Schreibprozesse gilt:

- Dry-Run und Ausführung müssen denselben Planer verwenden.
- Quell- und Zielressourcen werden vor dem ersten Write an die Operation gebunden.
- Jeder externe Write wird zurückgelesen und gegen erwartete Postconditions geprüft.
- Eine Teilmutation darf niemals als Erfolg erscheinen.
- Retry muss idempotent sein oder an der letzten bestätigten Stufe fortsetzen.
- Ein echter Fachdatensatz wird erst nach isoliertem synthetischem Staging-Beweis verwendet.
- Live-Schreibtests sind ausgeschlossen.

Für eine einzelne deterministische Live-Admin-Mutation – insbesondere ein ausdrücklich beauftragtes Event in `Events` – gilt die engere Ausnahme:

- genau eine stabile Zielidentität;
- Vorherzustand vollständig lesen;
- nur deklarierte Felder ändern;
- sofortiges Rücklesen;
- klare Rücknahmeoption;
- kein Testen durch wiederholte Live-Mutationen.

Staging- und Live-Ressourcen:

```text
staging: Inbox_Staging -> Events_Staging
live:    Inbox         -> Events
```

`Events` bleibt für normale Staging-Arbeit read-only. Das Staging-Overlay darf beim Deploy gelesen, aber nicht mit Live verwechselt werden.

## 6. Externe Ressourcen und Ressourcen-Lock

Die kanonische Matrix steht in `docs/external-resource-matrix.md`.

- Alles, was nicht ausdrücklich als staging-isoliert oder als einzelne kontrollierte Live-Admin-Mutation nachgewiesen ist, bleibt fail-closed und read-only.
- Pro externer Ressource darf nur ein kontrollierter Schreib- oder Abnahmevorgang aktiv sein.
- Ein Ressourcen-Lock gilt unabhängig von Git-Dateipfaden.
- Vor einem kontrollierten Write müssen Ziel, stabile Identität, Vorherzustand, erwartete Mutation, Rücklesen und Cleanup/Rollback feststehen.
- Normale Implementierungsschritte mutieren keine externen Daten.

## 7. Deploy-, Workflow- und Cache-Sicherheit

- `Deploy to STRATO` akzeptiert ausschließlich `main` und `staging` und blockiert andere Refs vor Secrets und externen Zugriffen.
- Branch- und Zielauflösung liegen zentral in `scripts/resolve-deploy-target.sh`.
- Staging- und Live-Deploys laufen nie parallel.
- Ein Integrations- oder Hotfixschritt endet erst nach Deploy und erforderlicher Abnahme.
- Schwere Content-, Growth-, KI- und Audit-Läufe werden nicht pauschal an jeden `staging`-Push gekoppelt.
- `PR Gate` bleibt stabiler Always-run-Check für PR-Pfade; er ersetzt keine Runtime-Evidence.
- Der direkte Main-Hotfixpfad verwendet keinen PR als Umweg, sondern benötigt einen eigens freigegebenen Ruleset-Bypass-Akteur und die Live-Hotfix-Gates aus `AI_ENTRYPOINT.md`.
- Workflowdateien werden nicht durch selbstmodifizierende One-off-Workflows geändert.
- Service Worker, Cache und Asset-Versionierung werden nicht beiläufig verändert.
- Der Deploy setzt öffentliche Asset-Versionen über `BUILD_ID`; keine manuellen Query-Bumps über viele HTML-Dateien.

## 8. CSS-, UI- und Visual-Regeln

### CSS-Owner

- `css/style.css`: öffentlicher Entrypoint und Importreihenfolge.
- `css/base.css`: Tokens, Reset, Foundation.
- `css/pages.css`: statische/public Seiten und Funnel.
- `css/components.css`: wiederverwendbare Komponenten.
- `css/home.css`: historischer Discovery-/Event-/Activity-Shell-Owner.
- `css/today.css`: Today/Home-spezifische Flächen.
- `css/overlays.css`: Detailpanel, Sheets, Modals und Overlay-Locks.

Regeln:

- Token-first; keine unnötigen Hardcodes.
- CSS ist kein Bildqualitätskontrollsystem.
- Kleine visuelle Workpacks: ein Hauptpatch plus höchstens ein gezielter Polish-Patch; danach neu bewerten.
- Overlays rendern in einem dedizierten Root direkt unter `body`, nicht in sticky/transformed/backdrop-filter Containern.

### Visuals

Maßgeblich ist `VISUAL_WORKFLOW.md`.

- prominente Flächen verwenden nur freigegebene `ready`- oder bewusst erlaubte `fallback`-Assets;
- unsichere Bilder werden ersetzt, abgestuft oder blockiert;
- Visual-Key-, Motiv- und Assetprobleme werden upstream im Pool-/Auditvertrag gelöst, nicht dauerhaft per CSS kaschiert;
- ein Live-Hotfix darf ein nachweislich falsches Bild gezielt entfernen oder unterdrücken, aber kein ungeprüftes Ersatzmotiv als `ready` deklarieren.

## 9. Content- und KI-Regeln

- KI-Verifikation ist gezielter Fallback, kein Standard für alle Inhalte.
- Unsichere Fälle erhalten typisierte Reviewzustände statt stiller Korrektur.
- Feedbacksignale bleiben typisiert, begrenzt, konsolidiert und ablaufbar.
- Keine automatische Regelbuch- oder Prompt-Mutation aus Rohfeedback.
- Eventbeschreibungen folgen `EVENT_DESCRIPTION_STANDARD.md`.
- Visual-Fit ist eine eigene Qualitätsdomäne.

## 10. Test- und Evidence-Regeln

Tests müssen Zielverhalten beweisen, nicht nur Marker oder Funktionsnamen.

- Unit-/Contract-/Replay-/CI-Nachweise entsprechen maximal E2.
- Runtime-, Host-, Endpoint- und Ressourcenauflösung benötigen E3.
- Wiederverwendbare externe Writes benötigen E4 vor einem echten Fachfall.
- Eine einzelne deterministische Admin-Mutation benötigt Vorherzustand, Write, Rücklesen und unveränderte Nicht-Zielfelder als E4-Nachweis.
- Browser-Smokes sind read-only, außer ein R3-Testplan definiert ausdrücklich isolierte synthetische Daten und Cleanup.
- Ein roter Staging-Smoke blockiert den Release nach `main`.
- Ein direkter Main-Hotfix ist erst nach read-only Live-Smoke E6 abgeschlossen.

## 11. Stop-the-line

Sofortiger Stopp bei:

- unerwarteter Umgebung oder Ressource;
- unklarer Identität;
- Abweichung zwischen Quelle, API, DB und UI;
- unerwarteter Mutation;
- Teilmutation mit Fehlermeldung;
- fehlendem Vorherzustand;
- parallelem Zugriff auf denselben Owner oder dieselbe Ressource;
- Tests, die nur statische Marker statt Zielverhalten beweisen.

Danach gelten Fehlerbudget und Gates aus `AI_ENTRYPOINT.md`: keine Wiederholung ohne neue Evidence, keine manuelle Grünkorrektur und nach zwei widerlegten Hypothesen Architektur- oder Revert-Entscheidung. Bei einem Main-Hotfix gilt Revert vor einem zweiten Reparaturpatch.

## 12. Git- und Schreibdisziplin

- Kein Force-Push.
- Direkte Commits auf `main` sind ausschließlich im ausdrücklich beauftragten und technisch freigeschalteten Live-Sofortpfad zulässig.
- Ein direkter Main-Hotfix umfasst höchstens drei fachlich zusammengehörige Owner-Dateien und im Regelfall höchstens 100 geänderte Zeilen.
- Direkte Main-Hotfixes dürfen keine Features, Refactorings, Workflow-, Security-, Berechtigungs-, Deploy-, Environment- oder Governance-Änderungen enthalten.
- Der GitHub-Akteur für direkte KI-Hotfixes muss im Main-Ruleset gezielt freigegeben sein; eine pauschale Abschaltung des Main-Schutzes ist unzulässig.
- Fehlt diese technische Freigabe, wird kein breiter `staging -> main`-Merge als Ersatz durchgeführt.
- Keine Feature-Branch-Deploys.
- Keine Secrets oder Credentials im Repo oder Chat offenlegen.
- Datei- oder Branchlöschungen nur, wenn sie Teil des genehmigten Scopes sind und der Inhalt weiterhin über Git/PR nachvollziehbar bleibt.
- Revert-Commit vor Force-Reset.
- Keine neuen großen Patches auf ungeklärte oder halbfertige Zwischenstände stapeln.

## 13. Dokumentations-Governance

- `AI_ENTRYPOINT.md`: operativer Router.
- `CURRENT_WORKPACK.md`: einziger aktueller technischer Status.
- `SYSTEM_MAP.md`: aktuelle System- und Datenflussübersicht.
- `ENGINEERING.md`: dauerhafte technische Guardrails.
- `external-resource-matrix.md`: externe Ressourcen und Schreibgrenzen.
- `MASTER.md`: Strategie.
- `ROADMAP.md`: Produktprioritäten.
- `TEST_STATUS.md`: Proofindex.
- `docs/decisions/` und `docs/forensics/`: Entscheidungen und historische Evidence.

Historische Dokumente dürfen aktuelle operative Regeln nicht reaktivieren.

## 14. Projektziel

Technische Arbeit dient dem Premium-Zielzustand: vertrauenswürdig, ruhig, modern, stabil, leicht scannbar, mobile-first und als kuratierter lokaler Sichtbarkeitskanal. Keine Änderung darf das Produkt in Richtung Anzeigenportal, gekaufte Empfehlungen oder instabile Bastelarchitektur verschieben.
