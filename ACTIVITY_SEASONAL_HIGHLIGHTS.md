# Seasonal Activity Highlights V1

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
| Activity-Card | aktives Highlight erscheint als kurzer Chip |
| Detailpanel | aktives Highlight oder Statushinweis wird kompakt erklärt |

## Datenstand nach Content Batch 01

Aktive/stabile Highlights:

- Zwillbrocker Venn: Flamingo-Zeit
- Korenburgerveen: Wollgras-/Moorzeit
- Quellengrundpark Weseke: Apothekergarten-Saison
- Witte Venn: Heideblüte-Zeit
- Wasserburg Anholt: Landschaftspark-Saison
- Anholter Schweiz: Wildpark-Sommersaison mit langen Öffnungszeiten

Zustandsabhängige, aktuell nicht öffentlich als Bade-Highlight ausgespielte Kandidaten:

- Aasee: `blocked`, solange keine frische positive Freigabe vorliegt
- Hilgelo: `unknown`, bis eine frische positive Badewasser-/Betreiberstatusquelle gepflegt ist
- Pröbstingsee: `unknown`, bis eine frische positive Badewasser-/Betreiberstatusquelle gepflegt ist
- Auesee Wesel: `unknown`, bis eine frische positive Badewasser-/Betreiberstatusquelle gepflegt ist

## Content Batch 01 Scope

Content Batch 01 ergänzt nur sichere Datensätze:

- keine Blüten-/Tierannahmen ohne konkrete Quelle
- keine konkreten Aktionen als Activity-Highlight
- keine Badeempfehlung ohne aktuelle positive Statusquelle
- keine Live-News-Abfrage im Browser

Ergänzt wurden zwei stabile Saison-/Öffnungsfenster und zwei weitere zustandsabhängige Badegewässer-Kandidaten. Die Badegewässer-Kandidaten werden bewusst nicht aktiv ausgespielt, solange der tägliche Status-Guard keine frische positive Quelle im Repo hinterlegt hat.

## Prüfung

Lokaler Struktur-Audit:

```bash
python3 scripts/audit-activity-highlights.py --scope full
```

Der bestehende Content-Quality-Audit ruft den Activity-Highlight-Status-Guard zusätzlich auf. Im täglichen Scope werden insbesondere zustandsabhängige Highlights geprüft, damit Badesee-/Status-Empfehlungen nicht allein aus Saisonfenstern entstehen.
