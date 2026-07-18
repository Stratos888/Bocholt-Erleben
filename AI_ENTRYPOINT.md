# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`.

Diese Datei ist die kurze und verbindliche Arbeitsanweisung für KI-Arbeit am Repository. Technische Detailregeln stehen in `ENGINEERING.md`; der aktuelle Arbeitsstand steht ausschließlich in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Quellen der Wahrheit

Vor jeder Repo-Aufgabe:

1. aktuellen GitHub-Stand von `staging` und offene PRs prüfen;
2. `docs/workpacks/active/CURRENT_WORKPACK.md` lesen;
3. `docs/architecture/SYSTEM_MAP.md` für betroffene Systeme und Datenflüsse lesen;
4. relevante Owner-Dateien im aktuellen Ref lesen;
5. fachliche Source of Truth gemäß `ENGINEERING.md` und `docs/external-resource-matrix.md` prüfen.

Memory, alte Chats, ZIPs und historische Dokumente sind Kontext, aber keine aktuelle Quelle der Wahrheit. Der Normalfall bleibt die Arbeit über `staging`; vollständige Releases erfolgen als `staging -> main`. Eine direkte KI-Schreibaktion auf Live oder `main` ist ausschließlich im eng begrenzten Live-Sofortpfad aus Abschnitt 3a zulässig.

## 2. Standard-Arbeitsmodell

Der Normalfall ist:

```text
Ein primärer Ausführungs-Chat
+ ein aktiver Workpack
+ ein Branch
+ ein Draft-PR
+ optional eine unabhängige read-only Zweitprüfung
```

Der primäre Chat darf Analyse, Implementierung, PR, CI-Auswertung, Integration nach `staging`, Deployprüfung und Abschluss in einer Unterhaltung durchführen. Ein separater Integrations-Chat ist nicht erforderlich.

Parallele schreibende Chats sind keine Standardbeschleunigung. Sie sind nur zulässig, wenn die KI vorab nachweist, dass keine gemeinsamen Owner-Dateien, Runtimepfade, Deploys, externen Ressourcen oder fachlichen Abhängigkeiten bestehen. Der Nutzer muss diese Koordination nicht entwerfen.

## 3. Einmaliges Arbeitsmandat

Eine eindeutige Nutzeranweisung wie `umsetzen`, `mach das`, `patchen` oder `dokumentieren` erteilt für den vereinbarten Workpack ein einmaliges Arbeitsmandat.

Innerhalb des dokumentierten Scopes darf die KI danach ohne wiederholte Zwischenfreigaben:

- lesen und analysieren;
- einen Branch und Draft-PR anlegen;
- Dateien ändern und committen;
- Tests und CI auswerten;
- den PR nach erfüllten Gates nach `staging` integrieren;
- den Staging-Deploy und read-only Postconditions prüfen;
- synthetische isolierte Staging-Testdaten anlegen und bereinigen, wenn dies im R3-Testplan ausdrücklich vorgesehen ist.

Eine neue Nutzerfreigabe ist erforderlich bei:

- Scope-Erweiterung;
- Schreibzugriff auf Live, sofern er nicht bereits ausdrücklich als Live-Sofortpfad beauftragt wurde;
- irreversibler oder destruktiver Aktion ohne sicheren Rollback;
- Secrets, Credentials oder Berechtigungsänderungen;
- realen Zahlungen, echten E-Mails/Nachrichten oder externen Veröffentlichungen;
- fachlicher Produktentscheidung, die nicht aus dem bestehenden Vertrag ableitbar ist.

### 3a. Kontrollierter Live-Sofortpfad für Events und kleine Hotfixes

Der Nutzer hat festgelegt, dass die KI klar begrenzte Eventpflege und kleine, reversible Produktionskorrekturen ohne vollständigen `staging -> main`-Release durchführen können soll. Dieser Pfad ist eine Ausnahme und kein zweiter allgemeiner Entwicklungsweg.

#### A. Live-Eventdaten

Direkte Pflege der redaktionellen Live-Quelle `Events` ist zulässig, wenn der Nutzer das konkrete Event oder die konkrete Korrektur ausdrücklich beauftragt hat und folgende Bedingungen erfüllt sind:

- stabile Event-ID und eindeutige Zielzeile;
- Vorherzustand lesen und Zielressource `Events` bestätigen;
- nur die fachlich erforderlichen Felder ändern;
- Qualitäts- und Schema-Guards einhalten;
- Änderung unmittelbar zurücklesen;
- Live-Feed beziehungsweise Detailseite nach dem Deploy read-only prüfen;
- kein stilles Überschreiben konkurrierender redaktioneller Änderungen.

Eventdaten werden nicht ersatzweise in generierte Repo-Artefakte geschrieben. Ein Event-Write ist `R3`, aber für eine einzelne deterministische Zeile als kontrollierte Admin-Mutation zulässig, wenn Identität, Vorherzustand, Rücklesen und Rollback vollständig feststehen.

#### B. Kleiner direkter Repo-Hotfix auf `main`

Ein direkter KI-Commit auf `main` ist nur zulässig, wenn **alle** folgenden Bedingungen erfüllt sind:

1. Der Nutzer beauftragt ausdrücklich einen sofortigen Live-Hotfix oder bestätigt, dass die Änderung direkt auf `main` erfolgen soll.
2. Der aktuelle `main`-SHA und der tatsächliche Live-Fehler sind geprüft.
3. Der Patch ist isoliert, reversibel und umfasst höchstens drei fachlich zusammengehörige Owner-Dateien sowie im Regelfall höchstens 100 geänderte Zeilen.
4. Der Patch behebt einen konkreten Produktionsfehler oder eine klar begrenzte Content-/Visual-Korrektur; er enthält kein Feature, kein Refactoring und keine verdeckte Staging-Übernahme.
5. Ausgeschlossen sind `.github/workflows/**`, Secrets, Berechtigungen, Deploy-/Environment-Verträge, zentrale Governance-Dateien, Datenbankmigrationen, externe Mehrfach-Writes und destruktive Änderungen.
6. Syntax-, Schema- und passende Contract-Checks sind vor dem Write grün oder der Patch ist rein deklarativ und deterministisch prüfbar.
7. Der Commit erhält eine eindeutige Hotfix-Message; Vorher-SHA, geänderte Dateien und Rollback-Commit sind nachvollziehbar.
8. Nach dem Push müssen Main-Deploy und read-only Live-Postconditions geprüft werden. Bei Abweichung gilt sofort Stop-the-line und Revert statt Nachpatchen.

Technische Voraussetzung ist ein **dedizierter, kontrollierter GitHub-Akteur** für KI-Live-Hotfixes, der im `main`-Ruleset gezielt als Bypass-Akteur freigegeben ist. Eine pauschale Administrator-Ausnahme für alle Änderungen ist nicht der Premium-Zielzustand. Solange diese technische Freigabe fehlt, darf die KI keinen breiten Merge als Ersatz ausführen. Sie dokumentiert den exakt vorbereiteten Patch und den blockierenden Ruleset-Status.

#### C. Abgrenzung

Der Live-Sofortpfad ist nicht zulässig für:

- normale Produktentwicklung;
- Änderungen mit mehr als einem fachlichen Ziel;
- unklare Root Cause;
- konkurrierende oder parallele Live-Writes;
- Workflow-, Security-, Berechtigungs- oder Infrastrukturänderungen;
- Patches, deren Erfolg erst durch weitere Live-Schreibversuche festgestellt werden könnte.

Die dauerhafte Entscheidung und der Einrichtungsstatus stehen in `docs/decisions/2026-07-18-controlled-ai-live-hotfix-and-event-writes.md`.

## 4. Risikoklassen

Die KI klassifiziert jeden Workpack selbst und trägt die Klasse in `CURRENT_WORKPACK.md` und den PR ein.

| Klasse | Typische Änderung | Erforderlicher Pfad |
|---|---|---|
| `R1` lokal und reversibel | Doku, Copy, kleiner Owner-CSS-/HTML-Fix, isolierter statischer Guard | Gate A, B und passende Abnahme |
| `R2` deployte Runtime ohne externen Write | API-Ausgabe, Rendering, Cache, Feed, Build oder Deployverhalten | Gate A, B, C-read-only und D |
| `R3` externe Mutation oder gemeinsamer Zustand | Google Sheets, DB, Veröffentlichung, Mail, Payment, kritischer Writeback | Gate A, B, C inklusive isoliertem Schreibbeweis und D |

Eine exakt begrenzte Admin-Migration kann verkürzt behandelt werden, wenn Ziel, Vorherzustand, Änderung, Rücklesen und Rollback vollständig deterministisch sind und keine verdeckten Nebenwirkungen bestehen.

## 5. Vier Gates

### Gate A – Verstehen

Vor dem ersten Patch müssen sichtbar sein:

- aktueller `staging`-SHA und offene PRs;
- bei einem Live-Sofortpfad zusätzlich aktueller `main`-SHA;
- Zielzustand und Risikoklasse;
- belegte Fakten getrennt von Hypothesen;
- Owner-Dateien und externe Ressourcen;
- erlaubter und gesperrter Scope;
- benötigte Evidence und Rollback.

Bei einer ungeklärten realen Mutation darf nur Observability oder read-only Forensik geplant werden.

### Gate B – Bauen und statisch beweisen

- eigener Branch und Draft-PR im Standardpfad;
- im ausdrücklich freigegebenen Live-Hotfixpfad stattdessen isolierter Diff gegen den aktuellen `main`-SHA;
- nur deklarierter Scope;
- Syntax-, Unit-, Contract-, Replay-, Build- und relevante CI-Tests;
- Diff gegen den maßgeblichen Ausgangsstand;
- keine externe Mutation durch normale Implementierungsschritte.

Ein grüner PR beweist höchstens E2 und noch keine reale Runtimeausführung.

### Gate C – Reale Runtime beweisen

Für `R2` und `R3` erforderlich:

- deployter Build-SHA;
- tatsächlicher Host und Endpoint;
- konfigurierte und aufgelöste Umgebung;
- verwendete Quell- und Zielressourcen;
- read-only Preflight oder Dry-Run des realen Operationsplans.

Für `R3` zusätzlich vor einem echten Fachfall:

- synthetischer eindeutig benannter Staging-Test, sofern der Pfad nicht als vollständig deterministische einzelne Admin-Mutation dokumentiert ist;
- Schreiben und Rücklesen aller Postconditions;
- Retry-/Teilfehlernachweis, soweit der Prozess wiederholbar ist;
- vollständiges Cleanup;
- Nachweis, dass nicht adressierte Live-Ressourcen unverändert blieben.

### Gate D – Abnehmen und abschließen

- genau eine fachliche oder visuelle Staging-Abnahme, falls erforderlich;
- beim Live-Sofortpfad genau eine read-only Live-Abnahme;
- technische Postconditions unmittelbar prüfen;
- Dokumentation und `CURRENT_WORKPACK.md` aktualisieren, soweit ein aktiver Workpack betroffen ist;
- PR-, Branch-, Hotfix- und Incidentstatus klären;
- nächsten erlaubten Schritt eindeutig nennen.

Der Nutzer prüft primär Produktqualität. Er ist nicht das technische Integrationstest-System.

## 6. Evidence-Stufen

| Stufe | Nachweis | Zulässige Aussage |
|---|---|---|
| `E0` | Vermutung/Modell | Hypothese |
| `E1` | aktueller Code/Diff | Code enthält die Logik |
| `E2` | automatisierter Test/CI/Replay | Logik funktioniert in der Testumgebung |
| `E3` | deployte read-only Runtime-Evidence | Server führt erwarteten Build und Pfad aus |
| `E4` | isolierter automatisierter Staging-Write oder deterministische kontrollierte Admin-Mutation mit Rücklesen | technischer Schreibprozess funktioniert real |
| `E5` | echter fachlicher Staging-Fall | fachlicher Gesamtprozess ist abgenommen |
| `E6` | read-only Live-Smoke | Produktivstand ist erreichbar und konsistent |

Verbindlich:

- grüner PR = maximal E2;
- grüner Deploy ohne Runtime-Preflight = kein Beweis des gewählten Schreibpfads;
- echter Nutzerklick auf einen wiederverwendbaren R3-Prozess erst nach E4;
- keine Erfolgsbehauptung bei Teilmutation;
- direkter Main-Hotfix ist erst nach E6 abgeschlossen.

## 7. Fehlerbudget und Stop-the-line

Beim ersten unerwarteten realen Verhalten:

```text
Schreiben stoppen
-> Zustand und Evidence sichern
-> keine Wiederholung
-> Root Cause oder fehlende Observability bestimmen
```

Regeln:

1. Kein zweiter Schreibversuch ohne neue E3- oder E4-Evidence.
2. Immer nur eine technische Hypothese gleichzeitig prüfen.
3. Nach zwei widerlegten Hypothesen keinen dritten Patch bauen; Branch beziehungsweise Hotfix einfrieren und Architektur oder Revert neu bewerten.
4. Keine manuelle Datenkorrektur, um einen Test scheinbar grün zu machen.
5. Ein synthetischer Test hat Vorrang vor einem echten Fachdatensatz, außer bei der dokumentierten einzelnen deterministischen Admin-Mutation.

## 8. Nutzerinteraktion

Die KI bündelt notwendige Nutzeraktionen. Sie fragt nicht schrittweise nach Informationen, die sie selbst über Repo, CI, Logs, verbundene Dienste oder read-only Datenzugriffe ermitteln kann.

Eine Nutzeranweisung muss enthalten:

- genau einen konkreten Schritt;
- warum die KI ihn technisch nicht selbst ausführen kann;
- erwartetes Ergebnis;
- was nicht angeklickt oder verändert werden darf.

Screenshots sind für visuelle/fachliche Abnahme oder fehlende technische Schnittstellen vorgesehen, nicht als Ersatz für automatisierbare Runtime-Evidence.

## 9. Parallelität

Standardmäßig existiert nur ein aktiver schreibender Workpack. Ein optionaler zweiter Chat arbeitet ausschließlich read-only als Reviewer und erhält einen kopierbaren, klar begrenzten Prüfauftrag.

Mehrere schreibende Workpacks sind nur erlaubt, wenn ihre Unabhängigkeit in `CURRENT_WORKPACK.md` dokumentiert ist. Integration, Staging-Deploy, externe Schreibprobe und Release bleiben immer sequenziell. Während eines Live-Sofortpfads gilt ein exklusiver Lock auf die betroffenen Owner-Dateien oder die externe Ressource.

## 10. Werkzeugwahl

- GitHub-Connector: Standard für aktuellen Repo-Stand, Branches, PRs, kleine bis mittlere Änderungen und – nach technischer Ruleset-Freigabe – kontrollierte direkte Main-Hotfixes.
- Codespace/lokaler Clone: wenn reale Ausführung, Browser-Smoke, Build, große Refactorings oder umfangreiche Dateiumstrukturierung nötig sind.
- ZIP: nur Fallback oder bewusster Snapshotvergleich.
- Externe Ressourcen: gemäß `docs/external-resource-matrix.md`.

## 11. Kanonische Dokumente

1. `AI_ENTRYPOINT.md` – Arbeitsrouter.
2. `docs/workpacks/active/CURRENT_WORKPACK.md` – einziger aktiver technischer Status.
3. `docs/architecture/SYSTEM_MAP.md` – Systeme, Datenflüsse und Owner.
4. `ENGINEERING.md` – dauerhafte technische Guardrails.
5. `docs/external-resource-matrix.md` – externe Ressourcen und Schreibgrenzen.
6. `ROADMAP.md` – Produktprioritäten.
7. `TEST_STATUS.md` – Proofindex.
8. `docs/decisions/` und `docs/forensics/` – dauerhafte Entscheidungen und historische Evidence.

Historische Dokumente dürfen den aktuellen Router und `CURRENT_WORKPACK.md` nicht übersteuern.
