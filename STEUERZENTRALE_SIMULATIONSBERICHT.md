# Steuerzentrale – Simulations- und Validierungsbericht

Stand: 2026-07-10  
Status: Gesamtprojektintegration plus Feedback-Korrekturpatch dokumentiert; Freeze-Nutzung vorbereitet

## 1. Prüfprinzip

Der Bericht trennt verbindlich:

1. statische Syntax- und Vertragsprüfung,
2. ausgeführte interne Domänensimulation,
3. reale Laufzeitnachweise gegen Google Sheets, Apps Script, MySQL, GitHub, öffentliche Feeds und Browser.

Keine Ebene ersetzt eine andere.

## 2. Integrierte Gesamtkette

```text
Content-Audit
→ Such- und Visual-Feedback
→ wöchentliche KI-Eventsuche
→ KI-Intake
→ führende Sheet-Inbox
→ Steuerzentrale / Prüfen
→ Events-Sheet oder Anbieter-Datenbank
→ Deployment beziehungsweise öffentliche API
→ öffentlicher Event-/Aktivitätsbestand
→ Nutzer-Funnel
→ Content-Ops-, Search-Console- und GA4-Wirkung
→ Entwicklung beziehungsweise neue Arbeit
```

## 3. Bereits ausgeführte Domänensimulationen

Geprüft wurden:

- Eventfeld-Aliasse und vollständige Updateplanung,
- Pflichtfeld-, Datums- und URL-Schutz,
- öffentliche Eventwerte und Abweichungen,
- Anbieter-Events mit separater Adresse, Quelle und Ticketlink,
- Aktivitätsfeld-Whitelist und Sicherheitsgate,
- Veröffentlichungszustände `failed`, `blocked`, `waiting`, `confirmed`,
- Arbeitsstatusübergänge,
- technischer Fehler gegenüber erfolgreichem Lauf mit fachlicher Folgearbeit,
- abhängige Suchlauf→Intake-Kette,
- fehlender, älterer und bestätigter Intake.

Die Simulation fand und bestätigte die Korrektur des PHP-Zeitstempelfalls, bei dem ein leerer Intake-Zeitstempel zuvor als aktueller Zeitpunkt interpretiert werden konnte.

## 4. Feedback-Korrekturpatch

Der Freeze-Patch korrigiert die auf Staging beobachteten Punkte.

### Prüfen

- alle Filter zeigen ihre eigene Anzahl,
- die Zahl basiert immer auf allen aktuell offenen Prüffällen und nicht nur auf dem gerade ausgewählten Filter.

### Arbeit

- kein `Als Aufgabe starten` für Backlogpunkte,
- kein redundanter `Details`-Button,
- aufgeklappte Zeile ist die vollständige Detailebene,
- technische Zustände `Wartet` und `Blockiert` bleiben für reale Prozess- und Veröffentlichungsfälle erhalten.

### Verwaltung

Die vorher beobachtete Endlosanzeige `Inhalte werden geladen …` wird durch einen fehlertoleranten Ladevertrag ersetzt:

```text
Redaktionelle Events ─┐
                      ├─ parallel, jeweils mit Zeitlimit
Anbieter-Events ──────┘
→ verfügbare Quellen sofort anzeigen
→ Teilfehler verständlich melden
→ nur bei Ausfall aller Eventquellen Gesamtfehler anzeigen
```

Aktivitäten besitzen einen eigenen Ladepfad mit Zeitlimit.

### Entwicklung

Die sichtbare Hauptansicht besteht nur noch aus:

1. Content,
2. Prozesse,
3. Sichtbarkeit.

Weitere Werte sind standardmäßig eingeklappt. Repo-Workpacks werden nicht mehr in Entwicklung wiederholt.

## 5. Automatische Produktverträge des Freeze-Stands

Der Produktvertragsaudit verlangt nun:

- Zähler auf allen Prüffiltern,
- Filterung der Backlog-Umwandlungsaktion,
- keinen Details-Button in Arbeitszeilen,
- getrennte Eventquellen in der Verwaltung,
- paralleles Laden mit Zeitlimit,
- Teilfehleranzeige,
- kompakte Entwicklungsansicht,
- keine Workpack-Duplikation in Entwicklung,
- ehrlichen Veröffentlichungsstatus.

Der Workflow prüft weiterhin PHP-/JavaScript-Syntax, beide Prozesssimulationen, Produktvertrag, Pflichtdateien und Sicherheitsverträge.

## 6. Cache- und Auslieferungsvertrag

Der Freeze verwendet eine eigene Asset-Version:

```text
2026-07-10-control-center-freeze-v1
```

Die Version gilt für:

- Steuerzentralen-JavaScript,
- Integrationscontroller,
- den CSS-Entry-Point,
- den Import von `control-center.css`.

Damit soll verhindert werden, dass der Browser die zuvor beobachtete Oberfläche aus dem Cache weiterverwendet.

## 7. Reale Nachweise während der Freeze-Nutzung

Während der praktischen Nutzung werden insbesondere beobachtet:

- Verwaltung lädt redaktionelle Events und Anbieter-Events,
- Teilfehler einer Quelle blockiert die andere nicht,
- Suche behält den Fokus,
- Speichern startet die Aktualisierung und bestätigt später die öffentliche Wirkung,
- Prüffilter zeigen plausible Zähler,
- Backlog ist ohne unnötige Statusumwandlung bedienbar,
- Entwicklung bleibt auf Mobilgeräten kompakt,
- Prozessfehler erscheinen als Arbeit und verschwinden nach erfolgreichem Folgelauf.

## 8. Weiterhin externe Grenzen

Nicht allein durch den Repository-Code beweisbar sind:

- reale Google-Sheets-Berechtigungen,
- tatsächlicher Apps-Script-Deploy,
- Staging-MySQL und vorhandene Content-Ops-Daten,
- öffentliche Feed-/API-Aktualisierung,
- GitHub-Secret für Aktivitäten,
- reale Search-Console-/GA4-Daten,
- Browser- und Geräteverhalten.

## 9. Freeze-Ergebnis

Der Stand wird nach Deployment bewusst nicht weiter konzeptionell erweitert. Er dient zunächst als praktischer Arbeitsstand. Neue Beobachtungen werden gesammelt und erst danach als gebündeltes Folge-Workpack analysiert.

Eine Main-Freigabe ist nicht automatisch Bestandteil dieses Freeze-Stands.
