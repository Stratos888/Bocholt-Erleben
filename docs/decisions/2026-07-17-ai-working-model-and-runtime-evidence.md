# Entscheidung: KI-Arbeitsmodell und Runtime-Evidence

Datum: 2026-07-17  
Status: angenommenes Zielmodell; technische Folgeworkpacks noch nicht begonnen

## Kontext

Die bisherigen Git-/PR-/Deploy-Guardrails haben reale Risiken reduziert, konnten aber den tatsächlich ausgeführten Backend- und Writebackpfad nicht beweisen. Beim CityArt-Fall trat trotz grüner Tests und Deploys zunächst eine Teilmutation und später erneut ein unerklärter Runtimefehler auf.

Belegt:

- Ein Staging-Klick erzeugte unerwartet eine unvollständige CityArt-Zeile im gemeinsamen Tab `Events`, während `Inbox_Staging` auf `review` blieb.
- Die unbeabsichtigte Zeile wurde eindeutig zurückgerollt.
- Nach Einführung von `Events_Staging` blieb ein weiterer Klick ohne Sheet-Mutation, lieferte aber erneut die alte Fehlermeldung.
- Ein grüner PR und Deploy konnten nicht zeigen, welcher Writer auf dem realen Server lief.
- Der Nutzer wurde dadurch als technisches Integrationstest-System eingesetzt.

## Entscheidung

### Arbeitsmodell

```text
Ein primärer Ausführungs-Chat
+ ein aktiver Workpack
+ ein Branch / Draft-PR
+ optional eine unabhängige read-only Zweitprüfung
```

Ein separater Integrations-Chat und parallele schreibende Workpack-Chats sind nicht mehr der Standard. Die primäre KI darf nach einem eindeutigen Arbeitsmandat Analyse, Implementierung, PR, CI, Staging-Integration und technische Prüfung innerhalb des vereinbarten Scopes selbst durchführen.

### Risikobasierter Prozess

- `R1`: lokal/reversibel;
- `R2`: deployte Runtime ohne externen Write;
- `R3`: externe Mutation oder gemeinsamer Zustand.

Alle Workpacks nutzen Gates A–D, aber nur R2/R3 benötigen Runtime-Preflight und nur R3 benötigt den isolierten synthetischen Schreibbeweis.

### Evidence

- E1: Code/Diff;
- E2: automatisierter Test/CI/Replay;
- E3: deployte read-only Runtime-Evidence;
- E4: isolierter automatisierter Staging-Write;
- E5: echter fachlicher Staging-Fall;
- E6: read-only Live-Smoke.

Ein grüner PR beweist maximal E2. Ein echter Nutzerklick auf einen R3-Prozess wird erst nach E4 verlangt.

### Fehlerbudget

- erster unerwarteter realer Zustand: sofort stoppen;
- kein zweiter Write ohne neue E3-/E4-Evidence;
- nur eine technische Hypothese gleichzeitig;
- nach zwei widerlegten Hypothesen Architektur- oder Revert-Entscheidung statt drittem Patch;
- synthetischer Test vor echtem Fachfall.

## Mentale Validierung am CityArt-Fall

### Was weiterhin schnell geblieben wäre

Die Headermigration und Präsentationskorrekturen hätten als klar begrenzte, reversible Änderungen normal über Code-/Contract-Tests, exaktes Rücklesen und visuelle Staging-Abnahme laufen können. Dafür ist kein vollständiger Geschäftsprozess-E2E nötig.

### Wo der Prozess gestoppt hätte

Vor `Event übernehmen` lagen nur E1/E2 und ein grüner Deploy vor. Der R3-Prozess hätte deshalb den echten Klick blockiert und zuerst einen read-only Runtime-Preflight verlangt.

Der Preflight hätte vor einer Mutation ausgewiesen:

- Build und Endpoint;
- Host und aufgelöste Umgebung;
- Quelltab `Inbox_Staging`;
- geplantes Ziel;
- ausgewählten Writer;
- erwartete Event-ID und Statusänderung.

Die reale Abweichung wäre damit vor dem ersten Fachdatensatz sichtbar geworden.

### Danach

Ein synthetischer Test hätte Schreiben, Rücklesen, Retry, Cleanup und Live-Unverändertheit automatisiert. Erst nach E4 wäre genau ein CityArt-Klick zulässig gewesen.

### Bewertung

Das Modell verhindert die beobachtete Try-and-Error-Schleife, ohne R1-Arbeiten zu verlangsamen. Sicherheit wächst nur mit der realen Nebenwirkung.

## Sequenzielle Folgeworkpacks

### WP-1 – Arbeitsmodell und Projektsicht

- operativen Router kürzen;
- Systemkarte anlegen;
- eine aktive Workpack-Datei etablieren;
- PR-Vertrag und Guardrail-Checks anpassen;
- alte Mehrchat- und Integrationsregeln entfernen.

Keine Runtime- oder Datenänderung.

### WP-2 – Runtime-Truth und Dry-Run

- Build-, Host-, Endpoint- und Environment-Evidence;
- Quell-/Zielressourcen und Writer ausweisen;
- read-only Operationsplan;
- automatischer Staging-Smoke.

Keine Writeränderung und keine externe Mutation.

### Entscheidungsgate

Nach E3 wird die reale Root Cause bewertet. Erst dann wird der Umfang von WP-3 final bestimmt.

### WP-3 – Minimaler robuster Writeback

Zielprinzip:

```text
ein Resolver
+ ein Operationsplaner
+ ein Writer-Kern
+ gebundene Ressourcen
+ idempotente Wiederaufnahme
+ kein Erfolg bei Teilmutation
```

Keine unnötigen Wrapper- oder Parallelpfade.

### WP-4 – Isolierter E4-Test und CityArt-Abschluss

- synthetischer Testkandidat;
- Write, Rücklesen, Feedprüfung, Retry und Cleanup;
- Live-Unverändertheit;
- danach genau ein echter CityArt-Versuch und E5-Abnahme.

## Konsequenzen

Positiv:

- weniger Chat- und Branchkoordination für den Nutzer;
- KI kann innerhalb eines Mandats eigenständig arbeiten;
- klare Trennung zwischen Code-, Runtime- und Write-Evidence;
- kleine Änderungen bleiben schnell;
- kritische Nebenwirkungen werden vor echten Fachfällen getestet;
- Architekturvereinfachung erhält Vorrang vor weiteren Schutzschichten.

Kosten:

- R2/R3 benötigen wiederverwendbare Runtime-Observability;
- R3 benötigt einmalig synthetische Testinfrastruktur;
- manche kritischen Workpacks brauchen einen optionalen read-only Review.

Diese Kosten sind bewusst akzeptiert, weil sie wiederkehrende manuelle Tests und Hypothesenpatches ersetzen.

## Nicht entschieden

Die konkrete Zielarchitektur des Writebacks wird nicht vorweggenommen. Sie wird erst nach WP-2 und E3 geschnitten. PR #86 bleibt bis dahin lediglich technische Evidence und darf nicht gemergt werden.