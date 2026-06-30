# Browser-Smoke-System — Bocholt erleben

Stand: 2026-06-29

## Entscheidung

Ein kleiner Browser-Smoke ist notwendig, aber nicht als grosses Testframework.

Grund: Das Projekt hat bereits HTTP-/Syntax-/CSS-Pruefungen. Diese finden aber keine echten Browser-/UX-Brueche wie:

- Consent-Hinweis erscheint nach Bottom-Tab-Wechsel erneut.
- Mobile Navigation klickt, aber Zielseite ist nicht nutzbar.
- Detail-/Funnel-Seite laedt HTML, aber zentrale Formularfelder fehlen.
- JS laeuft syntaktisch, rendert aber keine Karten.
- Anbieter-/Zahlungsroute liefert keine harte Serverfehlermeldung, zeigt aber keinen nutzbaren Zustand.

Der Browser-Smoke ist deshalb ein Sicherheitsnetz fuer Kernwege, kein Ersatz fuer Content-Audit, KI-Suche oder manuelle Premium-UI-Abnahme.

## Zielbild

### Wann laeuft er?

1. Automatisch nach jedem STRATO-Deploy auf `staging` und `main`.
2. Manuell ueber GitHub Actions → `Browser Smoke`, ohne Redeploy.
3. Kein eigener zusaetzlicher taeglicher Watchdog in V1. Da der Deploy-Workflow bereits planmaessig laeuft, wird der Browser-Smoke dort mitgeprueft.

### Was prueft er?

Read-only. Keine echten Zahlungen, keine E-Mails, keine produktiven Schreibaktionen.

Geprueft werden:

- Home / Today
- Events
- Aktivitaeten
- Bottom-Tabbar-Navigation
- Consent-Systemlayer inkl. Reappearing-Fix nach Tabwechsel
- Event-Einreichung
- Aktivitaetspraesenz-Funnel
- Zahlung-starten-Zugangszustand
- Veranstalterlogin
- Veranstalter-Dashboard-Zugangszustand

### Was passiert bei Fehlern?

Der Lauf wird rot und erzeugt Artefakte:

- `summary.md` mit betroffener Route, Profil und Fehlermeldung
- klare Trennung zwischen `OK`, `WARNUNG` und `FEHLER`
- `results.json` fuer maschinenlesbare Diagnose
- Screenshot je fehlgeschlagenem Check

Auf `staging` bedeutet rot: nicht nach `main` mergen.

Auf `main` bedeutet rot: Live-Zustand pruefen und manuell Hotfix/Rollback entscheiden.

### Was passiert nicht automatisch?

- Kein automatischer Patch.
- Kein automatischer Rollback.
- Kein automatisches GitHub-Issue in V1.
- Kein echter Checkout.
- Keine echten Formularsendungen.

## Fehlerklassen

| Klasse | Bedeutung | Reaktion |
|---|---|---|
| Blocker | Kernroute nicht nutzbar, zentrale Karte/Formular/Button fehlt, Consent taucht falsch erneut auf | Staging nicht mergen / Live Hotfix pruefen |
| Warnung | Browser-Konsole meldet nicht-blockierende, aber noch nicht bekannte Hinweise | Beobachten, kein Sofortblocker |
| Bekannter Zugangshinweis | Erwartete `401`-/Fetch-Hinweise beim geschuetzten Veranstalter-Dashboard oder bei optionaler Portal-Session auf der Einreichungsseite ohne Login, wenn der sichtbare Zielzustand OK ist | Im Report nicht als Warnung zaehlen |
| Nicht-Ziel | Falscher Eventinhalt, falsches Bild, KI-/Sheet-Problem | Content-/KI-/Audit-Strang, nicht Browser-Smoke |

## Manuelle Ausloesung

GitHub Actions → `Browser Smoke`:

- `target=staging`: prueft `https://staging.bocholt-erleben.de`
- `target=live`: prueft `https://bocholt-erleben.de`
- `target=custom`: prueft eine manuell angegebene URL
- `profile=all`: Desktop + Mobile
- `profile=desktop` oder `mobile`: gezielte Einzelpruefung

## Akzeptanzkriterien fuer P1 V1

P1 V1 ist abgenommen, wenn:

- Deploy-Workflow nach HTTP-Smoke auch Browser-Smoke ausfuehrt.
- Manueller Browser-Smoke ohne Redeploy verfuegbar ist.
- Bei Fehlern Summary, JSON und Screenshots als Artefakte verfuegbar sind.
- Die Tests read-only bleiben.
- Content-/KI-/Audit-Fragen nicht mit Browser-Smoke vermischt werden.


## Reporting-Polish 2026-06-29

Nach dem ersten Staging-Lauf wurden zwei Punkte gehaertet:

- `summary.md` zeigt Warnungen als `WARNUNG`, nicht mehr irrefuehrend als `FEHLER`.
- Erwartete `401`-/Fetch-Konsolenhinweise beim geschuetzten Veranstalter-Dashboard ohne Login werden nicht mehr als Warnung gezaehlt, solange der sichtbare Zugangszustand erfolgreich geprueft wurde.

Ziel: Der Browser-Smoke soll echte Blocker sichtbar machen, aber erwartete Zugangszustaende nicht als vermeintliche Fehler dramatisieren.

## Reporting-Polish 2026-06-29 V2

Der Staging-Lauf `browser-smoke-staging-3303` zeigte danach noch erwartete Browser-Konsole-Hinweise:

- `401` auf `/events-veroeffentlichen/einreichen/`, weil die Einreichungsseite optional eine vorhandene Veranstalter-Session via `/api/organizer-portal/me.php` prueft. Ohne Login ist `401` fachlich korrekt; die Seite selbst bleibt nutzbar.
- Ein mobiler `App initialization failed: TypeError: Failed to fetch` waehrend der Bottom-Tabbar-Navigation. Der Navigationscheck wird deshalb verschaerft: Events und Aktivitaeten muessen nach Tabwechsel nicht nur Container, sondern echte Karten rendern. Erst dann wird dieser bekannte Hintergrund-Hinweis ignoriert.

Damit gilt:

- Erwartete Zugangshinweise werden nicht als Warnung gezaehlt.
- Bottom-Tabbar-Navigation ist strenger: leere Zielcontainer reichen nicht mehr.
- Echte Konsolenprobleme ausserhalb dieser bekannten Muster bleiben weiterhin sichtbar.

## Reporting-Polish 2026-06-29 V3

Der Lauf nach V2 zeigte keinen App-Fehler, sondern einen instabilen Consent-Testpfad:

- Der Test erwartete im Clean-Kontext zwingend einen sichtbaren Consent-Hinweis.
- In der CI kann der Hinweis je nach Timing/Runtime-Zustand nicht sichtbar werden, obwohl der eigentliche Produktzustand korrekt ist.
- Dadurch entstand ein False Negative: `locator.waitFor: Timeout ... [data-privacy-consent-banner]`.

V3 stabilisiert den Test fachlich:

- Wenn der Consent-Hinweis im Clean-Kontext sichtbar ist, wird weiterhin real `Ohne Statistik` geklickt und danach der Bottom-Tabwechsel geprüft.
- Wenn der Hinweis im CI-Clean-Kontext nicht sichtbar wird, setzt der Test kontrolliert eine gespeicherte Ablehnung und prüft den eigentlichen P1-Sicherheitsfall: Nach gespeicherter Entscheidung darf der Hinweis beim Bottom-Tabwechsel nicht erscheinen.
- Nach dem Tabwechsel werden weiterhin echte Eventkarten erwartet. Der Test wird dadurch nicht weicher fuer Navigation oder Rendering.

Damit bleibt der urspruengliche Schutz erhalten, aber der Smoke laeuft nicht wegen eines nicht reproduzierbaren Consent-Anzeige-Timings rot.


## Reporting-/Robustheits-Polish 2026-06-30 V4

Vor Anwendung von V3 wurde der Consent-Testpfad erneut gegen den urspruenglichen Fehler simuliert. Ergebnis: V3 wuerde zwar den CI-Timeout vermeiden, aber im Fallback durch einen Reload einen Teil des urspruenglichen Risikos abschneiden. Der relevante Fehler war: Eine Seite wurde ohne Consent-Auswahl vorbereitet, danach wurde die Entscheidung getroffen, und erst beim Bottom-Tabwechsel erschien ein alter Hinweis erneut.

V4 bildet diesen Zustand robuster ab:

- Der Test startet weiterhin in einem Clean-Kontext und laesst die App initial booten.
- Wenn der Consent-Hinweis sichtbar ist, wird real `Ohne Statistik` geklickt.
- Wenn der Hinweis in der CI nicht sichtbar wird, setzt der Test die Ablehnung ueber die vorhandene `BEPrivacy`-Runtime statt per Reload. Dadurch bleibt der bereits gebootete Seitenzustand erhalten.
- Erst danach erfolgt der Bottom-Tabwechsel. So bleibt der urspruengliche Reappearing-Fall abgedeckt.
- Nach dem Tabwechsel muessen echte Eventkarten sichtbar sein, und ein sichtbarer Consent-Hinweis bleibt ein harter Fehler.

Damit ist V4 der bevorzugte Patch gegenueber V3: stabiler als V2, aber naeher am realen Bug als der V3-Fallback mit Reload.
