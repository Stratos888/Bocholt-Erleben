# Dokumentationslandkarte

Stand: 2026-07-19

Diese Datei definiert Lesepfade, Rangfolge und Pflege der Projektdokumentation. Die vollständige Rollenklassifikation steht in `DOCUMENT_REGISTRY.md`. Diese Datei ist kein Projektstatus und kein Workpack.

## 1. Verbindliche Lesereihenfolge

Für jede neue Repo-Aufgabe:

1. `AI_ENTRYPOINT.md` – Arbeitsrouter und Sicherheitsgrenzen
2. `docs/workpacks/active/CURRENT_WORKPACK.md` – einziger operativer Projektstatus
3. `docs/architecture/SYSTEM_MAP.md` – stabile Systeme, Datenhoheit und Datenflüsse
4. Aufgabenroute aus Abschnitt 6 und `docs/DOCUMENT_REGISTRY.md`
5. fachliche Owner-Dateien und relevante Implementierung im aktuellen Ref
6. `ENGINEERING.md` – dauerhafte technische Guardrails
7. `docs/external-resource-matrix.md` – externe Ressourcen und Schreibgrenzen
8. Produkt-, Strategie-, Roadmap- und Evidence-Dokumente nur nach Bedarf

Alte Chats, Memory, ZIPs, PR-Beschreibungen, Freeze-Dateien und historische Dokumente sind Kontext. Sie dürfen die aktuelle Repo-Hierarchie nicht übersteuern.

## 2. Dokumenttypen und Rangfolge

| Typ | Kanonische Beispiele | Inhalt | Pflege |
|---|---|---|---|
| Arbeitsrouter | `AI_ENTRYPOINT.md` | Vorgehen, Mandat, Risiko, Evidence, Stop-the-line | inhaltlich ersetzen |
| Operativer Status | `CURRENT_WORKPACK.md` | exakt ein aktiver oder nächster erlaubter Schritt, Locks, Blocker | bei jedem Statuswechsel ersetzen |
| Dokumentregister | `DOCUMENT_REGISTRY.md` | Rollen, Pfade, Änderungsmatrix | bei Rollen- oder Strukturänderung |
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
| Vertrag/Playbook | z. B. `EVENT_DESCRIPTION_STANDARD.md`, `DEBUG.md` | aktueller Vertrag oder Vorgehen einer klaren Domäne | beim Domainwechsel |
| Referenz | historische oder ausführliche Detaildateien | unterstützender Kontext, kein Statusrouter | nur bei fachlichem Bedarf |
| Archiv | `docs/archive/**` oder klar historische Root-Datei | ersetzter Zustand oder Beleg | grundsätzlich unverändert |

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

Für `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md`, `SYSTEM_MAP.md`, `MASTER.md`, `ROADMAP.md`, `TEST_STATUS.md`, `docs/README.md` und `DOCUMENT_REGISTRY.md` gilt:

1. Keine fortlaufenden `BEGIN BLOCK`-/`END BLOCK`-Anhänge.
2. Keine immer neuen „aktueller Stand“-Kapitel über älteren „aktuellen“ Kapiteln.
3. Kein PR-, Branch- oder Incidentstatus in stabiler Architektur oder Strategie.
4. Keine Implementierungsdetails in `MASTER.md` oder `ROADMAP.md`.
5. Kein vollständiges Beweisarchiv in `TEST_STATUS.md`; dort stehen nur aktueller Proofstand und Links.
6. Veraltete Inhalte werden ersetzt. Git bewahrt die Vorgeschichte.
7. Ein Dokument hat genau eine Hauptrolle.
8. Links auf Zielzustände nennen ausdrücklich, ob sie umgesetzt sind.
9. Kanonische Dokumente verlinken nicht direkt auf Archive als Arbeitsanweisung.
10. Neue Root-Markdown-Dateien benötigen vor dem Merge eine registrierte Rolle.

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

## 6. Aufgabenbezogener Lesepfad

Die KI liest nicht pauschal alle Dokumente, sondern nur den kleinsten belastbaren Satz:

| Aufgabe | Pflichtpfad nach Einstieg und Systemkarte |
|---|---|
| Produktmechanik oder Funnel | `MASTER.md` -> `Produktvertrag.md` -> `COMMERCIAL_STRATEGY.md` -> `ROADMAP.md` -> Workpack/Owner |
| Control Center oder externe Writes | `ENGINEERING.md` -> `external-resource-matrix.md` -> relevante Control-Center-Verträge -> API-/UI-Owner |
| Content und KI-Suche | `EVENT_DESCRIPTION_STANDARD.md` -> Suchregelwerk/Quellenregister -> relevante Skripte -> Evidence |
| Visuals und Bildqualität | `VISUAL_WORKFLOW.md` -> Visual-Pool -> Generatoren/Audits -> konkrete Owner |
| Public UI oder Today | `ENGINEERING.md` -> betroffene CSS-/JS-/HTML-Owner -> `DEBUG.md` nur für UI-Proofs |
| Activities und saisonale Inhalte | fachliche Activity-Referenzen -> Datenowner -> Generatoren/Audits |
| SEO und Growth | aktuelle Forensik/Entscheidung -> queued Workpack -> Rendering-/Tracking-Owner |
| Deploy, CI oder GitHub Actions | `ENGINEERING.md` -> `github-actions-trigger-policy.md` -> betroffene Workflows und Tests |
| Dokumentationsänderung | `DOCUMENT_REGISTRY.md` -> zuständiger Owner -> Inventur -> Governance-Audit |

Historische Referenzen werden nur geöffnet, wenn eine aktuelle Datei sie ausdrücklich als Beleg oder Detailquelle nennt.

## 7. Änderungsvorgehen

Vor dem Patch:

1. Rolle der betroffenen Aussage bestimmen.
2. Bestehenden Owner und aktuellen Ref lesen.
3. Entscheiden, ob IST, Vertrag, ZIEL, Evidence oder Historie betroffen ist.
4. Dokumentationsauswirkung im Workpack und PR deklarieren.

Beim Patch:

1. Code und dauerhafte Dokumentation konsistent ändern.
2. Keine zukünftige Funktion als umgesetzt dokumentieren.
3. Statusdateien ersetzen statt ergänzen.
4. Evidence getrennt von Vertrag und Entscheidung halten.

Nach dem Patch:

1. `CURRENT_WORKPACK.md` auf den echten Folgezustand setzen.
2. Vollinventur und Governance-Audit ausführen.
3. Fehler beseitigen; Warnungen bewusst bewerten und im PR benennen.
4. Erst danach integrieren.

## 8. Dokumentations-Definition-of-Done

Ein Workpack ist dokumentarisch erst abgeschlossen, wenn:

- `CURRENT_WORKPACK.md` den echten Folgezustand zeigt;
- dauerhafte neue Regeln in der zuständigen kanonischen Datei stehen;
- Zielentscheidung und Runtimebeweis getrennt sind;
- `TEST_STATUS.md` nur bei tatsächlich neuer Evidence aktualisiert wurde;
- veraltete Statusaussagen entfernt statt ergänzt wurden;
- alle betroffenen Dokumentlinks funktionieren;
- jede Markdown-Datei eine bekannte Rolle besitzt;
- keine kanonische Datei ungeprüft auf ein Archiv routet;
- `python3 scripts/report-documentation-inventory.py --check` grün ist;
- `python3 scripts/audit-documentation-governance.py` grün ist.

## 9. Automatischer Schutz

`report-documentation-inventory.py` inventarisiert alle getrackten Markdown-Dateien, klassifiziert Rollen und prüft Links sowie Statusgrenzen. `audit-documentation-governance.py` schützt die kanonischen Steuerdateien und den CI-Vertrag. `Project Guardrails` führt beide Prüfungen read-only aus und veröffentlicht den maschinenlesbaren Inventurbericht als Artefakt.

Die Prüfungen ersetzen keine fachliche Bewertung. Sie verhindern, dass die Dokumentation erneut zu einem widersprüchlichen Statusarchiv oder zu einer ungerouteten Dateisammlung anwächst.
