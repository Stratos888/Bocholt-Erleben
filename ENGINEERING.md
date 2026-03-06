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

## PATCH OUTPUT FORMAT (MINIMAL, DEFAULT)

If a response contains Replace-instructions, it MUST use this exact minimal format and nothing else:

For each file:

1) **File:** `<exact path>`

For each replace operation in that file:

2) **BEGIN line:** `<exact, verbatim line from the current file>`
3) **END line:** `<exact, verbatim line from the current file>`
4) **Replacement block:** (a single fenced code block)

Rules:

- Do NOT include patch-notes, rationale, checklists, “next steps”, or any other commentary inside patch responses.
- Do NOT include fingerprints/hashes/byte counts by default (only on explicit user request).
- Replace ranges MUST NOT overlap within a single response. If they would overlap: merge into one larger Replace-block.
- BEGIN/END lines MUST be unique within the file. If not unique: STOP and request a better anchor (or the current relevant block).
- If BEGIN/END lines cannot be found exactly: STOP and request the current file or the relevant block.

## MARKER RULE (UNIQUE BLOCK IDS, NO PATCH NOTES IN FILES)

Every inserted or replaced code block MUST include markers with a unique, stable block id:

BEGIN marker:

`/* === BEGIN BLOCK: <UNIQUE_ID> | Purpose: ... | Scope: ... === */`

END marker:

`/* === END BLOCK: <UNIQUE_ID> === */`

Rules:

- `<UNIQUE_ID>` must be globally unique within the file (never reuse the same id for a different block).
- No “patch notes” headers/paragraphs inside code files (CSS/JS/HTML). Use only these BEGIN/END block markers.
- If a block needs a short explanation, keep it inside the BEGIN marker line only (purpose + scope), no multi-paragraph commentary.

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
