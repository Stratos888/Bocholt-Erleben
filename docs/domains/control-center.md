# Domain-Router: Control Center und interne Steuerzentrale

Stand: 2026-07-19

Rolle: aktueller Einstieg für Aufgaben an Control Center, Inbox-Review, interner Steuerzentrale, Source-Reconciliation und externen Writeback-Pfaden. Diese Datei enthält keinen Workpackstatus. Operativer Status, Locks und der nächste erlaubte Schritt stehen ausschließlich in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Ziel

Das Control Center soll redaktionelle und technische Prüffälle bis zu einem verifizierten Endzustand führen. Anzeige, Entscheidung, Persistenz, Folgeprozess und Rücklesen müssen zusammenpassen. Eine sichtbare Meldung ohne ausführbare und nachweisbare Folgeaktion ist unvollständig.

## 2. Autoritätsreihenfolge

1. `docs/workpacks/active/CURRENT_WORKPACK.md` – aktiver Status, Locks und nächster Schritt.
2. `docs/architecture/SYSTEM_MAP.md` – Systeme, Datenhoheit und Datenflüsse.
3. `docs/external-resource-matrix.md` – reale Ressourcen, Umgebungen und Schreibgrenzen.
4. aktuelle Control-Center-Verträge und die konkrete Implementierung im aktuellen Ref.
5. Evidence und Forensik nur für den belegten Einzelfall.
6. historische Handoffs, alte Workpacks, Freeze- und Simulationsdateien nur als Historie.

## 3. Aktuelle Owner

| Ebene | Owner | Rolle |
|---|---|---|
| Arbeits- und Risikovertrag | `AI_ENTRYPOINT.md` | Mandat, R2/R3, E3/E4, Stop-the-line |
| Architektur und Datenhoheit | `docs/architecture/SYSTEM_MAP.md` | autoritativer System- und Ressourcenfluss |
| externe Ressourcen | `docs/external-resource-matrix.md` | Staging-/Live-Ziele und Mutationsgrenzen |
| technische Workflowpolicy | `docs/github-actions-trigger-policy.md` | Trigger-, Branch- und Operatorvertrag |
| redaktionelle Backendverträge | `api/control-center/_editorial_contracts.php` | Felder, Entscheidungen und Validierung |
| Darstellung | `api/control-center/_presentation.php`, `js/control-center/review-render.js` | Projektion und Review-UI |
| Aktionen | `js/control-center/review-actions.js` | Nutzeraktionen und Aufrufpfade |
| Quellenabgleich | `api/control-center/_source_reconciliation.php` | Abgleich lokaler und führender Zustände |
| verifizierter Writeback | `api/control-center/_verified_source_writeback.php` | begrenzte Mutation und Rücklesen |
| Workflowkonsolidierung | `docs/workpacks/queued/CONTROL-CENTER-WORKFLOW-CONSOLIDATION.md` | nächster technischer Zielscope, noch nicht aktiv |

## 4. Aktuelle fachliche Referenzen

Nur aufgabenbezogen lesen:

- `STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md` – Integrationsvertrag.
- `STEUERZENTRALE_INFORMATIONARCHITEKTUR.md` – Informationsarchitektur.
- `STEUERZENTRALE_SCREENVERTRAG.md` – Screen- und Interaktionsvertrag.
- `STEUERZENTRALE_VORGANGSKATALOG.md` – Vorgänge und Falltypen.
- `STEUERZENTRALE_ABNAHMEMATRIX.md` – fachliche Abnahmedimensionen.
- `docs/steuerzentrale-backlog-roadmap-vertrag.md` – Backlog-/Roadmap-Vertrag.
- `docs/steuerzentrale-redaktioneller-entscheidungs-und-lernprozess.md` – redaktioneller Entscheidungs- und Lernvertrag.
- `docs/internal-dashboard.md` und `docs/internal-dashboard-target.md` – Zielreferenzen für die interne Betreiberoberfläche, keine Implementierungsbehauptung.

## 5. Historische Dateien

Die folgenden Dokumenttypen dürfen keinen aktuellen Arbeitsauftrag bilden:

- Freeze-, Implementierungsstatus- und Simulationsdateien;
- datierte E2E-/Implementierungsworkpacks;
- alte „nächster Chat“- oder „nächstes Workpack“-Handoffs;
- alte Deploy-, Redeploy- und Release-Notizen;
- frühere konkrete CityArt- oder Einzelfallbeschreibungen.

Sie werden nur geöffnet, wenn ein aktueller Owner sie ausdrücklich als Beleg benötigt. Alte SHAs, PR-Nummern und damalige nächste Schritte übersteuern niemals `CURRENT_WORKPACK.md`.

## 6. Aufgabenbezogener Lesepfad

| Aufgabe | Zusätzlich lesen |
|---|---|
| GitHub-Actions- oder Operatorproblem | queued Workflow-Konsolidierungsworkpack, Triggerpolicy, alle betroffenen Workflows und Ruleset-Abhängigkeiten |
| externer Write oder E4 | Ressourcenmatrix, Current Workpack, Writer-/Reconciliation-Owner, E4-Harness und konkrete Staging-Ressourcen |
| Review-UI oder Entscheidungsaktion | Informationsarchitektur, Screenvertrag, Vorgangskatalog, Render-/Action-Owner und Browser-Smoke |
| falscher Zustand oder verschwundener Fall | Source-Reconciliation, führende Ressource, lokale Projektion, API-Antwort und Rücklesebeleg |
| neuer Falltyp oder neue Entscheidung | Vorgangskatalog, redaktioneller Vertrag, Backendverträge und vollständiger End-to-End-Folgeprozess |
| internes Dashboard | Dashboard-Zielreferenzen, bestehende Datenquellen und Betreiber-Jobs; keine parallele zweite Writeback-Logik |

## 7. Harte Regeln

- Genau ein schreibender Control-Center-Workpack.
- Keine E4-Ausführung, kein CityArt-Fachfall und keine Mutation, solange `CURRENT_WORKPACK.md` dies sperrt.
- Staging und Live verwenden ausschließlich ihre dokumentierten Ressourcen.
- UI-Erfolg beweist keinen Writeback; ein Write beweist keinen korrekten Endzustand.
- Ein wiederverwendbarer R3-Pfad benötigt vor dem echten Fachfall einen isolierten synthetischen E4-Beweis mit Rücklesen und Cleanup.
- Beim ersten unerwarteten realen Verhalten wird nicht erneut geschrieben.
- Keine zweite fachliche Resolver-, Writer- oder Observer-Schicht ohne belegte Notwendigkeit.

## 8. Evidence

Mindestens zu trennen sind:

- E1: Owner, Verträge und Diff sind konsistent.
- E2: Tests, Replay, Trigger- und Contract-Gates sind grün.
- E3: deployter Host, Build, Umgebung, Ressourcen und read-only Operationsplan sind belegt.
- E4: genau ein isolierter synthetischer Staging-Write wurde vollständig zurückgelesen und bereinigt.
- E5: ein echter fachlicher Staging-Fall wurde erst nach grünem E4 ausgeführt.

## 9. Dokumentationspflege

Dauerhafte Regeln gehören in diesen Router, die Ressourcenmatrix, die Workflowpolicy oder den konkreten fachlichen Owner. Laufstände gehören ausschließlich in `CURRENT_WORKPACK.md`; konkrete Belege in Evidence oder Forensik; abgeschlossene Umsetzungspakete in `docs/workpacks/completed/`.
