# Content-Ops Roboter / Selbstlernprozess – Handoff vor Dashboard

Stand: 2026-07-08  
Branch: `staging`  
Ziel: Vor dem internen Dashboard sicherstellen, dass der automatische Verbesserungsprozess fachlich und technisch belastbar geschlossen ist.

## Grundprinzip

Der Prozess ist ein kontrollierter Verbesserungs-Kreislauf:

```text
Roboterlauf
→ Content-Ops-Signal
→ Normalisierung in Metriken / Findings / RuleEffects
→ HTTP-Ingest
→ DB-Persistenz
→ Run Health
→ Betreiberentscheidung / Feedback
→ nächste Läufe werden besser
```

Wichtig:

- Keine blinde automatische Live-Änderung.
- Automatisch erkennen, klassifizieren, priorisieren und messen.
- Kritische fachliche Entscheidungen bleiben kontrolliert.
- Dashboard wird erst gebaut, nachdem der Prozess real geschlossen ist.

## Aktuell relevante Roboter

### Content Quality Audit

Aufgabe:

- prüft Events, Activities, Quellen, Beschreibungen, Fakten, Visual Keys, Visual Motifs und Visual Asset Status.
- erzeugt Content-Ops-Artefakte.
- schreibt Content-/Search-/Visual-Feedback in Sheet-Tabs.
- soll seine Ergebnisse per HTTP-Ingest in die DB schreiben.

Bewertung:

- fachlich stark.
- HTTP-Ingest-Step ist im Workflow vorhanden.
- echter Ingest funktionierte zeitweise.
- zuletzt fiel Content Quality wieder auf `failed_http_ingest:503` / `Content Ops ingest is not configured`, weil der Ingest-Token nach Deploy nicht nachhaltig in der aktiven Server-Config lag.
- zusätzlicher fachlicher Fix noch nötig: Content-Audit-Fälle ohne explizite `decision_class` sollen automatisch sinnvoll inferiert werden.

Zielbild für `decision_class_counts`:

```text
done
needs_patch
needs_source
needs_visual_fix
watch
```

Nicht ausreichend:

```text
fast nur done, obwohl viele manual_review / warnings / visual cases existieren
```

### Growth Intelligence Backlog

Aufgabe:

- erkennt SEO-/GSC-/GA4-/Content-Potenziale.
- dedupliziert Growth-Backlog.
- nutzt Betreiberfeedback.
- unterdrückt erledigte, irrelevante, duplizierte und temporär zurückgestellte Growth-Signale korrekt.

Bewertung:

- aktuell gut.
- echter Staging-Lauf war erfolgreich.
- HTTP-Ingest wurde real bestätigt:

```text
source_mode: growth_intelligence
status: ok
result: persisted_http_ingest
stored_runs: 1
```

Keine weitere Härtung vor Dashboard nötig.

### Inbox Cleanup

Aufgabe:

- archiviert erledigte Inbox-Fälle.
- räumt Review-Altlasten auf.
- erzeugt Content-Ops-Impact.

Bewertung:

- Workflow schreibt Content-Ops-Artefakte.
- direkter HTTP-Ingest-Step wurde ergänzt.
- echter Staging-Lauf war erfolgreich, solange der Ingest-Token aktiv war:

```text
source_mode: inbox_cleanup
status: ok
result: persisted_http_ingest
stored_runs: 1
```

Keine weitere fachliche Härtung vor Dashboard nötig.

### Weekly KI Websearch → Manual Inbox

Aufgabe:

- sucht neue Event-Kandidaten per KI/Websuche.
- dedupliziert gegen Events, Inbox, Archiv und Manual-Puffer.
- schreibt nur Delta-Kandidaten in `data/inbox_manual.json`.
- nutzt begrenztes Feedback aus Search-/Inbox-/Archiv-Signalen.

Bewertung:

- auf `staging` bewusst blockiert.
- auf `main` produktiv relevant.
- nicht vor Dashboard auf staging erzwingen.
- nach main-Merge/Live-Lauf prüfen.

Wichtig:

```text
staging: optional / bewusst nicht required
live: required
```

### Manual KI Event Intake

Aufgabe:

- übernimmt `data/inbox_manual.json` in die Inbox.
- validiert Pflichtfelder, Datum, Dubletten, Beschreibungsgüte und Visual-Zuordnung.
- setzt Status/created_at und leert den Manual-Puffer nach erfolgreichem Append.

Bewertung:

- fachlich gut.
- auf `staging` bewusst blockiert.
- nicht vor Dashboard auf staging erzwingen.
- nach main-Merge/Live-Lauf prüfen.

Wichtig:

```text
staging: optional / bewusst nicht required
live: optional oder event-driven, aber im Live-E2E prüfen
```

### Visual Feedback / Visual-Lernkreis

Aufgabe:

- erkennt Visual-Key-/Motiv-/Asset-Probleme.
- klassifiziert Visual-Followups.
- erzeugt keine automatische Bildproduktion.

Bewertung:

- fachlich gut.
- Audit erzeugte echte Visual-Signale wie `visual_review`, `visual_motif_wrong` und `needs_visual_fix`.
- keine weitere Automatisierung vor Dashboard.

### Run Health

Aufgabe:

- prüft, ob Pflichtroboter aktuell und erfolgreich in der DB angekommen sind.
- trennt technische Prozessgesundheit von fachlichen Content-Findings.

Staging-Zielzustand:

```text
content_quality_audit   required=true   ok
growth_intelligence     required=true   ok
inbox_cleanup           required=true   ok
weekly_ki_websearch     required=false  missing_optional / info
manual_ki_intake        required=false  missing_optional / info
```

Dieser Zustand wurde real einmal erreicht.

## Wichtiger technischer Befund: Ingest-Token

Problem:

- Manuell angelegte `api/_config.local.php` ist im Staging-Zielordner nicht nachhaltig.
- Der STRATO-Deploy nutzt für staging `mirror -R --delete deploy/ .`.
- Dadurch können manuell abgelegte Dateien im Zielordner gelöscht werden.
- Deshalb muss der Token aus GitHub Secrets direkt in die deployte `api/_config.php` geschrieben werden.

Nachhaltige Lösung:

```text
GitHub Secret CONTENT_OPS_INGEST_TOKEN
→ Deploy to STRATO
→ deploy/api/_config.php
→ content_ops.ingest_token
→ api/content-ops-ingest.php kann Token lesen
```

Nicht mehr pflegen:

```text
/STRATO-apps/staging/api/_config.local.php
```

Diese Datei ist für den Deploy-Zielordner nicht ausreichend nachhaltig.

## Noch offene Korrekturen vor Dashboard

### P0 – Deploy-Config schreibt Content-Ops-Token

Status: noch nicht abgeschlossen.

Beobachteter Stand aus dem Chat:

- Patchversuch meldete `MARKER FEHLT: content ops config payload`.
- Anschließende Checks waren grün, aber `git commit -m "Schreibe Content Ops Token in Deploy Config"` meldete `nothing to commit`.
- Daraus folgt: Der nachhaltige Deploy-Config-Fix wurde noch nicht committed.

Erwarteter Commit:

```text
Schreibe Content Ops Token in Deploy Config
```

Ziel:

- `.github/workflows/deploy-strato.yml` macht `CONTENT_OPS_INGEST_TOKEN` im Prepare-Deploy-Step verfügbar.
- Deploy setzt `APP_CONTENT_OPS_INGEST_TOKEN`.
- Deploy failt, wenn dieser Token fehlt.
- Python-Config-Writer schreibt in `deploy/api/_config.php`:

```json
"content_ops": {
  "ingest_token": "..."
}
```

- Deploy-Log zeigt ohne Secret-Wert:

```text
Content Ops ingest token configured: yes
```

- `scripts/audit-self-learning-contract.py` hat einen Guard für den Deploy-Config-Token.

Zu prüfen nach Patch:

```text
Deploy to STRATO auf staging grün
Content Quality Audit danach grün
Send Content Ops audit impact to HTTP ingest:
  persisted_http_ingest
  stored_runs: 1
```

### P1 – Content-Audit decision_class-Fallback

Status: im Chat vorbereitet und lokal offenbar schon teilweise angewendet.

Beobachteter Stand aus dem Chat:

- E2E-Fixtures meldeten zuletzt `content_ops_e2e_fixtures=pass` mit `total: 28`.
- Das spricht dafür, dass der Decision-Class-Fallback-Patch lokal bereits angewendet und geprüft wurde.
- Ob er bereits committed wurde, muss im nächsten Chat über `git log` geprüft werden.

Erwarteter Commit:

```text
Inferiere Content Audit Entscheidungsklassen
```

Ziel:

```text
manual_action_required → needs_patch / needs_source
ai_factcheck_candidate → needs_source
visual_workflow        → needs_visual_fix
manual_warning         → watch
guarded_by_runtime     → watch
neutral_observed       → watch
auto_fixed             → done
```

Nach Test prüfen:

```text
decision_class_counts enthält nicht nur done,
sondern auch needs_patch / needs_source / needs_visual_fix / watch.
```

### P2 – Abschlussdokumentation nach Proof

Nach erfolgreichem P0/P1:

- realen Health-Stand dokumentieren.
- Commit-SHAs dokumentieren.
- Dashboard-Freigabe markieren.

## Nicht vor Dashboard erweitern

Nicht mehr neu öffnen:

```text
kein neues KI-Suchsystem
keine neue Growth-Logik
keine automatische Bildproduktion
keine automatische Content-Änderung
keine großen neuen Regelkataloge
kein Overengineering bei recurrence/false_positive vor mehreren echten Wochenläufen
```

## Dashboard-Freigabekriterium

Dashboard erst starten, wenn gilt:

```text
Content Quality Audit HTTP-Ingest real ok
Growth HTTP-Ingest real ok
Inbox Cleanup HTTP-Ingest real ok
Run Health staging korrekt
Weekly/Manual KI auf staging optional/info
Content-Audit decision_class-Fallback real sichtbar
```

Dann Dashboard als Prozessoberfläche bauen:

```text
Was braucht Entscheidung?
Was ist nur Beobachtung?
Was läuft nicht?
Was wurde automatisch erledigt?
Welche Regeln wirken?
Welche Visual-/Source-/Patch-Aufgaben sind offen?
```

## Prompt für neuen Chat

```text
Wir arbeiten am Projekt „Bocholt erleben“, Branch `staging`.

Wichtige Arbeitsregeln:
- Kein V1/V2-Zielbild; immer Premium-Zielzustand denken.
- Dashboard erst bauen, wenn der Content-Ops-/Selbstlernprozess real sauber geschlossen ist.
- Keine blinde automatische Content-Änderung; automatisch erkennen, klassifizieren, messen und priorisieren, aber kritische Entscheidungen kontrolliert.
- Keine Patch-ZIPs mit README/MANIFEST/Wrapper. Wenn nötig, direkt Repo-Struktur oder klare Codespace-Kommandos.
- Interne Dashboard-UI muss später prozessorientiert sein, nicht source-tab-first.

Aktueller Stand:
- Content-Ops/Selbstlernprozess wurde umfangreich gehärtet:
  - zentrale `decision_class`-Taxonomie,
  - semantischer Selbstlern-Audit,
  - Growth-Feedback über `content_ops_decisions.py`,
  - Content-/Inbox-Entscheidungen semantisch gemessen,
  - Run Health/Freshness,
  - Visual Feedback Lernkreis,
  - E2E-Fixture-Guard,
  - HTTP-Ingest für Growth, Content Quality und Inbox Cleanup,
  - geschützte Health-API.
- Reale Staging-Health war einmal korrekt:
  - `content_quality_audit` required true ok
  - `growth_intelligence` required true ok
  - `inbox_cleanup` required true ok
  - `weekly_ki_websearch` required false missing_optional/info
  - `manual_ki_intake` required false missing_optional/info
- Growth und Inbox Cleanup zeigten real `persisted_http_ingest`.
- Content Quality zeigte ebenfalls den Ingest-Step, fiel zuletzt aber wieder mit `failed_http_ingest:503` / `Content Ops ingest is not configured`, weil der manuelle `_config.local.php`-Ansatz nach Deploy nicht nachhaltig war.

Wichtiger technischer Befund:
- STRATO-Deploy spiegelt staging mit `mirror -R --delete deploy/ .`.
- Manuell in `/STRATO-apps/staging/api/_config.local.php` abgelegte Secrets sind deshalb nicht zuverlässig.
- Nachhaltige Lösung: `CONTENT_OPS_INGEST_TOKEN` muss aus GitHub Secrets direkt beim `Deploy to STRATO` in die generierte private `deploy/api/_config.php` geschrieben werden.
- Der Patchversuch meldete `MARKER FEHLT: content ops config payload`; danach war nichts zu committen. Also Deploy-Config-Fix als Erstes sauber nachziehen.
- Erwarteter Commit: `Schreibe Content Ops Token in Deploy Config`.
- Danach Staging-Deploy abwarten und `Content Quality Audit` erneut starten. Erwartung im Step `Send Content Ops audit impact to HTTP ingest`: `persisted_http_ingest`, `stored_runs: 1`.

Noch offene fachliche Härtung vor Dashboard:
- Content Quality Audit soll Findings ohne explizite `decision_class` sauber inferieren:
  - `manual_action_required` → `needs_patch` oder `needs_source`
  - `ai_factcheck_candidate` → `needs_source`
  - `visual_workflow` → `needs_visual_fix`
  - `manual_warning` → `watch`
  - `guarded_by_runtime` → `watch`
  - `neutral_observed` → `watch`
  - `auto_fixed` → `done`
- Der Patch scheint lokal schon geprüft zu sein, weil `content_ops_e2e_fixtures=pass` mit `total: 28` lief. Im neuen Chat prüfen, ob Commit `Inferiere Content Audit Entscheidungsklassen` existiert. Wenn nicht, committen/pushen oder sauber neu anwenden.

Start im neuen Chat:
1. Zuerst aktuellen Git-/Repo-Stand prüfen:
   - `git status --short`
   - `git log -8 --oneline --decorate`
2. Prüfen, ob diese Commits existieren:
   - `Schreibe Content Ops Token in Deploy Config`
   - `Inferiere Content Audit Entscheidungsklassen`
   - `Dokumentiere Content Ops Roboter Handoff`
3. Fehlende Patches abschließen.
4. Danach Staging-Deploy und Content Quality Audit erneut prüfen.
5. Erst wenn HTTP-Ingest und decision_class_counts sauber sind, mit dem internen Dashboard beginnen.
```
