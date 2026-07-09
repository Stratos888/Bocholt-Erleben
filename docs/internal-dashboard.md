# Internes Dashboard

Ziel: Eine mobile interne Steuerzentrale für Bocholt erleben.

## Nutzerfrage

Das Dashboard beantwortet zuerst:

1. Greift der Prozess?
2. Wird die App besser?
3. Was muss als Nächstes getan werden?
4. Kann den Daten vertraut werden?
5. Welche konkreten Fälle müssen geprüft oder entschieden werden?

## Struktur

- Heute: Gesamtstatus, nächste sinnvolle Aktion, kurze Einordnung.
- Aufgaben: zentrale Arbeitszentrale mit Überblick und integriertem Fälle-Modus.
- Qualität: Content-Prüfung, Bild-/Quellen-/Inhaltsfälle und Prozesswirkung.
- Wachstum: SEO/Growth, KI-Suche und Monatsreview-Hinweise.
- System: technische Vertrauensbasis, Läufe, Datenalter und Diagnose.

## Aufgaben-Tab

Der Aufgaben-Tab ist zweistufig:

1. Überblick: priorisierte Aufgaben, Sammelgruppen und KI-sinnvolle Reviews.
2. Fälle: konkrete Einzelfälle aus Inbox und Content-Ops-Findings.

Der Fälle-Modus bündelt:

- Inbox-Fälle aus `data/inbox.json`
- Content-Prüffälle aus dem letzten `content_quality_audit`-Run
- Quellenfälle
- Bild-/Visual-Fälle
- Beobachtungsfälle
- erledigte Fälle zur Kontrolle

Wenn offene Inbox-Elemente vorhanden sind, steht `Inbox-Elemente prüfen` ganz oben in der Aufgabenliste. Wenn keine Inbox-Elemente offen sind, aber Content-Prüffälle vorhanden sind, steht `Prüffälle ansehen` oben. Schreibaktionen werden erst angebunden, wenn die bestehende Writeback-Logik sicher übernommen ist.

## Zeiträume

- Heute
- 7 Tage
- 28 Tage
- seit Referenzpunkt 2026-07-08
- seit Launch
- 6 Monate, sobald genug Historie vorhanden ist

## Zugriff

Die Seite liegt unter `/intern/`. Dashboard-Daten kommen aus `api/content-ops-dashboard.php`; konkrete Aufgabenfälle kommen aus `api/content-ops-cases.php`. Beide Endpunkte sind durch das Review-Passwort geschützt.
