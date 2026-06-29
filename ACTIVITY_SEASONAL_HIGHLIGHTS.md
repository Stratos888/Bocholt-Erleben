# Seasonal Activity Highlights V1

<!-- === BEGIN BLOCK: ACTIVITY_SEASONAL_HIGHLIGHTS_CURRENT_STATUS_2026_06_27 | Zweck: current-first Einordnung nach Badegewaesser-Guard-V2-Abschluss; Umfang: Status, offene Punkte, keine veraltete report-only-Lesart === -->
## Current Status 2026-06-27

Seasonal Activity Highlights V1 ist abgeschlossen. Der Badegewaesserstatus-Proof / Guard V2 ist abgehakt und nicht mehr als offener Proof-Auftrag zu lesen.

Aktueller Umgang:

- Zustandsabhaengige Bade-/Wasser-Highlights bleiben ohne finalen Guard-Status `ok` unsichtbar.
- Guard V2 schreibt nicht in `data/offers.json`, sondern sicher in `data/bathing_water_status.json`.
- Das Frontend nutzt diese Statusdatei als Override und verhindert positive Badeempfehlungen bei `watch`, `blocked` oder `unknown`.
- Weitere Arbeit nur bei konkretem Quellenausfall, falscher UI-Ausspielung oder neuer Badestelle.

<!-- === END BLOCK: ACTIVITY_SEASONAL_HIGHLIGHTS_CURRENT_STATUS_2026_06_27 === -->

## Ziel

Seasonal Activity Highlights sind eine geprüfte Zusatzschicht für Aktivitäten. Sie dürfen Home und Aktivitätenseite nur dann beeinflussen, wenn das Highlight quellenbasiert, zeitlich passend und nicht durch Status-/News-Lage blockiert ist.

## Harte Regeln

- Keine Annahmen aus Aktivitätstypen: Ein Park bekommt kein Blüten-Highlight, nur weil dort theoretisch Blumen blühen könnten.
- Konkrete Termine, Führungen, Workshops, Aktionstage und Ferienprogramme bleiben Events.
- Zustandsabhängige Highlights wie Baden, Wasserqualität oder temporäre Nutzbarkeit brauchen eine frische positive Statusquelle.
- Negative Status-/News-Signale blockieren die Ausspielung stärker als jedes Saisonfenster.
- Das Frontend ruft keine Live-News ab; es spielt nur geprüfte Repo-Daten aus.

## Aktivierungsmodi

| Modus | Zweck | Ausspielung |
|---|---|---|
| `stable_seasonal` | stabile, belegte Saisonphänomene oder belastbare Saison-/Öffnungsfenster | aktiv, wenn Zeitraum und Quellenprüfung gültig sind |
| `condition_sensitive` | kurzfristig kippende Zustände | nur aktiv mit `current_status.state=ok` und frischer offizieller/Betreiber-Quelle |
| `event_only` | konkrete Termine/Aktionen | nie als Activity-Highlight ausspielen |
| `candidate_only` | plausible, aber nicht belastbare Idee | nie öffentlich ausspielen |

## Statusmodell für `condition_sensitive`

| Status | Wirkung |
|---|---|
| `ok` | Highlight darf ausgespielt werden, wenn Quelle frisch und offiziell/Betreiberstatus ist |
| `watch` | kein Boost, ggf. Detailhinweis |
| `blocked` | kein Chip, kein Boost, kein Filtertreffer |
| `unknown` | kein Chip, kein Boost, kein Filtertreffer |

## UI-Rollen

| Fläche | Verhalten |
|---|---|
| Home | aktive Highlights dürfen stärker ranken und als kurzer Reason-Chip erscheinen |
| `/aktivitaeten/` | aktive Highlights werden moderat höher sortiert und über „Jetzt besonders“ filterbar |
| Activity-Card | aktives Highlight erscheint als kurzer Chip; konkrete Bade-/Nutzungshinweise erscheinen nur als knapper Statuschip, z. B. `Badehinweis prüfen` |
| Detailpanel | aktives Highlight oder Statushinweis wird kompakt erklärt; konkrete Ursachen wie Schlamm-/Geruchshinweis werden dort erläutert |

## Datenstand nach Content Batch 01

Aktive/stabile Highlights:

- Zwillbrocker Venn: Flamingo-Zeit
- Korenburgerveen: Wollgras-/Moorzeit
- Quellengrundpark Weseke: Apothekergarten-Saison
- Witte Venn: Heideblüte-Zeit
- Wasserburg Anholt: Landschaftspark-Saison
- Anholter Schweiz: Wildpark-Sommersaison mit langen Öffnungszeiten

Zustandsabhängige, aktuell nicht öffentlich als Bade-Highlight ausgespielte Kandidaten:

- Aasee: `watch`, solange der lokale Schlamm-/Geruchshinweis aktiv ist bzw. keine stärkere positive lokale Freigabe vorliegt
- Hilgelo: `unknown`, bis eine frische positive Badewasser-/Betreiberstatusquelle gepflegt ist
- Pröbstingsee: `unknown`, bis eine frische positive Badewasser-/Betreiberstatusquelle gepflegt ist
- Auesee Wesel: `unknown`, bis eine frische positive Badewasser-/Betreiberstatusquelle gepflegt ist

## Content Batch 01 Scope

Content Batch 01 ergänzt nur sichere Datensätze:

- keine Blüten-/Tierannahmen ohne konkrete Quelle
- keine konkreten Aktionen als Activity-Highlight
- keine Badeempfehlung ohne aktuelle positive Statusquelle
- keine Live-News-Abfrage im Browser

Ergänzt wurden zwei stabile Saison-/Öffnungsfenster und zwei weitere zustandsabhängige Badegewässer-Kandidaten. Die Badegewässer-Kandidaten werden bewusst nicht aktiv ausgespielt, solange keine frische positive Quelle fachlich gepflegt ist. Korrektur 2026-06-27: Guard V2 bleibt zwar safe-writeback und schreibt nicht direkt in `data/offers.json`, schreibt aber in die getrennte Statusdatei `data/bathing_water_status.json`; die frühere reine `report-only`-Einordnung ist überholt.

## Prüfung

Lokaler Struktur-Audit:

```bash
python3 scripts/audit-activity-highlights.py --scope full
```

Der bestehende Activity-Highlight-Audit prüft die strukturierten Highlight-Daten. Zustandsabhängige Bade-/Wasser-Highlights bleiben ohne finalen Status `ok` unsichtbar. Guard V2 aktualisiert den Badegewässerstatus über die getrennte Datei `data/bathing_water_status.json`; `data/offers.json` bleibt redaktionelles Stammdatenartefakt.

## Badegewässer-Statusdatei V2

Ab Guard V2 ist `data/offers.json` nicht mehr die einzige Statusquelle für Badegewässer. Die redaktionellen Activity-Daten bleiben dort erhalten; der tagesaktuelle Guard-Status wird separat generiert:

```text
data/bathing_water_status.json
```

Frontend-Regel:

1. `data/offers.json` liefert die Activity- und Highlight-Stammdaten.
2. `data/bathing_water_status.json` überschreibt bei Badegewässer-Highlights nur `current_status`.
3. Wenn die Statusdatei fehlt, kaputt oder nicht passend ist, bleibt der konservative Fallback aus `offers.json` aktiv.
4. `ok` aus der Statusdatei darf nur aktivierend wirken, wenn der Guard `water_state=ok` und `local_suitability_state=ok` liefert.
5. `watch`, `blocked` und `unknown` verhindern weiter Bade-Highlights und Bade-Boosts.

Damit können tägliche Guard-Läufe Live-Hinweise aktualisieren, ohne redaktionelle Stammdaten automatisch umzuschreiben.
