# ENGINEERING CONSTITUTION — BOCHOLT ERLEBEN
# TECHNICAL RULES — MANDATORY FOR CHATGPT

This document defines mandatory engineering safety and architecture rules.

These rules must never be violated.

MASTER.md defines WHAT to build.
ENGINEERING.md defines HOW to build safely.

If conflict occurs:

ENGINEERING.md safety rules take precedence.

---

# CORE PRINCIPLES

Primary objective:

Maintain production stability at all times.

Avoid regressions.

Avoid uncontrolled changes.

Every change must be:

targeted
minimal
safe

---

# CHANGE CONTROL RULE

Never rewrite large code areas unnecessarily.

Always modify smallest possible scope.

Preserve existing behavior unless explicitly required.

---

# NO ASSUMPTION RULE

Never guess.

Never invent missing code.

If required information is missing, request clarification.

---

# OVERLAY ARCHITECTURE RULE

All overlays must exist under dedicated overlay root under body.

Never place overlays inside:

sticky elements
transformed elements
filtered elements

This prevents rendering and z-index defects.

---

# SERVICE WORKER RULE

Service worker is critical infrastructure.

Never modify caching logic unless explicitly required.

Never break versioning logic.

---

# DEPLOYMENT RULE

Deployment pipeline must remain deterministic and stable.

Never introduce asset reference instability.

Never break cache-busting.

---

# REGRESSION PREVENTION RULE

Never break existing working features.

If change affects working behavior, explicitly verify impact.

---

# UI MODIFICATION RULE

UI polish changes must prefer CSS-only modifications.

Avoid structural DOM changes unless required.

---

# DEBUG PRINCIPLE

When fixing bugs:

Identify root cause first.

Do not apply blind fixes.

Do not stack speculative fixes.

---

# SESSION OPERATION RULE

At session start:

Read MASTER.md
Read ENGINEERING.md

Follow MASTER.md priorities.

Respect ENGINEERING.md safety constraints.

At session close:

Update MASTER.md when requested.

---

# END OF FILE
