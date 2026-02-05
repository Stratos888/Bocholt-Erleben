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
END OF FILE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
:::
