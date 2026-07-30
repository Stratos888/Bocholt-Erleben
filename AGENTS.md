# Codex-Router – Bocholt erleben

`AI_ENTRYPOINT.md` ist der verbindliche Prozess. Diese Datei enthält nur die Kurzfassung.

## Start

1. aktuellen `staging`-Stand lesen;
2. bestehende Branches und PRs zur Aufgabe prüfen;
3. betroffenen Owner bestimmen;
4. vorhandene passende Arbeit fortsetzen;
5. erst dann schreiben.

Analyse bleibt read-only.

## Normaler Write

```text
ein Branch
-> ein PR nach staging
-> passende Tests
-> Merge
-> Staging-Deploy prüfen
-> fertig zur Prüfung melden
```

Ein Workpack-Issue ist nicht erforderlich.

## Workpack

Nur bei mehreren Systemen oder Ownern, Schema, Berechtigungen, Zahlungen, externen Writes, zentraler Governance oder mehrchatfähiger Fortsetzung:

```text
ein Issue
-> ein deklarierter Branch
-> ein PR mit Workpack: #<Issue>
```

Keine zweite Branch- oder PR-Alternative eröffnen.

## Parallelität

Unabhängige PRs sind erlaubt. Stoppen bei:

- derselben Datei in einem anderen offenen PR;
- demselben Workpack in einem anderen offenen PR;
- fachlich abhängigem zentralem Owner.

## Testen

Draft:

```bash
bash scripts/validate-repo.sh quick
```

Reviewbereit: den vom PR Gate ermittelten Plan `docs`, `quick`, `backend`, `frontend` oder `full` verwenden.

Bei UI zusätzlich relevante Browser- beziehungsweise Screenshotnachweise ausführen.

## Schreiben und Lieferung

- kein direkter Commit nach `staging` oder `main`;
- größere Änderungen nur mit Checkout und lokalen Tests;
- Merge nur mit aktuellem grünen Head-SHA;
- normale Lieferung endet auf Staging;
- Nutzer erst mit konkreter Prüfaufforderung oder echtem Blocker einbeziehen.
