# Steuerzentrale – verbindlicher Arbeits- und Abnahmemodus

Stand: 2026-07-17  
Status: kanonisch und vor jeder Steuerzentralen-Arbeit zuerst zu lesen

## Warum dieser Vertrag notwendig ist

Bei der ausnahmebasierten Eventprüfung wurden Code, reale Staging-Daten und Abnahme gleichzeitig verändert. Dadurch war nach einem sichtbaren Fehler nicht mehr eindeutig, ob die Ursache in Quelle, Mapping, Sheet-Zeile, lokaler Fallkopie, API, Reviewvertrag, Deploy oder Browser lag.

Die vorhandenen Dokumente beschrieben zwar einzelne Schutzregeln, aber sie wurden nicht technisch erzwungen. Gleichzeitig bestand ein Widerspruch: Eine reale Staging-Abnahme wurde vor dem Merge nach `staging` verlangt, obwohl nur der Branch `staging` auf die Staging-Umgebung deployt wird. Dieser Widerspruch begünstigte Merge-then-test und reale Daten als Testlabor.

## Verbindliche Grundsätze

1. **Keine gleichzeitige Veränderung von Code, realen Daten und Abnahmezustand.**
2. **Reale Google-Sheets-Daten sind kein Entwicklungs-Testbestand.**
3. **Vor jeder Runtime-Änderung muss die vollständige Daten- und Zustandskette bekannt sein.**
4. **CI-Erfolg ersetzt keine isolierte Integration und keine reale Staging-Abnahme.**
5. **Ein HTTP-`ok`, ein verschwundener UI-Punkt oder ein grüner Smoke-Test beweist keinen korrekten Endzustand.**
6. **Keine Vermutung wird durch einen Daten- oder Codepatch „bestätigt“. Erst Beleg, dann Änderung.**
7. **Keine funktionale Fortsetzung, solange ein entdeckter Umgebungs-, Identitäts- oder Persistenzfehler nicht forensisch abgeschlossen ist.**

## Verbindliche Phasen

### Phase 0 – Problem- und Ursachengrenze

Vor einem Patch werden dokumentiert:

- beobachteter Ist-Zustand,
- erwarteter Zustand,
- betroffene führende Quelle,
- stabile Objekt- und Zeilenidentität,
- Umgebung und Zieltab,
- letzte bekannte schreibende Prozesse,
- belegte Ursache oder ausdrücklich offene Hypothesen.

Ohne diese Angaben ist kein Runtime-Patch zulässig.

### Phase 1 – Isolierte Reproduktion

Der Fehler wird mit Fixture-, Mock- oder temporären Testdaten reproduziert. Dabei gelten dieselben Verträge wie in Produktion:

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

Reale Staging- oder Live-Daten dürfen diese Phase nicht ersetzen.

### Phase 2 – Arbeitsbranch und CI

Ein Arbeitsbranch darf nach `staging` vorgeschlagen werden, wenn:

- Ursache und Scope dokumentiert sind,
- isolierte Integration vollständig grün ist,
- Environment- und Datenidentitätsvertrag geprüft sind,
- Rollback beschrieben ist,
- ein maschinenlesbares Change-Manifest vorliegt.

### Phase 3 – Merge nach `staging`

Der Merge nach `staging` ist ausschließlich der Deploy in die Entwicklungsumgebung. Er ist **keine fachliche Freigabe** und keine Releaseentscheidung.

Vor dem Merge nach `staging` ist keine reale Staging-Abnahme erforderlich; stattdessen ist die isolierte Integration zwingend. Dadurch wird der frühere Prozesswiderspruch beseitigt.

### Phase 4 – reale Staging-Abnahme

Nach dem Staging-Deploy erfolgen getrennt:

1. read-only Smoke und Datenkettenvergleich,
2. nur bei Bedarf genau eine vorab definierte Schreibprobe,
3. Vorher-Snapshot,
4. explizite Staging-Identität,
5. erwartete Felder und Postconditions,
6. Nachher-Snapshot,
7. Rücklese- und UI-Nachweis,
8. dokumentierter Rollback.

Keine spontane zusätzliche Aktion während der Abnahme.

### Phase 5 – Merge nach `main`

Ein Main-PR ist erst zulässig, wenn die reale Staging-Abnahme als Evidence-Artefakt vorliegt. Live-Schreibtests sind unzulässig. Nach dem Live-Deploy erfolgt ausschließlich der definierte Live-Smoke; operative Änderungen sind normale Betreiberaktionen und keine Testschritte.

## Umgebungs- und Datenvertrag

| Umgebung | führende Inbox | zulässiger Testmodus |
|---|---|---|
| lokal/CI | Fixtures oder temporärer Bestand | lesen und schreiben innerhalb des Testbestands |
| Staging | `Inbox_Staging` | read-only Smoke; kontrollierte Schreibprobe nur nach Manifest und Snapshot |
| Live | `Inbox` | keine Testschreibaktionen |

Ein Staging-Lauf darf niemals auf `Inbox` zeigen. Ein unbekannter Environment-Wert muss fail-closed abbrechen.

## Stop-the-line-Regeln

Sofortiger Arbeitsstopp bei:

- unerwartetem Zieltab oder Zielsystem,
- nicht eindeutig auflösbarer Zeilenidentität,
- Abweichung zwischen Quelle, lokalem Fall und UI,
- unerwarteter realer Datenmutation,
- fehlendem Vorher-Snapshot,
- Ergebnis, das nur durch manuelle Datenkorrektur „grün“ wird,
- CI-Vertrag, der alte Hartverdrahtungen statt Zielverhalten prüft.

Danach ausschließlich Forensik; kein Folgepatch aus Vermutung.

## Maschinenlesbares Gate

Jeder PR mit Steuerzentralen-Bezug benötigt:

```text
docs/evidence/control-center/changes/<branch-mit-__-statt-/>.json
```

Der Workflow `Control Center Change Governance` prüft unter anderem:

- Scope stimmt mit den tatsächlich geänderten Dateien überein,
- Runtime-Änderungen besitzen isolierte Integrations-Evidence,
- Environment- und Datenidentität sind bei Quell-/Writeback-Änderungen verifiziert,
- reale Staging-Schreibprobe wird nicht vor dem Merge behauptet,
- Main-PR besitzt reale Staging-Abnahme,
- Live-Schreibtests bleiben verboten.

## Aktueller Freeze

Bis zur forensischen Stabilisierung der Eventprüfung sind keine weiteren funktionalen Patches zulässig. Der nächste fachliche Workpack beginnt mit der vollständigen CityArt-Daten- und Zustandskette sowie der Prüfung möglicher unbeabsichtigter Live-Mutationen.
