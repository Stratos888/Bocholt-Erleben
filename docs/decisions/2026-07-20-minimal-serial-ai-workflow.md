# Minimaler serieller KI-Arbeitsmodus

Datum: 2026-07-20

## Entscheidung

Dauerhafter Standard:

```text
ein Chat -> ein Workpack -> ein Branch -> ein PR -> ein Deploy
```

Das Repository behält einen PR-Check, einen Deploypfad und fünf fachlich arbeitende Betriebsworkflows.

## Entfernt

- doppelter Control-Center-CI-Workflow;
- Project-Guardrails-Workflow;
- Staging-Verification-Observer;
- einmaliger synthetischer E4-Workflow und seine temporären Hilfsdateien;
- Content-Ops-Folgeobserver;
- Dokumentationsregister und Vollinventur als Standardgate.

Die produktiven Content-, Growth-, Inbox- und KI-Workflows bleiben erhalten. Die fachlich relevanten Tests bleiben ebenfalls bestehen und laufen zentral über `scripts/validate-repo.sh` im `PR Gate`.

## Begründung

Die zusätzlichen Prüf- und Beobachtungsschichten erzeugten mehrere Merge- und Deployrunden. Der synthetische Lauf bestätigte Writeback, Replay, Wiederaufnahme und Cleanup; der zusätzliche vollständige Feed-Deploy war eine getrennte Build-Kompatibilitätsfrage. Dafür wird kein weiterer externer Testlauf benötigt.

## Dauerhafte Regel

Neue Workflows benötigen einen dauerhaft notwendigen fachlichen Trigger. Observer, Aggregatoren und einmalige Testharnesses werden nach ihrem Einsatz entfernt. Produktive Fachworkflows werden nicht im Namen der Prozessvereinfachung gelöscht. Historie bleibt in Git; aktuelle Dokumente werden ersetzt statt durch weitere Verwaltungsdateien ergänzt.
