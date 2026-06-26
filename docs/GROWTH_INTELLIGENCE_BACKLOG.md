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
- interne Nutzwert-Metriken aus `value_metric_daily`
- Inbox-/Review-Historie, soweit im Sheet vorhanden
- Repo-Visual-Backlog als aggregiertes Qualitätssignal
- Content-/Event-/Activity-Bestand
- Content-Audit-Ergebnisse, sobald sie dauerhaft maschinenlesbar archiviert werden

Diese Quellen sollen deduplizierte Backlog-Cluster erzeugen, nicht einzelne rohe Hinweise.

## Automatischer Growth-Intelligence-Prozess

Der automatische Prozess erzeugt keine Live-Änderungen und gibt keine Aufträge an den KI-Event-Suchlauf weiter. Er sammelt nur priorisierte, deduplizierte Arbeitspakete im Backlog.

### Datenquellen

- Google Search Console API über `GSC_SITE_URL` oder `SEARCH_METRICS_GOOGLE_SITE_URL`
- Google Analytics Data API über `GA4_PROPERTY_ID`
- interne Nutzwert-Metriken aus der Datenbanktabelle `value_metric_daily`
- kanonischer Content-Bestand aus dem Google Sheet, insbesondere `Events`, `Activities`/`Aktivitaeten` und `Locations`
- vorhandene Backlog-Historie aus `Growth_Backlog`
- Inbox-/Review-Historie aus `Inbox_Archive`, sofern vorhanden
- Repo-Visual-Backlog als aggregiertes Qualitätssignal

Es gibt keinen CSV-Import als Zielprozess und keinen zusätzlichen bezahlten KI-Websearch.

### Workflow

GitHub Actions Workflow:

```text
.github/workflows/growth-intelligence-backlog.yml
```

Script:

```text
scripts/growth-intelligence-backlog.py
```

Der Workflow läuft wöchentlich und kann manuell gestartet werden. Er liest 30 Tage zurück, sofern kein anderer Zeitraum beim manuellen Start angegeben wird.

### Secrets

Erforderlich:

```text
GOOGLE_SERVICE_ACCOUNT_JSON
LIVE_SHEET_ID oder SHEET_ID
```

Für echte externe Growth-Signale zusätzlich:

```text
GSC_SITE_URL
GA4_PROPERTY_ID
```

Die Service-Account-Adresse muss Zugriff auf das Google Sheet haben. Für GSC und GA4 muss derselbe Service Account in Search Console beziehungsweise GA4 berechtigt sein.

### Deduplizierung und Historie

Der Prozess erzeugt `cluster_key`-basierte Themencluster. Ein Thema wird nicht erneut angelegt, wenn derselbe `cluster_key` bereits im Backlog existiert, unabhängig vom Status.

Damit verhindern auch abgeschlossene und abgelehnte Einträge ein erneutes Auftauchen desselben Punktes.

### Semantische Entscheidungs- und Priorisierungsschicht

Der Prozess schreibt keine einzelnen Rohsignale wie Suchbegriffe direkt ins Backlog. Vorher wird verdichtet:

```text
Rohsignal -> Such-/Nutzungsintention -> Projektproblem -> dedupliziertes Arbeitspaket
```

Beispiele:

- „was ist heute los“ / „wo ist heute was los“ -> Arbeitspaket für Today-/Landingpage-/SEO-Prüfung
- starke interne Detailaufrufe + viele Website-/Maps-Klicks -> prominente Verlinkung oder Highlight-Verwendung prüfen
- wiederholte Ablehnungen im Inbox-Archiv -> Quellen-/Regel-/Review-Muster prüfen
- viele offene Visual-Gaps -> Bildbestand als Arbeitspaket härten

### Output-Kategorien

- `Content-Lücke`
- `SEO-Optimierung`
- `UX-/Content-Prüfung`
- `Nutzungs-/Content-Chance`
- `Akquise-/Funnel-Chance`
- `Prozess-/Qualitätssignal`
- `Visual-/Content-Qualität`

Jeder Eintrag enthält:

- Thema
- Priorität
- Kurzgrund
- Begründung
- empfohlene Maßnahme
- erwarteten Nutzen
- Akquise-Hinweis
- technische Signale in `signals_json`

### Akquise-Logik

Akquise ist nur eine Zusatzbewertung. Der Prozess erzeugt keine automatische Ansprache.

Beispiel: Hohe Nachfrage nach Hallenbad/Bahia wird nicht automatisch als Akquise-Aufgabe interpretiert. Der Eintrag weist stattdessen darauf hin, dass Content-Potenzial bestehen kann, Akquise aber wegen Bekanntheit, öffentlichem Charakter oder möglicher Auslastung niedrig priorisiert werden sollte.

### Abgrenzung zur KI-Suche

Der Growth-Backlog-Prozess verändert nicht:

- `bocholt-erleben_eventsuche_regelwerk_v3.md`
- `eventsuche_quellenregister_v1.md`
- den regulären KI-Event-Suchlauf
- Content-Prüfungsregeln

Er ist ein eigenständiger Entscheidungs- und Backlog-Loop.
