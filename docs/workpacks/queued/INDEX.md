# Workpack-Kandidaten – Routingregel

Diese Datei ist kein operatives Backlog und enthält bewusst keine Issue-Nummern, Aktivstatus oder nächsten technischen Schritt.

## Source of Truth

- Produktprioritäten und dauerhafte Kandidaten stehen in `ROADMAP.md`.
- Konkrete offene Beobachtungen und vorbereitete Aufgaben stehen als GitHub-Issues.
- Genau ein offenes Issue mit `[ACTIVE WORKPACK]` ist der operative Scope-, Status- und Evidence-Owner.
- `docs/workpacks/active/CURRENT_WORKPACK.md` ist nur der statusfreie Router zu diesem Issue.

## Aktivierung

Vor einer Aktivierung:

1. aktuellen Repo-, Runtime- und Datenstand read-only prüfen;
2. Produktwirkung und Priorität neu bestätigen;
3. Ziel, Nicht-Ziele, Owner, erlaubte und gesperrte Pfade, Tests, Evidence, Dokumentationsdelta und Rollback schließen;
4. konkurrierende zentrale Änderungen ausschließen;
5. genau ein Issue mit `[ACTIVE WORKPACK]` markieren;
6. Gate A schließen.

Eine Aktivierung verändert diese Datei nicht. Operativer Fortschritt wird nicht in Repository-Queue-Dateien gespiegelt.

## Historische Scope-Dateien

Frühere Dateien unter `docs/workpacks/queued/` sind keine aktuelle Routingquelle. Abgeschlossene langlebige Erkenntnisse liegen kompakt unter `docs/workpacks/completed/`; vollständige operative Evidence bleibt im abgeschlossenen GitHub-Issue und in Git.

## Regeln

- genau ein aktiver schreibender Workpack;
- genau ein Repository-Schreiber;
- parallele Arbeit nur bei vollständig getrennten Ownern und externen Ressourcen;
- Produktwirkung hat Vorrang vor Meta-Arbeit;
- keine neue Prozess-, Workflow- oder Dokumentationsschicht ohne belegten Engpass;
- keine abgeschlossene Issue als nächsten Workpack in Repository-Dateien führen.
