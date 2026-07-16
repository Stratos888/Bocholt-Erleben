# Steuerzentrale – End-to-End-Zustandskonsistenz

Stand: 2026-07-16  
Zielbranch: `staging`

## Anlass

Die Ablehnung der „2. Bocholter Vereinsmesse“ wurde zuletzt korrekt in der führenden Sheet-Inbox gespeichert (`verworfen`, `Dublette`), blieb aber als lokaler Prüffall sichtbar. Zuvor waren weitere Symptome einzeln korrigiert worden: falscher Statuswert, fehlende Rückleseprüfung, lange serielle Google-Aufrufe und veraltete Browsermodule.

Diese Folge zeigte, dass kein einzelner Buttonfehler vorlag. Es fehlte ein durchgängiger Zustandsvertrag zwischen:

1. führender Quelle,
2. lokalem `control_cases`-Zustand,
3. aktiver API-Ausgabe,
4. Frontend-Sichtbarkeit,
5. Reload und Idempotenz.

## Warum frühere Analysen die Fehler nicht vollständig fanden

Die bisherigen Prüfungen waren überwiegend komponentenbezogen:

- Writeback-Funktion schreibt den erwarteten Wert.
- Rücklesefunktion erkennt diesen Wert.
- lokale Transition besitzt einen gültigen Zielstatus.
- Frontend sendet eine typisierte Entscheidung.

Damit wurden jeweils Teilverträge geprüft, jedoch nicht die entscheidende Gesamtinvariante:

> Eine Aktion ist erst erfolgreich, wenn die führende Quelle den Zielzustand bestätigt, der lokale Fall den korrespondierenden Zustand besitzt, die aktive API den Fall korrekt ein- oder ausblendet und die neu geladene Oberfläche exakt diesen Zustand zeigt.

Zusätzliche systemische Ursachen:

- Quellen synchronisierten nur offene Datensätze; abgeschlossene Quellzustände schlossen vorhandene lokale Fälle nicht.
- Quellen und Fallliste wurden beim Laden parallel abgefragt. Dadurch konnte die Fallliste den alten Zustand liefern, obwohl die Synchronisation im selben Ladevorgang bereits lief.
- Reload-Fehler wurden im Frontend angezeigt, aber nicht an die auslösende Aktion zurückgegeben.
- Zukünftige Wiedervorlagen galten im Frontend weiterhin als aktive Prüffälle.
- Sheet-Inbox, Anbieter-Einreichungen und Content-Audit nutzten unterschiedliche Transport-, Timeout- und Verifikationslogiken.
- Cache-Versionen wurden nur entlang einzelner Modulpfade angehoben.
- Grüne Teiltests wurden zu früh als End-to-End-Nachweis interpretiert.

## Kanonischer Zustandsvertrag

### Führende Sheet-Inbox

| Quellstatus | Lokaler Zielzustand | Prüfliste |
|---|---:|---:|
| `review`, `open`, `new`, `pending` | `decision_required` | sichtbar |
| `später prüfen` | `snoozed` bzw. lokaler Wiedervorlagezustand | bis Fälligkeit unsichtbar |
| `übernommen` | `done` | unsichtbar |
| `verworfen` | `rejected` | unsichtbar |

Der historische Fehlwert `verwerfen` wird ausschließlich als terminaler Kompatibilitätsalias behandelt.

### Anbieter-Einreichungen

| Quellstatus | Lokaler Zielzustand | Prüfliste |
|---|---:|---:|
| `pending_review` | `decision_required` | sichtbar |
| `payment_released`, `checkout_started` | `waiting` | als Prozessfall sichtbar |
| `paid`, `in_review` | `decision_required` | sichtbar |
| `approved`, `published` | `done` | unsichtbar |
| `rejected` | `rejected` | unsichtbar |
| `cancelled`, `withdrawn`, `expired` | `done` | unsichtbar |

### Content-Audit

| Quellstatus | Lokaler Zielzustand | Prüfliste |
|---|---:|---:|
| offene relevante Befunde | `decision_required` | sichtbar |
| `snoozed` | `snoozed` | bis Fälligkeit unsichtbar |
| `verified`, `corrected`, `resolved`, `archived` | `done` | unsichtbar |
| `ignored` | `rejected` | unsichtbar |

## Umgesetzter Zielprozess

### Quelle → lokaler Fall

Jede Synchronisation verarbeitet nicht nur offene Zeilen, sondern reconciliiert terminale Quellzustände gegen bereits vorhandene lokale Fälle. Die Quelle bleibt fachlich führend. Ein lokal veralteter Fall wird bei jedem Laden automatisch repariert.

### Aktion → bestätigter Endzustand

Eine redaktionelle Aktion liefert nur Erfolg, wenn:

1. der Quell-Writeback bestätigt wurde,
2. der lokale Zielzustand gespeichert wurde,
3. der lokale Fall zurückgelesen wurde,
4. seine Prüflisten-Sichtbarkeit zum Zielzustand passt.

Die API-Antwort enthält dafür einen expliziten `completion`-Nachweis.

### Reload → sichtbare Konsistenz

Beim Laden gilt jetzt eine feste Reihenfolge:

1. Quellen synchronisieren und reconciliieren,
2. danach ausschließlich aktive Fälle lesen,
3. anschließend Oberfläche rendern.

Ein Reload-Fehler wird nicht mehr verschluckt. Das Dialogfenster schließt erst, wenn die neue Fallliste den vom Server bestätigten Endzustand widerspiegelt.

### Wiedervorlage

Ein zukünftiger `snoozed`-Fall ist nicht Teil der Prüfliste. Nach Erreichen des Wiedervorlagedatums wird er wieder sichtbar. Diese Regel gilt serverseitig und im gemeinsamen Frontendzustand.

### Begrenzte Quellzugriffe

Redaktionelle Sheet-Writebacks verwenden einen gemeinsamen begrenzten Transport:

- maximal acht Sekunden je Google-Aufruf,
- Batch-Schreiben statt vieler Einzelupdates,
- maximal zwei Rückleseversuche,
- Pflichtprüfung aller geschriebenen Felder.

Die Inbox-Übernahme nutzt weiterhin den notwendigen Veröffentlichungsdienst, aber ebenfalls mit harter Zeitgrenze und anschließender Sheet-Verifikation.

## Geprüfte Aktionsketten

| Quelle | Aktion | Quellnachweis | lokaler Nachweis | UI-Nachweis |
|---|---|---:|---:|---:|
| Sheet-Inbox | übernehmen | ja | `done` | Fall entfernt |
| Sheet-Inbox | ablehnen | ja | `rejected` | Fall entfernt |
| Sheet-Inbox | zurückstellen | ja | `snoozed` | bis Fälligkeit entfernt |
| Submission | Zahlung freigeben | `payment_released` | `waiting` | Prozessfall konsistent |
| Submission | final freigeben | `approved` | `done` | Fall entfernt |
| Submission | ablehnen | `rejected` | `rejected` | Fall entfernt |
| Submission | zurückstellen | Quellstatus unverändert bestätigt | `snoozed` | bis Fälligkeit entfernt |
| Content-Audit | bestätigen | `verified` | `done` | Fall entfernt |
| Content-Audit | korrigieren | Event- und Auditfelder bestätigt | `done` | Fall entfernt |
| Content-Audit | ignorieren | `ignored` | `rejected` | Fall entfernt |
| Content-Audit | zurückstellen | `snoozed` | `snoozed` | bis Fälligkeit entfernt |
| Systemfall | neu prüfen | Prüfquelle bestätigt | `done` | Fall entfernt |

## Verbindliche CI-Invarianten

Der neue Zustandskonsistenztest prüft gemeinsam:

- Statusmatrix aller drei Quellen,
- lokale Zielzustände aller Aktionen,
- Sichtbarkeit terminaler und zurückgestellter Fälle,
- Reconciliation in jeder Quellkette,
- serielles Laden von Synchronisation und aktiver Fallliste,
- verifizierte Quell- und lokale Postconditions,
- fehleroffenen Reload,
- vollständige Modul- und Cache-Versionierung,
- begrenzte Batch-Writebacks.

Diese Prüfung läuft sowohl in der allgemeinen Control-Center-Validierung als auch in den redaktionellen Vertragsgates.

## Abnahmekriterien vor Main

- keine produktiven Schreibtests durch CI,
- alle PHP- und JavaScript-Syntaxprüfungen grün,
- alle bestehenden Produkt-, Sicherheits-, CSS- und Architekturverträge grün,
- neuer Zustandskonsistenzvertrag grün,
- reale Staging-Quelle wird ohne manuellen Eingriff reconciliiert,
- die bereits verworfene Vereinsmesse verschwindet nach Staging-Reload aus der aktiven Prüfliste,
- kein Main-Merge ohne diesen Staging-Nachweis.

## Realer Staging-Nachweis

Der Nachweis wurde am 2026-07-16 auf dem Staging-Stand `bba370093f83bd2190ead0fb2f8f605c46c047f5` erbracht:

1. Die führende Sheet-Zeile der Vereinsmesse war bereits terminal gespeichert.
2. Die Staging-Steuerzentrale wurde neu geladen.
3. Die Quelle wurde vor der aktiven Fallabfrage synchronisiert.
4. Der lokale Altfall wurde automatisch auf den terminalen Zielzustand reconciliiert.
5. Der Eintrag war anschließend nicht mehr in der offenen Prüfliste sichtbar.

Damit ist die konkrete Zustandsfehlerklasse auf `staging` fachlich abgenommen. Die Zustandskonsistenz ist nicht länger ein offener Bestandteil des nächsten UX-Workpacks.

## Release- und Workpack-Grenze

PR `#77` bleibt vorerst ausschließlich auf `staging`. Der reale Nachweis erlaubt grundsätzlich einen späteren Release, aber der Main-Merge wird bewusst zurückgestellt, bis das nachfolgende Premium-Workpack zur Eventprüfung abgeschlossen ist.

Die nächste offene Fehlerklasse betrifft nicht mehr Quelle-versus-lokaler-Fall, sondern die fachliche Nutzerführung innerhalb eines offenen Eventkandidaten:

- technische Blocker ohne direkt ausführbare Fachaktion,
- fehlende Kennzeichnung bereits geprüfter Felder,
- kein typisierter Zeitstatus,
- Motivbestätigung ohne konkrete Asset-Bindung,
- Visual-Gaps ohne geschlossenen Rückführungsprozess,
- Vollformular statt ausnahmebasierter Aufgaben.

Kanonische Fortsetzung:

`docs/steuerzentrale-naechstes-workpack-ausnahmebasierte-eventpruefung-2026-07-16.md`

Der nächste Chat darf die mit diesem Dokument bestätigte Zustandsarchitektur nicht erneut symptomatisch umbauen. Er muss auf ihr aufsetzen und die fachlichen Event-Ausnahmen jeweils bis zum verifizierten Endzustand schließen.
