# ENGINEERING RULES — BOCHOLT ERLEBEN

<!-- === BEGIN CANONICAL ENGINEERING FILE: Hard implementation rules only === -->

<!-- === BEGIN BLOCK: ENGINEERING_CHATGPT_EXECUTION_ROUTER_V1 | Zweck: macht die Engineering-Regeln fuer ChatGPT als ausfuehrende KI sofort anwendbar; Umfang: Start-Gates, Mode-Router, Preview-vor-Deploy, Dirty-Tree-Disziplin === -->
## 0. CHATGPT EXECUTION ROUTER

These rules are optimized for ChatGPT executing project work. Apply this router before any patch, command sequence, commit, or deploy recommendation.

### 0.1 Start gate for every work item

Before starting implementation work:

1. Establish the current baseline:
   - In Codespaces/repo work: use `git status --short`, current branch and current `HEAD`.
   - In ZIP-only review work: use the uploaded ZIP as the visible baseline.
   - If both exist, the user's current Codespaces/repo output overrides an older ZIP.
2. If the working tree is dirty:
   - Do not start a new workpack.
   - First classify the existing changes as:
     - current work to continue,
     - unrelated WIP to stash,
     - changes to commit,
     - changes to discard.
3. Choose exactly one primary working mode:
   - UI/CSS/static JS polish → Mode A.
   - New route/content hub → Mode B.
   - Feature/logic/data/backend → Mode C.
4. Identify owner files before patching.
5. Prefer one small, sustainable next step over broad patch bundles.

### 0.2 Validation router

Use the cheapest reliable validation environment for the task:

- UI/CSS/static JS visual polish:
  - Use Codespaces Preview on port `8000` before commit/push whenever the change can be validated locally.
  - Do not use staging deploy as the first visual feedback loop.
  - Staging remains useful after commit/push or when service worker/build/runtime behavior must be validated.
- Backend/PHP/API/DB/Stripe/mail/STRATO/deploy behavior:
  - Validate with the relevant lint, smoke, API, database or staging checks.
  - Codespaces static preview is not sufficient.
- Data/Sheet/Event pipeline work:
  - Verify the source of truth first.
  - Do not treat generated repo artifacts as editorial truth.
- Visual asset quality work:
  - Use pool/audit/asset contracts first.
  - Do not permanently fix weak individual images with ad-hoc CSS.

### 0.3 Commit and deploy discipline

For UI polish:

1. Patch locally.
2. Run syntax/diff checks.
3. Preview locally via Codespaces when applicable.
4. Only then commit.
5. Push/deploy only after local validation or when deploy-specific validation is required.

Never stack a new patch on unrelated uncommitted WIP.
<!-- === END BLOCK: ENGINEERING_CHATGPT_EXECUTION_ROUTER_V1 === -->

## 1. SOURCE OF TRUTH

- In Codespaces/repo workflows, the current branch, current `HEAD`, and visible file contents are the canonical working baseline.
- In ZIP-only analysis workflows, the uploaded ZIP is the canonical visible baseline.
- If the user provides newer terminal output, file excerpts, screenshots, or confirms manual edits, that newer evidence overrides older ZIP contents.
- Never patch without visible current code for the affected file(s).

### Data source rule

- Production/staging event data is hybrid.
- The public event feed consists of:
  1. Google Sheets tab `Events` as the editorial source for KI-/Sheet- and manually maintained events. During deploy, this tab is exported to `data/events.tsv` and transformed into the generated runtime feed `/data/events.json`.
  2. Approved DB submissions from `/api/events/public.php` for organizer submissions after final review approval.
- Organizer submissions are not written back into the Google Sheet as part of the V1 publishing flow.
- `data/events.tsv` and `data/events.json` are generated during deploy and must not be maintained or reviewed as repository source files.
- `data/events.json` must still exist in the deployed runtime because the frontend, SEO schema and service worker load it.
- A stale repository/ZIP copy of `data/events.json` is not proof that live/staging event data is stale; the repository copy should not be tracked.
- Treat `data/search-metrics.json` as a runtime/deploy artifact as well. A local ZIP value such as `not_configured` does not invalidate a live dashboard proof; live measurement state must be judged from the deployed live dashboard/export, not from stale repository artifacts.
- The Kuratier-Inbox is also hybrid:
  1. Google Sheet / generated Inbox data for KI-/Sheet candidates.
  2. DB submissions for organizer single-event, membership and activity-presence submissions.
- Google-Sheet-Inbox data is separated by tab, not by separate sheet:
  - `main` / live uses `Inbox` and `Inbox_Archive`.
  - `staging` uses `Inbox_Staging` and `Inbox_Archive_Staging`.
- Deploy must resolve the Inbox tab by branch and export from that resolved tab.
- Staging must never read or write the live `Inbox` tab for KI-/Sheet candidates.
- Production KI workflows run on `main` only and must target the live Inbox/push path.
- `Inbox → Events` import is production-only and must not run on `staging`.

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
4. `ROADMAP.md` for tactical prioritized workpacks / To-dos
5. `ENGINEERING.md` for implementation rules and working modes

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

1. Check baseline and dirty working tree first.
2. Define page contract:
   - Goal
   - Freeze
   - Target State
   - Acceptance Criteria
3. Identify owner files and define only 3 main gaps.
4. Deliver 1 consolidated main patch.
5. Validate locally with Codespaces Preview on port `8000` before commit/push whenever the change is UI/CSS/static JS and does not require backend/deploy behavior.
6. Deliver at most 1 polish patch.
7. Freeze the page.

Do not drift into open-ended visual iteration loops. Do not use staging deploy as the first visual feedback loop for changes that can be checked in Codespaces Preview.

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

Default output format:

- Use a unified Git patch whenever the current ZIP/repo baseline is visible and the change can be applied safely with `git apply`.
- The user validates first with `git apply --check patch.diff` before applying the patch.
- The patch must be consolidated, owner-file focused, and free of unrelated edits.
- After the user has provided the required baseline proof, do not default back to prose-only insertion instructions.
- Do not provide vague placement instructions such as `append at the end`, `insert after this section`, or `add this block` as the primary patch format when a Git patch is safe.
- Do not provide long manual copy/paste snippets when a unified Git patch can safely express the same change.

Fallback format:

- Use concrete replace instructions only when a Git patch would be unsafe, too ambiguous, or when the affected file has diverged from the visible baseline.
- The fallback must still be deterministic and copy/paste-safe.
- Always specify all of the following:
  - file
  - exact block to replace, preferably by existing begin/end marker comments
  - exact BEGIN line or unique BEGIN marker
  - exact END line or unique END marker
  - complete replacement block
- Never split one logical replacement into drifting snippets.
- Never include nested fenced code blocks inside a copy/paste block.
- Consolidate overlapping edits into one replacement per file.
- Do not append drifting snippets.
- When inserting or replacing code blocks, use clear begin/end marker comments where technically appropriate.

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

Assistant response discipline:

- For implementation work, answer with the next concrete action only.
- When a patch is requested or clearly needed, provide the patch as one terminal-ready Git patch creation block.
- Do not describe where the user should manually paste code when a safe Git patch is possible.
- Do not mix patch delivery with broad planning text.
- If a documentation change is needed, treat documentation files like normal owner files and patch them with the same rules.
- If the safe patch format is unclear, stop and ask for the missing baseline or affected file content instead of guessing.

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


<!-- === BEGIN BLOCK: ENGINEERING_PREMIUM_VISUAL_ASSET_CONTRACT_2026_06_01 | Zweck: technische Regeln fuer nachhaltige Premium-Bildausspielung; Umfang: Event-/Activity-Card-Assets, Statuslogik, Cropping-Grenzen, Audit-Pflicht === -->
## 11.1 PREMIUM VISUAL ASSET CONTRACT

For event and activity visuals, the default solution is not manual crop guessing. The default solution is a prepared, reviewed card asset.

Rules:

- Card visuals should be prepared as 16:9 WebP assets for card contexts.
- Preferred visual source hierarchy:
  1. own/exclusive premium real photo,
  2. premium real photo explicitly cleared by organizer/rightsholder,
  3. otherwise legally clean and high-quality photo with documented source, rights basis, license and required credit,
  4. self-generated symbolic AI premium visual,
  5. legacy external image only as temporary non-ready source material.
- If a legally clean premium real photo is not available, the default replacement path is a self-generated symbolic AI premium visual, not manual crop rescue of a weak external image.
- Raw large source images, arbitrary external images, unclear-license images or unreviewed crops must not be promoted into premium surfaces directly.
- Visual status values are:
  - `ready`: approved for premium card use.
  - `usable`: acceptable in normal lists, but not automatically approved for Today/Home prominence.
  - `fallback`: approved symbolic fallback when no better specific visual exists.
  - `needs_review`: not allowed in prominent surfaces.
  - `blocked`: not allowed.
- Today/Home and other prominent recommendation surfaces may use only `ready` visuals or deliberately approved `fallback` visuals.
- If a visual looks weak because of subject, crop, clutter, pipes, signs, harsh shadows, poor resolution, unclear rights or inconsistent style, do not solve it as a permanent CSS/object-position hotfix.
- Replace with a cleared premium photo, regenerate as symbolic AI premium visual, downgrade or exclude weak visuals instead of masking them with layout code.
- CSS may define the stable rendering frame, aspect ratio and fallback object-position; CSS must not become the quality-control system for individual images.
- A future visual-audit view should preview assets in real card contexts before they are marked `ready`.

Implementation guidance:

- Extend existing visual pools or item data only through clear owner files.
- Keep one shared standard for Today cards and feed cards unless a later proof shows a real context-specific requirement.
- Prefer generated/prepared card assets over trying to rescue unsuitable source images at runtime.
- When adding a new visual workpack, include checks for file existence, format, reasonable size, status and visual preview readiness.

<!-- === END BLOCK: ENGINEERING_PREMIUM_VISUAL_ASSET_CONTRACT_2026_06_01 === -->

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

3. Default patch delivery is a copyable terminal block in chat. Use a temporary `patch.diff` in the repo for normal Git patches, or a guarded script patch when targeted replacements are safer.
   Do not provide separate sandbox/download patch files unless the user explicitly requests a downloadable file.

4. If the user applies the patch in Codespaces, the repo baseline is more important than an uploaded ZIP.

5. For Git patches, always run:
   - git apply --check patch.diff
   - git apply patch.diff
   - rm patch.diff
   - git diff --check
   - git --no-pager diff -- <affected-file>

   Do not use plain `git diff -- <affected-file>` in Codespaces workflows, because it can open the terminal pager and look like a frozen command. If the pager opens anyway, exit it with `q`.

6. If git apply --check fails:
   - stop immediately
   - verify the working tree
   - inspect the current owner block
   - do not retry with a similar large patch

7. For CSS/UI polish with many small declarations, prefer a robust script patch over a large Git diff.

8. A robust script patch must:
   - target only the relevant owner file or owner block
   - verify every selector/block uniquely before writing
   - abort without writing if anything is missing or ambiguous
   - end with git diff --check and git --no-pager diff -- <affected-file>

9. If block markers are inconsistent, patch against the real current marker state. Marker cleanup must be explicit and must not change runtime behavior.

10. After every failed patch attempt, run:
   - git status --short
   - git --no-pager diff -- <affected-file>

11. No blind retries.

Correct sequence after a failed patch:

failure
-> verify working tree
-> inspect current owner/block
-> identify mismatch
-> create smaller corrected patch
-> validate fail-fast

<!-- END PATCH_WORKFLOW_CORRECTION_V1 -->
<!-- BEGIN UI_DASHBOARD_PATCH_DISCIPLINE_V1 -->

## UI-/Dashboard-Patch-Disziplin

Für größere UI-, Dashboard- oder Strukturpolish-Arbeiten gilt zusätzlich:

1. Keine größeren UI-/Dashboard-Patches auf einem dirty Working Tree.
   - Wenn bereits uncommitted Änderungen vorhanden sind, zuerst entscheiden:
     - gezielt finalisieren und prüfen, oder
     - vollständig zurücksetzen und sauber neu starten.
   - Keine neuen großen Patches auf halbfertige Zwischenstände stapeln.

2. Vor größeren UI-/Dashboard-Patches zuerst eine Owner-Map erstellen.
   - Struktur/HTML-Owner
   - Rendering-/State-Owner
   - Styling-/CSS-Owner
   - bestehende Logik, die ersetzt oder ausdrücklich behalten wird
   - mögliche doppelte Alt-/Neu-Logik identifizieren, bevor gepatcht wird.

3. Große Terminal-Heredocs vermeiden.
   - Bevorzugt kleine, robuste Script-Patches mit eindeutigen Ankern.
   - Jeder Script-Patch muss abbrechen, wenn ein Anker fehlt oder mehrdeutig ist.
   - Lange Copy/Paste-Blöcke nur verwenden, wenn sie wirklich die sicherste Option sind.

4. Korrekturschleifen hart begrenzen.
   - Wenn nach einem größeren Patch mehr als ein Korrekturpatch nötig wird: stoppen.
   - Danach Diff prüfen und entscheiden:
     - konsolidieren, oder
     - Working Tree zurücksetzen und neu aufbauen.
   - Nicht weiterflicken.

5. Dashboard-V2-/Layout-Arbeiten als eigenen Workpack behandeln.
   - Erst Zielzustand und Reihenfolge definieren.
   - Dann ein kleiner, testbarer Strukturpatch.
   - Danach maximal ein Polish-Patch.
   - Erst nach Screenshot-/Smoke-Proof dokumentieren oder committen.

<!-- END UI_DASHBOARD_PATCH_DISCIPLINE_V1 -->

<!-- === BEGIN BLOCK: ENGINEERING_CODESPACES_PREVIEW_WORKFLOW_V1 | Zweck: dokumentiert schnellen lokalen UI-Preview-Workflow in Codespaces; Umfang: statische PWA-Ansichten, nicht Backend-/Deploy-Validierung === -->
## Codespaces Preview für schnelle UI-Prüfungen

Für schnelle UI-, CSS- und statische JS-Prüfungen kann in Codespaces ein lokaler Preview-Server genutzt werden. Das spart Zwischen-Deploys auf `staging` und ist besonders geeignet für Card-Layouts, Detailpanel, Header, Bottom-Navigation, responsive Verhalten und Bilddarstellung.

Start im Repo-Root:

    python3 -m http.server 8000 --bind 0.0.0.0 > /tmp/bocholt-preview.log 2>&1 &
    echo $! > /tmp/bocholt-preview.pid

Danach in Codespaces den Port `8000` über den Reiter `Ports` öffnen. Wichtige lokale Prüfrouten:

- `/`
- `/events/`
- `/aktivitaeten/`
- `/ueber/`
- `/events-veroeffentlichen/`

Bei PWA-/Browser-Cache-Problemen einen Cache-Buster nutzen, z. B. `?preview=ui-check`.

Stoppen:

    kill "$(cat /tmp/bocholt-preview.pid)"
    rm -f /tmp/bocholt-preview.pid /tmp/bocholt-preview.log

Grenze: Diese Preview ersetzt keine produktionsnahe Prüfung von STRATO, PHP-Endpunkten, Stripe, Webhooks, Mailversand oder Deploy-spezifischem Verhalten. Für solche Themen bleibt `staging` die maßgebliche Validierungsumgebung. Einzelne lokale Console-Warnungen durch Codespaces-/GitHub-Preview-Redirects oder fehlende generierte Datenexporte sind für reine UI-Prüfungen nicht automatisch blockierend.
<!-- === END BLOCK: ENGINEERING_CODESPACES_PREVIEW_WORKFLOW_V1 === -->
