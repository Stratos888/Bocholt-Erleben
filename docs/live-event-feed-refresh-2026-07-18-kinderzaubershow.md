# Live-Event-Feed-Refresh: Kinderzaubershow

Stand: 2026-07-18

Das redaktionelle Live-Event `du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04` wurde im führenden Google-Sheets-Tab `Events` ergänzt.

Dieser dokumentationsreine Commit durchläuft zuerst den regulären Staging-Deploy. Nach erfolgreicher Integration von `staging` nach `main` wird der aktuelle Sheet-Stand nach `data/events.tsv` und `/data/events.json` exportiert und das Event öffentlich verfügbar.

Der eindeutige Event-Identifier bleibt für die spätere Reichweiten- und Aufrufauswertung erhalten.
