# Steuerzentrale – verbindliche Informationsarchitektur

Stand: 2026-07-10
Status: verbindlicher Navigations- und Bereichsvertrag

## 1. Leitentscheidung

Die Steuerzentrale ist die zentrale Betreiberanwendung für Aufmerksamkeit, Entscheidungen, geplante Arbeit, Live-Inhalte und messbare Projektentwicklung. Sie ist keine parallele fachliche Datenquelle.

## 2. Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

Seltene Funktionen liegen im Header-Menü und belegen keinen Haupttab.

## 3. Übersicht

Zweck: in wenigen Sekunden erkennen, ob Handlung nötig ist und ob sich das Gesamtprojekt stabil entwickelt.

Blöcke:

- Jetzt erforderlich
- Zu prüfen
- Arbeit
- Entwicklung

Regeln:

- keine vollständigen Vorgangskarten,
- keine doppelte Arbeitsliste,
- maximal zwei Beispiele pro verdichtetem Block,
- CTA öffnet den passenden Bereich,
- Normalzustand ruhig und fachlich formuliert.

## 4. Prüfen

Zweck: alle ungeklärten, entscheidungsbedürftigen oder direkt korrekturbedürftigen Quellfälle bearbeiten.

Typische Fälle:

- neue Inhalte,
- Qualitätsprüfungen,
- Anbieterentscheidungen,
- Freigaben,
- Quellen- und Beschreibungskorrekturen.

Mobil wird genau ein Fall fokussiert. Desktop nutzt Queue plus Detail. `Prüfen` ist bewusst von `Arbeit` getrennt: Hier fehlt noch eine Entscheidung; unter `Arbeit` ist die Notwendigkeit bereits entschieden.

## 5. Arbeit

Zweck: den Lebenszyklus bereits entschiedener menschlicher Arbeit abbilden.

Unteransichten:

- Jetzt
- Als Nächstes
- Wartet
- Blockiert
- Backlog
- Ideen
- Archiv

Standarddarstellung sind kompakte Zeilen mit Titel, Art und Kurzbeschreibung. Volltext und Aktionen erscheinen erst nach Aufklappen.

Neue Einträge werden über einen gemeinsamen Einstieg `+ Neu` als Aufgabe, Backlogpunkt oder Idee angelegt. Übergänge erzeugen keine Doppelkarte.

## 6. Verwaltung

Zweck: fachliche Live-Inhalte suchen, öffnen, bearbeiten und veröffentlichen.

### Veranstaltungen

- führende Quelle: Events-Sheet,
- bearbeitbare Stammdaten,
- fachliche Validierung,
- Speichern in der führenden Quelle,
- automatischer Deploy-Start,
- öffentliche Vorschau,
- klare Rückmeldung bei Teilfehlern.

### Aktivitäten

- führende Quelle: versionierte Datei `data/offers.json`,
- öffentliche Vorschau und Bestandsanzeige vorhanden,
- dauerhafte Bearbeitung erst mit abgesichertem Repo-Writeback,
- kein scheinbarer lokaler Dateischreibpfad.

Das Suchfeld bleibt beim Tippen bestehen; nur die Ergebnisliste wird aktualisiert.

## 7. Entwicklung

Zweck: verständlich zeigen, ob Content, Automatisierungen, SEO und Produktentwicklung das Gesamtprojekt verbessern oder verschlechtern.

Enthält:

- Content-Qualität,
- Automatisierungswirkung,
- SEO-Onpage-Signale,
- Search-Console-Datenlage,
- offene kuratierte Workpacks,
- Blockaden und Risiken.

Nicht zulässig:

- erfundene Trends,
- ein künstlicher Gesamtscore ohne belastbare Gewichtung,
- rohe Commitlisten,
- technische Laufdaten ohne fachliche Einordnung.

Search-Console-Kennzahlen werden erst angezeigt, wenn die Quelle tatsächlich angebunden ist. Bis dahin wird die Datenlücke ausdrücklich benannt.

## 8. Header-Menü

Enthält nur selten benötigte Funktionen:

- Anbieterbereich,
- Systemstatus,
- Deployment starten,
- Einstellungen, sobald implementiert,
- Abmelden,
- technische Diagnose nachrangig.

## 9. Systemstatus

Die Standardansicht zeigt:

- fachlichen Normal- oder Störungszustand,
- offene Prüfungen,
- aktive und blockierte Arbeit,
- tatsächliche Auswirkung.

Technische Werte wie `seen` und `upserted` bleiben eingeklappt.

## 10. Badges

- Prüfen: aktive entscheidungsbedürftige Fälle.
- Arbeit: aktive konkrete Aufgaben.
- Keine Badges für Ideen, normalen Backlog, Informationen oder erfolgreiche Läufe.

## 11. Rolle der Altansicht

`/inbox/` bleibt bis zur vollständigen Migration Fallback für noch nicht überführte Push-, Diagnose- und Sonderfunktionen. Sie ist kein Bestandteil der finalen Hauptnavigation.
