# ENGINEERING RULES — BOCHOLT ERLEBEN

<!-- === BEGIN CANONICAL ENGINEERING FILE: Hard implementation rules only === -->

## 1. SOURCE OF TRUTH

- The uploaded ZIP is the canonical working baseline for each chat.
- If the user says manual edits were made or a patch was not applied, the current file content becomes the new truth.
- Never patch without visible current code for the affected file(s).

### Data source rule

- Production/staging event data is hybrid.
- The public event feed consists of:
  1. Google Sheet / generated `data/events.json` for editorial, KI-/Sheet- and manually maintained events.
  2. Approved DB submissions from `/api/events/public.php` for organizer submissions after final review approval.
- Organizer submissions are not written back into the Google Sheet as part of the V1 publishing flow.
- `data/events.json` and `data/inbox.json` remain deploy artifacts for the sheet-based paths.
- A stale repository/ZIP copy of `data/events.json` is not proof that live/staging event data is stale.
- Treat `data/events.json` as a runtime artifact unless the current deploy output itself is being inspected.
- The Kuratier-Inbox is also hybrid:
  1. Google Sheet / generated Inbox data for KI-/Sheet candidates.
  2. DB submissions for organizer single-event and membership submissions.
---

## 2. PROOF BEFORE PATCH

- Never guess.
- For bugs: prove root cause before patching.
- For UI work: use the real ZIP, the real file structure, and the provided screenshots.
- Never claim certainty without proof.

---

## 3. CANONICAL PROJECT HIERARCHY

Use this hierarchy when documents, screenshots, or repo leftovers create ambiguity:

1. visible current code from the uploaded ZIP
2. `Produktvertrag.md` for canonical product logic
3. `MASTER.md` for strategic direction / frozen areas / current focus
4. `ENGINEERING.md` for implementation rules and working modes

Rules:

- Do not treat old routes, leftover files, or repo presence alone as canonical product truth.
- Do not redefine product rules from `Produktvertrag.md` inside `MASTER.md`, UI copy, or ad-hoc chat reasoning.
- If a contradiction is found, resolve it at the canonical source first.

---

## 4. WORKING MODES

Every chat must run in exactly one primary mode.

### MODE A — UI POLISH / EXISTING PAGE OPTIMIZATION

Preferred input:

1. uploaded ZIP
2. target page
3. 3 screenshots:
   - mobile
   - desktop normal
   - desktop wide
4. 1 clear goal sentence

Execution loop:

1. define page contract:
   - Goal
   - Freeze
   - Target State
   - Acceptance Criteria
2. define only 3 main gaps
3. deliver 1 consolidated main patch
4. deliver at most 1 polish patch
5. freeze the page

Do not drift into open-ended visual iteration loops.

### MODE B — NEW ROUTE / CONTENT HUB

Preferred input:

1. uploaded ZIP
2. target route
3. page purpose
4. desired blocks / order
5. 1 reference page

Execution loop:

1. define page contract:
   - Goal
   - Audience
   - Page Role
   - Freeze
   - Acceptance Criteria
2. define content / structure contract:
   - required blocks
   - block order
   - CTA logic
   - excluded content
3. deliver 1 consolidated multi-file implementation patch
4. review once
5. freeze the page structure

Do not start with CSS polish before the structure contract is clear.

### MODE C — FEATURE / LOGIC / DATA

Preferred input:

1. uploaded ZIP
2. user flow
3. trigger
4. data source
5. desired behavior
6. definition of done

Execution loop:

1. define flow contract / root-cause contract
2. identify owner files
3. deliver 1 consolidated implementation patch
4. provide smoke-test proof points

Do not mix feature work with unrelated UI polish in the same workpack.

---

## 5. ONE CHAT = ONE WORKPACK

- Each chat should focus on one primary workpack only.
- Do not mix UI polish, new route design, feature logic, and product-governance changes in the same implementation round unless root cause proves they are inseparable.

---

## 6. PATCH OUTPUT CONTRACT

Preferred output format:

- Use a unified Git patch when the current ZIP/repo baseline is visible and the change can be applied safely with `git apply`.
- The user validates first with `git apply --check patch.diff` before applying the patch.
- The patch must be consolidated, owner-file focused, and free of unrelated edits.

Fallback format:

- Use concrete replace instructions when a Git patch would be unsafe, too ambiguous, or when the affected file has diverged from the visible baseline.
- Always specify:

Baseline rule for Git patches:

- Before a Git patch is created, the current repository state must be proven with:
  - `git status --short`
  - `git branch --show-current`
  - `git pull --ff-only`
  - `git rev-parse --short HEAD`
- A Git patch may only be created against that proven branch and commit SHA, or against a fresh ZIP exported from that same state.
- Every Git patch must be validated with `git apply --check patch.diff` before it is applied.
- If `git apply --check patch.diff` fails, the patch must not be applied or manually repaired.
- In that case, re-check the current repository state and create a new patch from the updated baseline.

Concrete replace instructions must:
  - file
  - exact BEGIN line
  - exact END line
  - replacement block
- Consolidate overlapping edits into one replacement per file.
- Do not append drifting snippets.
- When inserting or replacing code blocks, use clear begin/end marker comments where technically appropriate.

---

## 7. OWNER-FILE RULE

Patch the owning file first.

Ownership:

- `css/style.css` = public CSS entrypoint / import order only
- `css/base.css` = tokens, foundation, app-wide primitives
- `css/pages.css` = content/static page styling
- `css/components.css` = reusable UI components and component states
- `css/home.css` = home, hero, feed, search-row layout
- `css/overlays.css` = detailpanel, sheets, modals, overlay locks

Rules:

- Components style themselves; page/layout files place them.
- Layout fixes belong in the owning layout file, not in component files.
- Overlay mechanics belong in `css/overlays.css`.
- Cross-file fixes are allowed only if root cause proves they are necessary.

---

## 8. TOKEN-FIRST RULE

- Reuse existing design tokens first.
- Introduce new values as tokens only when they are truly reusable.
- Avoid repeated hardcoded UI values.

---

## 9. UI-POLISH / NO-HOTFIX RULE

- UI-polish patches should be CSS-only unless root cause proves otherwise.
- Do not spread small visual fixes across multiple files without proof.
- Do not solve UI regressions by appending late override blocks when the affected component already has an owner block.
- Prefer one consolidated owner replacement over stacked override chains.
- A staging branch is for validating sustainable fixes, not for shipping quick temporary fixes.
- If a patch creates this pattern, it is not acceptable as final implementation:

```text
base rule
→ desktop override
→ polish override
→ mobile restore override
→ later counter-override

The correct pattern is:
single owner block
→ shared component base
→ mobile contract
→ desktop contract
→ narrow/wide breakpoint refinements
Before patching UI regressions, identify whether the real problem is a missing owner, conflicting owners, or a broken breakpoint boundary.

## 10. OVERLAY RULE

- All overlays must render in a dedicated overlay root directly under `body`.
- Never render overlays inside sticky, transformed, or backdrop-filter containers.

---

## 11. DEPLOY / ASSET SAFETY

- Preserve deterministic build and versioning behavior.
- Never break service worker, cache, or asset-reference logic.
- Broken asset references are fail-fast.
- Validate on `staging` before `main`, except for urgent live hotfixes.

Asset/versioning rules:

- `css/style.css` is the public CSS entrypoint and must not be deleted.
- `css/style.css` owns CSS import order only.
- Do not patch `css/style.css` for normal visual changes unless the import order or actual CSS entrypoint changes.
- Do not manually bump asset query versions in multiple HTML files for normal CSS/JS edits.
- The deploy workflow replaces existing `?v=...` asset references with the generated `BUILD_ID`.
- Only touch asset references manually when a new asset is introduced, an asset is renamed, or a script/link tag is missing completely.


## 12. DEPRECATED PROMPT FILES

If deprecated prompt files still exist, they are not canonical workflow controllers.

Canonical project control is limited to:

- `Produktvertrag.md`
- `MASTER.md`
- `ENGINEERING.md`
- the uploaded ZIP
- the active workpack input

<!-- BEGIN PATCH_WORKFLOW_CORRECTION_V1 -->

## Patch workflow correction

For all future repo patches, use this order:

1. First prove the current repo baseline:
   - git status --short
   - git branch --show-current
   - git pull --ff-only
   - git rev-parse --short HEAD

2. Create patches only against the proven repo baseline.

3. If the user applies the patch in Codespaces, the repo baseline is more important than an uploaded ZIP.

4. For Git patches, always run:
   - git apply --check patch.diff
   - git apply patch.diff
   - rm patch.diff
   - git diff --check
   - git diff -- <affected-file>

5. If git apply --check fails:
   - stop immediately
   - verify the working tree
   - inspect the current owner block
   - do not retry with a similar large patch

6. For CSS/UI polish with many small declarations, prefer a robust script patch over a large Git diff.

7. A robust script patch must:
   - target only the relevant owner file or owner block
   - verify every selector/block uniquely before writing
   - abort without writing if anything is missing or ambiguous
   - end with git diff --check and git diff -- <affected-file>

8. If block markers are inconsistent, patch against the real current marker state. Marker cleanup must be explicit and must not change runtime behavior.

9. After every failed patch attempt, run:
   - git status --short
   - git diff -- <affected-file>

10. No blind retries.

Correct sequence after a failed patch:

failure
-> verify working tree
-> inspect current owner/block
-> identify mismatch
-> create smaller corrected patch
-> validate fail-fast

<!-- END PATCH_WORKFLOW_CORRECTION_V1 -->
