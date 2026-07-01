<!-- === BEGIN BLOCK: ENGINEERING_ACTIVITY_FAVORITES_LOCAL_STORAGE_2026_06_30 | Zweck: technische Leitplanken fuer lokale Activity-Favoriten; Umfang: Storage, Datenschutzabgrenzung, Event-Abgrenzung === -->
## Activity-Favoriten – technische Leitplanken

- Activity-Favoriten nutzen den bestehenden lokalen Nutzerpraeferenzspeicher `bocholt_erleben.user_preferences.v1`.
- Es werden keine Cookies gesetzt.
- Es gibt keine Backend-Synchronisierung, kein Login und keine Serveruebertragung.
- Der gespeicherte Schluessel ist fachlich `activity:<id>`.
- Events bekommen keine Favoritenlogik; fuer Events bleibt Kalender/Terminaktion der passende Nutzerpfad.
- Favoriten sind im UI als persoenliche Priorisierung zu behandeln, nicht als Filtergruppe. Schnellfilter bleiben Inhaltsfilter wie Situation, Ort/Naehe oder Aktivitaetsart.
- Mobile Schnellfilterleisten werden als horizontale Chip-Rail umgesetzt, sobald mehrere Chips sonst mehrzeilig werden. Desktop bleibt bei der bestehenden Wrap-/Grid-Darstellung; horizontales Scrollen ist dort kein Zielzustand.
- UI-Zustand muss ueber `activity:favorites-changed` neu gerendert werden, damit Card, Filter und Detailpanel synchron bleiben.
- Browser-Smoke muss die lokale Favoritenfunktion pruefen, ohne produktive Daten zu schreiben.
<!-- === END BLOCK: ENGINEERING_ACTIVITY_FAVORITES_LOCAL_STORAGE_2026_06_30 === -->

<!-- === BEGIN BLOCK: ENGINEERING_BROWSER_SMOKE_RULES_V1_2026_06_29 | Zweck: technische Regeln fuer Browser-Smoke-V1; Umfang: read-only, Trigger, Artefakte, keine Auto-Reparatur === -->
## Browser-Smoke V1 — Engineering-Regeln

Browser-Smoke ist erlaubt und gewuenscht, wenn er folgende Grenzen einhaelt:

- Nur read-only Browseraktionen.
- Keine echten Checkouts, E-Mails oder produktiven Schreibaktionen.
- Keine Auto-Reparatur und kein Auto-Rollback.
- Stabil vor vollstaendig: wenige robuste Kernwege statt viele fragile Detailtests.
- Desktop und Mobile werden mit Chromium/Playwright geprueft.
- Fehler erzeugen Summary, JSON und Screenshots als GitHub-Artefakte.
- Ein roter Staging-Browser-Smoke blockiert fachlich den Merge nach `main`.
- Ein roter Main-Browser-Smoke erzeugt Handlungsbedarf fuer Hotfix/Rollback-Entscheidung, fuehrt aber nicht automatisch Code aus.

Owner-Dateien:

- Script: `scripts/browser-smoke.mjs`
- Manueller Workflow: `.github/workflows/browser-smoke.yml`
- Deploy-Integration: `.github/workflows/deploy-strato.yml`
- Betriebsdoku: `BROWSER_SMOKE_SYSTEM.md`
<!-- === END BLOCK: ENGINEERING_BROWSER_SMOKE_RULES_V1_2026_06_29 === -->

<!-- === BEGIN BLOCK: ENGINEERING_PRIVACY_TRACKING_RUNTIME_CONTRACT_2026_06_29 | Zweck: technischer Contract fuer Datenschutz-/Tracking-Runtime nach P0-Umsetzung; Umfang: Consent-Gating, Serverguard, Folgearbeiten === -->
## Datenschutz-/Tracking-Runtime-Contract

Stand: 2026-06-29.

- GA4 und First-Party-Nutzwerttracking duerfen im Client nur nach aktiver Statistik-Zustimmung starten.
- `window.BEAnalytics` wird erst nach Zustimmung initialisiert; Featurecode muss weiterhin defensiv auf Existenz/Funktionen pruefen.
- `/api/value-track.php` muss Metriken ohne `be_statistics_consent=granted` ignorieren.
- Consent-/Datenschutzeinstellungen liegen in `config.js`; UI-Styles liegen in `css/components.css`; oeffentlicher Erklaertext liegt in `/datenschutz/`.
- Neue Tracking-, Reminder-, Push-, Karten- oder Personalisierungsfunktionen muessen zuerst gegen diesen Contract geprueft werden.
- Nach jeder Aenderung an Tracking/Consent mindestens pruefen: `node --check config.js`, `php -l api/value-track.php`, CSS-Governance-Audit und Live-Smoke ohne/mit Zustimmung.

<!-- === END BLOCK: ENGINEERING_PRIVACY_TRACKING_RUNTIME_CONTRACT_2026_06_29 === -->

<!-- === BEGIN BLOCK: ENGINEERING_PRODUCT_MATURITY_ROADMAP_RULES_2026_06_29 | Zweck: technische Arbeitsregeln fuer die validierte nicht-contentbezogene Produktreife-Roadmap; Umfang: Workpack-Reihenfolge, Validierung, Trennung von Content-Operation === -->
## Produktreife-Roadmap – technische Arbeitsregeln

Stand: 2026-06-29.

Fuer die validierte Nicht-Content-Roadmap gilt:

1. Datenschutz-/Tracking-Konsistenz ist P0.
   - Vor neuen groesseren Nutzerfeatures klaeren, ob GA4 aktiv bleibt, angepasst wird oder deaktiviert wird.
   - Datenschutzerklaerung, technische Runtime (`config.js`, `BEAnalytics`, `/api/value-track.php`), LocalStorage, Push, Formspree, Stripe und Anbieterbereich muessen konsistent behandelt werden.
   - Rechtstexte nicht beiläufig in einem Feature-Patch nebenbei umschreiben; als eigener Review-fähiger Workpack.

2. Vor groesseren Produktfeatures einen kleinen Browser-Smoke-Test-Grundstock einfuehren.
   - Zielpfade: `/`, `/events/`, `/aktivitaeten/`, Einreichungsseiten, `/zahlung-starten/`, `/fuer-veranstalter/login/`, `/fuer-veranstalter/dashboard/`.
   - Kein Volltest-Projekt als Einstieg; wenige stabile, wartbare Checks reichen.

3. `Merken / Fuer dich / Erinnern` nur auf vorhandener lokaler Profil-/Recommendation-Schicht aufbauen.
   - Keine Account-/Sync-Pflicht ohne ausdrueckliche Produktentscheidung.
   - Push/Reminder erst nach Datenschutz-/Einwilligungsentscheidung.

4. Standort-/Kartenarbeit beginnt mit Datenmodell, nicht mit UI.
   - Erst Koordinaten-/Location-ID-Kontrakt und Datenpflege klaeren.
   - Danach Karten-, Naehe- oder Umkreissortierung.

5. Anbieter-/Verkaufsreife ist kein Dashboard-Neubau.
   - Vorhandene Anbieter-, Billing-, Mail- und Nutzwertpfade verbessern: Verstaendlichkeit, naechste Aktion, Statusklarheit, oeffentliche Leistungsbeschreibung.

6. UI-/CSS-/JS-Konsolidierung bleibt owner-file-orientiert.
   - Keine grosse Neuarchitektur.
   - Keine spaeten Override-Schichten, wenn ein bestehender Owner-Block sauber angepasst werden kann.
   - Konsolidierung nur mit konkretem Feature-/Bugfix-Kontext oder belegtem Wartbarkeitsrisiko.

Abgrenzung: Content-Live-Lauf, KI-Suche, Content-Audit und Inbox-Content-Routing bleiben eigene Workstreams und duerfen nicht mit dieser Produktreife-Roadmap vermischt werden.
<!-- === END BLOCK: ENGINEERING_PRODUCT_MATURITY_ROADMAP_RULES_2026_06_29 === -->

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

### Content Quality Guard rule

- Regular content quality checks must follow the actual source ownership:
  1. Google Sheet tab `Events` for editorial events.
  2. Public DB/API feed `/api/events/public.php` for approved organizer events.
  3. `data/offers.json` for Activities.
- Audit results are written to `Content_Audit` on live/main and `Content_Audit_Staging` on staging.
- The private `/inbox/` is the human workbench: event candidates and content-quality findings must remain separate queues, but use the same review UI.
- The audit is action-only for the user-facing workbench:
  - every run checks the relevant source set again,
  - safe technical findings are auto-handled or silently observed,
  - correct/benign results do not become review tasks,
  - only findings that cannot be solved safely by the audit itself appear as open work.
- Harmless canonical redirects, language-path redirects and one-off transient network timeouts are not user tasks, but must be rechecked by cheap technical checks on the next run.
- HTTP 429, bot protection, blocked pages, repeated timeouts or weak fact confirmation are not automatically user tasks. They should become typed `ai_verification_candidate` / retry candidates before they are escalated to the Inbox.
- AI verification must be a targeted fallback, not a replacement for the audit. Do not send all content through AI by default.
- AI verification must use a cache/validity model. A fresh `confirmed` result with unchanged `source_fingerprint` and `content_fingerprint` must suppress repeat KI checks until `verified_until` or `next_check_at` requires it.
- Manual proof tests or reviewed confirmations must use `Content_Verification_Acceptance` on `main` and `Content_Verification_Acceptance_Staging` on `staging`; do not ask the user to edit generated `Content_Audit` rows or technical fingerprints directly.
- Content-search feedback must be derived from typed audit findings, Inbox decisions, rejection reasons and KI verification outcomes. Do not rely on the user manually rewriting KI search rules every few weeks.
- `data/content-search-feedback.json` is a generated audit/report artifact, not a hand-maintained editorial data source. The workflow handoff is the Sheet tab `Content_Search_Feedback` on live/main and `Content_Search_Feedback_Staging` on staging.
- The Weekly-KI-Suchlauf may read `Content_Search_Feedback` as prompt/source/validation context and may log applied feedback classes in diagnostics. It must also include typed Inbox/Inbox_Archive rejection history where available. It must not treat feedback as permission to write canonical content or permanently self-mutate the rulebook.
- Feedback-loop automation may improve search prompts, source preferences and validation priorities, but it must not silently mutate canonical Event, Activity or DB data.
- Search feedback must be capped and consolidated: group by feedback class/field/source scope, count duplicates instead of appending raw cases, keep examples diagnostic-only, expire stale one-off rejection signals, and pass only the highest-priority rules into the prompt.
- KI checks must be budgeted and prioritized by risk, for example near-term Events, blocked official sources, source/ticket conflicts, or stale Activity opening data.
- Audit workflows may write audit rows, summaries, verification metadata and review recommendations, but must not silently overwrite editorial source rows.
- Deterministic auto-handling is allowed only for safe technical cases, for example expired Events being excluded by the build/runtime feed, benign redirects being suppressed as tasks, or duplicate stale findings being kept out of the active queue.
- Semantically uncertain findings, such as changed times, cancellations, moved locations, unreachable sources, problematic redirects, ticket portals as primary Event source or stale Activity availability, must become `review_needed`, `warning` or a typed correction candidate, not a blind data mutation.
- The user should not be instructed to edit Google Sheets directly. For Sheet-owned Events, `/inbox/` → Content-Prüfung is the intended correction surface; an explicit reviewed action may write back to the canonical Google Sheet `Events` tab.
- Approved organizer events are DB/API-owned; their correction path must stay in the review/admin/DB workflow and must not be routed through the editorial Sheet.
- Activities remain repo-/JSON-owned in V1. Activity corrections must be collected as visible repo patch candidates unless a future owner contract explicitly moves them into a Sheet-/DB-backed source.
- KI facts-check results must be stored as structured states such as `confirmed`, `conflict`, `better_source_found`, `not_found` or `uncertain`. Free-text KI output alone is not sufficient for automated routing.
- Event visual-key and concrete image-fit correctness is a separate quality domain. It may be surfaced by the Content Guard as `visual_fit_candidate`, but rule/motif changes, image production and pool updates must be handled through the Visual Workflow and not mixed into generic link/date/opening-status fixes.

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

## 3A. DOCUMENTATION GOVERNANCE

Documentation is part of project control. It must be current-first and role-clean so future ChatGPT work does not reactivate obsolete tasks.

### Canonical document roles

- `MASTER.md` = short strategic control, current focus, frozen areas, permanent product direction.
- `ROADMAP.md` = current tactical backlog, active/geparkte/wartende workpacks, next proofs.
- `ENGINEERING.md` = hard working rules, patch modes, audits, fallback workflows.
- `TEST_STATUS.md` = proof archive and current test index, not product definition.
- `Produktvertrag.md` = product model, prices, tariffs, funnel/product logic.

### Current-first rule

- Active steering information belongs at the top of `MASTER.md`, `ROADMAP.md` or the index of `TEST_STATUS.md`.
- Long history must not be appended to `MASTER.md` as active context.
- `ROADMAP.md` must not become an undifferentiated archive; old blocks may remain only when the current top block clearly overrides them.
- `TEST_STATUS.md` may stay long, but must keep a current index near the top.
- Historical route names, old screenshots and old test paths in `TEST_STATUS.md` are evidence, not current architecture.

### Documentation patch rule

- A Doku patch must state which owner document it changes and why.
- Prefer small current-state/index updates over mass rewriting old history.
- If an old block is obsolete but still useful as evidence, leave it in `TEST_STATUS.md`; do not copy it into `MASTER.md` or the active ROADMAP section.
- If a contradiction is found, fix the canonical source first and only then adjust secondary references.
- Do not add broad future plans unless they become an active, geparkt or wartend workpack.

### Archive rule

- Use archive movement only for clearly obsolete large documentation clusters.
- Do not create `docs/archive/` churn just to make a small current-state correction.
- Archive moves, deletions or file splits are their own workpack and should not be mixed with UI, feature or visual patches.

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

- `css/style.css` = public CSS entrypoint / import order only; no selectors, no visual fixes
- `css/base.css` = tokens, reset, foundation, app-wide primitives
- `css/pages.css` = public content pages, static pages, funnel pages and legal pages
- `css/components.css` = reusable UI components and component states
- `css/home.css` = historical Discovery/Event/Activity shell owner; frozen against new large UI blocks
- `css/today.css` = Today/Home-specific surface and recommendation layout
- `css/overlays.css` = detailpanel, sheets, modals, overlay locks

Rules:

- Components style themselves; page/layout files place them.
- Layout fixes belong in the owning layout file, not in component files.
- Overlay mechanics belong in `css/overlays.css`.
- Cross-file fixes are allowed only if root cause proves they are necessary.
- `css/home.css` must not be used as the default dumping ground for new visual patches.
- New large CSS blocks require an owner decision first: existing owner, new owner file, or conscious extraction from an old owner.
- If an existing owner block is touched, prefer replacing/consolidating that owner block over appending a later override block.
- CSS governance is enforced by `tools/audit-css-governance.py`; do not bypass it to ship cosmetic patches.

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
- `css/style.css` must remain import-only; real selectors belong in owner files.
- Public HTML files must load `/css/style.css` as the single CSS entrypoint.
- Source-level CSS cache keys must stay consistent and are checked by `tools/audit-css-governance.py`.
- Do not patch `css/style.css` for normal visual changes unless the import order or actual CSS entrypoint changes.
- Do not manually bump asset query versions in multiple HTML files for normal CSS/JS edits, except in an intentional CSS-governance/cache-key normalization patch.
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

<!-- === BEGIN BLOCK: ENGINEERING_CODESPACES_COST_AND_FALLBACK_V1 | Zweck: begrenzt Codespaces-Verbrauch und definiert sicheren Fallback bei ausgeschöpftem Kontingent; Umfang: Machine-Type, parallele Codespaces, Webeditor-/Local-Git-Fallback === -->
## Codespaces Cost Discipline und Quota-Fallback

Codespaces ist die bevorzugte Ausführungsumgebung für Repo-Patches, lokale Preview, Checks, Commit und Push. Codespaces ist nicht als dauerhafte Denk-, Planungs- oder Parallel-Chat-Umgebung zu behandeln.

### Standardregeln

- Für dieses Projekt ist `2-core` der Standard-Maschinentyp.
- `4-core` oder größer darf nur genutzt werden, wenn ein konkreter schwerer Arbeitsschritt dies rechtfertigt, z. B. große Bildkonvertierungen, ungewöhnlich langsame Voll-Audits oder ein späterer echter Build-Prozess.
- Pro Repo soll normalerweise maximal ein aktiver Codespace genutzt werden.
- Mehrere parallele Chats dürfen nicht gleichzeitig konkurrierende Repo-Patches gegen denselben Branch liefern.
- Analyse, Planung, ZIP-Review, Bildprompt-Arbeit und längere UI-Bewertung sollen möglichst ohne laufenden Codespace stattfinden.
- Nach abgeschlossenem Commit/Push oder längerer Pause soll der Codespace gestoppt werden oder durch einen kurzen Idle-Timeout auslaufen.

### Quota-Fallback ohne Codespaces

Wenn das Codespaces-Kontingent ausgeschöpft ist oder Codespaces bewusst nicht genutzt werden soll, darf auf einen reduzierten Fallback-Workflow gewechselt werden.

Zulässige Fallback-Wege:

- GitHub-Webeditor für kleine, klar begrenzte Änderungen.
- Lokales Git auf dem Nutzer-PC, wenn verfügbar.
- ZIP-only Analyse durch ChatGPT mit anschließenden manuellen Ersetzungspatches.

Fallback-Einschränkungen:

- Keine großen UI-/CSS-Polish-Patches ohne lokale oder staging-nahe Sichtprüfung.
- Keine breit gestreuten Multi-Datei-Änderungen ohne aktuelle sichtbare Baseline.
- Keine unsicheren Snippet-Ergänzungen.
- Bevorzugt werden konkrete Block-Ersetzungen mit eindeutigem BEGIN-/END-Marker oder vollständige kleine Dateiänderungen.
- Nach manueller Änderung muss der Nutzer im GitHub-Diff prüfen, ob ausschließlich die beabsichtigten Dateien und Blöcke geändert wurden.
- Wenn ein sicherer Git-Patch-Check nicht möglich ist, muss der Patch kleiner und deterministischer sein als im normalen Codespaces-Workflow.

### ZIP-first Webupload-Fallback

Wenn Codespaces nicht verfügbar ist und der Nutzer ein aktuelles Projekt-ZIP liefert, ist der bevorzugte Fallback für kleine bis mittlere Patches:

1. Nutzer liefert eine aktuelle ZIP des Zielbranches.
2. ChatGPT prüft die Baseline lokal gegen den Zielzustand.
3. ChatGPT erstellt ein Patch-ZIP in echter Repo-Root-Struktur.
4. Das Patch-ZIP enthält ausschließlich Dateien/Ordner, die ins Repo übernommen werden sollen.
5. Keine Wrapper-/Hilfsdateien im Patch-ZIP: keine `README.txt`, keine `MANIFEST.json`, kein `UPLOAD_TO_REPO_ROOT`.
6. Nutzer entpackt das Patch-ZIP und lädt den entpackten Inhalt per GitHub Drag & Drop auf den Zielbranch hoch.
7. Nutzer wartet den Deploy ab und liefert danach eine neue ZIP zur finalen Prüfung.
8. ChatGPT prüft den neuen ZIP-Stand gegen Zielzustand, Checks und mögliche Restverweise.

Dieser Fallback ist bevorzugt für Doku-, HTML-, CSS-, statische JS-, kleine PHP- und kleine Tool-/Audit-Patches. Nicht bevorzugt ist er für große Refactorings, viele Löschungen, Dateiumbenennungen, komplexe Merge-Konflikte oder Asset-Massenänderungen.
<!-- === END BLOCK: ENGINEERING_CODESPACES_COST_AND_FALLBACK_V1 === -->

## Eventdaten-Quelle: Sheet-first

Redaktionelle Events haben eine feste Quellenhierarchie:

1. Kanonische Bearbeitungsquelle ist das Google Sheet, Tab `Events`.
2. `data/events.tsv` und `data/events.json` sind erzeugte Artefakte aus dem Deploy-/Export-Prozess.
3. Ein lokales `data/events.json` im Repo oder Codespace ist nur dann belastbar, wenn es unmittelbar aus dem aktuellen Sheet exportiert bzw. im Deploy neu erzeugt wurde.
4. `/api/events/public.php` ist eine zusätzliche Quelle für freigegebene DB-/Veranstalter-Events, aber nicht die Quelle für redaktionelle Sheet-Events.

Konsequenz:
- Bei Fragen wie „welche Events sind aktuell sichtbar?“ oder „welche Event-Visual-Gaps existieren?“ darf nicht blind vom lokalen `data/events.json` ausgegangen werden.
- Vor datenabhängigen Analysen muss geklärt werden, ob die Analyse auf frischem Sheet-Export, Deploy-Artefakt oder nur lokalem Repo-Snapshot basiert.
- `data/events.json` ist Website-Feed und Build-Artefakt, nicht redaktionelle Source of Truth.

