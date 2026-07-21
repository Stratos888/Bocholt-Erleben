# Aktueller Proofindex – Bocholt erleben

Stand: 2026-07-21

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
| Branch- und Deployrouting | E2 | nur `staging` und `main` dürfen deployen; Releasepfad `Feature-Branch -> staging -> main` |
| PR-Integration | E2 | ein Required Check `PR Gate` führt die Repositorytests aus |
| Geschützte Deploy-Environments | E2 | Deployjob nutzt abhängig vom Branch ausschließlich `staging` oder `main`; Branchregeln sind aktiv; `GITHUB_TOKEN` ist auf `contents: read` begrenzt |
| Staging-Deploy | E3 | normaler Deploy über Environment `staging` einschließlich Build-, HTTP- und Browser-Smoke erfolgreich |
| Staging-Secret-Isolation | E3 | `STAGING_REVIEW_PASSWORD` liegt ausschließlich im Environment `staging`; nach Löschen des gleichnamigen Repository-Secrets blieben Deploy und Login erfolgreich |
| Live-Deploy | E6 | validierter Stand über `staging -> main` veröffentlicht; Live-Deploy, HTTP-/Browser-Smoke und manueller Funktionstest erfolgreich |
| Branch-Inhaltsgleichheit | E1 | `main` und `staging` besitzen nach dem Release denselben Dateiinhalt; Unterschiede bestehen nur in der Merge-Historie |
| Control-Center-Writeback | E4 | Success, Replay, kontrollierter Fehler, Resume und Cleanup synthetisch belegt |
| Live-Unverändertheit beim E4 | E4 | Live-Sheet und Live-Feed blieben unverändert |
| Event-Builder-Kompatibilität | E2 | eine vom echten Control-Center-Writer erzeugte Zeile mit `11:00–18:00 Uhr` wird vom normalen Event-Builder vollständig verarbeitet |
| Event-Identität und Dubletten-Preflight | E2 | gemeinsamer Python-/PHP-Vertrag; CityArt-Semantik, Shared-URL, getrennte Programmpunkte, ID-Konflikt, Same-ID-Resume, Staging-Overlay und Approval-Wiring sind im `PR Gate` grün |
| echter Control-Center-Fachfall | nicht erforderlich | kein echter Fall wurde für den technischen Nachweis verändert |
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

Der damalige gekoppelte Feed-Build scheiterte an der unterschiedlichen Zeitformat-Akzeptanz von Control-Center-Writer und Event-Builder. Der lokale E2-Contract schließt diese Lücke dauerhaft; der E4-Writeback-Beweis wird nicht wiederholt.

## Pflege

`TEST_STATUS.md` wird nur geändert, wenn sich ein relevanter Proofstand oder eine konkrete Evidence-Lücke ändert. Keine kompletten Logs, Patchchroniken oder Prozessinventuren hier ablegen.
