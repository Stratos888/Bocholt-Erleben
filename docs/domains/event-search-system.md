# Domain-Router: Eventsuche und Contentprüfung

Stand: 2026-07-19

Rolle: aktueller Einstieg für Eventsuche, Quellenprüfung, Beschreibungsqualität und KI-gestützte Intake-/Audit-Prozesse. Diese Datei enthält keinen Workpackstatus.

## 1. Ziel

Das System soll relevante lokale Veranstaltungen aus belastbaren Quellen finden, strukturiert übernehmen, redaktionell hochwertig beschreiben und unsichere Fälle kontrolliert zur Prüfung geben, ohne fachliche Quellen blind durch KI-Ausgaben zu ersetzen.

## 2. Quellen und Owner

| Ebene | Owner | Rolle |
|---|---|---|
| Beschreibungsstandard | `EVENT_DESCRIPTION_STANDARD.md` | aktueller Qualitätsvertrag für Eventtexte |
| ausführliches Suchregelwerk | `bocholt-erleben_eventsuche_regelwerk_v3.md` | große Detail- und Legacy-Referenz, nicht als Projektstatus lesen |
| Quellenpriorität | `eventsuche_quellenregister_v1.md` | operatives Quellenregister |
| Such-, Intake- und Auditlogik | relevante Dateien unter `.github/workflows/**`, `scripts/**` und API-/Control-Center-Owner | tatsächliche Implementierung |
| Eventdaten | `Events`, `Events_Staging`, DB-Submissions | fachliche Quellen gemäß `SYSTEM_MAP.md` |
| Evidence | Reports, `docs/evidence/**`, `TEST_STATUS.md` | Beleg, keine Source of Truth für Inhalte |

## 3. Autoritativer Ablauf

```text
Quellenkandidat
-> Quellenidentität und Aktualität prüfen
-> Eventidentität und Duplikate bestimmen
-> strukturierte Felder extrahieren
-> Qualitäts- und Vertragsprüfung
-> sicherer Fall oder typisierter Reviewfall
-> autoritative Zielressource
-> Rücklesen und öffentliche Projektion
-> Audit- und Lernsignal
```

KI-Ausgaben sind Vorschläge oder Verifikation. Sie dürfen fachliche Quellen nicht blind überschreiben.

## 4. Harte Regeln

- Stabile Eventidentität und Quellenbezug gehen vor Textähnlichkeit.
- Mehrere Termine einer Serie dürfen dieselbe Quellen-URL besitzen, benötigen aber eindeutige IDs.
- Unsichere Daten werden typisiert zur Prüfung gegeben statt still ergänzt.
- Eventbeschreibungen müssen konkret, lokal, verständlich und quellenbelegt sein.
- Keine erfundenen Programmpunkte, Zielgruppenversprechen, Preise, Zeiten oder Barrierefreiheitsangaben.
- Veraltete oder sekundäre Quellen dürfen eine aktuelle Primärquelle nicht übersteuern.
- Feedback erzeugt begrenzte, überprüfbare Regeln; Rohfeedback mutiert keine Prompts oder Regelbücher automatisch.
- Staging schreibt ausschließlich in die dafür vorgesehenen Staging-Ressourcen.

## 5. Aufgabenbezogener Lesepfad

| Aufgabe | Zusätzlich lesen |
|---|---|
| schlechte Eventbeschreibung | `EVENT_DESCRIPTION_STANDARD.md`, konkrete Quelldaten und betroffene Generatorlogik |
| fehlender oder falscher Event | Quellenregister, Suchregelwerk abschnittsweise, Intake-/Duplikatlogik |
| KI-Suche oder Weekly-Lauf | Triggerpolicy, konkrete Workflowdatei, Reports und aktuelle Evidence |
| Review-/Inbox-Fall | Control-Center-Verträge, Source-/Writeback-Owner und Ressourcenmatrix |
| neue Quelle | Quellenregister, Identitäts-/Duplikatvertrag und Fetch-/Parser-Owner |
| Lern- oder Feedbackprozess | Auditdaten, typisierte Feedbackregeln und Ablauf-/Verfallsgrenzen |

Das große Suchregelwerk wird nur in den für die konkrete Aufgabe relevanten Abschnitten gelesen.

## 6. Evidence

Zu trennen sind:

- Quellen-Evidence: URL, Aktualität, Primär-/Sekundärstatus und konkrete Fakten.
- Daten-Evidence: Event-ID, strukturierte Felder und Duplikatentscheidung.
- Qualitäts-Evidence: Beschreibung erfüllt den Vertrag ohne Erfindungen.
- Runtime-Evidence: Workflow, Zielressource und Rücklesen sind korrekt.
- öffentliche Evidence: Feed und Detailseite zeigen den freigegebenen Zustand.

Ein grüner Syntax- oder Auditlauf beweist nicht automatisch Quellenrichtigkeit oder fachliche Textqualität.

## 7. Dokumentationspflege

Dauerhafte Systemregeln werden in diesem Router, dem Beschreibungsvertrag oder dem zuständigen technischen Owner konsolidiert. Quellenlisten bleiben im Quellenregister. Ausführliche historische Suchregeln bleiben im großen Regelwerk. Laufstände und einzelne Fehlerfälle gehören in Workpack, Forensik oder Evidence.
