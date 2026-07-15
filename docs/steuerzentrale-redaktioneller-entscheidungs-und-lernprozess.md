# Steuerzentrale – redaktioneller Entscheidungs- und Lernprozess

Stand: 2026-07-15  
Basis: `staging` ab Commit `b77277fefd0588f285e6dc4df1de564cd5d90ba7`  
Workpack-Status: `PHASE_1_CONTRACTS_AND_FIXTURES_IMPLEMENTED`  
Freigabestatus: `STAGING_ONLY / LIVE_FUNCTIONAL_ACCEPTANCE_BLOCKED`

## Ziel

Dieses Workpack härtet den gesamten redaktionellen Entscheidungsweg der Steuerzentrale:

```text
Kandidat oder Qualitätsfall
→ vollständige Entscheidungsgrundlage
→ serverseitig geprüfte Entscheidung
→ stabil aufgelöste führende Quelle
→ nachvollziehbarer Abschluss oder Veröffentlichung
→ typisiertes Feedback
→ kontrollierter Lerneffekt
```

SEO-/Reichweitenursachen bleiben ein getrenntes Folgeworkpack.

## Freeze-Grenze

Bis zur vollständigen Staging-Abnahme gilt:

- keine Main- oder Live-Änderung,
- keine produktiven Schreibaktionen nur zu Testzwecken,
- keine Abnahme als abgeschlossen,
- Tests nur mit Fixtures oder eindeutig markierten Staging-Testdaten.

## Bestätigte strukturelle Lücken

1. Neue Eventkarten zeigen nicht alle für eine sichere Übernahme nötigen Daten.
2. Normale Inbox-Kandidaten sind vor der Übernahme nicht editierbar.
3. Sheet-Inbox-Aktionen verwenden aktuell eine gespeicherte Zeilennummer ohne erneute Identitätsprüfung.
4. Content-Audit-Fälle werden nur über `content_id + issue_code` aufgelöst, obwohl Fingerprints vorhanden sind.
5. Ablehnungen besitzen keine serverseitig verpflichtende `decision_class`.
6. Redaktionelle Textänderungen erzeugen noch keinen vollständigen Diff-/Lernvertrag.
7. Backlog unterscheidet „leer“ und „nicht geladen“ nicht eindeutig.
8. Verwaltung besitzt kein echtes Detailpanel und der Button „Vorschau“ ist nur ein Link.
9. Veröffentlichungs-Polling wird als missverständliche Zahl dargestellt.
10. Mehrere Frontendcontroller überlagern dieselben Aktionen und DOM-Bereiche.

## Premium-Zielverträge

### Event-Entscheidungsreife

Harte Pflichtwerte:

- Titel,
- Startdatum,
- Stadt oder eindeutiger lokaler Bezug,
- Ort,
- Kategorie,
- offizielle eventbezogene Quelle,
- finale Beschreibung,
- `visual_key`,
- `visual_motif`,
- kein harter Dublettenkonflikt.

Bedingte Werte:

- Enddatum bei Mehrtagesevents,
- sichtbarer Grund bei fehlender sicherer Uhrzeit,
- Adresse, wenn für Auffindbarkeit/Route erforderlich,
- Ticketlink, wenn Buchung erforderlich.

Die Runtime muss später ein serverseitiges `decision_gate` liefern. Eine UI-Sperre allein reicht nicht.

### Entscheidungen

Kanonische Taxonomie bleibt `data/content_ops_decision_classes.json`.

- Übernahme unverändert: `accepted`
- fachlich bestätigt: `confirmed`
- redaktionell geändert: `corrected`
- Zurückstellen: `snoozed` plus Wiedervorlagedatum
- Ablehnen: verpflichtende typisierte Klasse, optionaler Kommentar

Ablehnungsklassen:

- `duplicate`
- `rejected_not_event`
- `rejected_not_public`
- `rejected_not_local`
- `rejected_source_weak`
- `rejected_low_value`
- `rejected_commercial`

### Stabile Sheet-Identität

Inbox-Auflösung:

1. stabile Kandidaten-ID,
2. kanonische Quell-URL plus Datum,
3. Content-Fingerprint,
4. gespeicherte Zeilennummer nur als erneut validierter Hinweis.

Audit-Auflösung:

1. `verification_key`,
2. vollständige Audit-Fingerprints,
3. eindeutiges `content_id + issue_code`,
4. Zeilennummer nur bei bestätigter Identität.

Null oder mehrere Treffer blockieren den Writeback.

### Audit-Konflikte

- Auditzeile aktuell: normal korrigieren.
- Auditzeile überholt, Event unverändert: Korrektur nach erneuter Eventvalidierung zulässig; Abschluss `superseded_audit_row`.
- Event zwischenzeitlich verändert: nicht überschreiben; aktuellen Stand und Konflikt liefern; Betreiberentwurf erhalten.

### Idempotenz

Jede schreibende Aktion erhält später eine `operation_id`. Gleiche ID plus gleicher Payload liefert das vorhandene Ergebnis. Gleiche ID plus anderer Payload ist ein Konflikt.

### Kontrollierter Text-Lernpfad

Gespeichert werden sollen später:

- aktueller Text,
- KI-/Audit-Vorschlag,
- finale Betreiberfassung,
- Wort-Diff,
- Änderungskategorien,
- Event-/Issue-Identität,
- Prompt-/Regelversion,
- Fingerprints.

Ein einzelner Edit bleibt Beobachtung. Erst ein wiederkehrendes Muster über mehrere unterschiedliche Events darf Regelkandidat werden. Auch ein Regelkandidat ändert das permanente Regelwerk nie automatisch.

## Phase 1 – umgesetzt

Phase 1 ist rein additiv und nebenwirkungsfrei. Noch keine produktive Runtime bindet diese Module ein.

Neu:

- `data/control_center_editorial_contract.json`
- `api/control-center/_decision_contract.php`
- `api/control-center/_editorial_contracts.php`
- `api/control-center/_sheet_identity.php`
- `api/control-center/_operations.php`
- `api/control-center/_editorial_feedback.php`
- `tests/fixtures/control_center_editorial_cases.json`
- `tests/control_center_editorial_contracts_test.php`
- `tests/control_center_sheet_identity_test.php`
- `scripts/audit_control_center_editorial_contracts.py`
- `.github/workflows/control-center-editorial-contracts.yml`

Abgedeckte Fixtures:

- vollständiger Event,
- Mehrtagesevent ohne Enddatum,
- fehlende Uhrzeit mit dokumentiertem Grund,
- fehlende Quelle,
- harte Dublette,
- Ablehnung ohne Klasse,
- Snooze ohne Datum,
- korrigierte Übernahme,
- Operation-Replay und Operation-Konflikt,
- Einzeledit ohne Regelaktivierung,
- wiederkehrendes Muster nur als Regelkandidat,
- verschobene Inbox-Zeile,
- falscher Zeilenhinweis,
- mehrdeutige Inbox-Zeilen,
- verschobene Auditzeile,
- überholte Auditzeile bei unverändertem Event,
- Eventkonflikt nach zwischenzeitlicher Änderung,
- mehrdeutiger Auditfall.

## Noch nicht umgesetzt

Phase 2:

- Runtime-Einbindung der Verträge,
- serverseitiges Entscheidungsgate,
- stabiler realer Sheet-Writeback,
- Audit-Überholt-/Konfliktlogik,
- Operationspersistenz,
- Feedbackpersistenz,
- Backlog-Quellenstatus,
- Verwaltungsdetaildaten.

Phase 3:

- vollständige Prüfkarte,
- Bearbeiten und übernehmen,
- typisierte Dialoge,
- konfliktfester Entwurf,
- Detailpanel und exakter Live-Link,
- klare Veröffentlichungsphasen,
- Konsolidierung der Frontendcontroller.

Phase 4:

- Feedbackaggregation in den Suchlauf,
- begrenzter Prompt-Kontext,
- Recurrence-/False-Positive-Metriken.

## Nächster Gate

Phase 2 beginnt erst, wenn:

1. beide PHP-Testpakete lokal und in GitHub Actions grün sind,
2. der Python-Vertragsaudit grün ist,
3. der PR ausschließlich gegen `staging` läuft,
4. keine Main-/Live-Datei verändert wurde.
