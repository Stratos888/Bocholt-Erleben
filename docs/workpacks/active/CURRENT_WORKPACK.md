# Current Workpack Router

Stand: 2026-07-22

Diese Datei ist kein Statusjournal. Operativer Status, Entscheidungen, Evidence und der nächste Schritt stehen ausschließlich im zuständigen GitHub-Issue.

## Aktiver Workpack

**Keiner.**

## Zuletzt abgeschlossen

**SEO Recovery – Search Intent und statische Renderingbasis**

Kanonischer Abschluss:

- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

Die 14-/28-Tage-Rankingmessung ist laufender Betrieb und öffnet den Workpack nicht erneut.

## Nächster vorbereiteter Workpack

**SEO Structured Data – Search-Console-Warnungen**

Dauerhafter Scope:

- `docs/workpacks/queued/SEO-STRUCTURED-DATA-search-console-warnings-2026-07-22.md`

Operativer Status- und Evidence-Owner:

- GitHub-Issue **#165** – `[QUEUED] SEO Structured Data – Search-Console-Warnungen`

Der Workpack ist noch nicht aktiv. Bis zur Aktivierung gelten:

- keine Repository- oder Eventdatenänderung;
- keine pauschale Search-Console-Validierung;
- keine erfundenen Organizer-, Performer-, Preis-, Währungs-, `validFrom`-, Availability- oder Ticketwerte;
- keine Änderung an Startseite, statischem Rendering, Auswahl oder allgemeiner UI.

## Erster Test des vereinfachten Arbeitsmodells

Beim nächsten Chat gilt:

```text
Chat führt
-> Gate A read-only
-> Work nur bei belegten unabhängigen Liefersträngen
-> falls Patch nötig: genau ein Codex-Task und ein PR nach staging
-> ein Staging-Deploy und eine Abnahme
-> ein Release-PR nach main
-> operativer Status nur in Issue #165
```

Repository-Dokumentation wird während des Workpacks nicht fortlaufend aktualisiert. Ein dauerhaftes Wissensdelta wird genau einmal am Ende dokumentiert.

## Genau nächster Schritt

Im nächsten Chat zuerst `AI_ENTRYPOINT.md`, dieses Routerdokument, Issue #165 und die Queue-Datei lesen. Danach Gate A ausschließlich read-only durchführen und Issue #165 von `QUEUED` auf `ACTIVE` setzen, sobald der Nutzer den Workpack ausdrücklich startet.