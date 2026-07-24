# Current Workpack Router

Diese Datei ist kein Statusjournal und enthält bewusst keine wechselnde Issue-Nummer.

## Verbindliche Auswahlregel

Das aktive Workpack ist genau das eine **offene GitHub-Issue**, dessen Titel den Marker

```text
[ACTIVE WORKPACK]
```

enthält.

- **Genau ein Treffer:** Dieses Issue vollständig lesen. Es ist der einzige Scope-, Status- und Evidence-Owner.
- **Kein Treffer:** Es gibt kein aktives Workpack. Schreibende Repository-Arbeit stoppen, bis Chat einen Workpack aktiviert und Gate A schließt.
- **Mehr als ein Treffer:** Fail-closed stoppen und den Konflikt im GitHub-Issue-Bestand bereinigen.

Beim Abschluss wird der Marker im selben finalen Issue-Update entfernt. Queue-, vorbereitete und abgeschlossene Issues tragen den Marker nicht.

## Arbeitsgrenze

Repository-Dateien enthalten nur dauerhafte Regeln und Architektur. Operativer Fortschritt, Entscheidungen, Evidence und der nächste Schritt stehen ausschließlich im aktiven Issue.
