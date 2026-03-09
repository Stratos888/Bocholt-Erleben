# MASTER CONTROL FILE — BOCHOLT ERLEBEN
# SINGLE SOURCE OF TRUTH FOR PROJECT STATE
# CHATGPT MUST FOLLOW THIS FILE STRICTLY

---

# SESSION INSTRUCTIONS FOR CHATGPT

You are operating a persistent production software project.

This file defines:

- project phase
- sprint scope<img width="388" height="747" alt="image" src="https://github.com/user-attachments/assets/1afd8458-1a10-4b0e-bc5f-6ae294637ea9" />

- frozen systems
- product priorities
- decision memory

You MUST:

1. Read this file at session start
2. Read ENGINEERING.md at session start
3. Work ONLY on CURRENT SPRINT tasks
4. Never modify frozen areas
5. Never invent new priorities
6. Update this file when session closes
7. Record all permanent decisions
8. Never regress completed areas

This file is the project brain.

---

# SESSION PROTOCOLS (STANDARDIZED)

Purpose:
Reduce setup/close friction and prevent regressions by using standardized session prompts.

Rule:
Use the repo prompt templates for every session start and close.

Templates (canonical):
- docs/prompts/session-open.md
- docs/prompts/session-close.md

<!-- === BEGIN REPLACEMENT BLOCK: SESSION PROTOCOLS (Open/Close) | Scope: replaces SESSION OPEN/CLOSE PROTOCOL bullet blocks only === -->

SESSION OPEN PROTOCOL (must do):
- Read MASTER.md fully
- Read ENGINEERING.md fully
- Confirm CURRENT SPRINT scope + Frozen Areas
- Choose 1 Golden Screen / 1 Workpack
- Produce Rubric Gap List (Top 3 FAIL) before patch
- Use uploaded repo ZIP as canonical baseline for this session (ZIP-FIRST)
- Maintain an assistant-managed canonical working copy per file for this chat session (ENGINEERING.md)
- Every patch response MUST include Working Copy Attestation (source + file + verbatim anchors + bytes + sha256 fingerprint); otherwise: STOP (no patch)
- Output code changes as Replace-instructions only (BEGIN/END + replacement block)
- Batch is allowed for speed ONLY if Replace ranges do not overlap; otherwise merge into one Replace block
- Assume user applies all assistant-provided changes unless user explicitly says otherwise; if user says they didn’t apply or made manual edits, STOP and request current file/section

SESSION CLOSE PROTOCOL (must do):
- Short session report (max 8 bullets)
- Update CURRENT SPRINT statuses (only what changed)
- Update COMPLETED AREAS (only if DoD met)
- Append DECISIONS LOG entries (only permanent decisions made)
- Update SESSION STATE (LAST UPDATE)

<!-- === END REPLACEMENT BLOCK: SESSION PROTOCOLS (Open/Close) === -->

---

# PROJECT OBJECTIVE

Build and operate a production-grade event discovery PWA.

Target level:

Enterprise-grade stability
Enterprise-grade UX
Enterprise-grade evolution safety

Business goal:

Enable reliable event discovery and monetizable organizer onboarding.

---

# CURRENT PHASE

PHASE:

UI STABILIZATION AND CONTENT PIPELINE RELIABILITY

PHASE GOAL:

Users must perceive platform as:

trustworthy
modern
stable
professional

Event pipeline must operate continuously.

Manual daily event search must not be required.

---

# CURRENT SPRINT

ACTIVE TASKS:

---

<!-- === BEGIN REPLACEMENT BLOCK: TASK 1 (Enterprise Gate + controlled evolution) | Scope: replaces Task 1 section only === -->

TASK 1: DETAILPANEL UI STABILIZATION

STATUS: IN PROGRESS — ENTERPRISE GATE CLOSURE WORKPACKS (UI ONLY) — NOT FROZEN

DEFINITION OF DONE (ENTERPRISE GATE):

Detailpanel is considered enterprise-frozen ONLY when ALL are true:

1) Enterprise UI Rubric score >= 9/10 (see UI BASELINE section)
2) Golden Screens captured for Detailpanel (mobile + desktop) and referenced in Decisions Log
3) No layout jumps or overflow issues in:
   - mobile narrow (<= 375px)
   - mobile wide (<= 430px)
   - desktop (>= 1024px)
4) Interactive contract verified:
   - focus visible + consistent
   - touch targets >= 44px for primary controls
   - reduced motion respected (prefers-reduced-motion)
   - max 2 primary actions (CTA discipline)

FOUNDATION BASELINE (VERIFIED):

- Focus trap + ESC close behavior stable
- Background scroll-lock stable when panel is open
- Overlay stability guardrails applied (fixed overlays must never be clipped)
- Touch targets meet enterprise minimum (close/actionbar)
- Reduced Motion support present (prefers-reduced-motion)

WORKPACKS COMPLETED THIS SESSION (VERIFIED VIA PROOFS):

- Icons: Appweite Single-Source-of-Truth eingeführt:
  - `window.Icons` Registry (`js/icons.js`) mit SVG Line Icons
  - Script-Order in `index.html`: icons.js vor details.js/events.js
  - Detailpanel + Event Cards nutzen SVGs über `Icons.svg()` (keine Emojis mehr)
- Categories: Kanonische Kategorie ist single-source in `FilterModule.normalizeCategory()`:
  - Variante A umgesetzt: `Highlights` + `Wirtschaft` → `Innenstadt & Leben`
  - `Icons.categoryKey()` mappt ausschließlich kanonische Kategorien auf `cat-*` Keys
- CSS: Appweite Tokenisierung/Rendering für SVG Icons:
  - `svg.ui-icon-svg` globaler Base-Style (size/stroke/opacity via Tokens)
  - Kontext-Overrides (sm/md/lg + stroke-sm/md/lg) für Cards/Meta/Header
  - Detailpanel SVG-Guard scoped (UI-Icons clamped, Kategorie-Badge frei skalierbar)

REMAINING GAPS (NEXT WORKPACKS):

Detailpanel UI is now visually stabilized and considered ENTERPRISE BASELINE for the product.

The following improvements were implemented and verified during the latest UI stabilization session:

- Header hierarchy finalized
  - Category icon acts as indicator (not button)
  - Title baseline alignment corrected
  - Close button visual weight reduced

- Meta rows stabilized
  - Global gutter alignment enforced (no internal row inset)
  - Location arrow proximity fixed via grid layout (fit-content column)
  - Location wrapping fixed (no artificial width clamp)

- Description block typography stabilized
  - improved line-height and wrapping behavior
  - consistent spacing rhythm between sections

- Source row alignment stabilized
  - arrow alignment works for 1-line and 2-line sources
  - gutter alignment matches description block

- Actionbar visually integrated with content block

This means the DETAIL PANEL STRUCTURE is now ENTERPRISE READY.

Remaining future work (optional polish only):

1) Motion polish
   - panel opening / closing easing
   - drag interaction refinement

2) Accessibility audit
   - final keyboard navigation verification
   - ARIA roles verification

3) Feed scanning improvements (outside detailpanel)
   - event card hierarchy / scan speed

<!-- === END REPLACEMENT BLOCK: TASK 1 (Enterprise Gate + controlled evolution) === -->

---

# NEXT PRIORITIES (DO NOT START YET)

/* === BEGIN REPLACEMENT: GOLDEN SCREEN BASELINE + NEXT PRIORITIES (Canonical reference only) | Scope: removes legacy conflicting GS-01 DoD block === */

# GOLDEN SCREEN BASELINE — CANONICAL REFERENCE

Golden Screens are defined exclusively in:

# GOLDEN SCREENS (CANONICAL SET + RULES)

Rule:
There must be no second/legacy Golden Screen DoD elsewhere in this file.

Notes:
- GS-01 (Event Feed) is already enterprise-baseline frozen per Decisions Log (2026-02-20).
- Next Golden Screen work happens via Workpacks against GS-02..GS-06 as defined in the canonical set.

/* === END REPLACEMENT: GOLDEN SCREEN BASELINE + NEXT PRIORITIES (Canonical reference only) === */

---

# UI BASELINE + ENTERPRISE RUBRIC + GOLDEN SCREENS (CANONICAL SET + RULES)

(omitted here for brevity in this patch block; keep the existing canonical section from your file)

---

# SESSION STATE

OVERALL STATUS:

SESSION REPORT (this session, verified):
- CSS Design System DS-01 in `css/style.css` aufgebaut und konsolidiert:
  - `:root` Token-Contract auf „must-exist“ Tokens erweitert
  - fehlende Layout-/Feed-/Detailpanel-/Icon-/Control-Tokens ergänzt
  - DS-02-Vorbereitungs-Aliases (`--cmp-*`) eingeführt
- CSS Design System DS-02 begonnen und für bestehende UI-Komponenten ohne Redesign gemappt:
  - Search Input
  - Filter Pills + Reset Pill
  - Header Buttons (`App`, `Info`)
  - Modal CTA
  - Detailpanel Actionbar Buttons
  - Calendar Choice Buttons
  - Detailpanel Links / Source Rows
  - Detailpanel Close Button
  - Event-Card Shell
  - Detailpanel Meta Rows
- Mehrere Copy/Paste-/Strukturfehler in `css/style.css` reproduzierbar behoben:
  - doppelte/kaputte DS-02-Blöcke konsolidiert
  - fehlerhafte Selector-Anker korrigiert
  - Header/App-Button-Regressionsursache nachgewiesen und behoben
- Detailpanel Link-Dedupe funktional korrigiert in `js/details.js`:
  - Website/Quelle werden nicht mehr doppelt gerendert, wenn beide effektiv auf dasselbe Ziel zeigen
  - Vergleich erfolgt nicht nur über rohe URL-Gleichheit, sondern über kanonisierten Target-Key
- Empty State im Feed auf finalen Zielzustand gebracht und eingefroren:
  - wie echtes Feed-/Event-Card-Element, kein Sonderpanel
  - keine Accent-Rail / kein Primary-CTA
  - ruhige Secondary-Action
  - Layout/Ausrichtung bewusst linksbündig belassen
- Aktueller beobachteter Functional Bug außerhalb des gefreezten Empty States:
  - Reset über das X neben den Pills setzt Suche/Pills optisch zurück, berechnet aber Facet-Counts nicht neu
  - wahrscheinlicher Root Cause in `js/filter.js`: Reset-Pfad ruft nicht den vollständigen kanonischen Recompute (`applyFilters`) auf

DECISIONS LOG (permanent, project-wide):
- GS-01 Feed / Design System:
  - DS-01 Token-Contract in `css/style.css` ist aufgebaut; fehlende „must-exist“ Tokens gelten als Root-Cause-Klasse für Layout-/Icon-/Spacing-Drift und müssen künftig zuerst geschlossen werden, bevor Component-Mapping erfolgt.
- GS-01 Feed / Design System:
  - DS-02 wird als CSS-only Component-Mapping ohne Redesign umgesetzt.
  - Ziel-DNA: Button / Link / Input / Card / Divider / Focus über `--cmp-*` Tokens; bestehende Selektoren werden schrittweise darauf gemappt.
- GS-01 Feed / Empty State:
  - Empty State ist vorerst eingefroren.
  - Zielzustand ist eine echte Feed-/Event-Card-Variante, nicht ein bewusst andersartiges Sonderpanel.
  - Text linksbündig, Action darunter; keine Vollzentrierung.
- Detailpanel Links:
  - Website/Quelle-Dedupe gehört auf Render-/JS-Ebene, nicht in CSS.
  - Wenn Website und Quelle effektiv auf dasselbe Ziel zeigen, bleibt nur eine Zeile sichtbar.
- Header Controls:
  - App-Button im Header wird über den echten Button-Selector gemappt (`button.pwa-install-button`), nicht über einen ID-only Selector.

CURRENT SPRINT (TASK 1: DETAILPANEL UI STABILIZATION) — STATUS:
- Detailpanel bleibt Enterprise-Baseline; keine strukturelle Neugestaltung.
- In dieser Session wurden nur konsistente CSS-/Render-Fixes vorgenommen.
- Aktiver UI-Fokus dieser Session: GS-01 Feed + CSS Design System (`css/style.css`) + Detailpanel-Feinschliff ohne Redesign.

REMAINING GAPS (NEXT WORKPACKS, UI/UX + FUNCTIONAL):
1) Filter Reset Functional Fix in `js/filter.js`:
   - Reset über die Reset-Pill / das X neben den Pills muss Suche + Facets + Counts + Disabled-States vollständig neu berechnen
   - `resetFilters()` als kanonischen Full-Reset konsolidieren
2) CSS Design System DS-02 weiterführen (weiterhin one-file / CSS-only, kein Redesign):
   - Restliche Komponenten in `css/style.css` auf vorhandene `--cmp-*` DNA prüfen und nur dort weiter mappen, wo keine sichtbare Regression entsteht
3) `css/style.css` Strukturhygiene:
   - verbleibende stray braces / Marker-Unsauberkeiten gezielt bereinigen, sobald der nächste echte CSS-Workpack ansteht

NEXT CHAT PROMPT (start here):
„ZIP-first: Bitte zuerst MASTER.md und ENGINEERING.md lesen. Danach nur eine Datei bearbeiten: `js/filter.js`. Problem: Wenn ich die Suche über das X neben den Category-/Time-Pills zurücksetze, werden Suche und Pills optisch korrekt geleert, aber die Time- und Category-Facets bleiben auf 0 statt sich neu korrekt zu füllen. Bitte Root-Cause in `js/filter.js` nachweisen und dann einen minimalen Patch liefern, sodass der Reset-Pfad ein vollständiger kanonischer Full-Reset wird (Suche leeren, State resetten, `applyFilters()`/Facet-Recompute korrekt ausführen). Nur Replace-Instructions mit eindeutigen BEGIN/END Anchors.“

LAST UPDATE:

2026-03-09

2026-03-05
<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->

---

# END OF FILE
