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

## 3. DEFAULT INPUT FOR UI WORK

Preferred input:

1. uploaded ZIP
2. target page
3. 3 screenshots:
   - mobile
   - desktop normal
   - desktop wide
4. 1 clear goal sentence

---

## 4. DEFAULT UI WORK LOOP

For each UI page:

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

---

## 5. PATCH OUTPUT CONTRACT

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

## 6. OWNER-FILE RULE

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

## 7. TOKEN-FIRST RULE

- Reuse existing design tokens first.
- Introduce new values as tokens only when they are truly reusable.
- Avoid repeated hardcoded UI values.

---

## 8. UI-POLISH RULE

- UI-polish patches should be CSS-only unless root cause proves otherwise.
- Do not spread small visual fixes across multiple files without proof.

---

## 9. OVERLAY RULE

- All overlays must render in a dedicated overlay root directly under `body`.
- Never render overlays inside sticky, transformed, or backdrop-filter containers.

---

## 10. DEPLOY / ASSET SAFETY

- Preserve deterministic build and versioning behavior.
- Never break service worker, cache, or asset-reference logic.
- Broken asset references are fail-fast.
- Validate on `staging` before `main`, except for urgent live hotfixes.

---

## 11. DEPRECATED PROMPT FILES

The following files are not canonical workflow controllers anymore:

- `docs/prompts/session-open.md`
- `docs/prompts/session-close.md`

Canonical project control is now limited to:

- `MASTER.md`
- `ENGINEERING.md`
- the uploaded ZIP
- the current target page
- the current screenshots

<!-- === END CANONICAL ENGINEERING FILE === -->
