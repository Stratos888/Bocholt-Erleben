# MASTER CONTROL FILE — BOCHOLT ERLEBEN
# SINGLE SOURCE OF TRUTH FOR PROJECT STATE
# CHATGPT MUST FOLLOW THIS FILE STRICTLY

---

# SESSION INSTRUCTIONS FOR CHATGPT

You are operating a persistent production software project.

This file defines:

- project phase
- sprint scope
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

SESSION OPEN PROTOCOL (must do):
- Read MASTER.md fully
- Read ENGINEERING.md fully
- Confirm CURRENT SPRINT scope + Frozen Areas
- Choose 1 Golden Screen / 1 Workpack
- Produce Rubric Gap List (Top 3 FAIL) before patch
- Output code changes as Replace-instructions only (BEGIN/END + replacement block)

SESSION CLOSE PROTOCOL (must do):
- Short session report (max 8 bullets)
- Update CURRENT SPRINT statuses (only what changed)
- Update COMPLETED AREAS (only if DoD met)
- Append DECISIONS LOG entries (only permanent decisions made)
- Update SESSION STATE (LAST UPDATE)

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

STATUS: IN PROGRESS — REGRESSION INVESTIGATION (UI ONLY) — ENTERPRISE FREEZE: BLOCKED

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

FOUNDATION BASELINE (PREVIOUSLY ACHIEVED):

- Focus trap + ESC close behavior stable
- Scroll isolation baseline was previously stable (panel open: background locked; intended: panel body scroll only)
- Overlay stability guardrails applied (fixed overlays must never be clipped)
- Touch targets meet enterprise minimum (close/actionbar)
- Reduced Motion support present (prefers-reduced-motion)

VISUAL DNA ALIGNMENT (VERIFIED VIA CONSOLE PROOFS THIS SESSION):

Verified (PASS proofs captured):
- Header title reserve works (no collision with close): rightGapPx > 0
- Category icon is indicator-only (18x18, transparent, no border/shadow/transform)
- Meta block is a single enterprise info surface (grid + subtle bg + border + padding + radius)
- Meta rows calm and consistent (no borders, readable muted color)

REGRESSION BLOCKERS (NOT FIXED YET):

Must be resolved before any “frozen baseline” claim:
- Scroll container contract is NOT active:
  - #event-detail-panel .detail-panel-body computed overflowY = visible (should be auto)
  - scrollPaddingBottom = auto (should be var(--dp-actionbar-reserve))
  - canScroll = false in proof (needs verification under long content once overflow is correct)
- Until fixed: bottom-overlap prevention cannot be claimed as enterprise-safe.

FREEZE INSTRUCTION:

Detailpanel is treated as the Event Detail Page (overlay-based).

Until Enterprise Gate is met AND regression blockers are cleared:
Changes are allowed ONLY as "Enterprise Gate Closure Workpacks" (UI-only) with console proofs.

After Enterprise Gate is met and recorded:
Detailpanel becomes ENTERPRISE-FROZEN.
Only bug fixes allowed unless explicit unfreeze decision is recorded.

<!-- === END REPLACEMENT BLOCK: TASK 1 (Enterprise Gate + controlled evolution) === -->

---

TASK 2: EVENT CARD HIERARCHY STABILIZATION

STATUS: COMPLETED

---

TASK 3: EVENT PIPELINE RELIABILITY IMPROVEMENT

STATUS: COMPLETED
---

TASK 4: PIPELINE ENRICHMENT BACKFILL (JUBOH date/time completion)

STATUS: IN PROGRESS

DEFINITION OF DONE:

Existing Inbox events with missing date must be automatically backfilled.

Backfill must run during normal pipeline execution.

Backfill must respect existing budget and host caps.

No duplicate Inbox rows allowed.

Only missing fields may be filled.

Verification criteria:

Inbox backfilled (updated rows) appears in pipeline logs.

JUBOH /programm/kurs/ missing-date ratio must decrease continuously.

Time extraction must appear (>0 filled time values).

Scope restriction:

Pipeline only.

No UI changes allowed.

---
---

RULE:

Work ONLY on tasks listed here.

Do not introduce unrelated improvements.
/* === BEGIN INSERT: EXECUTION PRIORITY CONTROL (Golden Screen Primary, Pipeline Secondary) === */

# EXECUTION PRIORITY CONTROL

This section defines execution priority between UI Golden Screen and Discovery Pipeline.

Both systems are allowed to evolve in parallel.

However, priority order is strictly defined.

---

## PRIMARY OBJECTIVE — GOLDEN SCREEN FREEZE

Golden Screen Freeze is the primary execution objective.

Reason:

Golden Screen becomes visual and UX baseline for entire product.

Without Golden Screen Freeze, product lacks enterprise-grade visual foundation.

Rule:

Golden Screen tasks must be prioritized.

Golden Screen Freeze must be achieved as early as possible.

Golden Screen defines permanent UI reference.

---

## SECONDARY OBJECTIVE — DISCOVERY PIPELINE IMPROVEMENT

Discovery Pipeline improvement is allowed in parallel.

However:

Golden Screen Freeze has priority.

Pipeline work must not delay Golden Screen Freeze.

Pipeline becomes primary focus only AFTER Golden Screen Freeze is achieved.

---

## EXECUTION RULE

When session begins:

If Golden Screen is not frozen:

Golden Screen tasks have priority.

Pipeline tasks may be executed in separate sessions.

But Golden Screen Freeze remains primary milestone.

---

## FREEZE TRANSITION RULE

After Golden Screen Freeze is achieved:

Execution priority shifts.

Pipeline improvement becomes primary objective.

Golden Screen becomes frozen infrastructure.

No visual changes allowed.

Only bug fixes allowed.

/* === END INSERT: EXECUTION PRIORITY CONTROL (Golden Screen Primary, Pipeline Secondary) === */
---

# FROZEN AREAS

These systems are stable infrastructure.

Do not modify unless explicitly required.

HEADER LAYOUT

ROUTING SYSTEM

SERVICE WORKER

OVERLAY ROOT SYSTEM

BUILD PIPELINE

DEPLOY PIPELINE

PWA CORE

---

<!-- === BEGIN REPLACEMENT BLOCK: UI BASELINE + ENTERPRISE RUBRIC + GOLDEN SCREENS (Canonical Set & Rules) | Scope: replaces the entire UI BASELINE section === -->
# UI BASELINE

Mandatory visual rules:

Spacing scale (allowed values only):

4
8
12
16
24
32

Corner radius (allowed values only):

Cards: 12px
Buttons: 10px

Interaction rules:

Max primary actions per screen: 2
Avoid unnecessary dividers
No layout jumps when conditional UI appears/disappears (e.g. Install button)
Touch targets: primary controls >= 44px
Reduced motion must be respected (prefers-reduced-motion)

Visual goal:

calm
clean
professional
trustworthy

Avoid:

visual noise
tight layouts
aggressive styling

---

# ENTERPRISE UI RUBRIC (CUSTOMER / TOP APP DEVELOPER STANDARD)

Enterprise UI is achieved when a target screen scores >= 9/10 on this rubric.
Each item is PASS/FAIL.

1) Hierarchy clarity:
   - Title vs meta vs body instantly readable; no competing emphasis.

2) Rhythm & spacing discipline:
   - Consistent vertical rhythm; spacing uses the scale only.

3) Typography quality:
   - Body readability (line-height/length/weight); meta calm; headings not oversized.

4) Surface & contrast:
   - Cards/sections feel intentional; contrast is accessible; key info not washed out.

5) Noise budget:
   - Dividers/icons/badges minimal; nothing looks “template-y” or “form-like”.

6) CTA discipline:
   - <= 2 primary CTAs; rest secondary/tertiary; actions predictable.

7) Interaction states:
   - Hover/active/focus consistent across components; focus visible and elegant.

8) Touch & ergonomics:
   - Spacing prevents mis-taps; controls feel comfortable on mobile.

9) Stability:
   - No jitter, overflow, clipping, or scroll traps; long text safe.

10) Brand trust:
   - Screen feels like a serious product (no “prototype vibes”).

---

# GOLDEN SCREENS (CANONICAL SET + RULES)

Golden Screens are the permanent visual reference set for enterprise-grade UX.
They exist to eliminate subjective “feels better” loops.

Rule:
All UI work must target one Golden Screen at a time.

Canonical set (IDs are stable and referenced in chat workpacks):

GS-01 — Event Feed (Home):
- Header + filter/search + event list cards
- Install button may appear/disappear without layout jump

GS-02 — Detailpanel (Event Detail Overlay):
- Title/meta/location/description/actions
- Long text stress test
- Focus/ESC/overlay click behavior stable

GS-03 — /info/ Hub:
- Explanation + trust + primary CTA “Event veröffentlichen”
- Navigation block (links) must feel enterprise and calm

GS-04 — /events-veroeffentlichen/ (Organizer Funnel):
- CTA hierarchy, form/steps, error states, focus states
- Must feel trustworthy and conversion-strong

GS-05 — Locations Modal:
- Trust story + “ab 9,99€” principle + CTA
- No tariff table; no visual favoritism

GS-06 — “Empty / Edge States”:
- No events, offline, fetch error, loading states
- Must not look broken; messaging calm and clear

Golden Screen verification checklist (mandatory per UI workpack):
- mobile + desktop check
- long text / overflow check
- focus + keyboard check (visible focus)
- no layout jump check for conditional UI
- reduced motion check

---

# UI EXECUTION MODE (FASTER ENTERPRISE PROGRESS)

UI changes are executed as “Enterprise Gate Closure Workpacks”.

Workpack definition (in chat, not in MASTER):
- Choose 1 Golden Screen (GS-xx)
- List top 3 failing rubric items (PASS/FAIL delta target)
- Apply one consolidated CSS-only patch (tokens + component mapping)
- Re-score rubric after patch (must improve measurably)

Anti-rule:
No endless micro-iterations.
If a patch does not improve rubric score, stop and redesign the patch approach.

---

# UI FREEZE POLICY (PREVENTS PREMATURE FREEZES)

A UI area may be marked “Completed” for baseline stability.
A UI area is “Frozen” ONLY after:
- Rubric score >= 9/10 on its Golden Screen(s)
- Golden Screen verification checklist passed (mobile + desktop)
- Decision recorded in Decisions Log

Frozen means:
- No visual changes
- Bug fixes only
- Any unfreeze requires explicit decision entry

---

<!-- === END REPLACEMENT BLOCK: UI BASELINE + ENTERPRISE RUBRIC + GOLDEN SCREENS (Canonical Set & Rules) === -->

# CONTENT PIPELINE REQUIREMENTS

/* === BEGIN INSERT: DISCOVERY PIPELINE ENTERPRISE TARGET STATE === */

# DISCOVERY PIPELINE — ENTERPRISE TARGET STATE

This section defines the enterprise-grade target state for the automated event discovery system.

This system is separate from the UI and must be treated as independent production infrastructure.

Goal:

Fully automated, reliable, high-quality event intake.

Manual event search must not be required.

---

## DISCOVERY PIPELINE RESPONSIBILITY

Discovery Pipeline is responsible for:

Source fetching

Event candidate extraction

Quality classification

Deduplication

Inbox population

Providing high-quality review candidates

It is NOT responsible for:

UI rendering

Final publishing

User interaction

---

## DISCOVERY PIPELINE DEFINITION OF DONE

Pipeline is considered enterprise-grade when ALL criteria are fulfilled:

---

### INTAKE VOLUME

Pipeline must consistently produce:

Minimum:

20 valid review candidates per run

Target:

30–60 valid review candidates per run

Maximum candidate volume is not limited.

---

### QUALITY

At least:

80% of review candidates must be real events

Reject rate caused by parsing errors must be minimal

False positives from:

Impressum

Datenschutz

Blog publish dates

must be eliminated

---

### AUTOMATION

Pipeline must operate:

fully automatically

without manual triggering

without manual correction

Sources must be fetched automatically.

---

### RELIABILITY

Pipeline must:

never silently fail

log all candidates

log classification reason

log source health

Failures must be observable.

---

### ANALYSIS CAPABILITY

System must provide full visibility of:

all candidates

classification status

classification reason

deduplication result

This is mandatory.

Discovery_Candidates tab fulfills this requirement.

---

## DISCOVERY PIPELINE ARCHITECTURE STATUS

Current status:

ACTIVE DEVELOPMENT

Not frozen.

This system is allowed to evolve until enterprise target state is achieved.

After Definition of Done is fulfilled:

System becomes FROZEN INFRASTRUCTURE.

Further changes must be treated as enterprise-level architectural changes.

---

## DISCOVERY PIPELINE CURRENT PRIMARY OBJECTIVE

Current focus:

Improve extraction accuracy.

Priority order:

1 Improve HTML event extraction accuracy

2 Improve date extraction accuracy

3 Eliminate publish-date false positives

4 Improve source-specific parsers

5 Increase valid review candidate volume

---

## DISCOVERY PIPELINE SUCCESS CONDITION

Discovery Pipeline is considered COMPLETE when:

Manual event search is no longer required.

Pipeline provides sufficient high-quality events continuously.

Inbox consistently receives valid event candidates.

At that point:

Pipeline becomes frozen enterprise infrastructure.

/* === END INSERT: DISCOVERY PIPELINE ENTERPRISE TARGET STATE === */

---

# COMPLETED AREAS

These must never regress.

PWA infrastructure

Overlay architecture

Deploy pipeline

Event loading system

Base UI system

Detailpanel (Event Detail Overlay baseline frozen)

---

# DECISIONS LOG
2026-02-24

Detailpanel UI Stabilization — Category Icon Semantics + Proof Snapshot

Status:

IN PROGRESS (Gate not claimable)

Decision:

- Category icon is an INDICATOR (same purpose as Eventcards), NOT a control (must not look like Close).
- GS-02 verification is proof-driven; no “frozen baseline” claim while scroll contract is failing.

Proof snapshot (captured):

- Header reserve: rightGapPx = 80; paddingRight = 54px
- Category icon: 18x18, transparent, no border/shadow/transform; opacity 0.92
- Meta block: display grid; bg rgba(31,41,51,0.027); border 1px solid rgba(31,41,51,0.06); padding 8px; radius 14px
- Scroll contract FAIL: detail-panel-body overflowY visible; scrollPaddingBottom auto
- Token present: --dp-actionbar-reserve = calc(78px + max(0px, 0px) + 24px)

2026-02-23

Discovery Pipeline — Inbox Writeback API Stabilization (row_number writeback reliability)

Status:

COMPLETED (Infrastructure Stabilization)

Scope:

Pipeline infrastructure only.

No UI changes.

No UX changes.

No visual changes.

No Golden Screen impact.

---

Problem:

Inbox Review Writeback required reliable server-side status persistence.

Token session logic and JSONP transport verified functional.

Primary stabilization goal:

Ensure deterministic and observable writeback behavior.

---

Implemented decisions:

Writeback endpoint now guarantees:

- Strict allowlist enforcement for status values
- Explicit row_number validation
- Explicit header column mapping
- Explicit token session validation
- Explicit write execution using SpreadsheetApp.flush()

Writeback response now provides verifiable confirmation:

- spreadsheet_title
- sheet_name
- row_number
- status_col
- cell_a1
- old_status
- new_status

This enables full traceability of write operations.

---

Architecture impact:

Writeback API is now considered:

Stable
Production-ready
Enterprise-grade infrastructure component

---

UI impact:

NONE

Inbox Review UI behavior intentionally unchanged.

Review UI is not part of Golden Screen baseline.

Golden Screen Freeze remains unaffected.

---

Pipeline impact:

Inbox Review → Sheet writeback reliability established.

Enables reliable manual validation workflow.

Supports enterprise-grade content pipeline.

---

Freeze status:

Writeback API considered infrastructure.

Further changes must be treated as infrastructure changes.

Not UI changes.
Permanent architectural and UX decisions.

ChatGPT MUST append new decisions here when made.

---

2026-02-23

Discovery Pipeline — HTML Extraction Hardening (CTA/Filter Titles, JUBOH Link Classification, URL Normalization)

Status:

APPLIED (Quality hardening)
OPEN (JUBOH date/time fill pending)

Scope:

Pipeline only.

No UI changes.

---

Problem:

HTML-based sources produced candidates with incorrect titles derived from UI/CTA link text (e.g. “Mehr erfahren”) and filter/navigation links (e.g. “8 Jahre”, “16 Jahre”, numeric pagination).

These polluted Discovery_Candidates and led to low-quality Inbox review items.

Additionally, double-encoded href URLs reduced detection reliability.

---

Implemented decisions:

1) HTML link candidate quality gates:

- Reject numeric-only titles (pagination)
- Reject age-filter titles matching “<n> Jahre” patterns
- Reject generic/CTA titles (“Mehr erfahren”, “Details”, etc.) as final titles

2) Title repair:

- For generic CTA titles, fetch detail page and repair title via:
  og:title → h1 → title fallback

3) Source-specific hardening for JUBOH:

- Only allow true course detail pages under /programm/kurs/
- Reject category/info/filter pages (/programm/kategorie/, /info/, etc.)

4) URL normalization robustness:

- Apply unquote + HTML unescape before canonicalization to handle double-encoded hrefs

---

Observed run result after changes:

- Candidates parsed: 229
- New Inbox rows: 46
- New Inbox rows no longer contain CTA/Filter titles (“Mehr erfahren”, “Plus”, “… Jahre”, etc.)
- Majority of new rows from JUNGE UNI Bocholt (html)

---

Open issue / next step:

JUBOH course detail candidates still frequently lack date/time fields.

Next patch:

- Adjust detail-fetch trigger: for JUBOH /programm/kurs/ pages, fetch details for missing date/time even when listing-context “event_signal” is weak (budget/host caps unchanged).

Verification:

- Post-run TSV gates:
  - Inbox contains 0 titles matching “mehr erfahren|plus|<n> jahre|numeric-only”
  - All juboh.de Inbox URLs contain /programm/kurs/
  - JUBOH missing-date ratio drops significantly after trigger adjustment
---

2026-02-23

Discovery Pipeline — Inbox Backfill Pass (Automatic completion of missing date fields)

Status:

APPLIED (date fill active)
IN PROGRESS (time fill completion pending)

Scope:

Pipeline only.

No UI changes.

---

Problem:

Pipeline deduplication prevented improvement of existing Inbox rows.

Events already present in Inbox with missing date remained permanently incomplete.

Detail extraction improvements had no effect on already existing rows.

---

Implemented decision:

Pipeline now performs Inbox Backfill Pass:

During normal pipeline execution:

Inbox rows are scanned.

High-confidence event detail pages (currently JUBOH /programm/kurs/) with missing date are refetched.

Missing fields are filled using:

JSON-LD extraction

HTML detail extraction fallback

Batch update is applied in-place.

No duplicate Inbox rows created.

Budget and host caps remain enforced.

---

Observed run result:

Pipeline log shows:

Inbox backfilled (updated rows): 8

JUBOH missing-date count decreased accordingly.

Backfill operates without introducing duplicate Inbox entries.

---

Next step:

Extend Backfill extraction to reliably fill:

time field from JSON-LD startDate

location field when available

Goal:

Achieve enterprise-grade completeness of Inbox events.

Pipeline infrastructure remains UI-independent.

---
---

2026-02-19

MASTER/ENGINEERING system introduced

Purpose:

Prevent context loss

Prevent regression

Enable enterprise-grade evolution

Status:

ACTIVE

---

2026-02-20

Golden Screen — Events Feed Enterprise Baseline achieved

Status:

GOLDEN SCREEN FREEZE READY

All Golden Screen Definition of Done criteria fulfilled.

Implementation decisions frozen:

---

EVENT CARD BASELINE

Card padding:

16px

Card radius:

12px

Card vertical spacing:

16px (token spacing)

Card border:

rgba(31,41,51,0.055)

Card shadow:

0 1px 1px rgba(0,0,0,0.035)
0 10px 26px rgba(0,0,0,0.045)

Interaction:

Press scale feedback active

Focus-visible ring active

---

TYPOGRAPHY BASELINE

Title:

18px
600 weight

Meta:

14px
400 weight

Hierarchy validated.

---

SECTION HEADER BASELINE

Section headers produce no independent vertical spacing.

Spacing controlled exclusively by feed container gap.

Margin:

0

Typography:

uppercase
letter-spacing 0.11em

---

HEADER BASELINE

Header blur:

12px

Header surface:

color-mix(in srgb, var(--color-primary-muted) 78%, white)

Header shadow:

0 1px 0 rgba(255,255,255,0.78) inset
0 6px 16px rgba(31,41,51,0.045)

Header considered enterprise-grade.

---

RHYTHM VALIDATION

Console measurement confirmed:

Card → Card = 16px  
Card → Label = 16px  
Label → Card = 16px  

Vertical rhythm considered correct and frozen.

---

FREEZE STATUS

Golden Screen baseline must not be visually modified further.

Only bug fixes allowed.

Any visual changes require explicit unfreeze decision.

---

NEXT UI PRIORITY

Continue CURRENT SPRINT Task:

DETAILPANEL UI STABILIZATION

Golden Screen considered reference baseline.

---

ADDENDUM (BUGFIX, UI ONLY)

Events Feed — Date Badge Rail alignment model fixed (Single Coordinate System)

Reason:

Rail + Grid previously drifted due to padding/offset hacks → badge could appear off-center or overflow rail.

Fix model:

- Rail is a true grid column (single source of truth via --rail-w)
- Rail starts at card edge (no negative left compensation)
- Date badge is centered inside rail column
- Rail rendered as solid surface to avoid optical drift

Proof requirement:

Console checks confirm:
railLeft - cardLeft = 0
delta(badgeCenter - railCenter) ≤ 1px

Status:

APPLIED as bugfix within Golden Screen constraints.

Decision authority:

Permanent UX and surface baseline established.
2026-02-21

Detailpanel UI Stabilization — Foundation Baseline Hardening (UI-only)

Status:

IN PROGRESS (Foundation baseline achieved; visual alignment pending)

Applied (UI/Overlay only; pipeline untouched):

- Detailpanel keyboard accessibility hardened:
  - ESC closes
  - Focus trap prevents focus leak
  - robust focus handling on open
- Panel open/close root-state standardized:
  - .is-panel-open is applied on html + body to enable root-level scroll/overscroll contracts
- Scroll & overscroll stabilization:
  - Android “rubberband” on Events page eliminated via root overscroll-behavior guard
  - Pull-to-refresh disabled intentionally (enterprise-controlled refresh; prevents accidental reload)
- Overlay regression guardrail:
  - Removed/neutralized overlay-clipping causes (contain/transform/backdrop contexts) when overlays are active
  - Fixed filter sheet clipping regression caused by containing contexts
- Design system token introduced:
  - --ui-overlay-dim defined globally and used by detailpanel overlay for consistent dimming
- Touch target compliance:
  - Close button hit area expanded (visual unchanged) via ::before
  - Actionbar buttons guarded to >= 44x44
- Reduced motion support:
  - prefers-reduced-motion contract present and verified via devtools emulation

Next inside Task 1:

- Visual DNA alignment (detail header/typography/rhythm to Golden Screen baseline)
- Chooser overlay hardening (avoid scope regressions)
- Drag/snap feel polish
2026-02-20

Discovery Pipeline — Date extraction improvement + detail enrichment

Status:

IN PROGRESS

Session scope:

Improve date extraction completeness without increasing noise.

Pipeline-only session.

UI not modified.

Implemented:

- Multi-day date extraction support (startDate + endDate)
- HTML <time datetime> extraction added
- Budgeted detail-page fetch introduced for missing-date candidates
- JSON-LD Event extraction implemented on detail pages

Observed results:

- Pipeline stable
- Inbox increased from 55 → 67 review events after date extraction improvements
- Subsequent runs produced 0 new rows (expected due to dedupe)
- Major sources provide dates primarily via HTML text on detail pages

Conclusion:

Current primary gap:

Detail-page HTML date extraction (non-JSON-LD sources)

Next pipeline focus:

Extract date from detail-page HTML text using existing extractor

Pipeline reliability remains intact.

---
2026-02-21

Discovery Pipeline — TASK 3 Abschluss: Detail-HTML Nachladen + Datums-Extraktion (Correctness-first)

Status:

COMPLETED

Beschlusslage:

- Datumsermittlung wird bei fehlendem JSON-LD durch budgetiertes Nachladen von Detailseiten-HTML ergänzt.
- Auf listing-like Seiten wird die Datumszuweisung konservativ gehandhabt (Korrektheit vor Vollständigkeit).
- Listing → Detail wird bevorzugt (Detail-URL wird bei erfolgreicher Extraktion „hochgestuft“), um systematisch echte Event-Detailseiten zu erfassen.

Nachweis (Messwerte, beobachtete Runs):

- review:event_signal_missing_date reduziert von ~500–600 auf ~50–60 pro Run (bei ~200–216 candidates parsed).
- Keine offensichtlichen falschen Datums-Treffer in den geprüften Review-Fällen festgestellt.
- Dedupe-Verhalten bestätigt: „0 new inbox rows“ ist erwartbar, wenn Kandidaten bereits in Inbox existieren.

Scope-Hinweis:

- Keine UI-Änderungen.
- Nur pipeline-seitige Anpassungen in scripts/discovery-to-inbox.py im Rahmen TASK 3.

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

# SESSION CLOSE PROTOCOL

When session closes ChatGPT MUST:

Update CURRENT SPRINT statuses

Move completed tasks to COMPLETED AREAS

Append new DECISIONS if needed

Update SESSION STATE

---

# SESSION STATE

OVERALL STATUS:

UI: DETAILPANEL REGRESSION INVESTIGATION — HEADER/META PASS (proofs green), SCROLL CONTRACT FAIL (detail-panel-body overflowY visible, scrollPaddingBottom auto)

PIPELINE: IN PROGRESS (TASK 4 BACKFILL ACTIVE)

LAST UPDATE:

2026-02-24
---

# END OF FILE
