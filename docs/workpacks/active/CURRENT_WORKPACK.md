# Current Workpack

Stand: 2026-07-20

Diese Datei ist der einzige operative technische Projektstatus. Offene PRs, aktuelle SHAs und CI-Zustände werden bei jeder Aufgabe direkt aus GitHub gelesen und nicht dauerhaft hier gespiegelt.

## Aktiver Implementierungs-Workpack

**Control Center: serververmittelter Ersatzpfad für genau einen synthetischen E4-Staging-Beweis**

- Detailvertrag: `docs/workpacks/active/CONTROL-CENTER-E4-SYNTHETIC-2026-07-20.md`
- Evidence zum ersten Lauf: `docs/evidence/control-center-e4-direct-db-timeout-2026-07-20.md`
- Domain-Router: `docs/domains/control-center.md`
- Risikoklasse: `R3`
- finaler vor dem Lauf geprüfter `staging`-SHA: `311db94a12d67efc1921af1d4981cfaacb016d82`
- Main-Operatoranker: integriert, manuell, bestätigt und SHA-gebunden
- Evidence vor E4: E1, E2 und fachfallfreies read-only E3 grün
- erster E4-Start: vor jeder Mutation durch MySQL-Verbindungs-Timeout abgebrochen
- verbleibende Evidence-Lücke: ein erfolgreicher und vollständig bereinigter Ersatz-E4-Lauf

## Belegter Fehler und Architekturentscheidung

Der GitHub-hosted Runner konnte die geschützte STRATO-Staging-Datenbank nicht direkt über MySQL erreichen. Der Timeout trat beim Konstruktor der bisherigen Datenbankklasse auf, bevor `mutations_started = True` gesetzt wurde.

Damit entstanden keine synthetischen Sheet-, DB- oder Feedzustände und keine Live-Mutation.

Der direkte MySQL-Pfad wird nicht durch Portfreigabe, wechselnde IP-Allowlist oder erneuten unveränderten Lauf repariert. Der nachhaltige Zielpfad ist:

```text
GitHub-hosted Runner
-> authentifiziertes HTTPS
-> Staging-only E4-Endpunkt
-> private serverseitige PDO-Verbindung
```

Der Endpunkt akzeptiert ausschließlich den exakten synthetischen Run-Key und verweigert Produktion, fremde Identitäten, fremde Operationen und reale Cases.

## Verbindlicher Evidence-first-Modus

1. Der bestehende fachliche E4-Ablauf bleibt unverändert; nur sein DB-Transport wird ersetzt.
2. Der aktive Runner-Einstieg enthält keine direkte MySQL-Verbindung und keine DB-Zugangsdaten.
3. Preflight und Fault-Injection verlangen den passenden deployten Staging-Build.
4. State-Abfrage und Cleanup bleiben nach einer möglichen Staging-Bewegung verfügbar, aber auf den exakten Run-Key begrenzt.
5. Alle negativen Verträge werden vor Integration mit injizierten E2-Tests bewiesen.
6. Vor einem Ersatzlauf müssen E2, Deploy und fachfallfreies read-only E3 für den neuen finalen SHA grün sein.
7. Der fehlgeschlagene alte Lauf wird nicht erneut ausgeführt.

## Autoritative technische Kette

1. `PR Gate` – Always-run-Integration und Branchpolicy.
2. `Project Guardrails` – Architektur-, Dokumentations- und E4-Sicherheitsvertrag.
3. `Control Center CI` – vollständige E1/E2-Prüfung einschließlich serververmitteltem E4-Contract.
4. `Deploy to STRATO` – einziger Deploypfad.
5. `Staging Verification` – fachfallfreie read-only E3-Evidence.
6. `Control Center E4 Synthetic Proof` – manuelle, bestätigte und SHA-gebundene R3-Capability.

`Staging Verification` darf E4 weder aufrufen noch dispatchen.

## Aktiver Ressourcen-Lock

Bis zum Abschluss gilt:

- keine parallele Control-Center-Workflow-, Writer- oder E4-Endpunktänderung;
- keine parallele Mutation in `Inbox_Staging`, `Events_Staging` oder der Staging-Control-Center-DB;
- kein CityArt-Fachklick und kein anderer echter Fachfall;
- keine Mutation in `Inbox` oder `Events`;
- kein Live-Deploy und keine Live-Schreibaktion;
- keine Datenbankfreigabe für GitHub-hosted Runner;
- kein Ruleset-, Required-Check- oder Status-Bypass.

## E4-Erfolgskriterien

- aktueller Staging-SHA, Host, Build und Ressourcenpfad bestätigt;
- keine synthetischen Reste vor dem Write;
- Success-Write und idempotenter Replay;
- exakt kontrollierter Teilfehler und Resume ohne Duplikat;
- beide synthetischen IDs genau einmal im Staging-Feed und niemals im Live-Feed;
- vollständiger Sheet-, DB- und Feed-Cleanup;
- Nicht-Testdaten in Staging sowie Live-Ressourcen unverändert;
- Evidence `result=success`, keine Cleanup-Fehler.

## Stop-the-line

```text
unerwartetes Verhalten
-> weitere Writes stoppen
-> Evidence sichern
-> nur Run-Key-begrenzten Cleanup versuchen
-> keinen unveränderten Lauf wiederholen
-> Architektur- oder Revertentscheidung
```

## Nächster erlaubter Schritt

Den serververmittelten E4-Contract, Adapter, Tests, Guards und diese Dokumentation vollständig durch E2 validieren. Nur bei grünem, mergebarem PR wird nach `staging` integriert. Danach müssen Deploy und fachfallfreies read-only E3 für den neuen finalen SHA grün sein.

Erst anschließend darf genau ein neuer manueller Ersatz-E4-Lauf gestartet werden. Der bisherige fehlgeschlagene Lauf wird nicht erneut ausgeführt.

## Nicht Teil dieses Workpacks

- echter CityArt- oder anderer Fachfall;
- Produkt-, UI-, SEO-, Content- oder Visualänderung;
- breiter `staging -> main`-Release;
- Live-Release oder Live-Write;
- Öffnung der Staging-Datenbank für externe Runner.

## Folgezustand

Nach grünem und bereinigtem E4 wird der Workpack abgeschlossen. Danach wird gesondert entschieden, ob ein echter Staging-Fachfall noch E5-Mehrwert besitzt; er wird nicht automatisch ausgeführt. Anschließend folgt wieder ein produktwirksamer Workpack.
