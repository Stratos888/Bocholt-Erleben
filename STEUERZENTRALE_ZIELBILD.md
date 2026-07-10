# Steuerzentrale – kanonischer Premium-Zielzustand

Stand: 2026-07-10
Status: verbindlicher Produkt-, UX-, Daten- und E2E-Prozessvertrag

## 1. Zweck

Die Steuerzentrale ist die zentrale private Betreiberoberfläche von Bocholt erleben. Sie bündelt Aufmerksamkeit und menschliche Arbeit, ohne eine zweite unabhängige Datenwelt neben den führenden Quellen aufzubauen.

Sie beantwortet zuerst:

1. Was benötigt jetzt Aufmerksamkeit?
2. Welche konkrete Entscheidung oder Handlung ist erforderlich?
3. Was ist bereits entschieden und als Arbeit vorgemerkt?
4. Welche fachlichen Objekte sind betroffen?
5. Welche Automatisierung ist gestört und welche Auswirkung hat das?

## 2. Gesamtprojektprinzipien

### 2.1 Führende Quellen bleiben führend

Events, Aktivitäten, Anbieter-Einreichungen, Content-Audit und Backlogs werden nicht in der Steuerzentrale dupliziert. Die Steuerzentrale liest, normalisiert und schreibt über eindeutige fachliche Writebacks in die jeweilige führende Quelle zurück.

### 2.2 Ein Sachverhalt, ein Arbeitsstatus

Ein Sachverhalt darf nicht gleichzeitig als unabhängiger Eingang, Aufgabe, Backlogpunkt und Freigabe existieren. Bereiche sind Ansichten desselben Vorgangsmodells.

### 2.3 Objekt und Arbeit bleiben getrennt

Events, Aktivitäten und Anbieter sind fachliche Objekte. Bearbeiten-Fälle, Aufgaben, Backlogpunkte und Ideen sind Arbeitszustände mit optionalem Objektbezug.

### 2.4 Automatisierung vor Handarbeit

Reihenfolge:

1. sicher automatisch beheben,
2. sicher automatisch verwerfen oder verdichten,
3. nur echte Unsicherheit unter `Bearbeiten`,
4. entschiedene Arbeit unter `Arbeit`,
5. mögliche spätere Arbeit als Backlog oder Idee.

### 2.5 E2E-Veröffentlichungswirkung

Fachliche Änderung:

`Aktion -> Validierung -> führende Quelle -> Verlauf -> Datenaufbereitung/Deploy -> öffentliche Wirkung`

Ein Vorgang wird nicht als erledigt markiert, solange ein notwendiger Writeback oder Veröffentlichungsschritt fehlgeschlagen ist.

### 2.6 Mobile first

- dauerhaft sichtbare Navigation,
- genau eine dominante Hauptaktion,
- technische Details nachrangig,
- sichere Touchziele,
- Fokus und Suchzustand bleiben erhalten,
- Desktop erhöht Dichte, ändert aber nicht die Prozesslogik.

## 3. Kanonische Arbeitszustände

### Bearbeiten-Fall

Noch ungeklärter oder entscheidungsbedürftiger Sachverhalt aus einem Quellsystem.

Beispiele:

- neuer Eventkandidat,
- Qualitätskorrektur,
- Anbieterprüfung,
- Freigabe,
- relevante Änderung oder Absage.

### Aufgabe

Entschiedene konkrete Arbeit mit eindeutigem nächsten Schritt.

### Backlog

Bewusst vorgemerkte Arbeit ohne aktive Einplanung. Backlogpunkte dürfen priorisiert, bearbeitet, verworfen oder in Aufgaben überführt werden.

### Idee

Ungeprüfter möglicher Nutzen. Keine normale Fälligkeit und keine Belastung der täglichen Aufgabenanzeige.

### Information

Relevante Meldung ohne Handlungsbedarf. Routineerfolge werden verdichtet.

## 4. Arbeitslebenszyklus

`Idee -> Backlog -> Aufgabe -> erledigt`

Direkte Übergänge sind möglich. Es entstehen keine Kopien. Ein Punkt wechselt seinen führenden Zustand.

Quellfälle folgen:

`neu/erneut relevant -> Bearbeiten -> übernehmen / ablehnen / zurückstellen / Aufgabe erzeugen -> aus aktiver Queue verschwinden`

## 5. Verbindliche Hauptnavigation

1. **Übersicht**
2. **Bearbeiten**
3. **Arbeit**
4. **Verwaltung**
5. **Menü**

### Übersicht

Verdichtete Aufmerksamkeitszentrale:

- jetzt erforderlich,
- zu bearbeiten,
- nächste aktive Arbeit,
- wartet/blockiert,
- relevante Information,
- Systemauswirkung.

Keine vollständige Wiederholung aller Vorgänge.

### Bearbeiten

Fokussierte Queue für:

- neue Inhalte,
- Änderungen,
- Qualität,
- Anbieter,
- Freigaben,
- Sonstige.

Mobil ein Vorgang; Desktop Queue plus Detail.

### Arbeit

Gemeinsamer Arbeitsbereich mit Unteransichten:

- Aktiv/Jetzt,
- Als Nächstes,
- Wartet,
- Blockiert,
- Backlog,
- Ideen,
- Archiv.

### Verwaltung

Interne Objektverwaltung für Events und Aktivitäten:

- suchen und filtern,
- Status erkennen,
- öffnen und bearbeiten,
- Quelle und Aktualisierung sehen,
- offene Vorgänge verknüpfen,
- speichern mit Writeback,
- Veröffentlichung anstoßen und Ergebnis sehen,
- Verlauf nachvollziehen.

### Menü

Seltene Funktionen:

- Anbieterbereich,
- Systemstatus,
- Einstellungen,
- Abmelden,
- technische Diagnose nur nachrangig.

## 6. Priorisierung der Übersicht

### Jetzt erforderlich

Nur überfällige, heute notwendige, blockierende, kritische oder dringende Fälle.

### Zu bearbeiten

Verdichtete Anzahl und Typverteilung der aktiven Bearbeiten-Fälle.

### Arbeit

Nächste Aufgabe, wartet/blockiert und relevante Fälligkeiten.

### Zur Kenntnis

Wenige verdichtete Informationen ohne Handlungsdruck.

### Systemzustand

Normalzustand:

`Alle relevanten Quellen sind synchronisiert. Keine bekannte Störung mit Auswirkung.`

Rohwerte und Logs nur unter technischen Details.

## 7. Verwaltung als verbindlicher E2E-Bereich

Eine Verwaltung ist nur fertig, wenn Speichern tatsächlich:

1. fachliche Eingaben validiert,
2. die führende Quelle aktualisiert,
3. den zentralen Verlauf ergänzt,
4. notwendige Datenaufbereitung/Deploy auslöst oder zuverlässig anstößt,
5. die öffentliche Wirkung bestätigt oder einen wartenden/blockierten Vorgang erzeugt.

Eine Such- oder Linkliste allein ist keine Verwaltung.

## 8. Backlog- und Projektintegration

Zu integrieren sind:

- bestehender Growth-/Acquisition-Backlog,
- manuelle Ideen,
- relevante offene Workpacks,
- kuratierte technische Schulden,
- geplante Content- und Produktverbesserungen.

Repo-Inhalte werden nicht ungefiltert gespiegelt. Nur deduplizierte, handlungsrelevante Punkte mit Nutzen/Risiko und klarer Zuordnung werden übernommen.

## 9. Freigaberegel

Der Zielzustand ist erst erreicht, wenn:

- alle führenden Quellen korrekt angebunden sind,
- Verwaltung echte Bearbeitung und Veröffentlichungswirkung besitzt,
- Aufgaben, Backlog und Ideen als ein Lebenszyklus funktionieren,
- Such- und Fokuszustände stabil bleiben,
- Systemstatus fachlich statt technisch formuliert ist,
- alte Inbox für reguläre Arbeit nicht mehr erforderlich ist,
- alle automatisierten und realen Staging-Abnahmen bestanden sind.
