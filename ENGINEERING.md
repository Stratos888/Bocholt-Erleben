# ENGINEERING RULES — BOCHOLT ERLEBEN

<!-- === BEGIN CANONICAL ENGINEERING FILE: Hard implementation rules only === -->

## 1. SOURCE OF TRUTH

- The uploaded ZIP is the canonical working baseline for each chat.
- If the user says manual edits were made or a patch was not applied, the current file content becomes the new truth.
- Never patch without visible current code for the affected file(s).

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

- Output code/document changes as concrete replace instructions only.
- Always specify:
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

## 9. UI-POLISH RULE

- UI-polish patches should be CSS-only unless root cause proves otherwise.
- Do not spread small visual fixes across multiple files without proof.

---

## 10. OVERLAY RULE

- All overlays must render in a dedicated overlay root directly under `body`.
- Never render overlays inside sticky, transformed, or backdrop-filter containers.

---

## 11. DEPLOY / ASSET SAFETY

- Preserve deterministic build and versioning behavior.
- Never break service worker, cache, or asset-reference logic.
- Broken asset references are fail-fast.
- Validate on `staging` before `main`, except for urgent live hotfixes.

---

## 12. DEPRECATED PROMPT FILES

If deprecated prompt files still exist, they are not canonical workflow controllers.

Canonical project control is limited to:

- `Produktvertrag.md`
- `MASTER.md`
- `ENGINEERING.md`
- the uploaded ZIP
- the active workpack input
