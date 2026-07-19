# Bocholt erleben

Bocholt erleben ist eine mobile-first Web-App für kuratierte Veranstaltungen, Aktivitäten und lokale Sichtbarkeit in Bocholt.

- **Live:** `https://bocholt-erleben.de/`
- **Staging:** `https://staging.bocholt-erleben.de/`
- **Entwicklungsbranch:** `staging`
- **Produktionsbranch:** `main`

## Einstieg

Für jede KI- oder Engineering-Aufgabe gilt diese Reihenfolge:

1. `AI_ENTRYPOINT.md`
2. `docs/workpacks/active/CURRENT_WORKPACK.md`
3. `docs/architecture/SYSTEM_MAP.md`
4. Aufgabenroute aus `docs/README.md`
5. Rollenprüfung über `docs/DOCUMENT_REGISTRY.md`
6. relevante fachliche Owner-Dateien
7. `ENGINEERING.md` und `docs/external-resource-matrix.md`

Die vollständige Dokumentationslandkarte, die Regeln zur Pflege und der automatische Vollinventurvertrag stehen unter `docs/`.

## Produktquellen

- `MASTER.md` – stabiler Produkt-Nordstern
- `Produktvertrag.md` – bereits gültige und umgesetzte Produktmechanik
- `COMMERCIAL_STRATEGY.md` – kommerzielle Ausrichtung
- `ROADMAP.md` – priorisierte Produktentwicklung
- `TEST_STATUS.md` – kompakter aktueller Proofindex

## Arbeitsmodell

Der Normalfall ist:

```text
Feature-Branch
-> Draft-PR nach staging
-> CI und passende Evidence
-> Staging-Deploy und Abnahme
-> vollständiger Release staging -> main
```

Direkte Live-Eventpflege und kleine direkte Main-Hotfixes sind keine alternativen Entwicklungswege. Sie sind nur als ausdrücklich beauftragte, eng begrenzte Ausnahme nach `AI_ENTRYPOINT.md` zulässig.

## Datenhoheit

`data/events.tsv` und `data/events.json` sind generierte Runtime-Artefakte. Die tatsächlichen Datenquellen und Umgebungsgrenzen stehen in `docs/architecture/SYSTEM_MAP.md` und `docs/external-resource-matrix.md`.

## Dokumentationsqualität

Jede getrackte Markdown-Datei muss eine bekannte Rolle besitzen. `Project Guardrails` führt eine read-only Vollinventur, Linkprüfung und den Governance-Audit aus. Neue Root-Dokumente, Statusmischung und ungeprüfte Archivpfade sind nicht zulässig.

## Historie

Aktuelle Steuerdateien werden inhaltlich ersetzt und nicht als fortlaufendes Tagebuch erweitert. Frühere Zustände bleiben über Git, `docs/decisions/`, `docs/forensics/`, `docs/evidence/`, completed Workpacks und Archive nachvollziehbar.
