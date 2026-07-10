# Steuerzentrale – Simulations- und Validierungsbericht

Stand: 2026-07-10  
Status: Gesamtprojekt-Integrationspatch intern simuliert; externe Laufzeitnachweise separat offen

## 1. Prüfprinzip

Der Bericht trennt verbindlich:

1. statische Syntax- und Vertragsprüfung,
2. tatsächlich ausgeführte interne Domänensimulation,
3. externe Laufzeitnachweise gegen Google Sheets, Apps Script, MySQL, GitHub, Search Console, GA4 und öffentliche Feeds.

Keine dieser Ebenen wird als Ersatz für eine andere ausgegeben.

## 2. Integrierte Gesamtkette

Der aktuelle technische Zielpfad lautet:

```text
Content-Audit
→ Such- und Visual-Feedback
→ wöchentliche KI-Eventsuche
→ Manual-Puffer
→ KI-Intake
→ führende Sheet-Inbox
→ Steuerzentrale / Prüfen
→ Events-Sheet oder Anbieter-Datenbank
→ Deployment beziehungsweise öffentliche API
→ öffentlicher Event-/Aktivitätsbestand
→ Nutzer-Funnel
→ Content-Ops-, Search-Console- und GA4-Wirkung
→ Entwicklung und neue Arbeit
```

Wesentliche Integrationsregeln:

- Die Steuerzentrale liest die führende Sheet-Inbox direkt.
- `data/inbox.json` wird nur bei einem Fehler der Sheet-Inbox als Fallback verwendet.
- Jede Quellsynchronisation ist einzeln gekapselt; ein Quellfehler legt Arbeit an, blockiert aber nicht die gesamte Steuerzentrale.
- Sheet-Events und öffentlich sichtbare Anbieter-Events sind gemeinsam verwaltbar, behalten aber ihre jeweilige führende Quelle.
- Aktivitätsänderungen bleiben durch das explizite Repo-Gate geschützt.
- Content-Ops-, Lern-, Search-, Intake-, Search-Console- und GA4-Signale werden nur bei real vorhandener Datenbasis angezeigt.

## 3. Tatsächlich ausgeführte interne Simulationen

### 3.1 Event- und Aktivitätsverträge

Ausgeführt wurden:

- Eventfeld-Aliasse,
- vollständige Updateplanung vor dem Schreiben,
- Pflichtfeld-, Datums- und URL-Schutz,
- nicht vorhandene Zielspalten ohne falschen Erfolg,
- öffentliche Eventwerte mit abweichenden Feldnamen,
- Anbieter-Events mit zusammengesetztem Ortslabel,
- separate Verifikation von Adresse, offizieller Quelle und Ticketlink,
- erkannte Adressabweichung,
- Aktivitätsfeld-Whitelist,
- sichere Standardsperre des Aktivitätseditors,
- Veröffentlichungszustände `failed`, `blocked`, `waiting`, `confirmed`.

### 3.2 Arbeitslebenszyklus

Geprüft wurden unter anderem:

```text
open → in_progress → waiting → open
open → blocked → open
open → snoozed → open
open → done → open
new → task/open
decision_required → done
new → rejected
```

Zusätzlich:

- unzulässige Aktion für aktuellen Status,
- unbekannte Aktion.

### 3.3 Prozessgesundheit

Geprüft wurde die Unterscheidung zwischen:

- erfolgreichem Lauf ohne Folgearbeit,
- erfolgreichem Lauf mit fachlicher Folgearbeit,
- technischem Fehler,
- fehlendem beziehungsweise veraltetem Lauf.

`action_required=true` gilt nicht als technischer Fehler, wenn ein Lauf erfolgreich Prüffälle oder Aufgaben erzeugt hat.

### 3.4 Abhängige KI-Suchkette

Ein eigener Test simuliert:

- Suchlauf erzeugt Kandidaten, aber Intake fehlt → Aufmerksamkeit,
- Intake ist älter als der Suchlauf → Aufmerksamkeit,
- Intake ist neuer als der Suchlauf → Kette bestätigt,
- Suchlauf erzeugt keine Kandidaten → Intake ist nicht erforderlich.

Die erste Ausführung fand einen realen Fehler:

```text
new DateTimeImmutable('')
```

liefert den aktuellen Zeitpunkt statt eines Fehlers. Ein fehlender Intake-Zeitstempel wurde dadurch fälschlich als aktueller Intake erkannt. Der Vertrag prüft nun vor dem Parsen ausdrücklich auf einen nichtleeren Zeitstempel.

## 4. Ausführungsergebnis

Lokal tatsächlich ausgeführt:

```text
No syntax errors detected in api/control-center/_contracts.php
No syntax errors detected in api/control-center/_github_repo.php
No syntax errors detected in api/control-center/_workflow_contracts.php
No syntax errors detected in api/control-center/_content_ops.php
No syntax errors detected in api/control-center/_process_chain.php
No syntax errors detected in tests/control_center_contracts_test.php
No syntax errors detected in tests/control_center_process_chain_test.php
=== Control Center Contract Simulation: OK ===
=== Control Center Process Chain Simulation: OK ===
```

Zusätzlich wurde der aktuelle Integrationscontroller mit `node --check` geprüft. Die JavaScript-Syntaxprüfung war erfolgreich.

Die lokale Ausführung verwendet rekonstruierten Code derselben reinen Verträge. Die vollständige Repository-Syntax- und Produktvertragsprüfung ist zusätzlich im GitHub-Workflow hinterlegt; ein grüner Actions-Lauf ist über den verfügbaren Connector noch nicht belegt.

## 5. Qualitäts- und Entwicklungsberechnung

Der Entwicklungsbereich berechnet die Eventqualität jetzt gemeinsam über:

1. veröffentlichte redaktionelle Sheet-Events,
2. tatsächlich öffentlich sichtbare Anbieter-Events.

Für Anbieter-Events werden dieselben Sichtbarkeitsbedingungen wie im öffentlichen API-Endpunkt verwendet:

- genehmigt,
- Freigabezeitpunkt vorhanden,
- zukünftiges Datum,
- Titel vorhanden,
- bestätigter und benannter Ort.

Aktivitäten besitzen eine separate Vollständigkeitsquote. Dadurch werden Event- und Aktivitätsqualität nicht mehr vermischt.

## 6. Fehler- und Degradationsverhalten

### Quellfehler

Eine gestörte Quelle:

- erzeugt einen deduplizierten blockierten Arbeitsfall,
- wird im Systemstatus sichtbar,
- verhindert nicht die Nutzung anderer Quellen und Bereiche.

### Sheet-Inbox

- verfügbar → JSON-Fallback bleibt im Standby,
- nicht verfügbar → JSON-Fallback wird aktiviert,
- paralleler Import beider Quellen wird verhindert.

### Prozessfehler

- technische Fehler oder überfällige Kernläufe → blockierte Arbeit,
- erfolgreicher Folgelauf → technischer Arbeitsfall wird automatisch abgeschlossen,
- fehlender Suchlauf→Intake-Handoff → technische Aufmerksamkeit.

## 7. Öffentliche Inhaltsquellen

### Redaktionelle Events

```text
Steuerzentrale
→ Events-Sheet
→ Apps-Script-Deployment
→ /data/events.json
→ Feldvergleich
→ Bestätigung
```

### Anbieter-Events

```text
Steuerzentrale
→ transaktionaler submissions-Writeback
→ /api/events/public.php
→ Feldvergleich einschließlich Adresse, Quelle und Ticketlink
→ Bestätigung
```

### Aktivitäten

```text
Steuerzentrale
→ explizites Repo-Gate
→ SHA-geschützter GitHub-Commit
→ /data/offers.json
→ Feldvergleich
→ Bestätigung
```

## 8. Automatische CI-Prüfung

`.github/workflows/control-center-validation.yml` prüft nun:

- PHP-Syntax aller Control-Center-Endpunkte und des öffentlichen Anbieter-Event-Endpunkts,
- JavaScript-Syntax aller fünf UI-Skripte,
- beide ausführbaren Domänensimulationen,
- Produktvertragsaudit,
- JSON-Gültigkeit,
- Pflichtdateien,
- Zugriffsschutz,
- direkte Sheet-Inbox und echten Fallback,
- abhängige KI-Suchlauf→Intake-Kette,
- Content-Ops-/Lern-/Funnel-Verträge,
- alle drei öffentlichen Inhaltspfade.

Ein grüner GitHub-Actions-Lauf ist über den aktuell verfügbaren Connector noch nicht belegt und wird daher nicht behauptet.

## 9. Gegen den Premium-Zielzustand

| Zielkriterium | Interner Stand |
|---|---|
| klare Informationsarchitektur | umgesetzt |
| direkte KI-/Sheet-Inbox-Integration | umgesetzt |
| echter, nicht paralleler JSON-Fallback | umgesetzt |
| Quellfehlertoleranz | umgesetzt |
| abhängige Suchlauf→Intake-Prüfung | umgesetzt und simuliert |
| kompakter Arbeitsbereich | umgesetzt |
| einheitlicher Arbeitslebenszyklus | umgesetzt und simuliert |
| Sheet-Eventverwaltung | umgesetzt und intern simuliert |
| Anbieter-Eventverwaltung | umgesetzt und intern simuliert |
| Aktivitäts-Repo-Writeback | umgesetzt, durch Server-Gate geschützt |
| öffentliche Verifikation aller Inhaltspfade | technisch umgesetzt |
| Lernwirkungsanzeige | Content-Ops-Vertrag integriert |
| Prozessstatus | integriert |
| Search-/Intake-Wirkung | integriert |
| Search Console und GA4 | bei vorhandenen Betriebsdaten integriert |
| kombinierte öffentliche Eventqualität | umgesetzt |
| separate Aktivitätsqualität | umgesetzt |
| alte Inbox vollständig abgelöst | nein, Push-/Diagnose-Restfunktionen |
| reale externe Gesamtkette | noch auf Staging beziehungsweise kontrolliert auf main nachzuweisen |

## 10. Externe Nachweise

Nicht intern simulierbar sind:

- reale Google-Sheets-Berechtigungen,
- tatsächlicher Apps-Script-Deploy,
- Staging-MySQL-Schema und vorhandene Content-Ops-Datensätze,
- reale öffentliche Feed-/API-Aktualisierung,
- GitHub-Secret und dessen konkrete Rechte,
- aktivierter Aktivitätseditor,
- Browserdarstellung auf den Zielgeräten,
- tatsächliche Search-Console-/GA4-Daten,
- realer Nutzer-Funnel.

## 11. Ergebnis

Der Gesamtprojekt-Integrationspatch ist intern konsistent modelliert. Die reinen Event-, Aktivitäts-, Workflow-, Veröffentlichungs- und Prozesskettenverträge wurden erfolgreich simuliert. Die Simulation hat einen realen Zeitstempelfehler gefunden und dessen Korrektur bestätigt.

Weitere konzeptionelle Integration ist vor dem externen Staging-Nachweis nicht erforderlich. Eine Main-Freigabe folgt daraus noch nicht; sie setzt den realen Lauf der externen Systeme und die öffentliche Wirkungskontrolle voraus.
