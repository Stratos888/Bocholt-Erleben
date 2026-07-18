# Steuerzentrale – kanonischer Premium-Zielzustand

Stand: 2026-07-10  
Status: verbindlicher Produkt-, UX-, Daten- und E2E-Prozessvertrag

## 1. Zweck

Die Steuerzentrale ist die zentrale private Betreiberoberfläche von Bocholt erleben. Sie bündelt Aufmerksamkeit, Entscheidungen, geplante Arbeit, Live-Verwaltung und messbare Projektentwicklung, ohne eine zweite unabhängige Datenwelt neben den führenden Quellen aufzubauen.

Sie beantwortet zuerst:

1. Was benötigt jetzt Aufmerksamkeit?
2. Was muss geprüft, entschieden oder korrigiert werden?
3. Welche Arbeit ist bereits beschlossen oder vorgemerkt?
4. Welche Live-Inhalte müssen verändert werden?
5. Verbessert oder verschlechtert sich das Gesamtprojekt?
6. Welche Automatisierung ist gestört und welche fachliche Auswirkung hat das?

## 2. Gesamtprojektprinzipien

### 2.1 Führende Quellen bleiben führend

Events, Aktivitäten, Anbieter-Einreichungen, Content-Audit und Backlogs werden nicht in der Steuerzentrale dupliziert. Die Steuerzentrale liest, normalisiert und schreibt über eindeutige fachliche Writebacks in die jeweilige führende Quelle zurück.

### 2.2 Ein Sachverhalt, ein Arbeitsstatus

Ein Sachverhalt darf nicht gleichzeitig als unabhängiger Prüffall, Aufgabe, Backlogpunkt und Freigabe existieren. Bereiche sind Ansichten desselben Vorgangsmodells.

### 2.3 Objekt und Arbeit bleiben getrennt

Events, Aktivitäten und Anbieter sind fachliche Objekte. Prüffälle, Aufgaben, Backlogpunkte und Ideen sind Arbeitszustände mit optionalem Objektbezug.

### 2.4 Automatisierung vor Handarbeit

Reihenfolge:

1. sicher automatisch beheben,
2. sicher automatisch verwerfen oder verdichten,
3. nur echte Unsicherheit unter `Prüfen`,
4. entschiedene Arbeit unter `Arbeit`,
5. mögliche spätere Arbeit als Backlog oder Idee.

### 2.5 E2E-Veröffentlichungswirkung

Fachliche Änderung:

`Aktion -> Validierung -> führende Quelle -> Verlauf -> Datenaufbereitung/Deploy -> öffentliche Wirkung`

Ein Vorgang wird nicht als vollständig erledigt bewertet, solange ein notwendiger Writeback oder Veröffentlichungsschritt fehlgeschlagen ist.

### 2.6 Mobile first

- dauerhaft sichtbare Navigation,
- genau eine dominante Hauptaktion,
- technische Details nachrangig,
- sichere Touchziele,
- Fokus und Suchzustand bleiben erhalten,
- Desktop erhöht Dichte, ändert aber nicht die Prozesslogik.

## 3. Kanonische Arbeitszustände

### Prüffall

Noch ungeklärter, entscheidungsbedürftiger oder unmittelbar korrekturbedürftiger Sachverhalt aus einem Quellsystem.

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

`neu/erneut relevant -> Prüfen -> übernehmen / ablehnen / zurückstellen / Aufgabe erzeugen -> aus aktiver Queue verschwinden`

## 5. Verbindliche Hauptnavigation

1. **Übersicht**
2. **Prüfen**
3. **Arbeit**
4. **Verwaltung**
5. **Entwicklung**

Seltene Funktionen liegen im Header-Menü.

### Übersicht

Verdichtete Aufmerksamkeitszentrale:

- jetzt erforderlich,
- zu prüfen,
- nächste aktive Arbeit,
- Projektentwicklung und Risiken.

Keine vollständige Wiederholung aller Vorgänge.

### Prüfen

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

- Jetzt,
- Als Nächstes,
- Wartet,
- Blockiert,
- Backlog,
- Ideen,
- Archiv.

Standarddarstellung sind kompakte Zeilen; Volltext und Aktionen erscheinen erst nach dem Aufklappen.

### Verwaltung

Interne Objektverwaltung für Events und Aktivitäten:

- suchen und filtern,
- Status erkennen,
- öffnen und bearbeiten,
- Quelle und Aktualisierung sehen,
- speichern mit Writeback,
- Veröffentlichung anstoßen,
- öffentliche Wirkung bestätigen,
- Verlauf nachvollziehen.

Eine Such- oder Linkliste allein ist keine Verwaltung.

### Entwicklung

Verständliche Sicht auf:

- Content-Qualität,
- Automatisierungswirkung,
- SEO-Inhaltsbasis und technische SEO,
- Search-Console-Daten,
- Produktfortschritt,
- Regressionen und Risiken.

Nicht zulässig sind erfundene Trends, künstliche Gesamtscores ohne belastbare Gewichtung oder rohe technische Laufdaten ohne fachliche Einordnung.

## 6. Header-Menü

Seltene Funktionen:

- Anbieterbereich,
- Systemstatus,
- Deployment,
- Einstellungen, sobald implementiert,
- Abmelden,
- technische Diagnose nur nachrangig.

## 7. Verwaltung als verbindlicher E2E-Bereich

Eine Verwaltung ist nur fertig, wenn Speichern tatsächlich:

1. fachliche Eingaben validiert,
2. die führende Quelle aktualisiert,
3. den Verlauf ergänzt,
4. notwendige Datenaufbereitung oder Deploy auslöst,
5. die öffentliche Wirkung bestätigt oder einen wartenden beziehungsweise blockierten Vorgang erzeugt.

## 8. Entwicklung als Wirkungsnachweis

Die Steuerzentrale darf nur dann von Verbesserung oder Verschlechterung sprechen, wenn historische Vergleichswerte vorhanden sind.

Bis dahin sind zulässig:

- aktueller Basisstand,
- aktuelle Handlungsbedarfe,
- ausdrücklich gekennzeichnete Datenlücken.

Für einen vollständigen Entwicklungsnachweis sind erforderlich:

- gespeicherte Zeitreihen,
- Vergleichszeiträume,
- Qualitäts- und Fehlerquoten der Automatisierungen,
- technische SEO-Signale,
- Search-Console-Kennzahlen,
- erkennbare Regressionen,
- deduplizierte Überführung negativer Signale in `Arbeit`.

## 9. Backlog- und Projektintegration

Zu integrieren sind:

- bestehender Growth-/Acquisition-Backlog,
- manuelle Ideen,
- relevante offene Workpacks,
- kuratierte technische Schulden,
- geplante Content- und Produktverbesserungen.

Repo-Inhalte werden nicht ungefiltert gespiegelt. Nur deduplizierte, handlungsrelevante Punkte mit Nutzen oder Risiko und klarer Zuordnung werden übernommen.

## 10. Freigaberegel

Der Zielzustand ist erst erreicht, wenn:

- alle führenden Quellen korrekt angebunden sind,
- Event- und Aktivitätsverwaltung echte Bearbeitung und Veröffentlichungswirkung besitzen,
- Aufgaben, Backlog und Ideen als ein Lebenszyklus funktionieren,
- Such- und Fokuszustände stabil bleiben,
- Systemstatus fachlich statt technisch formuliert ist,
- Entwicklung echte Trends und Regressionen belegen kann,
- technische SEO und Search Console angebunden sind,
- alte Inbox für reguläre Arbeit nicht mehr erforderlich ist,
- automatisierte und reale Staging-Abnahmen bestanden sind.
