# Workpack-Queue

Diese Datei enthält nur priorisierte dauerhafte Scopes. Operativer Status, Entscheidungen und Evidence stehen im jeweiligen GitHub-Issue.

## Nächster vorbereiteter Qualitäts-Workpack

1. **SEO Structured Data – Search-Console-Warnungen**
   - dauerhafter Scope: `SEO-STRUCTURED-DATA-search-console-warnings-2026-07-22.md`;
   - operativer Owner: GitHub-Issue **#165**;
   - alle Search-Console-Warnungen und betroffenen URLs read-only auflösen;
   - historischen Crawlstand, bewusste optionale Lücke, Datenlücke und technischen Fehler unterscheiden;
   - nur belegte Fehler korrigieren;
   - keine Organizer-, Performer-, Preis-, Währungs-, `validFrom`-, Availability- oder Ticketwerte erfinden.

Der Workpack ist eingeplant, aber nicht aktiv. Bis zur ausdrücklichen Aktivierung werden weder Code- oder Datenänderungen noch pauschale Search-Console-Validierungen durchgeführt.

Er ist zugleich der erste Test des vereinfachten Arbeitsmodells:

- Chat führt;
- Work nur bei belegten unabhängigen Liefersträngen;
- Codex ist der einzige Repository-Schreiber;
- Normalfall: ein Codex-Task, ein PR nach `staging`, ein Staging-Deploy und ein Release-PR nach `main`;
- operativer Fortschritt ausschließlich in Issue #165;
- dauerhaftes Wissensdelta genau einmal am Ende dokumentieren.

## Danach möglicher Produkt-Workpack

2. Startpartner-Wachstumspilot aus `docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md` operationalisieren.

Die Priorität wird bei Aktivierung anhand Produktwirkung, aktuellem Repositorystand und konkretem Risiko erneut bestätigt.

## Laufender Betrieb

- Weekly-KI-Ergebnisse konkret bewerten;
- Content-, Visual- und Quellenhinweise ausnahmebasiert bearbeiten;
- normalen Deploy-Smoke grün halten;
- SEO-Wirkung nach 14 und 28 Tagen getrennt messen;
- keine weitere Prozess- oder Workflowoptimierung ohne neuen belegten Engpass.

## Regeln

- genau ein aktiver schreibender Workpack;
- vor Aktivierung den aktuellen `staging`-Stand prüfen;
- Produktwirkung hat Vorrang vor Meta-Arbeit;
- parallele Arbeit nur bei vollständig getrennten Ownern und externen Ressourcen;
- Queue-Dateien nicht als laufendes Statusjournal verwenden.