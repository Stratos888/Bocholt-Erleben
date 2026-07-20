# Minimaler serieller KI-Arbeitsmodus

Status: Umsetzung und PR-Abnahme

## Ziel

```text
ein Chat -> ein Workpack -> ein Branch -> ein PR -> ein Deploy
```

## Scope

- einen Required Check `PR Gate`;
- einen Deploypfad `Deploy to STRATO`;
- produktive Content-, Growth-, Inbox- und KI-Workflows behalten;
- doppelte CI-, Governance-, Observer- und Einmal-Testpfade entfernen;
- temporäre E3-/E4-Dateien und ihre Meta-Dokumentation entfernen;
- kanonische Dokumente auf den seriellen Minimalmodus ausrichten.

## Abnahme

- Branch ist nicht hinter `staging`;
- `PR Gate` führt `scripts/validate-repo.sh` erfolgreich aus;
- keine produktive Workflowrolle wurde entfernt;
- nach Merge genau ein normaler Staging-Deploy;
- danach Workpackdatei entfernen und `CURRENT_WORKPACK.md` auf „keiner“ setzen.
