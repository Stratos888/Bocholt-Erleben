# Steuerzentrale / CityArt – Inbox-Schema-Guard: Implementierungs- und Testevidence

Stand: 2026-07-17  
Workpack-Branch: `agent/control-center-cityart-forensics`  
Zielbranch: `staging`  
Ausgangs-SHA: `757b48788ab1eff99be19002bbaf65348e6311f0`

## Zweck

Dieses Evidence-Artefakt dokumentiert den nach der read-only Forensik freigegebenen Implementierungsstand. Es ersetzt nicht die getrennte Ursachenanalyse unter:

`docs/forensics/steuerzentrale-cityart-read-only-analysis-2026-07-17.md`

Bis zum Abschluss des Code-Gates wurden keine Werte in `Inbox`, `Inbox_Staging` oder anderen externen Ressourcen verändert.

## Belegte Ursache

`Inbox_Staging` enthält in Zeile 2 fachlich korrekte Werte in S bis X, während die Headerzellen S1 bis X1 leer sind. Die bisherige Synchronisation arbeitete headerbasiert und interpretierte die dadurch unsichtbaren Werte als fehlende Fachangaben.

Die Staging-/Live-Trennung war korrekt. Es fehlte jedoch ein gemeinsamer, fail-closed Schema-Vertrag für beide Lese- und Schreibwege.

## Implementierter Zielzustand

### Zentraler Schema-Vertrag

`api/control-center/_inbox_schema.php` ist jetzt die gemeinsame side-effect-freie Quelle für:

- erforderliche Kernheader `status`, `title` und `date`;
- mindestens eine stabile Identität aus `id_suggestion`, `id`, `event_id`, `source_url` oder `url`;
- kanonische Feldaliases;
- eindeutige normalisierte Header;
- Erkennung doppelter Header;
- Erkennung nichtleerer Zellen unter leeren Headern;
- strukturierte Diagnose mit Tab, Spalte und betroffenen Zeilen;
- Aufbau des kanonischen Tabellen- und Zeilenmodells.

Unbekannte zusätzliche, korrekt benannte Spalten bleiben erlaubt.

### Fail-closed Synchronisation

`api/control-center/_sheet_inbox_source.php` validiert den kompletten Tabellenrohzustand vor dem ersten Upsert oder Reconcile.

Bei einer Schemaabweichung:

- wird kein unvollständiger Eventpayload erzeugt;
- werden keine irreführenden fachlichen Reviewaufgaben erzeugt;
- wirft der Source-Reader eine konkrete Diagnose;
- `be_cc_safe_source_sync` erzeugt daraus den bestehenden technischen Datenquellenfall;
- der leere JSON-Fallback erzeugt keine zweite CityArt-Kopie.

### Fail-closed Writeback

`api/control-center/_inbox_decision_writeback.php` verwendet denselben Parser. Direkte Entscheidungen und Review-Teilaktionen können daher nicht auf einem strukturell ungültigen Tab schreiben oder bestehende Werte unter leeren Headern überschreiben.

Die bestehende Fähigkeit, tatsächlich fehlende kanonische Header und Werte atomar in einem verifizierten Batch anzulegen, bleibt erhalten.

### Eindeutiger Aktionsvertrag

Im Event-Review-Backend ist `edit_and_approve` nur noch aktiv, wenn `decision_gate.ready=true` ist. Damit stimmen Backend, Presentation und Frontend für offene Aufgaben überein.

## Reales Raw-Sheet-Replay

`tests/control_center_inbox_schema_contract_test.php` enthält den belegten Rohzustand:

- Header A bis R;
- CityArt in Zeile 2;
- Werte S bis X;
- leere Header S1 bis X1.

Der Test bestätigt:

1. der fehlerhafte Rohzustand wird mit Tab, Spalten S und X sowie Zeile 2 fail-closed abgelehnt;
2. doppelte Header werden eindeutig diagnostiziert;
3. nach Benennung von S1 bis X1 werden Zeit, Zeitstatus, Zeitdetails, Visual-Key, Motiv und Asset korrekt gelesen;
4. der vollständig benannte CityArt-Datensatz ist entscheidungsreif;
5. `edit_and_approve` ist bei offenen Blockern deaktiviert und im Ready-Zustand aktiviert;
6. fehlende neue kanonische Spalten können weiterhin atomar geplant werden.

## CI-Evidence

Auf Head `5780a3b5c9e6b8a6d424e7b610ede5d3f3ca1238` waren erfolgreich:

- `Control Center Editorial Contracts`;
- `Control Center Validation`;
- `Control Center Contract Diagnostics`;
- `PR Gate`.

Die beiden fachlichen Workflows führen den neuen PHP-Syntaxtest und das Raw-Sheet-Replay explizit aus.

## Dateiscope

Runtime und Tests:

- `api/control-center/_inbox_schema.php`;
- `api/control-center/_sheet_inbox_source.php`;
- `api/control-center/_inbox_decision_writeback.php`;
- `api/control-center/_event_review_task_result.php`;
- `tests/control_center_inbox_schema_contract_test.php`;
- `.github/workflows/control-center-editorial-contracts.yml`;
- `.github/workflows/control-center-validation.yml`.

Dokumentation:

- read-only Forensik;
- dieses Evidence-Artefakt.

Keine UI-, CSS-, Visual-Pool-, Event-, Deploy- oder Live-Datenänderung.

## Noch gesperrtes externes Gate

Die Codeintegration allein verändert keine Sheetdaten. Erst nach:

1. final grüner PR-CI;
2. Merge nach `staging`;
3. grünem Staging-Deploy;
4. erneuter read-only Vorherprüfung;

darf exakt `Inbox_Staging!S1:X1` gesetzt werden auf:

| Zelle | Wert |
|---|---|
| S1 | `visual_key` |
| T1 | `visual_motif` |
| U1 | `visual_asset_id` |
| V1 | `visual_asset_role` |
| W1 | `time_status` |
| X1 | `time_details` |

Unmittelbar davor müssen weiterhin gelten:

- Tab exakt `Inbox_Staging`;
- Headerzeile exakt 1;
- CityArt exakt Zeile 2;
- S1:X1 leer;
- S2:X2 unverändert;
- keine weitere aktive Reviewzeile mit abweichender Semantik.

`Inbox` bleibt read-only und darf durch dieses Workpack nicht verändert werden.

## Abnahme nach der kontrollierten Migration

- S1:X2 werden unmittelbar zurückgelesen;
- Staging synchronisiert ohne Datenquellen-Schemafehler;
- CityArt erkennt `time=11:00`, `time_status=fixed_time` und `time_details=11:00–18:00 Uhr`;
- Visual-Key, Motiv und `motif-gap-art-market-01` werden erkannt;
- kein Zeit- oder Visual-Pflichtfeldblocker bleibt bestehen;
- kein Vollbearbeitungs-CTA erscheint bei offenen Aufgaben;
- Reload bleibt idempotent;
- Live-Tab und Live-Steuerzentrale bleiben unverändert.

## Rollback

Vor der externen Migration wird `Inbox_Staging!A1:X2` erneut als Evidence gelesen. Bei abweichendem Ergebnis wird nicht geschrieben.

Falls die sechs Header wider Erwarten eine neue Staging-Störung auslösen, werden ausschließlich S1:X1 auf den dokumentierten Vorherzustand zurückgesetzt; S2:X2 werden nicht verändert. Anschließend wird der Code-PR über Git revertiert. Eine Live-Rollback-Aktion ist nicht zulässig, weil Live nicht Teil des Workpacks ist.
