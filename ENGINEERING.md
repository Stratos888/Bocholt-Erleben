# ENGINEERING – Bocholt erleben

Diese Datei enthaelt technische Guardrails fuer KI- und Repo-Arbeit. Der operative Arbeitsprozess steht in `AI_ENTRYPOINT.md`. `ENGINEERING.md` definiert, welche technischen Grenzen, Owner-Dateien, Datenquellen, externen Ressourcen und Sicherheitsregeln dabei nicht verletzt werden duerfen.

## 1. Rolle dieser Datei

`ENGINEERING.md` ist keine Workflow-Datei mehr.

- Workflow-Routing und Chatrollen: `AI_ENTRYPOINT.md`.
- Externe Ressourcen, Isolation und Schreibstatus: `docs/external-resource-matrix.md`.
- Strategische Produktsteuerung: `MASTER.md`.
- Taktische Workpacks: `ROADMAP.md`.
- Proofs und Abnahmen: `TEST_STATUS.md`.
- Technische Guardrails: diese Datei.

Wenn alte Chatstaende, ZIPs oder historische Doku widersprechen, gilt fuer Repo-Arbeit zuerst `AI_ENTRYPOINT.md`, danach diese Datei fuer technische Sicherheitsregeln.

## 2. Source-of-Truth-Regeln

### 2.1 Repo-Arbeit

- Aktueller GitHub-Stand auf Branch `staging` ist die primaere Repo-Baseline, sofern der GitHub-Connector verfuegbar ist.
- Aktive Codespace-/lokale Git-Ausgaben des Nutzers koennen diese Baseline uebersteuern, wenn der Nutzer gerade in einer Arbeitskopie arbeitet.
- ZIPs sind Fallback oder bewusster Snapshot-Vergleich, nicht Standard.
- Nie ohne sichtbaren aktuellen Code der betroffenen Datei patchen.
- Jeder schreibende Workpack beginnt auf einem eigenen Branch von einem dokumentierten `staging`-SHA.
- Vor Integrationsfreigabe muss der aktuelle `staging`-Stand erneut einbezogen und der Diff gegen diesen Stand geprueft werden.

### 2.2 Eventdaten

Produktions- und Staging-Eventdaten sind hybrid:

1. Google Sheet Tab `Events` ist die redaktionelle Quelle fuer KI-/Sheet- und manuell gepflegte Events.
2. Freigegebene DB-Submissions aus `/api/events/public.php` ergaenzen den oeffentlichen Feed.
3. `data/events.tsv` und `data/events.json` sind Deploy-/Runtime-Artefakte und keine handzupflegende redaktionelle Quelle.
4. Ein lokaler Repo-/ZIP-Stand von `data/events.json` beweist nicht, dass Live-/Stagingdaten veraltet sind.

Staging darf fuer Sheet-Inbox-Kandidaten nicht die Live-Tabs `Inbox` oder `Inbox_Archive` verwenden. Staging nutzt `Inbox_Staging` und `Inbox_Archive_Staging`.

`Inbox -> Events` Import ist production-only und darf nicht auf `staging` laufen.

Der Tab `Events` ist derzeit nicht als separate Staging-Schreibressource belegt. Staging-Tests duerfen ihn daher nicht mutieren. Eine spaetere Schreibfreigabe erfordert eine echte `Events_Staging`-Quelle oder einen isolierten Adapter.

### 2.3 Activity- und sonstige Daten

- Activities bleiben in V1 repo-/JSON-owned, sofern kein spaeterer Owner-Vertrag sie in Sheet oder DB verschiebt.
- Activity-Korrekturen werden als sichtbare Repo-Patch-Kandidaten behandelt.
- `data/search-metrics.json` und vergleichbare Runtime-/Report-Artefakte duerfen nicht als dauerhafte fachliche Source of Truth interpretiert werden.
- Externe Datenquellen duerfen nie allein aus UI-Text, Dateinamen oder historischer Doku als staging-isoliert eingestuft werden.

### 2.4 Externe Ressourcen

Die kanonische Ressourcen- und Schreibmatrix steht in `docs/external-resource-matrix.md`.

- Alles, was nicht ausdrücklich als staging-isoliert nachgewiesen ist, bleibt fail-closed und read-only.
- Workpack-Chats duerfen keine externen Daten mutieren.
- Eine kontrollierte Staging-Schreibprobe wird nur vom Integrations-Chat nach Ziel-, Identitaets-, Vorherzustands- und Rollback-Nachweis koordiniert.
- Live-Schreibtests sind ausgeschlossen.
- Git-Konfliktfreiheit ist kein Nachweis, dass zwei Workpacks dieselbe externe Ressource sicher parallel verwenden koennen.

## 3. Content-Quality- und KI-Audit-Regeln

Content-Quality-Checks muessen der tatsaechlichen Datenhoheit folgen:

1. Google Sheet `Events` fuer redaktionelle Events.
2. DB/API fuer freigegebene Veranstalter-Events.
3. Repo-/JSON-Dateien fuer Activities.

Regeln:

- Audit darf technische Artefakte, Reports, Empfehlungen und Review-Kandidaten schreiben.
- Audit und KI duerfen fachliche Source-Daten nicht blind ueberschreiben.
- KI-Verifikation ist gezielter Fallback, nicht Standard fuer alle Inhalte.
- Sichere technische Faelle duerfen auto-handled oder still beobachtet werden.
- Semantisch unsichere Faelle werden `review_needed`, `warning` oder typed correction candidate.
- Manuelle Entscheidungen laufen ueber Inbox/Admin-/Review-Flaechen, nicht ueber direkte Sheet-Bearbeitung durch den Nutzer.
- Feedback aus Audit, Inbox und Ablehnungen darf Suchprioritaeten und Prompts verbessern, aber keine kanonischen Event-, Activity- oder DB-Daten still mutieren.
- Feedbacksignale muessen typisiert, begrenzt, konsolidiert und ablaufbar sein. Kein Rohdump in Prompts.
- KI-Faktencheck-Ergebnisse muessen strukturierte States nutzen, z. B. `confirmed`, `conflict`, `better_source_found`, `not_found`, `uncertain`.

Event-Visual-Fit ist eine eigene Qualitaetsdomaene. Visual-Key-, Motiv- und Bildpool-Aenderungen laufen ueber `VISUAL_WORKFLOW.md`, nicht ueber generische Content-Link-/Datumskorrekturen.

## 4. CSS- und Owner-Regeln

### 4.1 CSS-Dateirollen

- `css/style.css` = public CSS entrypoint und Import-Reihenfolge. Keine normalen Selektor- oder visuellen Fixes.
- `css/base.css` = Tokens, Reset, Foundation, app-weite Primitive.
- `css/pages.css` = Public Content Pages, statische Seiten, Funnel und Legal Pages.
- `css/components.css` = wiederverwendbare UI-Komponenten und States.
- `css/home.css` = historischer Discovery/Event/Activity-Shell-Owner; nicht als Dumping Ground fuer neue grosse UI-Bloecke verwenden.
- `css/today.css` = Today/Home-spezifische Surface- und Recommendation-Layouts.
- `css/overlays.css` = Detailpanel, Sheets, Modals, Overlay Locks.

### 4.2 Owner-Prinzip

- Components stylen sich selbst; Page-/Layout-Dateien platzieren sie.
- Layout-Fixes gehoeren in die owning Layout-Datei, nicht in zufaellige Komponenten.
- Overlay-Mechanik gehoert in `css/overlays.css`.
- Cross-file-Fixes nur, wenn die Root Cause sie beweist.
- Wenn ein Owner-Block existiert, diesen konsolidieren statt spaete Override-Bloecke anzufuegen.
- Neue grosse CSS-Bloecke brauchen zuerst eine Owner-Entscheidung.
- CSS-Governance wird durch `tools/audit-css-governance.py` abgesichert und darf nicht fuer kosmetische Patches umgangen werden.
- Ein aktiver Code-/Owner-Lock sperrt den betroffenen Pfad fuer andere schreibende Workpacks, auch wenn Git noch keinen Konflikt meldet.

### 4.3 Token-first

- Bestehende Design-Tokens zuerst nutzen.
- Neue Werte nur als Token einfuehren, wenn sie wiederverwendbar sind.
- Wiederholte Hardcoded-Werte vermeiden.

## 5. UI-Polish- und No-Hotfix-Regeln

- UI-Polish soll owner-file-fokussiert bleiben.
- CSS-only bevorzugen, ausser Root Cause beweist JS/HTML-Bedarf.
- Keine kleinen visuellen Fixes breit ueber viele Dateien streuen.
- Keine Override-Ketten als Endzustand akzeptieren.
- Richtiger Zielzustand ist ein klarer Owner-Block mit Base, Mobile Contract, Desktop Contract und Breakpoint-Refinements.
- Vor UI-Regressionsfixes klaeren: fehlender Owner, konkurrierende Owner oder defekte Breakpoint-Grenze?
- Ein visueller Workpack soll maximal einen Hauptcommit und einen gezielten Polish-Commit haben. Danach stoppen und neu bewerten.

## 6. Overlay-Regeln

- Alle Overlays muessen in einem dedizierten Overlay-Root direkt unter `body` rendern.
- Overlays niemals in sticky, transformed oder backdrop-filter Containern rendern.

## 7. Deploy-, Cache- und Asset-Sicherheit

- Deterministisches Build- und Versionierungsverhalten bewahren.
- Service Worker, Cache und Asset-Referenzen nicht beiläufig aendern.
- Broken Asset References sind fail-fast.
- `css/style.css` bleibt der einzige oeffentliche CSS-Entrypoint.
- Public HTML-Dateien laden `/css/style.css`.
- Asset-Query-Versionen nicht manuell in vielen HTML-Dateien bumpen; der Deploy ersetzt Versionen mit `BUILD_ID`.
- Asset-Referenzen nur manuell anfassen, wenn ein neues Asset eingefuehrt, umbenannt oder ein fehlendes Tag ergaenzt wird.
- Deploy-/Workflow-/Service-Worker-/Cache-Aenderungen sind kein kleiner G2-Direktcommit und brauchen erhoehtes Risiko-Routing.
- `Deploy to STRATO` akzeptiert ausschliesslich `main` und `staging`. Andere oder leere Branches muessen vor Python-Setup, Secrets, Google Sheets, Search-Metrics oder SFTP fail-closed abbrechen.
- Die kanonische Branch-Aufloesung liegt in `scripts/resolve-deploy-target.sh` und wird durch `tests/test_deploy_branch_routing.sh` geprueft.
- Staging- und Live-Deploys werden nie parallel gestartet. Ein Integrationsschritt ist erst abgeschlossen, wenn Deploy und Abnahme des vorherigen Schritts beendet sind.

## 8. Premium Visual Asset Contract

Fuer Event- und Activity-Visuals ist die Standardloesung nicht manuelles Crop-Raten. Standard ist ein vorbereitetes, geprueftes Card-Asset.

Visual-Status:

- `ready`: fuer Premium-Card-Nutzung freigegeben.
- `usable`: normal nutzbar, aber nicht automatisch fuer prominente Today/Home-Flaechen freigegeben.
- `fallback`: bewusst freigegebener symbolischer Fallback.
- `needs_review`: nicht fuer prominente Flaechen.
- `blocked`: nicht verwenden.

Regeln:

- Today/Home und andere prominente Empfehlungsflaechen nutzen nur `ready` oder bewusst freigegebene `fallback`-Visuals.
- Schwache Bilder nicht dauerhaft per CSS/object-position kaschieren.
- Unsichere, unklare oder schwache externe Bilder ersetzen, downgraden oder ausschliessen.
- CSS definiert Frame, Ratio und Fallback-Position; CSS ist kein Bildqualitaetskontrollsystem.
- Visual-Key- und Motiv-Fixes laufen ueber Pool-/Audit-/Asset-Contracts und `VISUAL_WORKFLOW.md`.

## 9. Codespace, Smoke und lokale Ausfuehrung

Codespace ist Spezialwerkzeug, nicht Standardprozess.

Nutzen bei:

- Browser-Smoke / Playwright;
- Python-/Node-/PHP-Ausfuehrung;
- Build-/Deploy-Skripten;
- generierten Artefakten;
- Backend/API/DB/Stripe/Mail;
- Service Worker / Cache / Deploy-Workflow;
- grossen Refactorings;
- Asset-/Bildverarbeitung.

Mehrere schreibende Workpacks duerfen nicht dieselbe Arbeitskopie verwenden. Pro Branch ist ein eigener Worktree, Clone oder Connector-basierter Branch-Kontext erforderlich.

Browser-Smoke bleibt read-only:

- keine echten Checkouts;
- keine produktiven E-Mails;
- keine produktiven Schreibaktionen;
- keine Auto-Reparatur;
- kein Auto-Rollback.

Ein roter Staging-Smoke blockiert fachlich den Merge nach `main`. Ein roter Main-Smoke erzeugt Handlungsbedarf, aber fuehrt nicht automatisch Code aus.

## 10. ZIP-Fallback

ZIP ist Fallback, nicht Standard.

Geeignet, wenn GitHub-Zugriff nicht verfuegbar ist oder ein expliziter Snapshot-Vergleich benoetigt wird.

Patch-ZIPs muessen direkt die Repo-Root-Struktur enthalten und duerfen keine Wrapper-/Hilfsdateien enthalten:

- kein `README.txt`;
- kein `MANIFEST.json`;
- kein `UPLOAD_TO_REPO_ROOT`;
- kein zusaetzlicher Repo-Root-Ordner.

Nicht bevorzugt fuer grosse Refactorings, viele Loeschungen, Dateiumbenennungen, Merge-Konflikte oder Asset-Massenarbeiten.

## 11. Parallele Workpacks, Code-Lock und Ressourcen-Lock

### 11.1 Code-/Owner-Lock

Jeder schreibende Draft-PR deklariert erlaubte und gesperrte Dateien/Pfade. Ein zweiter Workpack darf denselben Owner-Pfad nicht parallel veraendern.

Besonders zentral und standardmaessig seriell zu behandeln sind:

- `.github/workflows/**`;
- `AI_ENTRYPOINT.md`, `ENGINEERING.md`, `MASTER.md`, `ROADMAP.md`, `TEST_STATUS.md`;
- `css/style.css`, globale Tokens und Bootstrap-/Entrypoint-Dateien;
- zentrale Control-Center-Schema-, Source-, Writeback- und Environment-Dateien;
- Service Worker, Cache- und Deploy-Konfiguration;
- gemeinsame Datenvertraege und Generatoren.

### 11.2 Ressourcen-Lock

Ein Ressourcen-Lock gilt fuer externe Systeme unabhaengig von Git-Dateipfaden. Beispiele:

- `Google Sheet / Inbox_Staging`;
- `Google Sheet / Events`;
- Growth-Backlog;
- Submissions-DB;
- Stripe-/Mail-Testpfade;
- STRATO Staging oder Live;
- GitHub Deploy-/Releasepfad.

Pro Ressource darf nur ein Integrations- oder Abnahmevorgang gleichzeitig aktiv sein.

### 11.3 Sequenzielle Integration

Auch unabhaengige Branches werden einzeln integriert:

```text
PR aktualisieren -> Checks -> Merge nach staging -> Deploy -> Abnahme -> naechster PR
```

Der Integrations-Chat ist alleiniger Merge- und Abnahme-Owner. Workpack-Chats starten keinen Deploy und mergen nicht selbst.

## 12. Incident- und Stop-the-line-Sicherheit

Sofortiger Stopp bei:

- unerwarteter Zielumgebung oder Ressource;
- nicht eindeutig aufloesbarer Objekt-/Zeilenidentitaet;
- Abweichung zwischen Quelle, lokaler Kopie, API und UI;
- unerwarteter Datenmutation;
- fehlendem Vorherzustand;
- manueller Datenkorrektur als Voraussetzung fuer einen gruenen Test;
- parallelem Zugriff auf denselben Code- oder Ressourcen-Scope;
- Tests, die nur statische Marker und nicht den Zielzustand beweisen.

Nach dem Stopp sind nur read-only Forensik, Evidence-Sicherung und Root-Cause-Analyse erlaubt. Kein weiterer Funktionspatch, keine Datenkorrektur, keine neue Schreibprobe und kein Merge bis zur expliziten Integrationsfreigabe.

## 13. Patch- und Schreibdisziplin

- Keine Schreibaktion ohne ausdrueckliche Nutzerfreigabe im aktuellen Chat.
- Keine direkten KI-Schreibaktionen auf `main`.
- Im Parallelbetrieb keine direkten KI-Schreibaktionen auf `staging`.
- Kein Force-Push.
- Keine Branch-Loeschung.
- Keine Datei-Loeschung ohne ausdrueckliche Spezialfreigabe.
- Keine parallelen konkurrierenden Workpacks auf demselben Branch, denselben Owner-Pfaden oder derselben externen Ressource.
- Keine neuen grossen Patches auf halbfertige oder dirty Zwischenstaende stapeln.
- Nach fehlgeschlagener Aenderung: stoppen, Zustand pruefen, Root Cause klaeren, nicht blind erneut versuchen.
- Revert bevorzugt per Revert-Commit, nicht per Force-Reset.
- Der Workpack-Chat darf seinen PR nicht selbst nach `staging` oder `main` mergen.

## 14. Dokumentations-Governance

- `MASTER.md` bleibt kurz strategisch: aktueller Fokus, gefrorene Bereiche, permanente Produktentscheidungen.
- `ROADMAP.md` bleibt taktisch: aktive/geparkte Workpacks und naechste Proofs.
- `TEST_STATUS.md` bleibt Proof-Archiv und Testindex, nicht Produktdefinition.
- `Produktvertrag.md` bleibt Produktmodell, Preise, Tarife, Funnel-Logik.
- Prozess-, Rollen- und Workflow-Routing gehoert in `AI_ENTRYPOINT.md`.
- Technische Guardrails gehoeren in `ENGINEERING.md`.
- Externe Ressourcen und Schreibgrenzen gehoeren in `docs/external-resource-matrix.md`.
- Alte Doku darf als Beweis stehen bleiben, darf aber nicht alte Arbeitsmodi reaktivieren.
- Ein Incident- oder Arbeitsstopp-Dokument ersetzt niemals einen validierten Produkt-Freeze.

## 15. Projektzielbezug

Technische Arbeit dient dem Premium-Zielzustand:

- vertrauenswuerdig;
- ruhig;
- modern;
- stabil;
- leicht scannbar;
- mobile-first;
- kuratierter lokaler Sichtbarkeitskanal fuer Nutzer, Veranstalter und Anbieter.

Keine technische Aenderung darf das Produkt in Richtung Anzeigenportal, Zwei-Klassen-Optik, gekaufte Empfehlungen oder instabile Bastelarchitektur verschieben.
