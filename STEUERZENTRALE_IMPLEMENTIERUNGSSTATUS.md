# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10

## Verbindliche Produktgrundlage

Der bisherige abstrakte Zieltext wird durch vier konkrete, gemeinsam verbindliche Produktverträge ergänzt:

- `STEUERZENTRALE_VORGANGSKATALOG.md`
- `STEUERZENTRALE_INFORMATIONARCHITEKTUR.md`
- `STEUERZENTRALE_SCREENVERTRAG.md`
- `STEUERZENTRALE_ABNAHMEMATRIX.md`

Diese Dokumente sind vor jedem weiteren UI-Patch maßgeblich. Ein Bereich gilt nur dann als umgesetzt, wenn alle zugehörigen Muss-Kriterien der Abnahmematrix bestanden sind.

## Tragfähige technische Grundlage

Bereits vorhanden und grundsätzlich weiterverwendbar:

- kanonisches MySQL-Datenmodell für Vorgänge und Verlauf
- zentrale Domainlogik für Typen, Status, Priorisierung und Übergänge
- geschützte APIs für Übersicht, Vorgänge, Details, Synchronisation und Aktionen
- Deduplizierung über eindeutige Quellreferenzen
- Quell-Writeback vor zentralem Abschluss
- Anbieter-Statusführung von Prüfung über Zahlung bis Veröffentlichung
- Content-Audit- und KI-/Sheet-Writebacks
- Zugriffsschutz
- Syntax- und Vertragsvalidierung

Diese technische Grundlage ist kein Nachweis für einen fertigen Produktzustand.

## Abgeschlossene Quellmigration

### KI-/Sheet-Inbox

- offene Kandidaten werden dedupliziert als zentrale Vorgänge übernommen
- Annahme verwendet den bestehenden fachlichen Event-Writeback
- Ablehnung schreibt Status und Grund in die führende Quelle
- Zurückstellen schreibt Quelle und zentrale Wiedervorlage

### Anbieter-Einreichungen

- Event- und Aktivitätseinreichungen werden aus MySQL synchronisiert
- `pending_review` führt zur Zahlungsfreigabe
- laufende Zahlung wird als wartender Vorgang geführt
- `paid` oder `in_review` öffnet denselben Vorgang erneut zur finalen Entscheidung
- finale Freigabe verwendet Approval-, Kontingent- und Veröffentlichungslogik

### Content-Audit

- nur wirklich entscheidungsbedürftige Fälle werden zentral angelegt
- reine Visual-Backlogs und automatisch lösbare Hinweise bleiben aus der Arbeitsqueue
- Verifizieren, Korrigieren, Ablehnen und Zurückstellen schreiben in die führende Quelle zurück

## Bewertung der derzeit sichtbaren UI

Die aktuell unter `/steuerzentrale/` sichtbare Oberfläche ist ein technischer Prototyp auf der neuen Datenbasis und **nicht** der führende Produktentwurf.

Nach der Staging-Sichtprüfung wurden folgende grundlegende Abweichungen festgestellt:

- Navigation ist ab bestimmten Breiten nicht dauerhaft sichtbar
- Übersicht repliziert vollständige Eingangskarten statt zu verdichten
- Bearbeitung zeigt eine überladene Kartenwand statt fokussierter Queue beziehungsweise Master-Detail
- interne englische Statuswerte sind sichtbar
- Hauptaktionen entsprechen teilweise nicht dem fachlichen nächsten Schritt
- `Inhalte` ist nur eine Linkliste und keine Verwaltung
- Aufgabenverwaltung ist unvollständig
- Ideenbereich besitzt keine Erfassung
- `Mehr` enthält noch keine vollständigen echten Funktionen

Daher ist die aktuelle sichtbare UI nicht abnahmefähig und nicht für einen Main-Merge vorgesehen.

## Verbindlicher Konzeptwechsel

Die endgültige Hauptnavigation lautet:

1. Übersicht
2. Bearbeiten
3. Aufgaben
4. Verwaltung
5. Mehr

Dabei gilt:

- `Bearbeiten` ist die fokussierte Arbeitsoberfläche für neue Inhalte, Änderungen, Qualitätsprüfungen, Anbieterfälle und Freigaben.
- `Verwaltung` ist eine echte Objektverwaltung für Events und Aktivitäten, keine Linkliste.
- Leere oder nur geplante Funktionen werden nicht als Hauptbereich veröffentlicht.
- Übersicht und Bearbeiten zeigen denselben Vorgang nicht als vollständige Doppelkarte.

## Nächste Umsetzungsgates

### Gate A – Backend-Gap-Abgleich

- Vorgangskatalog gegen bestehendes Datenmodell prüfen
- fehlende Aktionsarten, Statusfelder und Objektinformationen ergänzen
- Quellenpayloads so normalisieren, dass fallgerechte Hauptaktionen möglich sind

### Gate B – sichtbare Shell ersetzen

- dauerhaft sichtbare Navigation
- neue Bereichsbezeichnungen
- verdichtete Übersicht
- fokussierte mobile Bearbeitung
- Desktop-Master-Detail
- deutsche Statussprache

### Gate C – vollständige Funktionsbereiche

- Aufgaben anlegen und vollständig verwalten
- Event-/Aktivitätsverwaltung mit Suche, Status, Bearbeitung und Vorgangsbezug
- Ideen erfassen und in Aufgaben umwandeln
- Systemstatus und Archiv
- Growth-Backlog in Ideen/Aufgaben migrieren

### Gate D – automatisierte und visuelle Abnahme

- alle Muss-Kriterien aus `STEUERZENTRALE_ABNAHMEMATRIX.md`
- Pflicht-Viewports 360, 390, Samsung-S24-nah, Tablet und Desktop
- reale Staging-Szenarien mit Datenbank-, Sheet-, Apps-Script- und Submission-Writebacks

## Rolle der bisherigen `/inbox/`

Die alte Seite bleibt bis zum vollständigen Funktionsersatz als Fallback für technische Diagnose und noch nicht migrierte Nebenfunktionen erhalten. Sie wird erst weitergeleitet, wenn kein regulärer Arbeitsfall mehr darauf angewiesen ist.

## Freigabestatus

- Technische Vorgangs- und Writeback-Grundlage: **tragfähig, weiterzuverwenden**
- Aktuell sichtbare Steuerzentralen-UI: **nicht abnahmefähig, wird geschlossen ersetzt**
- Main-Merge: **nicht zulässig**
- Nächster Schritt: **Backend-Gap-Abgleich gegen den verbindlichen Vorgangskatalog, danach geschlossener UI-Ersatz**
