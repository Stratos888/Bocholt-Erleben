# Internes Dashboard: Premium-Steuerzentrale

Ziel: Eine mobile interne Steuerzentrale für Bocholt erleben, die nicht nach technischen Quellen aufgebaut ist, sondern nach Nutzeraufgaben.

Das Dashboard soll dem Betreiber ohne Entwicklerwissen beantworten:

1. Läuft der Prozess grundsätzlich?
2. Was muss ich jetzt manuell tun?
3. Warum ist diese Aufgabe wichtig?
4. Was kann KI vorbereiten?
5. Was ist nur Beobachtung oder späteres Backlog?
6. Kann ich den Daten vertrauen?
7. Wird die App besser und relevanter?

## Validierter Gesamtansatz

Das Dashboard darf nicht mehr Datenquellen direkt in UI-Bereiche übersetzen. Die bisherigen Quellen bleiben technisch bestehen, werden aber in ein einheitliches Aufgabenmodell normalisiert.

Technische Quellen sind zum Beispiel:

- `data/inbox.json`
- Content-Prüfung / `Content_Audit`
- Growth-/Acquisition-Backlog
- Repo-/Produkt-/Technik-Backlog
- Systemläufe und Robot-Zustände
- manuelle Notizen

Die UI zeigt daraus nicht primär `Inbox`, `Content-Prüfung`, `Backlog`, `Wachstum` oder `System`, sondern priorisierte Aufgaben.

Grundregel:

- Alle Aufgaben stehen zentral im Tab `Aufgaben`.
- Andere Tabs liefern Kontext, Bewertung und Vertrauen.
- Operative Bearbeitung nutzt die bestehende vollständige `/inbox/`-Arbeitsansicht.
- Backlog-Aufgaben werden nicht über mehrere Tabs verteilt, sondern im Aufgaben-Tab gegeneinander priorisiert.

## Rolle der bestehenden Inbox

Die bestehende `/inbox/` bleibt die vollständige operative Bearbeitungsansicht.

Sie wird nicht neu nachgebaut und nicht durch eine zweite vereinfachte Arbeitsansicht ersetzt, weil dort bereits die vollständigen Writeback-Pfade und Schutzlogiken existieren:

- Apps-Script-Writeback für KI-/Sheet-Inbox-Fälle
- Submission-Review-Endpunkte für eingereichte Events/Aktivitäten
- Content-Audit-Update-Endpunkt für Content-Prüffälle
- Growth-Backlog-Endpunkte für bestehende Backlog-Aktionen

Zielintegration:

- `/intern/` ist die Steuerzentrale und priorisiert Aufgaben.
- `/inbox/` ist die operative Bearbeitungsansicht für konkrete Writeback-Fälle.
- Aufgaben aus `/intern/` verlinken gezielt in `/inbox/`, idealerweise mit Modus-Deep-Link wie `?mode=content_audit`, `?mode=curation` oder `?mode=growth_backlog`.
- Die Passwortabfrage soll im Normalfall einmal pro interner Sitzung reichen. Die Inbox soll ein bereits im Dashboard eingegebenes Review-Passwort übernehmen oder anderweitig nahtlos entsperrt werden, ohne Passwort in URLs zu schreiben.

Die zwischenzeitlich angelegte `/intern/work.html` ist nicht der Zielzustand und darf nicht primärer Arbeitsweg bleiben. Der Zielzustand nutzt die bestehende vollständige Inbox statt einer reduzierten Nachbildung.

## Hauptnavigation Zielzustand

Die Zielnavigation soll die Aufgabenlogik stützen und nicht technische Quellen ausstellen.

Empfohlenes Zielbild:

1. `Heute`
2. `Aufgaben`
3. `Entwicklung`
4. `System`

### Heute

Zweck: Lagebild und nächste sinnvolle Aktion.

Zeigt nur:

- Gesamtstatus: Prozess greift / prüfen / blockiert
- nächste Aufgabe
- Anzahl Aufgaben heute
- Anzahl spätere / unbewertete Backlog-Aufgaben
- Anzahl Beobachtungsfälle
- Systemvertrauen kurz

Keine Detailarbeit, keine langen Listen, keine technischen Rohwerte.

### Aufgaben

Zweck: zentrale priorisierte Aufgabenliste für alles, was getan, bewertet, vorbereitet oder entschieden werden muss.

Dieser Tab enthält auch Backlog-Aufgaben. Backlog wird nicht auf Qualität, Wachstum und System verteilt, weil Aufgaben sonst untergehen und nicht gegeneinander priorisierbar sind.

Filter oder Segmente sollen nach Nutzerlogik funktionieren, zum Beispiel:

- `Heute`
- `Diese Woche`
- `Später`
- `KI`
- `Backlog`
- `Erledigt`
- optional `Alle`

Die Liste ist priorisiert, nicht quellengetrieben.

### Entwicklung

Zweck: Kontext und Bewertung, ob Bocholt erleben besser und relevanter wird.

Dieser Tab fasst die bisher getrennten Sichtweisen `Qualität` und `Wachstum` als Kontext zusammen:

- Content-Qualität
- Quellenqualität
- Bild-/Visual-Qualität
- SEO-/GSC-/GA4-Signale
- KI-Suche / Content-Chancen
- Backlog-Einordnung nach Bereichen
- Trends über 7/28 Tage und seit Referenzpunkt

Wichtig: Entwicklung erzeugt Aufgaben, zeigt sie aber nicht als separate Arbeitsliste. Handlungsrelevante Punkte erscheinen zentral im Aufgaben-Tab.

### System

Zweck: Datenvertrauen und Automatikstatus.

Zeigt nur:

- Kernläufe OK / veraltet / blockiert
- Datenalter
- Persistierung / Ingest OK
- optionale Läufe als optional, nicht als Fehler

System enthält keine normale Aufgabenliste. Nur echte Blocker erzeugen Aufgaben im Aufgaben-Tab.

## Einheitliches Aufgabenmodell

Alle Quellen werden in ein gemeinsames Task-Modell übersetzt.

Jede Aufgabe benötigt mindestens:

- `id`
- `title`
- `why`
- `priority`: `sofort`, `hoch`, `mittel`, `niedrig`
- `horizon`: `heute`, `diese_woche`, `monatsreview`, `später`, `archiv`
- `area`: `inhalt`, `qualität`, `wachstum`, `system`, `produkt`, `technik`
- `work_type`: `manuell_bearbeiten`, `bewerten`, `ki_vorbereiten`, `beobachten`, `planen`
- `source`: technische Herkunft, nur als kleines Label
- `count`: Anzahl betroffener Einzelfälle, falls aggregiert
- `status`: `open`, `snoozed`, `done`, `rejected`, `archived`
- `primary_action`: Label und Ziel
- optional `effort`, `risk`, `confidence`, `details`

Beispiel operative Aufgabe:

```json
{
  "id": "content-audit-open",
  "title": "Content-Prüffälle bearbeiten",
  "why": "Inhalte, Quellen und Bilder brauchen manuelle Prüfung.",
  "priority": "hoch",
  "horizon": "heute",
  "area": "qualität",
  "work_type": "manuell_bearbeiten",
  "source": "content_audit",
  "count": 39,
  "status": "open",
  "primary_action": {
    "label": "Bearbeiten",
    "url": "/inbox/?mode=content_audit"
  }
}
```

Beispiel Backlog-Aufgabe:

```json
{
  "id": "growth-backlog-weekend-intent",
  "title": "Wochenend-Suchintention für Veranstaltungen prüfen",
  "why": "Mögliche SEO-Chance mit lokaler Relevanz.",
  "priority": "mittel",
  "horizon": "monatsreview",
  "area": "wachstum",
  "work_type": "bewerten",
  "source": "growth_backlog",
  "count": 1,
  "status": "open",
  "primary_action": {
    "label": "Bewerten",
    "url": "/inbox/?mode=growth_backlog"
  }
}
```

Beispiel Repo-/Produktaufgabe:

```json
{
  "id": "internal-dashboard-premium-task-model",
  "title": "Internes Dashboard auf Premium Task Model umbauen",
  "why": "Die Steuerzentrale muss Aufgaben aus allen Quellen einheitlich priorisieren.",
  "priority": "hoch",
  "horizon": "diese_woche",
  "area": "produkt",
  "work_type": "planen",
  "source": "repo_backlog",
  "count": 1,
  "status": "open",
  "primary_action": {
    "label": "Patch vorbereiten",
    "url": "repo"
  }
}
```

## Priorisierung

Priorisiert wird nicht nach Quelle, sondern nach Wirkung.

Interne Bewertungsfaktoren:

1. Nutzer-/Produktwirkung
2. Risiko für Datenvertrauen
3. Zeitdruck
4. manuelle Notwendigkeit
5. Aufwand
6. KI-Vorbereitbarkeit
7. Wiederholung / Häufigkeit
8. Abhängigkeiten

Nutzerseitige Prioritätslogik:

- `Sofort`: blockiert Vertrauen, Veröffentlichung, Zahlung, Freigabe oder Nutzung
- `Heute`: konkrete manuelle Prüfung oder Entscheidung nötig
- `Diese Woche`: wichtig, aber nicht tageskritisch
- `Monatsreview`: strategisch prüfen
- `Später`: Idee ohne akuten Nutzen
- `Archiv`: erledigt, abgelehnt oder nicht relevant

Backlog-Aufgaben können `Heute` oder `Diese Woche` werden, wenn Wirkung und Dringlichkeit hoch genug sind. Sie sind nicht automatisch später.

## Umgang mit den zwei Backlogs

Es gibt zwei Backlog-Arten:

### 1. Dashboard-/Growth-Backlog

Quelle für automatisch erzeugte Chancen, Growth-Hinweise, SEO-Ideen, Visual-Debt, KI-Funde und manuelle Notizen.

Dieser Backlog ist ein Intake-Backlog. Neue Punkte sind zunächst unbewertet.

Mögliche Entscheidungen:

- verwerfen
- später parken
- für KI vorbereiten
- in verbindliche Aufgabe überführen
- sofort bearbeiten, wenn dringend

### 2. Repo-/Produkt-/Technik-Backlog

Quelle für verbindliche Produkt-, Technik-, UX- und Dokumentationsaufgaben.

Dieser Backlog muss maschinenlesbar werden, damit er gegen operative und Growth-Aufgaben priorisiert werden kann. Zielartefakt zum Beispiel:

- `data/project_tasks.json`

Die Doku kann diese Aufgaben erklären, aber das Dashboard braucht strukturierte Daten.

## Aufgaben-Tab Zielstruktur

Der Aufgaben-Tab zeigt eine einzige priorisierte Liste.

Beispiel:

1. `Content-Prüffälle bearbeiten`
   - hoch · heute · Qualität · manuell
   - 39 Fälle
   - Button: `Bearbeiten`

2. `Quellen recherchieren lassen`
   - hoch · KI · Qualität
   - 11 Fälle
   - Button: `Für KI vorbereiten`

3. `Internes Dashboard auf Premium Task Model umbauen`
   - hoch · diese Woche · Produkt
   - Quelle: Repo-Backlog
   - Button: `Patch vorbereiten`

4. `Wochenend-Suchintention für Veranstaltungen prüfen`
   - mittel · Monatsreview · Wachstum
   - Quelle: Growth-Backlog
   - Button: `Bewerten`

5. `Event-Bildbestand gezielt weiter härten`
   - mittel · Qualität · Backlog
   - Button: `Bewerten`

6. `Beobachtungsfälle ansehen`
   - niedrig · beobachten
   - Button: `Ansehen`

Karten zeigen im Standard nur:

- Titel
- ein Satz warum
- Priorität / Zeithorizont / Bereich / Arbeitsart als Chips
- eine Primäraktion

Details werden erst aufgeklappt.

## Nächster Workpack

Name: `Internes Dashboard Premium Task Model`

Inhalt:

1. `/intern/work.html` aus dem primären Flow entfernen oder nicht mehr verlinken.
2. Bestehende `/inbox/` als vollständige operative Bearbeitung behalten.
3. Inbox-Modus-Deep-Links und nahtlosen Passwortfluss herstellen.
4. `data/project_tasks.json` für Repo-/Produkt-/Technikaufgaben anlegen.
5. `api/internal-tasks.php` als zentrale Task-Normalisierung bauen.
6. Dashboard-/Growth-Backlog als Intake-Quelle einbinden.
7. Aufgaben-Tab auf priorisierte Gesamtliste umbauen.
8. Heute auf Lagebild + nächste Aufgabe reduzieren.
9. Qualität und Wachstum zu `Entwicklung` als Kontext konsolidieren.
10. System nur als Datenvertrauen und Automatikstatus belassen.
11. Backlog-Entscheidungen ermöglichen: verwerfen, parken, für KI vorbereiten, in verbindliche Aufgabe überführen.

## Zeiträume

- Heute
- Diese Woche
- Monatsreview
- 7 Tage
- 28 Tage
- seit Referenzpunkt 2026-07-08
- seit Launch
- 6 Monate, sobald genug Historie vorhanden ist

## Zugriff

Die Seite liegt unter `/intern/`. Dashboard-Daten kommen aktuell aus `api/content-ops-dashboard.php`; konkrete Fälle kommen aktuell aus `api/content-ops-cases.php`.

Ziel ist ein zusätzlicher zentraler Aufgaben-Endpunkt:

- `api/internal-tasks.php`

Die bestehende operative Bearbeitung liegt unter `/inbox/` und bleibt der vollständige Writeback-Ort.
