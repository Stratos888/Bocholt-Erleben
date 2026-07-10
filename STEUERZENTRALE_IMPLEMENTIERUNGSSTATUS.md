# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10  
Status: Gesamtprojekt-Integrationspatch umgesetzt; interne Verträge erweitert, externe Staging-Freigabe ausstehend

## 1. Verbindliche Hauptnavigation

1. Übersicht
2. Prüfen
3. Arbeit
4. Verwaltung
5. Entwicklung

Seltene Funktionen liegen im Header-Menü.

## 2. Umgesetzter Produkt- und Prozesskern

### Übersicht und Prüfen

- verdichtete Aufmerksamkeit statt vollständiger Kartenwiederholung,
- neue Inhalte, Qualitätsfälle, Anbieterentscheidungen und Freigaben,
- direkte Synchronisation aus dem führenden Sheet-Tab `Inbox`,
- `data/inbox.json` nur noch als deduplizierter Fallback,
- identische Quellreferenzen verhindern Doppelfälle,
- mobil fokussierter Einzelfall,
- Desktop Queue plus Detail,
- fachliche Hauptaktionen und deutsche Statussprache.

Damit ist die Kette `KI-Suche → Manual Intake → Sheet-Inbox → Steuerzentrale` nicht mehr vom erfolgreichen JSON-Export als alleiniger Quelle abhängig.

### Arbeit

- Jetzt, Als Nächstes, Wartet, Blockiert, Backlog, Ideen und Archiv,
- kompakte aufklappbare Zeilen,
- gemeinsame Neuerfassung über `+ Neu`,
- Growth-Backlog- und kuratierte Repo-Workpack-Anbindung,
- Umwandlung ohne Doppelvorgang,
- Warte- und Blockadegründe,
- deduplizierte Publikationsprobleme,
- fehlerhafte Automatisierungsprozesse werden als blockierte Arbeit angelegt,
- wieder gesunde Prozesse schließen ihren technischen Arbeitsfall automatisch.

Die Statusübergänge liegen in `api/control-center/_workflow_contracts.php`. Laufzeit und Simulation verwenden dieselbe Regelmenge.

### Eventverwaltung

Die Verwaltung umfasst jetzt beide öffentlichen Eventquellen:

1. redaktionelle Events aus dem führenden Events-Sheet,
2. genehmigte Anbieter-Events aus der Einreichungsdatenbank.

Für Sheet-Events:

- vollständige Vorabvalidierung,
- Aliasauflösung zur tatsächlichen Sheet-Spalte,
- gemeinsamer Google-Sheets-Batch,
- Deploy-Start,
- öffentliche Verifikation gegen `/data/events.json`.

Für Anbieter-Events:

- direkter, transaktionaler Writeback in `submissions`,
- Bearbeitung von Titel, Datum, Zeit, Ort, Adresse, Beschreibung, offizieller Quelle und Ticketlink,
- öffentliche Verifikation gegen `/api/events/public.php`,
- getrennte öffentliche Felder `source_url`, `ticket_url` und `address`,
- kompatibles zusammengesetztes Ortslabel.

Für beide Quellen gelten:

- dauerhafter Änderungsverlauf,
- Abschluss erst nach bestätigter öffentlicher Wirkung,
- wartende oder blockierte Aufgabe bei Teilfehlern,
- UI-Text `Speichern und Aktualisierung starten`.

### Aktivitätsverwaltung

Der dauerhafte Repo-Pfad ist vollständig vorbereitet und in denselben Veröffentlichungsprozess integriert:

- GitHub Contents API für `data/offers.json`,
- aktueller Datei-SHA als Konfliktschutz,
- erlaubte Feldmenge,
- Pflichtfeld- und URL-Validierung,
- versionierter Commit,
- Änderungsverlauf,
- wartender Publikationsvorgang,
- öffentliche Verifikation gegen `/data/offers.json`.

Die Freigabe bleibt bewusst zweistufig:

1. `BE_GITHUB_REPO_TOKEN` und Repository-/Branch-Konfiguration,
2. explizit `BE_ACTIVITY_WRITEBACK_ENABLED=true`.

### Entwicklung, Lernwirkung und Nutzer-Funnel

Der Bereich `Entwicklung` liest jetzt zusätzlich die bestehende Content-Ops-Betriebsdatenbank:

- `content_ops_run`,
- `content_ops_metric_daily`,
- `feedback_rule_effectiveness_daily`.

Dadurch werden sichtbar:

- letzte erfasste Läufe von KI-Suche, Intake, Content-Audit, Inbox-Bereinigung und Growth-Auswertung,
- verhinderte Wiederholungen durch Lern- und Deduplizierungsregeln,
- False Positives,
- wiederkehrende Probleme,
- wirksamste Lernregeln,
- vorhandene Search-Console-, GA4- und Wertsignal-Datensätze,
- Content-Vollständigkeit, technische SEO und Publikationsprobleme.

Search Console gilt nur dann als angebunden, wenn reale GSC-Datensätze in der Betriebsdatenbank vorliegen. Fehlende Daten werden weiterhin transparent benannt.

### Systemstatus

- fachliche Aussage zuerst,
- technische Details nur eingeklappt,
- letzter erfasster Zustand der automatisierten Prozesskette,
- offene Prüfungen, aktive und blockierte Arbeit,
- Prozessfehler mit Link zum gespeicherten Lauf, soweit vorhanden,
- manueller Deploy-Start,
- Anbieterbereich und Abmelden im Header-Menü.

## 3. Gesamtprojekt-Integrationspatch

Neu beziehungsweise korrigiert:

- führende Sheet-Inbox direkt angebunden,
- JSON-Inbox als deduplizierter Fallback erhalten,
- Content-Ops- und Lernwirkungsdaten in `Entwicklung` integriert,
- Growth-/Funnel-Daten nur bei tatsächlicher Datenbasis angezeigt,
- Prozessgesundheit in Systemstatus und Arbeit verbunden,
- alle öffentlich sichtbaren Eventquellen in der Verwaltung zusammengeführt,
- Anbieter-Event-Writeback transaktional umgesetzt,
- Anbieter-Event-API um verifizierbare Adresse, Quelle und Ticketlink ergänzt,
- Eventquellen nutzen denselben Änderungs- und Publikationsprozess,
- CI- und Produktverträge um Gesamtprojektintegration erweitert.

## 4. Interne Simulation und automatische Prüfung

`tests/control_center_contracts_test.php` simuliert zusätzlich:

- öffentliche Anbieter-Events mit getrennten URLs,
- zusammengesetztes Ortslabel plus separate Adresse,
- erkannte Adressabweichung,
- bestehende Event-, Aktivitäts-, Workflow- und Veröffentlichungsverträge.

`.github/workflows/control-center-validation.yml` prüft zusätzlich:

- Syntax des öffentlichen Anbieter-Event-Endpunkts,
- neuen Integrationscontroller,
- Sheet-Inbox-Adapter,
- Content-Ops-Adapter,
- Anbieter-Event-Adapter,
- Lernwirkung, Funnelmetriken und Prozessstatus,
- Anbieter-Event-Public-Verifikation.

Ein grüner GitHub-Actions-Lauf ist über den aktuell verfügbaren Connector weiterhin nicht belegt.

## 5. Externe Grenzen

Intern nicht ersetzbar bleiben reale Nachweise für:

- Google-Sheets-Lese- und Schreibzugriff,
- Apps-Script-Unlock und Deploy,
- MySQL-Tabellen und tatsächliche Content-Ops-Daten auf Staging,
- reale öffentliche Feed- und API-Aktualisierung,
- serverseitiges GitHub-Secret und dessen Rechte,
- aktivierter Aktivitätseditor,
- Browserdarstellung auf Zielgeräten,
- tatsächlich vorhandene Search-Console-/GA4-Daten.

## 6. Alte Inbox

`/inbox/` bleibt nur noch für ausdrücklich nicht migrierte Push- und Diagnosefunktionen. Reguläre Prüfung, Arbeit, Backlog, Eventverwaltung und Entwicklungsbeobachtung gehören in die Steuerzentrale.

## 7. Freigabestatus

| Bereich | Status |
|---|---|
| Informationsarchitektur | umgesetzt |
| KI-/Sheet-Inbox → Prüfen | direkt integriert; externer Laufzeitnachweis offen |
| Arbeit | umgesetzt; Prozessfehler integriert |
| Sheet-Eventverwaltung | technisch vollständig modelliert |
| Anbieter-Eventverwaltung | technisch vollständig integriert |
| Aktivitätsverwaltung | vollständig vorbereitet; Aktivierung über Server-Gate |
| Lernwirkungsanzeige | Content-Ops-Datenvertrag integriert |
| Prozessstatus | integriert |
| Search Console/GA4 | wird bei real vorhandenen Betriebsdaten angezeigt |
| Nutzer-Funnel | vorhandene Growth-Signale integriert; reale Datenbasis extern zu bestätigen |
| Alte Inbox-Ablösung | Restdiagnose offen |
| CI-Nachweis | für neuesten Commit noch nicht verfügbar |
| Main-Merge | erst nach externer Staging-Abnahme |

## 8. Premium-Regel

Der Code gilt intern erst dann als Premium-tauglich, wenn Syntax, Domänensimulation und Produktvertragsaudit grün sind. Der Gesamtprozess gilt erst nach realer Staging-Bestätigung als freigegeben. Ein interner Test ersetzt keine externe Wirkungskontrolle und eine externe Wirkungskontrolle ersetzt keine Domänensimulation.
