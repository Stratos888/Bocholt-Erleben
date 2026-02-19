# ENGINEERING CONSTITUTION — BOCHOLT ERLEBEN
# ABSOLUTE ENGINEERING RULES — CHATGPT MUST FOLLOW

Defines HOW to safely modify the project.

MASTER.md defines WHAT to build.

ENGINEERING.md defines HOW to build.

These rules are mandatory.

---

# CORE PRINCIPLE

Primary objective:

Maintain production stability.

Never introduce regression.

Every change must be:

minimal
controlled
safe

---

# OPERATING MODE

Project operates in:

CONSOLIDATION MODE

Meaning:

Latest project state is complete truth.

Never assume missing code.

Never invent structure.

Work only with actual visible code.

---

# ONE FILE RULE

Work on ONLY ONE FILE per change.

Never modify multiple files simultaneously unless explicitly required.

---

# DIFF RULE

Never output full files unless explicitly required.

Always provide:

Clear instruction:

Replace block from X to Y

and provide replacement block separately.

---

# MARKER RULE

Every inserted or replaced block MUST include markers:

BEGIN marker:

Comment explaining purpose and scope

END marker:

Comment explaining end of block

Markers prevent corruption.

---

# 100 PERCENT RULE

When fixing or modifying:

All required changes must be included.

Never provide partial fixes.

Never rely on implicit assumptions.

---

# NO GUESS RULE

Never guess.

Never hallucinate missing code.

If information missing:

Request clarification.

---

# ROOT CAUSE RULE

Never apply speculative fixes.

Identify root cause first.

Apply minimal correction.

---

# UI MODIFICATION RULE

UI polish must be CSS-only whenever possible.

Avoid structural DOM changes.

Avoid JS changes unless necessary.

---

# OVERLAY RULE

All overlays must exist under overlay root under body.

Never inside transformed or sticky containers.

---

# SERVICE WORKER RULE

Never break caching logic.

Never break versioning logic.

Never introduce asset instability.

---

# DEPLOYMENT RULE

Deployment must remain deterministic.

Never break asset references.

Fail fast if asset inconsistency detected.

---

# REGRESSION RULE

Never break existing working functionality.

Preserve behavior.

---

# SESSION START PROTOCOL

At session start ChatGPT MUST:

Read MASTER.md

Read ENGINEERING.md

Follow MASTER.md priorities

Follow ENGINEERING.md safety rules

---

# SESSION CLOSE PROTOCOL

When requested:

Update MASTER.md

Preserve project continuity

Never lose decisions

---

# END OF FILE
