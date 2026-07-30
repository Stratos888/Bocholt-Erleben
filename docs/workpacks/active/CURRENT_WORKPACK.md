# Current Workpack Router

Diese Datei enthält keinen operativen Status. GitHub ist der einzige Workpack-Owner.

## Auswahl

Suche offene Issues mit:

```text
[ACTIVE WORKPACK]
```

- genau ein Treffer: Issue und Vertrag vollständig lesen;
- kein Treffer: Repository-Writes stoppen;
- mehrere Treffer: Konflikt bereinigen, keine Writes.

## Serialisierung

Der aktive Vertrag nennt genau einen `branch`.

Vor jeder schreibenden Arbeit:

1. offene PRs nach `staging` lesen;
2. existiert ein PR dieses Branches, genau dort fortsetzen;
3. existiert ein anderer Feature-PR nach `staging`, fail-closed stoppen;
4. existiert noch kein PR, darf nur der deklarierte Branch verwendet werden.

Der PR enthält genau eine Referenz:

```text
Workpack: #123
```

Keine Vertragsrevision, kein Hash, kein Rollback und kein Evidence-Block werden in den PR kopiert. Der Required Check liest den Vertrag direkt aus dem aktiven Issue.

Beim Abschluss wird der Active-Marker im finalen Issue-Update entfernt. Pausierte, vorbereitete und abgeschlossene Issues tragen ihn nicht.
