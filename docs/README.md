# Dokumentationslandkarte

Stand: 2026-07-18

Diese Datei definiert Rollen, Rangfolge und Pflege der Projektdokumentation. Sie ist kein Projektstatus und kein Workpack.

## 1. Verbindliche Lesereihenfolge

Für jede neue Repo-Aufgabe:

1. `AI_ENTRYPOINT.md` – Arbeitsrouter und Sicherheitsgrenzen
2. `docs/workpacks/active/CURRENT_WORKPACK.md` – einziger operativer Projektstatus
3. `docs/architecture/SYSTEM_MAP.md` – stabile Systeme, Datenhoheit und Datenflüsse
4. fachliche Owner-Dateien und relevante Implementierung
5. `ENGINEERING.md` – dauerhafte technische Guardrails
6. `docs/external-resource-matrix.md` – externe Ressourcen und Schreibgrenzen
7. Produkt-, Strategie-, Roadmap- und Evidence-Dokumente nach Bedarf

Alte Chats, Memory, ZIPs, PR-Beschreibungen und historische Dokumente sind Kontext. Sie dürfen die aktuelle Repo-Hierarchie nicht übersteuern.

## 2. Dokumenttypen und Rangfolge

| Typ | Kanonische Beispiele | Inhalt | Pflege |
|---|---|---|---|
| Arbeitsrouter | `AI_ENTRYPOINT.md` | Vorgehen, Mandat, Risiko, Evidence, Stop-the-line | inhaltlich ersetzen |
| Operativer Status | `CURRENT_WORKPACK.md` | exakt ein aktiver oder nächster erlaubter Schritt, Locks, Blocker | bei jedem Statuswechsel ersetzen |
| Architektur | `SYSTEM_MAP.md` | stabile Komponenten, Datenflüsse, Owner | nur bei Architekturänderung |
| Technische Guardrails | `ENGINEERING.md` | dauerhafte Entwicklungs- und Runtime-Regeln | nur bei neuer dauerhafter Regel |
| Ressourcenvertrag | `external-resource-matrix.md` | Staging-/Live-Ressourcen, Zugriff, Locks | bei Ressourcen- oder Schreibvertragsänderung |
| Produktvertrag | `Produktvertrag.md` | bereits gültige Produktmechanik | erst mit tatsächlicher Produktänderung |
| Produkt-Nordstern | `MASTER.md` | stabiles Zielbild und Entscheidungsfilter | selten |
| Strategie | `COMMERCIAL_STRATEGY.md` | kommerzielle Begründung und Richtung | bei Strategieentscheidung |
| Roadmap | `ROADMAP.md` | priorisierte Produktziele, keine Laufzeitdetails | bei Prioritätsentscheidung |
| Workpack | `docs/workpacks/**` | Scope, Gates, Evidence, DoD einer Umsetzung | aktivieren, abschließen oder archivieren |
| Entscheidung | `docs/decisions/**` | dauerhaft angenommene Architektur-/Produktentscheidung | datiert und danach grundsätzlich unverändert |
| Forensik | `docs/forensics/**` | belegte Untersuchung mit Restunsicherheiten | datiert und danach grundsätzlich unverändert |
| Evidence | `TEST_STATUS.md`, `docs/evidence/**` | Proofindex und ausführliche Nachweise | nach neuer Evidence |
| Domainvertrag/Playbook | z. B. `VISUAL_WORKFLOW.md`, `EVENT_DESCRIPTION_STANDARD.md`, `DEBUG.md` | Regeln eines klar begrenzten Fachbereichs | beim Domainwechsel |

Bei Widerspruch gilt die speziellere aktuelle Source of Truth innerhalb ihrer Rolle. Ein Zielzustandsdokument darf keine bereits umgesetzte Produktmechanik vortäuschen.

## 3. Status, Zielzustand und Historie strikt trennen

Jede Aussage gehört genau in eine Kategorie:

- **IST:** aktuell im Repo, in der Runtime oder extern belegt.
- **ZIEL:** beschlossen, aber noch nicht vollständig umgesetzt.
- **HYPOTHESE:** plausibel, aber nicht bewiesen.
- **HISTORIE:** früherer Zustand oder abgeschlossener Beleg.

`CURRENT_WORKPACK.md` enthält IST, Blocker und genau den nächsten erlaubten Schritt.  
`docs/decisions/` enthält ZIELentscheidungen.  
`docs/forensics/` enthält belegte Ursachenanalysen.  
Git und Evidence-Dateien tragen die Historie.

## 4. Regeln für kanonische Steuerdateien

Für `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md`, `SYSTEM_MAP.md`, `MASTER.md`, `ROADMAP.md` und `TEST_STATUS.md` gilt:

1. Keine fortlaufenden `BEGIN BLOCK`-/`END BLOCK`-Anhänge.
2. Keine immer neuen „aktueller Stand“-Kapitel über älteren „aktuellen“ Kapiteln.
3. Kein PR-, Branch- oder Incidentstatus in stabiler Architektur oder Strategie.
4. Keine Implementierungsdetails in `MASTER.md` oder `ROADMAP.md`.
5. Kein vollständiges Beweisarchiv in `TEST_STATUS.md`; dort stehen nur aktueller Proofstand und Links.
6. Veraltete Inhalte werden ersetzt. Git bewahrt die Vorgeschichte.
7. Ein Dokument hat genau eine Hauptrolle.
8. Links auf Zielzustände nennen ausdrücklich, ob sie umgesetzt sind.

## 5. Workpack-Lebenszyklus

```text
queued
-> active
-> completed oder cancelled
```

- `docs/workpacks/queued/INDEX.md` ordnet die technische Warteschlange.
- `docs/workpacks/active/CURRENT_WORKPACK.md` ist der einzige operative Status.
- Standardmäßig gibt es genau einen schreibenden Workpack.
- Ein pausierter Workpack bleibt mit Blocker und Abhängigkeit sichtbar, ist aber nicht parallel aktiv.
- Beim Abschluss werden Ergebnis, erreichte Evidence, Restlücken und nächster zulässiger Schritt dokumentiert.
- Offene PRs werden bei Taskbeginn aus GitHub gelesen und nicht als dauerhafte Liste in Steuerdateien kopiert.

## 6. Fachliche Lesepfade

### Produkt oder Funnel

`MASTER.md` -> `Produktvertrag.md` -> `COMMERCIAL_STRATEGY.md` -> `ROADMAP.md` -> fachlicher Zielzustand/Workpack

### Control Center und externe Writes

`CURRENT_WORKPACK.md` -> `SYSTEM_MAP.md` -> `ENGINEERING.md` -> `external-resource-matrix.md` -> betroffene API-/UI-Owner

### Content und KI-Suche

`EVENT_DESCRIPTION_STANDARD.md` -> `bocholt-erleben_eventsuche_regelwerk_v3.md` -> relevante Such-/Audit-Skripte -> Evidence

### Visuals

`VISUAL_WORKFLOW.md` -> `data/event_visual_pool.json` -> Visual-Generatoren/Audits -> konkrete Owner

### UI-Debugging

`DEBUG.md` ist ein begrenztes UI-/Layout-Proof-Kit. Es ist keine globale Projektstatus- oder Architekturquelle.

## 7. Dokumentations-Definition-of-Done

Ein Workpack ist dokumentarisch erst abgeschlossen, wenn:

- `CURRENT_WORKPACK.md` den echten Folgezustand zeigt;
- dauerhafte neue Regeln in der zuständigen kanonischen Datei stehen;
- Zielentscheidung und Runtimebeweis getrennt sind;
- `TEST_STATUS.md` nur bei tatsächlich neuer Evidence aktualisiert wurde;
- veraltete Statusaussagen entfernt statt ergänzt wurden;
- alle betroffenen Dokumentlinks funktionieren;
- `python3 scripts/audit-documentation-governance.py` grün ist.

## 8. Automatischer Schutz

`scripts/audit-documentation-governance.py` prüft Struktur, Größenbudget und zentrale Widerspruchsgrenzen. `Project Guardrails` führt diesen Audit für Änderungen an den Steuerdateien aus.

Der Audit ersetzt keine fachliche Prüfung. Er verhindert, dass die Dokumentation erneut zu einem langen, widersprüchlichen Statusarchiv anwächst.
