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

<!-- === BEGIN REPLACEMENT BLOCK: CANONICAL BASELINE (ZIP-FIRST) — ASSISTANT WORKING COPY (HARD) | Scope: replaces ZIP-FIRST section only === -->

# CANONICAL BASELINE RULE (ZIP-FIRST) — ASSISTANT-MAINTAINED WORKING COPY (HARD)

A repo ZIP uploaded at session start counts as "provided file content" and is the canonical baseline.

Workflow (mandatory):

- Use the ZIP snapshot as the canonical baseline for files.
- Maintain a consolidated, **assistant-maintained canonical working copy per file** inside this chat session.
- Every patch the assistant gives is treated as **applied immediately** to that canonical working copy.
- All subsequent patches MUST be computed against the **updated** canonical working copy (never against an older version).
- Assume the user applies ALL assistant-provided changes and makes NO additional edits unless explicitly stated.

## NO ATTESTATION, NO PATCH (HARD STOP)

If a response contains Replace-instructions, it MUST start with a "Working Copy Attestation".
If it does not: **the patch is invalid and must not be applied.**

## WORKING COPY ATTESTATION (MANDATORY FOR EVERY PATCH RESPONSE)

Every patch response MUST include:

- Source: ZIP snapshot (or later user-uploaded file, if explicitly used)
- File: exact path
- Anchors: exact, verbatim BEGIN and END lines that exist in the current canonical working copy
- Fingerprint: `bytes=<N>, sha256=<HASH>` of the current canonical working copy for that file

If the assistant cannot provide this attestation: **STOP. No patch.**

## SYNC LOSS EXCEPTION (ONLY IF USER SAYS SO)

Only if the user explicitly says they did NOT apply a patch or applied manual edits:
**STOP and request the current file.**

---

<!-- === END REPLACEMENT BLOCK: CANONICAL BASELINE (ZIP-FIRST) — ASSISTANT WORKING COPY (HARD) === -->
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

Pipeline changes must include a scorecard:

- parsed candidates
- new inbox rows
- missing fields ratios
- error counts
- top sources by contribution

This is mandatory for enterprise confidence.

---

# FIXTURE RULE (REPRODUCIBLE TEST INPUTS)

If a bug is source-specific:

- capture minimal HTML fixture
- store in repo under fixtures/
- ensure parser runs against fixture

No speculative fixes.

---

<!-- === END REPLACEMENT BLOCK: ROOT CAUSE + EVIDENCE PACK + SCORECARD + FIXTURES === -->

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
