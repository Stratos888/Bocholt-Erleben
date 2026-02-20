# MASTER CONTROL FILE — BOCHOLT ERLEBEN
# SINGLE SOURCE OF TRUTH FOR PROJECT STATE
# CHATGPT MUST FOLLOW THIS FILE STRICTLY

---

# SESSION INSTRUCTIONS FOR CHATGPT

You are operating a persistent production software project.

This file defines:

- project phase
- sprint scope
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

TASK 1: DETAILPANEL UI STABILIZATION

STATUS: IN PROGRESS

DEFINITION OF DONE:

Visual hierarchy clear

Spacing follows baseline scale

No visual clutter

Max two primary actions

No layout breaks

No visual trust-breaking elements

Professional appearance achieved

After completion:

Move task to COMPLETED AREAS

Add decision entry if structural decisions were made

Freeze component

---

TASK 2: EVENT CARD HIERARCHY STABILIZATION

STATUS: COMPLETED

---

TASK 3: EVENT PIPELINE RELIABILITY IMPROVEMENT

STATUS: TODO

---

RULE:

Work ONLY on tasks listed here.

Do not introduce unrelated improvements.
/* === BEGIN INSERT: EXECUTION PRIORITY CONTROL (Golden Screen Primary, Pipeline Secondary) === */

# EXECUTION PRIORITY CONTROL

This section defines execution priority between UI Golden Screen and Discovery Pipeline.

Both systems are allowed to evolve in parallel.

However, priority order is strictly defined.

---

## PRIMARY OBJECTIVE — GOLDEN SCREEN FREEZE

Golden Screen Freeze is the primary execution objective.

Reason:

Golden Screen becomes visual and UX baseline for entire product.

Without Golden Screen Freeze, product lacks enterprise-grade visual foundation.

Rule:

Golden Screen tasks must be prioritized.

Golden Screen Freeze must be achieved as early as possible.

Golden Screen defines permanent UI reference.

---

## SECONDARY OBJECTIVE — DISCOVERY PIPELINE IMPROVEMENT

Discovery Pipeline improvement is allowed in parallel.

However:

Golden Screen Freeze has priority.

Pipeline work must not delay Golden Screen Freeze.

Pipeline becomes primary focus only AFTER Golden Screen Freeze is achieved.

---

## EXECUTION RULE

When session begins:

If Golden Screen is not frozen:

Golden Screen tasks have priority.

Pipeline tasks may be executed in separate sessions.

But Golden Screen Freeze remains primary milestone.

---

## FREEZE TRANSITION RULE

After Golden Screen Freeze is achieved:

Execution priority shifts.

Pipeline improvement becomes primary objective.

Golden Screen becomes frozen infrastructure.

No visual changes allowed.

Only bug fixes allowed.

/* === END INSERT: EXECUTION PRIORITY CONTROL (Golden Screen Primary, Pipeline Secondary) === */
---

# FROZEN AREAS

These systems are stable infrastructure.

Do not modify unless explicitly required.

HEADER LAYOUT

ROUTING SYSTEM

SERVICE WORKER

OVERLAY ROOT SYSTEM

BUILD PIPELINE

DEPLOY PIPELINE

PWA CORE

---

# UI BASELINE

Mandatory visual rules:

Spacing scale:

4
8
12
16
24
32

Corner radius:

Cards: 12px
Buttons: 10px

Interaction rules:

Max primary actions per screen: 2

Avoid unnecessary dividers

Visual goal:

calm
clean
professional
trustworthy

Avoid:

visual noise
tight layouts
aggressive styling

---

# CONTENT PIPELINE REQUIREMENTS

/* === BEGIN INSERT: DISCOVERY PIPELINE ENTERPRISE TARGET STATE === */

# DISCOVERY PIPELINE — ENTERPRISE TARGET STATE

This section defines the enterprise-grade target state for the automated event discovery system.

This system is separate from the UI and must be treated as independent production infrastructure.

Goal:

Fully automated, reliable, high-quality event intake.

Manual event search must not be required.

---

## DISCOVERY PIPELINE RESPONSIBILITY

Discovery Pipeline is responsible for:

Source fetching

Event candidate extraction

Quality classification

Deduplication

Inbox population

Providing high-quality review candidates

It is NOT responsible for:

UI rendering

Final publishing

User interaction

---

## DISCOVERY PIPELINE DEFINITION OF DONE

Pipeline is considered enterprise-grade when ALL criteria are fulfilled:

---

### INTAKE VOLUME

Pipeline must consistently produce:

Minimum:

20 valid review candidates per run

Target:

30–60 valid review candidates per run

Maximum candidate volume is not limited.

---

### QUALITY

At least:

80% of review candidates must be real events

Reject rate caused by parsing errors must be minimal

False positives from:

Impressum

Datenschutz

Blog publish dates

must be eliminated

---

### AUTOMATION

Pipeline must operate:

fully automatically

without manual triggering

without manual correction

Sources must be fetched automatically.

---

### RELIABILITY

Pipeline must:

never silently fail

log all candidates

log classification reason

log source health

Failures must be observable.

---

### ANALYSIS CAPABILITY

System must provide full visibility of:

all candidates

classification status

classification reason

deduplication result

This is mandatory.

Discovery_Candidates tab fulfills this requirement.

---

## DISCOVERY PIPELINE ARCHITECTURE STATUS

Current status:

ACTIVE DEVELOPMENT

Not frozen.

This system is allowed to evolve until enterprise target state is achieved.

After Definition of Done is fulfilled:

System becomes FROZEN INFRASTRUCTURE.

Further changes must be treated as enterprise-level architectural changes.

---

## DISCOVERY PIPELINE CURRENT PRIMARY OBJECTIVE

Current focus:

Improve extraction accuracy.

Priority order:

1 Improve HTML event extraction accuracy

2 Improve date extraction accuracy

3 Eliminate publish-date false positives

4 Improve source-specific parsers

5 Increase valid review candidate volume

---

## DISCOVERY PIPELINE SUCCESS CONDITION

Discovery Pipeline is considered COMPLETE when:

Manual event search is no longer required.

Pipeline provides sufficient high-quality events continuously.

Inbox consistently receives valid event candidates.

At that point:

Pipeline becomes frozen enterprise infrastructure.

/* === END INSERT: DISCOVERY PIPELINE ENTERPRISE TARGET STATE === */

---

# COMPLETED AREAS

These must never regress.

PWA infrastructure

Overlay architecture

Deploy pipeline

Event loading system

Base UI system

---

# DECISIONS LOG

Permanent architectural and UX decisions.

ChatGPT MUST append new decisions here when made.

---

2026-02-19

MASTER/ENGINEERING system introduced

Purpose:

Prevent context loss

Prevent regression

Enable enterprise-grade evolution

Status:

ACTIVE

---

2026-02-20

Golden Screen — Events Feed Enterprise Baseline achieved

Status:

GOLDEN SCREEN FREEZE READY

All Golden Screen Definition of Done criteria fulfilled.

Implementation decisions frozen:

---

EVENT CARD BASELINE

Card padding:

16px

Card radius:

12px

Card vertical spacing:

16px (token spacing)

Card border:

rgba(31,41,51,0.055)

Card shadow:

0 1px 1px rgba(0,0,0,0.035)
0 10px 26px rgba(0,0,0,0.045)

Interaction:

Press scale feedback active

Focus-visible ring active

---

TYPOGRAPHY BASELINE

Title:

18px
600 weight

Meta:

14px
400 weight

Hierarchy validated.

---

SECTION HEADER BASELINE

Section headers produce no independent vertical spacing.

Spacing controlled exclusively by feed container gap.

Margin:

0

Typography:

uppercase
letter-spacing 0.11em

---

HEADER BASELINE

Header blur:

12px

Header surface:

color-mix(in srgb, var(--color-primary-muted) 78%, white)

Header shadow:

0 1px 0 rgba(255,255,255,0.78) inset
0 6px 16px rgba(31,41,51,0.045)

Header considered enterprise-grade.

---

RHYTHM VALIDATION

Console measurement confirmed:

Card → Card = 16px  
Card → Label = 16px  
Label → Card = 16px  

Vertical rhythm considered correct and frozen.

---

FREEZE STATUS

Golden Screen baseline must not be visually modified further.

Only bug fixes allowed.

Any visual changes require explicit unfreeze decision.

---

NEXT UI PRIORITY

Continue CURRENT SPRINT Task:

DETAILPANEL UI STABILIZATION

Golden Screen considered reference baseline.

---

Decision authority:

Permanent UX and surface baseline established.

---
---

# NEXT PRIORITIES (DO NOT START YET)

/* === BEGIN INSERT: GOLDEN SCREEN BASELINE + NEXT PRIORITIES (Enterprise Freeze Definition) === */

# GOLDEN SCREEN BASELINE — ENTERPRISE FREEZE DEFINITION

This section defines the mandatory enterprise-level baseline for the Events Feed first viewport.

This Golden Screen becomes the permanent visual and UX reference for the entire app.

After completion, it must be frozen.

---

## GOLDEN SCREEN SCOPE

Golden Screen is defined as:

First visible viewport of Events Feed without scrolling.

Includes:

Event Feed container  
First visible Event Cards  
Card spacing rhythm  
Typography hierarchy  
Surface system  

Does NOT include:

Detailpanel  
Other pages  

---

## GOLDEN SCREEN DEFINITION OF DONE

All criteria MUST be fulfilled.

### SPACING

Only token spacing allowed:

4  
8  
12  
16  
24  
32  

Card internal padding: 16px

Card vertical spacing: 16px

No non-token spacing values allowed.

---

### TYPOGRAPHY

Title:

18–20px  
600 weight  
Line height 1.2–1.3  

Meta:

14–15px  
400 weight  
Line height 1.4–1.5  

Clear visual hierarchy required.

---

### SURFACE SYSTEM

Page background, card surface, and borders must use only defined tokens.

No hardcoded colors allowed.

Card radius must use radius token only.

---

### VISUAL HIERARCHY

User eye must naturally focus on title of first visible card.

No visual clutter allowed.

No unnecessary dividers allowed.

Whitespace must define structure.

---

### RHYTHM

Vertical rhythm must be perfectly consistent.

Card  
24px  
Card  
24px  
Card  

No deviation allowed.

---

### INTERACTION REALISM

Card must provide immediate press feedback.

Card must feel like native app component.

No delayed feedback allowed.

---

### PERCEPTION TEST

Golden Screen must pass this test:

A first-time user viewing for 3 seconds perceives:

professional quality  
clear structure  
high trust  

User must perceive this as app-level product, not generic webpage.

---

## FREEZE RULE

After Golden Screen Definition of Done is achieved:

Golden Screen must be frozen.

No further visual modifications allowed.

Only bug fixes allowed.

Golden Screen becomes baseline reference for entire product.

---

# NEXT PRIORITIES (DO NOT START YET)

Organizer onboarding funnel

Monetization system

Organizer submission system

Account system

/* === END INSERT: GOLDEN SCREEN BASELINE + NEXT PRIORITIES (Enterprise Freeze Definition) === */

# SESSION CLOSE PROTOCOL

When session closes ChatGPT MUST:

Update CURRENT SPRINT statuses

Move completed tasks to COMPLETED AREAS

Append new DECISIONS if needed

Update SESSION STATE

---

# SESSION STATE

OVERALL STATUS:

UI: IN PROGRESS

PIPELINE: IN PROGRESS

LAST UPDATE:

2026-02-20

---

# END OF FILE
