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

<!-- === BEGIN REPLACEMENT BLOCK: CSS ARCHITECTURE CONTRACT (Public entrypoint + file ownership) | Scope: canonical CSS split rules after style.css decomposition === -->

# CSS ARCHITECTURE CONTRACT (PUBLIC ENTRYPOINT + FILE OWNERSHIP)

Public CSS entrypoint rule:
- The only public CSS entrypoint allowed in HTML is `/css/style.css?v=...`.
- HTML must never directly load `base.css`, `pages.css`, `components.css`, `home.css`, `overlays.css`, or `legacy.css`.

Canonical aggregator rule:
- `css/style.css` is the only canonical aggregator.
- `css/style.css` may only import CSS in this canonical order:
  1) `base.css`
  2) `pages.css`
  3) `components.css`
  4) `home.css`
  5) `overlays.css`

File ownership rule:
- `base.css` owns global foundation, tokens, app-wide header/footer/focus rules, and global icon base rules.
- `pages.css` owns content/static page styling.
- `components.css` owns reusable UI components and component states.
- `home.css` owns home/hero/feed/search-row layout and home-specific responsive behavior.
- `overlays.css` owns detailpanel, filter-sheet, modals, overlay locks, and overlay-adjacent states.

Boundary rule:
- Components style themselves; page/layout files place them.
- Layout fixes belong in the owning layout file, not in `components.css`.
- Overlay mechanics/locks must stay in `overlays.css`, not in page/layout files.
- Cross-file patches are allowed only with explicit root-cause proof.

Legacy rule:
- `css/legacy.css` is deprecated.
- It must not be re-imported into `css/style.css`.
- It must not become an active owner file again except in an explicit cleanup/removal task.

Patch discipline rule:
- For CSS bugs, patch the owner file first.
- If the fix appears to require changes in multiple CSS files, prove why before patching.

<!-- === END REPLACEMENT BLOCK: CSS ARCHITECTURE CONTRACT (Public entrypoint + file ownership) === -->

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

# BRANCH / RELEASE RULE

Standard workflow is mandatory:

- `staging` = default work + test branch
- `main` = production / release branch

Rules:

- Routine changes must start on `staging`, not on `main`
- Validate changes on `https://staging.bocholt-erleben.de` first
- Only after successful staging validation: merge `staging` into `main`
- Then deploy `main`
- After `main` deploy, verify both:
  - `https://bocholt-erleben.de`
  - `https://staging.bocholt-erleben.de`

Exception:
- Direct edits on `main` are allowed only for urgent live hotfixes
- Every direct `main` hotfix must be synced back into `staging` immediately after

# STAGING INFRASTRUCTURE RULE

The staging environment is now part of the canonical project infrastructure.

Canonical staging target:

- `https://staging.bocholt-erleben.de`

Rules:

- Never treat staging as optional or temporary
- Never propose routine direct work on `main` if `staging` is available
- Never break the staging URL, its SSL setup, or its deploy path without explicit instruction
- The deploy workflow must preserve the remote `staging/` folder during live deploys
- The wildcard SSL setup for `bocholt-erleben.de` + subdomains is part of the staging baseline and must be respected

# DEPLOYMENT RULE

Deployment must remain deterministic.

Never break asset references.

Fail fast if asset inconsistency detected.

Live deploy must not remove or damage staging infrastructure.

Staging deploy must remain isolated from live behavior as far as possible within the current STRATO setup.

Never break existing working functionality.

Preserve behavior.

Asset hardening rules:
- Fail fast if any HTML file references CSS differently than `/css/style.css?v=...`.
- Fail fast if any HTML file directly references `base.css`, `pages.css`, `components.css`, `home.css`, `overlays.css`, or `legacy.css`.
- Fail fast if `deploy/css/style.css` does not keep the canonical five-file import set and canonical order.
- Fail fast if the version-rewrite step does not propagate the active build version into the imported CSS URLs inside `deploy/css/style.css`.

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
