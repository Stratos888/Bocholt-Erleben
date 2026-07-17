# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`.

Diese Datei ist die einzige operative Arbeitsanweisung fuer KI-Arbeit am Repository. Sie beantwortet zuerst: Welche Quelle ist aktuell? Welcher Arbeitsmodus ist passend? Darf direkt geschrieben werden? Wann sind Draft-PR, Codespace oder ZIP sinnvoll? Wie werden mehrere parallele Chats sicher koordiniert?

## 1. Grundsatz

Bei jeder Repo-Aufgabe zuerst den aktuellen GitHub-Stand von `Stratos888/Bocholt-Erleben` auf Branch `staging` pruefen, sofern der GitHub-Connector verfuegbar ist.

Nicht aus Memory, alten Chatstaenden oder alten ZIPs als Quelle der Wahrheit arbeiten. Memory und alte Chats sind nur Kontext.

`main` bleibt geschuetzt. Direkte KI-Schreibaktionen auf `main` sind nicht erlaubt.

Parallelitaet bedeutet parallele Analyse und isolierte Branch-Arbeit. Integration, Staging-Deploy, externe Datenmutation und Release bleiben immer sequenziell.

## 2. Quellenhierarchie

Fuer Repo-Arbeit gilt:

1. GitHub-Connector: aktueller Branch `staging`.
2. Aktiver Codespace- oder lokaler Git-Stand, wenn der Nutzer Terminalausgaben aus einer laufenden Arbeitskopie liefert.
3. ZIP-Snapshot nur als Fallback oder bewusster Vergleichsstand.
4. Memory, fruehere Chats und alte Artefakte nur als Kontext.

Fuer fachliche Daten gilt weiterhin die jeweilige Source of Truth. Details stehen in `ENGINEERING.md` und `docs/external-resource-matrix.md`.

## 3. Start-Gate vor jeder Umsetzung

Vor Umsetzung nennt die KI knapp:

- gelesene Quelle und Branch/Ref;
- aktuellen `staging`-SHA oder eindeutig belegten Basestand;
- gelesene oder betroffene Dateien;
- Arbeitsmodus: G1, G2, G3, G4 oder G5;
- Risiko: niedrig, mittel oder hoch;
- offene Draft-PRs beziehungsweise erkennbare Code- und Ressourcen-Locks;
- betroffene externe Ressourcen und vorgesehenen Zugriff `none`, `read-only` oder `controlled-write`;
- ob eine Schreibfreigabe im aktuellen Chat noetig ist.

Keine Umsetzung ohne belegten aktuellen Stand der betroffenen Datei. Kein neuer Workpack, solange sein Code- oder Ressourcen-Scope mit einem aktiven Workpack kollidiert.

## 4. Arbeitsmodi

### G1 — GitHub Read

Standard fuer:

- Analyse;
- Review;
- Ursachenpruefung;
- Doku-Abgleich;
- Planung;
- Patch-Entscheidung;
- Incident-Forensik.

Keine Schreibaktion.

### G2 — Direkter Commit auf `staging` im exklusiven Einzelmodus

Nur nach ausdruecklicher Nutzerfreigabe im aktuellen Chat und nur, wenn nachweisbar kein paralleler KI-Workpack aktiv ist.

Geeignet fuer kleine, klar begrenzte und reversible Aenderungen:

- Doku;
- kleine Copy-/HTML-Korrekturen;
- kleine CSS-Polishs in klarer Owner-Datei;
- kleine statische JS-Guards;
- kleine Audit-/Konfigurationshaertungen ohne Laufzeitkomplexitaet.

Nicht geeignet fuer:

- Parallelbetrieb;
- `main`;
- Force-Push;
- Branch-Loeschungen;
- Datei-Loeschungen ohne ausdrueckliche Spezialfreigabe;
- Deploy-/Workflow-/Service-Worker-/Cache-Aenderungen;
- Backend/API/DB/Stripe/Mail;
- externe Datenpfade oder Writebacks;
- grosse Multi-Datei-Patches;
- generierte Datenartefakte als fachliche Quelle;
- automatische fachliche Datenkorrekturen aus KI-/Audit-Ergebnissen.

Nach einem G2-Commit nennt die KI:

- Commit-SHA;
- geaenderte Dateien;
- knappe Diff-Zusammenfassung;
- erwartete Staging-Pruefung;
- Revert-Weg.

### G3 — AI-Branch + Draft-PR gegen `staging`

Standard fuer mittlere und groessere Implementierungen und fuer jede KI-Schreibarbeit im Parallelbetrieb.

Geeignet fuer:

- mehrere Dateien;
- neue Struktur;
- Dashboard-/Inbox-/Tracking-Aenderungen;
- UI- oder JS-Aenderungen mit hoeherem Regressionsrisiko;
- Deploy-, Workflow- oder externe Datenpfade;
- Aenderungen, die vor Merge im GitHub-Diff geprueft werden sollen.

Regeln:

- genau ein eigener Branch pro Workpack;
- Draft-PR nach dem ersten belastbaren Commit als sichtbarer Arbeits- und Lock-Nachweis;
- PR-Ziel ist `staging`, nie direkt `main`;
- der Workpack-Chat merged nicht selbst;
- der Workpack-Chat startet keinen Deploy;
- der Workpack-Chat mutiert keine externen Daten;
- vor `Ready for integration` aktuellen `staging`-Stand einbeziehen und alle relevanten Tests erneut ausfuehren.

Der PR bleibt Review-Flaeche. Merge nach `staging` erfolgt bewusst und sequenziell durch den Integrations-Chat.

### G4 — Codespace / lokale Ausfuehrung

Codespace ist nicht mehr Standard fuer jede Repo-Arbeit. Codespace ist Spezialwerkzeug fuer Ausfuehrung, Preview, Smoke, Build und Debug.

Nutzen, wenn eine Aenderung ohne lokale Ausfuehrung nicht ausreichend sicher ist:

- Browser-Smoke / Playwright;
- Python-/Node-/PHP-Ausfuehrung;
- Build-/Deploy-Skripte;
- generierte Artefakte;
- Backend/API/DB/Stripe/Mail;
- Service Worker / Cache / Deploy-Workflow;
- grosse Refactorings;
- Asset-/Bildverarbeitung.

Mehrere schreibende Chats duerfen niemals dieselbe Codespace-Arbeitskopie verwenden. Entweder eigener Worktree/Clone pro Branch oder GitHub-Connector-Arbeit auf getrennten Branches.

### G5 — ZIP-Fallback

Nur wenn GitHub-Connector oder Repo-Zugriff nicht verfuegbar ist oder ein bewusster Snapshot-Vergleich benoetigt wird.

Patch-ZIPs bleiben ohne Wrapper-Dateien und enthalten direkt die Repo-Root-Struktur.

## 5. Verbindlicher Parallelbetrieb

### 5.1 Rollenmodell

Der sichere Standard besteht aus:

- einem **Integrations-Chat**;
- maximal zwei aktive Workpack-Chats, aber nur bei nachgewiesener Unabhaengigkeit.

Der Integrations-Chat ist allein zustaendig fuer:

- aktuellen `staging`-Stand und offene Draft-PRs;
- Zuschnitt neuer Workpacks;
- Code-/Owner- und Ressourcen-Locks;
- Abhaengigkeiten und Merge-Reihenfolge;
- Merge nach `staging`;
- Staging-Deploy und Abnahme;
- Merge `staging -> main`;
- read-only Live-Smoke;
- abschliessende kanonische Dokumentation.

Workpack-Chats analysieren und implementieren nur ihren vereinbarten Scope auf dem eigenen Branch.

### 5.2 Risikobasierte Besetzung

- Kleine, unabhaengige Workpacks: Integrations-Chat plus bis zu zwei schreibende Workpack-Chats.
- Normaler mittlerer Workpack: Integrations-Chat plus ein schreibender Workpack-Chat.
- Daten-, Writeback-, Deploy- oder Architektur-Workpack: Integrations-Chat plus Implementierungs-Chat plus unabhaengiger read-only Review-Chat.
- Incident oder ungeklärte Mutation: Integrations-Chat plus read-only Forensik-Chat; alle funktionalen Implementierungen im betroffenen Scope stoppen.

### 5.3 Workpack-Vertrag

Vor dem ersten Patch muessen im Draft-PR stehen:

- Workpack und Zielzustand;
- Ausgangs-SHA von `staging`;
- belegter Ist-Zustand und Root Cause beziehungsweise klar markierte Hypothese;
- erlaubte Dateien/Pfade;
- gesperrte Dateien/Pfade;
- Code-/Owner-Lock;
- externe Ressourcen und Zugriffsklasse;
- Ressourcen-Lock;
- Abhaengigkeiten zu anderen PRs;
- Abnahmekriterien und Tests;
- Staging-Abnahme;
- Rollback/Revert.

Die Vorlage liegt in `.github/pull_request_template.md`.

### 5.4 Integrationsregel

Parallele Branch-Arbeit ist erlaubt. Integration ist immer seriell:

```text
PR A aktualisieren -> CI -> Merge nach staging -> Deploy -> Abnahme -> stabiler Zwischenstand
PR B aktualisieren -> CI -> Merge nach staging -> Deploy -> Abnahme
```

Nie zwei Runtime-PRs gleichzeitig mergen. Nie einen zweiten `staging`-Merge starten, solange Deploy oder Abnahme des ersten PRs offen ist.

### 5.5 Externe Ressourcen

Die verbindliche Matrix steht in `docs/external-resource-matrix.md`.

- Workpack-Chats bleiben bei externen Ressourcen read-only.
- `controlled-write` ist keine Vorabfreigabe, sondern eine Anforderung an eine spaetere Integrationsabnahme.
- Alles, was nicht nachweisbar staging-isoliert ist, bleibt fail-closed und read-only.
- Live-Schreibtests sind ausgeschlossen.

## 6. Visueller Arbeitsloop

Fuer kleine visuelle Aenderungen im exklusiven Einzelmodus ist Staging selbst die Preview.

Standard:

```text
GitHub/staging lesen
-> Owner-Datei bestimmen
-> kleiner G2-Commit nach Freigabe oder G3-Branch im Parallelbetrieb
-> Staging-Deploy durch den Integrationspfad
-> Nutzer prueft echte Ansicht/Screenshot
-> maximal ein gezielter Polish-Commit
-> Freeze oder Stop
```

Wenn nach einem Hauptcommit und einem Polish-Commit weiter nachgebessert werden muesste, stoppen und neu bewerten. Nicht weiterflicken.

Codespace Preview ist nur fuer komplexere Vorabpruefung oder Ausfuehrungsbedarf gedacht.

## 7. Doku-only-Regel

Doku-only-Aenderungen duerfen nicht in viele kleine Staging-Commits zerfallen, weil jeder Push aktuell den Deploy-Workflow ausloest.

Daher gilt:

- Doku-Prozessumstellungen buendeln;
- bei mehreren Doku-Dateien ein konsolidiertes Doku-Paket beziehungsweise ein Draft-PR;
- keine Runtime-Dateien mit reinen Doku-Workpacks vermischen;
- im Parallelbetrieb auch Doku-Aenderungen ueber den eigenen Branch fuehren, wenn sie kanonische Steuerdateien betreffen.

## 8. GitHub-Actions- und Staging-Testpfad

Fuer Workflow-Trigger gilt zusaetzlich `docs/github-actions-trigger-policy.md`.

Gewollter Zustand:

- Kleine `staging`-Commits sollen primaer `Deploy to STRATO` ausloesen.
- Schwere Audits, Growth-, KI-, Inbox- und Content-Ops-Laeufe sollen nicht pauschal bei jedem `staging`-Push laufen.
- Diese Entkopplung ist bewusst und kein Fehler.
- Qualitaetssicherung bleibt ueber `schedule`, `workflow_dispatch`, `workflow_run`, `main` oder relevante Pfade erhalten.
- `Deploy to STRATO` darf ausschliesslich `main` und `staging` akzeptieren; jeder andere Ref bricht vor externem Zugriff fail-closed ab.

Bekannter Stand vom 2026-07-07:

- `Growth Intelligence Backlog` ist von Push-Triggern entkoppelt.
- `Content Ops HTTP Ingest` ist von Push-Triggern entkoppelt.
- `Deploy to STRATO` bleibt der schnelle Staging-Push-Pfad.
- `Content Quality Audit` ist final vom normalen `staging`-Push entkoppelt; er laeuft per `schedule`, `workflow_dispatch` und gezielt bei relevanten `main`-Pfaden.

Vor jeder Workflow-Aenderung konkret pruefen:

- `on.push.branches`;
- `on.push.paths`;
- `schedule`;
- `workflow_dispatch`;
- `workflow_run`;
- job-level `if`;
- Downstream-Dispatches und Artefakt-Abhaengigkeiten;
- Branch-/Environment-Aufloesung vor Secrets und externen Zugriffen.

Workflow-Dateien duerfen nicht per selbstmodifizierendem One-off-Workflow geaendert werden. Ein nur auf `staging` liegender `workflow_dispatch`-Workflow bietet keinen verlaesslichen manuellen Run-Button, und `GITHUB_TOKEN` mit `contents: write` reicht in diesem Repo nicht aus, um Dateien unter `.github/workflows/` aus einem Actions-Run heraus zu aendern. Details stehen in `docs/github-actions-trigger-policy.md`.

## 9. Incident- und Stop-the-line-Regel

Sofort funktional stoppen bei:

- unerwarteter Umgebung, Sheet-ID, Tab-, DB- oder Deploy-Zielauflösung;
- unklarer oder mehrfacher Objekt-/Zeilenidentitaet;
- Abweichung zwischen Quelle, lokaler Kopie, API und UI;
- unerwarteter realer Datenmutation;
- fehlendem Vorherzustand bei einer Schreibprobe;
- manueller Korrektur, die einen technischen Test scheinbar gruen macht;
- Tests, die nur Marker statt Zielverhalten beweisen;
- parallelem Zugriff auf denselben Code- oder Ressourcen-Scope.

Danach gilt:

```text
keine weitere Implementierung
keine Datenkorrektur
keine neue Schreibprobe
kein Merge
nur read-only Forensik und Evidence-Sicherung
```

Die Arbeit wird erst nach belegter Root Cause, stabiler Zustandsaufnahme und ausdruecklicher Freigabe durch den Integrations-Chat fortgesetzt.

## 10. Ruecknahme

Falsche Staging-Commits werden bevorzugt per Revert-Commit zurueckgenommen.

Kein Force-Reset als Standard.

## 11. Harte Grenzen fuer KI

- Nie direkt auf `main` schreiben.
- Kein Force-Push.
- Keine Branch-Loeschung.
- Keine Datei-Loeschung ohne ausdrueckliche Spezialfreigabe.
- Keine Secrets oder Credentials anfassen.
- Keine externen Deployments still aendern.
- Kein Deploy von Feature-/Agent-Branches.
- Keine externe Datenmutation durch Workpack-Chats.
- Keine generierten Datenartefakte als fachliche Quelle pflegen.
- Keine ungeprueften automatischen fachlichen Datenkorrekturen.
- Keine konkurrierenden parallelen Workpacks auf demselben Branch, denselben Owner-Pfaden oder derselben externen Ressource.
- Kein selbststaendiger Merge eines Workpack-Chats nach `staging` oder `main`.

## 12. Wichtige Steuerdateien

- `AI_ENTRYPOINT.md` – operative KI-Arbeitsweise, Rollen und Workflow-Router.
- `ENGINEERING.md` – technische Guardrails, Owner-Regeln, Datenquellen, Asset-/Deploy-Sicherheit.
- `docs/external-resource-matrix.md` – externe Ressourcen, Isolation, Schreibstatus und Locks.
- `.github/pull_request_template.md` – kompakter Workpack-, Scope- und Lock-Vertrag.
- `MASTER.md` – strategische Produktsteuerung.
- `ROADMAP.md` – aktuelle taktische Workpacks.
- `TEST_STATUS.md` – Proofs und Abnahmen.
- `Produktvertrag.md` – Produktmodell und kommerzielle Logik.
- `VISUAL_WORKFLOW.md` – Bild-, Motiv- und Asset-Arbeitsweise.
- `COMMERCIAL_STRATEGY.md` – Anbieter- und Veranstalterstrategie.
- `EVENT_IMPACT_TRACKING.md` – Nutzwert- und Wirkungsmessung.
- `docs/github-actions-trigger-policy.md` – gewollte Actions-Trigger-Architektur fuer schnellen Staging-Testpfad und schwere Qualitaetslaeufe.

## 13. Praktische Standardformeln

Wenn der Nutzer sagt:

- `Analysiere den aktuellen Stand`: G1.
- `Mach den kleinen Fix direkt`: G2 nur im exklusiven Einzelmodus, sonst G3.
- `Groesseres Feature`: G3 oder G4 nach Risiko.
- `Arbeite parallel`: Integrations-Chat plus getrennte G3-Branches; zuerst Locks und Ressourcen pruefen.
- `Visuell pruefen`: kleiner G2-Staging-Loop im Einzelmodus oder G3 im Parallelbetrieb.
- `Doku harmonisieren`: buendeln, kein Mikro-Commit.
- `Ohne GitHub-Zugriff`: G5.

## 14. Dokumentationshierarchie

Fuer Workflow-Routing gilt:

1. `AI_ENTRYPOINT.md`.
2. `ENGINEERING.md` fuer technische Sicherheits-, Owner-, Validierungs- und Datenquellenregeln.
3. `docs/external-resource-matrix.md` fuer externe Ressourcen und Schreibgrenzen.
4. `MASTER.md` fuer strategische Produktsteuerung.
5. `ROADMAP.md` fuer aktive Workpacks.
6. `TEST_STATUS.md` fuer Proofs und Abnahmen.
7. `docs/github-actions-trigger-policy.md` fuer GitHub-Actions-Trigger, schnellen Staging-Testpfad und schwere Qualitaetslaeufe.
