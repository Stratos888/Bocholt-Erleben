# Steuerzentrale – Premium-Workpack ausnahmebasierte Eventprüfung

Stand: 2026-07-16  
Zielbranch: `staging`  
Arbeitsbranch: `agent/premium-exception-based-event-review`  
Ausgangsstand: `7e1157eb97969d8ae7d0769473a17e5582fe51af`  
Status: Implementierung und CI-Verträge vorbereitet; kein Merge und keine Main-Änderung

## Ziel

Die Steuerzentrale führt den Betreiber nicht mehr über technische Blocker in ein vollständiges Eventformular. Sie zeigt nur ungelöste fachliche Ausnahmen, bietet die jeweils zulässige Aktion direkt an der Aufgabe an und bestätigt jede Teilaktion erst nach kanonischer Persistenz, Rückleseprüfung, vollständiger Neubewertung und konsistentem lokalen Fallzustand.

Die mit PR #77 bestätigte äußere Zustandskette bleibt unverändert:

```text
führende Quelle
→ lokaler Fall
→ aktive API
→ sichtbare UI
→ Reload und Idempotenz
```

Innerhalb eines offenen Eventfalls ergänzt dieses Workpack die verbindliche Teilaktionskette:

```text
Befund
→ verständliche Aufgabe und Evidenz
→ direkte Eingabe oder Auswahl
→ kanonischer Teil-Writeback
→ Folgeprozess
→ Rückleseprüfung
→ erneute Gesamtbewertung
→ verifizierter Aufgaben- und UI-Endzustand
```

## Kanonischer Aufgabenvertrag

Jede offene Ausnahme besitzt:

- stabile `task_id`, `finding_code` und `task_revision`,
- betroffene kanonische Felder,
- Blocker- oder Hinweiswirkung,
- verständlichen Nutzertext,
- Evidenzstatus, Vertrauensgrad und Quellenbezug,
- ausschließlich zulässige direkte Aktionen,
- Persistenz- und Folgeprozessvertrag,
- erwartete Postcondition,
- Audit- und Lernmetadaten,
- lokalen Zustand `open`, `waiting_external`, `candidate_ready`, `conflict` oder `failed_verification`.

Evidenzstatus:

- `officially_verified`
- `cross_checked`
- `format_validated`
- `system_suggestion`
- `human_required`
- `unverifiable`
- `conflict`

`decision_gate.ready` ist nur wahr, wenn keine blockierende Aufgabe mehr offen ist. Eine Teilaktion setzt den Fall nicht auf `done`; erst die bewusste Gesamtübernahme verwendet die vorhandene terminale PR-#77-Kette.

## Abgedeckte Befund- und Aktionsmatrix

| Bereich | Typische Befunde | Kanonische Aktion und Folge |
|---|---|---|
| Identität und Pflichtfelder | Titel, Ort, Kategorie oder Beschreibung fehlen | fokussierte Eingabe, Quell-Writeback, Rücklesen und Neubewertung |
| Datum und Zeitraum | ungültiger Termin, Enddatum fehlt, Ende vor Start | Datum/Terminart korrigieren, Ablauf- und Dedupeprüfung erneut ausführen |
| Ablauf und Aktualität | Termin abgelaufen oder Event abgesagt | typisierte Ablehnung oder neuen Termin erfassen; terminalen beziehungsweise neuen Zustand verifizieren |
| Uhrzeit | Zeit fehlt, mehrere Zeiten, Öffnungszeiten, nicht veröffentlicht, Konflikt | typisierten `time_status` und nötige Details speichern; bei fehlender Veröffentlichung oder Konflikt verifizierte Wiedervorlage statt Scheinfertigstellung |
| Stadt und lokaler Bezug | Stadt/Scope fehlt oder außerhalb Bocholt-Bezug | Stadt/Bezug bestätigen oder typisiert ablehnen; Scope neu prüfen |
| Ort und Adresse | Navigation benötigt Adresse oder Adresse ist nicht erforderlich | Adresse erfassen beziehungsweise begründeten Status speichern; Routing neu prüfen |
| Quelle | URL ungültig, Quelle schwach, nicht erreichbar oder widersprüchlich | offizielle Prüfquelle korrigieren, Konflikt auflösen oder zurückstellen; Eventlink und Prüfquelle getrennt führen |
| Beschreibung | fehlt, zu kurz, generisch oder unbelegt | nur betroffene Fassung korrigieren; Qualität und Behauptungen erneut prüfen |
| Ticket und Zugang | Ticket-/Anmeldeweg fehlt oder Link ungültig | typisierten Zugangsstatus und gegebenenfalls Link speichern; Link neu prüfen |
| Dublette/Serie | harte Dublette, eigenständiger Termin oder Ergänzung | ablehnen, als eigenständig markieren, Ergänzungsprozess auslösen oder zurückstellen |
| Visual-Key | Key fehlt oder Systemvorschlag muss bestätigt werden | kanonischen Key speichern und Motiv-/Assetauflösung neu starten |
| Visual-Motiv | Motiv fehlt oder passt nicht | Motivvorschlag bestätigen/korrigieren und Assetresolver neu ausführen |
| Konkretes Asset | ready Asset vorhanden, ungebunden oder Bindung ungültig | konkrete `visual_asset_id` mit Key/Motiv binden, Verfügbarkeit zurücklesen und genau dieses Asset erneut rendern |
| Visual-Fallback | exaktes Motiv fehlt, neutraler ready Fallback vorhanden | Fallback ausdrücklich binden und parallel idempotenten Gap für das Zielmotiv erzeugen |
| Visual-Gap | kein sicheres Asset vorhanden | stabile Gap-ID erzeugen, an `asset_production_backlog` routen, Fall in Wartezustand halten und fertigen Kandidaten als neue sichtbare Visual-Aufgabe zurückführen |

## Persistenz und Teilaktions-Postcondition

Die führende Inbox bleibt fachlich autoritativ. Teilaktionen schreiben ausschließlich erlaubte kanonische Felder. Fehlende neue Spalten werden innerhalb des verifizierten Batch-Vertrags dynamisch und atomar ergänzt; die Inbox wird dafür konsistent bis `A:ZZ` gelesen.

Jede Teilaktion muss nachweisen:

1. aktuelle Quellzeile über stabile Identität aufgelöst,
2. `task_revision` noch aktuell,
3. Aktion für den Befund zulässig,
4. alle vorgesehenen Quellfelder geschrieben,
5. Felder unmittelbar zurückgelesen und bestätigt,
6. vollständiger Reviewvertrag neu berechnet,
7. betroffene Aufgabe geschlossen, fortgeschritten oder bewusst in einen Wartezustand überführt,
8. lokaler Fall weiterhin sichtbar oder korrekt zurückgestellt/terminal,
9. Entscheidung, Evidenz, vorherige und neue Werte sowie Folgeprozess auditierbar protokolliert.

Idempotenz bleibt über `operation_id`, Aktion und Payload-Hash geschützt.

## Visual-Prozess

Der bestehende zentrale Visual-Feedback-Vertrag wird wiederverwendet:

- fehlendes Asset → `asset_missing` / `needs_visual_fix` / `asset_production_backlog`,
- falscher Key → Resolver-Regelprüfung,
- falsches Motiv → Motivregelprüfung,
- Rechteproblem → Quellen-/Rechteprüfung,
- fertiger Kandidat → sichtbare Ergebnisprüfung in derselben Eventaufgabe.

Die Gap-ID ist pro Eventidentität, Visual-Key und Zielmotiv stabil. Wiederholte Läufe erzeugen keinen zweiten Fall. Ein ready Kandidat wird nicht automatisch final gebunden; der Betreiber bestätigt immer das konkrete Bild.

## CityArt – erster realer Abnahmefall

Für

`Bocholter Kulturtage 2026 - Kunstmarkt CityArt und künstlerische Mitmach-Stände für Kinder und Jugendliche`

ergibt der neue Vertrag genau zwei offene Aufgaben:

1. **Beginnzeit klären**
   - konkrete Uhrzeit oder typisierter Zeitstatus,
   - bei `time_not_published` verifizierte Wiedervorlage und keine Freigabe.
2. **Passendes Bild bestätigen**
   - Systemvorschlag `art_exhibition_gallery` / `art_market`,
   - konkrete Vorschau `motif-gap-art-market-01`,
   - verbindliche Speicherung von `visual_key`, `visual_motif` und `visual_asset_id`,
   - Rücklesen und erneutes Rendern aus dem gebundenen Asset.

Erst danach erscheint die kompakte finale Gesamtfassung und `Übernehmen` wird aktiv.

## CI-Verträge

Das Workpack erweitert die bestehenden Gates um:

- Fixture-Matrix für alle Befundgruppen,
- CityArt-Vertrag mit exakt zwei offenen Aufgaben,
- erlaubte Aktionen und Persistenzwerte je Aufgabe,
- stale `task_revision` und unzulässige Feldänderungen,
- Teilaktions-Postconditions ohne terminalen Fallabschluss,
- `time_not_published` → Wiedervorlage statt `ready`,
- exakte Assetbindung und Bildvorschau,
- idempotente Visual-Gap-ID und Kandidatenrückführung,
- getrennte Event- und Prüfquelle,
- Schutz der PR-#77-Zustands-, Reload- und Sichtbarkeitsinvarianten,
- PHP-, JavaScript-, JSON-, Python-, CSS- und Architekturprüfung.

CI führt keine produktiven Schreibtests aus.

## Reale Staging-Abnahme vor Merge

Vor einem Merge nach `staging` beziehungsweise vor jeder späteren Veröffentlichung nach `main` sind mindestens nachzuweisen:

- CityArt: konkretes ready Asset plus Zeitklärung,
- ganztägiges Event,
- Uhrzeit noch nicht veröffentlicht,
- Mehrtagesevent ohne Enddatum,
- abgelaufenes oder abgesagtes Event,
- schwache beziehungsweise widersprüchliche Quelle,
- erforderlicher Ticket-/Anmeldelink fehlt,
- harte Dublette und eigenständiger ähnlicher Termin,
- sicherer Visual-Fallback,
- Motiv ohne ready Asset mit Gap-Erzeugung und Kandidatenrückführung,
- außerhalb des lokalen Scopes.

Für jeden Fall gilt:

```text
Befund
→ direkte Aufgabe
→ Persistenz
→ Folgeprozess
→ Rückleseprüfung
→ neue Bewertung
→ korrekter Fall- und UI-Endzustand
```

## Schutzregeln

- Kein CityArt-Einzelhotfix.
- Kein vollständiges Formular als normaler Blockerpfad.
- Kein Erfolg nur aufgrund eines HTTP-`ok`.
- Kein beliebiger Freitext als Ersatz für typisierte Zeit- oder Zugangslogik.
- Keine manuelle Bilderzeugung durch den Betreiber.
- Kein automatischer finaler Bildtausch ohne konkrete Bestätigung.
- Keine Änderung oder Veröffentlichung auf `main`.
- Kein Merge vor grüner CI und realer Staging-Abnahme.
