# Steuerzentrale – binäre Abnahmematrix

Stand: 2026-07-10
Status: verbindliche Definition of Done

## 1. Bewertungsregel

Jedes Kriterium wird ausschließlich mit `BESTANDEN` oder `NICHT BESTANDEN` bewertet.

Ein Bereich gilt nur dann als fertig, wenn alle Muss-Kriterien bestanden sind. Ein technisch funktionierender Teilbereich mit offenen Muss-Kriterien ist kein abnahmefähiger Produktzustand.

## 2. Globale Shell und Navigation

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| NAV-01 | Navigation dauerhaft sichtbar | Hauptnavigation bei jeder Scrollposition sichtbar bleibt |
| NAV-02 | Mobile Fixierung | Bottom-Bar auf Mobil- und Tabletbreite am Viewport fixiert ist |
| NAV-03 | Safe Area | Inhalte und Navigation auf Geräten mit Safe Area nicht überdeckt werden |
| NAV-04 | Zielstruktur | Navigation exakt Übersicht, Bearbeiten, Aufgaben, Verwaltung, Mehr enthält |
| NAV-05 | Keine Platzhalter | Jeder sichtbare Hauptbereich eine echte Funktion besitzt |
| NAV-06 | Badges | Nur Bearbeiten und Aufgaben handlungsrelevante aktive Fälle zählen |
| NAV-07 | Touchziele | alle primären Navigationselemente mindestens 44 px bedienbare Fläche besitzen |
| NAV-08 | Desktop | Navigation auch auf Desktop dauerhaft erreichbar ist |

## 3. Übersicht

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| OVR-01 | Sofortverständlichkeit | innerhalb weniger Sekunden erkennbar ist, ob jetzt Handlung nötig ist |
| OVR-02 | Verdichtung | keine vollständige Wiederholung aller Bearbeiten-Karten erfolgt |
| OVR-03 | Jetzt erforderlich | nur dringende, überfällige, blockierende oder kritische Fälle enthält |
| OVR-04 | Neuer Eingang | Vorgänge nach Typ verdichtet zusammenfasst |
| OVR-05 | Als Nächstes | nächste Aufgaben, Wiedervorlagen und wartende relevante Fälle zeigt |
| OVR-06 | Zur Kenntnis | Informationen ohne Handlungsdruck ruhig verdichtet |
| OVR-07 | Systemzustand | Normalzustand knapp und nicht alarmistisch darstellt |
| OVR-08 | Keine Doppelzählung | derselbe Vorgang nicht mehrfach als vollständiges Element erscheint |
| OVR-09 | Mobile Above-the-fold | wichtigster Handlungsblock ohne Scrollen verständlich ist |

## 4. Bearbeiten

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| WRK-01 | Fachliche Filter | Neue Inhalte, Änderungen, Qualität, Anbieter, Freigaben unterscheidbar sind |
| WRK-02 | Fokus mobil | mobil genau ein Vorgang fokussiert bearbeitet wird |
| WRK-03 | Master-Detail Desktop | Desktop kompakte Queue und Detailansicht nutzt |
| WRK-04 | Keine Kartenwand | keine mehrspaltige Darstellung vollständiger Entscheidungskarten existiert |
| WRK-05 | Hauptaktion | primäre Aktion exakt dem fachlich nächsten Schritt entspricht |
| WRK-06 | Statussprache | keine englischen internen Statuswerte sichtbar sind |
| WRK-07 | Kontext | Grund, Objekt, Quelle und relevante Daten verständlich sichtbar sind |
| WRK-08 | Technische Details | IDs, Auditcodes und Rohdaten standardmäßig eingeklappt sind |
| WRK-09 | Navigation | Vorher/Weiter beziehungsweise nächste Auswahl zuverlässig funktioniert |
| WRK-10 | Aktionsergebnis | nach erfolgreicher Aktion der nächste passende Fall erscheint |
| WRK-11 | Filtererhalt | Filter- und Auswahlzustand nach Aktion erhalten bleiben |
| WRK-12 | Ablehnung | Ablehnungsgrund verlangt und Wirkung klar bestätigt wird |
| WRK-13 | Zurückstellen | Fall bis Wiedervorlage aus normaler Arbeitsmenge verschwindet |
| WRK-14 | Quellkonsistenz | zentraler Abschluss erst nach erfolgreichem Quell-Writeback erfolgt |
| WRK-15 | Keine Scheinf­reigabe | korrekturbedürftige Fälle nicht mit allgemeinem Freigeben abgeschlossen werden |

## 5. Aufgaben

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| TSK-01 | Manuelle Erfassung | Aufgabe mit Titel und nächstem Schritt angelegt werden kann |
| TSK-02 | Umwandlung | Eingang ohne Doppelkarte in Aufgabe überführt werden kann |
| TSK-03 | Gruppen | Jetzt, Als Nächstes, Wartet und Blockiert vorhanden sind |
| TSK-04 | Fälligkeit | Fälligkeit verständlich dargestellt und bearbeitbar ist |
| TSK-05 | Wartegrund | wartende Aufgabe sichtbar benennt, worauf gewartet wird |
| TSK-06 | Blockadegrund | blockierte Aufgabe Grund und nächsten Entblockungsschritt zeigt |
| TSK-07 | Statusübergänge | Starten, Warten, Blockieren, Zurückstellen und Erledigen funktionieren |
| TSK-08 | Objektbezug | Aufgabe mit Event, Aktivität oder Anbieterfall verknüpft werden kann |
| TSK-09 | Keine falsche Aktion | Status prüfen nur erscheint, wenn echte Quellprüfung erfolgt |
| TSK-10 | Aktive Menge | erledigte Aufgaben keine aktive Ansicht oder Badge belasten |

## 6. Verwaltung

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| ADM-01 | Suche | Events und Aktivitäten gesucht werden können |
| ADM-02 | Filter | nach Typ, Publikationsstatus und offenem Handlungsbedarf gefiltert werden kann |
| ADM-03 | Status | Publikationsstatus in der Liste erkennbar ist |
| ADM-04 | Vorschau | öffentliche Darstellung direkt geöffnet werden kann |
| ADM-05 | Bearbeitung | fachliche Stammdaten gezielt bearbeitet werden können |
| ADM-06 | Quelle | Quelle und letzte relevante Aktualisierung sichtbar sind |
| ADM-07 | Vorgangsbezug | offene zentrale Vorgänge am Objekt sichtbar sind |
| ADM-08 | Änderung/Absage | relevante Änderung oder Absage nachvollziehbar durchgeführt werden kann |
| ADM-09 | Verlauf | relevante Änderungen nachvollziehbar sind |
| ADM-10 | Keine Scheinverwaltung | Bereich nicht nur Links auf öffentliche Seiten enthält |

## 7. Ideen und Mehr

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| MOR-01 | Idee erfassen | neue Idee mit sehr wenigen Feldern gespeichert werden kann |
| MOR-02 | Ideenstatus | Parken, Verwerfen und Bearbeiten funktionieren |
| MOR-03 | Umwandlung | Idee bewusst in Aufgabe überführt werden kann |
| MOR-04 | Keine Badgebelastung | Ideen keine roten Hauptbadges erzeugen |
| MOR-05 | Systemstatus | fachliche Auswirkung und nächste Aktion verständlich zeigt |
| MOR-06 | Archiv | erledigte und abgelehnte Vorgänge suchbar sind |
| MOR-07 | Anbieterzugang | separater Anbieterbereich erreichbar ist |
| MOR-08 | Abmelden | Sitzungszugang zuverlässig entfernt wird |
| MOR-09 | Keine leeren Punkte | nur tatsächlich implementierte Funktionen angezeigt werden |

## 8. Daten- und Prozesskonsistenz

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| DAT-01 | Eindeutigkeit | pro Quellsachverhalt höchstens ein aktiver Vorgang existiert |
| DAT-02 | Kein Wiederöffnen | erledigte/abgelehnte Fälle durch Sync nicht wieder aktiv werden |
| DAT-03 | Lokaler Status | in Arbeit, wartet, blockiert und aktive Wiedervorlage beim Sync erhalten bleiben |
| DAT-04 | Zahlungsverlauf | derselbe Submission-Vorgang von Prüfung über Warten bis Veröffentlichung geführt wird |
| DAT-05 | Verlauf | jeder relevante Übergang protokolliert ist |
| DAT-06 | Atomare Wirkung | Quellfehler zentralen Abschluss verhindern |
| DAT-07 | Keine Informationskarten | Routineerfolge nicht als einzelne aktive Vorgänge gespeichert werden |

## 9. Sprache und Barrierearmut

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| UX-01 | Fachsprache | sichtbare Begriffe verständlich deutsch und konsistent sind |
| UX-02 | Farbe nicht allein | Status nicht ausschließlich über Farbe vermittelt wird |
| UX-03 | Tastatur | zentrale Funktionen per Tastatur erreichbar sind |
| UX-04 | Fokus | sichtbarer Fokus vorhanden ist |
| UX-05 | Dialoge | Dialoge fokussiert, schließbar und mobil nutzbar sind |
| UX-06 | Fehlermeldungen | betroffenen Bereich und sinnvolle nächste Aktion benennen |
| UX-07 | Rückmeldung | jede Aktion klare Ergebnisrückmeldung liefert |

## 10. Responsive Abnahme

Pflicht-Viewports:

- 360 px
- 390 px
- Samsung-S24-nahe Breite
- Tablet 768–900 px
- Desktop ab 1280 px

| ID | Muss-Kriterium | BESTANDEN, wenn |
|---|---|---|
| RSP-01 | Kein Überlauf | keine horizontale Scrollfläche durch UI entsteht |
| RSP-02 | Navigation | Navigation in allen Pflicht-Viewports sichtbar bleibt |
| RSP-03 | Hauptaktion | primäre Aktion ohne verdeckten Inhalt erreichbar ist |
| RSP-04 | Mobile Fokus | mobil keine parallelen vollständigen Vorgänge konkurrieren |
| RSP-05 | Desktop Nutzung | Desktop verfügbare Breite sinnvoll für Master-Detail verwendet |

## 11. Automatisierte Prüfungen

Vor Staging-Abnahme müssen automatisiert bestanden sein:

- PHP-Syntax
- JavaScript-Syntax
- CSS-Governance
- API-Verträge
- Statusübergänge
- Deduplizierung
- Quell-Writeback-Fehlerfälle
- Sichtbare Statusübersetzungen
- Navigation-Fixierung in den Pflicht-Viewports
- keine vollständigen Vorgangskarten auf der Übersicht

## 12. Staging-Abnahmeszenarien

1. Qualitätsfall öffnen, korrigieren und abschließen.
2. Eventkandidat übernehmen.
3. Fall mit Grund ablehnen.
4. Fall sieben Tage zurückstellen und Neuladen prüfen.
5. Anbieterfall zur Zahlung freigeben.
6. Wartenden Anbieterfall prüfen.
7. Bezahlte Einreichung veröffentlichen.
8. Manuelle Aufgabe anlegen und erledigen.
9. Aufgabe blockieren und entblocken.
10. Event in Verwaltung suchen, Vorschau öffnen und bearbeiten.
11. Idee erfassen und in Aufgabe umwandeln.
12. Archivierten Vorgang finden.
13. Navigation auf langem Inhalt in allen Pflicht-Viewports prüfen.

## 13. Freigaberegel

Ein Main-Merge ist nur zulässig, wenn:

- alle Muss-Kriterien bestanden sind,
- alle automatisierten Prüfungen grün sind,
- alle Staging-Abnahmeszenarien erfolgreich waren,
- keine Altansicht mehr für reguläre tägliche Arbeit benötigt wird,
- Dokumentation und tatsächliche Oberfläche übereinstimmen.
