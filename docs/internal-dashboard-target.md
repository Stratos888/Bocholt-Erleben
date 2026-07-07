# Internes Dashboard – Zielbild

Stand: 2026-07-07
Branch: `staging`

Der aktuelle Stand von `/intern/dashboard/` ist nur ein technischer Datenbeweis fuer den Content-Ops-HTTP-Ingest. Er ist nicht der abgenommene Zielzustand.

Bewiesen ist: Login, SQL-Lesen und mindestens ein persistierter Growth-Run funktionieren.

Nicht abgenommen sind: Informationsarchitektur, mobile Nutzbarkeit, Konsolidierung mit Inbox und SEO-Dashboard, Aufgabenpriorisierung, Betreiberverstaendlichkeit und visuelles Premium-Niveau.

Ziel ist eine zentrale mobile Verwaltungsoberflaeche. Sie soll Inbox, Content-Ops, SEO/Growth, KI-Suchlauf, Feedback-Wirkung und Laufstatus konsolidieren. Bestehende Einzelseiten bleiben vorerst bestehen und werden erst entfernt, wenn die neue Verwaltung abgenommen ist.

Mobile ist fuehrend. Keine lange Reportseite, keine Tabellen als Hauptnavigation, keine langen Prosatexte. Wichtig sind kurze Labels, Status-Chips, klare Primaeraktionen, echte Aufgaben zuerst und technische Details nur im Drilldown.

Die Startansicht soll sofort beantworten:

1. Was ist gerade wichtig?
2. Was muss ich entscheiden?
3. Was laeuft automatisch gut?
4. Wo gibt es Risiken?
5. Welche Verbesserung hat messbar gewirkt?

Naechster Schritt: bestehende interne Seiten und Datenquellen erfassen, Betreiber-Jobs definieren, Zielnavigation fuer `/intern/dashboard/` festlegen und erst danach die UI neu schneiden.

Der Commit `58eca69` (`Ergaenze internes Verwaltungsdashboard`) darf ersetzt oder stark umgebaut werden.

## Zielvertrag fuer die Betreiber-Zentrale

Das Dashboard ist nicht der Hauptprozess. Es ist nur die mobile Oberflaeche fuer einen vorher funktionierenden Content-/KI-/Feedback-Betrieb.

Vor einem finalen Dashboard muss der Selbstlernprozess belastbar geschlossen sein:

```text
Roboterlauf
-> normalisiertes Signal
-> echte Betreiber-Aufgabe oder automatische Beobachtung
-> Betreiberentscheidung
-> typisiertes Feedback
-> Feedback-Regel / Suchregel / Suppression
-> naechster Lauf wird besser
-> messbare Wirkung
```

Solange dieser Kreislauf nicht vollstaendig funktioniert, darf `/intern/dashboard/` nur als Zwischenansicht gelten.

## Beste Dashboard-Form

Nicht nach technischen Quellen sortieren. Keine Hauptnavigation nach `Inbox`, `SEO`, `KI`, `Growth` oder `Content-Ops`.

Die zentrale Sicht wird nach Betreiber-Jobs aufgebaut:

1. `Heute` – Lage in einem Satz: alles ruhig, X Entscheidungen offen oder X Risiken.
2. `Aufgaben` – alle echten Entscheidungen an einem Ort, unabhaengig von Quelle.
3. `Automatik` – alle Roboter als Ampel: gruen, pruefen, ueberfaellig.
4. `Wirkung` – nur wenige verstaendliche Vorher-/Nachher-Werte.
5. `Diagnose` – technische Tabellen, Rohmetriken, Run-URLs und interne Namen nur eingeklappt oder in Detailansicht.

Die Startansicht soll keine lange Scroll-Reportseite sein. Auf Mobile muss innerhalb weniger Sekunden klar sein:

- Muss ich jetzt etwas tun?
- Wenn ja: was genau?
- Wenn nein: welche Automatik laeuft gut?
- Gibt es etwas zu beobachten?
- Hat Feedback/Suche/Pruefung messbar geholfen?

## Hauptansicht: erlaubt und nicht erlaubt

Erlaubt sind Betreiber-Labels wie:

- `2 neue Event-Kandidaten`
- `1 Content-Fall pruefen`
- `KI-Suche zuletzt heute gruen`
- `9 schlechte Treffer automatisch aussortiert`
- `Feedback verhindert bekannte Fehlertreffer`

Nicht erlaubt in der Hauptansicht:

- `source_mode`
- `metric_scope`
- `run_fingerprint`
- `content_ops_action_log`
- rohe Metric-Keys wie `growth.backlog.gsc_rows`
- lange technische Erklaertexte
- Tabellen als Hauptansicht

Diese Werte duerfen nur in `Diagnose` erscheinen.

## Kartenlogik

Jede sichtbare Hauptkarte muss genau eine Betreiberfrage beantworten:

| Kartentyp | Betreiberfrage |
|---|---|
| Entscheidung | Muss ich jetzt etwas entscheiden? |
| Beobachtung | Muss ich etwas im Auge behalten, aber nicht sofort handeln? |
| Automatik | Laeuft ein Roboter korrekt oder ist er ueberfaellig/rot? |
| Wirkung | Hat eine Massnahme messbar geholfen? |
| Diagnose | Was steckt technisch dahinter? |

Was nicht in diese Typen passt, gehoert nicht in die Hauptansicht.

## Aufgaben-Priorisierung

`Aufgaben` ist die zentrale Inbox der Betreiber-Zentrale. Sie ersetzt nicht zwingend sofort bestehende Detailseiten, aber aus Nutzersicht sollen alle Entscheidungen dort zusammenlaufen:

- neue KI-Event-Kandidaten,
- Content-Pruefungsfaelle,
- offene Inbox-/Review-Faelle,
- relevante Bild-/Motiv-Pruefungen,
- echte Growth-Freigaben,
- technische Betreiberentscheidungen nur bei akutem Handlungsbedarf.

Sortierung erfolgt nach Dringlichkeit und Entscheidungsbedarf, nicht nach Quellsystem.

## Bau-Reihenfolge

1. Selbstlernprozess pruefen und offene Luecken schliessen.
2. Roboter-Signale einheitlich in `Aufgabe`, `Beobachtung`, `Automatik`, `Wirkung`, `Diagnose` uebersetzen.
3. Erst danach `/intern/dashboard/` final als Betreiber-Zentrale bauen.

Ein Dashboard-Patch ohne vorher belastbar geschlossenen Selbstlernprozess ist nicht der Zielzustand.
