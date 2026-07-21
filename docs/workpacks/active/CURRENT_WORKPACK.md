# Current Workpack

Stand: 2026-07-21

## Aktiver Workpack

**Arbeitsweise – automatische Ziel- und Werkzeugsteuerung praktisch validieren.**

Ziel:

- der Nutzer nennt nur ein gewünschtes Ergebnis, ein Problem oder fragt offen nach dem besten nächsten Schritt;
- der primäre Chat ermittelt daraus selbst Ziel, Priorität, Scope, Werkzeug und vollständigen Übergabeprompt;
- Work, Codex und Chat werden ohne Doppelarbeit nach ihrer jeweiligen Stärke eingesetzt;
- genau ein schreibender Agent bleibt verbindlich;
- nach einem erfolgreichen realen Pilot wird die allgemeine Arbeitsweisenoptimierung endgültig eingefroren.

Kanonische Regeln:

- `AGENTS.md`
- `AI_ENTRYPOINT.md`

## Praktischer Pilot

Der vorbereitete Workpack

- `docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

wird als reale Pilotaufgabe verwendet, ist aber **noch nicht zur Umsetzung freigegeben**.

Die Werkzeugaufteilung wird durch den primären Chat automatisch festgelegt:

1. Work bearbeitet nur den externen beziehungsweise fachlichen Rechercheteil, sofern dafür ein klarer Mehrwert besteht;
2. Codex bearbeitet den Repository-Teil zunächst read-only;
3. Chat verbindet beide Ergebnisse, entscheidet über Gate A und formuliert erst danach gegebenenfalls den schreibenden Codex-Auftrag;
4. bis zum vollständigen Abschluss von Gate A erfolgt kein fachlicher SEO-Patch.

## Erfolgskriterien

- der Nutzer musste weder Werkzeug noch technischen Scope auswählen;
- keine manuelle Repository-Datei oder kein ZIP musste bereitgestellt werden;
- keine Analyse wurde unnötig in Work und Codex dupliziert;
- genau ein Agent schrieb ins Repository;
- keine ungeplante Datei lag im Diff;
- keine Grundsatzkorrektur wurde erst nach Beginn der Umsetzung nötig;
- `PR Gate` war vor jedem Merge grün;
- keine reale Try-and-Error-Schleife nach dem Staging-Merge;
- der primäre Chat lieferte bei jedem notwendigen Werkzeugwechsel einen vollständig kopierbaren Startprompt und genau einen nächsten Schritt.

Eine kurze Abschlussbewertung in dieser Datei genügt. Kein Dashboard, kein zusätzlicher Workflow und kein separates Meta-Dokument.

## Aktuelle Locks

- dieser Chat ist bis zum Merge des Routing-Patches der einzige schreibende Agent;
- nach dem Merge steuert genau ein primärer Chat den Pilot;
- Work bleibt repositoryseitig read-only;
- Codex bleibt bis zur Gate-A-Entscheidung repositoryseitig read-only;
- keine parallelen Änderungen an zentralen Ownern;
- kein direkter Commit nach `staging` oder `main`;
- kein Live-Write und keine Veröffentlichung im Pilot ohne separate Freigabe.

## Zuletzt abgeschlossen

**Allgemeine Repository-, Dokumentations-, Sicherheits- und Infrastrukturgrundlage.**

Ergebnis:

- kanonischer Einstieg, Workpack-Steuerung und Proofindex konsolidiert;
- geschützte Releasefolge `Feature-Branch -> staging -> main` mit verpflichtendem `PR Gate`;
- Rulesets, CodeQL, Dependabot, Secret Scanning und Push Protection aktiv;
- Deployjob mit `contents: read` den geschützten Environments `staging` und `main` zugeordnet;
- Staging- und Live-Deploy einschließlich Smoke-Tests erfolgreich validiert;
- `STAGING_REVIEW_PASSWORD` ausschließlich im Environment `staging`; Login nach Entfernung des gleichnamigen Repository-Secrets erfolgreich;
- übrige bestehende Repository-Secrets bleiben bewusst unverändert; neue umgebungsspezifische Secrets werden direkt im passenden Environment angelegt.

## Genau nächster Schritt

Den Routing-Patch über `PR Gate` nach `staging` integrieren. Danach startet ein neuer primärer Chat den SEO-Gate-A-Pilot und gibt selbst vor, welcher Teil in Work, Codex oder Chat ausgeführt wird.
