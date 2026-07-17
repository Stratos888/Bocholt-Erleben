# Staging-Freigabe schrieb unerwartet in den gemeinsamen Events-Tab

Stand: 2026-07-17
Status: Stop-the-line-Forensik; unbeabsichtigte Mutation vollständig zurückgerollt

## Auslöser

In der Staging-Steuerzentrale wurde der entscheidungsreife CityArt-Fall einmal über „Event übernehmen“ ausgelöst.

Die UI meldete anschließend:

`Inbox-Writeback wurde nicht bestätigt. Inbox-Writeback konnte nicht bestätigt werden: erwartet "übernommen", gefunden "review".`

## Belegter Ist-Zustand direkt nach dem Fehler

- `Inbox_Staging` Zeile 2 blieb auf `status=review`.
- `Inbox_Archive_Staging` enthielt keinen CityArt-Eintrag.
- Der Live-Tab `Inbox` blieb unverändert.
- Der gemeinsame Tab `Events` enthielt jedoch eine neue CityArt-Zeile mit der stabilen ID
  `bocholter-kulturtage-2026-kunstmarkt-cityart-und-kunstlerische-mitmach-stande-fur-kinder-und-jugendliche-2026-08-30`.
- Die erzeugte Events-Zeile war unvollständig: `time` war leer.

## Revisionsnachweis

- vorherige Sheet-Revision: `2487`, geändert 2026-07-17 15:30 Europe/Berlin im Rahmen der kontrollierten Header-Migration;
- fehlerhafte Bot-Revision: `2497`, geändert 2026-07-17 16:18 Europe/Berlin;
- die CityArt-Zeile entstand durch den fehlgeschlagenen Staging-Übernahmeversuch.

## Root Cause

Der Runtime-Pfad löste zwar `Inbox_Staging` korrekt auf, verwendete für die eigentliche Freigabe aber den externen Apps-Script-Dienst mit ausschließlich `row_number`. Der Zieltab beziehungsweise die Zielumgebung wurde nicht übergeben. Der Dienst erzeugte dadurch eine Zeile im gemeinsamen Tab `Events`, bestätigte aber keinen terminalen Writeback in `Inbox_Staging`.

Der Deploy exportierte für `main` und `staging` außerdem denselben Tab `Events`. Damit war der öffentliche Eventbestand auf Staging nicht isoliert.

## Sofortmaßnahmen

- kein zweiter Übernahmeversuch;
- exakte unbeabsichtigte Zeile `Events!A196:K196` unter Integrations-Lock gelöscht;
- Rückleseprüfung: CityArt nicht mehr in `Events` vorhanden;
- `Inbox_Staging` unverändert auf `review`;
- Live-Inbox unverändert.

## Nachhaltiger Zielzustand

- `main/live`: `Inbox -> Events`;
- `staging`: `Inbox_Staging -> Events_Staging`;
- Staging-Deploy exportiert ausschließlich `Events_Staging`;
- Staging-Freigabe erfolgt direkt, idempotent und mit Rückleseprüfung für Eventzeile und Inbox-Status;
- der bestehende Apps-Script-Pfad bleibt ausschließlich Live vorbehalten;
- unbekannte oder nicht isolierte Zieltab-Auflösungen blockieren fail-closed.
