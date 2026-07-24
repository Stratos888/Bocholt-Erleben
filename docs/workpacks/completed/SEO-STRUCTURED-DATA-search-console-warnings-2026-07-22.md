# Completed Workpack – SEO Structured Data und Search-Console-Warnungen

Abgeschlossen: 2026-07-23  
Operativer Evidence-Owner: GitHub-Issue #165

## Ziel

Search-Console-Warnungen zu `performer`, `organizer`, `offers`, `price`, `priceCurrency` und `validFrom` wurden URL-genau gegen aktuellen Live-Output, sichtbaren Seiteninhalt und kanonische Quelldaten geprüft.

## Dauerhafter Wahrheitsvertrag

- Organizer und Performer nur aus belastbarer Quelle;
- eine allgemeine Informations- oder Quellen-URL ist keine Ticket-URL;
- Preis und Währung müssen gemeinsam belegt sein;
- `validFrom` nur bei belegtem Verkaufsstart;
- unbekannte optionale Werte bleiben unbekannt;
- unvollständige Eintrittslage erzeugt kein synthetisches Offer;
- unzureichend belegte Eventdaten dürfen ohne Event-JSON-LD als normale HTML-Seite bestehen.

## Ergebnis

- Warnungen wurden als historischer Crawlstand, bewusst akzeptierte optionale Lücke, Datenlücke oder technischer Fehler klassifiziert;
- belegte technische und datenvertragliche Fehler wurden minimal korrigiert;
- Vereinsmesse und Kornmühle wurden auf kanonische Datensätze bereinigt;
- die Zaubershow erhielt die belegte externe Quelle;
- Desktop- und Mobilvertrag, semantischer Dublettenschutz, PR-Gates, Staging-, Browser- und Live-Smokes wurden erfolgreich abgeschlossen;
- es wurden keine Organizer-, Performer-, Preis-, Währungs-, `validFrom`-, Availability- oder Ticketwerte erfunden.

## Release

- Implementierung: PR #168;
- nachhaltige Reparaturen und Regressionstests: PR #169;
- Release: PR #170;
- finaler Workpack-Stand und vollständige Evidence: Issue #165.

## Abschlussgrenze

Eine spätere Google-Neubewertung nach erneutem Crawl ist laufender externer Betrieb und öffnet diesen Workpack nicht erneut. Neue Responsive-, Cache- oder allgemeine SEO-Probleme erhalten einen eigenen Workpack.

Die frühere vollständige Scope-Datei bleibt in der Git-Historie. Diese kompakte Datei enthält nur die dauerhaft relevante Wahrheit.
