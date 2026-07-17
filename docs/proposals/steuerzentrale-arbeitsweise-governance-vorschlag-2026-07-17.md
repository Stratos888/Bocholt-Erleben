# Vorschlag – Arbeits- und Abnahmemodus für die Steuerzentrale

Stand: 2026-07-17  
Status: **nicht validierter Vorschlag**  
Keine Freigabe, keine Verbindlichkeit, keine Merge-Empfehlung ohne erneute Analyse

## Zweck

Dieser Vorschlag entstand aus der Analyse der fehlgeschlagenen CityArt-Abnahme. Er soll verhindern, dass Code, reale Daten und Abnahme erneut gleichzeitig verändert werden. Er ist ausdrücklich nur ein Ausgangspunkt für einen separaten Analyse- und Validierungs-Chat.

Der Folgechat darf einzelne Bestandteile bestätigen, verändern, vereinfachen oder vollständig verwerfen.

## Beobachtete Ausgangsprobleme

- Der dokumentierte Ablauf verlangte reale Staging-Abnahme vor dem Merge nach `staging`, obwohl erst der Branch `staging` auf die Staging-Website deployt wird.
- Synthetische CI-Verträge wurden zu stark als vollständiger E2E-Nachweis interpretiert.
- Vor realen Schreibproben fehlten gemeinsame Evidence für Environment, Zieltab, stabile Zeilenidentität, lokalen Fall, Vorherzustand und Rollback.
- Diagnose, Datenkorrektur und Abnahme wurden vermischt.
- Nach einem möglichen Umgebungs- oder Datenfehler fehlte ein konsequenter Arbeitsstopp.
- PRs konnten ohne technisch verpflichtende Evidence-Grenze gemergt werden.

## Vorgeschlagene Grundsätze

1. Code, reale Daten und Abnahmezustand nicht gleichzeitig verändern.
2. Reale Google-Sheets-Daten nicht als Entwicklungs-Testbestand verwenden.
3. Vor Runtime-Änderungen die relevante Daten- und Zustandskette dokumentieren.
4. CI, isolierte Integration und reale Staging-Abnahme als unterschiedliche Evidence-Ebenen behandeln.
5. Kein Erfolg allein aufgrund von HTTP-`ok`, grünem Smoke-Test oder verschwundenem UI-Punkt behaupten.
6. Bei Environment-, Identitäts- oder unerwarteten Datenmutationsfehlern funktional stoppen und nur forensisch weiterarbeiten.

Diese Grundsätze sind plausibel, aber noch auf Angemessenheit, Umsetzbarkeit und Aufwand zu prüfen.

## Vorgeschlagene Phasen

### Phase A – Problem- und Ursachengrenze

Vor einem Patch werden mindestens festgehalten:

- beobachteter Ist-Zustand;
- erwarteter Zustand;
- betroffene führende Quelle;
- stabile Objekt- und Zeilenidentität;
- Umgebung und Zieltab;
- letzte bekannte schreibende Prozesse;
- belegte Ursache oder ausdrücklich offene Hypothesen.

Offene Validierungsfrage: Welche Angaben müssen technisch verpflichtend sein und welche reichen als normale Workpack-Dokumentation?

### Phase B – isolierte Reproduktion

Der Fehler wird mit Fixtures, Mocks oder einem temporären Testbestand reproduziert. Zielkette:

```text
Quelle
→ Identität
→ lokaler Fall
→ API
→ Writeback
→ Rücklesen
→ Neubewertung
→ sichtbarer Endzustand
```

Offene Validierungsfragen:

- Welche Teile dieser Kette lassen sich im aktuellen Repository realistisch automatisieren?
- Ist ein temporäres Sheet notwendig oder genügt ein dateibasierter Adapter?
- Wie wird verhindert, dass Tests zu stark von der echten Runtime abweichen?

### Phase C – Arbeitsbranch und CI

Ein Arbeitsbranch soll erst nach `staging` gelangen, wenn Ursache, Scope, isolierte Integration, Environment-Grenze, Datenidentität und Rollback ausreichend belegt sind.

Der Prototyp in PR #80 verwendet dafür ein maschinenlesbares Change-Manifest.

Offene Validierungsfragen:

- Ist ein Manifest pro PR sinnvoll oder zu aufwendig?
- Welche Felder sind wirklich nötig?
- Kann das Gate zuverlässig mit den tatsächlich geänderten Dateien abgeglichen werden?
- Muss die Lösung einfacher sein, etwa durch wenige klar definierte Pflichtchecks?

### Phase D – Merge nach `staging`

Vorschlag:

- Merge nach `staging` gilt als Deploy in die Entwicklungsumgebung.
- Er ist keine fachliche Freigabe und keine Main-Entscheidung.
- Vor dem Merge soll isolierte Integration verpflichtend sein.
- Reale Staging-Abnahme erfolgt erst nach dem Deploy.

Dieser Punkt muss besonders kritisch validiert werden, weil er die frühere widersprüchliche Reihenfolge korrigieren soll.

### Phase E – reale Staging-Abnahme

Vorgeschlagene Trennung:

1. read-only Smoke und Datenkettenvergleich;
2. nur bei Bedarf genau eine vorab definierte Schreibprobe;
3. Vorher-Snapshot;
4. explizite Staging-Identität und Zieltab;
5. erwartete Felder und Postconditions;
6. Nachher-Snapshot;
7. Rücklese- und UI-Nachweis;
8. dokumentierter Rollback.

Offene Validierungsfragen:

- Ist genau eine Schreibprobe sinnvoll oder müssen Tests fallabhängig definiert werden?
- Wo werden Vorher-/Nachher-Snapshots abgelegt?
- Wie wird eine Testzeile eindeutig von operativen Staging-Daten getrennt?
- Sollte Staging einen dedizierten, reproduzierbaren Abnahmebestand erhalten?

### Phase F – Merge nach `main`

Vorschlag:

- Main erst nach dokumentierter realer Staging-Abnahme.
- Keine Live-Schreibtests.
- Nach Live-Deploy ausschließlich definierter read-only Smoke.

Zu validieren ist, welche Evidence für Main tatsächlich erforderlich ist und wie sie ohne unnötigen Prozessaufwand erzeugt wird.

## Vorgeschlagener Umgebungsvertrag

| Umgebung | führende Inbox | möglicher Testmodus |
|---|---|---|
| lokal/CI | Fixtures oder temporärer Bestand | isoliert lesen und schreiben |
| Staging | `Inbox_Staging` | read-only Smoke; kontrollierte Schreibprobe nach Evidence |
| Live | `Inbox` | keine Testschreibaktionen |

Bereits implementierter und zu erhaltender Sicherheitsgedanke: Staging darf nicht auf `Inbox` zeigen; unbekannte Environment-Werte sollen fail-closed blockieren. Auch dieser Vertrag ist gegen die gesamte Runtime zu validieren, nicht nur anhand statischer Tests.

## Vorgeschlagene Stop-the-line-Kriterien

- unerwartetes Zielsystem oder Zieltab;
- nicht eindeutig auflösbare Zeilenidentität;
- Abweichung zwischen Quelle, lokalem Fall, API und UI;
- unerwartete reale Datenmutation;
- fehlender Vorherzustand bei einer Schreibprobe;
- ein Ergebnis wird nur durch manuelle Datenkorrektur grün;
- CI testet alte Hartverdrahtungen statt Zielverhalten.

Offene Validierungsfrage: Welche dieser Kriterien sollten technisch blockieren und welche als Arbeitsregel gelten?

## Governance-Prototyp in PR #80

PR #80 enthält als Prototyp:

- Workflow `Control Center Change Governance`;
- Change-Manifest;
- Validator für Scope und deklarierte Evidence;
- PR-Template;
- CODEOWNERS;
- Dokumentationsänderungen.

Der Prototyp ist **nicht freigegeben**. Insbesondere ist noch zu prüfen:

- ob der Workflow den richtigen Scope erkennt;
- ob er echte Qualität prüft oder nur vollständig ausgefüllte Deklarationen;
- ob CODEOWNERS bei der tatsächlichen Teamstruktur sinnvoll ist;
- ob Branch-Protection für `staging` und `main` erforderlich und angemessen ist;
- ob der Prototyp unnötige Komplexität erzeugt;
- ob PR #80 geteilt, stark reduziert oder geschlossen werden sollte.

## Parallelität mehrerer Chats und Branches

Der neue Analysechat soll zusätzlich bewerten, wie mehrere Chats sicher gleichzeitig mit demselben Repository arbeiten können. Zu prüfen sind mindestens:

- pro Chat genau ein eigener Arbeitsbranch;
- keine parallelen Änderungen an denselben Dateien ohne Koordination;
- keine direkten Änderungen an `staging` oder `main`;
- vor jedem Patch Vergleich mit aktuellem Zielbranch;
- klare Besitz- oder Lock-Regel für aktive Workpacks;
- Umgang mit gleichzeitigen Datenänderungen außerhalb des Repositories;
- Rebase- beziehungsweise Konfliktprüfung vor PR-Abschluss.

## Entscheidung, die der Folgechat liefern soll

Der Folgechat soll nach vollständiger Analyse eine begründete Empfehlung liefern:

1. Welche Root Causes sind korrekt und vollständig?
2. Welche Schutzmaßnahmen sind notwendig?
3. Welche Maßnahmen sind überzogen oder wirkungslos?
4. Wie sieht der einfachste nachhaltig sichere Zielprozess aus?
5. Was soll mit PR #80 passieren: übernehmen, ändern, teilen oder schließen?
6. Welche einmaligen GitHub-Einstellungen sind wirklich erforderlich?
7. Wie geht es danach mit der forensischen CityArt-Untersuchung weiter?

Bis zu dieser Entscheidung bleibt dieser Text nur ein Vorschlag.
