# MASTER CONTROL FILE — BOCHOLT ERLEBEN
# SINGLE SOURCE OF TRUTH FOR PROJECT STATE
# CHATGPT MUST FOLLOW THIS FILE STRICTLY

---

# SESSION INSTRUCTIONS FOR CHATGPT

You are operating a persistent production software project.

This file defines:

- project phase
- sprint scope<img width="388" height="747" alt="image" src="https://github.com/user-attachments/assets/1afd8458-1a10-4b0e-bc5f-6ae294637ea9" />

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

- Icons: Appweite Single-Source-of-Truth eingeführt:
  - `window.Icons` Registry (`js/icons.js`) mit SVG Line Icons
  - Script-Order in `index.html`: icons.js vor details.js/events.js
  - Detailpanel + Event Cards nutzen SVGs über `Icons.svg()` (keine Emojis mehr)
- Categories: Kanonische Kategorie ist single-source in `FilterModule.normalizeCategory()`:
  - Variante A umgesetzt: `Highlights` + `Wirtschaft` → `Innenstadt & Leben`
  - `Icons.categoryKey()` mappt ausschließlich kanonische Kategorien auf `cat-*` Keys
- CSS: Appweite Tokenisierung/Rendering für SVG Icons:
  - `svg.ui-icon-svg` globaler Base-Style (size/stroke/opacity via Tokens)
  - Kontext-Overrides (sm/md/lg + stroke-sm/md/lg) für Cards/Meta/Header
  - Detailpanel SVG-Guard scoped (UI-Icons clamped, Kategorie-Badge frei skalierbar)

REMAINING GAPS (NEXT WORKPACKS):

Detailpanel UI is now visually stabilized and considered ENTERPRISE BASELINE for the product.

The following improvements were implemented and verified during the latest UI stabilization session:

- Header hierarchy finalized
  - Category icon acts as indicator (not button)
  - Title baseline alignment corrected
  - Close button visual weight reduced

- Meta rows stabilized
  - Global gutter alignment enforced (no internal row inset)
  - Location arrow proximity fixed via grid layout (fit-content column)
  - Location wrapping fixed (no artificial width clamp)

- Description block typography stabilized
  - improved line-height and wrapping behavior
  - consistent spacing rhythm between sections

- Source row alignment stabilized
  - arrow alignment works for 1-line and 2-line sources
  - gutter alignment matches description block

- Actionbar visually integrated with content block

This means the DETAIL PANEL STRUCTURE is now ENTERPRISE READY.

Remaining future work (optional polish only):

1) Motion polish
   - panel opening / closing easing
   - drag interaction refinement

2) Accessibility audit
   - final keyboard navigation verification
   - ARIA roles verification

3) Feed scanning improvements (outside detailpanel)
   - event card hierarchy / scan speed

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

OVERALL STATUS:

SESSION REPORT (this session, verified):
- Status-/Feedback-Cluster in `css/style.css` wurde als eigener Workpack konsolidiert:
  - `.loading-container`, `.loading-spinner`, `.info-message`, `.error-message` auf App-/DS-02-DNA gezogen
  - Skeleton-Eventcards blieben funktional unverändert und wurden bewusst nicht angefasst
  - Zustands-Hierarchie zu Empty State / Offline-Hinweisen wurde geprüft und per CSS-only nachgeschärft
- Empty State sowie Offline-Badge/Toast wurden in die gemeinsame Zustandsfamilie eingeordnet:
  - Empty State ruhiger gegenüber Overlay-Zuständen
  - Offline-Hinweise klar niedriger priorisiert als Loading / Error / Info
  - Dev-Proofs/Konsolen-Checks für Status-/State-Prüfung wurden praktisch genutzt
- Geplanter Workpack „State Transition & Hierarchy Polish“ wurde bewusst vertagt:
  - keine weitere Energie in Rand-/Eventualitäts-Polish
  - Small-Viewport-/Prioritätskanten-/Übergangs-Feinschliff vorerst on hold
- Reale Event-Cards im Normalzustand wurden erneut geprüft:
  - Ergebnis: weitgehend konsolidiert, nur noch geringe Feinluft
  - kein neuer großer Event-Card-Workpack gestartet
  - Bereich vorerst eingefroren
- Detailpanel wurde erneut geprüft und minimal final nachgeschärft:
  - linke Fluchtung der Meta-Zeilen (Ort sowie Datum/Uhrzeit) per CSS-only angepasst
  - danach Detailpanel vorerst eingefroren
- Filterbereich wurde nicht erneut geöffnet:
  - gilt weiterhin als bereits gefreezt
- Info-/Content-Seiten hinter dem Info-Button wurden als nächster Hauptbereich bearbeitet:
  - gemeinsamer Content-/Info-Seiten-Cluster in `css/style.css` systemisch an die App-DNA angepasst
  - `/info/` als echter Hub finalisiert (Erklärung + Veranstalter-CTA + Navigation)
  - reduzierte Header-/Top-Chrome-Variante für Content-Seiten eingeführt, abgeleitet aus derselben App-Bar-DNA wie der Feed-Header
  - Seitenfamilie (`/info/`, `/ueber/`, `/events-veroeffentlichen/`, `/impressum/`, `/datenschutz/`) visuell gegengeprüft und in mehreren gemeinsamen CSS-Pässen konsolidiert
- Globales Design-System wurde erneut geprüft:
  - Ergebnis: im Wesentlichen konsolidiert und nicht mehr als offener Haupt-Workpack behandelt
  - verbleibend höchstens kleiner späterer Cleanup, kein eigener großer DS-Block
- Session-Endstand:
  - Feed, Filter, Event-Cards und Detailpanel gelten vorerst als eingefroren
  - Status-/Feedback-Cluster auf brauchbarem Stand; Rand-Polish vertagt
  - Info-/Content-Seiten deutlich konsistenter und näher an einer gemeinsamen App-Familie
  - nächster Chat sollte nicht alte UI-Hauptflächen wieder aufmachen, sondern auf Basis dieses Freeze-/Scope-Stands weiterarbeiten

DECISIONS LOG (permanent, project-wide):
- Status-/Feedback-Cluster:
  - Skeleton-Eventcards bleiben der bestehende Loading-Standard und werden in diesem Workpack funktional nicht verändert.
  - `.loading-container`, `.loading-spinner`, `.info-message`, `.error-message`, Empty State und Offline-Hinweise werden nur CSS-seitig an die App-DNA angeglichen, ohne Redesign und ohne JS-Logikänderung.
- State Transition & Hierarchy Polish:
  - Der Workpack zu Zustands-Übergängen, Prioritätskanten und Small-Viewport-Härtefällen wird vorerst bewusst auf Eis gelegt.
  - Keine weitere Energie in Rand-/Eventualitäts-Polish investieren, bis später explizit wieder aufgenommen.
- Event-Cards:
  - Event-Card Normal State Polish gilt vorerst als eingefroren.
  - Weitere Arbeit an realen Event-Cards nur noch bei klaren konkreten Symptomen im echten Feed, nicht als eigener großer Workpack.
- Detailpanel:
  - Das Event-Detailpanel gilt vorerst ebenfalls als eingefroren.
  - Nur noch gezielte Eingriffe bei klaren konkreten Symptomen; letzter Feinschliff war die linke Fluchtung der Meta-Zeilen (Ort sowie Datum/Uhrzeit).
- Filterbereich:
  - Search-/Filter-UI wird vorerst nicht erneut geöffnet; Bereich gilt als bereits gefreezt.
- Globales Design-System:
  - Das globale Design-System in `css/style.css` gilt als im Wesentlichen konsolidiert.
  - Weitere Arbeit daran ist höchstens Cleanup/Vereinheitlichung letzter Direktwerte, aber kein eigener Haupt-Workpack mehr.
- Info-/Content-Seiten hinter dem Info-Button:
  - Nächster großer sichtbarer UI-Bereich nach Feed/Detailpanel ist die gesamte Seitenfamilie hinter dem Info-Button.
  - Zielbild: nicht „Website neben der App“, sondern sekundäre App-Screens derselben Produktfamilie.
  - Gleiche Header-/Top-Bar-DNA über alle Seiten ja; denselben Feed-Header 1:1 über alle Unterseiten nein.
  - Für Content-Seiten gilt eine reduzierte Header-/Top-Chrome-Variante mit derselben App-DNA, aber screen-passender Aktionslogik.
- `/info/` Hub:
  - `/info/` ist der zentrale Hub des Bereichs hinter dem Info-Button und soll Erklärung + Veranstalter-CTA + Navigation bündeln.
  - `/fuer-veranstalter/` wird nicht wieder eingeführt; Preis-/Tarifdiskussion gehört nicht in den Hub.
- Release / Branch workflow:
  - `staging` ist ab jetzt der verbindliche Arbeits- und Test-Branch.
  - `main` ist ab jetzt der verbindliche Release-/Live-Branch.
  - Routinemäßige Änderungen starten nicht mehr auf `main`, sondern immer zuerst auf `staging`.
  - Nach erfolgreichem Test auf `https://staging.bocholt-erleben.de` wird `staging` per PR/Merge nach `main` übernommen und erst dann `main` deployed.
  - Direkte Änderungen auf `main` sind nur noch für echte Live-Hotfixes erlaubt und müssen danach zurück nach `staging` synchronisiert werden.
- Staging infrastructure:
  - Die Projektinfrastruktur umfasst jetzt verbindlich eine funktionierende Staging-Umgebung unter `https://staging.bocholt-erleben.de`.
  - Die Staging-Subdomain ist per Wildcard-SSL abgesichert und gehört zum kanonischen Betriebsmodell des Projekts.
  - Der Deploy-Workflow muss den Remote-Ordner `staging/` bei Live-Deploys explizit schützen und darf ihn nicht mehr durch `--delete` entfernen.
  - Staging ist kein temporärer Hilfspfad mehr, sondern fester Bestandteil des Release-Prozesses.
- CSS architecture / asset entrypoint:
  - Der öffentliche CSS-Einstiegspunkt in HTML ist ausschließlich `/css/style.css?v=...`.
  - `css/style.css` ist nur noch der kanonische Aggregator und lädt in fester Reihenfolge: `base.css`, `pages.css`, `components.css`, `home.css`, `overlays.css`.
  - HTML-Dateien dürfen die Teildateien `base.css`, `pages.css`, `components.css`, `home.css` und `overlays.css` nie direkt laden.
  - `css/legacy.css` ist deprecated, nur noch Übergangs-/Stub-Datei und darf nicht wieder in `style.css` eingebunden oder als aktiver Owner reaktiviert werden.
  - Verbindliche CSS-Besitzverteilung:
    - `base.css` = globale Foundation, Tokens, globale Header-/Footer-/Focus-/Icon-Basis
    - `pages.css` = Content-/statische Seiten
    - `components.css` = wiederverwendbare UI-Komponenten
    - `home.css` = Home-/Hero-/Feed-/Search-Row-Layout inkl. responsiver Home-Regeln
    - `overlays.css` = Detailpanel, Filter-Sheet, Modals, Overlay-Locks und overlaynahe Zustände
  - Neue CSS-Arbeit erfolgt ab jetzt im zuständigen Owner-File; Cross-File-Patches nur bei nachgewiesener Root Cause.

CURRENT SPRINT (TASK 1: DETAILPANEL UI STABILIZATION) — STATUS:
- Detailpanel bleibt Enterprise-Baseline und ist vorerst eingefroren; keine strukturelle Neugestaltung.
- Feed-/Filter-/Event-Card-Hauptflächen bleiben vorerst eingefroren.
- Status-/Feedback-Cluster wurde bearbeitet und ist aktuell auf brauchbarem Stand; Rand-/Übergangs-Polish bewusst vertagt.
- Aktiver sichtbarer UI-Fokus wurde in dieser Session auf die Info-/Content-Seiten hinter dem Info-Button verschoben.
- Globales Design-System gilt nicht mehr als offener Haupt-Workpack.
- Die Staging-/Release-Schiene wurde technisch aufgebaut und erfolgreich validiert.
- Neue UI-/CSS-/Strukturarbeit soll ab jetzt zuerst auf `staging` laufen und dort getestet werden, bevor etwas nach `main` geht.

REMAINING GAPS (NEXT WORKPACKS, UI/UX + FUNCTIONAL):
1) Info-/Hub-Bereich nur noch gegen echten UI-Bedarf weiterführen:
   - keine alten Hauptflächen wieder öffnen
   - nur noch gezielte Restarbeiten, falls in der realen Seitenfamilie noch klare Ausreißer sichtbar werden
2) Status-/Transition-Polish bleibt bewusst on hold:
   - nicht automatisch wieder aufnehmen
3) Globales DS-Cleanup nur bei echtem Bedarf:
   - kein eigener Workpack, nur opportunistisch bei späteren Patches
4) Neue größere Umbauten:
   - zuerst auf `staging` entwickeln und testen
   - erst nach validiertem Staging-Stand nach `main` übernehmen

NEXT CHAT PROMPT (start here):
„ZIP-first: Bitte zuerst MASTER.md und ENGINEERING.md lesen. Danach den aktuellen Freeze-/Scope-Stand strikt respektieren: Feed, Filter, Event-Cards und Detailpanel sind vorerst eingefroren; der Workpack ‚State Transition & Hierarchy Polish‘ bleibt on hold; das globale Design-System gilt als im Wesentlichen konsolidiert. Zusätzlich gilt ab jetzt verbindlich der neue Release-Workflow: normale Änderungen immer zuerst auf `staging`, Test auf `https://staging.bocholt-erleben.de`, danach PR/Merge `staging` → `main`, dann erst Live-Deploy. Die CSS-Architektur gilt als strukturell abgeschlossen und ist nicht erneut aufzubrechen: öffentlicher CSS-Einstiegspunkt nur `/css/style.css?v=...`; `style.css` lädt nur `base.css`, `pages.css`, `components.css`, `home.css`, `overlays.css`; Teildateien werden nie direkt in HTML eingebunden; `legacy.css` bleibt deprecated. Öffne keine eingefrorenen UI-Hauptflächen ohne klaren konkreten Defekt und arbeite neue CSS-Patches nur im zuständigen Owner-File auf Basis nachgewiesener Root Cause.“

LAST UPDATE:

2026-03-18
<!-- === END REPLACEMENT BLOCK: SESSION STATE + DECISIONS LOG (Session Close 2026-02-27) === -->
# END OF FILE
