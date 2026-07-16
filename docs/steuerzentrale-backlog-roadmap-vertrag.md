# Steuerzentrale – Backlogvertrag

Stand: 2026-07-16  
Status: verbindlicher Produktvertrag  
Umgebung der Erstimplementierung: `staging`

## Zweck

Der Bereich **Backlog** ist die vollständige Informations- und Reihenfolgeansicht für den Betreiber:

- Was ist noch vorgesehen?
- Welcher Punkt sollte sinnvollerweise zuerst folgen?
- Was wurde bereits abgeschlossen?

Er ist keine Prüfqueue und keine operative Fallbearbeitung. Die Punkte selbst dürfen und müssen jedoch direkt gepflegt werden können.

## Kanonische Status

Es gibt fachlich genau zwei Status:

```text
offen
abgeschlossen
```

Quellvarianten wie `open`, `done`, `closed` oder `erledigt` werden ausschließlich auf diese zwei Status normalisiert. Unbekannte Quellwerte bleiben als `offen` sichtbar, damit kein Punkt still verloren geht.

Ein abgeschlossener Punkt kann wieder geöffnet werden. Ablehnen, Zurückstellen, Blockieren oder lokale Zwischenstatus sind nicht Bestandteil des Backlogs.

## Sichtbarkeit

- Alle kanonischen Punkte werden immer angezeigt.
- Offene Punkte stehen zuerst.
- Abgeschlossene Punkte bleiben darunter sichtbar.
- Es gibt keinen Filter über aktive `control_cases`.
- Es gibt keine lokalen Backlogzustände, die von der kanonischen Quelle abweichen.

## Reihenfolge

Offene Punkte werden dargestellt nach:

1. Priorität (`hoch`, `mittel`, `niedrig`),
2. kanonischer Reihenfolge in der führenden Quelle.

Die Ansicht weist jedem offenen Punkt eine sichtbare empfohlene Reihenfolgenummer zu. Abgeschlossene Punkte erhalten keine neue Arbeitsreihenfolge.

## Seitenkopf

- Der feste Seitentitel lautet ausschließlich **Backlog**.
- Im Inhaltsbereich gibt es keinen zusätzlichen Kicker, keinen doppelten Titel, keinen Leadtext und keinen zusammenfassenden Informationsblock.
- Gesamtzahlen werden nicht in einem eigenen Kopfblock wiederholt; die Anzahl steht dezent an den Bereichen `Offen` und `Abgeschlossen`.
- Sichtbar bleibt der primäre Einstieg **+ Neuer Punkt**.

## Darstellung

- Jeder Punkt wird zunächst als kompakte einzelne Zeile dargestellt.
- Die geschlossene Zeile zeigt Reihenfolgenummer, Titel, Status, Priorität, Themenbereich und eine kurze Vorschau.
- Die Zeile weist verständlich auf **Details & Bearbeiten** hin.
- Erst durch Klick auf die Zeile werden Begründung, empfohlener nächster Schritt, erwarteter Nutzen, Abschlussinformationen und Bearbeitungsaktionen aufgeklappt.
- Beim Laden der Ansicht ist kein Punkt automatisch geöffnet.
- Die kompakte Darstellung ändert nichts an der vollständigen fachlichen Sichtbarkeit: Alle Punkte bleiben als Zeilen vorhanden.

## Bearbeitung

Direkt in der Steuerzentrale müssen möglich sein:

- neuen offenen Punkt anlegen,
- Titel bearbeiten,
- Priorität bearbeiten,
- Themenbereich bearbeiten,
- Relevanz beziehungsweise Begründung bearbeiten,
- empfohlenen nächsten Schritt bearbeiten,
- erwarteten Nutzen bearbeiten,
- offenen Punkt als abgeschlossen markieren,
- abgeschlossenen Punkt wieder öffnen,
- optionale Abschlussnotiz erfassen.

Die Bearbeitung schreibt unmittelbar in den kanonischen `Growth_Backlog`. Ein zwischenzeitlich geänderter Datensatz darf nicht still überschrieben werden; der Client sendet deshalb den zuletzt gesehenen Änderungszeitpunkt als Konfliktschutz.

## Nicht zulässige Funktionen

Im Backlogbereich sind nicht zulässig:

- Ablehnen,
- Zurückstellen,
- Blockieren,
- in Bearbeitung setzen,
- generische Fallaktionen,
- Ausblendung abgeschlossener Punkte,
- Badge-Logik, die den Bereich als offene Prüfqueue darstellt,
- dauerhaft vollständig aufgeklappte Karten für alle Punkte,
- ein zusätzlicher Informations- oder Erklärblock oberhalb der Liste.

## Technischer Vertrag

```text
Growth_Backlog
→ alle nichtleeren Quellzeilen lesen
→ Status auf offen/abgeschlossen normalisieren
→ offene Punkte nach Priorität und Quellreihenfolge sortieren
→ abgeschlossene Punkte weiterhin ausgeben
→ als kompakte aufklappbare Zeilen direkt an die Steuerzentrale liefern
→ Create/Edit/Complete/Reopen direkt gegen die kanonische Quelle schreiben
```

`control_cases` bleibt tatsächlichen Prüf-, Entscheidungs- und Systemvorgängen vorbehalten. Historisch synchronisierte Growth-Backlog-Fälle werden nicht mehr als führende Backlogquelle verwendet.

## Abnahme

Der Vertrag gilt als erfüllt, wenn die Staging-Ansicht:

- direkt unter dem Seitentitel ohne zusätzlichen Kopfblock beginnt,
- einen sichtbaren Button `+ Neuer Punkt` anbietet,
- sämtliche Quellpunkte als kompakte Zeilen darstellt,
- offene und abgeschlossene Bereiche gleichzeitig sichtbar macht,
- einen Punkt per Klick auf- und wieder zuklappen kann,
- im geöffneten Punkt Bearbeiten und Statuswechsel anbietet,
- Änderungen nach Speichern und Neuladen unverändert zeigt,
- beim Laden keinen Punkt automatisch öffnet,
- ausschließlich die Status offen und abgeschlossen verwendet,
- dieselben Zahlen wie die kanonische Quelle verwendet.
