## Workpack-Vertrag

- **Workpack:**
- **Ausgangs-SHA von `staging`:**
- **Ziel / erwarteter Endzustand:**
- **Belegte Ursache oder offene Hypothese:**

## Scope und Locks

- **Erlaubte Dateien/Pfade:**
- **Gesperrte Dateien/Pfade:**
- **Code-/Owner-Lock:**
- **Abhängige oder konkurrierende PRs:**

## Externe Ressourcen

- **Ressourcen:**
- **Zugriff je Ressource:** `none` / `read-only` / `controlled-write`
- **Stabile Test-/Objektidentität:**
- **Ressourcen-Lock:**

> `controlled-write` ist keine Schreibfreigabe für den Workpack-Chat. Eine kontrollierte Staging-Schreibprobe darf nur der Integrations-Chat nach Vorherzustand, Zielnachweis und Rollback koordinieren.

## Validierung

- **Ausgeführte Syntax-/Vertragstests:**
- **Realitätsnaher Replay-/Fixture-Nachweis:**
- **Noch offene Beweise:**
- **Staging-Abnahme erforderlich:** ja / nein
- **Erwartete Staging-Prüfung:**

## Sicherheit und Rücknahme

- **Rollback/Revert:**
- [ ] Kein direkter Commit auf `main`
- [ ] Kein Feature-Branch-Deploy
- [ ] Keine Live-Testschreibaktion
- [ ] Keine externe Mutation durch den Workpack-Chat
- [ ] Aktueller `staging`-Stand vor Integrationsfreigabe einbezogen
- [ ] Diff enthält ausschließlich den deklarierten Scope

## Integrationsstatus

- [ ] Draft / Work in progress
- [ ] Ready for integration
- [ ] Vom Integrations-Chat auf Überschneidungen geprüft
- [ ] Nach Merge auf Staging deployed und abgenommen
- [ ] Für `staging -> main` freigegeben
