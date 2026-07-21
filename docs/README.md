# Dokumentationslandkarte

Die Dokumentation soll der KI schnell den aktuellen Owner und den nächsten Schritt zeigen. Git bewahrt die Historie; der Arbeitsbaum enthält nur aktuell nützliche Verträge, Status- und Fachdokumente.

## Verbindliche Lesereihenfolge

1. `AI_ENTRYPOINT.md` – Arbeitsmodell
2. `docs/workpacks/active/CURRENT_WORKPACK.md` – aktueller Status
3. `docs/architecture/SYSTEM_MAP.md` – Systeme und Datenflüsse
4. fachlicher Domain-Router und betroffene Owner-Dateien
5. `ENGINEERING.md` – technische Regeln
6. `docs/external-resource-matrix.md`, falls externe Ressourcen betroffen sind
7. Produkt-, Roadmap-, Forensik- oder Evidence-Dokumente nur bei konkretem Bedarf

## Kanonische Dokumente

| Datei | Rolle |
|---|---|
| `AI_ENTRYPOINT.md` | Arbeitsweise der KI |
| `CURRENT_WORKPACK.md` | genau ein aktiver Workpack oder „keiner“ |
| `SYSTEM_MAP.md` | stabile Architektur und Datenhoheit |
| `ENGINEERING.md` | dauerhafte technische Regeln |
| `external-resource-matrix.md` | externe Ressourcen und Schreibgrenzen |
| `MASTER.md` | Produkt-Nordstern |
| `ROADMAP.md` | priorisierte Produktziele |
| `TEST_STATUS.md` | kompakter aktueller Proofstand |

## Aufgabenroute

| Aufgabe | Zusätzlich lesen |
|---|---|
| Control Center / Inbox | `docs/domains/control-center.md` und konkrete API-/UI-Owner |
| Eventsuche / Content | `docs/domains/event-search-system.md` und Inhaltsstandards |
| Visuals | `docs/domains/visual-system.md` und Visual-Pool |
| Deploy / GitHub Actions | `docs/github-actions-trigger-policy.md` und betroffene Workflowdatei |
| Produkt / Funnel | `MASTER.md`, `Produktvertrag.md`, `COMMERCIAL_STRATEGY.md`, `ROADMAP.md` |

## Workpacks

```text
queued -> active -> completed oder gelöscht
```

- Standardmäßig gibt es genau einen aktiven schreibenden Workpack.
- `CURRENT_WORKPACK.md` wird bei einem Zustandswechsel ersetzt, nicht fortgeschrieben.
- Ein abgeschlossener Workpack bleibt nur dann als Datei erhalten, wenn seine Entscheidung oder Evidence künftig tatsächlich gebraucht wird.
- Alte Chat-Handoffs, temporäre Prüfpläne und einmalige Workflowinventuren werden nicht als dauerhafter Lesepfad gepflegt.

## Dokumentationsregeln

- Eine Aussage ist entweder aktueller Vertrag, aktueller Status, Ziel, Evidence oder Historie.
- Keine neuen Meta-Dokumente zur Verwaltung anderer Dokumente.
- Keine Vollinventur aller Markdown-Dateien als Standardgate.
- Keine geplante Funktion als umgesetzt beschreiben.
- Veraltete aktuelle Aussagen werden entfernt oder ersetzt.
- Neue Dokumente benötigen einen klaren fachlichen Zweck und einen konkreten Leser.
