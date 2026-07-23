# Aktueller Proofindex – Bocholt erleben

Stand: 2026-07-23

Diese Datei enthält nur den aktuell relevanten Proofstand. Ausführliche Historie liegt in Git und den jeweiligen Action-Artefakten.

## Evidence-Legende

| Stufe | Bedeutung |
|---|---|
| E1 | aktueller Code oder Diff |
| E2 | automatisierter lokaler/CI-Test |
| E3 | deployter read-only Nachweis |
| E4 | begrenzter realer Write mit Rücklesen und Cleanup |
| E5 | echter fachlicher Staging-Fall |
| E6 | read-only Live-Smoke |

## Aktueller Proofstand

| Bereich | Evidence | Status |
|---|---:|---|
| Branch- und Deployrouting | E2 | nur `staging` und `main` dürfen deployen; Releasepfad `Feature-Branch -> staging -> main` |
| PR-Integration | E2 | ein Required Check `PR Gate` führt die Repositorytests aus |
| Geschützte Deploy-Environments | E2 | Deployjob nutzt abhängig vom Branch ausschließlich `staging` oder `main`; Branchregeln sind aktiv; `GITHUB_TOKEN` ist auf `contents: read` begrenzt |
| Staging-Deploy | E3 | normaler Deploy über Environment `staging` einschließlich Build-, HTTP- und Browser-Smoke erfolgreich |
| Staging-Secret-Isolation | E3 | `STAGING_REVIEW_PASSWORD` liegt ausschließlich im Environment `staging`; nach Löschen des gleichnamigen Repository-Secrets blieben Deploy und Login erfolgreich |
| Live-Deploy | E6 | Structured-Data- und Eventdarstellungsabschluss über PRs #168 bis #170 veröffentlicht; Main-SHA `eb5e0f87199d03879d8ae62085e2ae7a52bdf252`; normaler Main-Deploy grün |
| Branch-Inhaltsgleichheit | E1 | `main` und `staging` besitzen denselben Dateiinhalt; Unterschiede bestehen nur in der Merge-Historie |
| SEO Intent- und Renderingvertrag | E2 | gemeinsame neutrale Auswahl, Berliner Kalendertag, Mehrtagesevents, Ausschluss vergangener/ungültiger Inhalte, stabile Sortierung, echte Renderer-Fixtures und fail-closed leere Datenkerne im `PR Gate` |
| SEO Stagingabschluss | E3 | Staging-SHA `2ee2990bb06ee03ac8248e47150bb12de8a1c74e`; PR Gate #219 und normaler Deploy grün; mobile Sichtprüfung 327 × 779 Pixel grün; keine zusätzlichen Hero-Zeilen oder CTA-Regression |
| Statischer Startseitenkern live | E6 | Live-Seitenquelltext enthält `data-static-event-context`, `Alle Events ansehen` und `Aktivitäten entdecken` jeweils genau einmal; der statische Kern und beide Hauptlinks werden tatsächlich ausgeliefert |
| Event-/Offer-Vertrag | E2 | Quellen- und Ticket-URLs getrennt; kostenlose Offers `0 EUR`; kostenpflichtige Offers nur mit Preis, Währung und Ticket-URL; ungültige Preise, URLs, Availability und `validFrom` werden verworfen |
| Event-Schema und Detailseiten | E2 | Event-JSON-LD nur auf geeigneten eindeutigen Detailseiten; mehrere Ticketarten sichtbar und schema-deckungsgleich; unbekannte Eintrittslage bleibt indexierbare HTML-Seite ohne synthetisches Event-Markup |
| Sammelseiten-Schema | E2 | `/events/` und andere Sammelseiten geben keine einzelnen Event-Entitäten als Ersatz für Detailseiten aus |
| Robots, Sitemap und Canonical | E2/E3 | umgebungsabhängige Robots- und Sitemaptemplates, Canonical-Contracts und Ergänzung generierter Eventdetailseiten sind im Deploypfad abgesichert |
| Structured-Data-Reparatur | E1/E2/E6 | PRs #168 bis #170 schließen quellengestützte Eventdaten-, Detailseiten-, Offer- und Navigationsverträge; veröffentlicht mit Main-SHA `eb5e0f87199d03879d8ae62085e2ae7a52bdf252` |
| Arbeitsprozess-Härtung #172 | E1 | in Umsetzung; lokale und CI-Evidence dürfen erst nach grünem Contract- und PR-Gate als E2 gelten |
| SEO-Wirkungsmessung | ausstehend | erste Tendenz nach mindestens 14 Tagen, führende Bewertung nach 28 Tagen; Impressionen, Klicks, CTR und Position getrennt bewerten |
| Control-Center-Writeback | E4 | Success, Replay, kontrollierter Fehler, Resume und Cleanup synthetisch belegt |
| Live-Unverändertheit beim E4 | E4 | Live-Sheet und Live-Feed blieben unverändert |
| Event-Builder-Kompatibilität | E2 | eine vom echten Control-Center-Writer erzeugte Zeile mit `11:00–18:00 Uhr` wird vom normalen Event-Builder vollständig verarbeitet |
| Event-Identität und Dubletten-Preflight | E2 | gemeinsamer Python-/PHP-Vertrag; CityArt-Semantik, Shared-URL, getrennte Programmpunkte, ID-Konflikt, Same-ID-Resume, Staging-Overlay und Approval-Wiring sind im `PR Gate` grün |
| echter Control-Center-Fachfall | nicht erforderlich | kein echter Fall wurde für den technischen Nachweis verändert |
| Weekly-KI-/Inbox-Betrieb | laufender Betrieb | anhand der jeweiligen fachlichen Action-Läufe bewerten |

## SEO-Abschlussgrenze

Der Produkt-Workpack **SEO Recovery – Search Intent und statische Renderingbasis** ist mit E1, E2, Staging-E3 und Live-E6 abgeschlossen. Die neue Search-Console-Warnungsliste ist ein eigenständiger, noch nicht aktiver Folge-Workpack und öffnet den abgeschlossenen Workpack nicht erneut.

Die 14-/28-Tage-Rankingmessung bleibt eine zeitversetzte Betriebsaufgabe. Aus dem technischen Abschluss wird keine bereits eingetretene Rankingverbesserung abgeleitet.

Der parallel geprüfte Werkzeugsteuerungs-Pilot war nur teilweise erfolgreich: Zielableitung, Werkzeugaufteilung und PR-Gates funktionierten, die Kriterien „keine Grundsatzkorrektur“ und „keine reale Try-and-Error-Schleife“ wurden wegen der visuellen Linkkorrekturen nicht erreicht.

## E4-Ergebnis

Der synthetische Lauf für Build `6702fd5c12a0` bestätigte:

- `Inbox_Staging -> Events_Staging`;
- genau ein Success-Event trotz Replay;
- fail-closed Retry der fehlgeschlagenen Operation;
- erfolgreiche Wiederaufnahme ohne Duplikat;
- zwei gelöschte Inboxzeilen, zwei gelöschte Eventzeilen, zwei gelöschte Cases und drei gelöschte Operationszustände;
- keine synthetischen Reste;
- unveränderte Live- und Nicht-Testdaten.

Der damalige gekoppelte Feed-Build scheiterte an der unterschiedlichen Zeitformat-Akzeptanz von Control-Center-Writer und Event-Builder. Der lokale E2-Contract schließt diese Lücke dauerhaft; der E4-Writeback-Beweis wird nicht wiederholt.

## Pflege

`TEST_STATUS.md` wird nur geändert, wenn sich ein relevanter Proofstand oder eine konkrete Evidence-Lücke ändert. Keine kompletten Logs, Patchchroniken oder Prozessinventuren hier ablegen.
