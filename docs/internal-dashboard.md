# Internes Dashboard

Ziel: Eine mobile interne Steuerzentrale für Bocholt erleben.

## Nutzerfrage

Das Dashboard beantwortet zuerst:

1. Greift der Prozess?
2. Wird die App besser?
3. Was muss als Nächstes getan werden?
4. Kann den Daten vertraut werden?
5. Welche konkreten Inbox-Fälle müssen entschieden werden?

## Struktur

- Heute: Gesamtstatus, nächste sinnvolle Aktion, kurze Einordnung.
- Aufgaben: zentrale Arbeitszentrale mit Überblick und integrierter Inbox-Bearbeitungsebene.
- Qualität: Content-Prüfung, Bild-/Quellen-/Inhaltsfälle und Prozesswirkung.
- Wachstum: SEO/Growth, KI-Suche und Monatsreview-Hinweise.
- System: technische Vertrauensbasis, Läufe, Datenalter und Diagnose.

## Aufgaben-Tab

Der Aufgaben-Tab ist zweistufig:

1. Überblick: priorisierte Aufgaben, Sammelgruppen und KI-sinnvolle Reviews.
2. Inbox: konkrete Einzelfälle aus `data/inbox.json` mit Filterung nach Offen, Quellen, Bilder, KI, Später, Erledigt und Alle.

Wenn offene Inbox-Elemente vorhanden sind, steht `Inbox-Elemente prüfen` ganz oben in der Aufgabenliste. Die Inbox ersetzt dadurch keine vorhandene Entscheidungslogik, sondern wird in der internen App sichtbar und prüfbar. Schreibaktionen werden erst angebunden, wenn die bestehende Inbox-Writeback-Logik sicher übernommen ist.

## Zeiträume

- Heute
- 7 Tage
- 28 Tage
- seit Referenzpunkt 2026-07-08
- seit Launch
- 6 Monate, sobald genug Historie vorhanden ist

## Zugriff

Die Seite liegt unter `/intern/`. Dashboard-Daten kommen aus `api/content-ops-dashboard.php`; Inbox-Einzelfälle kommen aus `api/content-ops-inbox.php`. Beide Endpunkte sind durch das Review-Passwort geschützt.
