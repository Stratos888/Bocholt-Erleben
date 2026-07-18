# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** E4 – isolierter synthetischer Staging-Write und Wiederaufnahmenachweis
- **Status:** pausiert vor externer Ausführung; Workflow-Konsolidierung ist vorgeschaltet
- **Risikoklasse:** R3 – externe, ausschließlich synthetische und rückbaubare Staging-Mutation
- **Aktuelles Gate:** Stop-the-line nach belegter Ausführungsgrenze
- **Erforderliche Evidence:** E4, noch nicht erreicht
- **Implementierungs-PR:** #105, gemergt
- **Implementierungs-Commit:** `f504014da3e1c93a627c16dd3ac712fbd39267b7`
- **Letzter dokumentierter Staging-Abschluss:** `4becc53366b41fde24e796194859de7884c5613a`
- **Deploy-Status:** `deploy/staging-observed` erfolgreich
- **Runtime-Status:** `control-center/runtime-preflight-e3` erfolgreich

## Belegter Zustand

- E1 und E2 für den E4-Harness sind grün.
- Der reale Staging-Build und der read-only Runtime-Preflight sind grün.
- E3 bestätigt `Inbox_Staging -> Events_Staging`, Writer `be_cc_writeback_staging_inbox_approve_verified`, Live-Ressourcen `not_used` und `mutation=false`.
- Der externe synthetische E4-Lauf wurde nicht gestartet.
- `Inbox_Staging` und `Events_Staging` enthalten keine Zeile mit Präfix `be-e4-synthetic`.
- CityArt steht unverändert auf `review`.
- Es wurde kein CityArt-Event in `Events_Staging` erzeugt.
- Live-Ressourcen wurden nicht verändert.

## Stop-the-line-Befund

`Control Center E4 Synthetic Proof` ist ausschließlich per `workflow_dispatch` definiert und nur auf `staging` vorhanden. Der erwartete GitHub-Button `Run workflow` ist deshalb nicht zuverlässig verfügbar.

Diese Grenze war bereits in `docs/github-actions-trigger-policy.md` dokumentiert. Der E4-Pfad wurde somit zu früh als operativ ausführungsbereit eingestuft.

Zusätzlich ist die Control-Center-Actions-Struktur durch überlappende CI-, Contract-, Diagnostics-, Preflight-, Observer- und E4-Workflows zu unübersichtlich geworden. Vor einem externen Schreibbeweis wird sie konsolidiert.

Verbindliche Entscheidung:

`docs/decisions/2026-07-18-control-center-workflow-consolidation-before-e4.md`

Gequeue-ter Folge-Workpack:

`docs/workpacks/queued/CONTROL-CENTER-WORKFLOW-CONSOLIDATION.md`

## Ressourcen-Lock

Bis nach erfolgreicher Workflow-Konsolidierung und neu validiertem E4-Operatorpfad gilt:

- kein E4-Lauf;
- kein CityArt-Klick;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine Live-Schreibaktion;
- kein zusätzlicher Trigger-, Observer- oder One-off-Workflow;
- kein WP-3-Umbau ohne konkrete E4-Evidence.

## Offene PR-Grenzen

- PR #93 bleibt ein unabhängiger Dokumentationsentwurf und darf den aktiven technischen Workpack nicht übersteuern. Vor späterer Integration muss er gegen den dann aktuellen `staging`-Stand synchronisiert werden.
- PR #102 zielt aus einem Feature-Branch direkt auf `main` und entspricht nicht dem kanonischen Releasepfad `staging -> main`. Er darf in dieser Form nicht gemergt werden.

## Nächster erlaubter Schritt

Ein neuer primärer Ausführungs-Chat startet ausschließlich den R2-Workpack `CONTROL-CENTER-WORKFLOW-CONSOLIDATION`.

Er beginnt mit einer vollständigen Inventur aller GitHub-Actions-Workflows, Trigger, Testüberschneidungen, Secrets, Artefakte, Statusnamen und Ruleset-Abhängigkeiten. Erst danach darf ein Konsolidierungspatch erstellt werden.

E4 bleibt bis zum erfolgreichen Abschluss dieses Workpacks pausiert.

## Definition of Done für den aktuellen Stoppstatus

- [x] E1/E2 des E4-Harness belegt.
- [x] Deploy und E3 belegt.
- [x] Kein synthetischer E4-Lauf gestartet.
- [x] Keine synthetischen Restdaten vorhanden.
- [x] CityArt und Live unverändert.
- [x] Fehlende Bedienbarkeit als Architekturgrenze dokumentiert.
- [x] Workflow-Überkomplexität und Zielarchitektur dokumentiert.
- [x] Konsolidierungs-Workpack gequeued.
- [ ] Workflow-Konsolidierung umgesetzt und E3 erneut bestätigt.
- [ ] E4-Operatorpfad real nachgewiesen.
- [ ] Genau ein synthetischer E4-Lauf ausgeführt und vollständig bereinigt.
