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
