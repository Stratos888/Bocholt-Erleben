# Content-Ops Selbstlernprozess – optimaler Zielzustand

Stand: 2026-07-07
Branch: `staging`

## Ziel

Der Content-Ops-Prozess soll Bocholt Erleben dauerhaft besser machen. Das Dashboard ist nur eine spaetere Oberflaeche. Der Kern ist ein geschlossener Verbesserungsbetrieb:

```text
Roboterlauf
→ normalisiertes Signal
→ Aufgabe oder Beobachtung
→ Betreiberentscheidung
→ typisiertes Feedback
→ Regel, Recheck, Backlog-Folgeaktion oder Suppression
→ naechster Lauf wird besser
→ Wirkung und Fehlwirkung werden gemessen
```

Der aktuelle Gate-Check ist strukturell gruen:

```text
self_learning_contract=pass
ready_for_final_dashboard=true
37 ok · 0 warn · 0 fail
```

Das bedeutet: Alle kritischen Prozessanschluesse sind vorhanden. Es bedeutet nicht, dass jede Entscheidung, Regel und Wirkung schon semantisch optimal bewiesen ist.

## Premium-Zielzustand

Der Zielzustand ist erreicht, wenn jede relevante Entscheidung eine verwertbare Folge fuer spaetere Laeufe hat.

Der Prozess muss jederzeit beantworten koennen:

1. Welcher Roboter ist gelaufen?
2. Welches Signal wurde erzeugt?
3. Ist daraus eine echte Aufgabe oder nur eine Beobachtung entstanden?
4. Welche Entscheidung wurde getroffen?
5. Welche Regel, Recheck-Frist, Folgeaktion oder Suppression entstand daraus?
6. Hat der naechste Lauf dadurch weniger falsche Treffer, weniger Wiederholungen, bessere Kandidaten oder bessere Content-Qualitaet erzeugt?
7. Wurde eine Regel zu hart oder falsch angewendet?
8. Muss eine Regel verschaerft, abgeschwaecht, befristet oder entfernt werden?

## Fuehrende Prinzipien

- Nur echte Entscheidungen werden Betreiberaufgaben.
- Alles andere wird als Beobachtung, Automatik, Diagnose oder Metrik gefuehrt.
- Freitext darf begruenden, aber nicht die fuehrende Prozesslogik sein.
- Feedback darf Suchlaeufe verbessern, aber nicht unkontrolliert das dauerhafte Regelwerk veraendern.
- Jede Regel muss messbar und spaeter korrigierbar sein.
- Archivierte Entscheidungen bleiben Lernmaterial.
- Datenfrische und Roboter-Gesundheit sind Teil der Produktqualitaet.
- UI folgt dem Prozess, nicht umgekehrt.

## Zentrale Entscheidungstaxonomie

Langfristig soll neue Prozesslogik auf `decision_class` laufen. Status-Freitexte bleiben nur Kompatibilitaetsschicht.

| `decision_class` | Bedeutung | Folge |
|---|---|---|
| `accepted` | fachlich akzeptiert | uebernehmen oder bestehen lassen |
| `confirmed` | Fakt/Quelle bestaetigt | Cache/Recheck setzen |
| `corrected` | fachlich korrigiert | naechster Audit prueft Zustand |
| `done` | Arbeit umgesetzt | nicht erneut oeffnen, Wirkung beobachten |
| `duplicate` | bereits vorhanden | aehnliches Muster filtern |
| `rejected_not_event` | kein konkreter Event | Such-/Intake-Filter staerken |
| `rejected_not_public` | nicht oeffentlich | Quellen-/Pattern-Filter staerken |
| `rejected_not_local` | nicht lokal genug | Radius-/Quellenlogik staerken |
| `rejected_source_weak` | Quelle nicht belastbar | Quellenbewertung senken |
| `rejected_low_value` | redaktionell zu schwach | Qualitaetsfilter staerken |
| `rejected_commercial` | primaer Werbung/Promotion | kommerzielle Treffer filtern |
| `needs_patch` | Repo-/Datenpatch noetig | technische oder fachliche Aufgabe |
| `needs_source` | bessere Quelle noetig | Quellenaufgabe |
| `needs_visual_fix` | Bild/Key/Motiv/Asset falsch oder fehlt | Visual-Lernkreis starten |
| `snoozed` | spaeter erneut pruefen | `suppress_until` setzen |
| `watch` | beobachten | Recheck-Frist setzen |

Zielattribute je Entscheidung:

```text
decision_class
decision_note
resolved_by
resolved_at
suppress_until
recheck_at
reopen_policy
expected_effect_window_days
source_fingerprint
content_fingerprint
```

## Aufgabenmodell

Eine Aufgabe ist nur dann eine Aufgabe, wenn eine Betreiberentscheidung erforderlich ist.

Zielstatus:

| Status | Bedeutung |
|---|---|
| `open` | Entscheidung erforderlich |
| `acknowledged` | gesehen, noch nicht entschieden |
| `in_progress` | in Bearbeitung |
| `snoozed` | bewusst bis Datum zurueckgestellt |
| `resolved` | entschieden oder erledigt |
| `auto_routed` | keine Betreiberentscheidung noetig |
| `expired` | durch Zeitablauf irrelevant geworden |

Zielattribute:

```text
task_type
priority_score
due_at
owner
source_mode
entity_type
entity_id
decision_class
resolution_note
resolution_source
```

Prioritaet entsteht aus Dringlichkeit, Nutzerwert, Fehlerfolge, Wiederholung und Aufwand. Nicht aus dem Quellsystem.

## Regel- und Feedback-Lifecycle

Jede Regel oder Suppression hat einen Lebenszyklus:

```text
candidate → active → measured → strengthened / weakened / expired / disabled
```

Zielmetriken:

| Metrik | Bedeutung |
|---|---|
| `applied_count` | Regel angewendet |
| `prevented_count` | Fehlarbeit verhindert |
| `recurrence_count` | bekannter Fehler kam wieder |
| `false_positive_count` | Regel hat falsch oder zu hart gefiltert |
| `last_seen_at` | letzte fachliche Relevanz |
| `expires_at` | Ablaufdatum bei befristeter Wirkung |

Ein selbstlernender Prozess ist nur robust, wenn er falsches Lernen korrigieren kann.

## Lernkreise

### Content Quality

Audit-Signale werden zu Aufgabe, Beobachtung, Cache, Search Feedback, Visual Feedback oder Recheck. Bestaetigte Fakten entlasten kuenftige Laeufe nur mit Fingerprints und gueltigem `verified_until`.

### Weekly KI Websearch

Die Suche nutzt Regelwerk, Quellenregister, Content-Search-Feedback und Inbox-Ablehnungen. Ziel ist weniger Dubletten, weniger schwache Treffer, bessere Quellenwahl und weniger manuelle Nacharbeit.

### Manual KI Intake

Der Intake ist ein Qualitaetstor. Er prueft Pflichtfelder, Datum, Dedupe, Beschreibung und Visual-Fit. Schlechte Kandidaten werden mit typisiertem Skip-Grund geblockt.

### Inbox Cleanup

Abgeschlossene Entscheidungen werden archiviert und bleiben Lernmaterial fuer Dedupe, Ablehnungsmuster und spaetere Suchqualitaet.

### Growth Intelligence

Growth-Signale aus GSC, GA4, interner Nutzung, Content-Bestand, Sheet-Historie und Visual-Signalen werden dedupliziert. Betreiberentscheidungen muessen als `decision_class`, Recheck, Suppression oder Reopen-Policy in spaetere Growth-Laeufe zurueckwirken.

Wichtig: `done`, `irrelevant`, `duplicate`, `snoozed` und `planned` duerfen langfristig nicht dieselbe Bedeutung haben.

### Visual Feedback

Visual-Feedback soll nicht nur Backlog sein. Ziel ist:

```text
Visual-Problem
→ Entscheidung: Key falsch / Motiv falsch / Asset fehlt / akzeptieren / remastern
→ typisiertes Feedback
→ Resolver-, Motivregel- oder Asset-Folgeaktion
→ naechster Lauf macht weniger falsche Zuordnungen
→ Wirkung wird gemessen
```

### Run Health

Jeder Roboter braucht Frische- und Fehlerschwellen:

```text
letzter erfolgreicher Lauf
letztes valides Artefakt
letzter erfolgreicher Ingest
letzte DB-Persistenz
letztes aktualisiertes Feedback
```

Ein still ausgefallener Roboter darf nicht wie ein gruener Zustand wirken.

## Wirkungsmessung

Der Prozess misst nicht nur Menge, sondern Nutzen.

| Bereich | Gute Richtung |
|---|---|
| KI-Suche | weniger wiederkehrende Fehler, bessere Selected-Rate, weniger Ablehnungen gleicher Klasse |
| Inbox | weniger offene Altfaelle, weniger Dubletten, schnellere Entscheidungen |
| Content Quality | weniger wiederkehrende kritische Findings, mehr Cache-Hits, weniger Wiederholpruefung |
| Growth | weniger irrelevante Wiederholpunkte, bessere Chancen, Wirkung nach Umsetzung sichtbar |
| Visual | weniger Motiv-/Asset-Gaps, weniger manuelle Korrektur |
| Technik | frischere Laeufe, weniger fehlende Artefakte, weniger Ingest-/DB-Skips |

Mehr Findings sind nicht automatisch schlecht. Sie koennen bessere Erkennung oder mehr echte Fehler bedeuten. Jede Metrik muss im Kontext von Zeitraum, Quelle, Entscheidung und Lauf gelesen werden.

## Sicherheitsgrenze

Automatisch erlaubt:

```text
klassifizieren
deduplizieren
filtern
Drop-Gruende messen
Feedback-Anwendung messen
Regelwirkung messen
technische Regressionen markieren
Review-/Backlog-Signale erzeugen
Metriken schreiben
```

Nicht automatisch erlaubt:

```text
Live-Events veroeffentlichen
bestehende Events fachlich aendern
Termine oder Uhrzeiten ueberschreiben
Events entfernen
Bilder final austauschen
unsichere Quellen uebernehmen
dauerhafte Regelbuchaenderungen aus Einzelfaellen ableiten
```

## Noch noetige Haertung zum absoluten Optimum

Der Prozess ist strukturell geschlossen. Fuer maximale Langfrist-Sicherheit fehlen noch diese Haertungen:

1. Semantische Fixture-Tests: Entscheidung rein, naechster Lauf reagiert richtig, Wirkung stimmt.
2. Zentrale `decision_class` in allen operativen Bereichen.
3. Echte Befuellung von `recurrence_count` und `false_positive_count`.
4. Praeziser Growth-Lifecycle mit `suppress_until`, `reopen_policy` und Wirkungfenster.
5. Voller Visual-Lernkreis von Entscheidung zu Resolver-/Motiv-/Asset-Folgeaktion.
6. Run-Health-Freshness als harte Prozessqualitaet.

## Pflicht-Fixtures

Mindestens diese Szenarien muessen kuenftig maschinell testbar sein:

- KI-Kandidat `rejected_not_public` → aehnliche Kandidaten werden im naechsten Lauf gefiltert.
- KI-Kandidat `duplicate` → Dedupe wirkt im naechsten Lauf.
- Content-Fakt `confirmed` → kein neuer offener Fall bis `verified_until`.
- Growth-Punkt `irrelevant` → Cluster wird unterdrueckt.
- Growth-Punkt `snoozed` → Cluster wird nur bis `suppress_until` unterdrueckt.
- Growth-Punkt `done` → nicht erneut oeffnen, aber Wirkung beobachten.
- Visual-Problem `needs_visual_fix` → Visual-Folgeaktion entsteht.
- Regel filtert falsch → `false_positive_count` steigt und Regel wird pruefpflichtig.

## Naechste Prozessprioritaet

Nicht Dashboard-Optik, sondern semantische Selbstlern-Haertung:

1. Fixture-Testpaket fuer die Lernkreise.
2. Zentrale Entscheidungstaxonomie operativ einfuehren.
3. Feedback-Qualitaetsmetriken real befuellen.
4. Growth- und Visual-Lifecycle praezisieren.
5. Run-Health-Freshness ergaenzen.

## Zielbewertung

Aktuell gilt:

```text
Strukturell geschlossen: ja.
Fachlich optimal abgesichert: noch nicht vollstaendig.
Naechster Schwerpunkt: semantische Selbstlern-Haertung.
```

Das absolute Ziel ist erreicht, wenn richtige Entscheidungen spaetere Laeufe messbar verbessern, falsche Regeln erkannt werden und der manuelle Aufwand sinkt, ohne Qualitaetsverlust zu erzeugen.
