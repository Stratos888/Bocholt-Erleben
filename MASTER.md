# MASTER – Produkt-Nordstern Bocholt erleben

Stand: 2026-07-18  
Status: stabiler strategischer Produktvertrag, kein Projektstatus

## 1. Produktauftrag

Bocholt erleben ist eine mobile-first Plattform, mit der Menschen schnell relevante Veranstaltungen, Aktivitäten und lokale Freizeitideen in Bocholt entdecken.

Das Produkt soll wirken:

- vertrauenswürdig;
- ruhig und modern;
- lokal kuratiert;
- leicht scannbar;
- technisch stabil;
- auf dem Smartphone besonders effizient.

## 2. Doppelte Wertschöpfung

### Für Nutzer

- schnell passende Ideen finden;
- aktuelle, belastbare Angaben erhalten;
- Events und Aktivitäten klar unterscheiden;
- Details, Route, Website und Teilen ohne Reibung nutzen;
- Empfehlungen als redaktionell vertrauenswürdig erleben.

### Für Veranstalter und Anbieter

- weniger Pflegeaufwand;
- professionelle einheitliche Darstellung;
- teilbare Produktassets;
- nachvollziehbare Nutzungssignale;
- einfache Änderungs-, Absage- und Aktualisierungswege;
- einen fairen, klaren Weg zu dauerhafter Sichtbarkeit.

## 3. Nicht verhandelbare Produktprinzipien

1. Kuratierung vor Masse.
2. Qualität und Eignung vor Zahlung.
3. Keine Veröffentlichungsgarantie durch Zahlung.
4. Keine gekauften Empfehlungen oder künstlichen Top-Platzierungen.
5. Keine öffentliche Zwei-Klassen-Optik zwischen redaktionellen und bezahlten Inhalten.
6. Keine Entwicklung zum Kleinanzeigen- oder Display-Werbeportal.
7. Mobile-first, aber ohne Desktop zu degradieren.
8. Automatisierung reduziert Arbeit, darf aber Unsicherheit nicht verbergen.
9. Datenquelle, Entscheidung und öffentliche Projektion bleiben nachvollziehbar.
10. Ein Premium-Zielzustand ersetzt dauerhafte Patch-Schichten und Try-and-Error-Runden.

## 4. Quellenhierarchie

- `Produktvertrag.md` definiert bereits gültige Produktmechanik.
- `COMMERCIAL_STRATEGY.md` begründet das kommerzielle Modell.
- `ROADMAP.md` priorisiert die Produktentwicklung.
- Fachliche Zielzustandsdateien beschreiben beschlossene, noch nicht zwingend umgesetzte Zukunft.
- `CURRENT_WORKPACK.md` steuert ausschließlich die aktuelle Umsetzung.

Ein Zielzustand wird erst mit Implementierung, Evidence und bewusster Vertragsaktualisierung zur gültigen Produktmechanik.

## 5. Qualitätsdomänen

Jeder Workpack prüft seine Auswirkungen auf:

- Nutzerwert und Verständlichkeit;
- Content- und Quellenqualität;
- Visual-Fit;
- Mobile-Dichte und Interaktion;
- Performance und Cache;
- Accessibility;
- SEO und Teilbarkeit;
- Anbieterwert und kommerzielle Fairness;
- Daten- und Runtime-Sicherheit;
- Wartbarkeit und Observability.

Nicht betroffene Domänen werden bewusst als unverändert dokumentiert.

## 6. Entscheidungsfilter

Ein neuer Produktworkpack ist prioritär, wenn er mindestens eines nachweislich verbessert:

- mehr relevanter Nutzerwert;
- mehr hochwertige und vollständige Inhalte;
- bessere Auffindbarkeit oder Teilbarkeit;
- weniger Pflegeaufwand;
- glaubwürdig messbarer Anbieterwert;
- höhere technische Verlässlichkeit eines kritischen Pfads.

Er wird zurückgestellt, wenn er nur allgemeines UI-Polish, technische Eleganz ohne Produktwirkung oder eine weitere parallele Logikschicht erzeugt.

Ausnahmen: belegte Produktionsfehler, Sicherheit, Recht, Datenintegrität und notwendige Regressionstests.

## 7. Arbeitsziel

Nicht möglichst viele einzelne Verbesserungen bauen, sondern jeweils den kleinsten zusammenhängenden Premium-Zielzustand:

```text
Root Cause und Owner verstehen
-> Zielarchitektur festlegen
-> zusammenhängend umsetzen
-> technisch beweisen
-> genau einmal abnehmen
-> Dokumentation auf den neuen Zustand setzen
```
