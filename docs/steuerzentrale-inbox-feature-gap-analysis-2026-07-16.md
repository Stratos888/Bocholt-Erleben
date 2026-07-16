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

Die Analyse des CityArt-Falls ergänzt ein übergeordnetes P0-Gap: Die aktuelle Steuerzentrale erkennt technische Blocker, führt den Nutzer aber noch nicht für jede Ausnahme durch eine vollständige Aktion bis zum fertigen, verifizierten Ergebnis. Die Premium-Lücke ist deshalb nicht nur fehlende Information, sondern fehlende **Aktions- und Folgeprozessschließung**.

## Funktionsvergleich

| Bereich | Ursprüngliche Inbox | Aktuelle Steuerzentrale | Bewertung |
|---|---|---|---|
| Zentrale Bedienoberfläche | Eigenständige Inbox mit eigener Anmeldung und mehreren internen Modi | Gemeinsame Steuerzentrale mit Übersicht, Prüfen, Backlog, Verwaltung und Entwicklung | **Verbessert** |
| Fokus auf einen Fall | Eine Karte gleichzeitig, vor/zurück beziehungsweise nächster Fall | Ein fokussierter Fall plus Queue, Zurück/Weiter und Kategorien | **Mindestens gleichwertig** |
| Pflichtdaten eines Events | Datum, Zeitraum, Uhrzeit, Ort, Adresse, Kategorie, Quelle, Beschreibung und weitere Metadaten sichtbar | Termin, Uhrzeit, Ort, Adresse, Kategorie, Quelle, Ticket, Visual-Key, Motiv, Dublette und finale Beschreibung | **Weitgehend gleichwertig** |
| Serverseitige Entscheidungsreife | Hauptsächlich UI-Hinweise und clientseitige Schutzlogik | Verbindliches serverseitiges Gate für Pflichtfelder, URLs, Mehrtagesevents, Quelle, Visual und harte Dublette | **Deutlich verbessert** |
| Aufgabenorientierte Ausnahmeprüfung | Teilweise direkte Fachaktionen, aber ohne einheitlichen End-to-End-Vertrag | Blocker werden angezeigt; häufig öffnet anschließend ein vollständiger Editor statt einer fokussierten Aufgabe | **P0-Funktionslücke** |
| Feldbezogene Prüfevidenz | Risiko-, Import- und Prüfhinweise teilweise sichtbar | Kein einheitlicher Status, ob ein Wert offiziell verifiziert, nur formatiert, vorgeschlagen oder menschlich zu prüfen ist | **P0-Funktionslücke** |
| Eventlink und Originalquelle | Event-/Einreichungslink und Originalquelle wurden getrennt angezeigt und geöffnet | Neue Eventkandidaten liefern derzeit im Hauptpfad nur einen zusammengefassten Link `Offizielle Quelle` | **Funktionslücke** |
| Termin-/Zeitdetails | Mehrtageszeiträume, Zeitdetails und Schedule-Texte wurden ausführlich dargestellt | Ein normalisiertes Zeitfeld beziehungsweise Begründung bei fehlender Uhrzeit | **Teilweise Lücke** |
| Zeitstatus und Folgeaktion | Unterschiedliche Zeitinformationen sichtbar, aber nicht durchgehend typisiert | Beliebiger Erklärungstext kann eine fehlende Uhrzeit technisch entschärfen | **P0-Funktionslücke** |
| Abgelaufene Termine | Explizite Warnung, Übernahmebutton deaktiviert und passender Ablehnungsgrund vorgeschlagen | Kein eigener sichtbarer Ablaufstatus im aktuellen Eventvertrag | **Funktionslücke, hohe Priorität** |
| Dedupe-Prüfung | Match-Score, vorhandene Event-ID und Dedupe-/Importhinweise konnten sichtbar sein | Harte Dublette und matched_event_id werden dargestellt und blockieren die Übernahme | **Technisch stärker, Evidenzdarstellung schwächer** |
| Visual-Prüfung | Visual-Key, verständliches Label, Motiv und Asset-Status | Visual-Key und Motiv; kein verbindlich bestätigtes konkretes Asset | **P0-Funktionslücke** |
| Visual-Folgeprozess | Bildarbeit war als eigener Prozess vorhanden | Visual-Gap kann technisch erkannt werden, aber noch ohne vollständige Rückführung in dieselbe Prüfansicht | **P0-Funktionslücke** |
| Risiko- und Prüfhinweise | Review-Risikoflags, Kurationshinweise und Importhinweise direkt in der Karte | Serverseitige Blocker und Warnungen; weitere Rohdaten erst über `Quelldaten` | **Teilweise Lücke** |
| Ablehnungsgrund | Pflichtauswahl mit fachlichen Gründen; Freitext teilweise direkt als Sheet-Grund | Pflichtauswahl über kanonische `decision_class`, optionale Notiz, serverseitige Validierung | **Verbessert** |
| Dublettenentscheidung | Textgrund `Doppelt / bereits abgedeckt` | Typisierte Klasse `duplicate` mit Label `Dublette` | **Verbessert** |
| Rückschreiben der Ablehnung | Apps-Script-Writeback ohne belastbare Rückleseprüfung | Kanonischer Status `verworfen`, menschenlesbarer Grund, stabile Zeilenauflösung, Rückleseprüfung und lokale Reconciliation | **Deutlich verbessert und auf Staging real bestätigt** |
| Wiederholtes Absenden | Kein stabiler clientseitiger Operationsvertrag | Stabile `operation_id`, Payload-Hash, Replay und Konflikterkennung | **Verbessert** |
| Bearbeiten vor Übernahme | In Teilen vorhanden; Content-Audit-Formulare konnten Eventfelder korrigieren | Vollständiger Bearbeitungsdialog für Eventkandidaten, Entwurf bleibt bei Konflikten erhalten | **Technisch verbessert, fachlich noch zu breit** |
| Zurückstellen | `Später` verschob den Fall nur in der aktuellen Sitzung | Persistierte Wiedervorlage mit Datum und typisierter Entscheidung | **Deutlich verbessert** |
| Content-Audit | Umfangreiche Evidenz, Checklisten, empfohlene Quelle, Visual-Fit und Arbeitspaket-Routing | Aktueller Text, Vorschlag, Korrektur, Details und Quellenlink vorhanden; mehrere Evidenzfelder nur in Rohdaten oder gar nicht priorisiert | **Relevante Funktionslücke** |
| Anbieter-/Einreichungsprüfung | Zahlungsstatus, Zahlungszeitpunkte, Ortsfreigabe, Öffnungszeiten und Bildangaben konnten sichtbar sein | Status, Typ, Datum, Zeit, Ort und Organisation; Zahlungs- und Betriebsdetails sind in der Hauptkarte reduziert | **Relevante Funktionslücke** |
| Backlog | Als eigener Inbox-Modus mit Arbeitsaktionen integriert | Bewusst als kanonische Informations- und Reihenfolgeansicht im Tab `Backlog` getrennt | **Zielgemäß verbessert; nicht zurückführen** |
| Push-Benachrichtigungen | Registrierung und Testnachricht waren vorhanden | Keine gleichwertige Push-Bedienung in der Steuerzentrale nachgewiesen | **Optionale Funktionslücke** |
| Debug-/Systemmenü | Eigenes Systemmenü und technische Testfunktionen | Technische Zustände zentral unter `Entwicklung`, keine umfangreiche Debug-Bedienung in `Prüfen` | **Bewusste Vereinfachung** |
| Lernwirkung | Semantische Entscheidungen wurden teilweise gemessen | Typisierte Entscheidungen, strukturierte Korrekturbeobachtungen und keine automatische Regelaktivierung durch Einzeländerung | **Verbessert** |

## Behobener P0-Gap: Ablehnung bis zur führenden Quelle und zurück zur UI

Der Live-Fall `2. Bocholter Vereinsmesse` zeigte zunächst:

- Entscheidungsklasse `duplicate` war fachlich korrekt.
- Die führende Inbox blieb auf `review`.
- `ablehnungsgrund` blieb leer.
- Nach später erfolgreichem Quell-Writeback blieb der lokale Fall zunächst sichtbar.

Der vollständige korrigierte Vertrag lautet:

1. Aktuelle Zeile über stabile Identität auflösen.
2. Für Ablehnungen ausschließlich `status=verworfen` schreiben.
3. Bei fehlender Notiz das menschenlesbare Klassenlabel speichern, bei `duplicate` also `Dublette`.
4. Status und Grund gemeinsam und zeitlich begrenzt schreiben.
5. Die führende Inbox unmittelbar erneut lesen.
6. Identität, Status und Ablehnungsgrund verifizieren.
7. Den lokalen Control-Center-Fall auf `rejected` setzen oder anhand der terminalen Quelle reconciliieren.
8. Danach ausschließlich aktive Fälle laden.
9. Das Dialogfenster erst nach konsistentem Reload schließen.
10. Bei fehlender Bestätigung Operation als fehlgeschlagen protokollieren und den Fall offen lassen.

Der frühere falsche Wert `verwerfen` bleibt nur als Import-Kompatibilitätsalias geschlossen, darf aber nicht mehr neu geschrieben werden.

Der reale Staging-Nachweis ist erfolgt: Die bereits terminal gespeicherte Vereinsmesse verschwand nach dem Laden des Staging-Stands `bba370093f83bd2190ead0fb2f8f605c46c047f5` automatisch aus der offenen Prüfliste.

## Offener P0-Gap: ausnahmebasierte Prüfung mit vollständiger Folgeaktion

Der CityArt-Fall zeigt die nächste systemische Lücke:

- Die UI meldet technische Felder wie `visual_motif`.
- Sie erklärt nicht ausreichend, welche konkrete Fachaktion erforderlich ist.
- Sie bietet nicht direkt an der Aufgabe die nötige Eingabe oder Auswahl.
- Sie unterscheidet nicht zwischen fachlich gelösten Zeitstatus und lediglich noch nicht veröffentlichten Angaben.
- Sie kann ein Motiv bestätigen lassen, bindet aber nicht zwingend das konkret angezeigte Asset.
- Ein fehlendes Asset erzeugt noch keinen vollständig geschlossenen Prozess bis zur späteren Bildbestätigung.
- Bereits automatisch geprüfte Felder sind nicht so gekennzeichnet, dass der Nutzer sie verlässlich überspringen kann.

Der neue verbindliche Grundsatz lautet:

> Jeder Befund benötigt Erkennung, verständlichen Nutzertext, Evidenz, direkte Aktion, Persistenz, Folgeprozess, Rückleseprüfung, Fallzustand und sichtbaren Endzustand.

Vor der nächsten Codeänderung sind sämtliche möglichen Befundklassen eines Eventkandidaten zu inventarisieren. Dazu gehören mindestens:

- Identität und Titel,
- Datum, Zeitraum, Wiederholung, Ablauf und Absage,
- Uhrzeit und typisierter Zeitstatus,
- Stadt, lokaler Bezug und Reichweite,
- Veranstaltungsort, Treffpunkt und Adresse,
- Kategorie und Zielgruppe,
- Quelle, Quellenqualität und Quellenkonflikte,
- Beschreibung und belegte Aussagen,
- Ticket, Anmeldung, Preis und Zugang,
- Dubletten und Serienabgrenzung,
- Visual-Key, Motiv, konkretes Asset, Rechte und Visual-Gap.

Die kanonische Detailbeschreibung und Startanweisung für den Folgechat steht in:

`docs/steuerzentrale-naechstes-workpack-ausnahmebasierte-eventpruefung-2026-07-16.md`

## Noch offene priorisierte Gaps

### P1 – innerhalb oder nach dem P0-Workpack zu schließen

1. **Eventlink und Originalquelle getrennt anzeigen**
   - Event-/Zielseite und offizielle Prüfquelle dürfen nicht zu einem Link zusammenfallen.

2. **Abgelaufene Termine serverseitig blockieren und sichtbar erklären**
   - Enddatum vor heute als eigener Blocker.
   - Übernahme deaktivieren.
   - geeignete typisierte Ablehnung anbieten.

3. **Dedupe-Evidenz erweitern**
   - matched_event_id, Match-Art, Score beziehungsweise Begründung und direkter Link zum vorhandenen Event.

4. **Visual-Status vollständig zeigen**
   - verständliches Visual-Key-Label, Motivrolle, Asset-Status, konkrete Vorschau und bindende Asset-ID.

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
- direkte Statusänderung ohne typisierte Entscheidung und serverseitige Validierung,
- vollständige manuelle Neuprüfung jedes Feldes trotz vorhandener belastbarer Evidenz,
- manuelle Bilderzeugung durch den Betreiber.

## Verbindlicher Zielzustand

Die aktuelle Steuerzentrale bleibt die einzige Bedienoberfläche. Fachlich nützliche Details der ursprünglichen Inbox werden gezielt in den bestehenden Review-Vertrag zurückgeführt. Alte Architektur, doppelte Navigation und schwächere Prozesslogik werden nicht reaktiviert.

Die Prüfansicht arbeitet künftig ausnahmebasiert:

1. belastbar geprüfte Felder werden gekennzeichnet und müssen nicht erneut einzeln geprüft werden,
2. nur ungelöste Ausnahmen werden als direkte Aufgaben angezeigt,
3. jede Aufgabe löst eine vollständig definierte und verifizierte Folgeaktion aus,
4. erst nach Abschluss aller Aufgaben wird das Event entscheidungsreif,
5. die abschließende Übernahme bleibt eine bewusste Gesamtentscheidung.
