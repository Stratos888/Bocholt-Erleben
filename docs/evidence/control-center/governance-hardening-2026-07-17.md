# Ursachenanalyse und Vorschlags-Evidence – Steuerzentralen-Arbeitsweise

Stand: 2026-07-17  
Status: Ursachenbefund mit **nicht validierten Maßnahmenvorschlägen**

## Untersuchter Vorfall

Die ausnahmebasierte Eventprüfung wurde mit PR #78 nach `staging` gebracht und anschließend mit realen Staging-Daten getestet. Während der Abnahme wurden Daten verändert. Dabei wurde entdeckt, dass die Staging-API noch auf den Live-Tab `Inbox` zeigte. PR #79 korrigierte die Umgebungstrennung. Danach zeigte der reale CityArt-Fall weiterhin inkonsistente Zeit- und Visualfelder.

## Belegte beziehungsweise stark belegte Root Causes

### 1. Dokumentierter Prozess und technische Deploy-Realität widersprachen sich

Der Workpack verlangte reale Staging-Abnahme vor dem Merge nach `staging`. Der vorhandene Deploy-Prozess stellt jedoch nur den Branch `staging` auf der Staging-Website bereit. Dadurch war der geforderte Ablauf nicht reproduzierbar und wurde faktisch zu:

```text
CI → Merge nach staging → reale Daten als erster Integrationstest
```

### 2. CI prüfte überwiegend Verträge und Fixtures, nicht die reale Zustandskette

Die Tests bestätigten statische Marker, synthetische Payloads und einzelne Runtime-Funktionen. Sie konnten nicht vollständig nachweisen, welcher Google-Sheets-Tab in der tatsächlich deployten Umgebung gelesen und beschrieben wurde oder ob die reale Quellzeile mit dem sichtbaren lokalen Fall identisch war.

### 3. Keine verpflichtende gemeinsame Datenidentitäts- und Environment-Evidence

Vor dem ersten Schreibtest fehlte ein gemeinsamer Nachweis für:

- Zielumgebung;
- Zieltab;
- stabile Quellzeile;
- lokale Fallidentität;
- Vorherzustand;
- erwartete Mutation;
- Rollback.

Dadurch konnte eine erfolgreiche UI-Aktion nicht eindeutig einem Staging-Datensatz zugeordnet werden.

### 4. Reale Datenkorrektur und Systemdiagnose wurden vermischt

Die fehlende CityArt-Uhrzeit wurde manuell ergänzt, bevor die komplette Entstehungskette aus Discovery, Mapping, Inbox, Staging-Inbox und lokaler Fallkopie belegt war. Die Korrektur beseitigte damit möglicherweise ein Symptom, lieferte aber keinen Root-Cause-Nachweis.

### 5. Nach kritischem Befund wurde nicht konsequent gestoppt

Nach der Entdeckung eines möglichen Live-Writes wurde weiter an Zeitfeld, Feedbackprozess, Fixbranch und Abnahme gearbeitet. Ein konsequenter Wechsel zu reiner Forensik erfolgte zu spät.

### 6. Merge-Gates waren nicht technisch an ausreichende Evidence gebunden

PR #78 und #79 konnten ohne unabhängiges Review, ohne per-PR Change-Manifest und ohne isolierte vollständige E2E-Evidence gemergt werden. Grüne CI wurde dadurch zu stark als Freigabesignal interpretiert.

## Nur vorgeschlagene, noch zu validierende Kontrollen

Die folgenden Punkte sind keine beschlossene Lösung:

1. klar getrennte Phasen für Ursachenanalyse, isolierte Integration, Staging-Deploy, reale Staging-Abnahme und Main;
2. maschinenlesbares Change-Manifest pro Steuerzentralen-PR;
3. CI-Validator, der deklarierten Scope und Evidence prüft;
4. Environment- und Datenidentitätsnachweis für Quell-/Writeback-Änderungen;
5. Vorher-/Nachher-Snapshot und Rollback für kontrollierte Staging-Schreibproben;
6. Verbot von Live-Schreibtests;
7. PR-Template und gegebenenfalls CODEOWNERS;
8. Stop-the-line bei Environment-, Identitäts-, Persistenz- oder unerwarteten Datenmutationsfehlern;
9. Branch-Protection mit einem Governance-Statuscheck.

Diese Vorschläge müssen ein Folgechat gegen Aufwand, Wirksamkeit, Teamstruktur, Deploymodell und parallele Chat-/Branch-Arbeit prüfen. Sie können vereinfacht, geändert oder verworfen werden.

## Prototypischer Stand in PR #80

PR #80 enthält einen technischen Governance-Prototyp. Dass seine Workflows grün laufen, belegt nur die interne Konsistenz des Prototyps. Es belegt nicht, dass der vorgeschlagene Prozess der beste oder angemessene Zielzustand ist.

Insbesondere darf aktuell keine Branch-Protection auf Basis des Prototyps eingerichtet und PR #80 nicht gemergt werden.

## Führende Dokumente für den Folgechat

Fakten- und Übergabestand:

`docs/handoffs/steuerzentrale-analyse-validierung-uebergabe-2026-07-17.md`

Nicht validierter Vorschlag:

`docs/proposals/steuerzentrale-arbeitsweise-governance-vorschlag-2026-07-17.md`

## Abgrenzung

Dieser Dokumentationsstand verändert keine Steuerzentralen-Runtime, keine Google-Sheets-Daten und keine Deploy-Konfiguration. Die funktionale und forensische CityArt-Arbeit bleibt gestoppt, bis der neue Chat den Vorschlag analysiert und validiert hat.
