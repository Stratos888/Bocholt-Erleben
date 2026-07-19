# Dokumentregister

Stand: 2026-07-19

Dieses Register definiert die zulässigen Rollen und Ablageorte der Projektdokumentation. Es ist die menschlich lesbare Entsprechung der Klassifikation in `scripts/report-documentation-inventory.py`.

## 1. Verbindliche Grundregel

Jede Markdown-Datei hat genau eine Hauptrolle. Der Dateipfad, nicht der Chat oder die Entstehungsgeschichte, entscheidet darüber, wie eine KI sie verwenden darf.

```text
aktuell kanonisch
> aktueller Workpack oder Evidence
> aktuelle Domainverträge und Playbooks
> Ziel- oder Detailreferenz
> historische oder archivierte Datei
```

Eine niedrigere Ebene darf eine höhere Ebene nicht übersteuern.

## 2. Kanonische Root-Dokumente

| Datei | Rolle | Darf enthalten | Darf nicht enthalten |
|---|---|---|---|
| `README.md` | menschlicher Repo-Einstieg | Projektzweck, Startpfad, Branchmodell | operativer Status |
| `AI_ENTRYPOINT.md` | KI-Arbeitsrouter | Mandat, Gates, Risiko, Evidence, Stop-Regeln | fachliche Detailarchitektur |
| `ENGINEERING.md` | technische Guardrails | dauerhafte Entwicklungs- und Runtime-Regeln | PR-/Incidentstatus |
| `MASTER.md` | Produkt-Nordstern | stabiles Premium-Zielbild | laufende Umsetzungsschritte |
| `Produktvertrag.md` | umgesetzte Produktmechanik | tatsächlich gültiges Produktverhalten | ungebaute Zielzustände |
| `COMMERCIAL_STRATEGY.md` | kommerzielle Strategie | Geschäftslogik und Richtung | technischer Status |
| `ROADMAP.md` | priorisierte Produktziele | Reihenfolge und Entscheidungsgrenzen | Runtimebeweise |
| `TEST_STATUS.md` | aktueller Proofindex | kompakte Evidence-Lage und Links | vollständiges Testtagebuch |

Diese Dateien werden inhaltlich ersetzt, nicht durch datierte Statusblöcke erweitert.

## 3. Domain-Router

| Datei | Domäne | Pflicht bei |
|---|---|---|
| `docs/domains/control-center.md` | Control Center, Inbox-Review, Source-Reconciliation, externe Writes | Steuerzentrale, Workflowkette, E3/E4, Review-UI |
| `docs/domains/event-search-system.md` | Eventsuche, Contentprüfung, Quellen | Suchlauf, Intake, Eventtext, Feedback |
| `docs/domains/visual-system.md` | Event- und Activity-Visuals | Motiv, Asset, Duplikat, Rendering |

Große Detaildateien werden nur über den zuständigen Router und abschnittsweise gelesen.

## 4. Aktuelle Root-Verträge und Playbooks

Folgende Root-Dateien besitzen eine begrenzte fachliche Rolle und sind keine allgemeinen Projektstatusquellen:

| Gruppe | Dateien | Rolle |
|---|---|---|
| UI-Proof | `DEBUG.md` | UI-/Layout-Playbook |
| Eventcontent | `EVENT_DESCRIPTION_STANDARD.md` | aktueller Inhaltsvertrag |
| Visuals | `VISUAL_WORKFLOW.md`, `ACTIVITY_VISUAL_*` | Visualvertrag oder Detailreferenz |
| Eventsuche | `bocholt-erleben_eventsuche_regelwerk_v3.md`, `eventsuche_quellenregister_v1.md` | Suchreferenz und Quellenregister |
| Browser-Smoke | `BROWSER_SMOKE_SYSTEM.md` | Smoke-Vertrag |
| Wirkung | `EVENT_IMPACT_TRACKING.md` | Trackingvertrag |
| Mail | `MAIL_SYSTEM.md` | fachliche Mail-Referenz |
| Activities/Badegewässer | `ACTIVITY_SEASONAL_HIGHLIGHTS.md`, `BATHING_WATER_*` | Domainreferenz oder Evidence |
| Steuerzentrale | `STEUERZENTRALE_*` | Vertrag, Zielreferenz oder historischer Beleg gemäß expliziter Rolle |

Root-Dateien dürfen erst nach einer erfolgreichen Vollinventur und einem atomaren Referenzpatch verschoben werden. Eine bloße Aufräumabsicht rechtfertigt keine Massenmigration.

## 5. Explizit klassifizierte Dokumente unter `docs/`

| Datei oder Gruppe | Hauptrolle |
|---|---|
| `GROWTH_INTELLIGENCE_BACKLOG.md` | aktueller Growth-Vertrag |
| `GROWTH_TRACKING_GAPS.md` | aktuelle Gap-Referenz, kein Workpackstatus |
| `content-ops-decision-impact-engine.md`, `content-ops-http-ingest.md` | aktuelle Content-Ops-Verträge |
| `ingest-bridge.md` | veralteter Alias auf den kanonischen HTTP-Ingest-Vertrag |
| `content-ops-self-learning-target.md`, `internal-dashboard-target.md`, `internal-dashboard.md`, `startpartner-wachstumspilot-zielzustand-2026-07-18.md` | Zielreferenzen, keine Implementierungsbehauptung |
| `detailpanel-premium-system-contract.md` | Legacy-Vertragsreferenz; aktuelle Produktmechanik steht im Produktvertrag und Code |
| `steuerzentrale-backlog-roadmap-vertrag.md` | aktueller Control-Center-Vertrag |
| `steuerzentrale-inbox-feature-gap-analysis-2026-07-16.md` | datierte Forensik |
| `steuerzentrale-ausnahmebasierte-eventpruefung-implementation-2026-07-16.md`, `steuerzentrale-e2e-state-consistency-workpack-2026-07-16.md` | abgeschlossene Implementierungsbelege |
| `content-ops-robot-handoff.md`, `steuerzentrale-naechstes-workpack-ausnahmebasierte-eventpruefung-2026-07-16.md`, `steuerzentrale-redaktioneller-entscheidungs-und-lernprozess.md` | historische Handoffs beziehungsweise Phasen-Workpacks, niemals aktueller Arbeitsrouter |
| `deploy/staging-phase3-redeploy-2026-07-15.md`, `staging-redeploy-2026-07-15-compact-backlog.md`, `live-event-feed-refresh-2026-07-18-kinderzaubershow.md` | historische Deploy-/Release-Evidence |
| `today-home-premium-release-plan.md` | historischer Releaseplan |

Alte SHAs, PR-Nummern und damalige „nächste Schritte“ in historischen Rollen sind Beleginhalt. Sie übersteuern keine aktuelle Steuerdatei.

## 6. Pfadbasierte Rollen

| Pfad | Rolle | Mutabilität |
|---|---|---|
| `docs/README.md` | Dokumentations-Governance | aktuell, ersetzend |
| `docs/DOCUMENT_REGISTRY.md` | Rollenregister | aktuell, ersetzend |
| `docs/architecture/**` | stabile Architektur oder Architekturreferenz | selten |
| `docs/domains/**` | kompakte aktuelle Domain-Router | bei dauerhafter Domainänderung |
| `docs/contracts/**` | aktuelle Domain- oder Runtimeverträge | bei Vertragsänderung |
| `docs/playbooks/**` | operative Vorgehensweisen | bei Prozessänderung |
| `docs/workpacks/active/CURRENT_WORKPACK.md` | einziger operativer Status | bei jedem Statuswechsel |
| `docs/workpacks/active/**` | unterstützender aktiver Scope | nur wenn im Current Workpack geroutet |
| `docs/workpacks/queued/**` | noch nicht aktive Umsetzungspakete | vor Aktivierung neu validieren |
| `docs/workpacks/completed/**` | abgeschlossene Umsetzungspakete | nach Abschluss unverändert |
| `docs/decisions/**` | angenommene Zielentscheidungen | datiert, danach grundsätzlich unverändert |
| `docs/forensics/**` | belegte Ursachenanalysen | datiert, danach grundsätzlich unverändert |
| `docs/evidence/**` | konkrete Belege | neue separate Dateien statt Statusmischung |
| `docs/reference/**` | unterstützende Detailreferenz | kein aktueller Statusrouter |
| `docs/archive/**` | ersetzte historische Inhalte | unverändert, kein Arbeitsrouter |

Ein unbekannter Pfad unter `docs/**` ist nicht automatisch „unterstützend“, sondern vor Integration ein Klassifikationsfehler.

## 7. Klassifikationsstatus

- **kanonisch:** aktuelle Source of Truth einer klaren Rolle;
- **unterstützend:** aktuelle Detailinformation mit expliziter Rolle;
- **Zielreferenz:** beschlossen oder fachlich validiert, aber keine IST-Behauptung;
- **historisch:** datierter Beleg oder früherer Zustand;
- **unklassifiziert:** nicht zulässig.

Ein historisches Dokument wird nicht allein durch ein aktuelles Änderungsdatum wieder kanonisch.

## 8. Änderungsmatrix

| Änderung | Pflichtdokumentation |
|---|---|
| rein interner Codefix ohne Vertragsänderung | PR-Auswirkung `keine`; bestehende Verträge unverändert |
| dauerhaftes technisches Verhalten | zuständiger Vertrag oder `ENGINEERING.md` |
| neue oder geänderte Produktmechanik | `Produktvertrag.md`; Zielquellen nur falls Richtung betroffen |
| noch nicht umgesetztes Ziel | Decision, Roadmap oder queued Workpack; nicht `Produktvertrag.md` |
| operative Statusänderung | `CURRENT_WORKPACK.md` ersetzen |
| neue Runtime-/Testevidence | `docs/evidence/**` und bei Relevanz kompakter `TEST_STATUS.md`-Eintrag |
| neue Architekturentscheidung | `docs/decisions/**` und bei Umsetzung später `SYSTEM_MAP.md` |
| belegte Ursachenanalyse | `docs/forensics/**` |
| historischer Abschluss | completed Workpack oder explizite historische Rolle |

## 9. Neue Dokumente

Eine neue Markdown-Datei ist nur zulässig, wenn vor dem Merge geklärt ist:

1. Welche einzige Hauptrolle besitzt sie?
2. Warum reicht kein bestehender Owner?
3. Welcher aktuelle Lesepfad führt zu ihr?
4. Wie wird sie aktualisiert oder abgeschlossen?
5. Ist sie kanonisch, unterstützend, Zielreferenz, historisch oder archiviert?
6. Erkennt die Vollinventur ihren Pfad ohne generischen Catch-all als gültig?

Neue Root-Markdown-Dateien benötigen einen expliziten Eintrag in der maschinellen Rollenklassifikation. Neue datierte Dateien gehören grundsätzlich unter `docs/decisions`, `docs/forensics`, `docs/evidence` oder `docs/workpacks`.

## 10. KI-Nutzung und automatische Prüfung

Die KI bestimmt zuerst Domäne und Rolle, liest nur aktuelle Router und Owner und öffnet Historie ausschließlich bei Beweisbedarf. Bei Rollenwiderspruch wird zuerst die Governance-Lücke behoben.

`python3 scripts/report-documentation-inventory.py --check` muss mit 0 Fehlern und 0 Warnungen grün sein. `Project Guardrails` lädt den Bericht read-only hoch und darf keine Dokumente schreiben, verschieben, committen oder pushen.
