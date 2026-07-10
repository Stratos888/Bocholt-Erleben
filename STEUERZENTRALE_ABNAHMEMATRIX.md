# Steuerzentrale – binäre Abnahmematrix

Stand: 2026-07-10
Status: verbindliche Definition of Done

## 1. Regel

Jedes Muss-Kriterium wird nur mit `BESTANDEN` oder `NICHT BESTANDEN` bewertet. Ein Main-Merge ist ausgeschlossen, solange ein Muss-Kriterium offen ist.

## 2. Navigation und Shell

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| NAV-01 | Zielstruktur | exakt Übersicht, Bearbeiten, Arbeit, Verwaltung, Menü sichtbar sind |
| NAV-02 | Dauerhaft erreichbar | Navigation bei jeder Scrollposition sichtbar bleibt |
| NAV-03 | Responsive | Mobil, Tablet und Desktop ohne Überdeckung funktionieren |
| NAV-04 | Keine Platzhalter | jeder Hauptbereich echte Funktionen besitzt |
| NAV-05 | Badges | nur aktive Bearbeiten-Fälle und fällige/aktive Arbeit zählen |
| NAV-06 | Safe Area/Touch | Safe Area berücksichtigt und Touchziele mindestens 44 px sind |

## 3. Übersicht

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| OVR-01 | Verdichtung | keine vollständigen Arbeitskarten wiederholt werden |
| OVR-02 | Priorisierung | dringend, Bearbeiten und Arbeit fachlich korrekt getrennt sind |
| OVR-03 | Keine Doppelzählung | ein Vorgang nur einmal führend gezählt wird |
| OVR-04 | Systemwirkung | nur Störungen mit fachlicher Auswirkung auffallen |
| OVR-05 | Direkter Einstieg | jeder Handlungsblock den richtigen Bereich/Filter öffnet |

## 4. Bearbeiten

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| WRK-01 | Fachliche Filter | Neue Inhalte, Änderungen, Qualität, Anbieter und Freigaben unterscheidbar sind |
| WRK-02 | Fokus mobil | genau ein Vorgang bearbeitet wird |
| WRK-03 | Master-Detail | Desktop Queue plus Detail verwendet |
| WRK-04 | Hauptaktion | Aktion exakt dem fachlichen nächsten Schritt entspricht |
| WRK-05 | Kontext | Problem, Objekt, Quelle und relevante Daten verständlich sind |
| WRK-06 | Statussprache | keine internen englischen Statuswerte sichtbar sind |
| WRK-07 | Quellkonsistenz | Abschluss erst nach erfolgreichem Quell-Writeback erfolgt |
| WRK-08 | Nächster Fall | nach Erfolg der nächste passende Fall geöffnet wird |
| WRK-09 | Zustanderhalt | Filter, Auswahl und Queue-Scrollposition erhalten bleiben |
| WRK-10 | Ablehnung/Zurückstellen | Grund beziehungsweise Wiedervorlage korrekt verarbeitet werden |

## 5. Arbeit

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| JOB-01 | Gemeinsamer Bereich | Aufgaben, Backlog, Ideen und Archiv unter Arbeit liegen |
| JOB-02 | Aufgabe anlegen | Titel und nächster Schritt gespeichert werden können |
| JOB-03 | Aufgabenstatus | aktiv, wartet, blockiert, zurückgestellt und erledigt funktionieren |
| JOB-04 | Fälligkeit | Fälligkeit bearbeitbar und verständlich ist |
| JOB-05 | Backlogmigration | Growth-/Acquisition-Backlog vollständig und dedupliziert übernommen ist |
| JOB-06 | Repo-Kuratierung | relevante Workpacks/Schulden nur kuratiert und dedupliziert übernommen werden |
| JOB-07 | Idee erfassen | Idee mit wenigen Feldern angelegt werden kann |
| JOB-08 | Zustandsübergänge | Idee -> Backlog -> Aufgabe ohne Kopien funktioniert |
| JOB-09 | Archiv | erledigte, verworfene und abgeschlossene Punkte auffindbar sind |
| JOB-10 | Badge-Regel | Ideen und normaler Backlog keine rote Hauptbadge erzeugen |

## 6. Verwaltung

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| ADM-01 | Suche | Events und Aktivitäten gesucht werden können |
| ADM-02 | Fokus | Suchfeld beim Tippen Fokus und Cursor behält |
| ADM-03 | Filter | Typ, Publikationsstatus und Handlungsbedarf filterbar sind |
| ADM-04 | Status/Quelle | Status, Quelle und letzte Aktualisierung sichtbar sind |
| ADM-05 | Vorgangsbezug | offene zentrale Vorgänge am Objekt sichtbar sind |
| ADM-06 | Bearbeitung | relevante fachliche Felder editierbar sind |
| ADM-07 | Validierung | fehlerhafte Eingaben Speichern verhindern und am Feld erklärt werden |
| ADM-08 | Führender Writeback | Speichern die tatsächliche führende Quelle aktualisiert |
| ADM-09 | Veröffentlichung | notwendige Datengenerierung/Deploy automatisch angestoßen wird |
| ADM-10 | Wirkung | Erfolg öffentlich bestätigt oder Fehler als wartet/blockiert sichtbar wird |
| ADM-11 | Verlauf | relevante Änderungen protokolliert sind |
| ADM-12 | Kein Datenverlust | Fehler Editor und Eingaben erhalten |

## 7. Menü und Systemstatus

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| MNU-01 | Nur seltene Funktionen | Anbieter, System, Einstellungen und Abmelden enthalten sind |
| MNU-02 | Keine Arbeit versteckt | Ideen und Backlog nicht im Menü liegen |
| SYS-01 | Alltagssprache | Normalzustand ohne technische Rohwerte formuliert ist |
| SYS-02 | Auswirkung | Störung betroffene Funktion und Auswirkung benennt |
| SYS-03 | Nächste Aktion | bei Handlungsbedarf genau eine sinnvolle Aktion existiert |
| SYS-04 | Technische Details | `seen`, `upserted`, JSON und Logs nur eingeklappt erscheinen |

## 8. Daten- und E2E-Konsistenz

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| DAT-01 | Eindeutigkeit | pro Quellsachverhalt höchstens ein aktiver Vorgang existiert |
| DAT-02 | Kein Wiederöffnen | erledigte/abgelehnte Fälle durch Sync nicht erneut aktiv werden |
| DAT-03 | Lokaler Zustand | wartet, blockiert und Wiedervorlage beim Sync erhalten bleiben |
| DAT-04 | Anbieterlebenszyklus | derselbe Vorgang Prüfung, Zahlung und Veröffentlichung durchläuft |
| DAT-05 | Verlauf | alle relevanten Übergänge protokolliert sind |
| DAT-06 | Fehlerverhalten | Quell-, Build- oder Deployfehler Abschluss verhindern |
| DAT-07 | Führende Quelle | Steuerzentrale keine unabhängige Fachkopie pflegt |
| DAT-08 | Öffentliche Wirkung | fachliche Änderung bis zur öffentlichen Darstellung nachweisbar ist |

## 9. UX und Responsive

| ID | Kriterium | BESTANDEN, wenn |
|---|---|---|
| UX-01 | Fachsprache | Begriffe verständlich deutsch sind |
| UX-02 | Fokus | sichtbarer Tastaturfokus vorhanden ist |
| UX-03 | Dialoge | mobil nutzbar und schließbar sind |
| UX-04 | Fehler | konkrete nächste Aktion benannt wird |
| UX-05 | Rückmeldung | Wirkung jeder Aktion klar bestätigt wird |
| RSP-01 | Pflichtbreiten | 360, 390, S24-nah, 768–900 und >=1280 px funktionieren |
| RSP-02 | Kein Überlauf | keine unbeabsichtigte horizontale Scrollfläche entsteht |
| RSP-03 | Hauptaktion | nicht von Navigation überdeckt wird |

## 10. Automatisierte Prüfungen

Vor Staging-Abnahme:

- PHP- und JavaScript-Syntax,
- CSS-Governance,
- Produktvertragsprüfung,
- API-Verträge,
- Statusübergänge,
- Deduplizierung,
- Quell-Writeback-Fehlerfälle,
- Suchfokus-Test,
- Backlog-Migrations- und Übergangstests,
- Verwaltungs-Writeback und Deploy-Fehlerfall.

## 11. Reale Staging-Szenarien

1. Qualitätsfall korrigieren und öffentliche Wirkung prüfen.
2. Eventkandidat übernehmen.
3. Anbieterfall über Zahlung bis Veröffentlichung führen.
4. Fall ablehnen und zurückstellen.
5. Aufgabe anlegen, warten, blockieren und erledigen.
6. Growth-Backlogpunkt finden und als Aufgabe starten.
7. Idee anlegen, in Backlog und Aufgabe überführen.
8. Event suchen, mehrere Buchstaben tippen und stabilen Fokus prüfen.
9. Event/ Aktivität bearbeiten, speichern und öffentliche Änderung bestätigen.
10. simulierten Writeback-/Deployfehler als offenen wartet/blockiert-Zustand prüfen.
11. Systemstatus normal und gestört prüfen.
12. alte Inbox für keinen regulären Arbeitsfall mehr benötigen.

## 12. Freigabe

Main-Merge nur, wenn alle Muss-Kriterien, automatisierten Prüfungen und Staging-Szenarien bestanden sind und Dokumentation, führende Quellen und tatsächliche Oberfläche übereinstimmen.
