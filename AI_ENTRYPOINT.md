# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`.

Diese Datei ist die einzige operative Arbeitsanweisung fuer KI-Arbeit am Repository. Sie beantwortet zuerst: Welche Quelle ist aktuell? Welcher Arbeitsmodus ist passend? Darf direkt geschrieben werden? Wann sind Draft-PR, Codespace oder ZIP sinnvoll?

## 1. Grundsatz

Bei jeder Repo-Aufgabe zuerst den aktuellen GitHub-Stand von `Stratos888/Bocholt-Erleben` auf Branch `staging` pruefen, sofern der GitHub-Connector verfuegbar ist.

Nicht aus Memory, alten Chatstaenden oder alten ZIPs als Quelle der Wahrheit arbeiten. Memory und alte Chats sind nur Kontext.

`main` bleibt geschuetzt. Direkte KI-Schreibaktionen auf `main` sind nicht erlaubt.

## 2. Quellenhierarchie

Fuer Repo-Arbeit gilt:

1. GitHub-Connector: aktueller Branch `staging`.
2. Aktiver Codespace- oder lokaler Git-Stand, wenn der Nutzer Terminalausgaben aus einer laufenden Arbeitskopie liefert.
3. ZIP-Snapshot nur als Fallback oder bewusster Vergleichsstand.
4. Memory, fruehere Chats und alte Artefakte nur als Kontext.

Fuer fachliche Daten gilt weiterhin die jeweilige Source of Truth. Details stehen in `ENGINEERING.md`.

## 3. Start-Gate vor jeder Umsetzung

Vor Umsetzung nennt die KI knapp:

- gelesene Quelle und Branch/Ref,
- gelesene oder betroffene Dateien,
- Arbeitsmodus: G1, G2, G3, G4 oder G5,
- Risiko: niedrig, mittel oder hoch,
- ob eine Schreibfreigabe im aktuellen Chat noetig ist.

Keine Umsetzung ohne belegten aktuellen Stand der betroffenen Datei.

## 4. Arbeitsmodi

### G1 — GitHub Read

Standard fuer:

- Analyse,
- Review,
- Ursachenpruefung,
- Doku-Abgleich,
- Planung,
- Patch-Entscheidung.

Keine Schreibaktion.

### G2 — Direkter Commit auf `staging`

Nur nach ausdruecklicher Nutzerfreigabe im aktuellen Chat.

Geeignet fuer kleine, klar begrenzte und reversible Aenderungen:

- Doku,
- kleine Copy-/HTML-Korrekturen,
- kleine CSS-Polishs in klarer Owner-Datei,
- kleine statische JS-Guards,
- kleine Audit-/Konfigurationshaertungen ohne Laufzeitkomplexitaet.

Nicht geeignet fuer:

- `main`,
- Force-Push,
- Branch-Loeschungen,
- Datei-Loeschungen ohne ausdrueckliche Spezialfreigabe,
- Deploy-/Workflow-/Service-Worker-/Cache-Aenderungen,
- Backend/API/DB/Stripe/Mail,
- grosse Multi-Datei-Patches,
- generierte Datenartefakte als fachliche Quelle,
- automatische fachliche Datenkorrekturen aus KI-/Audit-Ergebnissen.

Nach einem G2-Commit nennt die KI:

- Commit-SHA,
- geaenderte Dateien,
- knappe Diff-Zusammenfassung,
- erwartete Staging-Pruefung,
- Revert-Weg.

### G3 — AI-Branch + Draft-PR gegen `staging`

Standard fuer mittlere und groessere Implementierungen.

Geeignet fuer:

- mehrere Dateien,
- neue Struktur,
- Dashboard-/Inbox-/Tracking-Aenderungen,
- UI- oder JS-Aenderungen mit hoeherem Regressionsrisiko,
- Aenderungen, die vor Merge im GitHub-Diff geprueft werden sollen.

Der PR bleibt Review-Flaeche. Merge nach `staging` erfolgt bewusst.

### G4 — Codespace / lokale Ausfuehrung

Codespace ist nicht mehr Standard fuer jede Repo-Arbeit. Codespace ist Spezialwerkzeug fuer Ausfuehrung, Preview, Smoke, Build und Debug.

Nutzen, wenn eine Aenderung ohne lokale Ausfuehrung nicht ausreichend sicher ist:

- Browser-Smoke / Playwright,
- Python-/Node-/PHP-Ausfuehrung,
- Build-/Deploy-Skripte,
- generierte Artefakte,
- Backend/API/DB/Stripe/Mail,
- Service Worker / Cache / Deploy-Workflow,
- grosse Refactorings,
- Asset-/Bildverarbeitung.

### G5 — ZIP-Fallback

Nur wenn GitHub-Connector oder Repo-Zugriff nicht verfuegbar ist oder ein bewusster Snapshot-Vergleich benoetigt wird.

Patch-ZIPs bleiben ohne Wrapper-Dateien und enthalten direkt die Repo-Root-Struktur.

## 5. Visueller Arbeitsloop

Fuer kleine visuelle Aenderungen ist Staging selbst die Preview.

Standard:

```text
GitHub/staging lesen
-> Owner-Datei bestimmen
-> kleiner G2-Commit nach Freigabe
-> Staging-Deploy abwarten
-> Nutzer prueft echte Ansicht/Screenshot
-> maximal ein gezielter Polish-Commit
-> Freeze oder Stop
```

Wenn nach einem Hauptcommit und einem Polish-Commit weiter nachgebessert werden muesste, stoppen und neu bewerten. Nicht weiterflicken.

Codespace Preview ist nur fuer komplexere Vorabpruefung oder Ausfuehrungsbedarf gedacht.

## 6. Doku-only-Regel

Doku-only-Aenderungen duerfen nicht in viele kleine Staging-Commits zerfallen, weil jeder Push aktuell den Deploy-Workflow ausloest.

Daher gilt:

- Doku-Prozessumstellungen buendeln.
- Wenn mehrere Doku-Dateien betroffen sind: ein konsolidierter Doku-Commit oder ein bewusstes Doku-Paket.
- Keine Runtime-Dateien mit reinen Doku-Workpacks vermischen.

## 7. GitHub-Actions- und Staging-Testpfad

Fuer Workflow-Trigger gilt zusaetzlich `docs/github-actions-trigger-policy.md`.

Gewollter Zustand:

- Kleine `staging`-Commits sollen primaer `Deploy to STRATO` ausloesen.
- Schwere Audits, Growth-, KI-, Inbox- und Content-Ops-Laeufe sollen nicht pauschal bei jedem `staging`-Push laufen.
- Diese Entkopplung ist bewusst und kein Fehler.
- Qualitaetssicherung bleibt ueber `schedule`, `workflow_dispatch`, `workflow_run`, `main` oder relevante Pfade erhalten.

Bekannter Stand vom 2026-07-07:

- `Growth Intelligence Backlog` ist von Push-Triggern entkoppelt.
- `Content Ops HTTP Ingest` ist von Push-Triggern entkoppelt.
- `Deploy to STRATO` bleibt der schnelle Staging-Push-Pfad.
- `Content Quality Audit` hat noch einen bekannten verbleibenden Haertungspunkt: aktueller `staging`-Push-Trigger mit breiten Pfaden, insbesondere `data/**`. Ziel bleibt, diesen schweren Audit nicht pauschal an normale Staging-Testcommits zu koppeln.

Vor jeder Workflow-Aenderung konkret pruefen:

- `on.push.branches`,
- `on.push.paths`,
- `schedule`,
- `workflow_dispatch`,
- `workflow_run`,
- job-level `if`,
- Downstream-Dispatches und Artefakt-Abhaengigkeiten.

Workflow-Dateien duerfen nicht per selbstmodifizierendem One-off-Workflow geaendert werden. Ein nur auf `staging` liegender `workflow_dispatch`-Workflow bietet keinen verlaesslichen manuellen Run-Button, und `GITHUB_TOKEN` mit `contents: write` reicht in diesem Repo nicht aus, um Dateien unter `.github/workflows/` aus einem Actions-Run heraus zu aendern. Details stehen in `docs/github-actions-trigger-policy.md`.

## 8. Ruecknahme

Falsche Staging-Commits werden bevorzugt per Revert-Commit zurueckgenommen.

Kein Force-Reset als Standard.

## 9. Harte Grenzen fuer KI

- Nie direkt auf `main` schreiben.
- Kein Force-Push.
- Keine Branch-Loeschung.
- Keine Datei-Loeschung ohne ausdrueckliche Spezialfreigabe.
- Keine Secrets oder Credentials anfassen.
- Keine externen Deployments still aendern.
- Keine generierten Datenartefakte als fachliche Quelle pflegen.
- Keine ungeprueften automatischen fachlichen Datenkorrekturen.
- Keine konkurrierenden parallelen Workpacks auf demselben Branch.

## 10. Wichtige Steuerdateien

- `AI_ENTRYPOINT.md` – operative KI-Arbeitsweise und Workflow-Router.
- `ENGINEERING.md` – technische Guardrails, Owner-Regeln, Datenquellen, Asset-/Deploy-Sicherheit.
- `MASTER.md` – strategische Produktsteuerung.
- `ROADMAP.md` – aktuelle taktische Workpacks.
- `TEST_STATUS.md` – Proofs und Abnahmen.
- `Produktvertrag.md` – Produktmodell und kommerzielle Logik.
- `VISUAL_WORKFLOW.md` – Bild-, Motiv- und Asset-Arbeitsweise.
- `COMMERCIAL_STRATEGY.md` – Anbieter- und Veranstalterstrategie.
- `EVENT_IMPACT_TRACKING.md` – Nutzwert- und Wirkungsmessung.
- `docs/github-actions-trigger-policy.md` – gewollte Actions-Trigger-Architektur fuer schnellen Staging-Testpfad und schwere Qualitaetslaeufe.

## 11. Praktische Standardformeln

Wenn der Nutzer sagt:

- `Analysiere den aktuellen Stand`: G1.
- `Mach den kleinen Fix direkt`: G2, wenn niedriges Risiko.
- `Groesseres Feature`: G3 oder G4 nach Risiko.
- `Visuell pruefen`: kleiner G2-Staging-Loop, wenn owner-file-klar.
- `Doku harmonisieren`: buendeln, kein Mikro-Commit.
- `Ohne GitHub-Zugriff`: G5.

## 12. Dokumentationshierarchie

Fuer Workflow-Routing gilt:

1. `AI_ENTRYPOINT.md`.
2. `ENGINEERING.md` fuer technische Sicherheits-, Owner-, Validierungs- und Datenquellenregeln.
3. `MASTER.md` fuer strategische Produktsteuerung.
4. `ROADMAP.md` fuer aktive Workpacks.
5. `TEST_STATUS.md` fuer Proofs und Abnahmen.
6. `docs/github-actions-trigger-policy.md` fuer GitHub-Actions-Trigger, schnellen Staging-Testpfad und schwere Qualitaetslaeufe.
