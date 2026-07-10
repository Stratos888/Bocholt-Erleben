# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10

## Verbindlicher Zielbezug

Das Produktziel bleibt der in `STEUERZENTRALE_ZIELBILD.md` dokumentierte Premium-Zustand. Bestehende Prozesse werden nicht aus Bestandsschutz erhalten, sondern nur dann weiterverwendet, wenn ihre fachliche Logik weiterhin sinnvoll und zuverlässig ist.

## Umgesetzte tragende Schicht

- kanonisches MySQL-Datenmodell für Vorgänge und Verlauf
- idempotente Schema-Initialisierung beim ersten geschützten Aufruf
- zentrale Domainlogik für Typen, Status, Priorisierung und Übergänge
- geschützte APIs für Übersicht, Vorgänge, Details, Synchronisation und Aktionen
- mobile-first Steuerzentralen-Shell unter `/steuerzentrale/`
- gemeinsame Navigation für Übersicht, Eingang, Inhalte, Aufgaben und Mehr
- zentrale Badges nur für echte aktive Eingänge und Aufgaben
- automatisierte Syntax- und Vertragsvalidierung über `.github/workflows/control-center-validation.yml`

## Abgeschlossene Quellmigration

### KI-/Sheet-Inbox

- offene Kandidaten werden aus dem generierten Inbox-Feed dedupliziert als zentrale Vorgänge übernommen
- Annahme verwendet weiterhin den bestehenden gehärteten Apps-Script-Prozess einschließlich fachlicher Eventübernahme
- Ablehnung schreibt Status und festen Ablehnungsgrund in die führende Inboxquelle
- Zurückstellen schreibt sowohl in die Quelle als auch in die zentrale Wiedervorlage
- ein zentraler Vorgang wird erst nach erfolgreichem Quell-Writeback abgeschlossen

### Anbieter-Einreichungen

- Event- und Aktivitätseinreichungen werden direkt aus MySQL als zentrale Vorgänge synchronisiert
- `pending_review` führt zur fachlichen Zahlungsfreigabe über den bestehenden Endpunkt
- anschließend wechselt derselbe Vorgang in `wartet`
- `payment_released` und `checkout_started` erzeugen keinen falschen neuen Handlungsbedarf
- nach `paid` oder `in_review` wird derselbe Vorgang automatisch wieder zur priorisierten Entscheidung
- finale Freigabe verwendet den bestehenden gehärteten Approval-Endpunkt einschließlich Kontingentbuchung und Veröffentlichungslogik
- Ablehnung verwendet den bestehenden Reject-Endpunkt

### Content-Audit

- nur kritische und wirklich entscheidungsbedürftige Fälle werden als zentrale Vorgänge angelegt
- reine Visual-Backlogs und automatisch bearbeitbare Hinweise bleiben aus der täglichen Queue
- Verifizieren, Ablehnen und Zurückstellen schreiben in den führenden Audit-Tab zurück
- Eventfelder und offizielle Quellen können direkt in der Steuerzentrale korrigiert werden
- Korrekturen verwenden dieselben Feld-, URL- und Datumsgrenzen wie der bestehende Audit-Prozess
- der zentrale Vorgang wird erst nach erfolgreichem Sheet-Writeback abgeschlossen

## Konsistenzregeln

- pro Quellsachverhalt existiert höchstens ein zentraler Vorgang
- erledigte oder abgelehnte Vorgänge werden durch einen späteren Sync nicht wieder geöffnet
- lokale Zustände `in Bearbeitung`, `wartet`, `blockiert` und aktive Wiedervorlagen werden durch Quellsynchronisation nicht überschrieben
- bestätigte Zahlungen dürfen einen wartenden Submission-Vorgang gezielt wieder in eine Entscheidung überführen
- Quell-Writeback erfolgt vor dem zentralen Abschluss; bei Quellfehler bleibt der Vorgang offen
- alle zentralen Übergänge werden in `control_case_events` protokolliert

## Rolle der bisherigen `/inbox/`

Die alte Seite ist nicht mehr die führende tägliche Arbeitsoberfläche für:

- KI-/Sheet-Kandidaten
- Anbieter-Einreichungen
- Content-Audit-Fälle

Sie bleibt vorläufig als technische Altansicht für folgende noch nicht überführte Nebenfunktionen erreichbar:

- Push-Registrierung und Push-Test
- Deploy-Sonderaktion
- Growth-/Acquisition-Backlog
- technische Diagnose und Vergleich während der Staging-Abnahme

Nach Überführung oder bewusster Neuplatzierung dieser Nebenfunktionen wird `/inbox/` auf `/steuerzentrale/` weitergeleitet.

## Noch erforderlich für den vollständigen Gesamtzielzustand

1. Staging-Laufzeitabnahme der neuen APIs mit realer Datenbank-, Sheet- und Apps-Script-Verbindung
2. Mobile Abnahme auf dem Samsung S24 und mindestens 360/390 Pixel Breite
3. Content-Objektansichten für Events und Aktivitäten
4. manuelle Aufgaben- und Ideenerfassung in der Steuerzentrale
5. Growth-Backlog in den kanonischen Ideen-/Aufgabenprozess migrieren
6. Push und Systemstatus sinnvoll unter `Mehr` beziehungsweise `Systemstatus` platzieren
7. alte `/inbox/` nach erfolgreicher Gesamtmigration weiterleiten
8. kontrollierter Main-Merge nach bestandener Staging-Abnahme

## Freigabestatus dieses Workpacks

Die fachliche Migration von Event-Inbox, Anbieter-Einreichungen und Content-Audit in die zentrale Vorgangslogik ist im Repo umgesetzt. Der Code ist durch einen eigenen CI-Syntax- und Vertragscheck abgesichert. Eine Produktionsfreigabe erfolgt erst nach realer Staging-Laufzeitabnahme der externen Writebacks und der mobilen Oberfläche.
