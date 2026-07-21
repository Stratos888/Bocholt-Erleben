# Current Workpack

Stand: 2026-07-21

## Aktiver Workpack

**GitHub-Actions-Vollinventur und Workflow-Cleanup.**

Ziel ist eine exakt klassifizierte, minimal notwendige Workflowmenge. Doppelte Prüfpfade, abgelaufene Einmal-Workflows, reine Beobachter und direkte Repository-Writer ohne dauerhaften Owner werden entfernt oder in den bestehenden fachlichen Owner integriert.

## Aktuelle Locks

- `.github/workflows/**`
- `scripts/validate-repo.sh`
- `scripts/audit_github_workflows.py`
- `docs/github-actions-trigger-policy.md`
- `docs/workpacks/active/GITHUB-ACTIONS-workflow-inventory-cleanup-2026-07-21.md`

Schreibowner ist ausschließlich der Branch `agent/workflow-inventory-cleanup`.

## Ausführungsregeln

- Keine produktiven Workflow-Ausführungen oder externen Schreibtests im Workpack.
- Fachlich notwendige Automatisierung bleibt erhalten.
- Workflowdateien werden nur behalten, wenn Owner, Trigger und dauerhafter Nutzen belegt sind.
- Der bestehende `PR Gate` erzwingt anschließend die exakte Workflow-Allowlist.

## Danach empfohlener Workpack

**Search-Intent und statische Renderingbasis – SEO-Recovery nach aktueller Suchbaseline.**
