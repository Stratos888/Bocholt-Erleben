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

1) HEADER FINALIZATION — BASELINE ALIGNMENT (TOP-APP LOOK)
   - Kategorie-Icon muss auf Baseline der ersten Titelzeile ausgerichtet werden (nicht vertikal zentriert im Header-Block)
   - Long-title clamp + keine Kollision mit Close (re-check nach Baseline-Adjust)

2) ACTIONBAR INTEGRATION (CONNECTED FEEL)
   - Visuelle Verbindung Content ↔ Actionbar finalisieren (Shadow/Divider/Rhythmus)
   - Safe-area bottom inset berücksichtigen (env(safe-area-inset-bottom))
   - Focus/active states konsistent

3) READABILITY + RHYTHM (TEXT FLOW)
   - Line-height / Absatzabstände im Description Block final beruhigen
   - Meta-Icon Gewichtung minimal leichter als Text

4) ENTERPRISE INTERACTION AUDIT
   - Focus-visible für alle interaktiven Elemente im Panel (Close, Links, Actions)
   - Tab-Reihenfolge (Header → Content → Actions) verifizieren

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
- Detailpanel: Appweite Icon-Standardisierung abgeschlossen (SVG Line Icons + Tokens)
  - `window.Icons` Registry (`js/icons.js`)
  - `details.js` + `events.js` nutzen `Icons.svg()` / `Icons.categoryKey()`
  - Keine Emoji-Kategorie-Icons mehr (keine OS/Font-Drifts)
- Categories: Single Source of Truth ist `FilterModule.normalizeCategory()`
  - Variante A: `Highlights` + `Wirtschaft` → `Innenstadt & Leben`
  - `Icons.categoryKey()` mappt ausschließlich kanonische Kategorien auf `cat-*`
- CSS/Tokens: SVG Rendering ist appweit konsistent
  - `svg.ui-icon-svg` Base Style (size/stroke/opacity via Tokens)
  - Kontextgrößen für Cards/Meta/Header (sm/md/lg + stroke-sm/md/lg)
  - Detailpanel SVG Guard scoped (UI-Icons clamped, Kategorie-Badge frei)

DECISIONS LOG (permanent, project-wide):
- Icons: Single Source of Truth ist `window.Icons` (SVG Line Icons). Keine Emojis für Kategorien.
- Kategorien: Single Source of Truth ist `FilterModule.normalizeCategory()` (kanonische Kategorien). Icons mappt nur kanonisch → IconKey.
- Darstellung: Größe/Stroke/Opacity werden ausschließlich über CSS Tokens gesteuert (`--ui-icon-*`).

CURRENT SPRINT (TASK 1: DETAILPANEL UI STABILIZATION) — STATUS:
- Architektur + Konsistenz stabil (Overlay-Root, Scroll/Close/Actions, Icon-System zentral)
- Nächster UI-Workpack: Header Baseline Alignment (Kategorie-Icon an erste Titelzeilen-Baseline, nicht center)

REMAINING GAPS (NEXT WORKPACKS, UI ONLY):
1) Header Baseline Alignment (Kategorie-Icon ↔ erste Titelzeile)
2) Actionbar Integration + Safe-Area bottom inset
3) Typografie/Rhythmus (Description weight/line-height, Meta-Icons leicht entschärfen)
4) Focus-visible Audit (Close/Links/Actions)

PIPELINE: TASK 4 — EVENT DISCOVERY PIPELINE (LLM COLLECTOR) — STATUS: FUNCTIONAL BASELINE REACHED

Current architecture verified in production runs:

Sources
→ Collector (HTML + RSS)
→ Discovery_Candidates (collector proof)
→ Field extraction (LLM + fallback parser)
→ Mandatory field gate (title + date)
→ Dedup (existing inbox URLs)
→ Inbox

Latest verified run example:
sources=3
candidates_logged=43
inbox_new=1

Key improvements implemented in this session:
- RSS sources now supported inside the LLM discovery pipeline
- Collector supports both HTML and RSS sources
- Discovery_Candidates now logs all collected detail URLs (collector proof)
- Inbox write happens only after mandatory field validation + dedup
- Health log reflects collector status per source

Stability assessment:
- Collector layer: STABLE
- RSS ingestion: WORKING
- Dedup logic: WORKING
- Health logging: WORKING

Remaining gaps (non-blocking):
1) Event date extraction robustness (HTML pages)
2) Better heuristics for aggregator sites (Münsterland / EUREGIO)
3) Some sources produce candidates but fail the mandatory field gate

Strategic source policy (confirmed):
- Prefer neutral aggregators and public feeds
- Avoid using individual venues/locations as primary discovery sources
- Example preferred sources:
  - presse-service Bocholt
  - presse-service Kreis Borken
  - Münsterland Veranstaltungen
  - EUREGIO Agenda

Next work focus (pipeline only):
- Improve date extraction reliability
- Adjust HTML detail-URL heuristics for aggregator sites
- Validate candidate → inbox conversion rates using TSV exports

Pipeline architecture itself is now considered **validated**.

LAST UPDATE:

2026-03-02
<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->

---

# END OF FILE
