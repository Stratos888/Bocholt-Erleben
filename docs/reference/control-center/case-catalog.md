# Steuerzentrale – verbindlicher Vorgangskatalog

Stand: 2026-07-10
Status: verbindlicher Produktvertrag vor weiterer UI-Umsetzung

## 1. Zweck

Dieser Katalog beschreibt alle realen Betreiberfälle, die in der privaten Steuerzentrale auftreten dürfen. Er ist die führende Grundlage für Navigation, Ansichten, Aktionen, Status, Automatisierung und Abnahmetests.

Ein Vorgang darf nur dann als eigene aktive Arbeit erscheinen, wenn eine konkrete menschliche Entscheidung oder Handlung erforderlich ist. Reine Technikmeldungen, erfolgreiche Routinen und automatisch lösbare Befunde werden verdichtet oder protokolliert, nicht als Einzelkarte in die aktive Arbeitsmenge gestellt.

## 2. Allgemeiner Vorgangsvertrag

Jeder aktive Vorgang muss beantworten:

1. Was ist passiert?
2. Warum ist menschliche Aufmerksamkeit erforderlich?
3. Welches fachliche Objekt ist betroffen?
4. Welche Information wird für die Entscheidung benötigt?
5. Was ist die genau eine primäre nächste Aktion?
6. Welche sekundären Aktionen sind zulässig?
7. Was passiert nach der Aktion im führenden Quellsystem?
8. In welchem Status und Bereich liegt der Vorgang danach?

Pflichtfelder für sichtbare Vorgänge:

- fachlicher Titel
- Vorgangsart
- verständlicher Status
- kurzer Grund
- betroffener Inhalt oder Anbieterfall
- primäre nächste Aktion
- Quelle oder Kontext, sofern entscheidungsrelevant
- eindeutige Quellreferenz

Technische IDs, Auditcodes, Rohpayloads und Guard-Bezeichnungen gehören ausschließlich in technische Details.

## 3. Vorgangsarten

### 3.1 Neuer KI-/Sheet-Eventkandidat

**Auslöser:** Automatische Eventsuche oder manuelle Zuführung findet einen neuen möglichen Veranstaltungstermin.

**Menschlicher Bedarf:** Prüfen, ob der Termin korrekt, lokal, öffentlich und nicht bereits vorhanden ist.

**Benötigte Informationen:**

- Titel
- Datum und Uhrzeit
- Ort
- Beschreibung
- offizielle Quelle
- mögliche Dublette
- vorgesehene Bildzuordnung

**Primäre Aktion:** `Übernehmen`

**Sekundäre Aktionen:**

- `Ablehnen`
- `Zurückstellen`
- `Quelle öffnen`
- `Details`

**Nach Übernahme:** Event wird über den bestehenden fachlichen Writeback angelegt oder aktualisiert; Vorgang wird erledigt.

**Nach Ablehnung:** Ablehnungsgrund wird in die führende Quelle geschrieben; Vorgang wird abgelehnt.

**Zielbereich:** Eingang, Filter `Neue Inhalte`.

### 3.2 Neuer Aktivitätskandidat

**Auslöser:** Anbieter, Redaktion oder automatischer Prozess schlägt eine dauerhaft nutzbare Aktivität vor.

**Menschlicher Bedarf:** Prüfen, ob es sich um eine eigenständige, öffentlich nutzbare Aktivität statt um einen Einzeltermin oder reine Werbung handelt.

**Primäre Aktion:** `Übernehmen`

**Sekundäre Aktionen:** Ablehnen, Zurückstellen, Quelle öffnen.

**Zielbereich:** Eingang, Filter `Neue Inhalte`.

### 3.3 Anbieter-Einreichung vor Zahlungsfreigabe

**Auslöser:** Event- oder Aktivitätseinreichung mit Status `pending_review`.

**Menschlicher Bedarf:** Angaben, öffentliche Ortsnennung, Tarifbezug und grundsätzliche Veröffentlichungsfähigkeit prüfen.

**Primäre Aktion:** `Zahlung freigeben`

**Sekundäre Aktionen:** Ablehnen, Zurückstellen, Anbieter-/Objektdetails öffnen.

**Nach Freigabe:** Zahlungslink wird erzeugt/versendet; derselbe Vorgang wechselt in `wartet`.

**Zielbereich:** Eingang, Filter `Anbieter`.

### 3.4 Anbieter-Einreichung wartet auf Zahlung

**Auslöser:** Status `payment_released` oder `checkout_started`.

**Menschlicher Bedarf:** Standardmäßig keiner. Der Fall wird nur als wartender Vorgang geführt, nicht als neue Entscheidung.

**Primäre Aktion:** keine reguläre Aktionsschaltfläche; `Details öffnen` beziehungsweise echte Statusaktualisierung.

**Zielbereich:** Aufgaben, Gruppe `Wartet`.

**Automatischer Übergang:** Nach bestätigter Zahlung wird derselbe Vorgang erneut entscheidungsbereit.

### 3.5 Bezahlte Anbieter-Einreichung zur Veröffentlichung

**Auslöser:** Status `paid` oder `in_review`.

**Menschlicher Bedarf:** Finale Qualitäts- und Pflichtfeldprüfung.

**Primäre Aktion:** `Veröffentlichen`

**Sekundäre Aktionen:** Ablehnen, Zurückstellen, Details.

**Nach Veröffentlichung:** Bestehender Approval-Prozess einschließlich Kontingentbuchung und Benachrichtigung läuft; Vorgang wird erledigt.

**Zielbereich:** Eingang, Filter `Freigaben` beziehungsweise Übersicht `Jetzt erforderlich`, wenn priorisiert.

### 3.6 Content-Qualitätskorrektur – Beschreibung

**Auslöser:** Audit erkennt einen tatsächlich entscheidungsbedürftigen Verstoß gegen den Beschreibungsstandard.

**Menschlicher Bedarf:** Beschreibung fachlich redaktionell korrigieren oder bewusst bestätigen.

**Benötigte Informationen:**

- aktueller Text
- verständlich benannter Verstoß
- offizielle Quelle
- vorgeschlagener oder bearbeitbarer Zieltext

**Primäre Aktion:** `Korrigieren und übernehmen`

**Sekundäre Aktionen:** Bewusst bestätigen, Zurückstellen, Ablehnen, Quelle öffnen.

**Nicht zulässig:** Eine allgemeine Schaltfläche `Freigeben`, solange die dokumentierte nächste Aktion eine Korrektur ist.

**Zielbereich:** Eingang, Filter `Qualitätsprüfung`.

### 3.7 Content-Qualitätskorrektur – Quelle

**Auslöser:** Quelle fehlt, ist ungeeignet, defekt, umgeleitet oder ein Ticketportal soll durch eine offizielle Quelle ersetzt werden.

**Primäre Aktion:** `Quelle übernehmen` oder `Quelle korrigieren`

**Sekundäre Aktionen:** Bestehende Quelle bewusst bestätigen, Zurückstellen, Details.

**Nach Aktion:** Eventquelle und Auditstatus werden konsistent aktualisiert.

### 3.8 Content-Faktenprüfung

**Auslöser:** Automatische Prüfung konnte Datum, Uhrzeit, Ort oder Titel nicht ausreichend bestätigen, ohne bereits einen belegten Fehler festzustellen.

**Primäre Aktion:** `Als korrekt bestätigen` oder `Korrigieren`, abhängig vom Ergebnis.

**Sekundäre Aktionen:** Quelle öffnen, Zurückstellen.

**Darstellung:** Befund und geprüfte Fakten, keine technische Trefferquote als dominante Information.

### 3.9 Eventänderung oder Absage

**Auslöser:** Veranstalter, Quelle oder Prüfung meldet geänderte Daten oder Absage.

**Primäre Aktion:** `Änderung übernehmen` beziehungsweise `Absage übernehmen`

**Benötigte Informationen:** Vorher/Nachher, Quelle, Auswirkung auf öffentliche Anzeige.

**Zielbereich:** Eingang, Filter `Änderungen`.

### 3.10 Bild-/Motiventscheidung mit echter redaktioneller Wahl

**Auslöser:** Nur wenn keine sichere automatische Zuordnung möglich ist und eine konkrete menschliche Bildentscheidung erforderlich ist.

**Primäre Aktion:** `Bildzuordnung übernehmen`

**Sekundäre Aktionen:** Alternative wählen, als Aufgabe weiterführen.

**Nicht als Einzelvorgang:** reine Asset-Lücken, Produktionsbacklogs oder automatisch patchbare Visual-Hinweise.

### 3.11 Manuelle Aufgabe

**Auslöser:** Betreiber legt konkrete Arbeit selbst an.

**Pflichtangaben:** Titel, nächster Schritt; optional Fälligkeit, Priorität, Objektbezug.

**Primäre Aktion:** `Erledigen`

**Sekundäre Aktionen:** Starten, Warten, Blockieren, Zurückstellen, Bearbeiten.

**Zielbereich:** Aufgaben.

### 3.12 Aufgabe aus Eingang

**Auslöser:** Ein ungeklärter Vorgang wurde entschieden, benötigt aber weitere konkrete Arbeit.

**Regel:** Der Eingangsvorgang wird in eine Aufgabe überführt oder geschlossen und eindeutig mit der Aufgabe verknüpft. Es entsteht keine unabhängige Doppelkarte.

### 3.13 Wartende Aufgabe

**Auslöser:** Externe Antwort, Zahlung, Termin oder Systemergebnis wird erwartet.

**Pflichtinformation:** Worauf wird gewartet und wann sollte erneut geprüft werden?

**Primäre Aktion:** `Status aktualisieren` nur wenn tatsächlich eine Quellprüfung ausgeführt wird; sonst `Details`.

### 3.14 Blockierte Aufgabe

**Auslöser:** Konkrete Arbeit kann wegen eines benannten Hindernisses nicht fortgesetzt werden.

**Pflichtinformation:** Blockadegrund und nächster möglicher Entblockungsschritt.

**Zielbereich:** Aufgaben, Gruppe `Blockiert`; bei zeitkritischer Auswirkung zusätzlich Übersicht `Jetzt erforderlich`.

### 3.15 Idee

**Auslöser:** Noch nicht entschiedener Verbesserungsgedanke, Akquiseansatz oder Produktvorschlag.

**Primäre Aktion:** keine Arbeitsaktion.

**Mögliche Aktionen:** Bearbeiten, Parken, Verwerfen, in Aufgabe umwandeln.

**Zielbereich:** Mehr → Ideen.

**Regel:** Ideen erzeugen keine roten Badges und keine künstliche Fälligkeit.

### 3.16 Systemstörung mit fachlicher Auswirkung

**Auslöser:** Automatisierung oder Datenfluss ist nachweislich gestört und menschliche Arbeit ist erforderlich.

**Primäre Aktion:** genau eine konkrete Handlung, zum Beispiel `Erneut ausführen`, `Zugang erneuern` oder `Aufgabe öffnen`.

**Zielbereich:** Übersicht `Jetzt erforderlich`; technische Details unter Systemstatus.

### 3.17 Systeminformation ohne Handlungsbedarf

**Auslöser:** Routine erfolgreich, Verarbeitung abgeschlossen oder unkritischer Hinweis.

**Darstellung:** verdichtet, zum Beispiel `3 Prüfungen erfolgreich – keine Aktion erforderlich`.

**Zielbereich:** Übersicht `Zur Kenntnis` oder Mehr → Systemstatus.

**Nicht zulässig:** Einzelkarten in Eingang oder Aufgaben.

## 4. Querschnittsregeln

- Ein Sachverhalt erzeugt höchstens einen aktiven führenden Vorgang.
- Ein Vorgang erscheint auf der Übersicht höchstens als verdichteter Hinweis und nicht zusätzlich als vollständige Kopie.
- Jede primäre Schaltfläche entspricht exakt der dokumentierten fachlichen nächsten Aktion.
- Nach erfolgreicher Aktion wird der nächste sinnvolle Fall gezeigt.
- Zurückgestellte Vorgänge verschwinden bis zur Wiedervorlage aus der normalen Arbeitsmenge.
- Erledigte, abgelehnte und archivierte Vorgänge belasten keine aktiven Badges.
- Quell-Writeback erfolgt vor dem zentralen Abschluss.
- Fehlender Kontext verhindert eine Scheinf­reigabe und führt zu Korrektur oder Aufgabe.

## 5. Ausschlussliste

Folgende Sachverhalte dürfen nicht als einzelne aktive Arbeitskarten erscheinen:

- erfolgreiche Standardläufe
- reine technische Logs
- automatisch lösbare Befunde
- Visual-Produktionsbacklogs ohne aktuelle Entscheidung
- allgemeine Kennzahlen
- bereits erledigte oder archivierte Fälle
- identische Hinweise für dasselbe Objekt und dieselbe Ursache
