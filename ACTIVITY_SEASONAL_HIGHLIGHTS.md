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

Ergänzt wurden zwei stabile Saison-/Öffnungsfenster und zwei weitere zustandsabhängige Badegewässer-Kandidaten. Die Badegewässer-Kandidaten werden bewusst nicht aktiv ausgespielt, solange keine frische positive Quelle fachlich gepflegt ist. Der Badegewässer-Guard bleibt zunächst report-only und schreibt keine Produktdaten automatisch zurück.

## Prüfung

Lokaler Struktur-Audit:

```bash
python3 scripts/audit-activity-highlights.py --scope full
```

Der bestehende Activity-Highlight-Audit prüft die strukturierten Highlight-Daten. Zustandsabhängige Bade-/Wasser-Highlights bleiben ohne `current_status.state=ok` unsichtbar. Der separate Badegewässer-Guard kann als Report-Proof genutzt werden, schreibt aber ohne eigenes Freigabe-Workpack keine Produktdaten zurück.
