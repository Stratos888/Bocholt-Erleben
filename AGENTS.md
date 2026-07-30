# Codex-Router – Bocholt erleben

`AI_ENTRYPOINT.md` ist der verbindliche Prozess. Diese Datei enthält nur die Codex-Kurzfassung.

## Start

1. `staging`-SHA und offene PRs lesen.
2. Genau ein `[ACTIVE WORKPACK]`-Issue lesen.
3. Den dort deklarierten `branch` lesen.
4. Bestehenden offenen PR zu diesem Branch fortsetzen.
5. `SYSTEM_MAP.md`, betroffene Owner und `ENGINEERING.md` lesen.

Stoppen, wenn:

- kein oder mehr als ein aktiver Workpack existiert;
- ein anderer Feature-PR nach `staging` offen ist;
- der deklarierte Branch nicht eindeutig ist;
- ein anderer Schreiber am Workpack arbeitet.

## Schreiben

- ein Workpack = ein Schreiber = ein Branch = ein PR;
- keine neue Branch- oder PR-Alternative eröffnen;
- Korrekturen bleiben im selben Branch und PR;
- kein direkter Commit nach `staging` oder `main`;
- keine Feature-Branch-Deploys;
- keine Secrets oder nicht freigegebenen externen Writes.

Für größere Codeänderungen ist ein vollständiger Checkout erforderlich. Der GitHub-Contents-API-Pfad ist nur für kleine, vollständig gelesene Text- oder Konfigurationsänderungen geeignet. Fehlt eine Checkout- und Testumgebung, keine große Implementierung Datei für Datei beginnen.

## Testen

Während der PR Draft ist:

```bash
bash scripts/validate-repo.sh quick
```

Vor Reviewbereitschaft:

```bash
bash scripts/validate-repo.sh
```

Bei UI zusätzlich die vereinbarten Browser- beziehungsweise Screenshotnachweise ausführen.

## Lieferung

Kompakt liefern:

1. Zielzustand und Diff;
2. Tests und Evidence-Grenzen;
3. unveränderte Risikobereiche;
4. denselben PR nach `staging`;
5. genau einen nächsten Schritt.

Operativer Status gehört ausschließlich in das aktive Issue. Dauerhafte Dokumentation wird nur bei echtem Wissensdelta einmal aktualisiert.
