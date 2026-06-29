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
| Warnung | Browser-Konsole meldet bekannte, nicht-blockierende Hinweise | Beobachten, kein Sofortblocker |
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
