id="94217" variant="document" subject="PROJECT.md – konsolidiert v2"

PROJECT.md – Bocholt erleben

Single Source of Truth (Architektur + Regeln + Arbeitsweise)

Ziel:
Eine mobile-first Event- und Freizeitplattform für Bocholt.
Fokus: schnell inspirieren („Was kann ich heute machen?“), nicht Datenbank oder Spezial-App.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
GRUNDPRINZIP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Die Seite ist KEINE:

Tourenplaner-App

Spezialistenplattform

Feature-überladene Web-App

Die Seite IST:
→ ein schneller, ruhiger Ideenfinder (Feed-first)

Design-DNA:

ruhig

hochwertig

wenig visuelle Unruhe

scannbar in Sekunden

Content vor Controls

Details nur im Panel

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CRITICAL ARCHITECTURE SUMMARY (UNVERÄNDERLICH)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Google Sheet ist die EINZIGE redaktionelle Quelle für Events.

events.tsv ist nur Build-Artefakt (niemals manuell pflegen).

events.json ist die einzige Runtime-Datenquelle im Frontend.

Schema wird ausschließlich vom Builder definiert.

Deploy ist Fail-Fast (ungültige Daten stoppen Build).

PWA/Manifest/SW sind System-Contract (Install muss funktionieren).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VERBINDLICHE ARBEITSREGELN (GLOBAL)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Diese Regeln gelten IMMER.

Konsolidierungs-Modus
Der zuletzt gepostete Dateistand ist vollständig und korrekt.
Niemals raten oder rekonstruieren.

Datei-Sichtbarkeits-Regel (NEU, verbindlich)
Änderungen dürfen NUR auf Basis von Dateien erfolgen,
deren aktueller Inhalt im Chat sichtbar ist.
ZIP-Archive gelten NICHT als persistente Quelle.
→ immer Datei einzeln posten/hochladen.

Diff statt Snippet
Nur Replace-Blöcke oder lokale Änderungen.
Keine kompletten Dateien „neu erfinden“.

Minimal-Diff
Nur die minimal nötigen Zeilen ändern.
Keine Nebenbei-Fixes.

Datei-fokussiert
Immer nur 1 Datei pro Schritt.

BEGIN/END Marker Pflicht

Niemals spekulieren
Erst Root Cause beweisen, dann fixen.

UI-Polish nur CSS
JS nur für Funktion, nie für Optik.

Overlay-Root unter <body>

Fail-Fast Deploy

Proof-First Patch Protocol (CSS/Layout)
Pre-Proof → Fix → Post-Proof

Token Discipline
Nur definierte CSS-Tokens verwenden.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
UI / UX SYSTEMGESETZE (Top-App Standard)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Diese Regeln sind verbindliche UX-Architektur, keine „Optik“.

Mobile-first, Desktop-aware (NEU)
Designentscheidungen werden IMMER im realistischen Mobile-Viewport validiert.
Desktop darf nie kaputt aussehen, ist aber sekundär.
Mobile ist die Wahrheit.

Feed-first (NEU, zentral)
Inhalte stehen über Controls.
Suche/Filter/Header dürfen den Feed nicht dominieren.

Subtil statt laut (NEU)
Weniger Shadow, weniger Rahmen, weniger „Buttons“.
Mehr Ruhe, mehr Lesbarkeit.

Scan-first Cards
Cards zeigen nur:

Datum

Titel

Meta
Keine Beschreibung.

Panels statt Seiten
Details gehören ins Bottom Sheet, nicht auf eigene Seiten.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DEV-TEST STANDARD (NEU, verbindlich)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Für alle UI-Arbeiten:

Chrome DevTools Custom Device:

Width: 360

Height: ~780

DPR: 3

Mobile UA

Alle Layout-/Spacing-Entscheidungen werden dort validiert.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EVENTS – RUNTIME FLOW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

events.json
→ js/main.js
→ js/events.js
→ js/details.js

Cards:

Date-Chip links

2 Zeilen rechts

Icon inline

keine Beschreibung

Sektionen:

reine Labels (keine extra Container)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EVENTS – DATENQUELLE & BUILD
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Google Sheet
→ TSV
→ Builder
→ events.json
→ Deploy

Frontend liest ausschließlich JSON.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
KATEGORIEN (CANONICAL)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Google Sheet + Filter + UI + Icons müssen IDENTISCHE Kategorien nutzen.

Single Source of Truth:
Filter.normalizeCategory()

Neue Kategorien nur zentral ergänzen.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LEITLINIEN FÜR KI / AUTOMATISIERUNG (NEU)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Diese Datei ist bewusst so formuliert, dass auch eine KI deterministisch arbeiten kann.

Daher:

keine impliziten Annahmen

keine versteckten Dateien

kein „liegt im ZIP“

immer sichtbarer Ist-Zustand

immer minimaler Patch

Wenn Informationen fehlen:
→ zuerst nachfragen, niemals raten.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LESSONS LEARNED / PATCH-SAFETY (NEU, verbindlich)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Ziel dieser Regeln: Keine „Halb-Patches“, keine Syntax-Brüche, keine widersprüchlichen Handler.

1) Anchor Discipline (NEU)
Für alle größeren UI-/Overlay-/Panel-Blöcke müssen stabile Anker existieren.
Bevor Änderungen erfolgen:
- Entweder BEGIN/END Marker im Code vorhanden
- Oder eindeutige Nachbarzeilen werden als Replace-Anker definiert

Regel:
→ Keine Patches mit „ungefähr hier“. Jede Replace-Anweisung muss eindeutig matchen.

2) One-Patch Policy bei Zielzustand (NEU)
Wenn ein „Zielzustand“ definiert ist (z.B. „Top-App Niveau Detailpanel“):
- Es wird EIN konsolidierter Patch geplant (max. 2 Dateien: JS + CSS)
- Danach erst getestet

Feature-Freeze-Regel (NEU)
Während ein Zielzustand umgesetzt wird:
- keine zusätzlichen Features
- keine spontanen „noch schnell“-Änderungen
- nur die zuvor definierte Maßnahmenliste
Erst nach erfolgreichem Test darf erweitert werden.

Root-Cause-First-Regel (NEU)
Vor jedem Fix:
- Ursache eindeutig identifizieren und reproduzieren
- erst dann minimalen Patch anwenden
Symptomatische „Workarounds“ sind verboten.

- Keine Patch-Kaskade ohne neue Root-Cause-Analyse

3) No-Transform-Fight Rule (NEU)
Wenn CSS Animationen/Transitions `transform` nutzen, darf JS NICHT gleichzeitig `element.style.transform` setzen.
Stattdessen:
- JS steuert ausschließlich CSS-Variablen (z.B. `--dp-drag-y`)
- CSS berechnet `transform` als Komposition (Base + Drag)

Regel:
→ Direkte Transform-Overrides in JS sind bei animierten Sheets verboten.

4) History/Back Contract (NEU)
Detailpanel-Back-Handling muss token-basiert sein:
- show(): pushState mit eindeutiger Token-ID (z.B. `__detailPanelOpen: <token>`)
- hide(): nutzt `history.back()` nur wenn Token exakt passt
- X muss IMMER zuverlässig schließen (Fallback: direkt schließen)

Regel:
→ Kein „hide() macht history.back()“ ohne Token-Check.

5) Handler Conflict Rule (NEU)
Für Overlay/Panel gilt:
- Es darf pro Interaktion (ESC, Overlay-Klick, Close, Popstate, Swipe) jeweils nur EIN aktiver Handler existieren.
- Keine Doppelregistrierungen, keine konkurrierenden Listener.

Regel:
→ Vor Patch: prüfen, ob bereits Handler existieren. Bei Bedarf konsolidieren statt addieren.

6) Chat-Budget Regel (NEU)
Bei längeren Iterationen:
- Antworten müssen kurz, deterministisch und patch-fokussiert bleiben
- Keine Mehrfachvarianten (A/B/C), wenn Zielzustand feststeht
- Bei drohender Chat-Länge: erst Planung + Maßnahmenliste, dann Patch

Max-Patch-Regel (NEU)
Ein einzelner Patch darf maximal 2 Dateien betreffen (typisch: 1x JS + 1x CSS).
Wenn mehr Dateien notwendig sind:
→ in mehrere Schritte aufteilen.
Ziel: kleine, sichere, nachvollziehbare Änderungen statt „Big Bang“-Patches.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DETAILPANEL – TOP-APP CONTRACT (NEU, verbindlich)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Das Detailpanel ist das zentrale Interaktionsmuster (Bottom Sheet). Es bleibt das Standardpattern.

Definition of Done (DoD) für Detailpanel:
A) Öffnen/Schließen
- Tap auf Event öffnet immer
- X schließt immer
- Overlay-Tap schließt
- ESC schließt (Desktop)

B) Back-Verhalten
- Back schließt zuerst das Panel (nicht die Seite)
- Token-basiert, X bleibt zuverlässig

C) Swipe-to-close (optional, aber wenn vorhanden: stabil)
- Start nur über definierte Hit-Area im oberen Bereich
- Nur wenn Panel-Scroll oben ist (scrollTop==0)
- Keine Transform-Fights: JS -> CSS-Variable, CSS -> Transform

D) Links / Actions
- Primär: „Ort öffnen“
- Sekundär: „Website“ nur wenn gültig, stabil, nicht redundant
- Bocholt „/veranstaltung*“ Pfade gelten als instabil → nicht als „Website“ anbieten

E) A11y-Minimum
- role="dialog", aria-modal, aria-hidden
- Focus Trap + Focus Restore
- Close Button aria-label

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
END OF FILE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
END OF FILE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
:::
