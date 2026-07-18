# Steuerzentrale – Simulations- und Validierungsbericht

Stand: 2026-07-10  
Status: Feedback-Korrekturpatch umgesetzt; Freeze-Nutzung vorbereitet

## Integrierte Gesamtkette

```text
Content-Audit
→ Suchfeedback
→ KI-Eventsuche
→ KI-Intake
→ führende Sheet-Inbox
→ Prüfen
→ führende Inhaltsquelle
→ öffentliche Wirkung
→ Entwicklungs- und Prozesssignale
```

## Bereits simulierte Kernverträge

- Event- und Aktivitätsvalidierung,
- Anbieter-Event-Verifikation,
- Veröffentlichungszustände,
- Arbeitsstatusübergänge,
- technische Prozessfehler gegenüber fachlicher Folgearbeit,
- Suchlauf→Intake-Abhängigkeit.

## Feedback-Korrekturpatch

### Prüfen

Jeder Filter zeigt seine eigene aktuelle Anzahl. Leere Gruppen werden mit `(0)` ausgewiesen.

### Arbeit

- kein sichtbares `Als Aufgabe starten` für Backlogpunkte,
- kein redundanter `Details`-Button,
- aufgeklappte Zeile ist die vollständige Detailebene.

### Verwaltung

Redaktionelle Events und Anbieter-Events werden getrennt und parallel geladen. Jede Anfrage besitzt ein Zeitlimit von 15 Sekunden. Eine verfügbare Quelle bleibt sichtbar, wenn die andere Quelle ausfällt. Nur beim Ausfall beider Eventquellen erscheint ein Gesamtfehler.

### Entwicklung

Die Hauptansicht besteht nur aus:

1. Content,
2. Prozesse,
3. Sichtbarkeit.

Weitere Werte liegen eingeklappt. Repo-Workpacks werden nicht wiederholt. Frühere Erweiterungscontroller hängen keine zusätzlichen Entwicklungsblöcke mehr an.

## Automatische Produktverträge

Der Audit verlangt jetzt:

- Zähler auf allen Prüffiltern,
- keine sichtbare Backlog-Umwandlungsaktion,
- keinen Details-Button in Arbeitszeilen,
- getrennte Verwaltungsquellen,
- Zeitlimit und Teilfehleranzeige,
- kompakte Entwicklung,
- keine Workpack-Duplikation.

Der Workflow prüft weiterhin PHP- und JavaScript-Syntax, Domänensimulationen, Produktvertrag, Pflichtdateien und Quellenverträge. Für die jüngsten Commits wurde über den verfügbaren Connector kein Statuscheck geliefert; ein grüner Lauf wird deshalb nicht behauptet.

## Cache-Vertrag

Asset-Version:

```text
2026-07-10-control-center-freeze-v2
```

Sie gilt für JavaScript, CSS-Entry-Point und `control-center.css`.

## Freeze-Beobachtung

Während der Nutzung werden insbesondere gesammelt:

- Lade- und Speicherfehler,
- unplausible Zähler,
- mobile Darstellungsprobleme,
- falsche Prozessbewertungen,
- Abweichungen zwischen führender Quelle und öffentlicher Seite.

## Ergebnis

Der Stand wird nach Deployment zunächst praktisch genutzt und nicht weiter konzeptionell verändert. Neue Rückmeldungen werden gesammelt und später als ein gemeinsames Folge-Workpack bewertet. Eine Main-Freigabe ist nicht automatisch Teil dieses Freeze-Stands.
