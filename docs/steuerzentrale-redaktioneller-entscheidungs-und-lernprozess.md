# Steuerzentrale – redaktioneller Entscheidungs- und Lernprozess

Stand: 2026-07-14  
Ausgangsbasis: `main` nach Merge-Commit `505fc69c9a631894b1b2f612e2c7d1b03e186218`  
Status: `LIVE_DEPLOYED / FUNCTIONAL_ACCEPTANCE_BLOCKED`  

## 1. Zweck dieses Workpacks

Dieses Workpack schließt die funktionalen Lücken der neuen Steuerzentrale systemisch. Es geht nicht um einzelne UI-Korrekturen, sondern um einen belastbaren redaktionellen Gesamtprozess:

```text
Kandidat oder Qualitätsfall
→ vollständige und verständliche Entscheidungsgrundlage
→ serverseitig abgesicherte Betreiberentscheidung
→ stabiler Writeback auf die führende Quelle
→ öffentliche Wirkung beziehungsweise sauberer Abschluss
→ typisiertes Feedback
→ kontrollierter Lerneffekt für spätere Läufe
```

Das Workpack umfasst:

- neue Eventkandidaten aus KI-Suche und Sheet-Inbox,
- Anbieter-Einreichungen,
- Content-Audit- und Beschreibungskorrekturen,
- Entscheidungsgründe und Zurückstellung,
- Sheet-basierte Writebacks,
- redaktionelles Textfeedback,
- Backlog-Quellenstatus,
- Verwaltungsdetails und Live-Prüfung,
- verständliche Veröffentlichungsstatus,
- Konsolidierung der überlagerten Frontendlogik,
- automatisierte und read-only Live-Tests.

Nicht Bestandteil dieses Workpacks ist die Ursachenanalyse des SEO-/Reichweitenrückgangs. Dafür folgt ein getrenntes Workpack.

## 2. Verbindlicher Freeze-Status

Bis zur Umsetzung und Abnahme dieses Workpacks gilt:

```text
LIVE_DEPLOYED
FUNCTIONAL_ACCEPTANCE_BLOCKED
```

Erlaubt:

- read-only Prüfung,
- Analyse,
- automatisierte Tests mit Fixtures,
- kontrollierte Staging-Tests.

Nicht erlaubt:

- produktive Schreibaktionen nur zu Testzwecken,
- neue funktionale Optimierungsworkpacks innerhalb der Steuerzentrale,
- Abnahme als vollständig abgeschlossen.

## 3. Bestandsanalyse

### 3.1 Vorhandene Daten werden in der neuen Prüfkarte nicht vollständig genutzt

Die führende Sheet-Inbox wird vollständig in `source_payload_json` gespiegelt. Die Präsentationsschicht gibt davon für neue Eventkandidaten aber nur einen kleinen Ausschnitt aus. Die aktuelle Karte zeigt im Wesentlichen Titel, Problem-/Beschreibungstext und Aktionen.

Folge:

- Datum, Zeitlogik, Ort, Adresse, Kategorie, Quelle, Veranstalter, Ticketlink, Dedupe- und Bildinformationen sind nicht als zusammenhängende Entscheidungsgrundlage sichtbar.
- Eine fachlich belastbare Übernahme ist nicht möglich.

### 3.2 Neue Sheet-Events werden derzeit ohne editierbare finale Fassung übernommen

Bei einem normalen Eventkandidaten führt `approve` unmittelbar den bestehenden Inbox-Approve-Writeback aus. Die Steuerzentrale übergibt keine fachlich korrigierte Endfassung.

Folge:

- Kleine redaktionelle Korrekturen können nicht vor der Übernahme vorgenommen werden.
- Betreiber müssen akzeptieren, ablehnen oder zurückstellen, obwohl oft nur einzelne Felder korrigiert werden müssten.

### 3.3 Sheet-Inbox-Writeback hängt an einer gespeicherten Zeilennummer

Der aktuelle Inbox-Writeback verwendet `row_number` aus dem beim Import gespeicherten Payload. Vor der Aktion wird nicht geprüft, ob diese Zeilennummer noch denselben Kandidaten bezeichnet.

Risiko:

- Durch Sortierung, Cleanup oder neue Zeilen kann die ursprüngliche Zeilennummer veralten.
- Im schlechtesten Fall könnte eine Aktion den falschen Sheet-Datensatz treffen.

Dieses Risiko ist kritischer als ein reiner Anzeige- oder Komfortfehler und muss im selben Workpack behoben werden.

### 3.4 Content-Audit-Writeback ist nur teilweise stabilisiert

Der Audit-Writeback sucht die aktuelle Zeile anhand von:

```text
content_id + issue_code
```

Der Auditdatensatz besitzt bereits weitere Identitätsmerkmale:

- `verification_key`,
- `source_fingerprint`,
- `content_fingerprint`.

Diese werden bei der Zeilenauflösung noch nicht verwendet.

Folge:

- Wird ein Audit neu erzeugt, ein Issue-Code ersetzt oder ein Fall durch einen aktuelleren Befund abgelöst, bricht die Korrektur ab.
- Bereits eingegebener Text geht im aktuellen Dialog verloren.
- Ein fachlich sinnvoller Eventfix wird unnötig von der Existenz der alten Auditzeile abhängig gemacht.

### 3.5 Ablehnungen sind nicht vollständig typisiert

Der aktuelle Ablehnungsdialog:

- öffnet vor dem Writeback,
- besitzt jedoch bereits einen vorausgewählten Grund,
- hat kein optionales Kommentarfeld,
- übermittelt keine explizite `decision_class`.

Das Backend akzeptiert zudem einen Defaultgrund. Die Pflicht ist damit nicht serverseitig abgesichert.

### 3.6 Redaktionelle Textkorrekturen erzeugen noch keinen vollständigen Lernpfad

Bei erfolgreicher Korrektur sind grundsätzlich vorhanden:

- Quellen-Payload,
- vorgeschlagener Text,
- final übertragene Eventfelder,
- Action-Payload im Case-Verlauf.

Nicht vorhanden beziehungsweise nicht nachgewiesen:

- normierter Text-Diff,
- typisierte Änderungskategorien,
- explizite Prompt-/Regelversion,
- aggregierter Lernpfad in den nächsten KI-Suchlauf,
- Schutz gegen Regeländerung aus einem einzelnen Sonderfall,
- Messung von Wiederholung und Fehlwirkung.

### 3.7 Backlog zeigt keinen eindeutigen Quellenstatus

Die Backlogansicht filtert die bereits geladenen Cases. Sie unterscheidet optisch nicht zwischen:

```text
Quelle erfolgreich synchronisiert und leer
Quelle erfolgreich synchronisiert und befüllt
Quelle konnte nicht synchronisiert werden
```

Eine alte globale Statusmeldung kann beim Wechsel in den Backlog sichtbar bleiben und fälschlich wie ein Backlogfehler wirken.

### 3.8 Verwaltungs-„Vorschau“ ist nur ein Live-Link

Der aktuelle Button öffnet `public_url`. Es gibt keine schreibgeschützte Detailansicht mit aktuellen Quelldaten, Bildzuordnung, Historie und Veröffentlichungsstatus.

### 3.9 Veröffentlichungszähler ist missverständlich

Die Zahl in Klammern bezeichnet den Polling-Versuch zur Bestätigung der öffentlichen Wirkung. Sie bezeichnet weder aktualisierte noch neu geladene Events.

### 3.10 Frontendlogik ist durch nachträgliche Überlagerungen schwer wartbar

Die Seite lädt aktuell neben dem Environment-Adapter mehrere voneinander unabhängige Controller:

- `control-center.js`,
- `control-center-source-editors.js`,
- `control-center-final-bridge.js`,
- `control-center-stability.js`,
- `control-center-publication.js`,
- `control-center-development.js`,
- `control-center-integrations.js`,
- `control-center-seo-embed.js`.

Mehrere Dateien:

- besitzen eigene API-Wrapper und Caches,
- fangen dieselben Klicks im Capture-Modus ab,
- verändern gerenderte Inhalte nachträglich per `MutationObserver`,
- ergänzen oder überschreiben dieselben Karten und Dialoge.

Das begünstigt genau die Fehlerklasse, die jetzt sichtbar wurde: UI und Datenprozess werden nachträglich überbrückt, statt aus einem gemeinsamen Zustands- und Datenvertrag gerendert zu werden.

### 3.11 Bestehende Tests prüfen überwiegend Marker und Teilverträge

Vorhanden sind gute Basisprüfungen für:

- PHP-/JS-Syntax,
- Feldalias- und Veröffentlichungsverträge,
- Prozessstatus,
- zentrale Dateimarker.

Nicht abgedeckt sind unter anderem:

- vollständige Event-Entscheidungsreife,
- Inbox-Zeilenverschiebung,
- veraltete Auditfälle,
- Ablehnung ohne expliziten Grund,
- Text-Diff-Lernpfad,
- Detailpanel und exakter Live-Link,
- Backlog-Quellenstatus,
- authentifizierter read-only Steuerzentralen-Smoke-Test.

## 4. Premium-Zielzustand

## 4.1 Einheitlicher Review-Datenvertrag

Das Backend liefert für jede Prüfkarte eine normalisierte Struktur. Rohfelder werden nicht mehr ad hoc im Frontend interpretiert.

Zielstruktur:

```json
{
  "identity": {},
  "facts": {},
  "description": {},
  "source": {},
  "classification": {},
  "visual": {},
  "dedupe": {},
  "quality": {},
  "decision_gate": {},
  "actions": []
}
```

### Harte Pflichtwerte für einen normalen Eventkandidaten

Diese Werte müssen für die Übernahme fachlich belastbar und sichtbar sein:

- Titel,
- Startdatum,
- Stadt beziehungsweise eindeutiger lokaler Bezug,
- Veranstaltungsort,
- Kategorie,
- offizielle eventbezogene Quelle,
- finale redaktionelle Beschreibung,
- belastbare Bildklassifikation mindestens aus `visual_key` und `visual_motif`,
- eindeutige Dedupe-Prüfung ohne offenen harten Konflikt.

### Bedingte Werte

- Enddatum: erforderlich bei Mehrtagesevents.
- Uhrzeit: erforderlich, wenn eine sichere einheitliche Startzeit vorhanden ist; darf bei belegbar unterschiedlichen Tageszeiten oder fehlender sicherer Zeit leer bleiben. Der Grund muss sichtbar sein.
- Adresse: erforderlich, wenn der Ort allein nicht eindeutig auffindbar ist oder eine Route angeboten wird.
- Ticketlink: erforderlich, wenn die Quelle ausdrücklich eine Buchung oder ein Ticket voraussetzt.
- Quellen-/Veranstaltername: sichtbar, soweit vorhanden; aus der Quelle ableitbar.

### Optionale Werte

- Tags,
- Zusatzhinweise,
- Preisangaben,
- weiterführende Links.

### Serverseitiges Entscheidungsgate

Das Backend berechnet:

```json
{
  "ready": true,
  "blockers": [],
  "warnings": [],
  "required_fields": [],
  "conditional_fields": []
}
```

`approve` wird serverseitig abgewiesen, wenn harte Blocker bestehen. Eine deaktivierte UI allein reicht nicht.

## 4.2 Eventkandidaten müssen vor Übernahme korrigierbar sein

Die Prüfkarte bietet zwei klare Wege:

- `Übernehmen`, wenn der Datensatz unverändert entscheidungsreif ist.
- `Bearbeiten und übernehmen`, wenn einzelne Felder fachlich korrigiert werden müssen.

Die editierbare Endfassung verwendet denselben Eventfeldvertrag wie die Verwaltung:

- Titel,
- Datum/Enddatum,
- Zeit,
- Stadt,
- Ort,
- Adresse,
- Kategorie,
- Quelle,
- Ticketlink,
- Beschreibung,
- Visual-Key,
- Visual-Motiv.

Vor `approve` werden die geänderten Felder in der aktuell aufgelösten Inbox-Zeile gespeichert. Erst anschließend wird genau diese Zeile über den kanonischen Inbox-Prozess übernommen.

## 4.3 Zentraler Entscheidungsvertrag

Die bestehende Datei `data/content_ops_decision_classes.json` bleibt die kanonische Taxonomie. PHP-Runtime und Frontend dürfen keine parallele, abweichende Taxonomie besitzen.

Für jede Betreiberentscheidung werden mindestens gespeichert:

- `decision_class`,
- `decision_note`,
- `resolved_by`,
- `resolved_at`,
- `suppress_until`,
- `recheck_at`,
- `reopen_policy`,
- `source_fingerprint`,
- `content_fingerprint`.

### Ablehnung

Der Dialog besitzt:

1. leeren Ausgangswert `Ablehnungsgrund auswählen`,
2. verpflichtende typisierte Auswahl,
3. optionalen ergänzenden Kommentar,
4. Zusammenfassung des betroffenen Inhalts,
5. explizite Bestätigung.

Das Backend lehnt `reject` ohne gültige `decision_class` ab.

Beispiele:

| UI-Grund | `decision_class` |
|---|---|
| Doppelt / bereits abgedeckt | `duplicate` |
| Kein konkreter Einzeltermin | `rejected_not_event` |
| Nicht öffentlich zugänglich | `rejected_not_public` |
| Nicht lokal genug | `rejected_not_local` |
| Quelle / Angaben reichen nicht | `rejected_source_weak` |
| Redaktionell zu schwach | `rejected_low_value` |
| Eher Werbung / Promotion | `rejected_commercial` |

### Zurückstellen

Pflicht:

- Wiedervorlagedatum,
- `decision_class = snoozed`.

Optional:

- Grund beziehungsweise kurze Notiz.

Eine Zurückstellung erzeugt keine dauerhafte negative Suchregel.

### Übernahme und Korrektur

- unveränderte Übernahme: `accepted`,
- fachliche Bestätigung: `confirmed`,
- redaktionelle Änderung: `corrected`.

## 4.4 Gemeinsame stabile Sheet-Identität

Alle Sheet-basierten Writebacks verwenden einen gemeinsamen Resolver und nie blind eine gespeicherte Zeilennummer.

### Inbox-Kandidaten

Auflösungsreihenfolge:

1. explizite stabile Kandidaten-ID (`id_suggestion`, `id`, `event_id`),
2. kanonische `source_url` plus Datum,
3. Content-Fingerprint aus Titel, Datum, Ort und Quelle,
4. gespeicherte Zeilennummer nur als beschleunigender Hinweis, der erneut validiert werden muss.

Bei null oder mehreren Treffern wird nicht geschrieben. Das Backend liefert einen fachlich verständlichen Konflikt zurück.

### Content-Audit

Auflösungsreihenfolge:

1. `verification_key`,
2. `content_id + issue_code + source_fingerprint + content_fingerprint`,
3. `content_id + issue_code`, wenn eindeutig,
4. gespeicherte Zeilennummer nur bei bestätigter Identität.

## 4.5 Konflikt- und Überholt-Logik für Auditfälle

Vor einer Korrektur werden drei Zustände unterschieden:

### A. Fall ist aktuell

- Eventstand entspricht dem gespeicherten Content-Fingerprint.
- Auditfall ist eindeutig auffindbar.
- Korrektur wird geschrieben.
- Auditfall wird als `corrected` abgeschlossen.

### B. Auditzeile ist überholt, Eventproblem besteht aber noch

- Eventstand ist unverändert beziehungsweise kompatibel.
- Alte Auditzeile ist nicht mehr vorhanden.
- Korrektur darf nach erneuter Eventvalidierung geschrieben werden.
- Steuerzentralenfall wird mit `resolution_kind = superseded_audit_row` abgeschlossen.
- Der nächste Auditlauf bestätigt die Wirkung.

### C. Event wurde zwischenzeitlich verändert

- Content-Fingerprint weicht ab.
- Keine automatische Überschreibung.
- Backend liefert den aktuellen Eventstand und einen Konfliktstatus.
- Der im Dialog eingegebene Entwurf bleibt im Browser erhalten.
- Betreiber kann aktuelle und eigene Fassung vergleichen und bewusst neu bestätigen.

## 4.6 Idempotente Aktionen

Jede schreibende Aktion erhält eine `operation_id`.

Ziel:

- Doppelklicks,
- Browser-Retry,
- Timeout mit anschließendem erneutem Absenden

erzeugen keine doppelte Quellaktion.

Dafür wird eine kleine Operations-/Outbox-Struktur geführt. Mindestens gespeichert werden:

- `operation_id`,
- `case_id`,
- Aktion,
- Status `started / source_written / completed / failed`,
- Payload-Hash,
- Ergebnis beziehungsweise Fehler,
- Zeitstempel.

Vor externem Writeback wird die Operation reserviert. Eine bereits bekannte Operation liefert ihr vorhandenes Ergebnis zurück.

## 4.7 Kontrollierter Text-Lernpfad

Bei jeder erfolgreichen Beschreibungskorrektur werden gespeichert:

- Event-ID,
- Issue-Code,
- ursprünglicher aktueller Text,
- KI-/Audit-Vorschlag,
- finale Betreiberfassung,
- Text-Diff,
- Änderungskategorien,
- `decision_class = corrected`,
- Quellen- und Content-Fingerprint,
- Prompt-/Regelversion,
- Zeitpunkt.

Mögliche Änderungskategorien:

- Werbesprache entfernt,
- Quellenherleitung entfernt,
- ungesicherte Behauptung entfernt,
- lokale Relevanz ergänzt,
- konkrete Aktivität ergänzt,
- Zielgruppe ergänzt,
- gekürzt,
- präzisiert,
- Wiederholung entfernt,
- Tonalität sachlicher formuliert.

### Sicherheitsregel

Ein einzelner Text-Edit ändert keine dauerhafte globale Regel.

Der Lernpfad lautet:

```text
Einzelbeobachtung
→ Aggregation über mehrere unterschiedliche Events
→ befristete Prompt-Kontextregel
→ Fixture-/Regressionstest
→ Anwendung im nächsten Suchlauf
→ Recurrence-/False-Positive-Messung
→ verstärken, abschwächen, auslaufen lassen oder deaktivieren
```

Die vorhandene `Content_Search_Feedback`-Infrastruktur wird verwendet. Beobachtungen werden zunächst als nicht aktive Feedbacksignale geschrieben. Erst ab einer definierten Evidenzschwelle werden sie als begrenzter Prompt-Kontext verwendet. Das permanente Regelwerk wird nicht automatisch verändert.

## 4.8 Backlog-Quellenstatus

Die API liefert pro kanonischer Quelle:

- Status,
- zuletzt erfolgreiche Synchronisation,
- `seen`,
- `upserted`,
- Fehlermeldung, falls vorhanden.

Die Backlogansicht zeigt genau einen der Zustände:

- `Erfolgreich geladen · 0 offen`,
- `Erfolgreich geladen · N offen`,
- `Quelle konnte nicht geladen werden`.

Globale Statusmeldungen werden beim Viewwechsel bereichsspezifisch zurückgesetzt.

## 4.9 Verwaltung: Details und Live öffnen

Jeder Eintrag besitzt zwei getrennte Aktionen:

### Details

Schreibgeschütztes Panel mit:

- vollständigen Event-/Aktivitätsdaten,
- Quelle und Ticketlink,
- Beschreibung,
- Bildklassifikation,
- führender Quelle,
- Veröffentlichungsstatus,
- Änderungsverlauf,
- letzter bestätigter öffentlicher Stand.

### Live öffnen

Öffnet exakt die öffentliche Detailseite dieses Inhalts. Ist keine exakte Detail-URL vorhanden, wird dies sichtbar erklärt; es wird nicht still auf eine allgemeine Übersichtsseite ausgewichen.

## 4.10 Verständlicher Veröffentlichungsstatus

Statt einer nackten Zahl in Klammern werden Phasen angezeigt:

```text
Änderung validiert
Änderung in führender Quelle gespeichert
Öffentliche Aktualisierung gestartet
Öffentliche Wirkung wird geprüft · Versuch 6
Öffentliche Wirkung bestätigt
```

Bei einem Fehler:

```text
Änderung gespeichert
Öffentliche Aktualisierung nicht bestätigt
Prüfvorgang wurde angelegt
```

Der Pollingprozess bleibt auf genau eine `change_id` und ein Objekt begrenzt.

## 4.11 Frontend-Konsolidierung

Es wird kein weiterer Overlay-Controller ergänzt.

Zielstruktur:

- `control-center-environment.js`: ausschließlich Umgebungs-/Pfadauflösung,
- `control-center.js`: gemeinsamer State, API, Rendering, Dialoge, Review, Backlog, Verwaltung, Publikation und Projektstatus,
- `control-center-seo-embed.js`: ausschließlich isolierte iframe-/SEO-Integration.

Nach erfolgreicher Migration werden entfernt beziehungsweise vollständig in den Core integriert:

- `control-center-source-editors.js`,
- `control-center-final-bridge.js`,
- `control-center-stability.js`,
- `control-center-publication.js`,
- `control-center-development.js`,
- `control-center-integrations.js`.

Verbindliche Regeln:

- ein API-Wrapper,
- ein Zustandsmodell,
- ein Click-Dispatch,
- kein Capture-Interzept für dieselbe Aktion,
- keine nachträgliche fachliche Kartenkorrektur per `MutationObserver`,
- kein paralleler Cache derselben API-Daten,
- serverseitige Datenverträge statt DOM-Interpretation.

## 5. Betroffene Dateien

Voraussichtlich zu ändern:

### Frontend

- `steuerzentrale/index.html`
- `js/control-center.js`
- `js/control-center-environment.js` nur falls gemeinsame Core-API nötig
- `js/control-center-seo-embed.js` nur zur Erhaltung der bestehenden Isolation
- `css/control-center.css`
- `css/control-center-final.css`

Nach Migration zu entfernen:

- `js/control-center-source-editors.js`
- `js/control-center-final-bridge.js`
- `js/control-center-stability.js`
- `js/control-center-publication.js`
- `js/control-center-development.js`
- `js/control-center-integrations.js`

### Backend

- `api/control-center/_presentation.php`
- `api/control-center/case.php`
- `api/control-center/cases.php`
- `api/control-center/action.php`
- `api/control-center/_domain.php`
- `api/control-center/_workflow_contracts.php`
- `api/control-center/_sheet_inbox_source.php`
- `api/control-center/_writeback.php`
- `api/control-center/_contracts.php`
- `api/control-center/_schema.php`
- `api/control-center/content.php`
- `api/control-center/_content_history.php`
- `api/control-center/publication.php`
- `api/control-center/overview.php`

Neue, klar abgegrenzte Backend-Helfer sind zulässig, insbesondere:

- `_decision_contract.php`,
- `_sheet_identity.php`,
- `_editorial_feedback.php`,
- `_operations.php`.

### Lernprozess

- `data/content_ops_decision_classes.json` nur bei notwendiger Ergänzung ohne Taxonomiebruch
- `scripts/weekly-ki-websearch-to-manual-inbox.py`
- gegebenenfalls `scripts/content-quality-audit.py`
- `scripts/content-ops-control.py`
- semantische Fixture-Tests

### Tests und CI

- `tests/control_center_contracts_test.php`
- `tests/control_center_process_chain_test.php`
- neue Fixture-/Writebacktests
- `scripts/audit_control_center_product_contract.py`
- `.github/workflows/control-center-validation.yml`
- `scripts/browser-smoke.mjs` beziehungsweise eigener read-only Steuerzentralen-Smoke-Test

## 6. Testmatrix

## 6.1 Reine Vertrags- und Fixturetests

### Event-Entscheidungsreife

- vollständiger Ein-Tages-Event ist übernehmbar,
- Mehrtagesevent ohne Enddatum wird blockiert,
- fehlende sichere Uhrzeit ist bei dokumentierter Mehrtagelogik zulässig,
- fehlende Quelle blockiert,
- fehlende Beschreibung blockiert,
- fehlender Visual-Key oder Visual-Motiv blockiert,
- harte Dublette blockiert,
- Warnung ohne harten Konflikt bleibt sichtbar und übernehmbar.

### Inbox-Identität

- unveränderte Zeile,
- Zeile verschoben,
- neue Zeilen oberhalb eingefügt,
- gleiche URL mit anderem Datum,
- gleiche Titel-/Datums-Kombination an anderem Ort,
- kein Treffer,
- mehrere Treffer,
- gespeicherte Zeilennummer zeigt auf falschen Datensatz.

### Audit-Identität und Konflikt

- unveränderte Auditzeile,
- Zeile verschoben,
- gleicher Fall unter neuer Zeilennummer,
- Issue-Code ersetzt, Fingerprints identisch,
- Auditzeile nicht mehr vorhanden, Event unverändert,
- Event bereits anderweitig korrigiert,
- Event zwischenzeitlich anders geändert,
- eingegebener Entwurf bleibt bei Konflikt erhalten.

### Entscheidungen

- Ablehnung ohne `decision_class` wird serverseitig abgewiesen,
- UI startet ohne vorausgewählten Ablehnungsgrund,
- Kommentar ist optional und wird zusätzlich gespeichert,
- Zurückstellung ohne Datum wird abgewiesen,
- Übernahme erzeugt `accepted`,
- Korrektur erzeugt `corrected`,
- Doppelklick mit gleicher `operation_id` ist idempotent.

### Lernpfad

- Vorschlag und finale Fassung werden gespeichert,
- Diff wird erzeugt,
- Änderungskategorien werden gespeichert,
- Einzelbeobachtung verändert keine aktive Regel,
- wiederkehrendes Muster über mehrere Events erzeugt begrenzten Regelkandidaten,
- False Positive deaktiviert oder schwächt die Regel,
- Regelkontext bleibt mengenmäßig begrenzt.

### Verwaltung und Veröffentlichung

- Detailpanel zeigt den richtigen Inhalt,
- Live-Link zeigt auf die exakte Detailseite,
- Änderungsverlauf wird angezeigt,
- Pollingtext nennt den Versuch eindeutig,
- nur die konkrete Änderung wird verifiziert.

### Backlog

- erfolgreich leer,
- erfolgreich befüllt,
- Quellfehler,
- alte Statusmeldung verschwindet beim Viewwechsel.

## 6.2 Authentifizierter read-only Deploy-Smoke-Test

Der bestehende Browser-Smoke-Test deckt die Steuerzentrale nicht ab. Ein zusätzlicher read-only Test muss nach Staging- und Main-Deploy prüfen:

- Login funktioniert,
- keine zweite SEO-Passwortabfrage,
- fünf Hauptnavigationseinträge,
- Übersicht lädt,
- Prüfen lädt oder zeigt einen fachlich korrekten Leerzustand,
- Backlog zeigt Quellenstatus,
- Verwaltung lädt Events und Aktivitäten,
- Entwicklung zeigt Projektstatus und SEO-Embed,
- keine schreibende Aktion wird ausgelöst.

## 6.3 Kontrollierter Staging-Schreibtest

Schreibtests erfolgen nur gegen dedizierte Fixtures beziehungsweise eindeutig markierte Testdatensätze, nicht gegen reale redaktionelle Produktivfälle.

Pflichtfälle:

1. vollständigen Kandidaten übernehmen,
2. Kandidaten vor Übernahme korrigieren,
3. Kandidaten mit typisiertem Grund ablehnen,
4. Kandidaten zurückstellen,
5. verschobene Inbox-Zeile sicher auflösen,
6. veralteten Auditfall korrigieren,
7. Auditkonflikt ohne Datenverlust behandeln,
8. bestehendes Event ändern und öffentliche Wirkung bestätigen,
9. Detailpanel und exakten Live-Link prüfen,
10. Feedbackbeobachtung und nächsten Lernlauf nachweisen.

## 7. Patch-Reihenfolge innerhalb eines PRs

Die Umsetzung erfolgt intern in dieser Reihenfolge, bleibt aber ein zusammenhängendes Workpack und ein gemeinsamer Zielzustand.

### Phase 1 – Verträge und Tests zuerst

- Review-Datenvertrag,
- Pflicht-/Bedingungslogik,
- Entscheidungsmapping,
- Identitätsresolver,
- Konfliktzustände,
- Operations-/Idempotenzvertrag,
- Lernfeedbackvertrag,
- Fixturetests.

Noch keine UI-Umschaltung.

### Phase 2 – Backend-Härtung

- normalisierte Case-Details,
- serverseitiges Entscheidungsgate,
- stabile Sheet-Auflösung,
- Audit-Überholt-/Konfliktlogik,
- typisierte Entscheidungen,
- Operations-/Outbox,
- Feedbackpersistenz,
- Backlog-Quellenstatus,
- Verwaltungsdetaildaten.

### Phase 3 – Frontend-Konsolidierung

- vollständige Prüfkarte,
- Bearbeiten-und-Übernehmen,
- Ablehnungs-/Snooze-Dialog,
- konfliktfester Draft,
- Backlogstatus,
- Detailpanel und Live-Link,
- verständliche Veröffentlichung,
- Übernahme der bestehenden Projektstatus-/SEO-Funktionen,
- Entfernung der Overlay-Controller.

### Phase 4 – Lernintegration

- strukturierte Textfeedbackbeobachtung,
- Aggregation,
- begrenzter Prompt-Kontext,
- Recurrence-/False-Positive-Metriken,
- semantische Fixtures.

### Phase 5 – CI, Staging und Live

- vollständige CI,
- Staging-Deploy,
- automatisierter read-only Smoke-Test,
- kontrollierter Fixture-Schreibtest,
- manuelle Premium-Abnahme,
- Main-Merge,
- Main-Actions,
- Live-read-only-Abnahme,
- dokumentierter Abschluss.

## 8. Abnahmekriterien

Das Workpack ist erst abgeschlossen, wenn alle Punkte erfüllt sind:

- Main- und Staging-CI vollständig grün,
- Main- und Staging-Deploy inklusive Steuerzentralen-Smoke grün,
- vollständige Kandidatendaten sichtbar,
- serverseitiges Entscheidungsgate aktiv,
- Kandidat korrigierbar und danach sicher übernehmbar,
- Ablehnung ohne expliziten Grund unmöglich,
- typisierte Entscheidung wird in Quell- und Lernprozess übernommen,
- keine blind verwendete Sheet-Zeilennummer,
- veraltete Auditfälle werden sicher und ohne Textverlust behandelt,
- ein erfolgreicher Text-Edit erzeugt nachweisbares strukturiertes Feedback,
- ein einzelner Edit verändert keine globale Dauerregel,
- Backlogstatus unterscheidet leer und fehlerhaft,
- Verwaltung besitzt Details und exakten Live-Link,
- Veröffentlichungsstatus ist eindeutig,
- die überlagerten Controller sind konsolidiert,
- read-only Live-Abnahme vollständig bestanden,
- keine produktive Testschreibaktion erforderlich,
- Abschlussstatus und Restgrenzen sind im Repository dokumentiert.

## 9. Ausdrücklich getrenntes Folgeworkpack

Nach Abschluss dieses Workpacks folgt separat:

```text
SEO-/Reichweiten-Rückgang – datenbasierte Ursachenanalyse
```

Dort werden geprüft:

- genaue 28-Tage-Zeiträume,
- Datenfrische von Google und Bing,
- Suchanfragen mit Verlust,
- Zielseiten mit Verlust,
- saisonale Peaks im Vorzeitraum,
- Indexierungs-/Snippetänderungen,
- Trackingkonsistenz,
- Messbruch versus echter Nutzerrückgang.

Diese Analyse wird nicht mit dem redaktionellen Steuerzentralen-Patch vermischt.

## 10. Aktueller nächster Schritt

Nach Freigabe dieses Zielzustands wird auf demselben Branch zuerst Phase 1 umgesetzt:

```text
Verträge und Fixturetests
```

Erst wenn diese Tests den Zielzustand vollständig abbilden, beginnt die Backend- und UI-Änderung.
