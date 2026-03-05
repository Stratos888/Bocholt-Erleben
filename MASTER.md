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
- GS-01 Event Feed (Golden Screen) — Enterprise Scanability Workpack umgesetzt (UI + Filter, per Console-Proofs verifiziert):
  - Event Cards: Titel bleibt 2-zeilig (Clamp); Meta ist 1-zeilig (Zeit fix | Ort ellipsis) und Redundanz City/Location entfernt.
  - Title-Row als Grid (Text | Kategorie-Icon) stabilisiert (kein Overlap/absolute hacks).
  - Feed Rhythm/Density tokenbasiert eingeführt und per Gaps-Proof feinjustiert:
    - `events-section-title` margins reduziert (mt/mb) und Cards/Section Abstände deterministisch aus Tokens abgeleitet.
- Filter (Zeit) — Enterprise Facets an Feed-Gruppen gekoppelt (kontextabhängige Counts):
  - Zeit-Buckets: `all | today | week | weekend | nextweek | later`
  - Facet-Counts korrekt (Search + aktive Kategorie) + `endDate` berücksichtigt (laufende Events zählen als „Heute“).
- Control System Polish (Search + Pills + Reset + Focus):
  - Einheitliche Control-DNA tokenbasiert (Höhe/Radius/Border/Shadow/Focus-Ring) für Search, Pills und Reset.
  - Reset-Icon als SVG (stroke=currentColor) + robuste Zentrierung; Fokus-Ring konsistent via `--ui-focus-ring`.

DECISIONS LOG (permanent, project-wide):
- GS-01 Feed: Scanability-Contract:
  - Event Card: Titel max 2 Zeilen; Meta 1 Zeile (Zeit vollständig, Ort ellipsis); City/Location-Dopplung wird vermieden.
  - Kategorie-Icon ist Indikator (nicht Button) und wird layoutstabil in der Titelzeile (Grid Text|Icon) geführt.
- Zeitfilter ist Single Source of Truth = Feed-Buckets:
  - Keys: `all | today | week | weekend | nextweek | later`
  - Facet-Counts bleiben kontextabhängig (Search + aktive Kategorie) und berücksichtigen `endDate`.
- Control-System-DNA ist tokenbasiert:
  - Search/Pills/Reset nutzen gemeinsame Control Tokens; Fokus-Ring ausschließlich über `--ui-focus-ring`.

CURRENT SPRINT (TASK 1: DETAILPANEL UI STABILIZATION) — STATUS:
- Detailpanel bleibt Enterprise-Baseline (frozen unless critical bug); in dieser Session nicht verändert.
- Aktiver UI-Fokus dieser Session war GS-01 Event Feed + Filter-Facets (ohne Detailpanel-Redesign).

REMAINING GAPS (NEXT WORKPACKS, UI ONLY):
1) GS-01 Loading/Empty/Error States vereinheitlichen (Skeleton Cards, „Keine Events“ + Reset CTA, Retry)
2) GS-01 Kategorie-Erkennbarkeit Feintuning (nur Tokens/CSS, low-noise)
3) GS-01 Scroll-Performance QA (Shadows/blur auf Midrange Android; ggf. CSS-only abmildern)
4) Optional: Detailpanel Accessiblity Audit (Keyboard/ARIA) — nur wenn Bug/Regression sichtbar

PIPELINE: TASK 4 — EVENT DISCOVERY PIPELINE (LLM COLLECTOR) — STATUS: HYBRID GO-LIVE TRACK (QUALITY-FIRST)

Current architecture (verified in runs):

Sources (Google Sheet tab "Sources")
→ Collector (HTML + RSS; Playwright for HTML)
→ Discovery_Candidates (collector proof)
→ Field extraction (OpenAI LLM + heuristic fallback)
→ Mandatory field gate (title + date)
→ Dedup (normalize_url vs existing inbox URLs)
→ Inbox

Key configuration rule (proven critical):
- Only rows with `enabled=true` AND `pipeline_mode=llm` are processed in LLM runs.
- For HTML sources, `include_detail_pages=true` is required to collect real event detail URLs
  unless an explicit list-page fallback exists.

Hybrid go-live strategy (decided):
- Primary goal: Inbox must stay curatable (quality & completeness), not maximum intake.
- Duplicates are acceptable during rapid run cycles; focus metric is "good inbox_new".
- Disable low-yield/high-noise sources for now; re-enable later only if needed.
- Prefer source-scoped rules (per domain) over fragile global heuristics.

Quality improvements implemented (confirmed by runs):
1) Strict detail URL gating for JUNGE UNI
- Only accept detail URLs matching: `/programm/kurs/...`
- Result: category/overview pages (e.g., /info/...) no longer pollute Inbox.

2) Stronger non-event filtering and write gates (HTML + RSS)
- Block obvious utility/legal/cookie/login/newsletter pages before Inbox-write.
- Block generic placeholder titles (e.g., "Details", "Buchungen", etc.) via non-event heuristics.

3) Isselburg-specific datetime fix (kept)
- isselburg.de encodes occurrence datetime in query param `from=YYYY-MM-DD HH:MM:SS`
- `extract_datetime_from_url()` overrides both heuristic + LLM extractor outputs.

4) ListPageParser fallback for band tour/termine pages (new)
- For domains:
  - `django-flint.de`
  - `coltplay.de`
- If Collector finds no detail URLs, parse list page directly and create synthetic URLs:
  `...#event=<hash>`
- Write directly to Inbox without detail fetch (still subject to title/date gate + non-event filter).

Recent proof runs (high-signal milestones):
- 2026-03-05T06:27:21Z: sources=14 → candidates_logged=111 → inbox_new=10
  - 10/10 curatable (JUNGE UNI).
- 2026-03-05T07:23:06Z: sources=11 → candidates_logged=109 → inbox_new=10
  - 10/10 curatable (JUNGE UNI).
- 2026-03-05T09:03:59Z: sources=11 → candidates_logged=155 → inbox_new=4
  - 3/4 curatable (JUNGE UNI)
  - 1/4 Coltplay written but title/location extraction is wrong (phone/email line used).

Current state (as of last run in this session):
- Stable good intake source: JUNGE UNI (consistently curatable).
- Other sources often show dup/no-new during minute-by-minute runs (expected).
- Band sources:
  - Coltplay: list-page fallback produces candidates but needs domain-specific row parsing to avoid phone/email lines.
  - Django Flint: list-page fallback currently produces 0 candidates; needs domain-specific selectors/date parsing.

Known remaining blockers (next work, pipeline only):
A) Coltplay list-page parsing must become domain-specific
- Extract actual tour rows (date/time/venue/city), never contact/footer text.
- Build clean titles like: "Coltplay – <Venue/City>".

B) Django Flint list-page parsing must become domain-specific
- Identify the "Termine" rows reliably; parse date/time/location.

C) Keep strict inbox quality gates
- Inbox must not contain listing/overview placeholders.
- Any list-page fallback must produce valid (title+date [+time/location]) or be skipped.

Next steps (next chat, in this order):
1) Inspect Coltplay tour HTML and implement robust domain-specific extractor (selectors → rows → date/time/venue/city).
2) Inspect Django Flint termine HTML and implement robust domain-specific extractor.
3) Run once; verify:
   - Coltplay produces multiple correct events
   - Django Flint produces events
   - Inbox remains 100% curatable (no placeholders/listings)

LAST UPDATE:

2026-03-05
<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->

---

# END OF FILE
