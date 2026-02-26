# ENGINEERING CONSTITUTION — BOCHOLT ERLEBEN
# ABSOLUTE ENGINEERING RULES — CHATGPT MUST FOLLOW

Defines HOW to safely modify the project.

MASTER.md defines WHAT to build.

ENGINEERING.md defines HOW to build.

These rules are mandatory.

---

# CORE PRINCIPLE

Primary objective:

Maintain production stability.

Never introduce regression.

Every change must be:

minimal
controlled
safe

---

# OPERATING MODE

Project operates in:

CONSOLIDATION MODE

Meaning:

Latest project state is complete truth.

Never assume missing code.

Never invent structure.

Work only with actual visible code.

---

# CANONICAL BASELINE RULE (ZIP-FIRST)

A repo ZIP uploaded at session start counts as "provided file content".

Workflow:

- Use the ZIP snapshot as canonical baseline for files.
- Maintain a consolidated working copy per file inside this session.
- Assume the user applies ALL assistant-provided changes.
- If the user does NOT apply a change or applies extra manual edits, the user MUST explicitly say so.
- If the assistant is not 100% sure the working copy matches the user's local file, STOP and request the current file (or the minimal relevant section).

---

# BATCH PATCH RULE (SAFE SPEED)

To avoid slow micro-iterations:

- Allowed: multiple Replace-blocks in one response
- Condition: Replace ranges MUST NOT overlap.
- If overlap risk exists: merge into ONE larger Replace-block.

---


# ONE FILE RULE

Work on ONLY ONE FILE per change.

Never modify multiple files simultaneously unless explicitly required.

---

# DIFF RULE

Never output full files unless explicitly required.

Always provide:

Clear instruction:

Replace block from X to Y

and provide replacement block separately.

---

# NO-FILE, NO-DIFF RULE

If the current file content is not provided:

DO NOT produce a diff.

Stop and request the current file.

Notes:

- A repo ZIP uploaded at session start counts as "provided file content".
- If the assistant has a verified consolidated working copy of the file in this session, it may continue to produce Replace-instructions without re-requesting the file.
- If there is any doubt about sync between working copy and user's local file: STOP and request the current file (or the minimal relevant section).

---


# EXACT BOUNDARY RULE

BEGIN/END boundaries MUST be exact, verbatim lines from the provided file.

Do not approximate line numbers.

Prefer stable markers (e.g. "=== BEGIN BLOCK ===" / "=== END BLOCK ===").

---

# MATCH OR STOP RULE

If the specified BEGIN/END lines cannot be found exactly in the provided file:

Stop.

Ask for the current file.

---


# MARKER RULE

Every inserted or replaced block MUST include markers:

BEGIN marker:

Comment explaining purpose and scope

END marker:

Comment explaining end of block

Markers prevent corruption.

---

# 100 PERCENT RULE

When fixing or modifying:

All required changes must be included.

Never provide partial fixes.

Never rely on implicit assumptions.

---

# NO GUESS RULE

Never guess.

Never hallucinate missing code.

If information missing:

Request clarification.

---

<!-- === BEGIN REPLACEMENT BLOCK: ROOT CAUSE + EVIDENCE PACK + SCORECARD + FIXTURES | Scope: replaces Root Cause section only === -->

# ROOT CAUSE RULE

Never apply speculative fixes.

Identify root cause first.

Apply minimal correction.

---

# EVIDENCE PACK RULE (SPEED WITHOUT GUESSING)

For any change that claims improvement (UI, pipeline, deploy, caching, parsing):
Provide a minimal Evidence Pack before proposing the patch:

- Repro: exact steps / input / page / source that shows the problem
- Observation: what is currently happening (symptom)
- Cause: why it happens (root cause in code/data)
- Proof: how we verify the fix (observable signal)

If an Evidence Pack cannot be produced from available material:
Stop and request the missing artifact (file, sample HTML, logs, screenshot).

---

# PIPELINE SCORECARD RULE (ENTERPRISE OBSERVABILITY)

For pipeline work, success must be measurable per run.

Any pipeline change must define or update a Scorecard output with:
- candidates_total
- inbox_new
- inbox_updated (backfill)
- dates_filled
- times_filled
- false_positive_reasons_top (at least top 3)
- source_health summary (success/fail + fetch counts)

No “it should be better” fixes without Scorecard delta.

---

# FIXTURE RULE (PREVENTS REGRESSIONS + ACCELERATES ITERATION)

When adding/changing extraction logic:
Create/extend fixtures (saved sample HTML snippets) for:
- at least 1 positive example (real event)
- at least 1 negative example (impressum/datenschutz/blog publish date)

Fixes must be validated against fixtures to avoid re-breaking solved cases.

<!-- === END REPLACEMENT BLOCK: ROOT CAUSE + EVIDENCE PACK + SCORECARD + FIXTURES === -->

---

<!-- === BEGIN REPLACEMENT BLOCK: UI MODIFICATION (Batch Mode + Component Contract + Golden Screen verification) | Scope: replaces UI modification rules only === -->

# UI MODIFICATION RULE

Default:
UI polish must be CSS-only whenever possible.

Avoid structural DOM changes.

Avoid JS changes unless necessary.

---

# UI BATCH MODE (ENTERPRISE LEAPS WITHOUT CHAOS)

Purpose:
Enable fewer iterations with larger, system-level UI improvements.

Allowed when MASTER.md defines an “Enterprise Gate Closure Workpack”:

- Still ONE FILE per change (typically css/style.css)
- Allowed to replace larger consolidated blocks (tokens + component mapping) in that one file
- Changes must be systemic (component-level), not scattered one-off overrides
- Must be validated against the Enterprise UI Rubric for the target screen

Disallowed in UI Batch Mode:
- touching multiple files
- ad-hoc per-page overrides that bypass component mapping
- micro-tweaks that do not move rubric score

---

# COMPONENT MAPPING CONTRACT (SINGLE SOURCE OF TRUTH)

In CSS, enterprise UI must be driven by:
1) Tokens (spacing, radius, typography, shadows, colors)
2) Component mapping blocks for:
   - Buttons (primary/secondary/ghost)
   - Cards / surfaces
   - Dividers
   - Links
   - Inputs
   - Focus styles

Rule:
If a UI issue repeats across screens, fix it in tokens/mapping, not per-screen.

---

# GOLDEN SCREEN VERIFICATION RULE (NO VISUAL GUESSING)

For UI changes:
Verification must target a Golden Screen and include:
- mobile + desktop check
- long text stress check (overflow prevention)
- focus + keyboard check (visible focus)
- no layout jump check for conditional UI

If Golden Screen reference is missing:
Stop and request screenshot or exact reproduction details.

<!-- === END REPLACEMENT BLOCK: UI MODIFICATION (Batch Mode + Component Contract + Golden Screen verification) === -->

---

# OVERLAY RULE

All overlays must exist under overlay root under body.

Never inside transformed or sticky containers.

---

# SERVICE WORKER RULE

Never break caching logic.

Never break versioning logic.

Never introduce asset instability.

---

# DEPLOYMENT RULE

Deployment must remain deterministic.

Never break asset references.

Fail fast if asset inconsistency detected.

---

# REGRESSION RULE

Never break existing working functionality.

Preserve behavior.

---

# SESSION START PROTOCOL

At session start ChatGPT MUST:

Read MASTER.md

Read ENGINEERING.md

Follow MASTER.md priorities

Follow ENGINEERING.md safety rules

---

# SESSION CLOSE PROTOCOL

When requested:

Update MASTER.md

Preserve project continuity

Never lose decisions

---

# END OF FILE
