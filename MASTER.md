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

Organizer onboarding funnel

Monetization system

Organizer submission system

Account system

---

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
