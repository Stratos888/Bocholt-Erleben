# Historischer Workpack – ausnahmebasierte Eventprüfung vom 16.07.2026

Status: **nicht mehr ausführungsleitend**  
Ersetzt durch: `STEUERZENTRALE_WORKMODE_FREEZE_2026-07-17.md`

## Grund der Ablösung

Der ursprüngliche Workpack enthielt fachlich sinnvolle Zielverträge für ausnahmebasierte Aufgaben, Evidenz, Teil-Writeback und Neubewertung. Seine Abnahmereihenfolge war jedoch nicht mit der tatsächlichen Deploy-Topologie vereinbar:

- gefordert wurde eine reale Staging-Abnahme vor dem Merge nach `staging`,
- tatsächlich wird nur der Branch `staging` auf die Staging-Umgebung deployt.

Dadurch wurde die erste reale Integration erst nach dem Merge durchgeführt und reale Google-Sheets-Daten wurden faktisch zum Testbestand. Zusätzlich waren Environment- und Datenidentitäts-Evidence nicht als verpflichtendes Merge-Gate umgesetzt.

## Weiterhin gültige fachliche Ziele

- Betreiber bearbeiten nur echte Ausnahmen statt vollständiger Formulare.
- Jede Aufgabe besitzt stabile Identität, Evidenz, zulässige Aktionen und Postconditions.
- Teilaktionen schreiben kanonisch, werden zurückgelesen und lösen eine vollständige Neubewertung aus.
- Teilaktionen schließen einen Fall nicht terminal ab.
- Zeit-, Quellen-, Dubletten- und Visualbefunde bleiben typisiert.
- Kein CityArt-Einzelhotfix.

## Nicht mehr zulässige Verwendung

Dieses Dokument darf nicht mehr verwendet werden als:

- aktive Arbeitsanweisung,
- Merge- oder Abnahmereihenfolge,
- Begründung für reale Staging-Datentests,
- Nachweis einer bestandenen E2E-Abnahme.

Die historische vollständige Implementierungsfassung bleibt über PR #78 und die Git-Historie nachvollziehbar.

## Nächste fachliche Voraussetzung

Vor jeder weiteren funktionalen Änderung ist zunächst der Governance-Workpack abzuschließen und danach die vollständige CityArt-Kette forensisch zu inventarisieren:

```text
offizielle Quelle
→ Discovery-/Extraktionsresultat
→ Inbox
→ Inbox_Staging
→ lokale Fallkopie
→ API-Payload
→ Reviewvertrag
→ UI
```
