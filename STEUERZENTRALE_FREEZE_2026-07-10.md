# Steuerzentrale – Freeze-Stand 2026-07-10

## Zweck

Dieser Stand wird nach dem Deployment zunächst praktisch genutzt. Ziel ist, reale Bedienerfahrungen zu sammeln, ohne die Informationsarchitektur währenddessen erneut grundsätzlich umzubauen.

## Freeze-Umfang

### Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

### Verbindliche Bedienregeln

- `Prüfen` zeigt auf jedem Filter die aktuelle Fallzahl.
- Auf Mobile wird der Prüfbereich über ein kompaktes Dropdown ausgewählt; die breite Pill-Leiste ist dort nicht das führende Bedienelement.
- Qualitätsfälle zeigen aktuellen Inhalt und einen direkt übernehmbaren Vorschlag.
- `Arbeit` verwendet aufklappbare Zeilen als einzige Detailebene.
- Die sichtbare Arbeitslogik besteht nur aus `Offen` und `Backlog`.
- Technische Zustände wie wartet oder blockiert bleiben intern möglich, werden aber unter `Offen` zusammengeführt.
- Ideen werden nicht in der Steuerzentrale geführt; unscharfe Themen laufen über SpecDoc und werden bei Bedarf direkt als Backlogpunkt angelegt.
- Erledigte Vorgänge werden nicht über ein sichtbares Archiv bedient; dauerhafte Nachvollziehbarkeit bleibt in Repo-Dokumentation und technischem Verlauf erhalten.
- Backlogpunkte werden bearbeitet, abgeschlossen oder verworfen.
- `Verwaltung` lädt redaktionelle Events und Anbieter-Events getrennt, parallel, mit Zeitlimit und Teilfehleranzeige.
- `Entwicklung` zeigt ein kompaktes SEO- und Wirkungsdashboard statt Repo-Workpacks.
- Repo-Workpacks erscheinen ausschließlich unter `Arbeit → Backlog`.

## Technischer Freeze-Vertrag

- JavaScript-Asset-Version: `2026-07-10-control-center-feedback-v2`
- CSS-Governance-Version: `2026-06-22-css-governance-v1`
- Branch: `staging`
- führende Inbox: Google-Sheet-Tab `Inbox`
- Inbox-Fallback: `data/inbox.json` nur bei Sheet-Fehler
- redaktionelle Events: Events-Sheet
- Anbieter-Events: `submissions`
- Aktivitäten: `data/offers.json` mit explizitem Repo-Gate
- Veröffentlichung gilt erst nach öffentlicher Bestätigung als abgeschlossen
- Quell- und Prozessfehler bleiben als deduplizierte offene Arbeit sichtbar

## Gebündeltes Nutzungsfeedback

Umgesetzt sind:

- `Prüfen`: aktueller Text und Vorschlag im direkten Vergleich.
- `Prüfen`: Ablehnen löst den aktuellen Audit-Datensatz über `content_id + issue_code` auf und ist nicht von einer veralteten Zeilennummer abhängig.
- `Prüfen`: mobile Auswahl der Prüfgruppe über ein Dropdown mit Fallzahlen.
- `Arbeit`: nur `Offen` und `Backlog` als sichtbare Auswahl.
- `Arbeit`: `+ Neu` legt direkt einen Backlogpunkt an.
- `Arbeit`: Ideen und sichtbares Archiv entfallen.
- `Verwaltung`: mobile Karten, Aktionen und Suchbereich sind verdichtet.
- `Entwicklung`: technische SEO-Basis, Search-Console-Status, GA4-Status, Inhaltsvollständigkeit sowie Such- und Intake-Wirkung werden gemeinsam dargestellt.

## SEO- und Wirkungsgrenze

Das Dashboard zeigt nur nachweislich vorhandene Daten:

- technische Onpage-Abdeckung,
- Content-Vollständigkeit,
- Anzahl vorhandener Search-Console-Datensätze,
- Anzahl vorhandener GA4-Datensätze,
- vorhandene Wertsignale,
- Suchkandidaten und Intake-Wirkung.

Klicks, Impressionen, CTR, Positionen und konkrete Seitenverläufe können erst angezeigt werden, wenn diese Kennzahlen durch den Growth-/SEO-Prozess in die Betriebsdatenbank geschrieben werden. Fehlende Daten werden als `Nicht angebunden` ausgewiesen und nicht als Nullleistung interpretiert.

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
- neue sichtbare Aufgabenstatus,
- neue Workpack-Duplikate,
- spontane Einzeloptimierungen ohne Gesamtabgleich.

Ausnahme: belegte Funktionsfehler mit unmittelbarer Auswirkung dürfen als kleiner Fehlerkorrekturpatch behoben werden.

## Bekannte externe Grenzen

- Aktivitätseditor benötigt Server-Secret und explizite Freigabe.
- Search Console und GA4 werden nur bei tatsächlich vorhandenen Betriebsdaten angezeigt.
- Google Sheets, Apps Script, MySQL und öffentliche Feeds müssen zur Laufzeit verfügbar sein.
- Änderungen werden zuerst auf `staging` geprüft und erst danach kontrolliert nach `main` übernommen.

## Fortsetzung nach dem Freeze

Nach ausreichender Nutzung werden alle Rückmeldungen gesammelt, priorisiert und als ein zusammenhängendes Folge-Workpack gegen den Premium-Zielzustand bewertet. Einzelne Beobachtungen sollen nicht wieder zu kleinteiligen, widersprüchlichen Umbauten führen.
