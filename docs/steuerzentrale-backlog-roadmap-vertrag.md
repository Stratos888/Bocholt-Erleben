# Steuerzentrale – Backlog-/Roadmapvertrag

Stand: 2026-07-15  
Status: verbindlicher Produktvertrag  
Umgebung der Erstimplementierung: `staging`

## Zweck

Der Bereich **Backlog** ist ausschließlich eine Informations- und Roadmapansicht für den Betreiber:

- Was ist noch vorgesehen?
- Welcher Punkt sollte sinnvollerweise zuerst folgen?
- Was wurde bereits abgeschlossen?

Er ist ausdrücklich **keine Arbeitsliste, Prüfqueue oder Fallbearbeitung**.

## Kanonische Status

Es gibt fachlich genau zwei Status:

```text
offen
abgeschlossen
```

Quellvarianten wie `open`, `done`, `closed` oder `erledigt` werden ausschließlich auf diese zwei Status normalisiert. Unbekannte Quellwerte bleiben als `offen` sichtbar, damit kein Punkt still verloren geht.

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

## Darstellung

- Jeder Punkt wird zunächst als kompakte einzelne Zeile dargestellt.
- Die geschlossene Zeile zeigt Reihenfolgenummer, Titel, Status, Priorität und Themenbereich.
- Eine kurze Vorschau darf einzeilig ergänzt werden.
- Erst durch Klick auf die Zeile werden Begründung, empfohlener nächster Schritt, erwarteter Nutzen und Abschlussinformationen aufgeklappt.
- Beim Laden der Ansicht ist kein Punkt automatisch geöffnet.
- Die kompakte Darstellung ändert nichts an der vollständigen fachlichen Sichtbarkeit: Alle Punkte bleiben als Zeilen vorhanden.

## Inhalt eines Punktes

Mindestens sichtbar:

- Status,
- empfohlene Reihenfolge bei offenen Punkten,
- Titel,
- Priorität,
- Typ/Themenbereich,
- Relevanz beziehungsweise Begründung,
- empfohlener nächster Schritt,
- erwarteter Nutzen,
- bei abgeschlossenen Punkten Abschlussnotiz und Abschlussdatum, sofern vorhanden.

Die ersten fünf Angaben gehören in die kompakte Zeile. Die übrigen Angaben erscheinen im aufgeklappten Detailbereich.

## Nicht zulässige Funktionen

Im Backlogbereich sind nicht zulässig:

- Ablehnen,
- Zurückstellen,
- Blockieren,
- in Bearbeitung setzen,
- generische Fallaktionen,
- Ausblendung abgeschlossener Punkte,
- Badge-Logik, die den Bereich als offene Arbeitsqueue darstellt,
- dauerhaft vollständig aufgeklappte Karten für alle Punkte.

## Technischer Vertrag

```text
Growth_Backlog
→ alle nichtleeren Quellzeilen lesen
→ Status auf offen/abgeschlossen normalisieren
→ offene Punkte nach Priorität und Quellreihenfolge sortieren
→ abgeschlossene Punkte weiterhin ausgeben
→ als kompakte aufklappbare Zeilen direkt an die Steuerzentrale liefern
```

`control_cases` bleibt tatsächlichen Prüf-, Entscheidungs- und Systemvorgängen vorbehalten. Historisch synchronisierte Growth-Backlog-Fälle werden als reine Information neutralisiert und nicht mehr als führende Backlogquelle verwendet.

## Abnahme

Der Vertrag gilt als erfüllt, wenn die Staging-Ansicht:

- Gesamtzahl, offene und abgeschlossene Zahl zeigt,
- sämtliche Quellpunkte als kompakte Zeilen darstellt,
- offene und abgeschlossene Bereiche gleichzeitig sichtbar macht,
- einen Punkt per Klick auf- und wieder zuklappen kann,
- beim Laden keinen Punkt automatisch öffnet,
- keine Arbeitsfallaktionen anbietet,
- dieselben Zahlen wie die kanonische Quelle verwendet.
