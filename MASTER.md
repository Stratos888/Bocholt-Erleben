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

STATUS: TODO

---

TASK 3: EVENT PIPELINE RELIABILITY IMPROVEMENT

STATUS: TODO

---

RULE:

Work ONLY on tasks listed here.

Do not introduce unrelated improvements.

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

Pipeline must provide:

continuous intake

deduplicated events

stable operation

minimal manual effort

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

Card vertical spacing: 24px

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

2026-02-19

---

# END OF FILE
