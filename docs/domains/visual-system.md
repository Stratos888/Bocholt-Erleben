# Domain-Router: Visual-System

Stand: 2026-07-19

Rolle: aktueller Einstieg für alle Aufgaben zu Event- und Activity-Visuals. Diese Datei enthält keinen Workpackstatus. Operativer Status und Locks stehen ausschließlich in `docs/workpacks/active/CURRENT_WORKPACK.md`.

## 1. Ziel

Das Visual-System soll fachlich passende, rechtlich und technisch nutzbare Bilder deterministisch auswählen und falsche Motive, Duplikate sowie unkontrollierte Fallbacks verhindern.

## 2. Quellen und Owner

| Ebene | Owner | Rolle |
|---|---|---|
| Visualvertrag und historische Detailregeln | `VISUAL_WORKFLOW.md` | ausführliche Referenz, nicht als Projektstatus lesen |
| strukturierter Pool | `data/event_visual_pool.json` | kanonische Motiv-, Asset- und Freigabedaten |
| Generatoren und Audits | `scripts/**` mit Visual-Bezug | Zuordnung, Gap-Erkennung, Prüfung und Backlogs |
| Assets | Bildpfade im Repo beziehungsweise freigegebene externe Quellen | tatsächliche Darstellung |
| Rendering | betroffene JS-/HTML-/CSS-Owner | Darstellung eines bereits fachlich gewählten Assets |
| technische Guardrails | `ENGINEERING.md` | dauerhafte Visual- und Owner-Regeln |

CSS darf Darstellung steuern, aber keine fachlich falsche Bildzuordnung dauerhaft kaschieren.

## 3. Autoritativer Ablauf

```text
Event oder Activity
-> fachliche Motivklassifikation
-> visual_key und visual_motif
-> freigegebener Pool-Eintrag
-> Asset- und Duplikatprüfung
-> ready / erlaubter fallback / blockiert
-> Rendering
-> Audit und Gap-Backlog
```

Eine UI-Datei darf keinen zweiten fachlichen Visual-Resolver neben dem Pool- und Auditvertrag aufbauen.

## 4. Harte Regeln

- Prominente Flächen verwenden nur freigegebene `ready`-Assets oder ausdrücklich erlaubte Fallbacks.
- Ein generisches Motiv ist nicht automatisch fachlich passend.
- Falsche Motive und Duplikate werden upstream im Pool, Motivvertrag oder Audit gelöst.
- Ein Asset wird nicht allein durch technische Erreichbarkeit als geeignet bewertet.
- Neue Motive benötigen eindeutigen Key, Motivbeschreibung, erlaubte Verwendung und Qualitätsstatus.
- Unklare Fälle werden blockiert oder als Gap erfasst; sie werden nicht still durch irgendein verfügbares Bild ersetzt.
- Ein Live-Hotfix darf ein nachweislich falsches Bild unterdrücken, aber kein ungeprüftes Ersatzbild als `ready` deklarieren.

## 5. Aufgabenbezogener Lesepfad

| Aufgabe | Zusätzlich lesen |
|---|---|
| falsches Motiv oder Duplikat | Pool-Eintrag, konkrete Generator-/Auditlogik, betroffene Datenzeile |
| neues Motiv | relevante Abschnitte in `VISUAL_WORKFLOW.md`, Pool-Schema, bestehende ähnliche Motive |
| Rendering-/Layoutfehler | betroffene JS-/CSS-/HTML-Owner und bei Bedarf `DEBUG.md`; fachliche Zuordnung bleibt unverändert |
| fehlendes Asset | Gap-Backlog, Sourcing-/Prompt-Referenzen und Freigaberegeln |
| Prozessänderung | `ENGINEERING.md`, vollständiger Visual-Datenfluss und alle konkurrierenden Resolver |

`VISUAL_WORKFLOW.md` ist wegen seines Umfangs nur abschnittsweise und aufgabenbezogen zu lesen.

## 6. Evidence

Mindestens zu unterscheiden:

- Daten-/Pool-Evidence: Key, Motiv, Status und Asset sind konsistent.
- statische Evidence: Generatoren und Audits akzeptieren den Zielzustand.
- Browser-Evidence: das richtige Bild erscheint an den relevanten Flächen.
- fachliche Abnahme: Motiv passt tatsächlich zum Inhalt.

Ein grüner Browser-Smoke allein beweist keine fachliche Bildqualität.

## 7. Dokumentationspflege

Dauerhafte Regeln werden hier oder in `ENGINEERING.md` konsolidiert. Ausführliche Motiv- und Prozessdetails bleiben in `VISUAL_WORKFLOW.md` beziehungsweise den spezialisierten Referenzdateien. Ein Workpackstatus oder einzelner Bildfall wird nicht in diesen Router geschrieben.
