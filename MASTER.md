# MASTER CONTROL FILE — BOCHOLT ERLEBEN
# THIS IS THE SINGLE SOURCE OF TRUTH FOR PROJECT STATE
# CHATGPT MUST FOLLOW THIS FILE STRICTLY

---

# SESSION INSTRUCTIONS FOR CHATGPT

You are operating on a persistent software project.

This file defines:

- current phase
- active sprint
- frozen areas
- product priorities
- allowed focus

You MUST:

1. Read this file completely at session start
2. Follow CURRENT SPRINT only
3. NEVER invent new priorities outside this file
4. NEVER modify frozen areas unless explicitly required
5. Update this file at session end when requested
6. Preserve all completed progress
7. Never regress completed areas

This file has priority over all other guidance except ENGINEERING.md safety rules.

---

# PROJECT OBJECTIVE

Build and operate a production-grade event discovery platform for Bocholt.

Primary business goal:

Enable reliable event discovery and monetizable organizer onboarding.

Platform must reach enterprise-grade stability, UX quality, and controlled evolution.

---

# CURRENT PHASE

PHASE: UI STABILIZATION AND CONTENT PIPELINE RELIABILITY

PHASE GOAL:

Achieve production-trustworthy UI perception and fully reliable automated event intake.

Definition of phase completion:

- UI appears modern, clean, stable, and professional
- No major visual trust-breaking issues remain
- Event pipeline continuously supplies usable events
- No manual daily event searching required

---

# CURRENT SPRINT

ACTIVE TASKS:

TASK 1:
Detailpanel UI stabilization
STATUS: IN PROGRESS

Objectives:

- clean hierarchy
- correct spacing rhythm
- remove visual noise
- achieve calm, professional appearance

TASK 2:
Event card visual hierarchy stabilization
STATUS: TODO

TASK 3:
Event pipeline deduplication and reliability improvement
STATUS: TODO

RULE:

ChatGPT must work on these tasks only.

Do not introduce unrelated improvements.

---

# FROZEN AREAS

These areas are considered stable and must not be modified unless explicitly required:

- Header layout and structure
- Routing system
- Service worker architecture
- Overlay root system
- Build and deploy pipeline

These systems are critical infrastructure.

Avoid touching them unless fixing a confirmed defect.

---

# UI BASELINE

These are mandatory UI rules:

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

Avoid unnecessary dividers.

Visual goals:

calm
clean
modern
professional
trustworthy

Avoid:

visual clutter
tight layouts
aggressive styling

---

# CONTENT PIPELINE REQUIREMENTS

Event pipeline must achieve:

continuous intake
minimal manual effort
deduplication reliability
stable operation

Manual daily event discovery must not be required.

---

# COMPLETED AREAS

Core PWA infrastructure
Overlay system
Deploy pipeline
Basic event system
Basic UI system

These must not regress.

---

# NEXT PRIORITIES (DO NOT START YET)

Organizer onboarding funnel improvement

Monetization integration

Organizer self-service submission system

---

# SESSION STATE

This section must be updated by ChatGPT when session closes.

Current overall stability:

UI: IN PROGRESS
Pipeline: IN PROGRESS

Last update:

2026-02-19

---

# END OF FILE
