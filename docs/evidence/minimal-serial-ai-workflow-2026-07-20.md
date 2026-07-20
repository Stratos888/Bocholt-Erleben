# Minimaler serieller KI-Arbeitsmodus – Änderungsnachweis

Datum: 2026-07-20

- Entwicklungsmodell: ein Chat, ein Workpack, ein Branch, ein PR, ein Deploy.
- PR-Prüfung: genau ein Required Check `PR Gate`.
- Tests: zentral über `scripts/validate-repo.sh`.
- Deploy: ausschließlich `Deploy to STRATO`.
- Produktive Content-, Growth-, Inbox- und KI-Workflows bleiben erhalten.
- Entfernt: doppelte CI, Governance-Workflow, Deployobserver, E4-Einmalworkflow, Content-Ops-Folgeobserver und temporäre E3-/E4-Dateien.
- Historie bleibt über Git erhalten.
