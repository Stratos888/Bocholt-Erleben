# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10  
Status: Feedback-Korrekturpatch umgesetzt; Freeze-Stand für praktische Nutzung vorbereitet

## 1. Verbindliche Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

Seltene Funktionen liegen im Header-Menü.

## 2. Nutzerorientierter Freeze-Zustand

### Übersicht

- zeigt nur aktuelle Aufmerksamkeit, offene Prüfungen, aktive Arbeit und einen verdichteten Entwicklungsstatus,
- Backlog wird als Zusatzinformation gezeigt, aber nicht als aktive Arbeit gezählt,
- Entwicklung nennt keine Repo-Workpacks mehr.

### Prüfen

- neue Inhalte, Qualitätsfälle, Anbieterentscheidungen und Freigaben,
- direkte Synchronisation aus der führenden Sheet-Inbox,
- JSON-Inbox ausschließlich als Fehlerfallback,
- jeder Filter zeigt seine aktuelle Anzahl:
  - Alle,
  - Neue Inhalte,
  - Qualität,
  - Anbieter,
  - Freigaben,
  - Sonstige,
- mobil fokussierter Einzelfall, Desktop Queue plus Detail.

### Arbeit

- Jetzt, Als Nächstes, Wartet, Blockiert, Backlog, Ideen und Archiv,
- kompakte, aufklappbare Zeilen,
- der aufgeklappte Inhalt ist die einzige Detailebene,
- kein zusätzlicher `Details`-Button,
- Backlogpunkte werden nicht mehr über `Als Aufgabe starten` in einen zweiten Arbeitsstatus überführt,
- Backlogaktionen bleiben auf Bearbeiten, Abschließen und Verwerfen reduziert,
- technische Warte- und Blockadefälle bleiben sichtbar, weil sie für automatisierte Prozesse und Veröffentlichungen erforderlich sind.

### Verwaltung

Die Verwaltung lädt die Quellen getrennt:

1. redaktionelle Events aus dem Events-Sheet,
2. Anbieter-Events aus der Einreichungsdatenbank,
3. Aktivitäten aus `data/offers.json`.

Für die beiden Eventquellen gilt:

- paralleles Laden mit festem Zeitlimit,
- Ausfall einer Quelle blockiert die andere Quelle nicht,
- Teilfehler wird verständlich angezeigt,
- kein dauerhafter Ladezustand ohne Rückmeldung,
- Suche ohne Fokusverlust,
- Quelle je Event sichtbar,
- Bearbeiten und Vorschau als Hauptaktionen.

Sheet-Events schreiben in das führende Sheet. Anbieter-Events schreiben transaktional in `submissions`. Beide verwenden Änderungsverlauf und öffentliche Wirkungskontrolle.

Die Aktivitätsbearbeitung bleibt durch das explizite Repo-Gate geschützt:

1. `BE_GITHUB_REPO_TOKEN` und Repository-/Branch-Konfiguration,
2. `BE_ACTIVITY_WRITEBACK_ENABLED=true`.

### Entwicklung

Die Hauptansicht ist bewusst auf drei mobile Kernkarten reduziert:

1. Content,
2. Prozesse,
3. Sichtbarkeit.

Zusätzliche Messwerte liegen eingeklappt unter `Weitere Messwerte`.

Nicht mehr in der Entwicklungsansicht:

- aktuelle Repo-Workpacks,
- ausführliche Lernregellisten,
- separate Textblöcke zur SEO-Datenlage,
- dauerhaft ausgeklappte Search-, Intake-, Funnel- und Prozessdetails.

Workpacks und geplante Verbesserungen bleiben ausschließlich unter `Arbeit → Backlog`.

## 3. Integrierte Prozesskette

```text
Content-Audit
→ Such- und Visual-Feedback
→ wöchentliche KI-Eventsuche
→ KI-Intake
→ führende Sheet-Inbox
→ Prüfen
→ führende Event-/Aktivitätsquelle
→ Deployment oder öffentliche API
→ öffentliche Wirkung
→ Content-Ops-/SEO-/Funnel-Messung
→ Entwicklung beziehungsweise neue Arbeit
```

Wesentliche Regeln:

- kandidaterzeugender Suchlauf benötigt einen nachgelagerten Intake-Lauf,
- Quellfehler werden einzeln gekapselt und als blockierte Arbeit sichtbar,
- ein Quellfehler macht nicht die gesamte Steuerzentrale unbenutzbar,
- Veröffentlichung gilt erst nach bestätigter öffentlicher Wirkung als abgeschlossen,
- fehlende Search-Console-/GA4-Daten werden nicht als Nullerfolg ausgegeben.

## 4. Öffentliche Inhaltsquellen

### Redaktionelle Events

```text
Steuerzentrale
→ Events-Sheet
→ Apps-Script-Deployment
→ /data/events.json
→ Feldvergleich
→ Bestätigung
```

### Anbieter-Events

```text
Steuerzentrale
→ transaktionaler submissions-Writeback
→ /api/events/public.php
→ Feldvergleich einschließlich Adresse, Quelle und Ticketlink
→ Bestätigung
```

### Aktivitäten

```text
Steuerzentrale
→ explizites Repo-Gate
→ SHA-geschützter GitHub-Commit
→ /data/offers.json
→ Feldvergleich
→ Bestätigung
```

## 5. Automatische Verträge

Der Produktvertragsaudit prüft zusätzlich:

- Zähler auf allen Prüffiltern,
- kein redundanter Details-Button in Arbeitszeilen,
- keine sichtbare Backlog-Umwandlungsaktion,
- getrennte Verwaltungsquellen,
- Zeitlimit und Teilfehlerverhalten der Verwaltung,
- kompakte Entwicklungsansicht mit Content, Prozesse und Sichtbarkeit,
- keine Workpack-Duplikation in Entwicklung,
- Cache-Version des Freeze-Stands.

Die GitHub-Validierung prüft weiterhin:

- PHP-Syntax,
- JavaScript-Syntax,
- ausführbare Domänen- und Prozesskettensimulation,
- Produktvertrag,
- Sicherheits- und Quellenverträge.

## 6. Freeze-Regel

Der aktuelle Stand wird nach erfolgreichem Deployment als Arbeits-Freeze verwendet.

Bis zum nächsten gebündelten Nutzerfeedback gelten folgende Regeln:

- keine neue Navigation,
- keine zusätzlichen Statusmodelle,
- keine neuen Hauptkarten oder Workpack-Duplikate,
- nur Fehlerkorrekturen bei nachweisbaren Funktionsproblemen,
- Feedback wird gesammelt und anschließend als ein zusammenhängendes Folge-Workpack bewertet.

## 7. Externe Grenzen

Weiterhin real zu bestätigen beziehungsweise zu konfigurieren:

- Google-Sheets-Lese- und Schreibzugriff,
- Apps-Script-Deployment,
- Staging-MySQL und vorhandene Content-Ops-Daten,
- öffentliche Feed-/API-Aktualisierung,
- Aktivitäts-GitHub-Secret und Freigabeschalter,
- reale Search-Console-/GA4-Daten,
- Browserdarstellung auf den Zielgeräten.

## 8. Freigabestatus

| Bereich | Status |
|---|---|
| Informationsarchitektur | Freeze-Ziel umgesetzt |
| Prüfen-Zähler | für alle Filter umgesetzt |
| Arbeit | vereinfacht; keine zweite Detailebene und keine Backlog-Umwandlung |
| Verwaltung | getrennte Quellen, Timeout und Teilfehleranzeige umgesetzt |
| Entwicklung | kompakte mobile Kernansicht umgesetzt |
| Workpacks | ausschließlich im Backlog |
| Eventveröffentlichung | technisch integriert; reale Wirkung weiter beobachtbar |
| Aktivitätsverwaltung | technisch vorbereitet; Server-Gate bleibt maßgeblich |
| Freeze | nach Deployment für praktische Nutzung vorgesehen |
| Main-Merge | nicht Bestandteil dieses Freeze; gesonderte Freigabe erforderlich |
