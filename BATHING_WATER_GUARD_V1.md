# Bathing Water Guard V1

Status: report-only proof workflow.

## Ziel

Der Guard prueft offizielle Badegewaesserquellen fuer die zustandsabhaengigen Activity-Highlights:

- Aasee Bocholt
- Hilgelo Winterswijk
- Proebstingsee Borken
- Auesee Wesel

Ziel ist ein tagesaktueller Schutz gegen falsche Badeempfehlungen. Der Guard erzeugt zunaechst nur ein Artefakt und schreibt keine Produktdaten.

## Nicht-Ziel

Dieser Workpack aktiviert keine Bade-Highlights automatisch.

Nicht enthalten:

- kein Writeback nach `data/offers.json`
- keine direkte Aenderung an `current_status`
- keine UI-Aktivierung
- kein automatisches `ok` im Frontend
- keine Integration in die normale Content-Inbox

## Quellen

| Gruppe | Quelle |
|---|---|
| Aasee Bocholt | NRW-Badegewaesserdatenbank, DataTables-Messwert-Endpunkt |
| Proebstingsee Borken | NRW-Badegewaesserdatenbank, DataTables-Messwert-Endpunkt |
| Auesee Wesel | NRW-Badegewaesserdatenbank, Rettungsinsel + Treibsand |
| Hilgelo Winterswijk | Zwemwater-Seite fuer `id=1750` |

Die NRW-Endpunkte wurden im Source-Discovery-V2-Proof ueber Browser-/XHR-Analyse gefunden. V1 nutzt diese Endpunkte direkt und wertet aktuelle Messwerttabellen aus.

## Statuslogik

| Status | Bedeutung |
|---|---|
| `ok` | Quelle ist erreichbar, in Saison, frischer positiver Status, kein Sperrsignal |
| `watch` | Quelle ist erreichbar, aber mit Warnsignal oder bald veraltendem Messwert |
| `blocked` | offizielles Badeverbot, negatives Schwimmadvies oder technisches Blocksignal |
| `unknown` | Quelle nicht eindeutig, nicht erreichbar, zu alt oder nicht positiv belegbar |
| `out_of_season` | konfiguriertes Badefenster nicht aktiv |

Wichtig:

- `blocked` schlaegt alle anderen Status.
- `unknown` aktiviert nichts.
- `watch` aktiviert nichts.
- Bei mehreren Quellen fuer eine Activity-Gruppe wird nur dann `ok` gesetzt, wenn alle konfigurierten Quellen positiv sind.
- Der Guard ist konservativ: Im Zweifel bleibt der Status `unknown`.

## Frischefenster

Default:

- `warn_measurement_age_days = 35`
- `max_measurement_age_days = 45`

Ein NRW-Messwert kann nur dann zu `ok` fuehren, wenn er innerhalb des maximalen Frischefensters liegt.

## Artefakte

Der Workflow erzeugt:

- `bathing-water-status-guard.json`
- `bathing-water-status-guard.md`

Diese Artefakte sind die Grundlage fuer eine manuelle Bewertung.

## Workflow

Datei:

```text
.github/workflows/bathing-water-guard-v1.yml
```

Trigger:

- manuell ueber `workflow_dispatch`, sobald der Workflow auf einem Branch liegt, auf dem GitHub den Button anbietet
- taeglich in der Badesaison per Cron
- bei Push auf Script/Workflow/Doku

Hinweis:

GitHub zeigt den manuellen Run-Button je nach Repository-Konfiguration oft erst, wenn der Workflow auf dem Default-Branch liegt. Auf `staging` laeuft der Proof trotzdem bei Push.

## Entscheidung nach 1 bis 2 Laeufen

Erst nach Sichtung der Artefakte entscheiden:

| Ergebnis | Konsequenz |
|---|---|
| Statusquellen stabil, eindeutig, plausibel | spaeteren Writeback-/Inbox-Designschritt planen |
| viele `unknown` trotz abrufbarer Quellen | manuelle Statuspflege beibehalten |
| Widersprueche oder fragile Parser | kein produktiver Guard |
| klare Blocksignale | Bade-Highlights weiter blockiert lassen |

## Produktregel

Bis zu einem separat freigegebenen Writeback-Workpack gilt:

> Bade-/Wasser-Highlights bleiben unsichtbar, solange keine frische positive Statusquelle fachlich bestaetigt und gepflegt wurde.


## Guard V1.1 Hardening

Der erste echte Guard-Lauf zeigte zwei wichtige Härtungspunkte:

- NRW-DataTables können geplante/future-dated Probenahmen enthalten. Der Guard ignoriert künftig Zukunftszeilen und verwendet die letzte nicht-zukünftige Probe.
- Zwemwater-Seiten können positive, Warn- und Negativformulierungen gleichzeitig als Legende/Hilfetext enthalten. Gemischte ungescopedte Seitensignale werden künftig konservativ `unknown`, statt vorschnell `blocked` zu setzen. Nur klar gescopte Statusformulierungen wie `Zwemplek status:goed` oder `Actuele situatie: in orde` dürfen zu `ok` führen.

Damit bleibt der Guard weiter report-only und schreibt keine Produktdaten.

## Guard V1.2 – lokale Badeeignung getrennt von Wasserstatus

Der Aasee-Befund zeigt: Gute Messwerte und kein Badeverbot reichen nicht automatisch fuer eine aktive Badeempfehlung.

V1.2 trennt deshalb drei Ebenen:

| Ebene | Bedeutung |
|---|---|
| `water_state` | offizielle Wasserqualitaet / Badeverbot / Messwerte |
| `local_suitability_state` | lokale praktische Badeeignung, z. B. Schlamm, Geruch, Algen, gesperrte Badebucht, gruenes Flaggen-/Freigabesignal |
| `state` | finaler Guard-Status fuer Empfehlungsschutz |

Produktregel ab V1.2:

> Ein Bade-Highlight darf spaeter nur dann technisch als Kandidat fuer `ok` gelten, wenn `water_state = ok` **und** `local_suitability_state = ok` sind.

Konsequenzen:

- `water_state = ok` allein fuehrt nicht mehr zu einem finalen `ok`.
- Lokale Negativsignale aus serioeser lokaler Presse oder offizieller Quelle fuehren mindestens zu `watch`.
- `watch` blockiert weiterhin jede automatische Bade-Highlight-Ausspielung.
- Lokale Presse kann eine Empfehlung unterdruecken, beweist aber kein offizielles Badeverbot.
- Positive lokale Freigaben sollen bevorzugt aus offiziellen Stadt-/Kreis-/Betreiberquellen kommen, z. B. gruene Flagge, Badestelle freigegeben, Badebucht geoeffnet.

Aktuell ergänzt fuer Aasee:

- offizielle Stadt-Bocholt-Aasee-Seite als lokale Freigabe-/Warnquelle,
- lokales BBV-Negativsignal zu Schlamm/Geruch als zeitlich begrenzter Empfehlungssuppressor bis `2026-07-15`.

Der Guard bleibt report-only:

- kein Writeback nach `data/offers.json`,
- keine direkte Änderung an Activity-Highlights,
- keine UI-Aktivierung,
- keine automatische Inbox-Aktion.

Erst nach Sichtung von 1–2 V1.2-Artefakten wird entschieden, ob und wie lokale Badeeignung in einen spaeteren Content-/Statusprozess uebernommen wird.

## UI-Policy fuer lokale Badehinweise

V1.2 trennt weiterhin Report-Guard und Produktdaten. Wenn ein lokaler Hinweis fachlich in `data/offers.json` gepflegt wird, gilt fuer die UI:

- Home zeigt keine negative grosse Warnung. Der Bade-/Wasser-Boost wird nur unterdrueckt.
- Activity-Cards duerfen bei konkretem `watch`/`blocked` einen knappen Statuschip zeigen, z. B. `Badehinweis pruefen`.
- `unknown` erzeugt keinen Card-Chip, um die Aktivitaetenliste nicht mit unklaren Hinweisen zu ueberfrachten.
- Das Detailpanel erklaert die Ursache kompakt, z. B. Wasserwerte unauffaellig, aber lokaler Schlamm-/Geruchshinweis aktiv.
- Ein negativer lokaler Hinweis laeuft ueber `valid_until` ab oder wird durch eine staerkere positive lokale Quelle ersetzt. Das blosse Fehlen neuer Negativmeldungen ist keine Entwarnung.

## Guard V2 – Safe Writeback über Statusdatei

V2 beendet den reinen Report-only-Endzustand, ohne `data/offers.json` automatisch zu verändern.

Neue generierte Produktdatei:

```text
data/bathing_water_status.json
```

Ablauf:

1. Der Guard prüft die konfigurierten Badegewässerquellen.
2. Er erzeugt weiterhin Report-Artefakte (`bathing-water-status-guard.json` und `.md`).
3. Zusätzlich schreibt er mit `--write-data data/bathing_water_status.json` eine kleine Frontend-Statusdatei.
4. Der Workflow committet diese Datei nur bei echtem Diff.
5. Der normale Deploy spielt die geänderte Statusdatei live aus.
6. Das Frontend lädt `offers.json` plus `bathing_water_status.json` und nutzt die Statusdatei als Override für `current_status`.

Abgrenzung:

- `data/offers.json` bleibt redaktioneller Stammdatenbestand.
- `data/bathing_water_status.json` ist ein generiertes Laufzeit-/Statusartefakt.
- `ok` wird nur wirksam, wenn der Guard sowohl `water_state=ok` als auch `local_suitability_state=ok` schreibt.
- `watch`, `blocked` und `unknown` werden automatisch live sichtbar bzw. suppressen Bade-Highlights.
- Bei fehlender oder fehlerhafter Statusdatei fällt das Frontend auf die konservativen Statuswerte aus `offers.json` zurück.

Der Workflow heißt weiterhin technisch `.github/workflows/bathing-water-guard-v1.yml`, trägt aber den Anzeigenamen `Bathing Water Guard V2`, damit bestehende Dateipfade nicht unnötig verschoben werden.


## Guard V2 – Staging-Validierung vom 2026-06-26

Die Safe-Writeback-Kette wurde auf `staging` validiert:

1. `Bathing Water Guard V2` lief grün.
2. Der Guard erzeugte `data/bathing_water_status.json` mit `script_version=BATHING_WATER_GUARD_V2_SAFE_WRITEBACK`.
3. Der Workflow erkannte einen echten Diff und schrieb den Commit `Aktualisiere Badegewaesser-Status` auf `staging`.
4. Der anschließende Deploy war grün.
5. Das Frontend las die Statusdatei und spielte keine falsche Badeempfehlung aus.
6. Aasee zeigte einen Badehinweis und war nicht im Filter `Jetzt besonders`.

Validierter Guard-Status:

| Gruppe | Status | Wirkung |
|---|---|---|
| Aasee Bocholt | `blocked` | keine Badeempfehlung; lokales Warn-/Sperrsignal sichtbar |
| Hilgelo | `unknown` | keine Badeempfehlung |
| Pröbstingsee Borken | `watch` | keine Badeempfehlung; Messwertalter beachten |
| Auesee Wesel | `watch` | keine Badeempfehlung; lokale Badeeignung nicht positiv belegt |

Damit ist auf `staging` belegt:

```text
Guard V2 -> Statusdatei -> Commit -> Deploy -> Frontend-Override -> sichere UI
```

Weiterhin gilt:

- kein automatischer Writeback nach `data/offers.json`,
- `ready_for_product_writeback=false`,
- keine positive Badeempfehlung ohne finalen Guard-Status `ok`,
- täglicher produktiver Schedule erst nach Merge auf `main` final prüfen.
