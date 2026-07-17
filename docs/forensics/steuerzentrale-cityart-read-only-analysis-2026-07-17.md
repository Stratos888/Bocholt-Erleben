# Steuerzentrale / CityArt – read-only Forensik und Root-Cause-Entscheidung

Stand: 2026-07-17  
Zielbranch fuer spaetere Integration: `staging`  
Analysemodus: read-only  
Ausgangsstand: `staging` nach Merge von PR #82 (`757b48788ab1eff99be19002bbaf65348e6311f0`)

## 1. Zweck und Grenze

Diese Datei dokumentiert den nachweisbaren Ist-Zustand der CityArt-Verarbeitung in der Steuerzentrale. Die hier beschriebene Analysephase nahm keine externe Datenmutation vor.

Waehrend der Analyse wurden nicht ausgefuehrt:

- keine Aktion im CityArt-Fall;
- keine Aenderung in `Inbox` oder `Inbox_Staging`;
- kein Control-Center-Patch;
- kein Deploy;
- kein Merge nach `main`;
- keine produktive oder Staging-Schreibprobe.

Nach ausdruecklicher Freigabe wurde der daraus abgeleitete systemische Code-Workpack auf demselben isolierten Branch umgesetzt. Implementierung, Tests und das weiterhin gesperrte externe Migrationsgate sind getrennt dokumentiert unter:

`docs/evidence/control-center-cityart-inbox-schema-guard-2026-07-17.md`

## 2. Relevanter Zielvertrag

PR #78 fuehrte eine ausnahmebasierte Eventpruefung ein. Fuer CityArt war fachlich vorgesehen:

1. Beginnzeit klaeren oder einen typisierten Wartezustand setzen;
2. das konkrete ready-Asset `motif-gap-art-market-01` bestaetigen;
3. erst danach die Gesamtuebernahme freigeben.

PR #79 trennte anschliessend die Laufzeitziele:

- Staging liest und schreibt ausschliesslich `Inbox_Staging`;
- Live liest und schreibt ausschliesslich `Inbox`;
- unbekannte Umgebungen werden blockiert.

Die Trennung des Ziel-Tabs ist im aktuellen Code korrekt umgesetzt. Nicht mit umgesetzt wurde ein belegter Schema-/Migrationsvertrag fuer den neu verwendeten Staging-Tab.

## 3. Aktueller Quellzustand

### 3.1 `Inbox_Staging`

Im fuehrenden Spreadsheet existiert aktuell genau ein aktiver Review-Datensatz fuer CityArt:

- Tabellenzeile: 2;
- Status: `review`;
- `id_suggestion`: leer;
- Titel: `Bocholter Kulturtage 2026 - Kunstmarkt CityArt und kuenstlerische Mitmach-Staende fuer Kinder und Jugendliche`;
- Datum: `2026-08-30`;
- Beginnzeit: `11:00`;
- Stadt: `Bocholt`;
- Ort: `Markt vor dem Historischen Rathaus`;
- Kategorie: `Kinder & Familie`;
- Quelle und Event-URL: offizielle Bocholt-Seite;
- Notiz: `staging acceptance; official time 11:00-18:00`.

Die benannten Header enden bei Spalte R (`created_at`). In den anschliessenden Spalten S bis X stehen dennoch Werte:

| Spalte | Physischer Wert | Erwarteter kanonischer Header |
|---|---|---|
| S | `art_exhibition_gallery` | `visual_key` |
| T | `art_market` | `visual_motif` |
| U | `motif-gap-art-market-01` | `visual_asset_id` |
| V | `specific` | `visual_asset_role` |
| W | `fixed_time` | `time_status` |
| X | `11:00–18:00 Uhr` | `time_details` |

Die Headerzellen S1 bis X1 sind leer. Die Werte sind daher physisch vorhanden, aber nicht kanonisch adressierbar.

### 3.2 `Inbox`

Der Live-Tab besitzt fuer S bis V die benannten Header:

- `visual_key`;
- `visual_motif`;
- `visual_asset_id`;
- `visual_asset_role`.

Die CityArt-Zeile im Live-Tab enthaelt darunter dieselben Visualwerte, aber keine Beginnzeit. Dieser Befund beweist eine Schemaabweichung zwischen Live und Staging. Er beweist ohne historischen Vorher-/Nachher-Snapshot nicht, durch welche konkrete fruehere Aktion die Live-Werte entstanden sind.

### 3.3 Lernsignal

`Content_Search_Feedback_Staging` enthaelt weiterhin das aktive Muster:

`event_source_has_time_but_dataset_missing_time`

CityArt ist dort neben einem zweiten Fall als Beispiel erfasst. Der aktuelle Staging-Datensatz besitzt inzwischen `time=11:00`; das Lernsignal dokumentiert den frueheren Extraktions-/Datenqualitaetsfehler weiterhin korrekt als historisches Beispiel.

## 4. Belegte Daten- und Identitaetskette

Die aktuelle Kette lautet:

```text
offizielle Bocholt-Quelle
→ Bocholt Events / Inbox_Staging, Zeile 2
→ headerbasierte Sheet-Synchronisation
→ control_cases.source_payload_json
→ Event-Review-Vertrag
→ Presentation API
→ Steuerzentralen-UI
```

### 4.1 Quellidentitaet

Der Sheet-Sync bestimmt `source_reference` in dieser Reihenfolge:

1. `id_suggestion`;
2. `id`;
3. `event_id`;
4. `source_url`;
5. `url`;
6. ersatzweise Zeilennummer.

Da CityArt keine `id_suggestion` besitzt, ist die offizielle URL die aktuelle stabile Quellidentitaet. Im Tab wurde nur eine passende CityArt-Zeile gefunden; eine Dublette im aktuellen `Inbox_Staging`-Bestand ist nicht belegt.

### 4.2 Synchronisation

Die Steuerzentralen-Uebersicht startet bei jedem Laden den Sheet-Sync. Der Sync:

- liest den umgebungsabhaengigen Tab;
- erkennt den Header;
- baut den Payload ausschliesslich aus Headername und Zellposition;
- ersetzt bei bestehendem Fall den gespeicherten `source_payload_json` durch den neu gelesenen Payload;
- berechnet den Reviewvertrag neu.

Unbenannte Spalten werden nicht als `visual_key`, `visual_motif`, `visual_asset_id`, `visual_asset_role`, `time_status` oder `time_details` erkannt.

## 5. Heute zu erwartender CityArt-Vertrag

Aus den aktuell benannten Spalten kann der Sync lesen:

- alle zentralen Pflichtangaben;
- `time=11:00`;
- aber keine Visualfelder aus S bis V;
- keinen `time_status` und keine `time_details` aus W und X.

Daraus folgt im damaligen Code deterministisch:

- keine Aufgabe `Beginnzeit klaeren`, weil `time` nicht leer ist;
- keine Aufgabe wegen Titel, Datum, Ort, Kategorie, Quelle oder Beschreibung;
- `visual_key` gilt als fehlend;
- es entsteht voraussichtlich genau die Aufgabe `Bildbereich festlegen` (`missing_visual_key`).

Die physisch vorhandenen Werte `art_exhibition_gallery`, `art_market` und `motif-gap-art-market-01` konnten den Vertrag nicht erreichen, solange ihre Header fehlten.

## 6. Visual-Prozess ist fachlich vorhanden

Der untersuchte Code besitzt bereits die CityArt-Regeln:

- Titel oder Beschreibung mit `Kunstmarkt` beziehungsweise `CityArt` → Motiv `art_market`;
- Visual-Key `art_exhibition_gallery`;
- ready Asset `motif-gap-art-market-01` im Event-Visual-Pool;
- direkte Aufgabe zur Bestaetigung des konkreten Assets.

Der Visual-Prozess scheiterte daher nicht an einer fehlenden CityArt-Regel oder einem fehlenden Asset. Er wurde durch die fehlende kanonische Headerzuordnung vor dem eigentlichen Motiv-/Assetresolver abgeschnitten.

## 7. Historischer CTA-Befund

Der fruehere Screenshot zeigte gleichzeitig:

- `Noch nicht uebernehmbar`;
- allgemeine Pflichtfeldblocker;
- `Bearbeiten und uebernehmen` als Hauptaktion.

Der aktuelle Code bildete diesen Widerspruch bereits vor dem Folgepatch nicht mehr in Presentation und Frontend ab:

- bei offenen Eventaufgaben ist die Hauptaktion `Offenen Punkt klaeren` beziehungsweise `Offene Punkte klaeren`;
- `Gesamtfassung bearbeiten` erscheint erst bei `decision_gate.ready=true`.

Der historische CTA-Befund war deshalb kein aktuell belegter Presentation-/Frontend-Codefehler mehr. Plausible historische Ursachen sind ein aelterer Frontend-Assetstand, ein aelterer Presentation-Payload oder ein Zwischenstand vor der finalen PR-#78-Integration. Ohne damaligen Runtime-Payload bleibt die exakte historische Ursache offen.

Im Backend-Reviewvertrag existierte weiterhin die interne Aktion `edit_and_approve` mit `enabled=true`, obwohl Presentation und UI sie bei offenen Eventaufgaben nicht anboten. Diese latente Vertragsinkonsistenz wurde im freigegebenen Folgepatch auf `decision_gate.ready` begrenzt.

## 8. Belegte Root Cause

### Primaere Root Cause

Die Staging-/Live-Trennung aus PR #79 wechselte das autoritative Staging-Ziel auf `Inbox_Staging`, ohne einen technisch erzwungenen Schema-Paritaets- und Migrationsvertrag fuer diesen Tab einzufuehren.

Der reale `Inbox_Staging`-Tab verletzte den vom Code vorausgesetzten Headervertrag:

- Daten standen unter leeren Headerzellen;
- der Sync arbeitete ausschliesslich headerbasiert;
- dadurch wurden vorhandene Werte als fehlend bewertet.

### Sekundaere Root Cause

Die Laufzeit akzeptierte einen teilweise gueltigen Header stillschweigend. Sie erkannte zwar `status` und `title`, pruefte aber nicht:

- ob nichtleere Zellen unter leeren Headern existieren;
- ob die kanonischen Reviewspalten eindeutig benannt sind;
- ob der Staging-Tab strukturell mit dem erwarteten Vertrag kompatibel ist.

Statt fail-closed einen Datenquellen-/Schemafall zu erzeugen, baute sie einen unvollstaendigen fachlichen Payload und erzeugte irrefuehrende manuelle Aufgaben.

### Testluecke

Die vorhandenen Tests prueften:

- richtige Tabwahl pro Umgebung;
- keine Hartverdrahtung auf `Inbox`;
- synthetische Planung neuer Header;
- CityArt-Motiv- und Assetvertrag auf vollstaendig benannten Payloads.

Nicht geprueft wurden:

- reale Headerparitaet von `Inbox` und `Inbox_Staging`;
- Werte unter leeren Headerzellen;
- unvollstaendige oder verschobene Tab-Schemata;
- fail-closed Verhalten bei Schemaabweichungen;
- Replay eines rohen realen Sheet-Ausschnitts.

Diese Testluecke ist im freigegebenen Folgepatch durch ein Raw-Sheet-Replay geschlossen.

## 9. Bewertung gegen den Premium-Zielzustand

| Bereich | Analysierter Ist-Zustand | Premium-Soll | Entscheidung |
|---|---|---|---|
| Umgebungsrouting | Staging → `Inbox_Staging`, Live → `Inbox` | strikt getrennt | technisch erfuellt |
| Quellidentitaet | eindeutige offizielle URL, eine Zeile | stabile, belegte Identitaet | erfuellt |
| Uhrzeit | `time=11:00` benannt; Details unbenannt | strukturierte Startzeit plus Zeitraum | Schemakorrektur erforderlich |
| Visualdaten | Werte vorhanden, Header fehlen | kanonisch benannte Felder | Schemakorrektur erforderlich |
| Motiv-/Assetresolver | Regel und ready Asset vorhanden | direkte konkrete Bestaetigung | durch vorgelagerte Struktur blockiert |
| Aufgaben-UI | aktueller Code fokussiert offene Aufgaben | keine Vollformular-Abkuerzung | im Code erfuellt |
| Schemafehler | wurde als fachlicher Feldmangel interpretiert | fail-closed Systemfall | Codepatch freigegeben und umgesetzt |
| Tests | logische Payload-Fixtures | Raw-Sheet-Replay und Schema-Gate | Codepatch freigegeben und umgesetzt |

## 10. Erforderlicher nachhaltiger Workpack

Kein CityArt-Einzelhotfix. Der freigegebene Implementierungs-Workpack arbeitet tab- und vertragsweit.

### A. Kanonischer Sheet-Schema-Vertrag

- zentrale Liste der erforderlichen und optionalen Inbox-Header;
- eindeutige Aliasaufloesung;
- Erkennung doppelter Header;
- Erkennung nichtleerer Zellen unter leeren Headern;
- strukturierte Diagnose mit Tab, Headerzeile, Spalte und betroffenen Zeilen;
- fail-closed statt fachlich falscher Reviewaufgaben.

### B. Replay-Test aus realem Rohzustand

Fixture mit:

- Header A bis R;
- CityArt-Zeile mit Werten S bis X;
- leeren Headern S bis X.

Der Test reproduziert den belegten Rohzustand und verlangt eine eindeutige Schemaabweichung. Nach einer kontrollierten Schemakorrektur muss derselbe Datensatz die kanonischen Zeit- und Visualwerte liefern.

### C. Kontrollierte Staging-Schemareparatur

Erst nach gruenem Code-PR und dokumentiertem Vorherzustand:

- S1 = `visual_key`;
- T1 = `visual_motif`;
- U1 = `visual_asset_id`;
- V1 = `visual_asset_role`;
- W1 = `time_status`;
- X1 = `time_details`.

Vor der Mutation sind erneut zu pruefen:

- Tab exakt `Inbox_Staging`;
- Headerzeile exakt 1;
- CityArt exakt Zeile 2;
- S1:X1 weiterhin leer;
- S2:X2 entsprechen weiterhin den dokumentierten Werten;
- keine weiteren nichtleeren Reviewzeilen mit abweichender Spaltensemantik.

Die Reparatur darf nicht `Inbox` veraendern.

### D. Vertragsbereinigung

- Backendaktion `edit_and_approve` bei offenen Eventaufgaben nicht pauschal aktiv ausweisen;
- Presentation und Reviewvertrag auf einen eindeutigen Aktionsvertrag reduzieren;
- keine zweite UI-Entscheidungslogik mit abweichenden Freigaben.

### E. Abnahme

Nach Merge nach `staging` und gruenem Deploy:

1. Uebersicht synchronisiert `Inbox_Staging` ohne Schemafehler;
2. CityArt zeigt keine Zeitaufgabe mehr;
3. Visual-Key, Motiv und konkretes Asset werden erkannt;
4. der Fall ist entweder direkt entscheidungsreif oder zeigt nur die fachlich noch notwendige konkrete Bildbestaetigung;
5. kein Vollbearbeitungs-CTA bei offenen Aufgaben;
6. `Inbox` bleibt unveraendert;
7. Reload ist idempotent;
8. `PR Gate`, Control-Center-Tests und Browser-Smoke sind gruen.

## 11. Arbeitsentscheidung

Die read-only Analysephase ist abgeschlossen. Der isolierte Implementierungs-Workpack wurde nach ausdruecklicher Freigabe umgesetzt und ist durch das separate Evidence-Artefakt nachvollziehbar.

Bis zur Integration und zum gruenen Staging-Deploy bleiben weiterhin gesperrt:

- die externe Reparatur von `Inbox_Staging!S1:X1`;
- Aenderungen am Live-Tab `Inbox`;
- automatische fachliche Uebernahme des CityArt-Falls;
- Merge nach `main` innerhalb dieses Workpacks.
