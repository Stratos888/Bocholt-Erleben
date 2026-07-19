# Dokumentregister

Stand: 2026-07-19

Dieses Register definiert die zulässigen Rollen und Ablageorte der Projektdokumentation. Es ist die menschlich lesbare Entsprechung der Klassifikation in `scripts/report-documentation-inventory.py`.

## 1. Verbindliche Grundregel

Jede Markdown-Datei hat genau eine Hauptrolle. Der Dateipfad, nicht der Chat oder die Entstehungsgeschichte, entscheidet darüber, wie eine KI sie verwenden darf.

```text
aktuell kanonisch
> aktueller Workpack oder Evidence
> aktuelle Domainverträge und Playbooks
> unterstützende Referenz
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

## 3. Aktuelle Root-Verträge und Playbooks

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
| Steuerzentrale | `STEUERZENTRALE_*` | Vertrag, Zielreferenz oder historischer Beleg gemäß Dateiname |

Diese Dateien dürfen erst nach einer erfolgreichen Vollinventur und einem atomaren Referenzpatch verschoben werden. Eine bloße Aufräumabsicht rechtfertigt keine Massenmigration.

## 4. Pfadbasierte Rollen

| Pfad | Rolle | Mutabilität |
|---|---|---|
| `docs/README.md` | Dokumentations-Governance | aktuell, ersetzend |
| `docs/DOCUMENT_REGISTRY.md` | Rollenregister | aktuell, ersetzend |
| `docs/architecture/**` | stabile Architektur oder Architekturreferenz | selten |
| `docs/contracts/**` | aktuelle Domain- oder Runtimeverträge | bei Vertragsänderung |
| `docs/playbooks/**` | operative Vorgehensweisen | bei Prozessänderung |
| `docs/workpacks/active/CURRENT_WORKPACK.md` | einziger operativer Status | bei jedem Statuswechsel |
| `docs/workpacks/active/**` | unterstützender aktiver Scope | nur wenn im Current Workpack geroutet |
| `docs/workpacks/queued/**` | noch nicht aktive Umsetzungspakete | vor Aktivierung neu validieren |
| `docs/workpacks/completed/**` | abgeschlossene Umsetzungspakete | nach Abschluss unverändert |
| `docs/decisions/**` | angenommene Zielentscheidungen | datiert, danach grundsätzlich unverändert |
| `docs/forensics/**` | belegte Ursachenanalysen | datiert, danach grundsätzlich unverändert |
| `docs/evidence/**` | konkrete Belege | append durch neue separate Dateien, nicht durch Statusmischung |
| `docs/reference/**` | unterstützende Detailreferenz | kein aktueller Statusrouter |
| `docs/archive/**` | ersetzte historische Inhalte | unverändert, kein Arbeitsrouter |

Sonstige Dateien unter `docs/**` gelten als unterstützende Dokumentation. Ihr Lesepfad muss von einer aktuellen kanonischen Datei ausgehen.

## 5. Klassifikationsstatus

Die maschinelle Inventur verwendet vier Qualitätszustände:

- **kanonisch:** aktuelle Source of Truth einer klaren Rolle;
- **unterstützend:** aktuelle Detailinformation, aber kein globaler Router;
- **historisch:** datierter Beleg oder früherer Zustand;
- **unklassifiziert:** nicht zulässig; Rolle oder Ablage muss vor Integration geklärt werden.

Ein historisches Dokument wird nicht allein durch ein aktuelles Änderungsdatum wieder kanonisch.

## 6. Änderungsmatrix

| Änderung | Pflichtdokumentation |
|---|---|
| rein interner Codefix ohne Vertragsänderung | PR-Dokumentationsauswirkung `keine`; bestehende Verträge unverändert |
| dauerhaftes technisches Verhalten | zuständiger Vertrag oder `ENGINEERING.md` |
| neue oder geänderte Produktmechanik | `Produktvertrag.md`; Zielquellen nur falls Richtung betroffen |
| noch nicht umgesetztes Ziel | Decision, Roadmap oder queued Workpack; nicht `Produktvertrag.md` |
| operative Statusänderung | `CURRENT_WORKPACK.md` ersetzen |
| neue Runtime-/Testevidence | `docs/evidence/**` und bei Relevanz kompakter `TEST_STATUS.md`-Eintrag |
| neue Architekturentscheidung | `docs/decisions/**` und bei Umsetzung später `SYSTEM_MAP.md` |
| belegte Ursachenanalyse | `docs/forensics/**` |
| historischer Abschluss | completed Workpack oder Archiv; nicht in stabile Verträge kopieren |

## 7. Neue Dokumente

Eine neue Markdown-Datei ist nur zulässig, wenn vor dem Merge geklärt ist:

1. Welche einzige Hauptrolle besitzt sie?
2. Warum reicht kein bestehender Owner?
3. Welcher aktuelle Lesepfad führt zu ihr?
4. Wie wird sie aktualisiert oder abgeschlossen?
5. Ist sie kanonisch, unterstützend, historisch oder archiviert?
6. Erkennt die Vollinventur ihren Pfad ohne Sonderfall als gültig?

Neue Root-Markdown-Dateien benötigen zusätzlich einen expliziten Eintrag in der maschinellen Rollenklassifikation. Neue datierte Dateien gehören grundsätzlich unter `docs/decisions`, `docs/forensics`, `docs/evidence` oder `docs/workpacks`.

## 8. KI-Nutzung

Die KI verwendet dieses Register, um die Lesemenge zu begrenzen:

1. Aufgabe und betroffene Domäne bestimmen.
2. Nur aktuelle kanonische Router lesen.
3. Genau die benötigten Verträge und Owner öffnen.
4. Referenzen oder Archive nur bei ausdrücklicher Verlinkung oder Beweisbedarf lesen.
5. Bei Rollenwiderspruch stoppen und zuerst die Governance-Lücke beheben.

## 9. Automatische Prüfung

`python3 scripts/report-documentation-inventory.py --check` muss vor Abschluss grün sein. Der Bericht enthält für jede getrackte Markdown-Datei Pfad, Rolle, Größe, Statusmarker, dynamische PR-Verweise, Linkfehler und Archivverweise.

`Project Guardrails` lädt `build/documentation-inventory.json` als read-only Artefakt hoch. Der Workflow darf keine Dokumente schreiben, verschieben, committen oder pushen.
