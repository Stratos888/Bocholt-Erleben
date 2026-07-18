# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Programm:** KI-Arbeitsmodell und Runtime-Verlässlichkeit
- **Workpack:** E4 – isolierter synthetischer Staging-Write und Wiederaufnahmenachweis
- **Status:** Implementierung integriert und Gate C erreicht; externer E4-Lauf noch nicht gestartet
- **Risikoklasse:** R3 – ausschließlich synthetische, vollständig rückbaubare Staging-Mutation
- **Aktuelles Gate:** C – reale Runtime bewiesen; E4-Ausführung bereit
- **Erforderliche Evidence:** E4
- **Implementierungs-PR:** #105, gemergt
- **Implementierungs-Commit:** `f504014da3e1c93a627c16dd3ac712fbd39267b7`
- **Deploy-Status:** `deploy/staging-observed` erfolgreich
- **Runtime-Status:** `control-center/runtime-preflight-e3` erfolgreich
- **E4-Workflow:** `Control Center E4 Synthetic Proof`
- **Workflow-Trigger:** ausschließlich manuell per `workflow_dispatch` auf Branch `staging`

## Belegter Bereitschaftszustand

- E4-Harness und Workflow sind auf `staging` deployt.
- Python-Syntax und deterministischer Selbsttest sind grün.
- Project Guardrails, Contract Diagnostics und PR Gate sind grün.
- Der reale Staging-Build entspricht dem Implementierungs-Commit.
- Der read-only Preflight bestätigt:
  - `Inbox_Staging -> Events_Staging`;
  - Writer `be_cc_writeback_staging_inbox_approve_verified`;
  - Live-Inbox und Live-Events `not_used`;
  - `mutation=false`;
  - keine Blocker.
- `Inbox_Staging` und `Events_Staging` enthalten aktuell keine Zeile mit Präfix `be-e4-synthetic`.
- Der echte CityArt-Fall steht weiterhin unverändert auf `review` und besitzt keinen Eventeintrag in `Events_Staging`.

## Geplanter E4-Nachweis

Der einmalige Lauf verwendet zwei eindeutige synthetische Kandidaten:

1. **Erfolgspfad:** Eventmodus `appended`, terminaler Inbox- und lokaler Fallstatus sowie idempotenter Replay derselben abgeschlossenen `operation_id`.
2. **Wiederaufnahmepfad:** vorab bestätigte Eventzeile bei offener Inbox; fehlgeschlagene Operation bleibt fail-closed; neue `operation_id` übernimmt mit Modus `existing` ohne Duplikat.
3. **Feed-Beweis:** beide Testevents erscheinen nur im Staging-Feed und niemals im Live-Feed.
4. **Cleanup:** synthetische Sheetzeilen und DB-Datensätze werden gelöscht; ein Cleanup-Deploy entfernt die Events wieder aus dem Staging-Feed.
5. **Abschluss:** Live-, Nicht-Test- und CityArt-Zustände müssen unverändert sein.

## Erlaubter Scope

- genau ein manueller Lauf von `.github/workflows/control-center-e4-synthetic.yml` auf `staging`;
- zwei synthetische Zeilen in `Inbox_Staging`;
- zwei synthetische Event-IDs in `Events_Staging`;
- zugehörige synthetische `control_cases` und `control_operations` in der Staging-Datenbank;
- ein Feed-Deploy und ein Cleanup-Deploy;
- anschließend `docs/evidence/**` und Abschlussaktualisierung dieser Datei.

## Gesperrter Scope

- kein echter CityArt-Klick;
- keine Mutation des CityArt-Falls;
- keine Mutation in `Inbox` oder `Events`;
- keine Live-Schreibaktion;
- kein Feature-Branch-Deploy;
- kein allgemeiner WP-3- oder Transaktionsumbau;
- kein zweiter E4-Lauf ohne Auswertung des ersten Laufs.

## Ressourcen-Lock und Stop-the-line

- Der Ressourcen-Lock beginnt mit dem manuellen Workflow-Start und endet erst nach bestätigtem Sheet-, DB- und Feed-Cleanup.
- Während dieses Fensters ist kein anderer schreibender Staging-Workpack zulässig.
- Der `finally`-Pfad versucht Cleanup unabhängig vom Testergebnis.
- E4 gilt nur bei Evidence `result=success`, leerer Cleanup-Fehlerliste und vollständig grünen Unverändertheitschecks.
- Bei unvollständigem Cleanup keine Wiederholung und kein echter Fachfall.

## Technische Ausführungsgrenze

Der verbundene GitHub-Connector kann Workflows lesen, integrieren und auswerten, stellt aber keine Aktion zum Erzeugen eines `workflow_dispatch`-Runs bereit. Ein automatisch schreibender Push-Ersatz wurde nicht zugelassen und wird nicht umgangen. Deshalb ist genau ein manueller UI-Start erforderlich; alle anschließenden Auswertungs-, Cleanup- und Dokumentationsschritte können wieder durch den primären Ausführungs-Chat erfolgen.

## Definition of Done

- [x] E1/E2 grün.
- [x] Implementierung nach `staging` integriert.
- [x] Deploy und E3 für denselben Commit grün.
- [x] Keine synthetischen Restdaten vor dem Lauf.
- [x] CityArt vor dem Lauf unverändert.
- [ ] Workflow genau einmal manuell auf `staging` gestartet.
- [ ] Erfolg, fail-closed Verhalten und Wiederaufnahme belegt.
- [ ] Feed-Isolation belegt.
- [ ] Cleanup vollständig belegt.
- [ ] E4-Evidence dokumentiert.
- [ ] Entscheidung: kein WP-3 oder ausschließlich ein durch E4 belegter Minimalfix.

## Nächster erlaubter Schritt

In GitHub unter **Actions → Control Center E4 Synthetic Proof → Run workflow** den Branch **staging** auswählen und den Workflow genau einmal starten. Danach ausschließlich den entstandenen Run auswerten; kein weiterer Klick und kein echter CityArt-Fall.
