# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10  
Status: finaler Härtungspatch umgesetzt; interne Gesamtsimulation und externe Staging-Freigabe getrennt

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
- mobil fokussierter Einzelfall,
- Desktop Queue plus Detail,
- fachliche Hauptaktionen und deutsche Statussprache.

### Arbeit

- Jetzt, Als Nächstes, Wartet, Blockiert, Backlog, Ideen und Archiv,
- kompakte aufklappbare Zeilen,
- gemeinsame Neuerfassung über `+ Neu`,
- Growth-Backlog- und kuratierte Repo-Workpack-Anbindung,
- Umwandlung ohne Doppelvorgang,
- Warte- und Blockadegründe,
- deduplizierte Publikationsprobleme.

Die Statusübergänge liegen in `api/control-center/_workflow_contracts.php`. Laufzeit und Simulation verwenden damit dieselbe Regelmenge.

### Eventverwaltung

- Suche ohne Fokusverlust,
- vollständige Vorabvalidierung,
- Aliasauflösung zur tatsächlichen Sheet-Spalte,
- gemeinsamer Google-Sheets-Batch,
- dauerhafter Änderungsverlauf,
- Deploy-Start,
- öffentliche Verifikation gegen `/data/events.json`,
- Abschluss erst nach bestätigter öffentlicher Wirkung,
- wartende oder blockierte Aufgabe bei Teilfehlern.

Die UI verwendet ausschließlich `Speichern und Aktualisierung starten`.

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

Die Freigabe ist absichtlich zweistufig:

1. `BE_GITHUB_REPO_TOKEN` und Repository-/Branch-Konfiguration,
2. explizit `BE_ACTIVITY_WRITEBACK_ENABLED=true`.

Ein Token allein aktiviert den Editor nicht. Ohne explizite Freigabe zeigt die Oberfläche den konkreten Sperrgrund.

### Entwicklung und SEO

- stündlich begrenzte Snapshots,
- Vergleich nur mit zeitlich getrenntem Snapshot,
- Deltas für Content-Vollständigkeit, fehlende Angaben, Prüfungen, Blockaden, technische SEO und Publikationsprobleme,
- technische SEO-Basis für zentrale Seiten,
- Title, Meta-Description, Canonical, Sitemap, robots.txt und öffentliche Datenfeeds,
- Search-Console-Lücke transparent gekennzeichnet,
- keine erfundenen Trend- oder Wirkungswerte.

### Systemstatus

- fachliche Aussage zuerst,
- technische Details nur eingeklappt,
- offene Prüfungen, aktive und blockierte Arbeit,
- manueller Deploy-Start,
- Anbieterbereich und Abmelden im Header-Menü.

## 3. Finaler Härtungspatch

Zusätzlich umgesetzt:

- Aktivitäts-Gate unterscheidet `nicht konfiguriert`, `konfiguriert aber nicht freigeschaltet` und `bereit`,
- GitHub-Konflikte erhalten eine verständliche fachliche Meldung,
- GitHub-Commit ohne bestätigte Commit-SHA gilt nicht als Erfolg,
- Event und Aktivität nutzen denselben Änderungs- und Publikationsstatus,
- Publikationsaufgaben werden objektartspezifisch dedupliziert und abgeschlossen,
- Event und Aktivität verwenden denselben Browser-Verifikationscontroller,
- alter unverifizierter Event-Speicherpfad entfernt,
- gemeinsamer ausführbarer Arbeitslebenszyklus eingeführt,
- CI prüft Aktivitäts-Gate, Workflow-Vertrag und beide Veröffentlichungspfade.

## 4. Interne Simulation

`tests/control_center_contracts_test.php` simuliert:

- Eventaliasse und Updateplanung,
- Pflichtfeld-, Datums- und URL-Validierung,
- öffentliche Eventwerte und Abweichungen,
- Aktivitätsfeld-Whitelist und URL-Schutz,
- sichere Standardsperre des Aktivitätseditors,
- sämtliche relevanten Arbeitsstatusübergänge,
- unzulässige und unbekannte Aktionen,
- Entwicklungsbewertung ohne erfundene Zeitreihe,
- Veröffentlichung `failed`, `blocked`, `waiting`, `confirmed`.

Der GitHub-Workflow führt zusätzlich PHP-/JavaScript-Syntax, Produktvertragsaudit, JSON- und Sicherheitsverträge aus.

## 5. Externe Grenzen

Intern nicht ersetzbar bleiben reale Nachweise für:

- Google-Sheets-Schreibzugriff,
- Apps-Script-Unlock und Deploy,
- MySQL-Schema auf Staging,
- tatsächliche öffentliche Feed-Aktualisierung,
- serverseitiges GitHub-Secret und dessen Rechte,
- Search Console,
- Browserdarstellung auf Zielgeräten.

Diese Punkte sind keine offenen Konzept- oder Code-Platzhalter mehr, sondern externe Konfiguration beziehungsweise Laufzeitnachweise.

## 6. Alte Inbox

`/inbox/` bleibt nur noch für ausdrücklich nicht migrierte Push- und Diagnosefunktionen. Reguläre Prüfung, Arbeit, Backlog, Eventverwaltung und Entwicklungsbeobachtung gehören in die Steuerzentrale.

Die Altansicht darf erst entfernt werden, wenn diese Restfunktionen entweder migriert oder bewusst dauerhaft als technische Diagnose abgegrenzt sind.

## 7. Freigabestatus

| Bereich | Status |
|---|---|
| Informationsarchitektur | umgesetzt |
| Prüfen | umgesetzt; reale Quellaktionen extern zu bestätigen |
| Arbeit | umgesetzt und über gemeinsamen Workflow-Vertrag simuliert |
| Eventverwaltung | technisch vollständig modelliert und intern simuliert |
| Event-Veröffentlichungsbestätigung | implementiert; externer Staging-Nachweis offen |
| Aktivitätsverwaltung | vollständig vorbereitet; Aktivierung über explizites Server-Gate |
| Entwicklung | Snapshots und technische SEO umgesetzt |
| Search Console | nicht angebunden; transparent abgegrenzt |
| Systemstatus | fachliche Grundsicht umgesetzt |
| Alte Inbox-Ablösung | Restdiagnose offen |
| CI-Nachweis | für neuesten Commit noch abzurufen |
| Main-Merge | erst nach externer Staging-Abnahme |

## 8. Premium-Regel

Der Code gilt intern als Premium-tauglich, wenn Syntax, Domänensimulation und Produktvertragsaudit grün sind. Der Gesamtprozess gilt erst nach realer Staging-Bestätigung als freigegeben. Ein interner Test ersetzt keine externe Wirkungskontrolle und eine externe Wirkungskontrolle ersetzt keine Domänensimulation.
