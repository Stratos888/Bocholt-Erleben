# Current Workpack

Stand: 2026-07-21

## Aktiver Workpack

**SEO Recovery – Search Intent und statische Renderingbasis.**

Kanonischer Auftrag:

- `docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

Der dort dokumentierte Workpack ist durch diese Datei aktiviert. Die Umsetzung beginnt mit **Gate A**. Vor dessen vollständigem Abschluss erfolgt kein fachlicher Patch.

## Aktuelle Locks

- genau ein schreibender Chat beziehungsweise Agent für den SEO-Workpack;
- parallele Chats dürfen analysieren, aber keine Repository-Änderungen vornehmen;
- Feature-Branch erst nach geprüftem aktuellen `staging`-Stand und vollständigem Gate A;
- kein direkter Commit nach `staging` oder `main`.

## Zuletzt abgeschlossen

**Allgemeine Repository-, Dokumentations-, Sicherheits- und Arbeitsweisenoptimierung.**

Ergebnis:

- kanonischer Einstieg, Workpack-Steuerung und Proofindex konsolidiert;
- allgemeine Meta-Optimierung beendet und bis zu neuer konkreter Evidenz eingefroren;
- geschützte Releasefolge `Feature-Branch -> staging -> main` mit verpflichtendem `PR Gate`;
- Rulesets, CodeQL, Dependabot, Secret Scanning und Push Protection aktiv;
- Deployjob mit `contents: read` den geschützten Environments `staging` und `main` zugeordnet;
- Staging- und Live-Deploy einschließlich Smoke-Tests erfolgreich validiert;
- `STAGING_REVIEW_PASSWORD` ausschließlich im Environment `staging`; Login nach Entfernung des gleichnamigen Repository-Secrets erfolgreich;
- übrige bestehende Repository-Secrets bleiben bewusst unverändert; neue umgebungsspezifische Secrets werden direkt im passenden Environment angelegt.

## Genau nächster Schritt

Gate A des SEO-Workpacks gegen den dann aktuellen Branch-, Such-, Daten-, Rendering-, Schema- und Indexierungsstand vollständig erheben und dokumentieren.
