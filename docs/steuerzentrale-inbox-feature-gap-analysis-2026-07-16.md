# Feature-Gap-Analyse: ursprüngliche Inbox → aktuelle Steuerzentrale

Stand: 2026-07-16

## Vergleichsbasis

- Ursprüngliche Inbox: `inbox/index.html` im Commit `e2943d88603b930c3120329255ab5e0a80846684`.
- Aktuelle Inbox: `Prüfen` in der Steuerzentrale mit `js/control-center/review-render.js`, `review-actions.js` und den serverseitigen Control-Center-Verträgen.
- Führende Quelle für neue Eventkandidaten bleibt der Google-Sheet-Tab `Inbox`.

Die Analyse bewertet nicht nur sichtbare Buttons, sondern den vollständigen Entscheidungsprozess: Eingangsdaten, Prüfbarkeit, Bearbeitung, Rückschreiben, Fehlerverhalten, Nachweis und Lernwirkung.

## Gesamturteil

Die aktuelle Steuerzentrale ist bei Architektur, Sicherheit und Prozessverbindlichkeit deutlich stärker als die ursprüngliche Inbox. Sie besitzt stabile Identitäten, typisierte Entscheidungen, idempotente Operationen, serverseitige Entscheidungsgates, konfliktgeschützte Entwürfe und strukturierte Lernbeobachtungen.

Die ursprüngliche Inbox war dagegen in mehreren fachlichen Detailansichten umfangreicher. Besonders Quellen-/Eventlink-Trennung, Ablaufwarnungen, Dedupe-Evidenz, Visual-Status, Content-Audit-Evidenz und Anbieter-/Zahlungsdetails sind in der aktuellen Hauptansicht noch nicht vollständig auf gleichem Niveau sichtbar.

## Funktionsvergleich

| Bereich | Ursprüngliche Inbox | Aktuelle Steuerzentrale | Bewertung |
|---|---|---|---|
| Zentrale Bedienoberfläche | Eigenständige Inbox mit eigener Anmeldung und mehreren internen Modi | Gemeinsame Steuerzentrale mit Übersicht, Prüfen, Backlog, Verwaltung und Entwicklung | **Verbessert** |
| Fokus auf einen Fall | Eine Karte gleichzeitig, vor/zurück beziehungsweise nächster Fall | Ein fokussierter Fall plus Queue, Zurück/Weiter und Kategorien | **Mindestens gleichwertig** |
| Pflichtdaten eines Events | Datum, Zeitraum, Uhrzeit, Ort, Adresse, Kategorie, Quelle, Beschreibung und weitere Metadaten sichtbar | Termin, Uhrzeit, Ort, Adresse, Kategorie, Quelle, Ticket, Visual-Key, Motiv, Dublette und finale Beschreibung | **Weitgehend gleichwertig** |
| Serverseitige Entscheidungsreife | Hauptsächlich UI-Hinweise und clientseitige Schutzlogik | Verbindliches serverseitiges Gate für Pflichtfelder, URLs, Mehrtagesevents, Quelle, Visual und harte Dublette | **Deutlich verbessert** |
| Eventlink und Originalquelle | Event-/Einreichungslink und Originalquelle wurden getrennt angezeigt und geöffnet | Neue Eventkandidaten liefern derzeit im Hauptpfad nur einen zusammengefassten Link `Offizielle Quelle` | **Funktionslücke** |
| Termin-/Zeitdetails | Mehrtageszeiträume, Zeitdetails und Schedule-Texte wurden ausführlich dargestellt | Ein normalisiertes Zeitfeld beziehungsweise Begründung bei fehlender Uhrzeit | **Teilweise Lücke** |
| Abgelaufene Termine | Explizite Warnung, Übernahmebutton deaktiviert und passender Ablehnungsgrund vorgeschlagen | Kein eigener sichtbarer Ablaufstatus im aktuellen Eventvertrag | **Funktionslücke, hohe Priorität** |
| Dedupe-Prüfung | Match-Score, vorhandene Event-ID und Dedupe-/Importhinweise konnten sichtbar sein | Harte Dublette und matched_event_id werden dargestellt und blockieren die Übernahme | **Technisch stärker, Evidenzdarstellung schwächer** |
| Visual-Prüfung | Visual-Key, verständliches Label, Motiv und Asset-Status | Visual-Key und Motiv | **Teilweise Lücke** |
| Risiko- und Prüfhinweise | Review-Risikoflags, Kurationshinweise und Importhinweise direkt in der Karte | Serverseitige Blocker und Warnungen; weitere Rohdaten erst über `Quelldaten` | **Teilweise Lücke** |
| Ablehnungsgrund | Pflichtauswahl mit fachlichen Gründen; Freitext teilweise direkt als Sheet-Grund | Pflichtauswahl über kanonische `decision_class`, optionale Notiz, serverseitige Validierung | **Verbessert** |
| Dublettenentscheidung | Textgrund `Doppelt / bereits abgedeckt` | Typisierte Klasse `duplicate` mit Label `Dublette` | **Verbessert** |
| Rückschreiben der Ablehnung | Apps-Script-Writeback ohne belastbare Rückleseprüfung | Ab diesem Workpack: kanonischer Status `verworfen`, menschenlesbarer Grund, stabile Zeilenauflösung und Rückleseprüfung vor lokalem Abschluss | **Deutlich verbessert** |
| Wiederholtes Absenden | Kein stabiler clientseitiger Operationsvertrag | Stabile `operation_id`, Payload-Hash, Replay und Konflikterkennung | **Verbessert** |
| Bearbeiten vor Übernahme | In Teilen vorhanden; Content-Audit-Formulare konnten Eventfelder korrigieren | Vollständiger Bearbeitungsdialog für Eventkandidaten, Entwurf bleibt bei Konflikten erhalten | **Verbessert** |
| Zurückstellen | `Später` verschob den Fall nur in der aktuellen Sitzung | Persistierte Wiedervorlage mit Datum und typisierter Entscheidung | **Deutlich verbessert** |
| Content-Audit | Umfangreiche Evidenz, Checklisten, empfohlene Quelle, Visual-Fit und Arbeitspaket-Routing | Aktueller Text, Vorschlag, Korrektur, Details und Quellenlink vorhanden; mehrere Evidenzfelder nur in Rohdaten oder gar nicht priorisiert | **Relevante Funktionslücke** |
| Anbieter-/Einreichungsprüfung | Zahlungsstatus, Zahlungszeitpunkte, Ortsfreigabe, Öffnungszeiten und Bildangaben konnten sichtbar sein | Status, Typ, Datum, Zeit, Ort und Organisation; Zahlungs- und Betriebsdetails sind in der Hauptkarte reduziert | **Relevante Funktionslücke** |
| Backlog | Als eigener Inbox-Modus mit Arbeitsaktionen integriert | Bewusst als kanonische Informations- und Reihenfolgeansicht im Tab `Backlog` getrennt | **Zielgemäß verbessert; nicht zurückführen** |
| Push-Benachrichtigungen | Registrierung und Testnachricht waren vorhanden | Keine gleichwertige Push-Bedienung in der Steuerzentrale nachgewiesen | **Optionale Funktionslücke** |
| Debug-/Systemmenü | Eigenes Systemmenü und technische Testfunktionen | Technische Zustände zentral unter `Entwicklung`, keine umfangreiche Debug-Bedienung in `Prüfen` | **Bewusste Vereinfachung** |
| Lernwirkung | Semantische Entscheidungen wurden teilweise gemessen | Typisierte Entscheidungen, strukturierte Korrekturbeobachtungen und keine automatische Regelaktivierung durch Einzeländerung | **Verbessert** |

## Sofort behobener P0-Gap: Ablehnung bis zur führenden Quelle

Der Live-Fall `2. Bocholter Vereinsmesse` zeigte:

- Entscheidungsklasse `duplicate` war fachlich korrekt.
- Die führende Inbox blieb auf `review`.
- `ablehnungsgrund` blieb leer.
- Der lokale Fall durfte deshalb nicht als erfolgreich abgeschlossen gelten.

Der korrigierte Vertrag lautet:

1. Aktuelle Zeile über stabile Identität auflösen.
2. Für Ablehnungen ausschließlich `status=verworfen` schreiben.
3. Bei fehlender Notiz das menschenlesbare Klassenlabel speichern, bei `duplicate` also `Dublette`.
4. Apps-Script-Erfolg allein reicht nicht.
5. Die führende Inbox unmittelbar erneut lesen.
6. Identität, Status und Ablehnungsgrund verifizieren.
7. Den lokalen Control-Center-Fall erst danach auf `rejected` setzen.
8. Bei fehlender Bestätigung Operation als fehlgeschlagen protokollieren und den Fall offen lassen.

Der frühere falsche Wert `verwerfen` bleibt nur als Import-Kompatibilitätsalias geschlossen, darf aber nicht mehr neu geschrieben werden.

## Noch offene priorisierte Gaps

### P1 – für Premium-Parität erforderlich

1. **Eventlink und Originalquelle getrennt anzeigen**
   - Event-/Zielseite und offizielle Prüfquelle dürfen nicht zu einem Link zusammenfallen.

2. **Abgelaufene Termine serverseitig blockieren und sichtbar erklären**
   - Enddatum vor heute als eigener Blocker.
   - Übernahme deaktivieren.
   - geeignete typisierte Ablehnung anbieten.

3. **Dedupe-Evidenz erweitern**
   - matched_event_id, Match-Art, Score beziehungsweise Begründung und direkter Link zum vorhandenen Event.

4. **Visual-Status vollständig zeigen**
   - verständliches Visual-Key-Label, Motivrolle und Asset-Status zusätzlich zu Key und Motiv.

5. **Content-Audit-Evidenz entscheidungsorientiert zurückbringen**
   - Kurzbefund, geprüfte/nicht bestätigte Felder, empfohlene Quelle und klare Checkliste.

6. **Anbieter-/Zahlungsdetails vervollständigen**
   - Zahlungsphase, relevante Zeitpunkte, Ortsfreigabe, Öffnungszeiten und Bildstatus, soweit für die Entscheidung erforderlich.

### P2 – nützlich, aber nicht releasekritisch

- Push-Benachrichtigungen für neue Fälle.
- Export beziehungsweise Kopieren fachlich getrennter Content-Audit-Arbeitspakete.
- Erweiterte technische Diagnoseansicht für Betreiber, ohne die normale Prüfansicht zu überladen.

## Funktionen, die bewusst nicht zurückkehren sollen

- separate Inbox-Anmeldung,
- parallele Hauptmodi innerhalb der Inbox,
- Backlog als Prüf- oder Arbeitsqueue,
- rein lokales `Später` ohne gespeicherte Wiedervorlage,
- sichtbare Debug- und Setup-Funktionen im normalen Betreiberfluss,
- direkte Statusänderung ohne typisierte Entscheidung und serverseitige Validierung.

## Verbindlicher Zielzustand

Die aktuelle Steuerzentrale bleibt die einzige Bedienoberfläche. Fachlich nützliche Details der ursprünglichen Inbox werden gezielt in den bestehenden Review-Vertrag zurückgeführt. Alte Architektur, doppelte Navigation und schwächere Prozesslogik werden nicht reaktiviert.
