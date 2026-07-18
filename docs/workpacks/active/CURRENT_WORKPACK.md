# Current Workpack

Stand: 2026-07-18

Diese Datei ist der einzige operative technische Projektstatus. Ein neuer KI-Chat liest sie direkt nach `AI_ENTRYPOINT.md`.

## Aktiver Workpack

- **Aktive Implementierung:** keine
- **Letzter abgeschlossener Workpack:** WP-2 – Runtime-Truth und read-only Preflight
- **Abschlussstand:** E3 erreicht
- **Belegter Staging-Build:** `42d555bd76a3`
- **Deploy-Status:** `deploy/staging-observed` erfolgreich
- **Runtime-Status:** `control-center/runtime-preflight-e3` erfolgreich
- **Evidence:** `docs/evidence/control-center-runtime-preflight-e3-2026-07-18.md`
- **Nächster geplanter Workpack:** isolierter synthetischer E4-Test und Wiederaufnahmenachweis
- **Status des nächsten Workpacks:** nicht gestartet; benötigt ein neues ausdrückliches Arbeitsmandat

## Belegter Abschlusszustand von WP-2

Der reale STRATO-Staging-Build und der geschützte Runtime-Preflight bestätigen für den eingefrorenen CityArt-Fall:

- Buildmarker und Deploymanifest: `42d555bd76a3`;
- Host: `staging.bocholt-erleben.de`;
- Endpoint: `/api/control-center/preflight.php`;
- konfigurierte und aufgelöste Umgebung: `staging`;
- Quelltab: `Inbox_Staging`;
- Zieltab: `Events_Staging`;
- Writer: `be_cc_writeback_staging_inbox_approve_verified`;
- Live-Inbox: `not_used`;
- Live-Events: `not_used`;
- CityArt-Quelle: eindeutig read-only aufgelöst;
- Eventziel: read-only als noch nicht vorhanden aufgelöst;
- Blocker: keine;
- Mutation: `false`.

Das E3-Artifact enthält keine Credentials, Tokens, Service-Account-Daten oder vollständige Google-Sheet-ID.

## Behobener Deploy-Blocker

Der erste E3-Versuch zeigte zusätzlich, dass STRATO-Staging noch auf Build `3b5795f07771` stand. Der exakt beobachtete Deploy-Run scheiterte beim Export des umgebungssicheren Eventfeeds.

Read-only Prüfung des führenden Sheets belegte:

- `Events` und `Events_Staging` besitzen denselben kanonischen Header;
- `Events_Staging` ist korrekt header-only;
- der Basisbestand besitzt keine doppelte ID;
- 15 Quellen-URLs werden legitim von mehreren Event-IDs genutzt.

Der Overlay-Merger hatte diese gemeinsamen Basis-URLs pauschal abgelehnt. PR #99 erlaubt sie jetzt bei weiterhin kanonisch eindeutigen IDs. Mehrdeutige Overlay-Auflösung ohne passende ID bleibt fail-closed. Der anschließende Staging-Deploy war erfolgreich.

## Entscheidung nach E3

Ein vorsorglicher allgemeiner WP-3-Writeback-Umbau ist derzeit nicht belegt.

Der ausgewählte Staging-Writer ist bereits:

- auf `Inbox_Staging -> Events_Staging` isoliert;
- bei Event-ID und Zielzeile fail-closed;
- auf Event-Ebene idempotent;
- mit Rücklesen und Fingerprint-Prüfung des Eventziels abgesichert;
- mit Rücklesen und Prüfung des terminalen Inboxstatus abgesichert;
- vor dem lokalen Fallabschluss an bestätigte Quellpostconditions gebunden.

Die verbleibende offene Evidence ist ein realer technischer Staging-Write mit synthetischen Daten, insbesondere die Wiederaufnahme nach einem kontrollierten Teilfehler zwischen Event- und Inbox-Schritt.

Daher gilt:

- kein WP-3-Code vor E4;
- E4 testet Erfolg, Teilfehler, Wiederaufnahme, Duplikatschutz, Cleanup und Live-Unverändertheit;
- nur bei einer konkret belegten E4-Lücke wird anschließend ein minimaler WP-3-Fix geschnitten.

## Aktueller Freeze

Bis E4 ausdrücklich gestartet und erfolgreich abgeschlossen ist:

- kein echter CityArt-Klick;
- keine manuelle Statuskorrektur;
- keine Mutation des CityArt-Falls in `Inbox_Staging` oder `Events_Staging`;
- keine Mutation in `Inbox` oder `Events`;
- kein Live-Schreibtest;
- kein allgemeiner Writeback-Umbau;
- Ressourcen-Lock auf Inbox-/Events-Writeback bleibt bestehen.

Sicherer Datenzustand:

- `Inbox_Staging`: CityArt bleibt `review`;
- `Events_Staging`: kein CityArt-Eintrag;
- `Events`: kein CityArt-Eintrag aus einem Staging-Versuch;
- Live wurde nicht verändert.

## Nächster erlaubter Schritt

Nur nach neuem Nutzerauftrag:

### E4 – isolierter synthetischer Staging-Write und Wiederaufnahmenachweis

Pflichtumfang:

1. eindeutig benannter synthetischer Testkandidat;
2. dokumentierter Vorherzustand aller Staging- und Live-Ressourcen;
3. read-only E3-Preflight für den Testkandidaten;
4. erfolgreicher Gesamtwrite nach `Inbox_Staging -> Events_Staging`;
5. kontrollierter Teilfehler nach bestätigtem Event-Write;
6. sichere Wiederaufnahme ohne doppelte Eventzeile;
7. Rücklesen von Event, Inboxstatus und lokalem Fallstatus;
8. Prüfung des generierten Staging-Feeds;
9. vollständiges Cleanup;
10. Nachweis, dass `Inbox`, `Events` und Live unverändert blieben.

Kein echter Fachfall vor erfolgreichem E4.
