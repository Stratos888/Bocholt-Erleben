# Steuerzentrale – Backend-Gap-Analyse

Stand: 2026-07-10
Status: verbindliche technische Umsetzungsvorgabe

## 1. Ergebnis

Das bestehende zentrale Vorgangsmodell ist als Grundlage weiterverwendbar, deckt den vollständigen Produktvertrag aber noch nicht ab.

Die größte Lücke liegt nicht in zusätzlichen Statuswerten, sondern in fehlender fachlicher Normalisierung:

- Der zentrale Vorgang kennt aktuell Quelle, Typ, Status und Rohpayload.
- Er kennt noch nicht zuverlässig die konkrete fachliche Aktionsart, den Vorgangsuntertyp, entscheidungsrelevante strukturierte Daten und die verfügbaren Aktionen.
- Die UI leitet deshalb Hauptaktionen aus generischen Statuswerten ab. Dadurch entstehen falsche Bezeichnungen wie `Freigeben` bei korrekturbedürftigen Fällen.

Vor dem UI-Ersatz muss diese Interpretation serverseitig vereinheitlicht werden.

## 2. Weiterverwendbare Grundlage

### Datenmodell

Weiterverwendbar:

- `control_cases`
- `control_case_events`
- eindeutige Kombination aus `source_system` und `source_reference`
- Objektbezug
- Priorität
- Fälligkeit
- Wiedervorlage
- Blockadegrund
- Verlauf

### Domainlogik

Weiterverwendbar:

- zentrale Statusübergänge
- Transaktionsschutz
- Quell-Writeback vor zentralem Abschluss
- Deduplizierung
- Schutz erledigter Fälle vor Wiederöffnung
- gezieltes Wiederöffnen bezahlter Anbieterfälle

### Quellenadapter

Weiterverwendbar:

- KI-/Sheet-Inbox
- Content-Audit
- Anbieter-Submissions

## 3. Fehlende kanonische Fachfelder

Das API-Ausgabeobjekt muss folgende normalisierte Felder bereitstellen:

### 3.1 `case_kind`

Fachlicher Vorgangsuntertyp, zum Beispiel:

- `event_candidate`
- `activity_candidate`
- `submission_pre_payment`
- `submission_waiting_payment`
- `submission_publish`
- `content_description_correction`
- `content_source_correction`
- `content_fact_check`
- `content_change`
- `content_cancellation`
- `visual_decision`
- `manual_task`
- `manual_idea`
- `system_incident`
- `system_information`

### 3.2 `primary_action`

Strukturiertes Objekt:

```json
{
  "key": "correct_and_apply",
  "label": "Korrigieren und übernehmen",
  "requires_input": true,
  "destructive": false
}
```

Die UI darf die Hauptaktion nicht mehr aus `state` oder `decision_ready` ableiten.

### 3.3 `secondary_actions`

Liste zulässiger, bereits fachlich benannter Aktionen.

### 3.4 `display_status`

Deutsche fachliche Bezeichnung, unabhängig vom internen Statuswert.

### 3.5 `queue_group`

Eine der fachlichen Bearbeitungsgruppen:

- `new_content`
- `changes`
- `quality`
- `provider`
- `approvals`
- `other`

### 3.6 `decision_context`

Strukturierte entscheidungsrelevante Daten, abhängig vom Vorgang:

- aktueller Text
- vorgeschlagener Text
- Vorher/Nachher
- aktuelle und vorgeschlagene Quelle
- Eventdaten
- Zahlungsstatus
- Risikohinweise
- Dublettenhinweis

### 3.7 `source_links`

Benannte Links, zum Beispiel:

- offizielle Quelle
- öffentliche Vorschau
- Anbieterfall

### 3.8 `waiting_for`

Für wartende Vorgänge verständlicher Grund und optional erwarteter Zeitpunkt.

## 4. Datenbankschema

### 4.1 Keine zwingende sofortige Spaltenerweiterung für abgeleitete Felder

`case_kind`, Aktionsdefinitionen, Anzeigegruppen und Entscheidungskontext können zunächst deterministisch aus Quellsystem, Status und Payload normalisiert werden.

Damit wird vermieden, abgeleitete Informationen redundant zu speichern und später inkonsistent zu halten.

### 4.2 Sinnvolle persistente Ergänzungen

Für echte Aufgaben und Ideen werden folgende zusätzliche Felder beziehungsweise eine Erweiterung benötigt:

- `description` als längerer Arbeitstext oder konsistente Nutzung von `reason`
- `category` für Ideen und Aufgaben
- `waiting_for`
- `parent_case_id` oder Relation für nachvollziehbare Umwandlungen
- optional `archived_at`

Bevorzugte Lösung:

- bestehende `control_cases` für Aufgabe und Idee beibehalten
- neue Relationstabelle `control_case_relations` für Vorgang → Aufgabe, Idee → Aufgabe und Objektbezüge ergänzen
- keine zweite Aufgaben- oder Ideentabelle

## 5. Fehlende Domainaktionen

Erforderlich zusätzlich zu den bestehenden generischen Übergängen:

### Fachliche Orchestrierungsaktionen

- `correct_and_apply`
- `confirm_correct`
- `replace_source`
- `apply_change`
- `apply_cancellation`
- `release_payment`
- `publish_submission`
- `create_task_from_case`
- `create_task_for_object`
- `convert_idea_to_task`
- `archive`
- `restore_from_archive`

Diese Aktionen dürfen intern bestehende Writebacks und Statusübergänge wiederverwenden. Sie benötigen aber jeweils einen eindeutigen fachlichen Vertrag und eine eindeutige Rückmeldung.

## 6. Übersicht

Der aktuelle Overview-Endpunkt liefert vollständige Vorgangsgruppen. Für den Zielzustand wird zusätzlich eine serverseitige Verdichtung benötigt:

```json
{
  "now": {
    "count": 2,
    "breakdown": [{"key":"approvals","label":"Freigaben","count":1}],
    "highlights": []
  },
  "inbox": {
    "count": 12,
    "breakdown": [
      {"key":"quality","label":"Qualitätsprüfungen","count":8},
      {"key":"new_content","label":"Neue Inhalte","count":3},
      {"key":"provider","label":"Anbieterfälle","count":1}
    ]
  }
}
```

Die Übersicht darf nicht gezwungen sein, vollständige Vorgangskarten selbst zu verdichten.

## 7. Aufgaben

### Bereits möglich

- generisches Erstellen eines Vorgangs per POST
- grundlegende Statusübergänge
- Fälligkeit und Wiedervorlage

### Noch erforderlich

- validierter Aufgaben-spezifischer Create-Endpunkt oder klarer Domainmodus
- Bearbeiten von Titel, Beschreibung, Fälligkeit, Priorität und Objektbezug
- Wartegrund setzen und ändern
- relationale Umwandlung aus Eingang oder Idee
- Archivabfrage
- gruppierte Aufgabenabfrage

## 8. Verwaltung

Aktuell fehlt eine interne objektzentrierte Verwaltungs-API.

Erforderliche Endpunkte beziehungsweise Services:

- Event-/Aktivitätssuche
- Filter nach Publikationsstatus und offenen Vorgängen
- Objektdetail
- öffentliche Vorschau-URL
- fachliche Bearbeitung
- Änderung/Absage
- verknüpfte Vorgänge
- Verlauf

Die führenden Contentquellen bleiben bestehen. Die Verwaltungs-API bildet eine kontrollierte interne Schreib- und Leseschicht, keine zweite Contentdatenbank.

## 9. Ideen

Aktuell kann das generische Vorgangs-POST theoretisch eine Idee anlegen, es fehlt aber ein fachlicher Vertrag.

Erforderlich:

- Idee anlegen
- Idee bearbeiten
- Kategorie/Nutzen
- parken
- verwerfen
- in Aufgabe umwandeln
- Growth-Backlog importieren und deduplizieren

## 10. Systemstatus und Archiv

### Systemstatus

Erforderlich ist eine verdichtete API-Ausgabe mit:

- Gesamtzustand
- fachlicher Auswirkung
- relevanten letzten Läufen
- nächster Aktion nur bei Handlungsbedarf
- optionalen technischen Details

### Archiv

Erforderlich:

- erledigte, abgelehnte, geparkte und archivierte Vorgänge abfragen
- Zeitraum und Typ filtern
- Verlauf öffnen
- Wiederherstellung nur für zulässige Fälle

## 11. Sicherheits- und Konsistenzregeln

- Aktionsdefinitionen werden serverseitig erzeugt.
- UI darf keine fachlich unzulässige Aktion durch freie Statusinterpretation anbieten.
- Jede Orchestrierungsaktion prüft Quellsystem und `case_kind`.
- Schreibaktionen bleiben durch Review-Zugang geschützt.
- Quellfehler verhindern den zentralen Abschluss.
- Umwandlungen erzeugen eine Relation und keine unverbundene Doppelkarte.

## 12. Umsetzungsreihenfolge

1. Normalisierer für `case_kind`, `queue_group`, deutsche Status und Aktionen.
2. Verdichtete Overview-API.
3. Fachliche Aktionsorchestrierung für bestehende Quellfälle.
4. Aufgaben-Create/Edit/Relation/Archiv.
5. Ideen-Create/Edit/Convert und Growth-Import.
6. Verwaltungs-Lese-API für Events und Aktivitäten.
7. Verwaltungs-Schreibaktionen.
8. Systemstatus- und Archiv-API.
9. Erst danach geschlossener UI-Ersatz.

## 13. Gate-Ergebnis

Das bestehende Backend wird nicht verworfen. Vor dem sichtbaren UI-Ersatz sind jedoch die Punkte 1 bis 4 zwingend erforderlich, damit Übersicht, Bearbeiten und Aufgaben fachlich korrekt funktionieren.

Die Verwaltungs-, Ideen-, Systemstatus- und Archivfunktionen müssen abgeschlossen sein, bevor ihre Bereiche in der finalen Hauptnavigation sichtbar werden.
