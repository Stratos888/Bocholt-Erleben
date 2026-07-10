# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10

## Bereits umgesetzt

- kanonisches MySQL-Datenmodell für Vorgänge und Verlauf
- idempotente Schema-Initialisierung beim ersten geschützten Aufruf
- zentrale Domainlogik für Typen, Status, Priorisierung und Übergänge
- geschützte APIs für Übersicht, Vorgänge und Aktionen
- Quellenadapter für generierten Inbox-Feed und entscheidungsrelevante Content-Audit-Fälle
- mobile-first Steuerzentralen-Shell unter `/steuerzentrale/`
- gemeinsame Navigation für Übersicht, Eingang, Inhalte, Aufgaben und Mehr
- zentrale Badges nur für aktive Eingänge und Aufgaben
- Schutz vor fachlich unvollständigem Writeback für bestehende Inbox-/Audit-Quellfälle

## Bewusste Sicherheitsgrenze

Quellfälle aus `inbox_feed` und `content_audit` werden bereits zentral sichtbar und priorisiert. Ihre fachliche Bearbeitung bleibt bis zur vollständigen Writeback-Migration in der bestehenden `/inbox/`-Arbeitsansicht.

Grund: Die bestehende Inbox führt bei Annahme nicht nur einen Statuswechsel aus, sondern validiert und schreibt Eventdaten, prüft Duplikate und aktualisiert mehrere fachliche Felder. Ein generisches Schließen des zentralen Vorgangs ohne diesen Writeback wäre fachlich falsch. Der neue Action-Endpunkt blockiert deshalb solche Aktionen ausdrücklich.

## Noch erforderlich für den vollständigen Zielzustand

1. bestehende Inbox-Annahme-/Ablehnungslogik als zentrale serverseitige Writeback-Domainfunktion extrahieren
2. Content-Audit-Korrektur- und Verifikationsaktionen in dieselbe Transaktion beziehungsweise kompensierbare Orchestrierung integrieren
3. nach erfolgreichem Writeback den zentralen Vorgang abschließen und Verlauf schreiben
4. lokale Wiedervorlagen beim erneuten Quellsync respektieren
5. Content-Objektansichten für Events und Aktivitäten ergänzen
6. Ideen-Erfassung und manuelle Aufgabenerstellung in der neuen Oberfläche vervollständigen
7. alte `/inbox/`-UI nach vollständiger Funktionsmigration entfernen oder auf die Steuerzentrale weiterleiten
8. automatisierte PHP-, API-, Übergangs- und Mobile-E2E-Tests ergänzen
9. Gesamtvalidierung auf Staging und danach kontrollierter Main-Merge

## Freigabestatus

Die neue Schicht ist ein tragfähiges Foundation-Workpack, aber noch nicht als vollständiger Ersatz der bestehenden Inbox freigegeben. Die Sicherheitsblockade verhindert, dass Quellfälle nur lokal als erledigt markiert werden, während das fachliche Quellsystem unverändert bleibt.
