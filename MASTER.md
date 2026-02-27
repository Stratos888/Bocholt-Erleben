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

- Scroll container contract fixed:
  - detail-panel-body overflowY = auto
  - scrollPaddingBottom resolves to px (not auto)
  - canScroll becomes true under long-content stress test (scrollHeight > clientHeight)
- Header direction locked (enterprise alignment):
  - Category icon is indicator-only and must visually behave as content badge (not a control)
  - Close is a dedicated dismiss control with app-like affordance (quiet-filled), token-based

REMAINING GAPS (NEXT WORKPACKS):

1) HEADER FINALIZATION (UI POLISH, TOKEN-CONSISTENT)
   - Ensure header row feels “one line” (consistent optical alignment across title / badge / close)
   - Validate long-title clamp + no collision with close
   - Validate focus states (close + any header interactive elements)

2) META COMPACTION (DATE/TIME/LOCATION, CALM)
   - Row model: Row 1 = location link, Row 2 = date + time
   - No right-side “meta box” next to title on mobile
   - Robust under long location names (wrap safe)

3) READABILITY + RHYTHM (TEXT FLOW)
   - Calm vertical rhythm across sections (no “jumps”)
   - Line-height + paragraph spacing consistent
   - Description readability strong; no cramped blocks

4) ACTIONBAR INTEGRATION (CONNECTED FEEL)
   - Actionbar feels connected to content
   - Touch targets >= 44px
   - Focus/active states consistent

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

<!-- === BEGIN REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) | Scope: replaces SESSION STATE section to end of file === -->

SESSION REPORT (this session, max 8 bullets):

- Pipeline hatte harte Runtime-Regressions (NameError/UnboundLocalError/NoneType/Indentation) → aktuell wieder lauffähiger Stand erreicht (Run endet grün).
- Discovery-Run zeigt wieder Output (z. B. „Candidates parsed: 37“; zeitweise „New inbox rows: 19/21“, danach je nach Dedupe „0“).
- Source_Health logging zeigt Ausfälle sichtbar (z. B. Münsterland e.V. zeitweise HTTP 503) → Daily Discovery kann damit als „Quelle kaputt“ auffallen.
- Inbox-Qualität aktuell noch zu „müllig“ (viele Einträge ohne echten Event-Charakter, u. a. normale Gottesdienste).
- Pflichtfeld-Policy in der Realität inkonsistent (Events ohne Location tauchen im Review auf) → Gate/Filter muss wieder strikt werden.
- Nächster Fokus bleibt: Pipeline-Qualitätsgates so scharf machen, dass nur „wirkliche Events“ in die Inbox kommen (Review wird wieder handhabbar).

---

OVERALL STATUS:

UI: DETAILPANEL ENTERPRISE GATE CLOSURE — unverändert gegenüber letztem Stand (siehe Task 1 Workpacks/Gaps).

PIPELINE: TASK 4 IN PROGRESS — Script wieder run-stabil (keine harten Crashes im letzten Log), Source-Ausfälle werden sichtbar geloggt; Hauptproblem ist jetzt klar „Qualitätsgate (was ist ein Event?)“ + „Location-Pflichtfeld strikt durchsetzen“.

OBSERVED RUN FACTS (from session logs):

- Sources enabled: 18
- Candidates parsed: 37
- New inbox rows: schwankend je nach Dedupe (beobachtet: 0 bis 21)
- Inbox backfilled (updated rows): beobachtet: 4–7
- Beispiel-Fehlerfall: Münsterland e.V. → HTTP 503 (Service Temporarily Unavailable)

---

DECISIONS LOG (append-only):

- 2026-02-27 — Event-Definition für Inbox-Qualitätsgate geschärft:
  - „Normale Gottesdienste“ (auch mit Abendmahl/Kirchencafé) zählen NICHT als Event für die App.
  - Erlaubt sind nur „Kirchen-Events mit Event-Charakter“ (z. B. Konzert/Lesung/Workshop/Sonderveranstaltung), nicht der reguläre Gottesdienstbetrieb.
- 2026-02-27 — Freeze-Ziel (Pipeline) präzisiert:
  - Ziel ist NICHT maximale Menge/Monat, sondern ein stabiler Daily-Discovery-Fluss mit sinnvollen Events.
  - Daily Discovery muss Source-Ausfälle sichtbar machen (Source_Health), damit zeitnah repariert werden kann.
  - Danach „Freeze“ des Stands, und nur noch reaktiv/bei Auffälligkeiten nachziehen.

---

LAST UPDATE:

2026-02-27

<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->

---

# END OF FILE
