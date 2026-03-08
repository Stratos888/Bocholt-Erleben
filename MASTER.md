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
- GS-01 Loading/Empty/Error States (Workpack umgesetzt, proof-basiert):
  - Loading: Skeleton-Eventcards im Feed (stabile Geometrie; kein Layout-Jump).
  - Empty: „Keine Events gefunden“ als Card + Reset-CTA („Filter zurücksetzen“).
  - Error: Retry-CTA („Erneut versuchen“) (deterministisch via Reload).
  - Loading-Overlay wird beim normalen Laden nicht mehr gezeigt (Skeleton-only); Overlay bleibt für Error-State.
- FilterContract Hotfix (UI):
  - Pflicht-Search-Element (`#search-filter`) wieder im DOM bereitgestellt → FilterModule init bricht nicht mehr ab.
- PWA Offline-Shell Robustness (Service Worker):
  - Mobile „weiß/Offline“-Screen behoben durch Precache der in `/index.html` referenzierten CSS/JS-Assets (inkl. `?v=`).
  - Offline-Reload kann weiterhin „normal“ funktionieren (Cache-Fallback für `events.json`); Error-State tritt nur auf, wenn Cache fehlt.

DECISIONS LOG (permanent, project-wide):
- GS-01 Feed: Scanability-Contract:
  - Event Card: Titel max 2 Zeilen; Meta 1 Zeile (Zeit vollständig, Ort ellipsis); City/Location-Dopplung wird vermieden.
  - Kategorie-Icon ist Indikator (nicht Button) und wird layoutstabil in der Titelzeile (Grid Text|Icon) geführt.
- GS-01 Feed: Loading/Empty/Error-Contract:
  - Loading zeigt Skeleton-Cards im Feed (keine Layout-Shifts).
  - Normaler Load zeigt kein Vollbild-Overlay; Overlay ist für Error-State reserviert.
  - Empty-State enthält Reset-CTA („Filter zurücksetzen“).
  - Error-State enthält Retry-CTA („Erneut versuchen“).
- Zeitfilter ist Single Source of Truth = Feed-Buckets:
  - Keys: `all | today | week | weekend | nextweek | later`
  - Facet-Counts bleiben kontextabhängig (Search + aktive Kategorie) und berücksichtigen `endDate`.
- Control-System-DNA ist tokenbasiert:
  - Search/Pills/Reset nutzen gemeinsame Control Tokens; Fokus-Ring ausschließlich über `--ui-focus-ring`.
- Pipeline strategy shift (Bocholt-only, quality-first):
  - LLM Collector pipeline is parked for now (optional later add-on).
  - Primary intake becomes “Manual KI → curatierfreundlich” using the existing Inbox Review PWA workflow.
  - Optional later: neutral newsletter leads (facts only + deep-link; no description copying).
  - Search/Pills/Reset nutzen gemeinsame Control Tokens; Fokus-Ring ausschließlich über `--ui-focus-ring`.

CURRENT SPRINT (TASK 1: DETAILPANEL UI STABILIZATION) — STATUS:
- Detailpanel bleibt Enterprise-Baseline (frozen unless critical bug); in dieser Session nicht verändert.
- Aktiver UI-Fokus dieser Session war GS-01 Event Feed + Filter-Facets (ohne Detailpanel-Redesign).

REMAINING GAPS (NEXT WORKPACKS, UI ONLY):
1) GS-01 Kategorie-Erkennbarkeit Feintuning (nur Tokens/CSS, low-noise)
2) GS-01 Scroll-Performance QA (Shadows/blur auf Midrange Android; ggf. CSS-only abmildern)
3) GS-01.5 Offline-Indicator (Toast + persistentes Badge; UI-only, minimal JS)
   - Toast nur bei Zustandswechsel:
     - online → offline: „Offline – gespeicherte Daten“
     - offline → online: „Wieder online“
   - Persistentes Badge solange offline (unaufdringlich, nicht blockierend):
     - Text: „Offline – gespeicherte Daten (ggf. nicht aktuell)“
     - Kein Vollflächen-Layer; keine Layout-Shifts; kompatibel mit sticky header/top-stack.
4) Optional: Detailpanel Accessiblity Audit (Keyboard/ARIA) — nur wenn Bug/Regression sichtbar

NEXT CHAT PROMPT (start here):
„GS-01.5 Offline-Indicator umsetzen: UI-only. Ziel: Toast bei online/offline Wechsel + persistentes Offline-Badge solange offline. Bitte ZIP-first: MASTER.md + ENGINEERING.md lesen, dann one-file-at-a-time Patch liefern (CSS-first; minimal JS für online/offline Listener). Pipeline-Sektion in MASTER.md nicht anfassen.“

PIPELINE: TASK 4 — EVENT DISCOVERY PIPELINE (LLM COLLECTOR) — STATUS: PARKED (for later / optional add-on)

Reason (decision):
- Current focus is “curation-first intake” for Bocholt only.
- Scalability/automation is not priority right now; trial/error on scraping domains is too costly.

Current architecture (kept as reference; do not delete; use later if reactivated):

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

Hybrid go-live strategy (decided; keep):
- Primary goal: Inbox must stay curatable (quality & completeness), not maximum intake.
- Duplicates are acceptable during rapid run cycles; focus metric is "good inbox_new".
- Disable low-yield/high-noise sources for now; re-enable later only if needed.
- Prefer source-scoped rules (per domain) over fragile global heuristics.

Quality improvements implemented (confirmed by runs; keep):
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

Recent proof runs (high-signal milestones; keep):
- 2026-03-05T06:27:21Z: sources=14 → candidates_logged=111 → inbox_new=10
  - 10/10 curatable (JUNGE UNI).
- 2026-03-05T07:23:06Z: sources=11 → candidates_logged=109 → inbox_new=10
  - 10/10 curatable (JUNGE UNI).
- 2026-03-05T09:03:59Z: sources=11 → candidates_logged=155 → inbox_new=4
  - 3/4 curatable (JUNGE UNI)
  - 1/4 Coltplay written but title/location extraction is wrong (phone/email line used).

Known remaining blockers (pipeline-only; deferred):
A) Coltplay list-page parsing must become domain-specific
- Extract actual tour rows (date/time/venue/city), never contact/footer text.
- Build clean titles like: "Coltplay – <Venue/City>".

B) Django Flint list-page parsing must become domain-specific
- Identify the "Termine" rows reliably; parse date/time/location.

C) Keep strict inbox quality gates
- Inbox must not contain listing/overview placeholders.
- Any list-page fallback must produce valid (title+date [+time/location]) or be skipped.

Deferred next steps (only if/when pipeline is reactivated):
1) Inspect Coltplay tour HTML and implement robust domain-specific extractor (selectors → rows → date/time/venue/city).
2) Inspect Django Flint termine HTML and implement robust domain-specific extractor.
3) Run once; verify:
   - Coltplay produces multiple correct events
   - Django Flint produces events
   - Inbox remains 100% curatable (no placeholders/listings)


PIPELINE: TASK 4.5 — MANUAL KI EVENT INTAKE (CURATION-FIRST) — STATUS: ACTIVE (Bocholt-only, Zielstufe A)

Goal:
- Minimal ongoing effort.
- Regular intake of “good events” without scraping/Cloudflare trial/error.
- Output must be immediately curatable in the existing Inbox Review PWA.
- Zielstufe A: Nach der Chat-Suche maximal 1x Copy-Paste + 1x Workflow-Klick.

Rules (fixed):
- Search with Regelwerk stays unchanged for now.
- Extract facts only (title/date/time/location/city/source URL/category suggestion).
- Do not copy long descriptions from sources/newsletters.
- Prefer neutral/public sources; avoid over-representing potential future organizer subscribers.
- Deliver as a file format that can be imported into the existing inbox flow (no copy/paste into spreadsheets).

Operational flow (current target):
1) Chat returns JSON for `data/inbox_manual.json`
2) User pastes once into `data/inbox_manual.json`
3) User starts exactly one workflow: `Manual KI Event Intake`
4) Workflow appends to Google Sheet tab `Inbox`, clears `data/inbox_manual.json`, and dispatches `Deploy to STRATO`
5) Inbox Review PWA is then refreshed from the normal deploy output (`data/inbox.json`)

Non-goal for this stage:
- No direct Chat → Sheet integration yet
- No new UI for manual JSON input yet
- No changes to search prompt / rulebook behavior yet

Optional lead source (later):
- Newsletter-based leads (neutral sources), extracted as facts only and deep-linked to original source.
   - Coltplay produces multiple correct events
   - Django Flint produces events
   - Inbox remains 100% curatable (no placeholders/listings)

LAST UPDATE:

2026-03-05
<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->

---

# END OF FILE
