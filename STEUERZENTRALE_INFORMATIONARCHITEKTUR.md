# Steuerzentrale – verbindliche Informationsarchitektur

Stand: 2026-07-10
Status: verbindlicher Produktvertrag vor weiterer UI-Umsetzung

## 1. Leitentscheidung

Die Steuerzentrale ist keine Sammlung vorhandener Seiten, sondern eine auf Handlungsbedarf optimierte Betreiberanwendung.

Die Hauptnavigation enthält nur Bereiche, die im finalen Zustand eine echte, regelmäßig nutzbare Funktion besitzen. Leere Platzhalter, reine Links auf öffentliche Seiten oder technische Altansichten gehören nicht in die primäre Navigation.

## 2. Endgültige Hauptnavigation

### 2.1 Übersicht

Aufgabe: In wenigen Sekunden beantworten, ob und was jetzt getan werden muss.

Enthält ausschließlich verdichtete Blöcke:

1. `Jetzt erforderlich`
2. `Neuer Eingang`
3. `Als Nächstes`
4. `Zur Kenntnis`
5. `Systemzustand`

Die Übersicht zeigt keine vollständige Wiederholung aller Arbeitskarten.

### 2.2 Bearbeiten

Aufgabe: Fokussierte Bearbeitung aller ungeklärten und entscheidungsbedürftigen Fälle.

Bezeichnung: `Bearbeiten` ist verständlicher als `Eingang`, weil der Bereich neben neuen Inhalten auch Qualitätskorrekturen, Änderungen und Freigaben enthält. `Eingang` bleibt ein Filter innerhalb dieses Bereichs.

Filter:

- Alle
- Neue Inhalte
- Änderungen
- Qualitätsprüfung
- Anbieter
- Freigaben
- Sonstige

Mobil: ein fokussierter Vorgang mit Vorher/Weiter.

Desktop: kompakte Queue links, geöffneter Vorgang rechts.

### 2.3 Aufgaben

Aufgabe: Ausschließlich bereits entschiedene konkrete Arbeit verwalten.

Unteransichten:

- Jetzt
- Als Nächstes
- Wartet
- Blockiert
- Erledigt/Archiv

Funktionen:

- manuell anlegen
- aus einem Vorgang erzeugen
- starten
- warten
- blockieren
- zurückstellen
- erledigen
- bearbeiten
- mit Event, Aktivität oder Anbieterfall verknüpfen

### 2.4 Verwaltung

Aufgabe: Fachliche Objektverwaltung für Events und Aktivitäten.

Bezeichnung: `Verwaltung` statt `Inhalte`, weil der Bereich nicht nur Inhalte öffnet, sondern Objekte sucht, bearbeitet, korrigiert und mit Vorgängen verknüpft.

Unterbereiche:

- Events
- Aktivitäten

Mindestfunktionen:

- Suche
- Filter
- Publikationsstatus
- öffentliche Vorschau
- Stammdaten bearbeiten
- Quelle und letzte Aktualisierung
- offene Vorgänge am Objekt
- Änderung oder Absage durchführen
- relevanter Verlauf

Die öffentliche Event- oder Aktivitätsseite ist nur eine Vorschau, nicht der Verwaltungsbereich selbst.

### 2.5 Mehr

Aufgabe: Selten benötigte, aber echte Funktionen bündeln.

Enthält nur implementierte Bereiche:

- Ideen
- Systemstatus
- Archiv
- Anbieterzugang
- Einstellungen
- Abmelden

Optional später:

- Auswertungen

Nicht enthalten:

- technische Altansicht als regulärer Menüpunkt
- leere Funktionsversprechen
- Links ohne Betreibermehrwert

## 3. Mobile Navigation

Die Hauptnavigation ist auf allen mobilen und tabletähnlichen Viewports dauerhaft am unteren Viewportrand fixiert.

Reihenfolge:

1. Übersicht
2. Bearbeiten
3. Aufgaben
4. Verwaltung
5. Mehr

Regeln:

- bei jeder Scrollposition sichtbar
- Safe-Area berücksichtigt
- Inhalt besitzt ausreichenden unteren Abstand
- Badge nur auf `Bearbeiten` und `Aufgaben`
- keine Badge-Zählung für Ideen, Informationen oder erfolgreiche Systemläufe
- aktive Ansicht eindeutig

Auf breiten Desktopansichten darf dieselbe Navigation als feste linke Seitenleiste erscheinen. Sie darf niemals erst am Dokumentende sichtbar werden.

## 4. Übersicht im Detail

### 4.1 Jetzt erforderlich

Zeigt nur dringende oder blockierende Sachverhalte.

Darstellung:

- Anzahl
- kurze Typzusammenfassung
- maximal zwei priorisierte Beispiele
- ein CTA `Jetzt bearbeiten`

### 4.2 Neuer Eingang

Verdichtete Gruppen, zum Beispiel:

- 8 Qualitätsprüfungen
- 3 neue Inhalte
- 1 Anbieterfall

Ein CTA führt mit gesetztem Filter in `Bearbeiten`.

### 4.3 Als Nächstes

Zeigt:

- nächste fällige Aufgabe
- erreichte Wiedervorlage
- bald relevante wartende Entscheidung

### 4.4 Zur Kenntnis

Verdichtete Informationen ohne Aktion.

### 4.5 Systemzustand

Normalzustand:

`Automatisierungen laufen – keine bekannte Störung mit Auswirkung.`

Nur bei echter Auswirkung wird ein auffälliger Handlungsblock erzeugt.

## 5. Bearbeiten im Detail

### 5.1 Mobil

Struktur:

1. Bereichstitel und Filter
2. Fortschritt, zum Beispiel `3 von 8`
3. fachlicher Vorgangstyp
4. Titel
5. Problem oder Anlass
6. entscheidungsrelevanter Kontext
7. Quelle beziehungsweise Vorher/Nachher
8. genau eine dominante Hauptaktion
9. maximal zwei direkt sichtbare Nebenaktionen
10. weitere Aktionen im Menü oder Detailbereich
11. Vorher/Weiter

Nach erfolgreicher Aktion wird automatisch der nächste passende Fall geöffnet.

### 5.2 Desktop

Master-Detail:

- links kompakte Queue mit Titel, Typ, Status und Kurzgrund
- rechts der geöffnete Vorgang mit vollständigem Kontext und Aktionen
- keine mehrspaltige Wand vollständiger Karten
- Filter und Auswahl bleiben nach Aktionen erhalten

## 6. Aufgaben im Detail

### Listenzeile

- Titel
- Status
- Fälligkeit oder Wartestatus
- Objektbezug
- nächster Schritt

### Detail

- Beschreibung
- Herkunft
- Verlauf
- Blockade-/Wartegrund
- Aktionen

Ein wartender Vorgang zeigt nur dann `Status aktualisieren`, wenn diese Aktion tatsächlich eine Quelle neu prüft. Andernfalls lautet die Aktion `Details öffnen`.

## 7. Verwaltung im Detail

### 7.1 Objektliste

- Suchfeld
- Filter nach Typ, Status und Handlungsbedarf
- kompakte Ergebnisse
- sichtbarer Publikationsstatus
- Indikator für offene Vorgänge

### 7.2 Objektdetail

- öffentliche Vorschau
- Stammdaten
- Quelle
- Qualitätsstatus
- offene Vorgänge
- Verlauf
- Bearbeiten
- Absage/Änderung, sofern anwendbar

Keine parallele Aufgabenliste innerhalb des Objekts; es werden nur zentrale Vorgänge verknüpft.

## 8. Mehr im Detail

### 8.1 Ideen

- Idee erfassen
- bearbeiten
- kategorisieren
- parken
- verwerfen
- in Aufgabe umwandeln

### 8.2 Systemstatus

- Alltagssprache
- nur relevante Auswirkung prominent
- letzte wichtige Läufe verdichtet
- technische Details nachrangig

### 8.3 Archiv

- erledigte, abgelehnte und archivierte Vorgänge suchen
- Verlauf öffnen
- keine aktiven Badges

### 8.4 Anbieterzugang

Schnellzugriff auf den separaten Anbieterbereich. Kein Ersatz für interne Anbieterfälle in `Bearbeiten`.

### 8.5 Einstellungen

Nur tatsächlich vorhandene Betreiberoptionen, zum Beispiel Benachrichtigungen oder Anzeigepräferenzen.

## 9. Nicht zulässige Navigationsmuster

- öffentliche Event-/Aktivitätslisten als angebliche Verwaltung
- technische Altansicht in der Hauptnavigation
- leere Haupttabs
- identische vollständige Vorgänge auf Übersicht und Bearbeiten
- Bottom-Bar im Dokumentfluss
- technische Begriffe als sichtbare Hauptnavigation
