# Bocholt erleben

Bocholt erleben ist eine mobile-first Web-App für kuratierte Veranstaltungen, Aktivitäten und lokale Sichtbarkeit in Bocholt.

- **Live:** `https://bocholt-erleben.de/`
- **Staging:** `https://staging.bocholt-erleben.de/`
- **Entwicklungsbranch:** `staging`
- **Produktionsbranch:** `main`

## Einstieg

Für jede KI- oder Engineering-Aufgabe:

1. `AI_ENTRYPOINT.md`
2. `docs/workpacks/active/CURRENT_WORKPACK.md`
3. `docs/architecture/SYSTEM_MAP.md`
4. Aufgabenroute aus `docs/README.md`
5. relevante fachliche Owner-Dateien
6. `ENGINEERING.md`
7. `docs/external-resource-matrix.md`, falls externe Ressourcen betroffen sind

## Arbeitsmodell

```text
ein primärer Chat
-> ein Workpack
-> ein Feature-Branch
-> ein PR nach staging
-> ein normaler Staging-Deploy
-> Abschluss
```

Der Required Check `PR Gate` führt die lokalen Syntax-, Unit- und Contracttests aus. `Deploy to STRATO` ist der einzige Deploypfad. Zusätzliche Observer-, Governance- oder Einmal-Testworkflows gehören nicht zur dauerhaften Architektur.

## Produktquellen

- `MASTER.md` – Produkt-Nordstern
- `Produktvertrag.md` – gültige Produktmechanik
- `COMMERCIAL_STRATEGY.md` – kommerzielle Ausrichtung
- `ROADMAP.md` – priorisierte Produktentwicklung
- `TEST_STATUS.md` – kompakter aktueller Proofstand

## Datenhoheit

`data/events.tsv`, `data/events.json` und `data/inbox.json` sind generierte Runtime-Artefakte. Die tatsächlichen Datenquellen und Umgebungsgrenzen stehen in `docs/architecture/SYSTEM_MAP.md` und `docs/external-resource-matrix.md`.

## Historie

Aktuelle Steuerdateien werden ersetzt, nicht als Tagebuch erweitert. Frühere Zustände bleiben in Git nachvollziehbar. Historische Workpacks, Evidence- oder Entscheidungsdateien werden nur im Arbeitsbaum behalten, wenn sie für eine aktuelle Entscheidung tatsächlich benötigt werden.
