# Steuerzentrale – kanonischer Premium-Zielzustand

Stand: 2026-07-10
Status: verbindlicher Produkt-, UX- und Prozessvertrag für die private Verwaltungsoberfläche von Bocholt erleben

## 1. Zweck

Die Steuerzentrale ist die zentrale, mobile-first Arbeitsoberfläche des Betreibers von Bocholt erleben. Sie ist weder ein technisches Monitoring-Dashboard noch eine Sammlung gleichrangiger Verwaltungsseiten.

Sie muss jederzeit zuerst beantworten:

1. Was benötigt jetzt meine Aufmerksamkeit?
2. Welche konkrete Entscheidung oder Handlung ist als Nächstes erforderlich?
3. Was hat das System bereits selbst erledigt?
4. Was ist nur eine Information und erzeugt keine Arbeit?
5. An welchem fachlichen Objekt – Event, Aktivität, Anbieter, Bild oder anderem Inhalt – wird gearbeitet?

Der Betreiber soll nicht mehrere Bereiche öffnen müssen, um herauszufinden, ob irgendwo etwas Wichtiges liegt. Die Steuerzentrale reduziert Aufmerksamkeit, Suchaufwand und Doppelpflege.

## 2. Verbindliche Grundprinzipien

### 2.1 Aufmerksamkeit statt Datenmenge

Die Startansicht zeigt nicht möglichst viele Daten, sondern priorisiert ausschließlich nach tatsächlichem Handlungsbedarf.

Prominent sind nur:

- überfällige oder heute notwendige Aufgaben,
- blockierte fachliche Vorgänge,
- entscheidungsreife Freigaben,
- neue ungeklärte Eingangsfälle,
- fehlgeschlagene Automatisierungen mit echter fachlicher Auswirkung.

Reine Kennzahlen, historische Mengen, erfolgreiche Routineabläufe und technische Details sind nachrangig.

### 2.2 Ein Vorgang, ein führender Status

Jeder fachliche Vorgang besitzt genau einen führenden Datensatz und genau einen führenden Arbeitsstatus.

Andere Bereiche dürfen denselben Vorgang als gefilterte Ansicht zeigen, aber keine unabhängige Kopie erzeugen.

Unzulässig ist insbesondere, denselben Sachverhalt gleichzeitig getrennt als:

- Eingangskarte,
- Aufgabe,
- Freigabe,
- Content-Prüffall

zu führen.

### 2.3 Fachliches Objekt und Arbeitsvorgang bleiben getrennt

Events, Aktivitäten, Anbieter, Bilder und redaktionelle Texte sind fachliche Objekte.

Eingänge, Aufgaben, Entscheidungen, Informationen und Ideen sind Arbeitsvorgänge oder Aufmerksamkeitstypen, die mit diesen Objekten verknüpft sein können.

Beispiel:

`Event -> Qualitätsproblem -> notwendige Entscheidung/Aufgabe -> korrigierter Event`

Nicht zulässig:

`Eventkopie im Inhalt + unabhängige Inboxkopie + unabhängige Aufgabenkopie`.

### 2.4 Menschliche Arbeit nur bei echtem Bedarf

Für jeden automatisch erkannten Sachverhalt gilt diese Reihenfolge:

1. Kann das System ihn sicher selbst beheben? Dann automatisch erledigen und protokollieren.
2. Kann das System ihn sicher als unkritisch einstufen? Dann aggregiert dokumentieren.
3. Ist eine menschliche Entscheidung erforderlich? Dann in den Eingang beziehungsweise die Entscheidungsansicht.
4. Ist die Entscheidung bereits getroffen und nur noch konkrete Arbeit nötig? Dann als Aufgabe führen.
5. Ist keine zeitnahe Handlung erforderlich? Dann als Information, Idee oder Backlog führen.

Technische Guards, Visual-Backlogs, normale Audit-Hinweise und erfolgreiche Routineprozesse dürfen keine künstliche tägliche Aufgabenlast erzeugen.

### 2.5 Mobile first

Die Steuerzentrale wird zuerst für die regelmäßige Nutzung auf einem Smartphone entworfen.

Mobile first bedeutet:

- wichtigste Entscheidung und Hauptaktion sind ohne horizontales Scrollen sichtbar,
- Karten sind knapp, scannbar und nicht mit technischen Details überladen,
- eine Karte besitzt höchstens eine visuell dominante Hauptaktion,
- sekundäre Aktionen liegen nachrangig oder in einem Menü,
- Status, Dringlichkeit und nächster Schritt sind ohne Öffnen technischer Detaildaten verständlich,
- Touch-Ziele sind sicher bedienbar,
- Formulare vermeiden unnötige Pflichtfelder und Mehrfachpflege,
- Zurück-Navigation, nächster/vorheriger Fall und Erhalt von Filter-/Scrollzustand funktionieren verlässlich,
- leere Zustände erklären knapp, dass aktuell nichts zu tun ist,
- Desktop erweitert Übersicht und Dichte, verändert aber nicht Begriffe oder Prozesslogik.

## 3. Kanonische Aufmerksamkeitstypen

Jeder Arbeitsvorgang gehört zu genau einem der folgenden Typen.

### 3.1 Eingang

Bedeutung: Ein neuer oder erneut aufgekommener Sachverhalt ist noch nicht abschließend eingeordnet oder entschieden.

Typische Beispiele:

- neuer KI-Eventkandidat,
- manuell eingereichter Event,
- neue Anbieteränderung,
- Content-Fall mit echter menschlicher Unsicherheit,
- neue Idee, die zunächst bewertet werden soll.

Lebenszyklus:

`neu -> prüfen -> übernehmen / ablehnen / zurückstellen / Aufgabe erzeugen -> aus Eingang verschwinden`

Ein Eingang darf nicht dauerhaft zum Aufbewahrungsort werden.

### 3.2 Aufgabe

Bedeutung: Die Notwendigkeit ist bereits entschieden; ein konkreter nächster Arbeitsschritt steht fest.

Pflichtbestandteile:

- klarer Titel als Handlung,
- nachvollziehbarer Kontext,
- nächster Schritt,
- Status,
- optional Fälligkeit,
- optional Verknüpfung zum fachlichen Objekt,
- optional Blockade- oder Wartegrund.

Lebenszyklus:

`offen -> geplant/in Arbeit -> optional wartet/blockiert -> erledigt`

Eine Aufgabe darf nicht gleichzeitig als ungeklärter Eingang geführt werden.

### 3.3 Entscheidung / Freigabe

Bedeutung: Alle notwendigen Informationen liegen vor; der Betreiber muss nur noch eine bewusste fachliche Entscheidung treffen.

Typische Aktionen:

- freigeben,
- ablehnen,
- zur Überarbeitung zurückgeben,
- bewusst unverändert bestätigen.

Freigaben sind kein unabhängiger Datentyp und kein separater Speicherort. Sie sind eine fokussierte Ansicht entscheidungsreifer Vorgänge.

### 3.4 Information

Bedeutung: Die Information ist relevant, erfordert aber keine Handlung.

Beispiele:

- Weekly-KI-Lauf erfolgreich,
- Content-Audit ohne kritische Befunde,
- Event automatisch aktualisiert,
- Aufgabe erfolgreich abgeschlossen.

Informationen dürfen keine Aufgabenbadge-Zahl erhöhen. Sie werden gelesen, automatisch verdichtet oder nach angemessener Zeit archiviert.

### 3.5 Idee

Bedeutung: Ein mögliches Produkt-, Content- oder Geschäftsvorhaben ohne bereits getroffene Umsetzungsentscheidung.

Ideen besitzen grundsätzlich:

- keine normale Fälligkeit,
- keinen Status „überfällig“,
- keinen Platz in der täglichen Aufgabenlast.

Lebenszyklus:

`gesammelt -> bewerten -> verwerfen / parken / in Vorhaben oder konkrete Aufgabe umwandeln`

## 4. Priorisierungsmodell der Übersicht

Die Übersicht ist die persönliche Aufmerksamkeitszentrale und gliedert sich in dieser Reihenfolge.

### 4.1 Jetzt erforderlich

Enthält nur:

- überfällige Aufgaben,
- heute notwendige Aufgaben,
- blockierende Fehler mit fachlicher Auswirkung,
- dringende Freigaben,
- kritische Content-Probleme.

Jedes Element zeigt den nächsten sinnvollen Schritt.

### 4.2 Als Nächstes

Enthält:

- bald fällige Aufgaben,
- geplante Folgeaktionen,
- zurückgestellte Vorgänge, deren Wiedervorlagedatum erreicht ist,
- wartende Entscheidungen, die bald relevant werden.

### 4.3 Neuer Eingang

Zeigt neue ungeklärte Vorgänge, sinnvoll nach Typ gruppiert oder verdichtet.

Die Übersicht muss nicht jede Karte vollständig replizieren. Sie führt direkt in die passende fokussierte Bearbeitung.

### 4.4 Zur Kenntnis

Zeigt wenige relevante Informationen ohne Handlungsdruck.

Erfolgreiche Routineabläufe sollen bevorzugt verdichtet werden, beispielsweise:

`3 automatische Prüfungen erfolgreich – keine Aktion erforderlich`.

### 4.5 Systemzustand

Der normale Zustand lautet knapp und ruhig:

`Automatisierungen laufen – keine Störung mit Auswirkung`.

Technische Details, Logs und historische Läufe liegen in einer nachrangigen Systemansicht.

## 5. Kanonische Navigation

Empfohlene primäre Navigation:

1. Übersicht
2. Eingang
3. Inhalte
4. Aufgaben
5. Mehr

Unter `Mehr`:

- Ideen,
- Anbieter,
- Auswertungen,
- Automatisierungen/Systemstatus,
- Einstellungen,
- Archiv.

`Freigaben` sind eine hervorgehobene Filteransicht in Übersicht und Eingang beziehungsweise innerhalb des betroffenen Fachbereichs. Sie bilden keine zusätzliche gleichrangige Datenwelt.

Die Nutzerbezeichnung lautet konsistent `Inhalte`, nicht wechselnd `Content`, sofern ein technischer Kontext nicht ausdrücklich gemeint ist.

## 6. Einzelbereich: Übersicht

### Aufgabe

Die Übersicht beantwortet in weniger als wenigen Sekunden, ob und was der Betreiber tun muss.

### Muss enthalten

- klare Gesamtampel ohne dramatische Darstellung,
- Anzahl und Einstieg in `Jetzt erforderlich`,
- Anzahl und Einstieg in neue Eingangsfälle,
- nächste fällige Aufgaben,
- relevante Informationen stark verdichtet,
- Systemstörung nur bei echter Auswirkung.

### Darf nicht

- reine Gesamtmengen als dominante KPI darstellen,
- erfolgreiche Routineprozesse wie Warnungen inszenieren,
- dieselben Vorgänge mehrfach in mehreren Blöcken zählen,
- eine Analyseoberfläche statt einer Arbeitsoberfläche sein.

### Mobile Abnahme

- oberster Bildschirmbereich zeigt Handlungsbedarf und nicht Dekoration,
- zentrale CTAs beanspruchen nicht unverhältnismäßig viel Höhe,
- ohne Scrollen ist mindestens der wichtigste aktuelle Handlungsblock vollständig verständlich,
- Badges unterscheiden Handlung von Information.

## 7. Einzelbereich: Eingang

### Aufgabe

Der Eingang ist die mobile Ausnahme-Arbeitsansicht für noch ungeklärte Fälle.

### Queue-Trennung

Mindestens fachlich unterscheidbar:

- neue Inhalte,
- Änderungen an bestehenden Inhalten,
- echte Qualitätsentscheidungen,
- Anbieter-/Nutzeranliegen,
- sonstige neue Vorgänge.

Die Trennung kann über Filter oder Tabs erfolgen, muss aber auf demselben Vorgangsmodell beruhen.

### Kartenstandard

Jede Karte zeigt:

- worum es geht,
- warum der Fall hier liegt,
- welches fachliche Objekt betroffen ist,
- welche Hauptentscheidung jetzt erforderlich ist,
- belastbare Quelle oder Kontext, sofern relevant.

Technische Felder, interne IDs und Guard-Codes sind eingeklappt oder in einer Debugansicht.

### Aktionen

Typische primäre Aktionen:

- übernehmen,
- ablehnen,
- bestätigen,
- korrigieren,
- Aufgabe erzeugen.

Sekundär:

- zurückstellen,
- Details,
- Quelle öffnen,
- technischer Kontext.

### Mobile Abnahme

- vor/zurück beziehungsweise nächster Fall funktioniert,
- nach einer Aktion wird der nächste sinnvolle Fall gezeigt,
- Filter- und Scrollzustand gehen nicht unnötig verloren,
- Ablehnung und Übernahme sind nicht verwechselbar,
- destruktive Aktionen verlangen eine angemessene Bestätigung oder Undo-Möglichkeit,
- zurückgestellte Fälle verschwinden bis zur Wiedervorlage aus der normalen Arbeitsmenge.

## 8. Einzelbereich: Inhalte

### Aufgabe

`Inhalte` ist die fachliche Verwaltungsoberfläche für Events, Aktivitäten und später weitere öffentliche Objekte.

### Muss ermöglichen

- suchen und filtern,
- aktuellen Publikationsstatus erkennen,
- öffentlichen Inhalt öffnen,
- Daten gezielt bearbeiten,
- mit dem Objekt verbundene offene Vorgänge sehen,
- Änderung, Absage oder Korrektur nachvollziehbar durchführen,
- Herkunft und letzte relevante Aktualisierung erkennen.

### Objektzentrierung

Auf der Objektseite erscheinen verknüpft:

- öffentliche Darstellung,
- Stammdaten,
- Qualitätsstatus,
- Quelle,
- offene Aufgabe oder Entscheidung,
- Verlauf relevanter Änderungen.

Es wird kein zweiter unabhängiger Aufgabenbestand innerhalb des Content-Bereichs aufgebaut.

### Mobile Abnahme

- Listenzeile oder Karte zeigt Titel, Typ, Status und wichtigste Kontextinformation,
- Bearbeitung konzentriert sich auf fachliche Felder,
- lange Formulare werden logisch gegliedert,
- Speichern/Verwerfen ist eindeutig,
- öffentliche Vorschau ist direkt erreichbar,
- technische Rohdaten sind nachrangig.

## 9. Einzelbereich: Aufgaben

### Aufgabe

`Aufgaben` enthält ausschließlich bereits entschiedene, konkrete Arbeit.

### Mindestansichten

- jetzt/überfällig,
- als Nächstes,
- wartet/blockiert,
- erledigt beziehungsweise Archiv.

### Aufgabenerstellung

Eine Aufgabe kann entstehen aus:

- einem Eingang,
- einem fachlichen Objekt,
- einer Idee,
- einer manuellen Erfassung,
- einem Systembefund, wenn menschliche Arbeit wirklich erforderlich ist.

Bei Umwandlung aus einem Eingang wird der Eingangsvorgang geschlossen oder in den neuen Aufgabenstatus überführt; es entsteht keine unabhängige Doppelkarte.

### Mobile Abnahme

- Erledigen ist schnell möglich,
- Fälligkeit und Dringlichkeit sind verständlich, aber nicht alarmistisch,
- Blockiert/Wartet benötigt einen sichtbaren Grund,
- Aufgabe öffnen, Objekt öffnen und nächsten Schritt ausführen sind klar getrennt,
- erledigte Aufgaben belasten die aktive Ansicht nicht.

## 10. Einzelbereich: Freigaben / Entscheidungen

### Aufgabe

Diese Ansicht bündelt Vorgänge, die ohne weitere Recherche entscheidungsbereit sind.

### Muss zeigen

- Entscheidungsgegenstand,
- vorgeschlagene Änderung oder Veröffentlichung,
- relevante Vorher-/Nachher-Information,
- Quelle und Qualitätsstatus,
- klare Auswirkungen der Entscheidung.

### Mobile Abnahme

- Freigeben und Ablehnen sind eindeutig,
- Vorher/Nachher ist bei Änderungen mobil verständlich,
- fehlende Informationen verhindern eine Scheinf­reigabe und führen zurück in Eingang/Aufgabe,
- nach Entscheidung verschwindet der Vorgang aus der aktiven Freigabeliste.

## 11. Einzelbereich: Ideen

### Aufgabe

Ideen dienen als ruhiger Sammel- und Bewertungsbereich, nicht als Schatten-Aufgabenliste.

### Muss ermöglichen

- schnell erfassen,
- grob kategorisieren,
- Nutzen oder Anlass notieren,
- parken,
- verwerfen,
- bewusst in ein Vorhaben oder eine Aufgabe überführen.

### Mobile Abnahme

- schnelle Erfassung mit sehr wenigen Feldern,
- keine künstlichen Pflichttermine,
- keine roten Überfälligkeitszustände,
- klare Umwandlung in konkrete Arbeit.

## 12. Einzelbereich: Informationen und Systemstatus

### Aufgabe

Diese Bereiche schaffen Vertrauen in Automatisierungen, ohne den Betreiber mit Technik zu belasten.

### Informationsregeln

- Erfolg wird verdichtet,
- Warnung erscheint nur bei möglicher fachlicher Auswirkung,
- Fehler erscheint prominent nur bei bestätigter Auswirkung oder notwendiger Handlung,
- technische Ursache und Logs sind nachrangig erreichbar,
- ein Systemfehler erzeugt nur dann eine Aufgabe, wenn tatsächlich menschliche Arbeit erforderlich ist.

### Mobile Abnahme

- Status ist in Alltagssprache formuliert,
- betroffene Funktion und Auswirkung sind erkennbar,
- bei Handlungsbedarf existiert genau eine klare nächste Aktion,
- Rohlogs dominieren keine Standardansicht.

## 13. Bereichsübergreifende UX-Regeln

### 13.1 Sprache

Begriffe sind fachlich und konsistent:

- Übersicht,
- Eingang,
- Inhalte,
- Aufgaben,
- Entscheidungen/Freigaben,
- Ideen,
- Informationen,
- Systemstatus.

Technische Begriffe wie Pipeline, Guard, Runtime, JSON oder Audit-Code erscheinen nur in technischen Details.

### 13.2 Statusdarstellung

Ein gemeinsames Statusmodell wird visuell konsistent verwendet:

- neu,
- Entscheidung erforderlich,
- offen,
- in Arbeit,
- wartet,
- blockiert,
- zurückgestellt,
- erledigt,
- abgelehnt,
- Information.

Farben sind unterstützend, nie alleinige Bedeutungsträger.

### 13.3 Badges und Zähler

Badges zählen standardmäßig nur ungelesene oder handlungsrelevante Vorgänge.

Nicht in den Hauptbadge gehören:

- Ideen,
- erfolgreiche Systemläufe,
- normale Informationen,
- technische Backlogs ohne unmittelbare Aktion,
- erledigte Vorgänge.

### 13.4 Rückmeldung nach Aktionen

Jede Aktion liefert eine klare, knappe Rückmeldung:

- was geändert wurde,
- ob weitere Arbeit nötig ist,
- wohin der Vorgang gewechselt ist.

Bei leicht reversiblen Aktionen ist Undo einer zusätzlichen Bestätigungsstufe vorzuziehen.

### 13.5 Leere Zustände

Leere Zustände sind positiv und konkret:

- `Kein neuer Eingang – aktuell ist nichts zu prüfen.`
- `Keine dringenden Aufgaben.`
- `Keine Freigabe erforderlich.`

Keine technischen Nullzustände oder leeren Tabellen ohne Erklärung.

## 14. Daten- und Prozessvertrag

Jeder Vorgang benötigt mindestens:

- stabile Vorgangs-ID,
- Aufmerksamkeitstyp,
- führenden Status,
- Priorität/Dringlichkeit nur wenn fachlich begründet,
- Erstellungs- und Änderungszeitpunkt,
- optional Fälligkeit oder Wiedervorlage,
- optional Verknüpfung zum fachlichen Objekt,
- Quelle beziehungsweise Ursprung,
- klaren nächsten Schritt,
- Verlauf relevanter Entscheidungen.

Übergänge müssen atomar sein. Beispiel:

`Eingang -> Aufgabe`

bedeutet nicht „neue Aufgabe zusätzlich erzeugen und Eingang offen lassen“, sondern eine nachvollziehbare Zustandsumwandlung oder geschlossene Herkunftsverknüpfung.

## 15. Gesamtabnahme des Konzepts

Der Zielzustand gilt erst als erreicht, wenn alle folgenden Aussagen wahr sind:

1. Der Betreiber erkennt auf der Übersicht sofort, ob etwas zu tun ist.
2. Kein wichtiger Vorgang erfordert das routinemäßige Öffnen mehrerer Bereiche.
3. Derselbe Sachverhalt wird nicht mehrfach unabhängig geführt oder gezählt.
4. Eingang, Aufgabe, Entscheidung, Information und Idee sind fachlich eindeutig unterscheidbar.
5. Inhalte bleiben fachliche Objekte und werden nicht zu einer parallelen Aufgabenwelt.
6. Automatisierungen reduzieren Arbeit und erzeugen nur bei echter Notwendigkeit menschliche Vorgänge.
7. Alle Kernabläufe sind auf Mobile vollständig verständlich und bedienbar.
8. Desktop bietet mehr Raum, aber keine andere Prozesslogik.
9. Erfolgreiche Routineprozesse erzeugen Ruhe; echte Blockaden erzeugen klare Aufmerksamkeit.
10. Nach jeder Aktion ist eindeutig, was mit dem Vorgang geschehen ist.

## 16. Validierungs- und Umsetzungsreihenfolge

Vor UI-Patches wird der aktuelle Bestand gegen diesen Vertrag inventarisiert:

1. alle bestehenden Bereiche, Karten, Zähler und Datenquellen erfassen,
2. Doppelungen und konkurrierende Statusmodelle identifizieren,
3. jeden bestehenden Vorgangstyp einem kanonischen Aufmerksamkeitstyp zuordnen,
4. Übergänge und führenden Datensatz festlegen,
5. Übersicht und Navigation daraus ableiten,
6. anschließend jeden Einzelbereich mobile-first härten,
7. erst danach visuelles Polish durchführen.

Nicht lokal einzelne Karten verschönern, solange unklar ist, ob sie im Gesamtsystem richtig eingeordnet sind.

## 17. Abgrenzung zu bestehenden Dokumenten

Bestehende Regeln bleiben gültig, soweit sie diesem Vertrag nicht widersprechen, insbesondere:

- private Inbox als Ausnahme-Arbeitsansicht,
- Trennung neuer Inhalte und bestehender Content-Prüffälle,
- keine normalen Inboxkarten für technische oder aggregierbare Visual-Hinweise,
- keine ungeprüfte automatische fachliche Datenänderung durch KI,
- systemische Qualitätsverhinderung vor nachträglicher manueller Prüfung.

Bei Widersprüchen ist dieser kanonische Zielzustand für die Steuerzentrale maßgeblich. Fachspezifische Dokumente konkretisieren ihn, dürfen aber keine abweichenden Parallelmodelle für Aufgaben, Eingang, Freigaben, Informationen oder Ideen einführen.
