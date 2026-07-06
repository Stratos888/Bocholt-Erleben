# GitHub-first KI-Arbeitsweise – Bocholt erleben

Stand: 2026-07-06.

Diese Datei dokumentiert die ausfuehrbare Zielarbeitsweise fuer KI-Arbeit am Repository. Sie ist bewusst operativ geschrieben: Eine KI soll daraus direkt ableiten koennen, welche Quelle zu lesen ist, ob direkt geschrieben werden darf, wann ein Draft-PR noetig ist und wann Codespace oder ZIP weiterhin sinnvoll sind.

## 1. Zielbild

Der Standard ist nicht mehr ZIP-first und nicht mehr Codespace-first.

Der Standard ist:

```text
GitHub/staging lesen
-> Risiko und Owner-Dateien bestimmen
-> passenden Arbeitsmodus waehlen
-> nach Freigabe umsetzen
-> Staging oder PR als Pruefflaeche nutzen
```

Damit entfallen wiederholte ZIP-Uploads und routinemaessige Codespace-Starts. `staging` ist die schnelle, reversible Arbeits- und Pruefumgebung. `main` bleibt geschuetzt und wird nicht direkt durch KI beschrieben.

## 2. Quelle der Wahrheit

Fuer Repo-Arbeit gilt:

1. Aktueller GitHub-Stand von `Stratos888/Bocholt-Erleben` auf Branch `staging`.
2. Aktive Codespace-/lokale Git-Ausgaben des Nutzers, wenn er gerade in einer Arbeitskopie arbeitet.
3. ZIP-Snapshot nur als Fallback oder bewusster Vergleichsstand.
4. Memory, alte Chats und alte Artefakte nur als Kontext.

Fuer Daten gilt weiterhin die jeweilige fachliche Quelle. Beispiele:

- Redaktionelle Events: Google Sheet `Events` beziehungsweise frischer Deploy-Export.
- Veranstalter-Events: DB/API nach Freigabe.
- Generierte Dateien wie `data/events.json` oder `data/events.tsv`: Runtime-/Build-Artefakte, keine handzupflegende Quelle.

## 3. Start-Gate fuer jede KI-Aufgabe

Vor Umsetzung muss die KI knapp nennen:

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
- Datei-Loeschungen ohne Spezialfreigabe,
- Deploy-/Workflow-/Service-Worker-/Cache-Aenderungen,
- Backend/API/DB/Stripe/Mail,
- grosse Multi-Datei-Patches,
- generierte Datenartefakte als fachliche Quelle,
- automatische fachliche Datenkorrekturen aus KI-/Audit-Ergebnissen.

Nach einem G2-Commit muss die KI nennen:

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

Codespace Preview ist nur noch fuer komplexere Vorabpruefung oder Ausfuehrungsbedarf gedacht.

## 6. Doku-only-Regel

Doku-only-Aenderungen duerfen nicht in viele kleine Staging-Commits zerfallen, weil jeder Push aktuell den Deploy-Workflow ausloest.

Daher gilt:

- Doku-Prozessumstellungen buendeln.
- Nicht erst `AI_ENTRYPOINT.md`, dann `ENGINEERING.md`, dann `MASTER.md` einzeln committen.
- Wenn mehrere Doku-Dateien betroffen sind: ein konsolidierter Doku-Commit oder ein bewusstes Doku-Paket.
- Keine Runtime-Dateien mit reinen Doku-Workpacks vermischen.

## 7. Deploy-Bewusstsein

Der STRATO-Deploy-Workflow laeuft auf Push nach `main` und `staging`. Daher sind direkte Staging-Commits fuer visuelle oder runtime-relevante Aenderungen sinnvoll, weil der Deploy die Pruefflaeche ist.

Fuer reine Doku ist der Deploy meistens nur Kosten-/Zeit-Overhead. Deshalb Doku buendeln und nicht kleinteilig committen.

## 8. Ruecknahme

Falsche Staging-Commits werden bevorzugt per Revert-Commit zurueckgenommen.

Kein Force-Reset als Standard.

## 9. Praktische Standardformeln

Wenn der Nutzer sagt:

- `Analysiere den aktuellen Stand`: G1.
- `Mach den kleinen Fix direkt`: G2, wenn niedriges Risiko.
- `Groesseres Feature`: G3 oder G4 nach Risiko.
- `Visuell pruefen`: kleiner G2-Staging-Loop, wenn owner-file-klar.
- `Doku harmonisieren`: buendeln, kein Mikro-Commit.
- `Ohne GitHub-Zugriff`: G5.

## 10. Harte Grenzen fuer KI

- Nie direkt auf `main` schreiben.
- Kein Force-Push.
- Keine Branch-Loeschung.
- Keine Datei-Loeschung ohne ausdrueckliche Spezialfreigabe.
- Keine Secrets oder Credentials anfassen.
- Keine externen Deployments still aendern.
- Keine generierten Datenartefakte als fachliche Quelle pflegen.
- Keine ungeprueften automatischen fachlichen Datenkorrekturen.
- Keine konkurrierenden parallelen Workpacks auf demselben Branch.
