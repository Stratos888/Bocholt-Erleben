# Current Workpack

Stand: 2026-07-21

## Aktiver Workpack

**Arbeitsweise – automatische Ziel- und Werkzeugsteuerung praktisch validieren.**

Ziel:

- der Nutzer nennt nur ein gewünschtes Ergebnis, ein Problem oder fragt offen nach dem besten nächsten Schritt;
- die zuerst angesprochene KI ermittelt daraus selbst Ziel, Priorität, Aufgabengröße, Scope und Werkzeug;
- kleine Aufgaben bleiben im Chat;
- große Workpacks werden durch Work orchestriert, durch Codex technisch umgesetzt und durch Chat unabhängig geprüft;
- genau ein schreibender Agent bleibt verbindlich;
- nach einem erfolgreichen realen Pilot wird die allgemeine Arbeitsweisenoptimierung endgültig eingefroren.

Kanonische Regeln:

- `AGENTS.md`
- `AI_ENTRYPOINT.md`

## Praktischer Pilot

Der vorbereitete Workpack

- `docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

wird als reale Pilotaufgabe verwendet, ist aber **noch nicht zur Umsetzung freigegeben**.

Die KI legt die Werkzeugaufteilung automatisch fest. Für diesen großen Workpack ist der vorgesehene Zielablauf:

1. ein neuer Chat prüft den aktuellen Repository- und Workpack-Stand und erkennt den SEO-Pilot als großen, mehrstufigen Workpack;
2. Chat übergibt mit einem vollständigen Startprompt an Work;
3. Work übernimmt Gesamtziel, Gate-Steuerung, externe Evidenz und Orchestrierung;
4. Work gibt Codex einen klar abgegrenzten read-only Auftrag für Build-, Daten-, Rendering-, Schema- und Indexierungsanalyse;
5. Work führt die Teilbefunde zusammen und übergibt nur echte Entscheidungen an Chat;
6. Chat prüft Gate A unabhängig und entscheidet über den nachfolgenden schreibenden Codex-Auftrag;
7. bis zum vollständigen Abschluss von Gate A erfolgt kein fachlicher SEO-Patch.

## Erfolgskriterien

- der Nutzer musste weder Werkzeug noch technischen Scope auswählen;
- die KI leitete das operative Ziel aus Nutzeranliegen, Repository und Projektkontext ab;
- keine manuelle Repository-Datei und kein ZIP musste bereitgestellt werden;
- Work und Codex analysierten keine Bereiche unnötig doppelt;
- Work lieferte vollständige, selbstständig ausführbare Übergabeprompts;
- genau ein Agent schrieb ins Repository;
- keine ungeplante Datei lag im Diff;
- keine Grundsatzkorrektur wurde erst nach Beginn der Umsetzung nötig;
- `PR Gate` war vor jedem Merge grün;
- keine reale Try-and-Error-Schleife nach dem Staging-Merge;
- Chat wiederholte nicht die vollständige Work-/Codex-Analyse, sondern prüfte gezielt Entscheidungen, Diff und Wirkung;
- jede Instanz lieferte genau einen nächsten Schritt.

Eine kurze Abschlussbewertung in dieser Datei genügt. Kein Dashboard, kein zusätzlicher Workflow und kein separates Meta-Dokument.

## Aktuelle Locks

- bis zum Abschluss von Gate A gibt es keinen schreibenden SEO-Agenten;
- Work übernimmt die Orchestrierung des großen SEO-Piloten und bleibt repositoryseitig read-only;
- Codex bleibt bis zur Gate-A-Entscheidung repositoryseitig read-only;
- Chat bleibt Prüf- und Entscheidungsinstanz;
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

Einen neuen Chat im Projekt mit einer offenen Fortsetzungsfrage starten. Die KI muss den aktiven SEO-Pilot aus Repository und Projektkontext selbst erkennen und einen vollständigen Startprompt für Work liefern.
