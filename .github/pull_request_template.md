## Ziel

- **Problem und Zielzustand:**
- **Ausgangs-SHA von `staging`:**
- **Warum ist dies der kleinste nachhaltige Patch?**

## Scope

- **Geänderte Owner-Dateien:**
- **Bewusst nicht geändert:**
- **Externe Ressourcen:** keine / read-only / kontrollierter Einzelwrite
- **Repository-Schreiber:** Chat / Codex

## Prüfung

- **Automatisierte Tests:**
- **Erforderlicher Staging-Smoke nach dem Merge:**
- **Rollback/Revert:**
- **Nutzerartefakte:** keine / ausdrücklich angefordert

## Maschinenlesbare PR-Evidence

Den Block vollständig ausfüllen. Scope und erlaubte Dateien werden ausschließlich vom referenzierten aktiven Workpack-Issue vorgegeben.

<!-- PR_EVIDENCE_START -->
```toml
schema_version = 1
workpack_issue = 0
contract_revision = 0
contract_hash = ""
tests = []
evidence_scope = []
not_proven = []
rollback = ""
```
<!-- PR_EVIDENCE_END -->

## Dokumentations-Reconciliation

Owner-Matrix aus `AI_ENTRYPOINT.md` prüfen und nur dauerhafte Änderungen dokumentieren.

- **Arbeitsmodell / Werkzeugwahl / Nutzerartefakte:** geändert / unverändert
- **Technische Regeln / Workflowrollen / System Map:** geändert / unverändert
- **Produktziel / Roadmap:** geändert / unverändert
- **Proofstand / Evidence-Grenze:** geändert / unverändert
- **Externe Ressourcenmatrix:** geändert / unverändert
- **Operativer Status:** ausschließlich im aktiven Issue aktualisiert
- **Ergebnis:** geänderte kanonische Dokumente auflisten oder ausdrücklich `kein dauerhaftes Wissensdelta`

## Abschluss

- [ ] PR zielt auf `staging` oder ist der reguläre Release-PR `staging -> main`
- [ ] Diff ist auf den im aktiven Workpack-Issue eingefrorenen Scope begrenzt
- [ ] Keine parallele Änderung am selben Owner oder an derselben externen Ressource
- [ ] `PR Gate` ist auf dem aktuellen Head-SHA grün
- [ ] Nach dem Merge genügt genau ein normaler Staging- beziehungsweise Main-Deploy oder es ist dokumentations-only ohne fachlichen Runtime-Smoke
- [ ] Keine ZIP-Datei oder Downloadartefakt als Nutzerlieferung, sofern nicht ausdrücklich angefordert
