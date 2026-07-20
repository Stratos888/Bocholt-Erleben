# Abgeschlossener Workpack – Evidence-first Ausführungsmodell

Stand: 2026-07-20

## Anlass

Die Workflow-Konsolidierung erreichte den richtigen technischen Endzustand, benötigte nach der ersten Integration jedoch mehrere nachgelagerte Korrektur-PRs. Die Sicherheitsmechanismen funktionierten fail-closed, aber das Evidence-Design war vor dem ersten Patch nicht vollständig genug:

- technische E3-Evidence hing zunächst von einem vorhandenen echten Fachfall ab;
- der ausdrücklich eingefrorene CityArt-Fall wurde read-only als Prüfobjekt ausgewählt;
- technische Runtime-Verlässlichkeit und fachliche Entscheidungsreife waren zunächst gekoppelt;
- erst die abschließende Korrektur führte einen fachfallfreien Runtime-Ressourcenvertrag ein.

Das Ergebnis war sicher, aber nicht effizient genug für das Projektziel, zusammenhängende Premium-Zustände möglichst ohne Merge-/Deploy-Try-and-Error zu erreichen.

## Ausgangsstand

- `staging`-Baseline: `a2813789d6c5b3891d15652493be18fc880424c7`
- offene PRs bei Aktivierung: keine
- aktiver Implementierungs-Workpack bei Aktivierung: keiner
- technische Evidence des Control-Center-Pfads: E1, E2 und fachfallfreies read-only E3
- externe Mutation: keine

## Zielzustand

Das bestehende Governance-Modell bleibt erhalten, wird aber in der Ausführung verbindlich präzisiert:

```text
Problem und Owner verstehen
-> Evidence vor dem Patch vollständig entwerfen
-> proportionalen Prüfpfad wählen
-> zusammenhängend implementieren
-> E2 und Runtime-Design-Gate vor Integration
-> erforderliche E3/E4-Evidence
-> höchstens eine begrenzte Korrekturrunde
-> sonst Revert-, Architektur- oder Workpack-Neuentscheidung
-> Governance abschließen und zur Produktwirkung zurückkehren
```

## Dauerhafte Entscheidungen

### 1. Evidence-Design gehört zu Gate A

Für `R2` und `R3` müssen vor dem ersten Patch konkret feststehen:

- Host, Endpoint, Trigger oder Workflow;
- Umgebung, Quelle, Zielressource, Identität und Operationsplan;
- positive und negative Assertions;
- erforderliche Daten;
- Fachfallabhängigkeit oder ausdrücklich fachfallfreier Nachweis;
- E2-Contract, Fixture oder Replay für spätere Runtime-Assertions;
- Postconditions, Cleanup und Revert.

### 2. Technische Runtime-Evidence ist grundsätzlich fachfallfrei

Ein zufällig vorhandener, eingefrorener oder fachlich ungeklärter Echtdatensatz darf keine technische Infrastruktur-Postcondition tragen. Ein echter Fachfall wird nur verwendet, wenn der Fachfall selbst ausdrücklich Prüfgegenstand ist.

### 3. Prüfaufwand wird proportional angewendet

- `R1`: aktueller Owner, begrenzter Diff, passende statische Prüfung und direkte Abnahme;
- `R2`: vollständiger Runtimevertrag, E2, Deploy und E3;
- `R3`: vollständiger Ressourcen- und Transaktionsvertrag sowie E4 vor echtem Fachfall;
- Vollinventuren nur bei tatsächlich konkurrierenden Ownern, Triggern, Resolvern, Writern oder Runtimepfaden.

### 4. Runtime-Design-Gate vor Integration

Ein `R2`- oder `R3`-Workpack darf erst nach `staging`, wenn alle späteren E3-/E4-Assertions eindeutig und maschinenprüfbar definiert sind und der vorgesehene Runtime-Pfad die behauptete Eigenschaft tatsächlich beweisen kann.

### 5. Nachgelagertes Korrekturbudget

Nach einer fehlgeschlagenen Integration ist höchstens eine eng begrenzte Korrekturrunde im selben Workpack zulässig. Scheitert auch diese oder war eine tragende Annahme falsch, folgt kein weiterer Reparatur-PR, sondern eine Revert-, Architektur- oder Workpack-Neuentscheidung.

Sorgfältige E1-/E2-Korrekturen auf dem Feature-Branch vor der ersten Integration bleiben zulässig und erwünscht.

### 6. Meta-Optimierung wird beendet

Allgemeine Governance-, Dokumentations- oder Prozessarbeit wird nur noch bei einer neuen belegten Lücke eröffnet. Nach diesem Workpack kehrt die technische Reihenfolge zum bereits dokumentierten R3-Pflichtpfad und anschließend zu Produktworkpacks zurück.

## Geänderte Owner

- `AI_ENTRYPOINT.md`: operativer Arbeits-, Gate-, Evidence- und Korrekturvertrag
- `.github/pull_request_template.md`: verpflichtende Anwendung im PR
- `docs/workpacks/active/CURRENT_WORKPACK.md`: integrierter Folgezustand
- dieses abgeschlossene Workpack: historische Begründung und Entscheidung

`ENGINEERING.md` bleibt unverändert, weil es den Arbeitsablauf ausdrücklich an `AI_ENTRYPOINT.md` delegiert und seine bestehenden technischen Guardrails mit den neuen Regeln vereinbar sind.

## Risikoklasse und Grenzen

- Risikoklasse: `R1`
- reine Dokumentations- und PR-Prozessänderung
- keine Workflowänderung
- keine Runtime-, Produkt-, UI-, SEO-, Content- oder Visualänderung
- keine externe Ressource gelesen oder mutiert
- kein Deploy erforderlich, außer den durch die normale Staging-Integration technisch ausgelösten Standardpfaden
- kein E4-Lauf
- kein CityArt-Fachfall

## Abschlusskriterien

- dauerhafte Regeln stehen ausschließlich im operativen Owner `AI_ENTRYPOINT.md`;
- PR-Template erzwingt Evidence-Design, Proportionalität und Korrekturbudget;
- `CURRENT_WORKPACK.md` nennt weiterhin genau einen nächsten zulässigen Schritt;
- Dokumentationsinventur und Governance-Audit sind grün;
- `PR Gate` ist grün;
- PR ist nach `staging` integriert;
- danach besteht kein weiterer allgemeiner Governance-Workpack.

## Folgezustand

Der nächste erlaubte technische Schritt bleibt ein separat aktivierter `R3`-Workpack für genau einen isolierten synthetischen E4-Staging-Lauf. Dieser Workpack muss das neue Evidence-first-Modell bereits vor dem ersten Patch vollständig anwenden.
