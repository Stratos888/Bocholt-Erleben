# Internes Dashboard

Ziel: Eine mobile interne Steuerzentrale für Bocholt erleben.

## Nutzerfrage

Das Dashboard beantwortet zuerst:

1. Läuft der Prozess grundsätzlich?
2. Was muss ich jetzt manuell tun?
3. Warum ist diese manuelle Aufgabe nötig?
4. Kann ich den Daten vertrauen?
5. Wird die App besser und relevanter?

## Struktur

- Heute: kurze Lage, nächste sinnvolle manuelle Aktion, kompakter Status.
- Aufgaben: zentrale manuelle Arbeitszentrale. Keine technischen Modi als Hauptnavigation.
- Qualität: Bewertung, ob der Qualitätsprozess greift und wo die größten Hebel liegen.
- Wachstum: kurzer Überblick zu SEO, KI-Suche, Sichtbarkeit und Monatsreview.
- System: Vertrauensbasis der Daten und Automatik; optionale Läufe sind keine Blocker.

## Aufgaben-Tab

Der Aufgaben-Tab ist aus Nutzersicht aufgebaut und nicht nach internen Datenquellen:

1. Was muss ich jetzt tun?
2. Jetzt bearbeiten
3. Für KI vorbereiten
4. Nur beobachten
5. Details ansehen

`Jetzt bearbeiten` enthält nur echte manuelle Arbeit, zum Beispiel:

- neue Inbox-Einträge prüfen
- Content-Prüffälle bearbeiten
- Wachstums-/Backlog-Punkte prüfen
- echte Systemblocker prüfen

Die Bearbeitung läuft über `/intern/work.html`. Diese Arbeitsansicht verwendet das bereits im Dashboard eingegebene Review-Passwort aus derselben Browser-Sitzung. Dadurch ist im Normalfall keine zweite Passwortabfrage nötig.

Die Arbeitsansicht nutzt die vorhandenen Writeback-Pfade:

- Apps-Script-Writeback für KI-/Sheet-Inbox-Fälle
- Submission-Review-Endpunkte für eingereichte Events/Aktivitäten
- `api/content-audit/update.php` für Content-Prüffälle
- `api/growth-backlog/update.php` für Backlog-Punkte

Die bestehende `/inbox/` bleibt als Fallback erhalten, ist aber nicht mehr der primäre Einstieg aus dem internen Dashboard.

`Details ansehen` zeigt einzelne Fälle nur zur Orientierung und Priorisierung. Die Buttons in `Für KI vorbereiten` öffnen diese Detailansicht direkt mit dem passenden Filter.

## Zeiträume

- Heute
- 7 Tage
- 28 Tage
- seit Referenzpunkt 2026-07-08
- seit Launch
- 6 Monate, sobald genug Historie vorhanden ist

## Zugriff

Die Seite liegt unter `/intern/`. Dashboard-Daten kommen aus `api/content-ops-dashboard.php`; konkrete Aufgabenfälle kommen aus `api/content-ops-cases.php`. Beide Endpunkte sind durch das Review-Passwort geschützt. Die integrierte Arbeitsansicht liegt unter `/intern/work.html`.
