# Evidence – Steuerzentralen-Governance-Härtung 2026-07-17

## Untersuchter Vorfall

Die ausnahmebasierte Eventprüfung wurde mit PR #78 nach `staging` gebracht und anschließend mit realen Staging-Daten getestet. Während der Abnahme wurden Daten verändert. Dabei wurde entdeckt, dass die Staging-API noch auf den Live-Tab `Inbox` zeigte. PR #79 korrigierte die Umgebungstrennung. Danach zeigte der reale CityArt-Fall weiterhin inkonsistente Zeit- und Visualfelder.

## Belegte Root Causes

### 1. Dokumentierter Prozess und technische Deploy-Realität widersprachen sich

Der Workpack verlangte reale Staging-Abnahme vor dem Merge nach `staging`. Der vorhandene Deploy-Prozess stellt jedoch nur den Branch `staging` auf der Staging-Website bereit. Dadurch war der geforderte Ablauf nicht reproduzierbar und wurde faktisch zu:

```text
CI → Merge nach staging → reale Daten als erster Integrationstest
```

### 2. CI prüfte überwiegend Verträge und Fixtures, nicht die reale Zustandskette

Die Tests bestätigten statische Marker, synthetische Payloads und einzelne Runtime-Funktionen. Sie konnten nicht nachweisen, welcher Google-Sheets-Tab in der tatsächlich deployten Umgebung gelesen und beschrieben wurde oder ob die reale Quellzeile mit dem sichtbaren lokalen Fall identisch war.

### 3. Keine verpflichtende Datenidentitäts- und Environment-Evidence

Vor dem ersten Schreibtest fehlte ein maschinenlesbarer Nachweis für:

- Zielumgebung,
- Zieltab,
- stabile Quellzeile,
- lokale Fallidentität,
- Vorherzustand,
- erwartete Mutation,
- Rollback.

Dadurch konnte eine erfolgreiche UI-Aktion nicht eindeutig einem Staging-Datensatz zugeordnet werden.

### 4. Reale Datenkorrektur und Systemdiagnose wurden vermischt

Die fehlende CityArt-Uhrzeit wurde manuell in Daten ergänzt, bevor die komplette Entstehungskette aus Discovery, Mapping, Inbox, Staging-Inbox und lokaler Fallkopie belegt war. Die Korrektur beseitigte damit eventuell ein Symptom, lieferte aber keinen Root-Cause-Nachweis.

### 5. Stop-the-line fehlte als verbindlicher Mechanismus

Nach der Entdeckung eines möglichen Live-Writes wurde weiter an Zeitfeld, Feedbackprozess, Fixbranch und Abnahme gearbeitet. Der Prozess schrieb keinen zwingenden Stopp mit Forensik vor.

### 6. Merge-Gates waren nicht technisch an Evidence gebunden

PR #78 und #79 konnten ohne unabhängiges Review, ohne per-PR Change-Manifest und ohne isolierte vollständige E2E-Evidence gemergt werden. Grüne CI wurde dadurch zu stark als Freigabesignal interpretiert.

## Präventive Kontrollen

1. Neuer kanonischer Arbeitsmodus `STEUERZENTRALE_WORKMODE_FREEZE_2026-07-17.md`.
2. Klare Trennung: isolierte Integration vor Merge nach `staging`; reale Staging-Abnahme nach Deploy; Main erst danach.
3. Maschinenlesbares Change-Manifest pro Steuerzentralen-PR.
4. CI-Validator gleicht deklarierten Scope mit tatsächlich geänderten Dateien ab.
5. Runtime-Änderungen benötigen isolierte Integration und Evidence-Dateien.
6. Quell-/Writeback-Änderungen benötigen Environment- und Datenidentitätsnachweis.
7. Reale Staging-Schreibprobe darf vor dem Merge nicht als abgeschlossen oder autorisiert gelten.
8. Main-PR benötigt reale Staging-Abnahme als Datei im Repository.
9. Live-Schreibtests sind grundsätzlich verboten.
10. PR-Template und CODEOWNERS machen die Regeln im normalen GitHub-Ablauf sichtbar.
11. Stop-the-line bei Environment-, Identitäts-, Persistenz- oder unerwarteten Datenmutationsfehlern.

## Noch nicht technisch erzwingbar

Der Workflow kann nur dann einen Merge sicher blockieren, wenn der Check `Control Center Change Governance / governance` in der Branch-Protection von `staging` und `main` als erforderlich konfiguriert ist. Das verfügbare GitHub-Werkzeug erlaubt keine Änderung der Branch-Protection. Dieser einmalige Repository-Admin-Schritt bleibt deshalb separat erforderlich.

## Abgrenzung dieses Workpacks

Dieser Governance-Workpack verändert keine Steuerzentralen-Runtime, keine Google-Sheets-Daten und keine Deploy-Konfiguration. Die forensische CityArt-Untersuchung beginnt erst nach Übernahme und Aktivierung der Governance-Regeln.
