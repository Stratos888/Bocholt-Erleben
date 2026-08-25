# Agent Operating Contract V1 – Bocholt erleben

Status: **verbindlicher AI Entry Point**. Diese Datei steuert, **wie** KI-Arbeit im Repository ausgeführt wird. Fachliche Wahrheiten bleiben in ihren bestehenden Ownern.

## 0. Kernregeln

```text
ITERATE TO MATERIAL IMPROVEMENT, NOT TO A FIXED COUNT.
REAL EVIDENCE AND VALIDATION OUTRANK ADDITIONAL INTERNAL ITERATION.
STOP AT DIMINISHING RETURNS OR THE CURRENT EVIDENCE / ACCESS CEILING.
```

Ziel ist nicht der schnellste lokale Patch, sondern der kleinste zusammenhängende Zielzustand, der zum Gesamtprodukt passt, vorhandene Systeme weiterverwendet und real verifiziert werden kann.

## 1. Authority und Quellen der Wahrheit

Vor substantieller Arbeit die Authorities nach ihrer Rolle behandeln, nicht gegeneinander vermischen:

| Authority | Rolle |
|---|---|
| `AGENTS.md` | Arbeitsweise, Task Routing, Qualitätsloop und erlaubte Endzustände |
| `MASTER.md` | strategischer Produkt-Nordstern und nicht verhandelbare Produktprinzipien |
| `Produktvertrag.md` | verbindliche Produktlogik in seinem Geltungsbereich |
| `ENGINEERING.md` | dauerhafte technische, Safety-, Git- und Evidence-Regeln |
| `docs/architecture/SYSTEM_MAP.md` | Systeme, Owner, Datenhoheit und kritische Datenflüsse |
| `docs/external-resource-matrix.md` | externe Ressourcen, Schreibgrenzen und Side Effects |
| `ROADMAP.md` | Prioritäten und Zukunftsziele; kein aktueller operativer Status |
| aktives Workpack-Issue | operativer Scope und Status dieses Workpacks |
| `TEST_STATUS.md` | dauerhafte Prooffähigkeiten und Evidence-Grenzen; kein Ersatz für aktuellen Runtime-Status |
| `DEBUG.md` | wiederverwendbare Root-Cause-Proofs für passende Bugklassen |

Fachliche Zielzustands- oder Brand-Dateien beschreiben Zukunft nur in ihrem erklärten Scope. Sie werden nicht allein durch Existenz zur aktuellen Produktwahrheit.

Aktueller Code, Datenstand, Branch, Deploy und reale Tests bestimmen den **Current State**. Sie dürfen einen normativen Produktvertrag nicht stillschweigend überschreiben. Bei einem materiellen Widerspruch zuerst Authority und Ursache klären; nicht durch einen lokalen Patch eine dritte Wahrheit erzeugen.

## 2. Task Routing und Start

Vor jeder Mutation:

1. den vom Auftrag betroffenen Branch und aktuellen SHA verifizieren; normale Entwicklungsbaseline ist `staging`, `main` ist Live-Releasebranch;
2. offene Branches, PRs und Workpacks auf vorhandene passende Arbeit oder Owner-Kollision prüfen;
3. fachliche und technische Owner des Ursprungswerts bestimmen;
4. bestehende vergleichbare Implementierung und relevante Tests suchen;
5. nur danach schreiben.

Für substantielle Änderungen mindestens `MASTER.md`, `ENGINEERING.md` und die betroffenen Teile von `docs/architecture/SYSTEM_MAP.md` berücksichtigen; dazu die fachlich relevante Authority. Nicht pauschal das gesamte Repository lesen, aber die reale Impact Surface darf nicht auf die zuerst sichtbare Datei verkürzt werden.

### Routing nach Aufgabenart

- **UI / UX:** relevante Produktlogik, bestehende vergleichbare Seiten/Komponenten, Tokens und UI-Patterns lesen; keine neue UI-DNA oder page-spezifische Sonderlogik ohne belegten Bedarf. Sichtbare Wirkung mit relevanten Viewports und Browser-/Screenshot-Evidence prüfen.
- **Bugfix:** owning Code und nächstliegende Regressionstests lesen; bei passender Bugklasse `DEBUG.md` nutzen. Root Cause vor Symptompatch. Einen neuen Debug-Vertrag nur bei dauerhaft wiederverwendbarem Wissensdelta ergänzen.
- **API / Behavior / State:** Produktvertrag, Owner/Dataflow in `SYSTEM_MAP`, Status-/Side-Effect-Owner und bestehende Contracttests prüfen. Zustandsübergänge und unveränderte Invarianten real oder mit repräsentativer Contract-Evidence belegen.
- **Event / Datenquelle:** kanonische Quelle und Projektionen bestimmen. Generierte Artefakte nicht manuell zur zweiten Wahrheit machen. Environment-, Feed-, Audit- und Writer-Grenzen der owning Workflows/Tools respektieren.
- **größere Produkt-/Architekturentscheidung:** `MASTER.md`, relevanten Produktvertrag, `ROADMAP.md`, `SYSTEM_MAP.md` und bestehende Zielverträge gemeinsam prüfen. Neue Systeme nur, wenn vorhandene Owner das Ziel nachweislich nicht tragen können.
- **externe Writes:** zusätzlich `docs/external-resource-matrix.md`; stabile Identität, Before State, begrenzte Mutation, Readback und Rollback/Cleanup vor dem Write festlegen.
- **Governance, Deployment, Schema, Payment, Berechtigungen oder andere zentrale Risikogrenzen:** Workpack gemäß `ENGINEERING.md` und aktuellem PR-Gate-Vertrag verwenden.

Reine Analyse bleibt read-only.

## 3. Current State, Target State und Invarianten

Vor einer substantiellen Änderung einen **flüchtigen Task Frame** bilden. Er wird nicht als neue Datei oder Statusdatenbank gespeichert, sofern kein Workpack ihn ausdrücklich benötigt.

```text
OBJECTIVE        gewünschte Nutzer-/Systemwirkung
CURRENT STATE    belegter aktueller Zustand und relevante Evidence
TARGET STATE     kleinster zusammenhängender Sollzustand
INVARIANTS       was fachlich/technisch unverändert wahr bleiben muss
OWNERS / IMPACT  Ursprungsowner, Projektionen und betroffene Nachbarsysteme
EXISTING PATTERN vorhandene Lösung, die bevorzugt erweitert/wiederverwendet wird
VALIDATION       günstigste belastbare Evidence bis zur real nötigen Stufe
EVIDENCE CEILING aktuell erreichbare Beweisgrenze
```

Bei trivialen Text-, Format- oder eindeutig lokalen Korrekturen darf dieser Frame implizit und kurz bleiben. Keine Analyse produzieren, die das Risiko nicht verändert.

## 4. Existing-Pattern-First und System Fit

Vor einer neuen Lösung prüfen:

- **PRODUCT FIT:** verbessert sie den beauftragten Nutzer-/Anbieterwert und respektiert Produktprinzipien?
- **ARCHITECTURE FIT:** sitzt die Änderung beim richtigen Owner und erzeugt keine zweite Source of Truth?
- **UX / DESIGN FIT:** folgt sie vorhandener Informations-, Komponenten-, Token- und Interaktionslogik?
- **DATA / BEHAVIOR FIT:** stimmen Identitäten, Zustände, Projektionen, Environment und Side Effects?
- **REGRESSION:** welche bestehenden Wege oder Zustände können sich mitverändern?
- **SIMPLIFICATION:** kann bestehende Sonderlogik entfernt oder vermieden werden?
- **SYSTEM FIT:** ist der lokale Gewinn auch im Gesamtfluss ein Gewinn?

Eine neue Abstraktion, Tabelle, API, UI-Komponente, Workflow- oder Statuslogik ist nur zulässig, wenn ein vorhandenes Pattern den Zielzustand materiell nicht tragen kann. Begründung und Evidence müssen stärker sein als die Zusatzkomplexität.

## 5. Adaptive Quality Loop

Nach dem ersten kohärenten Lösungsentwurf genau auf **materielles** Verbesserungspotenzial challengen:

1. widerspricht die Lösung einer Authority oder Invariante?
2. wurde ein Ursprungsowner oder bestehendes Pattern übersehen?
3. entsteht neue Sonder-, Override-, Wrapper- oder Parallel-Logik?
4. gibt es einen einfacheren Zielzustand mit gleicher Wirkung?
5. beruht die Lösung auf einer ungeprüften Annahme über reale Daten, States oder Runtime?
6. fehlen betroffene Randzustände oder Regressionen?

Ergibt der Challenge einen materiellen Vorteil, Lösung adaptieren und erneut validieren. Ergibt er nur kosmetische oder theoretische Verbesserungen, stoppen.

Sobald eine reale Prüfung die nächste relevante Unsicherheit günstiger auflösen kann, wird getestet statt weiter intern iteriert.

## 6. Repeated-Patch- und Contradiction-Trigger

Nicht weiter lokal patchen, wenn eines davon eintritt:

- derselbe Fehler oder dieselbe Wirkung kehrt nach einem Fix zurück;
- der nächste Fix wäre ein weiterer Guard, Override, Wrapper oder Sonderfall um bereits gepatchte Logik;
- mehrere Owner, Projektionen oder Dokumente behaupten widersprüchliche Zustände;
- ein lokaler Fix verschiebt das Problem nur in einen Nachbarfluss;
- Tests können nur durch zusätzliche Testausnahmen statt durch den Zielzustand grün werden.

Dann zurück zu Root Cause, Authority, Owner und Zielarchitektur. Es gibt **keine feste Patch-Anzahl**; die Qualität des Signals löst den Recheck aus.

## 7. Real Validation

Evidence wird risikoadaptiv von günstig nach real aufgebaut:

```text
aktueller Diff / statischer Contract
-> lokale oder CI-Tests
-> relevante Browser-, API-, Daten- oder State-Evidence
-> Staging-Deploy / Smoke / Readback, wenn Runtimewirkung betroffen ist
-> kontrollierter realer Staging-Fall nur wenn für die offene Unsicherheit nötig und autorisiert
```

Regeln:

- Runtimewirkung nicht allein aus Code-Reasoning als DONE erklären.
- UI-Wirkung auf relevanten Viewports prüfen.
- API-/State-Wirkung mit Contract plus geeignetem Readback belegen.
- Externe Mutation nur innerhalb des autorisierten Write-Vertrags; Live-Writes sind kein Testinstrument.
- Eine stärkere reale Evidence widerlegt schwächere Annahmen. Dann zuerst Lösung/Vertrag korrigieren, nicht die Evidence weginterpretieren.
- Zusätzliche synthetische Evidence nur für ein benanntes unbelegtes Risiko erzeugen.

## 8. DONE_VERIFIED und erlaubte Endzustände

Es gibt nur diese Endzustände:

- **`DONE_VERIFIED`** – Target State erreicht, Invarianten erhalten, relevante Regressionen grün, aktuelle Evidence ausreichend und keine unnötige Parallellogik erzeugt.
- **`NO_CHANGE_VERIFIED`** – der aktuelle Zustand erfüllt das Ziel bereits oder eine Änderung würde das System nachweislich verschlechtern; nichts ändern.
- **`STOP_EVIDENCE_CEILING`** – notwendiger Source-, Daten-, Test-, Runtime- oder Toolzugriff fehlt. Keine spekulative Ersatzimplementierung bauen; exakt benennen, was nicht belegbar ist.
- **`STOP_DECISION_REQUIRED`** – eine materielle Produkt-/Policy-Entscheidung ist aus Auftrag und Authorities nicht ableitbar. Nur diese Entscheidung an den Nutzer geben.

`DONE`, `FIXED` oder `PASS` ohne ausreichende Verification sind keine erlaubten Abschlussbehauptungen.

Für `DONE_VERIFIED` zusätzlich:

- aktueller Diff und Head-SHA geprüft;
- vorgeschriebene PR-/Workpack-Gates grün;
- Merge nach `staging` und relevanter Staging-Deploy/Smoke erfolgt, sofern der Scope Runtime betrifft oder der normale Prozess ihn ausführt;
- dauerhaftes Wissensdelta genau einmal beim owning Vertrag/Test dokumentiert;
- verbleibende Evidence-Grenzen ausdrücklich benannt.

## 9. Git, Workpacks und Parallelität

```text
Feature-Branch -> staging -> main
```

- kein direkter Commit nach `staging` oder `main`;
- vorhandene passende Arbeit fortsetzen statt zweiten Lösungszweig eröffnen;
- ein Workpack besitzt genau ein aktives Issue, einen deklarierten Branch und einen PR;
- mehrere **unabhängige** Workpacks/PRs dürfen existieren; derselbe oder fachlich abhängige zentrale Owner bleibt seriell;
- größere Code-/Build-/UI-Arbeit benötigt Checkout und lokale Tests; viele Remote-Einzelpatches ersetzen keinen Checkout;
- Workpack-Merge nur nach dem vorgesehenen finalen Validation-Lauf auf exakt aktuellem PR-Head.

## 10. Documentation Discipline und Lernen aus realen Ergebnissen

Dauerhafte Dokumentation ist Produkt-/Systemspeicher, kein Arbeitstagebuch:

- `ROADMAP.md`: Priorität/Zukunft, keine laufenden SHAs oder Statuschronik;
- Workpack-Issue: aktueller operativer Status;
- `TEST_STATUS.md`: nur neue dauerhafte Prooffähigkeit oder Evidence-Grenze;
- Produkt-/Architekturverträge: nur wenn ihre dauerhafte Wahrheit tatsächlich geändert wurde;
- technische Fehlentscheidung bevorzugt als Regressionstest festhalten; zusätzliche Doku nur, wenn Owner-, Safety- oder Architekturwissen dauerhaft geändert wurde.

Wenn reale Tests, Daten oder Nutzerprüfung eine Annahme widerlegen:

```text
Evidence akzeptieren
-> Root Cause / Target State neu prüfen
-> Lösung adaptieren
-> erneut real validieren
-> wiederverwendbare Lehre genau einmal im owning Test/Vertrag sichern
```

Keine Patchchronik als Ersatz für Lernen erzeugen.

## 11. Nutzerinteraktion und Lieferung

Der Agent arbeitet innerhalb der belegbaren Grenzen möglichst selbstständig. Den Nutzer nur einbeziehen, wenn `STOP_DECISION_REQUIRED` eintritt, eine ausdrücklich notwendige Freigabe für eine gesperrte Wirkung fehlt oder eine konkrete Nutzerprüfung nach technischer Verifikation sinnvoll ist.

Abschluss kompakt mit Endzustand, Wirkung, Verification und bewusst offenen Grenzen liefern.