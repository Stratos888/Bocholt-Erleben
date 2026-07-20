# Aktueller Proofindex – Bocholt erleben

Stand: 2026-07-20

Diese Datei enthält nur den aktuell relevanten Proofstand. Ausführliche Historie liegt in Git und den jeweiligen Action-Artefakten.

## Evidence-Legende

| Stufe | Bedeutung |
|---|---|
| E1 | aktueller Code oder Diff |
| E2 | automatisierter lokaler/CI-Test |
| E3 | deployter read-only Nachweis |
| E4 | begrenzter realer Write mit Rücklesen und Cleanup |
| E5 | echter fachlicher Staging-Fall |
| E6 | read-only Live-Smoke |

## Aktueller Proofstand

| Bereich | Evidence | Status |
|---|---:|---|
| Branch- und Deployrouting | E2 | nur `staging` und `main` dürfen deployen; Releasepfad `staging -> main` |
| PR-Integration | E2 | ein Required Check `PR Gate` führt die Repositorytests aus |
| Staging-Deploy | E3 | normaler Deploy enthält Build- und HTTP-Smoke |
| Control-Center-Writeback | E4 | Success, Replay, kontrollierter Fehler, Resume und Cleanup synthetisch belegt |
| Live-Unverändertheit beim E4 | E4 | Live-Sheet und Live-Feed blieben unverändert |
| Event-Builder-Kompatibilität | offene Contract-Lücke | ein synthetisch akzeptierter Datensatz scheiterte im nachgelagerten Feed-Build |
| echter Control-Center-Fachfall | nicht erforderlich | kein echter Fall wurde für den technischen Nachweis verwendet |
| Weekly-KI-/Inbox-Betrieb | laufender Betrieb | anhand der jeweiligen fachlichen Action-Läufe bewerten |

## E4-Ergebnis

Der synthetische Lauf für Build `6702fd5c12a0` bestätigte:

- `Inbox_Staging -> Events_Staging`;
- genau ein Success-Event trotz Replay;
- fail-closed Retry der fehlgeschlagenen Operation;
- erfolgreiche Wiederaufnahme ohne Duplikat;
- zwei gelöschte Inboxzeilen, zwei gelöschte Eventzeilen, zwei gelöschte Cases und drei gelöschte Operationszustände;
- keine synthetischen Reste;
- unveränderte Live- und Nicht-Testdaten.

Der Lauf blieb insgesamt rot, weil der zusätzlich gekoppelte vollständige Feed-Deploy beim Event-Build scheiterte. Der Cleanup-Deploy war anschließend grün. Der Writeback-Beweis wird deshalb nicht wiederholt; die Build-Kompatibilität ist ein eigener lokaler Contract-Bedarf.

## Pflege

`TEST_STATUS.md` wird nur geändert, wenn sich ein relevanter Proofstand oder eine konkrete Evidence-Lücke ändert. Keine kompletten Logs, Patchchroniken oder Prozessinventuren hier ablegen.
