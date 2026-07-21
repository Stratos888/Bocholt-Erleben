# Codex-Router – Bocholt erleben

Diese Datei ist nur der kurze Einstieg für Codex. Dauerhafte Regeln werden nicht dupliziert, sondern aus den kanonischen Dateien gelesen.

## Pflichtstart

1. aktuellen `staging`-SHA und offene Pull Requests prüfen;
2. `AI_ENTRYPOINT.md` vollständig lesen;
3. `docs/workpacks/active/CURRENT_WORKPACK.md` vollständig lesen;
4. `docs/architecture/SYSTEM_MAP.md` und danach nur die betroffenen Owner-Dateien lesen;
5. `ENGINEERING.md` lesen;
6. bei externen Ressourcen zusätzlich `docs/external-resource-matrix.md` lesen.

Der aktuelle Repository-Stand ist die Source of Truth. Nicht auf Chatverlauf, Memory, ZIPs oder vermutete frühere Zustände verlassen.

## Arbeitsgrenzen

- ausschließlich innerhalb des aktiven Workpacks arbeiten;
- genau ein Feature-Branch und genau ein schreibender Agent;
- keine parallelen Änderungen an denselben Ownern;
- keine Feature-Branch-Deploys;
- keine direkten Commits nach `staging` oder `main`;
- keine Secrets, Zugangsdaten oder Live-Schreibaktionen;
- bei unerwartetem externem Verhalten sofort stoppen;
- keine neuen Wrapper, Workflows oder Prozessdateien ohne belegten dauerhaften Bedarf.

Wenn der Auftrag keine Repository-Analyse oder -Umsetzung erfordert, nichts ändern und die Aufgabe an Chat beziehungsweise Work zurückrouten.

## Prüfung

- die für den Scope relevanten Syntax-, Unit-, Contract- und Browsertests ausführen;
- anschließend `bash scripts/validate-repo.sh` ausführen;
- rote Tests vor einem PR beheben;
- keine reale Try-and-Error-Schleife nach dem Merge einplanen.

## Lieferung

Kompakt liefern:

1. Befund und Zielzustand;
2. geänderte Dateien;
3. ausgeführte Tests und Ergebnisse;
4. Risiken und bewusst offene Grenzen;
5. Pull Request nach `staging`, sofern Änderungen beauftragt waren;
6. genau einen nächsten Schritt.
