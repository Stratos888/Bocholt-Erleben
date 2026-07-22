# Codex-Router – Bocholt erleben

Diese Datei ist der kurze Einstieg für Codex. Der verbindliche Arbeitsprozess steht in `AI_ENTRYPOINT.md`.

## Pflichtstart

1. aktuellen `staging`-SHA und offene Pull Requests prüfen;
2. `AI_ENTRYPOINT.md` vollständig lesen;
3. `docs/workpacks/active/CURRENT_WORKPACK.md` lesen;
4. das dort genannte GitHub-Issue und dessen Abnahmevertrag lesen;
5. `docs/architecture/SYSTEM_MAP.md` und nur die betroffenen Owner-Dateien lesen;
6. `ENGINEERING.md` lesen;
7. bei externen Ressourcen zusätzlich `docs/external-resource-matrix.md` lesen.

Aktueller Ref, owning Dateien, Abnahmevertrag und Workpack-Issue sind die Source of Truth. Nicht aus Chatverlauf, Memory, ZIPs oder vermuteten früheren Zuständen arbeiten.

## Schreibgrenze

Codex ist der einzige Repository-Schreiber, arbeitet aber ausschließlich innerhalb des geschlossenen Abnahmevertrags.

Vor einem Write müssen eindeutig sein:

- Ziel und Nicht-Ziele;
- erlaubte sichtbare Änderungen;
- unveränderte Bereiche;
- betroffene Owner;
- Tests und Evidence;
- Definition of Done;
- Rollback.

Fehlt eine dieser Angaben oder entsteht eine neue Grundsatzfrage, stoppen und an Chat zurückgeben.

## Arbeitsweise

- genau ein Codex-Task, ein Feature-Branch und ein PR nach `staging`;
- Korrekturen vor dem Merge bleiben im selben Branch und PR;
- keinen konkurrierenden Produkt- oder Workpack-Scope eröffnen;
- keine parallelen Änderungen an denselben Ownern;
- keine Feature-Branch-Deploys;
- keine direkten Commits nach `staging` oder `main`;
- keine Secrets, Zugangsdaten oder Live-Schreibaktionen;
- keine neuen Wrapper, Workflows, Prozess- oder Statusdateien ohne belegten dauerhaften Bedarf.

Wenn der Auftrag keine Repository-Analyse oder -Umsetzung erfordert, nichts ändern und an Chat zurückgeben.

## Prüfung vor dem PR

- relevante Syntax-, Unit- und Contracttests ausführen;
- anschließend `bash scripts/validate-repo.sh` ausführen;
- roten Zustand vor dem PR beheben;
- bei Build oder Rendering den tatsächlichen erzeugten Output prüfen, nicht nur Vorlagen;
- bei UI-Änderungen Browser- oder Screenshotnachweis für die vereinbarten Viewports liefern;
- bei Progressive Enhancement Zustand mit und ohne JavaScript prüfen;
- keinen PR öffnen, solange eine bekannte Grundsatzfrage oder visuelle Zielunsicherheit besteht.

## Lieferung

Kompakt liefern:

1. Befund und umgesetzter Zielzustand;
2. geänderte Dateien und Owner;
3. Tests, Build-/Browserartefakte und Ergebnisse;
4. Risiken und bewusst unveränderte Bereiche;
5. PR nach `staging`;
6. genau einen nächsten Schritt.

Operativen Status und Zwischen-Evidence im zuständigen GitHub-Issue aktualisieren. Repository-Dokumentation nur ändern, wenn der Auftrag ein dauerhaftes Wissensdelta enthält.