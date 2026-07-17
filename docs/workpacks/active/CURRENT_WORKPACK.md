# Current Workpack

Stand: 2026-07-17

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Aktive Implementierung:** keine
- **Letzter abgeschlossener Workpack:** WP-1 – Arbeitsmodell vereinfachen und Projektsicht konsolidieren
- **Abschluss-Commit auf `staging`:** `7a26fdd1f13cf5c469d6e56d811e2419dfd357a4`
- **Erreichte Evidence:** E2
- **Nächster geplanter Workpack:** WP-2 – Runtime-Truth und read-only Preflight
- **Status von WP-2:** nicht gestartet; benötigt ein neues ausdrückliches Arbeitsmandat

## Verbindlicher Arbeitszustand

- Standard: ein primärer Ausführungs-Chat, ein aktiver Schreibbranch/Draft-PR.
- Risikoklassen: R1–R3.
- Prozess: Gates A–D.
- KI arbeitet nach einem einmaligen Arbeitsmandat innerhalb des dokumentierten Scopes selbstständig.
- Kritische Nutzeraktionen benötigen die passende Runtime- und Write-Evidence.
- Optionaler Zweitchat ist read-only; parallele Schreibarbeit bleibt Ausnahme.
- Fehlerbudget und Stop-the-line aus `AI_ENTRYPOINT.md` gelten verbindlich.

## Aktueller Freeze

Bis WP-2 gestartet und E3 hergestellt ist:

- keine weitere CityArt-Übernahme;
- keine manuelle Statuskorrektur;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine weiteren Hypothesenpatches im Writeback-/Environment-Scope.

Sicherer Datenzustand:

- `Inbox_Staging`: CityArt bleibt `review`;
- `Events_Staging`: kein CityArt-Eintrag;
- `Events`: kein CityArt-Eintrag aus dem Staging-Versuch;
- Live wurde nach dem Rollback nicht weiter verändert.

## PR- und Lockstatus

- PR #87: gemergt; WP-1 abgeschlossen.
- PR #86: geschlossen, nicht gemergt, nur historische technische Evidence.
- Kein aktiver Code-/Owner-Lock aus WP-1.
- Ressourcen-Lock auf Inbox-/Events-Writeback bleibt bis WP-4 bestehen.

## Nächster erlaubter Schritt

Nur nach neuem Nutzerauftrag:

### WP-2 – Runtime-Truth und read-only Preflight

Ziel: E3 herstellen, ohne Writerlogik oder externe Daten zu verändern.

Scope:

- Build-SHA und Endpointversion sichtbar machen;
- Host sowie konfigurierte und aufgelöste Umgebung ausweisen;
- Quelltab, Zieltab und ausgewählten Writer read-only anzeigen;
- Dry-Run/Operationsplan für eine Inbox-Entscheidung bereitstellen;
- automatischen Staging-Smoke gegen diese Diagnose ergänzen.

Nicht enthalten:

- keine Writeränderung;
- keine Sheetmutation;
- kein CityArt-Klick;
- keine Übernahme von Code aus PR #86 ohne neue Analyse und aktuellen Scope.

## Danach geplante Reihenfolge

1. **Entscheidungsgate nach WP-2** – reale Root Cause und kleinsten notwendigen Umbau bestimmen.
2. **WP-3 – Minimaler robuster Writeback** – Umfang erst nach E3 festlegen.
3. **WP-4 – Isolierter E4-Test und CityArt-Abschluss** – synthetischer Test vor genau einem echten CityArt-Versuch.