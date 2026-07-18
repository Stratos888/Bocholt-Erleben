<!-- === BEGIN FILE: BATHING_WATER_STATUS_PROOF.md | Zweck: beschreibt den Machbarkeits-Proof fuer automatische Badegewaesser-Statusquellen; Umfang: Proof vor Operationalisierung, keine Produktlogik === -->
# Bathing Water Status Proof

<!-- === BEGIN BLOCK: BATHING_WATER_STATUS_PROOF_CURRENT_STATUS_2026_06_27 | Zweck: current-first Einordnung nach Guard-V2-Abschluss; Umfang: Proof-Status, Abgrenzung zu aktueller Guard-Datei === -->
## Current Status 2026-06-27

Dieser Proof ist als eigenstaendiger naechster Schritt ueberholt. Die Erkenntnisse wurden in `BATHING_WATER_SOURCE_DISCOVERY_V2.md` und danach in `BATHING_WATER_GUARD_V1.md` / Guard V2 weitergefuehrt.

Aktueller Stand:

- Safe-Writeback ueber `data/bathing_water_status.json` ist auf Staging validiert.
- Der Guard schreibt nicht nach `data/offers.json`.
- Das Frontend nutzt die Statusdatei als Override und verhindert positive Badeempfehlungen ohne finalen Guard-Status `ok`.
- Dieses Dokument bleibt als historischer Machbarkeits-/Proof-Kontext erhalten, ist aber kein offener Roadmap-Block.

<!-- === END BLOCK: BATHING_WATER_STATUS_PROOF_CURRENT_STATUS_2026_06_27 === -->


## Zweck

Dieser Proof klaert, ob offizielle Badegewaesser-Statusquellen fuer die saisonalen Activity-Highlights automatisch robust auslesbar sind.

Der Proof ist bewusst **noch keine Produkt-Automatisierung**:

- keine Aenderung an `data/offers.json`,
- keine automatische Aktivierung von Bade-Highlights,
- kein Sheet-/Inbox-Writeback,
- keine Bewertung lokaler Presse-/News-Signale,
- kein Ersatz fuer die bestehende Schutzlogik `unknown/blocked => kein Bade-Highlight`.

## Gepruefte Badestellen

| Activity | Quelle | Zweck |
|---|---|---|
| `aasee-erleben` | NRW-Badegewaesserdatenbank, Aasee Badestelle | pruefen, ob aktuelle Status-/Messwerte maschinenlesbar ausspielbar sind |
| `hilgelo-erleben` | Zwemwater.nl, `'t Hilgelo` | pruefen, ob aktueller Status wie `goed` / Warnung / Verbot lesbar ist |
| `proebstingsee-borken-erleben` | NRW-Badegewaesserdatenbank, Proebstingsee Badestelle | pruefen, ob aktuelle Status-/Messwerte maschinenlesbar ausspielbar sind |
| `auesee-wesel-erleben` | NRW-Badegewaesserdatenbank, Auesee Rettungsinsel und Treibsand | beide Badestellen muessen fuer eine Activity-Einordnung beruecksichtigt werden |

## Akzeptanzkriterium

Eine echte Operationalisierung ist erst sinnvoll, wenn der Proof zeigt:

- mindestens 3 von 4 Activity-Gruppen liefern einen eindeutig maschinenlesbaren aktuellen Status,
- Statuswerte koennen konservativ auf `ok`, `watch`, `blocked` oder `unknown` gemappt werden,
- Fehlerfaelle fallen sicher auf `unknown`,
- kein reines Jahresqualitaetslabel wird als aktuelle Badefreigabe missverstanden.

## Ausfuehrung

Manuell in GitHub Actions:

```text
Bathing Water Status Proof
```

Oder lokal:

```bash
python scripts/check-bathing-water-status-proof.py \
  --output tmp/bathing-water-status-proof.json \
  --markdown tmp/bathing-water-status-proof.md
```

## Interpretation

| Ergebnis | Bedeutung |
|---|---|
| `ready_to_operationalize = true` | Quellen sind ausreichend robust fuer einen Folgeworkpack |
| `ready_to_operationalize = false` | keine automatische Aktivierung; Bade-Highlights bleiben manuell/statusbasiert blockiert |
| NRW-Quelle liefert nur Jahresbewertung | keine aktuelle Badefreigabe; Status bleibt `unknown` |
| Zwemwater liefert `status:goed` / `In orde` | kann als aktueller positiver Status gewertet werden, sofern keine Gegensignale vorliegen |
| Quelle nicht erreichbar | Status `unknown`; kein Bade-Highlight |

## Weiterarbeit nach Proof

Nur bei bestandenem Proof:

1. separaten Guard-Workflow oder Content-Audit-Schritt entwerfen,
2. Status-Artefakt definieren,
3. Inbox-Faelle nur bei Statuswechsel, Widerspruch oder ablaufender Quelle erzeugen,
4. `data/offers.json` weiterhin nicht direkt blind ueberschreiben,
5. Bade-Highlights nur bei frischer positiver Quelle ausspielen.

Wenn der Proof nicht bestanden ist, bleibt der aktuelle sichere Zustand verbindlich: keine Badeempfehlung ohne frische positive Statusquelle.
<!-- === END FILE: BATHING_WATER_STATUS_PROOF.md === -->
