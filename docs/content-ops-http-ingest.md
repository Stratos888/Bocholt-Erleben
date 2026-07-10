# Content Ops HTTP Ingest

Status: technische Datenbruecke fuer die zentrale Verwaltung.

## Zweck

GitHub Actions kann die Strato-MySQL-Datenbank nicht verlaesslich direkt erreichen. Die Content-Ops-Metriken werden deshalb nicht direkt per MySQL aus dem Runner geschrieben, sondern per HTTPS an einen PHP-Endpunkt auf Strato uebergeben.

```text
GitHub Actions
-> Content-Ops-Artefakt
-> HTTPS POST /api/content-ops-ingest.php
-> Strato PHP
-> lokale MySQL-Persistenz
-> spaeter /intern/dashboard/
```

## Endpunkte

```text
api/content-ops-ingest.php
api/content-ops-metrics.php
content-ops-ingest.php
```

`api/content-ops-ingest.php` ist der geschuetzte Schreib-Endpunkt fuer Roboterlaeufe.

`content-ops-ingest.php` ist nur ein kurzer Root-Redirect auf den API-Endpunkt, damit ein versehentlich angelegter Root-Pfad nicht ins Leere laeuft.

`api/content-ops-metrics.php` ist der geschuetzte Lese-Endpunkt fuer die spaetere Verwaltungsoberflaeche. Er liefert 28-Tage-Vergleiche, Action-Zusammenfassungen und Feedback-Wirkung.

## Token

Der Ingest erwartet einen geheimen Token. Serverseitig kann er in der privaten `api/_config.php` hinterlegt werden:

```php
'content_ops' => [
    'ingest_token' => '...'
]
```

Alternativ wird serverseitig diese Environment-Variable unterstuetzt:

```text
CONTENT_OPS_INGEST_TOKEN
```

In GitHub Actions werden benoetigt:

```text
CONTENT_OPS_INGEST_URL
CONTENT_OPS_INGEST_TOKEN
```

Empfohlene URL:

```text
https://bocholt-erleben.de/api/content-ops-ingest.php
```

Der Token darf nicht im Repo dokumentiert oder im Chat geteilt werden.

## Workflow

`.github/workflows/content-ops-http-ingest.yml` laeuft als zentraler Folgeworkflow nach diesen Laeufen:

```text
Content Quality Audit
Inbox Cleanup (Archive)
Growth Intelligence Backlog
Weekly KI Websearch → Manual Inbox
Manual KI Event Intake
```

Der Workflow laedt die vorhandenen Content-Ops-Artefakte des Quell-Runs, dedupliziert die Run-Payloads und sendet sie an den Ingest-Endpunkt.

Wenn URL oder Token fehlen, wird der Ingest uebersprungen. Der fachliche Roboterlauf bleibt davon unberuehrt.

## Sicherheitsgrenze

Der Ingest schreibt nur Metrik-/Finding-/Rule-Effect-Daten in SQL. Er veraendert keine Events, keine Activities, keine Bilder und keine redaktionellen Sheet-Inhalte.

## Zielpruefung

Nach einem Roboterlauf sollte der Folgeworkflow im Summary zeigen:

```text
http_ingest: persisted_http_ingest
```

Wenn dort steht:

```text
skipped_missing_ingest_config
```

fehlen GitHub Secrets oder serverseitige Token-Konfiguration.

Wenn dort steht:

```text
failed_http_ingest:401
```

stimmt der Token nicht ueberein.

Wenn dort steht:

```text
failed_http_ingest:500
```

ist der PHP-/DB-Endpunkt erreichbar, aber die serverseitige Speicherung hat einen Fehler.
