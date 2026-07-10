# Event Impact Tracking

## Ziel

Das Event-Impact-System misst objektgenau, ob veröffentlichte Inhalte auf Bocholt erleben genutzt werden. Der öffentliche Auftritt bleibt für kostenlose und bezahlte Einträge gleich hochwertig. Der Anbieter-Mehrwert liegt in Service, Messbarkeit, Pflegeersparnis und nachvollziehbarer Wirkung.

## Produktvertrag

- Öffentliche Event-Detailseiten unter `/events/<slug>/` bleiben kanonische, teilbare Event-URLs.
- Das bestehende App-Verhalten Eventliste → Detailpanel bleibt erhalten.
- Detailseite ersetzt das Detailpanel nicht.
- Keine bessere öffentliche Optik für bezahlte Einträge.
- Anbieterberichte zeigen gemessene Nutzsignale, keine Besucherzahlen vor Ort, keine Ticketverkäufe, keine eindeutigen Personen.
- PDF-/Download-Quellen dürfen nicht als öffentliche Eventquelle oder als öffentlicher Tracking-CTA ausgespielt werden.

## Tracking-Contract

Das bestehende First-Party-System `/api/value-track.php` bleibt führend. Es schreibt Tagesaggregate in `value_metric_daily`.

Erlaubte Event-relevante Metriken:

- `event_detail_view` – Eventdetails wurden geöffnet, entweder im Detailpanel oder auf der öffentlichen Detailseite.
- `website_click` – Veranstaltungsseite, Ticketlink oder vergleichbarer externer Eventlink wurde geöffnet.
- `maps_click` – Route/Kartenlink wurde geöffnet.
- `event_share_click` – Native Teilen-Funktion wurde erfolgreich ausgelöst.
- `event_copy_link` – Teilen-Fallback hat den Eventlink in die Zwischenablage kopiert.

Zusätzlicher Kontext:

- `source_context=public_detail_page`
- `source_context=event_panel`
- `source_context=event_card`
- `source_context=today_card`

`source_context` ist Teil des Bucket-Hashs, damit Panel-, Card-, Today- und öffentliche Detailseiten-Signale intern getrennt bleiben, im Anbieterbericht aber verständlich zusammengeführt werden können.

## Datenschutzgrenzen

- Tracking bleibt consent-gated über die bestehende Statistik-Zustimmung.
- Ohne `be_statistics_consent=granted` ignoriert der Server Metriken.
- Keine personenbezogenen Profile, kein Fingerprinting, keine eindeutigen Besucherbehauptungen.
- Anbieterkommunikation verwendet vorsichtige Begriffe wie „gemessene Aufrufe“, „gemessene Interaktionen“ und „Aktionen“.

## Öffentliche Event-Detailseiten

`scripts/build-event-detail-pages.py` instrumentiert generierte Detailseiten unsichtbar:

- lädt `config.js`, damit `BEAnalytics` nach Zustimmung verfügbar ist,
- zählt `event_detail_view` mit `source_context=public_detail_page`,
- trackt Website-/Eventquellenlinks als `website_click`,
- trackt Kartenlinks als `maps_click`,
- trackt Teilen als `event_share_click` bzw. Clipboard-Fallback als `event_copy_link`.

Die sichtbare Detailseiten-UI bleibt unverändert.

## Bestehende App-Flows

- `js/details.js` zählt Detailpanel-Öffnungen weiter als `event_detail_view`, ergänzt aber `source_context=event_panel`.
- `js/events.js` zählt Card-CTAs mit `source_context=event_card` und Share-Aktionen objektgenau.
- `js/today-home.js` ergänzt `source_context=today_card` bei Desktop-Direktaktionen.

## Anbieter-Wirkungsbericht

`api/organizer-portal/me.php` liefert im bestehenden `impact_summary` zusätzlich:

- `share_clicks`
- `copy_link_clicks`
- `items` mit objektgenauen Top-Inhalten
- `impact_metrics` je jüngerer Einreichung

`js/organizer-portal.js` zeigt daraus:

- Gesamtinteraktionen,
- Detail-Aufrufe,
- Website-/Ticket-Klicks,
- Route/Maps,
- Teilungen,
- stärkste Inhalte,
- Wirkung direkt in der jeweiligen Einreichung.

Die Darstellung bleibt eine kompakte Wirkungsübersicht, kein komplexes Analytics-Dashboard.

## Guard

Der statische Guard prüft, ob Backend, Frontend, Detailseiten-Generator und Anbieterbereich weiterhin denselben Event-Impact-Contract verwenden:

```bash
python3 scripts/audit-event-impact-tracking.py
```

Ergänzende Pflichtchecks bei Änderungen:

```bash
php -l api/value-track.php
php -l api/organizer-portal/me.php
node --check config.js
node --check js/events.js
node --check js/details.js
node --check js/today-home.js
node --check js/organizer-portal.js
python3 -m py_compile scripts/build-event-detail-pages.py
python3 -m py_compile scripts/growth-intelligence-backlog.py
```

## Bekannte Grenze

DB-/Anbieter-Events aus `/api/events/public.php` haben bereits ein sicheres `reporting_target_*` und stabile `submission-<id>`-Entity-IDs. Eine kanonische öffentliche Detailseiten-Route für dynamische DB-Events darf erst ergänzt werden, wenn die Routing-Strategie sauber entschieden ist. Es dürfen keine `detail_path`-/`detail_url`-Felder ausgespielt werden, solange diese URLs nicht tatsächlich auflösbar sind.
