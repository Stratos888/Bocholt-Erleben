# Current Workpack Router

Ein aktives Workpack ist optional.

## Normaler Fall

Kleine und mittlere Änderungen benötigen kein Workpack-Issue. Bestehende Branches und Pull Requests zur Aufgabe werden geprüft und passende Arbeit wird fortgesetzt.

## Workpack-Fall

Ein Issue mit `[ACTIVE WORKPACK]` wird nur verwendet, wenn die Aufgabe mehrere Chats, Systeme oder Owner umfasst oder Schema-, Berechtigungs-, Zahlungs-, externe Write-, Deployment- oder Governancegrenzen berührt.

Jedes Workpack nennt genau einen `branch`. Der zugehörige PR enthält:

```text
Workpack: #123
```

Mehrere voneinander unabhängige aktive Workpacks dürfen existieren. Für dasselbe Workpack ist nur ein offener PR zulässig.

Operativer Status gehört ausschließlich in das jeweilige Issue. Beim Abschluss wird der Active-Marker entfernt.
