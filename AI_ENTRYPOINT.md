# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`

Diese Datei ist der verbindliche Kurzrouter für KI-Arbeit. Dokumentrollen und Lesepfade stehen in `docs/README.md`. Der einzige operative Status steht in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Startprotokoll

Vor jeder Repo-Aufgabe:

1. aktuellen `staging`-SHA und offene PRs direkt in GitHub prüfen;
2. `CURRENT_WORKPACK.md` lesen;
3. `SYSTEM_MAP.md` für betroffene Systeme und Datenflüsse lesen;
4. fachliche Owner-Dateien im aktuellen Ref lesen;
5. `ENGINEERING.md` und `external-resource-matrix.md` für technische und externe Grenzen prüfen;
6. IST, ZIEL, HYPOTHESE und HISTORIE sichtbar trennen.

Memory, alte Chats, ZIPs, PR-Texte und historische Dokumente sind Kontext, aber keine aktuelle Source of Truth.

## 2. Standard-Arbeitsmodell

```text
Ein primärer Ausführungs-Chat
+ ein aktiver Workpack
+ ein Feature-Branch
+ ein Draft-PR nach staging
+ optional eine unabhängige read-only Zweitprüfung
```

Der primäre Chat darf Analyse, Implementierung, Tests, PR, Integration nach `staging`, Deployprüfung und Abschluss zusammenhängend durchführen. Parallele schreibende Chats sind nur nach dokumentiertem Nachweis vollständiger Owner-, Runtime- und Ressourcenunabhängigkeit zulässig.

## 3. Einmaliges Arbeitsmandat

Eine eindeutige Anweisung wie `umsetzen`, `patchen`, `dokumentieren` oder `mach das` erteilt für den vereinbarten Scope ein einmaliges Arbeitsmandat.

Innerhalb dieses Scopes darf die KI ohne wiederholte Freigabe:

- analysieren und den Workpack präzisieren;
- Branch, Commits und Draft-PR erstellen;
- statische und automatisierte Tests ausführen;
- nach erfüllten Gates nach `staging` integrieren;
- Staging-Deploy und read-only Postconditions prüfen;
- ausdrücklich geplante, isolierte synthetische Staging-Daten anlegen, zurücklesen und bereinigen.

Eine neue Freigabe ist erforderlich bei Scope-Erweiterung, irreversibler Aktion, Secrets/Berechtigungen, realer Zahlung, echter Nachricht, externer Veröffentlichung oder einer nicht aus bestehenden Verträgen ableitbaren Produktentscheidung.

## 4. Ausführungspfade

### Standardpfad

Normale Entwicklung und vollständige Releases erfolgen ausschließlich über:

```text
Feature-Branch -> PR nach staging -> Staging-Evidence -> staging -> main
```

### Kontrollierte einzelne Live-Eventpflege

Eine direkte Mutation in `Events` ist nur zulässig, wenn der Nutzer das konkrete Event ausdrücklich beauftragt hat und alle Bedingungen erfüllt sind:

- stabile Event-ID und eindeutige Zielzeile;
- vollständiger Vorherzustand;
- nur deklarierte Felder;
- fachliche und Schema-Guards;
- sofortiges Rücklesen;
- unveränderte Nicht-Zielfelder;
- klarer Rollback;
- read-only Prüfung von Feed und Detailseite;
- kein zweiter Versuch bei unerwartetem Verhalten.

Dies ist eine deterministische Admin-Mutation, kein Live-Test und kein allgemeiner Entwicklungsweg.

### Kleiner direkter Main-Hotfix

Ein direkter Main-Hotfix ist nur nach ausdrücklicher Nutzerbeauftragung und nur bei vollständig erfülltem Vertrag zulässig:

- konkreter belegter Produktionsfehler;
- aktueller `main`-SHA als Baseline;
- isoliert, reversibel, höchstens drei zusammengehörige Owner-Dateien und im Regelfall höchstens 100 geänderte Zeilen;
- kein Feature, Refactoring, Workflow, Security-, Berechtigungs-, Deploy-, Environment- oder Governance-Umbau;
- passende Checks vor dem Write;
- dedizierter Ruleset-Bypass für einen gezielt freigegebenen KI-Hotfix-Akteur;
- Deploy- und E6-Live-Smoke danach;
- Revert vor einem zweiten Reparaturpatch.

Solange der dedizierte Ruleset-Bypass nicht eingerichtet ist, ist dieser Pfad technisch gesperrt. Ein breiter `staging -> main`-Merge darf nicht als Ersatzhotfix verwendet werden.

## 5. Risikoklassen

| Klasse | Typische Änderung | Mindestpfad |
|---|---|---|
| `R1 lokal und reversibel` | Doku, Copy, kleiner statischer Owner-Fix | Gate A, B, passende Abnahme |
| `R2 deployte Runtime ohne externen Write` | API, Rendering, Feed, Cache, Build, Deploy | Gate A–D inklusive E3 |
| `R3 externe Mutation oder gemeinsamer Zustand` | Sheets, DB, Mail, Payment, Veröffentlichung | Gate A–D inklusive E4 vor echtem Fachfall |

Die KI klassifiziert selbst. Risiko, Scope, benötigte Evidence und Rollback stehen im Workpack und PR.

## 6. Vier Gates

### Gate A – Verstehen

Vor dem Patch müssen Zielzustand, Ausgangs-SHA, Fakten/Hypothesen, Owner, erlaubter und gesperrter Scope, externe Ressourcen, Evidence und Rollback feststehen.

Bei ungeklärter realer Mutation ist nur read-only Forensik oder Observability zulässig.

### Gate B – Bauen und statisch beweisen

- Branch/Diff gegen den maßgeblichen Ausgangsstand;
- nur deklarierter Scope;
- Syntax-, Unit-, Contract-, Replay-, Build- und relevante CI-Tests;
- keine externe Mutation durch normale Implementierungsschritte.

Ein grüner PR beweist höchstens E2.

### Gate C – Reale Runtime beweisen

Für R2/R3 mindestens:

- deployter Build-SHA;
- Host und Endpoint;
- konfigurierte und aufgelöste Umgebung;
- Quell- und Zielressourcen;
- tatsächlicher Operationsplan;
- read-only Preflight.

Für wiederverwendbare R3-Pfade zusätzlich: isolierter synthetischer Staging-Write, Rücklesen, Teilfehler-/Retry-Nachweis und Cleanup vor einem echten Fachfall.

### Gate D – Abnehmen und abschließen

- technische Postconditions;
- genau eine fachliche/visuelle Abnahme, falls erforderlich;
- `CURRENT_WORKPACK.md` auf den Folgezustand setzen;
- Evidence-Index nur bei neuem Beweis aktualisieren;
- PR-, Branch-, Lock- und Rollbackstatus klären;
- genau den nächsten zulässigen Schritt nennen.

## 7. Evidence-Stufen

| Stufe | Beweis |
|---|---|
| `E0` | Hypothese |
| `E1` | aktueller Code/Diff |
| `E2` | automatisierter Test, CI oder Replay |
| `E3` | deployte read-only Runtime-Evidence |
| `E4` | isolierter realer Staging-Write oder deterministische kontrollierte Admin-Mutation mit Rücklesen |
| `E5` | echter fachlicher Staging-Fall |
| `E6` | read-only Live-Smoke |

Keine Erfolgsbehauptung bei Teilmutation oder fehlender erforderlicher Evidence.

## 8. Fehlerbudget und Stop-the-line

Beim ersten unerwarteten realen Verhalten:

```text
Schreiben stoppen
-> Zustand und Evidence sichern
-> nicht wiederholen
-> Root Cause oder fehlende Observability bestimmen
```

Regeln:

1. eine technische Hypothese gleichzeitig;
2. kein zweiter Write ohne neue Evidence;
3. nach zwei widerlegten Hypothesen kein dritter Patch, sondern Architektur- oder Revert-Entscheidung;
4. keine manuelle Datenkorrektur zum künstlichen Grünmachen;
5. Revert vor Nachpatchen bei einem direkten Main-Hotfix.

## 9. Nutzerinteraktion

Die KI ermittelt selbst, was über Repo, CI, Logs und read-only Dienste zugänglich ist. Eine unvermeidbare Nutzeraktion enthält genau einen Schritt, technische Begründung, erwartetes Ergebnis und klare Nicht-Aktionen.

Screenshots dienen visueller/fachlicher Abnahme oder fehlenden Schnittstellen, nicht als Ersatz für automatisierbare Runtime-Evidence.
