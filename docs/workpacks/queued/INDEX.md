# Workpack-Queue

Stand: 2026-07-18

Diese Datei ordnet geplante technische und fachliche Workpacks. Sie aktiviert keinen Workpack. Der einzige operative Status steht in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## Verbindliche technische Reihenfolge

| Rang | Workpack | Risiko | Status | Abhängigkeit |
|---:|---|---|---|---|
| 1 | `CONTROL-CENTER-WORKFLOW-CONSOLIDATION.md` | R2 | als nächstes zulässig | Dokumentationsbaseline |
| 2 | Control Center: genau ein synthetischer E4-Lauf | R3 | noch anzulegen | Rang 1 und erneutes E3 |
| 3 | CityArt: genau ein echter Staging-Fall | R3 | bedingt | grünes E4 und gesonderte Aktivierung |

Die Ränge 2 und 3 werden nicht vorgezogen und nicht parallel gestartet.

## Produktworkpacks – bereit zur späteren Aktivierung

| Workpack | Risiko | Zielzustand | Aktivierungsgrenze |
|---|---|---|---|
| `SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md` | R2 | statische Suchbasis plus Progressive Enhancement | technischer Pflichtpfad abgeschlossen; frische Suchbaseline |
| Startpartner-Wachstumspilot operationalisieren | voraussichtlich R3 | gemeinsamer Kandidaten-, Aktivierungs-, Pilot- und Abschlussprozess | eigener Workpack aus dem validierten Zielzustand erstellen |

Startpartner-Referenz:

`docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md`

Zwischen den beiden Produktworkpacks wird bei Aktivierung anhand aktueller Nutzungs-/Suchdaten und Geschäftspriorität entschieden. Sie dürfen nicht parallel dieselben zentralen Owner oder Deploys verändern.

## Laufende Betriebsbeweise

Diese Punkte sind keine allgemeinen Umbau-Workpacks:

- regulären Weekly-KI-Lauf auf Vorlauf-/Drop-Regeln prüfen;
- Content-, Visual- und Quellenhinweise aus regulären Reports ausnahmebasiert bearbeiten;
- Browser- und Deploy-Smokes grün halten;
- keine gefreezten UI-Bereiche ohne konkreten Fehler öffnen.

## Queue-Regeln

1. Genau ein schreibender aktiver Workpack.
2. Jeder Workpack wird vor Aktivierung gegen aktuellen `staging`-Stand neu geprüft.
3. Zielzustandsdokumente sind keine Implementierungsbehauptung.
4. Neue ungeplante Aufgaben verdrängen die Reihenfolge nur bei belegtem Produktionsfehler, Sicherheit, Recht oder akutem Datenrisiko.
5. Ein Workpack wird nicht durch einen Chat, PR oder Commit allein als abgeschlossen markiert, sondern erst nach erforderlicher Evidence und aktualisiertem `CURRENT_WORKPACK.md`.
