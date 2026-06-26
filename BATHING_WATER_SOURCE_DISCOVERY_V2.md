<!-- === BEGIN FILE: BATHING_WATER_SOURCE_DISCOVERY_V2.md | Zweck: dokumentiert den tieferen Discovery-Proof fuer aktuelle Badegewaesser-Statusquellen; Umfang: Machbarkeit vor Guard-Operationalisierung, kein Produkt-Writeback === -->
# Bathing Water Source Discovery V2

## Zweck

Dieser Proof klaert tiefer als der erste Badegewaesser-Proof, ob fuer die relevanten Badestellen ein **aktueller, technischer Statuskanal** auffindbar ist.

Ziel ist nicht, sofort Bade-Highlights zu aktivieren. Ziel ist die technische Vorentscheidung:

- Gibt es eine maschinenlesbare API oder XHR-Quelle?
- Rendert die offizielle Seite aktuelle Messwerte/Status nur im Browser?
- Gibt es nur Jahresqualitaet/Stammdaten?
- Ist die Quelle fuer tagesnahe Empfehlungen ungeeignet?

## Nicht-Ziele

Dieser Proof macht bewusst **keinen Produkt-Writeback**:

- keine Aenderung an `data/offers.json`,
- keine automatische Aktivierung von Bade-Highlights,
- keine Sheet-/Inbox-Korrektur,
- keine positive Badefreigabe nur aus Jahresqualitaet,
- keine lokale Presse-/News-Wertung.

## Gepruefte Quellen

| Activity | Quelle | Ziel der Pruefung |
|---|---|---|
| `aasee-erleben` | NRW-Badegewaesserdatenbank, Aasee Badestelle | API/XHR/rendered current samples/status finden |
| `proebstingsee-borken-erleben` | NRW-Badegewaesserdatenbank, Proebstingsee Badestelle | NRW-Mechanismus fuer Kreis-Borken-Badestelle pruefen |
| `auesee-wesel-erleben` | NRW-Badegewaesserdatenbank, Auesee Rettungsinsel und Treibsand | mehrere Messstellen fuer eine Activity pruefen |
| `hilgelo-erleben` | Zwemwater.nl, `'t Hilgelo` | aktuellen NL-Status auslesen und klassifizieren |

## Technischer Ansatz

`scripts/discover-bathing-water-sources-v2.py` kombiniert drei Ebenen:

1. statischer HTML-Abruf,
2. Asset-/JavaScript-/URL-Discovery,
3. optionaler Playwright-Browserlauf mit Network-/XHR-Mitschnitt und gerendertem Body-Text.

Der GitHub-Workflow installiert dafuer Playwright/Chromium und schreibt die Ergebnisse als Artifact:

```text
bathing-water-source-discovery-v2
```

## Ergebnisfelder

| Feld | Bedeutung |
|---|---|
| `ready_for_next_stage` | ausreichend fuer Entwurf eines separaten Guard-Workflows |
| `usable_activity_groups` | Anzahl der Activity-Gruppen mit verwertbarer Statusquelle |
| `nrw_usable_group_count` | davon verwertbare NRW-Gruppen |
| `discovery_class` | technische Fundklasse je Quelle |
| `inferred_status_for_proof_only` | nur Proof-Signal, kein Produktstatus |

Moegliche `discovery_class`-Werte:

| Wert | Bedeutung |
|---|---|
| `api_found` | API/XHR/JSON oder datenartige Antwort mit aktuellen Status-/Messwertsignalen gefunden |
| `browser_rendered_current_data` | Browser rendert aktuelle Status-/Messwertsignale, auch wenn keine API sauber isoliert wurde |
| `static_html_only` | statisches HTML enthaelt aktuelle Signale |
| `only_annual_quality_or_stammdaten` | nur Jahresqualitaet oder Stammdaten, nicht ausreichend fuer tagesnahe Badeempfehlung |
| `not_usable_for_current_status` | keine belastbare Statusquelle gefunden |
| `technical_error` | technischer Fehler im Discovery-Lauf |

## Akzeptanzkriterium

Ein weiterer Guard-Workpack ist erst sinnvoll, wenn:

- mindestens 3 von 4 Activity-Gruppen verwertbar sind,
- mindestens eine NRW-Gruppe verwertbar ist,
- Fehlerfaelle konservativ auf `unknown` fallen koennen,
- Jahresqualitaet nicht als aktuelle Badefreigabe missverstanden wird.

## Ausfuehrung

Auf `staging` laeuft der Proof automatisch bei Push auf diese Dateien.

Manueller UI-Start ist in GitHub Actions erst verlaesslich sichtbar, wenn der Workflow auf dem Default-Branch vorhanden ist. Bis dahin ist der Push-Run der Staging-Proof.

Lokal ohne Browser:

```bash
python scripts/discover-bathing-water-sources-v2.py --skip-browser
```

Lokal mit Browser braucht Playwright:

```bash
python -m pip install playwright
python -m playwright install chromium
python scripts/discover-bathing-water-sources-v2.py
```

## Entscheidung nach Proof

| V2-Ergebnis | Entscheidung |
|---|---|
| robust genug | separaten Badegewaesser-Guard-Workflow entwerfen |
| nur Browser-Scraping | sehr vorsichtig, eher Review-Hinweise statt automatischer `ok`-Status |
| nur Jahresqualitaet | keine automatische Badeempfehlung |
| uneinheitlich/fragil | manuelle Statuspflege bleibt Standard |

Bis ein belastbarer Guard umgesetzt und getestet ist, bleibt verbindlich:

> Bade-/Wasser-Highlights werden ohne frische positive Statusquelle nicht ausgespielt.
<!-- === END FILE: BATHING_WATER_SOURCE_DISCOVERY_V2.md === -->
