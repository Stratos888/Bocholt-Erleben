# Steuerzentrale – validierter Zielzustand 2026-07-14

## Zweck

Die Steuerzentrale ist der zentrale tägliche Betreiberarbeitsplatz. Sie integriert Entscheidungsfälle, einen kanonischen Backlog, Inhaltsverwaltung, Projektstatus und das bestehende SEO-/Mehrwert-Dashboard ohne doppelte Bedienmodelle oder erneute Anmeldung.

## Verbindliche Hauptnavigation

1. Übersicht
2. Prüfen
3. Backlog
4. Verwaltung
5. Entwicklung

Andere Haupttabs sind nicht Teil des Zielzustands.

## Übersicht

Die Übersicht zeigt ausschließlich:

- jetzt erforderliche Entscheidungen,
- offene Prüffälle,
- offene Backlogpunkte,
- kompakten Projektstatus.

Der Projektstatus nennt den konkreten Handlungsgrund. Abstrakte Aussagen wie „benötigt Aufmerksamkeit“ ohne Begründung sind unzulässig.

## Prüfen

`Prüfen` enthält alle entscheidungsbedürftigen Fälle:

- neue Inhalte,
- Qualität,
- Anbieter,
- Freigaben,
- System,
- Sonstige.

Verbindliche Regeln:

- `Alle` wird immer angeboten.
- Weitere Kategorien erscheinen nur bei mindestens einem offenen Fall.
- Desktop verwendet kompakte Pills.
- Mobile verwendet ein Dropdown.
- Jede sichtbare Kategorie zeigt ihre Fallzahl.
- Die Oberfläche fokussiert jeweils einen Fall.
- Beschreibungsfälle zeigen aktuellen Text, faktenbasierten Vorschlag und direkte Übernahme.
- Systemfälle verwenden `Erneut prüfen`; sie werden nur nach einer erfolgreich ausgeführten Quell- oder Prozessprüfung geschlossen.
- Ein manueller Statuswechsel gilt nicht als technische Behebung.

## Backlog

Der Tab enthält ausschließlich offene Einträge der führenden Growth-Backlog-Quelle mit:

- Bearbeiten,
- Abschließen,
- Verwerfen,
- `+ Neu`.

Repo-Workpacks sind Dokumentation und keine zweite operative Backlogquelle. Sie werden nicht in die tägliche Backlogliste importiert.

Die Darstellung ist fest nach Priorität sortiert:

1. kritisch,
2. hoch,
3. mittel,
4. niedrig,
5. innerhalb einer Priorität ältere Einträge zuerst.

Deduplizierung erfolgt über stabile Quellidentitäten und nicht ausschließlich über gleiche Titel.

## Verwaltung

Die Verwaltung enthält nur:

- Veranstaltungen,
- Aktivitäten,
- Suche,
- Bearbeiten,
- Vorschau.

Redaktionelle Events und Anbieter-Events werden getrennt und parallel mit Zeitlimit geladen. Vergangene Events werden aus der operativen Standardliste ausgeschlossen.

## Entwicklung

`Entwicklung` besitzt genau zwei Ansichten:

### Projektstatus

Die Contentkennzahl bewertet ausschließlich die aktive Eventbasis, also aktuelle und zukünftige veröffentlichte Events. Der Scope wird ausdrücklich als `Aktive Eventbasis` bezeichnet.

Angezeigt werden:

- Vollständigkeit der aktiven Eventbasis,
- Zahl offener Qualitätsfälle,
- Prozesszustand,
- offener kanonischer Backlog.

### Automatisierte Verbesserung

Unter dem kompakten Projektstatus wird die Entwicklung der automatisierten Projektbausteine dargestellt:

- KI-Suche,
- Contentprüfung und Lernwirkung,
- Inbox und Intake,
- Growth und SEO,
- Veröffentlichung.

Jede Zeile trennt:

- aktuellen technischen Betriebszustand,
- längerfristige Wirkungsentwicklung,
- zugrunde liegende Messwerte.

Verbindliche Bewertungsregeln:

- Vergleich mit einem mindestens sieben Tage alten Snapshot,
- mindestens zwei belastbare Vergleichssignale für eine Trendbewertung,
- `Datenbasis noch zu klein`, solange diese Voraussetzung nicht erfüllt ist,
- technische Fehler überschreiben eine rechnerisch positive Trendbewertung mit `Technische Aufmerksamkeit`,
- Mengen allein gelten nicht als Verbesserung,
- KI-Suche wird insbesondere über Übernahmequote, Verwerfungsquote und automatisch verhinderte Kandidaten bewertet,
- Contentprüfung wird über offene Qualitätsfälle, verhinderte Wiederholungen, False Positives und Rückfälle bewertet,
- Detailwerte bleiben standardmäßig eingeklappt.

Mögliche Trendzustände sind ausschließlich:

- Verbessert,
- Stabil,
- Verschlechtert,
- Technische Aufmerksamkeit,
- Datenbasis noch zu klein.

### SEO & Reichweite

Das bestehende Dashboard unter `/intern/seo-dashboard/` bleibt das führende SEO-/Mehrwert-Dashboard und wird unmittelbar innerhalb der Steuerzentrale eingebettet.

Verbindliche Regeln:

- kein vorgeschaltetes großes Growth-/SEO-Kartenmodul,
- kein zweites SEO-Dashboard,
- keine verschachtelte Scrollfläche,
- automatische Anpassung der iframe-Höhe,
- Staging prüft ausschließlich Staging-Endpunkte,
- Live prüft ausschließlich Live-Endpunkte,
- kein zweites Passwort.

## Gemeinsame Anmeldung

Die Steuerzentralen-Anmeldung initialisiert zusätzlich die bestehende Session `BE_INTERNAL_SEO_DASHBOARD`.

Verbindliche Sicherheitsregeln:

- kein Passwort in URLs,
- kein Passwort im iframe,
- `HttpOnly`-Session-Cookie,
- `SameSite=Lax`,
- Secure-Cookie bei HTTPS,
- Session-ID-Regeneration nach erfolgreicher Anmeldung,
- gemeinsame Abmeldung löscht die SEO-Dashboard-Session.

## Technischer Vertrag

- JavaScript-Asset-Version: `2026-07-14-control-center-improvement-v1`
- CSS-Governance-Version: `2026-06-22-css-governance-v1`
- führende Inbox: Google-Sheet-Tab `Inbox`
- Inbox-Fallback: `data/inbox.json` nur bei Sheet-Fehler
- redaktionelle Events: Events-Sheet
- Anbieter-Events: `submissions`
- Backlog: ausschließlich Growth-Backlog-Quelle
- Repo-Workpacks: ausschließlich Projektdokumentation
- SEO-/Mehrwert-Dashboard: `intern/seo-dashboard/index.php`
- Bausteinverlauf: stündliche Snapshots, fachlicher Vergleich nach mindestens sieben Tagen
- Veröffentlichung gilt erst nach öffentlicher Bestätigung als abgeschlossen

## Release-Gates

Vor einer Übernahme nach `main` müssen erfolgreich sein:

- PHP-Syntaxprüfung,
- JavaScript-Syntaxprüfung,
- Control-Center-Vertragstests,
- Produktvertragsaudit,
- Staging-Deployment,
- Mobile-Smoke-Test,
- gemeinsamer Login ohne zweite SEO-Passwortabfrage,
- Systemfall-Neuprüfung mit belegtem Ergebnis,
- Backlog ohne Repo-Workpack-Duplikate,
- Verwaltung mit aktuellen Events,
- Baustein-Wirkungsansicht ohne Scheingenauigkeit,
- eingebettetes SEO-Dashboard ohne Live-/Staging-Vermischung.

## Operativer Übergabestand 2026-07-14

### Freigabestand

- Der validierte Steuerzentralen-Stand wurde auf `staging` vollständig sichtbar geprüft.
- Die Ansicht `Entwicklung → Projektstatus → Automatisierte Verbesserung` ist vorhanden.
- Die Bausteine KI-Suche, Contentprüfung und Lernen, Inbox und Intake, Growth und SEO sowie Veröffentlichung werden angezeigt.
- `Datenbasis noch zu klein` ist unmittelbar nach Einführung der neuen Verlaufsmessung der erwartete und fachlich korrekte Zustand.
- Der Merge von `staging` nach `main` wurde gestartet. Die abschließende Live-Prüfung ist noch offen.

### Validierter Staging-Deploy

Der Staging-Deploy verwendet jetzt einen inhaltsbasierten Delta-Prozess:

- Manifestvergleich über SHA-256-Dateihashes,
- Upload nur tatsächlich geänderter Dateien,
- `api/_config.php` wird für die gewählte Umgebung immer aktualisiert,
- HTML-Einstiegspunkte und `meta/build.txt` werden zuletzt veröffentlicht,
- fehlendes Remote-Manifest löst automatisch einen vollständigen Reparatur-Deploy aus,
- ein manueller `full_repair`-Modus bleibt verfügbar,
- Live-Deploys bleiben vollständige Deploys.

Der erste validierte Lauf mit diesem Modell war vollständig grün:

- Gesamtlauf ungefähr 2 Minuten 23 Sekunden,
- Delta-Vorbereitung ungefähr 1 Sekunde,
- Upload geänderter Assets ungefähr 7 Sekunden,
- HTTP-Smoke-Test erfolgreich,
- Browser-Smoke-Test erfolgreich.

### Nächste verbindliche Prüfung nach dem Main-Merge

Live-URL:

`https://bocholt-erleben.de/steuerzentrale/`

In dieser Reihenfolge prüfen:

1. Main-Deploy vollständig grün, einschließlich HTTP- und Browser-Smoke-Test.
2. Anmeldung der Steuerzentrale funktioniert.
3. Keine zweite Passwortabfrage beim Wechsel zu `SEO & Reichweite`.
4. Hauptnavigation zeigt ausschließlich Übersicht, Prüfen, Backlog, Verwaltung und Entwicklung.
5. Übersicht lädt korrekte Zähler und nennt konkrete Handlungsgründe.
6. Prüfen zeigt auf Mobile das Dropdown und nur belegte Kategorien.
7. Backlog enthält ausschließlich die kanonische Growth-Quelle und keine Repo-Workpack-Duplikate.
8. Verwaltung lädt aktuelle Veranstaltungen und Aktivitäten.
9. Projektstatus zeigt `Automatisierte Verbesserung` mit allen fünf Bausteinen.
10. SEO-Dashboard ist ohne vorgeschaltete Großkarte, ohne verschachtelte Scrollfläche und ohne Live-/Staging-Pfadvermischung eingebettet.
11. Keine produktiven Schreibaktionen nur zu Testzwecken ausführen.

### Zeitliche Wirkungsprüfung

Die neue Bausteinbewertung benötigt reale Verlaufsdaten:

- nach mindestens 7 Tagen: erster technischer Vergleich; noch geringe Aussagekraft,
- nach ungefähr 4 Wochen: erste belastbare Wirkungsbewertung,
- nach 8 bis 12 Wochen: robuste Bewertung des E2E-Verbesserungs- und Selbstlernprozesses.

Besonders zu beobachten:

- KI-Suche: Übernahmequote, Verwerfungsquote, automatisch verhinderte Kandidaten und manuelle Ablehnungen,
- Contentprüfung: False Positives, verhinderte Wiederholungen, Rückfälle und offene Qualitätsfälle,
- Inbox und Intake: offener Bestand, Übernahmen und übersprungene Fälle,
- Growth und SEO: technische Abdeckung, Search Console, GA4 und Nutzwertaktionen,
- Veröffentlichung: Veröffentlichungsprobleme und blockierte Systemfälle.

Eine positive Bewertung darf niemals allein aus geringeren Fallzahlen oder höheren Rohmengen abgeleitet werden. Technik, Qualität, Effizienz, Lernwirkung und Ergebnis müssen gemeinsam bewertet werden.
