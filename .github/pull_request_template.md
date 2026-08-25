## Ziel

- **Problem / gewünschte Wirkung:**
- **Target State:**
- **Ausgangs-SHA von `staging`:**
- **Warum ist dies der kleinste nachhaltige Zielzustand?**

## Scope

- **Geänderte Owner-Dateien:**
- **Bestehendes Pattern, das weiterverwendet wird:**
- **Wichtige Invarianten / bewusst unverändert:**
- **Externe Ressourcen:** keine / read-only / kontrollierter Einzelwrite

## Prüfung

- **Automatisierte Tests / Evidence:**
- **Erforderlicher Staging-Smoke nach dem Merge:**
- **Nicht belegt / aktuelle Evidence-Grenze:** keine / konkret benennen
- **Rollback / Revert:**

## Workpack

Für normale PRs leer lassen. Wenn ein Workpack erforderlich ist, ausschließlich eine Zeile mit der tatsächlichen Issue-Nummer verwenden; der PR Gate lädt den Vertrag live aus dem Issue. Keine Contract-, Hash- oder Statuskopie im PR führen.

```text
Workpack: #123
```

## Dokumentations-Reconciliation

Nur dauerhaftes Wissensdelta dokumentieren. Operativer Status bleibt im jeweiligen Workpack-Issue; `ROADMAP.md`, `TEST_STATUS.md` und Produkt-/Architekturverträge werden nur in ihrer in `AGENTS.md` definierten Rolle geändert.

- **Dauerhaftes Wissensdelta:** keines / geänderte kanonische Owner nennen

## Abschluss

- [ ] PR zielt auf `staging` oder ist der reguläre Release-PR `staging -> main`
- [ ] vorhandene passende Arbeit / Owner-Kollisionen wurden geprüft
- [ ] Workpack-Scope wird eingehalten, falls ein Workpack erforderlich ist
- [ ] `PR Gate` ist auf dem aktuellen Head-SHA grün
- [ ] relevante reale Evidence ist stärker gewichtet als weitere interne Iteration
- [ ] keine unnötige Parallel-, Wrapper-, Override- oder Sonderlogik eingeführt
- [ ] nach dem Merge relevanten Staging-Deploy/Smoke prüfen, sofern der normale Prozess oder die Runtimewirkung ihn erfordert