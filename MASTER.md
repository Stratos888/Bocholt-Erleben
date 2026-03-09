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
- Filter-Reset-Bug in `js/filter.js` wurde analysiert:
  - Root-Cause-Hypothese war ein nicht-kanonischer Reset-Pfad über das X neben den Pills
  - aktueller Ist-Stand wurde danach gegen den realen Datei-/UI-Stand geprüft
  - Ergebnis: kein weiterer Patch in `js/filter.js` eingebracht, weil der Reset im getesteten Stand wieder korrekt funktioniert
  - Filter-/Reset-Verhalten wurde praktisch gegengeprüft (Suche, Input-X, Reset-X, Kombinationen mit Kategorie/Zeit) und vorerst gefreezt
- CSS Design System DS-02 in `css/style.css` deutlich weiter konsolidiert, ohne Redesign:
  - Focus-/Link-/Action-Mappings weiter vereinheitlicht
  - Content-/Info-Seiten hinter dem Info-Button strukturell an die App-DNA angeglichen
  - größere Content-/Info-Cluster statt vieler Mini-Patches zusammengeführt
  - Modal-/Filter-/Detailpanel-/Icon-/Header-/Content-Blöcke weiter auf DS-02-/Token-DNA gezogen
- Mehrere strukturelle CSS-Regressions-/Konsolidierungsfehler wurden reproduzierbar gefunden und behoben:
  - kaputter `DS-02_MODAL_CTA_MAPPING`-Block korrigiert
  - doppelte/inkonsistente Content-Link-/Content-Cluster-Struktur bereinigt
  - größere Detailpanel-Header-/Meta-/Description-/Icon-Cluster sauberer zusammengeführt
- Event-Card-Regressionsphase während der Konsolidierung wurde analysiert und behoben:
  - Event-Card-Shell/Grid/Surface-DNA wiederhergestellt
  - Event-Card-Innenstruktur (Badge, Body, Title/Icon-Row) wiederhergestellt
  - unsaubere Worttrennungen/Title-Umbruchlogik korrigiert
  - Ziel: kein Qualitätsverlust gegenüber Vorzustand durch CSS-Konsolidierung
- Session-Endstand:
  - Event-Cards wieder auf stabilem Qualitätsniveau
  - keine weitere akute Functional-Fix-Baustelle offen
  - nächster sinnvoller CSS-Workpack ist nicht mehr Feed-/Card-Regression, sondern Status-/Feedback-Zustände

DECISIONS LOG (permanent, project-wide):
- GS-01 Feed / Filter Reset:
  - Der in einem Zwischenstand vermutete Reset-Bug in `js/filter.js` wurde in dieser Session nicht final als aktueller Defekt bestätigt.
  - Wenn beide Reset-Wege (Input-X und Reset-X neben den Pills) im praktischen Test korrekt laufen, wird `js/filter.js` nicht weiter angefasst.
- GS-01 Feed / Event Cards:
  - CSS-Design-System-Konsolidierung darf keine sichtbare Qualitätsminderung der Event-Cards verursachen.
  - Bei Regressionen gilt: erst Vorzustand gegen Ist-Stand abgleichen, dann die vollständige Card-DNA wiederherstellen (Shell + Innenstruktur + Typografie).
- CSS Design System / Content Pages:
  - Die Content-/Info-Seiten hinter dem Info-Button werden nicht separat „neu designt“, sondern systemisch an die bestehende App-DNA angeglichen.
  - Ziel bleibt: gleiche Oberflächenlogik, gleiche Typo-/Spacing-/Focus-/Link-DNA wie im Rest der App.
- CSS Cleanup:
  - Alte Kommentar-/Patch-Marker werden nicht als eigener Workpack priorisiert, sondern nur peu à peu bei späteren echten Patches mitbereinigt.

CURRENT SPRINT (TASK 1: DETAILPANEL UI STABILIZATION) — STATUS:
- Detailpanel bleibt Enterprise-Baseline; keine strukturelle Neugestaltung.
- Feed-/Filter-/Card-Regressionsphase dieser Session ist beendet.
- Aktiver UI-Fokus am Session-Ende: CSS Design System (`css/style.css`) → Status-/Feedback-Zustände an App-DNA angleichen, ohne Loading-Logik neu zu bauen.

REMAINING GAPS (NEXT WORKPACKS, UI/UX + FUNCTIONAL):
1) Status-/Feedback-Cluster in `css/style.css` auf DS-02 / App-DNA ziehen:
   - bestehende Skeleton-Eventcards bleiben als Loading-Standard bestehen
   - prüfen/konsolidieren: `.loading-container`, `.loading-spinner`, `.info-message`, `.error-message`
   - Ziel: gleiche Surface-/Spacing-/Radius-/Typo-Logik wie im Rest der App, kein Redesign
2) Danach Modal-/Overlay-Feinschliff:
   - verbleibende strukturelle Alt-/Duplikat-Reste im Modal-/Overlay-Bereich prüfen und nur bei echtem Bedarf bereinigen
3) Kommentar-/Marker-Cleanup nicht separat priorisieren:
   - nur bei künftigen echten CSS-Workpacks mit erledigen

NEXT CHAT PROMPT (start here):
„ZIP-first: Bitte zuerst MASTER.md und ENGINEERING.md lesen. Danach nur `css/style.css` bearbeiten. Nächster Workpack ist der Status-/Feedback-Cluster, nicht die Skeleton-Eventcards. Die Skeletons sind bereits eingebaut und sollen funktional unverändert bleiben. Bitte prüfe den aktuellen Stand von `.loading-container`, `.loading-spinner`, `.info-message` und `.error-message`, beschreibe kurz den Ist-Zustand für Dummies, definiere den Zielzustand im Rahmen unserer bestehenden App-DNA und liefere dann einen größeren, konsolidierten CSS-Patch nur für diese Zustände. Kein Redesign, keine JS-Logikänderung, nur CSS-only mit eindeutigen BEGIN/END Anchors.“

LAST UPDATE:

2026-03-09

2026-03-09

2026-03-05
<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->

---

# END OF FILE
