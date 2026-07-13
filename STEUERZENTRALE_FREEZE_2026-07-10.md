# Steuerzentrale – finaler Zielzustand 2026-07-10

## Zweck

Die Steuerzentrale ist der zentrale tägliche Betreiberarbeitsplatz. Sie integriert Entscheidungsfälle, Backlog, Inhaltsverwaltung, Projektstatus und das bestehende SEO-/Mehrwert-Dashboard ohne doppelte Bedienmodelle oder erneute Anmeldung.

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

Technische/systemische Vorgänge werden als prüfbare Fälle bewertet und nicht in einem separaten Aufgabenmodell versteckt.

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
- Redundante Details-Aktionen sind nicht sichtbar.

## Backlog

Der frühere Tab `Arbeit` heißt verbindlich `Backlog`.

Er enthält ausschließlich offene Growth-/Projekt-Backlogpunkte mit:

- Bearbeiten,
- Abschließen,
- Verwerfen,
- `+ Neu`.

Nicht sichtbar und nicht Teil des Bedienmodells sind:

- Als Nächstes,
- Wartet,
- Blockiert,
- Ideen,
- Archiv,
- manuelle Aufgabenanlage.

Abgeschlossene Entscheidungen und Entwicklungsschritte werden dauerhaft im Repo und in der Projektdokumentation festgehalten.

## Verwaltung

Die Verwaltung enthält nur:

- Veranstaltungen,
- Aktivitäten,
- Suche,
- Bearbeiten,
- Vorschau.

Redaktionelle Events und Anbieter-Events werden getrennt und parallel mit Zeitlimit geladen. Vergangene Events werden aus der operativen Standardliste ausgeschlossen. Mobile Karten zeigen nur Quelle/Status, Titel, Datum/Ort und die beiden Aktionen.

## Entwicklung

`Entwicklung` besitzt genau zwei Ansichten:

### Projektstatus

Kompakte Betreiberübersicht zu:

- Contentqualität,
- Prozesszustand,
- offenem Backlog.

### SEO & Reichweite

Das bestehende Dashboard unter `/intern/seo-dashboard/` bleibt das führende SEO-/Mehrwert-Dashboard und wird innerhalb der Steuerzentrale eingebettet.

Es wird kein zweites SEO-Dashboard nachgebaut.

Oberhalb des eingebetteten Dashboards steht nur ein kompakter operativer Growth-/SEO-Prozesskopf mit:

- Zustand des letzten Growth-/SEO-Laufs,
- Verfügbarkeit der Betriebsdaten,
- ausgewählten Chancen,
- automatisch verhinderten Kandidaten,
- offenem Backlog.

Die vollständigen Sichtbarkeits-, Klick-, Nutzwert-, Vergleichs- und Reporting-Zielkennzahlen verbleiben im bestehenden SEO-Dashboard.

## Gemeinsame Anmeldung

Die Steuerzentralen-Anmeldung initialisiert zusätzlich die bestehende Session `BE_INTERNAL_SEO_DASHBOARD`.

Verbindliche Sicherheitsregeln:

- kein Passwort in URLs,
- kein Passwort im iframe,
- SEO-Dashboard ohne zweite Passwortabfrage,
- `HttpOnly`-Session-Cookie,
- `SameSite=Lax`,
- Secure-Cookie bei HTTPS,
- Session-ID-Regeneration nach erfolgreicher Anmeldung,
- gemeinsame Abmeldung löscht die SEO-Dashboard-Session.

Die bestehenden Control-Center-APIs bleiben mit dem Review-Header kompatibel.

## Technischer Vertrag

- JavaScript-Asset-Version: `2026-07-10-control-center-final-v1`
- CSS-Governance-Version: `2026-06-22-css-governance-v1`
- ergänzende responsive CSS: `css/control-center-final.css`
- führende Inbox: Google-Sheet-Tab `Inbox`
- Inbox-Fallback: `data/inbox.json` nur bei Sheet-Fehler
- redaktionelle Events: Events-Sheet
- Anbieter-Events: `submissions`
- Backlog: Growth-Backlog-Quelle
- SEO-/Mehrwert-Dashboard: `intern/seo-dashboard/index.php`
- Veröffentlichung gilt erst nach öffentlicher Bestätigung als abgeschlossen

## Release-Gates

Vor einer Übernahme nach `main` müssen mindestens erfolgreich sein:

- PHP-Syntaxprüfung,
- JavaScript-Syntaxprüfung,
- Control-Center-Vertragstests,
- Produktvertragsaudit,
- Staging-Deployment,
- Mobile-Smoke-Test,
- gemeinsamer Login ohne zweite SEO-Passwortabfrage,
- Prüfen mit dynamischen Kategorien,
- Backlog ohne zusätzliche Filter,
- Verwaltung mit aktuellen Events,
- eingebettetes SEO-Dashboard.
