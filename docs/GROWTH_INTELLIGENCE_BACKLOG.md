# Growth Intelligence / Acquisition Backlog

## Ziel

Der Growth-Loop ist eine eigene Entscheidungsschicht neben Content-Prüfung und Event-KI-Suche.
Er erzeugt keine Live-Änderungen und keine Aufgaben für den KI-Suchlauf.

Ziel ist ein dedupliziertes, priorisiertes Backlog aus echten Nutzungs-, SEO- und Projekt-Signalen:

- Content-Lücken
- SEO-Verbesserungen
- Landingpage-Chancen
- UX-/Produktideen
- Activity-Ideen
- Akquise-Hinweise
- manuelle Notizen des Betreibers

## Harte Abgrenzung

- Die KI-Suche bleibt Event-Suche.
- Activity-Ideen werden nicht automatisch gesucht.
- Growth-Hinweise werden nicht als Regelwerk oder befristetes Signal in die KI-Suche geschrieben.
- Umsetzung erfolgt manuell mit Betreiberentscheidung.
- Abgeschlossene oder abgelehnte Punkte bleiben historisch im Backlog-Sheet erhalten, damit gleiche Cluster nicht wieder als offene Punkte auftauchen.

## Inbox-UI

Der private Inbox-Bereich enthält zusätzlich den Tab `Backlog`.
Die Darstellung ist mobil zuerst gedacht:

- kompakte Listenzeilen
- Priorität und Typ als Chips
- Titel und Kurzgrund sofort sichtbar
- Detail auf Klick/Expand
- Detailfelder: Warum relevant, Was tun, Erwarteter Nutzen, Akquise-Einschätzung
- Aktionen nur `Abgeschlossen` und `Ablehnen`
- `+ Notiz` für manuelle mobile Backlog-Punkte

## Persistenz

Kanonischer Speicher ist der Google-Sheet-Tab `Growth_Backlog`.
Der API-Layer legt den Tab und die Header bei Bedarf an.

Pflichtspalten:

```text
id
cluster_key
status
priority
type
title
short_reason
why_relevant
recommended_action
expected_benefit
acquisition_note
source
signals_json
created_at
updated_at
closed_at
decision_note
```

## Statuslogik

Offen:

- `open`
- `offen`
- leer

Geschlossen:

- `completed`
- `rejected`

Geschlossene Punkte werden nicht aus der Historie gelöscht.

## Akquise-Nutzung

Akquise ist kein Automatismus. Ein Backlog-Punkt kann eine Akquise-Einschätzung enthalten, aber daraus entsteht keine direkte Ansprache.

Geeignet sind vor allem Anbieter mit:

- erkennbarem Bedarf an Sichtbarkeit
- privatem oder buchungsorientiertem Geschäftsmodell
- möglichem Zusatzumsatz
- Familien-, Freizeit-, Kurs-, Gastro- oder Eventbezug

Nicht automatisch geeignet sind hoch bekannte oder ohnehin ausgelastete Angebote, zum Beispiel öffentliche Einrichtungen oder stark nachgefragte Standardangebote.

## Spätere Datenquellen

Die Backlog-Struktur ist auf direkte API-Integration ausgelegt:

- Google Search Console API
- Google Analytics Data API
- interne Klick- und Suchdaten
- Content-/Event-/Activity-Bestand
- Content-Audit-Ergebnisse

Diese Quellen sollen deduplizierte Backlog-Cluster erzeugen, nicht einzelne rohe Hinweise.
