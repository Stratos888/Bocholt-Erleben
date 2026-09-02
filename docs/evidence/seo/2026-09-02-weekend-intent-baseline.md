# Weekend Intent Baseline — 2026-09-02

## Ausgangslage
- `/events/` ist indexierbar und technisch vorhanden.
- Die Zeitoption `weekend` existiert bislang nur als JavaScript-Filterzustand ohne eigene URL, Canonical oder Sitemap-Repräsentation.
- Der belegte Wochenend-Intent verliert Sichtbarkeit/Klicks; konkurrierende Portale bedienen denselben Intent mit crawlbaren Landingpages.

## Maßnahme
Genau eine indexierbare Seite `/events/wochenende/`, täglich aus dem bestehenden Eventbestand gerendert.

## Messgrenze
Die Wirkung wird nach Live-Release über die bestehende GSC-Messung bewertet. Keine Aussage über Erfolg vor tatsächlicher Search-Console-Evidence.

## Explizit zurückgestellt
Der Intent `heute` wird nicht durch eine zusätzliche Route verändert, bevor Query×Page-Daten geklärt haben, ob `/` und `/events/` konkurrieren.
