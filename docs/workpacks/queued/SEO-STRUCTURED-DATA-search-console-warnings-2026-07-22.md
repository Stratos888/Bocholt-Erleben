# Queued Workpack: SEO Structured Data – Search-Console-Warnungen

Stand: 2026-07-22  
Status: eingeplant, nicht aktiv  
Risikoklasse: R2

## Einordnung

Dieser Workpack ist ein eigenständiger, eng begrenzter Folge-Workpack. Er öffnet den abgeschlossenen Workpack

- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

nicht erneut.

Der vorherige Workpack hat Search-Intent, statisches Rendering, Event-Detailseiten und den wahrheitsgetreuen Offer-Vertrag technisch und im Livebetrieb abgeschlossen. Der neue Workpack behandelt ausschließlich die danach in Google Search Console sichtbar gebliebenen Warnungen zu strukturierten Eventdaten.

Diese Datei enthält den dauerhaften Scope, Wahrheitsvertrag und die Definition of Done. Operativer Status, Entscheidungen und Evidence stehen ausschließlich in GitHub-Issue **#165**.

## Erster Test des vereinfachten Arbeitsmodells

Für diesen Workpack gilt verbindlich:

```text
Chat führt
-> Gate A vollständig read-only
-> Work nur bei mindestens zwei belegten unabhängigen Liefersträngen
-> falls Patch nötig: ein Codex-Task, ein Feature-Branch, ein PR nach staging
-> ein Staging-Deploy und ein gezielter Smoke
-> ein Release-PR nach main und ein Live-Smoke
-> operativer Status ausschließlich in Issue #165
```

Zusätzlich:

- ein großer einzelner Repository-Patch aktiviert Work nicht;
- Codex ist der einzige Repository-Schreiber;
- Korrekturen vor dem Staging-Merge bleiben im selben Task, Branch und PR;
- nach dem Staging-Merge ist höchstens eine klar begründete Korrektur zulässig;
- der Nutzer wird nur für private Search-Console-Evidence oder echte fachliche Entscheidungen benötigt;
- Repository-Dokumentation wird während des Workpacks nicht fortlaufend geändert;
- dauerhaftes Wissensdelta wird genau einmal am Ende dokumentiert.

## Belegter Auslöser

Google Search Console zeigte am 2026-07-22 unter **Verbesserungen -> Ereignisse** folgende nicht kritische Warnungen:

| Warnung | betroffene Elemente |
|---|---:|
| Feld `performer` fehlt | 2 |
| Feld `organizer` fehlt | 2 |
| Feld `priceCurrency` fehlt in `offers` | 1 |
| Feld `validFrom` fehlt in `offers` | 1 |
| Feld `price` fehlt in `offers` | 1 |
| Feld `offers` fehlt | 1 |

Als konkret angezeigte Beispiel-URL ist bisher belegt:

- `https://bocholt-erleben.de/events/2-bocholter-vereinsmesse-in-den-shopping-arkaden-2026-09-27/`

Die Search-Console-Zahlen können historischen Crawlstand und mehrere Warnungen derselben URL enthalten. Aus ihnen folgt noch nicht, dass der aktuelle Live-Code fehlerhaft ist.

## Ziel

Für jede Search-Console-Warnung und jede tatsächlich betroffene URL wird belastbar entschieden, ob sie

1. nur einen historischen Google-Stand zeigt;
2. im aktuellen Live-Markup bereits behoben ist;
3. wegen eines optionalen, unbekannten Feldes bewusst bestehen bleiben muss;
4. auf eine behebbare technische Mapping- oder Renderinglücke zurückgeht;
5. auf eine reale, quellengebundene Datenlücke zurückgeht; oder
6. bedeutet, dass auf der betreffenden Seite kein Event-JSON-LD ausgegeben werden darf.

Nur belegte technische oder datenvertragliche Fehler werden korrigiert. Es werden keine Angaben erfunden, nur um Warnungen zu entfernen.

## Wahrheitsvertrag

Folgende Werte dürfen nur bei belastbarer Quelle erscheinen:

- `performer`;
- `organizer`;
- `offers`;
- `price`;
- `priceCurrency`;
- `validFrom`;
- Ticket-URL;
- Verfügbarkeit.

Insbesondere gilt:

- ein fehlender Künstler wird nicht aus Titel, Bild oder Vermutung abgeleitet;
- ein fehlender Veranstalter wird nicht pauschal aus Veranstaltungsort, Quelle oder Stadtbezug erzeugt;
- eine allgemeine Informations- oder Quellen-URL wird nicht automatisch zur Ticket-URL;
- Preis und Währung müssen gemeinsam quellenbelegt sein;
- `validFrom` wird nur bei belegtem Verkaufsstart ausgegeben;
- fehlende empfohlene Felder dürfen bewusst fehlen bleiben;
- unvollständige Eintrittslage erzeugt kein synthetisches Offer;
- unzureichend belegte Eventdaten führen im Zweifel zu einer normalen indexierbaren HTML-Seite ohne Event-JSON-LD.

## Gate A – ausschließlich read-only

Vor jeder Repository- oder Datenänderung vollständig im Issue #165 dokumentieren:

1. aktuellen `staging`-SHA, `main`-SHA und offene Pull Requests;
2. vollständigen Search-Console-Export aller sechs Warnungsklassen;
3. alle betroffenen URLs je Warnung, einschließlich Mehrfachzuordnung derselben URL;
4. Zeitpunkt des letzten Google-Crawls beziehungsweise der letzten Erkennung, soweit sichtbar;
5. Live-URL-Test jeder betroffenen URL;
6. tatsächlich ausgeliefertes JSON-LD aus dem Live-Seitenquelltext;
7. sichtbare Seiteninhalte, insbesondere Eintritt, Preis, Veranstalter, Künstler und Ticketweg;
8. kanonische Quelldaten und deren Herkunft;
9. aktuellen Mappingfluss von Quelldaten über Build bis Eventdetailseite;
10. zuständige Owner für Datenvertrag, Eventbuilder, Detailseitenbuilder und Tests;
11. Klassifizierung jeder Warnung nach der folgenden Entscheidungsmatrix;
12. minimalen Scope, Evidence und Rollback nur für tatsächlich belegte Fehler.

Bis Gate A geschlossen ist:

- kein schreibender Codeauftrag;
- keine Änderung an Event- oder Sheetdaten;
- keine pauschale Search-Console-Validierung aller Warnungen;
- kein erneuter Umbau von Startseite, statischem Rendering oder Auswahl;
- kein künstliches Auffüllen empfohlener Schemafelder.

## Entscheidungsmatrix

| Befund | Konsequenz |
|---|---|
| Google zeigt nur historisches Markup; aktueller Live-Test ist korrekt | Validierung für genau diesen Befund starten; kein Patch |
| aktuelles Markup ist korrekt, empfohlenes Feld aber unbekannt | Warnung bewusst akzeptieren und dokumentieren |
| Quelldaten sind belegt, werden aber nicht ausgegeben | minimaler Mapping-/Buildfix mit Contracttest |
| Quelldaten fehlen, können aber über eine belastbare kanonische Quelle automatisiert übernommen werden | separaten minimalen Datenvertragsfix prüfen |
| Quelldaten fehlen und sind nicht belastbar beschaffbar | nichts ergänzen; Warnung bewusst bestehen lassen |
| Event-JSON-LD ist wegen unvollständiger Kerndaten nicht vertretbar | Event-Markup für diese Seite fail-closed unterdrücken |
| sichtbare Seite und JSON-LD widersprechen sich | technischer Fehler; vor Google-Validierung korrigieren |

## Abnahmevertrag vor einem Codex-Auftrag

Gate A muss vor jedem Write einen geschlossenen Abnahmevertrag liefern:

1. exakter fachlicher Fehler;
2. Nicht-Ziele und gesperrter Scope;
3. zulässige sichtbare Änderungen;
4. unveränderte Seiten und Datenpfade;
5. owning Dateien und Komponenten;
6. konkrete Positiv- und Negativtests;
7. Evidence aus dem tatsächlichen Build- und Live-Pfad;
8. Definition of Done;
9. Rollback.

Ohne geschlossenen Vertrag bleibt der Workpack read-only. Falls kein technischer Fehler belegt ist, gibt es keinen Codex-Auftrag.

## Voraussichtliche Owner

Nur nach Gate-A-Befund und nur soweit tatsächlich betroffen:

- `scripts/event_public_contract.py`;
- `scripts/event_builder.py`;
- `scripts/build-event-detail-pages.py`;
- `api/events/public.php`;
- kanonische Eventfelder für Organizer, Performer, Eintritt und Ticketangebote;
- `tests/test_event_offer_contract.py`;
- `tests/test_event_detail_schema_contract.py`;
- ergänzende kleine Fixturetests, falls eine bislang unbelegte Fehlerklasse vorliegt.

## Gesperrter Scope

- keine Änderung an `/`, `/events/` oder `/aktivitaeten/` außerhalb eines konkret belegten Detailseitenvertrags;
- keine neue Ranking-, Empfehlungs- oder Personalisierungslogik;
- keine UI-Neugestaltung;
- keine allgemeine SEO-Massenrunde;
- keine künstlichen Defaultwerte für Organizer, Performer, Preis, Währung, `validFrom`, Availability oder Ticket-URL;
- kein neues SSR- oder Schemaframework;
- keine breite manuelle Nachpflege aller Events ohne belegten Prozessbedarf;
- keine Search-Console-Erfolgsbehauptung allein aufgrund eines grünen Deploys.

## Akzeptanz

### E1

- vollständige URL-/Warnungsmatrix;
- aktuelles Live-JSON-LD je betroffener URL;
- Vergleich mit sichtbarem Seiteninhalt und kanonischer Quelle;
- Klassifizierung jeder Warnung;
- geschlossener Abnahmevertrag oder belegte Entscheidung `kein Patch`;
- klarer Diff und Rollback für jeden tatsächlich erforderlichen Fix.

### E2

Falls ein Patch nötig ist:

- Contracttests für jede korrigierte Warnungsklasse;
- Positiv- und Negativtests gegen erfundene Werte;
- kostenlose und kostenpflichtige Offers weiterhin quellentreu;
- mehrere Ticketarten weiterhin deckungsgleich;
- unbekannte Felder bleiben fail-closed;
- tatsächlicher Detailseiten-Output wird geprüft, nicht nur Templates;
- vollständiges `PR Gate` grün.

### E3

Auf Staging nur für geänderte Fälle:

- tatsächliches JSON-LD entspricht den belegten Quelldaten;
- sichtbare Seite und Schema stimmen überein;
- keine Regression anderer Eventdetailseiten;
- normaler Deploy-, HTTP- und Browser-Smoke grün;
- nur ein gezielter Staging-Smoke außerhalb der regulären Deployprüfung.

### E6

Nach regulärem Release:

- read-only Live-URL-Test der korrigierten beziehungsweise bewusst unveränderten Beispiele;
- nur tatsächlich behobene oder historisch veraltete Search-Console-Befunde zur Überprüfung einreichen;
- Validierungsstatus zeitversetzt beobachten;
- optionale, fachlich unbehebbare Warnungen ausdrücklich im Issue dokumentieren.

## Definition of Done

- alle sechs Warnungsklassen sind vollständig auf URLs aufgelöst;
- jede betroffene URL besitzt einen aktuellen Live- und Quellenbefund;
- jede Warnung ist eindeutig als historisch, bewusst akzeptiert, Datenlücke oder technischer Fehler klassifiziert;
- keine synthetischen Organizer-, Performer-, Preis-, Währungs-, `validFrom`-, Verfügbarkeits- oder Ticketwerte;
- notwendige Korrekturen sind über E1, E2, E3 und E6 belegt;
- nur sachlich geeignete Search-Console-Validierungen wurden gestartet;
- verbleibende Warnungen sind begründet und kein stiller Qualitätsrest;
- Issue #165 enthält finalen Status, Entscheidungen, Evidence und nächsten Zustand;
- der abgeschlossene SEO-Recovery-Workpack bleibt geschlossen;
- die 14-/28-Tage-Rankingmessung bleibt getrennt;
- dauerhaftes Wissensdelta wurde höchstens einmal am Ende dokumentiert.

## Rollback

Technische Änderungen werden ausschließlich über den normalen Pfad

```text
Feature-Branch -> staging -> main
```

integriert und können über den jeweiligen Merge-Commit revertiert werden. Search-Console-Validierungen werden erst nach bestätigtem Live-Befund gestartet und sind kein Ersatz für einen technischen Rollback.

## Aktivierung

Der Workpack wird ohne Repository-Statuscommit aktiviert:

1. Nutzer startet den Workpack ausdrücklich im neuen Chat;
2. Issue #165 wird von `QUEUED` auf `ACTIVE` gesetzt;
3. Gate A läuft ausschließlich read-only;
4. das Issue bleibt der einzige operative Status- und Evidence-Owner.

Work wird nur nach einem belegten Gate-A-Befund aktiviert, der mindestens zwei unabhängige Lieferstränge oder mehrere getrennte Codex-Aufträge erfordert.

## Genau nächster Schritt bei Aktivierung

Im neuen Chat `AI_ENTRYPOINT.md`, `CURRENT_WORKPACK.md`, Issue #165 und diese Datei lesen. Danach den aktuellen Repositoryzustand prüfen und den vollständigen Search-Console-Export mit allen betroffenen URLs gegen Live-JSON-LD, sichtbaren Seiteninhalt und kanonische Quelldaten stellen.