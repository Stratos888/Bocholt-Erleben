# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10  
Status: Workpack `Arbeit konsolidieren` umgesetzt; Gesamtprodukt noch nicht freigegeben

## 1. Verbindliche Dokumente

- `STEUERZENTRALE_ZIELBILD.md`
- `STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md`
- `STEUERZENTRALE_VORGANGSKATALOG.md`
- `STEUERZENTRALE_INFORMATIONARCHITEKTUR.md`
- `STEUERZENTRALE_SCREENVERTRAG.md`
- `STEUERZENTRALE_ABNAHMEMATRIX.md`
- `STEUERZENTRALE_BACKEND_GAP_ANALYSE.md`

Bei Widersprüchen gelten Zielbild, Gesamtprojekt-Integration und Abnahmematrix in dieser Reihenfolge als führend.

## 2. Bereits umgesetzt

### Technische Grundlage

- kanonisches MySQL-Modell `control_cases` und Verlauf,
- eindeutige Quellreferenzen und Deduplizierung,
- geschützte APIs für Übersicht, Vorgänge, Details und Aktionen,
- Quell-Writeback vor zentralem Abschluss,
- serverseitige Präsentationsnormalisierung,
- deutsche Status- und fallgerechte Aktionsbezeichnungen,
- CSS- und Produktvertrags-Governance,
- mobile Authentifizierung und permanente Navigation.

### Angebundene Prozesse

#### KI-/Sheet-Kandidaten

- Synchronisation,
- Übernahme über bestehenden Eventprozess,
- Ablehnung mit Grund,
- Zurückstellen mit Wiedervorlage.

#### Content-Audit

- nur echte Handlungsfälle in der Queue,
- Korrigieren, Verifizieren, Ablehnen und Zurückstellen,
- Writeback in den führenden Audit-Tab.

#### Anbieter-Einreichungen

- Synchronisation aus MySQL,
- Zahlungsfreigabe,
- wartender Zahlungsstatus,
- erneute Vorlage nach Zahlung,
- finale Veröffentlichung oder Ablehnung.

#### Growth-/Acquisition-Backlog

- bestehendes `Growth_Backlog`-Sheet bleibt führende Backlogquelle,
- offene Punkte werden dedupliziert in die Steuerzentrale synchronisiert,
- Priorität, Beschreibung, Nutzen und empfohlene Aktion bleiben erhalten,
- Bearbeiten, Abschließen und Verwerfen schreiben zuerst in das führende Sheet zurück,
- `Als Aufgabe starten` überführt denselben Vorgang ohne Kopie in eine konkrete Aufgabe,
- nach der Umwandlung bleibt der Vorgang auch bei erneutem Sync eine Aufgabe.

#### Kuratierte Repo-Workpacks

- bestätigte Repo-Arbeitspakete liegen in `data/control_center_repo_workpacks.json`,
- keine automatische Extraktion beliebiger TODOs oder historischer Dokumenttexte,
- Synchronisation über eindeutige Workpack-ID,
- Umwandlung in eine konkrete Aufgabe ohne Doppelkarte,
- zentrale Erledigungs- und Ablehnungszustände verhindern erneutes Öffnen im laufenden System.

Das Repo-Manifest ist eine kuratierte Importgrenze und kein allgemeiner GitHub-Issue-Ersatz. Änderungen am Manifest erfolgen weiterhin bewusst im Repo.

## 3. Sichtbare Oberfläche

Die Hauptnavigation lautet nun verbindlich:

1. Übersicht
2. Bearbeiten
3. Arbeit
4. Verwaltung
5. Menü

### Übersicht

- verdichteter Handlungsbedarf,
- keine vollständige Wiederholung der Bearbeiten-Queue,
- konkrete aktive Arbeit wird separat vom Backlog gezählt,
- Backlog und Ideen erzeugen keine roten Hauptbadges.

### Bearbeiten

- mobile fokussierte Einzelbearbeitung,
- Desktop Queue plus Detail,
- fallgerechte Hauptaktionen,
- technische Details nachrangig.

### Arbeit

Unteransichten:

- Jetzt,
- Als Nächstes,
- Wartet,
- Blockiert,
- Backlog,
- Ideen,
- Archiv.

Funktionen:

- Aufgabe, Idee und Backlogpunkt anlegen,
- Aufgabe starten,
- auf Rückmeldung warten mit sichtbarem Grund,
- blockieren mit sichtbarem Grund,
- fortsetzen beziehungsweise Blockade aufheben,
- zurückstellen,
- erledigen,
- Idee oder Backlogpunkt ohne Kopie in Aufgabe umwandeln,
- Growth-Backlog bearbeiten, abschließen oder verwerfen.

### Verwaltung

Bereits vorhanden:

- Event- und Aktivitätsbestand laden,
- Suche ohne Fokusverlust,
- öffentliche Ansicht öffnen,
- offene Vorgänge am Objekt anzeigen,
- objektbezogene Aufgabe anlegen.

### Menü

Bereits vorhanden:

- Anbieterbereich,
- Systemstatus in Alltagssprache,
- technische Synchronisationsdaten nur eingeklappt,
- Abmelden.

## 4. Noch erforderlich

### Workpack 2 – Verwaltung E2E fertigstellen

- echte Editoren für Events und Aktivitäten,
- vollständige fachliche Validierung,
- Writeback in die jeweilige führende Quelle,
- automatische Datenaufbereitung beziehungsweise Deploy,
- Bestätigung der öffentlichen Wirkung,
- relevante Änderung und Absage,
- Änderungsverlauf,
- Fehlerzustände als `wartet` oder `blockiert`.

Die Verwaltung ist erst dann vollständig, wenn Speichern nicht nur eine lokale Anzeige ändert, sondern den öffentlichen Datenprozess nachweislich aktualisiert.

### Workpack 3 – Menü und Systemstatus vervollständigen

- Störungen nur bei echter fachlicher Auswirkung hervorheben,
- erneute Ausführung oder klare nächste Aktion anbieten,
- Einstellungen und gegebenenfalls Diagnose sinnvoll platzieren,
- technische Begriffe aus der Standardansicht fernhalten.

### Workpack 4 – Altprozess ablösen

`/inbox/` bleibt vorläufig nur noch für:

- Push-Registrierung und Push-Test,
- Deploy-Sonderaktion,
- technische Diagnose während der Migration.

Der Growth-Backlog ist fachlich in `Arbeit` integriert und benötigt die alte Inbox nicht mehr als reguläre Arbeitsoberfläche.

### Workpack 5 – Gesamtvalidierung

- automatisierte API- und Übergangstests erweitern,
- reale Growth-Backlog- und Aufgabenübergänge auf Staging prüfen,
- reale Event-/Audit-/Submission-Writebacks prüfen,
- Pflicht-Viewports vollständig abnehmen,
- Abnahmematrix vollständig belegen,
- danach kontrollierter Main-Merge.

## 5. Automatische Absicherung

`.github/workflows/control-center-validation.yml` prüft:

- PHP-Syntax,
- JavaScript-Syntax beider Steuerzentralen-Skripte,
- JSON-Gültigkeit des Repo-Workpack-Manifests,
- Pflichtdateien,
- Zugriffsschutz,
- Growth-Backlog-Sync und Writeback,
- Repo-Workpack-Sync,
- Umwandlung ohne Doppelkarte,
- Warte- und Blockadegründe,
- verbindliche Navigation und Produktverträge.

## 6. Freigabestatus

| Bereich | Status |
|---|---|
| Vorgangs- und Writeback-Grundlage | tragfähig |
| Bearbeiten-Queue | funktional, reale Aktionen weiter prüfen |
| Übersicht | strukturell umgesetzt, reale Priorisierung weiter prüfen |
| Arbeit/Aufgaben/Backlog/Ideen | Workpack umgesetzt, Staging-Laufzeitprüfung ausstehend |
| Verwaltung | begonnen, E2E-Bearbeitung fehlt |
| Menü/Systemstatus | Grundzustand umgesetzt, Störungsfälle offen |
| Alte Inbox-Ablösung | teilweise; Backlog migriert, technische Restfunktionen offen |
| Main-Merge | nicht zulässig |

## 7. Nächster Schritt

Nach erfolgreichem Deployment wird Workpack 1 auf Staging mit realen Daten geprüft. Danach folgt ausschließlich Workpack 2: vollständige E2E-Fertigstellung der Verwaltung.
