# Steuerzentrale – Freeze-Stand 2026-07-10

## Zweck

Dieser Stand wird nach dem Deployment zunächst praktisch genutzt. Ziel ist, reale Bedienerfahrungen zu sammeln, ohne die Informationsarchitektur oder das Statusmodell währenddessen erneut umzubauen.

## Freeze-Umfang

### Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

### Verbindliche Bedienregeln

- `Prüfen` zeigt auf jedem Filter die aktuelle Fallzahl.
- `Arbeit` verwendet aufklappbare Zeilen als einzige Detailebene.
- Backlogpunkte werden bearbeitet, abgeschlossen oder verworfen; eine separate Umwandlung zur Aufgabe ist nicht Teil der sichtbaren Bedienung.
- `Verwaltung` lädt redaktionelle Events und Anbieter-Events getrennt und fehlertolerant.
- `Entwicklung` zeigt in der Hauptansicht nur Content, Prozesse und Sichtbarkeit.
- Repo-Workpacks erscheinen ausschließlich unter `Arbeit → Backlog`.

## Technischer Freeze-Vertrag

- Asset-Version: `2026-07-10-control-center-freeze-v1`
- Branch: `staging`
- führende Inbox: Google-Sheet-Tab `Inbox`
- Inbox-Fallback: `data/inbox.json` nur bei Sheet-Fehler
- redaktionelle Events: Events-Sheet
- Anbieter-Events: `submissions`
- Aktivitäten: `data/offers.json` mit explizitem Repo-Gate
- Veröffentlichung gilt erst nach öffentlicher Bestätigung als abgeschlossen
- Quell- und Prozessfehler bleiben als deduplizierte Arbeit sichtbar

## Während des Freeze zu sammeln

- Bedienprobleme,
- unklare Begriffe,
- fehlende oder überflüssige Informationen,
- mobile Darstellungsprobleme,
- unerwartete Zähler,
- Lade- und Speicherfehler,
- falsche Prozessbewertungen,
- Abweichungen zwischen führender Quelle und öffentlicher Seite.

## Während des Freeze nicht einzeln ändern

- Hauptnavigation,
- Grundtrennung `Prüfen` / `Arbeit`,
- neue Aufgabenstatus,
- neue Hauptkarten in Entwicklung,
- neue Workpack-Duplikate,
- spontane Einzeloptimierungen ohne Gesamtabgleich.

Ausnahme: belegte Funktionsfehler mit unmittelbarer Auswirkung dürfen als kleiner Fehlerkorrekturpatch behoben werden.

## Bekannte externe Grenzen

- Aktivitätseditor benötigt Server-Secret und explizite Freigabe.
- Search Console und GA4 werden nur bei tatsächlich vorhandenen Betriebsdaten angezeigt.
- Google Sheets, Apps Script, MySQL und öffentliche Feeds müssen zur Laufzeit verfügbar sein.
- Main-Merge ist nicht Bestandteil dieses Freeze-Beschlusses.

## Fortsetzung nach dem Freeze

Nach ausreichender Nutzung werden alle Rückmeldungen gesammelt, priorisiert und als ein zusammenhängendes Folge-Workpack gegen den Premium-Zielzustand bewertet. Einzelne Beobachtungen sollen nicht wieder zu kleinteiligen, widersprüchlichen Umbauten führen.
