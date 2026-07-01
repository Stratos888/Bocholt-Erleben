<!-- === BEGIN BLOCK: BROWSER_SMOKE_ACTIVITY_FAVORITES_PREMIUM_2026_06_30 | Zweck: erweitert den Browser-Smoke um lokale Activity-Favoriten als neuen Kernpfad; Umfang: Testmatrix-/Reporting-Ergaenzung === -->
## Erweiterung: Activity-Favoriten

Der Browser-Smoke prueft zusaetzlich, ob die lokale Activity-Favoritenfunktion im echten Browser nutzbar ist und dem Premium-Zielzustand folgt: Favoriten sind stille persoenliche Sortierprioritaet, kein Schnellfilter und keine eigene Feed-Section.

Pruefung:

- `/aktivitaeten/` laden.
- Lokalen Favoritenspeicher im Testkontext leeren.
- Ersten Activity-Herzbutton klicken.
- Pruefen, ob `activity:<id>` lokal im Nutzerpraeferenzspeicher gespeichert ist.
- Pruefen, ob der Herzbutton den aktiven Zustand zeigt.
- Seite neu laden.
- Pruefen, ob kein `Favoriten`-Schnellfilter-Pill existiert.
- Pruefen, ob der gespeicherte Favorit priorisiert oben steht, ohne Favoriten-Pill, ohne `Deine Favoriten`-Section und ohne Erklaerzeile.

Abgrenzung:

- Keine Serveruebertragung.
- Keine echten Nutzerkonten.
- Keine Event-Favoriten.
- Keine produktiven Schreibaktionen ausser lokaler Browserzustand im isolierten Testkontext.
<!-- === END BLOCK: BROWSER_SMOKE_ACTIVITY_FAVORITES_PREMIUM_2026_06_30 === -->

<!-- === BEGIN BLOCK: BROWSER_SMOKE_MOBILE_QUICK_FILTER_RAIL_2026_07_01 | Zweck: dokumentiert die mobile Schnellfilter-Rail als Browser-Smoke-Kerncheck; Umfang: Activities Quick Filters Mobile === -->
## Erweiterung: Mobile Schnellfilter-Rail

Der Browser-Smoke prueft zusaetzlich, dass die mobilen Schnellfilter auf `/aktivitaeten/` als einzeilige horizontale Chip-Rail gerendert werden.

Pruefung:

- `/aktivitaeten/` im Mobile-Profil laden.
- `#offer-quick-filters` muss sichtbar sein.
- Die Schnellfilter muessen als `flex` mit `nowrap` laufen.
- Sichtbare Schnellfilter duerfen nicht in mehrere Zeilen umbrechen.
- Die Rail darf nicht deutlich hoeher als eine Chip-Zeile sein.
- Die Aktivitaetskarten muessen danach weiterhin sichtbar sein.

Abgrenzung:

- Kein Desktop-Scrollpattern; Desktop bleibt beim bestehenden Wrap-/Grid-Layout.
- Keine Aenderung der Filterlogik.
- Aktuell ist `/aktivitaeten/` die relevante Seite mit Schnellfilter-Chips; das Pattern gilt kuenftig fuer vergleichbare mobile Schnellfilterleisten.
<!-- === END BLOCK: BROWSER_SMOKE_MOBILE_QUICK_FILTER_RAIL_2026_07_01 === -->

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
