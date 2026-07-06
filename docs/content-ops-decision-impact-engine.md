# Content Ops Decision & Impact Engine

## Ziel

Die automatisierten Content-, Such-, Intake-, Cleanup- und Growth-Laeufe erzeugen nicht nur Reports, sondern verwertbare Betriebsdaten fuer die zentrale interne Verwaltung.

Der Zielprozess lautet:

```text
Prueflauf / Suchlauf / Intake / Cleanup / Growth
→ normalisierte Findings
→ sichere Folgeaktion
→ messbare Impact-Metrik
→ spaeter zentrale Verwaltungsoberflaeche
```

Die Engine nimmt keine fachlichen Live-Aenderungen vor. Sie veroeffentlicht keine Events, aendert keine Termine, loescht keine Inhalte und tauscht keine Bilder aus.

## Fuehrende Rollen

| Ebene | Zweck |
|---|---|
| Google Sheets | operative Review-, Inbox- und Feedbackdaten |
| SQL | Zeitreihen, Wirkungsmetriken und spaeter Dashboard-Aggregate |
| GitHub Actions | automatische Laeufe und Metrik-Erfassung |
| `scripts/content-ops-control.py` | Decision-&-Impact-Normalisierung |
| spaetere Verwaltung | Aufgaben, Entwicklung, Search, Feedback-Wirkung, SEO/Growth, Technik |

## Angeschlossene Workflows

| Workflow | Modus |
|---|---|
| `content-quality-audit.yml` | `record-audit` |
| `weekly-ki-websearch-to-manual-inbox.yml` | `record-weekly-ki` |
| `manual-ki-intake.yml` | `record-manual-intake` |
| `inbox-cleanup.yml` | `record-inbox-cleanup` |
| `growth-intelligence-backlog.yml` | `record-growth` |

## SQL-Tabellen

Die Engine legt Tabellen automatisch an, wenn die Datenbankverbindung vorhanden ist. Das additive Schema liegt zusaetzlich in:

```text
api/sql/009_content_ops_metrics.sql
```

Tabellen:

| Tabelle | Zweck |
|---|---|
| `content_ops_run` | ein Datensatz pro automatisiertem Lauf |
| `content_ops_metric_daily` | exakte Metrikwerte pro Lauf/Tag/Scope |
| `content_ops_action_log` | geroutete Findings und Folgeaktionen |
| `feedback_rule_effectiveness_daily` | Regel-/Guard-Anwendung und direkte Filterwirkung |

Wenn DB-Secrets fehlen oder die DB nicht erreichbar ist, bricht die Engine den Workflow nicht hart ab. Sie schreibt weiter lokale JSON-Artefakte unter:

```text
data/content-ops/*.json
```

## Sichtbare Logik spaeter in der Verwaltung

Die Verwaltung soll keine allgemeinen Bewertungen wie „besser“ oder „schlechter“ anzeigen, sondern konkrete Werte:

```text
vorheriger Zeitraum → aktueller Zeitraum (Delta)
```

Standard:

| Sicht | Zeitraum |
|---|---|
| akute Aufgaben | letzte 7 Tage |
| Hauptvergleich | aktuelle 28 Tage vs. vorherige 28 Tage |
| Trend | 12 Wochen |
| SEO/Growth-Kontext | 90 Tage |

## Sicherheitsgrenze

Automatisch erlaubt:

```text
klassifizieren
unterdruecken
deduplizieren
Drop-Gruende messen
Feedback-Anwendung messen
technische Regressionen markieren
Inbox-Faelle erzeugen
Metriken schreiben
```

Nicht automatisch erlaubt:

```text
Live-Events veroeffentlichen
bestehende Events fachlich aendern
Termine/Uhrzeiten ueberschreiben
Events loeschen
Bilder final austauschen
unsichere Quellen uebernehmen
```

## Patch-1-Grenze

Dieser Patch baut bewusst noch nicht die finale Verwaltungsoberflaeche. Er schafft die belastbare Datenbasis, damit die spaetere UI keine dekorative Report-Ansicht wird, sondern auf echten gemessenen Laeufen, Findings und Folgeaktionen aufbaut.
