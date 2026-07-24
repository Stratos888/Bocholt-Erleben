## Ziel

- **Problem und Zielzustand:**
- **Ausgangs-SHA von `staging`:**
- **Warum ist dies der kleinste nachhaltige Patch?**

## Scope

- **Geänderte Owner-Dateien:**
- **Bewusst nicht geändert:**
- **Externe Ressourcen:** keine / read-only / kontrollierter Einzelwrite

## Prüfung

- **Automatisierte Tests:**
- **Erforderlicher Staging-Smoke nach dem Merge:**
- **Rollback/Revert:**

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

## Dokumentation

- **Geänderte kanonische Dokumente:**
- **Operativer Status aktualisiert:** ja / nicht erforderlich

## Abschluss

- [ ] PR zielt auf `staging` oder ist der reguläre Release-PR `staging -> main`
- [ ] Diff ist auf den im aktiven Workpack-Issue eingefrorenen Scope begrenzt
- [ ] Keine parallele Änderung am selben Owner oder an derselben externen Ressource
- [ ] `PR Gate` ist auf dem aktuellen Head-SHA grün
- [ ] Nach dem Merge genügt genau ein normaler Staging- beziehungsweise Main-Deploy
