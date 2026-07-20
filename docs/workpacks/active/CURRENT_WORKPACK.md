# Current Workpack

Stand: 2026-07-20

Diese Datei ist der einzige operative technische Projektstatus. Aktuelle SHAs, PRs und Action-Läufe werden bei jeder Aufgabe direkt aus GitHub gelesen.

## Aktiver Implementierungs-Workpack

**Keiner.**

Die Repository- und Workflowvereinfachung ist der aktuelle abgeschlossene Zielzustand.

## Verbindlicher Arbeitsmodus

```text
ein primärer Entwicklungs-Chat
-> ein Workpack
-> ein Feature-Branch
-> ein PR nach staging
-> ein normaler Staging-Deploy
-> Abschluss
```

- keine parallelen Änderungen an denselben Ownern oder externen Ressourcen;
- keine zusätzlichen Observer-, Verification- oder Einmal-Workflows;
- kein allgemeiner Governance- oder Dokumentations-Workpack ohne konkreten aktuellen Befund;
- Tests laufen über den einzigen Required Check `PR Gate`;
- Runtimeänderungen erhalten nach dem Merge genau einen normalen Staging-Deploy.

## Dauerhafte Workflowtopologie

1. `PR Gate`
2. `Deploy to STRATO`
3. `Content Quality Audit`
4. `Inbox Cleanup (Archive)`
5. `Weekly KI Websearch → Manual Inbox`
6. `Manual KI Event Intake`

## Abgeschlossener synthetischer Writeback-Nachweis

Der einmalige synthetische Lauf auf `staging` belegte:

- Success-Write nach `Events_Staging`;
- idempotenten Replay;
- kontrollierten fehlgeschlagenen Operationszustand;
- Resume ohne Eventduplikat;
- vollständigen Sheet- und Datenbank-Cleanup;
- unveränderte Live-Ressourcen und unveränderte Nicht-Testdaten.

Der zusätzlich erzwungene Feed-Deploy scheiterte beim Event-Build, während der anschließende Cleanup-Deploy grün war. Daraus folgt kein weiterer E4-Lauf. Die temporäre E3-/E4-Infrastruktur wird entfernt. Die verbleibende fachliche Lücke lautet separat:

> Ein vom Control Center akzeptierter Eventdatensatz muss vom normalen Event-Builder verarbeitet werden können.

Diese Lücke wird erst als kleiner lokaler Build-Contract bearbeitet, wenn sie als nächster Produkt-/Risikoworkpack priorisiert wird.

## Aktive Locks

Keine.

## Nächster zulässiger Schritt

Einen neuen Workpack ausschließlich nach Produktwirkung oder konkretem Risiko priorisieren. Keine weitere allgemeine Prozess-, Workflow- oder Dokumentationsoptimierung ohne neuen belegten Bedarf.
